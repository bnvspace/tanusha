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
  <link rel="icon" type="image/svg+xml" href="/static/favicon.svg">
  <link rel="stylesheet" href="/static/css/style.css">
  <?= $extra_css ?? '' ?>
</head>
<body>
<div class="layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-title">🎓 <?= __('app_name') ?></div>
      <div class="logo-sub"><?= __('app_sub') ?></div>
    </div>
    
    <div class="lang-switcher" style="display:flex;gap:12px;padding:8px 20px 20px;font-size:0.75rem;font-weight:600">
      <a href="index.php?route=set_lang&lang=ru" style="color:<?= $_SESSION['lang'] == 'ru' ? 'var(--primary)' : 'var(--muted)' ?>">RU</a>
      <a href="index.php?route=set_lang&lang=kk" style="color:<?= $_SESSION['lang'] == 'kk' ? 'var(--primary)' : 'var(--muted)' ?>">KK</a>
      <a href="index.php?route=set_lang&lang=en" style="color:<?= $_SESSION['lang'] == 'en' ? 'var(--primary)' : 'var(--muted)' ?>">EN</a>
    </div>

    <nav class="sidebar-nav">
      <?php if ($user): ?>
        <?php if (in_array($user['role'], ['teacher', 'admin'])): ?>
          <div class="nav-section"><?= __('admin_panel') ?></div>
          <a href="/index.php?route=admin_dashboard" class="<?= $route == 'admin_dashboard' ? 'active' : '' ?>">
            📊 <?= __('admin_panel') ?>
          </a>
          <a href="/index.php?route=admin_students" class="<?= in_array($route, ['admin_students', 'admin_student_detail']) ? 'active' : '' ?>">
            👥 <?= __('students') ?>
          </a>
          <a href="/index.php?route=admin_review" class="<?= in_array($route, ['admin_review', 'admin_review_detail']) ? 'active' : '' ?>">
            ✏️ <?= __('review') ?>
          </a>
          <a href="/index.php?route=admin_course" class="<?= (strpos($route, 'course') !== false || strpos($route, 'material') !== false || strpos($route, 'assignment') !== false || strpos($route, 'test') !== false || strpos($route, 'discussion') !== false) ? 'active' : '' ?>">
            📚 <?= __('course_mgmt') ?>
          </a>
          <a href="/index.php?route=admin_statistics" class="<?= $route == 'admin_statistics' ? 'active' : '' ?>">
            📈 <?= __('statistics') ?>
          </a>
          <?php if ($user['role'] == 'admin'): ?>
          <a href="/index.php?route=admin_users" class="<?= $route == 'admin_users' ? 'active' : '' ?>">
            🔑 <?= __('users') ?>
          </a>
          <?php endif; ?>
        <?php else: ?>
          <div class="nav-section"><?= __('course_mgmt') ?></div>
          <a href="/index.php?route=dashboard" class="<?= $route == 'dashboard' ? 'active' : '' ?>">
            🏠 <?= __('dashboard') ?>
          </a>
          <a href="/index.php?route=materials" class="<?= in_array($route, ['materials', 'week_discussion', 'discussion_topic']) ? 'active' : '' ?>">
            📖 <?= __('materials') ?>
          </a>
          <a href="/index.php?route=assignments" class="<?= in_array($route, ['assignments', 'assignment_detail']) ? 'active' : '' ?>">
            📝 <?= __('assignments') ?>
          </a>
          <a href="/index.php?route=tests" class="<?= in_array($route, ['tests', 'test_take', 'test_result']) ? 'active' : '' ?>">
            🧪 <?= __('tests') ?>
          </a>
          <a href="/index.php?route=grades" class="<?= $route == 'grades' ? 'active' : '' ?>">
            🏆 <?= __('grades') ?>
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>
    <?php if ($user): ?>
    <div class="sidebar-footer">
      <div class="user-info"><?= __('welcome') ?></div>
      <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
      <div style="margin-top:10px">
        <a href="/index.php?route=logout" class="btn btn-secondary btn-sm" style="width:100%;justify-content:center">
          <?= __('logout') ?>
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
