<?php
/**
 * Coupons – Skope Digital Academy
 *
 * Admin-made discount codes (admin/coupons) for course enrolments and certificate fees.
 *   coupon_check()   validate a code for a learner + purchase and work out the discount
 *   coupon_redeem()  record a use (only once money is verified, or a free coupon path completes)
 *   coupon_redeem_payment()  record the use carried by a payment row (payments.coupon_id / discount)
 *
 * A payment that used a coupon stores payments.coupon_id and payments.discount (amount taken off).
 * Whoever approves such a payment must treat it as covering (amount + discount).
 */
require_once __DIR__ . '/learning.php';

function coupon_normalize(?string $code): string {
    return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$code));
}

function coupon_find(PDO $pdo, string $code): ?array {
    $code = coupon_normalize($code);
    if ($code === '') return null;
    try {
        $st = $pdo->prepare("SELECT * FROM coupons WHERE code = ?");
        $st->execute([$code]);
        return $st->fetch() ?: null;
    } catch (Throwable $e) { return null; }   // table missing before migration
}

/** Amount a coupon takes off $amount (never more than $amount). */
function coupon_discount(array $coupon, float $amount): float {
    if ($amount <= 0) return 0.0;
    $off = $coupon['discount_type'] === 'percent'
        ? round($amount * min(100, max(0, (float)$coupon['value'])) / 100, 2)
        : max(0.0, (float)$coupon['value']);
    return min($amount, round($off, 2));
}

/** Uses so far: recorded redemptions plus payments still waiting for approval with this coupon. */
function coupon_uses(PDO $pdo, int $couponId, ?int $uid = null): int {
    $u = $uid ? ' AND user_id = ' . (int)$uid : '';
    $p = $uid ? ' AND student_id = ' . (int)$uid : '';
    $n = (int)$pdo->query("SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = " . (int)$couponId . $u)->fetchColumn();
    $n += (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE coupon_id = " . (int)$couponId . " AND status = 'pending'" . $p)->fetchColumn();
    return $n;
}

/** Short human label, e.g. "20% off" or "KES 500 off". */
function coupon_label(array $coupon): string {
    return $coupon['discount_type'] === 'percent'
        ? rtrim(rtrim(number_format((float)$coupon['value'], 2), '0'), '.') . '% off'
        : money((float)$coupon['value']) . ' off';
}

/**
 * Validate a coupon code for a learner and a purchase.
 *   $kind: 'course' | 'certificate'; $courseId: the course (for a certificate, the certificate's course).
 * Returns ['ok' => bool, 'error' => string, 'coupon' => ?array, 'discount' => float, 'final' => float].
 */
function coupon_check(PDO $pdo, string $code, int $uid, string $kind, ?int $courseId, float $amount): array {
    $out = ['ok' => false, 'error' => '', 'coupon' => null, 'discount' => 0.0, 'final' => $amount];
    $c = coupon_find($pdo, $code);
    $now = time();
    if (!$c || !(int)$c['active'])                                   $out['error'] = 'That coupon code is not valid.';
    elseif ($c['starts_at'] && strtotime($c['starts_at']) > $now)    $out['error'] = 'That coupon is not active yet. It starts on ' . date('M j, Y', strtotime($c['starts_at'])) . '.';
    elseif ($c['expires_at'] && strtotime($c['expires_at']) < $now)  $out['error'] = 'That coupon has expired.';
    elseif ($c['applies_to'] !== 'all' && $c['applies_to'] !== $kind) $out['error'] = $c['applies_to'] === 'certificate' ? 'That coupon only works on certificate fees.' : 'That coupon only works on course enrolments.';
    elseif ($c['course_id'] && (int)$c['course_id'] !== (int)$courseId) $out['error'] = 'That coupon is for a different course.';
    elseif ($amount <= 0)                                             $out['error'] = 'There is nothing to pay, so no coupon is needed.';
    elseif ($c['max_uses'] !== null && coupon_uses($pdo, (int)$c['id']) >= (int)$c['max_uses']) $out['error'] = 'That coupon has been fully used.';
    elseif (coupon_uses($pdo, (int)$c['id'], $uid) >= max(1, (int)$c['per_user_limit'])) $out['error'] = 'You have already used that coupon.';
    else {
        $off = coupon_discount($c, $amount);
        if ($off <= 0) $out['error'] = 'That coupon gives no discount on this payment.';
        else $out = ['ok' => true, 'error' => '', 'coupon' => $c, 'discount' => $off, 'final' => max(0.0, round($amount - $off, 2))];
    }
    if (!$out['ok']) $out['coupon'] = $c;
    return $out;
}

/** Record one use. Idempotent per payment. */
function coupon_redeem(PDO $pdo, int $couponId, int $uid, float $amountOff, ?int $paymentId = null, ?int $enrollmentId = null, ?int $certificateId = null): void {
    $pdo->prepare("INSERT IGNORE INTO coupon_redemptions (coupon_id, user_id, payment_id, enrollment_id, certificate_id, amount_off) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$couponId, $uid, $paymentId, $enrollmentId, $certificateId, round($amountOff, 2)]);
}

/** Record the coupon use carried by a verified payment row (no-op without a coupon). */
function coupon_redeem_payment(PDO $pdo, int $paymentId): void {
    $st = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
    $st->execute([$paymentId]);
    $p = $st->fetch();
    if (!$p || empty($p['coupon_id']) || $p['status'] !== 'verified') return;
    $enrId = null;
    if ($p['payment_type'] === 'course' && $p['course_id']) {
        $e = get_enrollment($pdo, (int)$p['student_id'], (int)$p['course_id']);
        $enrId = $e ? (int)$e['id'] : null;
    }
    coupon_redeem($pdo, (int)$p['coupon_id'], (int)$p['student_id'], (float)$p['discount'], (int)$p['id'], $enrId,
                  $p['certificate_id'] ? (int)$p['certificate_id'] : null);
}
