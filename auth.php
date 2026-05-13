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

function current_lang(): string {
    return $_SESSION['lang'] ?? 'ru';
}

function is_authenticated() {
    return isset($_SESSION['user_id']);
}

function default_route_for_role(?string $role): string {
    return in_array($role, ['teacher', 'admin'], true) ? 'admin_dashboard' : 'dashboard';
}

function redirect_to_route(string $route): void {
    header("Location: index.php?route=$route");
    exit;
}

function sanitize_internal_target(?string $target, string $fallback = 'index.php'): string {
    $target = trim((string) $target);

    if ($target === '' || preg_match('/[\r\n]/', $target)) {
        return $fallback;
    }

    $parts = parse_url($target);
    if ($parts === false) {
        return $fallback;
    }

    if (isset($parts['scheme']) || isset($parts['host'])) {
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $targetHost = strtolower((string) ($parts['host'] ?? ''));
        $targetPort = isset($parts['port']) ? ':' . $parts['port'] : '';

        if ($targetHost === '') {
            return $fallback;
        }

        $normalizedCurrentHost = strtolower($currentHost);
        $currentHostWithoutPort = strtolower((string) preg_replace('/:\d+$/', '', $currentHost));
        if (
            $normalizedCurrentHost !== ''
            && $targetHost !== $normalizedCurrentHost
            && $targetHost !== $currentHostWithoutPort
            && $targetHost . $targetPort !== $normalizedCurrentHost
        ) {
            return $fallback;
        }

        $target = (string) ($parts['path'] ?? '');
        if (isset($parts['query'])) {
            $target .= '?' . $parts['query'];
        }
    }

    if ($target === '') {
        return $fallback;
    }

    if ($target[0] === '/') {
        return str_starts_with($target, '/index.php') ? $target : $fallback;
    }

    return str_starts_with($target, 'index.php') ? $target : $fallback;
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
        redirect_to_route('landing');
    }
    
    if (!empty($roles) && !in_array($user['role'], $roles, true)) {
        redirect_to_route(default_route_for_role($user['role'] ?? null));
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
    redirect_to_route('landing');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function is_valid_csrf_token(?string $token): bool {
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

function require_csrf_token(): void {
    if (!is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit(__('csrf_invalid'));
    }
}

function require_post_request(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        exit(__('method_not_allowed'));
    }
}

function utc_now(): DateTimeImmutable {
    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
}

function parse_utc_datetime(?string $value): ?DateTimeImmutable {
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    return new DateTimeImmutable($value, new DateTimeZone('UTC'));
}

function is_open_for_students(?string $openDate): bool {
    $openAt = parse_utc_datetime($openDate);
    return $openAt === null || $openAt <= utc_now();
}

function is_deadline_reached(?string $deadline): bool {
    $deadlineAt = parse_utc_datetime($deadline);
    return $deadlineAt !== null && $deadlineAt < utc_now();
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

    $assessmentCriteriaUrl = build_upload_url($course['assessment_criteria_path'] ?? null);
    if ($assessmentCriteriaUrl !== null) {
        $documents[] = [
            'label' => __('open_assessment_criteria'),
            'url' => $assessmentCriteriaUrl,
        ];
    }

    return $documents;
}
?>
