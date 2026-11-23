<?php
/** Admin: contact-form inbox – read, keep notes, mark replied, archive, delete. */
$pageTitle = 'Contact Inbox';
require_once 'includes/header.php';

/* The inbox needs notes / read time / replied status. Add them once if the table predates them (additive only). */
(function (PDO $pdo) {
    try {
        $cols = array_column($pdo->query("SHOW COLUMNS FROM contact_messages")->fetchAll(), 'Type', 'Field');
        if (!isset($cols['admin_notes'])) $pdo->exec("ALTER TABLE contact_messages ADD COLUMN admin_notes TEXT NULL");
        if (!isset($cols['read_at']))     $pdo->exec("ALTER TABLE contact_messages ADD COLUMN read_at DATETIME NULL");
        if (!isset($cols['replied_at']))  $pdo->exec("ALTER TABLE contact_messages ADD COLUMN replied_at DATETIME NULL");
        if (isset($cols['status']) && !str_contains($cols['status'], "'replied'")) {
            $pdo->exec("ALTER TABLE contact_messages MODIFY status ENUM('unread','read','replied','archived') DEFAULT 'unread'");
        }
    } catch (Throwable $e) { error_log('contact_messages upgrade: ' . $e->getMessage()); }
})($pdo);

$statuses = ['unread' => 'Unread', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $id  = (int)($_POST['msg_id'] ?? 0);
    $act = $_POST['action'] ?? '';
    $filter = array_key_exists($_POST['filter'] ?? '', $statuses) ? $_POST['filter'] : '';
    $list = 'messages' . ($filter ? '?filter=' . $filter : '');
    $view = 'messages?' . ($filter ? 'filter=' . $filter . '&' : '') . 'view=' . $id;
    $notes = trim((string)($_POST['admin_notes'] ?? ''));

    if ($act === 'notes') {
        $pdo->prepare("UPDATE contact_messages SET admin_notes = ? WHERE id = ?")->execute([$notes ?: null, $id]);
        ax_flash('ok', 'Notes saved.'); ax_back($view);
    }
    if ($act === 'replied') {
        $pdo->prepare("UPDATE contact_messages SET status = 'replied', admin_notes = ?, replied_at = NOW() WHERE id = ?")->execute([$notes ?: null, $id]);
        ax_flash('ok', 'Marked as replied.'); ax_back($view);
    }
    if ($act === 'status' && array_key_exists($_POST['status'] ?? '', $statuses)) {
        $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?")->execute([$_POST['status'], $id]);
        ax_flash('ok', 'Moved to ' . strtolower($statuses[$_POST['status']]) . '.');
        ax_back($_POST['status'] === 'unread' ? $list : $view);
    }
    if ($act === 'delete') {
        $pdo->prepare("DELETE FROM contact_messages WHERE id = ?")->execute([$id]);
        ax_flash('ok', 'Message deleted.'); ax_back($list);
    }
    ax_back($list);
}

$filter = array_key_exists($_GET['filter'] ?? '', $statuses) ? $_GET['filter'] : 'all';
$q = trim((string)($_GET['q'] ?? ''));
$viewId = (int)($_GET['view'] ?? 0);

$counts = array_fill_keys(array_keys($statuses), 0);
foreach ($pdo->query("SELECT status, COUNT(*) n FROM contact_messages GROUP BY status")->fetchAll() as $r) $counts[$r['status']] = (int)$r['n'];
$total = array_sum($counts);

$where = []; $p = [];
if ($filter !== 'all') { $where[] = 'status = ?'; $p[] = $filter; } else { $where[] = "status <> 'archived'"; }
if ($q !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); }
$st = $pdo->prepare("SELECT * FROM contact_messages WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 300");
$st->execute($p);
$messages = $st->fetchAll();

$viewed = null;
if ($viewId) {
    $st = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $st->execute([$viewId]);
    $viewed = $st->fetch() ?: null;
    if ($viewed && $viewed['status'] === 'unread') {
        $pdo->prepare("UPDATE contact_messages SET status = 'read', read_at = NOW() WHERE id = ?")->execute([$viewId]);
        $viewed['status'] = 'read'; $viewed['read_at'] = date('Y-m-d H:i:s');
        $counts['unread']--; $counts['read']++;
    }
}
$week = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM contact_messages WHERE created_at >= NOW() - INTERVAL 7 DAY");
$pill = ['unread' => 'bad', 'read' => '', 'replied' => 'ok', 'archived' => 'dark'];
$qs = fn(array $o) => 'messages?' . http_build_query(array_filter(array_merge(['filter' => $filter === 'all' ? '' : $filter, 'q' => $q], $o), fn($v) => $v !== '' && $v !== null));

require_once 'includes/sidebar.php';
?>
<style>
  .main-content { min-width: 0; }
  .ib { display: grid; grid-template-columns: minmax(300px, 380px) minmax(0, 1fr); gap: 0; overflow: hidden; }
  .ib-list { border-right: 1px solid var(--border); min-width: 0; }
  .ib-list .ax-search { margin: 14px 16px; flex: none; min-width: 0; }
  .ib-rows { max-height: 70vh; overflow-y: auto; }
  .ib-row { display: flex; gap: 12px; padding: 14px 18px; border-top: 1px solid var(--border); text-decoration: none; position: relative; }
  .ib-row:hover { background: var(--surface-2); }
  .ib-row.on { background: var(--primary-glow); box-shadow: inset 3px 0 0 var(--primary); }
  .ib-row .m { flex: 1; min-width: 0; }
  .ib-row .m b, .ib-row .m span, .ib-row .m small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ib-row .m b { font-size: .88rem; color: var(--text-main); }
  .ib-row .m span { font-size: .8rem; color: var(--text-dim); }
  .ib-row .m small { font-size: .7rem; color: var(--text-muted); margin-top: 3px; }
  .ib-row.unread .m b, .ib-row.unread .m span { font-weight: 800; color: var(--text-main); }
  .ib-row.unread::after { content: ''; position: absolute; right: 16px; top: 18px; width: 8px; height: 8px; border-radius: 50%; background: var(--danger); }
  .ib-view { min-width: 0; padding: 24px 28px; }
  .ib-subject { font: 800 1.25rem Poppins, sans-serif; color: var(--text-main); letter-spacing: -.3px; overflow-wrap: anywhere; }
  .ib-chips { display: flex; gap: 8px; flex-wrap: wrap; margin: 10px 0 16px; }
  .ib-chip { display: inline-flex; gap: 6px; align-items: center; padding: 5px 11px; border-radius: 9px; background: var(--surface-2); color: var(--text-dim); font-size: .78rem; font-weight: 600; text-decoration: none; max-width: 100%; overflow-wrap: anywhere; }
  .ib-chip i { color: var(--primary); }
  .ib-body { background: var(--surface-2); border: 1px solid var(--border); border-radius: 16px; padding: 20px 22px; color: var(--text-main); white-space: pre-wrap; line-height: 1.7; font-size: .92rem; overflow-wrap: anywhere; }
  .ib-meta { font-size: .74rem; color: var(--text-muted); margin-bottom: 12px; display: flex; gap: 14px; flex-wrap: wrap; }
  .ib-acts { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
  .ib-acts form { margin: 0; }
  .ib-back { display: none; }
  @media (max-width: 1000px) {
    .ib { grid-template-columns: 1fr; }
    .ib-list { border-right: 0; }
    .ib.has-view .ib-list { display: none; }
    .ib:not(.has-view) .ib-view { display: none; }
    .ib-back { display: inline-flex; margin-bottom: 14px; }
    .ib-rows { max-height: none; }
    .ib-view { padding: 18px 16px; }
  }
</style>
<main class="main-content">
    <?php ax_header('Contact', 'Inbox', 'Messages from the website contact form'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('envelope', number_format($counts['unread']), 'Unread', '', 'red') ?>
            <?= ax_stat('envelope-open', number_format($counts['read']), 'Read, not replied', '', 'amber') ?>
            <?= ax_stat('reply', number_format($counts['replied']), 'Replied', '', 'green') ?>
            <?= ax_stat('calendar-week', number_format($week), 'Received this week', number_format($total) . ' in total') ?>
        </div>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Filter messages">
                <a href="messages" class="<?= $filter === 'all' ? 'on' : '' ?>">Inbox <span class="n"><?= $total - $counts['archived'] ?></span></a>
                <?php foreach ($statuses as $k => $l): ?><a href="messages?filter=<?= $k ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= $l ?> <span class="n"><?= $counts[$k] ?></span></a><?php endforeach; ?>
            </nav>
        </div>

        <section class="ax-panel ib <?= $viewed ? 'has-view' : '' ?>">
            <div class="ib-list">
                <form class="ax-search" method="get" action="messages" role="search">
                    <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= e($filter) ?>"><?php endif; ?>
                    <i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, subject…" aria-label="Search messages">
                </form>
                <div class="ib-rows">
                <?php if (!$messages): ?>
                    <div class="ax-empty"><i class="fas fa-inbox"></i><b><?= $q !== '' ? 'No messages match' : 'Nothing here' ?></b><?= $total ? 'Try another filter.' : 'Messages sent from the contact page will appear here.' ?></div>
                <?php else: foreach ($messages as $m): ?>
                    <a class="ib-row <?= e($m['status']) ?> <?= $viewed && (int)$viewed['id'] === (int)$m['id'] ? 'on' : '' ?>" href="<?= e($qs(['view' => $m['id']])) ?>">
                        <?= ax_avatar($m['name']) ?>
                        <div class="m"><b><?= e($m['name']) ?></b><span><?= e($m['subject']) ?></span><small><?= ax_ago($m['created_at']) ?> · <?= e($statuses[$m['status']] ?? $m['status']) ?></small></div>
                    </a>
                <?php endforeach; endif; ?>
                </div>
            </div>

            <div class="ib-view">
            <?php if ($viewed):
                $mailto = 'mailto:' . rawurlencode($viewed['email']) . '?subject=' . rawurlencode('Re: ' . $viewed['subject'])
                        . '&body=' . rawurlencode("\n\n---\nOn " . date('M j, Y', strtotime($viewed['created_at'])) . ', ' . $viewed['name'] . " wrote:\n" . $viewed['message']); ?>
                <a class="ax-btn ghost sm ib-back" href="<?= e($qs([])) ?>"><i class="fas fa-arrow-left"></i> All messages</a>
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                    <h2 class="ib-subject"><?= e($viewed['subject']) ?></h2>
                    <span class="ax-pill <?= $pill[$viewed['status']] ?? '' ?>"><?= e($statuses[$viewed['status']] ?? $viewed['status']) ?></span>
                </div>
                <div class="ib-chips">
                    <span class="ib-chip"><i class="fas fa-user"></i><?= e($viewed['name']) ?></span>
                    <a class="ib-chip" href="mailto:<?= e($viewed['email']) ?>"><i class="fas fa-envelope"></i><?= e($viewed['email']) ?></a>
                    <?php if ($viewed['phone']): ?><a class="ib-chip" href="tel:<?= e($viewed['phone']) ?>"><i class="fas fa-phone"></i><?= e($viewed['phone']) ?></a><?php endif; ?>
                </div>
                <div class="ib-meta">
                    <span><i class="fas fa-clock"></i> Received <?= ax_date($viewed['created_at'], 'D, M j, Y g:i a') ?></span>
                    <?php if (!empty($viewed['read_at'])): ?><span><i class="fas fa-check-double"></i> Read <?= ax_date($viewed['read_at'], 'M j, g:i a') ?></span><?php endif; ?>
                    <?php if (!empty($viewed['replied_at'])): ?><span><i class="fas fa-reply"></i> Replied <?= ax_date($viewed['replied_at'], 'M j, g:i a') ?></span><?php endif; ?>
                </div>
                <div class="ib-body"><?= e($viewed['message']) ?></div>

                <div class="ib-acts">
                    <a class="ax-btn" href="<?= e($mailto) ?>"><i class="fas fa-paper-plane"></i> Reply by email</a>
                    <?php if ($viewed['status'] !== 'archived'): ?>
                    <form method="post"><?= ax_csrf() ?><input type="hidden" name="filter" value="<?= e($filter === 'all' ? '' : $filter) ?>"><input type="hidden" name="msg_id" value="<?= (int)$viewed['id'] ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="archived"><button class="ax-btn ghost"><i class="fas fa-box-archive"></i> Archive</button></form>
                    <?php else: ?>
                    <form method="post"><?= ax_csrf() ?><input type="hidden" name="filter" value="<?= e($filter === 'all' ? '' : $filter) ?>"><input type="hidden" name="msg_id" value="<?= (int)$viewed['id'] ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="read"><button class="ax-btn ghost"><i class="fas fa-inbox"></i> Back to inbox</button></form>
                    <?php endif; ?>
                    <form method="post"><?= ax_csrf() ?><input type="hidden" name="filter" value="<?= e($filter === 'all' ? '' : $filter) ?>"><input type="hidden" name="msg_id" value="<?= (int)$viewed['id'] ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="unread"><button class="ax-btn ghost"><i class="fas fa-envelope"></i> Mark unread</button></form>
                    <form method="post" data-confirm="Delete this message from <?= e($viewed['name']) ?>? This can't be undone." data-danger><?= ax_csrf() ?><input type="hidden" name="filter" value="<?= e($filter === 'all' ? '' : $filter) ?>"><input type="hidden" name="msg_id" value="<?= (int)$viewed['id'] ?>"><input type="hidden" name="action" value="delete"><button class="ax-btn danger"><i class="fas fa-trash"></i> Delete</button></form>
                </div>

                <form method="post" class="ax-form" style="margin-top:22px;border-top:1px solid var(--border);padding-top:18px">
                    <?= ax_csrf() ?><input type="hidden" name="filter" value="<?= e($filter === 'all' ? '' : $filter) ?>"><input type="hidden" name="msg_id" value="<?= (int)$viewed['id'] ?>">
                    <div class="ax-field"><label for="notes">Internal notes</label><textarea id="notes" name="admin_notes" placeholder="Only admins see these – e.g. what you replied or who is following up."><?= e($viewed['admin_notes'] ?? '') ?></textarea><div class="hint">Notes are never sent to the person who wrote in.</div></div>
                    <div class="ax-form-actions">
                        <button class="ax-btn ghost" name="action" value="notes"><i class="fas fa-floppy-disk"></i> Save notes</button>
                        <button class="ax-btn ok" name="action" value="replied"><i class="fas fa-check"></i> Save &amp; mark replied</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="ax-empty" style="padding:80px 20px"><i class="fas fa-envelope-open-text"></i><b>Select a message</b>Pick a message on the left to read it, reply and keep notes.</div>
            <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
