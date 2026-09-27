<?php
$pageTitle = 'Billing & Payment Ledger';
require_once 'includes/header.php';

try {
    // 1. Fetch payment history
    $stmt = $pdo->prepare("SELECT p.*, c.title as course_title, c.thumbnail as course_thumb
                           FROM payments p
                           JOIN courses c ON p.course_id = c.id
                           WHERE p.student_id = ?
                           ORDER BY p.created_at DESC");
    $stmt->execute([$student['id']]);
    $payments = $stmt->fetchAll();

    // 2. Counts
    $pending_count = count(array_filter($payments, fn($p) => $p['status'] === 'pending'));
    $verified_count = count(array_filter($payments, fn($p) => $p['status'] === 'verified'));

} catch (Exception $e) { $payments = []; $pending_count = $verified_count = 0; }

require_once 'includes/layout-top.php';
?>

<header class="portal-header" style="margin-bottom: 24px;">
    <div class="greeting">
        <h1 style="font-size: 1.85rem; font-weight: 800; margin: 0;">Billing & Ledger</h1>
        <p style="color: var(--text-dim); margin-top: 4px;">Track your curriculum investments and course acquisitions.</p>
    </div>
    <div>
        <a href="../courses.php" class="btn-premium" style="display: flex; gap: 8px; align-items: center; padding: 12px 24px; border-radius: 12px;"><i class="fas fa-plus"></i> New Enrollment</a>
    </div>
</header>

<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card" style="display: flex; align-items: center; gap: 20px;">
        <div style="width: 50px; height: 50px; background: #f1f5f9; color: var(--text-main); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;"><i class="fas fa-receipt"></i></div>
        <div>
            <span style="display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Total Invoices</span>
            <strong style="font-size: 1.5rem; font-weight: 800;"><?= count($payments) ?></strong>
        </div>
    </div>
    <div class="card" style="display: flex; align-items: center; gap: 20px;">
        <div style="width: 50px; height: 50px; background: #FFF9E7; color: #D97706; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;"><i class="fas fa-clock"></i></div>
        <div>
            <span style="display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">In Audit</span>
            <strong style="font-size: 1.5rem; font-weight: 800;"><?= $pending_count ?></strong>
        </div>
    </div>
    <div class="card" style="display: flex; align-items: center; gap: 20px;">
        <div style="width: 50px; height: 50px; background: #ECFDF5; color: #059669; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;"><i class="fas fa-check-circle"></i></div>
        <div>
            <span style="display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Verified</span>
            <strong style="font-size: 1.5rem; font-weight: 800;"><?= $verified_count ?></strong>
        </div>
    </div>
</div>

<div class="ledger-stack">
    <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 20px;">Transaction History</h3>
    
    <?php if(!empty($payments)): ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
        <?php foreach($payments as $p): ?>
        <div class="card" style="padding: 16px 24px; display: flex; align-items: center; gap: 24px; border: 1px solid var(--border); border-radius: 16px; transition: 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
            <img src="../<?= $p['course_thumb'] ?: 'assets/images/course-placeholder.jpg' ?>" style="width: 60px; height: 40px; border-radius: 8px; object-fit: cover;">
            
            <div style="flex: 1;">
                <div style="font-weight: 800; font-size: 0.9rem;"><?= htmlspecialchars($p['course_title']) ?></div>
                <div style="font-size: 0.7rem; color: var(--text-dim);">Tx ID: #<?= str_pad($p['id'], 6, '0', STR_PAD_LEFT) ?> • <?= date('M j, Y', strtotime($p['created_at'])) ?></div>
            </div>

            <div style="text-align: right;">
                <div style="font-weight: 900; font-size: 1rem; color: var(--text-main);">KES <?= number_format($p['amount']) ?></div>
                <span style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; background: <?= $p['status'] == 'verified' ? '#ECFDF5' : ($p['status'] == 'pending' ? '#FFF9E7' : '#FEF2F2') ?>; color: <?= $p['status'] == 'verified' ? '#059669' : ($p['status'] == 'pending' ? '#D97706' : '#ef4444') ?>;">
                    <?= $p['status'] ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 60px 40px; border-style: dashed;">
            <i class="fas fa-receipt" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 20px; display: block;"></i>
            <h3 style="font-weight: 800;">No Financial Records</h3>
            <p style="color: var(--text-dim);">You haven't made any curriculum investments yet.</p>
        </div>
    <?php endif; ?>
</div>

<div style="margin-top: 40px; background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 16px; padding: 20px; display: flex; gap: 16px; align-items: flex-start;">
    <i class="fas fa-shield-alt" style="color: var(--primary); font-size: 1.25rem;"></i>
    <div>
        <h4 style="margin: 0; font-size: 0.9rem; font-weight: 800; color: #0284c7;">Secure Payments</h4>
        <p style="margin: 4px 0 0; font-size: 0.8rem; color: #0369a1; line-height: 1.5;">All manual payment proofs are reviewed by our financial compliance team within 2-4 hours. For billing support, please contact the Help Desk.</p>
    </div>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
