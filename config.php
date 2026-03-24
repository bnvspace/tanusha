<?php
// config.php - Подключение к базе данных SQLite

$db_path = __DIR__ . '/portal.db';

try {
    $db = new PDO("sqlite:$db_path");
    // Настройка режима ошибок для PDO
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Режим получения данных по умолчанию - ассоциативный массив
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Глобальные настройки
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('SITE_NAME', 'Образовательный портал IPRE');
?>
