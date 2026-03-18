<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="dashSidebar">
    <div class="sidebar-header">
        <a href="../index.php" class="navbar-logo">
            <img src="../assets/images/Skope Digital  logo.png" alt="Logo" style="height: 40px;">
        </a>
    </div>
    
    <nav class="sidebar-menu">
        <div class="menu-label">Learning</div>
        <a href="index.php" class="menu-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>My Dashboard</span>
        </a>
        <a href="courses.php" class="menu-item <?= $current_page == 'courses.php' ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap"></i>
            <span>My Courses</span>
        </a>
        <a href="../courses.php" class="menu-item">
            <i class="fas fa-search"></i>
            <span>Browse All Courses</span>
        </a>
        
        <div class="menu-label">Assessments</div>
        <a href="quizzes.php" class="menu-item <?= $current_page == 'quizzes.php' ? 'active' : '' ?>">
            <i class="fas fa-brain"></i>
            <span>Quizzes</span>
        </a>
        <a href="assignments.php" class="menu-item <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
            <i class="fas fa-tasks"></i>
            <span>Assignments</span>
        </a>
        
        <div class="menu-label">Achievements</div>
        <a href="certificates.php" class="menu-item <?= $current_page == 'certificates.php' ? 'active' : '' ?>">
            <i class="fas fa-medal"></i>
            <span>Certificates</span>
        </a>
        <a href="badges.php" class="menu-item <?= $current_page == 'badges.php' ? 'active' : '' ?>">
            <i class="fas fa-award"></i>
            <span>My Badges</span>
        </a>
        
        <div class="menu-label">Account</div>
        <a href="payments.php" class="menu-item <?= $current_page == 'payments.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Payment History</span>
        </a>
        <a href="profile.php" class="menu-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i>
            <span>My Profile</span>
        </a>
        <a href="support.php" class="menu-item <?= $current_page == 'support.php' ? 'active' : '' ?>">
            <i class="fas fa-life-ring"></i>
            <span>Help Desk</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-ghost btn-block btn-sm" style="background: rgba(248, 81, 73, 0.1); color: var(--danger); border-color: rgba(248, 81, 73, 0.2);">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

