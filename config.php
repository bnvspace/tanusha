<?php
// config.php - Подключение к SQLite и автозапуск инициализации PHP-версии

require_once __DIR__ . '/bootstrap.php';

$db_path = __DIR__ . '/portal.db';

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    bootstrap_database($db);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('SITE_NAME', 'Образовательный портал IPRE');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
?>
