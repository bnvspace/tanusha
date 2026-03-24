<?php
// fix_paths.php - Загрузите на сервер и откройте в браузере
$dir = __DIR__ . '/pages';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

echo "<h3>Исправление путей...</h3>";
$count = 0;
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        $new_content = str_replace("require_once 'config.php';", "", $content);
        $new_content = str_replace("require_once 'auth.php';", "", $new_content);
        if ($content !== $new_content) {
            file_put_contents($file->getPathname(), preg_replace("/^\\s*$/m", "", $new_content));
            echo "Исправлен: " . $file->getFilename() . "<br>";
            $count++;
        }
    }
}

// Исправление header.php
$headerFile = __DIR__ . '/header.php';
$content = file_get_contents($headerFile);
$new_content = str_replace("require_once 'auth.php';", "", $content);
if ($content !== $new_content) {
    file_put_contents($headerFile, $new_content);
    echo "Исправлен: header.php<br>";
    $count++;
}

echo "<br><b>Готово! Исправлено файлов: $count</b>";
?>
