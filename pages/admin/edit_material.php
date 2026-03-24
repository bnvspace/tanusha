<?php
// pages/admin/edit_material.php
require_once 'config.php';
require_once 'auth.php';

$user = login_required(['teacher', 'admin']);

$mid = $_GET['mid'] ?? null;
if (!$mid) {
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->prepare("SELECT * FROM materials WHERE id = ?");
$stmt->execute([$mid]);
$material = $stmt->fetch();

if (!$material) {
    die("Материал не найден.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $week_id = intval($_POST['week_id']);
    $title = trim($_POST['title']);
    $material_type = $_POST['material_type'];
    $content = trim($_POST['content'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $visible = intval($_POST['visible']);
    $open_date = !empty($_POST['open_date']) ? $_POST['open_date'] : null;
    
    $file_path = $material['file_path'];
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $original_filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $_FILES['file']['name']);
        $filename = date('YmdHis') . '_' . $original_filename;
        if (move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_DIR . $filename)) {
            $file_path = $filename;
        }
    }
    
    $stmt = $db->prepare("UPDATE materials SET week_id = ?, material_type = ?, title = ?, content = ?, url = ?, file_path = ?, open_date = ?, visible = ? WHERE id = ?");
    $stmt->execute([$week_id, $material_type, $title, $content, $url, $file_path, $open_date, $visible, $mid]);
    
    set_flash('Материал обновлен!', 'success');
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = 'Редактировать материал';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>✏️ Редактировать материал</h1>
  </div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm">← Назад</a>
</div>

<div class="card">
  <form method="POST" enctype="multipart/form-data">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Неделя</label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>" <?= $week['id'] == $material['week_id'] ? 'selected' : '' ?>>Неделя <?= $week['number'] ?>: <?= htmlspecialchars($week['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Тип материала</label>
        <select name="material_type" class="form-control">
          <?php foreach (['text','file','video','audio','link','interactive'] as $t): ?>
          <option value="<?= $t ?>" <?= $t == $material['material_type'] ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Название</label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($material['title']) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label">Содержание / описание</label>
      <textarea name="content" class="form-control" rows="4"><?= htmlspecialchars($material['content'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">URL</label>
      <input type="url" name="url" class="form-control" value="<?= htmlspecialchars($material['url'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Заменить файл</label>
      <input type="file" name="file" class="form-control">
      <?php if ($material['file_path']): ?>
      <div class="form-hint">Текущий файл: <a href="/uploads/<?= htmlspecialchars($material['file_path']) ?>" target="_blank">скачать</a></div>
      <?php endif; ?>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Дата открытия</label>
        <input type="datetime-local" name="open_date" class="form-control"
          value="<?= $material['open_date'] ? date('Y-m-d\TH:i', strtotime($material['open_date'])) : '' ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Видимость</label>
        <select name="visible" class="form-control">
          <option value="1" <?= $material['visible'] ? 'selected' : '' ?>>Видимо</option>
          <option value="0" <?= !$material['visible'] ? 'selected' : '' ?>>Скрыто</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">💾 Сохранить</button>
  </form>
</div>

<?php include 'footer.php'; ?>
