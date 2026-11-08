<?php require SYH_ROOT . '/templates/header.php'; ?>
<main class="site-main" id="content">
    <div class="content-with-sidebar">
        <div class="content-area">
            <nav class="breadcrumbs" aria-label="面包屑导航">
                <a href="/" class="breadcrumb-home">首页</a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current">搜索：<?= e($query) ?></span>
            </nav>
            <header class="page-header search-header">
                <h1 class="page-title">搜索结果：<span><?= e($query) ?></span></h1>
                <p class="search-results-count">共找到 <?= (int) $total ?> 条结果</p>
            </header>
            <?php if ($posts): ?>
            <div class="post-list">
                <?php foreach ($posts as $post): render('partials/post-card', ['post' => $post]); endforeach; ?>
            </div>
            <?= pagination($paged, $total_pages, '/', $base_query ?? []) ?>
            <?php else: ?>
            <div class="no-results">
                <p>没有找到相关内容，换个关键词试试？</p>
                <?= search_form($query) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
