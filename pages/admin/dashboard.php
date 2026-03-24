<?php
// pages/admin/dashboard.php
require_once 'config.php';
require_once 'auth.php';

$user = login_required(['teacher', 'admin']);

// Студенты
$stmt = $db->prepare("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC");
$stmt->execute();
$students = $stmt->fetchAll();

// Ждут проверки
$stmt = $db->query("SELECT COUNT(*) FROM submissions WHERE status = 'pending'");
$pending = $stmt->fetchColumn();

// Всего ответов
$stmt = $db->query("SELECT COUNT(*) FROM submissions");
$total_subs = $stmt->fetchColumn();

// Тестов пройдено
$stmt = $db->query("SELECT COUNT(*) FROM test_submissions WHERE finished_at IS NOT NULL");
$test_subs = $stmt->fetchColumn();

$page_title = 'Панель управления';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📊 Панель управления</h1>
    <div class="breadcrumb">Добро пожаловать, <?= htmlspecialchars($user['full_name']) ?></div>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-label">Студентов</div>
    <div class="stat-value"><?= count($students) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-label">Ждут проверки</div>
    <div class="stat-value" style="color:var(--warning)"><?= $pending ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📝</div>
    <div class="stat-label">Всего ответов</div>
    <div class="stat-value"><?= $total_subs ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🧪</div>
    <div class="stat-label">Тестов пройдено</div>
    <div class="stat-value"><?= $test_subs ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <!-- Быстрые действия -->
  <div class="card">
    <div class="card-title">⚡ Быстрые действия</div>
    <div style="display:flex;flex-direction:column;gap:10px">
      <a href="index.php?route=admin_review" class="btn btn-primary">
        ✏️ Проверить задания
        <?php if ($pending > 0): ?><span style="background:#fff;color:var(--primary);border-radius:20px;padding:1px 8px;font-size:.78rem;margin-left:4px"><?= $pending ?></span><?php endif; ?>
      </a>
      <a href="index.php?route=admin_course" class="btn btn-secondary">🛠 Управление курсом</a>
      <a href="index.php?route=admin_students" class="btn btn-secondary">👥 Все студенты</a>
      <a href="index.php?route=admin_statistics" class="btn btn-secondary">📈 Статистика</a>
      <?php if ($user['role'] == 'admin'): ?>
      <a href="index.php?route=admin_users" class="btn btn-secondary">🔑 Пользователи системы</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Студенты -->
  <div class="card">
    <div class="card-title">👥 Студенты</div>
    <?php if (!empty($students)): ?>
    <div style="max-height:250px;overflow-y:auto">
      <?php 
      $recent_students = array_slice($students, 0, 10);
      foreach ($recent_students as $s): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($s['full_name']) ?></div>
          <div style="font-size:.75rem;color:var(--muted)">@<?= htmlspecialchars($s['username']) ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <span class="badge <?= $s['is_active'] ? 'badge-success' : 'badge-revision' ?>">
            <?= $s['is_active'] ? 'Активен' : 'Заблокирован' ?>
          </span>
          <a href="index.php?route=admin_student_detail&uid=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">→</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (count($students) > 10): ?>
    <a href="index.php?route=admin_students" style="font-size:.82rem;color:var(--primary);margin-top:10px;display:block">
      Показать всех (<?= count($students) ?>) →
    </a>
    <?php endif; ?>
    <?php else: ?>
    <p style="color:var(--muted);font-size:.88rem">Студентов пока нет.</p>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
