<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Must be logged in at all to use this file
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'start') {
    // Only admins or tutors can initiate impersonation
    if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'tutor') {
        die("Unauthorized to use diagnostic login.");
    }
    
    $uid = (int)$_GET['uid'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $target = $stmt->fetch();
    
    if ($target) {
        // Save the real user data before overwriting
        $_SESSION['admin_impersonator_id'] = $_SESSION['user_id'];
        $_SESSION['admin_impersonator_role'] = $_SESSION['role'];
        
        loginUser($target);
        redirectByRole($target['role']);
    } else {
        die("Identity not found.");
    }

} elseif ($action === 'stop') {
    // Must currently be impersonating someone
    if (!isset($_SESSION['admin_impersonator_id'])) {
        die("You are not currently impersonating another identity.");
    }
    
    // Restore original
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_impersonator_id']]);
    $original = $stmt->fetch();
    
    unset($_SESSION['admin_impersonator_id']);
    unset($_SESSION['admin_impersonator_role']);
    
    loginUser($original);
    redirectByRole($original['role']);
}
