<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Must be logged in at all to use this file
if (!isLoggedIn()) {
    header("Location: login");
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'start') {
    // "View as student" for support: POST + CSRF only, students only.
    // Admins can view any active student; tutors only students enrolled in one of their courses.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
        http_response_code(400);
        exit('This link has expired. Go back and use the button again.');
    }
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['admin', 'tutor'], true) || isset($_SESSION['admin_impersonator_id'])) {
        http_response_code(403);
        exit('Not allowed.');
    }
    $uid = (int)($_POST['uid'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' AND status = 'active'");
    $stmt->execute([$uid]);
    $target = $stmt->fetch();
    if ($target && $role === 'tutor') {
        $own = $pdo->prepare("SELECT 1 FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.student_id = ? AND c.tutor_id = ? LIMIT 1");
        $own->execute([$uid, (int)$_SESSION['user_id']]);
        if (!$own->fetchColumn()) $target = false;
    }
    if (!$target) {
        http_response_code(404);
        exit('That student was not found in your courses.');
    }
    $_SESSION['admin_impersonator_id'] = $_SESSION['user_id'];
    $_SESSION['admin_impersonator_role'] = $role;
    loginUser($target);
    redirectByRole('student');

} elseif ($action === 'stop') {
    // Must currently be impersonating someone
    if (!isset($_SESSION['admin_impersonator_id'])) {
        die("You are not currently impersonating another identity.");
    }
    
    // Restore original
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_impersonator_id']]);
    $original = $stmt->fetch();
    
    $back = (string)($_SESSION['admin_impersonator_back'] ?? '');
    unset($_SESSION['admin_impersonator_id'], $_SESSION['admin_impersonator_role'], $_SESSION['admin_impersonator_back']);

    loginUser($original);
    // Return admins to the admin page they switched from
    if ($original['role'] === 'admin' && preg_match('~^admin/[a-z0-9_-]*(\.php)?(\?[^#]*)?$~i', $back)) {
        header('Location: ' . $back);
        exit;
    }
    redirectByRole($original['role']);
}
