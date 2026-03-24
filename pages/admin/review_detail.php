<?php
// pages/admin/review_detail.php



$user = login_required(['teacher', 'admin']);

$sid = $_GET['sid'] ?? null;
if (!$sid) {
    header("Location: index.php?route=admin_review");
    exit;
}

// Получаем ответ
$stmt = $db->prepare("SELECT s.*, u.full_name as student_name, a.title as assignment_title, a.description as assignment_description
                      FROM submissions s 
                      JOIN users u ON s.user_id = u.id 
                      JOIN assignments a ON s.assignment_id = a.id 
                      WHERE s.id = ?");
$stmt->execute([$sid]);
$stmt->execute([$sid]);
$submission = $stmt->fetch();

if (!$submission) {
    die(__('submission_not_found'));
}

// Обработка оценки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = isset($_POST['grade']) ? intval($_POST['grade']) : null;
    $status = $_POST['status'] ?? 'reviewed';
    $comment = trim($_POST['comment'] ?? '');
    
    $stmt = $db->prepare("UPDATE submissions SET grade = ?, status = ?, comment = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$grade, $status, $comment, $sid]);
    
    set_flash(__('grade_saved'), 'success');
    header("Location: index.php?route=admin_review");
    exit;
}

$page_title = __('review_work_title');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>✏️ <?= __('review_work_title') ?></h1>
    <div class="breadcrumb"><?= htmlspecialchars($submission['student_name']) ?> · <?= htmlspecialchars($submission['assignment_title']) ?></div>
  </div>
  <a href="index.php?route=admin_review" class="btn btn-secondary btn-sm"><?= __('back_btn') ?></a>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-title">📋 <?= __('assignment_info_title') ?></div>
      <div style="font-weight:700;margin-bottom:8px"><?= htmlspecialchars($submission['assignment_title']) ?></div>
      <div style="line-height:1.7;color:var(--text);white-space:pre-line"><?= htmlspecialchars($submission['assignment_description'] ?? __('no_description')) ?></div>
    </div>

    <div class="card">
      <div class="card-title">📤 <?= __('student_answer_title') ?></div>
      <div style="font-size:.82rem;color:var(--muted);margin-bottom:12px">
        <?= __('submitted_lbl') ?>: <?= date('d.m.Y', strtotime($submission['submitted_at'])) ?> <?= __('at_time') ?> <?= date('H:i', strtotime($submission['submitted_at'])) ?>
      </div>
      <?php if ($submission['text_answer']): ?>
      <div style="background:#f4f6fb;border-radius:8px;padding:14px;line-height:1.7;white-space:pre-line;margin-bottom:12px">
        <?= htmlspecialchars($submission['text_answer']) ?>
      </div>
      <?php endif; ?>
      <?php if ($submission['file_path']): ?>
      <a href="/uploads/<?= htmlspecialchars($submission['file_path']) ?>" class="btn btn-secondary" target="_blank">
        📄 <?= __('download_attached_file') ?>
      </a>
      <?php endif; ?>
      <?php if (!$submission['text_answer'] && !$submission['file_path']): ?>
      <p style="color:var(--muted)"><?= __('no_answer_attached') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📊 <?= __('set_grade_title') ?></div>
    <form method="POST">
      <div class="form-group">
        <label class="form-label"><?= __('grade_0_100') ?></label>
        <input type="number" name="grade" class="form-control" min="0" max="100"
          value="<?= htmlspecialchars($submission['grade'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('status') ?></label>
        <select name="status" class="form-control">
          <option value="reviewed" <?= $submission['status'] == 'reviewed' ? 'selected' : '' ?>>✅ <?= __('reviewed') ?></option>
          <option value="revision" <?= $submission['status'] == 'revision' ? 'selected' : '' ?>>🔄 <?= __('needs_revision') ?></option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('comment') ?></label>
        <textarea name="comment" class="form-control" rows="5"
          placeholder="<?= __('comment_placeholder') ?>"><?= htmlspecialchars($submission['comment'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        ✅ <?= __('confirm_grade_btn') ?>
      </button>
    </form>

    <?php if ($submission['status'] != 'pending'): ?>
    <div style="margin-top:14px;padding:12px;background:#d1e7dd;border-radius:8px;font-size:.82rem;color:#0f5132">
      <?= __('grade_already_set') ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
