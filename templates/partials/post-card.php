<?php
/** @var array $post */
$cats        = categories_of((int) $post['id']);
$createdTs   = strtotime((string) $post['created_at']);
$updatedTs   = strtotime((string) ($post['updated_at'] ?? '')) ?: $createdTs;
$isUpdated   = $updatedTs > $createdTs + 86400;
$shownDate   = $updatedTs > 0 ? format_date((string) $post['updated_at']) : format_date((string) $post['created_at']);
$dateIso     = $updatedTs > 0 ? iso_date((string) $post['updated_at']) : iso_date((string) $post['created_at']);
?>
<article id="post-<?= (int) $post['id'] ?>" class="post">
    <div class="post-list-head">
        <span class="post-list-date">
            <time class="meta-date" datetime="<?= e($dateIso) ?>"><?= e($shownDate) ?></time>
            <?php if ($isUpdated): ?><span class="meta-updated-mark">更新</span><?php endif; ?>
        </span>
        <?php if ($cats): ?>
        <span class="meta-categories">
            <?php foreach ($cats as $i => $cat): ?><?= $i ? '<span class="meta-cat-sep">·</span>' : '' ?><a href="<?= e(term_url($cat)) ?>"><?= e($cat['name']) ?></a><?php endforeach; ?>
        </span>
        <?php endif; ?>
    </div>
    <h2 class="entry-title"><a href="<?= e(post_url($post)) ?>"><?= e($post['title']) ?></a></h2>
    <div class="entry-excerpt"><p><?= e(post_excerpt($post)) ?></p></div>
</article>
