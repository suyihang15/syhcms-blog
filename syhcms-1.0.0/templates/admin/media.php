<?php
$imageCount = 0;
foreach ($rows as $row) {
    if (str_starts_with((string) $row['mime'], 'image/')) {
        $imageCount++;
    }
}
?>
<div class="admin-page-head">
    <div>
        <h2>媒体库</h2>
        <p>共 <?= (int) $total ?> 个文件<?= $search !== '' ? '，搜索“' . e($search) . '”' : '' ?>，本页 <?= $imageCount ?> 张图片。</p>
    </div>
</div>

<div class="admin-panel">
    <div class="admin-panel-head"><h2>上传文件</h2></div>
    <form class="admin-form" action="<?= e(admin_url('media/upload/')) ?>" method="post" enctype="multipart/form-data">
        <?= Auth::csrfField() ?>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <label for="media-file">选择文件</label>
                <input class="admin-input" type="file" id="media-file" name="file" required>
                <span class="hint">支持 JPG、PNG、GIF、WebP、AVIF、PDF、ZIP、TXT，最大 20MB。</span>
            </div>
            <div class="admin-form-row">
                <label for="media-title">标题</label>
                <input class="admin-input" type="text" id="media-title" name="title" placeholder="留空则使用文件名">
            </div>
            <div class="admin-form-row">
                <label for="media-alt">替代文本（alt）</label>
                <input class="admin-input" type="text" id="media-alt" name="alt" placeholder="用于图片 SEO 与无障碍">
            </div>
        </div>
        <div id="media-preview" class="admin-cover-preview"></div>
        <div><button class="admin-btn admin-btn-primary" type="submit">上传</button></div>
    </form>
</div>

<div class="admin-panel">
    <div class="admin-panel-head">
        <h2>文件列表</h2>
        <form class="admin-filters" action="<?= e(admin_url('media/')) ?>" method="get" style="margin:0;">
            <input class="admin-input" type="search" name="s" value="<?= e($search) ?>" placeholder="搜索文件名、标题或 alt">
            <button class="admin-btn" type="submit">搜索</button>
            <?php if ($search !== ''): ?><a class="admin-btn" href="<?= e(admin_url('media/')) ?>">清除</a><?php endif; ?>
        </form>
    </div>

    <?php if ($rows): ?>
    <div class="admin-media-grid">
        <?php foreach ($rows as $row): ?>
        <?php
        $isImage = str_starts_with((string) $row['mime'], 'image/');
        $fileUrl = media_url_by_file((string) $row['file']);
        $ext     = strtoupper((string) pathinfo((string) $row['file'], PATHINFO_EXTENSION));
        ?>
        <div class="admin-media-card">
            <div class="admin-media-thumb">
                <?php if ($isImage): ?>
                <a href="<?= e($fileUrl) ?>" target="_blank" rel="noopener"><img src="<?= e($fileUrl) ?>" alt="<?= e($row['alt']) ?>" loading="lazy"></a>
                <?php else: ?>
                <div class="file-icon"><?= e($ext !== '' ? $ext : 'FILE') ?><br><?= e($row['mime']) ?></div>
                <?php endif; ?>
            </div>
            <div class="admin-media-meta">
                <div class="name" title="<?= e($row['file']) ?>"><?= e($row['title'] !== '' ? $row['title'] : basename((string) $row['file'])) ?></div>
                <?php if ((int) $row['width'] > 0 && (int) $row['height'] > 0): ?>
                <div class="admin-muted"><?= (int) $row['width'] ?> × <?= (int) $row['height'] ?></div>
                <?php endif; ?>
                <div class="admin-muted"><?= e(format_date((string) $row['created_at'], 'Y-m-d')) ?></div>
                <input class="admin-input admin-small" type="text" readonly value="<?= e($fileUrl) ?>" onclick="this.select()" aria-label="文件地址">
                <div class="row-actions">
                    <a class="admin-btn admin-btn-sm" href="<?= e($fileUrl) ?>" target="_blank" rel="noopener">打开</a>
                    <form action="<?= e(admin_url('media/delete/')) ?>" method="post" data-confirm="确定删除“<?= e(basename((string) $row['file'])) ?>”吗？已使用该图片的内容将不再显示它。">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <button class="admin-btn admin-btn-sm admin-btn-danger" type="submit">删除</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?= admin_pagination((int) $paged, (int) $pages, admin_url('media/'), ['s' => $search]) ?>
    <?php else: ?>
    <div class="admin-empty">没有找到媒体文件。</div>
    <?php endif; ?>
</div>
