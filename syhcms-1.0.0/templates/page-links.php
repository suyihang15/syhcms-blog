<?php
/** @var array $page */
/** @var array $link_groups */
require SYH_ROOT . '/templates/header.php';
$siteInfo = [
    'name' => site_name(),
    'url'  => site_url() . '/',
    'icon' => site_icon_url(),
    'desc' => site_description(),
];
$applyTitle = trim((string) setting('links_page_apply_title')) ?: '申请友链';
$applyContent = trim((string) setting('links_page_apply_content'));
$applyContact = trim((string) setting('links_page_apply_contact'));
$siteIntro = trim((string) setting('links_page_site_intro', '互帮互助，万物互联'));
?>
<main class="site-main" id="content">
    <div class="content-with-sidebar">
        <div class="content-area">
            <article id="post-<?= (int) $page['id'] ?>" class="post">
                <header><h1 class="entry-title"><?= e($page['title']) ?></h1></header>
                <div class="entry-content">
                    <?php if (trim((string) $page['content']) !== ''): ?>
                    <?= content_html((string) $page['content']) ?>
                    <?php endif; ?>
                    <?php if (trim((string) setting('links_page_intro')) !== ''): ?>
                    <p class="friend-links-intro"><?= e((string) setting('links_page_intro')) ?></p>
                    <?php endif; ?>

                    <h2 class="friend-section-title">友情链接</h2>
                    <?php if ($link_groups): ?>
                    <div class="friend-links-groups">
                        <?php foreach ($link_groups as $groupName => $items): ?>
                        <section class="friend-links-group">
                            <h3 class="friend-group-title"><?= e((string) $groupName) ?><span class="friend-group-count"><?= count($items) ?></span></h3>
                            <div class="friend-links-grid">
                                <?php foreach ($items as $item): ?>
                                <?php
                                $name = (string) $item['name'];
                                $url  = (string) $item['url'];
                                $image = normalize_media_url((string) $item['image']);
                                if ($image === '') {
                                    $image = normalize_media_url((string) $item['image_dark']);
                                }
                                $gradient = friend_gradient($name);
                                $host = link_host($url);
                                ?>
                                <div class="friend-card">
                                    <div class="friend-card-header">
                                        <a class="friend-icon-link" href="<?= e($url) ?>" target="_blank" rel="noopener nofollow" title="访问 <?= e($name) ?>">
                                            <span class="friend-icon-wrap<?= $image === '' ? ' no-image' : '' ?>"<?= $image === '' ? ' style="background:' . e($gradient) . ';"' : '' ?>>
                                                <?php if ($image !== ''): ?>
                                                <img class="friend-icon" src="<?= e($image) ?>" alt="<?= e($name) ?>" loading="lazy" decoding="async" width="48" height="48" onerror="this.classList.add('is-hidden');var w=this.parentElement;w.classList.add('no-image');w.style.background='<?= e($gradient) ?>';var l=w.querySelector('.friend-icon-letter');if(l){l.style.display='flex';}">
                                                <?php endif; ?>
                                                <span class="friend-icon-letter"<?= $image !== '' ? ' style="display:none;"' : '' ?>><?= e(mb_substr($name, 0, 1)) ?></span>
                                            </span>
                                        </a>
                                        <div class="friend-info">
                                            <h3 class="friend-name">
                                                <a href="<?= e($url) ?>" target="_blank" rel="noopener nofollow"><?= e($name) ?></a>
                                                <?php if ($host !== ''): ?><span class="friend-host"><?= e($host) ?></span><?php endif; ?>
                                            </h3>
                                            <?php if (trim((string) $item['description']) !== ''): ?><p class="friend-description"><?= e((string) $item['description']) ?></p><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="friend-latest-post">
                                        <?php if (trim((string) $item['rss']) !== ''): ?>
                                        <span class="no-latest-post"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7.5 12 12 15.5 14"></polyline></svg>暂无更新</span>
                                        <?php else: ?>
                                        <span class="no-latest-post" title="该站点未提供 RSS 订阅"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><polyline points="12 7.5 12 12 15.5 14"></polyline></svg>未提供 RSS</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="friend-links-empty">还没有添加友链。</p>
                    <?php endif; ?>

                    <section class="friend-apply" aria-labelledby="friend-apply-title">
                        <h2 class="friend-section-title" id="friend-apply-title"><?= e($applyTitle) ?></h2>
                        <div class="friend-apply-grid">
                            <div class="friend-apply-box">
                                <h3>本站信息</h3>
                                <ul class="friend-site-info">
                                    <li><span>名称</span><?= e($siteInfo['name']) ?></li>
                                    <li><span>地址</span><code><?= e($siteInfo['url']) ?></code></li>
                                    <?php if ($siteInfo['icon'] !== ''): ?><li><span>图标</span><code><?= e($siteInfo['icon']) ?></code></li><?php endif; ?>
                                    <?php if ($siteInfo['desc'] !== ''): ?><li><span>描述</span><?= e($siteInfo['desc']) ?></li><?php endif; ?>
                                    <?php if ($siteIntro !== ''): ?><li class="friend-site-intro"><span>简介</span><?= e($siteIntro) ?></li><?php endif; ?>
                                </ul>
                            </div>
                            <div class="friend-apply-box">
                                <h3>申请要求</h3>
                                <?php if ($applyContent !== ''): ?>
                                <?= content_html($applyContent) ?>
                                <?php else: ?>
                                <ul class="friend-apply-rules">
                                    <li>站点内容合法、原创为主，无违规内容。</li>
                                    <li>已在我的站点添加本站链接。</li>
                                    <li>网站能正常访问，长期维护。</li>
                                </ul>
                                <?php endif; ?>
                                <?php if ($applyContact !== ''): ?><p class="friend-apply-contact">联系方式：<?= e($applyContact) ?></p><?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </article>
            <?php if ($page['comment_status'] === 'open' || $comment_count > 0): ?>
                <?php render('comments', ['post' => $page, 'comments' => $comments, 'comment_count' => $comment_count]); ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
