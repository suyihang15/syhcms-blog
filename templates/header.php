<?php
$syh        = $GLOBALS['syh_page'] ?? [];
$siteName   = site_name();
$siteDesc   = site_description();
$pageTitle  = trim((string) ($syh['title'] ?? ''));
$docTitle   = $pageTitle !== '' ? $pageTitle . ' - ' . $siteName : $siteName;
$desc       = trim((string) ($syh['description'] ?? '')) ?: $siteDesc;
$canonical  = (string) ($syh['canonical'] ?? site_url() . '/');
$bodyClass  = trim((string) ($syh['body_class'] ?? ''));
$ogType     = (string) ($syh['og_type'] ?? 'website');
$ogImage    = (string) ($syh['og_image'] ?? '');
$icon       = site_icon_url();
$logo       = logo_url();
$cssVer     = is_file(SYH_ROOT . '/public/assets/site.css') ? (string) filemtime(SYH_ROOT . '/public/assets/site.css') : '1';
?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <link rel="alternate" type="application/rss+xml" title="<?= e($siteName) ?> RSS 2.0" href="/feed/">
    <link rel="alternate" type="application/atom+xml" title="<?= e($siteName) ?> Atom 1.0" href="/feed/atom/">
    <script>(function(){try{var e=document.documentElement,m=localStorage.getItem('themeMode')||'auto';var d=m==='dark'||(m==='auto'&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);e.classList.add(d?'dark-mode':'light-mode');e.setAttribute('data-theme-mode',m);}catch(err){}})();</script>
    <title><?= e($docTitle) ?></title>
    <meta name="robots" content="max-image-preview:large">
    <meta name="description" content="<?= e($desc) ?>">
    <meta name="theme-color" content="#0071e3" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:locale" content="zh_CN">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($pageTitle !== '' ? $pageTitle : $siteName) ?>">
    <meta property="og:description" content="<?= e($desc) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle !== '' ? $pageTitle : $siteName) ?>">
    <meta name="twitter:description" content="<?= e($desc) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <?php if ($icon !== ''): ?>
    <link rel="icon" href="<?= e($icon) ?>">
    <link rel="apple-touch-icon" href="<?= e($icon) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/site.css?v=<?= e($cssVer) ?>">
    <?php $inlineCss = theme_inline_css(); if ($inlineCss !== ''): ?>
    <style id="syh-theme-inline-css"><?= $inlineCss ?></style>
    <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link screen-reader-text" href="#content">跳到主要内容</a>
<div class="reading-progress-bar"></div>
<div class="site-wrapper">
    <header class="site-header">
        <div class="header-inner">
            <div class="site-branding">
                <?php if ($logo !== ''): ?>
                <a href="/" class="custom-logo-link" rel="home"><img src="<?= e($logo) ?>" class="custom-logo" alt="<?= e($siteName) ?>"></a>
                <?php endif; ?>
                <p class="site-title"><a href="/"><?= e($siteName) ?></a></p>
            </div>
            <div class="header-actions">
                <?= theme_toggle_button('theme-toggle--mobile') ?>
                <button class="mobile-menu-toggle" aria-label="菜单" aria-controls="site-nav" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <nav class="site-nav" id="site-nav" aria-label="主要导航">
                    <button class="mobile-menu-close" aria-label="关闭菜单">×</button>
                    <div class="mobile-nav-search"><?= search_form() ?></div>
                    <?php $menu = menu_items('primary'); ?>
                    <?php if ($menu): ?>
                        <?= render_menu($menu) ?>
                    <?php else: ?>
                        <ul class="menu-items">
                            <li class="menu-item"><a href="/about/">关于</a></li>
                            <li class="menu-item"><a href="/archive/">归档</a></li>
                            <li class="menu-item"><a href="/stats/">统计</a></li>
                            <li class="menu-item"><a href="/guestbook/">留言板</a></li>
                        </ul>
                    <?php endif; ?>
                    <div class="mobile-nav-tools"><?= social_links_markup('mobile') ?></div>
                </nav>
            </div>
        </div>
        <div class="header-desktop-tools">
            <?= search_form() ?>
            <?= social_links_markup('desktop') ?>
            <?= theme_toggle_button() ?>
        </div>
    </header>
    <?php $banner = trim((string) setting('homepage_banner_text', '')); if ($banner !== '' && str_contains($bodyClass, 'home')): ?>
    <div class="site-banner"><p><?= $banner ?></p></div>
    <?php endif; ?>
