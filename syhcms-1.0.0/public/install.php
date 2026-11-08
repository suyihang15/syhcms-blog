<?php
declare(strict_types=1);

/**
 * 安装向导
 *
 * 浏览器打开 /install.php 按提示填写即可；也可以在命令行执行：
 *
 *   php public/install.php --site-url=https://example.com --site-name=我的博客 \
 *       --admin-user=admin --admin-pass=password
 *
 * 安装完成后会写入 data/installed.lock，建议随后删除本文件。
 */

define('SYH_ROOT', dirname(__DIR__));
$syhConfig = require SYH_ROOT . '/config.php';
define('SYH_CONFIG', $syhConfig);

require SYH_ROOT . '/src/Database.php';

date_default_timezone_set((string) ($syhConfig['timezone'] ?? 'Asia/Shanghai'));

$isCli    = PHP_SAPI === 'cli';
$lockFile = SYH_ROOT . '/data/installed.lock';

/**
 * 已经装过就不要再动数据库，避免覆盖现有内容。
 */
function syh_installed(string $lockFile, array $config): bool
{
    if (is_file($lockFile)) {
        return true;
    }
    if (!is_file((string) $config['db'])) {
        return false;
    }
    try {
        $pdo = DB::pdo();
        return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function syh_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function syh_guess_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base   = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
    $base   = rtrim($base, '/');

    return $scheme . '://' . $host . $base;
}

/**
 * 写入初始数据。返回 [ok, 消息列表]。
 */
function syh_install_run(array $input, string $lockFile): array
{
    $siteUrl  = rtrim(trim((string) ($input['site_url'] ?? '')), '/');
    $siteName = trim((string) ($input['site_name'] ?? ''));
    $admin    = trim((string) ($input['admin_user'] ?? ''));
    $pass     = (string) ($input['admin_pass'] ?? '');
    $email    = trim((string) ($input['admin_email'] ?? ''));

    $errors = [];
    if ($siteUrl === '' || !preg_match('#^https?://#i', $siteUrl) || !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = '站点地址需要是完整的 http(s) 链接，例如 https://example.com。';
    }
    if ($siteName === '' || mb_strlen($siteName) > 60) {
        $errors[] = '站点名称不能为空，且不超过 60 个字。';
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $admin)) {
        $errors[] = '管理员账号只能使用字母、数字、点、下划线或短横线，长度 3 - 60 位。';
    }
    if (strlen($pass) < 6) {
        $errors[] = '管理员密码至少 6 位。';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '管理员邮箱格式不正确。';
    }
    if ($errors) {
        return [false, $errors];
    }

    $pdo = DB::pdo();
    $now = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        $options = [
            'site_name'              => $siteName,
            'site_description'       => '记录技术、折腾与生活。',
            'site_url'               => $siteUrl,
            'posts_per_page'         => '10',
            'timezone'               => 'Asia/Shanghai',
            'site_launch'            => $now,
            'homepage_banner_text'   => '记录技术、折腾与生活。',
            'homepage_cover_image'   => '',
            'comment_moderation'     => '1',
            'comment_whitelist'      => '1',
            'require_name_email'     => '1',
            'mail_enabled'           => '0',
            'commenter_notify'       => '1',
            'custom_css'             => '',
            'profile_title'          => '',
            'profile_tagline'        => '',
            'profile_intro'          => '',
            'profile_location'       => '',
            'profile_experience'     => '',
            'profile_stack'          => '',
            'links_page_intro'       => '这里是常去的一些站点，欢迎随便逛逛。',
            'links_page_apply_title' => '申请友链',
            'links_page_apply_contact' => '',
            'links_page_apply_content' => "欢迎交换友链。\n\n请先在自己的站点加上本站链接，然后通过留言板或邮件告诉我：站点名称、地址、一句话简介。\n\n内容健康、能正常访问的站点都会尽快加上。",
            'links_page_site_intro'  => '一个安静记录想法的地方。',
        ];
        $optStmt = $pdo->prepare('INSERT INTO options (name, value) VALUES (?, ?) ON CONFLICT(name) DO UPDATE SET value = excluded.value');
        foreach ($options as $name => $value) {
            $optStmt->execute([$name, (string) $value]);
        }

        $userStmt = $pdo->prepare('INSERT INTO users (login, email, pass, display_name, role, registered_at) VALUES (?, ?, ?, ?, ?, ?)');
        $userStmt->execute([
            $admin,
            $email,
            password_hash($pass, PASSWORD_DEFAULT),
            $admin === 'admin' ? '站长' : $admin,
            'admin',
            $now,
        ]);

        $termStmt = $pdo->prepare('INSERT INTO terms (taxonomy, name, slug, description) VALUES (?, ?, ?, ?)');
        $termStmt->execute(['category', '随笔', 'essay', '零散的想法和日常记录']);
        $termId = (int) $pdo->lastInsertId();

        $postStmt = $pdo->prepare(
            'INSERT INTO posts (type, status, slug, title, content, excerpt, author_id, created_at, updated_at, comment_status, menu_order, template)
             VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)'
        );

        $helloContent = <<<'HTML'
<p>站点装好了，第一篇文章还空着。与其套一段欢迎模板，不如先把它当成一张便签，记下这里准备写些什么。</p>

<h2>打算写点什么</h2>
<p>技术笔记、折腾记录、读书摘要，或者某个下午突然想明白的一件小事。博客不用宏大，能持续写下去就行。</p>
<blockquote><p>先写起来，再慢慢变好。</p></blockquote>

<h2>上手提示</h2>
<ul>
<li>后台入口在 <code>/admin/</code>，默认账号 <code>admin</code>，密码 <code>password</code>，登录后请第一时间改掉。</li>
<li>「外观」里可以换主题色、Logo、favicon、首页封面和横幅文字，也能追加自定义 CSS。</li>
<li>文章支持目录、代码块和评论；菜单、友链、备案信息都在后台维护。</li>
</ul>

<h2>一段示例代码</h2>
<pre><code class="language-php">echo 'Hello, world!';</code></pre>

<p>这篇示例可以直接删掉，换成你自己的开场白。</p>
HTML;

        $guideContent = <<<'HTML'
<p>这套程序的默认样子已经很克制，想改的话，大部分设置都在后台完成，不用碰代码。</p>

<h2>站点信息</h2>
<p>「设置」里可以修改站点名称、描述、地址、每页文章数和时区。站点地址请填完整的 http(s) 链接，末尾不要带斜杠。</p>

<h2>外观</h2>
<p>「外观」页面支持实时预览：主题色、背景色、文字色、Logo、favicon、首页封面和横幅文字都能直接调。页面底部还有一块「自定义 CSS」，适合做小幅调整，例如：</p>
<pre><code class="language-css">:root {
    --primary: #0a84ff;
    --bg: #f7f7f8;
    --text: #1d1d1f;
}</code></pre>
<p>改动会追加在主题样式之后，保存即可生效。</p>

<h2>页面与导航</h2>
<p>「页面」里新建的内容可以指定模板：友链页使用 <code>page-links.php</code>，留言板使用 <code>page-guestbook.php</code>。菜单在「菜单」中维护，支持把页面挂到导航上。</p>

<h2>评论与邮件</h2>
<p>评论默认需要审核，避免垃圾信息直接出现。需要邮件提醒时，在「设置 - 邮件通知」里填 SMTP 参数并勾选启用；QQ、163 邮箱使用的是授权码，不是登录密码。</p>

<h2>备份</h2>
<p>所有内容都保存在 <code>data/site.sqlite</code>，连同 <code>public/wp-content/uploads</code> 一起备份即可。恢复时把文件和目录放回原位就行。</p>
HTML;

        $postStmt->execute(['post', 'publish', 'hello-world', '你好，世界', $helloContent, '站点装好后的第一篇示例文章，可以删掉换成你自己的开场白。', $now, $now, 'open', 0, '']);
        $hellId = (int) $pdo->lastInsertId();
        $postStmt->execute(['post', 'publish', 'make-it-yours', '把站点改成自己的样子', $guideContent, '从站点信息、外观到页面模板，一份简短的自定义指南。', $now, $now, 'open', 0, '']);
        $guideId = (int) $pdo->lastInsertId();

        $aboutContent = <<<'HTML'
<p>这里是一个个人博客，主要记录技术、折腾和日常生活。</p>
<p>如果你也想留言，可以去<a href="/guestbook/">留言板</a>；想交换友链，页脚有入口。</p>
HTML;

        $linksContent = <<<'HTML'
<p>下面是一些常去的站点。如果它们的更新对你有帮助，不妨点进去看看。</p>
HTML;

        $guestbookContent = <<<'HTML'
<p>欢迎在这里留下想说的话。广告和无关内容会被删除，请见谅。</p>
HTML;

        $postStmt->execute(['page', 'publish', 'about', '关于', $aboutContent, '', $now, $now, 'closed', 1, '']);
        $aboutId = (int) $pdo->lastInsertId();
        $postStmt->execute(['page', 'publish', 'links', '友链', $linksContent, '', $now, $now, 'closed', 2, 'page-links.php']);
        $linksId = (int) $pdo->lastInsertId();
        $postStmt->execute(['page', 'publish', 'guestbook', '留言板', $guestbookContent, '', $now, $now, 'open', 3, 'page-guestbook.php']);
        $guestbookId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO post_terms (post_id, term_id) VALUES (?, ?)')->execute([$hellId, $termId]);

        $menuStmt = $pdo->prepare('INSERT INTO menu_items (menu, parent_id, label, url, target, sort, kind, object_id) VALUES (?, 0, ?, ?, ?, ?, ?, ?)');
        $menuStmt->execute(['primary', '首页', '/', '', 1, 'custom', 0]);
        $menuStmt->execute(['primary', '关于', '/about/', '', 2, 'page', $aboutId]);
        $menuStmt->execute(['primary', '友链', '/links/', '', 3, 'page', $linksId]);
        $menuStmt->execute(['primary', '留言板', '/guestbook/', '', 4, 'page', $guestbookId]);

        $linkStmt = $pdo->prepare('INSERT INTO links (grp, name, url, image, image_dark, description, rss, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $linkStmt->execute(['朋友们', 'suyihang15', 'https://suyihang15.com', '', '', '我的个人网站', '', 1]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [false, ['写入数据库失败：' . $e->getMessage()]];
    }

    $dir = dirname($lockFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $written = @file_put_contents($lockFile, "installed_at=" . $now . "\nversion=1\n");
    if ($written === false) {
        return [false, ['数据已写入，但无法创建 ' . $lockFile . '，请检查 data 目录权限。']];
    }

    return [true, [
        '站点地址：' . $siteUrl,
        '后台入口：' . $siteUrl . '/admin/',
        '管理员账号：' . $admin,
        '管理员密码：' . $pass,
    ]];
}

/* ---------------------------------------------------------------- CLI ---- */

if ($isCli) {
    if (in_array('--help', $argv ?? [], true) || in_array('-h', $argv ?? [], true)) {
        fwrite(STDOUT, "用法：php public/install.php [选项]\n\n");
        fwrite(STDOUT, "  --site-url=https://example.com   站点地址（必填）\n");
        fwrite(STDOUT, "  --site-name=我的博客             站点名称（默认：我的博客）\n");
        fwrite(STDOUT, "  --admin-user=admin               管理员账号（默认：admin）\n");
        fwrite(STDOUT, "  --admin-pass=password            管理员密码（默认：password）\n");
        fwrite(STDOUT, "  --admin-email=you@example.com    管理员邮箱（可选）\n");
        exit(0);
    }

    if (syh_installed($lockFile, $syhConfig)) {
        fwrite(STDERR, "已经安装过了。如需重装，请先备份并删除 data/site.sqlite 与 data/installed.lock。\n");
        exit(1);
    }

    $args = [];
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $pair = explode('=', substr($arg, 2), 2);
        $args[$pair[0]] = $pair[1] ?? '';
    }

    $input = [
        'site_url'    => $args['site-url'] ?? 'http://localhost',
        'site_name'   => $args['site-name'] ?? '我的博客',
        'admin_user'  => $args['admin-user'] ?? 'admin',
        'admin_pass'  => $args['admin-pass'] ?? 'password',
        'admin_email' => $args['admin-email'] ?? '',
    ];

    [$ok, $messages] = syh_install_run($input, $lockFile);
    if (!$ok) {
        fwrite(STDERR, implode("\n", $messages) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "安装完成。\n");
    foreach ($messages as $line) {
        fwrite(STDOUT, '  ' . $line . "\n");
    }
    fwrite(STDOUT, "请删除 public/install.php，并尽快修改管理员密码。\n");
    exit(0);
}

/* ---------------------------------------------------------------- Web ---- */

if (syh_installed($lockFile, $syhConfig)) {
    http_response_code(200);
    ?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>已经安装</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f5f7;color:#1d1d1f;font:16px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.card{max-width:520px;margin:24px;background:#fff;border-radius:16px;padding:32px;box-shadow:0 10px 40px rgba(0,0,0,.08)}
h1{margin:0 0 12px;font-size:22px}
p{margin:8px 0;color:#424245}
code{background:#f0f0f2;border-radius:5px;padding:2px 6px}
</style>
</head>
<body>
<div class="card">
    <h1>站点已经安装过了</h1>
    <p>为了不覆盖现有数据，安装程序不会再执行。</p>
    <p>如果确认要重装，请先备份并删除 <code>data/site.sqlite</code> 和 <code>data/installed.lock</code>。</p>
    <p>另外，别忘了删除 <code>public/install.php</code>。</p>
</div>
</body>
</html>
    <?php
    exit;
}

$form = [
    'site_url'    => syh_guess_url(),
    'site_name'   => '我的博客',
    'admin_user'  => 'admin',
    'admin_pass'  => 'password',
    'admin_email' => '',
];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    foreach (array_keys($form) as $key) {
        $form[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    [$ok, $messages] = syh_install_run($form, $lockFile);
    if ($ok) {
        http_response_code(200);
        ?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>安装完成</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f5f7;color:#1d1d1f;font:16px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.card{max-width:560px;margin:24px;background:#fff;border-radius:16px;padding:32px;box-shadow:0 10px 40px rgba(0,0,0,.08)}
h1{margin:0 0 16px;font-size:22px}
ul{padding-left:20px;margin:12px 0}
li{margin:6px 0}
a{color:#0071e3;text-decoration:none}
.note{margin-top:20px;padding:12px 14px;background:#fff7e6;border-radius:10px;color:#8a5a00;font-size:14px}
</style>
</head>
<body>
<div class="card">
    <h1>安装完成</h1>
    <ul>
        <?php foreach ($messages as $line): ?>
        <li><?= syh_h($line) ?></li>
        <?php endforeach; ?>
    </ul>
    <p><a href="/">打开站点</a> · <a href="/admin/">进入后台</a></p>
    <div class="note">请立即删除 <code>public/install.php</code>，并在后台修改管理员密码。</div>
</div>
</body>
</html>
        <?php
        exit;
    }
    $errors = $messages;
}

?>
<!DOCTYPE html>
<html lang="zh-Hans">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>安装站点</title>
<style>
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f5f7;color:#1d1d1f;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.card{width:min(560px,calc(100% - 32px));margin:32px 0;background:#fff;border-radius:18px;padding:32px;box-shadow:0 12px 48px rgba(0,0,0,.09)}
h1{margin:0 0 6px;font-size:24px}
.sub{margin:0 0 24px;color:#6e6e73;font-size:14px}
label{display:block;margin:16px 0 6px;font-size:14px;font-weight:600}
input{width:100%;padding:10px 12px;border:1px solid #d2d2d7;border-radius:9px;font:inherit;background:#fff}
input:focus{outline:2px solid rgba(0,113,227,.35);border-color:#0071e3}
.hint{margin:5px 0 0;color:#86868b;font-size:12px}
.errors{margin:0 0 18px;padding:12px 16px;background:#fff1f0;border-radius:10px;color:#b3261e;font-size:14px}
.errors ul{margin:0;padding-left:18px}
button{margin-top:24px;width:100%;padding:12px;border:0;border-radius:10px;background:#0071e3;color:#fff;font:inherit;font-weight:600;cursor:pointer}
button:hover{background:#0077ed}
.foot{margin-top:18px;color:#86868b;font-size:12px;line-height:1.6}
</style>
</head>
<body>
<form class="card" method="post" action="">
    <h1>安装站点</h1>
    <p class="sub">填写基础信息，几秒钟就能开始写。数据库使用 SQLite，无需额外配置。</p>

    <?php if ($errors): ?>
    <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?= syh_h($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <label for="site_url">站点地址</label>
    <input id="site_url" name="site_url" type="url" value="<?= syh_h($form['site_url']) ?>" placeholder="https://example.com" required>
    <p class="hint">完整的 http(s) 地址，末尾不要带斜杠。装好后可以在后台修改。</p>

    <label for="site_name">站点名称</label>
    <input id="site_name" name="site_name" type="text" value="<?= syh_h($form['site_name']) ?>" maxlength="60" required>

    <label for="admin_user">管理员账号</label>
    <input id="admin_user" name="admin_user" type="text" value="<?= syh_h($form['admin_user']) ?>" required>

    <label for="admin_pass">管理员密码</label>
    <input id="admin_pass" name="admin_pass" type="password" value="<?= syh_h($form['admin_pass']) ?>" required>
    <p class="hint">默认是 password。安装后请立即在后台改成自己的密码。</p>

    <label for="admin_email">管理员邮箱（可选）</label>
    <input id="admin_email" name="admin_email" type="email" value="<?= syh_h($form['admin_email']) ?>" placeholder="you@example.com">
    <p class="hint">用于登录和接收评论通知，可以稍后再填。</p>

    <button type="submit">开始安装</button>
    <p class="foot">安装程序会创建 <code>data/site.sqlite</code>、示例文章和页面，并写入 <code>data/installed.lock</code>。完成后请删除 <code>public/install.php</code>。</p>
</form>
</body>
</html>
