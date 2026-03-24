<?php
// pages/admin/student_detail.php



$user = login_required(['teacher', 'admin']);

$uid = $_GET['uid'] ?? null;
if (!$uid) {
    header("Location: index.php?route=admin_students");
    exit;
}

// Получаем студента
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$uid]);
$student = $stmt->fetch();

if (!$student) {
    die("Студент не найден.");
}

// Получаем задания студента
$stmt = $db->prepare("SELECT s.*, a.title as assignment_title 
                      FROM submissions s 
                      JOIN assignments a ON s.assignment_id = a.id 
                      WHERE s.user_id = ? 
                      ORDER BY s.submitted_at DESC");
$stmt->execute([$uid]);
$submissions = $stmt->fetchAll();

// Получаем тесты студента
$stmt = $db->prepare("SELECT ts.*, t.title as test_title 
                      FROM test_submissions ts 
                      JOIN tests t ON ts.test_id = t.id 
                      WHERE ts.user_id = ? AND ts.finished_at IS NOT NULL 
                      ORDER BY ts.finished_at DESC");
$stmt->execute([$uid]);
$test_submissions = $stmt->fetchAll();

// Обработка блокировки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] == 'admin' && isset($_POST['toggle_active'])) {
    $new_status = $student['is_active'] ? 0 : 1;
    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->execute([$new_status, $uid]);
    set_flash('Статус пользователя изменен.', 'success');
    header("Location: index.php?route=admin_student_detail&uid=$uid");
    exit;
}

$page_title = 'Профиль студента';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>👤 <?= htmlspecialchars($student['full_name']) ?></h1>
    <div class="breadcrumb">@<?= htmlspecialchars($student['username']) ?> · <?= htmlspecialchars($student['email']) ?></div>
  </div>
  <a href="index.php?route=admin_students" class="btn btn-secondary btn-sm">← Назад</a>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start">
  <div class="card">
    <div style="text-align:center;padding:16px 0">
      <div style="width:72px;height:72px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:700;margin:0 auto">
        <?= mb_substr($student['full_name'], 0, 1) ?>
      </div>
      <div style="font-weight:700;font-size:1.1rem;margin-top:12px"><?= htmlspecialchars($student['full_name']) ?></div>
      <div style="color:var(--muted);font-size:.85rem">@<?= htmlspecialchars($student['username']) ?></div>
      <div style="color:var(--muted);font-size:.82rem;margin-top:4px"><?= htmlspecialchars($student['email']) ?></div>
      <div style="margin-top:12px">
        <span class="badge <?= $student['is_active'] ? 'badge-reviewed' : 'badge-revision' ?>">
          <?= $student['is_active'] ? 'Активен' : 'Заблокирован' ?>
        </span>
      </div>
      <div style="font-size:.75rem;color:var(--muted);margin-top:8px">
        Зарегистрирован <?= date('d.m.Y', strtotime($student['created_at'])) ?>
      </div>
    </div>
    <?php if ($user['role'] == 'admin'): ?>
    <form method="POST" style="margin-top:12px">
      <input type="hidden" name="toggle_active" value="1">
      <button type="submit" class="btn <?= $student['is_active'] ? 'btn-danger' : 'btn-success' ?>" style="width:100%;justify-content:center" onclick="return confirm('Уверены?')">
        <?= $student['is_active'] ? '🚫 Заблокировать' : '✅ Разблокировать' ?>
      </button>
    </form>
    <?php endif; ?>
  </div>

  <div>
    <div class="card" style="margin-bottom:20px">
      <div class="card-title">📝 Сданные задания</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Задание</th><th>Сдано</th><th>Статус</th><th>Оценка</th><th>Комментарий</th></tr>
          </thead>
          <tbody>
            <?php foreach ($submissions as $sub): ?>
            <tr>
              <td style="font-weight:600"><?= htmlspecialchars($sub['assignment_title']) ?></td>
              <td style="font-size:.82rem;color:var(--muted)"><?= date('d.m.Y H:i', strtotime($sub['submitted_at'])) ?></td>
              <td>
                <?php if ($sub['status'] == 'pending'): ?><span class="badge badge-pending">⏳</span>
                <?php elseif ($sub['status'] == 'reviewed'): ?><span class="badge badge-reviewed">✅</span>
                <?php else: ?><span class="badge badge-revision">🔄</span><?php endif; ?>
              </td>
              <td style="font-weight:700;text-align:center"><?= $sub['grade'] !== null ? $sub['grade'] : '—' ?></td>
              <td style="font-size:.82rem;color:var(--muted)">
                <?= $sub['comment'] ? (mb_strlen($sub['comment']) > 60 ? htmlspecialchars(mb_substr($sub['comment'], 0, 60)) . '...' : htmlspecialchars($sub['comment'])) : '—' ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($submissions)): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--muted)">Заданий не сдавал</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-title">🧪 Результаты тестов</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Тест</th><th>Дата</th><th>Результат</th><th>%</th></tr>
          </thead>
          <tbody>
            <?php foreach ($test_submissions as $ts): 
                $pct = ($ts['max_score'] > 0) ? intval($ts['score'] / $ts['max_score'] * 100) : 0;
            ?>
            <tr>
              <td style="font-weight:600"><?= htmlspecialchars($ts['test_title']) ?></td>
              <td style="font-size:.82rem;color:var(--muted)"><?= date('d.m.Y H:i', strtotime($ts['finished_at'])) ?></td>
              <td style="text-align:center"><?= $ts['score'] ?>/<?= $ts['max_score'] ?></td>
              <td style="font-weight:700;color:<?= $pct >= 75 ? 'var(--success)' : ($pct >= 50 ? 'var(--accent)' : 'var(--danger)') ?>"><?= $pct ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($test_submissions)): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--muted)">Тестов не проходил</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
