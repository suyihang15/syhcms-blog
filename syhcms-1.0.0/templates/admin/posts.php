<?php
$base = $type === 'page' ? 'pages' : 'posts';
$statusOptions = ['' => '全部状态', 'publish' => '已发布', 'draft' => '草稿', 'pending' => '待审核', 'private' => '私密'];
?>
<div class="admin-page-head">
    <div>
        <h2><?= $type === 'page' ? '页面' : '文章' ?></h2>
        <p>共 <?= (int) $total ?> 条<?= $search !== '' ? '，搜索“' . e($search) . '”' : '' ?>。</p>
    </div>
    <a class="admin-btn admin-btn-primary" href="<?= e(admin_url($base . '/new/')) ?>">新建<?= $type === 'page' ? '页面' : '文章' ?></a>
</div>

<div class="admin-panel">
    <form class="admin-filters" action="<?= e(admin_url($base . '/')) ?>" method="get">
        <select class="admin-select" name="status">
            <?php foreach ($statusOptions as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="admin-input" type="search" name="s" value="<?= e($search) ?>" placeholder="搜索标题或别名">
        <button class="admin-btn" type="submit">筛选</button>
        <?php if ($status !== '' || $search !== ''): ?><a class="admin-btn" href="<?= e(admin_url($base . '/')) ?>">清除</a><?php endif; ?>
    </form>

    <?php if ($rows): ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>标题</th>
                <?php if ($type === 'post'): ?><th>分类</th><?php endif; ?>
                <th>状态</th>
                <th>评论</th>
                <th>浏览</th>
                <th>日期</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
            <?php $editUrl = admin_url($base . '/edit/' . (int) $row['id'] . '/'); ?>
            <tr>
                <td>
                    <a class="row-title" href="<?= e($editUrl) ?>"><?= e($row['title']) ?></a>
                    <div class="admin-small admin-muted"><?= e($row['slug']) ?></div>
                </td>
                <?php if ($type === 'post'): ?>
                <td class="admin-small">
                    <?php if ($row['categories']): ?>
                        <?= e(implode('、', array_column($row['categories'], 'name'))) ?>
                    <?php else: ?>
                        <span class="admin-muted">未分类</span>
                    <?php endif; ?>
                </td>
                <?php endif; ?>
                <td><span class="admin-badge admin-badge-<?= e($row['status']) ?>"><?= e(admin_status_label((string) $row['status'])) ?></span></td>
                <td><?= comment_count((int) $row['id'], false) ?></td>
                <td><?= (int) $row['views'] ?></td>
                <td class="admin-small admin-muted"><?= e(format_date((string) $row['created_at'], 'Y-m-d')) ?></td>
                <td>
                    <div class="row-actions">
                        <a class="admin-btn admin-btn-sm" href="<?= e($editUrl) ?>">编辑</a>
                        <?php if ($row['status'] === 'publish'): ?>
                        <a class="admin-btn admin-btn-sm" href="<?= e($type === 'page' ? url(rawurlencode((string) $row['slug']) . '/') : post_url($row)) ?>" target="_blank" rel="noopener">查看</a>
                        <?php endif; ?>
                        <form action="<?= e(admin_url($base . '/delete/')) ?>" method="post" data-confirm="确定删除“<?= e($row['title']) ?>”吗？相关评论也会一并删除。">
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
    <?= admin_pagination((int) $paged, (int) $pages, admin_url($base . '/'), ['status' => $status, 's' => $search]) ?>
    <?php else: ?>
    <div class="admin-empty">没有找到内容。</div>
    <?php endif; ?>
</div>
