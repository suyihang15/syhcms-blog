<?php
/** @var array $post */
$cats = categories_of((int) $post['id']);
?>
<article id="post-<?= (int) $post['id'] ?>" class="post">
    <?php $thumb = featured_image_html($post); if ($thumb !== ''): ?>
    <a href="<?= e(post_url($post)) ?>" class="post-thumbnail">
        <?= $thumb ?>
    </a>
    <?php endif; ?>
    <div class="post-content">
        <h2 class="entry-title"><a href="<?= e(post_url($post)) ?>"><?= e($post['title']) ?></a></h2>
        <div class="entry-meta">
            <span class="meta-date"><?= e(format_date((string) $post['created_at'])) ?></span>
            <?php if ($cats): ?>
            <span class="meta-sep">·</span>
            <span class="meta-categories">
                <?php foreach ($cats as $i => $cat): ?><?= $i ? ', ' : '' ?><a href="<?= e(term_url($cat)) ?>"><?= e($cat['name']) ?></a><?php endforeach; ?>
            </span>
            <?php endif; ?>
            <?php if ((int) $post['views'] > 0): ?>
            <span class="meta-sep">·</span>
            <span class="meta-views"><?= number_format((int) $post['views']) ?> 次浏览</span>
            <?php endif; ?>
        </div>
        <div class="entry-excerpt"><p><?= e(post_excerpt($post)) ?></p></div>
        <a href="<?= e(post_url($post)) ?>" class="read-more">阅读全文 →</a>
    </div>
</article>
