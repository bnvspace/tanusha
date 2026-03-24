<?php
// pages/dashboard.php



// Данные курса
$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

if (!$course) {
    die("Курс не найден в базе данных.");
}

// Получаем недели курса
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

// Статистика студента
$stmt = $db->prepare("SELECT COUNT(*) FROM submissions WHERE user_id = ? AND status != 'pending'");
$stmt->execute([$user['id']]);
$done_assignments = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM test_submissions WHERE user_id = ? AND finished_at IS NOT NULL");
$stmt->execute([$user['id']]);
$done_tests = $stmt->fetchColumn();

$page_title = 'Курс';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1><?= htmlspecialchars($course['title']) ?></h1>
    <div class="breadcrumb">Главная страница курса</div>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">📝</div>
    <div class="stat-label">Недель в курсе</div>
    <div class="stat-value"><?= count($weeks) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Выполнено заданий</div>
    <div class="stat-value"><?= $done_assignments ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🧪</div>
    <div class="stat-label">Тестов пройдено</div>
    <div class="stat-value"><?= $done_tests ?></div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-title">📋 О курсе</div>
  <p style="margin-bottom:14px;color:var(--muted);line-height:1.7"><?= htmlspecialchars($course['description'] ?? '') ?></p>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px">
    <div>
      <div style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px">
        🎯 Цели курса
      </div>
      <p style="font-size:.9rem;line-height:1.7;color:var(--text)"><?= htmlspecialchars($course['goals'] ?? '') ?></p>
    </div>
    <div>
      <div style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px">
        📌 Задачи
      </div>
      <p style="font-size:.9rem;line-height:1.7;color:var(--text);white-space:pre-line"><?= htmlspecialchars($course['objectives'] ?? '') ?></p>
    </div>
  </div>

  <?php if (!empty($course['content_info'])): ?>
  <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
    <div style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px">
      📚 Содержание обучения
    </div>
    <p style="font-size:.9rem;line-height:1.7;color:var(--text)"><?= htmlspecialchars($course['content_info']) ?></p>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">📅 Структура курса</div>
  <?php if (!empty($weeks)): ?>
    <?php foreach ($weeks as $week): 
        // Считаем материалы, задания и тесты для этой недели
        $stmt = $db->prepare("SELECT COUNT(*) FROM materials WHERE week_id = ?");
        $stmt->execute([$week['id']]);
        $mat_count = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM assignments WHERE week_id = ?");
        $stmt->execute([$week['id']]);
        $asn_count = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM tests WHERE week_id = ?");
        $stmt->execute([$week['id']]);
        $tst_count = $stmt->fetchColumn();
    ?>
    <div class="week-block">
      <div class="week-header" style="justify-content:space-between">
        <div class="week-title">
          <div class="week-num"><?= $week['number'] ?></div>
          <?= htmlspecialchars($week['title']) ?>
        </div>
        <span style="font-size:.8rem;color:var(--muted)">
          <?= $mat_count ?> материал(а) · <?= $asn_count ?> задание(й) · <?= $tst_count ?> тест(а)
        </span>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:var(--muted);text-align:center;padding:20px">Недели курса ещё не добавлены.</p>
  <?php endif; ?>
</div>

<div style="display:flex;gap:12px;margin-top:20px">
  <a href="index.php?route=materials" class="btn btn-primary">📖 Учебные материалы</a>
  <a href="index.php?route=assignments" class="btn btn-secondary">📝 Задания</a>
  <a href="index.php?route=tests" class="btn btn-secondary">🧪 Тесты</a>
  <a href="index.php?route=grades" class="btn btn-secondary">🏆 Оценки</a>
</div>

<?php include 'footer.php'; ?>
