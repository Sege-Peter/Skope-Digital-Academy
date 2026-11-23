<?php
/**
 * Admin: Payment Approvals.
 *   Queue of pending payments (manual M-Pesa / bank proofs and under-paid Paystack payments) and
 *   the history of approved / rejected ones. This page owns the approve/reject logic; the Master
 *   Ledger posts its approve/reject buttons here (with a `return` address).
 *
 *   Approve rules:
 *     - only a pending payment can be approved, and only once;
 *     - a reference (Paystack reference or M-Pesa code in the message) already used on an approved
 *       payment is refused (reject the duplicate instead);
 *     - an amount below what is due right now stays pending;
 *     - course payment  → enroll_student() on the paid track/cohort (credits the amount), referral reward, notify;
 *     - certificate fee → unlock_certificate() only, never an enrolment;
 *     - registration    → activates the account.
 */
$pageTitle = 'Payment Approvals';
require_once 'includes/header.php';

/** References a payment carries: its Paystack reference and any M-Pesa-style code in the message. */
function pay_refs(array $p): array {
    $refs = [];
    if (trim((string)$p['paystack_reference']) !== '') $refs['ref'] = trim($p['paystack_reference']);
    if (preg_match_all('/\b(?=[A-Z0-9]*\d)(?=[A-Z0-9]*[A-Z])[A-Z0-9]{10}\b/', strtoupper((string)$p['transaction_message']), $m)) {
        $refs['codes'] = array_values(array_unique($m[0]));
    }
    return $refs;
}

/** Another payment using the same reference/code (approved only, or any not rejected). Returns its id or null. */
function pay_duplicate(PDO $pdo, array $p, bool $approvedOnly): ?int {
    $refs = pay_refs($p);
    $statuses = $approvedOnly ? "('verified')" : "('verified','pending')";
    if (!empty($refs['ref'])) {
        $s = $pdo->prepare("SELECT id FROM payments WHERE id <> ? AND paystack_reference = ? AND status IN $statuses LIMIT 1");
        $s->execute([$p['id'], $refs['ref']]);
        if ($id = $s->fetchColumn()) return (int)$id;
    }
    foreach ($refs['codes'] ?? [] as $code) {
        $s = $pdo->prepare("SELECT id FROM payments WHERE id <> ? AND status IN $statuses AND (UPPER(transaction_message) LIKE ? OR paystack_reference = ?) LIMIT 1");
        $s->execute([$p['id'], '%' . $code . '%', $code]);
        if ($id = $s->fetchColumn()) return (int)$id;
    }
    return null;
}

/** What this payment has to cover right now: ['due' => float|null, 'note' => string, 'course' => ?array, 'cert' => ?array]. */
function pay_due(PDO $pdo, array $p): array {
    $out = ['due' => null, 'note' => '', 'course' => null, 'cert' => null];
    if ($p['payment_type'] === 'certificate') {
        if ($p['certificate_id']) {
            $s = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
            $s->execute([$p['certificate_id']]);
            $out['cert'] = $s->fetch() ?: null;
        }
        if (!$out['cert']) { $out['note'] = 'Certificate not found'; return $out; }
        if ($out['cert']['status'] === 'pending') { $out['due'] = (float)$out['cert']['fee']; $out['note'] = 'Certificate fee'; }
        else { $out['due'] = 0.0; $out['note'] = 'Certificate already ' . ($out['cert']['status'] === 'approved' ? 'unlocked' : $out['cert']['status']); }
        return $out;
    }
    if ($p['payment_type'] === 'course' && $p['course_id']) {
        $s = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $s->execute([$p['course_id']]);
        $c = $s->fetch();
        if (!$c) { $out['note'] = 'Course no longer exists'; return $out; }
        $out['course'] = $c;
        $mode = in_array($p['learning_mode'], SDA_MODES, true) ? $p['learning_mode'] : course_modes($c)[0];
        $out['due'] = amount_due($c, $mode, get_enrollment($pdo, (int)$p['student_id'], (int)$c['id']));
        $out['note'] = mode_label($mode);
        return $out;
    }
    if ($p['payment_type'] === 'registration') { $out['due'] = (float)setting('registration_fee'); $out['note'] = 'Registration fee'; }
    return $out;
}

function pay_audit(PDO $pdo, int $adminId, string $action, string $desc): void {
    try {
        $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)")
            ->execute([$adminId, $action, $desc, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Throwable $e) { error_log($e->getMessage()); }
}

/** Approve a pending payment. Returns [ok, message]. */
function pay_approve(PDO $pdo, int $id, int $adminId): array {
    $s = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
    $s->execute([$id]);
    $p = $s->fetch();
    if (!$p) return [false, 'That payment no longer exists.'];
    if ($p['status'] !== 'pending') return [false, "Payment #$id was already " . ($p['status'] === 'verified' ? 'approved' : 'rejected') . '.'];
    if ($dup = pay_duplicate($pdo, $p, true)) {
        return [false, "Payment #$id uses a reference that was already used on approved payment #$dup. It stays pending — reject it as a duplicate."];
    }
    $d = pay_due($pdo, $p);
    if ($p['payment_type'] === 'course' && !$d['course']) return [false, "Payment #$id is for a course that no longer exists. Reject it instead."];
    // A coupon discount counts towards what is due (payments.discount, set when the learner applied a coupon).
    $covered = (float)$p['amount'] + (float)($p['discount'] ?? 0);
    if ($d['due'] !== null && $covered + 0.001 < $d['due']) {
        return [false, 'Payment #' . $id . ' covers ' . money($covered) . ' but ' . money($d['due']) . ' is due. It stays pending until the learner pays the rest (or reject it).'];
    }

    $pdo->beginTransaction();
    try {
        $u = $pdo->prepare("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ? AND status = 'pending'");
        $u->execute([$adminId, $id]);
        if ($u->rowCount() === 0) { $pdo->rollBack(); return [false, "Payment #$id was already handled."]; }
        $sid = (int)$p['student_id'];
        $msg = "Payment #$id approved.";
        if ($p['payment_type'] === 'certificate') {
            // Certificate fee: unlock the certificate. Never touches enrolments.
            if ($p['certificate_id'] && unlock_certificate($pdo, (int)$p['certificate_id'], $covered)) {
                $msg = "Payment #$id approved and the certificate is unlocked.";
            } else {
                notify($pdo, $sid, 'Payment approved', 'Your certificate payment of ' . money((float)$p['amount']) . ' was approved.',
                       'payment_approved', ['item' => 'Certificate fee', 'amount' => money((float)$p['amount']), 'reference' => $p['paystack_reference'] ?: "#$id"]);
            }
        } elseif ($p['payment_type'] === 'course' && $d['course']) {
            $c = $d['course'];
            $mode = in_array($p['learning_mode'], SDA_MODES, true) ? $p['learning_mode'] : course_modes($c)[0];
            enroll_student($pdo, $sid, $c, $mode, $p['cohort_id'] ? (int)$p['cohort_id'] : null, (float)$p['amount']);
            require_once __DIR__ . '/../includes/gamified_logic.php';
            rewardReferrer($sid, (float)$p['amount'], $pdo);
            notify($pdo, $sid, 'Payment approved', 'Your payment of ' . money((float)$p['amount']) . ' for "' . $c['title'] . '" was approved. Your ' . mode_label($mode) . ' access is open now.');
            require_once __DIR__ . '/../includes/notify.php';
            sda_notify_enrolled($pdo, $sid, $c, $mode, $p['cohort_id'] ? (int)$p['cohort_id'] : null, (float)$p['amount'], $p['paystack_reference'] ?: "#$id");
            $msg = "Payment #$id approved — the learner is enrolled in \"{$c['title']}\" (" . mode_label($mode) . ').';
        } elseif ($p['payment_type'] === 'registration') {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$sid]);
            notify($pdo, $sid, 'Account activated', 'Your registration payment was approved. Welcome to Skope Digital Academy!');
            require_once __DIR__ . '/../includes/notify.php';
            sda_notify_activated($sid, (float)$p['amount'], $p['paystack_reference'] ?: "#$id");
            $msg = "Payment #$id approved and the account is active.";
        }
        if (!empty($p['coupon_id'])) {
            require_once __DIR__ . '/../includes/coupons.php';
            coupon_redeem_payment($pdo, $id);   // records the coupon as used now that the payment is verified
        }
        pay_audit($pdo, $adminId, 'payment_approved', "Payment #$id ({$p['payment_type']}, " . money((float)$p['amount']) . ") approved");
        $pdo->commit();
        return [true, $msg];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Approve payment #' . $id . ': ' . $e->getMessage());
        return [false, 'Could not approve payment #' . $id . ': ' . $e->getMessage()];
    }
}

/** Reject a pending payment and tell the learner why. */
function pay_reject(PDO $pdo, int $id, int $adminId, string $reason): array {
    $s = $pdo->prepare("SELECT p.*, c.title AS course_title FROM payments p LEFT JOIN courses c ON c.id = p.course_id WHERE p.id = ?");
    $s->execute([$id]);
    $p = $s->fetch();
    if (!$p) return [false, 'That payment no longer exists.'];
    if ($p['status'] !== 'pending') return [false, "Payment #$id was already " . ($p['status'] === 'verified' ? 'approved' : 'rejected') . '.'];
    $u = $pdo->prepare("UPDATE payments SET status = 'failed', verified_by = ?, verified_at = NOW() WHERE id = ? AND status = 'pending'");
    $u->execute([$adminId, $id]);
    if ($u->rowCount() === 0) return [false, "Payment #$id was already handled."];
    $what = $p['payment_type'] === 'certificate' ? 'certificate fee' . ($p['course_title'] ? ' for "' . $p['course_title'] . '"' : '')
          : ($p['course_title'] ? '"' . $p['course_title'] . '"' : $p['payment_type'] . ' payment');
    notify($pdo, (int)$p['student_id'], 'Payment not approved',
        'We could not approve your payment of ' . money((float)$p['amount']) . ' for ' . $what . '.'
        . ($reason !== '' ? ' Reason: ' . $reason : '') . ' Please check the details and send it again, or contact support.',
        'payment_rejected', ['item' => trim($what, '"'), 'amount' => money((float)$p['amount']), 'reason' => $reason !== '' ? $reason : 'The payment could not be matched.']);
    pay_audit($pdo, $adminId, 'payment_rejected', "Payment #$id rejected" . ($reason !== '' ? ': ' . $reason : ''));
    return [true, "Payment #$id rejected and the learner was told" . ($reason !== '' ? ' why' : '') . '.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ret = (string)($_POST['return'] ?? '');
    $ret = preg_match('/^(verifications|payments)(\?[A-Za-z0-9_=&%.+\-]*)?$/', $ret) ? $ret : null;
    ax_post_guard();
    $id = (int)($_POST['id'] ?? 0);
    $act = $_POST['action'] ?? '';
    if ($act === 'approve') {
        [$ok, $msg] = pay_approve($pdo, $id, (int)$user['id']);
        ax_flash($ok ? 'ok' : 'err', $msg);
    } elseif ($act === 'reject') {
        [$ok, $msg] = pay_reject($pdo, $id, (int)$user['id'], mb_substr(trim((string)($_POST['reason'] ?? '')), 0, 300));
        ax_flash($ok ? 'ok' : 'err', $msg);
    }
    ax_back($ret);
}

/* ── Data ─────────────────────────────────────────────────────────────── */
$tab = ($_GET['tab'] ?? '') === 'history' ? 'history' : 'queue';
$hist = in_array($_GET['h'] ?? '', ['verified', 'failed'], true) ? $_GET['h'] : 'all';

$base = "SELECT p.*, u.name AS student_name, u.email AS student_email, u.avatar, u.phone AS student_phone,
                c.title AS course_title, co.name AS cohort_name, co.start_date AS cohort_start,
                ce.verification_code, ce.status AS cert_status, ce.fee AS cert_fee,
                a.name AS admin_name
         FROM payments p
         LEFT JOIN users u ON u.id = p.student_id
         LEFT JOIN courses c ON c.id = p.course_id
         LEFT JOIN course_cohorts co ON co.id = p.cohort_id
         LEFT JOIN certificates ce ON ce.id = p.certificate_id
         LEFT JOIN users a ON a.id = p.verified_by";
$pending = $pdo->query("$base WHERE p.status = 'pending' ORDER BY p.created_at ASC")->fetchAll();
foreach ($pending as &$p) {
    $p['_due'] = pay_due($pdo, $p);
    $p['_dup'] = pay_duplicate($pdo, $p, false);
    $p['_enr'] = $p['course_id'] && $p['payment_type'] === 'course' ? get_enrollment($pdo, (int)$p['student_id'], (int)$p['course_id']) : null;
}
unset($p);

$history = [];
$reasons = [];
if ($tab === 'history') {
    $w = $hist === 'all' ? "p.status IN ('verified','failed')" : 'p.status = ' . $pdo->quote($hist);
    $history = $pdo->query("$base WHERE $w ORDER BY COALESCE(p.verified_at, p.created_at) DESC LIMIT 200")->fetchAll();
    foreach ($pdo->query("SELECT description FROM audit_log WHERE action = 'payment_rejected' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $d) {
        if (preg_match('/^Payment #(\d+) rejected: (.+)$/s', $d, $m)) $reasons[(int)$m[1]] = $m[2];
    }
}

$stats = [
    'pending_n'   => count($pending),
    'pending_amt' => array_sum(array_map(fn($p) => (float)$p['amount'], $pending)),
    'month_amt'   => (float)ax_scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'verified' AND verified_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'month_n'     => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM payments WHERE status = 'verified' AND verified_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'rejected_30' => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM payments WHERE status = 'failed' AND COALESCE(verified_at, created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
    'avg_hours'   => ax_scalar($pdo, "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, verified_at)) / 60 FROM payments WHERE status = 'verified' AND verified_at IS NOT NULL AND proof_file IS NOT NULL"),
    'verified_n'  => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM payments WHERE status = 'verified'"),
    'failed_n'    => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM payments WHERE status = 'failed'"),
];

function pay_proof_url(?string $f): ?string {
    if (!$f) return null;
    $f = basename($f);
    return is_file(__DIR__ . '/../uploads/proofs/' . $f) ? '../uploads/proofs/' . rawurlencode($f) : null;
}
function pay_is_image(string $f): bool { return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true); }

/** "What it's for" cell. */
function pay_for_html(array $p): string {
    if ($p['payment_type'] === 'certificate') {
        return '<span class="ax-pill violet"><i class="fas fa-award"></i> Certificate fee</span><div class="pv-for">' . e($p['course_title'] ?? 'Course removed') . '</div>'
             . ($p['verification_code'] ? '<div class="muted">Code ' . e($p['verification_code']) . '</div>' : '');
    }
    if ($p['payment_type'] === 'course') {
        $m = in_array($p['learning_mode'], SDA_MODES, true) ? mode_label($p['learning_mode']) : 'Track not set';
        return '<span class="ax-pill info"><i class="fas fa-book"></i> Course</span><div class="pv-for">' . e($p['course_title'] ?? 'Course removed') . '</div>'
             . '<div class="muted">' . e($m) . ($p['cohort_name'] ? ' · ' . e($p['cohort_name']) . ' (starts ' . ax_date($p['cohort_start']) . ')' : '') . '</div>';
    }
    return '<span class="ax-pill">' . e(ucfirst((string)$p['payment_type'])) . '</span>';
}

function pay_ref_html(array $p): string {
    $out = '';
    if ($p['paystack_reference']) $out .= '<span class="ax-code">' . e($p['paystack_reference']) . '</span>';
    $msg = trim((string)$p['transaction_message']);
    if ($msg !== '') $out .= '<div class="pv-msg" title="' . e($msg) . '">' . e(mb_strimwidth($msg, 0, 90, '…')) . '</div>';
    return $out !== '' ? $out : '<span class="muted">No reference given</span>';
}

$queueUrl = 'verifications';
require_once 'includes/sidebar.php';
?>
<style>
  .pv-for { font-weight: 700; color: var(--text-main); margin-top: 6px; }
  .pv-msg { font-size: .78rem; color: var(--text-dim); margin-top: 4px; max-width: 260px; overflow-wrap: anywhere; line-height: 1.4; }
  .pv-thumb { width: 54px; height: 54px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface-2); padding: 0; overflow: hidden; cursor: zoom-in; display: inline-flex; align-items: center; justify-content: center; color: var(--text-muted); }
  .pv-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .pv-due { font-size: .74rem; margin-top: 4px; }
  .pv-amt { font: 800 1rem 'Poppins', sans-serif; color: var(--text-main); }
  .pv-flag { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
  #proof-dlg { width: min(900px, calc(100vw - 32px)); }
  #proof-dlg .pv-stage { background: var(--surface-2); border-radius: 14px; display: flex; align-items: center; justify-content: center; min-height: 240px; padding: 10px; }
  #proof-dlg .pv-stage img { max-width: 100%; max-height: 68vh; border-radius: 8px; }
  #proof-dlg .pv-stage iframe { width: 100%; height: 68vh; border: 0; border-radius: 8px; background: var(--surface); }
  .ax-table .actions .ax-btn + .ax-btn { margin-left: 6px; }
  @media (max-width: 760px) { .pv-msg { max-width: none; text-align: right; } .ax-table.cards td .pv-for { text-align: right; } }
</style>
<main class="main-content">
    <?php ax_header('Payment', 'Approvals', 'Check proofs, approve or reject',
        '<a class="ax-btn ghost" href="payments"><i class="fas fa-receipt"></i> Master ledger</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('hourglass-half', number_format($stats['pending_n']), 'Waiting for approval', $stats['pending_n'] ? ax_money($stats['pending_amt']) . ' in total' : 'Queue is clear', 'amber') ?>
            <?= ax_stat('circle-check', ax_money($stats['month_amt']), 'Approved this month', number_format($stats['month_n']) . ' payment' . ($stats['month_n'] == 1 ? '' : 's'), 'green') ?>
            <?= ax_stat('circle-xmark', number_format($stats['rejected_30']), 'Rejected (30 days)', number_format($stats['failed_n']) . ' all time', 'red') ?>
            <?= ax_stat('stopwatch', $stats['avg_hours'] ? (round((float)$stats['avg_hours'], 1) . ' h') : '—', 'Average approval time', 'For payments sent with a proof', 'violet') ?>
        </div>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="View">
                <a href="verifications" class="<?= $tab === 'queue' ? 'on' : '' ?>"><i class="fas fa-inbox"></i> Waiting <span class="n"><?= $stats['pending_n'] ?></span></a>
                <a href="verifications?tab=history" class="<?= $tab === 'history' && $hist === 'all' ? 'on' : '' ?>"><i class="fas fa-clock-rotate-left"></i> History <span class="n"><?= $stats['verified_n'] + $stats['failed_n'] ?></span></a>
                <a href="verifications?tab=history&amp;h=verified" class="<?= $tab === 'history' && $hist === 'verified' ? 'on' : '' ?>">Approved <span class="n"><?= $stats['verified_n'] ?></span></a>
                <a href="verifications?tab=history&amp;h=failed" class="<?= $tab === 'history' && $hist === 'failed' ? 'on' : '' ?>">Rejected <span class="n"><?= $stats['failed_n'] ?></span></a>
            </nav>
            <label class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Search learner, course, reference…" data-filter-table="pv-table" aria-label="Search payments"></label>
        </div>

        <?php if ($tab === 'queue'): ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-inbox"></i> Waiting for approval</h2><p>Oldest first. Approving a course payment enrols the learner on the track they paid for; a certificate payment only unlocks the certificate.</p></div></div>
            <?php if (!$pending): ?>
                <div class="ax-empty"><i class="fas fa-check-double"></i><b>Nothing to approve</b>New payment proofs from learners will appear here.</div>
            <?php else: ?>
            <div class="ax-table-wrap">
                <table class="ax-table cards" id="pv-table">
                    <thead><tr><th>Learner</th><th>For</th><th>Amount</th><th>Reference / M-Pesa message</th><th>Proof</th><th>Sent</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($pending as $p):
                        $due = $p['_due']['due'];
                        $covered = (float)$p['amount'] + (float)($p['discount'] ?? 0);
                        $short = $due !== null && $covered + 0.001 < $due;
                        $proof = pay_proof_url($p['proof_file']); ?>
                        <tr>
                            <td data-label="Learner"><div class="ax-who"><?= ax_avatar($p['student_name'] ?? '?', $p['avatar'] ?? null) ?><div><b><?= e($p['student_name'] ?? 'Deleted account') ?></b><small><?= e($p['student_email'] ?? '#' . $p['student_id']) ?></small></div></div></td>
                            <td data-label="For"><div><?= pay_for_html($p) ?>
                                <?php if ($p['_enr'] && in_array($p['_enr']['status'], ['active', 'completed'], true)): ?><div class="muted">Already enrolled (<?= e(mode_label($p['_enr']['learning_mode'])) ?>, paid <?= e(money((float)$p['_enr']['amount_paid'])) ?>)</div><?php endif; ?></div></td>
                            <td data-label="Amount" class="num"><div><div class="pv-amt"><?= e(money((float)$p['amount'])) ?></div>
                                <?php if ($due !== null): ?>
                                    <div class="pv-due"><?= (float)($p['discount'] ?? 0) > 0 ? '<span class="ax-pill orange"><i class="fas fa-ticket"></i> Coupon −' . e(money((float)$p['discount'])) . '</span> ' : '' ?><?= $short ? '<span class="ax-pill bad">Short by ' . e(money($due - $covered)) . '</span>' : '<span class="ax-pill ok">Covers ' . e(money($due)) . ' due</span>' ?></div>
                                <?php elseif ($p['_due']['note']): ?><div class="pv-due"><span class="ax-pill warn"><?= e($p['_due']['note']) ?></span></div><?php endif; ?></div></td>
                            <td data-label="Reference"><div><?= pay_ref_html($p) ?>
                                <?php if ($p['_dup']): ?><div class="pv-flag"><span class="ax-pill bad"><i class="fas fa-clone"></i> Same reference as #<?= (int)$p['_dup'] ?></span></div><?php endif; ?></div></td>
                            <td data-label="Proof">
                                <?php if ($proof): ?>
                                    <button type="button" class="pv-thumb" data-proof="<?= e($proof) ?>" data-pdf="<?= pay_is_image($p['proof_file']) ? '' : '1' ?>" data-title="<?= e(money((float)$p['amount']) . ' from ' . ($p['student_name'] ?? 'learner') . ' · payment #' . $p['id']) ?>" aria-label="View proof for payment #<?= (int)$p['id'] ?>">
                                        <?= pay_is_image($p['proof_file']) ? '<img src="' . e($proof) . '" alt="" loading="lazy">' : '<i class="fas fa-file-pdf fa-lg"></i>' ?>
                                    </button>
                                <?php else: ?><span class="muted"><?= $p['proof_file'] ? 'File missing' : 'No proof' ?></span><?php endif; ?>
                            </td>
                            <td data-label="Sent"><span title="<?= e(ax_date($p['created_at'], 'M j, Y g:i a')) ?>"><?= e(ax_ago($p['created_at'])) ?></span><div class="muted">#<?= (int)$p['id'] ?></div></td>
                            <td class="actions" data-label="">
                                <form method="post" data-confirm="<?= e($p['payment_type'] === 'certificate' ? 'Approve this certificate payment and unlock the certificate?' : ($p['payment_type'] === 'course' ? 'Approve this payment and enrol ' . ($p['student_name'] ?? 'the learner') . ' in "' . ($p['course_title'] ?? '') . '"?' : 'Approve this payment?')) ?>">
                                    <?= ax_csrf() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button class="ax-btn ok sm"<?= $short || $p['_dup'] ? ' title="Amount is short or the reference is a duplicate – approval will be refused"' : '' ?>><i class="fas fa-check"></i> Approve</button>
                                </form>
                                <button type="button" class="ax-btn danger sm" data-reject="<?= (int)$p['id'] ?>" data-who="<?= e(($p['student_name'] ?? 'learner') . ' · ' . money((float)$p['amount'])) ?>" data-dup="<?= $p['_dup'] ? (int)$p['_dup'] : '' ?>" data-short="<?= $short ? e(money($due)) : '' ?>"><i class="fas fa-xmark"></i> Reject</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <?php else: ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-clock-rotate-left"></i> Decisions</h2><p>Latest 200 approved and rejected payments, with who decided and why.</p></div></div>
            <?php if (!$history): ?>
                <div class="ax-empty"><i class="fas fa-folder-open"></i><b>No decisions yet</b>Approved and rejected payments will be listed here.</div>
            <?php else: ?>
            <div class="ax-table-wrap">
                <table class="ax-table cards" id="pv-table">
                    <thead><tr><th>Learner</th><th>For</th><th>Amount</th><th>Reference</th><th>Result</th><th>Decided by</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): $proof = pay_proof_url($h['proof_file']); ?>
                        <tr>
                            <td data-label="Learner"><div class="ax-who"><?= ax_avatar($h['student_name'] ?? '?', $h['avatar'] ?? null) ?><div><b><?= e($h['student_name'] ?? 'Deleted account') ?></b><small>Payment #<?= (int)$h['id'] ?></small></div></div></td>
                            <td data-label="For"><div><?= pay_for_html($h) ?></div></td>
                            <td data-label="Amount" class="num strong"><?= e(money((float)$h['amount'])) ?></td>
                            <td data-label="Reference"><div><?= pay_ref_html($h) ?>
                                <?php if ($proof): ?><div style="margin-top:6px"><button type="button" class="ax-btn ghost sm" data-proof="<?= e($proof) ?>" data-pdf="<?= pay_is_image($h['proof_file']) ? '' : '1' ?>" data-title="<?= e(money((float)$h['amount']) . ' from ' . ($h['student_name'] ?? 'learner') . ' · payment #' . $h['id']) ?>"><i class="fas fa-image"></i> Proof</button></div><?php endif; ?></div></td>
                            <td data-label="Result"><div>
                                <?= $h['status'] === 'verified' ? '<span class="ax-pill ok"><i class="fas fa-check"></i> Approved</span>' : '<span class="ax-pill bad"><i class="fas fa-xmark"></i> Rejected</span>' ?>
                                <?php if (isset($reasons[(int)$h['id']])): ?><div class="pv-msg"><?= e($reasons[(int)$h['id']]) ?></div><?php endif; ?></div></td>
                            <td data-label="Decided by"><?= e($h['admin_name'] ?? ($h['paystack_reference'] ? 'Paystack (automatic)' : '—')) ?></td>
                            <td data-label="When" class="num"><?= e(ax_date($h['verified_at'] ?: $h['created_at'], 'M j, Y g:i a')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</main>

<dialog class="ax-modal" id="proof-dlg" aria-labelledby="proof-title">
    <div class="ax-panel-h"><div><h3 id="proof-title"><i class="fas fa-image"></i> Proof of payment</h3><p data-proof-meta></p></div>
        <div style="display:flex;gap:8px"><a class="ax-btn ghost sm" data-proof-open target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> Open full size</a><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div></div>
    <div class="ax-panel-b"><div class="pv-stage" data-proof-stage></div></div>
</dialog>

<dialog class="ax-modal" id="reject-dlg" aria-labelledby="reject-title" style="width:min(520px,calc(100vw - 32px))">
    <form method="post" class="ax-form" data-confirm="Reject this payment? The learner will be notified with your reason." data-danger>
        <div class="ax-panel-h"><div><h3 id="reject-title"><i class="fas fa-xmark"></i> Reject payment</h3><p data-reject-who></p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="" data-reject-id>
            <div class="ax-field"><label for="rj-reason">Reason (sent to the learner)</label>
                <textarea id="rj-reason" name="reason" maxlength="300" placeholder="e.g. We couldn't find this M-Pesa code in our statement."></textarea>
                <div class="hint">Quick picks:
                    <a href="#" data-reason="We couldn't find this payment in our M-Pesa / bank statement.">not found</a> ·
                    <a href="#" data-reason="The amount paid is less than the amount due.">amount short</a> ·
                    <a href="#" data-reason="This reference was already used on another payment.">duplicate reference</a> ·
                    <a href="#" data-reason="The proof image is unreadable. Please upload a clear screenshot.">unreadable proof</a></div></div>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn warn"><i class="fas fa-xmark"></i> Reject and notify</button></div>
        </div>
    </form>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function(){
  var pd = document.getElementById('proof-dlg'), stage = pd.querySelector('[data-proof-stage]');
  document.querySelectorAll('[data-proof]').forEach(function(b){
    b.addEventListener('click', function(){
      stage.innerHTML = '';
      var el = document.createElement(b.dataset.pdf ? 'iframe' : 'img');
      el.src = b.dataset.proof; if (!b.dataset.pdf) el.alt = 'Proof of payment'; else el.title = 'Proof of payment';
      stage.appendChild(el);
      pd.querySelector('[data-proof-meta]').textContent = b.dataset.title || '';
      pd.querySelector('[data-proof-open]').href = b.dataset.proof;
      pd.showModal();
    });
  });
  pd.addEventListener('click', function(e){ if (e.target === pd) pd.close(); });
  var rd = document.getElementById('reject-dlg'), ta = rd.querySelector('textarea');
  document.querySelectorAll('[data-reject]').forEach(function(b){
    b.addEventListener('click', function(){
      rd.querySelector('[data-reject-id]').value = b.dataset.reject;
      rd.querySelector('[data-reject-who]').textContent = 'Payment #' + b.dataset.reject + ' · ' + b.dataset.who;
      ta.value = b.dataset.dup ? 'This reference was already used on another payment (#' + b.dataset.dup + ').' : (b.dataset.short ? 'The amount paid is less than the ' + b.dataset.short + ' due.' : '');
      rd.showModal(); ta.focus();
    });
  });
  rd.querySelectorAll('[data-reason]').forEach(function(a){ a.addEventListener('click', function(e){ e.preventDefault(); ta.value = a.dataset.reason; ta.focus(); }); });
})();
</script>
</body></html>
