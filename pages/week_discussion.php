<?php
// pages/week_discussion.php

$week_id = (int) ($_GET['wid'] ?? 0);
$from = $_GET['from'] ?? '';

if ($week_id <= 0) {
    header('Location: index.php?route=' . (in_array($user['role'], ['teacher', 'admin'], true) ? 'admin_course' : 'materials'));
    exit;
}

$stmt = $db->prepare(
    "SELECT w.*, c.title AS course_title
     FROM weeks w
     JOIN courses c ON c.id = w.course_id
     WHERE w.id = ?"
);
$stmt->execute([$week_id]);
$week = $stmt->fetch();

if (!$week) {
    die(__('week_not_found'));
}

$backRoute = in_array($user['role'], ['teacher', 'admin'], true) ? 'admin_course' : 'materials';
if (in_array($from, ['dashboard', 'materials', 'admin_course'], true)) {
    $backRoute = $from;
}

$topicBaseUrl = "index.php?route=discussion_topic&from=" . urlencode($backRoute);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_forum_description' && in_array($user['role'], ['teacher', 'admin'], true)) {
        $description = trim($_POST['discussion_description'] ?? '');
        $stmt = $db->prepare("UPDATE weeks SET discussion_description = ? WHERE id = ?");
        $stmt->execute([$description, $week_id]);

        set_flash(__('forum_description_saved'), 'success');
        header("Location: index.php?route=week_discussion&wid=$week_id&from=$backRoute");
        exit;
    }

    if ($action === 'create_topic') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($title === '') {
            set_flash(__('topic_title_required'), 'warning');
            header("Location: index.php?route=week_discussion&wid=$week_id&from=$backRoute");
            exit;
        }

        $stmt = $db->prepare(
            "INSERT INTO discussion_topics (week_id, user_id, title, body, created_at, updated_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([$week_id, $user['id'], $title, $body]);

        $topicId = (int) $db->lastInsertId();
        set_flash(__('topic_created'), 'success');
        header("Location: " . $topicBaseUrl . "&topic_id=$topicId");
        exit;
    }
}

$stmt = $db->prepare(
    "SELECT t.*,
            u.full_name AS author_name,
            (SELECT COUNT(*) FROM discussion_comments dc WHERE dc.topic_id = t.id) AS comment_count,
            MAX(
                COALESCE((SELECT MAX(dc.created_at) FROM discussion_comments dc WHERE dc.topic_id = t.id), t.created_at),
                COALESCE(t.updated_at, t.created_at)
            ) AS last_activity
     FROM discussion_topics t
     JOIN users u ON u.id = t.user_id
     WHERE t.week_id = ?
     ORDER BY last_activity DESC, t.id DESC"
);
$stmt->execute([$week_id]);
$topics = $stmt->fetchAll();

$page_title = __('discussion');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1><?= __('week_discussion_title') ?></h1>
    <div class="breadcrumb"><?= __('week') ?> <?= $week['number'] ?> · <?= htmlspecialchars($week['title']) ?></div>
  </div>
  <a href="index.php?route=<?= htmlspecialchars($backRoute) ?>" class="btn btn-secondary btn-sm"><?= __('back') ?></a>
</div>

<div class="card forum-hero" style="margin-bottom:20px">
  <div class="card-title"><?= __('forum_description') ?></div>
  <?php if (!empty($week['discussion_description'])): ?>
    <div class="forum-description-text"><?= htmlspecialchars($week['discussion_description']) ?></div>
  <?php else: ?>
    <p class="forum-empty-note"><?= __('discussion_description_empty') ?></p>
  <?php endif; ?>

  <?php if (in_array($user['role'], ['teacher', 'admin'], true)): ?>
    <form method="POST" style="margin-top:18px">
      <input type="hidden" name="action" value="update_forum_description">
      <div class="form-group" style="margin-bottom:12px">
        <label class="form-label"><?= __('edit_forum_description') ?></label>
        <textarea name="discussion_description" class="form-control" rows="3" placeholder="<?= __('discussion_description_hint') ?>"><?= htmlspecialchars($week['discussion_description'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn btn-secondary btn-sm"><?= __('save_forum_description') ?></button>
    </form>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:20px">
  <div class="card-title"><?= __('discussion_topics') ?></div>

  <?php if (!empty($topics)): ?>
    <div class="forum-topic-list">
      <?php foreach ($topics as $topic): ?>
        <div class="forum-topic-row">
          <div class="forum-topic-main">
            <div class="forum-topic-title"><?= htmlspecialchars($topic['title']) ?></div>
            <div class="forum-topic-meta">
              <span><?= __('topic_author') ?>: <?= htmlspecialchars($topic['author_name']) ?></span>
              <span><?= __('comments_count') ?>: <?= (int) $topic['comment_count'] ?></span>
              <span><?= __('last_activity') ?>: <?= date('d.m.Y H:i', strtotime($topic['last_activity'])) ?></span>
            </div>
            <?php if (!empty($topic['body'])): ?>
              <div class="forum-topic-snippet">
                <?= htmlspecialchars(mb_substr($topic['body'], 0, 180)) ?><?= mb_strlen($topic['body']) > 180 ? '...' : '' ?>
              </div>
            <?php endif; ?>
          </div>
          <a href="<?= $topicBaseUrl ?>&topic_id=<?= $topic['id'] ?>" class="btn btn-secondary btn-sm"><?= __('open_topic') ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="forum-empty-note"><?= __('forum_topics_empty') ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-title"><?= __('create_topic') ?></div>
  <form method="POST">
    <input type="hidden" name="action" value="create_topic">
    <div class="form-group">
      <label class="form-label"><?= __('topic_title') ?></label>
      <input type="text" name="title" class="form-control" placeholder="<?= __('topic_title_placeholder') ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('topic_message') ?></label>
      <textarea name="body" class="form-control" rows="5" placeholder="<?= __('topic_message_placeholder') ?>"></textarea>
    </div>
    <button type="submit" class="btn btn-primary"><?= __('create_topic') ?></button>
  </form>
</div>

<?php include 'footer.php'; ?>
