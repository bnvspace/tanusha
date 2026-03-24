<?php
// pages/admin/review.php
require_once 'config.php';
require_once 'auth.php';

$user = login_required(['teacher', 'admin']);

// Ожидают проверки
$stmt = $db->prepare("SELECT s.*, u.full_name as student_name, a.title as assignment_title, w.number as week_number 
                      FROM submissions s 
                      JOIN users u ON s.user_id = u.id 
                      JOIN assignments a ON s.assignment_id = a.id 
                      JOIN weeks w ON a.week_id = w.id 
                      WHERE s.status = 'pending' 
                      ORDER BY s.submitted_at ASC");
$stmt->execute();
$pending = $stmt->fetchAll();

// Последние проверенные
$stmt = $db->prepare("SELECT s.*, u.full_name as student_name, a.title as assignment_title 
                      FROM submissions s 
                      JOIN users u ON s.user_id = u.id 
                      JOIN assignments a ON s.assignment_id = a.id 
                      WHERE s.status IN ('reviewed', 'revision') 
                      ORDER BY s.reviewed_at DESC LIMIT 20");
$stmt->execute();
$reviewed = $stmt->fetchAll();

$page_title = 'Проверка заданий';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>✏️ Проверка заданий</h1>
    <div class="breadcrumb"><?= count($pending) ?> ожидают проверки</div>
  </div>
</div>

<?php if (!empty($pending)): ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-title" style="color:var(--danger)">⏳ Ожидают проверки (<?= count($pending) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Студент</th><th>Задание</th><th>Неделя</th><th>Сдано</th><th>Файл</th><th>Действие</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pending as $sub): ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($sub['student_name']) ?></td>
          <td><?= htmlspecialchars($sub['assignment_title']) ?></td>
          <td style="color:var(--muted)"><?= $sub['week_number'] ?></td>
          <td style="font-size:.82rem;color:var(--muted)"><?= date('d.m.Y H:i', strtotime($sub['submitted_at'])) ?></td>
          <td>
            <?php if ($sub['file_path']): ?>
            <a href="/uploads/<?= htmlspecialchars($sub['file_path']) ?>" class="btn btn-secondary btn-sm" target="_blank">📄 Файл</a>
            <?php else: ?><span style="color:var(--muted);font-size:.82rem">Текст</span><?php endif; ?>
          </td>
          <td>
            <a href="index.php?route=admin_review_detail&sid=<?= $sub['id'] ?>" class="btn btn-primary btn-sm">Проверить</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:20px">
  <p style="text-align:center;padding:30px;color:var(--muted)">✅ Все задания проверены!</p>
</div>
<?php endif; ?>

<?php if (!empty($reviewed)): ?>
<div class="card">
  <div class="card-title">✅ Последние проверенные</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Студент</th><th>Задание</th><th>Статус</th><th>Оценка</th><th>Проверено</th></tr>
      </thead>
      <tbody>
        <?php foreach ($reviewed as $sub): ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($sub['student_name']) ?></td>
          <td><?= htmlspecialchars($sub['assignment_title']) ?></td>
          <td>
            <?php if ($sub['status'] == 'reviewed'): ?><span class="badge badge-reviewed">✅</span>
            <?php else: ?><span class="badge badge-revision">🔄</span><?php endif; ?>
          </td>
          <td style="font-weight:700;text-align:center"><?= $sub['grade'] !== null ? $sub['grade'] : '—' ?></td>
          <td style="font-size:.82rem;color:var(--muted)">
            <?= $sub['reviewed_at'] ? date('d.m.Y H:i', strtotime($sub['reviewed_at'])) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>
