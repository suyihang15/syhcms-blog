<?php
declare(strict_types=1);

/**
 * 友链 RSS：抓取各站最近一篇文章，落盘缓存，页面渲染不等待外站。
 *
 * 设计要点：
 * - 缓存 6 小时；过期后先返回旧数据，等响应结束再后台刷新。
 * - 首次（无缓存）才同步抓取，且总预算 3 秒。
 * - 抓取失败保留旧标题，只记录时间，避免频繁重试。
 * - 只允许 http/https 公网地址，拒绝内网与回环，降低 SSRF 风险。
 */

const FRIEND_FEED_TTL = 21600;       // 缓存有效期 6 小时
const FRIEND_FEED_MAX_BYTES = 524288; // 单个 feed 最多读 512KB

function friend_feed_cache_file(): string
{
    return rtrim((string) SYH_CONFIG['cache'], '/') . '/friend-feeds.json';
}

function friend_feed_cache_read(): array
{
    $file = friend_feed_cache_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function friend_feed_cache_write(array $entries): void
{
    $file = friend_feed_cache_file();
    $old  = friend_feed_cache_read();

    // 抓取失败（title 为空）时保留旧标题，只更新时间戳
    foreach ($entries as $url => $item) {
        if (($item['title'] ?? '') === '' && ($old[$url]['title'] ?? '') !== '') {
            $entries[$url] = $old[$url];
            $entries[$url]['fetched'] = time();
        }
    }

    $data = array_merge($old, $entries);

    // 只保留近 30 天有更新的记录，避免缓存文件无限增长
    $cut = time() - 30 * 86400;
    foreach ($data as $url => $item) {
        if ((int) ($item['fetched'] ?? 0) < $cut) {
            unset($data[$url]);
        }
    }

    $tmp = $file . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }
    if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
        @rename($tmp, $file);
        @chmod($file, 0664);
    }
}

/**
 * 返回 ["feed 地址" => ["title","url","timestamp","fetched"]]。
 * 只处理传入的地址，页面按需取用。
 */
function friend_feed_map(array $urls): array
{
    $cache  = friend_feed_cache_read();
    $now    = time();
    $result = [];
    $stale  = [];

    foreach ($urls as $url) {
        $url = trim((string) $url);
        if ($url === '') {
            continue;
        }
        if (isset($cache[$url]) && is_array($cache[$url])) {
            $result[$url] = $cache[$url];
            if ((int) ($cache[$url]['fetched'] ?? 0) + FRIEND_FEED_TTL < $now) {
                $stale[] = $url;
            }
        } else {
            $stale[] = $url;
        }
    }

    if ($stale === []) {
        return $result;
    }

    // 一个都没有缓存时才同步抓取，其余情况先出页面再后台刷新
    if ($result === []) {
        $fresh = friend_feed_fetch($stale, 3);
        if ($fresh !== []) {
            friend_feed_cache_write($fresh);
            $result = array_merge($result, $fresh);
        }
        return $result;
    }

    friend_feed_refresh_after_response($stale);
    return $result;
}

function friend_feed_refresh_after_response(array $urls): void
{
    static $queued = false;
    if ($queued) {
        return;
    }
    $queued = true;

    register_shutdown_function(function () use ($urls): void {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request(); // 先把页面发给访客，再抓取
        }
        ignore_user_abort(true);
        @set_time_limit(20);
        $fresh = friend_feed_fetch($urls, 5);
        if ($fresh !== []) {
            friend_feed_cache_write($fresh);
        }
    });
}

/**
 * 并发抓取多个 feed，超时即中断，返回解析结果。
 */
function friend_feed_fetch(array $urls, int $budget = 3): array
{
    if (!function_exists('curl_multi_init')) {
        return [];
    }

    $mh      = curl_multi_init();
    $handles = [];
    $buffers = [];

    foreach ($urls as $url) {
        if (!friend_feed_url_allowed($url)) {
            continue;
        }
        $buffers[$url] = '';
        $ch = curl_init($url);
        if ($ch === false) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; syh-friend-feed/1.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION  => static function ($ch, string $chunk) use (&$buffers, $url): int {
                if (strlen($buffers[$url]) >= FRIEND_FEED_MAX_BYTES) {
                    return 0; // 超出上限直接中断
                }
                $buffers[$url] .= $chunk;
                return strlen($chunk);
            },
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$url] = $ch;
    }

    $start = microtime(true);
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh, 0.2);
        }
    } while ($active && $status === CURLM_OK && (microtime(true) - $start) < $budget);

    $now = time();
    $out = [];
    foreach ($handles as $url => $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $body = $buffers[$url] ?? '';
        $item = ($code >= 200 && $code < 300 && $body !== '') ? friend_feed_parse($body, $url) : null;
        if ($item !== null) {
            $item['fetched'] = $now;
            $out[$url]       = $item;
        } else {
            $out[$url] = ['title' => '', 'url' => '', 'timestamp' => 0, 'fetched' => $now];
        }
        curl_multi_remove_handle($mh, $ch);
    }
    curl_multi_close($mh);

    return $out;
}

/**
 * 解析 RSS 2.0 / Atom，取第一条文章。解析失败时用正则兜底。
 */
function friend_feed_parse(string $xml, string $baseUrl): ?array
{
    if (trim($xml) === '') {
        return null;
    }

    $prev = libxml_use_internal_errors(true);
    $doc  = new DOMDocument();
    $ok   = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$ok) {
        return friend_feed_parse_regex($xml, $baseUrl);
    }

    $xpath = new DOMXPath($doc);
    $node  = $xpath->query('//*[local-name()="item"]')->item(0);
    $atom  = false;
    if ($node === null) {
        $node = $xpath->query('//*[local-name()="entry"]')->item(0);
        $atom = true;
    }
    if ($node === null) {
        return null;
    }

    $title = '';
    foreach ($xpath->query('./*[local-name()="title"]', $node) as $t) {
        $title = friend_feed_clean($t->textContent);
        break;
    }
    if ($title === '') {
        return null;
    }

    $link = '';
    if ($atom) {
        foreach ($xpath->query('./*[local-name()="link"]', $node) as $l) {
            $rel = strtolower((string) $l->getAttribute('rel'));
            if ($rel === '' || $rel === 'alternate') {
                $link = (string) $l->getAttribute('href');
                break;
            }
        }
        if ($link === '') {
            foreach ($xpath->query('./*[local-name()="id"]', $node) as $i) {
                $link = trim($i->textContent);
                break;
            }
        }
    } else {
        foreach ($xpath->query('./*[local-name()="link"]', $node) as $l) {
            $link = trim($l->textContent);
            break;
        }
        if ($link === '') {
            foreach ($xpath->query('./*[local-name()="guid"]', $node) as $g) {
                $link = trim($g->textContent);
                break;
            }
        }
    }

    $timestamp = 0;
    $dateTags  = $atom ? ['published', 'updated'] : ['pubDate', 'date', 'published'];
    foreach ($dateTags as $tag) {
        foreach ($xpath->query('./*[local-name()="' . $tag . '"]', $node) as $d) {
            $ts = strtotime(trim($d->textContent));
            if ($ts !== false && $ts > 0) {
                $timestamp = $ts;
                break 2;
            }
        }
    }

    return [
        'title'     => mb_substr($title, 0, 120),
        'url'       => friend_feed_abs_url($link, $baseUrl),
        'timestamp' => $timestamp,
    ];
}

function friend_feed_parse_regex(string $xml, string $baseUrl): ?array
{
    if (!preg_match('#<(item|entry)\b[^>]*>(.*?)</\1>#is', $xml, $m)) {
        return null;
    }
    $block = $m[2];
    if (!preg_match('#<title\b[^>]*>(.*?)</title>#is', $block, $t)) {
        return null;
    }
    $title = friend_feed_clean($t[1]);
    if ($title === '') {
        return null;
    }

    $link = '';
    if (preg_match('#<link\b[^>]*href=["\']([^"\']+)["\']#i', $block, $l)) {
        $link = $l[1];
    } elseif (preg_match('#<link\b[^>]*>(.*?)</link>#is', $block, $l)) {
        $link = friend_feed_clean($l[1]);
    }

    $timestamp = 0;
    if (preg_match('#<(pubDate|published|updated)\b[^>]*>(.*?)</\1>#is', $block, $d)) {
        $ts = strtotime(trim($d[2]));
        if ($ts !== false && $ts > 0) {
            $timestamp = $ts;
        }
    }

    return [
        'title'     => mb_substr($title, 0, 120),
        'url'       => friend_feed_abs_url($link, $baseUrl),
        'timestamp' => $timestamp,
    ];
}

function friend_feed_clean(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

function friend_feed_abs_url(string $link, string $baseUrl): string
{
    $link = trim($link);
    if ($link === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }
    $parts = parse_url($baseUrl);
    if (!$parts || empty($parts['host'])) {
        return '';
    }
    $root = strtolower((string) ($parts['scheme'] ?? 'https')) . '://' . $parts['host'];
    if (str_starts_with($link, '//')) {
        return strtolower((string) ($parts['scheme'] ?? 'https')) . ':' . $link;
    }
    return $root . '/' . ltrim($link, '/');
}

/**
 * 只允许公网 http/https，拒绝内网、回环、保留地址。
 */
function friend_feed_url_allowed(string $url): bool
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) {
        return false;
    }
    if (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return false;
    }
    $host = strtolower((string) $parts['host']);
    if ($host === 'localhost' || str_ends_with($host, '.local')) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    $ips = gethostbynamel($host);
    if (!$ips) {
        return false;
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
    }
    return true;
}

/**
 * 时间展示：今天 / 昨天 / n 天前 / 月日。
 */
function friend_feed_age_label(int $timestamp): string
{
    if ($timestamp <= 0) {
        return '';
    }
    $today = strtotime(date('Y-m-d'));
    $then  = strtotime(date('Y-m-d', $timestamp));
    $days  = (int) round(($today - $then) / 86400);
    if ($days <= 0) {
        return '今天';
    }
    if ($days === 1) {
        return '昨天';
    }
    if ($days < 7) {
        return $days . ' 天前';
    }
    return date('n月j日', $timestamp);
}

function friend_feed_age_class(int $timestamp): string
{
    if ($timestamp <= 0) {
        return '';
    }
    $days = (int) round((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', $timestamp))) / 86400);
    if ($days <= 0) {
        return ' is-today';
    }
    return $days < 7 ? ' is-week' : '';
}
