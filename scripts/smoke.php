<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

if (!extension_loaded('curl')) {
    fwrite(STDERR, "The curl extension is required.\n");
    exit(1);
}

$baseUrl = 'http://127.0.0.1:8000';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--base-url=')) {
        $baseUrl = rtrim(substr($argument, strlen('--base-url=')), '/');
    }
}

function smoke_note(string $label, bool $ok, string $details = ''): bool {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label;
    if ($details !== '') {
        echo ' - ' . $details;
    }
    echo PHP_EOL;

    return $ok;
}

function smoke_fetch(string $method, string $url, array $data = [], ?string $cookieFile = null): array {
    $curl = curl_init();
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
    ];

    if ($cookieFile !== null) {
        $options[CURLOPT_COOKIEJAR] = $cookieFile;
        $options[CURLOPT_COOKIEFILE] = $cookieFile;
    }

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
    }

    curl_setopt_array($curl, $options);
    $rawResponse = curl_exec($curl);
    if ($rawResponse === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException($error);
    }

    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headers = substr($rawResponse, 0, $headerSize);
    $body = substr($rawResponse, $headerSize);
    curl_close($curl);

    preg_match('/^Location:\s*(.+)$/mi', $headers, $matches);
    $location = isset($matches[1]) ? trim($matches[1]) : null;

    return [
        'status' => $statusCode,
        'headers' => $headers,
        'body' => $body,
        'location' => $location,
    ];
}

function smoke_csrf_from(string $html): string {
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('CSRF token not found');
    }

    return html_entity_decode($matches[1], ENT_QUOTES);
}

function smoke_login(string $baseUrl, string $username, string $password): array {
    $cookieFile = tempnam(sys_get_temp_dir(), 'portal-cookie-');
    if ($cookieFile === false) {
        throw new RuntimeException('Unable to create a cookie file');
    }

    $landing = smoke_fetch('GET', $baseUrl . '/index.php', [], $cookieFile);
    $csrf = smoke_csrf_from($landing['body']);
    $login = smoke_fetch('POST', $baseUrl . '/index.php?route=login', [
        'csrf_token' => $csrf,
        'username' => $username,
        'password' => $password,
    ], $cookieFile);

    return [$cookieFile, $login];
}

$cookies = [];
$insertedAssignmentIds = [];
$insertedTestIds = [];
$allPassed = true;

try {
    $baseProbe = smoke_fetch('GET', $baseUrl . '/index.php');
    $allPassed = smoke_note('Application root responds', $baseProbe['status'] === 200, 'status=' . $baseProbe['status']) && $allPassed;

    $weekId = (int) $db->query('SELECT id FROM weeks ORDER BY id LIMIT 1')->fetchColumn();
    $studentId = (int) $db->query("SELECT id FROM users WHERE username = 'student1'")->fetchColumn();
    if ($weekId <= 0 || $studentId <= 0) {
        throw new RuntimeException('Bootstrap data is missing');
    }

    $suffix = date('YmdHis');
    $insertAssignment = $db->prepare(
        'INSERT INTO assignments (week_id, title, description, deadline, visible, open_date, created_at)
         VALUES (?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP)'
    );
    $insertTest = $db->prepare(
        'INSERT INTO tests (week_id, title, description, time_limit, show_answers, visible, open_date, created_at)
         VALUES (?, ?, ?, ?, 1, 1, ?, CURRENT_TIMESTAMP)'
    );
    $insertQuestion = $db->prepare(
        'INSERT INTO test_questions (test_id, question_text, question_type, order_num)
         VALUES (?, ?, ?, 1)'
    );
    $insertOption = $db->prepare(
        'INSERT INTO test_options (question_id, option_text, is_correct)
         VALUES (?, ?, ?)'
    );

    $insertAssignment->execute([
        $weekId,
        "Smoke Open Assignment $suffix",
        'Available now',
        date('Y-m-d H:i:s', strtotime('+1 day')),
        date('Y-m-d H:i:s', strtotime('-1 hour')),
    ]);
    $openAssignmentId = (int) $db->lastInsertId();
    $insertedAssignmentIds[] = $openAssignmentId;

    $insertAssignment->execute([
        $weekId,
        "Smoke Future Assignment $suffix",
        'Hidden until later',
        date('Y-m-d H:i:s', strtotime('+2 day')),
        date('Y-m-d H:i:s', strtotime('+1 day')),
    ]);
    $futureAssignmentId = (int) $db->lastInsertId();
    $insertedAssignmentIds[] = $futureAssignmentId;

    $insertAssignment->execute([
        $weekId,
        "Smoke Expired Assignment $suffix",
        'Deadline already passed',
        date('Y-m-d H:i:s', strtotime('-1 hour')),
        date('Y-m-d H:i:s', strtotime('-2 day')),
    ]);
    $expiredAssignmentId = (int) $db->lastInsertId();
    $insertedAssignmentIds[] = $expiredAssignmentId;

    $insertTest->execute([
        $weekId,
        "Smoke Open Test $suffix",
        'Available now',
        1,
        date('Y-m-d H:i:s', strtotime('-1 hour')),
    ]);
    $openTestId = (int) $db->lastInsertId();
    $insertedTestIds[] = $openTestId;

    $insertQuestion->execute([$openTestId, 'Boiler room?', 'single']);
    $openQuestionId = (int) $db->lastInsertId();
    $insertOption->execute([$openQuestionId, 'Yes', 1]);
    $openCorrectOptionId = (int) $db->lastInsertId();
    $insertOption->execute([$openQuestionId, 'No', 0]);

    $insertTest->execute([
        $weekId,
        "Smoke Future Test $suffix",
        'Hidden until later',
        1,
        date('Y-m-d H:i:s', strtotime('+1 day')),
    ]);
    $futureTestId = (int) $db->lastInsertId();
    $insertedTestIds[] = $futureTestId;

    $insertQuestion->execute([$futureTestId, 'Future question?', 'single']);
    $futureQuestionId = (int) $db->lastInsertId();
    $insertOption->execute([$futureQuestionId, 'A', 1]);
    $insertOption->execute([$futureQuestionId, 'B', 0]);

    [$studentCookie, $studentLogin] = smoke_login($baseUrl, 'student1', 'student123');
    $cookies[] = $studentCookie;
    $allPassed = smoke_note(
        'Student login redirects to dashboard',
        $studentLogin['status'] === 302 && str_contains((string) $studentLogin['location'], 'route=dashboard'),
        'status=' . $studentLogin['status'] . ' location=' . ($studentLogin['location'] ?? 'none')
    ) && $allPassed;

    $dashboard = smoke_fetch('GET', $baseUrl . '/index.php?route=dashboard', [], $studentCookie);
    $allPassed = smoke_note('Student dashboard returns 200', $dashboard['status'] === 200, 'status=' . $dashboard['status']) && $allPassed;

    $assignments = smoke_fetch('GET', $baseUrl . '/index.php?route=assignments', [], $studentCookie);
    $allPassed = smoke_note(
        'Assignments page shows open assignment',
        str_contains($assignments['body'], "Smoke Open Assignment $suffix")
    ) && $allPassed;
    $allPassed = smoke_note(
        'Assignments page hides future assignment',
        !str_contains($assignments['body'], "Smoke Future Assignment $suffix")
    ) && $allPassed;

    $assignmentDetail = smoke_fetch('GET', $baseUrl . "/index.php?route=assignment_detail&aid=$openAssignmentId", [], $studentCookie);
    $openAssignmentCsrf = smoke_csrf_from($assignmentDetail['body']);
    $allPassed = smoke_note(
        'Direct access to open assignment works',
        $assignmentDetail['status'] === 200 && str_contains($assignmentDetail['body'], "Smoke Open Assignment $suffix"),
        'status=' . $assignmentDetail['status']
    ) && $allPassed;

    $futureAssignment = smoke_fetch('GET', $baseUrl . "/index.php?route=assignment_detail&aid=$futureAssignmentId", [], $studentCookie);
    $allPassed = smoke_note(
        'Direct access to future assignment is blocked',
        $futureAssignment['status'] === 302 && str_contains((string) $futureAssignment['location'], 'route=assignments'),
        'status=' . $futureAssignment['status'] . ' location=' . ($futureAssignment['location'] ?? 'none')
    ) && $allPassed;

    $expiredAssignment = smoke_fetch('GET', $baseUrl . "/index.php?route=assignment_detail&aid=$expiredAssignmentId", [], $studentCookie);
    $expiredHasForm = str_contains($expiredAssignment['body'], 'name="text_answer"')
        || str_contains($expiredAssignment['body'], 'enctype="multipart/form-data"');
    $allPassed = smoke_note(
        'Expired assignment page hides submission form',
        $expiredAssignment['status'] === 200 && !$expiredHasForm,
        'status=' . $expiredAssignment['status']
    ) && $allPassed;

    $expiredSubmit = smoke_fetch('POST', $baseUrl . "/index.php?route=assignment_detail&aid=$expiredAssignmentId", [
        'csrf_token' => $openAssignmentCsrf,
        'text_answer' => 'Late answer should fail',
    ], $studentCookie);
    $stmt = $db->prepare('SELECT COUNT(*) FROM submissions WHERE assignment_id = ? AND user_id = ?');
    $stmt->execute([$expiredAssignmentId, $studentId]);
    $expiredSubmissionCount = (int) $stmt->fetchColumn();
    $allPassed = smoke_note(
        'Expired assignment rejects forged POST submission',
        $expiredSubmit['status'] === 302 && $expiredSubmissionCount === 0,
        'status=' . $expiredSubmit['status'] . ' submissions=' . $expiredSubmissionCount
    ) && $allPassed;

    $tests = smoke_fetch('GET', $baseUrl . '/index.php?route=tests', [], $studentCookie);
    $allPassed = smoke_note(
        'Tests page shows open test',
        str_contains($tests['body'], "Smoke Open Test $suffix")
    ) && $allPassed;
    $allPassed = smoke_note(
        'Tests page hides future test',
        !str_contains($tests['body'], "Smoke Future Test $suffix")
    ) && $allPassed;

    $futureTest = smoke_fetch('GET', $baseUrl . "/index.php?route=test_take&tid=$futureTestId", [], $studentCookie);
    $allPassed = smoke_note(
        'Direct access to future test is blocked',
        $futureTest['status'] === 302 && str_contains((string) $futureTest['location'], 'route=tests'),
        'status=' . $futureTest['status'] . ' location=' . ($futureTest['location'] ?? 'none')
    ) && $allPassed;

    $openTest = smoke_fetch('GET', $baseUrl . "/index.php?route=test_take&tid=$openTestId", [], $studentCookie);
    $openTestCsrf = smoke_csrf_from($openTest['body']);
    if (!preg_match('/name="attempt_id"\s+value="(\d+)"/', $openTest['body'], $attemptMatch)) {
        throw new RuntimeException('attempt_id not found');
    }

    $attemptId = (int) $attemptMatch[1];
    $stmt = $db->prepare('UPDATE test_submissions SET started_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE) WHERE id = ?');
    $stmt->execute([$attemptId]);

    $expiredTestSubmit = smoke_fetch('POST', $baseUrl . "/index.php?route=test_take&tid=$openTestId", [
        'csrf_token' => $openTestCsrf,
        'attempt_id' => $attemptId,
        'q_' . $openQuestionId => $openCorrectOptionId,
    ], $studentCookie);

    $stmt = $db->prepare('SELECT score, finished_at FROM test_submissions WHERE id = ?');
    $stmt->execute([$attemptId]);
    $attemptRow = $stmt->fetch();
    $allPassed = smoke_note(
        'Server expires timed-out test attempt',
        $expiredTestSubmit['status'] === 302
            && str_contains((string) $expiredTestSubmit['location'], 'route=test_result')
            && (int) ($attemptRow['score'] ?? -1) === 0
            && !empty($attemptRow['finished_at']),
        'status=' . $expiredTestSubmit['status'] . ' score=' . ($attemptRow['score'] ?? 'null')
    ) && $allPassed;

    [$teacherCookie, $teacherLogin] = smoke_login($baseUrl, 'teacher', 'teacher123');
    $cookies[] = $teacherCookie;
    $teacherForbidden = smoke_fetch('GET', $baseUrl . '/index.php?route=admin_users', [], $teacherCookie);
    $allPassed = smoke_note(
        'Teacher forbidden redirect goes to admin dashboard',
        $teacherForbidden['status'] === 302 && str_contains((string) $teacherForbidden['location'], 'route=admin_dashboard'),
        'status=' . $teacherForbidden['status'] . ' location=' . ($teacherForbidden['location'] ?? 'none')
    ) && $allPassed;

    [$adminCookie, $adminLogin] = smoke_login($baseUrl, 'admin', 'admin123');
    $cookies[] = $adminCookie;
    $deleteByGet = smoke_fetch('GET', $baseUrl . "/index.php?route=admin_delete_assignment&aid=$openAssignmentId", [], $adminCookie);
    $stmt = $db->prepare('SELECT COUNT(*) FROM assignments WHERE id = ?');
    $stmt->execute([$openAssignmentId]);
    $assignmentStillExists = (int) $stmt->fetchColumn() === 1;
    $allPassed = smoke_note(
        'Admin delete via GET is rejected',
        $deleteByGet['status'] === 405 && $assignmentStillExists,
        'status=' . $deleteByGet['status']
    ) && $allPassed;
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    $allPassed = false;
} finally {
    foreach ($cookies as $cookieFile) {
        if (is_string($cookieFile) && is_file($cookieFile)) {
            unlink($cookieFile);
        }
    }

    if (!empty($insertedTestIds)) {
        $placeholders = implode(',', array_fill(0, count($insertedTestIds), '?'));
        $stmt = $db->prepare("DELETE FROM tests WHERE id IN ($placeholders)");
        $stmt->execute($insertedTestIds);
    }

    if (!empty($insertedAssignmentIds)) {
        $placeholders = implode(',', array_fill(0, count($insertedAssignmentIds), '?'));
        $stmt = $db->prepare("DELETE FROM assignments WHERE id IN ($placeholders)");
        $stmt->execute($insertedAssignmentIds);
    }
}

exit($allPassed ? 0 : 1);
