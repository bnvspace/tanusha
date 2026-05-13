<?php
// pages/dashboard.php

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

if (!$course) {
    die(__('no_data'));
}

normalize_course_week_numbers($db, (int) $course['id']);
$courseDocuments = get_course_documents($course);

$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(DISTINCT assignment_id) FROM submissions WHERE user_id = ?");
$stmt->execute([$user['id']]);
$doneAssignments = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(DISTINCT test_id) FROM test_submissions WHERE user_id = ? AND finished_at IS NOT NULL");
$stmt->execute([$user['id']]);
$doneTests = (int) $stmt->fetchColumn();

function render_course_objectives(?string $objectives): void {
    $objectives = trim((string) $objectives);
    if ($objectives === '') {
        return;
    }

    $items = array_values(array_filter(
        array_map('trim', preg_split('/(?:^|\R|\s*)[•]\s*/u', $objectives) ?: []),
        static fn(string $item): bool => $item !== ''
    ));

    if (count($items) < 2) {
        echo '<p class="course-section-text">' . htmlspecialchars($objectives) . '</p>';
        return;
    }

    echo '<ul class="course-objectives-list">';
    foreach ($items as $item) {
        echo '<li>' . htmlspecialchars($item) . '</li>';
    }
    echo '</ul>';
}

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
    <div class="stat-value"><?= $doneAssignments ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🧪</div>
    <div class="stat-label"><?= __('tests_done') ?></div>
    <div class="stat-value"><?= $doneTests ?></div>
  </div>
</div>

<div class="card mb-5 about-course-card">
  <div class="card-title">📋 <?= __('about_course') ?></div>
  <p class="course-section-text course-description"><?= htmlspecialchars($course['description'] ?? '') ?></p>

  <div class="course-info-grid">
    <section class="course-info-section">
      <div class="course-section-title">
        🎯 <?= __('course_goals') ?>
      </div>
      <p class="course-section-text"><?= htmlspecialchars($course['goals'] ?? '') ?></p>
    </section>
    <section class="course-info-section">
      <div class="course-section-title">
        📌 <?= __('course_objectives') ?>
      </div>
      <?php render_course_objectives($course['objectives'] ?? null); ?>
    </section>
  </div>

  <?php if (!empty($course['content_info'])): ?>
  <section class="course-info-section course-learning-section">
    <div class="course-section-title">
      📚 <?= __('learning_content') ?>
    </div>
    <p class="course-section-text"><?= htmlspecialchars($course['content_info']) ?></p>
  </section>
  <?php endif; ?>
</div>

<?php if (!empty($courseDocuments)): ?>
<div class="card mb-5">
  <div class="card-title">📄 <?= __('course_documents') ?></div>
  <div class="flex gap-3 flex-wrap">
    <?php foreach ($courseDocuments as $document): ?>
      <a href="<?= htmlspecialchars($document['url']) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
        <?= htmlspecialchars($document['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">📅 <?= __('course_structure') ?></div>
  <?php if (!empty($weeks)): ?>
    <?php foreach ($weeks as $week): ?>
    <div class="week-block">
      <div class="week-header forum-week-header justify-between">
        <div class="week-title">
          <div class="week-num"><?= $week['number'] ?></div>
          <?= htmlspecialchars(format_week_title($week['title'])) ?>
        </div>
        <div class="forum-week-actions">
          <span class="text-xs text-muted">
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
    <p class="empty-state"><?= __('no_data') ?></p>
  <?php endif; ?>
</div>

<div class="flex gap-4 mt-5">
  <a href="index.php?route=materials" class="btn btn-primary">📖 <?= __('materials') ?></a>
  <a href="index.php?route=assignments" class="btn btn-secondary">📝 <?= __('assignments') ?></a>
  <a href="index.php?route=tests" class="btn btn-secondary">🧪 <?= __('tests') ?></a>
  <a href="index.php?route=grades" class="btn btn-secondary">🏆 <?= __('grades') ?></a>
</div>

<?php include 'footer.php'; ?>
