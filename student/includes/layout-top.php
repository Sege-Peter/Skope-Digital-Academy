<?php
/**
 * layout-top.php
 * Unified layout wrapper for the student portal.
 * Use: require_once 'includes/layout-top.php'; at the top of your page content.
 */
require_once 'includes/sidebar.php';
require_once 'includes/portal-data.php';
require_once 'includes/global-styles.php';

// Global Debug Logger Hook (Captures silent failures for a more stable portal)
function skope_debug_log($msg) {
    $logFile = __DIR__ . '/../../logs/portal_debug.log';
    if (!is_dir(dirname($logFile))) mkdir(dirname($logFile), 0777, true);
    error_log("[" . date("Y-m-d H:i:s") . "] " . $msg . "\n", 3, $logFile);
}

// Prepare variables for the view with security sanitization
$unread_count     = (int)$portal_unread_count;
$live_notifications = $portal_notifications;
$new_assignments   = $portal_assignments;
$cat_quizzes      = $portal_cats;
$cal_events        = $portal_cal_events;
?>

<style>
    /* Global Balanced Responsiveness Framework */
    :root {
        --max-portal-width: 1540px;
        --grid-gap: clamp(16px, 3vw, 32px);
        --container-padding: clamp(12px, 2.5vw, 40px);
    }
    
    .dashboard-wrapper {
        max-width: var(--max-portal-width);
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        transition: 0.3s ease;
    }

    .main-content {
        background: #fcfcfc;
        min-height: 100vh;
    }

    @media (min-width: 1200px) {
        main { padding: var(--container-padding) !important; }
        .dashboard-main { gap: var(--grid-gap); }
    }

    @media (max-width: 1024px) {
        .dashboard-main { grid-template-columns: 1fr !important; }
        .side-panel { display: none; }
    }
    
    @media (min-width: 1025px) {
        .mobile-header { display: none !important; }
    }

    /* Professional Touch-up for Mobile Header */
    .mobile-header {
        padding: 12px var(--container-padding);
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        z-index: 999;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    
    .mobile-header .search-box {
        background: #f1f5f9;
        border-radius: 12px;
        padding: 8px 16px;
        flex: 1;
        max-width: 400px;
    }

    .hamburger-btn {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: white;
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-main);
        cursor: pointer;
        transition: 0.2s;
    }
    .hamburger-btn:hover { background: var(--primary); color: white; }
</style>

<div class="main-content">
    <!-- Mobile Sticky Header -->
    <header class="mobile-header">
        <button class="hamburger-btn" onclick="document.getElementById('dashSidebar').classList.toggle('open')">
            <i class="fas fa-bars-staggered"></i>
        </button>
        
        <form action="search.php" method="GET" class="search-box mobile-search" style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-search" style="color: var(--text-dim); font-size: 0.8rem;"></i>
            <input type="text" name="q" placeholder="Quick find..." value="<?= htmlspecialchars($q ?? '') ?>" style="background: none; border: none; outline: none; flex: 1; font-size: 0.85rem; font-weight: 500;">
        </form>

        <div style="display: flex; align-items: center; gap: 8px;">
            <!-- Notifications (Mobile) -->
            <div style="position: relative;">
                <div style="width: 38px; height: 38px; border-radius: 10px; background: white; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; position: relative; cursor: pointer; color: var(--text-dim); transition: 0.2s;" onclick="const d = document.getElementById('notifDropdownM'); d.style.display = d.style.display === 'none' ? 'flex' : 'none'; event.stopPropagation();">
                    <i class="far fa-bell" style="font-size: 0.95rem;"></i>
                    <?php if($unread_count > 0): ?>
                        <span style="position: absolute; top: -2px; right: -2px; min-width: 16px; height: 16px; background: #ef4444; border-radius: 50%; border: 2px solid white; font-size: 0.55rem; color: white; font-weight: 900; display: flex; align-items: center; justify-content: center;"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                    <?php endif; ?>
                </div>
                <div id="notifDropdownM" style="display: none; flex-direction: column; position: absolute; top: 48px; right: -10px; background: white; border: 1px solid var(--border); border-radius: 16px; padding: 16px; width: 280px; box-shadow: var(--shadow-premium); z-index: 1000;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h6 style="margin: 0; font-size: 0.85rem; font-weight: 900; color: var(--text-main);">Pulse Alerts</h6>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px; max-height: 240px; overflow-y: auto;">
                    <?php if(empty($live_notifications)): ?>
                        <div style="text-align: center; padding: 20px 0; font-size: 0.8rem; color: var(--text-dim);">No alerts yet.</div>
                    <?php else: foreach(array_slice($live_notifications, 0, 4) as $n): ?>
                        <div style="display: flex; gap: 10px; padding: 10px; background: <?= empty($n['read_status']) ? '#F0F9FF' : '#f8fafc' ?>; border-radius: 10px;">
                            <div style="flex: 1; min-width: 0;">
                                <strong style="display: block; font-size: 0.75rem; font-weight: 800;"><?= htmlspecialchars($n['title'] ?? 'Notice') ?></strong>
                                <span style="font-size: 0.68rem; color: var(--text-dim); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($n['message']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                    </div>
                    <a href="notifications.php" style="display: block; text-align: center; margin-top: 12px; font-size: 0.75rem; font-weight: 800; color: var(--primary); text-decoration: none; padding: 10px; background: var(--primary-light); border-radius: 10px;">View All</a>
                </div>
            </div>

            <!-- Profile (Mobile) -->
            <div style="position: relative;">
                <div style="display: flex; align-items: center; cursor: pointer;" onclick="const d = document.getElementById('mobileDropdown'); d.style.display = d.style.display === 'none' ? 'flex' : 'none'; event.stopPropagation();">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: #f1f5f9; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-dim); font-size: 1rem; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                        <?php if(!empty($student['avatar'])): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="mobileDropdown" style="display: none; flex-direction: column; position: absolute; top: 48px; right: 0; background: white; border: 1px solid var(--border); border-radius: 16px; padding: 8px; width: 200px; box-shadow: var(--shadow-premium); z-index: 1000;">
                    <a href="profile.php" style="display: flex; align-items: center; gap: 10px; padding: 12px; color: var(--text-main); text-decoration: none; font-size: 0.85rem; border-radius: 10px; font-weight: 750;"><i class="fas fa-user-cog" style="color: var(--primary);"></i> Settings</a>
                    <div style="height: 1px; background: #f1f5f9; margin: 4px 0;"></div>
                    <a href="../logout.php" style="display: flex; align-items: center; gap: 10px; padding: 12px; color: #ef4444; text-decoration: none; font-size: 0.85rem; border-radius: 10px; font-weight: 750;"><i class="fas fa-power-off"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>


    <div class="dashboard-wrapper">
        <!-- Main Content Area -->
        <main style="padding: 16px; background: #fff; flex: 1;">
            <div class="dashboard-main" style="display: grid; gap: 32px;">
