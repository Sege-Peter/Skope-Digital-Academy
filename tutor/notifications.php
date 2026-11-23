<?php
/**
 * Tutor: notifications addressed to this tutor, or broadcast to tutors / everyone.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    if (isset($_POST['mark_all_read'])) {
        // Only personal notifications can be marked: broadcasts share one read flag for every recipient.
        $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE target_user_id = ?")->execute([$tid]);
        td_flash('ok', 'All your notifications are marked as read.');
    } elseif (isset($_POST['read_id'])) {
        $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE id = ? AND target_user_id = ?")->execute([(int)$_POST['read_id'], $tid]);
    }
    td_redirect('notifications');
}

$pageTitle = 'Notifications';
$pageSub = 'Messages from the academy and alerts about your courses.';
$st = $pdo->prepare("SELECT * FROM notifications WHERE (target_user_id = ? OR (target_user_id IS NULL AND user_role IN ('tutor','all'))) ORDER BY created_at DESC, id DESC LIMIT 200");
$st->execute([$tid]);
$items = $st->fetchAll();
$unreadPersonal = count(array_filter($items, fn($n) => !$n['read_status'] && (int)$n['target_user_id'] === $tid));
$csrf = csrfToken();
if ($unreadPersonal) $pageActions = '<form method="post" action="notifications"><input type="hidden" name="csrf_token" value="' . e($csrf) . '"><button class="tp-btn" type="submit" name="mark_all_read" value="1"><i class="fas fa-check-double"></i> Mark all as read</button></form>';
require_once 'includes/header.php';
?>
<section class="tp-card">
    <?php if ($items): ?>
        <ul class="tp-list">
            <?php foreach ($items as $n): $mine = (int)$n['target_user_id'] === $tid; $unread = !$n['read_status']; ?>
                <li><div class="tp-row" style="<?= $unread ? 'background:#f8fbff' : '' ?>">
                    <span class="tp-row-icon <?= $mine ? '' : 'violet' ?>"><i class="fas <?= $mine ? 'fa-bell' : 'fa-bullhorn' ?>"></i></span>
                    <span class="tp-row-main">
                        <span class="tp-row-title"><?php if ($unread): ?><span class="tp-unread" aria-label="Unread"></span> <?php endif; ?><?= e($n['title']) ?></span>
                        <span class="tp-row-meta" style="color:var(--tp-body);white-space:pre-line"><?= e($n['message']) ?></span>
                        <span class="tp-row-meta"><?= $mine ? 'For you' : 'Announcement to ' . ($n['user_role'] === 'all' ? 'everyone' : 'all tutors') ?> · <?= e(date('j M Y, g:i a', strtotime($n['created_at']))) ?> (<?= e(td_ago($n['created_at'])) ?>)</span>
                    </span>
                    <?php if ($mine && $unread): ?>
                        <span class="tp-row-side"><form method="post" action="notifications"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><button class="tp-btn sm" type="submit" name="read_id" value="<?= (int)$n['id'] ?>">Mark read</button></form></span>
                    <?php endif; ?>
                </div></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="tp-empty" style="padding:48px 22px"><i class="fas fa-bell"></i><h3>No notifications</h3><p>Announcements from the academy and alerts about your courses will appear here.</p></div>
    <?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
