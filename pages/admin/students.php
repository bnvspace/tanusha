<?php
// pages/admin/students.php
require_once 'config.php';
require_once 'auth.php';

$user = login_required(['teacher', 'admin']);

$stmt = $db->prepare("SELECT * FROM users WHERE role = 'student' ORDER BY created_at DESC");
$stmt->execute();
$students = $stmt->fetchAll();

$page_title = 'Студенты';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>👥 Студенты</h1>
    <div class="breadcrumb">Список зарегистрированных студентов</div>
  </div>
  <?php if ($user['role'] == 'admin'): ?>
  <a href="index.php?route=admin_users" class="btn btn-primary btn-sm">+ Добавить</a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>ФИО</th>
          <th>Логин</th>
          <th>Email</th>
          <th>Регистрация</th>
          <th>Статус</th>
          <th>Действия</th>
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
              <?= $s['is_active'] ? 'Активен' : 'Заблокирован' ?>
            </span>
          </td>
          <td>
            <a href="index.php?route=admin_student_detail&uid=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Профиль</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Студентов нет</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
