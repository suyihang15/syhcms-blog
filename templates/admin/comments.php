<?php
$statusLabels = [
    'approved' => '已通过',
    'pending'  => '待审核',
    'spam'     => '垃圾评论',
    'trash'    => '回收站',
];
$tabs = [
    ''         => ['全部', (int) $counts['all']],
    'pending'  => ['待审核', (int) $counts['pending']],
    'approved' => ['已通过', (int) $counts['approved']],
    'spam'     => ['垃圾', (int) $counts['spam']],
    'trash'    => ['回收站', (int) $counts['trash']],
];
?>
<div class="admin-page-head">
    <div>
        <h2>评论</h2>
        <p>共 <?= (int) $total ?> 条<?= $search !== '' ? '，搜索“' . e($search) . '”' : '' ?>。</p>
    </div>
</div>

<div class="admin-panel">
    <div class="admin-filters">
        <?php foreach ($tabs as $value => [$label, $count]): ?>
        <a class="admin-btn admin-btn-sm<?= $status === $value ? ' admin-btn-primary' : '' ?>" href="<?= e(admin_url('comments/' . ($value !== '' ? '?status=' . $value : ''))) ?>"><?= e($label) ?> (<?= $count ?>)</a>
        <?php endforeach; ?>
        <form class="admin-filters" action="<?= e(admin_url('comments/')) ?>" method="get" style="margin:0 0 0 auto;">
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <input class="admin-input" type="search" name="s" value="<?= e($search) ?>" placeholder="搜索作者、邮箱或内容">
            <button class="admin-btn" type="submit">搜索</button>
            <?php if ($search !== '' || $status !== ''): ?><a class="admin-btn" href="<?= e(admin_url('comments/')) ?>">清除</a><?php endif; ?>
        </form>
    </div>

    <?php if ($rows): ?>
    <form id="comments-bulk" action="<?= e(admin_url('comments/action/')) ?>" method="post" data-confirm="确定对选中的评论执行此操作吗？">
        <?= Auth::csrfField() ?>
        <div class="admin-filters">
            <select class="admin-select" name="do" required>
                <option value="approved">标记为已通过</option>
                <option value="pending">标记为待审核</option>
                <option value="spam">标记为垃圾评论</option>
                <option value="trash">移入回收站</option>
                <option value="delete">永久删除</option>
            </select>
            <button class="admin-btn" type="submit">批量操作</button>
            <span class="admin-small admin-muted">勾选下方评论后执行。</span>
        </div>
    </form>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th style="width:28px;"></th>
                <th>评论</th>
                <th>评论者</th>
                <th>内容页面</th>
                <th>状态</th>
                <th>时间</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
            <?php
            $statusValue = (string) $row['status'];
            $postUrl = (string) ($row['post_slug'] ?? '') !== '' ? post_url(['slug' => $row['post_slug']]) : '';
            ?>
            <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>" form="comments-bulk" aria-label="选择评论"></td>
                <td style="max-width:420px;">
                    <div class="admin-small"><?= nl2br(e(mb_substr(trim((string) $row['content']), 0, 600, 'UTF-8'))) ?></div>
                    <?php if ((string) $row['ip'] !== ''): ?>
                    <div class="admin-small admin-muted" title="<?= e((string) $row['ua']) ?>">IP：<?= e($row['ip']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="admin-small">
                    <strong><?= e($row['author']) ?></strong>
                    <?php if ((string) $row['email'] !== ''): ?><div class="admin-muted"><?= e($row['email']) ?></div><?php endif; ?>
                    <?php if ((string) $row['url'] !== ''): ?><div><a href="<?= e($row['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">网站</a></div><?php endif; ?>
                </td>
                <td class="admin-small">
                    <?php if ($row['post_title'] !== null && $postUrl !== ''): ?>
                    <a href="<?= e($postUrl) ?>" target="_blank" rel="noopener"><?= e($row['post_title']) ?></a>
                    <?php else: ?>
                    <span class="admin-muted">（文章已删除）</span>
                    <?php endif; ?>
                </td>
                <td><span class="admin-badge admin-badge-<?= e($statusValue) ?>"><?= e($statusLabels[$statusValue] ?? $statusValue) ?></span></td>
                <td class="admin-small admin-muted"><?= e(format_date((string) $row['created_at'], 'Y-m-d H:i')) ?></td>
                <td>
                    <div class="row-actions">
                        <?php if ($statusValue !== 'approved'): ?>
                        <form action="<?= e(admin_url('comments/action/')) ?>" method="post">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="do" value="approved">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button class="admin-btn admin-btn-sm" type="submit">通过</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($statusValue !== 'spam'): ?>
                        <form action="<?= e(admin_url('comments/action/')) ?>" method="post">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="do" value="spam">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <button class="admin-btn admin-btn-sm" type="submit">垃圾</button>
                        </form>
                        <?php endif; ?>
                        <form action="<?= e(admin_url('comments/action/')) ?>" method="post" data-confirm="确定永久删除这条评论吗？">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="do" value="delete">
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
    <?= admin_pagination((int) $paged, (int) $pages, admin_url('comments/'), ['status' => $status, 's' => $search]) ?>
    <?php else: ?>
    <div class="admin-empty">没有找到评论。</div>
    <?php endif; ?>
</div>
