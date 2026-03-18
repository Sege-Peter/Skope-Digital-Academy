<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access control: Tutor only
requireRole('tutor');

$tutor = currentUser();
$user  = $tutor; // Shared variable for sidebar compatibility
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Tutor Portal' ?> – SDAC Academy</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css"> <!-- Reusing admin.css for consistent dashboard look -->
    
    <link rel="icon" type="image/png" href="../assets/images/Skope Digital  logo.png">
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('dashSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const sidebarClose = document.getElementById('sidebarClose');
        
        window.toggleSidebar = function() {
            if (!sidebar) return;
            const isOpen = sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('open', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
        };
        
        if (sidebarClose) {
            sidebarClose.addEventListener('click', function() {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('open');
                document.body.style.overflow = '';
            });
        }
        
        // Close on backdrop click
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 1024 && sidebar && sidebar.classList.contains('open')) {
                const btn = e.target.closest('.nav-toggle');
                if (!sidebar.contains(e.target) && !btn) {
                    sidebar.classList.remove('open');
                    if (overlay) overlay.classList.remove('open');
                    document.body.style.overflow = '';
                }
            }
        });
    });
    </script>
</head>
<body class="dashboard-body">

