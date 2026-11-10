<?php
declare(strict_types=1);

final class Frontend
{
    private int $perPage = 10;
    private array $page = [];

    public function __construct()
    {
        $this->perPage = max(1, (int) setting('posts_per_page', 10));
    }

    public function dispatch(string $path, string $method): void
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            http_response_code(405);
            header('Allow: GET, HEAD');
            exit('Method Not Allowed');
        }

        $bare = '/' . trim($path, '/');

        if ($bare === '/feed' || $bare === '/feed/rss' || $bare === '/rss') {
            $this->feed('rss');
            return;
        }
        if ($bare === '/feed/atom') {
            $this->feed('atom');
            return;
        }
        if ($bare === '/comments/feed') {
            $this->commentFeed();
            return;
        }
        if ($bare === '/sitemap.xml' || $bare === '/sitemap_index.xml' || $bare === '/wp-sitemap.xml') {
            $this->sitemap();
            return;
        }
        if ($bare === '/robots.txt') {
            $this->robots();
            return;
        }
        if ($bare === '/favicon.ico') {
            $icon = site_icon_url();
            if ($icon !== '') {
                redirect($icon, 301);
            }
            http_response_code(404);
            exit;
        }

        if ($path === '/') {
            if (isset($_GET['s'])) {
                $this->search();
                return;
            }
            $this->home(1);
            return;
        }

        if (preg_match('#^/page/(\d+)/$#', $path, $m)) {
            $this->home(max(1, (int) $m[1]));
            return;
        }

        if (preg_match('#^/category/(.+?)(?:/page/(\d+))?/$#', $path, $m)) {
            $this->taxonomy('category', explode('/', trim($m[1], '/')), (int) ($m[2] ?? 1));
            return;
        }

        if (preg_match('#^/tag/([^/]+)(?:/page/(\d+))?/$#', $path, $m)) {
            $this->taxonomy('post_tag', [rawurldecode($m[1])], (int) ($m[2] ?? 1));
            return;
        }

        if (preg_match('#^/author/([^/]+)(?:/page/(\d+))?/$#', $path, $m)) {
            $this->author(rawurldecode($m[1]), (int) ($m[2] ?? 1));
            return;
        }

        if (preg_match('#^/(\d{4})(?:/(\d{2}))?(?:/(\d{2}))?(?:/page/(\d+))?/$#', $path, $m)) {
            $this->dateArchive($m[1], $m[2] ?? '', $m[3] ?? '', (int) ($m[4] ?? 1));
            return;
        }

        if ($path === '/links/' || $path === '/友情链接/') {
            $page = DB::one("SELECT * FROM posts WHERE type = 'page' AND status = 'publish' AND template = 'page-links.php' ORDER BY menu_order, id LIMIT 1");
            if ($page) {
                $this->page($page);
                return;
            }
        }

        if (preg_match('#^/([^/]+)/$#', $path, $m)) {
            $slug = rawurldecode($m[1]);
            $post = DB::one("SELECT * FROM posts WHERE type = 'post' AND status = 'publish' AND slug = ? LIMIT 1", [$slug]);
            if ($post) {
                $this->single($post);
                return;
            }
            $page = DB::one("SELECT * FROM posts WHERE type = 'page' AND status = 'publish' AND slug = ? LIMIT 1", [$slug]);
            if ($page) {
                if ($slug === 'links') {
                    // /links/ 由上面的别名分支处理。
                }
                $this->page($page);
                return;
            }
            // 兼容旧版中文 slug：数据库里可能仍保留百分号编码。
            $encoded = rawurlencode($slug);
            if ($encoded !== $slug) {
                $post = DB::one("SELECT * FROM posts WHERE type = 'post' AND status = 'publish' AND slug = ? LIMIT 1", [$encoded]);
                if ($post) {
                    $this->single($post);
                    return;
                }
            }
        }

        $this->notFound();
    }

    private function home(int $paged): void
    {
        $total = (int) DB::val("SELECT COUNT(*) FROM posts WHERE type = 'post' AND status = 'publish'", [], 0);
        $pages = max(1, (int) ceil($total / $this->perPage));
        if ($paged > $pages && $total > 0) {
            $this->notFound();
            return;
        }
        $offset = ($paged - 1) * $this->perPage;
        $posts  = DB::all(
            "SELECT * FROM posts WHERE type = 'post' AND status = 'publish' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
            [$this->perPage, $offset]
        );
        $this->renderPage('home', [
            'title'       => '',
            'description' => site_description(),
            'body_class'  => 'home blog',
            'canonical'   => $paged > 1 ? abs_url('page/' . $paged . '/') : site_url() . '/',
            'posts'       => $posts,
            'paged'       => $paged,
            'total_pages' => $pages,
        ]);
    }

    private function taxonomy(string $taxonomy, array $segments, int $paged): void
    {
        $term = $this->findTerm($taxonomy, $segments);
        if (!$term) {
            $this->notFound();
            return;
        }
        $total = (int) DB::val(
            "SELECT COUNT(*) FROM posts p JOIN post_terms pt ON pt.post_id = p.id WHERE pt.term_id = ? AND p.type = 'post' AND p.status = 'publish'",
            [(int) $term['id']],
            0
        );
        $pages  = max(1, (int) ceil($total / $this->perPage));
        $paged  = max(1, min($paged, $pages));
        $offset = ($paged - 1) * $this->perPage;
        $posts  = DB::all(
            "SELECT p.* FROM posts p JOIN post_terms pt ON pt.post_id = p.id
             WHERE pt.term_id = ? AND p.type = 'post' AND p.status = 'publish'
             ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?",
            [(int) $term['id'], $this->perPage, $offset]
        );
        $isTag = $taxonomy === 'post_tag';
        $label = ($isTag ? '标签：' : '分类：') . $term['name'];
        $this->renderPage('archive', [
            'title'       => $label,
            'description' => trim((string) $term['description']) !== '' ? (string) $term['description'] : $label . ' · ' . site_name(),
            'body_class'  => 'archive ' . ($isTag ? 'tag' : 'category') . ' term-' . $term['id'],
            'canonical'   => abs_url(term_path($term) . '/'),
            'heading'     => $label,
            'term'        => $term,
            'posts'       => $posts,
            'paged'       => $paged,
            'total_pages' => $pages,
            'base_url'    => term_url($term),
        ]);
    }

    private function author(string $login, int $paged): void
    {
        $user = DB::one('SELECT * FROM users WHERE login = ?', [$login]);
        if (!$user) {
            $this->notFound();
            return;
        }
        $total = (int) DB::val(
            "SELECT COUNT(*) FROM posts WHERE type = 'post' AND status = 'publish' AND author_id = ?",
            [(int) $user['id']],
            0
        );
        if ($total === 0) {
            $this->notFound();
            return;
        }
        $pages  = max(1, (int) ceil($total / $this->perPage));
        $paged  = max(1, min($paged, $pages));
        $offset = ($paged - 1) * $this->perPage;
        $posts  = DB::all(
            "SELECT * FROM posts WHERE type = 'post' AND status = 'publish' AND author_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
            [(int) $user['id'], $this->perPage, $offset]
        );
        $name = $user['display_name'] !== '' ? $user['display_name'] : $user['login'];
        $this->renderPage('archive', [
            'title'       => '作者：' . $name,
            'description' => '作者：' . $name . ' · ' . site_name(),
            'body_class'  => 'archive author',
            'canonical'   => abs_url('author/' . rawurlencode((string) $user['login']) . '/'),
            'heading'     => '作者：' . $name,
            'posts'       => $posts,
            'paged'       => $paged,
            'total_pages' => $pages,
            'base_url'    => author_url($user),
        ]);
    }

    private function dateArchive(string $year, string $month, string $day, int $paged): void
    {
        $like = $year . '-' . ($month !== '' ? $month : '') . ($day !== '' ? '-' . $day : '');
        $total = (int) DB::val(
            "SELECT COUNT(*) FROM posts WHERE type = 'post' AND status = 'publish' AND created_at LIKE ?",
            [$like . '%'],
            0
        );
        if ($total === 0) {
            $this->notFound();
            return;
        }
        $pages  = max(1, (int) ceil($total / $this->perPage));
        $paged  = max(1, min($paged, $pages));
        $offset = ($paged - 1) * $this->perPage;
        $posts  = DB::all(
            "SELECT * FROM posts WHERE type = 'post' AND status = 'publish' AND created_at LIKE ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?",
            [$like . '%', $this->perPage, $offset]
        );
        $label = (int) $year . ' 年' . ($month !== '' ? ' ' . (int) $month . ' 月' : '') . ($day !== '' ? ' ' . (int) $day . ' 日' : '');
        $base  = abs_url(trim($year . '/' . $month . '/' . $day, '/') . '/');
        $this->renderPage('archive', [
            'title'       => '日期：' . $label,
            'description' => '日期归档：' . $label,
            'body_class'  => 'archive date',
            'canonical'   => $base,
            'heading'     => '日期：' . $label,
            'posts'       => $posts,
            'paged'       => $paged,
            'total_pages' => $pages,
            'base_url'    => $base,
        ]);
    }

    private function search(): void
    {
        $query = trim((string) ($_GET['s'] ?? ''));
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $posts = [];
        $total = 0;
        $pages = 1;
        if ($query !== '') {
            $needle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $total  = (int) DB::val(
                "SELECT COUNT(*) FROM posts WHERE status = 'publish' AND type IN ('post','page') AND (title LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\')",
                [$needle, $needle, $needle],
                0
            );
            $pages  = max(1, (int) ceil($total / $this->perPage));
            $paged  = max(1, min($paged, $pages));
            $offset = ($paged - 1) * $this->perPage;
            $posts  = DB::all(
                "SELECT * FROM posts WHERE status = 'publish' AND type IN ('post','page') AND (title LIKE ? ESCAPE '\\' OR content LIKE ? ESCAPE '\\' OR excerpt LIKE ? ESCAPE '\\') ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$needle, $needle, $needle, $this->perPage, $offset]
            );
        }
        $this->renderPage('search', [
            'title'       => '搜索结果：' . $query,
            'description' => '搜索：' . $query,
            'body_class'  => 'search search-results',
            'canonical'   => abs_url('?s=' . rawurlencode($query)),
            'query'       => $query,
            'posts'       => $posts,
            'total'       => $total,
            'paged'       => $paged,
            'total_pages' => $pages,
            'base_url'    => '/',
            'base_query'  => ['s' => $query],
        ]);
    }

    private function single(array $post): void
    {
        $GLOBALS['syh_post'] = $post;
        $cats = categories_of((int) $post['id']);
        $tags = tags_of((int) $post['id']);
        $prev = DB::one(
            "SELECT * FROM posts WHERE type = 'post' AND status = 'publish' AND (created_at < ? OR (created_at = ? AND id < ?)) ORDER BY created_at DESC, id DESC LIMIT 1",
            [$post['created_at'], $post['created_at'], (int) $post['id']]
        );
        $next = DB::one(
            "SELECT * FROM posts WHERE type = 'post' AND status = 'publish' AND (created_at > ? OR (created_at = ? AND id > ?)) ORDER BY created_at ASC, id ASC LIMIT 1",
            [$post['created_at'], $post['created_at'], (int) $post['id']]
        );
        $related = [];
        if ($cats) {
            $ids = array_map(static fn (array $c): int => (int) $c['id'], $cats);
            $in  = implode(',', array_fill(0, count($ids), '?'));
            $params = $ids;
            $params[] = (int) $post['id'];
            $params[] = 4;
            $related = DB::all(
                "SELECT DISTINCT p.* FROM posts p JOIN post_terms pt ON pt.post_id = p.id
                 WHERE pt.term_id IN ($in) AND p.id <> ? AND p.type = 'post' AND p.status = 'publish'
                 ORDER BY p.created_at DESC LIMIT ?",
                $params
            );
        }
        $this->renderPage('single', [
            'title'       => $post['title'],
            'description' => post_excerpt($post, 150),
            'body_class'  => 'single single-post postid-' . (int) $post['id'],
            'canonical'   => abs_url(post_url($post)),
            'og_type'     => 'article',
            'post'        => $post,
            'cats'        => $cats,
            'tags'        => $tags,
            'prev'        => $prev,
            'next'        => $next,
            'related'     => $related,
            'comments'    => comments_tree((int) $post['id']),
            'comment_count' => comment_count((int) $post['id']),
        ]);
    }

    private function page(array $page): void
    {
        $GLOBALS['syh_post'] = $page;
        $template = (string) $page['template'];
        $view     = match ($template) {
            'page-stats.php'     => 'page-stats',
            'page-archive.php'   => 'page-archive',
            'page-guestbook.php' => 'page-guestbook',
            'page-links.php'     => 'page-links',
            default              => 'page',
        };
        $data = [
            'title'         => $page['title'],
            'description'   => post_excerpt($page, 150),
            'body_class'    => 'page ' . ($template !== '' ? 'page-template-' . preg_replace('/\.php$/', '', $template) : 'page-template-default'),
            'canonical'     => abs_url(post_url($page)),
            'page'          => $page,
            'comments'      => comments_tree((int) $page['id']),
            'comment_count' => comment_count((int) $page['id']),
        ];
        if ($view === 'page-archive') {
            $rows = DB::all("SELECT * FROM posts WHERE type = 'post' AND status = 'publish' ORDER BY created_at DESC, id DESC");
            $groups = [];
            foreach ($rows as $row) {
                $ts = strtotime((string) $row['created_at']);
                $y  = (int) date('Y', $ts ?: time());
                $mo = (int) date('n', $ts ?: time());
                $groups[$y][$mo][] = $row;
            }
            $data['archive_groups'] = $groups;
            $data['archive_total']  = count($rows);
            $data['archive_rows']   = $rows;
        }
        if ($view === 'page-links') {
            $data['link_groups'] = link_groups();
        }
        $this->renderPage($view, $data);
    }

    private function findTerm(string $taxonomy, array $segments): ?array
    {
        $segments = array_values(array_filter(array_map('rawurldecode', $segments), static fn ($s) => $s !== ''));
        if (!$segments) {
            return null;
        }
        $slug = end($segments);
        $rows = DB::all('SELECT * FROM terms WHERE taxonomy = ? AND slug = ?', [$taxonomy, $slug]);
        foreach ($rows as $row) {
            $path = array_map(static fn (array $t): string => (string) $t['slug'], term_ancestors($row));
            $path[] = (string) $row['slug'];
            if ($path === $segments) {
                return $row;
            }
        }
        return $rows[0] ?? null;
    }

    public function submitComment(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            exit;
        }
        Auth::verifyCsrf();

        $postId = (int) ($_POST['post_id'] ?? 0);
        $post   = DB::one("SELECT * FROM posts WHERE id = ? AND status = 'publish' AND type IN ('post','page')", [$postId]);
        if (!$post) {
            http_response_code(404);
            exit('文章不存在');
        }
        $guestbook = (string) $post['template'] === 'page-guestbook.php';
        $back      = post_url($post) . '#comments';
        $ip        = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect($back);
        }
        if (rate_limit('comment:' . $ip, 5, 600)) {
            flash('error', '提交太频繁了，请稍后再试。');
            redirect($back);
        }

        $author  = trim((string) ($_POST['author'] ?? ''));
        $email   = trim((string) ($_POST['email'] ?? ''));
        $url     = trim((string) ($_POST['url'] ?? ''));
        $content = trim((string) ($_POST['comment'] ?? ''));
        $parent  = (int) ($_POST['parent_id'] ?? 0);

        $errors = [];
        $maxAuthor = $guestbook ? 30 : 245;
        if ($author === '') {
            $errors[] = '请填写昵称。';
        } elseif (mb_strlen($author, 'UTF-8') > $maxAuthor) {
            $errors[] = '昵称太长了。';
        }
        $emailRequired = !$guestbook && setting_bool('require_name_email', true);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '邮箱格式不正确。';
        } elseif ($email === '' && $emailRequired) {
            $errors[] = '请填写邮箱。';
        }
        if ($content === '') {
            $errors[] = '评论内容不能为空。';
        } elseif (mb_strlen($content, 'UTF-8') > ($guestbook ? 2000 : 5000)) {
            $errors[] = '评论内容太长了。';
        }
        if ($url !== '') {
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = '网站地址格式不正确。';
            }
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect($back);
        }

        if ($parent > 0) {
            $parentRow = DB::one("SELECT id FROM comments WHERE id = ? AND post_id = ? AND status = 'approved'", [$parent, $postId]);
            if (!$parentRow) {
                $parent = 0;
            }
        }

        $dupe = DB::one(
            'SELECT id FROM comments WHERE post_id = ? AND author = ? AND email = ? AND content = ? LIMIT 1',
            [$postId, $author, $email, $content]
        );
        if ($dupe) {
            flash('error', '这条内容已经提交过了。');
            redirect($back);
        }

        $status = 'pending';
        $trusted = false;
        if ($email !== '') {
            $trusted = (bool) DB::val(
                "SELECT COUNT(*) FROM comments WHERE email = ? AND status = 'approved'",
                [$email],
                0
            );
        }
        $linkCount = preg_match_all('#https?://#i', $content);
        if (!$guestbook && !setting_bool('comment_moderation', false) && setting_bool('comment_whitelist', true) && $trusted) {
            $status = 'approved';
        }
        if ($linkCount > 2) {
            $status = 'pending';
        }
        // 登录的管理员/编辑在本站回复直接发布，自己的回复不必再走审核。
        if (Auth::can('manage')) {
            $status = 'approved';
        }

        $id = DB::insert('comments', [
            'post_id'    => $postId,
            'parent_id'  => $parent,
            'author'     => $author,
            'email'      => $email,
            'url'        => $url,
            'content'    => $content,
            'status'     => $status,
            'created_at' => now(),
            'ip'         => $ip,
            'ua'         => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'user_id'    => Auth::check() ? (int) Auth::user()['id'] : 0,
        ]);
        remember_commenter($author, $email, $url);

        $commentRow = DB::one('SELECT * FROM comments WHERE id = ?', [$id]);
        if ($commentRow && class_exists('Mailer')) {
            $commentRow['status'] = $status;
            Mailer::queueNewComment($commentRow, $post);
            if ($status === 'approved') {
                Mailer::queueReplyNotice($commentRow, $post);
            }
        }

        if ($status === 'approved') {
            flash('success', '评论已发布。');
            redirect(post_url($post) . '#comment-' . $id);
        }
        flash('success', $guestbook ? '留言已提交，审核通过后会公开显示。' : '评论已提交，审核通过后会显示。');
        redirect($back);
    }

    private function feed(string $type): void
    {
        $posts = DB::all("SELECT * FROM posts WHERE type = 'post' AND status = 'publish' ORDER BY created_at DESC LIMIT 20");
        $self  = site_url();
        $updated = $posts ? date(DATE_RSS, strtotime($posts[0]['updated_at']) ?: time()) : date(DATE_RSS);
        header('Content-Type: ' . ($type === 'atom' ? 'application/atom+xml' : 'application/rss+xml') . '; charset=utf-8');
        header('Cache-Control: public, max-age=600');
        if ($type === 'atom') {
            echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
            echo '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";
            echo '<title>' . e(site_name()) . '</title><subtitle>' . e(site_description()) . '</subtitle>';
            echo '<link href="' . e($self . '/feed/atom/') . '" rel="self"/><link href="' . e($self . '/') . '"/>';
            echo '<updated>' . e(date(DATE_ATOM, strtotime($posts[0]['updated_at'] ?? 'now') ?: time())) . '</updated>';
            echo '<id>' . e($self . '/') . '</id><author><name>' . e(site_name()) . '</name></author>';
            foreach ($posts as $post) {
                $link = abs_url(post_url($post));
                echo '<entry><title>' . e($post['title']) . '</title><link href="' . e($link) . '"/>';
                echo '<id>' . e($link) . '</id><updated>' . e(date(DATE_ATOM, strtotime($post['updated_at']) ?: time())) . '</updated>';
                echo '<published>' . e(date(DATE_ATOM, strtotime($post['created_at']) ?: time())) . '</published>';
                echo '<author><name>' . e(post_author_name($post)) . '</name></author>';
                echo '<content type="html">' . e(content_html((string) $post['content'])) . '</content></entry>';
            }
            echo '</feed>';
            return;
        }
        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/"><channel>';
        echo '<title>' . e(site_name()) . '</title><link>' . e($self . '/') . '</link><description>' . e(site_description()) . '</description>';
        echo '<language>zh-CN</language><lastBuildDate>' . e($updated) . '</lastBuildDate>';
        echo '<atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="' . e($self . '/feed/') . '" rel="self" type="application/rss+xml"/>';
        foreach ($posts as $post) {
            $link = abs_url(post_url($post));
            echo '<item><title>' . e($post['title']) . '</title><link>' . e($link) . '</link>';
            echo '<guid isPermaLink="true">' . e($link) . '</guid>';
            echo '<pubDate>' . e(date(DATE_RSS, strtotime($post['created_at']) ?: time())) . '</pubDate>';
            echo '<dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">' . e(post_author_name($post)) . '</dc:creator>';
            foreach (categories_of((int) $post['id']) as $cat) {
                echo '<category><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string) $cat['name']) . ']]></category>';
            }
            echo '<description><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', post_excerpt($post, 300)) . ']]></description>';
            echo '<content:encoded><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', content_html((string) $post['content'])) . ']]></content:encoded>';
            echo '</item>';
        }
        echo '</channel></rss>';
    }

    private function commentFeed(): void
    {
        $rows = DB::all(
            "SELECT c.*, p.slug, p.title AS post_title FROM comments c JOIN posts p ON p.id = c.post_id
             WHERE c.status = 'approved' AND p.status = 'publish' ORDER BY c.created_at DESC LIMIT 30"
        );
        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=600');
        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        echo '<rss version="2.0"><channel><title>' . e(site_name()) . ' 评论</title>';
        echo '<link>' . e(site_url() . '/') . '</link><description>' . e(site_description()) . '</description><language>zh-CN</language>';
        foreach ($rows as $row) {
            $link = abs_url(post_url($row)) . '#comment-' . (int) $row['id'];
            echo '<item><title>' . e($row['author'] . ' 评论《' . $row['post_title'] . '》') . '</title>';
            echo '<link>' . e($link) . '</link><guid isPermaLink="false">' . e(site_url() . '/?comment=' . (int) $row['id']) . '</guid>';
            echo '<pubDate>' . e(date(DATE_RSS, strtotime($row['created_at']) ?: time())) . '</pubDate>';
            echo '<description><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string) $row['content']) . ']]></description></item>';
        }
        echo '</channel></rss>';
    }

    private function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $add = static function (string $loc, string $lastmod = '', string $priority = '0.6'): void {
            echo '<url><loc>' . e($loc) . '</loc>';
            if ($lastmod !== '') {
                echo '<lastmod>' . e(date('c', strtotime($lastmod) ?: time())) . '</lastmod>';
            }
            echo '<priority>' . $priority . '</priority></url>';
        };
        $add(site_url() . '/', '', '1.0');
        foreach (DB::all("SELECT * FROM posts WHERE status = 'publish' AND type IN ('post','page') ORDER BY updated_at DESC") as $row) {
            $add(abs_url(post_url($row)), (string) $row['updated_at'], $row['type'] === 'post' ? '0.8' : '0.5');
        }
        foreach (DB::all("SELECT * FROM terms WHERE taxonomy = 'category' AND count > 0") as $term) {
            $add(abs_url(term_path($term) . '/'), '', '0.4');
        }
        echo '</urlset>';
    }

    private function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /comment/\n\nSitemap: " . site_url() . "/sitemap.xml\n";
    }

    private function notFound(): void
    {
        http_response_code(404);
        $this->renderPage('404', [
            'title'       => '页面不存在',
            'description' => '抱歉，你访问的页面不存在或已被移除。',
            'body_class'  => 'error404',
            'canonical'   => site_url() . '/',
        ]);
    }

    private function renderPage(string $template, array $data, int $status = 200): void
    {
        if ($status !== 200) {
            http_response_code($status);
        }
        $this->page = array_merge([
            'title'       => '',
            'description' => site_description(),
            'body_class'  => '',
            'canonical'   => '',
            'og_type'     => 'website',
            'og_image'    => og_image_url(),
        ], $data);
        $GLOBALS['syh_page'] = $this->page;
        $GLOBALS['syh_site'] = [
            'name'        => site_name(),
            'description' => site_description(),
            'url'         => site_url(),
        ];

        header('Content-Type: text/html; charset=utf-8');
        if (empty($this->page['canonical'])) {
            $this->page['canonical'] = site_url() . '/';
        }
        $this->page['canonical'] = str_starts_with($this->page['canonical'], 'http')
            ? $this->page['canonical']
            : site_url() . '/' . ltrim($this->page['canonical'], '/');
        $GLOBALS['syh_page'] = $this->page;
        render($template, $this->page);
    }
}
