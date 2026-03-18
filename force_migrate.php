<?php
require_once 'includes/db.php';

try {
    echo "Starting Force Migration...\n";
    
    // Use traditional ALTER TABLE if IF NOT EXISTS is not supported in the user's MariaDB/MySQL version
    $columns = [
        "referral_code" => "ALTER TABLE users ADD COLUMN referral_code VARCHAR(50) UNIQUE DEFAULT NULL AFTER bio",
        "referred_by" => "ALTER TABLE users ADD COLUMN referred_by INT NULL AFTER referral_code",
        "merit_coins" => "ALTER TABLE users ADD COLUMN merit_coins DECIMAL(15,2) DEFAULT 0.00 AFTER points",
        "dob" => "ALTER TABLE users ADD COLUMN dob DATE NULL AFTER bio",
        "gender" => "ALTER TABLE users ADD COLUMN gender ENUM('male','female','other') NULL AFTER dob",
        "county" => "ALTER TABLE users ADD COLUMN county VARCHAR(100) NULL AFTER gender",
        "nationality" => "ALTER TABLE users ADD COLUMN nationality VARCHAR(100) DEFAULT 'Kenyan' AFTER county",
        "national_id" => "ALTER TABLE users ADD COLUMN national_id VARCHAR(50) UNIQUE NULL AFTER nationality",
        "education_level" => "ALTER TABLE users ADD COLUMN education_level VARCHAR(100) NULL AFTER national_id",
        "id_document" => "ALTER TABLE users ADD COLUMN id_document VARCHAR(255) NULL AFTER education_level"
    ];

    foreach ($columns as $name => $sql) {
        try {
            $pdo->exec($sql);
            echo "Added column: $name\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "Column $name already exists, skipping.\n";
            } else {
                echo "Error adding $name: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "Force Migration Finished.";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
}
