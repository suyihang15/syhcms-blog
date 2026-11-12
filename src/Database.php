<?php
declare(strict_types=1);

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $path = SYH_CONFIG['db'];
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        self::$pdo = $pdo;
        self::migrate($pdo);
        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        if ($version < 1) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS options (
    name TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    login TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL DEFAULT '',
    pass TEXT NOT NULL,
    display_name TEXT NOT NULL DEFAULT '',
    role TEXT NOT NULL DEFAULT 'subscriber',
    registered_at TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS terms (
    id INTEGER PRIMARY KEY,
    taxonomy TEXT NOT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    parent_id INTEGER NOT NULL DEFAULT 0,
    count INTEGER NOT NULL DEFAULT 0,
    UNIQUE (taxonomy, slug)
);
CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY,
    type TEXT NOT NULL DEFAULT 'post',
    status TEXT NOT NULL DEFAULT 'publish',
    slug TEXT NOT NULL,
    title TEXT NOT NULL DEFAULT '',
    content TEXT NOT NULL DEFAULT '',
    excerpt TEXT NOT NULL DEFAULT '',
    author_id INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    comment_status TEXT NOT NULL DEFAULT 'open',
    menu_order INTEGER NOT NULL DEFAULT 0,
    template TEXT NOT NULL DEFAULT '',
    views INTEGER NOT NULL DEFAULT 0,
    cover_id INTEGER NOT NULL DEFAULT 0,
    UNIQUE (type, slug)
);
CREATE INDEX IF NOT EXISTS posts_type_status_date ON posts (type, status, created_at DESC);
CREATE TABLE IF NOT EXISTS post_terms (
    post_id INTEGER NOT NULL,
    term_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, term_id)
);
CREATE INDEX IF NOT EXISTS post_terms_term ON post_terms (term_id);
CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY,
    post_id INTEGER NOT NULL,
    parent_id INTEGER NOT NULL DEFAULT 0,
    author TEXT NOT NULL DEFAULT '',
    email TEXT NOT NULL DEFAULT '',
    url TEXT NOT NULL DEFAULT '',
    content TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending',
    created_at TEXT NOT NULL,
    ip TEXT NOT NULL DEFAULT '',
    ua TEXT NOT NULL DEFAULT '',
    user_id INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS comments_post_status ON comments (post_id, status, created_at);
CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY,
    file TEXT NOT NULL UNIQUE,
    mime TEXT NOT NULL DEFAULT '',
    width INTEGER NOT NULL DEFAULT 0,
    height INTEGER NOT NULL DEFAULT 0,
    alt TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS menu_items (
    id INTEGER PRIMARY KEY,
    menu TEXT NOT NULL DEFAULT 'primary',
    parent_id INTEGER NOT NULL DEFAULT 0,
    label TEXT NOT NULL DEFAULT '',
    url TEXT NOT NULL DEFAULT '',
    target TEXT NOT NULL DEFAULT '',
    sort INTEGER NOT NULL DEFAULT 0,
    kind TEXT NOT NULL DEFAULT 'custom',
    object_id INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS links (
    id INTEGER PRIMARY KEY,
    grp TEXT NOT NULL DEFAULT '好友',
    name TEXT NOT NULL DEFAULT '',
    url TEXT NOT NULL DEFAULT '',
    image TEXT NOT NULL DEFAULT '',
    image_dark TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    rss TEXT NOT NULL DEFAULT '',
    sort INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS traffic (
    day TEXT NOT NULL,
    source TEXT NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (day, source)
);
CREATE TABLE IF NOT EXISTS rate_limits (
    name TEXT PRIMARY KEY,
    window_start INTEGER NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY,
    ip TEXT NOT NULL,
    at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS login_attempts_ip ON login_attempts (ip, at);
SQL
        );
        $pdo->exec('PRAGMA user_version = 1');
        $version = 1;
        }

        if ($version < 2) {
            $pdo->exec(<<<'SQL'
CREATE INDEX IF NOT EXISTS comments_status_date ON comments (status, created_at DESC);
CREATE INDEX IF NOT EXISTS comments_email_status ON comments (email, status);
CREATE INDEX IF NOT EXISTS posts_author ON posts (author_id);
SQL
            );
            $pdo->exec('PRAGMA user_version = 2');
        }
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $params = [], mixed $default = null): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(fn ($c) => $c . ' = ?', array_keys($data)));
        $st  = self::pdo()->prepare('UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where);
        $st->execute(array_merge(array_values($data), $params));
        return $st->rowCount();
    }
}
