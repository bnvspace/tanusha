<?php
// pages/test_take.php
require_once 'config.php';
require_once 'auth.php';

$tid = $_GET['tid'] ?? null;
if (!$tid) {
    header("Location: index.php?route=tests");
    exit;
}

// Получаем тест и вопросы
$stmt = $db->prepare("SELECT * FROM tests WHERE id = ?");
$stmt->execute([$tid]);
$test = $stmt->fetch();

if (!$test) {
    die("Тест не найден.");
}

// Проверяем, не пройден ли тест уже
$stmt = $db->prepare("SELECT id FROM test_submissions WHERE test_id = ? AND user_id = ? AND finished_at IS NOT NULL LIMIT 1");
$stmt->execute([$tid, $user['id']]);
$existing = $stmt->fetch();

if ($existing) {
    header("Location: index.php?route=test_result&tid=$tid&sid=" . $existing['id']);
    exit;
}

// Получаем вопросы и варианты
$stmt = $db->prepare("SELECT * FROM test_questions WHERE test_id = ? ORDER BY order_num");
$stmt->execute([$tid]);
$questions = $stmt->fetchAll();

foreach ($questions as &$q) {
    $stmt_opt = $db->prepare("SELECT * FROM test_options WHERE question_id = ?");
    $stmt_opt->execute([$q['id']]);
    $q['options'] = $stmt_opt->fetchAll();
}

// Обработка отправки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO test_submissions (test_id, user_id, started_at, max_score) VALUES (?, ?, CURRENT_TIMESTAMP, ?)");
        $stmt->execute([$tid, $user['id'], count($questions)]);
        $sid = $db->lastInsertId();
        
        $score = 0;
        foreach ($questions as $q) {
            $is_correct = false;
            $answer_text = null;
            $selected_options = null;
            
            if ($q['question_type'] == 'text') {
                $answer_text = trim($_POST['q_' . $q['id']] ?? '');
                $correct_options = array_filter($q['options'], function($o) { return $o['is_correct']; });
                $correct_texts = array_map(function($o) { return mb_strtolower(trim($o['option_text'])); }, $correct_options);
                $is_correct = in_array(mb_strtolower($answer_text), $correct_texts);
            } 
            elseif ($q['question_type'] == 'multiple') {
                $selected_ids = array_map('intval', $_POST['q_' . $q['id']] ?? []);
                $correct_ids = array_map('intval', array_column(array_filter($q['options'], function($o) { return $o['is_correct']; }), 'id'));
                sort($selected_ids);
                sort($correct_ids);
                $is_correct = ($selected_ids === $correct_ids);
                $selected_options = json_encode($selected_ids);
            } 
            else { // single
                $selected_id = isset($_POST['q_' . $q['id']]) ? intval($_POST['q_' . $q['id']]) : null;
                if ($selected_id) {
                    foreach ($q['options'] as $opt) {
                        if ($opt['id'] == $selected_id) {
                            $is_correct = (bool)$opt['is_correct'];
                            break;
                        }
                    }
                }
                $selected_options = json_encode($selected_id ? [$selected_id] : []);
            }
            
            if ($is_correct) $score++;
            
            $stmt_ans = $db->prepare("INSERT INTO test_answers (submission_id, question_id, answer_text, selected_options, is_correct) VALUES (?, ?, ?, ?, ?)");
            $stmt_ans->execute([$sid, $q['id'], $answer_text, $selected_options, $is_correct ? 1 : 0]);
        }
        
        $stmt_upd = $db->prepare("UPDATE test_submissions SET score = ?, finished_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt_upd->execute([$score, $sid]);
        
        $db->commit();
        header("Location: index.php?route=test_result&tid=$tid&sid=$sid");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        die("Ошибка сохранения результатов: " . $e->getMessage());
    }
}

$page_title = $test['title'];
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>🧪 <?= htmlspecialchars($test['title']) ?></h1>
    <div class="breadcrumb">Прохождение теста</div>
  </div>
</div>

<?php if ($test['description']): ?>
<div class="card" style="margin-bottom:20px">
  <p style="line-height:1.7"><?= htmlspecialchars($test['description']) ?></p>
  <?php if ($test['time_limit']): ?>
  <div style="margin-top:10px;padding:10px 14px;background:#fff9e6;border-radius:8px;border:1px solid #ffe082;font-size:.88rem">
    ⏱ Ограничение времени: <strong><?= $test['time_limit'] ?> минут</strong>
    <span id="timer" style="float:right;font-weight:700;color:var(--danger)"></span>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" id="test-form">
  <?php foreach ($questions as $index => $q): ?>
  <div class="question-block">
    <div class="question-num">Вопрос <?= $index + 1 ?> из <?= count($questions) ?></div>
    <div class="question-text"><?= htmlspecialchars($q['question_text']) ?></div>

    <?php if ($q['question_type'] == 'text'): ?>
      <input type="text" name="q_<?= $q['id'] ?>" class="form-control" placeholder="Введите ответ..." required>

    <?php elseif ($q['question_type'] == 'multiple'): ?>
      <div style="font-size:.78rem;color:var(--muted);margin-bottom:8px">Выберите все верные варианты</div>
      <?php foreach ($q['options'] as $opt): ?>
      <label class="option-label">
        <input type="checkbox" name="q_<?= $q['id'] ?>[]" value="<?= $opt['id'] ?>">
        <?= htmlspecialchars($opt['option_text']) ?>
      </label>
      <?php endforeach; ?>

    <?php else: ?> <!-- single -->
      <?php foreach ($q['options'] as $opt): ?>
      <label class="option-label">
        <input type="radio" name="q_<?= $q['id'] ?>" value="<?= $opt['id'] ?>" required>
        <?= htmlspecialchars($opt['option_text']) ?>
      </label>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div style="display:flex;gap:12px;margin-top:10px">
    <button type="submit" class="btn btn-primary" onclick="return confirm('Завершить тест и отправить ответы?')">
      ✅ Завершить тест
    </button>
    <a href="index.php?route=tests" class="btn btn-secondary">Отмена</a>
  </div>
</form>

<?php if ($test['time_limit']): ?>
<script>
  let remaining = <?= $test['time_limit'] ?> * 60;
  const timerEl = document.getElementById('timer');
  const form = document.getElementById('test-form');
  const iv = setInterval(() => {
    remaining--;
    const m = Math.floor(remaining / 60);
    const s = remaining % 60;
    timerEl.textContent = `${m}:${s.toString().padStart(2,'0')}`;
    if (remaining <= 0) {
      clearInterval(iv);
      form.submit();
    }
  }, 1000);
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
