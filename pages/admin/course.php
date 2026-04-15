<?php
// pages/admin/course.php

$user = login_required(['teacher', 'admin']);

function upload_course_pdf(array $file, string $prefix): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [null, __('pdf_upload_failed')];
    }

    if (($file['size'] ?? 0) > 25 * 1024 * 1024) {
        return [null, __('pdf_upload_too_large')];
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($extension !== 'pdf' || !is_uploaded_file($file['tmp_name'])) {
        return [null, __('pdf_upload_invalid')];
    }

    $signature = file_get_contents($file['tmp_name'], false, null, 0, 4);
    if ($signature !== '%PDF') {
        return [null, __('pdf_upload_invalid')];
    }

    $fileName = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $fileName)) {
        return [null, __('pdf_save_failed')];
    }

    return [$fileName, null];
}

$stmt = $db->query("SELECT * FROM courses LIMIT 1");
$course = $stmt->fetch();

if (!$course) {
    die(__('no_data'));
}

normalize_course_week_numbers($db, (int) $course['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_course') {
        $glossaryPdfPath = $course['glossary_pdf_path'] ?? null;
        $syllabusPdfPath = $course['syllabus_pdf_path'] ?? null;

        if (isset($_FILES['glossary_pdf'])) {
            [$uploadedGlossaryPdf, $glossaryPdfError] = upload_course_pdf($_FILES['glossary_pdf'], 'glossary');
            if ($glossaryPdfError !== null) {
                set_flash($glossaryPdfError, 'danger');
                header("Location: index.php?route=admin_course");
                exit;
            }
            if ($uploadedGlossaryPdf !== null) {
                $glossaryPdfPath = $uploadedGlossaryPdf;
            }
        }

        if (isset($_FILES['syllabus_pdf'])) {
            [$uploadedSyllabusPdf, $syllabusPdfError] = upload_course_pdf($_FILES['syllabus_pdf'], 'syllabus');
            if ($syllabusPdfError !== null) {
                set_flash($syllabusPdfError, 'danger');
                header("Location: index.php?route=admin_course");
                exit;
            }
            if ($uploadedSyllabusPdf !== null) {
                $syllabusPdfPath = $uploadedSyllabusPdf;
            }
        }

        $stmt = $db->prepare(
            "UPDATE courses
             SET title = ?, description = ?, goals = ?, objectives = ?, content_info = ?, glossary_pdf_path = ?, syllabus_pdf_path = ?
             WHERE id = ?"
        );
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['goals'],
            $_POST['objectives'],
            $_POST['content_info'],
            $glossaryPdfPath,
            $syllabusPdfPath,
            $course['id'],
        ]);
        set_flash(__('course_updated'), 'success');
    } elseif ($action === 'add_week') {
        $title = trim($_POST['week_title'] ?? '');
        if ($title !== '') {
            $stmt = $db->prepare("SELECT MAX(number) FROM weeks WHERE course_id = ?");
            $stmt->execute([$course['id']]);
            $maxNumber = (int) ($stmt->fetchColumn() ?: 0);
            $stmt = $db->prepare("INSERT INTO weeks (course_id, number, title) VALUES (?, ?, ?)");
            $stmt->execute([$course['id'], $maxNumber + 1, $title]);
            normalize_course_week_numbers($db, (int) $course['id']);
            set_flash(__('week_added'), 'success');
        }
    } elseif ($action === 'delete_week') {
        $weekId = (int) ($_POST['week_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
        $stmt->execute([$weekId]);
        normalize_course_week_numbers($db, (int) $course['id']);
        set_flash(__('week_deleted'), 'success');
    }

    header("Location: index.php?route=admin_course");
    exit;
}

$stmt = $db->prepare("SELECT * FROM courses WHERE id = ? LIMIT 1");
$stmt->execute([$course['id']]);
$course = $stmt->fetch();
$courseDocuments = get_course_documents($course);

$stmt = $db->prepare("SELECT * FROM weeks WHERE course_id = ? ORDER BY number, id");
$stmt->execute([$course['id']]);
$weeks = $stmt->fetchAll();

foreach ($weeks as &$week) {
    $stmtMaterials = $db->prepare("SELECT * FROM materials WHERE week_id = ? ORDER BY created_at");
    $stmtMaterials->execute([$week['id']]);
    $week['materials'] = $stmtMaterials->fetchAll();

    $stmtAssignments = $db->prepare(
        "SELECT a.*, (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) AS sub_count
         FROM assignments a
         WHERE week_id = ?
         ORDER BY created_at"
    );
    $stmtAssignments->execute([$week['id']]);
    $week['assignments'] = $stmtAssignments->fetchAll();

    $stmtTests = $db->prepare("SELECT * FROM tests WHERE week_id = ? ORDER BY created_at");
    $stmtTests->execute([$week['id']]);
    $week['tests'] = $stmtTests->fetchAll();
    foreach ($week['tests'] as &$test) {
        $stmtQuestionCount = $db->prepare("SELECT COUNT(*) FROM test_questions WHERE test_id = ?");
        $stmtQuestionCount->execute([$test['id']]);
        $test['q_count'] = $stmtQuestionCount->fetchColumn();
    }
    unset($test);

    $stmtDiscussionCount = $db->prepare("SELECT COUNT(*) FROM discussion_topics WHERE week_id = ?");
    $stmtDiscussionCount->execute([$week['id']]);
    $week['discussion_topics_count'] = (int) $stmtDiscussionCount->fetchColumn();
}
unset($week);

$page_title = __('course_mgmt');
include 'header.php';
?>

<div class="topbar">
  <div>
    <h1>📚 <?= __('course_mgmt') ?></h1>
    <div class="breadcrumb"><?= htmlspecialchars($course['title']) ?></div>
  </div>
  <div class="flex gap-3">
    <a href="index.php?route=admin_add_material" class="btn btn-secondary btn-sm"><?= __('add_material_btn') ?></a>
    <a href="index.php?route=admin_add_assignment" class="btn btn-secondary btn-sm"><?= __('add_assignment_btn') ?></a>
    <a href="index.php?route=admin_add_test" class="btn btn-primary btn-sm"><?= __('add_test_btn') ?></a>
  </div>
</div>

<div class="card mb-5">
  <div class="card-title">📋 <?= __('course_info_title') ?></div>
  <form method="POST" enctype="multipart/form-data">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="update_course">
    <div class="form-group">
      <label class="form-label"><?= __('course_title_lbl') ?></label>
      <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($course['title']) ?>" required>
    </div>
    <div class="grid grid-cols-2 gap-5">
      <div class="form-group">
        <label class="form-label"><?= __('description_lbl') ?></label>
        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($course['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('goals_lbl') ?></label>
        <textarea name="goals" class="form-control" rows="3"><?= htmlspecialchars($course['goals'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('objectives_lbl') ?></label>
        <textarea name="objectives" class="form-control" rows="3"><?= htmlspecialchars($course['objectives'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('learning_content_lbl') ?></label>
        <textarea name="content_info" class="form-control" rows="3"><?= htmlspecialchars($course['content_info'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-5 mt-4">
      <div class="form-group">
        <label class="form-label"><?= __('glossary_pdf') ?></label>
        <input type="file" name="glossary_pdf" class="form-control" accept=".pdf,application/pdf">
        <div class="form-hint"><?= __('pdf_upload_hint') ?></div>
        <?php if (!empty($course['glossary_pdf_path'])): ?>
          <div class="form-hint"><?= __('current_file') ?> <a href="<?= htmlspecialchars(build_upload_url($course['glossary_pdf_path'])) ?>" target="_blank" rel="noopener"><?= __('open_glossary') ?></a></div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label class="form-label"><?= __('syllabus_pdf') ?></label>
        <input type="file" name="syllabus_pdf" class="form-control" accept=".pdf,application/pdf">
        <div class="form-hint"><?= __('pdf_upload_hint') ?></div>
        <?php if (!empty($course['syllabus_pdf_path'])): ?>
          <div class="form-hint"><?= __('current_file') ?> <a href="<?= htmlspecialchars(build_upload_url($course['syllabus_pdf_path'])) ?>" target="_blank" rel="noopener"><?= __('open_syllabus') ?></a></div>
        <?php endif; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-primary btn-sm">💾 <?= __('save') ?></button>
  </form>
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

<?php foreach ($weeks as $week): ?>
<div class="week-block mb-3">
  <div class="week-header" style="cursor:default">
    <div class="week-title">
      <div class="week-num"><?= $week['number'] ?></div>
      <?= htmlspecialchars(format_week_title($week['title'])) ?>
    </div>
    <div class="forum-week-actions">
      <a href="index.php?route=week_discussion&wid=<?= $week['id'] ?>&from=admin_course" class="btn btn-secondary btn-sm">
        💬 <?= __('open_discussion') ?>
        <?php if (!empty($week['discussion_topics_count'])): ?>
          <span class="forum-count-badge"><?= $week['discussion_topics_count'] ?></span>
        <?php endif; ?>
      </a>
      <form method="POST" onsubmit="return confirm('<?= __('confirm_delete_week') ?>')">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="delete_week">
        <input type="hidden" name="week_id" value="<?= $week['id'] ?>">
        <button type="submit" class="btn btn-danger btn-sm"><?= __('delete_week_btn') ?></button>
      </form>
    </div>
  </div>
  <div class="week-body" style="display:block">
    <?php if (!empty($week['materials'])): ?>
    <div class="mb-3">
      <div class="section-label">📚 <?= __('materials') ?></div>
      <?php foreach ($week['materials'] as $material): ?>
      <div class="content-row">
        <div class="content-row-info">
          <span class="font-semibold text-sm"><?= htmlspecialchars($material['title']) ?></span>
          <span class="badge badge-secondary"><?= $material['material_type'] ?></span>
          <?php if (!$material['visible']): ?><span class="badge badge-revision"><?= __('hidden') ?></span><?php endif; ?>
        </div>
        <div class="content-row-actions">
          <a href="index.php?route=admin_edit_material&mid=<?= $material['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
          <form method="POST" action="index.php?route=admin_delete_material" style="display:inline" onsubmit="return confirm('<?= __('confirm_delete_material') ?>')">
            <?= csrf_input() ?>
            <input type="hidden" name="mid" value="<?= $material['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($week['assignments'])): ?>
    <div class="mb-3">
      <div class="section-label">📝 <?= __('assignments') ?></div>
      <?php foreach ($week['assignments'] as $assignment): ?>
      <div class="content-row">
        <div class="content-row-info">
          <span class="font-semibold text-sm"><?= htmlspecialchars($assignment['title']) ?></span>
          <?php if (!$assignment['visible']): ?><span class="badge badge-revision"><?= __('hidden') ?></span><?php endif; ?>
          <?php if ($assignment['deadline']): ?><span class="text-xs text-muted"><?= __('deadline') ?>: <?= date('d.m.Y', strtotime($assignment['deadline'])) ?></span><?php endif; ?>
          <span class="text-xs text-muted"><?= $assignment['sub_count'] ?> <?= __('submissions_count') ?></span>
        </div>
        <div class="content-row-actions">
          <a href="index.php?route=admin_edit_assignment&aid=<?= $assignment['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
          <form method="POST" action="index.php?route=admin_delete_assignment" style="display:inline" onsubmit="return confirm('<?= __('confirm_delete_assignment') ?>')">
            <?= csrf_input() ?>
            <input type="hidden" name="aid" value="<?= $assignment['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($week['tests'])): ?>
    <div>
      <div class="section-label">🧪 <?= __('tests') ?></div>
      <?php foreach ($week['tests'] as $test): ?>
      <div class="content-row">
        <div class="content-row-info">
          <span class="font-semibold text-sm"><?= htmlspecialchars($test['title']) ?></span>
          <?php if (!$test['visible']): ?><span class="badge badge-revision"><?= __('hidden') ?></span><?php endif; ?>
          <span class="text-xs text-muted"><?= $test['q_count'] ?> <?= __('questions_short') ?></span>
        </div>
        <div class="content-row-actions">
          <a href="index.php?route=admin_edit_test&tid=<?= $test['id'] ?>" class="btn btn-secondary btn-sm">✏️</a>
          <form method="POST" action="index.php?route=admin_delete_test" style="display:inline" onsubmit="return confirm('<?= __('confirm_delete_test') ?>')">
            <?= csrf_input() ?>
            <input type="hidden" name="tid" value="<?= $test['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">🗑</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($week['materials']) && empty($week['assignments']) && empty($week['tests'])): ?>
    <p class="empty-state"><?= __('no_content_added') ?></p>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="card">
  <div class="card-title">➕ <?= __('add_week_title') ?></div>
  <form method="POST" class="flex gap-4 items-end">
    <?= csrf_input() ?>
    <input type="hidden" name="action" value="add_week">
    <div class="form-group mb-0 flex-1">
      <label class="form-label"><?= __('week_title_lbl') ?></label>
      <input type="text" name="week_title" class="form-control" placeholder="<?= __('week_title_placeholder') ?>" required>
    </div>
    <button type="submit" class="btn btn-primary"><?= __('add') ?></button>
  </form>
</div>

<?php include 'footer.php'; ?>
