<?php
/**
 * Admins can use the tutor or student portal with THEIR OWN account (same user, same data) – not as another person.
 * The session keeps portal_home_role = 'admin' so "Back to admin" (or opening any admin page) restores admin access.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireLogin();

$to = (string)($_POST['to'] ?? $_GET['to'] ?? '');
$home = (string)($_SESSION['portal_home_role'] ?? '');
$log = function (string $what) use ($pdo) {
    try { $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, 'portal_switch', ?, ?)")->execute([$_SESSION['user_id'], $what, $_SERVER['REMOTE_ADDR'] ?? '']); } catch (Throwable $e) {}
};

if ($to === 'admin') {
    // Back to admin: only for a session that started as admin
    if ($home === 'admin') {
        $_SESSION['role'] = 'admin';
        unset($_SESSION['portal_home_role']);
        $log('Back to the admin panel');
    }
    redirectByRole((string)$_SESSION['role']);
}

if (!in_array($to, ['tutor', 'student'], true) || $_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    http_response_code(400);
    exit('This link has expired. Go back to the admin panel and use Switch portal again.');
}
$real = $home !== '' ? $home : (string)($_SESSION['role'] ?? '');
if ($real !== 'admin' || isset($_SESSION['admin_impersonator_id'])) { http_response_code(403); exit('Not allowed.'); }

$_SESSION['portal_home_role'] = 'admin';
$_SESSION['role'] = $to;
$log('Opened the ' . $to . ' portal with their own account');
redirectByRole($to);
