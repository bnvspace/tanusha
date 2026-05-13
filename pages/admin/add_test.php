<?php
// pages/admin/add_test.php



$user = login_required(['teacher', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $db->beginTransaction();
    try {
        $week_id = intval($_POST['week_id']);
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $time_limit = intval($_POST['time_limit'] ?? 0);
        $visible = intval($_POST['visible']);
        $show_answers = intval($_POST['show_answers']);
        $open_date = !empty($_POST['open_date']) ? $_POST['open_date'] : null;
        
        $stmt = $db->prepare("INSERT INTO tests (week_id, title, description, time_limit, visible, show_answers, open_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$week_id, $title, $description, $time_limit, $visible, $show_answers, $open_date]);
        $test_id = $db->lastInsertId();
        
        $q_texts = $_POST['q_text'] ?? [];
        $q_types = $_POST['q_type'] ?? [];
        
        $i = 0;
        foreach ($q_texts as $idx => $text) {
            $type = $q_types[$idx];
            $stmt_q = $db->prepare("INSERT INTO test_questions (test_id, question_text, question_type, order_num) VALUES (?, ?, ?, ?)");
            $stmt_q->execute([$test_id, $text, $type, $i + 1]);
            $q_id = $db->lastInsertId();
            
            $orig_qi = $_POST['q_idx'][$idx];
            $opts = $_POST['opt_' . $orig_qi] ?? [];
            $corrects = $_POST['correct_' . $orig_qi] ?? [];
            
            foreach ($opts as $j => $opt_text) {
                $opt_text = trim((string) $opt_text);
                if ($opt_text === '') {
                    continue;
                }

                if ($type == 'text') {
                    $is_correct = true;
                } else {
                    $is_correct = in_array($j, $corrects);
                }
                $stmt_o = $db->prepare("INSERT INTO test_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                $stmt_o->execute([$q_id, $opt_text, $is_correct ? 1 : 0]);
            }
            $i++;
        }
        
        $db->commit();
        set_flash(__('test_created_success'), 'success');
        header("Location: index.php?route=admin_course");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        die("Ошибка создания теста: " . $e->getMessage());
    }
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
normalize_course_week_numbers($db, (int) $course['id']);
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = __('create_test_title');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>🧪 <?= __('create_test_title') ?></h1>
    <div class="breadcrumb"><?= __('auto_check_test') ?></div>
  </div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm"><?= __('back_btn') ?></a>
</div>

<div class="card">
  <form method="POST" id="test-form">
    <?= csrf_input() ?>
    <div class="grid grid-cols-2 gap-5">
      <div class="form-group">
        <label class="form-label"><?= __('week') ?></label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>"><?= __('week') ?> <?= $week['number'] ?>: <?= htmlspecialchars(format_week_title($week['title'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('time_limit_hint') ?></label>
        <input type="number" name="time_limit" class="form-control" min="0" value="0">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('test_name') ?></label>
      <input type="text" name="title" class="form-control" placeholder="<?= __('test_name_placeholder') ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('description_lbl') ?></label>
      <textarea name="description" class="form-control" rows="2" placeholder="<?= __('test_desc_placeholder') ?>"></textarea>
    </div>
    <div class="grid grid-cols-3 gap-5 mb-5">
      <div class="form-group mb-0">
        <label class="form-label"><?= __('visibility') ?></label>
        <select name="visible" class="form-control">
          <option value="1"><?= __('visible_short') ?></option>
          <option value="0"><?= __('hidden') ?></option>
        </select>
      </div>
      <div class="form-group mb-0">
        <label class="form-label"><?= __('show_answers_lbl') ?></label>
        <select name="show_answers" class="form-control">
          <option value="1"><?= __('yes_after_passing') ?></option>
          <option value="0"><?= __('no_short') ?></option>
        </select>
      </div>
      <div class="form-group mb-0">
        <label class="form-label"><?= __('open_date_lbl') ?></label>
        <input type="datetime-local" name="open_date" class="form-control">
      </div>
    </div>

    <!-- Вопросы -->
    <div class="separator">
      <div class="flex items-center justify-between mb-4">
        <div class="font-bold text-lg">❓ <?= __('questions_title') ?></div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addQuestion()"><?= __('add_question_btn') ?></button>
      </div>
      <div id="questions-container"></div>
    </div>

    <div class="flex gap-4 mt-5">
      <button type="submit" class="btn btn-primary">✅ <?= __('create_test_title') ?></button>
      <a href="index.php?route=admin_course" class="btn btn-secondary"><?= __('cancel') ?></a>
    </div>
  </form>
</div>

<script>
let qIndex = 0;

function addQuestion() {
  const container = document.getElementById('questions-container');
  const qi = qIndex++;
  const div = document.createElement('div');
  div.className = 'question-block';
  div.id = `q-block-${qi}`;
  div.innerHTML = `
    <input type="hidden" name="q_idx[]" value="${qi}">
    <div class="flex items-center justify-between mb-3">
      <div class="font-bold q-label"><?= __('question') ?></div>
      <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.question-block').remove(); updateLabels()">✕ <?= __('delete') ?></button>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('question_text') ?></label>
      <input type="text" name="q_text[]" class="form-control" placeholder="<?= __('enter_question') ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label"><?= __('item_type') ?></label>
      <select name="q_type[]" class="form-control" onchange="updateOptions(this, ${qi})">
        <option value="single"><?= __('type_single') ?></option>
        <option value="multiple"><?= __('type_multiple') ?></option>
        <option value="text"><?= __('type_text_exact') ?></option>
      </select>
    </div>
    <div id="opts-${qi}">
      <label class="form-label"><?= __('answer_options') ?> <span class="text-muted" style="font-weight:400"><?= __('mark_correct_hint') ?></span></label>
      <div id="opts-list-${qi}"></div>
      <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addOption(${qi})" id="add-opt-btn-${qi}"><?= __('add_option_btn') ?></button>
    </div>
    <div id="text-hint-${qi}" style="display:none" class="text-sm text-muted p-4 mt-3" style="background:hsl(var(--muted));border-radius:var(--radius)">
      <?= __('text_hint_comma') ?>
      <input type="text" name="opt_${qi}[]" class="form-control mt-2" placeholder="<?= __('correct_answer_alt') ?>">
    </div>
  `;
  container.appendChild(div);
  addOption(qi);
  addOption(qi);
  updateLabels();
}

function addOption(qi) {
  const list = document.getElementById(`opts-list-${qi}`);
  const idx = list.children.length;
  const row = document.createElement('div');
  row.className = 'flex items-center gap-2 mb-2';
  row.innerHTML = `
    <input type="checkbox" name="correct_${qi}[]" value="${idx}" style="accent-color:hsl(var(--primary));width:18px;height:18px">
    <input type="text" name="opt_${qi}[]" class="form-control" placeholder="<?= __('option_placeholder') ?>">
    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">✕</button>
  `;
  list.appendChild(row);
}

function updateOptions(sel, qi) {
  const isText = sel.value === 'text';
  document.getElementById(`opts-${qi}`).style.display = isText ? 'none' : '';
  document.getElementById(`text-hint-${qi}`).style.display = isText ? '' : 'none';
}

function updateLabels() {
    const labels = document.querySelectorAll('.q-label');
    labels.forEach((l, i) => l.textContent = '<?= __('question') ?> ' + (i + 1));
}

addQuestion();
</script>

<?php include 'footer.php'; ?>
