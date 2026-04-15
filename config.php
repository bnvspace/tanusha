<?php

require_once __DIR__ . '/bootstrap.php';

function load_env_file(string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $length = strlen($value);
        if (
            $length >= 2
            && (
                ($value[0] === '"' && $value[$length - 1] === '"')
                || ($value[0] === "'" && $value[$length - 1] === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function config_env(string $key, ?string $default = null): ?string {
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

define('DATA_DIR', __DIR__ . '/storage/');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
load_env_file(__DIR__ . '/.env');
define('SITE_NAME', config_env('SITE_NAME', 'Образовательный портал IPRE'));

define('DB_HOST', config_env('DB_HOST', '127.0.0.1'));
define('DB_PORT', config_env('DB_PORT', '3306'));
define('DB_NAME', config_env('DB_NAME', 'portal'));
define('DB_USER', config_env('DB_USER', 'root'));
define('DB_PASS', config_env('DB_PASS', ''));
define('DB_CHARSET', config_env('DB_CHARSET', 'utf8mb4'));

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
    die(
        'Ошибка подключения к базе данных: ' . $e->getMessage()
        . '. Проверьте DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS и что MariaDB/MySQL запущена.'
    );
}
?>
