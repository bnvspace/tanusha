<?php

require_once __DIR__ . '/bootstrap.php';

define('DATA_DIR', __DIR__ . '/storage/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('SITE_NAME', 'Образовательный портал IPRE');

// ─── Настройки подключения к MariaDB ────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'p-352504_espforhpe');
define('DB_USER', 'p-352504_admin');
define('DB_PASS', 'admin123aa$');
define('DB_CHARSET', 'utf8mb4');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $db = new PDO($dsn, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec("SET NAMES utf8mb4");

    bootstrap_database($db);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}
?>
