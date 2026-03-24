<?php
// pages/admin/users.php
require_once 'config.php';
require_once 'auth.php';

$user = login_required(['admin']);

$stmt = $db->query("SELECT * FROM users ORDER BY role, full_name");
$users = $stmt->fetchAll();

$page_title = 'Управление пользователями';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>🔑 Пользователи</h1>
    <div class="breadcrumb">Управление учётными записями</div>
  </div>
</div>

<!-- Форма создания -->
<div class="card" style="margin-bottom:20px">
  <div class="card-title">➕ Новый пользователь</div>
  <form method="POST" action="index.php?route=admin_create_user">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">ФИО</label>
        <input type="text" name="full_name" class="form-control" placeholder="Иванов Иван Иванович" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Логин</label>
        <input type="text" name="username" class="form-control" placeholder="ivanov" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Пароль</label>
        <input type="text" name="password" class="form-control" placeholder="Исходный пароль" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Роль</label>
        <select name="role" class="form-control">
          <option value="student">Студент</option>
          <option value="teacher">Преподаватель</option>
          <option value="admin">Администратор</option>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end">
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Создать</button>
      </div>
    </div>
  </form>
</div>

<!-- Список -->
<div class="card">
  <div class="card-title">Все пользователи</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>ФИО</th><th>Логин</th><th>Email</th><th>Роль</th><th>Статус</th><th>Действия</th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($u['full_name']) ?></td>
          <td style="color:var(--muted)">@<?= htmlspecialchars($u['username']) ?></td>
          <td style="font-size:.85rem"><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?php if ($u['role'] == 'admin'): ?><span class="badge badge-info">Администратор</span>
            <?php elseif ($u['role'] == 'teacher'): ?><span class="badge badge-secondary" style="background:#e8f4fd;color:#1a6fc4">Преподаватель</span>
            <?php else: ?><span class="badge badge-secondary">Студент</span><?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $u['is_active'] ? 'badge-reviewed' : 'badge-revision' ?>">
              <?= $u['is_active'] ? 'Активен' : 'Заблокирован' ?>
            </span>
          </td>
          <td>
            <?php if ($u['id'] != $user['id']): ?>
            <a href="index.php?route=admin_toggle_user&uid=<?= $u['id'] ?>" class="btn <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm"
               onclick="return confirm('Уверены?')">
              <?= $u['is_active'] ? 'Заблокировать' : 'Разблокировать' ?>
            </a>
            <?php else: ?>
            <span style="font-size:.8rem;color:var(--muted)">Это вы</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
