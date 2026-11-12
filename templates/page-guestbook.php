<?php
/** @var array $page */
require SYH_ROOT . '/templates/header.php';
?>
<main class="site-main" id="content">
    <article id="post-<?= (int) $page['id'] ?>" class="post guestbook-page">
        <header class="guestbook-header">
            <p class="archive-eyebrow">留言板</p>
            <h1 class="entry-title"><?= e($page['title']) ?></h1>
            <p class="guestbook-lede">想说什么都可以：想法、建议、吐槽、打个招呼都欢迎。友善和守规矩，是这里唯一的门槛。</p>
            <p class="guestbook-meta">
                <?php if ($comment_count > 0): ?>
                <span class="guestbook-meta-item"><?= $comment_count ?> 条留言</span>
                <span class="guestbook-meta-dot" aria-hidden="true">·</span>
                <?php endif; ?>
                <span class="guestbook-meta-item">无需注册，填好昵称即可</span>
            </p>
        </header>

        <?php if (trim((string) $page['content']) !== ''): ?>
        <div class="entry-content guestbook-intro"><?= content_html((string) $page['content']) ?></div>
        <?php endif; ?>

        <aside class="guestbook-rules" aria-labelledby="guestbook-rules-title">
            <h2 class="guestbook-rules-title" id="guestbook-rules-title">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                留言规范
            </h2>
            <ul>
                <li>友善交流，尊重不同观点，不人身攻击、不引战。</li>
                <li>不发布违法、色情、暴力、赌博、诈骗等违规内容。</li>
                <li>广告、推广链接、拉人头和二维码会被直接删除。</li>
                <li>涉及他人隐私的信息，请先征得对方同意。</li>
                <li>邮箱选填，只用于显示头像和接收回复通知，不会公开。</li>
                <li>首次留言会先人工审核，通过后才会公开显示。</li>
            </ul>
        </aside>

        <details class="guestbook-privacy" id="guestbook-privacy">
            <summary id="guestbook-privacy-title">隐私说明：你的信息会怎么被处理</summary>
            <ul>
                <li>昵称、留言内容和提交时间会公开展示，请勿在留言中填写真实姓名、住址、电话等敏感信息。</li>
                <li>邮箱为选填项。填写后仅用于显示头像和接收回复通知，不会公开显示，也不会用于营销。</li>
                <li>网站为选填项。填写后会公开显示为头像链接；不填则只显示默认头像。</li>
                <li>为防止刷屏和恶意提交，系统会记录提交时的 IP 地址与浏览器信息，仅管理员可见，只用于处理滥用与安全问题，不对外展示。</li>
            </ul>
        </details>

        <?php render('comments', ['post' => $page, 'comments' => $comments, 'comment_count' => $comment_count]); ?>
    </article>
</main>
<?php require SYH_ROOT . '/templates/footer.php'; ?>
