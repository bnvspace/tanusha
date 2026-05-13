<?php
// bootstrap.php - Инициализация MariaDB-схемы и стартовых данных

function bootstrap_database(PDO $db): void {
    try {
        bootstrap_create_schema($db);
        bootstrap_migrate_existing_schema($db);
        bootstrap_seed_course($db);
        bootstrap_seed_default_users($db);
        bootstrap_migrate_legacy_default_passwords($db);
    } catch (Throwable $e) {
        throw $e;
    }
}

function bootstrap_create_schema(PDO $db): void {
    $statements = [
        <<<SQL
        CREATE TABLE IF NOT EXISTS courses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(256) NOT NULL,
            description TEXT,
            goals TEXT,
            objectives TEXT,
            content_info TEXT,
            glossary_pdf_path VARCHAR(512) NULL,
            syllabus_pdf_path VARCHAR(512) NULL,
            assessment_criteria_path VARCHAR(512) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS weeks (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT NOT NULL,
            number INT NOT NULL,
            title VARCHAR(256) NOT NULL,
            FOREIGN KEY(course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(64) NOT NULL UNIQUE,
            full_name VARCHAR(128) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            password_hash VARCHAR(256) NOT NULL,
            role VARCHAR(16) NOT NULL DEFAULT 'student',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS discussion_topics (
            id INT PRIMARY KEY AUTO_INCREMENT,
            week_id INT NOT NULL,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY(week_id) REFERENCES weeks(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS discussion_comments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            topic_id INT NOT NULL,
            user_id INT NOT NULL,
            comment_text TEXT,
            image_path VARCHAR(512),
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(topic_id) REFERENCES discussion_topics(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS materials (
            id INT PRIMARY KEY AUTO_INCREMENT,
            week_id INT NOT NULL,
            title VARCHAR(256) NOT NULL,
            material_type VARCHAR(32),
            content TEXT,
            file_path VARCHAR(512),
            url VARCHAR(1024),
            visible TINYINT(1) NOT NULL DEFAULT 1,
            open_date DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(week_id) REFERENCES weeks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS assignments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            week_id INT NOT NULL,
            title VARCHAR(256) NOT NULL,
            description TEXT,
            deadline DATETIME,
            visible TINYINT(1) NOT NULL DEFAULT 1,
            open_date DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(week_id) REFERENCES weeks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS submissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            assignment_id INT NOT NULL,
            user_id INT NOT NULL,
            file_path VARCHAR(512),
            text_answer TEXT,
            submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            grade INT,
            comment TEXT,
            reviewed_at DATETIME,
            reviewed_by INT,
            FOREIGN KEY(assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(reviewed_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS tests (
            id INT PRIMARY KEY AUTO_INCREMENT,
            week_id INT NOT NULL,
            title VARCHAR(256) NOT NULL,
            description TEXT,
            time_limit INT,
            show_answers TINYINT(1) NOT NULL DEFAULT 1,
            visible TINYINT(1) NOT NULL DEFAULT 1,
            open_date DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(week_id) REFERENCES weeks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS test_questions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            test_id INT NOT NULL,
            question_text TEXT NOT NULL,
            question_type VARCHAR(16) NOT NULL DEFAULT 'single',
            order_num INT NOT NULL DEFAULT 0,
            FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS test_options (
            id INT PRIMARY KEY AUTO_INCREMENT,
            question_id INT NOT NULL,
            option_text TEXT NOT NULL,
            is_correct TINYINT(1) NOT NULL DEFAULT 0,
            FOREIGN KEY(question_id) REFERENCES test_questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS test_submissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            test_id INT NOT NULL,
            user_id INT NOT NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME,
            score INT NOT NULL DEFAULT 0,
            max_score INT NOT NULL DEFAULT 0,
            FOREIGN KEY(test_id) REFERENCES tests(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
        <<<SQL
        CREATE TABLE IF NOT EXISTS test_answers (
            id INT PRIMARY KEY AUTO_INCREMENT,
            submission_id INT NOT NULL,
            question_id INT NOT NULL,
            answer_text TEXT,
            selected_options TEXT,
            is_correct TINYINT(1),
            FOREIGN KEY(submission_id) REFERENCES test_submissions(id) ON DELETE CASCADE,
            FOREIGN KEY(question_id) REFERENCES test_questions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL,
    ];

    foreach ($statements as $sql) {
        $db->exec($sql);
    }

    // Индексы (CREATE INDEX IF NOT EXISTS не поддерживается в MySQL, используем обход)
    $indexes = [
        ['idx_discussion_topics_week_id', 'discussion_topics', 'week_id'],
        ['idx_discussion_comments_topic_id', 'discussion_comments', 'topic_id'],
    ];

    foreach ($indexes as [$indexName, $table, $column]) {
        $stmt = $db->query("SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
        if ($stmt->rowCount() === 0) {
            $db->exec("CREATE INDEX `$indexName` ON `$table`(`$column`)");
        }
    }
}

function bootstrap_migrate_existing_schema(PDO $db): void {
    bootstrap_add_column_if_missing($db, 'courses', 'glossary_pdf_path', 'VARCHAR(512) NULL');
    bootstrap_add_column_if_missing($db, 'courses', 'syllabus_pdf_path', 'VARCHAR(512) NULL');
    bootstrap_add_column_if_missing($db, 'courses', 'assessment_criteria_path', 'VARCHAR(512) NULL');
    bootstrap_add_column_if_missing($db, 'weeks', 'discussion_description', 'TEXT');
}

function bootstrap_add_column_if_missing(PDO $db, string $table, string $column, string $definition): void {
    $stmt = $db->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);

    if ($stmt->fetch()) {
        return; // Колонка уже существует
    }

    $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
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
