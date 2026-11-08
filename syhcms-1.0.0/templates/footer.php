<?php
$syh      = $GLOBALS['syh_page'] ?? [];
$siteName = site_name();
$groups   = link_groups();
$linksCount = 0;
foreach ($groups as $items) {
    $linksCount += count($items);
}
$linksPage = DB::one("SELECT * FROM posts WHERE type = 'page' AND status = 'publish' AND template = 'page-links.php' ORDER BY menu_order, id LIMIT 1");
$wechatQr  = normalize_media_url((string) setting('donation_wechat_qr', ''));
$alipayQr  = normalize_media_url((string) setting('donation_alipay_qr', ''));
$wechat    = trim((string) setting('donation_wechat', ''));
$alipay    = trim((string) setting('donation_alipay', ''));
$hasDonation = $wechatQr !== '' || $alipayQr !== '' || $wechat !== '' || $alipay !== '';
$donationDesc = trim((string) setting('donation_description', ''));
$uptimeStart  = strtotime((string) setting('site_launch', '2026-03-23 00:00:00')) ?: time();
$uptime       = max(0, time() - $uptimeStart);
$days         = intdiv($uptime, 86400);
$hours        = intdiv($uptime % 86400, 3600);
$minutes      = intdiv($uptime % 3600, 60);
$seconds      = $uptime % 60;
$icp          = (string) setting('icp_record_number', '');
$icpUrl       = (string) setting('icp_record_url', '');
$psb          = (string) setting('psb_record_number', '');
$psbUrl       = (string) setting('psb_record_url', '');
$jsVer        = is_file(SYH_ROOT . '/public/assets/site.js') ? (string) filemtime(SYH_ROOT . '/public/assets/site.js') : '1';
$postId       = isset($GLOBALS['syh_post']['id']) ? (int) $GLOBALS['syh_post']['id'] : 0;
$isPost       = isset($GLOBALS['syh_post']['type']) && $GLOBALS['syh_post']['type'] === 'post';
if (!$isPost) {
    $postId = 0;
}
?>
<button class="back-to-top" aria-label="返回顶部">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
</button>
<footer class="site-footer">
    <div class="footer-inner">
    <?php if ($hasDonation || $linksCount > 0): ?>
    <div class="footer-actions-grid<?= (!$hasDonation || $linksCount === 0) ? ' footer-actions-grid--single' : '' ?>">
        <?php if ($hasDonation): ?>
        <div class="footer-actions">
            <details class="donate-details">
                <summary>
                    <span class="donate-summary-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.7-7.5 1.1-1.1a5.5 5.5 0 0 0 0-7.8z"/></svg>
                    </span>
                    <span class="donate-summary-text">打赏支持</span>
                    <span class="donate-chevron" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </span>
                </summary>
                <div class="donate-box">
                    <?php if ($donationDesc !== ''): ?><p class="donate-desc"><?= e($donationDesc) ?></p><?php endif; ?>
                    <div class="donate-methods">
                        <?php foreach ([['微信', 'donate-badge--wechat', $wechatQr, $wechat], ['支付宝', 'donate-badge--alipay', $alipayQr, $alipay]] as $method): ?>
                            <?php if ($method[2] === '' && $method[3] === '') { continue; } ?>
                            <figure class="donate-method<?= $method[2] === '' ? ' donate-method--text' : '' ?>">
                                <?php if ($method[2] !== ''): ?>
                                <span class="donate-qr-wrap"><img class="donation-qr" src="<?= e($method[2]) ?>" alt="<?= e($method[0]) ?>打赏二维码" loading="lazy" decoding="async" width="150" height="150"></span>
                                <?php endif; ?>
                                <figcaption>
                                    <span class="donate-badge <?= e($method[1]) ?>"><?= e($method[0]) ?></span>
                                    <?php if ($method[3] !== ''): ?><code class="donate-account"><?= e($method[3]) ?></code><?php else: ?><span class="donate-hint">扫码打赏</span><?php endif; ?>
                                </figcaption>
                            </figure>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
        </div>
        <?php endif; ?>

        <?php if ($linksCount > 0): ?>
        <div class="footer-links-showcase">
            <h3 class="footer-links-showcase-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                友情链接
                <span class="footer-links-count"><?= $linksCount ?></span>
            </h3>
            <div class="footer-links-groups">
                <?php foreach ($groups as $groupName => $items): ?>
                <section class="footer-links-group">
                    <h4 class="footer-links-group-title"><?= e($groupName) ?><span class="footer-links-group-count"><?= count($items) ?></span></h4>
                    <div class="footer-links-mini-grid">
                        <?php foreach ($items as $item): ?>
                        <?php
                        $name = (string) $item['name'];
                        $image = normalize_media_url((string) $item['image']);
                        $gradient = friend_gradient($name);
                        ?>
                        <a href="<?= e((string) $item['url']) ?>" target="_blank" rel="noopener" class="footer-link-mini" title="访问 <?= e($name) ?>">
                            <div class="footer-link-mini-avatar<?= $image === '' ? ' no-image' : '' ?>"<?= $image === '' ? ' style="background: ' . e($gradient) . ';"' : '' ?>>
                                <?php if ($image !== ''): ?>
                                <img src="<?= e($image) ?>" alt="<?= e($name) ?>" loading="lazy" onerror="this.style.display='none';this.parentElement.classList.add('no-image');this.parentElement.style.background='<?= e($gradient) ?>';this.nextElementSibling.style.display='flex';">
                                <span class="footer-link-mini-letter" style="display:none;"><?= e(mb_substr($name, 0, 1)) ?></span>
                                <?php else: ?>
                                <span class="footer-link-mini-letter" style="display:flex;"><?= e(mb_substr($name, 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="footer-link-mini-name"><?= e($name) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>
            </div>
            <?php if ($linksPage): ?>
            <a href="<?= e(post_url($linksPage)) ?>" class="footer-links-more">查看全部友链
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="footer-meta">
        <p>© <?= date('Y') ?> <a href="/" rel="home"><?= e($siteName) ?></a> · Theme by suyihang15 · MIT License</p>
        <p class="site-uptime" title="自 <?= e(format_date(date('Y-m-d H:i:s', $uptimeStart), 'Y 年 n 月 j 日')) ?> 起">
            <span class="site-uptime-dot" aria-hidden="true"></span>
            已稳定运行
            <span class="site-uptime-value" data-site-uptime data-start="<?= $uptimeStart * 1000 ?>">
                <span class="site-uptime-num" data-unit="d"><?= number_format($days) ?></span> 天
                <span class="site-uptime-num" data-unit="h"><?= sprintf('%02d', $hours) ?></span> 时
                <span class="site-uptime-num" data-unit="m"><?= sprintf('%02d', $minutes) ?></span> 分
                <span class="site-uptime-num" data-unit="s"><?= sprintf('%02d', $seconds) ?></span> 秒
            </span>
        </p>
        <p>
            <?php if ($icp !== ''): ?><?php if ($icpUrl !== ''): ?><a href="<?= e($icpUrl) ?>" target="_blank" rel="noopener nofollow"><?= e($icp) ?></a><?php else: ?><?= e($icp) ?><?php endif; ?><?php endif; ?>
            <?php if ($icp !== '' && $psb !== ''): ?> · <?php endif; ?>
            <?php if ($psb !== ''): ?><?php if ($psbUrl !== ''): ?><a href="<?= e($psbUrl) ?>" target="_blank" rel="noopener nofollow"><?= e($psb) ?></a><?php else: ?><?= e($psb) ?><?php endif; ?><?php endif; ?>
        </p>
        <p><a href="/feed/">RSS 订阅</a><?php if ($linksPage): ?> · <a href="<?= e(post_url($linksPage)) ?>">友链</a><?php endif; ?></p>
    </div>
    </div>
</footer>
</div>
<script>window.syhStats = <?= json_encode([
    'hitEndpoint'   => '/api/hit',
    'statsEndpoint' => '/api/stats',
    'postId'        => $postId,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/assets/site.js?v=<?= e($jsVer) ?>"></script>
</body>
</html>
