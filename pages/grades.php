<?php
// pages/grades.php
require_once 'config.php';
require_once 'auth.php';

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

$rows = [];
$total_grade = 0;
$total_count = 0;

foreach ($weeks as $week) {
    // Получаем задания недели
    $stmt = $db->prepare("SELECT * FROM assignments WHERE week_id = ? AND visible = 1 ORDER BY created_at");
    $stmt->execute([$week['id']]);
    $assignments = $stmt->fetchAll();
    foreach ($assignments as $a) {
        $stmt_sub = $db->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND user_id = ?");
        $stmt_sub->execute([$a['id'], $user['id']]);
        $sub = $stmt_sub->fetch();
        
        $rows[] = [
            'type' => 'assignment',
            'week' => $week,
            'item' => $a,
            'submission' => $sub
        ];
        
        if ($sub && $sub['grade'] !== null) {
            $total_grade += $sub['grade'];
            $total_count++;
        }
    }
    
    // Получаем тесты недели
    $stmt = $db->prepare("SELECT * FROM tests WHERE week_id = ? AND visible = 1 ORDER BY created_at");
    $stmt->execute([$week['id']]);
    $tests = $stmt->fetchAll();
    foreach ($tests as $t) {
        $stmt_tsub = $db->prepare("SELECT * FROM test_submissions WHERE test_id = ? AND user_id = ? AND finished_at IS NOT NULL ORDER BY id DESC LIMIT 1");
        $stmt_tsub->execute([$t['id'], $user['id']]);
        $tsub = $stmt_tsub->fetch();
        
        $rows[] = [
            'type' => 'test',
            'week' => $week,
            'item' => $t,
            'tsub' => $tsub
        ];
        
        if ($tsub && $tsub['max_score'] > 0) {
            $total_grade += intval($tsub['score'] / $tsub['max_score'] * 100);
            $total_count++;
        }
    }
}

$avg = $total_count > 0 ? round($total_grade / $total_count) : 0;

$total_items = count($rows);
$done_items = 0;
foreach ($rows as $row) {
    if ($row['type'] == 'assignment' && $row['submission']) $done_items++;
    if ($row['type'] == 'test' && $row['tsub']) $done_items++;
}
$pct = $total_items > 0 ? intval($done_items / $total_items * 100) : 0;

$page_title = 'Мои оценки';
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>🏆 Мои оценки</h1>
    <div class="breadcrumb">Прогресс выполнения курса</div>
  </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-icon">📊</div>
    <div class="stat-label">Средний балл</div>
    <div class="stat-value" style="color:<?= $avg >= 75 ? 'var(--success)' : ($avg >= 50 ? 'var(--accent)' : 'var(--danger)') ?>">
      <?= $avg ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Проверено заданий</div>
    <div class="stat-value">
        <?php 
        $reviewed_count = 0;
        foreach($rows as $r) if($r['type'] == 'assignment' && $r['submission'] && $r['submission']['status'] != 'pending') $reviewed_count++;
        echo $reviewed_count;
        ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🧪</div>
    <div class="stat-label">Тестов пройдено</div>
    <div class="stat-value">
        <?php 
        $tests_count = 0;
        foreach($rows as $r) if($r['type'] == 'test' && $r['tsub']) $tests_count++;
        echo $tests_count;
        ?>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;margin-bottom:8px">
    <span style="font-weight:600">Общий прогресс курса</span>
    <span style="font-weight:800;color:var(--primary)"><?= $pct ?>%</span>
  </div>
  <div class="progress-wrap">
    <div class="progress-bar" style="width:<?= $pct ?>%"></div>
  </div>
  <div class="progress-label"><?= $done_items ?> из <?= $total_items ?> элементов выполнено</div>
</div>

<div class="card">
  <div class="card-title">📋 Подробные результаты</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Неделя</th>
          <th>Название</th>
          <th>Тип</th>
          <th>Статус</th>
          <th>Оценка</th>
          <th>Комментарий</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td>
            <span style="font-weight:700;color:var(--primary)"><?= $row['week']['number'] ?></span>
          </td>
          <td style="font-weight:600">
            <?php if ($row['type'] == 'assignment'): ?>
              <a href="index.php?route=assignment_detail&aid=<?= $row['item']['id'] ?>" style="color:var(--text);text-decoration:none">
                <?= htmlspecialchars($row['item']['title']) ?>
              </a>
            <?php else: ?>
                <?= htmlspecialchars($row['item']['title']) ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($row['type'] == 'assignment'): ?>
              <span class="badge badge-info">📝 Задание</span>
            <?php else: ?>
              <span class="badge badge-secondary">🧪 Тест</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($row['type'] == 'assignment'): ?>
              <?php if ($row['submission']): ?>
                <?php if ($row['submission']['status'] == 'pending'): ?>
                  <span class="badge badge-pending">⏳ На проверке</span>
                <?php elseif ($row['submission']['status'] == 'reviewed'): ?>
                  <span class="badge badge-reviewed">✅ Проверено</span>
                <?php else: ?>
                  <span class="badge badge-revision">🔄 Доработать</span>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-secondary">Не сдано</span>
              <?php endif; ?>
            <?php else: ?>
              <?php if ($row['tsub']): ?>
                <span class="badge badge-reviewed">✅ Пройдено</span>
              <?php else: ?>
                <span class="badge badge-secondary">Не пройдено</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="text-align:center">
            <?php if ($row['type'] == 'assignment' && $row['submission'] && $row['submission']['grade'] !== null): ?>
              <strong style="font-size:1.05rem;color:<?= $row['submission']['grade'] >= 75 ? 'var(--success)' : ($row['submission']['grade'] >= 50 ? '#c47a00' : 'var(--danger)') ?>">
                <?= $row['submission']['grade'] ?>/100
              </strong>
            <?php elseif ($row['type'] == 'test' && $row['tsub']): 
                $pct_t = ($row['tsub']['max_score'] > 0) ? intval($row['tsub']['score'] / $row['tsub']['max_score'] * 100) : 0;
            ?>
              <strong style="font-size:1.05rem;color:<?= $pct_t >= 75 ? 'var(--success)' : ($pct_t >= 50 ? '#c47a00' : 'var(--danger)') ?>">
                <?= $row['tsub']['score'] ?>/<?= $row['tsub']['max_score'] ?>
              </strong>
            <?php else: ?>
              <span style="color:var(--muted)">—</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--muted);max-width:200px">
            <?php if ($row['type'] == 'assignment' && $row['submission'] && !empty($row['submission']['comment'])): ?>
              <?= htmlspecialchars(mb_substr($row['submission']['comment'], 0, 80)) . (mb_strlen($row['submission']['comment']) > 80 ? '...' : '') ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr>
          <td colspan="6" style="text-align:center;color:var(--muted);padding:30px">Нет элементов</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
