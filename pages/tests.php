<?php
// pages/tests.php



$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$items = [];
$now = new DateTime('now', new DateTimeZone('UTC'));

foreach ($weeks as $week) {
    $stmt = $db->prepare("SELECT * FROM tests WHERE week_id = ? AND visible = 1");
    $stmt->execute([$week['id']]);
    $week_tests = $stmt->fetchAll();
    
    foreach ($week_tests as $t) {
        if ($t['open_date'] && new DateTime($t['open_date'], new DateTimeZone('UTC')) > $now) {
            continue;
        }
        
        $stmt_sub = $db->prepare("SELECT * FROM test_submissions WHERE test_id = ? AND user_id = ? AND finished_at IS NOT NULL ORDER BY id DESC LIMIT 1");
        $stmt_sub->execute([$t['id'], $user['id']]);
        $tsub = $stmt_sub->fetch();
        
        $stmt_q = $db->prepare("SELECT COUNT(*) FROM test_questions WHERE test_id = ?");
        $stmt_q->execute([$t['id']]);
        $q_count = $stmt_q->fetchColumn();
        
        $items[] = [
            'test' => $t,
            'week' => $week,
            'tsub' => $tsub,
            'q_count' => $q_count
        ];
    }
}

$page_title = __('tests');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>🧪 <?= __('tests') ?></h1>
    <div class="breadcrumb"><?= __('available_tests') ?></div>
  </div>
</div>

<?php if (!empty($items)): ?>
<div class="card">
  <?php foreach ($items as $index => $row): 
      $t = $row['test'];
      $tsub = $row['tsub'];
      $is_last = ($index === count($items) - 1);
  ?>
  <div style="border-bottom:1px solid var(--border);padding:16px 0;<?= $is_last ? 'border-bottom:none;' : '' ?>">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:.75rem;color:var(--muted);margin-bottom:4px"><?= __('week') ?> <?= $row['week']['number'] ?></div>
        <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars($t['title']) ?></div>
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px;display:flex;gap:14px">
          <span>❓ <?= $row['q_count'] ?> <?= __('questions_plural') ?></span>
          <?php if ($t['time_limit']): ?><span>⏱ <?= $t['time_limit'] ?> <?= __('minutes') ?></span><?php endif; ?>
          <?php if ($t['description']): ?><span><?= htmlspecialchars(mb_substr($t['description'], 0, 60)) . (mb_strlen($t['description']) > 60 ? '...' : '') ?></span><?php endif; ?>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <?php if ($tsub): 
            $pct = ($tsub['max_score'] > 0) ? intval($tsub['score'] / $tsub['max_score'] * 100) : 0;
        ?>
           <div style="text-align:center">
             <div class="grade-circle <?= $pct >= 75 ? 'grade-high' : ($pct >= 50 ? 'grade-mid' : 'grade-low') ?>">
               <?= $pct ?>%
             </div>
             <div style="font-size:.72rem;color:var(--muted);margin-top:3px"><?= $tsub['score'] ?>/<?= $tsub['max_score'] ?></div>
           </div>
           <a href="index.php?route=test_result&tid=<?= $t['id'] ?>&sid=<?= $tsub['id'] ?>" class="btn btn-secondary btn-sm"><?= __('test_result_btn') ?></a>
        <?php else: ?>
           <span class="badge badge-secondary"><?= __('not_passed') ?></span>
           <a href="index.php?route=test_take&tid=<?= $t['id'] ?>" class="btn btn-primary btn-sm"><?= __('start_test') ?> →</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card">
  <p style="text-align:center;color:var(--muted);padding:40px"><?= __('no_data') ?></p>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
