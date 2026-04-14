<?php
// pages/admin/edit_assignment.php



$user = login_required(['teacher', 'admin']);

$aid = $_GET['aid'] ?? null;
if (!$aid) {
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->prepare("SELECT * FROM assignments WHERE id = ?");
$stmt->execute([$aid]);
$assignment = $stmt->fetch();

if (!$assignment) {
    die("Задание не найдено.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $week_id = intval($_POST['week_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $open_date = !empty($_POST['open_date']) ? $_POST['open_date'] : null;
    $visible = intval($_POST['visible']);
    
    $stmt = $db->prepare("UPDATE assignments SET week_id = ?, title = ?, description = ?, deadline = ?, open_date = ?, visible = ? WHERE id = ?");
    $stmt->execute([$week_id, $title, $description, $deadline, $open_date, $visible, $aid]);
    
    set_flash(__('assignment_updated_success'), 'success');
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
normalize_course_week_numbers($db, (int) $course['id']);
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = __('edit_assignment_title');
include 'header.php';
?>

<div class="topbar">
  <div><h1>✏️ <?= __('edit_assignment_title') ?></h1></div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm"><?= __('back_btn') ?></a>
</div>

<div class="card">
  <form method="POST">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label"><?= __('week') ?></label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>" <?= $week['id'] == $assignment['week_id'] ? 'selected' : '' ?>><?= __('week') ?> <?= $week['number'] ?>: <?= htmlspecialchars(format_week_title($week['title'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('visibility') ?></label>
        <select name="visible" class="form-control">
          <option value="1" <?= $assignment['visible'] ? 'selected' : '' ?>><?= __('visible_short') ?></option>
          <option value="0" <?= !$assignment['visible'] ? 'selected' : '' ?>><?= __('hidden') ?></option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('item_title') ?></label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($assignment['title']) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('description_lbl') ?></label>
      <textarea name="description" class="form-control" rows="6"><?= htmlspecialchars($assignment['description'] ?? '') ?></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label"><?= __('deadline') ?></label>
        <input type="datetime-local" name="deadline" class="form-control"
          value="<?= $assignment['deadline'] ? date('Y-m-d\TH:i', strtotime($assignment['deadline'])) : '' ?>">
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('open_date_lbl') ?></label>
        <input type="datetime-local" name="open_date" class="form-control"
          value="<?= $assignment['open_date'] ? date('Y-m-d\TH:i', strtotime($assignment['open_date'])) : '' ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">💾 <?= __('save') ?></button>
  </form>
</div>

<?php include 'footer.php'; ?>
