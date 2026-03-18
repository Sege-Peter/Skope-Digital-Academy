<?php
require_once 'includes/db.php';

try {
    // 1. Add ID document column
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `id_document` VARCHAR(255) NULL AFTER `national_id` ");
    
    // 2. Create directory
    $upload_dir = 'uploads/id_docs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // 3. Create .htaccess to protect ID docs segment (security best practice)
    file_put_contents($upload_dir . '.htaccess', "Order Deny,Allow\nDeny from all");

    echo "Migration successful: ID document handling initialized.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
