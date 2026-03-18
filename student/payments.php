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

    // 2. Fetch pending verification count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE student_id = ? AND status = 'pending'");
    $stmt->execute([$student['id']]);
    $pending_count = $stmt->fetchColumn();

} catch (Exception $e) {
    error_log($e->getMessage());
    $payments = [];
    $pending_count = 0;
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .billing-hero {
        background: white;
        border: 1px solid var(--dark-border);
        border-radius: 32px;
        padding: 48px;
        margin-bottom: 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
    }
    .billing-hero::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 8px;
        background: var(--primary);
        border-radius: 32px 0 0 32px;
    }

    .payment-stats { display: flex; gap: 48px; }
    .stat-group { text-align: center; }
    .stat-val { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 900; color: var(--dark); line-height: 1.2; }
    .stat-label { font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-top: 4px; }

    .transaction-card {
        background: white;
        border: 1px solid var(--dark-border);
        border-radius: 24px;
        padding: 24px;
        margin-bottom: 16px;
        display: grid;
        grid-template-columns: 80px 1fr 150px 150px 120px;
        align-items: center;
        gap: 24px;
        transition: 0.3s;
    }
    .transaction-card:hover { border-color: var(--primary); transform: translateX(5px); box-shadow: var(--shadow-sm); }

    .course-mini-thumb { width: 80px; height: 55px; border-radius: 12px; object-fit: cover; }
    
    .status-pill { padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; text-align: center; }
    .status-verified { background: #DCFCE7; color: #166534; }
    .status-pending { background: #FEF9C3; color: #854D0E; }
    .status-failed { background: #FEE2E2; color: #991B1B; }

    .ledger-empty {
        text-align: center;
        padding: 80px 40px;
        background: var(--bg-light);
        border-radius: 32px;
        border: 1px dashed var(--dark-border);
    }

    @media (max-width: 1100px) {
        .transaction-card { grid-template-columns: 80px 1fr 150px; }
        .hide-md { display: none; }
    }
    @media (max-width: 768px) {
        .billing-hero { flex-direction: column; text-align: center; padding: 40px 24px; }
        .payment-stats { margin-top: 32px; gap: 32px; }
        .transaction-card { grid-template-columns: 1fr 1fr; gap: 16px; }
        .course-mini-thumb { grid-column: span 2; width: 100%; height: 120px; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Billing <span class="text-primary">& Ledger</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Verified history of your curriculum investments and course acquisitions.</p>
            </div>
        </div>
        <div>
            <a href="../courses.php" class="btn btn-primary btn-sm"><i class="fas fa-shopping-cart"></i> New Enrollment</a>
        </div>
    </header>

    <div class="billing-hero">
        <div>
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.4rem; font-weight: 800; margin-bottom: 8px;">Financial Overview</h2>
            <p style="color: var(--text-dim); font-size: 0.95rem;">Transparent tracking of all manual and automated payment cycles.</p>
        </div>
        <div class="payment-stats">
            <div class="stat-group">
                <div class="stat-val"><?= count($payments) ?></div>
                <div class="stat-label">Invoices</div>
            </div>
            <div class="stat-group">
                <div class="stat-val" style="color: var(--secondary);"><?= $pending_count ?></div>
                <div class="stat-label">In Audit</div>
            </div>
            <div class="stat-group">
                <div class="stat-val" style="color: #10B981;"><?= count(array_filter($payments, fn($p) => $p['status'] === 'verified')) ?></div>
                <div class="stat-label">Verified</div>
            </div>
        </div>
    </div>

    <!-- Payment List -->
    <div style="margin-bottom: 48px;">
        <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-list-ul text-primary"></i> Transaction History
        </h3>
        
        <?php if(!empty($payments)): ?>
            <?php foreach($payments as $p): ?>
            <div class="transaction-card">
                <img src="../<?= $p['course_thumb'] ?: 'assets/images/course-placeholder.jpg' ?>" class="course-mini-thumb" alt="">
                
                <div>
                    <div style="font-weight: 800; color: var(--dark); margin-bottom: 4px;"><?= htmlspecialchars($p['course_title']) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-dim);">Tx ID: #<?= str_pad($p['id'], 6, '0', STR_PAD_LEFT) ?></div>
                </div>

                <div class="hide-md" style="text-align: center;">
                    <div style="font-family: 'Poppins', sans-serif; font-weight: 900; color: var(--dark);">KES <?= number_format($p['amount']) ?></div>
                    <div style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase;">Amount Paid</div>
                </div>

                <div class="hide-md" style="text-align: center;">
                    <div style="font-weight: 700; color: var(--text-muted); font-size: 0.85rem;"><?= date('M j, Y', strtotime($p['created_at'])) ?></div>
                    <div style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase;">Payment Date</div>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <span class="status-pill status-<?= $p['status'] ?>"><?= $p['status'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="ledger-empty">
                <i class="fas fa-receipt" style="font-size: 4rem; color: var(--dark-border); margin-bottom: 24px;"></i>
                <h4 style="font-family: 'Poppins', sans-serif; font-weight: 800; color: var(--dark);">Financial Ledger is Empty</h4>
                <p style="color: var(--text-dim); margin-bottom: 32px;">You haven't made any course purchases yet. Explore our high-impact curriculum today.</p>
                <a href="../courses.php" class="btn btn-primary">Browse Courses</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Secure Notice -->
    <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 20px; padding: 24px; display: flex; align-items: flex-start; gap: 20px;">
        <i class="fas fa-shield-halved" style="font-size: 1.5rem; color: #10B981;"></i>
        <div>
            <h4 style="font-family: 'Poppins', sans-serif; font-weight: 800; color: #10B880; font-size: 0.9rem; margin-bottom: 4px;">Highly Secure Transactions</h4>
            <p style="font-size: 0.82rem; color: var(--text-dim); line-height: 1.5;">All manual payment proofs are reviewed by our financial compliance team within 2-4 hours. Once verified, your course content is unlocked automatically. For support regarding billing, please visit the Help Desk.</p>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
