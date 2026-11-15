<?php require SYH_ROOT . '/templates/header.php'; ?>
<?php $homeTotal = (int) DB::val("SELECT COUNT(*) FROM posts WHERE type = 'post' AND status = 'publish'", [], 0); ?>
<main class="site-main" id="content">
    <div class="content-area-full">
        <div class="content-area">
            <?php if ($paged === 1): ?>
            <header class="home-lede">
                <p class="home-lede-eyebrow">日志</p>
                <p class="home-lede-note">共 <?= number_format($homeTotal) ?> 篇，按更新时间排列。</p>
            </header>
            <?php endif; ?>

            <?php if ($posts): ?>
            <div class="post-list">
                <?php foreach ($posts as $post): render('partials/post-card', ['post' => $post]); endforeach; ?>
            </div>
            <?= pagination($paged, $total_pages, '/') ?>
            <?php else: ?>
            <div class="no-results"><p>暂无文章</p></div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
