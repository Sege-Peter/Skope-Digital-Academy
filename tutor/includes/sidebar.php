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
        <div class="menu-label">Workspace</div>
        <a href="index.php" class="menu-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Tutor Dashboard</span>
        </a>
        
        <div class="menu-label">Content</div>
        <a href="courses.php" class="menu-item <?= $current_page == 'courses.php' ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i>
            <span>My Courses</span>
        </a>
        <a href="lessons.php" class="menu-item <?= $current_page == 'lessons.php' ? 'active' : '' ?>">
            <i class="fas fa-play-circle"></i>
            <span>Lesson Library</span>
        </a>
        
        <div class="menu-label">Student Engagement</div>
        <a href="quizzes.php" class="menu-item <?= $current_page == 'quizzes.php' ? 'active' : '' ?>">
            <i class="fas fa-brain"></i>
            <span>Quizzes</span>
        </a>
        <a href="assignments.php" class="menu-item <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
            <i class="fas fa-tasks"></i>
            <span>Assignments</span>
        </a>
        <a href="students.php" class="menu-item <?= $current_page == 'students.php' ? 'active' : '' ?>">
            <i class="fas fa-users-viewfinder"></i>
            <span>My Students</span>
        </a>
        
        <div class="menu-label">Earnings</div>
        <a href="revenue.php" class="menu-item <?= $current_page == 'revenue.php' ? 'active' : '' ?>">
            <i class="fas fa-wallet"></i>
            <span>Revenue Share</span>
        </a>
        
        <div class="menu-label">Support</div>
        <a href="tickets.php" class="menu-item <?= $current_page == 'tickets.php' ? 'active' : '' ?>">
            <i class="fas fa-question-circle"></i>
            <span>Help Desk</span>
        </a>
        <a href="profile.php" class="menu-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-id-card"></i>
            <span>Tutor Profile</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-ghost btn-block btn-sm" style="background: rgba(248, 81, 73, 0.1); color: var(--danger); border-color: rgba(248, 81, 73, 0.2);">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

