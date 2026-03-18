<?php
require_once 'db.php';
require_once 'auth.php';

// Auth Check (Base)
if (!isLoggedIn()) {
    header('Location: /Skope Digital Academy/login.php');
    exit;
}

$user = currentUser();

// Role Check Based on Directory
$current_path = $_SERVER['REQUEST_URI'];
if (strpos($current_path, '/admin/') !== false && $user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
if (strpos($current_path, '/tutor/') !== false && $user['role'] !== 'tutor') {
    header('Location: ../login.php');
    exit;
}
if (strpos($current_path, '/student/') !== false && $user['role'] !== 'student') {
    header('Location: ../login.php');
    exit;
}

// Student context if applicable
$student = ($user['role'] === 'student') ? $user : null;
$tutor   = ($user['role'] === 'tutor') ? $user : null;
$admin   = ($user['role'] === 'admin') ? $user : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'Dashboard' ?> – Skope Digital Academy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="../assets/css/main.css">
<style>
    :root { --sidebar-w: 280px; }
    body { background: var(--bg-light); color: var(--text-primary); }
    
    .sidebar { width: var(--sidebar-w); position: fixed; left: 0; top: 0; bottom: 0; background: white; border-right: 1px solid var(--dark-border); z-index: 1001; padding: 40px 24px; display: flex; flex-direction: column; transition: transform 0.3s ease; }
    .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; margin-bottom: 60px; }
    .sidebar-menu { list-style: none; display: flex; flex-direction: column; gap: 8px; flex-grow: 1; }
    .menu-link { display: flex; align-items: center; gap: 16px; padding: 14px 20px; border-radius: 12px; color: var(--text-muted); font-weight: 600; font-size: 0.95rem; text-decoration: none; transition: 0.3s; }
    .menu-link:hover, .menu-link.active { background: var(--primary-glow); color: var(--primary); }
    
    .main-content { margin-left: var(--sidebar-w); min-height: 100vh; padding: 40px 60px; transition: margin-left 0.3s ease; }
    .admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 48px; }
    
    /* Responsive Adjustments for Dashboard Styles */
    @media (max-width: 1024px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.open { transform: translateX(0); }
        .main-content { margin-left: 0; padding: 24px 20px 60px; }
        .nav-toggle { display: flex !important; }
        .admin-header { flex-direction: column; align-items: flex-start; gap: 20px; margin-bottom: 32px; }
    }
    
    .sidebar-overlay { position: fixed; inset: 0; background: rgba(13, 17, 23, 0.4); backdrop-filter: blur(4px); z-index: 1000; display: none; }
    .sidebar-overlay.open { display: block; }

    .table-card { background: white; border: 1px solid var(--dark-border); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
    .admin-table th { background: var(--bg-light); color: var(--text-dim); text-transform: uppercase; font-size: 0.72rem; letter-spacing: 1px; font-weight: 800; padding: 16px 24px; text-align: left; }
    .admin-table td { padding: 18px 24px; border-top: 1px solid var(--dark-border); font-size: 0.92rem; color: var(--text-primary); }
</style>
</head>
<body>
