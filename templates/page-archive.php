<?php
/** @var array $page */
/** @var array $archive_groups */
/** @var int $archive_total */
/** @var array $archive_rows */
require SYH_ROOT . '/templates/header.php';

$tz = new DateTimeZone((string) setting('timezone', 'Asia/Shanghai'));
$today = new DateTimeImmutable('now', $tz);
$today = $today->setTime(0, 0, 0);
$oldest = null;
foreach ($archive_rows as $row) {
    $ts = strtotime((string) $row['created_at']);
    if ($ts !== false && ($oldest === null || $ts < $oldest)) {
        $oldest = $ts;
    }
}
$firstMonday = $oldest ? (new DateTimeImmutable('@' . $oldest))->setTimezone($tz)->modify('monday this week') : $today->modify('monday this week');
$weeks = $oldest ? (int) ceil(($today->getTimestamp() - $firstMonday->getTimestamp()) / (7 * 86400)) + 1 : 26;
$weeks = max(26, min(53, $weeks));
$start = $today->modify('monday this week')->modify('-' . ($weeks - 1) . ' weeks');

$counts = [];
$postsByDay = [];
foreach ($archive_rows as $row) {
    $ts = strtotime((string) $row['created_at']);
    if ($ts === false) {
        continue;
    }
    $key = date('Y-m-d', $ts);
    $counts[$key] = ($counts[$key] ?? 0) + 1;
    $postsByDay[$key][] = [
        'title' => (string) $row['title'],
        'url'   => post_url($row),
        'time'  => date('H:i', $ts),
    ];
}
$yearTotals = [];
foreach ($archive_groups as $y => $months) {
    $yearTotals[$y] = array_sum(array_map('count', $months));
}
$firstPost = $archive_rows ? $archive_rows[count($archive_rows) - 1] : null;
?>
<main class="site-main" id="content">
    <div class="content-with-sidebar">
        <div class="content-area">
            <header class="archive-hero">
                <p class="archive-eyebrow">时间线</p>
                <h1 class="archive-hero-title"><?= e($page['title']) ?></h1>
                <p class="archive-hero-meta">
                    <?php if ($firstPost): ?>
                    共 <strong><?= number_format($archive_total) ?></strong> 篇文章，最早一篇写于 <?= e(format_date((string) $firstPost['created_at'], 'Y 年 n 月')) ?>。
                    <?php else: ?>还没有文章。<?php endif; ?>
                </p>
                <?php if (trim((string) $page['content']) !== ''): ?>
                <div class="archive-hero-content"><?= content_html((string) $page['content']) ?></div>
                <?php endif; ?>
            </header>

            <section class="archive-section" aria-labelledby="archive-heatmap-title">
                <div class="archive-section-head">
                    <h2 class="archive-section-title" id="archive-heatmap-title">发布足迹</h2>
                    <p class="archive-section-note">颜色越深，当天写得越多</p>
                </div>
                <figure class="post-heatmap">
                    <div class="heatmap-scroll" data-heatmap-scroll tabindex="0" role="region" aria-label="发布热力图，可左右滚动查看更早的日期">
                        <div class="heatmap-inner" style="--heatmap-weeks:<?= (int) $weeks ?>;">
                            <div class="heatmap-months" aria-hidden="true">
                                <?php
                                $labels = [];
                                $lastMonth = '';
                                for ($w = 0; $w < $weeks; $w++) {
                                    $ws = $start->modify('+' . ($w * 7) . ' days');
                                    $m = $ws->format('n');
                                    if ($m !== $lastMonth) {
                                        $labels[] = ['week' => $w, 'text' => $m . '月'];
                                        $lastMonth = $m;
                                    }
                                }
                                foreach ($labels as $i => $label):
                                    $next = $labels[$i + 1]['week'] ?? $weeks;
                                    $span = $next - $label['week'];
                                    if ($span < 2) { continue; }
                                ?>
                                <span style="grid-column:<?= (int) $label['week'] + 1 ?> / span <?= (int) $span ?>;"><?= e($label['text']) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="heatmap-body">
                                <div class="heatmap-weekdays" aria-hidden="true"><span>一</span><span></span><span>三</span><span></span><span>五</span><span></span><span></span></div>
                                <div class="heatmap-grid">
                                    <?php for ($i = 0; $i < $weeks * 7; $i++): ?>
                                        <?php
                                        $date = $start->modify('+' . $i . ' days');
                                        if ($date > $today) {
                                            echo '<span class="heat-cell is-future" aria-hidden="true"></span>';
                                            continue;
                                        }
                                        $key = $date->format('Y-m-d');
                                        $count = (int) ($counts[$key] ?? 0);
                                        $level = $count >= 6 ? 4 : ($count >= 4 ? 3 : ($count >= 2 ? 2 : ($count >= 1 ? 1 : 0)));
                                        $cls = 'heat-cell heat-' . $level . ($date == $today ? ' is-today' : '');
                                        $label = $date->format('Y 年 n 月 j 日') . ' · ' . ($count ? $count . ' 篇文章' : '没有发文');
                                        if ($count > 0) {
                                            $payload = json_encode($postsByDay[$key] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                            printf(
                                                '<a class="%s" href="%s" title="%s" aria-haspopup="dialog" aria-expanded="false" data-heat-date="%s" data-heat-count="%d" data-heat-posts="%s"><span class="screen-reader-text">%s</span></a>',
                                                e($cls), e('/' . $date->format('Y/m/d') . '/'), e($label), e($key), $count, e((string) $payload), e($label)
                                            );
                                        } else {
                                            printf('<span class="%s" title="%s" aria-hidden="true"></span>', e($cls), e($label));
                                        }
                                        ?>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </figure>
            </section>

            <?php if ($archive_groups): ?>
            <nav class="archive-year-nav" aria-label="按年份跳转">
                <?php foreach ($yearTotals as $year => $total): ?>
                <a href="#year-<?= (int) $year ?>"><?= (int) $year ?><span><?= number_format($total) ?></span></a>
                <?php endforeach; ?>
            </nav>
            <div class="archive-timeline">
                <?php foreach ($archive_groups as $year => $months): ?>
                <section class="archive-year" id="year-<?= (int) $year ?>">
                    <h2 class="archive-year-title"><?= (int) $year ?><span><?= number_format($yearTotals[$year] ?? 0) ?> 篇</span></h2>
                    <?php foreach ($months as $month => $items): ?>
                    <div class="archive-month">
                        <h3 class="archive-month-title">
                            <a href="/<?= (int) $year ?>/<?= sprintf('%02d', (int) $month) ?>/"><?= (int) $month ?> 月</a>
                            <span><?= count($items) ?> 篇</span>
                        </h3>
                        <ul class="archive-list">
                            <?php foreach ($items as $item): ?>
                            <?php $cats = categories_of((int) $item['id']); ?>
                            <li class="archive-item">
                                <time class="archive-date" datetime="<?= e(iso_date((string) $item['created_at'])) ?>"><?= e(format_date((string) $item['created_at'], 'm-d')) ?></time>
                                <a class="archive-link" href="<?= e(post_url($item)) ?>"><?= e($item['title']) ?></a>
                                <?php if ($cats): ?><span class="archive-cat"><?= e($cats[0]['name']) ?></span><?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </section>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-results"><p>暂时还没有文章可以归档。</p></div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
