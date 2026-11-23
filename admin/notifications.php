<?php
/** Admin: send in-app notifications to everyone, a role, or one person; see the history. */
$pageTitle = 'Notifications';
require_once 'includes/header.php';

$roles = ['all' => 'Everyone', 'student' => 'All students', 'tutor' => 'All tutors', 'admin' => 'All admins'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    if ($act === 'send') {
        $title   = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['message'] ?? ''));
        $aud     = (string)($_POST['audience'] ?? 'all');
        if ($title === '' || $content === '') { ax_flash('err', 'Add a title and a message.'); ax_back(); }
        if ($aud === 'user') {
            $tid = (int)($_POST['target_user_id'] ?? 0);
            $st = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
            $st->execute([$tid]);
            $target = $st->fetch();
            if (!$target) { ax_flash('err', 'Choose who should get this notification.'); ax_back(); }
            // user_role stays NULL so only this person sees it (the portals show role-wide rows to everyone in that role).
            $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, NULL, ?)")
                ->execute([mb_substr($title, 0, 255), $content, $tid]);
            ax_flash('ok', 'Notification sent to ' . $target['name'] . '.');
        } else {
            if (!isset($roles[$aud])) $aud = 'all';
            $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, ?, NULL)")
                ->execute([mb_substr($title, 0, 255), $content, $aud]);
            ax_flash('ok', 'Notification sent to ' . strtolower($roles[$aud]) . '.');
        }
        ax_back();
    }
    if ($act === 'delete') {
        $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([(int)($_POST['notif_id'] ?? 0)]);
        ax_flash('ok', 'Notification deleted – people no longer see it.');
        ax_back();
    }
    ax_back();
}

/* audience buckets: role broadcasts vs. messages to one person (target set) */
$aud = ['all' => 0, 'everyone' => 0, 'student' => 0, 'tutor' => 0, 'admin' => 0, 'direct' => 0];
foreach ($pdo->query("SELECT CASE WHEN target_user_id IS NOT NULL THEN 'direct' WHEN user_role = 'all' OR user_role IS NULL THEN 'everyone' ELSE user_role END b, COUNT(*) n FROM notifications GROUP BY b")->fetchAll() as $r) {
    $aud[$r['b']] = (int)$r['n']; $aud['all'] += (int)$r['n'];
}
$tab = array_key_exists($_GET['tab'] ?? '', $aud) ? $_GET['tab'] : 'all';
$cond = [
    'all' => '1=1', 'everyone' => "n.target_user_id IS NULL AND (n.user_role = 'all' OR n.user_role IS NULL)",
    'student' => "n.target_user_id IS NULL AND n.user_role = 'student'", 'tutor' => "n.target_user_id IS NULL AND n.user_role = 'tutor'",
    'admin' => "n.target_user_id IS NULL AND n.user_role = 'admin'", 'direct' => 'n.target_user_id IS NOT NULL',
][$tab];
$rows = $pdo->query("SELECT n.*, u.name AS target_name, u.role AS target_role FROM notifications n LEFT JOIN users u ON u.id = n.target_user_id
                     WHERE $cond ORDER BY n.created_at DESC, n.id DESC LIMIT 200")->fetchAll();
$week = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM notifications WHERE created_at >= NOW() - INTERVAL 7 DAY");
$directUnread = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM notifications WHERE target_user_id IS NOT NULL AND read_status = 0");
$people = $pdo->query("SELECT id, name, email, role FROM users WHERE status <> 'suspended' ORDER BY role, name")->fetchAll();
$reach = [];
foreach ($pdo->query("SELECT role, COUNT(*) n FROM users WHERE status = 'active' GROUP BY role")->fetchAll() as $r) $reach[$r['role']] = (int)$r['n'];
$reach['all'] = array_sum($reach);
$preUser = (int)($_GET['to'] ?? 0);

require_once 'includes/sidebar.php';
$actions = '<a class="ax-btn ghost" href="announcements"><i class="fas fa-bullhorn"></i> Announcements</a>';
?>
<style>
  .main-content { min-width: 0; }
  .nt-msg { font-size: .8rem; color: var(--text-dim); line-height: 1.5; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line; }
  .nt-aud { display: inline-flex; gap: 6px; align-items: center; }
  @media (max-width: 760px) { .nt-msg { text-align: left; } }
</style>
<main class="main-content">
    <?php ax_header('Notifications', '', 'In-app alerts for learners, tutors and admins', $actions); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('bell', number_format($aud['all']), 'Sent in total', $week . ' this week') ?>
            <?= ax_stat('tower-broadcast', number_format($aud['all'] - $aud['direct']), 'Broadcasts', 'To everyone or a whole role', 'violet') ?>
            <?= ax_stat('user', number_format($aud['direct']), 'To one person', 'Includes automatic alerts', 'green') ?>
            <?= ax_stat('envelope', number_format($directUnread), 'Unread personal alerts', 'Read state is tracked for personal alerts only', 'amber') ?>
        </div>

        <div class="ax-grid">
            <section class="ax-panel ax-span-4">
                <div class="ax-panel-h"><div><h2><i class="fas fa-paper-plane"></i> Send a notification</h2><p>Appears in the bell menu of everyone in the audience.</p></div></div>
                <form method="post" class="ax-panel-b ax-form" id="sendForm" data-confirm="Send this notification now?">
                    <?= ax_csrf() ?><input type="hidden" name="action" value="send">
                    <div class="ax-field"><label for="n-aud">Send to</label>
                        <select id="n-aud" name="audience">
                            <?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>"><?= $l ?> (<?= (int)($reach[$k] ?? 0) ?> active)</option><?php endforeach; ?>
                            <option value="user" <?= $preUser ? 'selected' : '' ?>>One person…</option>
                        </select></div>
                    <div class="ax-field" id="n-user-wrap" <?= $preUser ? '' : 'hidden' ?>><label for="n-user">Person</label>
                        <select id="n-user" name="target_user_id"><option value="">Choose a person…</option>
                            <?php $g = null; foreach ($people as $pp): if ($g !== $pp['role']) { if ($g !== null) echo '</optgroup>'; $g = $pp['role']; echo '<optgroup label="' . e(ucfirst($g)) . 's">'; } ?>
                            <option value="<?= (int)$pp['id'] ?>" <?= $preUser === (int)$pp['id'] ? 'selected' : '' ?>><?= e($pp['name']) ?> – <?= e($pp['email']) ?></option>
                            <?php endforeach; if ($g !== null) echo '</optgroup>'; ?>
                        </select></div>
                    <div class="ax-field"><label for="n-title">Title</label><input id="n-title" type="text" name="title" required maxlength="255" placeholder="e.g. Scheduled maintenance tonight"></div>
                    <div class="ax-field"><label for="n-msg">Message</label><textarea id="n-msg" name="message" required rows="6" placeholder="Keep it short and clear."></textarea></div>
                    <div class="ax-form-actions"><button class="ax-btn block"><i class="fas fa-paper-plane"></i> Send notification</button></div>
                </form>
            </section>

            <section class="ax-panel ax-span-8">
                <div class="ax-panel-h">
                    <nav class="ax-tabs" aria-label="Audience">
                        <?php foreach (['all' => 'All', 'everyone' => 'Everyone', 'student' => 'Students', 'tutor' => 'Tutors', 'admin' => 'Admins', 'direct' => 'One person'] as $k => $l): ?>
                        <a href="notifications<?= $k === 'all' ? '' : '?tab=' . $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $aud[$k] ?></span></a><?php endforeach; ?>
                    </nav>
                    <div class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Filter history…" data-filter-table="ntTable" aria-label="Filter notifications"></div>
                </div>
                <?php if (!$rows): ?>
                    <div class="ax-empty"><i class="fas fa-bell-slash"></i><b>Nothing sent yet</b>Notifications you send appear here.</div>
                <?php else: ?>
                <div class="ax-table-wrap"><table class="ax-table cards" id="ntTable">
                    <thead><tr><th>Notification</th><th>Audience</th><th>Sent</th><th>Read</th><th></th></tr></thead>
                    <tbody><?php foreach ($rows as $n): ?>
                    <tr>
                        <td data-label=""><div><b class="strong"><?= e($n['title']) ?></b><div class="nt-msg"><?= e($n['message']) ?></div></div></td>
                        <td data-label="Audience"><?php if ($n['target_user_id']): ?>
                            <span class="nt-aud"><i class="fas fa-user" style="color:var(--primary)"></i><?= $n['target_name'] !== null ? '<a href="user-profile?id=' . (int)$n['target_user_id'] . '">' . e($n['target_name']) . '</a>' : '<span class="muted">Deleted user</span>' ?></span>
                        <?php else: ?><span class="ax-pill <?= ['student' => 'info', 'tutor' => 'violet', 'admin' => 'bad'][$n['user_role']] ?? '' ?>"><?= e($roles[$n['user_role'] ?? 'all'] ?? 'Everyone') ?></span><?php endif; ?></td>
                        <td data-label="Sent" class="num"><div><?= ax_date($n['created_at'], 'M j, Y') ?><div class="muted"><?= ax_date($n['created_at'], 'g:i a') ?></div></div></td>
                        <td data-label="Read"><?= $n['target_user_id'] ? ($n['read_status'] ? '<span class="ax-pill ok">Read</span>' : '<span class="ax-pill warn">Unread</span>') : '<span class="muted" title="Broadcasts share one read flag, so reads aren\'t tracked per person">Not tracked</span>' ?></td>
                        <td data-label="" class="actions"><form method="post" data-confirm="Delete “<?= e($n['title']) ?>”? It disappears for everyone who received it." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="notif_id" value="<?= (int)$n['id'] ?>"><button class="ax-btn danger sm icon" title="Delete" aria-label="Delete"><i class="fas fa-trash"></i></button></form></td>
                    </tr><?php endforeach; ?></tbody>
                </table></div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function(){
  var a = document.getElementById('n-aud'), w = document.getElementById('n-user-wrap'), u = document.getElementById('n-user');
  function sync(){ var one = a.value === 'user'; w.hidden = !one; u.required = one; }
  a.addEventListener('change', sync); sync();
})();
</script>
</body></html>
