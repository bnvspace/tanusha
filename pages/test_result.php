<?php
// pages/test_result.php



$tid = $_GET['tid'] ?? null;
$sid = $_GET['sid'] ?? null;

if (!$tid || !$sid) {
    header("Location: index.php?route=tests");
    exit;
}

$stmt = $db->prepare("SELECT * FROM tests WHERE id = ?");
$stmt->execute([$tid]);
$test = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM test_submissions WHERE id = ? AND test_id = ?");
$stmt->execute([$sid, $tid]);
$tsub = $stmt->fetch();

if (!$test || !$tsub) {
    die("Тест или результат не найдены.");
}

if ($tsub['user_id'] != $user['id'] && !in_array($user['role'], ['teacher', 'admin'])) {
    die("Доступ запрещен.");
}

$stmt = $db->prepare("SELECT * FROM test_questions WHERE test_id = ? ORDER BY order_num");
$stmt->execute([$tid]);
$questions = $stmt->fetchAll();

foreach ($questions as &$q) {
    $stmt_opt = $db->prepare("SELECT * FROM test_options WHERE question_id = ?");
    $stmt_opt->execute([$q['id']]);
    $q['options'] = $stmt_opt->fetchAll();
}

$stmt = $db->prepare("SELECT * FROM test_answers WHERE submission_id = ?");
$stmt->execute([$sid]);
$answers = $stmt->fetchAll();
$answers_map = [];
foreach ($answers as $a) {
    $answers_map[$a['question_id']] = $a;
}

$pct = ($tsub['max_score'] > 0) ? intval($tsub['score'] / $tsub['max_score'] * 100) : 0;

$page_title = 'Результат теста';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📊 Результат теста</h1>
    <div class="breadcrumb"><?= htmlspecialchars($test['title']) ?></div>
  </div>
  <a href="index.php?route=tests" class="btn btn-secondary btn-sm">← К тестам</a>
</div>

<div class="card" style="text-align:center;margin-bottom:20px">
  <div style="font-size:3.5rem;margin-bottom:12px">
    <?= $pct >= 75 ? '🏆' : ($pct >= 50 ? '👍' : '📚') ?>
  </div>
  <div style="font-size:3rem;font-weight:800;color:<?= $pct >= 75 ? 'var(--success)' : ($pct >= 50 ? 'var(--accent)' : 'var(--danger)') ?>">
    <?= $pct ?>%
  </div>
  <div style="font-size:1.1rem;color:var(--muted);margin-top:4px">
    Правильных ответов: <strong><?= $tsub['score'] ?></strong> из <strong><?= $tsub['max_score'] ?></strong>
  </div>
  <?php if ($tsub['finished_at']): ?>
  <div style="font-size:.82rem;color:var(--muted);margin-top:8px">
    Завершён: <?= date('d.m.Y H:i', strtotime($tsub['finished_at'])) ?>
  </div>
  <?php endif; ?>
  <div style="margin-top:20px">
    <div class="progress-wrap" style="height:14px">
      <div class="progress-bar" style="width:<?= $pct ?>%"></div>
    </div>
  </div>
</div>

<?php if ($test['show_answers']): ?>
<div class="card">
  <div class="card-title">📋 Разбор ответов</div>
  <?php foreach ($questions as $index => $q): 
      $ans = $answers_map[$q['id']] ?? null;
  ?>
  <div style="padding:16px 0;border-bottom:1px solid var(--border);<?= $index === count($questions)-1 ? 'border-bottom:none' : '' ?>">
    <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px">
      <span style="font-size:1.1rem"><?= ($ans && $ans['is_correct']) ? '✅' : '❌' ?></span>
      <div style="font-weight:600"><?= $index + 1 ?>. <?= htmlspecialchars($q['question_text']) ?></div>
    </div>
    
    <?php if ($q['question_type'] == 'text'): 
        $correct_options = array_filter($q['options'], function($o) { return $o['is_correct']; });
        $correct_texts = array_column($correct_options, 'option_text');
    ?>
      <div style="font-size:.88rem">
        <div>Ваш ответ: <span style="font-weight:600"><?= htmlspecialchars($ans['answer_text'] ?? '—') ?></span></div>
        <div style="color:var(--success)">Правильный: <span style="font-weight:600"><?= htmlspecialchars(implode(', ', $correct_texts)) ?></span></div>
      </div>
    <?php else: 
        $selected_ids = $ans ? json_decode($ans['selected_options'], true) : [];
        if (!is_array($selected_ids)) $selected_ids = $selected_ids ? [$selected_ids] : [];
    ?>
      <div style="display:flex;flex-direction:column;gap:6px">
        <?php foreach ($q['options'] as $opt): 
            $is_selected = in_array($opt['id'], $selected_ids);
            $bg = $opt['is_correct'] ? '#d1e7dd' : ($is_selected ? '#f8d7da' : '#f4f6fb');
            $border = $opt['is_correct'] ? '#badbcc' : ($is_selected ? '#f5c2c7' : 'var(--border)');
        ?>
        <div style="display:flex;align-items:center;gap:8px;padding:7px 12px;border-radius:7px;
          background:<?= $bg ?>;
          border:1px solid <?= $border ?>">
          <span><?= $opt['is_correct'] ? '✅' : ($is_selected ? '❌' : '⬜') ?></span>
          <span style="font-size:.88rem"><?= htmlspecialchars($opt['option_text']) ?></span>
          <?php if ($is_selected && !$opt['is_correct']): ?>
            <span style="font-size:.75rem;color:var(--danger);margin-left:auto">Ваш ответ</span>
          <?php elseif ($opt['is_correct']): ?>
            <span style="font-size:.75rem;color:var(--success);margin-left:auto">Верный ответ</span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="margin-top:20px;display:flex;gap:12px">
  <a href="index.php?route=grades" class="btn btn-primary">🏆 Мои оценки</a>
  <a href="index.php?route=tests" class="btn btn-secondary">← К тестам</a>
</div>

<?php include 'footer.php'; ?>
