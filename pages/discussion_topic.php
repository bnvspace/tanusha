<?php
// pages/discussion_topic.php

function upload_discussion_image(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [null, __('image_upload_failed')];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return [null, __('image_upload_too_large')];
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    if (!in_array($extension, $allowed, true)) {
        return [null, __('image_upload_invalid')];
    }

    if (!is_uploaded_file($file['tmp_name']) || @getimagesize($file['tmp_name']) === false) {
        return [null, __('image_upload_invalid')];
    }

    $baseName = pathinfo($file['name'] ?? 'image', PATHINFO_FILENAME);
    $safeBaseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
    $safeBaseName = trim($safeBaseName, '_');
    if ($safeBaseName === '') {
        $safeBaseName = 'image';
    }

    $fileName = 'discussion_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBaseName . '.' . $extension;

    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $fileName)) {
        return [null, __('image_save_failed')];
    }

    return [$fileName, null];
}

$topicId = (int) ($_GET['topic_id'] ?? 0);
$from = $_GET['from'] ?? '';

if ($topicId <= 0) {
    header('Location: index.php?route=' . (in_array($user['role'], ['teacher', 'admin'], true) ? 'admin_course' : 'materials'));
    exit;
}

$stmt = $db->prepare(
    "SELECT t.*,
            w.id AS week_id,
            w.number AS week_number,
            w.title AS week_title,
            u.full_name AS author_name
     FROM discussion_topics t
     JOIN weeks w ON w.id = t.week_id
     JOIN users u ON u.id = t.user_id
     WHERE t.id = ?"
);
$stmt->execute([$topicId]);
$topic = $stmt->fetch();

if (!$topic) {
    die(__('topic_not_found'));
}

$discussionUrl = "index.php?route=week_discussion&wid=" . (int) $topic['week_id'];
if (in_array($from, ['dashboard', 'materials', 'admin_course'], true)) {
    $discussionUrl .= '&from=' . urlencode($from);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_comment') {
        $commentText = trim($_POST['comment_text'] ?? '');
        $imagePath = null;

        if (isset($_FILES['comment_image'])) {
            [$imagePath, $uploadError] = upload_discussion_image($_FILES['comment_image']);
            if ($uploadError !== null) {
                set_flash($uploadError, 'danger');
                header("Location: index.php?route=discussion_topic&topic_id=$topicId" . ($from !== '' ? '&from=' . urlencode($from) : ''));
                exit;
            }
        }

        if ($commentText === '' && $imagePath === null) {
            set_flash(__('comment_requires_text_or_image'), 'warning');
            header("Location: index.php?route=discussion_topic&topic_id=$topicId" . ($from !== '' ? '&from=' . urlencode($from) : ''));
            exit;
        }

        $stmt = $db->prepare(
            "INSERT INTO discussion_comments (topic_id, user_id, comment_text, image_path, created_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([$topicId, $user['id'], $commentText, $imagePath]);

        $stmt = $db->prepare("UPDATE discussion_topics SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$topicId]);

        set_flash(__('comment_added'), 'success');
        header("Location: index.php?route=discussion_topic&topic_id=$topicId" . ($from !== '' ? '&from=' . urlencode($from) : ''));
        exit;
    }
}

$stmt = $db->prepare(
    "SELECT dc.*, u.full_name AS author_name, u.role AS author_role
     FROM discussion_comments dc
     JOIN users u ON u.id = dc.user_id
     WHERE dc.topic_id = ?
     ORDER BY datetime(dc.created_at) ASC, dc.id ASC"
);
$stmt->execute([$topicId]);
$comments = $stmt->fetchAll();

$page_title = __('discussion');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1><?= htmlspecialchars($topic['title']) ?></h1>
    <div class="breadcrumb"><?= __('week') ?> <?= $topic['week_number'] ?> · <?= htmlspecialchars($topic['week_title']) ?></div>
  </div>
  <a href="<?= $discussionUrl ?>" class="btn btn-secondary btn-sm"><?= __('back_to_discussions') ?></a>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-title"><?= __('topic_starter') ?></div>
  <div class="forum-topic-header">
    <div>
      <div class="forum-topic-title" style="margin-bottom:4px"><?= htmlspecialchars($topic['title']) ?></div>
      <div class="forum-topic-meta">
        <span><?= __('topic_author') ?>: <?= htmlspecialchars($topic['author_name']) ?></span>
        <span><?= date('d.m.Y H:i', strtotime($topic['created_at'])) ?></span>
      </div>
    </div>
  </div>
  <?php if (!empty($topic['body'])): ?>
    <div class="forum-topic-fulltext"><?= nl2br(htmlspecialchars($topic['body'])) ?></div>
  <?php else: ?>
    <p class="forum-empty-note"><?= __('no_data') ?></p>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-title"><?= __('comments') ?></div>
  <?php if (!empty($comments)): ?>
    <div class="comment-thread">
      <?php foreach ($comments as $comment): ?>
        <div class="comment-card">
          <div class="comment-header">
            <div class="comment-author"><?= htmlspecialchars($comment['author_name']) ?></div>
            <div class="comment-date"><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></div>
          </div>
          <?php if (!empty($comment['comment_text'])): ?>
            <div class="comment-body"><?= nl2br(htmlspecialchars($comment['comment_text'])) ?></div>
          <?php endif; ?>
          <?php if (!empty($comment['image_path'])): ?>
            <a href="/uploads/<?= htmlspecialchars($comment['image_path']) ?>" target="_blank" class="comment-image-link">
              <img src="/uploads/<?= htmlspecialchars($comment['image_path']) ?>" alt="comment image" class="comment-image">
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="forum-empty-note"><?= __('no_comments_yet') ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title"><?= __('new_comment') ?></div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_comment">
    <div class="form-group">
      <label class="form-label"><?= __('comment_text_label') ?></label>
      <textarea name="comment_text" class="form-control" rows="5" placeholder="<?= __('comment_text_placeholder') ?>"></textarea>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('attach_image_optional') ?></label>
      <input type="file" name="comment_image" class="form-control" accept=".png,.jpg,.jpeg,.gif,.webp,image/*">
      <div class="form-hint"><?= __('comment_image_hint') ?></div>
    </div>
    <button type="submit" class="btn btn-primary"><?= __('add_comment') ?></button>
  </form>
</div>

<?php include 'footer.php'; ?>
