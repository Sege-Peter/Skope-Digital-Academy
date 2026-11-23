<?php
/**
 * Student portal sidebar (design v2). Included by layout-top.php, or directly by a few pages.
 * Needs $pdo and $student (from includes/header.php).
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Small numbers for the menu: unread notifications, level/XP for the profile card
$sp_unread = 0;
$sp_level = ['level' => 1, 'title' => 'Newcomer', 'pct' => 0];
try {
    // Only this student's own notifications plus broadcasts (no target)
    $sp_q = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE (target_user_id = ? OR (target_user_id IS NULL AND user_role IN ('student', 'all'))) AND (read_status = 0 OR read_status IS NULL)");
    $sp_q->execute([$student['id']]);
    $sp_unread = (int)$sp_q->fetchColumn();
} catch (Throwable $e) {}
try {
    if (!function_exists('xp_level')) require_once __DIR__ . '/../../includes/progress.php';
    $sp_level = xp_level((int)($student['xp'] ?? 0));
} catch (Throwable $e) {}

$sp_name = trim((string)($student['name'] ?? 'Student'));
$sp_initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', $sp_name), 0, 2))));

$sp_menu = [
    'Learn' => [
        ['./', 'fa-house', 'Dashboard', ['index.php']],
        ['courses', 'fa-graduation-cap', 'My courses', ['courses.php', 'classroom.php']],
        ['../courses', 'fa-compass', 'Browse courses', []],
        ['../learn', 'fa-code', 'Coding tutorials', []],
        ['../lab', 'fa-flask', 'Learning Lab', []],
    ],
    'Practice' => [
        ['quizzes', 'fa-circle-question', 'Quizzes', ['quizzes.php', 'take-quiz.php']],
        ['assignments', 'fa-clipboard-check', 'Assignments', ['assignments.php']],
        ['mentor', 'fa-robot', 'AI mentor', ['mentor.php']],
    ],
    'Progress' => [
        ['achievements', 'fa-trophy', 'Achievements', ['achievements.php', 'badges.php']],
        ['leaderboard', 'fa-ranking-star', 'Leaderboard', ['leaderboard.php']],
        ['certificates', 'fa-certificate', 'Certificates', ['certificates.php', 'view-certificate.php']],
        ['transcript', 'fa-file-lines', 'Transcript', ['transcript.php']],
    ],
    'Connect' => [
        ['community', 'fa-users', 'Community', ['community.php']],
        ['messages', 'fa-comment-dots', 'Messages', ['messages.php']],
        ['notifications', 'fa-bell', 'Notifications', ['notifications.php'], $sp_unread],
        ['announcements', 'fa-bullhorn', 'Announcements', ['announcements.php']],
        ['referral', 'fa-gift', 'Refer a friend', ['referral.php']],
    ],
    'Account' => [
        ['payments', 'fa-receipt', 'Payments', ['payments.php']],
        ['profile', 'fa-user-gear', 'Profile & settings', ['profile.php']],
        ['support', 'fa-life-ring', 'Help & support', ['support.php']],
    ],
];
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="dashSidebar" aria-label="Student menu">
    <div class="sidebar-header">
        <a href="../" class="sp-brand" title="Skope Digital Academy home">
            <span class="sp-brand-logo"><img src="../assets/images/Skope Digital  logo.png" alt=""></span>
            <span class="sp-brand-text"><strong>Skope Digital</strong><span>Student portal</span></span>
        </a>
        <button type="button" class="sp-side-btn sp-hide-mobile" onclick="toggleDesktopFocus()" title="Shrink or expand the menu" aria-label="Shrink or expand the menu">
            <i class="fas fa-angles-left" id="spMiniIcon"></i>
        </button>
        <button type="button" class="sp-side-btn sp-show-mobile" onclick="toggleSidebar()" aria-label="Close menu">
            <i class="fas fa-xmark"></i>
        </button>
    </div>

    <nav class="sidebar-menu">
        <?php foreach ($sp_menu as $group => $items): ?>
            <div class="menu-label"><?= $group ?></div>
            <?php foreach ($items as $it):
                [$href, $icon, $label, $pages] = $it;
                $count = $it[4] ?? 0;
                $active = in_array($current_page, $pages, true);
            ?>
                <a href="<?= $href ?>" class="menu-item<?= $active ? ' active' : '' ?>" title="<?= $label ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                    <i class="fas <?= $icon ?>"></i>
                    <span><?= $label ?></span>
                    <?php if ($count > 0): ?><em class="sp-count" style="font-style: normal;"><?= $count > 99 ? '99+' : $count ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <a href="../logout" class="menu-item sp-logout" title="Log out" style="margin-top: 10px;">
            <i class="fas fa-arrow-right-from-bracket"></i>
            <span>Log out</span>
        </a>
    </nav>

    <a href="achievements" class="sp-side-card" title="Your level and XP">
        <span class="sp-avatar">
            <?php if (!empty($student['avatar'])): ?>
                <img src="../uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" alt="" onerror="this.remove()">
            <?php endif; ?>
            <?= htmlspecialchars($sp_initials ?: 'S') ?>
        </span>
        <span class="sp-who">
            <strong><?= htmlspecialchars($sp_name) ?></strong>
            <span>Level <?= (int)$sp_level['level'] ?> · <?= htmlspecialchars($sp_level['title']) ?></span>
            <span class="sp-xpbar"><i style="width: <?= max(3, (int)$sp_level['pct']) ?>%;"></i></span>
        </span>
    </a>
</aside>
<style>
    .sp-show-mobile { display: none !important; }
    @media (max-width: 1024px) { .sp-show-mobile { display: inline-flex !important; } .sp-hide-mobile { display: none !important; } }
    .sp-avatar { position: relative; }
    .sp-avatar img { position: absolute; inset: 0; }
</style>
