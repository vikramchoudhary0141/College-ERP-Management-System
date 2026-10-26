<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
$configFile = ROOT . '/config/config.php';
$config = file_exists($configFile) ? require $configFile : require ROOT . '/config/config.example.php';
date_default_timezone_set('Asia/Kolkata');

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name($config['app']['session_name'] ?? 'college_erp_session');
session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Lax', 'path' => '/']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

require_once ROOT . '/app/db.php';
require_once ROOT . '/app/security.php';
require_once ROOT . '/app/modules.php';
