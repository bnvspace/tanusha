<?php

$user = get_logged_in_user();
$route = $_GET['route'] ?? 'landing';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?? 'Портал' ?> — ИЯ для теплоэнергетиков</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/static/css/style.css">
  <?= $extra_css ?? '' ?>
</head>
<body>
<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-title">🎓 ИЯ для теплоэнергетиков</div>
      <div class="logo-sub">Образовательный портал</div>
    </div>
    <nav class="sidebar-nav">
      <?php if ($user): ?>
        <?php if (in_array($user['role'], ['teacher', 'admin'])): ?>
          <div class="nav-section">Управление</div>
          <a href="/index.php?route=admin_dashboard" class="<?= $route == 'admin_dashboard' ? 'active' : '' ?>">
            📊 Панель управления
          </a>
          <a href="/index.php?route=admin_students" class="<?= in_array($route, ['admin_students', 'admin_student_detail']) ? 'active' : '' ?>">
            👥 Студенты
          </a>
          <a href="/index.php?route=admin_review" class="<?= in_array($route, ['admin_review', 'admin_review_detail']) ? 'active' : '' ?>">
            ✏️ Проверка заданий
          </a>
          <a href="/index.php?route=admin_course" class="<?= (strpos($route, 'course') !== false || strpos($route, 'material') !== false || strpos($route, 'assignment') !== false || strpos($route, 'test') !== false) ? 'active' : '' ?>">
            📚 Управление курсом
          </a>
          <a href="/index.php?route=admin_statistics" class="<?= $route == 'admin_statistics' ? 'active' : '' ?>">
            📈 Статистика
          </a>
          <?php if ($user['role'] == 'admin'): ?>
          <a href="/index.php?route=admin_users" class="<?= $route == 'admin_users' ? 'active' : '' ?>">
            🔑 Пользователи
          </a>
          <?php endif; ?>
        <?php else: ?>
          <div class="nav-section">Курс</div>
          <a href="/index.php?route=dashboard" class="<?= $route == 'dashboard' ? 'active' : '' ?>">
            🏠 Главная
          </a>
          <a href="/index.php?route=materials" class="<?= $route == 'materials' ? 'active' : '' ?>">
            📖 Учебные материалы
          </a>
          <a href="/index.php?route=assignments" class="<?= in_array($route, ['assignments', 'assignment_detail']) ? 'active' : '' ?>">
            📝 Задания
          </a>
          <a href="/index.php?route=tests" class="<?= in_array($route, ['tests', 'test_take', 'test_result']) ? 'active' : '' ?>">
            🧪 Тесты
          </a>
          <a href="/index.php?route=grades" class="<?= $route == 'grades' ? 'active' : '' ?>">
            🏆 Мои оценки
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>
    <?php if ($user): ?>
    <div class="sidebar-footer">
      <div class="user-info">Вы вошли как</div>
      <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div style="margin-top:10px">
        <a href="/index.php?route=logout" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center">
          Выйти
        </a>
      </div>
    </div>
    <?php endif; ?>
  </aside>

  <!-- Main -->
  <main class="main-content">
    <?php if (isset($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $f): ?>
            <div class="alert alert-<?= $f['type'] ?>">
              <?= $f['icon'] ?> <?= htmlspecialchars($f['message']) ?>
            </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
