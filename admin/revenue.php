<?php
/**
 * Admin: Revenue – real numbers from approved payments only.
 * Revenue date = when the payment was approved (verified_at), falling back to when it was made.
 */
$pageTitle = 'Revenue';
require_once 'includes/header.php';

$D = "COALESCE(p.verified_at, p.created_at)";
$periods = ['all' => 'All time', '12m' => 'Last 12 months', 'year' => 'This year (' . date('Y') . ')', 'lastyear' => 'Last year (' . (date('Y') - 1) . ')'];
$period = isset($periods[$_GET['period'] ?? '']) ? $_GET['period'] : 'all';
$pw = ['all' => '1=1',
       '12m' => "$D >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)",
       'year' => "YEAR($D) = YEAR(CURDATE())",
       'lastyear' => "YEAR($D) = YEAR(CURDATE()) - 1"][$period];

$sum = fn(string $where) => (float)ax_scalar($pdo, "SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.status = 'verified' AND $where");
$cnt = fn(string $where) => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM payments p WHERE p.status = 'verified' AND $where");

$k = [
    'all'        => $sum('1=1'),
    'all_n'      => $cnt('1=1'),
    'month'      => $sum("$D >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'month_n'    => $cnt("$D >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'prev_month' => $sum("$D >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH) AND $D < DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'd30'        => $sum("$D >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
    'd30_n'      => $cnt("$D >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
    'pending'    => (float)ax_scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'pending'"),
    'pending_n'  => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
    'payers'     => (int)ax_scalar($pdo, "SELECT COUNT(DISTINCT p.student_id) FROM payments p WHERE p.status = 'verified' AND $pw"),
];

// Split by type for the chosen period
$byType = $pdo->query("SELECT p.payment_type, COUNT(*) n, SUM(p.amount) total FROM payments p WHERE p.status = 'verified' AND $pw GROUP BY p.payment_type ORDER BY total DESC")->fetchAll();
$periodTotal = array_sum(array_map(fn($r) => (float)$r['total'], $byType));
$typeLabels = ['course' => 'Course fees', 'certificate' => 'Certificate fees', 'registration' => 'Registration fees', 'donation' => 'Donations'];

// Last 12 months, course vs certificate vs other
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
    $months[$m] = ['course' => 0.0, 'certificate' => 0.0, 'other' => 0.0, 'total' => 0.0, 'n' => 0];
}
foreach ($pdo->query("SELECT DATE_FORMAT($D, '%Y-%m') ym, p.payment_type t, COUNT(*) n, SUM(p.amount) s FROM payments p
                      WHERE p.status = 'verified' AND $D >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                      GROUP BY ym, t")->fetchAll() as $r) {
    if (!isset($months[$r['ym']])) continue;
    $key = in_array($r['t'], ['course', 'certificate'], true) ? $r['t'] : 'other';
    $months[$r['ym']][$key] += (float)$r['s'];
    $months[$r['ym']]['total'] += (float)$r['s'];
    $months[$r['ym']]['n'] += (int)$r['n'];
}
$maxMonth = max(1, max(array_column($months, 'total')));
$sum12 = array_sum(array_column($months, 'total'));

// Top courses (course fees and certificate fees per course)
$top = $pdo->query("SELECT p.course_id, c.title, c.source, u.name AS tutor_name,
                           SUM(CASE WHEN p.payment_type = 'course' THEN p.amount ELSE 0 END) AS course_rev,
                           SUM(CASE WHEN p.payment_type = 'certificate' THEN p.amount ELSE 0 END) AS cert_rev,
                           SUM(p.amount) AS total, COUNT(DISTINCT CASE WHEN p.payment_type = 'course' THEN p.student_id END) AS buyers,
                           SUM(p.payment_type = 'certificate') AS certs
                    FROM payments p
                    LEFT JOIN courses c ON c.id = p.course_id
                    LEFT JOIN users u ON u.id = c.tutor_id
                    WHERE p.status = 'verified' AND p.payment_type IN ('course','certificate') AND $pw
                    GROUP BY p.course_id ORDER BY total DESC LIMIT 10")->fetchAll();
$topMax = $top ? max(1, (float)$top[0]['total']) : 1;

// By learning mode (course fees only)
$byMode = $pdo->query("SELECT COALESCE(p.learning_mode, '') m, COUNT(*) n, SUM(p.amount) total FROM payments p
                       WHERE p.status = 'verified' AND p.payment_type = 'course' AND $pw GROUP BY m ORDER BY total DESC")->fetchAll();
$modeTotal = array_sum(array_map(fn($r) => (float)$r['total'], $byMode));

// Approval speed (manual proofs only – Paystack ones are instant)
$avgHours = ax_scalar($pdo, "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, verified_at)) / 60 FROM payments WHERE status = 'verified' AND proof_file IS NOT NULL AND verified_at IS NOT NULL");

// Optional AI commentary (on request only – it calls an external API)
$insight = null;
if (isset($_GET['insights'])) {
    try {
        require_once __DIR__ . '/../includes/ai-handler.php';
        $insight = (string)SDAC_AI::revenueInsight([
            'currency' => setting('currency'), 'all_time' => $k['all'], 'this_month' => $k['month'], 'last_30_days' => $k['d30'],
            'monthly_last_12' => array_map(fn($m) => $m['total'], $months), 'by_type' => $byType,
            'top_courses' => array_map(fn($t) => ['title' => $t['title'], 'revenue' => $t['total']], $top), 'pending' => $k['pending'],
        ]);
    } catch (Throwable $e) { $insight = ''; error_log($e->getMessage()); }
}

$pct = fn($part, $whole) => $whole > 0 ? round($part / $whole * 100) : 0;
$monthDelta = $k['prev_month'] > 0 ? round(($k['month'] - $k['prev_month']) / $k['prev_month'] * 100) : null;

require_once 'includes/sidebar.php';
$periodSel = '<form method="get" style="display:flex;gap:8px"><select name="period" class="ax-input" onchange="this.form.submit()" aria-label="Period" style="width:auto">';
foreach ($periods as $pk => $pl) $periodSel .= '<option value="' . $pk . '"' . ($pk === $period ? ' selected' : '') . '>' . e($pl) . '</option>';
$periodSel .= '</select><noscript><button class="ax-btn sm">Go</button></noscript></form>';
?>
<style>
  .rv-chart { height: 200px; }
  .rv-chart .col i { position: relative; }
  .rv-legend { display: flex; gap: 16px; flex-wrap: wrap; font-size: .76rem; color: var(--text-dim); font-weight: 600; }
  .rv-split { display: grid; gap: 14px; }
  .rv-split .row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 12px; align-items: center; }
  .rv-split .row .ax-bar { grid-column: 1 / -1; }
  .rv-split b { color: var(--text-main); font-size: .88rem; }
  .rv-split small { color: var(--text-muted); font-size: .76rem; }
  .rv-split .amt { font-weight: 800; color: var(--text-main); font-variant-numeric: tabular-nums; }
  .rv-rank { width: 28px; height: 28px; border-radius: 9px; background: var(--surface-2); color: var(--text-dim); font-weight: 800; font-size: .78rem; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .rv-rank.first { background: var(--grad-primary); color: #fff; }
  .rv-insight { white-space: pre-wrap; line-height: 1.65; color: var(--text-main); font-size: .92rem; }
  details.rv-more summary { cursor: pointer; font-weight: 700; font-size: .84rem; color: var(--primary); padding: 14px 22px; }
  @media (max-width: 640px) { .rv-chart .col em { display: none; } .rv-chart .col span { font-size: .58rem; } .rv-chart { gap: 4px; } }
</style>
<main class="main-content">
    <?php ax_header('Revenue', '', 'From approved payments', $periodSel
        . '<a class="ax-btn ghost" href="payments?status=verified"><i class="fas fa-receipt"></i> Ledger</a>'
        . '<button type="button" class="ax-btn ghost" onclick="window.print()"><i class="fas fa-print"></i> Print</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('vault', ax_money($k['all']), 'All-time revenue', number_format($k['all_n']) . ' approved payments', 'green') ?>
            <?= ax_stat('calendar-day', ax_money($k['month']), 'This month (' . date('M') . ')',
                $monthDelta === null ? 'Last month: ' . ax_money($k['prev_month']) : ($monthDelta >= 0 ? '+' : '') . $monthDelta . '% vs ' . date('M', strtotime('first day of last month')) . ' (' . ax_money($k['prev_month']) . ')') ?>
            <?= ax_stat('calendar-week', ax_money($k['d30']), 'Last 30 days', number_format($k['d30_n']) . ' payment' . ($k['d30_n'] == 1 ? '' : 's'), 'violet') ?>
            <?= ax_stat('hourglass-half', ax_money($k['pending']), 'Waiting for approval', number_format($k['pending_n']) . ' pending · not counted above', 'amber') ?>
        </div>

        <div class="ax-grid">
            <section class="ax-panel ax-span-8">
                <div class="ax-panel-h"><div><h2><i class="fas fa-chart-column"></i> Last 12 months</h2><p><?= e(ax_money($sum12)) ?> approved from <?= date('M Y', strtotime(array_key_first($months) . '-01')) ?> to <?= date('M Y') ?>.</p></div>
                    <div class="rv-legend"><span>Hover a bar for the course / certificate split</span></div></div>
                <div class="ax-panel-b">
                    <div class="ax-chart rv-chart" role="img" aria-label="Monthly revenue for the last 12 months">
                        <?php foreach ($months as $ym => $m): $h = $m['total'] > 0 ? max(3, round($m['total'] / $maxMonth * 100)) : 0; ?>
                            <div class="col" title="<?= e(date('F Y', strtotime($ym . '-01')) . ': ' . ax_money($m['total']) . ' — courses ' . ax_money($m['course']) . ', certificates ' . ax_money($m['certificate']) . ($m['other'] ? ', other ' . ax_money($m['other']) : '') . ' (' . $m['n'] . ' payments)') ?>">
                                <em><?= $m['total'] >= 1000 ? round($m['total'] / 1000, $m['total'] < 10000 ? 1 : 0) . 'k' : ($m['total'] ? number_format($m['total']) : '') ?></em>
                                <i style="height:<?= $h ?>%;<?= $h ? '' : 'opacity:.25' ?>"></i>
                                <span><?= date('M', strtotime($ym . '-01')) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <details class="rv-more">
                    <summary>Show the monthly table</summary>
                    <div class="ax-table-wrap">
                        <table class="ax-table cards">
                            <thead><tr><th>Month</th><th>Course fees</th><th>Certificate fees</th><th>Other</th><th>Total</th><th>Payments</th></tr></thead>
                            <tbody>
                            <?php foreach (array_reverse($months, true) as $ym => $m): ?>
                                <tr><td data-label="Month" class="strong"><?= date('F Y', strtotime($ym . '-01')) ?></td>
                                    <td data-label="Course fees" class="num"><?= e(ax_money($m['course'])) ?></td>
                                    <td data-label="Certificate fees" class="num"><?= e(ax_money($m['certificate'])) ?></td>
                                    <td data-label="Other" class="num"><?= e(ax_money($m['other'])) ?></td>
                                    <td data-label="Total" class="num strong"><?= e(ax_money($m['total'])) ?></td>
                                    <td data-label="Payments" class="num"><?= (int)$m['n'] ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>

            <div class="ax-span-4 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-layer-group"></i> Where it comes from</h2><p><?= e($periods[$period]) ?> · <?= e(ax_money($periodTotal)) ?></p></div></div>
                    <div class="ax-panel-b">
                        <?php if (!$byType): ?><div class="ax-empty" style="padding:24px"><b>No approved payments</b>in this period.</div><?php else: ?>
                        <div class="rv-split">
                            <?php foreach ($byType as $t): ?>
                                <div class="row"><div><b><?= e($typeLabels[$t['payment_type']] ?? ucfirst((string)$t['payment_type'])) ?></b> <small><?= (int)$t['n'] ?> payment<?= $t['n'] == 1 ? '' : 's' ?> · <?= $pct($t['total'], $periodTotal) ?>%</small></div>
                                    <span class="amt"><?= e(ax_money($t['total'])) ?></span>
                                    <div class="ax-bar <?= $t['payment_type'] === 'certificate' ? 'orange' : ($t['payment_type'] === 'course' ? '' : 'green') ?>"><i style="width:<?= $pct($t['total'], $periodTotal) ?>%"></i></div></div>
                            <?php endforeach; ?>
                        </div>
                        <p class="muted" style="margin-top:14px;font-size:.76rem;color:var(--text-muted)">Certificate fees are academy income; tutors are paid from course fees only (see <a href="payroll">Tutor Payroll</a>).</p>
                        <?php endif; ?>
                    </div>
                </section>
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-route"></i> By learning mode</h2><p>Course fees · <?= e($periods[$period]) ?></p></div></div>
                    <div class="ax-panel-b">
                        <?php if (!$byMode): ?><div class="ax-empty" style="padding:24px"><b>No course fees</b>in this period.</div><?php else: ?>
                        <div class="rv-split">
                            <?php foreach ($byMode as $m): ?>
                                <div class="row"><div><b><?= e($m['m'] ? mode_label($m['m']) : 'Not recorded') ?></b> <small><?= (int)$m['n'] ?> payment<?= $m['n'] == 1 ? '' : 's' ?> · <?= $pct($m['total'], $modeTotal) ?>%</small></div>
                                    <span class="amt"><?= e(ax_money($m['total'])) ?></span>
                                    <div class="ax-bar <?= $m['m'] === 'structured' ? 'orange' : '' ?>"><i style="width:<?= $pct($m['total'], $modeTotal) ?>%"></i></div></div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (array_filter($byMode, fn($m) => $m['m'] === '')): ?><p style="margin-top:12px;font-size:.76rem;color:var(--text-muted)">"Not recorded" = older payments made before tracks were saved with each payment.</p><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="ax-panel ax-span-8">
                <div class="ax-panel-h"><div><h2><i class="fas fa-trophy"></i> Top courses by revenue</h2><p><?= e($periods[$period]) ?> · course fees and certificate fees</p></div></div>
                <?php if (!$top): ?>
                    <div class="ax-empty"><i class="fas fa-chart-line"></i><b>No course revenue yet</b>Approved course and certificate payments will rank courses here.</div>
                <?php else: ?>
                <div class="ax-table-wrap">
                    <table class="ax-table cards">
                        <thead><tr><th>Course</th><th>Course fees</th><th>Certificate fees</th><th>Total</th><th>Paying learners</th></tr></thead>
                        <tbody>
                        <?php foreach ($top as $i => $t): ?>
                            <tr>
                                <td data-label=""><div class="ax-who"><span class="rv-rank <?= $i === 0 ? 'first' : '' ?>"><?= $i + 1 ?></span><div><b><?= e($t['title'] ?? 'Removed course #' . $t['course_id']) ?></b><small><?= e($t['tutor_name'] ?? '—') ?><?= $t['source'] ? ' · free ' . e($t['source']) : '' ?></small></div></div></td>
                                <td data-label="Course fees" class="num"><?= e(ax_money($t['course_rev'])) ?></td>
                                <td data-label="Certificate fees" class="num"><?= e(ax_money($t['cert_rev'])) ?><?= $t['certs'] ? ' <span class="muted">(' . (int)$t['certs'] . ')</span>' : '' ?></td>
                                <td data-label="Total" class="num strong"><div><?= e(ax_money($t['total'])) ?><div class="ax-bar" style="margin-top:6px"><i style="width:<?= round($t['total'] / $topMax * 100) ?>%"></i></div></div></td>
                                <td data-label="Paying learners" class="num"><?= (int)$t['buyers'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <section class="ax-panel ax-span-4">
                <div class="ax-panel-h"><div><h2><i class="fas fa-gauge"></i> At a glance</h2><p><?= e($periods[$period]) ?></p></div></div>
                <div class="ax-panel-b">
                    <dl class="ax-kv">
                        <dt>Paying learners</dt><dd><?= number_format($k['payers']) ?></dd>
                        <dt>Average payment</dt><dd><?php $pn = array_sum(array_map(fn($r) => (int)$r['n'], $byType)); echo $pn ? e(ax_money($periodTotal / $pn)) : '—'; ?></dd>
                        <dt>Revenue per learner</dt><dd><?= $k['payers'] ? e(ax_money($periodTotal / $k['payers'])) : '—' ?></dd>
                        <dt>Approval time</dt><dd><?= $avgHours ? round((float)$avgHours, 1) . ' h average' : '—' ?> <small style="color:var(--text-muted)">(manual proofs)</small></dd>
                        <dt>Pending</dt><dd><a href="verifications"><?= number_format($k['pending_n']) ?> to approve</a></dd>
                    </dl>
                </div>
            </section>

            <section class="ax-panel ax-span-12">
                <div class="ax-panel-h"><div><h2><i class="fas fa-wand-magic-sparkles"></i> AI commentary</h2><p>Optional: sends these totals to the AI assistant and shows its suggestions. Numbers above are always from the database.</p></div>
                    <?php if ($insight === null): ?><a class="ax-btn ghost sm" href="revenue?period=<?= e($period) ?>&amp;insights=1"><i class="fas fa-wand-magic-sparkles"></i> Get suggestions</a><?php endif; ?></div>
                <?php if ($insight !== null): ?>
                    <div class="ax-panel-b"><?= trim($insight) !== '' ? '<div class="rv-insight">' . e(trim(strip_tags($insight))) . '</div>' : '<div class="ax-alert info" style="margin:0"><i class="fas fa-circle-info"></i><div>The AI assistant did not answer. Check the AI key in the configuration and try again.</div></div>' ?></div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
