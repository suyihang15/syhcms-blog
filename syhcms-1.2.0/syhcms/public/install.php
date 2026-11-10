<?php
declare(strict_types=1);

/**
 * 安装向导：创建数据库、首个管理员、基础选项与默认页面。
 * 安装完成后会写入 data/install.lock，之后本页面不再可用。
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$lockFile = SYH_ROOT . '/data/install.lock';
$errors   = [];
$done     = false;

$installed = is_file($lockFile);
if (!$installed) {
    try {
        $installed = (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0) > 0;
    } catch (Throwable $e) {
        $installed = false;
    }
}

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$host  = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$defaultUrl = ($https ? 'https' : 'http') . '://' . $host;

$form = [
    'site_name'  => '我的站点',
    'site_url'   => $defaultUrl,
    'site_desc'  => '记录技术、折腾与生活的个人站点。',
    'login'      => 'admin',
    'email'      => '',
];

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['site_name'] = trim((string) ($_POST['site_name'] ?? ''));
    $form['site_url']  = rtrim(trim((string) ($_POST['site_url'] ?? '')), '/');
    $form['site_desc'] = trim((string) ($_POST['site_desc'] ?? ''));
    $form['login']     = trim((string) ($_POST['login'] ?? ''));
    $form['email']     = trim((string) ($_POST['email'] ?? ''));
    $pass              = (string) ($_POST['password'] ?? '');
    $pass2             = (string) ($_POST['password2'] ?? '');
    $token             = (string) ($_POST['token'] ?? '');

    if (!hash_equals((string) ($_SESSION['install_token'] ?? ''), $token)) {
        $errors[] = '页面已过期，请刷新后重试。';
    }
    if ($form['site_name'] === '' || mb_strlen($form['site_name']) > 60) {
        $errors[] = '站点名称不能为空，且不超过 60 个字符。';
    }
    if (!filter_var($form['site_url'], FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $form['site_url'])) {
        $errors[] = '站点地址必须是完整的 http(s) URL，例如 https://example.com。';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $form['login'])) {
        $errors[] = '管理员账号需为 3–60 位字母、数字、点、下划线或短横线。';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = '邮箱格式不正确。';
    }
    if (strlen($pass) < 8) {
        $errors[] = '密码至少 8 位。';
    } elseif ($pass !== $pass2) {
        $errors[] = '两次输入的密码不一致。';
    }

    $root = SYH_ROOT;
    foreach ([$root . '/data', SYH_CONFIG['uploads']] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            $errors[] = '目录无法创建，请检查权限：' . $dir;
        } elseif (!is_writable($dir)) {
            $errors[] = '目录不可写，请检查权限：' . $dir;
        }
    }

    if (!$errors) {
        try {
            $now = date('Y-m-d H:i:s');
            DB::insert('users', [
                'login'         => $form['login'],
                'email'         => $form['email'],
                'pass'          => password_hash($pass, PASSWORD_DEFAULT),
                'display_name'  => $form['site_name'],
                'role'          => 'admin',
                'registered_at' => $now,
            ]);

            $options = [
                'site_name'          => $form['site_name'],
                'site_url'           => $form['site_url'],
                'site_description'   => $form['site_desc'],
                'timezone'           => 'Asia/Shanghai',
                'posts_per_page'     => '10',
                'site_launch'        => $now,
                'comment_moderation' => '1',
                'require_name_email' => '1',
                'comment_whitelist'  => '1',
                'author_name'        => '',
                'author_url'         => '',
            ];
            foreach ($options as $name => $value) {
                set_option($name, $value);
            }

            $pages = [
                ['about',     '关于',   '这里是关于页面，安装后可在后台“页面”中修改。', '',                20],
                ['archive',   '归档',   '',                                               'page-archive.php', 30],
                ['stats',     '统计',   '',                                               'page-stats.php',   40],
                ['guestbook', '留言板', '欢迎留言，留下你的想法。',                       'page-guestbook.php', 50],
                ['links',     '友链',   '',                                               'page-links.php',   60],
            ];
            foreach ($pages as [$slug, $title, $content, $template, $order]) {
                DB::insert('posts', [
                    'type'           => 'page',
                    'status'         => 'publish',
                    'slug'           => $slug,
                    'title'          => $title,
                    'content'        => $content,
                    'excerpt'        => '',
                    'author_id'      => 1,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                    'comment_status' => $slug === 'guestbook' ? 'open' : 'closed',
                    'menu_order'     => $order,
                    'template'       => $template,
                ]);
            }

            $menu = [
                ['首页', '/'],
                ['关于', '/about/'],
                ['归档', '/archive/'],
                ['统计', '/stats/'],
                ['留言板', '/guestbook/'],
                ['友链', '/links/'],
            ];
            foreach ($menu as $i => [$label, $url]) {
                DB::insert('menu_items', [
                    'menu'   => 'primary',
                    'label'  => $label,
                    'url'    => $url,
                    'target' => '',
                    'sort'   => ($i + 1) * 10,
                    'kind'   => 'custom',
                ]);
            }

            file_put_contents($lockFile, 'installed at ' . $now . PHP_EOL);
            $done = true;
        } catch (Throwable $e) {
            $errors[] = '安装失败：' . $e->getMessage();
        }
    }
}

if (!$installed && empty($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(16));
}
$token = (string) ($_SESSION['install_token'] ?? '');

function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>安装向导</title>
<style>
    :root { color-scheme: light dark; --line: #d9dee6; --muted: #667085; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
        background: #f4f6f9; color: #1c2430; font: 15px/1.6 -apple-system, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; }
    .card { width: 100%; max-width: 560px; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 28px; box-shadow: 0 18px 40px rgba(15, 23, 42, .08); }
    h1 { margin: 0 0 6px; font-size: 22px; }
    p.lead { margin: 0 0 20px; color: var(--muted); font-size: 14px; }
    label { display: block; margin: 14px 0 6px; font-size: 13px; font-weight: 600; }
    input { width: 100%; padding: 9px 11px; border: 1px solid var(--line); border-radius: 8px; font: inherit; background: #fff; color: inherit; }
    input:focus { outline: 2px solid rgba(0, 113, 227, .25); border-color: #0071e3; }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .hint { color: var(--muted); font-size: 12px; }
    button { margin-top: 20px; width: 100%; padding: 11px; border: 0; border-radius: 9px; background: #0071e3; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
    button:hover { background: #0062c4; }
    .errors { margin: 0 0 16px; padding: 10px 12px; border: 1px solid #f2c2c2; border-radius: 8px; background: #fff5f5; color: #b42318; font-size: 13px; }
    .ok { margin: 0 0 16px; padding: 12px; border: 1px solid #b7e1c1; border-radius: 8px; background: #f2fbf4; color: #14713a; }
    a { color: #0071e3; }
    @media (max-width: 520px) { .row { grid-template-columns: 1fr; } }
    @media (prefers-color-scheme: dark) {
        body { background: #0f141a; color: #e6e9ee; }
        .card { background: #161c24; border-color: #2a333f; }
        input { background: #0f141a; border-color: #2a333f; }
        .errors { background: #2a1717; border-color: #5b2626; color: #f3b4b4; }
    }
</style>
</head>
<body>
<div class="card">
<?php if ($done): ?>
    <h1>安装完成</h1>
    <p class="ok">数据库、管理员和默认页面已创建。为安全起见，建议从服务器上删除 public/install.php。</p>
    <p><a href="/admin/login/">进入后台登录 →</a></p>
<?php elseif ($installed): ?>
    <h1>已安装</h1>
    <p class="lead">检测到站点已存在管理员或安装锁，安装向导已停用。</p>
    <p><a href="/">返回首页</a> · <a href="/admin/login/">后台登录</a></p>
<?php else: ?>
    <h1>安装向导</h1>
    <p class="lead">创建 SQLite 数据库、管理员账号和默认页面。</p>
    <?php foreach ($errors as $msg): ?><div class="errors"><?= h($msg) ?></div><?php endforeach; ?>
    <form method="post" action="/install.php">
        <input type="hidden" name="token" value="<?= h($token) ?>">
        <label for="site_name">站点名称</label>
        <input id="site_name" name="site_name" maxlength="60" required value="<?= h($form['site_name']) ?>">
        <label for="site_url">站点地址</label>
        <input id="site_url" name="site_url" type="url" required value="<?= h($form['site_url']) ?>">
        <span class="hint">例如 https://example.com，不要以 / 结尾。</span>
        <label for="site_desc">站点描述</label>
        <input id="site_desc" name="site_desc" value="<?= h($form['site_desc']) ?>">
        <div class="row">
            <div>
                <label for="login">管理员账号</label>
                <input id="login" name="login" required value="<?= h($form['login']) ?>" autocomplete="username">
            </div>
            <div>
                <label for="email">管理员邮箱</label>
                <input id="email" name="email" type="email" value="<?= h($form['email']) ?>" autocomplete="email">
            </div>
        </div>
        <div class="row">
            <div>
                <label for="password">密码</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
            </div>
            <div>
                <label for="password2">确认密码</label>
                <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
            </div>
        </div>
        <button type="submit">开始安装</button>
    </form>
<?php endif; ?>
</div>
</body>
</html>
