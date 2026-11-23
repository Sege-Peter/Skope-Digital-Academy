<?php
/**
 * layout-top.php - student portal page shell (design v2).
 * Use: require_once 'includes/header.php'; ... require_once 'includes/layout-top.php'; <page content> require_once 'includes/layout-bottom.php';
 * Gives every page the sidebar, a sticky top bar (title, search, notifications, profile) and the content container.
 * Styles: assets/css/student-ui.css.
 */
require_once __DIR__ . '/portal-data.php';
require_once __DIR__ . '/sidebar.php';

// Global debug logger (kept for pages that call it)
if (!function_exists('skope_debug_log')) {
    function skope_debug_log($msg) {
        $logFile = __DIR__ . '/../../logs/portal_debug.log';
        if (!is_dir(dirname($logFile))) mkdir(dirname($logFile), 0777, true);
        error_log("[" . date("Y-m-d H:i:s") . "] " . $msg . "\n", 3, $logFile);
    }
}

// Variables older pages expect
$unread_count       = (int)($portal_unread_count ?? 0);
$live_notifications = $portal_notifications ?? [];
$new_assignments    = $portal_assignments ?? [];
$cat_quizzes        = $portal_cats ?? [];
$cal_events         = $portal_cal_events ?? [];
$has_panel          = basename($_SERVER['PHP_SELF']) == 'index.php'; // dashboard shows the right-hand panel

$tb_title = $pageTitle ?? 'My learning';
$tb_name  = trim((string)($student['name'] ?? 'Student'));
$tb_first = explode(' ', $tb_name)[0];
$tb_initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', $tb_name), 0, 2))));
?>
<div class="main-content<?= $has_panel ? ' has-panel' : '' ?>">
    <header class="sp-topbar">
        <button type="button" class="sp-icon-btn sp-menu-btn" onclick="toggleSidebar()" aria-label="Open menu"><i class="fas fa-bars"></i></button>
        <div class="sp-topbar-title"><?= htmlspecialchars($tb_title) ?></div>

        <form action="search" method="GET" class="sp-search" role="search">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" name="q" placeholder="Search courses, lessons, quizzes…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" aria-label="Search">
        </form>

        <!-- Notifications -->
        <div class="sp-pop" id="spPopNotif">
            <button type="button" class="sp-icon-btn" onclick="spPop('spPopNotif', event)" aria-label="Notifications">
                <i class="far fa-bell"></i>
                <?php if ($unread_count > 0): ?><span class="sp-dot"><?= $unread_count > 9 ? '9+' : $unread_count ?></span><?php endif; ?>
            </button>
            <div class="sp-pop-panel">
                <div class="sp-pop-head"><span>Notifications</span><?php if ($unread_count): ?><span class="sp-badge"><?= $unread_count ?> new</span><?php endif; ?></div>
                <?php if (empty($live_notifications)): ?>
                    <div class="sp-muted sp-small" style="text-align: center; padding: 24px 0;">You're all caught up.</div>
                <?php else: foreach (array_slice($live_notifications, 0, 5) as $n): ?>
                    <a href="notifications" class="sp-pop-item<?= empty($n['read_status']) ? ' unread' : '' ?>">
                        <strong><?= htmlspecialchars($n['title'] ?? 'Update') ?></strong>
                        <span><?= htmlspecialchars($n['message'] ?? '') ?></span>
                    </a>
                <?php endforeach; endif; ?>
                <a href="notifications" class="sp-pop-foot">See all notifications</a>
            </div>
        </div>

        <!-- Profile -->
        <div class="sp-pop" id="spPopUser">
            <button type="button" class="sp-user-btn" onclick="spPop('spPopUser', event)" aria-label="Account menu">
                <span class="sp-avatar">
                    <?php if (!empty($student['avatar'])): ?><img src="../uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" alt="" onerror="this.remove()"><?php endif; ?>
                    <?= htmlspecialchars($tb_initials ?: 'S') ?>
                </span>
                <span class="sp-user-name sp-hide-sm"><strong><?= htmlspecialchars($tb_first) ?></strong><span>Student</span></span>
                <i class="fas fa-chevron-down sp-hide-sm" style="font-size: .7rem; color: var(--text-muted);"></i>
            </button>
            <div class="sp-pop-panel" style="width: 230px;">
                <div style="padding: 8px 12px 10px; border-bottom: 1px solid var(--border); margin-bottom: 6px;">
                    <strong style="display: block; font-size: .88rem;"><?= htmlspecialchars($tb_name) ?></strong>
                    <span class="sp-muted" style="font-size: .76rem;"><?= htmlspecialchars($student['email'] ?? '') ?></span>
                </div>
                <a href="profile" class="sp-menu-link"><i class="fas fa-user-gear"></i> Profile & settings</a>
                <a href="achievements" class="sp-menu-link"><i class="fas fa-trophy"></i> Achievements</a>
                <a href="payments" class="sp-menu-link"><i class="fas fa-receipt"></i> Payments</a>
                <a href="support" class="sp-menu-link"><i class="fas fa-life-ring"></i> Help & support</a>
                <a href="../logout" class="sp-menu-link danger"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a>
            </div>
        </div>
    </header>

    <div class="dashboard-wrapper<?= $has_panel ? ' has-panel' : '' ?>">
        <main>
            <div class="dashboard-main">
