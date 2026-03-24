<?php
// pages/admin/students.php



$user = login_required(['teacher', 'admin']);

$stmt = $db->prepare("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC");
$stmt->execute();
$students = $stmt->fetchAll();

$page_title = __('students');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>👥 <?= __('students') ?></h1>
    <div class="breadcrumb"><?= __('students_list_desc') ?></div>
  </div>
  <?php if ($user['role'] == 'admin'): ?>
  <a href="index.php?route=admin_users" class="btn btn-primary btn-sm"><?= __('add_btn') ?></a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th><?= __('full_name') ?></th>
          <th><?= __('username') ?></th>
          <th><?= __('email') ?></th>
          <th><?= __('registered_at') ?></th>
          <th><?= __('status') ?></th>
          <th><?= __('actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $index => $s): ?>
        <tr>
          <td style="color:var(--muted);font-size:.82rem"><?= $index + 1 ?></td>
          <td style="font-weight:600"><?= htmlspecialchars($s['full_name']) ?></td>
          <td style="color:var(--muted)">@<?= htmlspecialchars($s['username']) ?></td>
          <td style="font-size:.85rem"><?= htmlspecialchars($s['email'] ?? '') ?></td>
          <td style="font-size:.82rem;color:var(--muted)"><?= date('d.m.Y', strtotime($s['created_at'])) ?></td>
          <td>
            <span class="badge <?= $s['is_active'] ? 'badge-reviewed' : 'badge-revision' ?>">
              <?= $s['is_active'] ? __('active') : __('blocked') ?>
            </span>
          </td>
          <td>
            <a href="index.php?route=admin_student_detail&uid=<?= $s['id'] ?>" class="btn btn-secondary btn-sm"><?= __('profile_btn') ?></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px"><?= __('no_students') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
