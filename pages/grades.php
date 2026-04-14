<?php
// pages/grades.php



$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

normalize_course_week_numbers($db, (int) $course['id']);
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$rows = [];
$total_grade = 0;
$total_count = 0;

foreach ($weeks as $week) {
    $stmt = $db->prepare("SELECT * FROM assignments WHERE week_id = ? AND visible = 1 ORDER BY created_at");
    $stmt->execute([$week['id']]);
    $assignments = $stmt->fetchAll();
    foreach ($assignments as $a) {
        $stmt_sub = $db->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ?");
        $stmt_sub->execute([$a['id'], $user['id']]);
        $sub = $stmt_sub->fetch();
        
        $rows[] = [
            'type' => 'assignment',
            'week' => $week,
            'item' => $a,
            'submission' => $sub
        ];
        
        if ($sub && $sub['grade'] !== null) {
            $total_grade += $sub['grade'];
            $total_count++;
        }
    }
    
    $stmt = $db->prepare("SELECT * FROM tests WHERE week_id = ? AND visible = 1 ORDER BY created_at");
    $stmt->execute([$week['id']]);
    $tests = $stmt->fetchAll();
    foreach ($tests as $t) {
        $stmt_tsub = $db->prepare("SELECT * FROM test_submissions WHERE test_id = ? AND user_id = ? AND finished_at IS NOT NULL ORDER BY id DESC LIMIT 1");
        $stmt_tsub->execute([$t['id'], $user['id']]);
        $tsub = $stmt_tsub->fetch();
        
        $rows[] = [
            'type' => 'test',
            'week' => $week,
            'item' => $t,
            'tsub' => $tsub
        ];
        
        if ($tsub && $tsub['max_score'] > 0) {
            $total_grade += intval($tsub['score'] / $tsub['max_score'] * 100);
            $total_count++;
        }
    }
}

$avg = $total_count > 0 ? round($total_grade / $total_count) : 0;

$total_items = count($rows);
$done_items = 0;
foreach ($rows as $row) {
    if ($row['type'] == 'assignment' && $row['submission']) $done_items++;
    if ($row['type'] == 'test' && $row['tsub']) $done_items++;
}
$pct = $total_items > 0 ? intval($done_items / $total_items * 100) : 0;

$page_title = __('my_grades');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>🏆 <?= __('my_grades') ?></h1>
    <div class="breadcrumb"><?= __('course_progress') ?></div>
  </div>
</div>

<div class="stats-grid mb-5">
  <div class="stat-card">
    <div class="stat-icon">📊</div>
    <div class="stat-label"><?= __('average_score') ?></div>
    <div class="stat-value <?= $avg >= 75 ? 'text-success' : ($avg >= 50 ? 'text-warning' : 'text-destructive') ?>">
      <?= $avg ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label"><?= __('reviewed_assignments') ?></div>
    <div class="stat-value">
        <?php 
        $reviewed_count = 0;
        foreach($rows as $r) if($r['type'] == 'assignment' && $r['submission'] && $r['submission']['status'] != 'pending') $reviewed_count++;
        echo $reviewed_count;
        ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🧪</div>
    <div class="stat-label"><?= __('tests_passed') ?></div>
    <div class="stat-value">
        <?php 
        $tests_count = 0;
        foreach($rows as $r) if($r['type'] == 'test' && $r['tsub']) $tests_count++;
        echo $tests_count;
        ?>
    </div>
  </div>
</div>

<div class="card mb-5">
  <div class="flex justify-between mb-2">
    <span class="font-semibold"><?= __('total_course_progress') ?></span>
    <span class="font-extrabold text-primary"><?= $pct ?>%</span>
  </div>
  <div class="progress-wrap">
    <div class="progress-bar" style="width:<?= $pct ?>%"></div>
  </div>
  <div class="progress-label"><?= $done_items ?> <?= __('out_of') ?> <?= $total_items ?> <?= __('items_completed') ?></div>
</div>

<div class="card">
  <div class="card-title">📋 <?= __('detailed_results') ?></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= __('week') ?></th>
          <th><?= __('item_title') ?? __('title') ?? 'Название' ?></th>
          <th><?= __('item_type') ?? __('type') ?? 'Тип' ?></th>
          <th><?= __('item_status') ?? __('status') ?? 'Статус' ?></th>
          <th><?= __('item_grade') ?? __('grade') ?? 'Оценка' ?></th>
          <th><?= __('item_comment') ?? __('comment') ?? 'Комментарий' ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <span class="font-bold text-primary"><?= $row['week']['number'] ?></span>
          </td>
          <td class="font-semibold">
            <?php if ($row['type'] == 'assignment'): ?>
              <a href="index.php?route=assignment_detail&aid=<?= $row['item']['id'] ?>" style="color:inherit">
                <?= htmlspecialchars($row['item']['title']) ?>
              </a>
            <?php else: ?>
                <?= htmlspecialchars($row['item']['title']) ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($row['type'] == 'assignment'): ?>
              <span class="badge badge-info">📝 <?= __('assignment_type') ?></span>
            <?php else: ?>
              <span class="badge badge-secondary">🧪 <?= __('test_type') ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($row['type'] == 'assignment'): ?>
              <?php if ($row['submission']): ?>
                <?php if ($row['submission']['status'] == 'pending'): ?>
                  <span class="badge badge-pending">⏳ <?= __('pending') ?></span>
                <?php elseif ($row['submission']['status'] == 'reviewed'): ?>
                  <span class="badge badge-reviewed">✅ <?= __('reviewed') ?></span>
                <?php else: ?>
                  <span class="badge badge-revision">🔄 <?= __('revision') ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-secondary"><?= __('not_submitted') ?></span>
              <?php endif; ?>
            <?php else: ?>
              <?php if ($row['tsub']): ?>
                <span class="badge badge-reviewed">✅ <?= __('passed') ?></span>
              <?php else: ?>
                <span class="badge badge-secondary"><?= __('not_passed') ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <?php if ($row['type'] == 'assignment' && $row['submission'] && $row['submission']['grade'] !== null): ?>
              <span class="score-display <?= $row['submission']['grade'] >= 75 ? 'score-high' : ($row['submission']['grade'] >= 50 ? 'score-mid' : 'score-low') ?>">
                <?= $row['submission']['grade'] ?>/100
              </span>
            <?php elseif ($row['type'] == 'test' && $row['tsub']): 
                $pct_t = ($row['tsub']['max_score'] > 0) ? intval($row['tsub']['score'] / $row['tsub']['max_score'] * 100) : 0;
            ?>
              <span class="score-display <?= $pct_t >= 75 ? 'score-high' : ($pct_t >= 50 ? 'score-mid' : 'score-low') ?>">
                <?= $row['tsub']['score'] ?>/<?= $row['tsub']['max_score'] ?>
              </span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td class="text-xs text-muted max-w-xs">
            <?php if ($row['type'] == 'assignment' && $row['submission'] && !empty($row['submission']['comment'])): ?>
              <?= htmlspecialchars(mb_substr($row['submission']['comment'], 0, 80)) . (mb_strlen($row['submission']['comment']) > 80 ? '...' : '') ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr>
          <td colspan="6" class="empty-state"><?= __('no_items') ?></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
