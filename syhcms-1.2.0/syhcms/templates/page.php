<?php
/** @var array $page */
require SYH_ROOT . '/templates/header.php';
?>
<main class="site-main" id="content">
    <div class="content-with-sidebar">
        <div class="content-area">
            <nav class="breadcrumbs" aria-label="面包屑导航">
                <a href="/" class="breadcrumb-home">首页</a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current"><?= e($page['title']) ?></span>
            </nav>
            <article id="post-<?= (int) $page['id'] ?>" class="post page">
                <header>
                    <h1 class="entry-title"><?= e($page['title']) ?></h1>
                </header>
                <div class="entry-content"><?= content_with_toc((string) $page['content']) ?></div>
            </article>
            <?php if ($page['comment_status'] === 'open' || $comment_count > 0): ?>
                <?php render('comments', ['post' => $page, 'comments' => $comments, 'comment_count' => $comment_count]); ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
