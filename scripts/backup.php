<?php
declare(strict_types=1);

// Creates a portable backup archive with a MySQL SQL dump and uploaded files.

$rootDir = dirname(__DIR__);
$defaultOutDir = $rootDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
$outDir = $defaultOutDir;

foreach ($argv as $index => $arg) {
    if ($index === 0) {
        continue;
    }

    if (str_starts_with($arg, '--out=')) {
        $outDir = substr($arg, strlen('--out='));
        continue;
    }

    if ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php scripts/backup.php [--out=path]\n");
        exit(0);
    }
}

$outDir = rtrim($outDir, "\\/");
if ($outDir === '') {
    fwrite(STDERR, "Output directory cannot be empty.\n");
    exit(1);
}

if (!is_dir($outDir) && !mkdir($outDir, 0775, true)) {
    fwrite(STDERR, "Cannot create output directory: $outDir\n");
    exit(1);
}

$env = load_backup_env($rootDir . DIRECTORY_SEPARATOR . '.env');
$dbHost = env_value($env, 'DB_HOST', '127.0.0.1');
$dbPort = env_value($env, 'DB_PORT', '3306');
$dbName = env_value($env, 'DB_NAME', 'portal');
$dbUser = env_value($env, 'DB_USER', 'root');
$dbPass = env_value($env, 'DB_PASS', '');
$dbCharset = env_value($env, 'DB_CHARSET', 'utf8mb4');

$stamp = date('Ymd-His');
$baseName = 'portal-backup-' . $stamp;
$workDir = $outDir . DIRECTORY_SEPARATOR . $baseName;
$sqlPath = $workDir . DIRECTORY_SEPARATOR . 'database.sql';
$manifestPath = $workDir . DIRECTORY_SEPARATOR . 'manifest.txt';
$uploadsDir = $rootDir . DIRECTORY_SEPARATOR . 'uploads';
$zipPath = $outDir . DIRECTORY_SEPARATOR . $baseName . '.zip';

if (!mkdir($workDir, 0775, true)) {
    fwrite(STDERR, "Cannot create working directory: $workDir\n");
    exit(1);
}

try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=$dbCharset";
    $db = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('SET NAMES ' . preg_replace('/[^a-zA-Z0-9_]/', '', $dbCharset));

    dump_mysql_database($db, $dbName, $sqlPath);

    $manifest = [
        'Created at: ' . date(DATE_ATOM),
        'Database: ' . $dbName,
        'Host: ' . $dbHost . ':' . $dbPort,
        'SQL dump: database.sql',
        'Uploads included: ' . (is_dir($uploadsDir) ? 'yes' : 'no'),
    ];
    file_put_contents($manifestPath, implode(PHP_EOL, $manifest) . PHP_EOL);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create zip archive: $zipPath");
    }

    add_file_to_zip($zip, $sqlPath, 'database.sql');
    add_file_to_zip($zip, $manifestPath, 'manifest.txt');
    if (is_dir($uploadsDir)) {
        add_directory_to_zip($zip, $uploadsDir, 'uploads');
    }
    $zip->close();

    remove_directory($workDir);

    fwrite(STDOUT, "Backup created: $zipPath\n");
    exit(0);
} catch (Throwable $e) {
    remove_directory($workDir);
    fwrite(STDERR, "Backup failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

function load_backup_env(string $path): array {
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $length = strlen($value);
        if ($length >= 2 && (($value[0] === '"' && $value[$length - 1] === '"') || ($value[0] === "'" && $value[$length - 1] === "'"))) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }

    return $env;
}

function env_value(array $env, string $key, string $default): string {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $env[$key] ?? $default;
}

function dump_mysql_database(PDO $db, string $dbName, string $sqlPath): void {
    $handle = fopen($sqlPath, 'wb');
    if ($handle === false) {
        throw new RuntimeException("Cannot write SQL dump: $sqlPath");
    }

    fwrite($handle, "-- Portal backup\n");
    fwrite($handle, "-- Database: `$dbName`\n");
    fwrite($handle, "-- Created: " . date(DATE_ATOM) . "\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
    fwrite($handle, "SET NAMES utf8mb4;\n\n");

    $tables = $db->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
    foreach ($tables as $tableRow) {
        $table = $tableRow[0];
        $quotedTable = quote_identifier($table);
        $createRow = $db->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_ASSOC);
        $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;
        if (!$createSql) {
            throw new RuntimeException("Cannot read CREATE TABLE for $table");
        }

        fwrite($handle, "\nDROP TABLE IF EXISTS $quotedTable;\n");
        fwrite($handle, $createSql . ";\n\n");

        $stmt = $db->query('SELECT * FROM ' . $quotedTable);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = array_map('quote_identifier', array_keys($row));
            $values = array_map(static fn($value): string => sql_literal($value), array_values($row));
            fwrite($handle, 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
        }
        fwrite($handle, "\n");
    }

    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
}

function quote_identifier(string $name): string {
    return '`' . str_replace('`', '``', $name) . '`';
}

function sql_literal(mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace(["\\", "'", "\0"], ["\\\\", "\\'", "\\0"], (string) $value) . "'";
}

function add_file_to_zip(ZipArchive $zip, string $path, string $name): void {
    if (is_file($path)) {
        $zip->addFile($path, $name);
    }
}

function add_directory_to_zip(ZipArchive $zip, string $dir, string $zipDir): void {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relative = str_replace('\\', '/', substr($path, strlen($dir) + 1));
        $zipPath = $zipDir . '/' . $relative;

        if ($item->isDir()) {
            $zip->addEmptyDir($zipPath);
        } elseif ($item->isFile()) {
            $zip->addFile($path, $zipPath);
        }
    }
}

function remove_directory(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($dir);
}
