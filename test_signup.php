<?php
require_once 'includes/db.php';

try {
    $name = "Test Student " . time();
    $email = "test" . time() . "@example.com";
    $password = password_hash("password123", PASSWORD_BCRYPT);
    $role = 'student';
    $status = 'active';
    $new_ref_code = 'SDA_TEST_' . time();
    $referred_by = null; // or test with an existing ID

    $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, status, email_verified, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
    $insert->execute([$name, $email, $password, $role, $status, $new_ref_code, $referred_by]);

    echo "Success: User created with ID " . $pdo->lastInsertId();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
