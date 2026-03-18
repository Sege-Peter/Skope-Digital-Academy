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
    <a href="../index.php" class="sidebar-logo" style="flex-shrink: 0;">
        <img src="../assets/images/Skope Digital  logo.png" style="height: 44px; flex-shrink: 0;" alt="Logo">
        <div style="line-height: 1; overflow: hidden;">
            <div style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 0.95rem; color: var(--dark); white-space: nowrap;">SKOPE <span style="color:var(--primary)">DIGITAL</span></div>
            <div style="font-size: 0.58rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; white-space: nowrap;">ACADEMY</div>
        </div>
    </a>

    <ul class="sidebar-menu">
        <!-- Dashboard Home -->
        <li>
            <a href="index.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> <span>Home Overview</span>
            </a>
        </li>

        <?php if ($user['role'] === 'student'): ?>
            <li>
                <a href="courses.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : '' ?>">
                    <i class="fas fa-book-open"></i> <span>My Learning Path</span>
                </a>
            </li>
            <li>
                <a href="quizzes.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'quizzes.php' ? 'active' : '' ?>">
                    <i class="fas fa-brain"></i> <span>Quiz History</span>
                </a>
            </li>
            <li>
                <a href="assignments.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'assignments.php' ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i> <span>Project Backlog</span>
                </a>
            </li>
            <li>
                <a href="certificates.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'active' : '' ?>">
                    <i class="fas fa-award"></i> <span>My Credentials</span>
                </a>
            </li>
            <li>
                <a href="badges.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'badges.php' ? 'active' : '' ?>">
                    <i class="fas fa-medal"></i> <span>My Badges</span>
                </a>
            </li>
            <li>
                <a href="transcript.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'transcript.php' ? 'active' : '' ?>">
                    <i class="fas fa-scroll"></i> <span>My Transcript</span>
                </a>
            </li>
            <li>
                <a href="mentor.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'mentor.php' ? 'active' : '' ?>">
                    <i class="fas fa-robot"></i> <span>AI Mentor</span>
                </a>
            </li>
            <li>
                <a href="support.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'support.php' ? 'active' : '' ?>">
                    <i class="fas fa-life-ring"></i> <span>Help Desk</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($user['role'] === 'tutor'): ?>
            <li>
                <a href="courses.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : '' ?>">
                    <i class="fas fa-chalkboard-teacher"></i> <span>Course Creation</span>
                </a>
            </li>
            <li>
                <a href="students.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> <span>Student Roster</span>
                </a>
            </li>
            <li>
                <a href="assignments.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'assignments.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-signature"></i> <span>Grade Submissions</span>
                </a>
            </li>
            <li>
                <a href="award_student.php" class="menu-link <?= (strpos(basename($_SERVER['PHP_SELF']), 'award') !== false) ? 'active' : '' ?>">
                    <i class="fas fa-trophy"></i> <span>Award Students</span>
                </a>
            </li>
            <li>
                <a href="analytics.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> <span>Sales & Impact</span>
                </a>
            </li>
            <li>
                <a href="revenue.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'revenue.php' ? 'active' : '' ?>">
                    <i class="fas fa-wallet"></i> <span>Revenue & Settlements</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-circle"></i> <span>Tutor Profile</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($user['role'] === 'admin'): ?>
            <li>
                <a href="courses.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : '' ?>">
                    <i class="fas fa-layer-group"></i> <span>Course Catalog</span>
                </a>
            </li>
            <li>
                <a href="categories.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : '' ?>">
                    <i class="fas fa-tags"></i> <span>Curriculum Categories</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i> <span>Stakeholders</span>
                </a>
            </li>
            <li>
                <a href="verifications.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'verifications.php' ? 'active' : '' ?>">
                    <i class="fas fa-check-double"></i> <span>Revenue Audit</span>
                </a>
            </li>
            <li>
                <a href="announcements.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : '' ?>">
                    <i class="fas fa-bullhorn"></i> <span>Portal Notices</span>
                </a>
            </li>
            <li>
                <a href="certificates.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'active' : '' ?>">
                    <i class="fas fa-certificate"></i> <span>Certificates & Awards</span>
                </a>
            </li>
            <li>
                <a href="scholarships.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'scholarships.php' ? 'active' : '' ?>">
                    <i class="fas fa-hand-holding-heart"></i> <span>Scholarships</span>
                </a>
            </li>
            <li>
                <a href="tickets.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'tickets.php' ? 'active' : '' ?>">
                    <i class="fas fa-envelope-open-text"></i> <span>Support Tickets</span>
                </a>
            </li>
            <li>
                <a href="notifications.php" class="menu-link <?= basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : '' ?>">
                    <i class="fas fa-satellite-dish"></i> <span>Broadcast Center</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Bottom: Profile strip + Logout -->
    <div style="padding-top: 32px; border-top: 1px solid var(--dark-border); margin-top: auto;">
        <div style="display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--bg-light); border-radius: 12px; margin-bottom: 16px;">
            <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-glow); display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 800; font-size: 1rem; flex-shrink: 0;">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <div style="min-width: 0;">
                <div style="font-weight: 700; font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--dark);"><?= htmlspecialchars($user['name']) ?></div>
                <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: capitalize;"><?= $user['role'] ?></div>
            </div>
        </div>
        <a href="../logout.php" class="menu-link" style="color: var(--danger);">
            <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
        </a>
    </div>
</aside>

<script>
(function() {
  const toggle = document.getElementById('dashToggle');
  const sidebar = document.getElementById('dashSidebar');
  const overlay = document.getElementById('sidebarOverlay');

  if (!toggle || !sidebar || !overlay) return;

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
    toggle.innerHTML = '<i class="fas fa-times"></i>';
    document.body.style.overflow = 'hidden';
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    toggle.innerHTML = '<i class="fas fa-bars"></i>';
    document.body.style.overflow = '';
  }

  toggle.addEventListener('click', function() {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });

  overlay.addEventListener('click', closeSidebar);
})();
</script>
