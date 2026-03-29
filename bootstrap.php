<?php
// bootstrap.php - Инициализация SQLite-схемы и стартовых данных для PHP-версии

function bootstrap_database(PDO $db): void {
    $db->exec('PRAGMA foreign_keys = ON');

    $db->beginTransaction();

    try {
        bootstrap_create_schema($db);
        bootstrap_seed_course($db);
        bootstrap_seed_default_users($db);
        bootstrap_migrate_legacy_default_passwords($db);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $e;
    }
}

function bootstrap_create_schema(PDO $db): void {
    $statements = [
        <<<SQL
        CREATE TABLE IF NOT EXISTS courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(256) NOT NULL,
            description TEXT,
            goals TEXT,
            objectives TEXT,
            content_info TEXT
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS weeks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            number INTEGER NOT NULL,
            title VARCHAR(256) NOT NULL,
            FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(64) NOT NULL UNIQUE,
            full_name VARCHAR(128) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            password_hash VARCHAR(256) NOT NULL,
            role VARCHAR(16) NOT NULL DEFAULT 'student',
            is_active BOOLEAN NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            week_id INTEGER NOT NULL,
            title VARCHAR(256) NOT NULL,
            material_type VARCHAR(32),
            content TEXT,
            file_path VARCHAR(512),
            url VARCHAR(1024),
            visible BOOLEAN NOT NULL DEFAULT 1,
            open_date DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(week_id) REFERENCES weeks(id) ON DELETE CASCADE
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            week_id INTEGER NOT NULL,
            title VARCHAR(256) NOT NULL,
            description TEXT,
            deadline DATETIME,
            visible BOOLEAN NOT NULL DEFAULT 1,
            open_date DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(week_id) REFERENCES weeks(id) ON DELETE CASCADE
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            assignment_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            file_path VARCHAR(512),
            text_answer TEXT,
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            grade INTEGER,
            comment TEXT,
            reviewed_at DATETIME,
            reviewed_by INTEGER,
            FOREIGN KEY(assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(reviewed_by) REFERENCES users(id)
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS tests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            week_id INTEGER NOT NULL,
            title VARCHAR(256) NOT NULL,
            description TEXT,
            time_limit INTEGER,
            show_answers BOOLEAN NOT NULL DEFAULT 1,
            visible BOOLEAN NOT NULL DEFAULT 1,
            open_date DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(week_id) REFERENCES weeks(id) ON DELETE CASCADE
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS test_questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_id INTEGER NOT NULL,
            question_text TEXT NOT NULL,
            question_type VARCHAR(16) NOT NULL DEFAULT 'single',
            order_num INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE CASCADE
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS test_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question_id INTEGER NOT NULL,
            option_text TEXT NOT NULL,
            is_correct BOOLEAN NOT NULL DEFAULT 0,
            FOREIGN KEY(question_id) REFERENCES test_questions(id) ON DELETE CASCADE
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS test_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            test_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME,
            score INTEGER NOT NULL DEFAULT 0,
            max_score INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS test_answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            submission_id INTEGER NOT NULL,
            question_id INTEGER NOT NULL,
            answer_text TEXT,
            selected_options TEXT,
            is_correct BOOLEAN,
            FOREIGN KEY(submission_id) REFERENCES test_submissions(id) ON DELETE CASCADE,
            FOREIGN KEY(question_id) REFERENCES test_questions(id) ON DELETE CASCADE
        )
        SQL,
    ];

    foreach ($statements as $sql) {
        $db->exec($sql);
    }
}

function bootstrap_seed_course(PDO $db): void {
    $courseCount = (int) $db->query('SELECT COUNT(*) FROM courses')->fetchColumn();

    if ($courseCount > 0) {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO courses (title, description, goals, objectives, content_info)
         VALUES (?, ?, ?, ?, ?)'
    );

    $stmt->execute([
        'Профессиональный иностранный язык для теплоэнергетиков',
        'Курс профессионального иностранного языка разработан специально для специалистов теплоэнергетической отрасли.',
        'Освоение профессиональной иноязычной коммуникации в области теплоэнергетики.',
        "• Овладение профессиональной терминологией\n• Чтение и перевод технической документации\n• Деловая переписка на иностранном языке\n• Устное профессиональное общение",
        'Курс состоит из тематических модулей по неделям. Каждый модуль включает лексический материал, грамматику, тексты по специальности, задания и тест.',
    ]);

    $courseId = (int) $db->lastInsertId();

    $weeks = [
        [1, 'Неделя 1: Введение в терминологию'],
        [2, 'Неделя 2: Техническая документация'],
        [3, 'Неделя 3: Деловая коммуникация'],
        [4, 'Неделя 4: Профессиональное общение'],
    ];

    $weekStmt = $db->prepare('INSERT INTO weeks (course_id, number, title) VALUES (?, ?, ?)');

    foreach ($weeks as [$number, $title]) {
        $weekStmt->execute([$courseId, $number, $title]);
    }
}

function bootstrap_seed_default_users(PDO $db): void {
    $users = bootstrap_default_users();
    $findStmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $insertStmt = $db->prepare(
        'INSERT INTO users (username, full_name, email, password_hash, role, is_active, created_at)
         VALUES (?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)'
    );

    foreach ($users as $user) {
        $findStmt->execute([$user['username'], $user['email']]);

        if ($findStmt->fetch()) {
            continue;
        }

        $insertStmt->execute([
            $user['username'],
            $user['full_name'],
            $user['email'],
            password_hash($user['password'], PASSWORD_DEFAULT),
            $user['role'],
        ]);
    }
}

function bootstrap_migrate_legacy_default_passwords(PDO $db): void {
    $users = bootstrap_default_users();
    $selectStmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
    $updateStmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');

    foreach ($users as $user) {
        $selectStmt->execute([$user['username']]);
        $row = $selectStmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !bootstrap_is_legacy_python_hash($row['password_hash'])) {
            continue;
        }

        $updateStmt->execute([
            password_hash($user['password'], PASSWORD_DEFAULT),
            $row['id'],
        ]);
    }
}

function bootstrap_is_legacy_python_hash(?string $hash): bool {
    if (!$hash) {
        return false;
    }

    return strpos($hash, 'scrypt:') === 0 || strpos($hash, 'pbkdf2:') === 0;
}

function bootstrap_default_users(): array {
    return [
        [
            'username' => 'admin',
            'full_name' => 'Администратор',
            'email' => 'admin@portal.ru',
            'role' => 'admin',
            'password' => 'admin123',
        ],
        [
            'username' => 'teacher',
            'full_name' => 'Преподаватель',
            'email' => 'teacher@portal.ru',
            'role' => 'teacher',
            'password' => 'teacher123',
        ],
        [
            'username' => 'student1',
            'full_name' => 'Тестовый студент',
            'email' => 'student1@test.ru',
            'role' => 'student',
            'password' => 'student123',
        ],
    ];
}
