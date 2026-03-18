<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? 'Website Inquiry');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($message)) {
        header('Location: index.php?msg=all_fields_required#contact');
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'open')");
        // Use user_id if logged in, else null
        $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
        $stmt->execute([$user_id, $subject, "From: $name ($email)\n\n" . $message]);
        
        header('Location: index.php?msg=sent#contact');
    } catch (Exception $e) {
        error_log($e->getMessage());
        header('Location: index.php?msg=error#contact');
    }
} else {
    header('Location: index.php');
}
?>
