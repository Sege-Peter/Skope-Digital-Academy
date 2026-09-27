<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access control: Tutor only
requireRole('tutor');

$tutor = currentUser();
$user  = $tutor; // Shared variable for sidebar compatibility
$current_page = basename($_SERVER['PHP_SELF']); // Used by sidebar for active state
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Tutor Portal' ?> – Skope Digital Academy</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Base Styles (CSS Variables + components) -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/tutor-layout.css">

    <?php require_once 'global-styles.php'; ?>

    <link rel="icon" type="image/png" href="../assets/images/Skope Digital  logo.png">

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('dashSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        // Unified Sidebar Logic
        window.toggleSidebar = function() {
            if (!sidebar) return;
            const isOpen = sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('open', isOpen);
            
            // Update Body State
            if (isOpen) {
                document.body.style.overflow = 'hidden';
                document.body.classList.add('sidebar-active');
            } else {
                document.body.style.overflow = '';
                document.body.classList.remove('sidebar-active');
            }
        };

        // SDAC Professional Notification System
        window.SDAC = {
            showToast: function(msg, type = 'success') {
                const container = document.getElementById('toast-container');
                if(!container) return;
                
                const toast = document.createElement('div');
                toast.className = `sdac-toast ${type}`;
                
                const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' || type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle');
                
                toast.innerHTML = `
                    <div class="toast-icon-wrap"><i class="fas ${icon}"></i></div>
                    <div class="toast-body">${msg}</div>
                `;
                
                container.appendChild(toast);
                
                // Entrance animation
                requestAnimationFrame(() => toast.classList.add('visible'));

                // Auto-removal
                setTimeout(() => {
                    toast.classList.add('fade-out');
                    setTimeout(() => toast.remove(), 500);
                }, 4500);
            }
        };

        // Close protocols
        if (overlay) {
            overlay.addEventListener('click', toggleSidebar);
        }

        // Handle Escape Key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
                toggleSidebar();
            }
        });
    });
    </script>
    <style>
        #toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }
        .sdac-toast {
            min-width: 320px;
            max-width: 450px;
            background: white;
            border-radius: 20px;
            padding: 16px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
            display: flex;
            align-items: center;
            gap: 16px;
            pointer-events: auto;
            transform: translateX(120%);
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s;
            border: 1px solid #f1f5f9;
        }
        .sdac-toast.visible { transform: translateX(0); }
        .sdac-toast.fade-out { transform: translateX(120%); opacity: 0; }
        
        .toast-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .sdac-toast.success { border-left: 5px solid var(--success); }
        .sdac-toast.success .toast-icon-wrap { background: #ECFDF5; color: #10B981; }
        
        .sdac-toast.danger, .sdac-toast.error { border-left: 5px solid var(--danger); }
        .sdac-toast.danger .toast-icon-wrap, .sdac-toast.error .toast-icon-wrap { background: #FEF2F2; color: #EF4444; }
        
        .toast-body {
            font-size: 0.9rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.4;
        }

        @media (max-width: 640px) {
            #toast-container { top: auto; bottom: 24px; left: 16px; right: 16px; }
            .sdac-toast { min-width: 0; width: 100%; transform: translateY(150%); }
            .sdac-toast.visible { transform: translateY(0); }
            .sdac-toast.fade-out { transform: translateY(150%); }
        }
    </style>
</head>
<body class="dashboard-body">
    <div id="toast-container"></div>


