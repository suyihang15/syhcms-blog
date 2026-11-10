<?php
$adminUser      = $adminUser ?? Auth::user();
$title          = $title ?? '后台';
$currentSection = $currentSection ?? '';
$flashes        = $flashes ?? [];
$pendingCount   = (int) DB::val("SELECT COUNT(*) FROM comments WHERE status = 'pending'", [], 0);
$adminName      = $adminUser ? (string) ($adminUser['display_name'] !== '' ? $adminUser['display_name'] : $adminUser['login']) : '';
$adminInitial   = $adminName !== '' ? mb_substr($adminName, 0, 1, 'UTF-8') : '·';

$icons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.6"/><rect x="14" y="3" width="7" height="7" rx="1.6"/><rect x="3" y="14" width="7" height="7" rx="1.6"/><rect x="14" y="14" width="7" height="7" rx="1.6"/>',
    'posts'     => '<path d="M6 3h8l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/><path d="M14 3v5h5"/><path d="M9 13h7M9 17h5"/>',
    'pages'     => '<path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H9l2 2.5h7.5A1.5 1.5 0 0 1 20 8v10.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5Z"/><path d="M8 12h8M8 16h5"/>',
    'comments'  => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.4A8 8 0 1 1 21 12Z"/><path d="M9 11h6M9 14.5h4"/>',
    'media'     => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5.2-5.2a1.5 1.5 0 0 1 2.1 0L20 20"/>',
    'terms'     => '<path d="M20.6 13.4 12 4.8H4v8l8.6 8.6a2 2 0 0 0 2.8 0l5.2-5.2a2 2 0 0 0 0-2.8Z"/><circle cx="8.5" cy="9.5" r="1.2"/>',
    'menu'      => '<path d="M4 7h16M4 12h16M4 17h10"/>',
    'links'     => '<path d="M10.5 13.5a4 4 0 0 0 5.7 0l2.5-2.5a4 4 0 1 0-5.7-5.7L11.5 6.8"/><path d="M13.5 10.5a4 4 0 0 0-5.7 0L5.3 13a4 4 0 1 0 5.7 5.7l1.5-1.5"/>',
    'settings'  => '<circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
    'appearance' => '<path d="M12 3a9 9 0 1 0 0 18c1.2 0 2-.9 2-2 0-.6-.2-1-.6-1.4-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2H17a4 4 0 0 0 4-4c0-4-4-7.3-9-7.3Z"/><circle cx="7.5" cy="11" r="1.1"/><circle cx="12" cy="7.5" r="1.1"/><circle cx="16" cy="10.5" r="1.1"/>',
    'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0"/><path d="M16.5 5.2a3.2 3.2 0 0 1 0 5.6M17.5 14.4A5.5 5.5 0 0 1 21.2 19"/>',
    'profile'   => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
    'code'      => '<path d="m8 7-5 5 5 5M16 7l5 5-5 5"/><path d="m13.5 4-3 16"/>',
];
$navGroups = [
    '概览' => [
        'dashboard' => ['仪表盘', admin_url()],
    ],
    '内容' => [
        'posts'    => ['文章', admin_url('posts/')],
        'pages'    => ['页面', admin_url('pages/')],
        'media'    => ['媒体库', admin_url('media/')],
        'comments' => ['评论', admin_url('comments/')],
    ],
    '组织' => [
        'terms' => ['分类 / 标签', admin_url('terms/')],
        'menu'  => ['导航菜单', admin_url('menu/')],
        'links' => ['友情链接', admin_url('links/')],
    ],
    '站点' => [
        'users'      => ['用户', admin_url('users/')],
        'appearance' => ['外观', admin_url('appearance/')],
        'settings'   => ['站点设置', admin_url('settings/')],
        'code'       => ['源码编辑', admin_url('code/')],
        'profile'    => ['个人资料', admin_url('profile/')],
    ],
];
$cssVer = is_file(SYH_ROOT . '/public/assets/admin.css') ? (string) filemtime(SYH_ROOT . '/public/assets/admin.css') : '1';
if (!Auth::can('admin')) {
    unset(
        $navGroups['站点']['users'],
        $navGroups['站点']['appearance'],
        $navGroups['站点']['settings'],
        $navGroups['站点']['code']
    );
}
?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> - <?= e(site_name()) ?> 后台</title>
    <link rel="stylesheet" href="/assets/admin.css?v=<?= e($cssVer) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <a class="admin-brand" href="<?= e(admin_url()) ?>">
            <span class="admin-brand-mark"><?= e(mb_substr(site_name() !== '' ? site_name() : '站', 0, 1)) ?></span>
            <span><?= e(site_name()) ?></span>
        </a>
        <nav class="admin-nav">
            <?php foreach ($navGroups as $groupLabel => $items): ?>
            <div class="admin-nav-group">
                <div class="admin-nav-group-label"><?= e($groupLabel) ?></div>
                <?php foreach ($items as $key => [$label, $href]): ?>
                <a href="<?= e($href) ?>" class="<?= $currentSection === $key ? 'is-active' : '' ?>"<?= $currentSection === $key ? ' aria-current="page"' : '' ?>>
                    <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><?= $icons[$key] ?? '' ?></svg>
                    <span><?= e($label) ?></span>
                    <?php if ($key === 'comments' && $pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-foot">
            <strong><?= e($adminName) ?></strong>
            <div>PHP <?= e(PHP_VERSION) ?> · SQLite</div>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="admin-menu-toggle" aria-label="打开菜单" aria-controls="admin-sidebar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <span class="admin-crumb">后台</span>
            <span class="admin-crumb">/</span>
            <h1><?= e($title) ?></h1>
            <div class="admin-topbar-actions">
                <a href="/" target="_blank" rel="noopener">查看站点</a>
                <a class="admin-user" href="<?= e(admin_url('profile/')) ?>">
                    <span class="admin-avatar"><?= e($adminInitial) ?></span>
                    <span><?= e($adminName) ?></span>
                </a>
                <form action="<?= e(admin_url('logout/')) ?>" method="post">
                    <?= Auth::csrfField() ?>
                    <button type="submit" class="admin-btn admin-btn-sm">退出</button>
                </form>
            </div>
        </header>
        <div class="admin-content">
            <?php foreach ($flashes as $f): ?>
            <div class="admin-flash admin-flash-<?= e((string) $f['type']) ?>"><?= e((string) $f['message']) ?></div>
            <?php endforeach; ?>
