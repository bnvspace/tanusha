<?php
// auth.php - Управление сессиями и авторизацией

session_start();
require_once __DIR__ . '/config.php';

// Переводы
function __($key) {
    static $translations = null;
    if ($translations === null) {
        $translations = require __DIR__ . '/lang.php';
    }
    $lang = $_SESSION['lang'] ?? 'ru';
    return $translations[$lang][$key] ?? $key;
}

function is_authenticated() {
    return isset($_SESSION['user_id']);
}

function get_logged_in_user() {
    global $db;
    if (!is_authenticated()) return null;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function login_required($roles = []) {
    $user = get_logged_in_user();
    if (!$user) {
        header("Location: index.php?route=landing");
        exit;
    }
    
    if (!empty($roles) && !in_array($user['role'], $roles)) {
        header("Location: index.php?route=dashboard");
        exit;
    }
    return $user;
}

function verify_login($username, $password) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header("Location: index.php?route=landing");
    exit;
}
?>
