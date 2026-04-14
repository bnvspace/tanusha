<?php
// pages/materials.php

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

if (!$course) {
    die(__('no_data'));
}

normalize_course_week_numbers($db, (int) $course['id']);
$courseDocuments = get_course_documents($course);

$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = __('materials');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📖 <?= __('materials') ?></h1>
    <div class="breadcrumb"><?= __('all_materials_by_week') ?></div>
  </div>
</div>

<?php if (!empty($courseDocuments)): ?>
<div class="card mb-5">
  <div class="card-title">📄 <?= __('course_documents') ?></div>
  <div class="flex gap-3 flex-wrap">
    <?php foreach ($courseDocuments as $document): ?>
      <a href="<?= htmlspecialchars($document['url']) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
        <?= htmlspecialchars($document['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($weeks)): ?>
    <?php foreach ($weeks as $week): ?>
    <?php
        $stmt = $db->prepare("SELECT * FROM materials WHERE week_id = ? AND visible = 1 ORDER BY created_at");
        $stmt->execute([$week['id']]);
        $materials = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT * FROM assignments WHERE week_id = ? AND visible = 1 ORDER BY created_at");
        $stmt->execute([$week['id']]);
        $assignments = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT * FROM tests WHERE week_id = ? AND visible = 1 ORDER BY created_at");
        $stmt->execute([$week['id']]);
        $tests = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT COUNT(*) FROM discussion_topics WHERE week_id = ?");
        $stmt->execute([$week['id']]);
        $topicCount = (int) $stmt->fetchColumn();

        $hasItems = !empty($materials) || !empty($assignments) || !empty($tests);
    ?>
    <div class="week-block">
      <div class="week-header" onclick="toggleWeek(<?= $week['id'] ?>)">
        <div class="week-title">
          <div class="week-num"><?= $week['number'] ?></div>
          <?= htmlspecialchars(format_week_title($week['title'])) ?>
        </div>
        <div class="forum-week-actions">
          <a href="index.php?route=week_discussion&wid=<?= $week['id'] ?>&from=materials" class="btn btn-secondary btn-sm" onclick="event.stopPropagation()">
            💬 <?= __('open_discussion') ?>
            <?php if ($topicCount > 0): ?>
              <span class="forum-count-badge"><?= $topicCount ?></span>
            <?php endif; ?>
          </a>
          <span id="arrow-<?= $week['id'] ?>" class="text-muted" style="font-size:1.1rem">▾</span>
        </div>
      </div>
      <div class="week-body" id="week-<?= $week['id'] ?>" style="display:none">

        <?php if (!empty($materials)): ?>
          <div class="mb-4">
            <div class="section-label">📚 <?= __('materials') ?></div>
            <div class="item-list">
              <?php foreach ($materials as $material): ?>
              <?php
                $typeIcons = ['file' => '📄', 'video' => '🎬', 'audio' => '🎵', 'link' => '🔗', 'text' => '📝', 'interactive' => '🎯'];
                $typeClasses = ['file' => 'ic-file', 'video' => 'ic-video', 'audio' => 'ic-audio', 'link' => 'ic-link', 'text' => 'ic-text', 'interactive' => 'ic-interactive'];
                $icon = $typeIcons[$material['material_type']] ?? '📄';
                $class = $typeClasses[$material['material_type']] ?? 'ic-file';

                $link = null;
                if (!empty($material['url'])) {
                    $link = $material['url'];
                } elseif (!empty($material['file_path'])) {
                    $link = '/uploads/' . $material['file_path'];
                }
              ?>
                <?php if ($link): ?>
                <a href="<?= $link ?>" target="_blank" class="item-card">
                <?php else: ?>
                <div class="item-card">
                <?php endif; ?>
                  <div class="item-icon <?= $class ?>"><?= $icon ?></div>
                  <div class="item-info">
                    <div class="item-title"><?= htmlspecialchars($material['title']) ?></div>
                    <div class="item-meta">
                      <?= __($material['material_type'] === 'interactive' ? 'interactive' : ($material['material_type'] === 'text' ? 'text_pres' : $material['material_type'])) ?>
                      <?php if ($material['content']): ?>
                        · <?= htmlspecialchars(mb_substr($material['content'], 0, 80)) . (mb_strlen($material['content']) > 80 ? '...' : '') ?>
                      <?php endif; ?>
                    </div>
                  </div>
                  <?php if ($link): ?><div class="item-action">→</div><?php endif; ?>
                <?php if ($link): ?></a><?php else: ?></div><?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($assignments)): ?>
          <div class="mb-4">
            <div class="section-label">📝 <?= __('assignments') ?></div>
            <div class="item-list">
              <?php foreach ($assignments as $assignment): ?>
              <a href="index.php?route=assignment_detail&aid=<?= $assignment['id'] ?>" class="item-card">
                <div class="item-icon ic-task">📝</div>
                <div class="item-info">
                  <div class="item-title"><?= htmlspecialchars($assignment['title']) ?></div>
                  <div class="item-meta">
                    <?= !empty($assignment['deadline']) ? __('due_date') . ': ' . date('d.m.Y H:i', strtotime($assignment['deadline'])) : __('no_deadline') ?>
                  </div>
                </div>
                <div class="item-action">→</div>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!empty($tests)): ?>
          <div>
            <div class="section-label">🧪 <?= __('tests') ?></div>
            <div class="item-list">
              <?php foreach ($tests as $test): ?>
              <?php
                $stmtQuestionCount = $db->prepare("SELECT COUNT(*) FROM test_questions WHERE test_id = ?");
                $stmtQuestionCount->execute([$test['id']]);
                $questionCount = $stmtQuestionCount->fetchColumn();
              ?>
              <a href="index.php?route=test_take&tid=<?= $test['id'] ?>" class="item-card">
                <div class="item-icon ic-test">🧪</div>
                <div class="item-info">
                  <div class="item-title"><?= htmlspecialchars($test['title']) ?></div>
                  <div class="item-meta"><?= $questionCount ?> <?= __('questions_plural') ?><?= !empty($test['time_limit']) ? ' · ' . $test['time_limit'] . ' ' . __('minutes') : '' ?></div>
                </div>
                <div class="item-action">→</div>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!$hasItems): ?>
          <p class="empty-state"><?= __('no_data') ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card">
      <p class="empty-state"><?= __('no_data') ?></p>
    </div>
<?php endif; ?>

<script>
function toggleWeek(id) {
  const body = document.getElementById('week-' + id);
  const arrow = document.getElementById('arrow-' + id);
  const isHidden = body.style.display === 'none';
  body.style.display = isHidden ? 'block' : 'none';
  arrow.textContent = isHidden ? '▴' : '▾';
}

document.addEventListener('DOMContentLoaded', () => {
  const firstBody = document.querySelector('.week-body');
  if (firstBody) firstBody.style.display = 'block';
  const arrows = document.querySelectorAll('[id^="arrow-"]');
  if (arrows.length > 0) arrows[0].textContent = '▴';
});
</script>

<?php include 'footer.php'; ?>
