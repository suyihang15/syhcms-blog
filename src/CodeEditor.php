<?php
declare(strict_types=1);

/**
 * 后台源码编辑器：只允许管理员在项目源码目录内读写文本文件。
 * 保存前自动备份到 data/backup/code，PHP 文件先做语法检查。
 */
final class CodeEditor
{
    private const MAX_BYTES = 1048576;
    private const KEEP_BACKUPS = 20;
    private const EXTENSIONS = ['php', 'css', 'js', 'html', 'json', 'txt', 'md', 'svg', 'xml', 'htaccess'];

    /** 目录白名单（相对项目根目录）。 */
    private const DIR_PREFIXES = ['src/', 'templates/', 'public/assets/'];
    /** 单文件白名单。 */
    private const FILE_WHITELIST = ['config.php', 'public/index.php', 'public/router.php'];

    public static function root(): string
    {
        return SYH_ROOT;
    }

    /** @return array<string, array{size:int, mtime:int, dir:string}> */
    public static function files(): array
    {
        $out = [];
        foreach (self::DIR_PREFIXES as $prefix) {
            $dir = SYH_ROOT . '/' . rtrim($prefix, '/');
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }
                $path = $file->getPathname();
                if (!self::editable($path)) {
                    continue;
                }
                $rel = self::relative($path);
                if ($rel === null) {
                    continue;
                }
                $out[$rel] = [
                    'size'  => (int) $file->getSize(),
                    'mtime' => (int) $file->getMTime(),
                    'dir'   => self::dirLabel($rel),
                ];
            }
        }
        foreach (self::FILE_WHITELIST as $rel) {
            $path = SYH_ROOT . '/' . $rel;
            if (!is_file($path) || !self::editable($path)) {
                continue;
            }
            $out[$rel] = [
                'size'  => (int) filesize($path),
                'mtime' => (int) filemtime($path),
                'dir'   => self::dirLabel($rel),
            ];
        }
        ksort($out);
        return $out;
    }

    public static function resolve(string $rel): ?string
    {
        $rel = trim(str_replace('\\', '/', $rel));
        if ($rel === '' || strlen($rel) > 200 || str_contains($rel, "\0") || str_starts_with($rel, '/')) {
            return null;
        }
        $parts = explode('/', $rel);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return null;
            }
        }
        $rel = implode('/', $parts);
        if (!self::allowed($rel)) {
            return null;
        }
        $path = SYH_ROOT . '/' . $rel;
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            return null;
        }
        $root = realpath(SYH_ROOT);
        if ($root === false || !str_starts_with($real, $root . '/')) {
            return null;
        }
        if (!self::editable($real) || filesize($real) > self::MAX_BYTES) {
            return null;
        }
        return $real;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function save(string $rel, string $content): array
    {
        if (strlen($content) > self::MAX_BYTES) {
            return ['ok' => false, 'message' => '文件内容超过 1 MB，已拒绝保存。'];
        }
        $path = self::resolve($rel);
        if ($path === null) {
            return ['ok' => false, 'message' => '文件不在允许编辑的范围内。'];
        }
        if (!is_writable($path)) {
            return ['ok' => false, 'message' => '文件不可写，请检查权限。'];
        }
        $old = (string) file_get_contents($path);
        if ($old === $content) {
            return ['ok' => true, 'message' => '内容没有变化。'];
        }
        if (str_ends_with(strtolower($path), '.php')) {
            if (str_contains($old, '<?php') && !str_contains($content, '<?php')) {
                return ['ok' => false, 'message' => 'PHP 文件必须包含 <?php 起始标记，已拒绝保存。'];
            }
            $lint = self::lint($content, $path);
            if (!$lint['ok']) {
                return ['ok' => false, 'message' => 'PHP 语法检查未通过，未保存：' . $lint['message']];
            }
        }
        self::backup($path, $old);
        if (!self::atomicWrite($path, $content)) {
            return ['ok' => false, 'message' => '写入失败，请检查目录权限。'];
        }
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        return ['ok' => true, 'message' => '已保存（旧版本已备份）。原文件：' . self::relative($path)];
    }

    /** @return array{ok: bool, message: string} */
    public static function lint(string $content, string $path): array
    {
        // 面板默认禁用 exec，无法调用 php -l；用同版本解析器在进程内检查语法。
        try {
            token_get_all($content, TOKEN_PARSE);
        } catch (\ParseError $e) {
            return ['ok' => false, 'message' => '第 ' . $e->getLine() . ' 行：' . $e->getMessage()];
        }
        return ['ok' => true, 'message' => '语法检查通过。'];
    }

    public static function relative(string $path): ?string
    {
        $root = rtrim((string) realpath(SYH_ROOT), '/');
        $real = realpath($path);
        if ($real === false || $root === '' || !str_starts_with($real, $root . '/')) {
            return null;
        }
        return substr($real, strlen($root) + 1);
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return number_format($bytes / 1048576, 2) . ' MB';
    }

    private static function allowed(string $rel): bool
    {
        if (in_array($rel, self::FILE_WHITELIST, true)) {
            return true;
        }
        foreach (self::DIR_PREFIXES as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private static function editable(string $path): bool
    {
        $base = basename($path);
        if ($base === '' || $base[0] === '.') {
            return false;
        }
        $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }
        return in_array($ext, self::EXTENSIONS, true);
    }

    private static function dirLabel(string $rel): string
    {
        $dir = dirname($rel);
        return $dir === '.' ? '根目录' : $dir;
    }

    private static function backup(string $path, string $content): void
    {
        $dir = SYH_ROOT . '/data/backup/code';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $stem = preg_replace('/[^A-Za-z0-9._-]+/', '_', self::relative($path) ?? basename($path)) ?? 'file';
        $file = $dir . '/' . $stem . '.' . date('Ymd-His') . '.' . bin2hex(random_bytes(3)) . '.bak';
        @file_put_contents($file, $content);
        $prefix = $stem . '.';
        $items = glob($dir . '/' . $prefix . '*.bak') ?: [];
        if (count($items) > self::KEEP_BACKUPS) {
            sort($items);
            foreach (array_slice($items, 0, count($items) - self::KEEP_BACKUPS) as $old) {
                @unlink($old);
            }
        }
    }

    private static function atomicWrite(string $path, string $content): bool
    {
        $tmp = dirname($path) . '/.syh-save-' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $content) === false) {
            return false;
        }
        $perms = @fileperms($path);
        if ($perms !== false) {
            @chmod($tmp, $perms & 0777);
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }
}
