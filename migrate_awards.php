<?php
/**
 * Skope Digital Academy – Awards & Transcript Migration
 * Run once: http://localhost/Skope%20Digital%20Academy/migrate_awards.php
 */

$host = 'localhost'; $dbname = 'skopedigital'; $username = 'root'; $password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $log = [];

    // 1. Add issued_by + issued_by_role to certificates
    try {
        $pdo->exec("ALTER TABLE `certificates` 
            ADD COLUMN IF NOT EXISTS `issued_by` INT NULL,
            ADD COLUMN IF NOT EXISTS `issued_by_role` ENUM('admin','tutor','system') DEFAULT 'system',
            ADD COLUMN IF NOT EXISTS `notes` TEXT NULL,
            ADD COLUMN IF NOT EXISTS `status` ENUM('pending','approved','revoked') DEFAULT 'approved',
            ADD FOREIGN KEY fk_cert_issuer (`issued_by`) REFERENCES `users`(`id`) ON DELETE SET NULL");
        $log[] = "✅ certificates: added issued_by, status, notes";
    } catch (Exception $e) { $log[] = "ℹ️  certificates: " . $e->getMessage(); }

    // 2. Add awarded_by to student_badges
    try {
        $pdo->exec("ALTER TABLE `student_badges`
            ADD COLUMN IF NOT EXISTS `awarded_by` INT NULL,
            ADD COLUMN IF NOT EXISTS `awarded_by_role` ENUM('admin','tutor','system') DEFAULT 'system',
            ADD COLUMN IF NOT EXISTS `notes` VARCHAR(255) NULL");
        $log[] = "✅ student_badges: added awarded_by, notes";
    } catch (Exception $e) { $log[] = "ℹ️  student_badges: " . $e->getMessage(); }

    // 3. Create transcript_entries table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `transcript_entries` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `course_id` INT NOT NULL,
            `entry_type` ENUM('course_completion','quiz_pass','assignment_grade','manual_entry') DEFAULT 'course_completion',
            `title` VARCHAR(255) NOT NULL,
            `score` DECIMAL(5,2) NULL,
            `max_score` DECIMAL(5,2) NULL,
            `grade` VARCHAR(10) NULL,
            `credits` DECIMAL(4,1) DEFAULT 1.0,
            `notes` TEXT NULL,
            `recorded_by` INT NULL,
            `recorded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        $log[] = "✅ transcript_entries: table created";
    } catch (Exception $e) { $log[] = "ℹ️  transcript_entries: " . $e->getMessage(); }

    // 4. Create point_ledger table for tracking point awards
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `point_ledger` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `student_id` INT NOT NULL,
            `points` INT NOT NULL,
            `reason` VARCHAR(255) NOT NULL,
            `awarded_by` INT NULL,
            `awarded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`awarded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        $log[] = "✅ point_ledger: table created";
    } catch (Exception $e) { $log[] = "ℹ️  point_ledger: " . $e->getMessage(); }

    // 5. Seed: Add 'manually_awarded' criteria type to badges if needed
    try {
        $pdo->exec("ALTER TABLE `badges` MODIFY `criteria_type` 
            ENUM('courses_completed','lessons_completed','quizzes_passed','points_earned','manually_awarded') 
            DEFAULT 'courses_completed'");
        $log[] = "✅ badges: added manually_awarded criteria_type";
    } catch (Exception $e) { $log[] = "ℹ️  badges: " . $e->getMessage(); }

} catch (PDOException $e) {
    die('<div style="background:#F85149;color:#fff;padding:20px;font-family:monospace;border-radius:8px;">DB Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
?>
<!DOCTYPE html><html><head><title>Migration</title>
<style>
body{font-family:Inter,sans-serif;background:#0D1117;color:#E6EDF3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;}
.card{background:#161B22;border:1px solid #30363D;border-radius:12px;padding:40px;max-width:600px;width:100%;}
h1{color:#00AEEF;} ul{list-style:none;padding:0;} li{padding:8px 0;border-bottom:1px solid #21262D;font-size:14px;}
a{display:inline-block;margin-top:24px;background:#00AEEF;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;margin-right:12px;}
.a2{background:#30363D;}
</style></head><body>
<div class="card">
    <h1>✅ Awards Migration Complete</h1>
    <ul>
        <?php foreach ($log as $item): ?>
        <li><?= $item ?></li>
        <?php endforeach; ?>
    </ul>
    <a href="/Skope Digital Academy/admin/certificates.php">Admin Certificates →</a>
    <a href="/Skope Digital Academy/tutor/students.php" class="a2">Tutor Students →</a>
</div>
</body></html>
