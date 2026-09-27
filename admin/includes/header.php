<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access control: Admin only
requireRole('admin');

$admin = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin Dashboard' ?> – Skope Digital Academy</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= time() ?>">
    
    <link rel="icon" type="image/png" href="../assets/images/Skope Digital  logo.png">
    
    <style>
        /* ── Stealth Report Protocol ── */
        .print-only { display: none !important; }
        
        @media print {
            body { background: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sidebar, .dashboard-sidebar, .sidebar-wrapper, .admin-header, .pay-header, .dash-header, .sidebar-overlay, .nav-toggle, .dash-toggle, .btn-sm, .screen-only, nav, header, button, .admin-alert { display: none !important; }
            .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            .admin-body { padding: 0 !important; }
            .print-only { display: block !important; }
            .table-card, .pay-table-card, .chart-card { border: none !important; box-shadow: none !important; }
            * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
        }
    </style>
</head>
<body class="dashboard-body">

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
    
    // Close on backdrop click (optional but good)
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
