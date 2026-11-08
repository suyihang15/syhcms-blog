<?php require SYH_ROOT . '/templates/header.php'; ?>
<main class="site-main" id="content">
    <div class="content-area-full">
        <div class="content-area">
            <?php if ($paged === 1): ?>
            <?php
            $cover = normalize_media_url((string) setting('homepage_cover_image'));
            if ($cover === '') {
                $cover = '/assets/home.jpg';
            }
            $profileTitle = trim((string) setting('profile_title')) ?: site_name();
            ?>
            <section class="homepage-profile">
                <div class="profile-cover">
                    <img src="<?= e($cover) ?>" alt="<?= e($profileTitle) ?>" loading="eager" fetchpriority="high" decoding="async">
                </div>
                <div class="profile-info">
                    <h2 class="profile-title"><?= e($profileTitle) ?></h2>
                    <?php if (trim((string) setting('profile_tagline')) !== ''): ?>
                    <p class="profile-tagline"><?= e((string) setting('profile_tagline')) ?></p>
                    <?php endif; ?>
                    <?php $intro = trim((string) setting('profile_intro')) ?: site_description(); ?>
                    <?php if ($intro !== ''): ?><p class="profile-intro"><?= e($intro) ?></p><?php endif; ?>
                    <div class="profile-details">
                        <?php foreach (['profile_location', 'profile_experience', 'profile_stack'] as $key): ?>
                        <?php if (trim((string) setting($key)) !== ''): ?>
                        <span class="<?= e(str_replace('profile_', 'profile-', $key)) ?>"><?= e((string) setting($key)) ?></span>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
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
