<?php
// Role-based Navigation Sidebar (shared across all dashboards)
?>
<!-- Mobile Dashboard Hamburger Toggle -->
<button class="dash-toggle" id="dashToggle" aria-label="Open Menu">
  <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="dashSidebar">
    <!-- Branding Hub -->
    <div class="sidebar-brand-container">
        <a href="../index.php" class="sidebar-logo">
            <img src="../assets/images/Skope Digital  logo.png" style="height: 36px;" alt="Skope Digital Academy Logo">
            <div class="brand-text">
                <span class="main">SKOPE <span class="accent">DIGITAL</span></span>
                <span class="sub">ACADEMY</span>
            </div>
        </a>
    </div>

    <!-- Navigation Registry -->
    <nav class="sidebar-nav-scroll">
        <ul class="sidebar-menu">
            <li>
                <a href="index.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i> <span>Dashboard Overview</span>
                </a>
            </li>

            <?php if ($user['role'] === 'student'): ?>
                <li class="menu-divider">Academics</li>
                <li>
                    <a href="courses.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : '' ?>">
                        <i class="fas fa-book-open"></i> <span>My Learning Path</span>
                    </a>
                </li>
                <li>
                    <a href="notifications.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                        <i class="fas fa-bell"></i> <span>System Alerts</span>
                        <?php if($unread_notif_count > 0): ?>
                            <span class="notif-badge-sidebar"><?= $unread_notif_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="quizzes.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'quizzes.php' ? 'active' : '' ?>">
                        <i class="fas fa-brain"></i> <span>Quiz Inventory</span>
                    </a>
                </li>
                <li>
                    <a href="assignments.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'assignments.php' ? 'active' : '' ?>">
                        <i class="fas fa-tasks"></i> <span>Project Backlog</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user"></i> <span>My Profile</span>
                    </a>
                </li>
                <li class="menu-divider">Achievements</li>
                <li>
                    <a href="certificates.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'active' : '' ?>">
                        <i class="fas fa-award"></i> <span>Credentials</span>
                    </a>
                </li>
                <li>
                    <a href="badges.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'badges.php' ? 'active' : '' ?>">
                        <i class="fas fa-medal"></i> <span>Merit Badges</span>
                    </a>
                </li>
                <li class="menu-divider">Network & Legacy</li>
                <li>
                    <a href="referral.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'referral.php' ? 'active' : '' ?>">
                        <i class="fas fa-network-wired"></i> <span>Referral Network</span>
                    </a>
                </li>
                <li>
                    <a href="transcript.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'transcript.php' ? 'active' : '' ?>">
                        <i class="fas fa-scroll"></i> <span>Academic Transcript</span>
                    </a>
                </li>
                <li>
                    <a href="leaderboard.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-trophy"></i> <span>Hall of Fame</span>
                    </a>
                </li>
                <li>
                    <a href="mentor.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'mentor.php' ? 'active' : '' ?>">
                        <i class="fas fa-robot"></i> <span>Scholarly AI</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($user['role'] === 'tutor'): ?>
                <li class="menu-divider">Curriculum Control</li>
                <li>
                    <a href="courses.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : '' ?>">
                        <i class="fas fa-chalkboard-teacher"></i> <span>Track Management</span>
                    </a>
                </li>
                <li>
                    <a href="notifications.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                        <i class="fas fa-bell"></i> <span>Alerts Center</span>
                        <?php if($unread_notif_count > 0): ?>
                            <span class="notif-badge-sidebar"><?= $unread_notif_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="students.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> <span>Student Roster</span>
                    </a>
                </li>
                <li>
                    <a href="assignments.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'assignments.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-signature"></i> <span>Assessments</span>
                    </a>
                </li>
                <li class="menu-divider">Financials & Impact</li>
                <li>
                    <a href="award_student.php" class="menu-link <?= (strpos(basename($_SERVER['PHP_SELF']), 'award') !== false) ? 'active' : '' ?>">
                        <i class="fas fa-trophy"></i> <span>Award Scholars</span>
                    </a>
                </li>
                <li>
                    <a href="analytics.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> <span>Clinical Analytics</span>
                    </a>
                </li>
                <li>
                    <a href="leaderboard.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-crown"></i> <span>Performance Hall</span>
                    </a>
                </li>
                <li>
                    <a href="revenue.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'revenue.php' ? 'active' : '' ?>">
                        <i class="fas fa-wallet"></i> <span>Revenue Hub</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-circle"></i> <span>Instructor Profile</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($user['role'] === 'admin'): ?>
                <li class="menu-divider">Institutional Oversight</li>
                <li>
                    <a href="courses.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : '' ?>">
                        <i class="fas fa-layer-group"></i> <span>Catalog Audit</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                        <i class="fas fa-users-cog"></i> <span>Identity Governance</span>
                    </a>
                </li>
                <li>
                    <a href="verifications.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'verifications.php' ? 'active' : '' ?>">
                        <i class="fas fa-check-double"></i> <span>Revenue Audit</span>
                    </a>
                </li>
                <li>
                    <a href="payroll.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'payroll.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-invoice-dollar"></i> <span>Payroll Hub</span>
                    </a>
                </li>
                <li>
                    <a href="leaderboard.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-medal"></i> <span>Merit Registry</span>
                    </a>
                </li>
                <li class="menu-divider">Operations</li>
                <li>
                    <a href="announcements.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : '' ?>">
                        <i class="fas fa-bullhorn"></i> <span>Global Notices</span>
                    </a>
                </li>
                <li>
                    <a href="scholarships.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'scholarships.php' ? 'active' : '' ?>">
                        <i class="fas fa-hand-holding-heart"></i> <span>Scholarships</span>
                    </a>
                </li>
                <li>
                    <a href="tickets.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : '' ?>">
                        <i class="fas fa-envelope-open-text"></i> <span>Support Desk</span>
                    </a>
                </li>
                <li>
                    <?php
                    try {
                        $unread_contact = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'")->fetchColumn();
                    } catch (Exception $e) { $unread_contact = 0; }
                    ?>
                    <a href="messages.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : '' ?>">
                        <i class="fas fa-inbox"></i>
                        <span>Contact Inbox</span>
                        <?php if ($unread_contact > 0): ?>
                            <span class="notif-badge-sidebar"><?= $unread_contact ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="notifications.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                        <i class="fas fa-bell"></i> <span>System Alerts</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-shield"></i> <span>Admin Profile</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Personnel Status Strip -->
    <div class="sidebar-footer">
        <div class="personnel-badge">
            <div class="avatar-shield">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div class="personnel-info">
                <span class="name"><?= htmlspecialchars($user['name']) ?></span>
                <span class="role"><?= $user['role'] ?> Quarters</span>
            </div>
        </div>
        <a href="../logout.php" class="logout-pill" title="Secure Exit">
            <i class="fas fa-power-off"></i> <span>Secure Logoff</span>
        </a>
    </div>
</aside>

<style>
    /* Institutional Sidebar Enhancements */
    .sidebar-brand-container { padding: 20px 24px; margin-bottom: 12px; }
    .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
    .brand-text { display: flex; flex-direction: column; line-height: 1.1; }
    .brand-text .main { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1rem; color: var(--dark); letter-spacing: -0.5px; }
    .brand-text .main .accent { color: var(--primary); }
    .brand-text .sub { font-size: 0.55rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; font-weight: 700; margin-top: 2px; }

    .sidebar-nav-scroll { flex: 1; overflow-y: auto; padding: 0 16px; scrollbar-width: none; }
    .sidebar-nav-scroll::-webkit-scrollbar { display: none; }

    .menu-divider { font-size: 0.68rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; padding: 24px 18px 10px; list-style: none; }
    
    .sidebar-footer { padding: 16px; border-top: 1px solid var(--dark-border); background: #fafafa; }
    .personnel-badge { display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border: 1px solid var(--dark-border); border-radius: 12px; margin-bottom: 8px; }
    .avatar-shield { width: 32px; height: 32px; border-radius: 8px; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(0,191,255,0.1); }
    .personnel-info { display: flex; flex-direction: column; line-height: 1.2; overflow: hidden; }
    .personnel-info .name { font-weight: 700; color: var(--dark); font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .personnel-info .role { font-size: 0.65rem; color: var(--text-dim); text-transform: capitalize; font-weight: 600; margin-top: 1px; }

    .logout-pill { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; height: 38px; border-radius: 10px; background: rgba(239, 68, 68, 0.05); color: #ef4444; font-weight: 700; font-size: 0.8rem; text-decoration: none; transition: 0.3s; }
    .logout-pill:hover { background: #ef4444; color: white; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(239, 68, 68, 0.2); }

    .notif-badge-sidebar {
        background: var(--danger);
        color: white;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 2px 8px;
        border-radius: 8px;
        margin-left: auto;
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
    }
</style>

<script>
window.toggleSidebar = function() {
    const sidebar = document.getElementById('dashSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('dashToggle');
    
    if (!sidebar || !overlay) return;

    if (sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        if (toggle) toggle.innerHTML = '<i class="fas fa-bars"></i>';
        document.body.style.overflow = '';
    } else {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        if (toggle) toggle.innerHTML = '<i class="fas fa-times"></i>';
        document.body.style.overflow = 'hidden';
    }
};

document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('dashToggle');
    if (toggle) {
        toggle.addEventListener('click', window.toggleSidebar);
    }
    
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) {
        overlay.addEventListener('click', window.toggleSidebar);
    }
});
</script>
