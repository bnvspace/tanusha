<?php
require_once 'config.php';

// Список стандартных пользователей и паролей для миграции
$users = [
    'admin' => 'admin123',
    'teacher' => 'teacher123',
    'student1' => 'student123'
];

echo "<h3>Миграция хешей паролей...</h3>";

foreach ($users as $username => $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$hash, $username]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Пароль для пользователя <b>$username</b> успешно обновлен!<br>";
    } else {
        echo "⚠️ Пользователь <b>$username</b> не найден в базе (или пароль уже обновлен).<br>";
    }
}

echo "<br><b>Готово! Теперь основные аккаунты доступны для входа через PHP.</b>";
?>
