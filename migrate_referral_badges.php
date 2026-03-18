<?php
require_once 'includes/db.php';

try {
    // 1. Update USERS table for Referral System
    $pdo->exec("ALTER TABLE `users` 
        ADD COLUMN `referral_code` VARCHAR(50) UNIQUE DEFAULT NULL AFTER `bio`,
        ADD COLUMN `referred_by` INT NULL AFTER `referral_code`,
        ADD COLUMN `merit_coins` DECIMAL(15,2) DEFAULT 0.00 AFTER `points`,
        ADD CONSTRAINT `fk_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ");

    // 2. Generate referral codes for existing users
    $stmt = $pdo->query("SELECT id FROM users");
    $users = $stmt->fetchAll();
    $updateStmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
    foreach ($users as $u) {
        $code = 'SDA' . $u['id'] . strtoupper(substr(md5(uniqid()), 0, 5));
        $updateStmt->execute([$code, $u['id']]);
    }

    // 3. Add more Badge Categories
    $pdo->exec("INSERT IGNORE INTO `badges` (name, description, icon, color, criteria_type, criteria_value) VALUES
        ('Referral Hero', 'Referred 5 successful learners', '🤝', '#00BFFF', 'referrals_count', 5),
        ('Academic Elite', 'Completed 10 courses', '🏅', '#FF8C00', 'courses_completed', 10),
        ('Quiz Champion', 'Passed 20 quizzes', '🏆', '#10B981', 'quizzes_passed', 20),
        ('Loyal Student', 'Referred 1st student', '✨', '#3B82F6', 'referrals_count', 1),
        ('Merit Multiplier', 'Earned 1000 merit coins', '💰', '#FBBF24', 'coins_earned', 1000)
    ");

    echo "Migration successful: Referral columns added and system badges expanded.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
