<?php
declare(strict_types=1);

define('SYH_ROOT', dirname(__DIR__));
define('SYH_CONFIG', require SYH_ROOT . '/config.php');

require SYH_ROOT . '/src/Database.php';
require SYH_ROOT . '/src/helpers.php';
require SYH_ROOT . '/src/Auth.php';
require SYH_ROOT . '/src/CodeEditor.php';
require SYH_ROOT . '/src/Mailer.php';
require SYH_ROOT . '/src/App.php';
require SYH_ROOT . '/src/Frontend.php';
require SYH_ROOT . '/src/Admin.php';

date_default_timezone_set((string) SYH_CONFIG['timezone']);

if (SYH_CONFIG['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', SYH_ROOT . '/data/php-error.log');
}

$sessionDir = SYH_CONFIG['sessions'];
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0775, true);
}
session_save_path($sessionDir);
session_name(SYH_CONFIG['session']);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

foreach (['data', 'data/backup', 'data/cache'] as $dir) {
    if (!is_dir(SYH_ROOT . '/' . $dir)) {
        mkdir(SYH_ROOT . '/' . $dir, 0775, true);
    }
}
