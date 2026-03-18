<?php
require_once 'includes/db.php';

try {
    // 1. Add column if it doesn't exist
    $pdo->exec("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `admission_number` VARCHAR(50) DEFAULT NULL UNIQUE AFTER `role`");

    // 2. Generate admission numbers for existing students
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'student' AND (admission_number IS NULL OR admission_number = '')");
    $students = $stmt->fetchAll();

    $count = 0;
    foreach ($students as $s) {
        $adm_number = 'SDA/' . date('Y') . '/' . str_pad($s['id'], 4, '0', STR_PAD_LEFT);
        $update = $pdo->prepare("UPDATE users SET admission_number = ? WHERE id = ?");
        $update->execute([$adm_number, $s['id']]);
        $count++;
    }

    echo "Migration complete. Added column and assigned ADM numbers to $count existing students.\n";

} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists. Proceeding to assign missing numbers...\n";
        
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'student' AND (admission_number IS NULL OR admission_number = '')");
        $students = $stmt->fetchAll();
        $count = 0;
        foreach ($students as $s) {
            $adm_number = 'SDA/' . date('Y') . '/' . str_pad($s['id'], 4, '0', STR_PAD_LEFT);
            $update = $pdo->prepare("UPDATE users SET admission_number = ? WHERE id = ?");
            $update->execute([$adm_number, $s['id']]);
            $count++;
        }
        echo "Assigned ADM numbers to $count existing students.\n";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
