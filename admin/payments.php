<?php
/**
 * Admin: Master Ledger – every payment, filterable, with totals for the filtered set,
 * CSV export (?export=csv, admin only) and a detail dialog per payment.
 * Approve / reject buttons post to verifications.php, which owns the approval rules.
 */
$PL_TYPES = ['course' => 'Course fee', 'certificate' => 'Certificate fee', 'registration' => 'Registration', 'donation' => 'Donation'];
$PL_STATUS = ['pending' => 'Pending', 'verified' => 'Approved', 'failed' => 'Rejected'];

/** WHERE clause + params for the current filters. */
function pl_filters(array $g, array $types): array {
    $w = ['1=1'];
    $p = [];
    $f = [
        'status' => in_array($g['status'] ?? '', ['pending', 'verified', 'failed'], true) ? $g['status'] : '',
        'type'   => isset($types[$g['type'] ?? '']) ? $g['type'] : '',
        'from'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', $g['from'] ?? '') ? $g['from'] : '',
        'to'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $g['to'] ?? '') ? $g['to'] : '',
        'q'      => mb_substr(trim((string)($g['q'] ?? '')), 0, 100),
    ];
    if ($f['type'])  { $w[] = 'p.payment_type = ?'; $p[] = $f['type']; }
    if ($f['from'])  { $w[] = 'p.created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
    if ($f['to'])    { $w[] = 'p.created_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
    if ($f['q'] !== '') {
        $like = '%' . addcslashes($f['q'], '%_\\') . '%';
        $w[] = '(u.name LIKE ? OR u.email LIKE ? OR c.title LIKE ? OR p.paystack_reference LIKE ? OR p.transaction_message LIKE ? OR p.id = ?)';
        array_push($p, $like, $like, $like, $like, $like, (int)ltrim($f['q'], '#'));
    }
    $base = implode(' AND ', $w);     // without status (for the tab counts)
    $full = $base;
    $fp = $p;
    if ($f['status']) { $full .= ' AND p.status = ?'; $fp[] = $f['status']; }
    return [$f, $base, $p, $full, $fp];
}

const PL_FROM = "FROM payments p
                 LEFT JOIN users u ON u.id = p.student_id
                 LEFT JOIN courses c ON c.id = p.course_id
                 LEFT JOIN course_cohorts co ON co.id = p.cohort_id
                 LEFT JOIN certificates ce ON ce.id = p.certificate_id
                 LEFT JOIN users a ON a.id = p.verified_by";
const PL_COLS = "p.*, u.name AS student_name, u.email AS student_email, u.phone AS student_phone, u.avatar,
                 c.title AS course_title, co.name AS cohort_name, co.start_date AS cohort_start,
                 ce.verification_code, ce.status AS cert_status, ce.fee AS cert_fee, a.name AS admin_name";

/* ── CSV export: before the page header (which prints HTML) ───────────── */
if (($_GET['export'] ?? '') === 'csv') {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/auth.php';
    requireRole('admin');
    require_once __DIR__ . '/../includes/learning.php';
    [$f, , , $where, $params] = pl_filters($_GET, $PL_TYPES);
    $st = $pdo->prepare("SELECT " . PL_COLS . " " . PL_FROM . " WHERE $where ORDER BY p.created_at DESC");
    $st->execute($params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payments-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Payment ID', 'Date', 'Status', 'Type', 'Learner', 'Email', 'Course', 'Track', 'Cohort', 'Certificate code', 'Amount', 'Currency', 'Reference', 'Message', 'Proof file', 'Decided by', 'Decided at']);
    $csv = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : $v;   // stop spreadsheet formula injection
    while ($r = $st->fetch()) {
        fputcsv($out, array_map($csv, [
            $r['id'], $r['created_at'], $PL_STATUS[$r['status']] ?? $r['status'], $PL_TYPES[$r['payment_type']] ?? $r['payment_type'],
            $r['student_name'] ?? 'Deleted account', $r['student_email'] ?? '', $r['course_title'] ?? '',
            in_array($r['learning_mode'], SDA_MODES, true) ? mode_label($r['learning_mode']) : '', $r['cohort_name'] ?? '', $r['verification_code'] ?? '',
            number_format((float)$r['amount'], 2, '.', ''), setting('currency'), $r['paystack_reference'] ?? '', $r['transaction_message'] ?? '',
            $r['proof_file'] ?? '', $r['admin_name'] ?? '', $r['verified_at'] ?? '',
        ]));
    }
    fclose($out);
    exit;
}

$pageTitle = 'Master Ledger';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {   // approvals are handled on verifications.php
    ax_post_guard();
    ax_back();
}

[$f, $baseWhere, $baseParams, $where, $params] = pl_filters($_GET, $PL_TYPES);

$counts = ['all' => 0, 'pending' => 0, 'verified' => 0, 'failed' => 0];
$st = $pdo->prepare("SELECT p.status, COUNT(*) n " . PL_FROM . " WHERE $baseWhere GROUP BY p.status");
$st->execute($baseParams);
foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $n) { if (isset($counts[$k])) $counts[$k] = (int)$n; $counts['all'] += (int)$n; }

$st = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(p.amount),0) total,
                            COALESCE(SUM(CASE WHEN p.status='verified' THEN p.amount END),0) approved,
                            COALESCE(SUM(CASE WHEN p.status='pending' THEN p.amount END),0) pending,
                            COALESCE(SUM(CASE WHEN p.status='failed' THEN p.amount END),0) rejected,
                            COALESCE(SUM(CASE WHEN p.status='verified' AND p.payment_type='certificate' THEN p.amount END),0) cert,
                            COALESCE(SUM(CASE WHEN p.status='verified' AND p.payment_type='course' THEN p.amount END),0) course
                     " . PL_FROM . " WHERE $where");
$st->execute($params);
$tot = $st->fetch();

$per = 50;
$pages = max(1, (int)ceil($tot['n'] / $per));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$st = $pdo->prepare("SELECT " . PL_COLS . " " . PL_FROM . " WHERE $where ORDER BY p.created_at DESC, p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();

$typesPresent = $pdo->query("SELECT payment_type, COUNT(*) FROM payments GROUP BY payment_type")->fetchAll(PDO::FETCH_KEY_PAIR);
$filtered = $f['type'] || $f['from'] || $f['to'] || $f['q'] !== '' || $f['status'];

function pl_url(array $f, array $over = []): string {
    $q = array_filter(array_merge($f, $over), fn($v) => $v !== '' && $v !== null);
    return 'payments' . ($q ? '?' . http_build_query($q) : '');
}
function pl_status_pill(string $s): string {
    return ['pending' => '<span class="ax-pill warn"><i class="fas fa-hourglass-half"></i> Pending</span>',
            'verified' => '<span class="ax-pill ok"><i class="fas fa-check"></i> Approved</span>',
            'failed' => '<span class="ax-pill bad"><i class="fas fa-xmark"></i> Rejected</span>'][$s] ?? '<span class="ax-pill">' . e($s) . '</span>';
}
function pl_type_pill(string $t, array $types): string {
    $cls = ['course' => 'info', 'certificate' => 'violet', 'registration' => 'dark', 'donation' => 'ok'][$t] ?? '';
    return '<span class="ax-pill ' . $cls . '">' . e($types[$t] ?? ucfirst($t)) . '</span>';
}
function pl_proof(?string $file): ?string {
    if (!$file) return null;
    $file = basename($file);
    return is_file(__DIR__ . '/../uploads/proofs/' . $file) ? '../uploads/proofs/' . rawurlencode($file) : null;
}

$returnTo = basename($_SERVER['REQUEST_URI']);
$returnTo = preg_match('/^payments(\?[A-Za-z0-9_=&%.+\-]*)?$/', $returnTo) ? $returnTo : 'payments';
$exportUrl = pl_url($f, ['export' => 'csv']);
require_once 'includes/sidebar.php';
?>
<style>
  .pl-filters { display: grid; grid-template-columns: minmax(0, 2fr) repeat(3, minmax(0, 1fr)) auto; gap: 12px; align-items: end; }
  .pl-filters .ax-field { margin: 0; }
  .pl-filters .pl-btns { display: flex; gap: 8px; }
  .pl-totals { display: flex; flex-wrap: wrap; gap: 10px 26px; padding: 14px 22px; border-bottom: 1px solid var(--border-light); background: var(--surface-2); font-size: .84rem; color: var(--text-dim); }
  .pl-totals b { color: var(--text-main); font-variant-numeric: tabular-nums; }
  .pl-pager { display: flex; gap: 8px; justify-content: space-between; align-items: center; padding: 14px 22px; border-top: 1px solid var(--border-light); font-size: .82rem; color: var(--text-muted); flex-wrap: wrap; }
  .pl-detail img { max-width: 100%; max-height: 50vh; border-radius: 12px; border: 1px solid var(--border); display: block; margin: 0 auto; }
  .pl-detail .ax-kv { margin-bottom: 18px; }
  .pl-msg { white-space: pre-wrap; background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; font-size: .85rem; color: var(--text-main); overflow-wrap: anywhere; }
  .pl-print-head { display: none; }
  @media (max-width: 1100px) { .pl-filters { grid-template-columns: repeat(2, minmax(0, 1fr)); } .pl-filters .pl-q { grid-column: 1 / -1; } }
  @media (max-width: 520px) { .pl-filters { grid-template-columns: 1fr; } }
  @media print {
    .pl-print-head { display: block; margin-bottom: 16px; }
    .ax-stats, .pl-filters-panel, .pl-pager, .ax-table .actions, dialog { display: none !important; }
    .ax-table td, .ax-table th { padding: 6px 8px; font-size: .72rem; }
  }
</style>
<main class="main-content">
    <?php ax_header('Master', 'Ledger', 'Every payment in one place',
        '<a class="ax-btn ghost" href="' . e($exportUrl) . '"><i class="fas fa-file-csv"></i> Export CSV</a>'
        . '<button type="button" class="ax-btn ghost" onclick="window.print()"><i class="fas fa-print"></i> Print</button>'
        . '<a class="ax-btn" href="verifications"><i class="fas fa-shield-halved"></i> Approvals' . ($counts['pending'] && !$filtered ? ' (' . $counts['pending'] . ')' : '') . '</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="pl-print-head"><h2>Skope Digital Academy – payments ledger</h2><p>Printed <?= date('M j, Y g:i a') ?><?= $filtered ? ' · filtered view' : '' ?></p></div>

        <div class="ax-stats">
            <?= ax_stat('vault', ax_money($tot['approved']), 'Approved' . ($filtered ? ' (filtered)' : ''), 'Courses ' . ax_money($tot['course']) . ' · Certificates ' . ax_money($tot['cert']), 'green') ?>
            <?= ax_stat('hourglass-half', ax_money($tot['pending']), 'Waiting for approval', number_format($counts['pending']) . ' payment' . ($counts['pending'] == 1 ? '' : 's'), 'amber') ?>
            <?= ax_stat('ban', ax_money($tot['rejected']), 'Rejected', number_format($counts['failed']) . ' payment' . ($counts['failed'] == 1 ? '' : 's'), 'red') ?>
            <?= ax_stat('receipt', number_format((int)$tot['n']), 'Payments listed', $counts['all'] ? round($counts['verified'] / $counts['all'] * 100) . '% approved' : 'No payments', 'slate') ?>
        </div>

        <section class="ax-panel pl-filters-panel">
            <form class="ax-panel-b ax-form pl-filters" method="get" action="payments">
                <?php if ($f['status']): ?><input type="hidden" name="status" value="<?= e($f['status']) ?>"><?php endif; ?>
                <div class="ax-field pl-q"><label for="pl-q">Search</label><input id="pl-q" type="text" name="q" value="<?= e($f['q']) ?>" placeholder="Learner, email, course, reference, #ID"></div>
                <div class="ax-field"><label for="pl-type">Type</label>
                    <select id="pl-type" name="type"><option value="">All types</option>
                        <?php foreach ($PL_TYPES as $k => $lbl): if (!isset($typesPresent[$k]) && $f['type'] !== $k) continue; ?>
                            <option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($lbl) ?> (<?= (int)($typesPresent[$k] ?? 0) ?>)</option>
                        <?php endforeach; ?></select></div>
                <div class="ax-field"><label for="pl-from">From</label><input id="pl-from" type="date" name="from" value="<?= e($f['from']) ?>"></div>
                <div class="ax-field"><label for="pl-to">To</label><input id="pl-to" type="date" name="to" value="<?= e($f['to']) ?>"></div>
                <div class="pl-btns"><button class="ax-btn"><i class="fas fa-filter"></i> Apply</button><?php if ($filtered): ?><a class="ax-btn ghost" href="payments">Clear</a><?php endif; ?></div>
            </form>
        </section>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Status">
                <?php foreach (['' => 'All', 'pending' => 'Pending', 'verified' => 'Approved', 'failed' => 'Rejected'] as $k => $lbl): ?>
                    <a href="<?= e(pl_url($f, ['status' => $k, 'page' => null])) ?>" class="<?= $f['status'] === $k ? 'on' : '' ?>"><?= $lbl ?> <span class="n"><?= $counts[$k ?: 'all'] ?></span></a>
                <?php endforeach; ?>
            </nav>
        </div>

        <section class="ax-panel">
            <div class="pl-totals">
                <span>Listed: <b><?= number_format((int)$tot['n']) ?></b></span>
                <span>Total value: <b><?= e(ax_money($tot['total'])) ?></b></span>
                <span>Approved: <b><?= e(ax_money($tot['approved'])) ?></b></span>
                <span>Pending: <b><?= e(ax_money($tot['pending'])) ?></b></span>
                <span>Rejected: <b><?= e(ax_money($tot['rejected'])) ?></b></span>
            </div>
            <?php if (!$rows): ?>
                <div class="ax-empty"><i class="fas fa-receipt"></i><b><?= $filtered ? 'No payments match these filters' : 'No payments yet' ?></b><?= $filtered ? '<a href="payments">Clear the filters</a>' : 'Payments will be listed here as learners pay.' ?></div>
            <?php else: ?>
            <div class="ax-table-wrap">
                <table class="ax-table cards">
                    <thead><tr><th>#</th><th>Learner</th><th>For</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="Payment" class="num muted">#<?= (int)$r['id'] ?></td>
                            <td data-label="Learner"><div class="ax-who"><?= ax_avatar($r['student_name'] ?? '?', $r['avatar'] ?? null) ?><div><b><?= e($r['student_name'] ?? 'Deleted account') ?></b><small><?= e($r['student_email'] ?? 'User #' . $r['student_id']) ?></small></div></div></td>
                            <td data-label="For"><div><?= pl_type_pill((string)$r['payment_type'], $PL_TYPES) ?>
                                <?php if ($r['course_title']): ?><div class="strong" style="margin-top:4px"><?= e($r['course_title']) ?></div><?php endif; ?>
                                <?php if ($r['payment_type'] === 'course' && in_array($r['learning_mode'], SDA_MODES, true)): ?><div class="muted"><?= e(mode_label($r['learning_mode'])) ?><?= $r['cohort_name'] ? ' · ' . e($r['cohort_name']) : '' ?></div><?php endif; ?></div></td>
                            <td data-label="Amount" class="num strong"><?= e(money((float)$r['amount'])) ?></td>
                            <td data-label="Status"><?= pl_status_pill((string)$r['status']) ?></td>
                            <td data-label="Date" class="num"><?= e(ax_date($r['created_at'], 'M j, Y')) ?><div class="muted"><?= e(ax_date($r['created_at'], 'g:i a')) ?></div></td>
                            <td class="actions" data-label=""><button type="button" class="ax-btn ghost sm" data-open="pd-<?= (int)$r['id'] ?>"><i class="fas fa-eye"></i> Details</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
            <div class="pl-pager">
                <span>Page <?= $page ?> of <?= $pages ?> · <?= $per ?> per page</span>
                <span style="display:flex;gap:8px">
                    <?php if ($page > 1): ?><a class="ax-btn ghost sm" href="<?= e(pl_url($f, ['page' => $page - 1])) ?>"><i class="fas fa-chevron-left"></i> Newer</a><?php endif; ?>
                    <?php if ($page < $pages): ?><a class="ax-btn ghost sm" href="<?= e(pl_url($f, ['page' => $page + 1])) ?>">Older <i class="fas fa-chevron-right"></i></a><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php foreach ($rows as $r): $proof = pl_proof($r['proof_file']); $isImg = $proof && in_array(strtolower(pathinfo($r['proof_file'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true); ?>
<dialog class="ax-modal pl-detail" id="pd-<?= (int)$r['id'] ?>" aria-label="Payment #<?= (int)$r['id'] ?>">
    <div class="ax-panel-h"><div><h3><i class="fas fa-receipt"></i> Payment #<?= (int)$r['id'] ?></h3><p><?= pl_status_pill((string)$r['status']) ?> <?= pl_type_pill((string)$r['payment_type'], $PL_TYPES) ?></p></div>
        <button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
    <div class="ax-panel-b">
        <dl class="ax-kv">
            <dt>Amount</dt><dd><?= e(money((float)$r['amount'])) ?></dd>
            <dt>Learner</dt><dd><?= e($r['student_name'] ?? 'Deleted account') ?><?= $r['student_email'] ? ' · ' . e($r['student_email']) : '' ?><?= $r['student_phone'] ? ' · ' . e($r['student_phone']) : '' ?>
                <?php if ($r['student_name']): ?> · <a href="user-profile?id=<?= (int)$r['student_id'] ?>">Profile</a><?php endif; ?></dd>
            <?php if ($r['course_title'] || $r['course_id']): ?><dt>Course</dt><dd><?= $r['course_title'] ? '<a href="../course-details?id=' . (int)$r['course_id'] . '" target="_blank">' . e($r['course_title']) . '</a>' : 'Removed course #' . (int)$r['course_id'] ?></dd><?php endif; ?>
            <?php if ($r['payment_type'] === 'course'): ?><dt>Track</dt><dd><?= in_array($r['learning_mode'], SDA_MODES, true) ? e(mode_label($r['learning_mode'])) : 'Not recorded' ?><?= $r['cohort_name'] ? ' · cohort ' . e($r['cohort_name']) . ' (starts ' . e(ax_date($r['cohort_start'])) . ')' : '' ?></dd><?php endif; ?>
            <?php if ($r['payment_type'] === 'certificate'): ?><dt>Certificate</dt><dd><?= $r['verification_code'] ? '<a href="../certificate?code=' . rawurlencode($r['verification_code']) . '" target="_blank">' . e($r['verification_code']) . '</a> · ' . e(['pending' => 'locked', 'approved' => 'unlocked', 'revoked' => 'revoked'][$r['cert_status']] ?? $r['cert_status']) . ' · fee ' . e(money((float)$r['cert_fee'])) : 'Not linked' ?></dd><?php endif; ?>
            <dt>Reference</dt><dd><?= $r['paystack_reference'] ? '<span class="ax-code">' . e($r['paystack_reference']) . '</span> (Paystack)' : '—' ?></dd>
            <dt>Sent</dt><dd><?= e(ax_date($r['created_at'], 'M j, Y g:i a')) ?></dd>
            <?php if ($r['status'] !== 'pending'): ?><dt><?= $r['status'] === 'verified' ? 'Approved' : 'Rejected' ?></dt><dd><?= e(ax_date($r['verified_at'], 'M j, Y g:i a')) ?><?= $r['admin_name'] ? ' by ' . e($r['admin_name']) : ($r['paystack_reference'] ? ' (automatic)' : '') ?></dd><?php endif; ?>
        </dl>
        <?php if (trim((string)$r['transaction_message']) !== ''): ?><div class="ax-field"><label>Message / M-Pesa text</label><div class="pl-msg"><?= e($r['transaction_message']) ?></div></div><?php endif; ?>
        <?php if ($proof): ?>
            <div class="ax-field"><label>Proof of payment</label>
                <?= $isImg ? '<a href="' . e($proof) . '" target="_blank" rel="noopener"><img src="' . e($proof) . '" alt="Proof of payment" loading="lazy"></a>' : '<a class="ax-btn ghost sm" href="' . e($proof) . '" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i> Open proof (PDF)</a>' ?></div>
        <?php elseif ($r['proof_file']): ?><p class="muted">The proof file (<?= e($r['proof_file']) ?>) is missing from uploads/proofs.</p><?php endif; ?>
        <?php if ($r['status'] === 'pending'): ?>
        <div class="ax-form-actions">
            <form method="post" action="verifications" class="ax-form" data-confirm="Reject payment #<?= (int)$r['id'] ?>? The learner is notified." data-danger style="display:flex;gap:8px;flex-wrap:wrap;flex:1">
                <?= ax_csrf() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="return" value="<?= e($returnTo) ?>">
                <input type="text" name="reason" maxlength="300" placeholder="Reason for rejecting (sent to the learner)" aria-label="Reason" style="flex:1;min-width:180px">
                <button class="ax-btn danger"><i class="fas fa-xmark"></i> Reject</button>
            </form>
            <form method="post" action="verifications" data-confirm="Approve payment #<?= (int)$r['id'] ?>?<?= $r['payment_type'] === 'course' ? ' The learner will be enrolled on the track they paid for.' : ($r['payment_type'] === 'certificate' ? ' The certificate will be unlocked.' : '') ?>">
                <?= ax_csrf() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="return" value="<?= e($returnTo) ?>">
                <button class="ax-btn ok"><i class="fas fa-check"></i> Approve</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</dialog>
<?php endforeach; ?>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>document.querySelectorAll('dialog.ax-modal').forEach(function(d){d.addEventListener('click',function(e){if(e.target===d)d.close();});});</script>
</body></html>
