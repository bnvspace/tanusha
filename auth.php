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

function normalize_course_week_numbers(PDO $db, int $courseId): void {
    $stmt = $db->prepare("SELECT id, number FROM weeks WHERE course_id = ? ORDER BY number ASC, id ASC");
    $stmt->execute([$courseId]);
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $needsUpdate = false;
    foreach ($weeks as $index => $week) {
        if ((int) $week['number'] !== $index + 1) {
            $needsUpdate = true;
            break;
        }
    }

    if (!$needsUpdate) {
        return;
    }

    $updateStmt = $db->prepare("UPDATE weeks SET number = ? WHERE id = ?");
    foreach ($weeks as $index => $week) {
        $updateStmt->execute([$index + 1, $week['id']]);
    }
}

function format_week_title(?string $title): string {
    $title = trim((string) $title);
    if ($title === '') {
        return '';
    }

    $cleanTitle = preg_replace('/^(?:week|неделя|апта)\s*\d+\s*[:.\-–—)]*\s*/iu', '', $title);
    $cleanTitle = trim((string) $cleanTitle);

    return $cleanTitle !== '' ? $cleanTitle : $title;
}

function build_upload_url(?string $filePath): ?string {
    $filePath = trim((string) $filePath);
    if ($filePath === '') {
        return null;
    }

    return '/uploads/' . str_replace('%2F', '/', rawurlencode($filePath));
}

function get_course_documents(?array $course): array {
    if (!$course) {
        return [];
    }

    $documents = [];

    $glossaryUrl = build_upload_url($course['glossary_pdf_path'] ?? null);
    if ($glossaryUrl !== null) {
        $documents[] = [
            'label' => __('open_glossary'),
            'url' => $glossaryUrl,
        ];
    }

    $syllabusUrl = build_upload_url($course['syllabus_pdf_path'] ?? null);
    if ($syllabusUrl !== null) {
        $documents[] = [
            'label' => __('open_syllabus'),
            'url' => $syllabusUrl,
        ];
    }

    return $documents;
}
?>
