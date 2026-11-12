<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function attr(mixed $value): string
{
    return e($value);
}

function esc_url_str(string $url): string
{
    return e($url);
}

function options_all(): array
{
    if (!isset($GLOBALS['__syh_options'])) {
        $rows = DB::all('SELECT name, value FROM options');
        $GLOBALS['__syh_options'] = array_column($rows, 'value', 'name');
    }
    return $GLOBALS['__syh_options'];
}

function opt(string $name, mixed $default = ''): mixed
{
    $all = options_all();
    return array_key_exists($name, $all) ? $all[$name] : $default;
}

function set_option(string $name, mixed $value): void
{
    DB::exec(
        'INSERT INTO options (name, value) VALUES (?, ?) ON CONFLICT(name) DO UPDATE SET value = excluded.value',
        [$name, (string) $value]
    );
    $GLOBALS['__syh_options'][$name] = (string) $value;
}

function setting(string $name, mixed $default = ''): mixed
{
    return opt($name, $default);
}

function site_url(): string
{
    $configured = rtrim(trim((string) setting('site_url', '')), '/');
    if ($configured !== '') {
        return $configured;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host  = (string) ($_SERVER['HTTP_HOST'] ?? '');
    return $host !== '' ? (($https ? 'https' : 'http') . '://' . $host) : 'http://localhost';
}

function abs_url(string $path = '/'): string
{
    return site_url() . '/' . ltrim($path, '/');
}

function url(string $path = '/'): string
{
    return '/' . ltrim($path, '/');
}

function post_url(array $post): string
{
    return url(rawurlencode((string) $post['slug']) . '/');
}

function term_path(array $term, int $guard = 0): string
{
    $prefix = ($term['taxonomy'] ?? 'category') === 'post_tag' ? 'tag' : 'category';
    $path   = rawurlencode((string) $term['slug']);
    $parent = (int) ($term['parent_id'] ?? 0);
    if ($prefix === 'category' && $parent > 0 && $guard < 8) {
        $row = DB::one("SELECT * FROM terms WHERE id = ? AND taxonomy = 'category'", [$parent]);
        if ($row) {
            // 递归结果已带 category/ 前缀，这里只取 slug 链，避免重复前缀。
            $parentPath = preg_replace('#^(?:category|tag)/#', '', term_path($row, $guard + 1));
            $path = $parentPath . '/' . $path;
        }
    }
    return $prefix . '/' . $path;
}

function term_url(array $term): string
{
    return url(term_path($term) . '/');
}

function media_file_url(string $file): string
{
    return media_url_by_file($file);
}

function author_url(array $user): string
{
    return url('author/' . rawurlencode((string) $user['login']) . '/');
}

function media_url_by_file(string $file): string
{
    $segments = array_map('rawurlencode', explode('/', ltrim($file, '/')));
    return url('wp-content/uploads/' . implode('/', $segments));
}

function media(array $row): ?array
{
    return $row ?: null;
}

function media_url(int $id): string
{
    if ($id <= 0) {
        return '';
    }
    $file = DB::val('SELECT file FROM media WHERE id = ?', [$id]);
    return $file ? media_url_by_file((string) $file) : '';
}

function format_date(string $datetime, string $format = 'Y年n月j日'): string
{
    try {
        $date = new DateTimeImmutable($datetime, new DateTimeZone((string) setting('timezone', 'Asia/Shanghai')));
        return $date->format($format);
    } catch (Throwable) {
        return $datetime;
    }
}

function iso_date(string $datetime): string
{
    return format_date($datetime, 'c');
}

function now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone((string) setting('timezone', 'Asia/Shanghai'))))->format('Y-m-d H:i:s');
}

function reading_time(string $html): int
{
    $chars = mb_strlen(trim(strip_tags(content_html($html))), 'UTF-8');
    return max(1, (int) ceil($chars / 300));
}

function word_count(string $html): int
{
    return mb_strlen(trim(strip_tags(content_html($html))), 'UTF-8');
}

function excerpt_text(string $html, int $length = 110): string
{
    $text = content_html($html);
    $text = strip_tags($text);
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));
    $text = trim(str_replace(['&nbsp;', '<!--more-->'], ' ', $text));
    if (mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '…';
    }
    return $text;
}

function post_excerpt(array $post, int $length = 110): string
{
    if (trim((string) $post['excerpt']) !== '') {
        return excerpt_text((string) $post['excerpt'], $length);
    }
    return excerpt_text((string) $post['content'], $length);
}

/**
 * 把 WordPress 遗留短代码转成普通 HTML。
 */
function content_html(string $html): string
{
    $html = preg_replace('/\[ai_chat[^\]]*\](?:.*?\[\/ai_chat\])?/is', '', $html) ?? $html;
    $html = preg_replace('/\[\/?scriptblock[^\]]*\]/i', '', $html) ?? $html;

    $html = preg_replace_callback('/\[caption([^\]]*)\](.*?)\[\/caption\]/is', static function (array $m): string {
        $attrs = $m[1];
        $align = preg_match('/align=["\']([^"\']+)["\']/', $attrs, $a) ? $a[1] : 'alignnone';
        $width = preg_match('/width=["\'](\d+)["\']/', $attrs, $w) ? (int) $w[1] : 0;
        $style = $width ? ' style="width:' . $width . 'px;max-width:100%"' : '';
        return '<figure class="wp-caption ' . e($align) . '"' . $style . '>' . $m[2]
            . '</figure>';
    }, $html) ?? $html;

    $html = preg_replace_callback('/\[gallery[^\]]*ids=["\']([^"\']+)["\'][^\]]*\]/i', static function (array $m): string {
        $ids = array_filter(array_map('intval', explode(',', $m[1])));
        if (!$ids) {
            return '';
        }
        $out = '<div class="gallery gallery-columns-3">';
        foreach ($ids as $id) {
            $file = DB::val('SELECT file FROM media WHERE id = ?', [$id]);
            if (!$file) {
                continue;
            }
            $out .= '<figure class="gallery-item"><a href="' . e(media_url_by_file((string) $file)) . '">'
                . '<img src="' . e(media_url_by_file((string) $file)) . '" alt="" loading="lazy"></a></figure>';
        }
        return $out . '</div>';
    }, $html) ?? $html;

    $html = preg_replace_callback('/\[embed\](.*?)\[\/embed\]/is', static function (array $m): string {
        $u = trim($m[1]);
        return '<p class="embed-link"><a href="' . e($u) . '" rel="noopener">' . e($u) . '</a></p>';
    }, $html) ?? $html;

    return $html;
}

function content_with_toc(string $html): string
{
    $html     = content_html($html);
    $headings = [];
    $index    = 0;
    $html     = preg_replace_callback('/<h([2-4])([^>]*)>(.*?)<\/h\1\s*>/is', static function (array $m) use (&$headings, &$index): string {
        $text = trim(strip_tags($m[3]));
        if ($text === '') {
            return $m[0];
        }
        $attrs = $m[2];
        if (preg_match('/\sid=(["\'])(.*?)\1/i', $attrs, $im)) {
            $anchor = $im[2];
        } else {
            $anchor = 'heading-' . $index++;
            $attrs .= ' id="' . e($anchor) . '"';
        }
        $headings[] = ['level' => (int) $m[1], 'text' => $text, 'anchor' => $anchor];
        return '<h' . $m[1] . $attrs . '>' . $m[3] . '</h' . $m[1] . '>';
    }, $html) ?? $html;

    $html = preg_replace_callback('/<img([^>]+)>/i', static function (array $m): string {
        if (str_contains($m[0], 'data-lightbox')) {
            return $m[0];
        }
        return '<img data-lightbox="post-image"' . $m[1] . '>';
    }, $html) ?? $html;

    $html = preg_replace_callback('/<pre><code([^>]*)>(.*?)<\/code><\/pre>/is', static function (array $m): string {
        $lang = preg_match('/class="language-([^"]+)"/', $m[1], $lm) ? $lm[1] : '';
        return '<pre class="code-block" data-lang="' . e($lang) . '"><code' . $m[1] . '>' . $m[2] . '</code></pre>';
    }, $html) ?? $html;

    if (count($headings) < 2) {
        return $html;
    }
    $min  = min(array_column($headings, 'level'));
    $toc  = '<div class="toc-container" id="toc-container"><div class="toc-header"><span class="toc-title">文章目录</span>'
        . '<button class="toc-toggle" aria-label="展开/收起目录" aria-expanded="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button></div><nav class="toc-list">';
    foreach ($headings as $h) {
        $indent = $h['level'] - $min;
        $toc .= '<a href="#' . e($h['anchor']) . '" class="toc-item toc-level-' . $h['level'] . '" style="padding-left:' . (12 + $indent * 16) . 'px">' . e($h['text']) . '</a>';
    }
    return $toc . '</nav></div>' . $html;
}

function avatar_url(string $email, int $size = 48): string
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return '';
    }
    if (preg_match('/^(\d+)@qq\.com$/i', $email, $m)) {
        $allowed = [40, 100, 140, 640];
        $pick    = 640;
        foreach ($allowed as $candidate) {
            if ($size <= $candidate) {
                $pick = $candidate;
                break;
            }
        }
        return 'https://q1.qlogo.cn/g?b=qq&nk=' . $m[1] . '&s=' . $pick;
    }
    return 'https://gravatar.loli.net/avatar/' . md5($email) . '?s=' . $size . '&d=mm';
}

function comment_count(int $postId, bool $approvedOnly = true): int
{
    $sql = 'SELECT COUNT(*) FROM comments WHERE post_id = ?' . ($approvedOnly ? " AND status = 'approved'" : '');
    return (int) DB::val($sql, [$postId], 0);
}

function categories_of(int $postId): array
{
    return DB::all(
        'SELECT t.* FROM terms t JOIN post_terms pt ON pt.term_id = t.id WHERE pt.post_id = ? AND t.taxonomy = \'category\' ORDER BY t.name',
        [$postId]
    );
}

function tags_of(int $postId): array
{
    return DB::all(
        'SELECT t.* FROM terms t JOIN post_terms pt ON pt.term_id = t.id WHERE pt.post_id = ? AND t.taxonomy = \'post_tag\' ORDER BY t.name',
        [$postId]
    );
}

function featured_image_html(array $post, string $class = 'post-thumbnail-img'): string
{
    $id = (int) ($post['cover_id'] ?? 0);
    if ($id <= 0) {
        return '';
    }
    $m = DB::one('SELECT * FROM media WHERE id = ?', [$id]);
    if (!$m) {
        return '';
    }
    $w = (int) $m['width'] > 0 ? ' width="' . (int) $m['width'] . '"' : '';
    $h = (int) $m['height'] > 0 ? ' height="' . (int) $m['height'] . '"' : '';
    return '<img src="' . e(media_url_by_file((string) $m['file'])) . '" class="' . e($class) . '" alt="' . e($m['alt'] ?: $post['title']) . '"' . $w . $h . ' loading="lazy" decoding="async">';
}

function social_icon(string $key): string
{
    $icons = [
        'qq'       => '<path d="M21.395 15.035a40 40 0 0 0-.803-2.264l-1.079-2.695c.001-.032.014-.562.014-.836C19.526 4.632 17.351 0 12 0S4.474 4.632 4.474 9.241c0 .274.013.804.014.836l-1.08 2.695a39 39 0 0 0-.802 2.264c-1.021 3.283-.69 4.643-.438 4.673.54.065 2.103-2.472 2.103-2.472 0 1.469.756 3.387 2.394 4.771-.612.188-1.363.479-1.845.835-.434.32-.379.646-.301.778.343.578 5.883.369 7.482.189 1.6.18 7.14.389 7.483-.189.078-.132.132-.458-.301-.778-.483-.356-1.233-.646-1.846-.836 1.637-1.384 2.393-3.302 2.393-4.771 0 0 1.563 2.537 2.103 2.472.251-.03.581-1.39-.438-4.673"/>',
        'wechat'   => '<path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.89c-.135-.01-.27-.027-.407-.03zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982z"/>',
        'bilibili' => '<path d="M17.813 4.653h.854c1.51.054 2.769.578 3.773 1.574 1.004.995 1.524 2.249 1.56 3.76v7.36c-.036 1.51-.556 2.769-1.56 3.773s-2.262 1.524-3.773 1.56H5.333c-1.51-.036-2.769-.556-3.773-1.56S.036 18.858 0 17.347v-7.36c.036-1.511.556-2.765 1.56-3.76 1.004-.996 2.262-1.52 3.773-1.574h.774l-1.174-1.12a1.234 1.234 0 0 1-.373-.906c0-.356.124-.658.373-.907l.027-.027c.267-.249.573-.373.92-.373.347 0 .653.124.92.373L9.653 4.44c.071.071.134.142.187.213h4.267a.836.836 0 0 1 .16-.213l2.853-2.747c.267-.249.573-.373.92-.373.347 0 .662.151.929.4.267.249.391.551.391.907 0 .355-.124.657-.373.906zM5.333 7.24c-.746.018-1.373.276-1.88.773-.506.498-.769 1.13-.786 1.894v7.52c.017.764.28 1.395.786 1.893.507.498 1.134.756 1.88.773h13.334c.746-.017 1.373-.275 1.88-.773.506-.498.769-1.129.786-1.893v-7.52c-.017-.765-.28-1.396-.786-1.894-.507-.497-1.134-.755-1.88-.773zM8 11.107c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c0-.373.129-.689.386-.947.258-.257.574-.386.947-.386zm8 0c.373 0 .684.124.933.373.25.249.383.569.4.96v1.173c-.017.391-.15.711-.4.96-.249.25-.56.374-.933.374s-.684-.125-.933-.374c-.25-.249-.383-.569-.4-.96V12.44c.017-.391.15-.711.4-.96.249-.249.56-.373.933-.373Z"/>',
        'github'   => '<path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>',
    ];
    return isset($icons[$key])
        ? '<svg class="social-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $icons[$key] . '</svg>'
        : '';
}

function social_links_markup(string $context = 'desktop'): string
{
    $links = [];
    if (trim((string) setting('social_qq_qr')) !== '') {
        $links['qq'] = ['label' => 'QQ 二维码', 'caption' => '扫码添加 QQ', 'url' => '', 'image' => setting('social_qq_qr')];
    }
    if (trim((string) setting('social_wechat_qr')) !== '') {
        $links['wechat'] = ['label' => '微信二维码', 'caption' => '扫码添加微信', 'url' => '', 'image' => setting('social_wechat_qr')];
    }
    if (trim((string) setting('social_bilibili')) !== '') {
        $links['bilibili'] = ['label' => 'B 站主页', 'caption' => '', 'url' => setting('social_bilibili'), 'image' => ''];
    }
    if (trim((string) setting('social_github')) !== '') {
        $links['github'] = ['label' => 'GitHub', 'caption' => '', 'url' => setting('social_github'), 'image' => ''];
    }
    if (!$links) {
        return '';
    }
    $out = '<div class="site-social site-social-' . e($context) . '">';
    foreach ($links as $key => $link) {
        $classes = 'social-item social-' . e($key);
        $icon    = social_icon($key);
        if ($link['image'] !== '') {
            $out .= '<button type="button" class="' . $classes . '" data-social="' . e($key) . '" data-image="' . e((string) $link['image'])
                . '" data-caption="' . e($link['caption']) . '" aria-haspopup="dialog" aria-expanded="false" aria-label="' . e($link['label'])
                . '" title="' . e($link['label']) . '">' . $icon . '</button>';
        } else {
            $out .= '<a class="' . $classes . '" href="' . e((string) $link['url']) . '" target="_blank" rel="noopener noreferrer" aria-label="'
                . e($link['label']) . '" title="' . e($link['label']) . '">' . $icon . '</a>';
        }
    }
    return $out . '</div>';
}

function theme_toggle_button(string $extra = ''): string
{
    $class = 'theme-toggle' . ($extra ? ' ' . $extra : '');
    return '<button type="button" class="' . e($class) . '" aria-label="切换深色模式" aria-pressed="false" title="切换深色模式">'
        . '<svg class="theme-icon theme-icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>'
        . '<svg class="theme-icon theme-icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg></button>';
}

function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    return '/' . trim($path, '/') . '/';
}

function is_current_url(string $url): bool
{
    $path = parse_url($url, PHP_URL_PATH) ?: '/';
    return current_path() === '/' . trim(rawurldecode($path), '/') . '/';
}

function menu_items(string $menu = 'primary'): array
{
    $rows = DB::all('SELECT * FROM menu_items WHERE menu = ? ORDER BY sort, id', [$menu]);
    if (!$rows) {
        return [];
    }
    $index    = [];
    $children = [];
    foreach ($rows as $i => $row) {
        $index[(int) $row['id']] = $i;
        $children[$i] = [];
    }
    $roots = [];
    foreach ($rows as $i => $row) {
        $pid = (int) $row['parent_id'];
        if ($pid > 0 && isset($index[$pid]) && $index[$pid] !== $i) {
            $children[$index[$pid]][] = $i;
        } else {
            $roots[] = $i;
        }
    }
    $build = static function (int $i) use (&$build, $rows, $children): array {
        $row = $rows[$i];
        $row['children'] = [];
        foreach ($children[$i] as $child) {
            $row['children'][] = $build($child);
        }
        return $row;
    };
    return array_map($build, $roots);
}

function menu_icon_key(array $item): string
{
    if (($item['kind'] ?? '') !== 'page' || (int) $item['object_id'] <= 0) {
        return '';
    }
    $tpl = DB::val('SELECT template FROM posts WHERE id = ? AND type = \'page\'', [(int) $item['object_id']]);
    return match ($tpl) {
        'page-archive.php'   => 'archive',
        'page-stats.php'     => 'stats',
        'page-guestbook.php' => 'guestbook',
        'page-links.php'     => 'links',
        default              => '',
    };
}

function menu_icon_svg(string $key): string
{
    $icons = [
        'archive'   => '<rect x="3" y="4" width="18" height="4" rx="1.2"/><path d="M5 8v10.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V8"/><path d="M10 12h4"/>',
        'stats'     => '<path d="M4 20v-7"/><path d="M10 20V5"/><path d="M16 20v-4"/><path d="M22 20H2"/>',
        'guestbook' => '<path d="M21 14.5a2.5 2.5 0 0 1-2.5 2.5H9l-5 4V5.5A2.5 2.5 0 0 1 6.5 3h12A2.5 2.5 0 0 1 21 5.5z"/><path d="M8 8.5h8"/><path d="M8 12h5"/>',
        'links'     => '<path d="M10.5 13.5a4.5 4.5 0 0 0 6.36 0l2.5-2.5a4.5 4.5 0 0 0-6.36-6.36l-1.2 1.2"/><path d="M13.5 10.5a4.5 4.5 0 0 0-6.36 0l-2.5 2.5a4.5 4.5 0 0 0 6.36 6.36l1.2-1.2"/>',
    ];
    return isset($icons[$key]) ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[$key] . '</svg>' : '';
}

function render_menu(array $items, int $depth = 0): string
{
    if (!$items) {
        return '';
    }
    $out = '<ul class="' . ($depth === 0 ? 'menu-items' : 'sub-menu') . '">';
    foreach ($items as $item) {
        $classes = ['menu-item', 'menu-item-type-' . $item['kind']];
        if ($item['children']) {
            $classes[] = 'menu-item-has-children';
        }
        if (is_current_url((string) $item['url'])) {
            $classes[] = 'current-menu-item';
        }
        $key = $depth === 0 ? menu_icon_key($item) : '';
        if ($key !== '') {
            $classes[] = 'menu-item--utility';
            $classes[] = 'menu-item--' . $key;
            if ($key === 'archive') {
                $classes[] = 'menu-item--group-start';
            }
        }
        $label = e($item['label']);
        if ($key !== '') {
            $label = '<span class="menu-icon" aria-hidden="true">' . menu_icon_svg($key) . '</span><span class="menu-text">' . $label . '</span>';
        }
        $out .= '<li class="' . e(implode(' ', $classes)) . '"><a href="' . e((string) $item['url']) . '"'
            . ($item['target'] ? ' target="' . e($item['target']) . '" rel="noopener"' : '') . '>' . $label . '</a>';
        if ($item['children']) {
            $out .= render_menu($item['children'], $depth + 1);
        }
        $out .= '</li>';
    }
    return $out . '</ul>';
}

function search_form(string $value = ''): string
{
    return '<form role="search" method="get" class="search-form" action="/">'
        . '<label><span class="screen-reader-text">搜索：</span>'
        . '<input type="search" class="search-field" placeholder="搜索文章..." value="' . e($value) . '" name="s" /></label>'
        . '<button type="submit" class="search-submit">搜索</button></form>';
}

function pagination(int $current, int $total, string $base, array $query = []): string
{
    if ($total <= 1) {
        return '';
    }
    $current = max(1, min($current, $total));
    $qs      = $query ? '?' . http_build_query($query) : '';
    $link    = static fn (int $n): string => $n <= 1 ? $base . $qs : $base . 'page/' . $n . '/' . $qs;
    $out     = '<nav class="pagination"><ul class="page-numbers">';
    if ($current > 1) {
        $out .= '<li><a class="prev page-numbers" href="' . e($link($current - 1)) . '">← 上一页</a></li>';
    }
    $start = max(1, $current - 2);
    $end   = min($total, $current + 2);
    if ($start > 1) {
        $out .= '<li><a class="page-numbers" href="' . e($link(1)) . '">1</a></li>';
        if ($start > 2) {
            $out .= '<li><span class="page-numbers dots">…</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $out .= $i === $current
            ? '<li><span aria-current="page" class="page-numbers current">' . $i . '</span></li>'
            : '<li><a class="page-numbers" href="' . e($link($i)) . '">' . $i . '</a></li>';
    }
    if ($end < $total) {
        if ($end < $total - 1) {
            $out .= '<li><span class="page-numbers dots">…</span></li>';
        }
        $out .= '<li><a class="page-numbers" href="' . e($link($total)) . '">' . $total . '</a></li>';
    }
    if ($current < $total) {
        $out .= '<li><a class="next page-numbers" href="' . e($link($current + 1)) . '">下一页 →</a></li>';
    }
    return $out . '</ul></nav>';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function render(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require SYH_ROOT . '/templates/' . $template . '.php';
}

function stats_since(): string
{
    return (string) DB::val('SELECT MIN(day) FROM traffic', [], '');
}

function stats_record_hit(?int $postId, ?string $ref): array
{
    if (stats_is_bot()) {
        return ['ok' => false, 'skipped' => 'bot'];
    }
    if (rate_limit('hit:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 30, 60)) {
        return ['ok' => false, 'skipped' => 'rate'];
    }
    if ($postId) {
        $post = DB::one("SELECT id FROM posts WHERE id = ? AND type = 'post' AND status = 'publish'", [$postId]);
        if ($post) {
            DB::exec('UPDATE posts SET views = views + 1 WHERE id = ?', [$postId]);
        }
    }
    if ($ref !== null) {
        $source = stats_classify_ref($ref);
        $day    = format_date(now(), 'Y-m-d');
        DB::exec(
            'INSERT INTO traffic (day, source, hits) VALUES (?, ?, 1) ON CONFLICT(day, source) DO UPDATE SET hits = hits + 1',
            [$day, $source]
        );
    }
    return ['ok' => true];
}

function stats_is_bot(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return true;
    }
    return (bool) preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|python-requests|python\/|curl\/|wget|headless|monitor|uptime|scan|mj12|ahrefs|semrush|petal|yandex|bspider|googlebot|bingbot|applebot|bytespider|gptbot|claude|ccbot|perplexity|scrapy|go-http|okhttp|java\/|libwww|httpclient|zgrab|masscan|nmap|censys|expanse|netcraft|dataprovider|lever|feed|validator|checker|facebook|twitterbot|linkedinbot|pinterest|slackbot|whatsapp|telegrambot|discordbot/i', $ua);
}

function stats_classify_ref(string $ref): string
{
    $ref = trim($ref);
    if ($ref === '') {
        return 'direct';
    }
    $self = strtolower((string) parse_url(site_url(), PHP_URL_HOST));
    $self = preg_replace('/^www\./', '', $self) ?? $self;
    $host = parse_url($ref, PHP_URL_HOST);
    if (!$host) {
        $host = parse_url($ref, PHP_URL_PATH);
    }
    $host = strtolower((string) $host);
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $host = preg_replace('/[^a-z0-9.\-:]/', '', $host) ?? $host;
    if ($host === '' || $host === $self) {
        return 'direct';
    }
    if (str_starts_with($host, 'com.google.')) {
        return 'search';
    }
    $social = ['weibo.com', 'weibo.cn', 'zhihu.com', 'douyin.com', 'tiktok.com', 'bilibili.com', 'x.com', 'twitter.com', 't.co', 'facebook.com', 'instagram.com', 'reddit.com', 't.me', 'telegram.org', 'linkedin.com', 'threads.net', 'mastodon.social', 'xiaohongshu.com', 'douban.com', 'tieba.baidu.com', 'weixin.qq.com', 'qq.com', 'discord.com'];
    $search = ['google.com', 'google.com.hk', 'google.co.jp', 'google.co.uk', 'google.com.tw', 'google.de', 'google.fr', 'google.ca', 'google.com.au', 'google.ru', 'bing.com', 'baidu.com', 'sogou.com', 'so.com', '360.cn', 'sm.cn', 'duckduckgo.com', 'yandex.com', 'yandex.ru', 'ecosia.org', 'naver.com', 'search.yahoo.com', 'yahoo.com', 'brave.com', 'startpage.com', 'qwant.com', 'searx.be'];
    foreach ($social as $domain) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            return 'social';
        }
    }
    foreach ($search as $domain) {
        if ($host === $domain || str_ends_with($host, '.' . $domain)) {
            return 'search';
        }
    }
    return 'ref:' . mb_substr($host, 0, 90);
}

function rate_limit(string $name, int $max, int $window): bool
{
    $now = time();
    $row = DB::one('SELECT window_start, hits FROM rate_limits WHERE name = ?', [$name]);
    if (!$row || $now - (int) $row['window_start'] >= $window) {
        DB::exec(
            'INSERT INTO rate_limits (name, window_start, hits) VALUES (?, ?, 1) ON CONFLICT(name) DO UPDATE SET window_start = excluded.window_start, hits = 1',
            [$name, $now]
        );
        return false;
    }
    if ((int) $row['hits'] >= $max) {
        return true;
    }
    DB::exec('UPDATE rate_limits SET hits = hits + 1 WHERE name = ?', [$name]);
    return false;
}

function stats_data(): array
{
    $now   = new DateTimeImmutable('now', new DateTimeZone((string) setting('timezone', 'Asia/Shanghai')));
    $today = $now->format('Y-m-d');
    $days  = [];
    for ($i = 29; $i >= 0; $i--) {
        $days[$now->modify('-' . $i . ' days')->format('Y-m-d')] = 0;
    }
    $sourceTotals = ['direct' => 0, 'search' => 0, 'social' => 0, 'referral' => 0];
    $refs         = [];
    $rows         = DB::all('SELECT day, source, hits FROM traffic WHERE day >= ? ORDER BY day', [$now->modify('-29 days')->format('Y-m-d')]);
    foreach ($rows as $row) {
        $day  = substr((string) $row['day'], 0, 10);
        $hits = (int) $row['hits'];
        $src  = (string) $row['source'];
        if (isset($days[$day])) {
            $days[$day] += $hits;
        }
        if (isset($sourceTotals[$src])) {
            $sourceTotals[$src] += $hits;
        } elseif (str_starts_with($src, 'ref:')) {
            $host = substr($src, 4);
            if ($host !== '') {
                $refs[$host] = ($refs[$host] ?? 0) + $hits;
                $sourceTotals['referral'] += $hits;
            }
        }
    }
    $trend = [];
    foreach ($days as $day => $hits) {
        $parts   = explode('-', $day);
        $trend[] = ['date' => $day, 'label' => (int) $parts[1] . '/' . (int) $parts[2], 'views' => (int) $hits];
    }
    arsort($refs);
    $refsList = [];
    foreach (array_slice($refs, 0, 8, true) as $host => $hits) {
        $refsList[] = ['host' => $host, 'views' => (int) $hits];
    }
    $topRows = DB::all("SELECT p.id, p.title, p.slug, p.views FROM posts p WHERE p.type = 'post' AND p.status = 'publish' AND p.views > 0 ORDER BY p.views DESC, p.created_at DESC LIMIT 10");
    $top     = [];
    foreach ($topRows as $row) {
        $cats  = categories_of((int) $row['id']);
        $top[] = [
            'id'       => (int) $row['id'],
            'title'    => $row['title'],
            'url'      => post_url($row),
            'views'    => (int) $row['views'],
            'category' => $cats ? $cats[0]['name'] : '',
        ];
    }
    $totalViews = (int) DB::val("SELECT COALESCE(SUM(views), 0) FROM posts WHERE type = 'post' AND status = 'publish'", [], 0);
    $postsCount = (int) DB::val("SELECT COUNT(*) FROM posts WHERE type = 'post' AND status = 'publish'", [], 0);
    $comments   = (int) DB::val("SELECT COUNT(*) FROM comments WHERE status = 'approved'", [], 0);
    $catsCount  = (int) DB::val("SELECT COUNT(*) FROM terms WHERE taxonomy = 'category' AND count > 0", [], 0);
    $tagsCount  = (int) DB::val("SELECT COUNT(*) FROM terms WHERE taxonomy = 'post_tag' AND count > 0", [], 0);
    $firstDate  = (string) DB::val("SELECT created_at FROM posts WHERE type = 'post' AND status = 'publish' ORDER BY created_at ASC LIMIT 1", [], '');
    $launch     = site_launch();
    $monthPosts = (int) DB::val("SELECT COUNT(*) FROM posts WHERE type = 'post' AND status = 'publish' AND created_at >= ?", [$now->format('Y-m-01') . ' 00:00:00'], 0);
    $weekViews  = 0;
    foreach (array_slice($days, -7, 7, true) as $h) {
        $weekViews += (int) $h;
    }
    $catRows = DB::all(
        "SELECT t.id, t.name, t.slug, COUNT(DISTINCT p.id) AS post_count, COALESCE(SUM(p.views), 0) AS views
         FROM terms t JOIN post_terms pt ON pt.term_id = t.id JOIN posts p ON p.id = pt.post_id
         WHERE t.taxonomy = 'category' AND p.type = 'post' AND p.status = 'publish'
         GROUP BY t.id ORDER BY views DESC, post_count DESC LIMIT 8"
    );
    $cats = [];
    foreach ($catRows as $c) {
        $cats[] = ['name' => $c['name'], 'posts' => (int) $c['post_count'], 'views' => (int) $c['views'], 'url' => term_url(['taxonomy' => 'category', 'slug' => $c['slug']])];
    }
    return [
        'generated_at' => $now->format('Y-m-d H:i'),
        'since'        => stats_since(),
        'total_views'  => $totalViews,
        'today_views'  => (int) ($days[$today] ?? 0),
        'week_views'   => $weekViews,
        'avg_views'    => $postsCount ? (int) round($totalViews / $postsCount) : 0,
        'posts'        => $postsCount,
        'comments'     => $comments,
        'categories'   => $catsCount,
        'tags'         => $tagsCount,
        'days_running' => max(0, intdiv(time() - $launch->getTimestamp(), 86400)),
        'month_posts'  => $monthPosts,
        'first_post'   => $firstDate ? substr($firstDate, 0, 10) : '',
        'trend'        => $trend,
        'top'          => $top,
        'sources'      => $sourceTotals,
        'refs'         => $refsList,
        'cats'         => $cats,
    ];
}

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . $path, true, $status);
    exit;
}

function site_name(): string
{
    return (string) setting('site_name', '我的站点');
}

function site_description(): string
{
    return (string) setting('site_description', '记录技术、折腾与生活的个人站点。');
}

function site_launch(): DateTimeImmutable
{
    static $launch = null;
    if ($launch instanceof DateTimeImmutable) {
        return $launch;
    }
    $tz  = new DateTimeZone((string) setting('timezone', 'Asia/Shanghai'));
    $raw = trim((string) setting('site_launch', ''));
    foreach ([$raw, (string) DB::val("SELECT created_at FROM posts WHERE type = 'post' AND status = 'publish' ORDER BY created_at ASC LIMIT 1", [], '')] as $candidate) {
        if ($candidate === '') {
            continue;
        }
        try {
            $launch = new DateTimeImmutable($candidate, $tz);
            return $launch;
        } catch (Throwable) {
        }
    }
    $launch = new DateTimeImmutable('now', $tz);
    return $launch;
}

function site_launch_ts(): int
{
    return site_launch()->getTimestamp();
}

function ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $num  = (int) $value;
    return match ($unit) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}

/** PHP 实际可接收的上传上限（upload_max_filesize 与 post_max_size 取小） */
function php_upload_limit(): int
{
    $limits = array_filter([
        ini_bytes((string) ini_get('upload_max_filesize')),
        ini_bytes((string) ini_get('post_max_size')),
    ]);
    return $limits ? (int) min($limits) : 8 * 1024 * 1024;
}

function human_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.') . ' MB';
    }
    return max(1, (int) round($bytes / 1024)) . ' KB';
}

function setting_bool(string $name, bool $default = false): bool
{
    $value = setting($name, $default ? '1' : '0');
    return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
}

function user_row(int $id): ?array
{
    static $cache = [];
    if ($id <= 0) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
    }
    return $cache[$id];
}

function post_author_name(array $post): string
{
    $user = user_row((int) ($post['author_id'] ?? 0));
    return $user ? (string) ($user['display_name'] !== '' ? $user['display_name'] : $user['login']) : site_name();
}

function page_by_slug(string $slug): ?array
{
    return DB::one("SELECT * FROM posts WHERE type = 'page' AND status = 'publish' AND slug = ? LIMIT 1", [$slug]);
}

function term_ancestors(array $term): array
{
    $out   = [];
    $guard = 0;
    $pid   = (int) ($term['parent_id'] ?? 0);
    while ($pid > 0 && $guard++ < 8) {
        $row = DB::one("SELECT * FROM terms WHERE id = ? AND taxonomy = 'category'", [$pid]);
        if (!$row) {
            break;
        }
        $out[] = $row;
        $pid   = (int) $row['parent_id'];
    }
    return array_reverse($out);
}

function comments_tree(int $postId): array
{
    $rows = DB::all(
        "SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at ASC, id ASC",
        [$postId]
    );
    $index    = [];
    $children = [];
    foreach ($rows as $i => $row) {
        $index[(int) $row['id']] = $i;
        $children[$i] = [];
    }
    $roots = [];
    foreach ($rows as $i => $row) {
        $pid = (int) $row['parent_id'];
        if ($pid > 0 && isset($index[$pid]) && $index[$pid] !== $i) {
            $children[$index[$pid]][] = $i;
        } else {
            $roots[] = $i;
        }
    }
    $build = static function (int $i) use (&$build, $rows, $children): array {
        $row = $rows[$i];
        $row['children'] = [];
        foreach ($children[$i] as $child) {
            $row['children'][] = $build($child);
        }
        return $row;
    };
    return array_map($build, $roots);
}

function comment_author_is_admin(array $comment): bool
{
    $user = user_row((int) ($comment['user_id'] ?? 0));
    return $user !== null && (string) $user['role'] === 'admin';
}

function link_groups(): array
{
    $rows = DB::all('SELECT * FROM links ORDER BY sort, id');
    $groups = [];
    foreach ($rows as $row) {
        $groups[(string) $row['grp']][] = $row;
    }
    return $groups;
}

function friend_gradient(string $name): string
{
    $hue = (crc32($name) % 360 + 360) % 360;
    return 'linear-gradient(135deg, hsl(' . $hue . ', 60%, 55%), hsl(' . (($hue + 40) % 360) . ', 65%, 45%))';
}

function link_host(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);
    return strtolower((string) preg_replace('/^www\./', '', (string) $host));
}

function media_file_path(string $file): string
{
    return rtrim((string) SYH_CONFIG['uploads'], '/') . '/' . ltrim($file, '/');
}

function logo_url(): string
{
    $id = (int) setting('custom_logo', 0);
    return $id > 0 ? media_url($id) : '';
}

function site_icon_url(): string
{
    $id = (int) setting('site_icon', 0);
    return $id > 0 ? media_url($id) : '';
}

function og_image_url(): string
{
    $icon = site_icon_url();
    if ($icon !== '') {
        return $icon;
    }
    $cover = (string) setting('homepage_cover_image', '');
    return $cover !== '' ? normalize_media_url($cover) : '';
}

/**
 * 把本站 WordPress 上传目录的绝对 URL 转成相对路径；
 * 外站图片（友链头像等）必须原样保留，不能改写。
 */
function normalize_media_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, '/')) {
        return $url;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host === '') {
        return $url;
    }
    $siteHost = strtolower((string) parse_url(site_url(), PHP_URL_HOST));
    $localHosts = array_filter([$siteHost]);
    if (!in_array($host, $localHosts, true)) {
        return $url;
    }
    $path  = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);
    if (!is_string($path) || $path === '') {
        return $url;
    }
    return $path . ($query ? '?' . $query : '');
}

function theme_inline_css(): string
{
    $css = '';
    $bg  = normalize_media_url((string) setting('background_image', ''));
    if ($bg !== '') {
        $css .= ':root{--bg-image:url("' . $bg . '");}';
    }
    $vars = '';
    $primary = (string) setting('primary_color', '');
    if (preg_match('/^#[0-9a-f]{6}$/i', $primary)) {
        $r = hexdec(substr($primary, 1, 2));
        $g = hexdec(substr($primary, 3, 2));
        $b = hexdec(substr($primary, 5, 2));
        $adjust = static function (int $c) { return max(0, min(255, $c - 20)); };
        $vars .= '--primary:' . $primary . ';';
        $vars .= '--primary-hover:rgb(' . $adjust($r) . ',' . $adjust($g) . ',' . $adjust($b) . ');';
        $vars .= '--primary-light:rgba(' . $r . ',' . $g . ',' . $b . ',0.08);';
        $vars .= '--primary-glow:rgba(' . $r . ',' . $g . ',' . $b . ',0.22);';
    }
    $bgColor = (string) setting('background_color', '');
    if (preg_match('/^#[0-9a-f]{6}$/i', $bgColor)) {
        $vars .= '--bg:' . $bgColor . ';';
    }
    $textColor = (string) setting('text_color', '');
    if (preg_match('/^#[0-9a-f]{6}$/i', $textColor)) {
        $vars .= '--text:' . $textColor . ';';
    }
    if ($vars !== '') {
        $css .= ':root{' . $vars . '}';
    }
    return $css;
}

function commenter_cookie(string $key): string
{
    return (string) ($_COOKIE['syh_comment_' . $key] ?? '');
}

function remember_commenter(string $author, string $email, string $url): void
{
    $expire = time() + 86400 * 30;
    foreach (['author' => $author, 'email' => $email, 'url' => $url] as $key => $value) {
        setcookie('syh_comment_' . $key, $value, [
            'expires'  => $expire,
            'path'     => '/',
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }
}

function comment_status_label(string $status): string
{
    return match ($status) {
        'approved' => '已通过',
        'pending'  => '待审核',
        'spam'     => '垃圾',
        'trash'    => '回收站',
        default    => $status,
    };
}
