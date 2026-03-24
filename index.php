<?php
// index.php - Главный роутер проекта

require_once 'auth.php';

$route = $_GET['route'] ?? 'landing';

// Функция для установки flash-сообщения
function set_flash($message, $type = 'info') {
    $icon = 'ℹ️';
    if ($type == 'success') $icon = '✅';
    if ($type == 'danger') $icon = '❌';
    if ($type == 'warning') $icon = '⚠️';
    
    $_SESSION['flash'][] = [
        'message' => $message,
        'type' => $type,
        'icon' => $icon
    ];
}

// Обработка маршрутов
switch ($route) {
    case 'landing':
        include 'pages/landing.php';
        break;
        
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (verify_login($_POST['username'], $_POST['password'])) {
                $user = get_current_user();
                if (in_array($user['role'], ['teacher', 'admin'])) {
                    header("Location: index.php?route=admin_dashboard");
                } else {
                    header("Location: index.php?route=dashboard");
                }
                exit;
            } else {
                set_flash('Неверный логин или пароль.', 'danger');
                header("Location: index.php?route=landing");
                exit;
            }
        }
        include 'pages/landing.php'; // Форма входа на лендинге
        break;
        
    case 'register':
        include 'pages/register.php';
        break;
        
    case 'logout':
        logout();
        break;

    case 'dashboard':
        $user = login_required(['student']);
        include 'pages/dashboard.php';
        break;
        
    case 'materials':
        $user = login_required(['student']);
        include 'pages/materials.php';
        break;

    case 'assignments':
        $user = login_required(['student']);
        include 'pages/assignments.php';
        break;

    case 'assignment_detail':
        $user = login_required(['student']);
        $aid = $_GET['aid'] ?? null;
        if (!$aid) die("ID задания не указан.");
        include 'pages/assignment_detail.php';
        break;
        
    case 'tests':
        $user = login_required(['student']);
        include 'pages/tests.php';
        break;

    case 'test_take':
        $user = login_required(['student']);
        $tid = $_GET['tid'] ?? null;
        if (!$tid) die("ID теста не указан.");
        include 'pages/test_take.php';
        break;

    case 'test_result':
        $user = login_required(['student', 'teacher', 'admin']);
        $tid = $_GET['tid'] ?? null;
        $sid = $_GET['sid'] ?? null;
        if (!$tid || !$sid) die("ID не указаны.");
        include 'pages/test_result.php';
        break;

    case 'grades':
        $user = login_required(['student']);
        include 'pages/grades.php';
        break;

    // Админка
    case 'admin_dashboard':
        $user = login_required(['teacher', 'admin']);
        include 'pages/admin/dashboard.php';
        break;

    case 'admin_users':
        $user = login_required(['admin']);
        include 'pages/admin/users.php';
        break;

    case 'admin_create_user':
        $user = login_required(['admin']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $full_name = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role = $_POST['role'] ?? 'student';
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hash, $full_name, $role]);
            set_flash('Пользователь создан.', 'success');
        }
        header("Location: index.php?route=admin_users");
        exit;

    case 'admin_toggle_user':
        $user = login_required(['admin']);
        $uid = $_GET['uid'] ?? null;
        if ($uid && $uid != $user['id']) {
            $stmt = $db->prepare("SELECT is_active FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $st = $stmt->fetch();
            if ($st) {
                $new_status = $st['is_active'] ? 0 : 1;
                $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                $stmt->execute([$new_status, $uid]);
                set_flash('Статус пользователя изменен.', 'success');
            }
        }
        $ref = $_SERVER['HTTP_REFERER'] ?? 'index.php?route=admin_users';
        header("Location: $ref");
        exit;

    case 'admin_course':
        $user = login_required(['teacher', 'admin']);
        include 'pages/admin/course.php';
        break;

    case 'admin_add_material':
        $user = login_required(['teacher', 'admin']);
        include 'pages/admin/add_material.php';
        break;

    case 'admin_edit_material':
        $user = login_required(['teacher', 'admin']);
        $mid = $_GET['mid'] ?? null;
        if (!$mid) die("ID материала не указан.");
        include 'pages/admin/edit_material.php';
        break;

    case 'admin_delete_material':
        $user = login_required(['teacher', 'admin']);
        $mid = $_GET['mid'] ?? null;
        if ($mid) {
            $stmt = $db->prepare("DELETE FROM materials WHERE id = ?");
            $stmt->execute([$mid]);
            set_flash('Материал удален.', 'success');
        }
        header("Location: index.php?route=admin_course");
        exit;

    case 'admin_add_assignment':
        $user = login_required(['teacher', 'admin']);
        include 'pages/admin/add_assignment.php';
        break;

    case 'admin_edit_assignment':
        $user = login_required(['teacher', 'admin']);
        $aid = $_GET['aid'] ?? null;
        if (!$aid) die("ID задания не указан.");
        include 'pages/admin/edit_assignment.php';
        break;

    case 'admin_delete_assignment':
        $user = login_required(['teacher', 'admin']);
        $aid = $_GET['aid'] ?? null;
        if ($aid) {
            $stmt = $db->prepare("DELETE FROM assignments WHERE id = ?");
            $stmt->execute([$aid]);
            set_flash('Задание удалено.', 'success');
        }
        header("Location: index.php?route=admin_course");
        exit;

    case 'admin_add_test':
        $user = login_required(['teacher', 'admin']);
        include 'pages/admin/add_test.php';
        break;

    case 'admin_edit_test':
        $user = login_required(['teacher', 'admin']);
        $tid = $_GET['tid'] ?? null;
        if (!$tid) die("ID теста не указан.");
        include 'pages/admin/edit_test.php';
        break;

    case 'admin_delete_test':
        $user = login_required(['teacher', 'admin']);
        $tid = $_GET['tid'] ?? null;
        if ($tid) {
            $stmt = $db->prepare("DELETE FROM tests WHERE id = ?");
            $stmt->execute([$tid]);
            set_flash('Тест удален.', 'success');
        }
        header("Location: index.php?route=admin_course");
        exit;

    case 'admin_students':
        $user = login_required(['teacher', 'admin']);
        include 'pages/admin/students.php';
        break;
        
    case 'admin_student_detail':
        $user = login_required(['teacher', 'admin']);
        $uid = $_GET['uid'] ?? null;
        if (!$uid) die("ID студента не указан.");
        include 'pages/admin/student_detail.php';
        break;

    case 'admin_review':
        $user = login_required(['teacher', 'admin']);
        include 'pages/admin/review.php';
        break;

    case 'admin_review_detail':
        $user = login_required(['teacher', 'admin']);
        $sid = $_GET['sid'] ?? null;
        if (!$sid) die("ID ответа не указан.");
        include 'pages/admin/review_detail.php';
        break;

    case 'admin_statistics':
        $user = login_required(['teacher', 'admin']);
        include 'pages/admin/statistics.php';
        break;

    default:
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
        break;
}
?>
