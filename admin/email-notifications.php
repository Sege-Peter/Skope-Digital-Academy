<?php
/** Admin: automatic emails – which events send email, their wording, the send queue, bulk email and background jobs. */
$pageTitle = 'Email Notifications';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/notify.php';
sda_notify_ensure($pdo);
if (setting('cron_key') === '') save_setting($pdo, 'cron_key', bin2hex(random_bytes(16)));

$tab = $_GET['tab'] ?? 'events';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $a = $_POST['action'] ?? '';
    $ev = (string)($_POST['event'] ?? '');
    if (in_array($a, ['toggle', 'save', 'reset', 'test'], true) && !isset(SDA_NOTIFY_EVENTS[$ev])) ax_back();

    if ($a === 'toggle') {
        $field = ($_POST['field'] ?? '') === 'inapp' ? 'inapp' : 'enabled';
        $pdo->prepare("INSERT INTO notification_templates (event, $field) VALUES (?, ?) ON DUPLICATE KEY UPDATE $field = VALUES($field)")->execute([$ev, (int)!empty($_POST['on'])]);
        ax_log('notify.toggle', 'notification', null, "$ev $field=" . (int)!empty($_POST['on']));
        ax_flash('ok', SDA_NOTIFY_EVENTS[$ev][1] . ': ' . ($field === 'inapp' ? 'in-app ' : 'email ') . (!empty($_POST['on']) ? 'on' : 'off') . '.');
        ax_back('email-notifications#ev-' . $ev);
    }
    if ($a === 'save') {
        $sub = trim(mb_substr((string)$_POST['subject'], 0, 255));
        $body = trim((string)$_POST['body']);
        $pdo->prepare("INSERT INTO notification_templates (event, subject, body) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body)")
            ->execute([$ev, $sub === SDA_NOTIFY_EVENTS[$ev][3] ? null : $sub, $body === SDA_NOTIFY_EVENTS[$ev][4] ? null : $body]);
        ax_log('notify.edit', 'notification', null, "Edited template $ev");
        ax_flash('ok', 'Template saved.');
        ax_back('email-notifications#ev-' . $ev);
    }
    if ($a === 'reset') {
        $pdo->prepare("UPDATE notification_templates SET subject = NULL, body = NULL WHERE event = ?")->execute([$ev]);
        ax_flash('ok', 'Template reset to the default wording.');
        ax_back('email-notifications#ev-' . $ev);
    }
    if ($a === 'test') {
        $r = sda_notify_render($ev, sda_notify_sample_vars($ev) + ['name' => $user['name']]);
        $err = '';
        $ok = sda_mail($user['email'], '[Test] ' . $r['subject'], $r['html'], '', $err, ['event' => $ev]);
        ax_flash($ok ? 'ok' : 'err', $ok ? 'Test sent to ' . $user['email'] . '.' : 'Could not send: ' . $err);
        ax_back('email-notifications#ev-' . $ev);
    }
    if ($a === 'retry' || $a === 'cancel') {
        $id = (int)$_POST['id'];
        $pdo->prepare($a === 'retry' ? "UPDATE mail_queue SET status = 'queued', send_after = NOW(), attempts = 0 WHERE id = ? AND status IN ('failed','cancelled')"
                                     : "UPDATE mail_queue SET status = 'cancelled' WHERE id = ? AND status = 'queued'")->execute([$id]);
        if ($a === 'retry') sda_queue_kick();
        ax_flash('ok', $a === 'retry' ? 'Queued again.' : 'Cancelled.');
        ax_back('email-notifications?tab=queue');
    }
    if ($a === 'retry_all') {
        $n = $pdo->exec("UPDATE mail_queue SET status = 'queued', send_after = NOW(), attempts = 0 WHERE status = 'failed'");
        sda_queue_kick();
        ax_flash('ok', "$n failed emails queued again.");
        ax_back('email-notifications?tab=queue');
    }
    if ($a === 'run_jobs' || $a === 'send_now') {
        $php = PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\php\\php.exe' : PHP_BINDIR . '/php';
        $out = [];
        @exec('"' . $php . '" "' . realpath(__DIR__ . '/../cron.php') . '" ' . ($a === 'send_now' ? 'queue' : 'all') . ' 2>&1', $out);
        ax_flash('ok', $out ? implode(' · ', array_slice($out, 0, 8)) : 'Jobs started.');
        ax_back('email-notifications?tab=' . ($a === 'send_now' ? 'queue' : 'jobs'));
    }
    if ($a === 'broadcast') {
        $subject = trim((string)$_POST['subject']);
        $content = trim((string)$_POST['content']);
        $seg = (string)$_POST['segment'];
        if ($subject === '' || $content === '') { ax_flash('err', 'Write a subject and a message.'); ax_back('email-notifications?tab=broadcast'); }
        $ids = sda_segment_users($pdo, $seg, (int)($_POST['course_id'] ?? 0), (int)($_POST['cohort_id'] ?? 0));
        // Plain text with line breaks and links -> simple HTML
        $html = nl2br(preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', htmlspecialchars($content, ENT_QUOTES, 'UTF-8')));
        $bid = date('YmdHis') . '-' . substr(md5($subject), 0, 6);
        $n = 0;
        foreach ($ids as $uid) {
            if (sda_notify('broadcast', (int)$uid, ['subject' => $subject, 'content' => $html], ['dedupe' => "bc:$bid:$uid", 'inapp' => !empty($_POST['inapp']), 'title' => $subject])) $n++;
        }
        ax_log('notify.broadcast', 'notification', null, "Bulk email \"$subject\" to $n of " . count($ids) . " ($seg)");
        ax_flash('ok', "Queued for $n people" . (count($ids) > $n ? ' (' . (count($ids) - $n) . ' opted out of announcements)' : '') . '. It sends in the background.');
        ax_back('email-notifications?tab=queue');
    }
    ax_back();
}

/** Learner ids for a bulk email audience. */
function sda_segment_users(PDO $pdo, string $seg, int $course, int $cohort): array
{
    switch ($seg) {
        case 'students': return $pdo->query("SELECT id FROM users WHERE role = 'student' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        case 'pending':  return $pdo->query("SELECT id FROM users WHERE role = 'student' AND status = 'pending'")->fetchAll(PDO::FETCH_COLUMN);
        case 'tutors':   return $pdo->query("SELECT id FROM users WHERE role = 'tutor' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        case 'everyone': return $pdo->query("SELECT id FROM users WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        case 'course':
            $s = $pdo->prepare("SELECT DISTINCT student_id FROM enrollments WHERE course_id = ? AND status IN ('active','completed')"); $s->execute([$course]); return $s->fetchAll(PDO::FETCH_COLUMN);
        case 'cohort':
            $s = $pdo->prepare("SELECT DISTINCT student_id FROM enrollments WHERE cohort_id = ? AND status = 'active'"); $s->execute([$cohort]); return $s->fetchAll(PDO::FETCH_COLUMN);
        case 'inactive':
            return $pdo->query("SELECT u.id FROM users u WHERE u.role = 'student' AND u.status = 'active' AND NOT EXISTS (SELECT 1 FROM learning_events le WHERE le.user_id = u.id AND le.created_at > NOW() - INTERVAL 30 DAY)")->fetchAll(PDO::FETCH_COLUMN);
    }
    return [];
}

/** Example values so previews and test emails look real. */
function sda_notify_sample_vars(string $ev): array
{
    return [
        'course' => 'Python Programming', 'course_id' => 1, 'item' => 'Python Programming (structured track)', 'amount' => 'KES 1,500',
        'reference' => 'SDA-TEST-1234', 'reason' => 'The M-Pesa code did not match our records.', 'mode' => 'Structured track',
        'schedule_note' => 'Your class starts Monday, 1 Dec. New lessons open every Monday.', 'access_note' => 'You have access for 180 days.',
        'expires' => date('l, j M Y', strtotime('+7 days')), 'when' => 'in 7 days', 'progress' => 64, 'title' => 'Python Programming',
        'fee' => 'KES 500', 'certificate_id' => 1, 'code' => 'SDA-26-ABCDE-12345', 'scholarship' => 'Women in Tech 2026', 'status' => 'approved',
        'note' => 'Congratulations! Your fees for the next term are covered.', 'subject' => 'Question about the Python course', 'ticket_id' => 42,
        'reply' => 'Thanks for reaching out — the next class starts on Monday.', 'cohort' => 'December 2026', 'start' => date('l, j M Y', strtotime('+3 days')),
        'lessons' => '<ul><li>Variables and types</li><li>Lists and loops</li></ul>', 'due' => date('l, j M', strtotime('+7 days')),
        'assignment' => 'Build a calculator', 'score' => '86 / 100', 'feedback' => 'Clean code — add input validation next time.',
        'badge' => 'Week Warrior', 'xp' => 340, 'lessons_done' => 9, 'streak' => 5,
        'upcoming' => '<ul><li>Mon 1 Dec — Python: week 3 lessons open</li><li>Wed 3 Dec — Due: Build a calculator</li></ul>',
        'content' => '<p>This is what an announcement looks like.</p>', 'sender' => 'Jane (Tutor)', 'excerpt' => 'Great work on the last quiz!',
        'role' => 'student', 'temp_password' => 'Tmp-xxxx', 'email' => 'learner@example.com', 'message' => 'Hello, I would like to join the next class.',
    ];
}

/* ── data for the page ─────────────────────────────────────────────────── */
$q = fn($sql) => (int)ax_scalar($pdo, $sql);
$counts = ['queued' => $q("SELECT COUNT(*) FROM mail_queue WHERE status IN ('queued','sending')"), 'failed' => $q("SELECT COUNT(*) FROM mail_queue WHERE status = 'failed'"),
           'sent7' => $q("SELECT COUNT(*) FROM mail_queue WHERE status = 'sent' AND sent_at > NOW() - INTERVAL 7 DAY"), 'optout' => $q("SELECT COUNT(DISTINCT user_id) FROM notification_prefs WHERE email_on = 0")];
$ready = sda_mail_ready();
$acct = sda_mail_account();
$lastCron = (int)setting('cron_last_run');
$events = [];
foreach (array_keys(SDA_NOTIFY_EVENTS) as $k) { $t = sda_notify_template($k); $events[$t['category']][] = $t; }
$sent30 = [];
foreach ($pdo->query("SELECT event, COUNT(*) n FROM mail_queue WHERE status = 'sent' AND sent_at > NOW() - INTERVAL 30 DAY GROUP BY event")->fetchAll() as $r) $sent30[$r['event']] = (int)$r['n'];

require_once 'includes/sidebar.php';
?>
<style>
  .en-cat { margin-bottom: 18px; }
  .en-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; padding: 12px 0; border-top: 1px solid var(--border); align-items: center; }
  .en-row:first-child { border-top: 0; }
  .en-row b { color: var(--text-main); font-size: .9rem; } .en-row small { color: var(--text-dim); font-size: .78rem; display: block; }
  .en-sw { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; justify-content: flex-end; }
  .en-sw form { margin: 0; }
  .en-tog { border: 1px solid var(--border); background: var(--surface-2); color: var(--text-dim); border-radius: 999px; padding: 5px 11px; font: 700 .72rem Inter, sans-serif; cursor: pointer; }
  .en-tog.on { background: var(--primary-glow, rgba(0,191,255,.15)); color: var(--primary); border-color: var(--primary); }
  details.en-edit { grid-column: 1 / -1; }
  details.en-edit summary { cursor: pointer; color: var(--primary); font-size: .8rem; font-weight: 700; list-style: none; }
  details.en-edit[open] summary { margin-bottom: 10px; }
  .en-edit textarea { width: 100%; min-height: 160px; font: .82rem/1.5 'JetBrains Mono', monospace; }
  .en-prev { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: #fff; }
  .en-prev iframe { width: 100%; height: 420px; border: 0; display: block; }
  .en-vars { font-size: .74rem; color: var(--text-muted); margin-top: 6px; }
  .en-code { font: .78rem 'JetBrains Mono', monospace; background: var(--surface-2); border: 1px solid var(--border); border-radius: 8px; padding: 8px 10px; overflow-x: auto; white-space: nowrap; color: var(--text-main); }
  @media (max-width: 700px) { .en-row { grid-template-columns: 1fr; } .en-sw { justify-content: flex-start; } }
</style>
<main class="main-content">
    <?php ax_header('Email', 'Notifications', 'Automatic emails · queue · bulk email · background jobs',
        '<a class="ax-btn ghost" href="mail"><i class="fas fa-envelope"></i> Webmail</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <?php if (!$ready): ?>
        <div class="ax-alert err" role="status"><i class="fas fa-plug-circle-xmark"></i><div><b>No mailbox connected.</b> Emails are queued but cannot be sent until you connect the academy Gmail in <a href="mail#settings">Webmail &gt; Settings</a>.</div></div>
        <?php endif; ?>
        <div class="ax-stats">
            <?= ax_stat('paper-plane', $counts['sent7'], 'Sent (7 days)', $ready ? 'via ' . e($acct['address']) : 'mailbox not connected', 'green') ?>
            <?= ax_stat('hourglass-half', $counts['queued'], 'Waiting to send', '', 'amber') ?>
            <?= ax_stat('triangle-exclamation', $counts['failed'], 'Failed', $counts['failed'] ? 'see Queue' : '', 'red') ?>
            <?= ax_stat('bell-slash', $counts['optout'], 'Learners who opted out of something', '', 'slate') ?>
        </div>
        <div class="ax-toolbar"><nav class="ax-tabs" aria-label="Sections">
            <?php foreach (['events' => 'Automatic emails', 'queue' => 'Queue & log', 'broadcast' => 'Bulk email', 'jobs' => 'Schedule & jobs'] as $k => $l): ?>
            <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= $l ?></a>
            <?php endforeach; ?>
        </nav></div>

        <?php if ($tab === 'events'): ?>
        <?php foreach ($events as $cat => $list): ?>
        <section class="ax-panel en-cat">
            <div class="ax-panel-h"><div><h2><?= e(SDA_NOTIFY_CATEGORIES[$cat][0]) ?></h2><p><?= e(SDA_NOTIFY_CATEGORIES[$cat][1]) ?></p></div></div>
            <div class="ax-panel-b">
            <?php foreach ($list as $t): $sample = sda_notify_render($t['event'], sda_notify_sample_vars($t['event']) + ['name' => $user['name']]); ?>
                <div class="en-row" id="ev-<?= e($t['event']) ?>">
                    <div><b><?= e($t['label']) ?></b>
                        <small><?= e($sample['subject']) ?> · to <?= e($t['who']) ?><?= $t['essential'] ? ' · always sent' : '' ?><?= $t['customised'] ? ' · <span style="color:var(--warning)">edited</span>' : '' ?><?= isset($sent30[$t['event']]) ? ' · ' . $sent30[$t['event']] . ' sent in 30 days' : '' ?></small></div>
                    <div class="en-sw">
                        <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="field" value="enabled"><input type="hidden" name="event" value="<?= e($t['event']) ?>"><input type="hidden" name="on" value="<?= $t['enabled'] ? 0 : 1 ?>">
                            <button class="en-tog <?= $t['enabled'] ? 'on' : '' ?>" title="Email on/off"><i class="fas fa-envelope"></i> <?= $t['enabled'] ? 'Email on' : 'Email off' ?></button></form>
                        <?php if ($t['category'] !== 'admin'): ?>
                        <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="field" value="inapp"><input type="hidden" name="event" value="<?= e($t['event']) ?>"><input type="hidden" name="on" value="<?= $t['inapp'] ? 0 : 1 ?>">
                            <button class="en-tog <?= $t['inapp'] ? 'on' : '' ?>" title="Bell notification on the portal"><i class="fas fa-bell"></i> <?= $t['inapp'] ? 'In-app on' : 'In-app off' ?></button></form>
                        <?php endif; ?>
                        <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="test"><input type="hidden" name="event" value="<?= e($t['event']) ?>">
                            <button class="en-tog" title="Send a sample to <?= e($user['email']) ?>"><i class="fas fa-flask"></i> Test</button></form>
                    </div>
                    <details class="en-edit"><summary><i class="fas fa-pen"></i> Edit wording &amp; preview</summary>
                        <div class="ax-grid">
                            <form method="post" class="ax-form ax-span-6"><?= ax_csrf() ?><input type="hidden" name="action" value="save"><input type="hidden" name="event" value="<?= e($t['event']) ?>">
                                <div class="ax-field"><label>Subject</label><input name="subject" value="<?= e($t['subject']) ?>" maxlength="255"></div>
                                <div class="ax-field"><label>Message (HTML)</label><textarea name="body"><?= e($t['body']) ?></textarea>
                                    <div class="en-vars">Placeholders: <?= e(implode(' ', array_unique(array_map(fn($m) => $m, (preg_match_all('/\{\{\s*[a-z0-9_]+\s*\}\}/i', SDA_NOTIFY_EVENTS[$t['event']][3] . SDA_NOTIFY_EVENTS[$t['event']][4], $mm) ? $mm[0] : []))))) ?> {{first_name}} {{name}} {{date}}</div></div>
                                <div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save</button>
                                    <?php if ($t['customised']): ?><button class="ax-btn ghost" name="action" value="reset"><i class="fas fa-rotate-left"></i> Reset to default</button><?php endif; ?></div>
                            </form>
                            <div class="ax-span-6"><div class="en-prev"><iframe title="Preview" sandbox srcdoc="<?= e(sda_mail_layout($sample['subject'], $sample['html'], $t['essential'] ? '' : '#')) ?>" loading="lazy"></iframe></div></div>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <?php elseif ($tab === 'queue'): $rows = $pdo->query("SELECT q.*, u.name FROM mail_queue q LEFT JOIN users u ON u.id = q.user_id ORDER BY q.id DESC LIMIT 100")->fetchAll(); ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-list-check"></i> Queue &amp; log</h2><p>The latest 100 emails. Failed emails retry automatically 3 times (after 5 min, 30 min, 2 h).</p></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <form method="post"><?= ax_csrf() ?><button class="ax-btn ghost" name="action" value="send_now"><i class="fas fa-paper-plane"></i> Send queued now</button></form>
                    <?php if ($counts['failed']): ?><form method="post"><?= ax_csrf() ?><button class="ax-btn" name="action" value="retry_all"><i class="fas fa-rotate"></i> Retry all failed</button></form><?php endif; ?>
                </div></div>
            <?php if (!$rows): ?><div class="ax-empty"><i class="fas fa-inbox"></i><b>No emails yet</b>Automatic emails appear here as they are queued and sent.</div><?php else: ?>
            <div class="ax-table-wrap"><table class="ax-table cards">
                <thead><tr><th>When</th><th>To</th><th>Subject</th><th>Event</th><th>Status</th><th></th></tr></thead>
                <tbody><?php foreach ($rows as $r): $pc = ['sent' => 'ok', 'failed' => 'danger', 'queued' => 'warn', 'sending' => 'info', 'cancelled' => ''][$r['status']] ?? ''; ?>
                <tr>
                    <td data-label="When"><?= ax_ago($r['sent_at'] ?: $r['created_at']) ?></td>
                    <td data-label="To"><?= e($r['name'] ?: $r['recipient']) ?><div style="font-size:.74rem;color:var(--text-muted)"><?= e($r['recipient']) ?></div></td>
                    <td data-label="Subject"><?= e($r['subject']) ?><?= $r['error'] ? '<div style="font-size:.74rem;color:var(--danger)">' . e($r['error']) . '</div>' : '' ?></td>
                    <td data-label="Event"><span class="ax-code"><?= e($r['event']) ?></span></td>
                    <td data-label="Status"><span class="ax-pill <?= $pc ?>"><?= e($r['status']) ?><?= $r['attempts'] > 1 ? ' ×' . (int)$r['attempts'] : '' ?></span></td>
                    <td><?php if (in_array($r['status'], ['failed', 'cancelled'], true)): ?><form method="post"><?= ax_csrf() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="ax-btn ghost sm" name="action" value="retry">Retry</button></form>
                        <?php elseif ($r['status'] === 'queued'): ?><form method="post"><?= ax_csrf() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="ax-btn ghost sm" name="action" value="cancel">Cancel</button></form><?php endif; ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div><?php endif; ?>
        </section>

        <?php elseif ($tab === 'broadcast'):
            $courses = $pdo->query("SELECT id, title FROM courses ORDER BY title")->fetchAll();
            $cohorts = $pdo->query("SELECT cc.id, cc.name, c.title FROM course_cohorts cc JOIN courses c ON c.id = cc.course_id WHERE cc.status <> 'archived' ORDER BY cc.start_date DESC")->fetchAll(); ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-bullhorn"></i> Bulk email</h2><p>Sends from the academy mailbox to a group. People who switched off "Announcements & messages" are skipped; every email has an unsubscribe link.</p></div></div>
            <form method="post" class="ax-panel-b ax-form" onsubmit="return confirm('Send this email to the selected group?')"><?= ax_csrf() ?><input type="hidden" name="action" value="broadcast">
                <div class="ax-row">
                    <div class="ax-field"><label for="seg">Send to</label><select id="seg" name="segment" onchange="document.getElementById('bc-course').hidden=this.value!=='course';document.getElementById('bc-cohort').hidden=this.value!=='cohort'">
                        <option value="students">All active learners</option><option value="course">Learners in a course</option><option value="cohort">A structured class (cohort)</option>
                        <option value="pending">Learners who have not activated yet</option><option value="inactive">Learners inactive for 30 days</option>
                        <option value="tutors">All tutors</option><option value="everyone">Everyone (all active accounts)</option></select></div>
                    <div class="ax-field" id="bc-course" hidden><label for="bcc">Course</label><select id="bcc" name="course_id"><?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
                    <div class="ax-field" id="bc-cohort" hidden><label for="bch">Class</label><select id="bch" name="cohort_id"><?php foreach ($cohorts as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title'] . ' – ' . $c['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="ax-field" style="grid-column:1/-1"><label for="bcs">Subject</label><input id="bcs" name="subject" required maxlength="200"></div>
                    <div class="ax-field" style="grid-column:1/-1"><label for="bcm">Message</label><textarea id="bcm" name="content" rows="9" required placeholder="Plain text. Links become clickable. Each email starts with the academy header and ends with an unsubscribe link."></textarea></div>
                    <div class="ax-field" style="grid-column:1/-1"><label class="ax-check"><input type="checkbox" name="inapp" checked> Also show it as a bell notification on the portal</label></div>
                </div>
                <div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-paper-plane"></i> Queue bulk email</button></div>
            </form>
        </section>

        <?php else: $key = setting('cron_key'); $base = rtrim(sda_site_url(), '/'); ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-clock"></i> Schedule &amp; background jobs</h2><p>Reminders that follow each learner's class calendar. Last run: <?= $lastCron ? ax_ago(date('Y-m-d H:i:s', $lastCron)) : 'never' ?>.</p></div>
                <form method="post"><?= ax_csrf() ?><button class="ax-btn" name="action" value="run_jobs"><i class="fas fa-play"></i> Run now</button></form></div>
            <div class="ax-panel-b">
                <ul class="ax-list">
                    <li><div class="grow"><b>New lessons open</b><div style="font-size:.78rem;color:var(--text-dim)">Structured classes: the morning a week opens, with the week's lessons and a calendar invite for the due date.</div></div></li>
                    <li><div class="grow"><b>Class starting soon</b><div style="font-size:.78rem;color:var(--text-dim)">3 days before a cohort starts, with an invite holding every week of the class.</div></div></li>
                    <li><div class="grow"><b>Assignment due</b><div style="font-size:.78rem;color:var(--text-dim)">About 24 hours before the deadline, only if not yet submitted.</div></div></li>
                    <li><div class="grow"><b>Access ending</b><div style="font-size:.78rem;color:var(--text-dim)">7 days and 1 day before course access ends.</div></div></li>
                    <li><div class="grow"><b>Weekly summary</b><div style="font-size:.78rem;color:var(--text-dim)">Mondays: last week's XP and lessons, plus what's coming up.</div></div></li>
                    <li><div class="grow"><b>We miss you</b><div style="font-size:.78rem;color:var(--text-dim)">Once a week to learners idle 7–30 days in an unfinished course.</div></div></li>
                </ul>
                <h3 style="font:800 .72rem Inter,sans-serif;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin:20px 0 8px">Live server cron (cPanel → Cron Jobs, every 15 minutes)</h3>
                <div class="en-code">/usr/local/bin/php <?= e(str_replace('\\', '/', realpath(__DIR__ . '/../cron.php'))) ?> &gt;/dev/null 2&gt;&amp;1</div>
                <h3 style="font:800 .72rem Inter,sans-serif;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin:16px 0 8px">Or call over the web (secret link)</h3>
                <div class="en-code"><?= e($base . '/cron?key=' . $key) ?></div>
                <p style="font-size:.8rem;color:var(--text-dim);margin-top:12px">On this local copy the jobs also run on their own when an admin page is opened (at most every 15 minutes), and queued emails start sending right after the page that queued them.</p>
                <h3 style="font:800 .72rem Inter,sans-serif;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin:20px 0 8px">Learners' calendars</h3>
                <p style="font-size:.84rem;color:var(--text-dim)">Every learner has a private calendar feed (lesson weeks, due dates, class starts, assignment deadlines, access end, their own reminders) that works in Google Calendar, Outlook and phones. The link is in their email preferences page and in schedule emails.</p>
            </div>
        </section>
        <?php endif; ?>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
