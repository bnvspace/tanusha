<?php
// pages/admin/review_detail.php
require_once 'config.php';
require_once 'auth.php';

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
$submission = $stmt->fetch();

if (!$submission) {
    die("Ответ не найден.");
}

// Обработка оценки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = isset($_POST['grade']) ? intval($_POST['grade']) : null;
    $status = $_POST['status'] ?? 'reviewed';
    $comment = trim($_POST['comment'] ?? '');
    
    $stmt = $db->prepare("UPDATE submissions SET grade = ?, status = ?, comment = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$grade, $status, $comment, $sid]);
    
    set_flash('Оценка успешно выставлена!', 'success');
    header("Location: index.php?route=admin_review");
    exit;
}

$page_title = 'Проверка работы';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>✏️ Проверка работы</h1>
    <div class="breadcrumb"><?= htmlspecialchars($submission['student_name']) ?> · <?= htmlspecialchars($submission['assignment_title']) ?></div>
  </div>
  <a href="index.php?route=admin_review" class="btn btn-secondary btn-sm">← Назад</a>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">
  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-title">📋 Задание</div>
      <div style="font-weight:700;margin-bottom:8px"><?= htmlspecialchars($submission['assignment_title']) ?></div>
      <div style="line-height:1.7;color:var(--text);white-space:pre-line"><?= htmlspecialchars($submission['assignment_description'] ?? 'Описание не указано.') ?></div>
    </div>

    <div class="card">
      <div class="card-title">📤 Ответ студента</div>
      <div style="font-size:.82rem;color:var(--muted);margin-bottom:12px">
        Сдано: <?= date('d.m.Y в H:i', strtotime($submission['submitted_at'])) ?>
      </div>
      <?php if ($submission['text_answer']): ?>
      <div style="background:#f4f6fb;border-radius:8px;padding:14px;line-height:1.7;white-space:pre-line;margin-bottom:12px">
        <?= htmlspecialchars($submission['text_answer']) ?>
      </div>
      <?php endif; ?>
      <?php if ($submission['file_path']): ?>
      <a href="/uploads/<?= htmlspecialchars($submission['file_path']) ?>" class="btn btn-secondary" target="_blank">
        📄 Скачать прикреплённый файл
      </a>
      <?php endif; ?>
      <?php if (!$submission['text_answer'] && !$submission['file_path']): ?>
      <p style="color:var(--muted)">Ответ не прикреплён.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-title">📊 Выставить оценку</div>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Оценка (0–100)</label>
        <input type="number" name="grade" class="form-control" min="0" max="100"
          value="<?= htmlspecialchars($submission['grade'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Статус</label>
        <select name="status" class="form-control">
          <option value="reviewed" <?= $submission['status'] == 'reviewed' ? 'selected' : '' ?>>✅ Проверено</option>
          <option value="revision" <?= $submission['status'] == 'revision' ? 'selected' : '' ?>>🔄 Требует доработки</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Комментарий</label>
        <textarea name="comment" class="form-control" rows="5"
          placeholder="Оставьте комментарий студенту..."><?= htmlspecialchars($submission['comment'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
        ✅ Подтвердить оценку
      </button>
    </form>

    <?php if ($submission['status'] != 'pending'): ?>
    <div style="margin-top:14px;padding:12px;background:#d1e7dd;border-radius:8px;font-size:.82rem;color:#0f5132">
      Оценка уже выставлена. Можно изменить.
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
