<?php
// pages/admin/statistics.php



$user = login_required(['teacher', 'admin']);

// Общая статистика
$total_students = $db->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_submissions = $db->query("SELECT COUNT(*) FROM submissions")->fetchColumn() ?: 0;
$reviewed_submissions = $db->query("SELECT COUNT(*) FROM submissions WHERE status IN ('reviewed', 'revision')")->fetchColumn() ?: 0;
$total_test_subs = $db->query("SELECT COUNT(*) FROM test_submissions WHERE finished_at IS NOT NULL")->fetchColumn() ?: 0;

// Распределение оценок
$grade_dist = [
    '81-100' => 0,
    '61-80'  => 0,
    '41-60'  => 0,
    '21-40'  => 0,
    '0-20'   => 0
];

$stmt = $db->query("SELECT grade FROM submissions WHERE grade IS NOT NULL");
while ($row = $stmt->fetch()) {
    $g = $row['grade'];
    if ($g > 80) $grade_dist['81-100']++;
    elseif ($g > 60) $grade_dist['61-80']++;
    elseif ($g > 40) $grade_dist['41-60']++;
    elseif ($g > 20) $grade_dist['21-40']++;
    else $grade_dist['0-20']++;
}

$total_graded = array_sum($grade_dist);

$page_title = 'Статистика';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📈 Статистика</h1>
    <div class="breadcrumb">Аналитика курса</div>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-label">Студентов</div>
    <div class="stat-value"><?= $total_students ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📝</div>
    <div class="stat-label">Всего ответов</div>
    <div class="stat-value"><?= $total_submissions ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Проверено</div>
    <div class="stat-value"><?= $reviewed_submissions ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🧪</div>
    <div class="stat-label">Тестов пройдено</div>
    <div class="stat-value"><?= $total_test_subs ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <!-- Прогресс проверки -->
  <div class="card">
    <div class="card-title">📊 Прогресс проверки заданий</div>
    <?php $pct = ($total_submissions > 0) ? intval($reviewed_submissions / $total_submissions * 100) : 0; ?>
    <div style="margin-bottom:8px;display:flex;justify-content:space-between">
      <span>Проверено</span>
      <strong><?= $pct ?>%</strong>
    </div>
    <div class="progress-wrap">
      <div class="progress-bar" style="width:<?= $pct ?>%"></div>
    </div>
    <div class="progress-label"><?= $reviewed_submissions ?> из <?= $total_submissions ?> работ проверено</div>
    <?php if ($total_submissions == 0): ?>
    <p style="color:var(--muted);font-size:.88rem;margin-top:12px;text-align:center">Ответов пока нет</p>
    <?php endif; ?>
  </div>

  <!-- Распределение оценок -->
  <div class="card">
    <div class="card-title">🏆 Распределение оценок</div>
    <?php foreach ($grade_dist as $label => $count): 
        $bar_pct = ($total_graded > 0) ? intval($count / $total_graded * 100) : 0;
        $color = 'var(--danger)';
        if ($label == '81-100') $color = 'var(--success)';
        elseif ($label == '61-80') $color = '#5cb85c';
        elseif ($label == '41-60') $color = 'var(--accent)';
        elseif ($label == '21-40') $color = '#e67e22';
    ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:.85rem">
        <span style="font-weight:600"><?= $label ?></span>
        <span style="color:var(--muted)"><?= $count ?> работ (<?= $bar_pct ?>%)</span>
      </div>
      <div class="progress-wrap" style="height:8px">
        <div class="progress-bar" style="width:<?= $bar_pct ?>%; background:<?= $color ?>"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if ($total_graded == 0): ?>
    <p style="color:var(--muted);font-size:.88rem;text-align:center;padding:20px">Оценок пока нет</p>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:20px">
  <div class="card-title">🔗 Полезные ссылки</div>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <a href="index.php?route=admin_review" class="btn btn-primary">✏️ Проверить задания</a>
    <a href="index.php?route=admin_students" class="btn btn-secondary">👥 Список студентов</a>
    <a href="index.php?route=admin_course" class="btn btn-secondary">📚 Курс</a>
  </div>
</div>

<?php include 'footer.php'; ?>
