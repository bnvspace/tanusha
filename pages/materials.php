<?php
// pages/materials.php

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
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

<?php if (!empty($weeks)): ?>
    <?php foreach ($weeks as $week):
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
        $topic_count = (int) $stmt->fetchColumn();

        $has_items = !empty($materials) || !empty($assignments) || !empty($tests);
    ?>
    <div class="week-block">
      <div class="week-header" onclick="toggleWeek(<?= $week['id'] ?>)">
        <div class="week-title">
          <div class="week-num"><?= $week['number'] ?></div>
          <?= htmlspecialchars($week['title']) ?>
        </div>
        <div class="forum-week-actions">
          <a href="index.php?route=week_discussion&wid=<?= $week['id'] ?>&from=materials" class="btn btn-secondary btn-sm" onclick="event.stopPropagation()">
            💬 <?= __('open_discussion') ?>
            <?php if ($topic_count > 0): ?>
              <span class="forum-count-badge"><?= $topic_count ?></span>
            <?php endif; ?>
          </a>
          <span id="arrow-<?= $week['id'] ?>" style="font-size:1.1rem;color:var(--muted)">▾</span>
        </div>
      </div>
      <div class="week-body" id="week-<?= $week['id'] ?>" style="display:none">

        <?php if (!empty($materials)): ?>
          <div style="margin-bottom:14px">
            <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">📚 <?= __('materials') ?></div>
            <div class="item-list">
              <?php foreach ($materials as $m):
                $type_icons = ['file' => '📄', 'video' => '🎬', 'audio' => '🎵', 'link' => '🔗', 'text' => '📝', 'interactive' => '🎯'];
                $type_classes = ['file' => 'ic-file', 'video' => 'ic-video', 'audio' => 'ic-audio', 'link' => 'ic-link', 'text' => 'ic-text', 'interactive' => 'ic-interactive'];
                $icon = $type_icons[$m['material_type']] ?? '📄';
                $class = $type_classes[$m['material_type']] ?? 'ic-file';

                $link = null;
                if (!empty($m['url'])) {
                    $link = $m['url'];
                } elseif (!empty($m['file_path'])) {
                    $link = "/uploads/" . $m['file_path'];
                }
              ?>
                <?php if ($link): ?>
                <a href="<?= $link ?>" target="_blank" class="item-card">
                <?php else: ?>
                <div class="item-card">
                <?php endif; ?>
                  <div class="item-icon <?= $class ?>"><?= $icon ?></div>
                  <div class="item-info">
                    <div class="item-title"><?= htmlspecialchars($m['title']) ?></div>
                    <div class="item-meta">
                      <?= __($m['material_type'] === 'interactive' ? 'interactive' : ($m['material_type'] === 'text' ? 'text_pres' : $m['material_type'])) ?>
                      <?php if ($m['content']): ?>
                        · <?= htmlspecialchars(mb_substr($m['content'], 0, 80)) . (mb_strlen($m['content']) > 80 ? '...' : '') ?>
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
          <div style="margin-bottom:14px">
            <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">📝 <?= __('assignments') ?></div>
            <div class="item-list">
              <?php foreach ($assignments as $a): ?>
              <a href="index.php?route=assignment_detail&aid=<?= $a['id'] ?>" class="item-card">
                <div class="item-icon ic-task">📝</div>
                <div class="item-info">
                  <div class="item-title"><?= htmlspecialchars($a['title']) ?></div>
                  <div class="item-meta">
                    <?= !empty($a['deadline']) ? __('due_date') . ": " . date('d.m.Y H:i', strtotime($a['deadline'])) : __('no_deadline') ?>
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
            <div style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">🧪 <?= __('tests') ?></div>
            <div class="item-list">
              <?php foreach ($tests as $t):
                $stmt_q = $db->prepare("SELECT COUNT(*) FROM test_questions WHERE test_id = ?");
                $stmt_q->execute([$t['id']]);
                $q_count = $stmt_q->fetchColumn();
              ?>
              <a href="index.php?route=test_take&tid=<?= $t['id'] ?>" class="item-card">
                <div class="item-icon ic-test">🧪</div>
                <div class="item-info">
                  <div class="item-title"><?= htmlspecialchars($t['title']) ?></div>
                  <div class="item-meta"><?= $q_count ?> <?= __('questions_plural') ?><?= !empty($t['time_limit']) ? " · " . $t['time_limit'] . " " . __('minutes') : "" ?></div>
                </div>
                <div class="item-action">→</div>
              </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if (!$has_items): ?>
          <p style="color:var(--muted);font-size:.88rem;text-align:center;padding:10px"><?= __('no_data') ?></p>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card">
      <p style="text-align:center;color:var(--muted);padding:30px"><?= __('no_data') ?></p>
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
