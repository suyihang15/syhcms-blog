#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * WordPress → SQLite 一次性迁移脚本。
 *
 * 用法：
 *   php bin/import.php [wp-config.php 路径] [--force]
 *
 * 说明：
 * - 保留文章/页面/用户/分类/评论/媒体/菜单的原始 ID，方便旧链接与附件引用继续有效。
 * - 不打印、写入或记录数据库密码。
 * - 已存在 SQLite 数据库时默认退出；加 --force 会先备份到 data/backup/ 再重建。
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "仅允许命令行运行。\n");
    exit(1);
}

$root = dirname(__DIR__);
define('SYH_ROOT', $root);
define('SYH_CONFIG', require $root . '/config.php');
require $root . '/src/Database.php';

date_default_timezone_set('Asia/Shanghai');

$args         = array_slice($argv, 1);
$force        = in_array('--force', $args, true);
$wpConfigPath = '/www/wwwroot/suyihang15.com/wp-config.php';
foreach ($args as $arg) {
    if ($arg !== '--force') {
        $wpConfigPath = $arg;
    }
}

if (!is_file($wpConfigPath)) {
    fwrite(STDERR, "找不到 wp-config.php：{$wpConfigPath}\n");
    exit(1);
}

function wp_define(string $source, string $name): ?string
{
    $name = preg_quote($name, '/');
    if (preg_match("/define\\s*\\(\\s*'" . $name . "'\\s*,\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*\\)/s", $source, $m)) {
        return stripcslashes($m[1]);
    }
    if (preg_match('/define\\s*\\(\\s*"' . $name . '"\\s*,\\s*"((?:[^"\\\\]|\\\\.)*)"\\s*\\)/s', $source, $m)) {
        return stripcslashes($m[1]);
    }
    return null;
}

$wpConfig = (string) file_get_contents($wpConfigPath);
$dbName   = wp_define($wpConfig, 'DB_NAME');
$dbUser   = wp_define($wpConfig, 'DB_USER');
$dbPass   = wp_define($wpConfig, 'DB_PASSWORD');
$dbHost   = wp_define($wpConfig, 'DB_HOST');
if ($dbName === null || $dbUser === null || $dbPass === null || $dbHost === null) {
    fwrite(STDERR, "wp-config.php 中缺少数据库配置常量。\n");
    exit(1);
}
$prefix = 'wp_';
if (preg_match('/\\$table_prefix\\s*=\\s*\'([^\']+)\'/', $wpConfig, $m)) {
    $prefix = $m[1];
}

$host = $dbHost;
$port = 3306;
$socket = null;
if (str_contains($dbHost, ':')) {
    [$host, $tail] = explode(':', $dbHost, 2);
    if (ctype_digit($tail)) {
        $port = (int) $tail;
    } else {
        $socket = $tail;
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $ms = new mysqli($host, $dbUser, $dbPass, $dbName, $port, $socket);
    $ms->set_charset('utf8mb4');
} catch (Throwable $e) {
    fwrite(STDERR, "连接 WordPress 数据库失败：" . $e->getMessage() . "\n");
    exit(1);
}

$fetch = static function (string $sql) use ($ms): array {
    $result = $ms->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
};

$dbPath = SYH_CONFIG['db'];
if (is_file($dbPath) && filesize($dbPath) > 0) {
    if (!$force) {
        fwrite(STDERR, "SQLite 数据库已存在：{$dbPath}\n如需重建请追加 --force（会先备份）。\n");
        exit(1);
    }
    $backupDir = $root . '/data/backup';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0775, true);
    }
    $backup = $backupDir . '/site-' . date('Ymd-His') . '.sqlite';
    copy($dbPath, $backup);
    @unlink($dbPath);
    @unlink($dbPath . '-wal');
    @unlink($dbPath . '-shm');
    echo "已备份旧数据库：{$backup}\n";
}

$pdo = DB::pdo();
$pdo->beginTransaction();

$insert = static function (string $table, array $data) use ($pdo): void {
    $cols = array_keys($data);
    $sql  = 'INSERT OR REPLACE INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES ('
        . implode(',', array_fill(0, count($cols), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($data));
};

$now = date('Y-m-d H:i:s');
$normalizeUploads = static function (string $text): string {
    return str_replace(
        [
            'https://suyihang15.com/wp-content/uploads/',
            'http://suyihang15.com/wp-content/uploads/',
            'https://www.suyihang15.com/wp-content/uploads/',
            'http://www.suyihang15.com/wp-content/uploads/',
            '//suyihang15.com/wp-content/uploads/',
        ],
        '/wp-content/uploads/',
        $text
    );
};
$assetUrl = static function (string $url) use ($normalizeUploads): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (in_array($host, ['suyihang15.com', 'www.suyihang15.com'], true)
        && str_starts_with((string) parse_url($url, PHP_URL_PATH), '/wp-content/uploads/')) {
        return $normalizeUploads($url);
    }
    return $url;
};

/* ---------------- 用户 ---------------- */
$capsRows = $fetch("SELECT user_id, meta_value FROM {$prefix}usermeta WHERE meta_key = '{$prefix}capabilities'");
$capsMap  = [];
foreach ($capsRows as $row) {
    $caps = @unserialize((string) $row['meta_value'], ['allowed_classes' => false]);
    $capsMap[(int) $row['user_id']] = is_array($caps) ? array_keys($caps) : [];
}
$roleMap = [
    'administrator' => 'admin',
    'editor'        => 'editor',
    'author'        => 'author',
    'contributor'   => 'subscriber',
    'subscriber'    => 'subscriber',
];
$userCount = 0;
foreach ($fetch("SELECT ID, user_login, user_email, user_pass, display_name, user_registered FROM {$prefix}users ORDER BY ID") as $row) {
    $wpRole = $capsMap[(int) $row['ID']][0] ?? 'subscriber';
    $insert('users', [
        'id'            => (int) $row['ID'],
        'login'         => (string) $row['user_login'],
        'email'         => (string) $row['user_email'],
        'pass'          => (string) $row['user_pass'],
        'display_name'  => (string) ($row['display_name'] !== '' ? $row['display_name'] : $row['user_login']),
        'role'          => $roleMap[$wpRole] ?? 'subscriber',
        'registered_at' => (string) $row['user_registered'],
    ]);
    $userCount++;
}

/* ---------------- 分类 / 标签 ---------------- */
$termRows = $fetch(
    "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description, tt.parent
     FROM {$prefix}terms t
     JOIN {$prefix}term_taxonomy tt ON tt.term_id = t.term_id
     WHERE tt.taxonomy IN ('category', 'post_tag')
     ORDER BY tt.taxonomy, t.term_id"
);
$termCount = 0;
foreach ($termRows as $row) {
    $insert('terms', [
        'id'          => (int) $row['term_id'],
        'taxonomy'    => (string) $row['taxonomy'],
        'name'        => (string) $row['name'],
        'slug'        => rawurldecode((string) $row['slug']),
        'description' => (string) $row['description'],
        'parent_id'   => (int) $row['parent'],
        'count'       => 0,
    ]);
    $termCount++;
}

/* ---------------- 媒体 ---------------- */
$metaFiles = [];
foreach ($fetch("SELECT post_id, meta_value FROM {$prefix}postmeta WHERE meta_key = '_wp_attached_file'") as $row) {
    $metaFiles[(int) $row['post_id']] = (string) $row['meta_value'];
}
$metaInfo = [];
foreach ($fetch("SELECT post_id, meta_value FROM {$prefix}postmeta WHERE meta_key = '_wp_attachment_metadata'") as $row) {
    $metaInfo[(int) $row['post_id']] = @unserialize((string) $row['meta_value'], ['allowed_classes' => false]);
}
$metaAlt = [];
foreach ($fetch("SELECT post_id, meta_value FROM {$prefix}postmeta WHERE meta_key = '_wp_attachment_image_alt'") as $row) {
    $metaAlt[(int) $row['post_id']] = (string) $row['meta_value'];
}
$mediaCount = 0;
foreach ($fetch("SELECT ID, post_title, post_mime_type, post_date FROM {$prefix}posts WHERE post_type = 'attachment' ORDER BY ID") as $row) {
    $id   = (int) $row['ID'];
    $file = ltrim($metaFiles[$id] ?? '', '/');
    if ($file === '') {
        continue;
    }
    $info = is_array($metaInfo[$id] ?? null) ? $metaInfo[$id] : [];
    $insert('media', [
        'id'         => $id,
        'file'       => $file,
        'mime'       => (string) $row['post_mime_type'],
        'width'      => (int) ($info['width'] ?? 0),
        'height'     => (int) ($info['height'] ?? 0),
        'alt'        => $metaAlt[$id] ?? '',
        'title'      => (string) $row['post_title'],
        'created_at' => (string) $row['post_date'],
    ]);
    $mediaCount++;
}

/* ---------------- 文章 / 页面 ---------------- */
$postMeta = [];
foreach ($fetch(
    "SELECT post_id, meta_key, meta_value FROM {$prefix}postmeta
     WHERE meta_key IN ('_wp_page_template', '_thumbnail_id', '_syh_views', 'views', 'post_views_count')"
) as $row) {
    $postMeta[(int) $row['post_id']][(string) $row['meta_key']][] = (string) $row['meta_value'];
}
$statusMap = ['publish' => 'publish', 'draft' => 'draft', 'pending' => 'pending', 'private' => 'private', 'future' => 'publish'];
$posts     = $fetch(
    "SELECT ID, post_author, post_date, post_content, post_title, post_excerpt, post_status,
            comment_status, post_name, post_modified, menu_order, post_type
     FROM {$prefix}posts
     WHERE post_type IN ('post', 'page') AND post_status IN ('publish', 'draft', 'pending', 'private', 'future')
     ORDER BY ID"
);
$usedSlugs  = [];
$postCount  = 0;
$pageCount  = 0;
foreach ($posts as $row) {
    $id    = (int) $row['ID'];
    $type  = (string) $row['post_type'];
    $slug  = rawurldecode((string) $row['post_name']);
    $slug  = trim($slug);
    if ($slug === '') {
        $slug = $type . '-' . $id;
    }
    $key = $type . "\0" . $slug;
    if (isset($usedSlugs[$key])) {
        $slug = $slug . '-' . $id;
        $key  = $type . "\0" . $slug;
    }
    $usedSlugs[$key] = true;

    $template = (string) ($postMeta[$id]['_wp_page_template'][0] ?? '');
    if ($template === 'default') {
        $template = '';
    }
    $views = (int) ($postMeta[$id]['_syh_views'][0] ?? 0);
    if ($views === 0) {
        $views = (int) ($postMeta[$id]['post_views_count'][0] ?? ($postMeta[$id]['views'][0] ?? 0));
    }
    $created = (string) $row['post_date'];
    if ($created === '' || str_starts_with($created, '0000')) {
        $created = $now;
    }
    $updated = (string) $row['post_modified'];
    if ($updated === '' || str_starts_with($updated, '0000')) {
        $updated = $created;
    }
    $insert('posts', [
        'id'             => $id,
        'type'           => $type,
        'status'         => $statusMap[(string) $row['post_status']] ?? 'publish',
        'slug'           => $slug,
        'title'          => (string) $row['post_title'],
        'content'        => $normalizeUploads((string) $row['post_content']),
        'excerpt'        => $normalizeUploads((string) $row['post_excerpt']),
        'author_id'      => (int) $row['post_author'],
        'created_at'     => $created,
        'updated_at'     => $updated,
        'comment_status' => (string) $row['comment_status'] === 'closed' ? 'closed' : 'open',
        'menu_order'     => (int) $row['menu_order'],
        'template'       => $template,
        'views'          => max(0, $views),
        'cover_id'       => (int) ($postMeta[$id]['_thumbnail_id'][0] ?? 0),
    ]);
    $type === 'page' ? $pageCount++ : $postCount++;
}

/* ---------------- 文章分类关系 ---------------- */
$relationCount = 0;
foreach ($fetch(
    "SELECT tr.object_id, tt.term_id
     FROM {$prefix}term_relationships tr
     JOIN {$prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
     WHERE tt.taxonomy IN ('category', 'post_tag')"
) as $row) {
    $insert('post_terms', [
        'post_id' => (int) $row['object_id'],
        'term_id' => (int) $row['term_id'],
    ]);
    $relationCount++;
}

/* ---------------- 评论 ---------------- */
$commentStatus = ['1' => 'approved', '0' => 'pending', 'spam' => 'spam', 'trash' => 'trash'];
$commentCount  = 0;
$knownPosts    = array_fill_keys(array_map(static fn (array $p): int => (int) $p['ID'], $posts), true);
foreach ($fetch(
    "SELECT comment_ID, comment_post_ID, comment_author, comment_author_email, comment_author_url,
            comment_author_IP, comment_date, comment_content, comment_approved, comment_agent,
            comment_parent, user_id
     FROM {$prefix}comments ORDER BY comment_ID"
) as $row) {
    if (!isset($knownPosts[(int) $row['comment_post_ID']])) {
        continue;
    }
    $insert('comments', [
        'id'         => (int) $row['comment_ID'],
        'post_id'    => (int) $row['comment_post_ID'],
        'parent_id'  => (int) $row['comment_parent'],
        'author'     => (string) $row['comment_author'],
        'email'      => (string) $row['comment_author_email'],
        'url'        => (string) $row['comment_author_url'],
        'content'    => (string) $row['comment_content'],
        'status'     => $commentStatus[(string) $row['comment_approved']] ?? 'pending',
        'created_at' => (string) $row['comment_date'],
        'ip'         => (string) $row['comment_author_IP'],
        'ua'         => (string) $row['comment_agent'],
        'user_id'    => (int) $row['user_id'],
    ]);
    $commentCount++;
}

/* ---------------- 菜单 ---------------- */
$menuMeta = [];
foreach ($fetch(
    "SELECT post_id, meta_key, meta_value FROM {$prefix}postmeta
     WHERE meta_key IN ('_menu_item_type', '_menu_item_object', '_menu_item_object_id',
                        '_menu_item_url', '_menu_item_menu_item_parent', '_menu_item_target')"
) as $row) {
    $menuMeta[(int) $row['post_id']][(string) $row['meta_key']] = (string) $row['meta_value'];
}

$termById = [];
foreach ($pdo->query('SELECT * FROM terms')->fetchAll() as $term) {
    $termById[(int) $term['id']] = $term;
}
$termPath = static function (array $term) use (&$termPath, $termById): string {
    $path   = rawurlencode((string) $term['slug']);
    $parent = (int) $term['parent_id'];
    if ($parent > 0 && isset($termById[$parent])) {
        $path = $termPath($termById[$parent]) . '/' . $path;
    }
    return $path;
};
$postById = [];
foreach ($pdo->query('SELECT id, type, slug, title FROM posts')->fetchAll() as $post) {
    $postById[(int) $post['id']] = $post;
}

$menuCount = 0;
foreach ($fetch(
    "SELECT ID, post_title, menu_order FROM {$prefix}posts
     WHERE post_type = 'nav_menu_item' AND post_status = 'publish' ORDER BY menu_order, ID"
) as $row) {
    $id   = (int) $row['ID'];
    $meta = $menuMeta[$id] ?? [];
    $type = (string) ($meta['_menu_item_type'] ?? 'custom');
    $objId = (int) ($meta['_menu_item_object_id'] ?? 0);
    $label = (string) $row['post_title'];
    $url   = '';
    $kind  = 'custom';
    if ($type === 'post_type') {
        $objType = (string) ($meta['_menu_item_object'] ?? 'page');
        $kind    = $objType === 'post' ? 'post' : 'page';
        $obj     = $postById[$objId] ?? null;
        if ($obj) {
            $url = '/' . rawurlencode((string) $obj['slug']) . '/';
            if ($label === '') {
                $label = (string) $obj['title'];
            }
        }
    } elseif ($type === 'taxonomy') {
        $kind = 'category';
        $term = $termById[$objId] ?? null;
        if ($term) {
            $url = '/category/' . $termPath($term) . '/';
            if ($label === '') {
                $label = (string) $term['name'];
            }
        }
    } else {
        $url = $normalizeUploads((string) ($meta['_menu_item_url'] ?? ''));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (in_array($host, ['suyihang15.com', 'www.suyihang15.com'], true)) {
            $url = $path !== '' ? $path : '/';
        }
        if ($url === '') {
            $url = '/';
        }
    }
    if ($label === '' || $url === '') {
        continue;
    }
    $insert('menu_items', [
        'id'        => $id,
        'menu'      => 'primary',
        'parent_id' => (int) ($meta['_menu_item_menu_item_parent'] ?? 0),
        'label'     => $label,
        'url'       => $url,
        'target'    => (string) ($meta['_menu_item_target'] ?? '') === '_blank' ? '_blank' : '',
        'sort'      => (int) $row['menu_order'],
        'kind'      => $kind,
        'object_id' => $objId,
    ]);
    $menuCount++;
}

/* ---------------- 友情链接 ---------------- */
$themeModsRow = $fetch("SELECT option_value FROM {$prefix}options WHERE option_name = 'theme_mods_suyihang15-blong-theme'");
$themeMods    = $themeModsRow ? @unserialize((string) $themeModsRow[0]['option_value'], ['allowed_classes' => false]) : [];
if (!is_array($themeMods)) {
    $themeMods = [];
}

$friendlyUrl = static function (string $url): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, '/')) {
        return $url;
    }
    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }
    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    return $url;
};
$importLinks = static function (string $blob, string $group, bool $hasDark) use ($insert, $friendlyUrl, $assetUrl): int {
    $count = 0;
    $sort  = 0;
    foreach (preg_split('/\r\n|\r|\n/', $blob) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $name  = $parts[0] ?? '';
        $url   = $friendlyUrl($parts[1] ?? '');
        if ($name === '' || $url === '') {
            continue;
        }
        $image = $assetUrl($friendlyUrl($parts[2] ?? ''));
        $desc  = $parts[3] ?? '';
        $rss   = $friendlyUrl($parts[4] ?? '');
        $dark  = $hasDark ? $assetUrl($friendlyUrl($parts[5] ?? '')) : '';
        $insert('links', [
            'grp'         => $group,
            'name'        => $name,
            'url'         => $url,
            'image'       => $image,
            'image_dark'  => $dark,
            'description' => $desc,
            'rss'         => $rss,
            'sort'        => $sort++,
        ]);
        $count++;
    }
    return $count;
};
$linkCount  = $importLinks((string) ($themeMods['friend_links_friends'] ?? ''), '好友', false);
$linkCount += $importLinks((string) ($themeMods['friend_links_org'] ?? ''), '组织', true);

/* ---------------- 访问统计 ---------------- */
$trafficCount = 0;
foreach ($fetch("SELECT day, source, hits FROM {$prefix}syh_traffic") as $row) {
    $insert('traffic', [
        'day'    => (string) $row['day'],
        'source' => (string) $row['source'],
        'hits'   => (int) $row['hits'],
    ]);
    $trafficCount++;
}

/* ---------------- 站点设置 ---------------- */
$wpOptions = [];
foreach ($fetch(
    "SELECT option_name, option_value FROM {$prefix}options
     WHERE option_name IN ('blogname', 'blogdescription', 'siteurl', 'posts_per_page', 'timezone_string',
                           'site_icon', 'comment_moderation', 'comment_whitelist', 'require_name_email')"
) as $row) {
    $wpOptions[(string) $row['option_name']] = (string) $row['option_value'];
}

$putOption = static function (string $name, mixed $value, bool $skipEmpty = true) use ($insert, $normalizeUploads): void {
    if (is_string($value)) {
        $value = $normalizeUploads($value);
        if ($skipEmpty && trim($value) === '') {
            return;
        }
    }
    $insert('options', ['name' => $name, 'value' => (string) $value]);
};

$putOption('site_name', $wpOptions['blogname'] ?? '苏的小站');
$putOption('site_description', $wpOptions['blogdescription'] ?? '');
$putOption('site_url', $wpOptions['siteurl'] ?? 'https://suyihang15.com');
$putOption('posts_per_page', (int) ($wpOptions['posts_per_page'] ?? 10));
$putOption('timezone', $wpOptions['timezone_string'] ?? 'Asia/Shanghai');
$putOption('comment_moderation', ($wpOptions['comment_moderation'] ?? '') === '1' ? '1' : '0', false);
$putOption('comment_whitelist', ($wpOptions['comment_whitelist'] ?? '1') === '1' ? '1' : '0', false);
$putOption('require_name_email', ($wpOptions['require_name_email'] ?? '1') === '1' ? '1' : '0', false);

if ((int) ($wpOptions['site_icon'] ?? 0) > 0) {
    $putOption('site_icon', (int) $wpOptions['site_icon']);
}
if ((int) ($themeMods['custom_logo'] ?? 0) > 0) {
    $putOption('custom_logo', (int) $themeMods['custom_logo']);
}

$themeSettingMap = [
    'profile_title'              => 'profile_title',
    'profile_tagline'            => 'profile_tagline',
    'profile_intro'              => 'profile_intro',
    'profile_location'           => 'profile_location',
    'profile_experience'         => 'profile_experience',
    'profile_stack'              => 'profile_stack',
    'homepage_banner_text'       => 'homepage_banner_text',
    'homepage_cover_image'       => 'homepage_cover_image',
    'background_image'           => 'background_image',
    'background_color'           => 'background_color',
    'primary_color'              => 'primary_color',
    'text_color'                 => 'text_color',
    'donation_wechat_qr'         => 'donation_wechat_qr',
    'donation_alipay_qr'         => 'donation_alipay_qr',
    'donation_description'       => 'donation_description',
    'links_page_intro'           => 'links_page_intro',
    'links_page_apply_title'     => 'links_page_apply_title',
    'links_page_apply_content'   => 'links_page_apply_content',
    'links_page_apply_contact'   => 'links_page_apply_contact',
    'icp_record_number'          => 'icp_record_number',
    'icp_record_url'             => 'icp_record_url',
    'psb_record_number'          => 'psb_record_number',
    'psb_record_url'             => 'psb_record_url',
    'social_github_url'          => 'social_github',
    'social_bilibili_url'        => 'social_bilibili',
    'social_qq_qr'               => 'social_qq_qr',
    'social_wechat_qr'           => 'social_wechat_qr',
];
foreach ($themeSettingMap as $source => $target) {
    if (array_key_exists($source, $themeMods)) {
        $putOption($target, $themeMods[$source]);
    }
}
/* 图片类设置统一转成本站相对路径 */
foreach (['homepage_cover_image', 'background_image', 'donation_wechat_qr', 'donation_alipay_qr', 'social_qq_qr', 'social_wechat_qr'] as $name) {
    $value = $pdo->query("SELECT value FROM options WHERE name = " . $pdo->quote($name))->fetchColumn();
    if (is_string($value) && $value !== '') {
        $putOption($name, $assetUrl($value));
    }
}

/* ---------------- 重算分类计数 ---------------- */
$pdo->exec(
    "UPDATE terms SET count = (
        SELECT COUNT(*) FROM post_terms pt JOIN posts p ON p.id = pt.post_id
        WHERE pt.term_id = terms.id AND p.status = 'publish'
    )"
);

$pdo->commit();

/* ---------------- 结果核对 ---------------- */
$scalar = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
echo "导入完成：\n";
printf("  用户       %d\n", $scalar('SELECT COUNT(*) FROM users'));
printf("  分类/标签  %d\n", $scalar('SELECT COUNT(*) FROM terms'));
printf("  文章       %d（发布 %d）\n", $scalar("SELECT COUNT(*) FROM posts WHERE type='post'"), $scalar("SELECT COUNT(*) FROM posts WHERE type='post' AND status='publish'"));
printf("  页面       %d（发布 %d）\n", $scalar("SELECT COUNT(*) FROM posts WHERE type='page'"), $scalar("SELECT COUNT(*) FROM posts WHERE type='page' AND status='publish'"));
printf("  媒体       %d\n", $scalar('SELECT COUNT(*) FROM media'));
printf("  评论       %d（通过 %d / 待审 %d / 垃圾 %d）\n",
    $scalar('SELECT COUNT(*) FROM comments'),
    $scalar("SELECT COUNT(*) FROM comments WHERE status='approved'"),
    $scalar("SELECT COUNT(*) FROM comments WHERE status='pending'"),
    $scalar("SELECT COUNT(*) FROM comments WHERE status='spam'")
);
printf("  文章分类   %d\n", $scalar('SELECT COUNT(*) FROM post_terms'));
printf("  菜单项     %d\n", $scalar('SELECT COUNT(*) FROM menu_items'));
printf("  友情链接   %d\n", $scalar('SELECT COUNT(*) FROM links'));
printf("  访问统计   %d\n", $scalar('SELECT COUNT(*) FROM traffic'));
printf("  站点设置   %d\n", $scalar('SELECT COUNT(*) FROM options'));
