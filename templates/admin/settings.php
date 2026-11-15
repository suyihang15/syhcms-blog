<?php
$value = static fn (string $key): string => (string) setting($key, '');

$text = static function (string $key, string $label, string $hint = '', string $type = 'text', string $placeholder = ''): void {
    ?>
    <div class="admin-form-row">
        <label for="<?= e($key) ?>"><?= e($label) ?></label>
        <input class="admin-input" type="<?= e($type) ?>" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e((string) setting($key, '')) ?>"<?= $placeholder !== '' ? ' placeholder="' . e($placeholder) . '"' : '' ?>>
        <?php if ($hint !== ''): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
    </div>
    <?php
};

$textarea = static function (string $key, string $label, string $hint = '', int $height = 90): void {
    ?>
    <div class="admin-form-row">
        <label for="<?= e($key) ?>"><?= e($label) ?></label>
        <textarea class="admin-textarea" id="<?= e($key) ?>" name="<?= e($key) ?>" style="min-height:<?= $height ?>px;"><?= e((string) setting($key, '')) ?></textarea>
        <?php if ($hint !== ''): ?><span class="hint"><?= e($hint) ?></span><?php endif; ?>
    </div>
    <?php
};

$timezone = $value('timezone') ?: 'Asia/Shanghai';
$zones = ['Asia/Shanghai', 'Asia/Hong_Kong', 'Asia/Taipei', 'Asia/Tokyo', 'Asia/Singapore', 'UTC', 'Europe/London', 'America/New_York', 'America/Los_Angeles'];
$launchRaw = $value('site_launch');
$launchTs  = $launchRaw !== '' ? strtotime($launchRaw) : false;
$launchValue = $launchTs !== false ? date('Y-m-d\TH:i', $launchTs) : '';
$smtpSecure = $value('smtp_secure') ?: 'ssl';
$hasSmtpPass = $value('smtp_pass') !== '';
$notifyTo = $value('notify_email') !== '' ? $value('notify_email') : Mailer::recipient();
?>
<div class="admin-page-head">
    <div>
        <h2>站点设置</h2>
        <p>修改后点底部“保存设置”生效；带 * 的项建议填写。</p>
    </div>
</div>

<form action="<?= e(admin_url('settings/')) ?>" method="post">
    <?= Auth::csrfField() ?>

    <div class="admin-settings-grid">
        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>站点信息</h2><p>名称、地址与基础参数。</p></div></div>
            <div class="admin-form">
                <div class="admin-form-grid-2">
                    <?php $text('site_name', '站点名称'); ?>
                    <?php $text('site_url', '站点地址', '完整 http(s) URL，不要以 / 结尾。', 'url', 'https://suyihang15.com'); ?>
                </div>
                <?php $textarea('site_description', '站点描述', '用于首页与搜索引擎摘要。', 78); ?>
                <div class="admin-form-grid-2">
                    <?php $text('posts_per_page', '每页文章数', '1 - 50。', 'number'); ?>
                    <div class="admin-form-row">
                        <label for="timezone">时区</label>
                        <select class="admin-select" id="timezone" name="timezone">
                            <?php if (!in_array($timezone, $zones, true)): ?><option value="<?= e($timezone) ?>" selected><?= e($timezone) ?></option><?php endif; ?>
                            <?php foreach ($zones as $zone): ?>
                            <option value="<?= e($zone) ?>"<?= $timezone === $zone ? ' selected' : '' ?>><?= e($zone) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label for="site_launch">建站时间</label>
                    <input class="admin-input" type="datetime-local" id="site_launch" name="site_launch" value="<?= e($launchValue) ?>">
                    <span class="hint">用于页脚运行时长统计。</span>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>外观与首页</h2><p>Logo、图标、配色与首页封面已移至独立的外观页面。</p></div></div>
            <div class="admin-form">
                <p class="admin-muted admin-small" style="margin:0;line-height:1.7;">外观页面提供实时预览：改站点名称、Logo、favicon、主题色、背景图、首页封面和横幅文字时可以马上看到效果。</p>
                <div>
                    <a class="admin-btn admin-btn-primary" href="<?= e(admin_url('appearance/')) ?>">打开外观设置</a>
                </div>
            </div>
        </div>

        <div class="admin-panel" id="live2d">
            <div class="admin-panel-head"><div><h2>看板娘</h2><p>前台右下角的 Live2D 形象：选模型、调尺寸、设置她说什么。</p></div></div>
            <div class="admin-form">
                <?php
                $live2dModels    = live2d_models();
                $live2dSelected  = (string) setting('live2d_model', '');
                $live2dPreview   = live2d_model_preview();
                $live2dLabel     = live2d_active_label();
                $live2dRef       = live2d_model_path();
                $live2dGreeting  = (string) setting('live2d_greeting', '');
                $live2dLines     = (string) setting('live2d_lines', '');
                $live2dLimit     = php_upload_limit();
                ?>
                <div class="mascot-admin">
                    <div class="mascot-admin-preview">
                        <?php if ($live2dPreview !== ''): ?>
                        <img src="<?= e($live2dPreview) ?>" alt="当前看板娘预览" loading="lazy" decoding="async">
                        <?php else: ?>
                        <span class="mascot-admin-empty">暂无预览</span>
                        <?php endif; ?>
                    </div>
                    <div class="mascot-admin-meta">
                        <p class="mascot-admin-name">当前模型：<strong><?= e($live2dLabel !== '' ? $live2dLabel : '未安装') ?></strong></p>
                        <p class="admin-muted admin-small">引用文件：<code><?= e($live2dRef !== '' ? $live2dRef : '无') ?></code></p>
                        <p class="admin-muted admin-small">预览图是模型自带的方形图标，只作占位；前台加载完成后显示的是完整 Live2D 形象。</p>
                        <p class="admin-muted admin-small">授权：仅限本站展示，不随站点打包分发；公开页面需保留页脚署名（已自动输出）。</p>
                    </div>
                </div>
                <label class="admin-check">
                    <input type="checkbox" name="live2d_enabled" value="1"<?= setting_bool('live2d_enabled', true) ? ' checked' : '' ?>>
                    <span>启用看板娘（关闭后前台不输出容器、不请求模型资源）</span>
                </label>
                <div class="admin-form-row">
                    <label for="live2d_model">使用模型</label>
                    <select class="admin-select" id="live2d_model" name="live2d_model">
                        <option value=""<?= $live2dSelected === '' || !isset($live2dModels[$live2dSelected]) ? ' selected' : '' ?>>自动（第一个可用模型）</option>
                        <?php foreach ($live2dModels as $live2dRel => $live2dName): ?>
                        <option value="<?= e($live2dRel) ?>"<?= $live2dSelected === $live2dRel ? ' selected' : '' ?>><?= e($live2dName) ?><?= str_contains($live2dRel, '/') ? ' · ' . e($live2dRel) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">上传与删除在页尾「看板娘模型管理」，单个 zip 上限 <?= e(human_bytes($live2dLimit)) ?>。</span>
                </div>
                <div class="admin-form-grid-2">
                    <div class="admin-form-row">
                        <label>显示尺寸</label>
                        <p class="admin-muted admin-small" style="margin:0;line-height:1.7;">固定为「中」且不可切换：桌面端约 180px 宽（窄屏约 156px），手机端再缩一档。大档会压住正文和侧栏，已下线。</p>
                    </div>
                    <div class="admin-form-row">
                        <label>显示与交互</label>
                        <label class="admin-check">
                            <input type="checkbox" name="live2d_mobile" value="1"<?= setting_bool('live2d_mobile', true) ? ' checked' : '' ?>>
                            <span>手机端也显示看板娘</span>
                        </label>
                        <label class="admin-check">
                            <input type="checkbox" name="live2d_bubble" value="1"<?= setting_bool('live2d_bubble', true) ? ' checked' : '' ?>>
                            <span>显示台词气泡</span>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label for="live2d_greeting">开场白</label>
                    <input class="admin-input" type="text" id="live2d_greeting" name="live2d_greeting" maxlength="40"
                           value="<?= e($live2dGreeting) ?>" placeholder="<?= e(live2d_default_greeting()) ?>">
                    <span class="hint">首次加载完成后在气泡里说一句，留空使用默认：<?= e(live2d_default_greeting()) ?></span>
                </div>
                <div class="admin-form-row">
                    <label for="live2d_lines">点击台词</label>
                    <textarea class="admin-input" id="live2d_lines" name="live2d_lines" rows="5"
                              placeholder="<?= e(implode("\n", live2d_default_lines())) ?>"><?= e($live2dLines) ?></textarea>
                    <span class="hint">一行一句，最多 8 句、每句 40 字；点击形象时依次轮播（碰到头部会先说“摸头好舒服～”）。留空使用默认台词。</span>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>个人资料</h2><p>关于页与首页署名信息。</p></div></div>
            <div class="admin-form">
                <div class="admin-form-grid-2">
                    <?php $text('profile_title', '显示名称', '留空时使用站点名称。'); ?>
                    <?php $text('profile_tagline', '一句话签名'); ?>
                </div>
                <?php $textarea('profile_intro', '个人简介', '留空时使用站点描述。', 86); ?>
                <div class="admin-form-grid-2">
                    <?php $text('profile_location', '所在地', '', 'text', '贵州'); ?>
                    <?php $text('profile_experience', '经验 / 身份', '', 'text', '技术爱好者'); ?>
                    <?php $text('profile_stack', '技术栈', '', 'text', 'python'); ?>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>社交</h2><p>页脚与关于页展示。</p></div></div>
            <div class="admin-form">
                <div class="admin-form-grid-2">
                    <?php $text('social_qq_qr', 'QQ 二维码图片', '图片地址，留空则不显示。'); ?>
                    <?php $text('social_wechat_qr', '微信二维码图片', '图片地址，留空则不显示。'); ?>
                    <?php $text('social_bilibili', 'B 站主页', '', 'url', 'https://space.bilibili.com/...'); ?>
                    <?php $text('social_github', 'GitHub 主页', '', 'url', 'https://github.com/...'); ?>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>赞赏</h2><p>赞赏说明与收款二维码。</p></div></div>
            <div class="admin-form">
                <?php $textarea('donation_description', '赞赏说明', '', 70); ?>
                <div class="admin-form-grid-2">
                    <?php $text('donation_wechat', '微信赞赏说明', '可填写微信号或提示文字。'); ?>
                    <?php $text('donation_alipay', '支付宝赞赏说明', '可填写账号或提示文字。'); ?>
                    <?php $text('donation_wechat_qr', '微信赞赏二维码', '图片地址。'); ?>
                    <?php $text('donation_alipay_qr', '支付宝赞赏二维码', '图片地址。'); ?>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>友链页面</h2><p>申请说明与本站信息。</p></div></div>
            <div class="admin-form">
                <?php $textarea('links_page_intro', '页面介绍', '', 70); ?>
                <div class="admin-form-grid-2">
                    <?php $text('links_page_apply_title', '申请标题', '留空时显示“申请友链”。'); ?>
                    <?php $text('links_page_apply_contact', '申请联系方式'); ?>
                </div>
                <?php $textarea('links_page_apply_content', '申请说明', '支持纯文本，换行会保留。', 100); ?>
                <?php $text('links_page_site_intro', '本站信息', '用于友链申请页展示的站点简介。'); ?>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>备案信息</h2><p>页脚展示的备案编号。</p></div></div>
            <div class="admin-form">
                <div class="admin-form-grid-2">
                    <?php $text('icp_record_number', 'ICP 备案号'); ?>
                    <?php $text('icp_record_url', 'ICP 备案链接', '', 'url', 'https://beian.miit.gov.cn'); ?>
                    <?php $text('psb_record_number', '公安备案号'); ?>
                    <?php $text('psb_record_url', '公安备案链接', '', 'url', 'https://beian.mps.gov.cn'); ?>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>评论</h2><p>审核与展示规则。</p></div></div>
            <div class="admin-form">
                <?php foreach ([
                    'comment_moderation' => '新评论需要审核后显示',
                    'comment_whitelist'  => '已审核通过作者的评论直接显示',
                    'require_name_email' => '评论必须填写昵称和邮箱',
                ] as $key => $label): ?>
                <label class="admin-check">
                    <input type="checkbox" name="<?= e($key) ?>" value="1"<?= (string) setting($key, '0') === '1' ? ' checked' : '' ?>>
                    <span><?= e($label) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-panel" id="mail">
            <div class="admin-panel-head">
                <div><h2>邮件通知</h2><p>评论提醒与测试邮件，SMTP 参数保存在本站设置中。</p></div>
            </div>
            <div class="admin-form">
                <label class="admin-check">
                    <input type="checkbox" name="mail_enabled" value="1"<?= (string) setting('mail_enabled', '0') === '1' ? ' checked' : '' ?>>
                    <span>启用邮件通知</span>
                </label>
                <label class="admin-check">
                    <input type="checkbox" name="commenter_notify" value="1"<?= setting_bool('commenter_notify', true) ? ' checked' : '' ?>>
                    <span>评论人通知（审核通过时通知作者，被回复时通知原评论者）</span>
                </label>
                <div class="admin-form-row">
                    <label for="smtp_preset">常用邮箱服务商</label>
                    <div class="smtp-preset-row">
                        <select class="admin-select" id="smtp_preset" data-smtp-preset>
                            <option value="">选择后自动填入主机、端口与加密方式</option>
                            <option value="smtp.qq.com|465|ssl">QQ 邮箱（smtp.qq.com:465 SSL）</option>
                            <option value="smtp.163.com|465|ssl">163 邮箱（smtp.163.com:465 SSL）</option>
                            <option value="smtp.126.com|465|ssl">126 邮箱（smtp.126.com:465 SSL）</option>
                            <option value="smtp.gmail.com|587|tls">Gmail（smtp.gmail.com:587 STARTTLS）</option>
                            <option value="smtp-mail.outlook.com|587|tls">Outlook（smtp-mail.outlook.com:587 STARTTLS）</option>
                        </select>
                    </div>
                    <span class="hint">QQ、163、126 邮箱请使用“授权码”作为 SMTP 密码，不是登录密码。</span>
                </div>
                <div class="admin-form-grid-2">
                    <div class="admin-form-row">
                        <label for="smtp_host">SMTP 主机 *</label>
                        <input class="admin-input" type="text" id="smtp_host" name="smtp_host" value="<?= e($value('smtp_host')) ?>" placeholder="smtp.example.com">
                    </div>
                    <div class="admin-form-row">
                        <label for="smtp_port">端口</label>
                        <input class="admin-input" type="number" id="smtp_port" name="smtp_port" value="<?= e($value('smtp_port') !== '' ? $value('smtp_port') : '465') ?>" min="1" max="65535">
                    </div>
                    <div class="admin-form-row">
                        <label for="smtp_secure">加密方式</label>
                        <select class="admin-select" id="smtp_secure" name="smtp_secure">
                            <option value="ssl"<?= $smtpSecure === 'ssl' ? ' selected' : '' ?>>SSL（常用 465）</option>
                            <option value="tls"<?= $smtpSecure === 'tls' ? ' selected' : '' ?>>STARTTLS（常用 587）</option>
                            <option value="none"<?= $smtpSecure === 'none' ? ' selected' : '' ?>>不加密（仅内网）</option>
                        </select>
                    </div>
                </div>
                <div class="admin-form-grid-2">
                    <div class="admin-form-row">
                        <label for="smtp_user">SMTP 用户名</label>
                        <input class="admin-input" type="text" id="smtp_user" name="smtp_user" value="<?= e($value('smtp_user')) ?>" autocomplete="off">
                        <span class="hint">通常与发件邮箱相同。</span>
                    </div>
                    <div class="admin-form-row">
                        <label for="smtp_pass">SMTP 密码 / 授权码</label>
                        <div class="smtp-pass-row">
                            <input class="admin-input" type="password" id="smtp_pass" name="smtp_pass" value="" autocomplete="new-password" placeholder="<?= $hasSmtpPass ? '已保存，留空则不修改' : '未设置' ?>">
                            <button class="admin-btn admin-btn-sm" type="button" data-toggle-pass="smtp_pass" aria-controls="smtp_pass" aria-pressed="false">显示</button>
                        </div>
                        <?php if ($hasSmtpPass): ?>
                        <label class="admin-check"><input type="checkbox" name="smtp_pass_clear" value="1"><span>清除已保存的密码</span></label>
                        <?php endif; ?>
                    </div>
                    <div class="admin-form-row">
                        <label for="smtp_from">发件地址 *</label>
                        <input class="admin-input" type="email" id="smtp_from" name="smtp_from" value="<?= e($value('smtp_from')) ?>" placeholder="noreply@example.com">
                    </div>
                    <div class="admin-form-row">
                        <label for="smtp_from_name">发件人名称</label>
                        <input class="admin-input" type="text" id="smtp_from_name" name="smtp_from_name" value="<?= e($value('smtp_from_name')) ?>" placeholder="<?= e(site_name()) ?>">
                    </div>
                </div>
                <div class="admin-form-row">
                    <label for="notify_email">通知接收邮箱</label>
                    <input class="admin-input" type="email" id="notify_email" name="notify_email" value="<?= e($value('notify_email')) ?>" placeholder="留空则发送给第一个管理员邮箱">
                </div>
                <div class="admin-note">密码只保存在本站 SQLite 中，页面不会回显；保存后可在页面底部发送测试邮件。</div>
            </div>
        </div>
    </div>

    <div class="admin-savebar">
        <button class="admin-btn admin-btn-primary" type="submit">保存设置</button>
        <span class="admin-small admin-muted">修改站点地址后请确认域名与证书配置。</span>
    </div>
</form>

<div class="admin-panel" id="live2d-manage" style="margin-top:18px;">
    <div class="admin-panel-head">
        <div><h2>看板娘模型管理</h2><p>上传 Cubism 3/4 模型压缩包，或删除不再使用的模型目录。</p></div>
    </div>
    <form class="admin-form" action="<?= e(admin_url('settings/live2d-upload/')) ?>" method="post" enctype="multipart/form-data">
        <?= Auth::csrfField() ?>
        <div class="admin-form-grid-2">
            <div class="admin-form-row">
                <label for="live2d-model-zip">模型压缩包（zip）</label>
                <input class="admin-input" type="file" id="live2d-model-zip" name="model" accept=".zip,application/zip" required>
                <span class="hint">把含 .model3.json、.moc3、贴图和动作的模型文件夹整体压缩上传；当前服务器单个 zip 上限 <?= e(human_bytes(php_upload_limit())) ?>（PHP upload_max_filesize / post_max_size），解压后不超过 120MB。</span>
            </div>
            <div class="admin-form-row">
                <label for="live2d-model-dir">存放目录名</label>
                <input class="admin-input" type="text" id="live2d-model-dir" name="dir" placeholder="留空则使用压缩包文件名">
                <span class="hint">仅字母、数字、点、下划线、短横线；重名会自动加后缀。</span>
            </div>
        </div>
        <div><button class="admin-btn admin-btn-primary" type="submit">上传并启用</button></div>
    </form>

    <?php $live2dInstalled = live2d_models(); $live2dActive = (string) setting('live2d_model', ''); $live2dInUse = $live2dActive !== '' ? $live2dActive : live2d_active_model(); ?>
    <div class="admin-table-wrap" style="margin-top:16px;">
        <?php if ($live2dInstalled): ?>
        <table class="admin-table">
            <thead><tr><th>模型</th><th>模型文件</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($live2dInstalled as $live2dRel => $live2dName): ?>
            <?php $live2dFolder = explode('/', $live2dRel)[0]; $live2dUsed = $live2dInUse !== '' && str_starts_with($live2dInUse, $live2dFolder . '/'); $live2dDeletable = str_contains($live2dRel, '/') && !$live2dUsed && is_dir(live2d_model_dir() . '/' . $live2dFolder); ?>
            <tr>
                <td><?= e($live2dName) ?><?= $live2dInUse === $live2dRel ? ' <span class="admin-badge">当前</span>' : '' ?><?= $live2dUsed && $live2dInUse !== $live2dRel ? ' <span class="admin-badge">使用中</span>' : '' ?></td>
                <td class="admin-mono admin-small"><?= e($live2dRel) ?></td>
                <td class="admin-cell-actions">
                    <?php if ($live2dDeletable): ?>
                    <form action="<?= e(admin_url('settings/live2d-delete/')) ?>" method="post" data-confirm="确定删除模型目录“<?= e($live2dFolder) ?>”吗？删除后需要重新上传才能恢复。">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="model" value="<?= e($live2dFolder) ?>">
                        <button class="admin-btn admin-btn-sm admin-btn-danger" type="submit">删除</button>
                    </form>
                    <?php elseif ($live2dUsed): ?>
                    <span class="admin-muted admin-small">使用中，先切换再删除</span>
                    <?php else: ?>
                    <span class="admin-muted admin-small">随包模型</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="admin-empty">还没有发现模型，请上传 zip 压缩包。</div>
        <?php endif; ?>
    </div>
    <div class="admin-note">仅识别 Cubism 3/4 的 .model3.json 套装；Cubism 2 的 .model.json 不会被识别。请确认模型授权允许在你的站点使用。</div>
</div>

<div class="admin-panel" style="margin-top:18px;" id="mail-test-panel">
    <div class="admin-panel-head">
        <div><h2>发送测试邮件</h2><p>先保存 SMTP 配置，再向指定邮箱发送测试；结果会显示在下方。</p></div>
    </div>
    <form class="mail-test-form" id="mail-test-form" action="<?= e(admin_url('settings/mail-test/')) ?>" method="post">
        <?= Auth::csrfField() ?>
        <input class="admin-input" type="email" name="to" value="<?= e($notifyTo) ?>" placeholder="收件邮箱" required>
        <button class="admin-btn" type="submit">发送测试</button>
        <span class="mail-test-status" id="mail-test-status" role="status" aria-live="polite"></span>
    </form>
</div>
<?php $settingsJsVer = is_file(SYH_ROOT . '/public/assets/admin-settings.js') ? (string) filemtime(SYH_ROOT . '/public/assets/admin-settings.js') : '1'; ?>
<script src="/assets/admin-settings.js?v=<?= e($settingsJsVer) ?>"></script>
