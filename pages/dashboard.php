<?php
// pages/dashboard.php



// Данные курса
$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

if (!$course) {
    die("Курс не найден.");
}

// ... (логика без изменений)

$page_title = __('dashboard');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1><?= htmlspecialchars($course['title']) ?></h1>
    <div class="breadcrumb"><?= __('course_home') ?></div>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">📝</div>
    <div class="stat-label"><?= __('weeks_in_course') ?></div>
    <div class="stat-value"><?= count($weeks) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label"><?= __('assignments_done') ?></div>
    <div class="stat-value"><?= $done_assignments ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🧪</div>
    <div class="stat-label"><?= __('tests_done') ?></div>
    <div class="stat-value"><?= $done_tests ?></div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-title">📋 <?= __('about_course') ?></div>
  <p style="margin-bottom:14px;color:var(--muted);line-height:1.7"><?= htmlspecialchars($course['description'] ?? '') ?></p>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px">
    <div>
      <div style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px">
        🎯 <?= __('course_goals') ?>
      </div>
      <p style="font-size:.9rem;line-height:1.7;color:var(--text)"><?= htmlspecialchars($course['goals'] ?? '') ?></p>
    </div>
    <div>
      <div style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px">
        📌 <?= __('course_objectives') ?>
      </div>
      <p style="font-size:.9rem;line-height:1.7;color:var(--text);white-space:pre-line"><?= htmlspecialchars($course['objectives'] ?? '') ?></p>
    </div>
  </div>

  <?php if (!empty($course['content_info'])): ?>
  <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
    <div style="font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px">
      📚 <?= __('learning_content') ?>
    </div>
    <p style="font-size:.9rem;line-height:1.7;color:var(--text)"><?= htmlspecialchars($course['content_info']) ?></p>
  </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title">📅 <?= __('course_structure') ?></div>
  <?php if (!empty($weeks)): ?>
    <?php foreach ($weeks as $week): 
        // ... (логика без изменений)
    ?>
    <div class="week-block">
      <div class="week-header" style="justify-content:space-between">
        <div class="week-title">
          <div class="week-num"><?= $week['number'] ?></div>
          <?= htmlspecialchars($week['title']) ?>
        </div>
        <span style="font-size:.8rem;color:var(--muted)">
          <?= $mat_count ?> <?= __('mats_unit') ?> · <?= $asn_count ?> <?= __('asns_unit') ?> · <?= $tst_count ?> <?= __('tests_unit') ?>
        </span>
      </div>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:var(--muted);text-align:center;padding:20px"><?= __('no_data') ?></p>
  <?php endif; ?>
</div>

<div style="display:flex;gap:12px;margin-top:20px">
  <a href="index.php?route=materials" class="btn btn-primary">📖 <?= __('materials') ?></a>
  <a href="index.php?route=assignments" class="btn btn-secondary">📝 <?= __('assignments') ?></a>
  <a href="index.php?route=tests" class="btn btn-secondary">🧪 <?= __('tests') ?></a>
  <a href="index.php?route=grades" class="btn btn-secondary">🏆 <?= __('grades') ?></a>
</div>

<?php include 'footer.php'; ?>
