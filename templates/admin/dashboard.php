<?php
$metrics = [
    ['label' => '文章', 'value' => (int) $counts['posts'], 'href' => admin_url('posts/'), 'note' => '已发布与草稿'],
    ['label' => '页面', 'value' => (int) $counts['pages'], 'href' => admin_url('pages/'), 'note' => '独立页面'],
    ['label' => '媒体', 'value' => (int) $counts['media'], 'href' => admin_url('media/'), 'note' => '图片与附件'],
    ['label' => '评论', 'value' => (int) $counts['comments'], 'href' => admin_url('comments/'), 'note' => $counts['pending'] > 0 ? $counts['pending'] . ' 条待审核' : '全部已处理'],
    ['label' => '总浏览量', 'value' => (int) $counts['views'], 'href' => '/stats/', 'note' => '对外统计页'],
];
$maxHits = 1;
foreach ($traffic as $t) {
    $maxHits = max($maxHits, (int) $t['hits']);
}
$totalHits = array_sum(array_map(static fn (array $t): int => (int) $t['hits'], $traffic));
?>
<div class="admin-page-head">
    <div>
        <h2>仪表盘</h2>
        <p>内容概况与近 7 日访问数据。</p>
    </div>
    <a class="admin-btn admin-btn-primary" href="<?= e(admin_url('posts/new/')) ?>">写文章</a>
</div>

<div class="admin-dash-metrics">
    <?php foreach ($metrics as $m): ?>
    <div class="admin-dash-metric">
        <div class="label"><?= e($m['label']) ?></div>
        <a class="value" href="<?= e($m['href']) ?>"><?= (int) $m['value'] ?></a>
        <div class="delta"><?= e($m['note']) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<div class="admin-dashboard-grid">
    <div>
        <div class="admin-panel admin-panel-flush">
            <div class="admin-panel-head">
                <div>
                    <h2>最近更新</h2>
                    <p>按最后修改时间排序。</p>
                </div>
                <a class="admin-btn admin-btn-sm" href="<?= e(admin_url('posts/')) ?>">全部内容</a>
            </div>
            <?php if ($recentPosts): ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>标题</th><th>状态</th><th>更新时间</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentPosts as $p): ?>
                    <?php $base = $p['type'] === 'page' ? 'pages' : 'posts'; ?>
                    <tr>
                        <td>
                            <a class="row-title" href="<?= e(admin_url($base . '/edit/' . (int) $p['id'] . '/')) ?>"><?= e($p['title']) ?></a>
                            <div class="admin-small admin-muted"><?= $p['type'] === 'page' ? '页面' : '文章' ?> · /<?= e($p['slug']) ?></div>
                        </td>
                        <td><span class="admin-badge admin-badge-<?= e($p['status']) ?>"><?= e(admin_status_label((string) $p['status'])) ?></span></td>
                        <td class="admin-small admin-muted"><?= e(format_date((string) $p['updated_at'], 'Y-m-d H:i')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="admin-empty">还没有内容，先写一篇吧。</div>
            <?php endif; ?>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head">
                <div>
                    <h2>待审核评论</h2>
                    <p>通过后才会在前台显示。</p>
                </div>
                <a class="admin-btn admin-btn-sm" href="<?= e(admin_url('comments/?status=pending')) ?>">去审核</a>
            </div>
            <?php if ($pending): ?>
            <div class="admin-dash-list">
                <?php foreach ($pending as $c): ?>
                <div class="admin-dash-list-item">
                    <div class="admin-dash-list-main">
                        <p><strong><?= e($c['author']) ?></strong> <span class="admin-dash-list-meta"><?= e(format_date((string) $c['created_at'], 'm-d H:i')) ?></span></p>
                        <p class="admin-small"><?= e(mb_substr(trim(strip_tags((string) $c['content'])), 0, 120, 'UTF-8')) ?></p>
                        <p class="admin-dash-list-meta">评论于：<?= e($c['post_title'] ?: '（文章已删除）') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="admin-empty">没有待审核评论。</div>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="admin-panel">
            <div class="admin-panel-head">
                <div>
                    <h2>近 7 日访问</h2>
                    <p>合计 <?= (int) $totalHits ?> 次请求。</p>
                </div>
            </div>
            <?php if ($traffic): ?>
            <div class="admin-traffic-bars">
                <?php foreach ($traffic as $t): ?>
                <div class="admin-traffic-bar" title="<?= e($t['day']) ?>：<?= (int) $t['hits'] ?> 次">
                    <i style="height:<?= max(2, (int) round((int) $t['hits'] / $maxHits * 100)) ?>%"></i>
                    <span><?= e(substr((string) $t['day'], 5)) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="admin-empty">暂无访问统计数据。</div>
            <?php endif; ?>
        </div>

        <div class="admin-panel">
            <div class="admin-panel-head"><h2>快捷入口</h2></div>
            <div class="admin-quicklinks">
                <a class="admin-quicklink" href="<?= e(admin_url('posts/new/')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>新建文章
                </a>
                <a class="admin-quicklink" href="<?= e(admin_url('media/')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>上传媒体
                </a>
                <a class="admin-quicklink" href="<?= e(admin_url('terms/')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>分类标签
                </a>
                <?php if (Auth::can('admin')): ?>
                <a class="admin-quicklink" href="<?= e(admin_url('appearance/')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18c1.2 0 2-.9 2-2 0-.6-.2-1-.6-1.4-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2H17a4 4 0 0 0 4-4c0-4-4-7.3-9-7.3Z"/><circle cx="7.5" cy="11" r="1.1"/><circle cx="12" cy="7.5" r="1.1"/><circle cx="16" cy="10.5" r="1.1"/></svg>外观
                </a>
                <a class="admin-quicklink" href="<?= e(admin_url('settings/')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/></svg>站点设置
                </a>
                <?php endif; ?>
            </div>
            <p class="admin-small admin-muted" style="margin:14px 0 0;">访问统计仅记录前台页面，编辑登录后的浏览不计入。</p>
        </div>
    </div>
</div>
