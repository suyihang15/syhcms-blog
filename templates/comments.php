<?php
/** @var array $post */
$comment_count = (int) ($comment_count ?? 0);
$guestbook = (string) $post['template'] === 'page-guestbook.php';
$flashes = take_flashes();
$requireEmail = $guestbook ? false : setting_bool('require_name_email', true);
$renderTree = function (array $items, int $depth = 0, string $replyTo = '') use (&$renderTree, $post): void {
    echo '<ol class="comment-list' . ($depth > 0 ? ' comment-children' : '') . '">';
    foreach ($items as $c) {
        $children = is_array($c['children'] ?? null) ? $c['children'] : [];
        $isAdmin  = comment_author_is_admin($c);
        $classes  = 'comment' . ($children ? ' parent' : '') . ($isAdmin ? ' comment-by-admin' : '');
        $author   = (string) $c['author'];
        $avatar   = avatar_url((string) $c['email'], 48);
        echo '<li id="comment-' . (int) $c['id'] . '" class="' . $classes . '">';
        echo '<article class="comment-body"><footer class="comment-meta"><div class="comment-author vcard">';
        if ($avatar !== '') {
            echo '<img alt="' . e($author) . '" src="' . e($avatar) . '" class="avatar avatar-48 photo" height="48" width="48" loading="lazy">';
        } else {
            echo '<span class="comment-avatar-fallback" aria-hidden="true">' . e(mb_substr($author, 0, 1)) . '</span>';
        }
        if (trim((string) $c['url']) !== '') {
            echo '<a class="fn comment-author-link" href="' . e((string) $c['url']) . '" target="_blank" rel="external nofollow ugc noopener">' . e($author) . '</a>';
        } else {
            echo '<b class="fn">' . e($author) . '</b>';
        }
        if ($isAdmin) {
            echo '<span class="comment-role-badge">博主</span>';
        }
        echo '</div><div class="comment-metadata">';
        if ($replyTo !== '') {
            echo '<span class="comment-reply-to">回复 <span class="comment-reply-name">@' . e($replyTo) . '</span></span>';
        }
        echo '<a href="#comment-' . (int) $c['id'] . '"><time datetime="' . e(iso_date((string) $c['created_at'])) . '">' . e(format_date((string) $c['created_at'], 'Y-m-d H:i')) . '</time></a></div></footer>';
        echo '<div class="comment-content">' . nl2br(e((string) $c['content'])) . '</div>';
        if ($post['comment_status'] === 'open') {
            echo '<div class="reply"><button type="button" class="comment-reply-link" data-comment-id="' . (int) $c['id'] . '" data-author="' . e($author) . '" aria-label="回复 ' . e($author) . '">回复</button></div>';
        }
        echo '</article>';
        if ($children) {
            $renderTree($children, $depth + 1, $author);
        }
        echo '</li>';
    }
    echo '</ol>';
};
?>
<div id="comments" class="comments-area">
    <?php foreach ($flashes as $f): ?>
    <div class="flash flash-<?= e((string) $f['type']) ?>"><?= e((string) $f['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($comment_count > 0): ?>
    <h2 class="comments-title"><?= $comment_count ?> 条评论</h2>
    <?php $renderTree($comments); ?>
    <?php elseif ($guestbook): ?>
    <p class="guestbook-empty">还没有留言，来坐第一排吧。</p>
    <?php endif; ?>

    <?php if ($post['comment_status'] !== 'open'): ?>
    <p class="no-comments">评论已关闭。</p>
    <?php else: ?>
    <?php
    $currentUser = Auth::user();
    $authorValue = commenter_cookie('author');
    $emailValue  = commenter_cookie('email');
    $urlValue    = commenter_cookie('url');
    if ($currentUser) {
        if (trim($authorValue) === '') {
            $authorValue = (string) ($currentUser['display_name'] !== '' ? $currentUser['display_name'] : $currentUser['login']);
        }
        if (trim($emailValue) === '') {
            $emailValue = (string) $currentUser['email'];
        }
    }
    ?>
    <h3 id="reply-title" class="comment-reply-title"><?= $guestbook ? '写下你的留言' : '发表评论' ?></h3>
    <p class="comment-notes"><?= $guestbook ? '昵称必填，邮箱和网站选填。首次留言会先审核，通过后才会公开显示。' : '邮箱用于显示头像，不会公开。' ?></p>
    <?php if ($currentUser && Auth::can('manage')): ?>
    <p class="comment-notes comment-staff-note">已登录为 <strong><?= e((string) ($currentUser['display_name'] !== '' ? $currentUser['display_name'] : $currentUser['login'])) ?></strong>，提交后直接发布。</p>
    <?php endif; ?>
    <form action="/comment/" method="post" class="comment-form" id="commentform">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
        <input type="hidden" name="parent_id" id="comment-parent-id" value="0">
        <p class="comment-form-author">
            <label for="author">昵称<?= $guestbook || $requireEmail ? ' <span class="required">*</span>' : '' ?></label>
            <input id="author" name="author" type="text" value="<?= e($authorValue) ?>" size="30" maxlength="<?= $guestbook ? 30 : 245 ?>" autocomplete="name"<?= $guestbook || $requireEmail ? ' required' : '' ?>>
        </p>
        <p class="comment-form-email">
            <label for="email">邮箱<?= $requireEmail ? ' <span class="required">*</span>' : ' <span class="optional">' . ($guestbook ? '选填' : '可选') . '</span>' ?></label>
            <input id="email" name="email" type="email" value="<?= e($emailValue) ?>" size="30" maxlength="100" autocomplete="email"<?= $requireEmail ? ' required' : '' ?>>
        </p>
        <p class="comment-form-url">
            <label for="url">网站 <span class="optional">可选</span></label>
            <input id="url" name="url" type="text" inputmode="url" value="<?= e($urlValue) ?>" size="30" maxlength="200" placeholder="https://example.com" autocomplete="url">
        </p>
        <p class="comment-form-comment">
            <label for="comment"><?= $guestbook ? '留言内容' : '评论内容' ?></label>
            <textarea id="comment" name="comment" cols="45" rows="6" required maxlength="<?= $guestbook ? 2000 : 5000 ?>"></textarea>
        </p>
        <p class="comment-form-honeypot" aria-hidden="true" style="position:absolute;left:-9999px;">
            <label for="website">网站</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </p>
        <p class="form-submit">
            <button type="submit" class="submit"><?= $guestbook ? '提交留言' : '提交评论' ?></button>
        </p>
    </form>
    <script>
    (function () {
        var form = document.getElementById('commentform');
        if (!form) return;
        var parentInput = document.getElementById('comment-parent-id');
        var textarea = document.getElementById('comment');
        var title = document.getElementById('reply-title');
        var original = title ? title.textContent : '';
        document.querySelectorAll('.comment-reply-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!parentInput) return;
                parentInput.value = btn.getAttribute('data-comment-id') || '0';
                if (title) title.textContent = '回复 ' + (btn.getAttribute('data-author') || '') + '：';
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                if (textarea) textarea.focus();
            });
        });
        if (title) {
            title.addEventListener('click', function () {
                if (!parentInput) return;
                if (parentInput.value !== '0') {
                    parentInput.value = '0';
                    title.textContent = original;
                }
            });
        }
    })();
    </script>
    <?php endif; ?>
</div>
