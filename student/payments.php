<?php
$pageTitle = 'Payments';
require_once 'includes/header.php';

try {
    // 1. Fetch payment history (LEFT JOIN so registration/certificate payments without a course still show)
    $stmt = $pdo->prepare("SELECT p.*, c.title as course_title, c.thumbnail as course_thumb
                           FROM payments p
                           LEFT JOIN courses c ON p.course_id = c.id
                           WHERE p.student_id = ?
                           ORDER BY p.created_at DESC");
    $stmt->execute([$student['id']]);
    $payments = $stmt->fetchAll();

    // 2. Counts
    $pending_count = count(array_filter($payments, fn($p) => $p['status'] === 'pending'));
    $verified_count = count(array_filter($payments, fn($p) => $p['status'] === 'verified'));
    $total_paid = array_sum(array_map(fn($p) => $p['status'] === 'verified' ? (float)$p['amount'] : 0, $payments));

} catch (Exception $e) { $payments = []; $pending_count = $verified_count = 0; $total_paid = 0; }

$type_labels = ['course' => 'Course', 'registration' => 'Registration', 'donation' => 'Donation', 'certificate' => 'Certificate'];
$status_map  = ['verified' => ['green', 'Paid'], 'pending' => ['yellow', 'Being checked'], 'failed' => ['red', 'Failed']];

require_once 'includes/layout-top.php';
?>

<div class="sp-page-head">
    <div><h1>Payments</h1><p>Your payment history and the status of each payment.</p></div>
    <div class="sp-actions"><a href="../courses" class="sp-btn"><i class="fas fa-plus"></i> Join a course</a></div>
</div>

<section class="sp-grid cols-4 sp-section">
    <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-receipt"></i></span><div><strong><?= count($payments) ?></strong><span>Payments</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon yellow"><i class="fas fa-clock"></i></span><div><strong><?= $pending_count ?></strong><span>Being checked</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-circle-check"></i></span><div><strong><?= $verified_count ?></strong><span>Paid</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon violet"><i class="fas fa-wallet"></i></span><div><strong>KES <?= number_format($total_paid) ?></strong><span>Total paid</span></div></div>
</section>

<section class="sp-section">
    <div class="sp-section-head"><div><h2>Payment history</h2><p>Newest first</p></div></div>

    <?php if (!empty($payments)): ?>
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr><th>Item</th><th>Reference</th><th>Date</th><th style="text-align: right;">Amount</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p):
                    [$badge, $label] = $status_map[$p['status']] ?? ['gray', ucfirst((string)$p['status'])];
                    $type = $type_labels[$p['payment_type'] ?? 'course'] ?? 'Payment';
                    $item = $p['course_title'] ?: $type . ' fee';
                ?>
                    <tr>
                        <td>
                            <div class="sp-row" style="flex-wrap: nowrap; min-width: 220px;">
                                <span class="sp-ico gray" style="width: 52px; height: 36px; overflow: hidden;">
                                    <?php if (!empty($p['course_thumb'])): ?>
                                        <img src="../uploads/courses/<?= htmlspecialchars($p['course_thumb']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.replaceWith(Object.assign(document.createElement('i'), {className: 'fas fa-receipt'}))">
                                    <?php else: ?>
                                        <i class="fas fa-receipt"></i>
                                    <?php endif; ?>
                                </span>
                                <div style="min-width: 0;">
                                    <strong style="display: block; font-size: .88rem;"><?= htmlspecialchars($item) ?></strong>
                                    <span class="sp-muted" style="font-size: .75rem;"><?= htmlspecialchars($type) ?></span>
                                </div>
                            </div>
                        </td>
                        <td style="white-space: nowrap;">
                            #<?= str_pad((string)(int)$p['id'], 6, '0', STR_PAD_LEFT) ?>
                            <?php if (!empty($p['paystack_reference'])): ?><div class="sp-muted" style="font-size: .72rem;"><?= htmlspecialchars($p['paystack_reference']) ?></div><?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;"><?= !empty($p['created_at']) ? date('j M Y', strtotime($p['created_at'])) : '' ?></td>
                        <td style="text-align: right; white-space: nowrap; font-weight: 700;">KES <?= number_format((float)$p['amount']) ?></td>
                        <td><span class="sp-badge <?= $badge ?>"><?= htmlspecialchars($label) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="sp-empty">
            <div class="sp-ico"><i class="fas fa-receipt"></i></div>
            <h3>No payments yet</h3>
            <p>When you pay for a course, registration or certificate, it will show up here.</p>
            <a href="../courses" class="sp-btn">Browse courses</a>
        </div>
    <?php endif; ?>
</section>

<div class="sp-alert">
    <i class="fas fa-shield-halved" style="margin-top: 3px;"></i>
    <div>
        <strong>About manual payments</strong><br>
        If you sent proof of payment, our team usually checks it within 2 to 4 hours. Questions about a payment? <a href="support" class="sp-link" style="font-size: inherit;">Contact support</a>.
    </div>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
