<?php
/**
 * Admin: Tutor Payroll.
 *   payroll                → every course owner with their approved course-fee revenue for a period,
 *                            split into tutor share / academy share (setting tutor_revenue_share, default 80%).
 *   payroll?tutor=ID       → printable statement for one tutor (every payment line in the period).
 * Certificate fees are academy income and never count here. Courses whose owner was deleted still show
 * (owner "—"), because courses.tutor_id is LEFT JOINed.
 */
$pageTitle = 'Tutor Payroll';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    if (($_POST['action'] ?? '') === 'share') {
        $v = $_POST['share'] ?? '';
        if (!is_numeric($v) || $v < 0 || $v > 100) { ax_flash('err', 'Enter a tutor share between 0 and 100%.'); ax_back(); }
        save_setting($pdo, 'tutor_revenue_share', (string)round((float)$v, 2));
        try {
            $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, 'tutor_share', ?, ?)")
                ->execute([$user['id'], 'Tutor revenue share set to ' . round((float)$v, 2) . '%', $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Throwable $e) { error_log($e->getMessage()); }
        ax_flash('ok', 'Tutor share set to ' . round((float)$v, 2) . '%. The academy keeps ' . (100 - round((float)$v, 2)) . '%.');
    }
    ax_back();
}

$share = max(0.0, min(100.0, (float)setting('tutor_revenue_share', '80')));
$rate = $share / 100;

/* ── Period ───────────────────────────────────────────────────────────── */
$periods = ['all' => 'All time', 'month' => 'This month', 'last' => 'Last month', 'year' => 'This year', 'custom' => 'Custom dates'];
$period = isset($periods[$_GET['period'] ?? '']) ? $_GET['period'] : 'month';
$from = $to = null;
switch ($period) {
    case 'month': $from = date('Y-m-01'); $to = date('Y-m-t'); break;
    case 'last':  $from = date('Y-m-01', strtotime('first day of last month')); $to = date('Y-m-t', strtotime('first day of last month')); break;
    case 'year':  $from = date('Y-01-01'); $to = date('Y-12-31'); break;
    case 'custom':
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
        break;
}
$D = "COALESCE(p.verified_at, p.created_at)";
$pw = '1=1';
$pp = [];
if ($from) { $pw = "$D >= ? AND $D <= ?"; $pp = [$from . ' 00:00:00', $to . ' 23:59:59']; }
$periodLabel = $period === 'all' ? 'All time' : ax_date($from) . ' – ' . ax_date($to);
$qs = http_build_query(array_filter(['period' => $period, 'from' => $period === 'custom' ? $from : null, 'to' => $period === 'custom' ? $to : null]));

$tutorId = isset($_GET['tutor']) ? (int)$_GET['tutor'] : null;

/* ── Statement for one tutor ──────────────────────────────────────────── */
if ($tutorId !== null) {
    $t = $pdo->prepare("SELECT id, name, email, phone, role FROM users WHERE id = ?");
    $t->execute([$tutorId]);
    $tutor = $t->fetch() ?: null;
    $st = $pdo->prepare("SELECT p.id, p.amount, p.learning_mode, $D AS paid_at, p.paystack_reference, c.id AS course_id, c.title, u.name AS learner
                         FROM payments p JOIN courses c ON c.id = p.course_id LEFT JOIN users u ON u.id = p.student_id
                         WHERE p.status = 'verified' AND p.payment_type = 'course' AND c.tutor_id = ? AND $pw
                         ORDER BY paid_at ASC, p.id ASC");
    $st->execute(array_merge([$tutorId], $pp));
    $lines = $st->fetchAll();
    $cs = $pdo->prepare("SELECT id, title, status FROM courses WHERE tutor_id = ? ORDER BY title");
    $cs->execute([$tutorId]);
    $ownCourses = $cs->fetchAll();
    $byCourse = [];
    foreach ($lines as $l) {
        $byCourse[$l['course_id']] ??= ['title' => $l['title'], 'n' => 0, 'gross' => 0.0];
        $byCourse[$l['course_id']]['n']++;
        $byCourse[$l['course_id']]['gross'] += (float)$l['amount'];
    }
    $gross = array_sum(array_map(fn($l) => (float)$l['amount'], $lines));
}

/* ── Summary for all owners ───────────────────────────────────────────── */
else {
    $rows = [];
    foreach ($pdo->query("SELECT c.tutor_id, u.name, u.email, u.role, u.avatar, COUNT(*) AS courses, SUM(c.status = 'published') AS published
                          FROM courses c LEFT JOIN users u ON u.id = c.tutor_id GROUP BY c.tutor_id")->fetchAll() as $r) {
        $rows[(int)$r['tutor_id']] = $r + ['sales' => 0, 'learners' => 0, 'gross' => 0.0];
    }
    foreach ($pdo->query("SELECT id AS tutor_id, name, email, role, avatar, 0 AS courses, 0 AS published FROM users WHERE role = 'tutor'")->fetchAll() as $r) {
        $rows[(int)$r['tutor_id']] ??= $r + ['sales' => 0, 'learners' => 0, 'gross' => 0.0];
    }
    $st = $pdo->prepare("SELECT c.tutor_id, COUNT(p.id) sales, COUNT(DISTINCT p.student_id) learners, SUM(p.amount) gross
                         FROM payments p JOIN courses c ON c.id = p.course_id
                         WHERE p.status = 'verified' AND p.payment_type = 'course' AND $pw GROUP BY c.tutor_id");
    $st->execute($pp);
    foreach ($st->fetchAll() as $r) {
        $rows[(int)$r['tutor_id']] ??= ['tutor_id' => $r['tutor_id'], 'name' => null, 'email' => null, 'role' => null, 'avatar' => null, 'courses' => 0, 'published' => 0];
        $rows[(int)$r['tutor_id']]['sales'] = (int)$r['sales'];
        $rows[(int)$r['tutor_id']]['learners'] = (int)$r['learners'];
        $rows[(int)$r['tutor_id']]['gross'] = (float)$r['gross'];
    }
    uasort($rows, fn($a, $b) => [$b['gross'], (int)$b['courses']] <=> [$a['gross'], (int)$a['courses']]);
    $totGross = array_sum(array_column($rows, 'gross'));
    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status = 'verified' AND p.payment_type = 'course' AND p.course_id NOT IN (SELECT id FROM courses) AND $pw");
    $st->execute($pp);
    $orphanRev = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status = 'verified' AND p.payment_type = 'certificate' AND $pw");
    $st->execute($pp);
    $certRev = (float)$st->fetchColumn();
}

require_once 'includes/sidebar.php';
$periodForm = function () use ($periods, $period, $from, $to, $tutorId) { ?>
    <form method="get" class="ax-form py-period">
        <?php if ($tutorId !== null): ?><input type="hidden" name="tutor" value="<?= (int)$tutorId ?>"><?php endif; ?>
        <div class="ax-field"><label for="py-p">Period</label><select id="py-p" name="period" onchange="this.form.querySelectorAll('.py-c').forEach(function(x){x.hidden=this.value!=='custom'},this)">
            <?php foreach ($periods as $k => $l): ?><option value="<?= $k ?>" <?= $k === $period ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
        <div class="ax-field py-c" <?= $period === 'custom' ? '' : 'hidden' ?>><label for="py-f">From</label><input id="py-f" type="date" name="from" value="<?= e($from ?? '') ?>"></div>
        <div class="ax-field py-c" <?= $period === 'custom' ? '' : 'hidden' ?>><label for="py-t">To</label><input id="py-t" type="date" name="to" value="<?= e($to ?? '') ?>"></div>
        <button class="ax-btn ghost"><i class="fas fa-rotate"></i> Show</button>
    </form>
<?php };
?>
<style>
  .py-period { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
  .py-period .ax-field { margin: 0; min-width: 150px; }
  .py-period .ax-btn { margin-bottom: 1px; }
  .py-share { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
  .py-share .ax-field { margin: 0; width: 160px; }
  .py-statement .py-head { display: flex; justify-content: space-between; gap: 20px; flex-wrap: wrap; padding-bottom: 18px; border-bottom: 2px solid var(--text-main); margin-bottom: 18px; }
  .py-statement .py-head h2 { font: 800 1.3rem 'Poppins', sans-serif; color: var(--text-main); }
  .py-statement .py-head p { color: var(--text-dim); font-size: .86rem; }
  .py-sum { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 18px 0; }
  .py-sum div { border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px; }
  .py-sum small { display: block; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; color: var(--text-muted); }
  .py-sum b { font: 800 1.2rem 'Poppins', sans-serif; color: var(--text-main); }
  .py-sum .tutor b { color: var(--success); }
  .py-foot { margin-top: 26px; font-size: .78rem; color: var(--text-muted); }
  .ax-table tfoot td { font-weight: 800; color: var(--text-main); border-top: 2px solid var(--border); padding: 14px 18px; }
  @media (max-width: 640px) { .py-sum { grid-template-columns: 1fr; } }
  @media (max-width: 760px) { .ax-table.cards tfoot { display: block; } .ax-table.cards tfoot tr { display: block; padding: 12px 16px; } .ax-table.cards tfoot td { display: flex; justify-content: space-between; border: 0; padding: 4px 0; } .ax-table.cards tfoot td:empty { display: none; } .ax-table.cards tfoot td::before { content: attr(data-label); font-size: .66rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); } }
  @media print {
    .py-period, .py-share-panel, .py-noprint { display: none !important; }
    .ax-body { padding: 0 !important; max-width: none; }
    .ax-panel { border: 0; }
    .ax-table.cards thead { display: table-header-group !important; }
  }
</style>
<main class="main-content">
<?php if ($tutorId !== null): ?>
    <?php ax_header('Payroll', 'Statement', $periodLabel,
        '<a class="ax-btn ghost" href="payroll' . ($qs ? '?' . e($qs) : '') . '"><i class="fas fa-arrow-left"></i> All tutors</a>'
        . '<button type="button" class="ax-btn" onclick="window.print()"><i class="fas fa-print"></i> Print / save PDF</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <section class="ax-panel py-noprint"><div class="ax-panel-b"><?php $periodForm(); ?></div></section>
        <section class="ax-panel py-statement">
            <div class="ax-panel-b">
                <div class="py-head">
                    <div><h2>Tutor earnings statement</h2><p>Skope Digital Academy · <?= e($periodLabel) ?></p></div>
                    <div><p><b><?= e($tutor['name'] ?? 'Deleted account #' . $tutorId) ?></b></p>
                        <?php if ($tutor): ?><p><?= e($tutor['email']) ?><?= $tutor['phone'] ? ' · ' . e($tutor['phone']) : '' ?></p><?php endif; ?>
                        <p>Issued <?= date('M j, Y') ?></p></div>
                </div>
                <?php if ($tutor && $tutor['role'] !== 'tutor'): ?><div class="ax-alert info py-noprint"><i class="fas fa-circle-info"></i><div>This account's role is <b><?= e($tutor['role']) ?></b>, so these courses may be academy-owned.</div></div><?php endif; ?>
                <div class="py-sum">
                    <div><small>Course fees collected</small><b><?= e(ax_money($gross)) ?></b></div>
                    <div class="tutor"><small>Tutor share (<?= e((string)$share) ?>%)</small><b><?= e(ax_money($gross * $rate)) ?></b></div>
                    <div><small>Academy share (<?= e((string)(100 - $share)) ?>%)</small><b><?= e(ax_money($gross * (1 - $rate))) ?></b></div>
                </div>

                <?php if ($byCourse): ?>
                <h3 style="font:800 .95rem Poppins,sans-serif;margin:8px 0 10px">By course</h3>
                <div class="ax-table-wrap"><table class="ax-table cards">
                    <thead><tr><th>Course</th><th>Payments</th><th>Collected</th><th>Tutor share</th></tr></thead>
                    <tbody><?php foreach ($byCourse as $c): ?>
                        <tr><td data-label="Course" class="strong"><?= e($c['title']) ?></td><td data-label="Payments" class="num"><?= (int)$c['n'] ?></td>
                            <td data-label="Collected" class="num"><?= e(ax_money($c['gross'])) ?></td><td data-label="Tutor share" class="num strong"><?= e(ax_money($c['gross'] * $rate)) ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table></div>
                <?php endif; ?>

                <h3 style="font:800 .95rem Poppins,sans-serif;margin:22px 0 10px">Payment lines</h3>
                <?php if (!$lines): ?>
                    <div class="ax-empty" style="padding:30px"><i class="fas fa-file-invoice"></i><b>No approved course payments in this period</b><?= $ownCourses ? 'Courses: ' . e(implode(', ', array_column($ownCourses, 'title'))) : 'This account owns no courses.' ?></div>
                <?php else: ?>
                <div class="ax-table-wrap"><table class="ax-table cards">
                    <thead><tr><th>Date</th><th>Payment</th><th>Learner</th><th>Course</th><th>Track</th><th>Amount</th><th>Tutor</th><th>Academy</th></tr></thead>
                    <tbody><?php foreach ($lines as $l): ?>
                        <tr><td data-label="Date" class="num"><?= e(ax_date($l['paid_at'])) ?></td><td data-label="Payment" class="num muted">#<?= (int)$l['id'] ?></td>
                            <td data-label="Learner"><?= e($l['learner'] ?? 'Deleted account') ?></td><td data-label="Course"><?= e($l['title']) ?></td>
                            <td data-label="Track"><?= e(in_array($l['learning_mode'], SDA_MODES, true) ? mode_label($l['learning_mode']) : '—') ?></td>
                            <td data-label="Amount" class="num"><?= e(ax_money($l['amount'])) ?></td><td data-label="Tutor" class="num strong"><?= e(ax_money($l['amount'] * $rate)) ?></td>
                            <td data-label="Academy" class="num"><?= e(ax_money($l['amount'] * (1 - $rate))) ?></td></tr>
                    <?php endforeach; ?></tbody>
                    <tfoot><tr><td data-label="Total" colspan="5">Total · <?= count($lines) ?> payment<?= count($lines) === 1 ? '' : 's' ?></td><td data-label="Amount" class="num"><?= e(ax_money($gross)) ?></td><td data-label="Tutor" class="num"><?= e(ax_money($gross * $rate)) ?></td><td data-label="Academy" class="num"><?= e(ax_money($gross * (1 - $rate))) ?></td></tr></tfoot>
                </table></div>
                <?php endif; ?>
                <p class="py-foot">Only approved course fees are included. Certificate fees, registration fees and donations are academy income. Split: tutor <?= e((string)$share) ?>% / academy <?= e((string)(100 - $share)) ?>% (current setting).</p>
            </div>
        </section>
    </div>
<?php else: ?>
    <?php ax_header('Tutor', 'Payroll', 'Course-fee earnings per tutor',
        '<a class="ax-btn ghost" href="revenue"><i class="fas fa-chart-line"></i> Revenue</a>'
        . '<button type="button" class="ax-btn ghost" onclick="window.print()"><i class="fas fa-print"></i> Print summary</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('hand-holding-dollar', ax_money($totGross + $orphanRev), 'Course fees', $periodLabel) ?>
            <?= ax_stat('user-check', ax_money($totGross * $rate), 'Owed to tutors (' . $share . '%)', count(array_filter($rows, fn($r) => $r['gross'] > 0)) . ' tutor(s) earned in this period', 'green') ?>
            <?= ax_stat('building-columns', ax_money($totGross * (1 - $rate) + $orphanRev), 'Academy share (' . (100 - $share) . '%)', $orphanRev ? 'Includes ' . ax_money($orphanRev) . ' from removed courses' : 'Of course fees', 'violet') ?>
            <?= ax_stat('award', ax_money($certRev), 'Certificate fees', 'Academy income — not shared', 'orange') ?>
        </div>

        <div class="ax-grid" style="margin-bottom:24px">
            <section class="ax-panel ax-span-7"><div class="ax-panel-h"><div><h2><i class="fas fa-calendar"></i> Period</h2><p>Payments count from the day they were approved.</p></div></div>
                <div class="ax-panel-b"><?php $periodForm(); ?></div></section>
            <section class="ax-panel ax-span-5 py-share-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-percent"></i> Revenue split</h2><p>Applies to every statement, past and future.</p></div></div>
                <form method="post" class="ax-panel-b ax-form py-share" data-confirm="Change the tutor share? All payroll figures and statements are recalculated with the new split.">
                    <?= ax_csrf() ?><input type="hidden" name="action" value="share">
                    <div class="ax-field"><label for="py-share">Tutor share (%)</label><input id="py-share" type="number" name="share" min="0" max="100" step="0.5" value="<?= e((string)$share) ?>" required></div>
                    <div class="hint" style="flex:1;min-width:140px;font-size:.78rem;color:var(--text-muted)">Academy keeps the rest (now <?= e((string)(100 - $share)) ?>%).</div>
                    <button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save</button>
                </form></section>
        </div>

        <div class="ax-toolbar"><span></span><label class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Search tutor…" data-filter-table="py-table" aria-label="Search tutors"></label></div>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-file-invoice-dollar"></i> Tutors</h2><p><?= e($periodLabel) ?> · approved course fees only</p></div></div>
            <?php if (!$rows): ?>
                <div class="ax-empty"><i class="fas fa-users-slash"></i><b>No tutors or courses yet</b>Tutors appear here once they have an account or own a course.</div>
            <?php else: ?>
            <div class="ax-table-wrap"><table class="ax-table cards" id="py-table">
                <thead><tr><th>Tutor</th><th>Courses</th><th>Sales</th><th>Course fees</th><th>Tutor share</th><th>Academy share</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $tid => $r): ?>
                    <tr>
                        <td data-label="Tutor"><div class="ax-who"><?= ax_avatar($r['name'] ?? '—', $r['avatar'] ?? null) ?><div><b><?= e($r['name'] ?? '—') ?></b><small><?= $r['name'] ? e($r['email']) . ($r['role'] !== 'tutor' ? ' · ' . e($r['role']) : '') : 'Owner account deleted (#' . (int)$tid . ')' ?></small></div></div></td>
                        <td data-label="Courses" class="num"><?= (int)$r['courses'] ?> <span class="muted">(<?= (int)$r['published'] ?> live)</span></td>
                        <td data-label="Sales" class="num"><?= (int)$r['sales'] ?><?= $r['learners'] ? ' <span class="muted">· ' . (int)$r['learners'] . ' learner' . ($r['learners'] == 1 ? '' : 's') . '</span>' : '' ?></td>
                        <td data-label="Course fees" class="num"><?= e(ax_money($r['gross'])) ?></td>
                        <td data-label="Tutor share" class="num"><?= $r['gross'] > 0 ? '<span class="ax-pill ok">' . e(ax_money($r['gross'] * $rate)) . '</span>' : e(ax_money(0)) ?></td>
                        <td data-label="Academy share" class="num"><?= e(ax_money($r['gross'] * (1 - $rate))) ?></td>
                        <td class="actions" data-label=""><a class="ax-btn ghost sm" href="payroll?tutor=<?= (int)$tid ?><?= $qs ? '&amp;' . e($qs) : '' ?>"><i class="fas fa-file-invoice"></i> Statement</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td data-label="Total" colspan="3">Total</td><td data-label="Course fees" class="num"><?= e(ax_money($totGross)) ?></td><td data-label="Tutor share" class="num"><?= e(ax_money($totGross * $rate)) ?></td><td data-label="Academy share" class="num"><?= e(ax_money($totGross * (1 - $rate))) ?></td><td></td></tr></tfoot>
            </table></div>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
