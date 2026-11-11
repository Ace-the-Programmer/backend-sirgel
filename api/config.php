<?php
declare(strict_types=1);

// Auto-detect environment: Local XAMPP vs x10hosting (elementfx.com)
$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
$isHosting = (strpos($hostHeader, 'elementfx.com') !== false || strpos($hostHeader, 'x10') !== false);

if ($isHosting) {
    // ==========================================
    // x10hosting (Live Production) Settings
    // ==========================================
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'rsjxgeti_taradb');
    define('DB_USER', 'rsjxgeti_taradb');
    define('DB_PASS', '9DJcvDUkq3Wyvjj7eeSD');
} else {
    // ==========================================
    // Local XAMPP Settings
    // ==========================================
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'ionic_students_api');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

function get_db_connection(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}
