<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Back to the contact page with a status the page shows above the form.
function contact_back(string $msg): void {
    header('Location: contact?msg=' . $msg . '#contactForm');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact');
    exit;
}
if (!verifyCsrf()) contact_back('expired');

$name    = mb_substr(trim($_POST['name'] ?? ''), 0, 120);
$email   = mb_substr(trim($_POST['email'] ?? ''), 0, 190);
$phone   = mb_substr(trim($_POST['phone'] ?? ''), 0, 30);
$subject = mb_substr(trim($_POST['subject'] ?? '') ?: 'Website Inquiry', 0, 150);
$message = mb_substr(trim($_POST['message'] ?? ''), 0, 5000);

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    contact_back('all_fields_required');
}

try {
    // Save to dedicated contact_messages table (for admin email inbox)
    $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $subject, $message]);
    // Also save to support_tickets for backward compatibility
    $stmt2 = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status) VALUES (?, ?, ?, 'open')");
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $stmt2->execute([$user_id, $subject, "From: $name ($email)\nPhone: $phone\n\n" . $message]);
    contact_back('sent');
} catch (Exception $e) {
    error_log($e->getMessage());
    contact_back('error');
}
