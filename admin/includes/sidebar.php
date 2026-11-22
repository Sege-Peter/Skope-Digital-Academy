<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="dashSidebar">
    <div class="sidebar-header">
        <a href="../" class="navbar-logo">
            <img src="../assets/images/Skope Digital  logo.png" alt="Logo" style="height: 40px;">
        </a>
        <button class="sidebar-close" id="sidebarClose"><i class="fas fa-times"></i></button>
    </div>
    
    <nav class="sidebar-menu">
        <div class="menu-label">Main</div>
        <a href="./" class="menu-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="menu-label">Academy</div>
        <a href="courses" class="menu-item <?= $current_page == 'courses.php' ? 'active' : '' ?>">
            <i class="fas fa-book"></i>
            <span>Manage Courses</span>
        </a>
        <a href="course-pricing" class="menu-item <?= $current_page == 'course-pricing.php' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Pricing &amp; Tracks</span>
        </a>
        <a href="learning-settings" class="menu-item <?= $current_page == 'learning-settings.php' ? 'active' : '' ?>">
            <i class="fas fa-sliders"></i>
            <span>Learning Settings</span>
        </a>
        <a href="categories" class="menu-item <?= $current_page == 'categories.php' ? 'active' : '' ?>">
            <i class="fas fa-tags"></i>
            <span>Categories</span>
        </a>
        <a href="users" class="menu-item <?= $current_page == 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <span>User Management</span>
        </a>
        
        <div class="menu-label">Financials</div>
        <a href="verifications" class="menu-item <?= $current_page == 'verifications.php' ? 'active' : '' ?>">
            <i class="fas fa-shield-check"></i>
            <span>Waitlist & Audit</span>
        </a>
        <a href="revenue" class="menu-item <?= $current_page == 'revenue.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Revenue Analysis</span>
        </a>
        <a href="payments" class="menu-item <?= $current_page == 'payments.php' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i>
            <span>Master Ledger</span>
        </a>
        <a href="scholarships" class="menu-item <?= $current_page == 'scholarships.php' ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap"></i>
            <span>Scholarships</span>
        </a>
        
        <div class="menu-label">System</div>
        <a href="announcements" class="menu-item <?= $current_page == 'announcements.php' ? 'active' : '' ?>">
            <i class="fas fa-bullhorn"></i>
            <span>Announcements</span>
        </a>
        <a href="notifications" class="menu-item <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
        </a>
        <a href="tickets" class="menu-item <?= $current_page == 'tickets.php' ? 'active' : '' ?>">
            <i class="fas fa-headset"></i>
            <span>Support Tickets</span>
        </a>
        <a href="settings" class="menu-item <?= $current_page == 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i>
            <span>Platform Settings</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../logout" class="btn btn-ghost btn-block btn-sm" style="background: rgba(248, 81, 73, 0.1); color: var(--danger); border-color: rgba(248, 81, 73, 0.2);">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

