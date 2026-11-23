<?php
/**
 * Tutor: earnings. Same rules as admin/payroll.php: verified course-fee payments for the tutor's courses,
 * dated COALESCE(verified_at, created_at), times the tutor share (setting tutor_revenue_share, default 80%).
 * Months with no payments show as zero — never invented.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

$periods = ['month' => 'This month', 'last' => 'Last month', 'year' => 'This year', 'all' => 'All time'];
$period = isset($periods[$_GET['period'] ?? '']) ? $_GET['period'] : 'all';
[$from, $to] = match ($period) {
    'month' => [date('Y-m-01'), date('Y-m-t')],
    'last'  => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
    'year'  => [date('Y-01-01'), date('Y-12-31')],
    default => [null, null],
};

$pageTitle = 'Earnings';
$pageSub = 'Your ' . td_share_pct() . '% share of verified course fees. The academy keeps the rest; certificate fees stay with the academy.';
$pageActions = '<button type="button" class="tp-btn" onclick="window.print()"><i class="fas fa-print"></i> Print statement</button>';
require_once 'includes/header.php';

$rate = td_share_rate();
$earn = td_earnings($pdo, $tid, $from, $to);
$all = $period === 'all' ? $earn : td_earnings($pdo, $tid);
$pending = td_pending_earnings($pdo, $tid);
$mc = td_month_compare($pdo, $tid);
$months = td_monthly_earnings($pdo, $tid, 12);

$D = "COALESCE(p.verified_at, p.created_at)";
$pw = $from ? " AND $D BETWEEN ? AND ?" : '';
$pp = $from ? [$from . ' 00:00:00', $to . ' 23:59:59'] : [];
$st = $pdo->prepare("SELECT c.id, c.title, COUNT(p.id) n, SUM(p.amount) gross FROM payments p JOIN courses c ON c.id = p.course_id
                     WHERE c.tutor_id = ? AND p.status = 'verified' AND p.payment_type = 'course'$pw GROUP BY c.id ORDER BY gross DESC");
$st->execute(array_merge([$tid], $pp));
$byCourse = $st->fetchAll();
$st = $pdo->prepare("SELECT p.id, p.amount, p.learning_mode, $D AS paid_at, c.title, u.name AS learner
                     FROM payments p JOIN courses c ON c.id = p.course_id LEFT JOIN users u ON u.id = p.student_id
                     WHERE c.tutor_id = ? AND p.status = 'verified' AND p.payment_type = 'course'$pw ORDER BY paid_at DESC, p.id DESC LIMIT 50");
$st->execute(array_merge([$tid], $pp));
$lines = $st->fetchAll();
$periodLabel = $from ? date('j M Y', strtotime($from)) . ' – ' . date('j M Y', strtotime($to)) : 'All time';
?>
<nav class="tp-tabs screen-only" style="margin-bottom:16px" aria-label="Period">
    <?php foreach ($periods as $k => $label): ?><a href="revenue?period=<?= $k ?>" class="<?= $period === $k ? 'active' : '' ?>"><?= $label ?></a><?php endforeach; ?>
</nav>

<div class="print-only" style="margin-bottom:18px">
    <h2>Earnings statement – <?= e($tutor['name']) ?></h2>
    <p><?= e($tutor['email']) ?> · <?= e($periodLabel) ?> · printed <?= e(date('j M Y, H:i')) ?></p>
</div>

<div class="tp-stats">
    <div class="tp-stat"><span class="tp-stat-icon warn"><i class="fas fa-wallet"></i></span><div><div class="tp-stat-value"><?= e(td_money($earn['share'])) ?></div><div class="tp-stat-label">Your earnings · <?= e($periods[$period]) ?></div><div class="tp-stat-sub"><?= td_share_pct() ?>% of <?= e(td_money($earn['gross'])) ?> from <?= $earn['payments'] ?> payment<?= $earn['payments'] === 1 ? '' : 's' ?></div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon"><i class="fas fa-calendar"></i></span><div><div class="tp-stat-value"><?= e(td_money($mc['this']['share'])) ?></div><div class="tp-stat-label">This month</div>
        <div class="tp-stat-sub"><?php if ($mc['change'] !== null): $up = $mc['change'] >= 0; ?><span class="tp-trend <?= $up ? 'up' : 'down' ?>"><i class="fas fa-arrow-<?= $up ? 'up' : 'down' ?>"></i><?= number_format(abs($mc['change'])) ?>%</span> vs last month (<?= e(td_money($mc['last']['share'])) ?>)<?php else: ?>Nothing earned last month to compare with<?php endif; ?></div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon ok"><i class="fas fa-sack-dollar"></i></span><div><div class="tp-stat-value"><?= e(td_money($all['share'])) ?></div><div class="tp-stat-label">All time</div><div class="tp-stat-sub"><?= $all['payments'] ?> verified payment<?= $all['payments'] === 1 ? '' : 's' ?></div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon violet"><i class="fas fa-hourglass-half"></i></span><div><div class="tp-stat-value"><?= e(td_money($pending['share'])) ?></div><div class="tp-stat-label">Awaiting verification</div><div class="tp-stat-sub"><?= $pending['payments'] ? $pending['payments'] . ' payment' . ($pending['payments'] === 1 ? '' : 's') . ' the admin has not approved yet' : 'No payments waiting' ?></div></div></div>
</div>

<section class="tp-card" style="margin-top:18px">
    <div class="tp-card-head"><div><h2><i class="fas fa-chart-column"></i> Last 12 months</h2><p>Your share per month. Empty months are zero.</p></div></div>
    <div class="tp-card-body"><?= td_bars($months, 'share', fn($v) => td_money($v), false, 'Earnings per month, last 12 months') ?></div>
</section>

<div class="tp-grid two" style="margin-top:18px">
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-book-open"></i> By course</h2><p><?= e($periodLabel) ?></p></div></div>
        <?php if ($byCourse): ?>
            <div class="tp-table-wrap"><table class="tp-table stack">
                <thead><tr><th>Course</th><th class="num">Payments</th><th class="num">Collected</th><th class="num">Your share</th></tr></thead>
                <tbody>
                <?php foreach ($byCourse as $c): ?>
                    <tr><td data-label=""><b style="color:var(--tp-text)"><?= e($c['title']) ?></b></td><td data-label="Payments" class="num"><?= (int)$c['n'] ?></td><td data-label="Collected" class="num"><?= e(td_money((float)$c['gross'])) ?></td><td data-label="Your share" class="num"><b><?= e(td_money((float)$c['gross'] * $rate)) ?></b></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td data-label="">Total</td><td data-label="Payments" class="num"><?= $earn['payments'] ?></td><td data-label="Collected" class="num"><?= e(td_money($earn['gross'])) ?></td><td data-label="Your share" class="num"><?= e(td_money($earn['share'])) ?></td></tr></tfoot>
            </table></div>
        <?php else: ?>
            <div class="tp-empty"><i class="fas fa-wallet"></i><h3>No earnings in this period</h3><p>When a learner's course payment is verified, your share shows here.</p></div>
        <?php endif; ?>
    </section>
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-receipt"></i> Payments</h2><p>Latest verified course payments<?= count($lines) === 50 ? ' (50 most recent)' : '' ?>.</p></div></div>
        <?php if ($lines): ?>
            <ul class="tp-list">
                <?php foreach ($lines as $l): ?>
                    <li><div class="tp-row no-indent"><span class="tp-row-main"><span class="tp-row-title"><?= e($l['learner'] ?? 'Deleted account') ?></span><span class="tp-row-meta"><?= e($l['title']) ?> · <?= e(mode_label($l['learning_mode'] ?: 'self_paced')) ?> · <?= e(date('j M Y', strtotime($l['paid_at']))) ?></span></span>
                        <span class="tp-row-side"><b style="color:var(--tp-text)"><?= e(td_money((float)$l['amount'] * $rate)) ?></b><br><span class="tp-note">of <?= e(td_money((float)$l['amount'])) ?></span></span></div></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="tp-empty"><i class="fas fa-receipt"></i><h3>No payments in this period</h3></div>
        <?php endif; ?>
    </section>
</div>
<p class="tp-note" style="margin-top:14px"><i class="fas fa-circle-info"></i> Payouts are handled by the academy admin from the tutor payroll statement. Contact the admin about payout dates or a missing payment.</p>
<?php require 'includes/footer.php'; ?>
