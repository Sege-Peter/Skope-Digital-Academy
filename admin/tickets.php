<?php
/** Admin: support tickets – queue by status, conversation, admin replies, status changes. */
$pageTitle = 'Support Tickets';
require_once 'includes/header.php';

/* Replies live in their own table (created once if missing – additive only). */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ticket_replies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        user_id INT NULL,
        is_admin TINYINT(1) NOT NULL DEFAULT 1,
        message TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY ticket_replies_ticket (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Throwable $e) { error_log('ticket_replies: ' . $e->getMessage()); }

$statuses = ['open' => 'Open', 'in_progress' => 'In progress', 'closed' => 'Closed'];

/** Guest tickets from the contact form carry "From: Name (email)" in the first line. */
function tk_contact(array $t): array {
    if (!empty($t['user_email'])) return [$t['user_name'], $t['user_email']];
    if (preg_match('/^From:\s*(.*?)\s*\(([^)\s]+@[^)\s]+)\)/', (string)$t['message'], $m)) return [$m[1], $m[2]];
    return ['Guest', null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $id = (int)($_POST['ticket_id'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ?");
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) { ax_flash('err', 'That ticket no longer exists.'); ax_back('tickets'); }
    $tab = array_key_exists($_POST['tab'] ?? '', $statuses) ? $_POST['tab'] : '';
    $view = 'tickets?' . ($tab ? 'status=' . $tab . '&' : '') . 'view=' . $id;
    $act = $_POST['action'] ?? '';

    if ($act === 'reply') {
        $msg = trim((string)($_POST['message'] ?? ''));
        $new = array_key_exists($_POST['status'] ?? '', $statuses) ? $_POST['status'] : $t['status'];
        if ($msg === '') { ax_flash('err', 'Write a reply first.'); ax_back($view); }
        $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, is_admin, message) VALUES (?, ?, 1, ?)")->execute([$id, (int)$user['id'], mb_substr($msg, 0, 5000)]);
        if ($new !== $t['status']) $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$new, $id]);
        require_once __DIR__ . '/../includes/notify.php';
        $tkVars = ['subject' => $t['subject'], 'ticket_id' => $id, 'reply' => $msg, 'status' => $statuses[$new]];
        $tkMailed = false;
        if ($t['user_id']) {
            // Bell (targeted to this one person) + email
            $tkMailed = sda_notify('ticket_reply', (int)$t['user_id'], $tkVars, ['title' => 'Reply to your support ticket #' . $id, 'message' => mb_substr($t['subject'] . ': ' . $msg, 0, 1000)]);
        } else {
            [$tkName, $tkTo] = tk_contact($t);
            if ($tkTo) $tkMailed = sda_notify_address('ticket_reply', $tkTo, $tkVars + ['name' => $tkName]);
        }
        ax_flash('ok', 'Reply saved' . ($t['user_id'] ? ' and the learner was notified' : '') . ($tkMailed ? ' (emailed)' : (!$t['user_id'] ? ' – no email address on this guest ticket' : '')) . '.');
        ax_back($view);
    }
    if ($act === 'status' && array_key_exists($_POST['status'] ?? '', $statuses)) {
        $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?")->execute([$_POST['status'], $id]);
        ax_flash('ok', 'Ticket #' . $id . ' is now ' . strtolower($statuses[$_POST['status']]) . '.');
        ax_back($view);
    }
    ax_back($view);
}

$tab = array_key_exists($_GET['status'] ?? '', $statuses) ? $_GET['status'] : 'all';
$q = trim((string)($_GET['q'] ?? ''));
$viewId = (int)($_GET['view'] ?? 0);

$counts = array_fill_keys(array_keys($statuses), 0);
foreach ($pdo->query("SELECT status, COUNT(*) n FROM support_tickets GROUP BY status")->fetchAll() as $r) $counts[$r['status']] = (int)$r['n'];
$total = array_sum($counts);

$where = []; $p = [];
if ($tab !== 'all') { $where[] = 'st.status = ?'; $p[] = $tab; }
if ($q !== '') { $where[] = '(st.subject LIKE ? OR st.message LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
$st = $pdo->prepare("SELECT st.*, u.name AS user_name, u.email AS user_email, u.role AS user_role, u.avatar,
        (SELECT COUNT(*) FROM ticket_replies r WHERE r.ticket_id = st.id) AS replies,
        (SELECT MAX(r.created_at) FROM ticket_replies r WHERE r.ticket_id = st.id) AS last_reply
    FROM support_tickets st LEFT JOIN users u ON u.id = st.user_id"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
    " ORDER BY FIELD(st.status, 'open', 'in_progress', 'closed'), st.created_at DESC LIMIT 300");
$st->execute($p);
$tickets = $st->fetchAll();

$viewed = null; $replies = [];
if ($viewId) {
    $st = $pdo->prepare("SELECT st.*, u.name AS user_name, u.email AS user_email, u.role AS user_role, u.avatar FROM support_tickets st LEFT JOIN users u ON u.id = st.user_id WHERE st.id = ?");
    $st->execute([$viewId]);
    $viewed = $st->fetch() ?: null;
    if ($viewed) {
        $st = $pdo->prepare("SELECT r.*, u.name, u.avatar FROM ticket_replies r LEFT JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ? ORDER BY r.created_at, r.id");
        $st->execute([$viewId]);
        $replies = $st->fetchAll();
    }
}
$unanswered = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM support_tickets st WHERE st.status <> 'closed' AND NOT EXISTS (SELECT 1 FROM ticket_replies r WHERE r.ticket_id = st.id)");
$pill = ['open' => 'bad', 'in_progress' => 'warn', 'closed' => 'ok'];
$qs = fn(array $o) => 'tickets?' . http_build_query(array_filter(array_merge(['status' => $tab === 'all' ? '' : $tab, 'q' => $q], $o), fn($v) => $v !== '' && $v !== null));

require_once 'includes/sidebar.php';
?>
<style>
  .main-content { min-width: 0; }
  .tk { display: grid; grid-template-columns: minmax(300px, 400px) minmax(0, 1fr); overflow: hidden; }
  .tk-list { border-right: 1px solid var(--border); min-width: 0; }
  .tk-list .ax-search { margin: 14px 16px; flex: none; min-width: 0; }
  .tk-rows { max-height: 72vh; overflow-y: auto; }
  .tk-row { display: block; padding: 14px 18px; border-top: 1px solid var(--border); text-decoration: none; }
  .tk-row:hover { background: var(--surface-2); }
  .tk-row.on { background: var(--primary-glow); box-shadow: inset 3px 0 0 var(--primary); }
  .tk-row .top { display: flex; justify-content: space-between; gap: 10px; align-items: center; }
  .tk-row b { font-size: .88rem; color: var(--text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .tk-row small { display: block; color: var(--text-muted); font-size: .72rem; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .tk-view { min-width: 0; padding: 24px 28px; }
  .tk-subject { font: 800 1.25rem Poppins, sans-serif; color: var(--text-main); letter-spacing: -.3px; overflow-wrap: anywhere; }
  .tk-thread { display: flex; flex-direction: column; gap: 14px; margin: 18px 0; }
  .tk-msg { display: flex; gap: 12px; align-items: flex-start; max-width: 92%; }
  .tk-msg.admin { margin-left: auto; flex-direction: row-reverse; }
  .tk-bubble { background: var(--surface-2); border: 1px solid var(--border); border-radius: 16px; padding: 14px 16px; min-width: 0; }
  .tk-msg.admin .tk-bubble { background: var(--primary-glow); border-color: color-mix(in srgb, var(--primary) 30%, transparent); }
  .tk-bubble .who { font-size: .74rem; color: var(--text-muted); font-weight: 700; margin-bottom: 6px; }
  .tk-bubble .who b { color: var(--text-main); }
  .tk-bubble .txt { white-space: pre-wrap; color: var(--text-main); font-size: .9rem; line-height: 1.65; overflow-wrap: anywhere; }
  .tk-status { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
  .tk-status form { margin: 0; }
  .tk-back { display: none; }
  @media (max-width: 1000px) {
    .tk { grid-template-columns: 1fr; }
    .tk-list { border-right: 0; }
    .tk.has-view .tk-list { display: none; }
    .tk:not(.has-view) .tk-view { display: none; }
    .tk-back { display: inline-flex; margin-bottom: 14px; }
    .tk-rows { max-height: none; }
    .tk-view { padding: 18px 16px; }
    .tk-msg { max-width: 100%; }
  }
</style>
<main class="main-content">
    <?php ax_header('Support', 'Tickets', 'Help requests from learners and visitors'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('circle-exclamation', number_format($counts['open']), 'Open', '', 'red') ?>
            <?= ax_stat('spinner', number_format($counts['in_progress']), 'In progress', '', 'amber') ?>
            <?= ax_stat('circle-check', number_format($counts['closed']), 'Closed', '', 'green') ?>
            <?= ax_stat('comment-slash', number_format($unanswered), 'Not answered yet', 'Open or in progress with no reply', 'violet') ?>
        </div>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Ticket status">
                <a href="tickets" class="<?= $tab === 'all' ? 'on' : '' ?>">All <span class="n"><?= $total ?></span></a>
                <?php foreach ($statuses as $k => $l): ?><a href="tickets?status=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $counts[$k] ?></span></a><?php endforeach; ?>
            </nav>
        </div>

        <section class="ax-panel tk <?= $viewed ? 'has-view' : '' ?>">
            <div class="tk-list">
                <form class="ax-search" method="get" action="tickets" role="search">
                    <?php if ($tab !== 'all'): ?><input type="hidden" name="status" value="<?= e($tab) ?>"><?php endif; ?>
                    <i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search subject, person…" aria-label="Search tickets">
                </form>
                <div class="tk-rows">
                <?php if (!$tickets): ?>
                    <div class="ax-empty"><i class="fas fa-check-double"></i><b><?= $q !== '' ? 'No tickets match' : 'All clear' ?></b><?= $q !== '' ? 'Try another search.' : 'No tickets in this view.' ?></div>
                <?php else: foreach ($tickets as $t): [$who] = tk_contact($t); ?>
                    <a class="tk-row <?= $viewed && (int)$viewed['id'] === (int)$t['id'] ? 'on' : '' ?>" href="<?= e($qs(['view' => $t['id']])) ?>">
                        <div class="top"><b>#<?= (int)$t['id'] ?> · <?= e($t['subject']) ?></b><span class="ax-pill <?= $pill[$t['status']] ?? '' ?>"><?= e($statuses[$t['status']] ?? $t['status']) ?></span></div>
                        <small><?= e($who) ?><?= $t['user_id'] ? '' : ' (guest)' ?> · <?= ax_ago($t['created_at']) ?> · <?= (int)$t['replies'] ?> repl<?= (int)$t['replies'] === 1 ? 'y' : 'ies' ?></small>
                    </a>
                <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="tk-view">
            <?php if ($viewed): [$who, $email] = tk_contact($viewed); $tabVal = $tab === 'all' ? '' : $tab; ?>
                <a class="ax-btn ghost sm tk-back" href="<?= e($qs([])) ?>"><i class="fas fa-arrow-left"></i> All tickets</a>
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
                    <div style="min-width:0">
                        <div style="font-size:.72rem;color:var(--text-muted);font-weight:800;letter-spacing:.8px;text-transform:uppercase">Ticket #<?= (int)$viewed['id'] ?> · <?= ax_date($viewed['created_at'], 'M j, Y g:i a') ?></div>
                        <h2 class="tk-subject"><?= e($viewed['subject']) ?></h2>
                    </div>
                    <span class="ax-pill <?= $pill[$viewed['status']] ?? '' ?>"><?= e($statuses[$viewed['status']]) ?></span>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:12px">
                    <?php if ($viewed['user_id']): ?>
                        <a class="ax-who" href="user-profile?id=<?= (int)$viewed['user_id'] ?>" style="text-decoration:none"><?= ax_avatar($who, $viewed['avatar']) ?><div><b><?= e($who) ?></b><small><?= e($email) ?> · <?= e(ucfirst((string)$viewed['user_role'])) ?></small></div></a>
                    <?php else: ?>
                        <div class="ax-who"><?= ax_avatar($who) ?><div><b><?= e($who) ?></b><small><?= $email ? e($email) . ' · ' : '' ?>Guest (contact form)</small></div></div>
                    <?php endif; ?>
                    <?php if ($email): ?><a class="ax-btn ghost sm" style="margin-left:auto" href="<?= e('mailto:' . rawurlencode($email) . '?subject=' . rawurlencode('Re: [Ticket #' . $viewed['id'] . '] ' . $viewed['subject'])) ?>"><i class="fas fa-paper-plane"></i> Reply by email</a><?php endif; ?>
                </div>

                <div class="tk-thread">
                    <div class="tk-msg"><?= ax_avatar($who, $viewed['avatar']) ?><div class="tk-bubble"><div class="who"><b><?= e($who) ?></b> · <?= ax_ago($viewed['created_at']) ?></div><div class="txt"><?= e($viewed['message']) ?></div></div></div>
                    <?php foreach ($replies as $r): ?>
                    <div class="tk-msg <?= $r['is_admin'] ? 'admin' : '' ?>"><?= ax_avatar($r['name'] ?? 'Admin', $r['avatar']) ?><div class="tk-bubble"><div class="who"><b><?= e($r['name'] ?? 'Admin') ?></b><?= $r['is_admin'] ? ' (support)' : '' ?> · <?= ax_ago($r['created_at']) ?></div><div class="txt"><?= e($r['message']) ?></div></div></div>
                    <?php endforeach; ?>
                </div>

                <form method="post" class="ax-form" style="border-top:1px solid var(--border);padding-top:18px">
                    <?= ax_csrf() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="<?= (int)$viewed['id'] ?>"><input type="hidden" name="tab" value="<?= e($tabVal) ?>">
                    <div class="ax-field"><label for="rp">Your reply</label><textarea id="rp" name="message" required maxlength="5000" placeholder="Write a helpful answer…"></textarea>
                        <div class="hint"><?= $viewed['user_id'] ? 'Saved on the ticket and sent to the learner as a notification.' : 'Guest ticket: the reply is saved here – use “Reply by email” to send it to them.' ?></div></div>
                    <div class="ax-row" style="align-items:end">
                        <div class="ax-field"><label for="rs">Then set status to</label><select id="rs" name="status">
                            <?php foreach ($statuses as $k => $l): ?><option value="<?= $k ?>" <?= ($viewed['status'] === 'open' ? 'in_progress' : $viewed['status']) === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
                        <div class="ax-field"><button class="ax-btn block"><i class="fas fa-reply"></i> Send reply</button></div>
                    </div>
                </form>

                <div class="tk-status" style="margin-top:6px">
                    <span style="font-size:.72rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px">Change status:</span>
                    <?php foreach ($statuses as $k => $l): if ($k === $viewed['status']) continue; ?>
                    <form method="post" <?= $k === 'closed' ? 'data-confirm="Close ticket #' . (int)$viewed['id'] . '? You can reopen it later."' : '' ?>><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="ticket_id" value="<?= (int)$viewed['id'] ?>"><input type="hidden" name="tab" value="<?= e($tabVal) ?>"><input type="hidden" name="status" value="<?= $k ?>">
                        <button class="ax-btn <?= $k === 'closed' ? 'ok' : 'ghost' ?> sm"><i class="fas fa-<?= ['open' => 'rotate-left', 'in_progress' => 'spinner', 'closed' => 'check'][$k] ?>"></i> <?= $k === 'open' ? 'Reopen' : ($k === 'closed' ? 'Close ticket' : 'Mark in progress') ?></button></form>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ax-empty" style="padding:80px 20px"><i class="fas fa-headset"></i><b>Select a ticket</b>Pick a ticket on the left to read the conversation and reply.</div>
            <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
