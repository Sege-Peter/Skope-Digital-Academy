<?php
/**
 * Skope Digital Academy – Database Installer
 * Visit: http://localhost/Skope%20Digital%20Academy/setup.php
 */

$host = 'localhost';
$dbname = 'skopedigital';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    $tables = [];

    $tables[] = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `email` VARCHAR(191) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin','tutor','student') NOT NULL DEFAULT 'student',
        `admission_number` VARCHAR(50) DEFAULT NULL UNIQUE,
        `phone` VARCHAR(20),
        `avatar` VARCHAR(255),
        `bio` TEXT,
        `status` ENUM('active','pending','suspended') NOT NULL DEFAULT 'pending',
        `email_verified` TINYINT(1) DEFAULT 0,
        `points` INT DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `last_login` DATETIME
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `categories` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `slug` VARCHAR(120) NOT NULL UNIQUE,
        `icon` VARCHAR(50) DEFAULT 'fas fa-book',
        `color` VARCHAR(20) DEFAULT '#00AEEF',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `courses` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tutor_id` INT NOT NULL,
        `category_id` INT,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(280) NOT NULL UNIQUE,
        `description` TEXT,
        `level` ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
        `price` DECIMAL(10,2) DEFAULT 0.00,
        `thumbnail` VARCHAR(255),
        `preview_video` VARCHAR(255),
        `status` ENUM('draft','pending','published','archived') DEFAULT 'draft',
        `duration_hours` DECIMAL(5,1) DEFAULT 0,
        `total_lessons` INT DEFAULT 0,
        `enrolled_count` INT DEFAULT 0,
        `avg_rating` DECIMAL(3,2) DEFAULT 0.00,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`tutor_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `lessons` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `course_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `content` LONGTEXT,
        `lesson_type` ENUM('video','pdf','audio','text','image') DEFAULT 'text',
        `file_url` VARCHAR(500),
        `order_num` INT DEFAULT 0,
        `is_mandatory` TINYINT(1) DEFAULT 1,
        `duration_mins` INT DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `enrollments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `status` ENUM('active','completed','cancelled') DEFAULT 'active',
        `progress_percent` DECIMAL(5,2) DEFAULT 0.00,
        `enrolled_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `completed_at` DATETIME,
        UNIQUE KEY `unique_enrollment` (`student_id`,`course_id`),
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `lesson_progress` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `lesson_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `status` ENUM('not_started','in_progress','completed') DEFAULT 'not_started',
        `time_spent_mins` INT DEFAULT 0,
        `completed_at` DATETIME,
        UNIQUE KEY `unique_progress` (`student_id`,`lesson_id`),
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `amount` DECIMAL(10,2) NOT NULL,
        `status` ENUM('pending','verified','failed') DEFAULT 'pending',
        `transaction_message` TEXT,
        `proof_file` VARCHAR(255),
        `verified_by` INT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `verified_at` DATETIME,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `quizzes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `course_id` INT NOT NULL,
        `lesson_id` INT,
        `title` VARCHAR(255) NOT NULL,
        `time_limit_mins` INT DEFAULT 30,
        `max_attempts` INT DEFAULT 3,
        `pass_score` INT DEFAULT 70,
        `is_randomized` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `quiz_questions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `quiz_id` INT NOT NULL,
        `question` TEXT NOT NULL,
        `type` ENUM('mcq','truefalse','short') DEFAULT 'mcq',
        `options_json` JSON,
        `correct_answer` TEXT,
        `points` INT DEFAULT 1,
        `order_num` INT DEFAULT 0,
        FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `quiz_attempts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `quiz_id` INT NOT NULL,
        `score` DECIMAL(5,2) DEFAULT 0,
        `passed` TINYINT(1) DEFAULT 0,
        `answers_json` JSON,
        `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `completed_at` DATETIME,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `assignments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `course_id` INT NOT NULL,
        `lesson_id` INT,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `due_date` DATETIME,
        `max_score` INT DEFAULT 100,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `assignment_submissions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `assignment_id` INT NOT NULL,
        `student_id` INT NOT NULL,
        `file_url` VARCHAR(500),
        `notes` TEXT,
        `score` DECIMAL(5,2),
        `feedback` TEXT,
        `status` ENUM('pending','graded') DEFAULT 'pending',
        `submitted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `graded_at` DATETIME,
        FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `certificates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `certificate_url` VARCHAR(500),
        `verification_code` VARCHAR(50) UNIQUE,
        `issued_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `badges` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `icon` VARCHAR(50) DEFAULT '🏅',
        `color` VARCHAR(20) DEFAULT '#F7941D',
        `criteria_type` ENUM('courses_completed','lessons_completed','quizzes_passed','points_earned') DEFAULT 'courses_completed',
        `criteria_value` INT DEFAULT 1,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `student_badges` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `badge_id` INT NOT NULL,
        `awarded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_badge` (`student_id`,`badge_id`),
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`badge_id`) REFERENCES `badges`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `announcements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `start_date` DATE,
        `end_date` DATE,
        `is_pinned` TINYINT(1) DEFAULT 0,
        `created_by` INT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `user_role` ENUM('all','admin','tutor','student'),
        `target_user_id` INT,
        `read_status` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `audit_log` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT,
        `action` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `ip_address` VARCHAR(45),
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `support_tickets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('open','in_progress','closed') DEFAULT 'open',
        `assigned_to` INT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `course_ratings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `student_id` INT NOT NULL,
        `course_id` INT NOT NULL,
        `rating` TINYINT NOT NULL,
        `review` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_rating` (`student_id`,`course_id`),
        FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `scholarships` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `amount` DECIMAL(10,2) DEFAULT 0.00,
        `expiry_date` DATE,
        `status` ENUM('active','closed') DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    $tables[] = "CREATE TABLE IF NOT EXISTS `scholarship_applications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `scholarship_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `sop` TEXT NOT NULL,
        `academic_background` TEXT NOT NULL,
        `document_file` VARCHAR(255) NULL,
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB";


    $tables[] = "CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(191) NOT NULL,
        `token` VARCHAR(100) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB";

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    // Seed default categories
    $pdo->exec("INSERT IGNORE INTO `categories` (name, slug, icon, color) VALUES
        ('Technology', 'technology', 'fas fa-laptop-code', '#00AEEF'),
        ('Business', 'business', 'fas fa-briefcase', '#F7941D'),
        ('Design', 'design', 'fas fa-palette', '#9b59b6'),
        ('Marketing', 'marketing', 'fas fa-bullhorn', '#e74c3c'),
        ('Finance', 'finance', 'fas fa-chart-line', '#27ae60'),
        ('Personal Development', 'personal-development', 'fas fa-brain', '#f39c12')
    ");

    // Seed default badges
    $pdo->exec("INSERT IGNORE INTO `badges` (name, description, icon, criteria_type, criteria_value) VALUES
        ('First Step', 'Completed your first lesson', '🎯', 'lessons_completed', 1),
        ('Course Graduate', 'Completed your first course', '🎓', 'courses_completed', 1),
        ('Quiz Master', 'Passed 5 quizzes', '🧠', 'quizzes_passed', 5),
        ('Top Learner', 'Earned 500 points', '⭐', 'points_earned', 500),
        ('Knowledge Seeker', 'Completed 3 courses', '📚', 'courses_completed', 3)
    ");

    // Seed default admin user
    $adminPassword = password_hash('Admin@2026', PASSWORD_BCRYPT);
    $pdo->exec("INSERT IGNORE INTO `users` (name, email, password, role, status, email_verified)
        VALUES ('Super Admin', 'admin@skopedigital.ac.ke', '$adminPassword', 'admin', 'active', 1)");

    // Seed demo tutor
    $tutorPassword = password_hash('Tutor@2026', PASSWORD_BCRYPT);
    $pdo->exec("INSERT IGNORE INTO `users` (name, email, password, role, status, email_verified)
        VALUES ('John Tutor', 'tutor@skopedigital.ac.ke', '$tutorPassword', 'tutor', 'active', 1)");

    // Seed demo student
    $studentPassword = password_hash('Student@2026', PASSWORD_BCRYPT);
    $pdo->exec("INSERT IGNORE INTO `users` (name, email, password, role, status, email_verified)
        VALUES ('Jane Student', 'student@skopedigital.ac.ke', '$studentPassword', 'student', 'active', 1)");

    // Seed demo announcement
    $pdo->exec("INSERT IGNORE INTO `announcements` (title, content, is_pinned, created_by, start_date, end_date)
        VALUES ('Welcome to Skope Digital Academy!', 'We are excited to launch our digital academy. Explore our courses, learn new skills, and earn certificates!', 1, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))");

    // Seed demo scholarships
    $pdo->exec("INSERT IGNORE INTO `scholarships` (title, description, amount, expiry_date) VALUES
        ('Merit Scholarship 2026', 'Full scholarship for top-performing students applying to any course.', 0, '2026-06-30'),
        ('Tech Excellence Award', 'KES 15,000 grant for passionate learners in technology courses.', 15000, '2026-05-31')
    ");


    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup Complete</title>
    <style>
        body { font-family: Inter, sans-serif; background: #0D1117; color: #E6EDF3; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
        .card { background: #161B22; border: 1px solid #30363D; border-radius: 12px; padding: 40px; max-width: 500px; text-align:center; }
        h1 { color: #00AEEF; } .success { color: #3FB950; font-size:48px; }
        a { display:inline-block; margin-top:20px; background:#00AEEF; color:#fff; padding:12px 28px; border-radius:8px; text-decoration:none; font-weight:600; }
        .cred { background:#0D1117; border-radius:8px; padding:16px; text-align:left; margin:16px 0; font-size:13px; line-height:1.8; }
        .cred strong { color:#F7941D; }
    </style></head><body>
    <div class="card">
        <div class="success">✅</div>
        <h1>Setup Complete!</h1>
        <p>Database <strong>skopedigital</strong> created successfully.</p>
        <div class="cred">
            <strong>Default Login Credentials:</strong><br>
            Admin: admin@skopedigital.ac.ke / Admin@2026<br>
            Tutor: tutor@skopedigital.ac.ke / Tutor@2026<br>
            Student: student@skopedigital.ac.ke / Student@2026
        </div>
        <a href="index.php">Go to Homepage →</a>
    </div></body></html>';

    // Seed Default Categories
    $catCount = $pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
    if ($catCount == 0) {
        $defaultCats = [
            ['Software Engineering', 'software-eng', 'fas fa-code', '#00BFFF'],
            ['Artifical Intelligence', 'ai-ml', 'fas fa-brain', '#8B5CF6'],
            ['Digital Marketing', 'digital-marketing', 'fas fa-bullhorn', '#FF8C00'],
            ['Data Analysis', 'data-science', 'fas fa-database', '#10B981'],
            ['Business Growth', 'business', 'fas fa-chart-line', '#6366F1'],
            ['Visual Design', 'design', 'fas fa-palette', '#EC4899']
        ];
        $catStmt = $pdo->prepare("INSERT INTO `categories` (name, slug, icon, color) VALUES (?, ?, ?, ?)");
        foreach ($defaultCats as $catData) {
            $catStmt->execute($catData);
        }
    }

} catch (PDOException $e) {
    echo '<div style="background:#F85149;color:#fff;padding:20px;border-radius:8px;font-family:monospace;">';
    echo 'Database Error: ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>
