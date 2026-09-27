<style>
/* ════════════════════════════════════════════════
   TUTOR SIDEBAR — Premium Faculty Suite
   Theme: Ocean Blue
════════════════════════════════════════════════ */
.sidebar {
    position: fixed;
    left: 0; top: 0; bottom: 0;
    width: 260px;
    background: #fff;
    border-right: 1px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.04);
}

.tsd-logo-wrap {
    padding: 28px 28px 20px;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #f8fafc;
}
.tsd-logo-img { height: 44px; object-fit: contain; }

.tsd-nav {
    flex: 1;
    overflow-y: auto;
    padding: 12px 16px;
    scrollbar-width: none;
}
.tsd-nav::-webkit-scrollbar { display: none; }

.tsd-section-label {
    font-size: 0.6rem;
    font-weight: 950;
    color: #94A3B8;
    text-transform: uppercase;
    letter-spacing: 2px;
    padding: 24px 12px 10px;
}

.tsd-menu-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    border-radius: 14px;
    text-decoration: none;
    color: #475569;
    font-size: 0.9rem;
    font-weight: 700;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    margin-bottom: 3px;
    border: 1px solid transparent;
    position: relative;
}

.tsd-menu-item:hover {
    background: #E0F7FF;
    color: #0099CC;
    transform: translateX(4px);
    border-color: rgba(0, 191, 255, 0.15);
}

.tsd-menu-item.active {
    background: linear-gradient(135deg, #00BFFF 0%, #0099CC 100%);
    color: white;
    box-shadow: 0 8px 20px rgba(0, 191, 255, 0.25);
    border-color: transparent;
}

.tsd-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    background: #F8FAFC;
    flex-shrink: 0;
    transition: 0.25s;
    color: #64748B;
}

.tsd-menu-item:hover .tsd-icon {
    background: white;
    color: #0099CC;
    box-shadow: 0 2px 8px rgba(0, 191, 255, 0.15);
}

.tsd-menu-item.active .tsd-icon {
    background: rgba(255, 255, 255, 0.25);
    color: white;
}

.tsd-label { flex: 1; }

.tsd-badge {
    font-size: 0.6rem;
    font-weight: 950;
    padding: 3px 8px;
    border-radius: 50px;
    background: rgba(0, 191, 255, 0.15);
    color: #0099CC;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.tsd-menu-item.active .tsd-badge {
    background: rgba(255,255,255,0.25);
    color: white;
}

.tsd-footer {
    padding: 20px;
    border-top: 1px solid #f1f5f9;
}

.tsd-user-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border-radius: 16px;
    background: #F8FAFC;
    margin-bottom: 12px;
    border: 1px solid #F1F5F9;
}

.tsd-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #00BFFF 0%, #0099CC 100%);
    color: #fff;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 950;
    font-size: 1.1rem;
    flex-shrink: 0;
    box-shadow: 0 6px 14px rgba(0, 191, 255, 0.25);
}

.tsd-user-name {
    font-size: 0.9rem;
    font-weight: 950;
    color: #0F172A;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
    letter-spacing: -0.3px;
}

.tsd-user-role {
    font-size: 0.7rem;
    color: #64748B;
    font-weight: 700;
    margin-top: 2px;
}

.tsd-logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 13px;
    border-radius: 14px;
    background: #FEF2F2;
    color: #EF4444;
    font-size: 0.82rem;
    font-weight: 800;
    text-decoration: none;
    transition: 0.3s;
    border: 1px solid #FEE2E2;
    box-sizing: border-box;
}

.tsd-logout-btn:hover {
    background: #EF4444;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(239, 68, 68, 0.15);
}

@media (max-width: 1024px) {
    .sidebar {
        transform: translateX(-100%);
        width: 260px;
        z-index: 1100;
    }
    .sidebar.open {
        transform: translateX(0);
        box-shadow: 20px 0 50px rgba(0,0,0,0.12);
    }
}
</style>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Mobile Header Bar -->
<div class="mobile-portal-header" id="mobilePortalHeader">
    <img src="../assets/images/Skope Digital  logo.png" alt="Skope Digital Academy" class="mobile-logo">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Open navigation">
        <i class="fas fa-bars"></i>
    </button>
</div>

<aside class="sidebar" id="dashSidebar">
    <div class="tsd-logo-wrap">
        <img src="../assets/images/Skope Digital  logo.png" alt="Skope Digital Academy" class="tsd-logo-img">
    </div>

    <nav class="tsd-nav">
        <a href="index.php" class="tsd-menu-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-th-large"></i></span>
            <span class="tsd-label">Command Center</span>
        </a>

        <div class="tsd-section-label">Institutional</div>
        <a href="courses.php" class="tsd-menu-item <?= $current_page == 'courses.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-book-open"></i></span>
            <span class="tsd-label">Course Registry</span>
        </a>
        <a href="lessons.php" class="tsd-menu-item <?= ($current_page == 'lessons.php') ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-layer-group"></i></span>
            <span class="tsd-label">Curriculum Arc</span>
        </a>
        <a href="quizzes.php" class="tsd-menu-item <?= $current_page == 'quizzes.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-brain"></i></span>
            <span class="tsd-label">Assessments</span>
        </a>
        <a href="assignments.php" class="tsd-menu-item <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-laptop-code"></i></span>
            <span class="tsd-label">Project Audits</span>
        </a>
        <a href="analytics.php" class="tsd-menu-item <?= $current_page == 'analytics.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-chart-pie"></i></span>
            <span class="tsd-label">Performance</span>
        </a>

        <div class="tsd-section-label">Faculty</div>
        <a href="students.php" class="tsd-menu-item <?= $current_page == 'students.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-user-graduate"></i></span>
            <span class="tsd-label">Scholars</span>
        </a>
        <a href="revenue.php" class="tsd-menu-item <?= $current_page == 'revenue.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-wallet"></i></span>
            <span class="tsd-label">Revenue Share</span>
        </a>
        <a href="leaderboard.php" class="tsd-menu-item <?= $current_page == 'leaderboard.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-trophy"></i></span>
            <span class="tsd-label">Hall of Fame</span>
        </a>
        <a href="notifications.php" class="tsd-menu-item <?= $current_page == 'notifications.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-bell"></i></span>
            <span class="tsd-label">Alerts Center</span>
        </a>
        <a href="profile.php" class="tsd-menu-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <span class="tsd-icon"><i class="fas fa-id-card"></i></span>
            <span class="tsd-label">Master Profile</span>
        </a>
    </nav>

    <div class="tsd-footer">
        <div class="tsd-user-card">
            <div class="tsd-avatar"><?= strtoupper(substr($tutor['name'] ?? 'T', 0, 1)) ?></div>
            <div class="tsd-user-info">
                <div class="tsd-user-name"><?= htmlspecialchars($tutor['name'] ?? 'Tutor') ?></div>
                <div class="tsd-user-role">Faculty Elite</div>
            </div>
        </div>
        <a href="../logout.php" class="tsd-logout-btn">
            <i class="fas fa-power-off"></i> Secure Logoff
        </a>
    </div>
</aside>
