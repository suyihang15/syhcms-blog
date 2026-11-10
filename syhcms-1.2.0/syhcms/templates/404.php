<?php require SYH_ROOT . '/templates/header.php'; ?>
<main class="site-main" id="content">
    <div class="error-404">
        <div class="error-code">404</div>
        <p class="error-message">抱歉，你访问的页面不存在或已被移除。</p>
        <div class="error-search"><?= search_form() ?></div>
        <p style="margin-top: 2rem;">
            <a href="/" style="display: inline-block; padding: 0.75rem 2rem; background: var(--primary); color: white; border-radius: var(--radius); font-weight: 500;">返回首页</a>
        </p>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
