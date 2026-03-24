<?php
// pages/admin/course.php



$user = login_required(['teacher', 'admin']);

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_course') {
        $stmt = $db->prepare("UPDATE courses SET title = ?, description = ?, goals = ?, objectives = ?, content_info = ? WHERE id = 1");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['goals'], $_POST['objectives'], $_POST['content_info']]);
        set_flash('Информация о курсе обновлена!', 'success');
    } 
    elseif ($action == 'add_week') {
        $title = trim($_POST['week_title'] ?? '');
        if ($title) {
            $stmt = $db->query("SELECT MAX(number) FROM weeks");
            $max_num = $stmt->fetchColumn() ?: 0;
            $stmt = $db->prepare("INSERT INTO weeks (course_id, number, title) VALUES (1, ?, ?)");
            $stmt->execute([$max_num + 1, $title]);
            set_flash('Неделя добавлена!', 'success');
        }
    }
    elseif ($action == 'delete_week') {
        $week_id = intval($_POST['week_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
        $stmt->execute([$week_id]);
        set_flash('Неделя удалена!', 'success');
    }
    
    header("Location: index.php?route=admin_course");
    exit;
}

// Данные курса
$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

foreach ($weeks as &$week) {
    $stmt_m = $db->prepare("SELECT * FROM materials WHERE week_id = ? ORDER BY created_at");
    $stmt_m->execute([$week['id']]);
    $week['materials'] = $stmt_m->fetchAll();
    
    $stmt_a = $db->prepare("SELECT a.*, (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as sub_count FROM assignments a WHERE week_id = ? ORDER BY created_at");
    $stmt_a->execute([$week['id']]);
    $week['assignments'] = $stmt_a->fetchAll();
    
    $stmt_t = $db->prepare("SELECT * FROM tests WHERE week_id = ? ORDER BY created_at");
    $stmt_t->execute([$week['id']]);
    $week['tests'] = $stmt_t->fetchAll();
    foreach ($week['tests'] as &$t) {
        $stmt_q = $db->prepare("SELECT COUNT(*) FROM test_questions WHERE test_id = ?");
        $stmt_q->execute([$t['id']]);
        $t['q_count'] = $stmt_q->fetchColumn();
    }
}

$page_title = 'Управление курсом';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📚 Управление курсом</h1>
    <div class="breadcrumb"><?= htmlspecialchars($course['title']) ?></div>
  </div>
  <div style="display:flex;gap:10px">
    <a href="index.php?route=admin_add_material" class="btn btn-secondary btn-sm">+ Материал</a>
    <a href="index.php?route=admin_add_assignment" class="btn btn-secondary btn-sm">+ Задание</a>
    <a href="index.php?route=admin_add_test" class="btn btn-primary btn-sm">+ Тест</a>
  </div>
</div>

<!-- Информация о курсе -->
<div class="card" style="margin-bottom:20px">
  <div class="card-title">📋 Информация о курсе</div>
  <form method="POST">
    <input type="hidden" name="action" value="update_course">
    <div class="form-group">
      <label class="form-label">Название курса</label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($course['title']) ?>" required>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="form-group">
        <label class="form-label">Описание</label>
        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Цели</label>
        <textarea name="goals" class="form-control" rows="3"><?= htmlspecialchars($course['goals'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Задачи</label>
        <textarea name="objectives" class="form-control" rows="3"><?= htmlspecialchars($course['objectives'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Содержание обучения</label>
        <textarea name="content_info" class="form-control" rows="3"><?= htmlspecialchars($course['content_info'] ?? '') ?></textarea>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">💾 Сохранить</button>
  </form>
</div>

<!-- Недели и контент -->
<?php foreach ($weeks as $week): ?>
<div class="week-block" style="margin-bottom:14px">
  <div class="week-header" style="cursor:default; justify-content:space-between">
    <div class="week-title">
      <div class="week-num"><?= $week['number'] ?></div>
      <?= htmlspecialchars($week['title']) ?>
    </div>
    <form method="POST" onsubmit="return confirm('Удалить неделю со всем содержимым?')">
      <input type="hidden" name="action" value="delete_week">
      <input type="hidden" name="week_id" value="<?= $week['id'] ?>">
      <button type="submit" class="btn btn-danger btn-sm">Удалить неделю</button>
    </form>
  </div>
  <div class="week-body" style="display:block">
    <!-- Материалы -->
    <?php if (!empty($week['materials'])): ?>
    <div style="margin-bottom:12px">
      <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">📚 Материалы</div>
      <?php foreach ($week['materials'] as $m): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#fafbff;border-radius:8px;border:1px solid var(--border);margin-bottom:6px">
        <div>
          <span style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($m['title']) ?></span>
          <span class="badge badge-secondary" style="margin-left:8px;font-size:.7rem"><?= $m['material_type'] ?></span>
          <?php if (!$m['visible']): ?><span class="badge badge-revision" style="margin-left:4px;font-size:.7rem">Скрыт</span><?php endif; ?>
        </div>
        <div style="display:flex;gap:6px">
          <a href="index.php?route=admin_edit_material&mid=<?= $m['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
          <a href="index.php?route=admin_delete_material&mid=<?= $m['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить материал?')">🗑</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Задания -->
    <?php if (!empty($week['assignments'])): ?>
    <div style="margin-bottom:12px">
      <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">📝 Задания</div>
      <?php foreach ($week['assignments'] as $a): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#fafbff;border-radius:8px;border:1px solid var(--border);margin-bottom:6px">
        <div>
          <span style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($a['title']) ?></span>
          <?php if (!$a['visible']): ?><span class="badge badge-revision" style="margin-left:8px;font-size:.7rem">Скрыто</span><?php endif; ?>
          <?php if ($a['deadline']): ?><span style="font-size:.78rem;color:var(--muted);margin-left:8px">Дедлайн: <?= date('d.m.Y', strtotime($a['deadline'])) ?></span><?php endif; ?>
          <span style="font-size:.78rem;color:var(--muted);margin-left:8px"><?= $a['sub_count'] ?> ответов</span>
        </div>
        <div style="display:flex;gap:6px">
          <a href="index.php?route=admin_edit_assignment&aid=<?= $a['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
          <a href="index.php?route=admin_delete_assignment&aid=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить задание?')">🗑</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Тесты -->
    <?php if (!empty($week['tests'])): ?>
    <div>
      <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">🧪 Тесты</div>
      <?php foreach ($week['tests'] as $t): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:#fafbff;border-radius:8px;border:1px solid var(--border);margin-bottom:6px">
        <div>
          <span style="font-weight:600;font-size:.9rem"><?= htmlspecialchars($t['title']) ?></span>
          <?php if (!$t['visible']): ?><span class="badge badge-revision" style="margin-left:8px;font-size:.7rem">Скрыт</span><?php endif; ?>
          <span style="font-size:.78rem;color:var(--muted);margin-left:8px"><?= $t['q_count'] ?> вопр.</span>
        </div>
        <div style="display:flex;gap:6px">
          <a href="index.php?route=admin_edit_test&tid=<?= $t['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
          <a href="index.php?route=admin_delete_test&tid=<?= $t['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Удалить тест?')">🗑</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($week['materials']) && empty($week['assignments']) && empty($week['tests'])): ?>
    <p style="color:var(--muted);font-size:.85rem;text-align:center">Содержимое не добавлено</p>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- Добавить неделю -->
<div class="card">
  <div class="card-title">➕ Добавить неделю</div>
  <form method="POST" style="display:flex;gap:12px;align-items:flex-end">
    <input type="hidden" name="action" value="add_week">
    <div class="form-group" style="flex:1;margin-bottom:0">
      <label class="form-label">Название недели</label>
      <input type="text" name="week_title" class="form-control" placeholder="Неделя 5: Технические переговоры" required>
    </div>
    <button type="submit" class="btn btn-primary">Добавить</button>
  </form>
</div>

<?php include 'footer.php'; ?>
