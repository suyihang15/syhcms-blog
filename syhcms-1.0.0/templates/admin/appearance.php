<?php
$primary   = (string) setting('primary_color', '');
$bgColor   = (string) setting('background_color', '');
$textColor = (string) setting('text_color', '');
$primaryVal   = preg_match('/^#[0-9a-f]{6}$/i', $primary) ? $primary : '';
$bgColorVal   = preg_match('/^#[0-9a-f]{6}$/i', $bgColor) ? $bgColor : '';
$textColorVal = preg_match('/^#[0-9a-f]{6}$/i', $textColor) ? $textColor : '';

$logoId    = (int) setting('custom_logo', 0);
$iconId    = (int) setting('site_icon', 0);
$logoUrl   = logo_url();
$iconUrl   = site_icon_url();
$bgImage   = normalize_media_url((string) setting('background_image', ''));
$cover     = normalize_media_url((string) setting('homepage_cover_image', ''));
if ($cover === '') {
    $cover = '/assets/home.jpg';
}
$banner    = (string) setting('homepage_banner_text', '');
$siteNameV = site_name();
$siteDescV = site_description();
$previewHost = (string) (parse_url(site_url(), PHP_URL_HOST) ?: 'example.com');
$editorMedia = array_map(static fn (array $m): array => [
    'id'    => (int) $m['id'],
    'url'   => media_url_by_file((string) $m['file']),
    'alt'   => (string) $m['alt'],
    'title' => (string) ($m['title'] !== '' ? $m['title'] : $m['file']),
], $media);
$jsVer = is_file(SYH_ROOT . '/public/assets/admin-appearance.js') ? (string) filemtime(SYH_ROOT . '/public/assets/admin-appearance.js') : '1';
?>
<div class="admin-page-head">
    <div>
        <h2>外观</h2>
        <p>站点标识、配色、背景与首页封面。右侧为实时预览，保存后站点立即生效。</p>
    </div>
    <div>
        <a class="admin-btn" href="/" target="_blank" rel="noopener">打开站点</a>
        <a class="admin-btn" href="<?= e(admin_url('settings/')) ?>">其他设置</a>
    </div>
</div>

<div class="admin-appearance">
    <form action="<?= e(admin_url('appearance/')) ?>" method="post" id="appearance-form">
        <?= Auth::csrfField() ?>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>站点标识</h2><p>显示在页头、浏览器标签和搜索结果中。</p></div></div>
            <div class="admin-form">
                <div class="admin-form-row">
                    <label for="site_name">站点名称</label>
                    <input class="admin-input" type="text" id="site_name" name="site_name" maxlength="100" value="<?= e($siteNameV) ?>" data-preview="name">
                </div>
                <div class="admin-form-row">
                    <label for="site_description">站点描述</label>
                    <textarea class="admin-textarea" id="site_description" name="site_description" maxlength="300" style="min-height:78px;" data-preview="desc"><?= e($siteDescV) ?></textarea>
                </div>
                <div class="admin-form-grid-2">
                    <div class="admin-form-row">
                        <label>站点 Logo</label>
                        <input type="hidden" id="custom_logo" name="custom_logo" value="<?= $logoId ?>" data-preview="logo">
                        <div class="ap-media-field">
                            <span class="ap-thumb ap-thumb-logo"><img id="preview-logo" src="<?= e($logoUrl) ?>" alt=""<?= $logoUrl === '' ? ' hidden' : '' ?>><em id="preview-logo-empty"<?= $logoUrl !== '' ? ' hidden' : '' ?>>无</em></span>
                            <button class="admin-btn admin-btn-sm" type="button" data-media-pick data-target="custom_logo" data-mode="id">选择图片</button>
                            <button class="admin-btn admin-btn-sm" type="button" data-clear-target="custom_logo">移除</button>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label>站点图标（favicon）</label>
                        <input type="hidden" id="site_icon" name="site_icon" value="<?= $iconId ?>" data-preview="icon">
                        <div class="ap-media-field">
                            <span class="ap-thumb ap-thumb-icon"><img id="preview-icon" src="<?= e($iconUrl) ?>" alt=""<?= $iconUrl === '' ? ' hidden' : '' ?>><em id="preview-icon-empty"<?= $iconUrl !== '' ? ' hidden' : '' ?>>无</em></span>
                            <button class="admin-btn admin-btn-sm" type="button" data-media-pick data-target="site_icon" data-mode="id">选择图片</button>
                            <button class="admin-btn admin-btn-sm" type="button" data-clear-target="site_icon">移除</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>配色</h2><p>留空表示使用主题默认值。</p></div></div>
            <div class="admin-form">
                <?php foreach ([
                    ['primary_color', '主色调', '按钮、链接与强调色。', '#0071e3', $primaryVal],
                    ['background_color', '页面背景色', '站点底色，背景图会覆盖在其上方。', '#f5f6f8', $bgColorVal],
                    ['text_color', '正文文字色', '深色文字颜色。', '#1d2327', $textColorVal],
                ] as [$key, $label, $hint, $fallback, $current]): ?>
                <div class="admin-form-row">
                    <label for="<?= e($key) ?>"><?= e($label) ?></label>
                    <div class="ap-color-row">
                        <input class="ap-color" type="color" value="<?= e($current !== '' ? $current : $fallback) ?>" data-color-for="<?= e($key) ?>" aria-label="<?= e($label) ?>取色器">
                        <input class="admin-input ap-color-text" type="text" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($current) ?>" placeholder="<?= e($fallback) ?>（默认）" data-color-text="<?= e($key) ?>" data-preview="<?= e($key) ?>">
                        <button class="admin-btn admin-btn-sm" type="button" data-clear-target="<?= e($key) ?>">默认</button>
                    </div>
                    <span class="hint"><?= e($hint) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>背景与首页</h2><p>背景图、封面和首页横幅。</p></div></div>
            <div class="admin-form">
                <div class="admin-form-row">
                    <label for="background_image">背景图片</label>
                    <div class="ap-url-row">
                        <input class="admin-input" type="text" id="background_image" name="background_image" value="<?= e((string) setting('background_image', '')) ?>" placeholder="留空使用纯色背景" data-preview="bgimage">
                        <button class="admin-btn admin-btn-sm" type="button" data-media-pick data-target="background_image" data-mode="url">媒体库</button>
                    </div>
                    <span class="hint">支持本站媒体路径或外链 https 图片。</span>
                </div>
                <div class="admin-form-row">
                    <label for="homepage_cover_image">首页封面图</label>
                    <div class="ap-url-row">
                        <input class="admin-input" type="text" id="homepage_cover_image" name="homepage_cover_image" value="<?= e((string) setting('homepage_cover_image', '')) ?>" placeholder="留空使用默认封面" data-preview="cover">
                        <button class="admin-btn admin-btn-sm" type="button" data-media-pick data-target="homepage_cover_image" data-mode="url">媒体库</button>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label for="homepage_banner_text">首页横幅文字</label>
                    <textarea class="admin-textarea" id="homepage_banner_text" name="homepage_banner_text" maxlength="500" style="min-height:70px;" placeholder="留空则不显示横幅" data-preview="banner"><?= e($banner) ?></textarea>
                    <span class="hint">显示在首页导航下方，支持简单 HTML。</span>
                </div>
                <div class="admin-form-row">
                    <label for="posts_per_page">每页文章数</label>
                    <input class="admin-input" type="number" id="posts_per_page" name="posts_per_page" min="1" max="50" value="<?= (int) setting('posts_per_page', 10) ?>">
                </div>
            </div>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><div><h2>自定义 CSS</h2><p>想微调间距、字体或者藏掉某个小模块时用，不填也没关系。</p></div></div>
            <div class="admin-form">
                <div class="admin-form-row">
                    <label for="custom_css">样式代码</label>
                    <textarea class="admin-textarea" id="custom_css" name="custom_css" spellcheck="false" style="min-height:180px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.6;" placeholder="/* 例：.site-header { box-shadow: none; } */"><?= e((string) setting('custom_css', '')) ?></textarea>
                    <span class="hint">会加在主题样式后面。常用变量：<code>--primary</code> 主色、<code>--bg</code> 背景、<code>--text</code> 正文色。</span>
                </div>
            </div>
        </div>

        <div class="admin-savebar">
            <button class="admin-btn admin-btn-primary" type="submit">保存外观</button>
            <span class="admin-small admin-muted">保存后前台立即使用新设置。</span>
        </div>
    </form>

    <aside class="admin-appearance-preview">
        <div class="ap-preview-head">
            <strong>实时预览</strong>
            <span class="hint">仅示意配色与图片</span>
        </div>
        <div class="ap-browser">
            <div class="ap-browser-bar"><i></i><i></i><i></i><span><?= e($previewHost) ?></span></div>
            <div class="ap-frame" id="ap-frame">
                <div class="ap-site">
                    <div class="ap-top">
                        <span class="ap-brand">
                            <img id="ap-logo" src="<?= e($logoUrl) ?>" alt=""<?= $logoUrl === '' ? ' hidden' : '' ?>>
                            <b id="ap-logo-mark"<?= $logoUrl !== '' ? ' hidden' : '' ?>><?= e(mb_substr($siteNameV !== '' ? $siteNameV : '我', 0, 1, 'UTF-8')) ?></b>
                            <strong id="ap-name"><?= e($siteNameV) ?></strong>
                        </span>
                        <span class="ap-nav"><i>首页</i><i>归档</i><i>关于</i></span>
                    </div>
                    <div class="ap-banner" id="ap-banner"<?= $banner === '' ? ' hidden' : '' ?>><?= e($banner) ?></div>
                    <div class="ap-cover">
                        <img id="ap-cover" src="<?= e($cover) ?>" alt="">
                        <div class="ap-cover-body">
                            <h3 id="ap-desc-title"><?= e($siteDescV !== '' ? $siteDescV : '记录技术与生活') ?></h3>
                            <span class="ap-btn" id="ap-btn">阅读全文</span>
                        </div>
                    </div>
                    <div class="ap-card">
                        <span class="ap-card-tag">最新文章</span>
                        <h4>示例文章标题</h4>
                        <p>正文摘要在预览中只展示样式，实际内容以站点为准。</p>
                        <span class="ap-meta">2026-11-08 · 阅读 128</span>
                    </div>
                </div>
            </div>
        </div>
        <p class="hint">颜色、Logo、背景与封面会即时反映在这里；保存后同步到站点。</p>
    </aside>
</div>

<div class="syh-modal" id="ap-media-modal" hidden>
    <div class="syh-modal-card">
        <div class="syh-modal-head">
            <h3>选择图片</h3>
            <button type="button" class="admin-btn admin-btn-sm" data-close>关闭</button>
        </div>
        <div class="syh-modal-body">
            <input class="admin-input" type="search" id="ap-media-search" placeholder="搜索图片…">
            <div class="syh-media-grid" id="ap-media-grid"></div>
            <p class="hint" style="margin:12px 0 0;">找不到？<a href="<?= e(admin_url('media/')) ?>" target="_blank" rel="noopener">去媒体库上传</a>。</p>
        </div>
    </div>
</div>

<script>window.SYH_MEDIA = <?= json_encode($editorMedia, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="/assets/admin-appearance.js?v=<?= e($jsVer) ?>"></script>
