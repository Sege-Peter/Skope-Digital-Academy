<?php
/** Admin navigation – the single sidebar for every admin page. */
require_once __DIR__ . '/ui.php';
$current_page = basename($_SERVER['PHP_SELF']);
$axCounts = [
    'verifications' => ax_scalar($pdo, "SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
    'messages'      => ax_scalar($pdo, "SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'"),
    'tickets'       => ax_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE status = 'open'"),
    'courses'       => ax_scalar($pdo, "SELECT COUNT(*) FROM courses WHERE status = 'pending'"),
    'scholarships'  => ax_scalar($pdo, "SELECT COUNT(*) FROM scholarship_applications WHERE status = 'pending'"),
    'projects'      => ax_scalar($pdo, "SELECT COUNT(*) FROM project_submissions WHERE status IN ('flagged','staff_review')"),
    'careers'       => (function () use ($pdo) { try { return (int)$pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'submitted' AND archived = 0")->fetchColumn(); } catch (Throwable $e) { return 0; } })(),
];
$axMenu = [
    'Overview' => [
        ['index.php', './', 'th-large', 'Dashboard'],
    ],
    'Learning' => [
        ['courses.php', 'courses', 'book', 'Courses', 'courses'],
        ['enrollments.php', 'enrollments', 'user-check', 'Enrolments'],
        ['content.php', 'content', 'book-open-reader', 'Content Library'],
        ['course-pricing.php', 'course-pricing', 'money-bill-wave', 'Pricing & Tracks'],
        ['categories.php', 'categories', 'tags', 'Categories'],
        ['paths.php', 'paths', 'route', 'Learning Paths'],
        ['learning-settings.php', 'learning-settings', 'sliders', 'Learning Settings'],
    ],
    'Achievement' => [
        ['certificates.php', 'certificates', 'certificate', 'Certificates'],
        ['projects.php', 'projects', 'diagram-project', 'Peer Projects', 'projects'],
        ['badges.php', 'badges', 'medal', 'Badges & XP'],
        ['leaderboard.php', 'leaderboard', 'ranking-star', 'Leaderboards'],
        ['reviews.php', 'reviews', 'star-half-stroke', 'Reviews'],
    ],
    'People' => [
        ['users.php', 'users', 'users', 'Users'],
        ['messages.php', 'messages', 'inbox', 'Contact Inbox', 'messages'],
        ['mail.php', 'mail', 'envelope', 'Webmail'],
        ['tickets.php', 'tickets', 'headset', 'Support Tickets', 'tickets'],
    ],
    'Finance' => [
        ['verifications.php', 'verifications', 'shield-halved', 'Payment Approvals', 'verifications'],
        ['payments.php', 'payments', 'receipt', 'Master Ledger'],
        ['revenue.php', 'revenue', 'chart-line', 'Revenue'],
        ['coupons.php', 'coupons', 'ticket', 'Coupons'],
        ['payroll.php', 'payroll', 'file-invoice-dollar', 'Tutor Payroll'],
        ['scholarships.php', 'scholarships', 'graduation-cap', 'Scholarships', 'scholarships'],
    ],
    'System' => [
        ['announcements.php', 'announcements', 'bullhorn', 'Announcements'],
        ['careers.php', 'careers', 'briefcase', 'Careers', 'careers'],
        ['notifications.php', 'notifications', 'bell', 'Notifications'],
        ['email-notifications.php', 'email-notifications', 'envelope-open-text', 'Email Notifications'],
        ['activity.php', 'activity', 'clock-rotate-left', 'Activity Log'],
        ['settings.php', 'settings', 'cog', 'Platform Settings'],
    ],
];
// Pages that belong to a menu entry without having their own link.
$axAlias = ['course_details.php' => 'courses.php', 'user-profile.php' => 'users.php', 'career-applications.php' => 'careers.php', 'career-application.php' => 'careers.php', 'career-assessment.php' => 'careers.php'];
$axActive = $axAlias[$current_page] ?? $current_page;
?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<aside class="sidebar" id="dashSidebar" aria-label="Admin menu">
    <div class="sidebar-header">
        <a href="../" class="brand" title="View website">
            <img src="../assets/images/skope-logo-512.png" alt="" >
            <span><b>SKOPE <span>DIGITAL</span></b><small>Admin Panel</small></span>
        </a>
        <button class="sidebar-close" id="sidebarClose" type="button" aria-label="Close menu"><i class="fas fa-times"></i></button>
    </div>

    <form class="ax-side-search" action="search" method="get" role="search">
        <i class="fas fa-search"></i><input type="search" name="q" placeholder="Search…  (people, courses, codes)" value="<?= e($current_page === 'search.php' ? ($_GET['q'] ?? '') : '') ?>" aria-label="Search admin">
    </form>

    <nav class="sidebar-menu">
        <?php foreach ($axMenu as $axLabel => $axItems): ?>
            <div class="menu-label"><?= e($axLabel) ?></div>
            <?php foreach ($axItems as $axIt): $axN = isset($axIt[4]) ? (int)$axCounts[$axIt[4]] : 0; ?>
            <a href="<?= e($axIt[1]) ?>" class="menu-item <?= $axActive === $axIt[0] ? 'active' : '' ?>">
                <i class="fas fa-<?= e($axIt[2]) ?>"></i>
                <span><?= e($axIt[3]) ?></span>
                <?php if ($axN > 0): ?><span class="count"><?= $axN ?></span><?php endif; ?>
            </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="profile" class="admin-chip <?= $current_page === 'profile.php' ? 'on' : '' ?>">
            <?= ax_avatar($user['name'] ?? 'Admin', $user['avatar'] ?? null) ?>
            <span style="min-width:0"><b><?= e($user['name'] ?? 'Admin') ?></b><small>My profile</small></span>
        </a>
        <a href="../logout" class="ax-signout"><i class="fas fa-sign-out-alt"></i> Sign out</a>
    </div>
</aside>
