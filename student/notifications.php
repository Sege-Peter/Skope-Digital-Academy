<?php
$pageTitle = 'Notifications';
require_once 'includes/header.php'; // student-only access check happens here

$uid = (int)$student['id'];
// Notifications for this student: ones sent to them, plus broadcasts to all students / everyone.
// (Before, every student's personal notifications were shown to every student.)
$notif_scope = "(target_user_id = ? OR (target_user_id IS NULL AND user_role IN ('student', 'all')))";

// 1. Mark all as read
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE $notif_scope AND read_status = 0");
        $stmt->execute([$uid]);
    } catch (Exception $e) { error_log('notifications mark all: ' . $e->getMessage()); }
    header('Location: notifications');
    exit;
}

// 1b. Mark one as read
if (isset($_GET['read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE id = ? AND $notif_scope");
        $stmt->execute([(int)$_GET['read'], $uid]);
    } catch (Exception $e) { error_log('notifications mark one: ' . $e->getMessage()); }
    header('Location: notifications');
    exit;
}

// 2. Fetch notifications
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE $notif_scope ORDER BY created_at DESC, id DESC LIMIT 50");
    $stmt->execute([$uid]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) { $notifications = []; }

$unread_here = count(array_filter($notifications, fn($n) => empty($n['read_status'])));
$filter = ($_GET['show'] ?? '') === 'unread' ? 'unread' : 'all';
$shown = $filter === 'unread' ? array_values(array_filter($notifications, fn($n) => empty($n['read_status']))) : $notifications;

// Pick an icon and colour from the notification text
function notif_style($n) {
    $t = strtolower(($n['title'] ?? '') . ' ' . ($n['message'] ?? ''));
    if (str_contains($t, 'badge')) return ['fa-medal', 'violet'];
    if (str_contains($t, 'certificate')) return ['fa-certificate', 'green'];
    if (str_contains($t, 'payment')) return ['fa-receipt', 'green'];
    if (str_contains($t, 'assignment')) return ['fa-file-pen', 'orange'];
    if (str_contains($t, 'quiz')) return ['fa-circle-question', ''];
    if (str_contains($t, 'reminder')) return ['fa-calendar-day', 'orange'];
    if (str_contains($t, 'scholarship')) return ['fa-graduation-cap', 'violet'];
    return empty($n['target_user_id']) ? ['fa-bullhorn', 'yellow'] : ['fa-bell', ''];
}

require_once 'includes/layout-top.php';
?>

<style>
.pg-notif .sp-list-item { align-items: flex-start; padding: 16px 18px; gap: 14px; border-left: 3px solid transparent; }
.pg-notif .sp-list-item.unread { background: var(--primary-soft); border-left-color: var(--primary); }
.pg-notif .sp-list-item.unread h4::after { content: ''; display: inline-block; width: 8px; height: 8px; margin-left: 8px; border-radius: 50%; background: var(--primary); vertical-align: middle; }
.pg-notif .sp-list-item p { font-size: .86rem; line-height: 1.55; overflow-wrap: anywhere; }
.pg-notif .meta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; font-size: .74rem; color: var(--text-muted); }
.pg-notif .mark { font-size: .76rem; white-space: nowrap; }
@media (max-width: 640px) { .pg-notif .sp-list-item { padding: 14px 12px; } .pg-notif .sp-ico { width: 34px; height: 34px; } }
</style>

<div class="sp-page-head">
    <div><h1>Notifications</h1><p>Updates about your courses, badges, certificates and announcements.</p></div>
    <?php if ($unread_here): ?>
    <div class="sp-actions">
        <a href="?mark_all_read=1" class="sp-btn ghost"><i class="fas fa-check-double"></i> Mark all as read</a>
    </div>
    <?php endif; ?>
</div>

<div class="sp-section-head">
    <div class="sp-tabs">
        <a href="notifications" class="sp-tab <?= $filter === 'all' ? 'active' : '' ?>">All (<?= count($notifications) ?>)</a>
        <a href="?show=unread" class="sp-tab <?= $filter === 'unread' ? 'active' : '' ?>">Unread (<?= $unread_here ?>)</a>
    </div>
</div>

<?php if (empty($shown)): ?>
    <div class="sp-empty">
        <div class="sp-ico green"><i class="fas fa-bell-slash"></i></div>
        <h3><?= $filter === 'unread' ? "You're all caught up" : 'No notifications yet' ?></h3>
        <p><?= $filter === 'unread' ? 'You have read everything. New updates will show up here.' : 'Badges, certificates, grades and announcements will show up here.' ?></p>
        <?php if ($filter === 'unread'): ?><a href="notifications" class="sp-btn ghost">See all notifications</a><?php endif; ?>
    </div>
<?php else: ?>
    <div class="sp-card flush pg-notif">
        <div class="sp-list">
            <?php foreach ($shown as $n):
                [$ic, $col] = notif_style($n);
                $is_unread = empty($n['read_status']);
            ?>
            <div class="sp-list-item <?= $is_unread ? 'unread' : '' ?>">
                <span class="sp-ico <?= $col ?>"><i class="fas <?= $ic ?>"></i></span>
                <div class="sp-grow">
                    <h4><?= htmlspecialchars($n['title'] ?: 'Update') ?></h4>
                    <?php if (!empty($n['message'])): ?><p><?= nl2br(htmlspecialchars($n['message'])) ?></p><?php endif; ?>
                    <div class="meta">
                        <span><i class="far fa-clock"></i> <?= !empty($n['created_at']) ? date('j M Y, H:i', strtotime($n['created_at'])) : '' ?></span>
                        <span class="sp-badge <?= empty($n['target_user_id']) ? 'yellow' : 'gray' ?>" style="height: 20px; font-size: .68rem;"><?= empty($n['target_user_id']) ? 'Announcement' : 'For you' ?></span>
                    </div>
                </div>
                <?php if ($is_unread): ?>
                    <a href="?read=<?= (int)$n['id'] ?>" class="sp-link mark" title="Mark as read"><i class="fas fa-check"></i><span class="sp-hide-sm">Mark as read</span></a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/layout-bottom.php'; ?>
