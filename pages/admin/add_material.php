<?php
// pages/admin/add_material.php
require_once 'config.php';
require_once 'auth.php';

$user = login_required(['teacher', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $week_id = intval($_POST['week_id']);
    $title = trim($_POST['title']);
    $material_type = $_POST['material_type'];
    $content = trim($_POST['content'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $visible = intval($_POST['visible']);
    $open_date = !empty($_POST['open_date']) ? $_POST['open_date'] : null;
    
    $file_path = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $original_filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $_FILES['file']['name']);
        $filename = date('YmdHis') . '_' . $original_filename;
        if (move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_DIR . $filename)) {
            $file_path = $filename;
        }
    }
    
    $stmt = $db->prepare("INSERT INTO materials (week_id, material_type, title, content, url, file_path, open_date, visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$week_id, $material_type, $title, $content, $url, $file_path, $open_date, $visible]);
    
    set_flash('Материал успешно добавлен!', 'success');
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = 'Добавить материал';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📎 Добавить материал</h1>
    <div class="breadcrumb">Учебный контент для студентов</div>
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
          <option value="<?= $week['id'] ?>">Неделя <?= $week['number'] ?>: <?= htmlspecialchars($week['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Тип материала</label>
        <select name="material_type" class="form-control" id="type-select" onchange="updateFields()">
          <option value="text">📝 Текст</option>
          <option value="file">📄 Файл</option>
          <option value="video">🎬 Видео</option>
          <option value="audio">🎵 Аудио</option>
          <option value="link">🔗 Ссылка</option>
          <option value="interactive">🎯 Интерактивное задание</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Название</label>
      <input type="text" name="title" class="form-control" placeholder="Например: Лексика по теме «Теплообмен»" required>
    </div>
    <div class="form-group">
      <label class="form-label">Содержание / описание</label>
      <textarea name="content" class="form-control" rows="4" placeholder="Текст материала или описание..."></textarea>
    </div>
    <div class="form-group" id="url-group">
      <label class="form-label">URL (для ссылок и видео)</label>
      <input type="url" name="url" class="form-control" placeholder="https://...">
    </div>
    <div class="form-group" id="file-group">
      <label class="form-label">Прикрепить файл</label>
      <input type="file" name="file" class="form-control">
      <div class="form-hint">PDF, Word, PPT, аудио, видео, изображения, ZIP. До 50 МБ.</div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Дата открытия</label>
        <input type="datetime-local" name="open_date" class="form-control">
        <div class="form-hint">Оставьте пустым — доступно сразу</div>
      </div>
      <div class="form-group">
        <label class="form-label">Видимость</label>
        <select name="visible" class="form-control">
          <option value="1">Видимо студентам</option>
          <option value="0">Скрыто</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">✅ Добавить материал</button>
  </form>
</div>

<script>
function updateFields() {
  const t = document.getElementById('type-select').value;
  document.getElementById('url-group').style.display = ['link','video'].includes(t) ? '' : 'none';
  document.getElementById('file-group').style.display = ['file','audio','interactive'].includes(t) ? '' : 'none';
}
updateFields();
</script>

<?php include 'footer.php'; ?>
