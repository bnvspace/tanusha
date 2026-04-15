<?php
// pages/admin/add_assignment.php



$user = login_required(['teacher', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $week_id = intval($_POST['week_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $open_date = !empty($_POST['open_date']) ? $_POST['open_date'] : null;
    $visible = intval($_POST['visible']);
    
    $stmt = $db->prepare("INSERT INTO assignments (week_id, title, description, deadline, open_date, visible) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$week_id, $title, $description, $deadline, $open_date, $visible]);
    
    set_flash(__('assignment_added_success'), 'success');
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
normalize_course_week_numbers($db, (int) $course['id']);
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = __('add_assignment_title');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📝 <?= __('add_assignment_title') ?></h1>
  </div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm"><?= __('back_btn') ?></a>
</div>

<div class="card">
  <form method="POST">
    <?= csrf_input() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label"><?= __('week') ?></label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>"><?= __('week') ?> <?= $week['number'] ?>: <?= htmlspecialchars(format_week_title($week['title'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('visibility') ?></label>
        <select name="visible" class="form-control">
          <option value="1"><?= __('visible_to_students') ?></option>
          <option value="0"><?= __('hidden') ?></option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('assignment_name') ?></label>
      <input type="text" name="title" class="form-control" placeholder="<?= __('assignment_name_placeholder') ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('assignment_description') ?></label>
      <textarea name="description" class="form-control" rows="6"
        placeholder="<?= __('assignment_desc_placeholder') ?>"></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label"><?= __('deadline') ?></label>
        <input type="datetime-local" name="deadline" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('open_date_lbl') ?></label>
        <input type="datetime-local" name="open_date" class="form-control">
        <div class="form-hint"><?= __('leave_empty_for_immediate') ?></div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">✅ <?= __('add_assignment_title') ?></button>
  </form>
</div>

<?php include 'footer.php'; ?>
