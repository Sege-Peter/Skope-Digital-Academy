<?php
require_once 'includes/db.php';
try {
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS paystack_reference VARCHAR(100) AFTER transaction_message");
    $pdo->exec("ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_type ENUM('course', 'registration', 'donation') DEFAULT 'course' AFTER paystack_reference");
    echo "Database schema synchronized successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
