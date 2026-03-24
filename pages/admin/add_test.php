<?php
// pages/admin/add_test.php



$user = login_required(['teacher', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            
            // Внимание: в JS мы используем порядковый индекс qi как часть имени поля opt_qi
            // При отправке формы индексы могут не совпадать с i, если вопросы удалялись.
            // Но в PHP $_POST['q_text'] будет последовательным массивом.
            // Однако в JS qi присваивается один раз и не меняется.
            // Нам нужно передать исходный индекс qi в форму, чтобы сопоставить его здесь.
            // Исправлю JS ниже, чтобы передавать qi.
            
            $orig_qi = $_POST['q_idx'][$idx];
            $opts = $_POST['opt_' . $orig_qi] ?? [];
            $corrects = $_POST['correct_' . $orig_qi] ?? [];
            
            foreach ($opts as $j => $opt_text) {
                if ($type == 'text') {
                    // Для текстового типа все введенные варианты считаются правильными (синонимы)
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
        set_flash('Тест успешно создан!', 'success');
        header("Location: index.php?route=admin_course");
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        die("Ошибка создания теста: " . $e->getMessage());
    }
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();
$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$page_title = 'Создать тест';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>🧪 Создать тест</h1>
    <div class="breadcrumb">Тест с автоматической проверкой</div>
  </div>
  <a href="index.php?route=admin_course" class="btn btn-secondary btn-sm">← Назад</a>
</div>

<div class="card">
  <form method="POST" id="test-form">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Неделя</label>
        <select name="week_id" class="form-control" required>
          <?php foreach ($weeks as $week): ?>
          <option value="<?= $week['id'] ?>">Неделя <?= $week['number'] ?>: <?= htmlspecialchars($week['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Лимит времени (мин, 0 = без лимита)</label>
        <input type="number" name="time_limit" class="form-control" min="0" value="0">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Название теста</label>
      <input type="text" name="title" class="form-control" placeholder="Лексический тест №1" required>
    </div>
    <div class="form-group">
      <label class="form-label">Описание</label>
      <textarea name="description" class="form-control" rows="2" placeholder="Инструкции для студентов..."></textarea>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Видимость</label>
        <select name="visible" class="form-control">
          <option value="1">Видимо</option>
          <option value="0">Скрыто</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Показывать ответы</label>
        <select name="show_answers" class="form-control">
          <option value="1">Да, после прохождения</option>
          <option value="0">Нет</option>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Дата открытия</label>
        <input type="datetime-local" name="open_date" class="form-control">
      </div>
    </div>

    <!-- Вопросы -->
    <div style="border-top:2px solid var(--border);padding-top:20px;margin-top:4px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
        <div style="font-weight:700;font-size:1.05rem">❓ Вопросы</div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addQuestion()">+ Добавить вопрос</button>
      </div>
      <div id="questions-container"></div>
    </div>

    <div style="margin-top:20px;display:flex;gap:12px">
      <button type="submit" class="btn btn-primary">✅ Создать тест</button>
      <a href="index.php?route=admin_course" class="btn btn-secondary">Отмена</a>
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
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
      <div style="font-weight:700" class="q-label">Вопрос</div>
      <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.question-block').remove(); updateLabels()">✕ Удалить</button>
    </div>
    <div class="form-group">
      <label class="form-label">Текст вопроса</label>
      <input type="text" name="q_text[]" class="form-control" placeholder="Введите вопрос..." required>
    </div>
    <div class="form-group">
      <label class="form-label">Тип</label>
      <select name="q_type[]" class="form-control" onchange="updateOptions(this, ${qi})">
        <option value="single">Один правильный ответ</option>
        <option value="multiple">Несколько правильных ответов</option>
        <option value="text">Ввод текста (точное совпадение)</option>
      </select>
    </div>
    <div id="opts-${qi}">
      <label class="form-label">Варианты ответов <span style="font-weight:400;color:var(--muted)">(отметьте правильные)</span></label>
      <div id="opts-list-${qi}"></div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="addOption(${qi})" id="add-opt-btn-${qi}" style="margin-top:8px">+ Добавить вариант</button>
    </div>
    <div id="text-hint-${qi}" style="display:none;font-size:.85rem;color:var(--muted);padding:10px;background:#f4f6fb;border-radius:8px">
      Введите правильные ответы через запятую ниже:
      <input type="text" name="opt_${qi}[]" class="form-control" style="margin-top:8px" placeholder="правильный ответ, альтернатива">
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
  row.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:6px';
  row.innerHTML = `
    <input type="checkbox" name="correct_${qi}[]" value="${idx}" style="accent-color:var(--primary);width:18px;height:18px">
    <input type="text" name="opt_${qi}[]" class="form-control" placeholder="Вариант ответа...">
    <button type="button" class="btn btn-danger btn-sm" style="padding:5px 10px" onclick="this.parentElement.remove()">✕</button>
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
    labels.forEach((l, i) => l.textContent = 'Вопрос ' + (i + 1));
}

addQuestion();
</script>

<?php include 'footer.php'; ?>
