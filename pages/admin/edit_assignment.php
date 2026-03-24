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
    
    set_flash('Задание обновлено!', 'success');
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = 'Редактировать задание';
include 'header.php';
?>

<div class="topbar">
  <div><h1>✏️ Редактировать задание</h1></div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm">← Назад</a>
</div>

<div class="card">
  <form method="POST">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Неделя</label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>" <?= $week['id'] == $assignment['week_id'] ? 'selected' : '' ?>>Неделя <?= $week['number'] ?>: <?= htmlspecialchars($week['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Видимость</label>
        <select name="visible" class="form-control">
          <option value="1" <?= $assignment['visible'] ? 'selected' : '' ?>>Видимо</option>
          <option value="0" <?= !$assignment['visible'] ? 'selected' : '' ?>>Скрыто</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Название</label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($assignment['title']) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label">Описание</label>
      <textarea name="description" class="form-control" rows="6"><?= htmlspecialchars($assignment['description'] ?? '') ?></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Дедлайн</label>
        <input type="datetime-local" name="deadline" class="form-control"
          value="<?= $assignment['deadline'] ? date('Y-m-d\TH:i', strtotime($assignment['deadline'])) : '' ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Дата открытия</label>
        <input type="datetime-local" name="open_date" class="form-control"
          value="<?= $assignment['open_date'] ? date('Y-m-d\TH:i', strtotime($assignment['open_date'])) : '' ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">💾 Сохранить</button>
  </form>
</div>

<?php include 'footer.php'; ?>
