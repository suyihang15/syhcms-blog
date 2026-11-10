<?php
/** @var array $page */
require SYH_ROOT . '/templates/header.php';
$since = stats_since();
$sinceLabel = '';
if ($since !== '') {
    $p = explode('-', $since);
    $sinceLabel = sprintf('%s 年 %d 月 %d 日', $p[0], (int) ($p[1] ?? 1), (int) ($p[2] ?? 1));
}
?>
<main class="site-main stats-page" id="content">
    <div class="content-with-sidebar">
        <div class="content-area" data-stats-page aria-busy="true">
            <header class="archive-hero">
                <p class="archive-eyebrow">数据</p>
                <h1 class="archive-hero-title"><?= e($page['title']) ?></h1>
                <p class="archive-hero-meta">
                    看看这个站点被谁、从哪里、读了什么。
                    <span class="stats-since" data-stat="since">
                        <?= $sinceLabel !== '' ? '数据自 ' . e($sinceLabel) . ' 起累积。' : '数据从今天开始累积。' ?>
                    </span>
                </p>
            </header>

            <section class="stats-overview" aria-label="核心数据">
                <article class="stats-card">
                    <span class="stats-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg></span>
                    <p class="stats-card-value" data-stat="total_views">—</p>
                    <p class="stats-card-label">总浏览量</p>
                </article>
                <article class="stats-card">
                    <span class="stats-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg></span>
                    <p class="stats-card-value" data-stat="today_views">—</p>
                    <p class="stats-card-label">今日访问</p>
                </article>
                <article class="stats-card">
                    <span class="stats-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l5-6 4 3 6-8"/><path d="M18 6h4v4"/></svg></span>
                    <p class="stats-card-value" data-stat="week_views">—</p>
                    <p class="stats-card-label">近 7 天访问</p>
                </article>
                <article class="stats-card">
                    <span class="stats-card-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3 8 4-16 3 8h4"/></svg></span>
                    <p class="stats-card-value" data-stat="avg_views">—</p>
                    <p class="stats-card-label">篇均浏览</p>
                </article>
            </section>

            <section class="archive-section stats-panel" aria-labelledby="stats-trend-title">
                <div class="archive-section-head">
                    <h2 class="archive-section-title" id="stats-trend-title">近 30 天访问趋势</h2>
                    <p class="archive-section-note">同一会话只计 1 次</p>
                </div>
                <div class="stats-trend" data-stats-trend role="img" aria-label="近 30 天每日访问量柱状图">
                    <p class="stats-empty">正在加载…</p>
                </div>
                <p class="stats-trend-summary" data-stats-trend-summary></p>
                <details class="stats-daily">
                    <summary>查看每日数据</summary>
                    <div class="stats-daily-table" data-stats-daily><p class="stats-empty">正在加载…</p></div>
                </details>
            </section>

            <div class="stats-grid">
                <section class="archive-section stats-panel" aria-labelledby="stats-top-title">
                    <div class="archive-section-head"><h2 class="archive-section-title" id="stats-top-title">文章浏览 Top 10</h2><p class="archive-section-note">按累计浏览排序</p></div>
                    <ol class="stats-top" data-stats-top><li class="stats-empty">正在加载…</li></ol>
                </section>
                <section class="archive-section stats-panel" aria-labelledby="stats-sources-title">
                    <div class="archive-section-head"><h2 class="archive-section-title" id="stats-sources-title">访问来源</h2><p class="archive-section-note">按会话首次进入统计</p></div>
                    <div class="stats-sources" data-stats-sources><p class="stats-empty">正在加载…</p></div>
                    <h3 class="stats-subtitle">来源站点</h3>
                    <ul class="stats-refs" data-stats-refs><li class="stats-empty">暂无外链来源</li></ul>
                </section>
                <section class="archive-section stats-panel" aria-labelledby="stats-cats-title">
                    <div class="archive-section-head"><h2 class="archive-section-title" id="stats-cats-title">分类热度</h2><p class="archive-section-note">分类下文章的累计浏览</p></div>
                    <div class="stats-cats" data-stats-cats><p class="stats-empty">正在加载…</p></div>
                </section>
                <section class="archive-section stats-panel" aria-labelledby="stats-facts-title">
                    <div class="archive-section-head"><h2 class="archive-section-title" id="stats-facts-title">站点概况</h2></div>
                    <dl class="stats-facts">
                        <div><dt>文章总数</dt><dd data-fact="posts">—</dd></div>
                        <div><dt>已通过留言</dt><dd data-fact="comments">—</dd></div>
                        <div><dt>分类 / 标签</dt><dd data-fact="taxonomies">—</dd></div>
                        <div><dt>建站天数</dt><dd data-fact="days_running">—</dd></div>
                        <div><dt>本月更新</dt><dd data-fact="month_posts">—</dd></div>
                        <div><dt>第一篇文章</dt><dd data-fact="first_post">—</dd></div>
                    </dl>
                </section>
            </div>

            <details class="stats-about">
                <summary>统计逻辑</summary>
                <ul>
                    <li><strong>一次浏览</strong>打开文章页就记一次。同一浏览器、同一天、同一篇文章只算一次；作者和编辑自己看不算。</li>
                    <li><strong>来源怎么分</strong>每次访问只看第一次是从哪进来的。搜索引擎算「搜索」，微博、知乎这类算「社交」，其他网站只记域名。</li>
                    <li><strong>关于隐私</strong>不保存 IP、浏览器信息和完整链接，只留下「日期 + 来源 + 次数」的汇总。</li>
                </ul>
            </details>
            <noscript><p class="stats-noscript">统计数字需要启用 JavaScript 才能加载。</p></noscript>
        </div>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
