<?php require SYH_ROOT . '/templates/header.php'; ?>
<main class="site-main" id="content">
    <div class="content-with-sidebar">
        <div class="content-area">
            <nav class="breadcrumbs" aria-label="面包屑导航">
                <a href="/" class="breadcrumb-home">首页</a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current"><?= e($heading ?? $title) ?></span>
            </nav>
            <header class="archive-header">
                <h1 class="archive-title"><?= e($heading ?? $title) ?></h1>
                <?php if (isset($term) && trim((string) $term['description']) !== ''): ?>
                <p class="archive-description"><?= e((string) $term['description']) ?></p>
                <?php endif; ?>
            </header>
            <div id="post-list" class="post-list">
                <?php if ($posts): ?>
                    <?php foreach ($posts as $post): render('partials/post-card', ['post' => $post]); endforeach; ?>
                <?php else: ?>
                    <div class="no-results"><p>这里还没有文章。</p></div>
                <?php endif; ?>
            </div>
            <?= pagination($paged, $total_pages, $base_url ?? '/') ?>
        </div>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
