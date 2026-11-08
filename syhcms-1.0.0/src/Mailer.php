<?php
declare(strict_types=1);

/**
 * 极简 SMTP 客户端（无第三方依赖）。
 * 仅支持 AUTH LOGIN + STARTTLS/SSL，够本站评论通知使用。
 */
final class Mailer
{
    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    public static function enabled(): bool
    {
        return setting('mail_enabled', '0') === '1'
            && (string) setting('smtp_host', '') !== ''
            && (string) setting('smtp_from', '') !== ''
            && (string) self::recipient() !== '';
    }

    public static function recipient(): string
    {
        $to = trim((string) setting('notify_email', ''));
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $to;
        }
        $admin = DB::one("SELECT email FROM users WHERE role = 'admin' AND email <> '' ORDER BY id LIMIT 1");
        return $admin && filter_var((string) $admin['email'], FILTER_VALIDATE_EMAIL) ? (string) $admin['email'] : '';
    }

    /**
     * 把通知推迟到响应发送之后，避免 SMTP 超时拖慢评论提交。
     */
    public static function queueNewComment(array $comment, array $post): void
    {
        if (!self::enabled()) {
            return;
        }
        register_shutdown_function(static function () use ($comment, $post): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            self::notifyNewComment($comment, $post);
        });
    }

    /**
     * 新评论通知。失败只写日志，不影响评论本身。
     */
    public static function notifyNewComment(array $comment, array $post): void
    {
        try {
            if (!self::enabled()) {
                return;
            }
            $to      = self::recipient();
            $title   = (string) ($post['title'] ?? '未知内容');
            $link    = abs_url(post_url($post));
            $admin   = abs_url('/admin/comments/?status=pending');
            $author  = (string) ($comment['author'] ?? '');
            $content = nl2br(e((string) ($comment['content'] ?? '')));
            $state   = (string) ($comment['status'] ?? '') === 'approved' ? '已直接发布' : '等待审核';

            $html = '<div style="font:14px/1.7 -apple-system,BlinkMacSystemFont,\'PingFang SC\',sans-serif;color:#1d1d1f;max-width:560px">'
                . '<p style="margin:0 0 12px">' . e($author) . ' 在《' . e($title) . '》留下了新评论（' . e($state) . '）。</p>'
                . '<blockquote style="margin:0 0 16px;padding:10px 14px;border-left:3px solid #d2d2d7;color:#3a3a3c;background:#f5f5f7">' . $content . '</blockquote>'
                . '<p style="margin:0 0 6px"><a href="' . e($admin) . '" style="color:#0071e3">去后台审核</a></p>'
                . '<p style="margin:0 0 6px"><a href="' . e($link) . '" style="color:#0071e3">查看文章</a></p>'
                . '<p style="margin:16px 0 0;font-size:12px;color:#86868b">' . e(site_name()) . ' · 自动通知</p></div>';

            self::send($to, '新评论：' . $title, $html);
        } catch (Throwable $e) {
            error_log('mail notify failed: ' . $e->getMessage());
        }
    }

    public static function commenterNotifyEnabled(): bool
    {
        return self::enabled() && setting_bool('commenter_notify', true);
    }

    /**
     * 后台审核通过：通知作者“已发布”，并通知被回复的评论者。
     */
    public static function queueCommenterNotice(array $comment, array $post): void
    {
        if (!self::commenterNotifyEnabled()) {
            return;
        }
        register_shutdown_function(static function () use ($comment, $post): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            self::notifyCommenterApproved($comment, $post);
            self::notifyReplyTarget($comment, $post);
        });
    }

    /**
     * 评论直接发布（白名单用户）：只通知被回复者，不再给作者发“已发布”。
     */
    public static function queueReplyNotice(array $comment, array $post): void
    {
        if (!self::commenterNotifyEnabled()) {
            return;
        }
        register_shutdown_function(static function () use ($comment, $post): void {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            self::notifyReplyTarget($comment, $post);
        });
    }

    public static function notifyCommenterApproved(array $comment, array $post): void
    {
        try {
            if (!self::commenterNotifyEnabled()) {
                return;
            }
            $to = trim((string) ($comment['email'] ?? ''));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $title   = (string) ($post['title'] ?? '未知内容');
            $link    = abs_url(post_url($post) . '#comment-' . (int) ($comment['id'] ?? 0));
            $content = nl2br(e((string) ($comment['content'] ?? '')));

            $html = '<div style="font:14px/1.7 -apple-system,BlinkMacSystemFont,\'PingFang SC\',sans-serif;color:#1d1d1f;max-width:560px">'
                . '<p style="margin:0 0 12px">你在《' . e($title) . '》的评论已通过审核并公开显示。</p>'
                . '<blockquote style="margin:0 0 16px;padding:10px 14px;border-left:3px solid #d2d2d7;color:#3a3a3c;background:#f5f5f7">' . $content . '</blockquote>'
                . '<p style="margin:0 0 6px"><a href="' . e($link) . '" style="color:#0071e3">查看评论</a></p>'
                . '<p style="margin:16px 0 0;font-size:12px;color:#86868b">' . e(site_name()) . ' · 自动通知</p></div>';

            self::send($to, '你的评论已发布：' . $title, $html);
        } catch (Throwable $e) {
            error_log('commenter notify failed: ' . $e->getMessage());
        }
    }

    public static function notifyReplyTarget(array $comment, array $post): void
    {
        try {
            if (!self::commenterNotifyEnabled()) {
                return;
            }
            $parentId = (int) ($comment['parent_id'] ?? 0);
            if ($parentId <= 0) {
                return;
            }
            $parent = DB::one(
                'SELECT * FROM comments WHERE id = ? AND post_id = ?',
                [$parentId, (int) ($post['id'] ?? 0)]
            );
            if (!$parent) {
                return;
            }
            $to = trim((string) ($parent['email'] ?? ''));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $selfEmail = trim((string) ($comment['email'] ?? ''));
            if ($selfEmail !== '' && strcasecmp($to, $selfEmail) === 0) {
                return;
            }
            $title      = (string) ($post['title'] ?? '未知内容');
            $author     = (string) ($comment['author'] ?? '有人');
            $link       = abs_url(post_url($post) . '#comment-' . (int) ($comment['id'] ?? 0));
            $parentText = nl2br(e((string) ($parent['content'] ?? '')));
            $replyText  = nl2br(e((string) ($comment['content'] ?? '')));

            $html = '<div style="font:14px/1.7 -apple-system,BlinkMacSystemFont,\'PingFang SC\',sans-serif;color:#1d1d1f;max-width:560px">'
                . '<p style="margin:0 0 12px">' . e($author) . ' 在《' . e($title) . '》回复了你的评论。</p>'
                . '<p style="margin:0 0 4px;font-size:12px;color:#86868b">你的评论</p>'
                . '<blockquote style="margin:0 0 12px;padding:10px 14px;border-left:3px solid #d2d2d7;color:#6e6e73;background:#f5f5f7">' . $parentText . '</blockquote>'
                . '<p style="margin:0 0 4px;font-size:12px;color:#86868b">回复内容</p>'
                . '<blockquote style="margin:0 0 16px;padding:10px 14px;border-left:3px solid #0071e3;color:#3a3a3c;background:#f5f5f7">' . $replyText . '</blockquote>'
                . '<p style="margin:0 0 6px"><a href="' . e($link) . '" style="color:#0071e3">查看并回复</a></p>'
                . '<p style="margin:16px 0 0;font-size:12px;color:#86868b">' . e(site_name()) . ' · 自动通知</p></div>';

            self::send($to, $author . ' 回复了你的评论：' . $title, $html);
        } catch (Throwable $e) {
            error_log('reply notify failed: ' . $e->getMessage());
        }
    }

    public static function sendTest(string $to): bool
    {
        $html = '<div style="font:14px/1.7 -apple-system,BlinkMacSystemFont,\'PingFang SC\',sans-serif;color:#1d1d1f">'
            . '<p>这是一封来自 <strong>' . e(site_name()) . '</strong> 的测试邮件。</p>'
            . '<p style="color:#6e6e73;font-size:12px">收到它说明 SMTP 配置可用，评论通知将按此配置发送。</p></div>';
        return self::send($to, 'SMTP 测试邮件 - ' . site_name(), $html);
    }

    public static function send(string $to, string $subject, string $html): bool
    {
        self::$lastError = '';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = '收件地址无效';
            return false;
        }
        $host     = trim((string) setting('smtp_host', ''));
        $port     = max(1, (int) setting('smtp_port', 465));
        $secure   = (string) setting('smtp_secure', 'ssl');
        $user     = (string) setting('smtp_user', '');
        $pass     = (string) setting('smtp_pass', '');
        $from     = trim((string) setting('smtp_from', ''));
        $fromName = trim((string) setting('smtp_from_name', ''));
        if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = 'SMTP 主机或发件地址未填写';
            return false;
        }
        if ($fromName === '') {
            $fromName = site_name();
        }

        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
        $socket  = @stream_socket_client($remote, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            self::$lastError = '连接 SMTP 失败：' . ($errstr !== '' ? $errstr : 'errno ' . $errno);
            return false;
        }
        stream_set_timeout($socket, 12);

        try {
            self::expect($socket, [220], '服务器欢迎语');

            $ehloHost = gethostname() ?: 'localhost';
            [$code, $text] = self::command($socket, 'EHLO ' . $ehloHost);
            if ($code !== 250) {
                [$code, $text] = self::command($socket, 'HELO ' . $ehloHost);
                if ($code !== 250) {
                    throw new RuntimeException('EHLO/HELO 被拒绝：' . $text);
                }
            }

            if ($secure === 'tls') {
                self::expect($socket, [220], 'STARTTLS', 'STARTTLS');
                $ok = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($ok !== true) {
                    throw new RuntimeException('STARTTLS 握手失败');
                }
                self::command($socket, 'EHLO ' . $ehloHost);
            }

            if ($user !== '') {
                [$code, $text] = self::command($socket, 'AUTH LOGIN');
                if ($code !== 334) {
                    throw new RuntimeException('SMTP 不支持 AUTH LOGIN：' . $text);
                }
                [$code, $text] = self::command($socket, base64_encode($user));
                if ($code !== 334) {
                    throw new RuntimeException('SMTP 用户名被拒绝：' . $text);
                }
                [$code, $text] = self::command($socket, base64_encode($pass));
                if ($code !== 235) {
                    throw new RuntimeException('SMTP 认证失败：' . $text);
                }
            }

            [$code, $text] = self::command($socket, 'MAIL FROM:<' . $from . '>');
            if ($code !== 250) {
                throw new RuntimeException('发件人被拒绝：' . $text);
            }
            [$code, $text] = self::command($socket, 'RCPT TO:<' . $to . '>');
            if (!in_array($code, [250, 251], true)) {
                throw new RuntimeException('收件人被拒绝：' . $text);
            }
            [$code, $text] = self::command($socket, 'DATA');
            if ($code !== 354) {
                throw new RuntimeException('DATA 被拒绝：' . $text);
            }

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . self::encodeHeader($fromName) . ' <' . $from . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url(site_url(), PHP_URL_HOST) ?: 'localhost') . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                'X-Mailer: syhcms',
            ];
            $body = rtrim(chunk_split(base64_encode($html), 76, "\r\n"));
            $data = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
            [$code, $text] = self::command($socket, $data);
            if ($code !== 250) {
                throw new RuntimeException('邮件被拒绝：' . $text);
            }
            @fwrite($socket, "QUIT\r\n");
            fclose($socket);
            return true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('mail send failed: ' . $e->getMessage());
            @fclose($socket);
            return false;
        }
    }

    private static function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], '', $value);
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function expect($socket, array $codes, string $stage): void
    {
        [$code, $text] = self::read($socket);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException($stage . ' 失败：' . $text);
        }
    }

    /** @return array{0:int,1:string} */
    private static function command($socket, string $line): array
    {
        if (@fwrite($socket, $line . "\r\n") === false) {
            throw new RuntimeException('写入 SMTP 连接失败');
        }
        return self::read($socket);
    }

    /** @return array{0:int,1:string} */
    private static function read($socket): array
    {
        $text = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $text .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if ($text === '') {
            throw new RuntimeException('SMTP 连接已断开');
        }
        $code = (int) substr($text, 0, 3);
        return [$code, trim($text)];
    }
}
