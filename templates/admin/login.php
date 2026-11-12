<?php
$title = '登录';
$cssVer = is_file(SYH_ROOT . '/public/assets/admin.css') ? (string) filemtime(SYH_ROOT . '/public/assets/admin.css') : '1';
?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>登录 - <?= e(site_name()) ?> 后台</title>
    <link rel="stylesheet" href="/assets/admin.css?v=<?= e($cssVer) ?>">
</head>
<body class="admin-login">
<div class="admin-login-card">
    <h1>登录后台</h1>
    <p class="lede"><?= e(site_name()) ?> · 内容管理系统</p>
    <?php foreach (($flashes ?? []) as $f): ?>
    <div class="admin-flash admin-flash-<?= e((string) $f['type']) ?>"><?= e((string) $f['message']) ?></div>
    <?php endforeach; ?>
    <form class="admin-form" action="<?= e(admin_url('login/')) ?>" method="post">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="next" value="<?= e((string) ($next ?? '')) ?>">
        <div class="admin-form-row">
            <label for="login">账号或邮箱</label>
            <input class="admin-input" type="text" id="login" name="login" autocomplete="username" required autofocus>
        </div>
        <div class="admin-form-row">
            <label for="password">密码</label>
            <input class="admin-input" type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="admin-btn admin-btn-primary" type="submit">登录</button>
    </form>
    <div class="admin-login-foot"><a href="/">← 返回站点首页</a></div>
</div>
</body>
</html>
