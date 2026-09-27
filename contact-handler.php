<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Website Inquiry');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        header('Location: index.php?msg=all_fields_required#contact');
        exit;
    }

    try {
        // Save to dedicated contact_messages table (for admin email inbox)
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $phone, $subject, $message]);

        // Also save to support_tickets for backward compatibility
        $stmt2 = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'open')");
        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        $stmt2->execute([$user_id, $subject, "From: $name ($email)\nPhone: $phone\n\n" . $message]);

        header('Location: index.php?msg=sent#contact');
    } catch (Exception $e) {
        error_log($e->getMessage());
        header('Location: index.php?msg=error#contact');
    }
} else {
    header('Location: index.php');
}
?>
