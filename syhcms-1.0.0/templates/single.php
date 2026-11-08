<?php
/** @var array $post */
require SYH_ROOT . '/templates/header.php';
$cats = $cats ?? [];
$tags = $tags ?? [];
$prev = $prev ?? null;
$next = $next ?? null;
$related = $related ?? [];
$comments = $comments ?? [];
$comment_count = (int) ($comment_count ?? 0);
$shareUrl = site_url() . post_url($post);
$shareTitle = (string) $post['title'];
?>
<main class="site-main" id="content">
    <div class="content-with-sidebar">
        <div class="content-area">
            <nav class="breadcrumbs" aria-label="面包屑导航">
                <a href="/" class="breadcrumb-home">首页</a>
                <span class="breadcrumb-sep">/</span>
                <?php if ($cats): ?><a href="<?= e(term_url($cats[0])) ?>"><?= e($cats[0]['name']) ?></a><span class="breadcrumb-sep">/</span><?php endif; ?>
                <span class="breadcrumb-current"><?= e($post['title']) ?></span>
            </nav>
            <article id="post-<?= (int) $post['id'] ?>" class="post single-post">
                <header>
                    <h1 class="entry-title"><?= e($post['title']) ?></h1>
                    <div class="entry-meta">
                        <span class="meta-date"><?= e(format_date((string) $post['created_at'])) ?></span>
                        <span class="meta-sep">·</span>
                        <span class="meta-author"><?= e(post_author_name($post)) ?></span>
                        <span class="meta-sep">·</span>
                        <span class="meta-comments"><?= $comment_count > 0 ? $comment_count . ' 条评论' : '暂无评论' ?></span>
                        <?php if ((int) $post['views'] > 0): ?>
                        <span class="meta-sep">·</span>
                        <span class="meta-views"><?= number_format((int) $post['views']) ?> 次浏览</span>
                        <?php endif; ?>
                        <?php $published = strtotime((string) $post['created_at']); $updated = strtotime((string) $post['updated_at']); ?>
                        <?php if ($updated && $published && $updated > $published + 86400): ?>
                        <span class="meta-sep">·</span>
                        <span class="meta-updated" title="最后更新于 <?= e(format_date((string) $post['updated_at'], 'Y-m-d H:i')) ?>">更新于 <?= e(format_date((string) $post['updated_at'], 'Y-m-d')) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="entry-meta-extra">
                        <span>约 <?= word_count((string) $post['content']) ?> 字</span>
                        <span>·</span>
                        <span>预计阅读 <?= reading_time((string) $post['content']) ?> 分钟</span>
                        <?php if ($cats): ?>
                        <span>·</span>
                        <span class="meta-categories-inline">
                            <?php foreach ($cats as $cat): ?><a href="<?= e(term_url($cat)) ?>"><?= e($cat['name']) ?></a><?php endforeach; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="font-size-controls">
                    <button class="font-size-btn font-size-decrease" aria-label="减小字号">A-</button>
                    <button class="font-size-btn font-size-reset" aria-label="重置字号">A</button>
                    <button class="font-size-btn font-size-increase" aria-label="增大字号">A+</button>
                </div>
                <button class="reading-mode-toggle" aria-label="阅读模式" title="阅读模式">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                    </svg>
                </button>

                <div class="entry-content"><?= content_with_toc((string) $post['content']) ?></div>

                <?php if ($tags): ?>
                <div class="post-tags">
                    <?php foreach ($tags as $tag): ?><a href="<?= e(term_url($tag)) ?>" class="post-tag">#<?= e($tag['name']) ?></a><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="share-buttons">
                    <span class="share-label">分享：</span>
                    <a href="https://connect.qq.com/widget/shareqq/index.html?url=<?= rawurlencode($shareUrl) ?>&amp;title=<?= rawurlencode($shareTitle) ?>" target="_blank" rel="noopener" class="share-btn share-qq" title="分享到QQ">QQ</a>
                    <a href="https://service.weibo.com/share/share.php?url=<?= rawurlencode($shareUrl) ?>&amp;title=<?= rawurlencode($shareTitle) ?>" target="_blank" rel="noopener" class="share-btn share-weibo" title="分享到微博">微博</a>
                    <a href="https://t.me/share/url?url=<?= rawurlencode($shareUrl) ?>&text=<?= rawurlencode($shareTitle) ?>" target="_blank" rel="noopener" class="share-btn share-telegram" title="分享到Telegram">TG</a>
                    <button class="share-btn share-copy" data-url="<?= e($shareUrl) ?>" title="复制链接">链接</button>
                </div>

                <?php if ($related): ?>
                <div class="related-posts">
                    <h3 class="related-posts-title">相关文章</h3>
                    <div class="related-posts-grid">
                        <?php foreach ($related as $rel): ?>
                        <article class="related-post-item">
                            <?php $thumb = featured_image_html($rel, 'related-post-thumb-img'); if ($thumb !== ''): ?>
                            <a href="<?= e(post_url($rel)) ?>" class="related-post-thumb"><?= $thumb ?></a>
                            <?php endif; ?>
                            <h4 class="related-post-title"><a href="<?= e(post_url($rel)) ?>"><?= e($rel['title']) ?></a></h4>
                            <time class="related-post-date" datetime="<?= e(iso_date((string) $rel['created_at'])) ?>"><?= e(format_date((string) $rel['created_at'])) ?></time>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <nav class="post-navigation">
                    <?php if ($prev): ?>
                    <a href="<?= e(post_url($prev)) ?>" class="post-nav-prev" title="<?= e($prev['title']) ?>">
                        <span class="post-nav-label">← 上一篇</span>
                        <span class="post-nav-title"><?= e($prev['title']) ?></span>
                    </a>
                    <?php else: ?><span class="post-nav-prev post-nav-empty"></span><?php endif; ?>
                    <?php if ($next): ?>
                    <a href="<?= e(post_url($next)) ?>" class="post-nav-next" title="<?= e($next['title']) ?>">
                        <span class="post-nav-label">下一篇 →</span>
                        <span class="post-nav-title"><?= e($next['title']) ?></span>
                    </a>
                    <?php else: ?><span class="post-nav-next post-nav-empty"></span><?php endif; ?>
                </nav>
            </article>

            <?php if ($post['comment_status'] === 'open' || $comment_count > 0): ?>
                <?php render('comments', ['post' => $post, 'comments' => $comments, 'comment_count' => $comment_count]); ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
