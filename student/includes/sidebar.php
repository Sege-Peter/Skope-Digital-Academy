<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="dashSidebar">
    <div class="sidebar-header" style="position: relative;">
        <a href="../index.php" class="navbar-logo">
            <img src="../assets/images/Skope Digital  logo.png" alt="Logo" class="logo-img" style="height: 40px;">
        </a>
        <!-- Integrated Focused-Mode Logic (Desktop + Mobile) -->
        <button class="menu-toggle-btn desktop-only" onclick="toggleDesktopFocus()">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
        <button class="mobile-only mobile-close-btn" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>


<style>
    /* Full Studio Focus State */
    .dashboard-body.studio-focus .sidebar { transform: translateX(-100%); }
    .dashboard-body.studio-focus .main-content { margin-left: 0 !important; width: 100% !important; }
    .dashboard-body.studio-focus .recall-btn { display: flex !important; }

    .recall-btn { 
        position: fixed; left: 24px; top: 24px; width: 44px; height: 44px; 
        background: white; border: 1px solid var(--border); border-radius: 14px; 
        display: none; align-items: center; justify-content: center; cursor: pointer; 
        z-index: 9999; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: 0.3s; color: var(--primary);
    }
    .recall-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: scale(1.1); }

    .menu-toggle-btn { 
        position: absolute; right: -15px; top: 32px; width: 30px; height: 30px; 
        background: white; border: 1px solid var(--border); border-radius: 50%; 
        display: flex; align-items: center; justify-content: center; cursor: pointer; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.08); color: var(--text-dim); transition: 0.3s; z-index: 1000;
    }
    .menu-toggle-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

    @media (max-width: 1024px) {
        .desktop-only { display: none !important; }
        .recall-btn { display: none !important; }
    }
</style>

<div class="recall-btn" onclick="toggleDesktopFocus()">
    <i class="fas fa-chevron-right"></i>
</div>

<style>
    .menu-item { display: flex; align-items: center; gap: 16px; padding: 14px 20px; color: #64748b; text-decoration: none; border-radius: 12px; font-size: 0.95rem; font-weight: 700; transition: 0.3s; margin-bottom: 4px; border: 1px solid transparent; white-space: nowrap; }
    .menu-item:hover { background: #f8fafc; color: var(--primary); transform: translateX(4px); }
    .menu-item.active { background: #F0F9FF; color: var(--primary); box-shadow: 0 4px 12px rgba(0, 174, 239, 0.08); border-color: rgba(0, 174, 239, 0.1); }
    .menu-item i { width: 22px; text-align: center; font-size: 1.1rem; opacity: 0.8; }
    .menu-label { padding: 24px 20px 8px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; color: #94a3b8; letter-spacing: 1.5px; }

    @media (max-width: 1024px) {
        .menu-toggle-btn { display: none; }
    }
</style>


    <nav class="sidebar-menu" style="flex: 1; padding: 12px;">
        <div class="menu-label">Main Console</div>
        <a href="index.php" class="menu-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Overview</span>
        </a>
        <a href="courses.php" class="menu-item <?= $current_page == 'courses.php' ? 'active' : '' ?>">
            <i class="fas fa-graduation-cap"></i>
            <span>Learning Path</span>
        </a>
        
        <div class="menu-label">Evaluation</div>
        <a href="quizzes.php" class="menu-item <?= in_array($current_page, ['quizzes.php', 'take-quiz.php']) ? 'active' : '' ?>">
            <i class="fas fa-brain"></i>
            <span>Challenges</span>
        </a>
        <a href="assignments.php" class="menu-item <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Assignments</span>
        </a>

        <div class="menu-label">Network Hub</div>
        <a href="leaderboard.php" class="menu-item <?= $current_page == 'leaderboard.php' ? 'active' : '' ?>">
            <i class="fas fa-star"></i>
            <span>Leaderboard</span>
        </a>
        <a href="referral.php" class="menu-item <?= $current_page == 'referral.php' ? 'active' : '' ?>">
            <i class="fas fa-network-wired"></i>
            <span>Network Growth</span>
        </a>
        <a href="mentor.php" class="menu-item <?= $current_page == 'mentor.php' ? 'active' : '' ?>">
            <i class="fas fa-microchip"></i>
            <span>AI Mentor</span>
        </a>
        <a href="community.php" class="menu-item <?= $current_page == 'community.php' ? 'active' : '' ?>">
            <i class="fas fa-users-viewfinder"></i>
            <span>Community</span>
        </a>

        <div class="menu-label">Personalization</div>
        <a href="transcript.php" class="menu-item <?= $current_page == 'transcript.php' ? 'active' : '' ?>">
            <i class="fas fa-file-contract"></i>
            <span>Academic Record</span>
        </a>
        <a href="profile.php" class="menu-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user-circle"></i>
            <span>Account Setting</span>
        </a>
        <a href="../logout.php" class="menu-item" style="color: #ef4444; margin-top: 12px; border: 1px solid transparent;">
            <i class="fas fa-power-off"></i>
            <span>Secure Logout</span>
        </a>
    </nav>


</aside>
