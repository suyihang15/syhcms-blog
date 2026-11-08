<?php
declare(strict_types=1);

// PHP 内置服务器路由：静态文件直接返回，其余交给前端控制器。
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . rawurldecode($path);
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
