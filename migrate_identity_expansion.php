<?php
require_once 'includes/db.php';

try {
    // 1. Update USERS table with new demographic and academic identifiers
    $pdo->exec("ALTER TABLE `users` 
        ADD COLUMN `dob` DATE NULL AFTER `bio`,
        ADD COLUMN `gender` ENUM('male','female','other') NULL AFTER `dob`,
        ADD COLUMN `county` VARCHAR(100) NULL AFTER `gender`,
        ADD COLUMN `nationality` VARCHAR(100) DEFAULT 'Kenyan' AFTER `county`,
        ADD COLUMN `national_id` VARCHAR(50) UNIQUE NULL AFTER `nationality`,
        ADD COLUMN `education_level` VARCHAR(100) NULL AFTER `national_id`
    ");

    echo "Migration successful: Demographic identity columns added to users table.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
