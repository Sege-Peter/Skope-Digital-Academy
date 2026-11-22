<?php
/**
 * Skope Digital Academy – learning platform schema.
 *
 * Brings any database (the repo dump or an older install) up to the schema the
 * code expects: self-paced/structured tracks, admin pricing, cohorts, public
 * tutorials, Try-it code examples, platform settings, plus columns the code
 * already used but no earlier migration created. Safe to run repeatedly.
 *
 * Run:  php migrate_learning_platform.php     (or open it while signed in as admin)
 */
require_once __DIR__ . '/includes/db.php';

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/includes/auth.php';
    if (($_SESSION['role'] ?? '') !== 'admin') { http_response_code(403); exit('Admins only.'); }
    header('Content-Type: text/plain; charset=utf-8');
}

$steps = [
    // Columns the existing code depends on
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS merit_points INT NOT NULL DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS streak_days INT NOT NULL DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_streak_date DATE NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS requires_password_change TINYINT(1) NOT NULL DEFAULT 0",
    "CREATE TABLE IF NOT EXISTS calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NULL,
        event_date DATETIME NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(50) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "ALTER TABLE calendar_events ADD COLUMN IF NOT EXISTS target_role ENUM('student','tutor','all') NULL",
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS week_num INT DEFAULT 1",
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS pdf_resource VARCHAR(255) NULL",
    "ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS linked_lesson_id INT NULL",
    "ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS linked_week_num INT NULL",
    "ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS type ENUM('quiz','cat','final') DEFAULT 'quiz'",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS paystack_reference VARCHAR(100) NULL",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_type ENUM('course','registration','donation') DEFAULT 'course'",

    // Admin-controlled pricing & delivery
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS learning_modes ENUM('self_paced','structured','both') NOT NULL DEFAULT 'both'",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS structured_price DECIMAL(10,2) NULL COMMENT 'NULL = same as price'",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS sale_price DECIMAL(10,2) NULL COMMENT 'self-paced sale price'",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS sale_ends_at DATETIME NULL",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS access_days INT NOT NULL DEFAULT 0 COMMENT '0 = platform default'",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_open_tutorial TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'all lessons readable free in the public reader'",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS pricing_updated_by INT NULL",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS pricing_updated_at DATETIME NULL",

    // Lessons: scheduling, free previews, Try-it examples
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS release_date DATETIME NULL",
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS is_preview TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS code_lang VARCHAR(20) NULL",
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS code_example MEDIUMTEXT NULL",

    // File-based tutorial library (content/tutorials, import_tutorials.php)
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS slug VARCHAR(120) NULL",
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS module_title VARCHAR(150) NULL",
    "ALTER TABLE lessons ADD COLUMN IF NOT EXISTS source_key VARCHAR(190) NULL",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS tutorial_short VARCHAR(40) NULL",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS tutorial_order INT NOT NULL DEFAULT 0",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS source VARCHAR(20) NULL COMMENT 'tutorial = managed by import_tutorials.php'",

    // Learning engine: XP events, levels, skill/achievement/path badges, paths, auto certificates
    "CREATE TABLE IF NOT EXISTS learning_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NULL,
        lesson_id INT NULL,
        event_type VARCHAR(40) NOT NULL,
        event_key VARCHAR(190) NOT NULL,
        xp INT NOT NULL DEFAULT 0,
        meta VARCHAR(255) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_event (user_id, event_key),
        KEY course_xp (course_id, user_id, created_at),
        KEY user_time (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS xp INT NOT NULL DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS best_streak INT NOT NULL DEFAULT 0",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS show_on_leaderboard TINYINT(1) NOT NULL DEFAULT 1",
    "ALTER TABLE lesson_progress ADD COLUMN IF NOT EXISTS time_spent_secs INT NOT NULL DEFAULT 0",
    "ALTER TABLE lesson_progress ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL",
    "ALTER TABLE badges MODIFY criteria_type VARCHAR(40) NULL",
    "ALTER TABLE badges ADD COLUMN IF NOT EXISTS code VARCHAR(60) NULL",
    "ALTER TABLE badges ADD COLUMN IF NOT EXISTS kind ENUM('skill','achievement','path','manual') NOT NULL DEFAULT 'achievement'",
    "ALTER TABLE badges ADD COLUMN IF NOT EXISTS course_id INT NULL",
    "ALTER TABLE badges ADD COLUMN IF NOT EXISTS path_id INT NULL",
    "ALTER TABLE badges ADD COLUMN IF NOT EXISTS xp_reward INT NOT NULL DEFAULT 0",
    "ALTER TABLE badges ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0",
    "CREATE UNIQUE INDEX IF NOT EXISTS uniq_badge_code ON badges (code)",
    "CREATE UNIQUE INDEX IF NOT EXISTS uniq_student_badge ON student_badges (student_id, badge_id)",
    "CREATE TABLE IF NOT EXISTS learning_paths (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(80) NOT NULL UNIQUE,
        title VARCHAR(150) NOT NULL,
        description TEXT NULL,
        icon VARCHAR(50) DEFAULT 'fa-route',
        color VARCHAR(20) DEFAULT '#0080ff',
        sort_order INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS learning_path_courses (
        path_id INT NOT NULL,
        course_slug VARCHAR(120) NOT NULL,
        position INT NOT NULL DEFAULT 0,
        PRIMARY KEY (path_id, course_slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "ALTER TABLE certificates ADD COLUMN IF NOT EXISTS certificate_type ENUM('course','path') NOT NULL DEFAULT 'course'",
    "ALTER TABLE certificates ADD COLUMN IF NOT EXISTS path_id INT NULL",
    "ALTER TABLE certificates ADD COLUMN IF NOT EXISTS score DECIMAL(5,2) NULL",
    "ALTER TABLE certificates MODIFY course_id INT NULL",
    "CREATE UNIQUE INDEX IF NOT EXISTS uniq_cert_code ON certificates (verification_code)",
    "ALTER TABLE course_ratings ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL",
    "CREATE UNIQUE INDEX IF NOT EXISTS uniq_rating ON course_ratings (student_id, course_id)",

    // Free courses with paid certificates
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS certificate_fee DECIMAL(10,2) NULL COMMENT 'NULL = platform default (setting certificate_fee)'",
    "ALTER TABLE courses ADD COLUMN IF NOT EXISTS outcomes TEXT NULL COMMENT 'JSON list: what you will learn'",
    "ALTER TABLE certificates ADD COLUMN IF NOT EXISTS fee DECIMAL(10,2) NOT NULL DEFAULT 0",
    "ALTER TABLE certificates ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL",
    "ALTER TABLE payments MODIFY payment_type ENUM('course','registration','donation','certificate') DEFAULT 'course'",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS certificate_id INT NULL",
    "INSERT INTO categories (name, slug, icon, color) SELECT 'Career & Personal Skills', 'career-personal-skills', 'fas fa-user-tie', '#14b8a6' FROM DUAL
       WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'career-personal-skills')",

    // Cohorts for structured tracks
    "CREATE TABLE IF NOT EXISTS course_cohorts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        name VARCHAR(120) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NULL,
        capacity INT NULL,
        status ENUM('open','closed','archived') NOT NULL DEFAULT 'open',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY course_id (course_id),
        CONSTRAINT fk_cohort_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // Learner's chosen track
    "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS learning_mode ENUM('self_paced','structured') NOT NULL DEFAULT 'self_paced'",
    "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS cohort_id INT NULL",
    "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS starts_on DATE NULL",
    "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL",
    "ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS learning_mode VARCHAR(20) NULL",
    "ALTER TABLE payments ADD COLUMN IF NOT EXISTS cohort_id INT NULL",

    // Platform settings
    "CREATE TABLE IF NOT EXISTS platform_settings (
        setting_key VARCHAR(64) PRIMARY KEY,
        setting_value TEXT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$ok = 0;
foreach ($steps as $sql) {
    try {
        $pdo->exec($sql);
        $ok++;
    } catch (Exception $e) {
        echo "FAILED: " . strtok(trim($sql), "\n") . "\n  " . $e->getMessage() . "\n";
    }
}

// Backfill: existing paid enrollments get credit for verified payments; start dates from enrolment.
try {
    $pdo->exec("UPDATE enrollments e
                SET e.amount_paid = (SELECT COALESCE(SUM(p.amount),0) FROM payments p
                                     WHERE p.student_id = e.student_id AND p.course_id = e.course_id AND p.status = 'verified')
                WHERE e.amount_paid = 0");
    $pdo->exec("UPDATE enrollments SET starts_on = DATE(enrolled_at) WHERE starts_on IS NULL");
    $pdo->exec("UPDATE courses SET slug = CONCAT(LOWER(TRIM(BOTH '-' FROM REGEXP_REPLACE(title, '[^A-Za-z0-9]+', '-'))), '-', id) WHERE slug IS NULL OR slug = ''");
} catch (Exception $e) {
    echo "Backfill warning: " . $e->getMessage() . "\n";
}

// Learning engine seed: learning paths, achievement/skill/path badges, XP backfill.
try {
    require_once __DIR__ . '/includes/progress.php';
    seed_learning_engine($pdo);
    echo "Learning engine: paths, badges and XP backfill done.\n";
} catch (Exception $e) {
    echo "Learning engine seed warning: " . $e->getMessage() . "\n";
}

echo "Learning platform schema: $ok/" . count($steps) . " steps applied.\n";
