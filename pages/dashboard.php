<?php
// pages/dashboard.php

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

if (!$course) {
    die("Курс не найден.");
}

$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(DISTINCT assignment_id) FROM submissions WHERE user_id = ?");
$stmt->execute([$user['id']]);
$done_assignments = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(DISTINCT test_id) FROM test_submissions WHERE user_id = ? AND finished_at IS NOT NULL");
$stmt->execute([$user['id']]);
$done_tests = (int) $stmt->fetchColumn();

foreach ($weeks as &$week) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM materials
         WHERE week_id = ? AND visible = 1 AND (open_date IS NULL OR open_date <= CURRENT_TIMESTAMP)"
    );
    $stmt->execute([$week['id']]);
    $week['materials_count'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM assignments
         WHERE week_id = ? AND visible = 1 AND (open_date IS NULL OR open_date <= CURRENT_TIMESTAMP)"
    );
    $stmt->execute([$week['id']]);
    $week['assignments_count'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM tests
         WHERE week_id = ? AND visible = 1 AND (open_date IS NULL OR open_date <= CURRENT_TIMESTAMP)"
    );
    $stmt->execute([$week['id']]);
    $week['tests_count'] = (int) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM discussion_topics WHERE week_id = ?");
    $stmt->execute([$week['id']]);
    $week['discussion_topics_count'] = (int) $stmt->fetchColumn();
}
unset($week);

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
    <?php foreach ($weeks as $week): ?>
    <div class="week-block">
      <div class="week-header forum-week-header" style="justify-content:space-between">
        <div class="week-title">
          <div class="week-num"><?= $week['number'] ?></div>
          <?= htmlspecialchars($week['title']) ?>
        </div>
        <div class="forum-week-actions">
          <span style="font-size:.8rem;color:var(--muted)">
            <?= $week['materials_count'] ?> <?= __('mats_unit') ?> · <?= $week['assignments_count'] ?> <?= __('asns_unit') ?> · <?= $week['tests_count'] ?> <?= __('tests_unit') ?>
          </span>
          <a href="index.php?route=week_discussion&wid=<?= $week['id'] ?>&from=dashboard" class="btn btn-secondary btn-sm">
            💬 <?= __('open_discussion') ?>
            <?php if (!empty($week['discussion_topics_count'])): ?>
              <span class="forum-count-badge"><?= $week['discussion_topics_count'] ?></span>
            <?php endif; ?>
          </a>
        </div>
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
