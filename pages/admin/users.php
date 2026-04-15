<?php
// pages/admin/users.php



$user = login_required(['admin']);

$stmt = $db->query("SELECT * FROM users ORDER BY role, full_name");
$users = $stmt->fetchAll();

$page_title = __('user_management');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>🔑 <?= __('users_title') ?></h1>
    <div class="breadcrumb"><?= __('account_management') ?></div>
  </div>
</div>

<!-- Форма создания -->
<div class="card" style="margin-bottom:20px">
  <div class="card-title">➕ <?= __('new_user') ?></div>
  <form method="POST" action="index.php?route=admin_create_user">
    <?= csrf_input() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label"><?= __('full_name') ?></label>
        <input type="text" name="full_name" class="form-control" placeholder="<?= __('full_name_placeholder') ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label"><?= __('username') ?></label>
        <input type="text" name="username" class="form-control" placeholder="<?= __('username_placeholder') ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label"><?= __('email') ?></label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label"><?= __('password') ?></label>
        <input type="text" name="password" class="form-control" placeholder="<?= __('initial_password') ?>" required>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label"><?= __('role') ?></label>
        <select name="role" class="form-control">
          <option value="student"><?= __('student') ?></option>
          <option value="teacher"><?= __('teacher') ?></option>
          <option value="admin"><?= __('admin') ?></option>
        </select>
      </div>
      <div style="display:flex;align-items:flex-end">
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"><?= __('create_btn') ?></button>
      </div>
    </div>
  </form>
</div>

<!-- Список -->
<div class="card">
  <div class="card-title"><?= __('all_users_title') ?></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th><?= __('full_name') ?></th><th><?= __('username') ?></th><th><?= __('email') ?></th><th><?= __('role') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
        <tr>
          <td style="font-weight:600"><?= htmlspecialchars($u['full_name']) ?></td>
          <td style="color:var(--muted)">@<?= htmlspecialchars($u['username']) ?></td>
          <td style="font-size:.85rem"><?= htmlspecialchars($u['email']) ?></td>
          <td>
            <?php if ($u['role'] == 'admin'): ?><span class="badge badge-info"><?= __('admin') ?></span>
            <?php elseif ($u['role'] == 'teacher'): ?><span class="badge badge-secondary" style="background:#e8f4fd;color:#1a6fc4"><?= __('teacher') ?></span>
            <?php else: ?><span class="badge badge-secondary"><?= __('student') ?></span><?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $u['is_active'] ? 'badge-reviewed' : 'badge-revision' ?>">
              <?= $u['is_active'] ? __('active') : __('blocked') ?>
            </span>
          </td>
          <td>
            <?php if ($u['id'] != $user['id']): ?>
            <form method="POST" action="index.php?route=admin_toggle_user" style="display:inline" onsubmit="return confirm('<?= __('are_you_sure') ?>')">
              <?= csrf_input() ?>
              <input type="hidden" name="uid" value="<?= $u['id'] ?>">
              <button type="submit" class="btn <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?> btn-sm">
                <?= $u['is_active'] ? __('block_btn') : __('unblock_btn') ?>
              </button>
            </form>
            <?php else: ?>
            <span style="font-size:.8rem;color:var(--muted)"><?= __('its_you') ?></span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
