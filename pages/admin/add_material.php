<?php
// pages/admin/add_material.php



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
    
    set_flash(__('material_added_success'), 'success');
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = __('add_material_title');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📎 <?= __('add_material_title') ?></h1>
    <div class="breadcrumb"><?= __('learning_content_subtitle') ?></div>
  </div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm"><?= __('back_btn') ?></a>
</div>

<div class="card">
  <form method="POST" enctype="multipart/form-data">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label"><?= __('week') ?></label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>"><?= __('week') ?> <?= $week['number'] ?>: <?= htmlspecialchars($week['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('material_type') ?></label>
        <select name="material_type" class="form-control" id="type-select" onchange="updateFields()">
          <option value="text">📝 <?= __('material_text') ?></option>
          <option value="file">📄 <?= __('material_file') ?></option>
          <option value="video">🎬 <?= __('material_video') ?></option>
          <option value="audio">🎵 <?= __('material_audio') ?></option>
          <option value="link">🔗 <?= __('material_link') ?></option>
          <option value="interactive">🎯 <?= __('material_interactive') ?></option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('title') ?></label>
      <input type="text" name="title" class="form-control" placeholder="<?= __('material_name_placeholder') ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('content_description') ?></label>
      <textarea name="content" class="form-control" rows="4" placeholder="<?= __('material_desc_placeholder') ?>"></textarea>
    </div>
    <div class="form-group" id="url-group">
      <label class="form-label"><?= __('url_lbl') ?></label>
      <input type="url" name="url" class="form-control" placeholder="https://...">
    </div>
    <div class="form-group" id="file-group">
      <label class="form-label"><?= __('attach_file') ?></label>
      <input type="file" name="file" class="form-control">
      <div class="form-hint"><?= __('file_hint') ?></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label"><?= __('open_date_lbl') ?></label>
        <input type="datetime-local" name="open_date" class="form-control">
        <div class="form-hint"><?= __('leave_empty_for_immediate') ?></div>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('visibility') ?></label>
        <select name="visible" class="form-control">
          <option value="1"><?= __('visible_to_students') ?></option>
          <option value="0"><?= __('hidden') ?></option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">✅ <?= __('add_material_title') ?></button>
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
