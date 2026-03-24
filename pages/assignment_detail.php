<?php
// pages/assignment_detail.php



$aid = $_GET['aid'] ?? null;
if (!$aid) {
    header("Location: index.php?route=assignments");
    exit;
}

// Получаем задание
$stmt = $db->prepare("SELECT a.*, w.number as week_number, w.title as week_title 
                      FROM assignments a 
                      JOIN weeks w ON a.week_id = w.id 
                      WHERE a.id = ?");
$stmt->execute([$aid]);
$assignment = $stmt->fetch();

if (!$assignment) {
    die("Задание не найдено.");
}

// Получаем существующий ответ
$stmt = $db->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ?");
$stmt->execute([$aid, $user['id']]);
$submission = $stmt->fetch();

$now = new DateTime('now', new DateTimeZone('UTC'));

// Обработка отправки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $text_answer = trim($_POST['text_answer'] ?? '');
    $file_path = $submission ? $submission['file_path'] : null;
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $original_filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", $_FILES['file']['name']);
        $filename = date('YmdHis') . '_' . $original_filename;
        if (move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_DIR . $filename)) {
            $file_path = $filename;
        }
    }
    
    if ($submission) {
        $stmt = $db->prepare("UPDATE submissions SET text_answer = ?, file_path = ?, submitted_at = CURRENT_TIMESTAMP, status = 'pending', grade = NULL, comment = NULL WHERE id = ?");
        $stmt->execute([$text_answer, $file_path, $submission['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO submissions (assignment_id, user_id, text_answer, file_path, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$aid, $user['id'], $text_answer, $file_path]);
    }
    
    set_flash(__('submit_success'), 'success');
    header("Location: index.php?route=assignment_detail&aid=$aid");
    exit;
}

$page_title = $assignment['title'];
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📝 <?= htmlspecialchars($assignment['title']) ?></h1>
    <div class="breadcrumb"><?= __('week') ?> <?= $assignment['week_number'] ?> · <?= htmlspecialchars($assignment['week_title']) ?></div>
  </div>
  <a href="index.php?route=assignments" class="btn btn-secondary btn-sm"><?= __('back_to_assignments') ?></a>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
  <!-- Описание и форма сдачи -->
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-title">📋 <?= __('assignment_desc') ?></div>
      <div style="line-height:1.8;white-space:pre-line"><?= htmlspecialchars($assignment['description'] ?? __('no_desc')) ?></div>
      <?php if ($assignment['deadline']): 
          $deadline = new DateTime($assignment['deadline']);
      ?>
      <div style="margin-top:16px;padding:12px;background:#fff9e6;border-radius:8px;border:1px solid #ffe082;font-size:.88rem">
        ⏰ <strong><?= __('due_date') ?>:</strong> <?= $deadline->format('d.m.Y, H:i') ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!$submission || $submission['status'] != 'reviewed'): ?>
    <div class="card">
      <div class="card-title">📤 <?= __('submit_work') ?></div>
      <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
          <label class="form-label"><?= __('text_answer_optional') ?></label>
          <textarea name="text_answer" class="form-control" rows="5"
            placeholder="<?= __('enter_answer_here') ?>"><?= htmlspecialchars($submission['text_answer'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label"><?= __('attach_file_optional') ?></label>
          <input type="file" name="file" class="form-control">
          <div class="form-hint"><?= __('file_formats_hint') ?></div>
        </div>
        <button type="submit" class="btn btn-primary">📤 <?= __('submit_work') ?></button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <!-- Статус -->
  <div>
    <div class="card">
      <div class="card-title">📊 <?= __('status') ?? 'Статус' ?></div>
      <?php if ($submission): ?>
        <div style="text-align:center;padding:16px 0">
          <?php if ($submission['status'] == 'pending'): ?>
            <div style="font-size:2.5rem">⏳</div>
            <div style="font-weight:700;margin-top:8px"><?= __('pending') ?></div>
            <div style="font-size:.82rem;color:var(--muted);margin-top:4px">
              <?= __('submitted_on') ?> <?= date('d.m.Y H:i', strtotime($submission['submitted_at'])) ?>
            </div>
          <?php elseif ($submission['status'] == 'reviewed'): ?>
            <div style="font-size:2.5rem">✅</div>
            <div style="font-weight:700;margin-top:8px"><?= __('reviewed') ?></div>
            <?php if ($submission['grade'] !== null): ?>
            <div style="margin-top:16px">
              <div class="grade-circle <?= $submission['grade'] >= 75 ? 'grade-high' : ($submission['grade'] >= 50 ? 'grade-mid' : 'grade-low') ?>" style="margin:0 auto">
                <?= $submission['grade'] ?>
              </div>
              <div style="font-size:.82rem;color:var(--muted);margin-top:4px"><?= __('out_of_100') ?></div>
            </div>
            <?php endif; ?>
            <?php if ($submission['comment']): ?>
            <div style="margin-top:16px;text-align:left;background:#f4f6fb;border-radius:8px;padding:12px;font-size:.88rem">
              <strong>💬 <?= __('teacher_comment') ?>:</strong><br>
              <p style="margin-top:6px;line-height:1.6"><?= htmlspecialchars($submission['comment']) ?></p>
            </div>
            <?php endif; ?>
          <?php elseif ($submission['status'] == 'revision'): ?>
            <div style="font-size:2.5rem">🔄</div>
            <div style="font-weight:700;margin-top:8px;color:var(--danger)"><?= __('needs_revision') ?></div>
            <?php if ($submission['comment']): ?>
            <div style="margin-top:12px;text-align:left;background:#fdf0f0;border-radius:8px;padding:12px;font-size:.88rem">
              <strong>💬 <?= __('comment') ?>:</strong><br>
              <p style="margin-top:6px;line-height:1.6"><?= htmlspecialchars($submission['comment']) ?></p>
            </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php if ($submission['file_path']): ?>
        <div style="border-top:1px solid var(--border);padding-top:12px;margin-top:12px">
          <div style="font-size:.8rem;color:var(--muted);margin-bottom:6px"><?= __('attached_file') ?>:</div>
          <a href="/uploads/<?= htmlspecialchars($submission['file_path']) ?>" class="btn btn-secondary btn-sm" target="_blank">
            📄 <?= __('download_file') ?>
          </a>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <div style="text-align:center;padding:20px;color:var(--muted)">
          <div style="font-size:2rem">📭</div>
          <p style="margin-top:8px;font-size:.88rem"><?= __('not_submitted_yet') ?></p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
