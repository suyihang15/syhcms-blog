<?php
declare(strict_types=1);

final class App
{
    public function run(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $raw    = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $trimmed = trim(rawurldecode($raw), '/');
        $path    = $trimmed === '' ? '/' : '/' . $trimmed . '/';

        if ($path === '/api/hit/') {
            $this->hit($method);
            return;
        }
        if ($path === '/api/stats/') {
            if ($method !== 'GET') {
                http_response_code(405);
                exit;
            }
            header('Cache-Control: no-store');
            json_out(stats_data());
        }

        if (str_starts_with($path, '/admin')) {
            (new Admin())->dispatch($path, $method);
            return;
        }

        if ($path === '/comment/' && $method === 'POST') {
            (new Frontend())->submitComment();
            return;
        }

        (new Frontend())->dispatch($path, $method);
    }

    private function hit(string $method): void
    {
        if ($method !== 'POST') {
            json_out(['ok' => false], 405);
        }
        $body = file_get_contents('php://input') ?: '';
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        $postId = isset($data['id']) ? (int) $data['id'] : 0;
        $ref    = array_key_exists('ref', $data) ? (string) $data['ref'] : null;
        if ($ref !== null && strlen($ref) > 500) {
            $ref = substr($ref, 0, 500);
        }
        // 已登录的编辑者自己浏览不计入浏览量。
        if (Auth::check() && Auth::can('manage')) {
            json_out(['ok' => true, 'skipped' => 'editor']);
        }
        json_out(stats_record_hit($postId ?: null, $ref));
    }
}
