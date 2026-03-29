<?php

require_once __DIR__ . '/bootstrap.php';

define('DATA_DIR', __DIR__ . '/storage/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('SITE_NAME', 'Образовательный портал IPRE');

function resolve_database_path(): string {
    $envPath = getenv('PORTAL_DB_PATH');
    if ($envPath !== false && trim($envPath) !== '') {
        $envPath = trim($envPath);
        $envDir = dirname($envPath);

        if (!is_dir($envDir)) {
            @mkdir($envDir, 0777, true);
        }

        return $envPath;
    }

    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0777, true);
    }

    $storageDbPath = DATA_DIR . 'portal.db';
    $legacyDbPath = __DIR__ . '/portal.db';

    if (file_exists($storageDbPath)) {
        return $storageDbPath;
    }

    if (file_exists($legacyDbPath)) {
        if (@copy($legacyDbPath, $storageDbPath)) {
            return $storageDbPath;
        }

        return $legacyDbPath;
    }

    return $storageDbPath;
}

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

define('DB_PATH', resolve_database_path());

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    bootstrap_database($db);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}
?>
