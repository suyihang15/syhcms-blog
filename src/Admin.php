<?php
declare(strict_types=1);

function admin_url(string $path = ''): string
{
    return '/admin/' . ltrim($path, '/');
}

function admin_status_label(string $status): string
{
    return match ($status) {
        'publish' => '已发布',
        'draft'   => '草稿',
        'pending' => '待审核',
        'private' => '私密',
        default   => $status,
    };
}

function admin_slugify(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[\s\/#?%\\\\]+/u', '-', $value) ?? $value;
    $value = preg_replace('/[^\p{L}\p{N}_\-]+/u', '', $value) ?? $value;
    $value = trim($value, '-_');
    return $value;
}

function admin_pagination(int $current, int $total, string $base, array $query = []): string
{
    if ($total <= 1) {
        return '';
    }
    $current = max(1, min($current, $total));
    $link = static function (int $n) use ($base, $query): string {
        $query['paged'] = $n;
        return $base . '?' . http_build_query($query);
    };
    $out = '<nav class="admin-pagination"><ul>';
    if ($current > 1) {
        $out .= '<li><a href="' . e($link($current - 1)) . '">上一页</a></li>';
    }
    $start = max(1, $current - 2);
    $end   = min($total, $current + 2);
    if ($start > 1) {
        $out .= '<li><a href="' . e($link(1)) . '">1</a></li>';
        if ($start > 2) {
            $out .= '<li><span>…</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $out .= $i === $current
            ? '<li><span class="current">' . $i . '</span></li>'
            : '<li><a href="' . e($link($i)) . '">' . $i . '</a></li>';
    }
    if ($end < $total) {
        if ($end < $total - 1) {
            $out .= '<li><span>…</span></li>';
        }
        $out .= '<li><a href="' . e($link($total)) . '">' . $total . '</a></li>';
    }
    if ($current < $total) {
        $out .= '<li><a href="' . e($link($current + 1)) . '">下一页</a></li>';
    }
    return $out . '</ul></nav>';
}

function admin_refresh_term_counts(): void
{
    DB::exec(
        "UPDATE terms SET count = (
            SELECT COUNT(*) FROM post_terms pt JOIN posts p ON p.id = pt.post_id
            WHERE pt.term_id = terms.id AND p.status = 'publish'
        )"
    );
}

final class Admin
{
    private const STATUSES = ['publish', 'draft', 'pending', 'private'];
    private const PAGE_TEMPLATES = ['', 'page-stats.php', 'page-archive.php', 'page-guestbook.php', 'page-links.php'];

    public function dispatch(string $path, string $method): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $route = trim(substr($path, strlen('/admin')), '/');
        $segments = $route === '' ? [] : explode('/', $route);
        $section = $segments[0] ?? '';

        if ($section === 'login') {
            $this->login($method);
            return;
        }
        if ($section === 'logout') {
            $this->logout($method);
            return;
        }

        if (!Auth::check()) {
            Auth::requireLogin();
            return;
        }
        if ($section !== 'profile') {
            Auth::requireManager();
        }

        switch ($section) {
            case '':
                $this->dashboard();
                break;
            case 'posts':
                $this->posts($segments, $method);
                break;
            case 'pages':
                $this->posts($segments, $method, 'page');
                break;
            case 'comments':
                $this->comments($segments, $method);
                break;
            case 'media':
                $this->media($segments, $method);
                break;
            case 'terms':
                $this->terms($segments, $method);
                break;
            case 'menu':
                $this->menu($segments, $method);
                break;
            case 'links':
                $this->links($segments, $method);
                break;
            case 'settings':
                $this->settings($segments, $method);
                break;
            case 'appearance':
                $this->appearance($method);
                break;
            case 'users':
                $this->users($segments, $method);
                break;
            case 'profile':
                $this->profile($method);
                break;
            case 'code':
                $this->code($segments, $method);
                break;
            default:
                $this->notFound();
        }
    }

    private function login(string $method): void
    {
        if (Auth::check()) {
            redirect(admin_url());
        }
        if ($method === 'POST') {
            Auth::verifyCsrf();
            $login = trim((string) ($_POST['login'] ?? ''));
            $pass  = (string) ($_POST['password'] ?? '');
            if ($login === '' || $pass === '') {
                flash('error', '请输入账号和密码。');
            } elseif (Auth::login($login, $pass)) {
                $next = (string) ($_POST['next'] ?? '');
                if (!str_starts_with($next, '/admin/') || str_starts_with($next, '//')) {
                    $next = admin_url();
                }
                redirect($next);
            } else {
                flash('error', '账号或密码不正确，或尝试次数过多。');
            }
            redirect(admin_url('login/'));
        }
        $flashes = take_flashes();
        $next    = (string) ($_GET['next'] ?? '');
        require SYH_ROOT . '/templates/admin/login.php';
    }

    private function logout(string $method): void
    {
        if ($method !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        Auth::verifyCsrf();
        Auth::logout();
        flash('success', '已退出登录。');
        redirect(admin_url('login/'));
    }

    private function dashboard(): void
    {
        $counts = [
            'posts'    => (int) DB::val("SELECT COUNT(*) FROM posts WHERE type = 'post'", [], 0),
            'pages'    => (int) DB::val("SELECT COUNT(*) FROM posts WHERE type = 'page'", [], 0),
            'pending'  => (int) DB::val("SELECT COUNT(*) FROM comments WHERE status = 'pending'", [], 0),
            'comments' => (int) DB::val("SELECT COUNT(*) FROM comments", [], 0),
            'media'    => (int) DB::val('SELECT COUNT(*) FROM media', [], 0),
            'links'    => (int) DB::val('SELECT COUNT(*) FROM links', [], 0),
            'views'    => (int) DB::val("SELECT COALESCE(SUM(views),0) FROM posts WHERE type = 'post' AND status = 'publish'", [], 0),
        ];
        $recentPosts = DB::all("SELECT * FROM posts WHERE type IN ('post','page') ORDER BY updated_at DESC LIMIT 8");
        $pending = DB::all(
            "SELECT c.*, p.title AS post_title, p.slug AS post_slug FROM comments c
             LEFT JOIN posts p ON p.id = c.post_id WHERE c.status = 'pending'
             ORDER BY c.created_at DESC LIMIT 6"
        );
        $traffic = DB::all('SELECT day, SUM(hits) AS hits FROM traffic WHERE day >= ? GROUP BY day ORDER BY day DESC LIMIT 7', [date('Y-m-d', strtotime('-6 days'))]);
        $this->view('dashboard', [
            'title'          => '仪表盘',
            'currentSection' => 'dashboard',
            'counts'         => $counts,
            'recentPosts'    => $recentPosts,
            'pending'        => $pending,
            'traffic'        => array_reverse($traffic),
        ]);
    }

    private function posts(array $segments, string $method, string $type = 'post'): void
    {
        $action = $segments[1] ?? '';
        if ($action === 'new') {
            $this->postEdit(0, $type);
            return;
        }
        if ($action === 'edit') {
            $this->postEdit((int) ($segments[2] ?? 0), $type);
            return;
        }
        if ($action === 'save' && $method === 'POST') {
            $this->postSave($type);
            return;
        }
        if ($action === 'delete' && $method === 'POST') {
            $this->postDelete($type);
            return;
        }
        $this->postList($type);
    }

    private function postList(string $type): void
    {
        $status = (string) ($_GET['status'] ?? '');
        $search = trim((string) ($_GET['s'] ?? ''));
        $paged  = max(1, (int) ($_GET['paged'] ?? 1));
        $per    = 20;

        $where = ['type = ?'];
        $params = [$type];
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(title LIKE ? OR slug LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) DB::val("SELECT COUNT(*) FROM posts WHERE $whereSql", $params, 0);
        $pages = max(1, (int) ceil($total / $per));
        $paged = min($paged, $pages);
        $rows  = DB::all(
            "SELECT * FROM posts WHERE $whereSql ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per, ($paged - 1) * $per])
        );
        foreach ($rows as &$row) {
            $row['categories'] = categories_of((int) $row['id']);
        }
        unset($row);

        $this->view('posts', [
            'title'          => $type === 'page' ? '页面' : '文章',
            'currentSection' => $type === 'page' ? 'pages' : 'posts',
            'type'           => $type,
            'rows'           => $rows,
            'status'         => $status,
            'search'         => $search,
            'paged'          => $paged,
            'pages'          => $pages,
            'total'          => $total,
        ]);
    }

    private function postEdit(int $id, string $type): void
    {
        $post = null;
        if ($id > 0) {
            $post = DB::one('SELECT * FROM posts WHERE id = ? AND type = ?', [$id, $type]);
            if (!$post) {
                $this->notFound();
                return;
            }
        }
        $selectedCats = [];
        $selectedTags = [];
        if ($post) {
            $selectedCats = array_map('intval', array_column(
                DB::all("SELECT t.id FROM terms t JOIN post_terms pt ON pt.term_id = t.id WHERE pt.post_id = ? AND t.taxonomy = 'category'", [$id]),
                'id'
            ));
            $selectedTags = array_map(static fn (array $t): string => (string) $t['name'], tags_of($id));
        }
        $cats = DB::all("SELECT * FROM terms WHERE taxonomy = 'category' ORDER BY name");
        $tags = DB::all("SELECT * FROM terms WHERE taxonomy = 'post_tag' ORDER BY name");
        $mediaItems = DB::all('SELECT id, file, alt, title FROM media ORDER BY created_at DESC LIMIT 300');
        $editorMedia = array_map(static fn (array $m): array => [
            'id'    => (int) $m['id'],
            'url'   => media_url_by_file((string) $m['file']),
            'alt'   => (string) $m['alt'],
            'title' => (string) ($m['title'] !== '' ? $m['title'] : $m['file']),
        ], $mediaItems);
        $this->view('post-edit', [
            'title'          => ($post ? '编辑' : '新建') . ($type === 'page' ? '页面' : '文章'),
            'currentSection' => $type === 'page' ? 'pages' : 'posts',
            'type'           => $type,
            'post'           => $post,
            'cats'           => $cats,
            'tags'           => $tags,
            'selectedCats'   => $selectedCats,
            'selectedTags'   => $selectedTags,
            'mediaItems'     => $mediaItems,
            'editorMedia'    => $editorMedia,
        ]);
    }

    private function postSave(string $type): void
    {
        Auth::verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $existing = $id > 0 ? DB::one('SELECT * FROM posts WHERE id = ? AND type = ?', [$id, $type]) : null;
        if ($id > 0 && !$existing) {
            $this->notFound();
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash('error', '标题不能为空。');
            redirect(admin_url(($type === 'page' ? 'pages' : 'posts') . '/edit/' . $id . '/'));
        }

        $slug = admin_slugify((string) ($_POST['slug'] ?? ''));
        if ($slug === '') {
            $slug = admin_slugify($title);
        }
        if ($slug === '') {
            $slug = $type === 'page' ? 'page' : 'post';
        }
        $slug = $this->uniqueSlug($type, $slug, $id);

        $status = (string) ($_POST['status'] ?? 'publish');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'draft';
        }
        $content = (string) ($_POST['content'] ?? '');
        $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
        $created = trim((string) ($_POST['created_at'] ?? ''));
        $created = str_replace('T', ' ', $created);
        if ($created === '') {
            $created = now();
        } elseif (strlen($created) === 16) {
            $created .= ':00';
        }
        if (strtotime($created) === false) {
            $created = now();
        }
        $commentStatus = ($_POST['comment_status'] ?? 'open') === 'closed' ? 'closed' : 'open';
        $menuOrder = max(0, (int) ($_POST['menu_order'] ?? 0));
        $template = $type === 'page' ? (string) ($_POST['template'] ?? '') : '';
        if (!in_array($template, self::PAGE_TEMPLATES, true)) {
            $template = '';
        }
        $coverId = max(0, (int) ($_POST['cover_id'] ?? 0));
        if ($coverId > 0 && !DB::val('SELECT id FROM media WHERE id = ?', [$coverId])) {
            $coverId = 0;
        }

        $data = [
            'type'           => $type,
            'status'         => $status,
            'slug'           => $slug,
            'title'          => $title,
            'content'        => $content,
            'excerpt'        => $excerpt,
            'author_id'      => (int) Auth::user()['id'],
            'created_at'     => $created,
            'updated_at'     => now(),
            'comment_status' => $commentStatus,
            'menu_order'     => $menuOrder,
            'template'       => $template,
            'cover_id'       => $coverId,
        ];

        if ($existing) {
            DB::update('posts', $data, 'id = ?', [$id]);
        } else {
            $data['views'] = 0;
            $id = DB::insert('posts', $data);
        }

        $catIds = array_map('intval', (array) ($_POST['categories'] ?? []));
        $tags   = (string) ($_POST['tags'] ?? '');
        $this->syncPostTerms($id, $catIds, $tags, $type);
        admin_refresh_term_counts();

        flash('success', '已保存。');
        redirect(admin_url(($type === 'page' ? 'pages' : 'posts') . '/edit/' . $id . '/'));
    }

    private function syncPostTerms(int $postId, array $catIds, string $tagsRaw, string $type): void
    {
        DB::exec('DELETE FROM post_terms WHERE post_id = ?', [$postId]);
        if ($type !== 'post') {
            return;
        }
        foreach (array_unique($catIds) as $tid) {
            if ($tid > 0 && DB::val("SELECT id FROM terms WHERE id = ? AND taxonomy = 'category'", [$tid])) {
                DB::exec('INSERT OR IGNORE INTO post_terms (post_id, term_id) VALUES (?, ?)', [$postId, $tid]);
            }
        }
        $names = preg_split('/[,，]/u', $tagsRaw) ?: [];
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '' || mb_strlen($name, 'UTF-8') > 60) {
                continue;
            }
            $row = DB::one("SELECT id FROM terms WHERE taxonomy = 'post_tag' AND (name = ? OR slug = ?)", [$name, admin_slugify($name)]);
            if ($row) {
                $tid = (int) $row['id'];
            } else {
                $tid = DB::insert('terms', [
                    'taxonomy'    => 'post_tag',
                    'name'        => $name,
                    'slug'        => $this->uniqueTermSlug('post_tag', admin_slugify($name) ?: 'tag', 0),
                    'description' => '',
                    'parent_id'   => 0,
                    'count'       => 0,
                ]);
            }
            DB::exec('INSERT OR IGNORE INTO post_terms (post_id, term_id) VALUES (?, ?)', [$postId, $tid]);
        }
    }

    private function postDelete(string $type): void
    {
        Auth::verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $post = $id > 0 ? DB::one('SELECT * FROM posts WHERE id = ? AND type = ?', [$id, $type]) : null;
        if (!$post) {
            $this->notFound();
            return;
        }
        DB::exec('DELETE FROM post_terms WHERE post_id = ?', [$id]);
        DB::exec('DELETE FROM comments WHERE post_id = ?', [$id]);
        DB::exec('DELETE FROM posts WHERE id = ?', [$id]);
        admin_refresh_term_counts();
        flash('success', '已删除。');
        redirect(admin_url($type === 'page' ? 'pages/' : 'posts/'));
    }

    private function uniqueSlug(string $type, string $slug, int $excludeId): string
    {
        $base = $slug;
        $try  = $base;
        $i    = 2;
        while (DB::val('SELECT id FROM posts WHERE type = ? AND slug = ? AND id <> ?', [$type, $try, $excludeId])) {
            $try = $base . '-' . $i++;
        }
        return $try;
    }

    private function comments(array $segments, string $method): void
    {
        $action = $segments[1] ?? '';
        if ($action === 'action' && $method === 'POST') {
            Auth::verifyCsrf();
            $do = (string) ($_POST['do'] ?? '');
            $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
            if (empty($ids)) {
                $single = (int) ($_POST['id'] ?? 0);
                if ($single > 0) {
                    $ids = [$single];
                }
            }
            $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
            if (!$ids) {
                flash('error', '请选择要操作的评论。');
                redirect(admin_url('comments/'));
            }
            $in = implode(',', array_fill(0, count($ids), '?'));
            if ($do === 'delete') {
                DB::exec("UPDATE comments SET parent_id = 0 WHERE parent_id IN ($in)", $ids);
                DB::exec("DELETE FROM comments WHERE id IN ($in)", $ids);
                flash('success', '已删除 ' . count($ids) . ' 条评论。');
            } elseif (in_array($do, ['approved', 'pending', 'spam', 'trash'], true)) {
                // 只对“本次由非通过变为通过”的评论发信，避免重复审核时轰炸评论者。
                $approvedRows = $do === 'approved'
                    ? DB::all("SELECT * FROM comments WHERE id IN ($in) AND status <> 'approved'", $ids)
                    : [];
                DB::exec("UPDATE comments SET status = ? WHERE id IN ($in)", array_merge([$do], $ids));
                if ($approvedRows) {
                    $posts = [];
                    foreach ($approvedRows as $row) {
                        $postId = (int) $row['post_id'];
                        if (!array_key_exists($postId, $posts)) {
                            $posts[$postId] = DB::one('SELECT * FROM posts WHERE id = ?', [$postId]);
                        }
                        if ($posts[$postId]) {
                            Mailer::queueCommenterNotice($row, $posts[$postId]);
                        }
                    }
                }
                flash('success', '已更新 ' . count($ids) . ' 条评论。');
            } else {
                flash('error', '未知操作。');
            }
            redirect(admin_url('comments/'));
        }

        $status = (string) ($_GET['status'] ?? '');
        $search = trim((string) ($_GET['s'] ?? ''));
        $paged  = max(1, (int) ($_GET['paged'] ?? 1));
        $per    = 30;
        $where  = ['1 = 1'];
        $params = [];
        if (in_array($status, ['approved', 'pending', 'spam', 'trash'], true)) {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(c.author LIKE ? OR c.email LIKE ? OR c.content LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereSql = implode(' AND ', $where);
        $total = (int) DB::val("SELECT COUNT(*) FROM comments c WHERE $whereSql", $params, 0);
        $pages = max(1, (int) ceil($total / $per));
        $paged = min($paged, $pages);
        $rows  = DB::all(
            "SELECT c.*, p.title AS post_title, p.slug AS post_slug, p.type AS post_type
             FROM comments c LEFT JOIN posts p ON p.id = c.post_id
             WHERE $whereSql ORDER BY c.created_at DESC, c.id DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per, ($paged - 1) * $per])
        );
        $counts = [
            'all'      => (int) DB::val('SELECT COUNT(*) FROM comments', [], 0),
            'pending'  => (int) DB::val("SELECT COUNT(*) FROM comments WHERE status = 'pending'", [], 0),
            'approved' => (int) DB::val("SELECT COUNT(*) FROM comments WHERE status = 'approved'", [], 0),
            'spam'     => (int) DB::val("SELECT COUNT(*) FROM comments WHERE status = 'spam'", [], 0),
            'trash'    => (int) DB::val("SELECT COUNT(*) FROM comments WHERE status = 'trash'", [], 0),
        ];
        $this->view('comments', [
            'title'          => '评论',
            'currentSection' => 'comments',
            'rows'           => $rows,
            'status'         => $status,
            'search'         => $search,
            'paged'          => $paged,
            'pages'          => $pages,
            'total'          => $total,
            'counts'         => $counts,
        ]);
    }

    private function media(array $segments, string $method): void
    {
        $action = $segments[1] ?? '';
        if ($action === 'upload' && $method === 'POST') {
            $this->mediaUpload();
            return;
        }
        if ($action === 'delete' && $method === 'POST') {
            $this->mediaDelete();
            return;
        }
        $paged  = max(1, (int) ($_GET['paged'] ?? 1));
        $search = trim((string) ($_GET['s'] ?? ''));
        $per    = 40;
        $where  = '1 = 1';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (file LIKE ? OR title LIKE ? OR alt LIKE ?)';
            $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
        }
        $total = (int) DB::val("SELECT COUNT(*) FROM media WHERE $where", $params, 0);
        $pages = max(1, (int) ceil($total / $per));
        $paged = min($paged, $pages);
        $rows  = DB::all("SELECT * FROM media WHERE $where ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?", array_merge($params, [$per, ($paged - 1) * $per]));
        $this->view('media', [
            'title'          => '媒体库',
            'currentSection' => 'media',
            'rows'           => $rows,
            'search'         => $search,
            'paged'          => $paged,
            'pages'          => $pages,
            'total'          => $total,
        ]);
    }

    private function mediaUpload(): void
    {
        Auth::verifyCsrf();
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', '上传失败，请重新选择文件。');
            redirect(admin_url('media/'));
        }
        if ((int) $file['size'] > 20 * 1024 * 1024) {
            flash('error', '文件不能超过 20MB。');
            redirect(admin_url('media/'));
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            flash('error', '无效的上传。');
            redirect(admin_url('media/'));
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($tmp);
        $allowed = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'image/avif'      => 'avif',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'text/plain'      => 'txt',
        ];
        if (!isset($allowed[$mime])) {
            flash('error', '不支持的文件类型：' . $mime);
            redirect(admin_url('media/'));
        }
        $ext  = $allowed[$mime];
        $name = (string) ($file['name'] ?? 'file');
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?: 'file';
        $base = trim($base, '-_.') ?: 'file';
        $base = mb_substr($base, 0, 80);
        $dir  = 'uploads/' . date('Y') . '/' . date('m');
        $abs  = rtrim((string) SYH_CONFIG['uploads'], '/') . '/' . substr($dir, strlen('uploads/'));
        if (!is_dir($abs) && !mkdir($abs, 0775, true) && !is_dir($abs)) {
            flash('error', '无法创建上传目录。');
            redirect(admin_url('media/'));
        }
        $filename = $base . '.' . $ext;
        $i = 2;
        while (is_file($abs . '/' . $filename)) {
            $filename = $base . '-' . $i++ . '.' . $ext;
        }
        if (!move_uploaded_file($tmp, $abs . '/' . $filename)) {
            flash('error', '保存文件失败。');
            redirect(admin_url('media/'));
        }
        @chmod($abs . '/' . $filename, 0644);
        $width = $height = 0;
        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($abs . '/' . $filename);
            if (is_array($info)) {
                $width  = (int) $info[0];
                $height = (int) $info[1];
            }
        }
        $rel = substr($dir, strlen('uploads/')) . '/' . $filename;
        DB::insert('media', [
            'file'       => $rel,
            'mime'       => $mime,
            'width'      => $width,
            'height'     => $height,
            'alt'        => trim((string) ($_POST['alt'] ?? '')),
            'title'      => trim((string) ($_POST['title'] ?? '')) ?: $base,
            'created_at' => now(),
        ]);
        flash('success', '已上传：' . $filename);
        redirect(admin_url('media/'));
    }

    private function mediaDelete(): void
    {
        Auth::verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $row = $id > 0 ? DB::one('SELECT * FROM media WHERE id = ?', [$id]) : null;
        if (!$row) {
            $this->notFound();
            return;
        }
        $base = realpath((string) SYH_CONFIG['uploads']);
        $path = realpath((string) SYH_CONFIG['uploads'] . '/' . ltrim((string) $row['file'], '/'));
        if ($base && $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR) && is_file($path)) {
            @unlink($path);
        }
        DB::exec('UPDATE posts SET cover_id = 0 WHERE cover_id = ?', [$id]);
        DB::exec('DELETE FROM media WHERE id = ?', [$id]);
        flash('success', '已删除媒体文件。');
        redirect(admin_url('media/'));
    }

    private function terms(array $segments, string $method): void
    {
        $action = $segments[1] ?? '';
        if ($action === 'save' && $method === 'POST') {
            Auth::verifyCsrf();
            $id    = (int) ($_POST['id'] ?? 0);
            $tax   = (string) ($_POST['taxonomy'] ?? 'category');
            $tax   = $tax === 'post_tag' ? 'post_tag' : 'category';
            $name  = trim((string) ($_POST['name'] ?? ''));
            $slug  = admin_slugify((string) ($_POST['slug'] ?? ''));
            $desc  = trim((string) ($_POST['description'] ?? ''));
            $parent = $tax === 'category' ? max(0, (int) ($_POST['parent_id'] ?? 0)) : 0;
            if ($name === '') {
                flash('error', '名称不能为空。');
                redirect(admin_url('terms/?taxonomy=' . $tax));
            }
            if ($slug === '') {
                $slug = admin_slugify($name) ?: 'term';
            }
            if ($parent === $id && $id > 0) {
                $parent = 0;
            }
            if ($id > 0) {
                $existing = DB::one('SELECT * FROM terms WHERE id = ?', [$id]);
                if (!$existing) {
                    $this->notFound();
                    return;
                }
                $tax = (string) $existing['taxonomy'];
                DB::update('terms', [
                    'name'        => $name,
                    'slug'        => $this->uniqueTermSlug($tax, $slug, $id),
                    'description' => $desc,
                    'parent_id'   => $parent,
                ], 'id = ?', [$id]);
            } else {
                DB::insert('terms', [
                    'taxonomy'    => $tax,
                    'name'        => $name,
                    'slug'        => $this->uniqueTermSlug($tax, $slug, 0),
                    'description' => $desc,
                    'parent_id'   => $parent,
                    'count'       => 0,
                ]);
            }
            admin_refresh_term_counts();
            flash('success', '分类/标签已保存。');
            redirect(admin_url('terms/?taxonomy=' . $tax));
        }
        if ($action === 'delete' && $method === 'POST') {
            Auth::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $term = $id > 0 ? DB::one('SELECT * FROM terms WHERE id = ?', [$id]) : null;
            if (!$term) {
                $this->notFound();
                return;
            }
            DB::exec('DELETE FROM post_terms WHERE term_id = ?', [$id]);
            DB::exec('UPDATE terms SET parent_id = 0 WHERE parent_id = ?', [$id]);
            DB::exec('DELETE FROM terms WHERE id = ?', [$id]);
            admin_refresh_term_counts();
            flash('success', '已删除。');
            redirect(admin_url('terms/?taxonomy=' . $term['taxonomy']));
        }

        $tax = (string) ($_GET['taxonomy'] ?? 'category');
        $tax = $tax === 'post_tag' ? 'post_tag' : 'category';
        $editId = (int) ($_GET['edit'] ?? 0);
        $edit = $editId > 0 ? DB::one('SELECT * FROM terms WHERE id = ?', [$editId]) : null;
        $rows = DB::all('SELECT * FROM terms WHERE taxonomy = ? ORDER BY name', [$tax]);
        $parents = $tax === 'category' ? DB::all("SELECT * FROM terms WHERE taxonomy = 'category' AND id <> ? ORDER BY name", [$editId]) : [];
        $this->view('terms', [
            'title'          => $tax === 'post_tag' ? '标签' : '分类',
            'currentSection' => 'terms',
            'taxonomy'       => $tax,
            'rows'           => $rows,
            'edit'           => $edit,
            'parents'        => $parents,
        ]);
    }

    private function uniqueTermSlug(string $taxonomy, string $slug, int $excludeId): string
    {
        $base = $slug;
        $try  = $base;
        $i    = 2;
        while (DB::val('SELECT id FROM terms WHERE taxonomy = ? AND slug = ? AND id <> ?', [$taxonomy, $try, $excludeId])) {
            $try = $base . '-' . $i++;
        }
        return $try;
    }

    private function menu(array $segments, string $method): void
    {
        $action = $segments[1] ?? '';
        if ($action === 'save' && $method === 'POST') {
            Auth::verifyCsrf();
            $id     = (int) ($_POST['id'] ?? 0);
            $label  = trim((string) ($_POST['label'] ?? ''));
            $kind   = (string) ($_POST['kind'] ?? 'custom');
            $kind   = in_array($kind, ['custom', 'page', 'post', 'category'], true) ? $kind : 'custom';
            $object = max(0, (int) ($_POST['object_id'] ?? 0));
            $url    = trim((string) ($_POST['url'] ?? ''));
            $target = ($_POST['target'] ?? '') === '_blank' ? '_blank' : '';
            $parent = max(0, (int) ($_POST['parent_id'] ?? 0));
            $sort   = (int) ($_POST['sort'] ?? 0);
            if ($parent === $id && $id > 0) {
                $parent = 0;
            }
            if ($kind === 'page' || $kind === 'post') {
                $obj = $object > 0 ? DB::one('SELECT * FROM posts WHERE id = ? AND type = ?', [$object, $kind]) : null;
                if ($obj) {
                    $url = post_url($obj);
                    if ($label === '') {
                        $label = (string) $obj['title'];
                    }
                }
            } elseif ($kind === 'category') {
                $obj = $object > 0 ? DB::one("SELECT * FROM terms WHERE id = ? AND taxonomy = 'category'", [$object]) : null;
                if ($obj) {
                    $url = term_url($obj);
                    if ($label === '') {
                        $label = (string) $obj['name'];
                    }
                }
            }
            if ($label === '' || $url === '') {
                flash('error', '菜单名称和链接不能为空。');
                redirect(admin_url('menu/'));
            }
            $data = [
                'menu'      => 'primary',
                'parent_id' => $parent,
                'label'     => $label,
                'url'       => $url,
                'target'    => $target,
                'sort'      => $sort,
                'kind'      => $kind,
                'object_id' => $object,
            ];
            if ($id > 0) {
                DB::update('menu_items', $data, 'id = ?', [$id]);
            } else {
                DB::insert('menu_items', $data);
            }
            flash('success', '菜单已保存。');
            redirect(admin_url('menu/'));
        }
        if ($action === 'delete' && $method === 'POST') {
            Auth::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                $this->notFound();
                return;
            }
            DB::exec('UPDATE menu_items SET parent_id = 0 WHERE parent_id = ?', [$id]);
            DB::exec('DELETE FROM menu_items WHERE id = ?', [$id]);
            flash('success', '已删除菜单项。');
            redirect(admin_url('menu/'));
        }
        $editId = (int) ($_GET['edit'] ?? 0);
        $edit = $editId > 0 ? DB::one('SELECT * FROM menu_items WHERE id = ?', [$editId]) : null;
        $rows = DB::all("SELECT * FROM menu_items WHERE menu = 'primary' ORDER BY sort, id");
        $this->view('menu', [
            'title'          => '导航菜单',
            'currentSection' => 'menu',
            'rows'           => $rows,
            'edit'           => $edit,
            'pages'          => DB::all("SELECT id, title FROM posts WHERE type = 'page' AND status = 'publish' ORDER BY title"),
            'posts'          => DB::all("SELECT id, title FROM posts WHERE type = 'post' AND status = 'publish' ORDER BY title"),
            'categories'     => DB::all("SELECT id, name FROM terms WHERE taxonomy = 'category' ORDER BY name"),
        ]);
    }

    private function links(array $segments, string $method): void
    {
        $action = $segments[1] ?? '';
        if ($action === 'save' && $method === 'POST') {
            Auth::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $url  = trim((string) ($_POST['url'] ?? ''));
            if ($name === '' || $url === '') {
                flash('error', '名称和网址不能为空。');
                redirect(admin_url('links/'));
            }
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $data = [
                'grp'         => trim((string) ($_POST['grp'] ?? '好友')) ?: '好友',
                'name'        => $name,
                'url'         => $url,
                'image'       => trim((string) ($_POST['image'] ?? '')),
                'image_dark'  => trim((string) ($_POST['image_dark'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'rss'         => trim((string) ($_POST['rss'] ?? '')),
                'sort'        => (int) ($_POST['sort'] ?? 0),
            ];
            if ($id > 0) {
                DB::update('links', $data, 'id = ?', [$id]);
            } else {
                DB::insert('links', $data);
            }
            flash('success', '友链已保存。');
            redirect(admin_url('links/'));
        }
        if ($action === 'delete' && $method === 'POST') {
            Auth::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                $this->notFound();
                return;
            }
            DB::exec('DELETE FROM links WHERE id = ?', [$id]);
            flash('success', '已删除友链。');
            redirect(admin_url('links/'));
        }
        $editId = (int) ($_GET['edit'] ?? 0);
        $edit = $editId > 0 ? DB::one('SELECT * FROM links WHERE id = ?', [$editId]) : null;
        $this->view('links', [
            'title'          => '友情链接',
            'currentSection' => 'links',
            'rows'           => DB::all('SELECT * FROM links ORDER BY grp, sort, id'),
            'edit'           => $edit,
        ]);
    }

    private function code(array $segments, string $method): void
    {
        if (!Auth::can('admin')) {
            http_response_code(403);
            exit('没有权限');
        }
        $action = $segments[1] ?? '';
        $rel    = (string) ($_POST['file'] ?? $_GET['f'] ?? '');

        if ($action === 'save' && $method === 'POST') {
            Auth::verifyCsrf();
            $result = CodeEditor::save($rel, (string) ($_POST['content'] ?? ''));
            flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect(admin_url('code/?f=' . rawurlencode($rel)));
        }

        $files = CodeEditor::files();
        if ($rel === '') {
            $rel = isset($files['src/helpers.php']) ? 'src/helpers.php' : (string) array_key_first($files);
        }
        $path = CodeEditor::resolve($rel);
        if ($path === null) {
            $this->notFound();
            return;
        }
        $content = (string) file_get_contents($path);
        $this->view('code', [
            'title'          => '源码编辑',
            'currentSection' => 'code',
            'files'          => $files,
            'currentFile'    => $rel,
            'content'        => $content,
            'fileSize'       => strlen($content),
            'fileMtime'      => (int) filemtime($path),
            'lineCount'      => substr_count($content, "\n") + 1,
        ]);
    }

    private function settings(array $segments, string $method): void
    {
        if (!Auth::can('admin')) {
            http_response_code(403);
            exit('没有权限');
        }
        $action = $segments[1] ?? '';
        if ($action === 'mail-test' && $method === 'POST') {
            Auth::verifyCsrf();
            $to = trim((string) ($_POST['to'] ?? ''));
            $ok = false;
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $message = '请输入有效的收件邮箱。';
            } elseif (Mailer::sendTest($to)) {
                $ok = true;
                $message = '测试邮件已发送，请检查收件箱与垃圾邮件目录。';
            } else {
                $message = '发送失败：' . (Mailer::lastError() ?: '未知错误');
            }
            $ajax = ($_POST['ajax'] ?? '') === '1'
                || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
            if ($ajax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
                return;
            }
            flash($ok ? 'success' : 'error', $message);
            redirect(admin_url('settings/#mail'));
        }
        $fields = [
            'site_name', 'site_description', 'site_url', 'posts_per_page', 'timezone', 'site_launch',
            'homepage_banner_text', 'homepage_cover_image',
            'profile_title', 'profile_tagline', 'profile_intro', 'profile_location', 'profile_experience', 'profile_stack',
            'author_name', 'author_url',
            'custom_logo', 'site_icon', 'background_image', 'background_color', 'primary_color', 'text_color',
            'social_qq_qr', 'social_wechat_qr', 'social_bilibili', 'social_github',
            'donation_description', 'donation_wechat', 'donation_alipay', 'donation_wechat_qr', 'donation_alipay_qr',
            'links_page_intro', 'links_page_apply_title', 'links_page_apply_content', 'links_page_apply_contact', 'links_page_site_intro',
            'icp_record_number', 'icp_record_url', 'psb_record_number', 'psb_record_url',
            'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_from', 'smtp_from_name', 'notify_email',
        ];
        $bools = ['comment_moderation', 'comment_whitelist', 'require_name_email', 'mail_enabled', 'commenter_notify'];
        if ($method === 'POST') {
            Auth::verifyCsrf();
            foreach ($fields as $key) {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                $value = trim((string) $_POST[$key]);
                if (in_array($key, ['background_color', 'primary_color', 'text_color'], true) && $value !== '' && !preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                    flash('error', $key . ' 需要 #RRGGBB 格式。');
                    redirect(admin_url('settings/'));
                }
                if (in_array($key, ['custom_logo', 'site_icon'], true)) {
                    $value = (string) max(0, (int) $value);
                }
                if ($key === 'site_url') {
                    $value = rtrim($value, '/');
                    if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $value)) {
                        flash('error', '站点地址必须是完整的 http(s) URL。');
                        redirect(admin_url('settings/'));
                    }
                }
                if ($key === 'posts_per_page') {
                    $value = (string) max(1, min(50, (int) $value));
                }
                if ($key === 'smtp_port') {
                    $value = (string) max(1, min(65535, (int) $value));
                }
                if ($key === 'smtp_secure' && !in_array($value, ['ssl', 'tls', 'none'], true)) {
                    $value = 'ssl';
                }
                if (in_array($key, ['smtp_from', 'notify_email'], true) && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    flash('error', $key === 'smtp_from' ? '发件地址格式不正确。' : '通知邮箱格式不正确。');
                    redirect(admin_url('settings/'));
                }
                set_option($key, $value);
            }
            foreach ($bools as $key) {
                set_option($key, isset($_POST[$key]) ? '1' : '0');
            }
            // SMTP 密码：留空表示保持原值；勾选“清除”则清空。
            if (isset($_POST['smtp_pass_clear'])) {
                set_option('smtp_pass', '');
            } elseif ((string) ($_POST['smtp_pass'] ?? '') !== '') {
                set_option('smtp_pass', (string) $_POST['smtp_pass']);
            }
            flash('success', '设置已保存。');
            redirect(admin_url('settings/'));
        }
        $this->view('settings', [
            'title'          => '站点设置',
            'currentSection' => 'settings',
            'fields'         => $fields,
            'bools'          => $bools,
        ]);
    }

    private function appearance(string $method): void
    {
        if (!Auth::can('admin')) {
            http_response_code(403);
            exit('没有权限');
        }
        if ($method === 'POST') {
            Auth::verifyCsrf();
            $texts = [
                'site_name' => 100, 'site_description' => 300, 'homepage_banner_text' => 500,
                'background_image' => 500, 'homepage_cover_image' => 500,
            ];
            foreach ($texts as $key => $max) {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                $value = trim((string) $_POST[$key]);
                if (mb_strlen($value, 'UTF-8') > $max) {
                    flash('error', $key . ' 超出长度限制。');
                    redirect(admin_url('appearance/'));
                }
                if (in_array($key, ['background_image', 'homepage_cover_image'], true)
                    && $value !== ''
                    && !str_starts_with($value, '/')
                    && (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $value))) {
                    flash('error', '图片地址需以 / 开头或为完整的 http(s) URL。');
                    redirect(admin_url('appearance/'));
                }
                set_option($key, $value);
            }
            foreach (['primary_color', 'background_color', 'text_color'] as $key) {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                $value = trim((string) $_POST[$key]);
                if ($value !== '' && !preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                    flash('error', '颜色值需要 #RRGGBB 格式。');
                    redirect(admin_url('appearance/'));
                }
                set_option($key, $value);
            }
            foreach (['custom_logo', 'site_icon'] as $key) {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                $id = max(0, (int) $_POST[$key]);
                if ($id > 0 && !DB::val("SELECT id FROM media WHERE id = ? AND mime LIKE 'image/%'", [$id])) {
                    $id = 0;
                }
                set_option($key, (string) $id);
            }
            if (array_key_exists('posts_per_page', $_POST)) {
                set_option('posts_per_page', (string) max(1, min(50, (int) $_POST['posts_per_page'])));
            }
            flash('success', '外观已保存，刷新站点即可看到效果。');
            redirect(admin_url('appearance/'));
        }

        $this->view('appearance', [
            'title'          => '外观',
            'currentSection' => 'appearance',
            'media'          => DB::all("SELECT id, file, alt, title FROM media WHERE mime LIKE 'image/%' ORDER BY created_at DESC, id DESC LIMIT 500"),
        ]);
    }

    private function users(array $segments, string $method): void
    {
        if (!Auth::can('admin')) {
            http_response_code(403);
            exit('没有权限');
        }
        $action = $segments[1] ?? '';
        $roles  = ['admin', 'editor', 'author', 'subscriber'];

        if ($action === 'save' && $method === 'POST') {
            Auth::verifyCsrf();
            $id      = (int) ($_POST['id'] ?? 0);
            $login   = trim((string) ($_POST['login'] ?? ''));
            $email   = trim((string) ($_POST['email'] ?? ''));
            $display = trim((string) ($_POST['display_name'] ?? ''));
            $role    = (string) ($_POST['role'] ?? 'subscriber');
            $pass    = (string) ($_POST['password'] ?? '');
            $back    = $id > 0 ? admin_url('users/edit/' . $id . '/') : admin_url('users/');

            if (!in_array($role, $roles, true)) {
                flash('error', '角色无效。');
                redirect($back);
            }
            if ($display === '') {
                flash('error', '显示名称不能为空。');
                redirect($back);
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', '邮箱格式不正确。');
                redirect($back);
            }
            if ($id > 0) {
                $existing = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
                if (!$existing) {
                    flash('error', '用户不存在。');
                    redirect(admin_url('users/'));
                }
                $isSelf = (int) $existing['id'] === (int) (Auth::user()['id'] ?? 0);
                if ($isSelf && $role !== 'admin') {
                    flash('error', '不能修改自己的管理员角色。');
                    redirect($back);
                }
                $data = ['email' => $email, 'display_name' => $display, 'role' => $role];
                if ($pass !== '') {
                    if (mb_strlen($pass, 'UTF-8') < 8) {
                        flash('error', '密码至少 8 位。');
                        redirect($back);
                    }
                    $data['pass'] = password_hash($pass, PASSWORD_DEFAULT);
                }
                DB::update('users', $data, 'id = ?', [$id]);
                flash('success', '用户已更新。');
                redirect(admin_url('users/'));
            }

            if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $login)) {
                flash('error', '登录名需为 3-60 位字母、数字、点、下划线或短横线。');
                redirect($back);
            }
            if (DB::val('SELECT COUNT(*) FROM users WHERE login = ?', [$login], 0)) {
                flash('error', '登录名已存在。');
                redirect($back);
            }
            if (mb_strlen($pass, 'UTF-8') < 8) {
                flash('error', '密码至少 8 位。');
                redirect($back);
            }
            DB::insert('users', [
                'login'         => $login,
                'email'         => $email,
                'pass'          => password_hash($pass, PASSWORD_DEFAULT),
                'display_name'  => $display,
                'role'          => $role,
                'registered_at' => now(),
            ]);
            flash('success', '用户已创建。');
            redirect(admin_url('users/'));
        }

        if ($action === 'delete' && $method === 'POST') {
            Auth::verifyCsrf();
            $id = (int) ($_POST['id'] ?? 0);
            $target = DB::one('SELECT * FROM users WHERE id = ?', [$id]);
            if (!$target) {
                flash('error', '用户不存在。');
                redirect(admin_url('users/'));
            }
            if ((int) $target['id'] === (int) (Auth::user()['id'] ?? 0)) {
                flash('error', '不能删除当前登录的账号。');
                redirect(admin_url('users/'));
            }
            if ($target['role'] === 'admin' && (int) DB::val("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0) <= 1) {
                flash('error', '至少需要保留一个管理员。');
                redirect(admin_url('users/'));
            }
            DB::exec('UPDATE posts SET author_id = ? WHERE author_id = ?', [(int) (Auth::user()['id'] ?? 1), $id]);
            DB::exec('UPDATE comments SET user_id = 0 WHERE user_id = ?', [$id]);
            DB::exec('DELETE FROM users WHERE id = ?', [$id]);
            flash('success', '用户已删除，其文章已转交给当前账号。');
            redirect(admin_url('users/'));
        }

        $edit = null;
        if ($action === 'edit') {
            $edit = DB::one('SELECT * FROM users WHERE id = ?', [(int) ($segments[2] ?? 0)]);
            if (!$edit) {
                $this->notFound();
                return;
            }
        }
        $rows = DB::all(
            'SELECT u.*, (SELECT COUNT(*) FROM posts p WHERE p.author_id = u.id) AS post_count,
                    (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) AS comment_count
             FROM users u ORDER BY CASE u.role WHEN \'admin\' THEN 0 WHEN \'editor\' THEN 1 WHEN \'author\' THEN 2 ELSE 3 END, u.id'
        );
        $this->view('users', [
            'title'          => '用户',
            'currentSection' => 'users',
            'rows'           => $rows,
            'edit'           => $edit,
            'roles'          => $roles,
        ]);
    }

    private function profile(string $method): void
    {
        $user = Auth::user();
        if (!$user) {
            Auth::requireLogin();
            return;
        }
        if ($method === 'POST') {
            Auth::verifyCsrf();
            $display = trim((string) ($_POST['display_name'] ?? ''));
            $email   = trim((string) ($_POST['email'] ?? ''));
            $new     = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            $current = (string) ($_POST['current_password'] ?? '');
            if ($display === '') {
                flash('error', '显示名称不能为空。');
                redirect(admin_url('profile/'));
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', '邮箱格式不正确。');
                redirect(admin_url('profile/'));
            }
            $data = ['display_name' => $display, 'email' => $email];
            if ($new !== '' || $confirm !== '') {
                if (mb_strlen($new, 'UTF-8') < 8) {
                    flash('error', '新密码至少 8 位。');
                    redirect(admin_url('profile/'));
                }
                if ($new !== $confirm) {
                    flash('error', '两次输入的新密码不一致。');
                    redirect(admin_url('profile/'));
                }
                if (!Auth::verifyPassword($current, (string) $user['pass'])) {
                    flash('error', '当前密码不正确。');
                    redirect(admin_url('profile/'));
                }
                $data['pass'] = password_hash($new, PASSWORD_DEFAULT);
            }
            DB::update('users', $data, 'id = ?', [(int) $user['id']]);
            if (isset($data['pass'])) {
                session_regenerate_id(true);
                flash('success', '资料与密码已更新。');
            } else {
                flash('success', '资料已更新。');
            }
            redirect(admin_url('profile/'));
        }
        $this->view('profile', [
            'title'          => '个人资料',
            'currentSection' => 'profile',
            'user'           => $user,
        ]);
    }

    private function view(string $template, array $data = []): void
    {
        $data['adminUser'] = Auth::user();
        $data['flashes']   = take_flashes();
        extract($data, EXTR_SKIP);
        require SYH_ROOT . '/templates/admin/header.php';
        require SYH_ROOT . '/templates/admin/' . $template . '.php';
        require SYH_ROOT . '/templates/admin/footer.php';
    }

    private function notFound(): void
    {
        http_response_code(404);
        $this->view('not-found', [
            'title'          => '未找到',
            'currentSection' => '',
        ]);
    }
}
