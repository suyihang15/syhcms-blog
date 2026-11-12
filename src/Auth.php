<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id > 0) {
            self::$user = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
            if (!self::$user) {
                unset($_SESSION['user_id']);
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u !== null && $u['role'] === 'admin';
    }

    public static function can(string $capability): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        return match ($capability) {
            'manage'   => in_array($u['role'], ['admin', 'editor'], true),
            'admin'    => $u['role'] === 'admin',
            'comment'  => in_array($u['role'], ['admin', 'editor', 'author'], true),
            default    => false,
        };
    }

    public static function login(string $login, string $password): bool
    {
        $ip       = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $attempts = (int) DB::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > ?', [$ip, time() - 900], 0);
        if ($attempts >= 10) {
            return false;
        }
        $user = DB::one('SELECT * FROM users WHERE login = ? OR email = ? LIMIT 1', [$login, $login]);
        if (!$user || !self::verifyPassword($password, (string) $user['pass'])) {
            DB::insert('login_attempts', ['ip' => $ip, 'at' => time()]);
            usleep(250000);
            return false;
        }
        DB::exec('DELETE FROM login_attempts WHERE ip = ?', [$ip]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf']    = bin2hex(random_bytes(32));
        self::$user          = $user;
        self::$loaded        = true;

        if (str_starts_with((string) $user['pass'], '$P$') || str_starts_with((string) $user['pass'], '$H$') || str_starts_with((string) $user['pass'], '$wp$')) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            DB::exec('UPDATE users SET pass = ? WHERE id = ?', [$newHash, (int) $user['id']]);
        }
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        self::$user   = null;
        self::$loaded = true;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/admin/login/?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin/'));
        }
    }

    public static function requireManager(): void
    {
        self::requireLogin();
        if (!self::can('manage')) {
            http_response_code(403);
            exit('没有权限');
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    public static function verifyCsrf(): void
    {
        $token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if ($token === '' || !hash_equals(self::csrfToken(), $token)) {
            http_response_code(419);
            exit('页面已过期，请刷新后重试。');
        }
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        // WordPress 6.8+：SHA-384 HMAC 预哈希后再 bcrypt，前缀 `$wp`。
        if (str_starts_with($hash, '$wp')) {
            $bcrypt = substr($hash, 3);
            foreach ([$password, trim($password)] as $candidate) {
                $pre = base64_encode(hash_hmac('sha384', $candidate, 'wp-sha384', true));
                if (password_verify($pre, $bcrypt)) {
                    return true;
                }
            }
            return false;
        }
        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return hash_equals($hash, self::cryptPrivate($password, $hash));
        }
        return password_verify($password, $hash);
    }

    /**
     * phpass portable hashing（WordPress 旧密码哈希）。
     */
    private static function cryptPrivate(string $password, string $setting): string
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $id     = substr($setting, 0, 3);
        if ($id !== '$P$' && $id !== '$H$') {
            return '*0';
        }
        $countLog2 = strpos($itoa64, $setting[3]);
        if ($countLog2 < 7 || $countLog2 > 30) {
            return '*0';
        }
        $count = 1 << $countLog2;
        $salt  = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return '*0';
        }
        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $output = substr($setting, 0, 12);
        $i      = 0;
        do {
            $value = ord($hash[$i++]);
            $output .= $itoa64[$value & 0x3f];
            if ($i < 16) {
                $value |= ord($hash[$i]) << 8;
            }
            $output .= $itoa64[($value >> 6) & 0x3f];
            if ($i++ >= 16) {
                break;
            }
            if ($i < 16) {
                $value |= ord($hash[$i]) << 16;
            }
            $output .= $itoa64[($value >> 12) & 0x3f];
            if ($i++ >= 16) {
                break;
            }
            $output .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < 16);
        return substr($output, 0, 34);
    }
}
