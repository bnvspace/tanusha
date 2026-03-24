<?php
// pages/admin/add_assignment.php
require_once 'config.php';
require_once 'auth.php';

$user = login_required(['teacher', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $week_id = intval($_POST['week_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $open_date = !empty($_POST['open_date']) ? $_POST['open_date'] : null;
    $visible = intval($_POST['visible']);
    
    $stmt = $db->prepare("INSERT INTO assignments (week_id, title, description, deadline, open_date, visible) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$week_id, $title, $description, $deadline, $open_date, $visible]);
    
    set_flash('Задание успешно добавлено!', 'success');
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = 'Добавить задание';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📝 Добавить задание</h1>
  </div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm">← Назад</a>
</div>

<div class="card">
  <form method="POST">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Неделя</label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>">Неделя <?= $week['number'] ?>: <?= htmlspecialchars($week['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Видимость</label>
        <select name="visible" class="form-control">
          <option value="1">Видимо студентам</option>
          <option value="0">Скрыто</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Название задания</label>
      <input type="text" name="title" class="form-control" placeholder="Например: Перевод технического текста" required>
    </div>
    <div class="form-group">
      <label class="form-label">Описание задания</label>
      <textarea name="description" class="form-control" rows="6"
        placeholder="Подробное описание задания, инструкции, требования..."></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Дедлайн</label>
        <input type="datetime-local" name="deadline" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label">Дата открытия</label>
        <input type="datetime-local" name="open_date" class="form-control">
        <div class="form-hint">Оставьте пустым — доступно сразу</div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">✅ Добавить задание</button>
  </form>
</div>

<?php include 'footer.php'; ?>
