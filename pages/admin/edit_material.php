<?php
// pages/admin/edit_material.php



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
    require_csrf_token();
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
    
    set_flash(__('material_updated_success'), 'success');
    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
normalize_course_week_numbers($db, (int) $course['id']);
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = __('edit_material_title');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>✏️ <?= __('edit_material_title') ?></h1>
  </div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm"><?= __('back_btn') ?></a>
</div>

<div class="card">
  <form method="POST" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label"><?= __('week') ?></label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>" <?= $week['id'] == $material['week_id'] ? 'selected' : '' ?>><?= __('week') ?> <?= $week['number'] ?>: <?= htmlspecialchars(format_week_title($week['title'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('material_type') ?></label>
        <select name="material_type" class="form-control">
          <?php
          $types = [
            'text' => __('material_text'),
            'file' => __('material_file'),
            'video' => __('material_video'),
            'audio' => __('material_audio'),
            'link' => __('material_link'),
            'interactive' => __('material_interactive')
          ];
          foreach ($types as $t_val => $t_label): ?>
          <option value="<?= $t_val ?>" <?= $t_val == $material['material_type'] ? 'selected' : '' ?>><?= $t_label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('item_title') ?></label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($material['title']) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('content_description') ?></label>
      <textarea name="content" class="form-control" rows="4"><?= htmlspecialchars($material['content'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">URL</label>
      <input type="url" name="url" class="form-control" value="<?= htmlspecialchars($material['url'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('replace_file') ?></label>
      <input type="file" name="file" class="form-control">
      <?php if ($material['file_path']): ?>
      <div class="form-hint"><?= __('current_file') ?> <a href="/uploads/<?= htmlspecialchars($material['file_path']) ?>" target="_blank"><?= __('download') ?></a></div>
      <?php endif; ?>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label"><?= __('open_date_lbl') ?></label>
        <input type="datetime-local" name="open_date" class="form-control"
          value="<?= $material['open_date'] ? date('Y-m-d\TH:i', strtotime($material['open_date'])) : '' ?>">
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('visibility') ?></label>
        <select name="visible" class="form-control">
          <option value="1" <?= $material['visible'] ? 'selected' : '' ?>><?= __('visible_short') ?></option>
          <option value="0" <?= !$material['visible'] ? 'selected' : '' ?>><?= __('hidden') ?></option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">💾 <?= __('save') ?></button>
  </form>
</div>

<?php include 'footer.php'; ?>
