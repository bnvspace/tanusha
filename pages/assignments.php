<?php
// pages/assignments.php



$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

normalize_course_week_numbers($db, (int) $course['id']);
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$items = [];
$now = new DateTime('now', new DateTimeZone('UTC'));

foreach ($weeks as $week) {
    $stmt = $db->prepare("SELECT * FROM assignments WHERE week_id = ? AND visible = 1");
    $stmt->execute([$week['id']]);
    $week_assignments = $stmt->fetchAll();
    
    foreach ($week_assignments as $a) {
        if ($a['open_date'] && new DateTime($a['open_date'], new DateTimeZone('UTC')) > $now) {
            continue;
        }
        
        $stmt_sub = $db->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ?");
        $stmt_sub->execute([$a['id'], $user['id']]);
        $sub = $stmt_sub->fetch();
        
        $items[] = [
            'assignment' => $a,
            'week' => $week,
            'submission' => $sub
        ];
    }
}

$page_title = __('assignments');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📝 <?= __('assignments') ?></h1>
    <div class="breadcrumb"><?= __('all_assignments') ?></div>
  </div>
</div>

<?php if (!empty($items)): ?>
<div class="card">
  <?php foreach ($items as $index => $row): 
      $a = $row['assignment'];
      $sub = $row['submission'];
      $week = $row['week'];
      $is_last = ($index === count($items) - 1);
      $deadline = $a['deadline'] ? new DateTime($a['deadline']) : null;
      $is_overdue = ($deadline && $now > $deadline);
  ?>
  <div style="border-bottom:1px solid var(--border);padding:16px 0;<?= $is_last ? 'border-bottom:none;' : '' ?>">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-size:.75rem;color:var(--muted);margin-bottom:4px"><?= __('week') ?> <?= $week['number'] ?></div>
        <div style="font-weight:700;font-size:1rem"><?= htmlspecialchars($a['title']) ?></div>
        <?php if ($deadline): ?>
        <div style="font-size:.8rem;color:var(--muted);margin-top:3px">
          ⏰ <?= __('due_date') ?>: <?= $deadline->format('d.m.Y H:i') ?>
          <?php if ($is_overdue): ?>
            <span class="badge badge-revision" style="margin-left:6px"><?= __('overdue') ?></span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <?php if ($sub): ?>
          <?php if ($sub['status'] == 'pending'): ?>
            <span class="badge badge-pending">⏳ <?= __('pending') ?></span>
          <?php elseif ($sub['status'] == 'reviewed'): ?>
            <span class="badge badge-reviewed">✅ <?= __('reviewed') ?></span>
            <?php if ($sub['grade'] !== null): ?>
            <span style="font-weight:800;font-size:1.1rem;color:var(--primary)"><?= $sub['grade'] ?>/100</span>
            <?php endif; ?>
          <?php elseif ($sub['status'] == 'revision'): ?>
            <span class="badge badge-revision">🔄 <?= __('revision') ?></span>
          <?php endif; ?>
        <?php else: ?>
          <span class="badge badge-secondary"><?= __('not_submitted') ?></span>
        <?php endif; ?>
        <a href="index.php?route=assignment_detail&aid=<?= $a['id'] ?>" class="btn btn-primary btn-sm">
          <?= !$sub ? __('open') : __('details') ?>
        </a>
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
