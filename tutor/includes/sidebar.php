<?php
/**
 * Tutor portal sidebar. Rendered once by includes/header.php (older pages that still include it are harmless).
 */
if (defined('TP_SIDEBAR_DONE')) return;
define('TP_SIDEBAR_DONE', true);

$current_page = $current_page ?? basename($_SERVER['PHP_SELF']);
$navCounts = $navCounts ?? ['grading' => 0, 'messages' => 0, 'alerts' => 0];
$tpNav = [
    'Overview' => [
        ['index.php', './', 'fa-house', 'Dashboard', 0],
    ],
    'Teaching' => [
        ['courses.php', 'courses', 'fa-book-open', 'Courses', 0],
        ['lessons.php', 'lessons', 'fa-layer-group', 'Lessons', 0],
        ['quizzes.php', 'quizzes', 'fa-circle-question', 'Quizzes', 0],
        ['assignments.php', 'assignments', 'fa-clipboard-check', 'Assignments', $navCounts['grading']],
    ],
    'Learners' => [
        ['students.php', 'students', 'fa-user-graduate', 'Learners', 0, ['student_profile.php', 'award_student.php']],
        ['messages.php', 'messages', 'fa-comments', 'Messages', $navCounts['messages']],
        ['leaderboard.php', 'leaderboard', 'fa-trophy', 'Leaderboard', 0],
    ],
    'Earnings' => [
        ['revenue.php', 'revenue', 'fa-wallet', 'Earnings', 0],
        ['analytics.php', 'analytics', 'fa-chart-column', 'Analytics', 0],
    ],
    'Account' => [
        ['notifications.php', 'notifications', 'fa-bell', 'Notifications', $navCounts['alerts']],
        ['profile.php', 'profile', 'fa-id-card', 'Profile', 0],
    ],
];
?>
<div class="tp-overlay" id="tpOverlay"></div>
<aside class="tp-side" id="tpSide" aria-label="Tutor portal navigation">
    <div class="tp-side-brand">
        <img src="../assets/images/skope-logo-512.png" alt="">
        <div><b>Skope Digital</b><small>Tutor portal</small></div>
        <button type="button" class="tp-side-close" data-tp-close-menu aria-label="Close menu"><i class="fas fa-xmark"></i></button>
    </div>
    <nav class="tp-nav">
        <?php foreach ($tpNav as $tpGroup => $tpItems): ?>
            <div class="tp-nav-group">
                <div class="tp-nav-label"><?= e($tpGroup) ?></div>
                <?php foreach ($tpItems as $tpIt):
                    $tpActive = $current_page === $tpIt[0] || in_array($current_page, $tpIt[5] ?? [], true); ?>
                    <a href="<?= e($tpIt[1]) ?>" class="<?= $tpActive ? 'active' : '' ?>"<?= $tpActive ? ' aria-current="page"' : '' ?>>
                        <i class="fas <?= e($tpIt[2]) ?>" aria-hidden="true"></i>
                        <span class="tp-nav-text"><?= e($tpIt[3]) ?></span>
                        <?php if ($tpIt[4] > 0): ?><span class="tp-count" aria-label="<?= (int)$tpIt[4] ?> new"><?= (int)min(99, $tpIt[4]) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>
    <div class="tp-side-foot">
        <a class="tp-side-user" href="profile">
            <?= td_avatar($tutor['avatar'] ?? null, $tutor['name'] ?? 'Tutor') ?>
            <span style="min-width:0"><b><?= e($tutor['name'] ?? 'Tutor') ?></b><small>Tutor</small></span>
        </a>
        <a class="tp-signout" href="../logout"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i> Sign out</a>
    </div>
</aside>
