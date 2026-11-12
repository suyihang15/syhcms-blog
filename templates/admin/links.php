<?php
$editId = $edit ? (int) $edit['id'] : 0;
?>
<div class="admin-page-head">
    <div>
        <h2>友情链接</h2>
        <p>共 <?= count($rows) ?> 个友链，按分组与排序值展示在友链页面。</p>
    </div>
</div>

<div class="admin-layout-3">
    <div class="admin-panel">
        <div class="admin-panel-head">
            <h2><?= $edit ? '编辑友链' : '添加友链' ?></h2>
            <?php if ($edit): ?><a class="admin-btn admin-btn-sm" href="<?= e(admin_url('links/')) ?>">取消编辑</a><?php endif; ?>
        </div>
        <form class="admin-form" action="<?= e(admin_url('links/save/')) ?>" method="post">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="id" value="<?= $editId ?>">
            <div class="admin-form-row">
                <label for="link-name">名称</label>
                <input class="admin-input" type="text" id="link-name" name="name" value="<?= e($edit['name'] ?? '') ?>" required>
            </div>
            <div class="admin-form-row">
                <label for="link-url">网址</label>
                <input class="admin-input" type="text" id="link-url" name="url" value="<?= e($edit['url'] ?? '') ?>" placeholder="https://example.com" required>
                <span class="hint">缺少协议时会自动补 https://。</span>
            </div>
            <div class="admin-form-row">
                <label for="link-group">分组</label>
                <input class="admin-input" type="text" id="link-group" name="grp" value="<?= e($edit['grp'] ?? '好友') ?>" placeholder="好友">
            </div>
            <div class="admin-form-row">
                <label for="link-image">头像 / Logo</label>
                <input class="admin-input" type="text" id="link-image" name="image" value="<?= e($edit['image'] ?? '') ?>" placeholder="https://example.com/logo.png 或 /wp-content/uploads/...">
            </div>
            <div class="admin-form-row">
                <label for="link-image-dark">深色模式 Logo（可选）</label>
                <input class="admin-input" type="text" id="link-image-dark" name="image_dark" value="<?= e($edit['image_dark'] ?? '') ?>">
            </div>
            <div class="admin-form-row">
                <label for="link-description">描述</label>
                <textarea class="admin-textarea" id="link-description" name="description" style="min-height:90px;"><?= e($edit['description'] ?? '') ?></textarea>
            </div>
            <div class="admin-form-row">
                <label for="link-rss">RSS 地址（可选）</label>
                <input class="admin-input" type="text" id="link-rss" name="rss" value="<?= e($edit['rss'] ?? '') ?>">
            </div>
            <div class="admin-form-row">
                <label for="link-sort">排序</label>
                <input class="admin-input" type="number" id="link-sort" name="sort" value="<?= (int) ($edit['sort'] ?? 0) ?>" step="1">
            </div>
            <div><button class="admin-btn admin-btn-primary" type="submit"><?= $edit ? '保存修改' : '添加友链' ?></button></div>
        </form>
    </div>

    <div class="admin-panel">
        <div class="admin-panel-head"><h2>友链列表</h2></div>
        <?php if ($rows): ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>名称</th>
                    <th>分组</th>
                    <th>网址</th>
                    <th>描述</th>
                    <th>排序</th>
                    <th>操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a class="row-title" href="<?= e(admin_url('links/?edit=' . (int) $row['id'])) ?>"><?= e($row['name']) ?></a>
                        <?php if ((string) $row['image'] !== ''): ?><div class="admin-small admin-muted">已设置 Logo</div><?php endif; ?>
                    </td>
                    <td><span class="admin-badge"><?= e($row['grp']) ?></span></td>
                    <td class="admin-small"><a href="<?= e($row['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($row['url']) ?></a></td>
                    <td class="admin-small admin-muted"><?= e(mb_substr((string) $row['description'], 0, 60, 'UTF-8')) ?></td>
                    <td><?= (int) $row['sort'] ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="admin-btn admin-btn-sm" href="<?= e(admin_url('links/?edit=' . (int) $row['id'])) ?>">编辑</a>
                            <form action="<?= e(admin_url('links/delete/')) ?>" method="post" data-confirm="确定删除友链“<?= e($row['name']) ?>”吗？">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button class="admin-btn admin-btn-sm admin-btn-danger" type="submit">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="admin-empty">还没有友链。</div>
        <?php endif; ?>
    </div>
</div>
