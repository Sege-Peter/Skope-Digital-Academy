<?php
$pageTitle = 'Payments & Revenue';
require_once 'includes/header.php';

$success_msg = '';
$error_msg = '';

// Handle Verification Action
if (isset($_GET['verify_id'])) {
    $verify_id = (int)$_GET['verify_id'];
    try {
        $pdo->beginTransaction();
        
        // 1. Update payment status
        $stmt = $pdo->prepare("UPDATE payments SET status = 'verified', verified_at = NOW(), verified_by = ? WHERE id = ?");
        $stmt->execute([$admin['id'], $verify_id]);
        
        // 2. Fetch student and course info for this payment
        $stmt = $pdo->prepare("SELECT student_id, course_id FROM payments WHERE id = ?");
        $stmt->execute([$verify_id]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            require_once '../includes/gamified_logic.php';
            
            // 3. Create or Update Enrollment
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active'");
            $stmt->execute([$payment['student_id'], $payment['course_id']]);
            
            // 4. Update Course Enrolled Count
            $stmt = $pdo->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?");
            $stmt->execute([$payment['course_id']]);
            
            // 5. Referral Reward (4% of amount)
            $stmt = $pdo->prepare("SELECT amount FROM payments WHERE id = ?");
            $stmt->execute([$verify_id]);
            $amount = $stmt->fetchColumn();
            rewardReferrer($payment['student_id'], $amount, $pdo);
            
            // 6. Send Notification to Student
            $stmt = $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, 'student', ?)");
            $stmt->execute(['Payment Verified!', 'Your payment has been verified. You can now access your course.', $payment['student_id']]);
        }
        
        $pdo->commit();
        $success_msg = "Payment verified successfully and student enrolled.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error verifying payment: " . $e->getMessage();
    }
}

// Fetch all payments with filtering
try {
    $status_filter = $_GET['status'] ?? 'all';
    $where = $status_filter !== 'all' ? "WHERE p.status = ?" : "WHERE 1=1";
    $params = $status_filter !== 'all' ? [$status_filter] : [];

    $stmt = $pdo->prepare("SELECT p.*, u.name as student_name, c.title as course_title, c.price as course_price
                         FROM payments p 
                         JOIN users u ON p.student_id = u.id 
                         JOIN courses c ON p.course_id = c.id 
                         $where
                         ORDER BY p.created_at DESC");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Summary Stats
    $total_collected = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?: 0;
    $pending_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn() ?: 0;
} catch (Exception $e) { $payments = []; $total_collected = 0; $pending_count = 0; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    /* Page-level overflow containment */
    .main-content { overflow-x: hidden; }
    .admin-body { width: 100%; box-sizing: border-box; }

    /* Payments Page Header */
    .payments-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 32px;
        background: rgba(13,17,23,0.8);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid var(--dark-border);
        padding: 0 32px;
        height: auto;
        min-height: 70px;
        padding-top: 12px;
        padding-bottom: 12px;
    }
    .payments-header-left { display: flex; align-items: center; gap: 14px; }
    .payments-header-right { display: flex; gap: 10px; flex-wrap: wrap; }

    /* Stats grid responsive */
    .dash-stats-grid { grid-template-columns: repeat(4, 1fr); }
    @media (max-width: 991px) { .dash-stats-grid { grid-template-columns: repeat(2, 1fr) !important; } }
    @media (max-width: 480px)  { .dash-stats-grid { grid-template-columns: repeat(2, 1fr) !important; } }

    /* Table card + scrollbar */
    .table-card { overflow: hidden; }
    .table-responsive { display: block; overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; padding-bottom: 8px; }
    .table-responsive::-webkit-scrollbar { height: 8px; }
    .table-responsive::-webkit-scrollbar-track { background: #f8fafc; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .table-responsive::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    .admin-table { min-width: 900px; }
    .admin-table td, .admin-table th { white-space: nowrap; }

    /* Table header responsive */
    .table-header { flex-wrap: wrap; gap: 12px; }

    @media (max-width: 768px) {
        .payments-header { padding: 12px 16px; flex-direction: column; align-items: flex-start; }
        .payments-header-right { width: 100%; }
        .payments-header-right .btn { flex: 1; justify-content: center; }
        .admin-body { padding: 16px; }
    }
</style>

<style>
    .main-content { overflow-x: hidden; }
    .admin-body { width: 100%; box-sizing: border-box; padding: 32px; }

    .pay-header {
        display: flex; justify-content: space-between; align-items: center;
        flex-wrap: wrap; gap: 16px; padding: 20px 32px;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-bottom: 1px solid rgba(0,191,255,0.15);
        position: sticky; top: 0; z-index: 100;
    }
    .pay-header-left { display: flex; align-items: center; gap: 14px; }
    .pay-header-title { font-family: 'Poppins', sans-serif; font-size: clamp(1.1rem, 3vw, 1.5rem); font-weight: 800; color: #fff; }
    .pay-header-title span { color: var(--primary); }
    .pay-header-sub { font-size: 0.72rem; color: #94a3b8; margin-top: 3px; }
    .pay-header-right { display: flex; gap: 10px; flex-wrap: wrap; }

    .pay-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; }
    .pay-stat-card { background: white; border: 1px solid #e2e8f0; border-radius: 20px; padding: 24px 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: 0.3s; }
    .pay-stat-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.08); transform: translateY(-2px); }
    .pay-stat-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0; }
    .pay-stat-icon.green  { background: #d1fae5; color: #059669; }
    .pay-stat-icon.orange { background: #fed7aa; color: #ea580c; }
    .pay-stat-icon.blue   { background: #dbeafe; color: #2563eb; }
    .pay-stat-val { font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 900; color: #0f172a; line-height: 1; }
    .pay-stat-lbl { font-size: 0.68rem; color: #64748b; font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

    .pay-table-card { background: white; border: 1px solid #e2e8f0; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .pay-table-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 20px 24px; border-bottom: 1px solid #f1f5f9; background: #fafbfc; }
    .pay-table-header h3 { font-family: 'Poppins', sans-serif; font-size: 1rem; font-weight: 800; color: #0f172a; }
    .pay-table-header select { padding: 6px 12px; border-radius: 10px; border: 1px solid #e2e8f0; font-size: 0.8rem; outline: none; }

    .pay-table-scroll { display: block; overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; padding-bottom: 8px; }
    .pay-table-scroll::-webkit-scrollbar { height: 8px; }
    .pay-table-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
    .pay-table-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .pay-table-scroll::-webkit-scrollbar-thumb:hover { background: var(--primary); }

    .pay-table { width: 100%; border-collapse: collapse; min-width: 900px; }
    .pay-table th { background: #f8fafc; padding: 13px 20px; text-align: left; font-size: 0.65rem; font-weight: 800; color: #64748b; letter-spacing: 0.8px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
    .pay-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; color: #334155; white-space: nowrap; vertical-align: middle; }
    .pay-table tr:last-child td { border-bottom: none; }
    .pay-table tr:hover td { background: #f8faff; }

    .pay-badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 8px; font-size: 0.68rem; font-weight: 800; letter-spacing: 0.5px; }
    .pay-badge.pending  { background: #fef9c3; color: #854d0e; }
    .pay-badge.verified { background: #d1fae5; color: #065f46; }
    .pay-badge.failed   { background: #fee2e2; color: #991b1b; }

    .pay-alert { padding: 14px 20px; border-radius: 14px; font-size: 0.88rem; font-weight: 600; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
    .pay-alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .pay-alert.danger  { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    
    .nav-toggle {
        display: none;
        background: rgba(255,255,255,0.08);
        border: 1px solid rgba(255,255,255,0.15);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 10px;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        cursor: pointer;
        flex-shrink: 0;
    }

    @media (max-width: 1024px) {
        .pay-stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
        .pay-header { padding: 16px 20px; }
        .admin-body { padding: 20px; }
        .nav-toggle { display: flex !important; }
    }
    @media (max-width: 600px) {
        .pay-stats-grid { grid-template-columns: repeat(2, 1fr) !important; }
        .pay-header { flex-direction: column; align-items: flex-start; }
        .pay-header-right { width: 100%; }
        .pay-header-right .btn { flex: 1; justify-content: center; }
        .admin-body { padding: 16px; }
    }
</style>

<main class="main-content">
    <header class="pay-header">
        <div class="pay-header-left">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="pay-header-title">Revenue <span>&amp; Payments</span></div>
                <div class="pay-header-sub"><i class="fas fa-circle" style="color: #10b981; font-size: 0.45rem; margin-right: 5px; vertical-align: middle;"></i>Live transaction audit &amp; verification control</div>
            </div>
        </div>
        <div class="pay-header-right">
            <button class="btn btn-ghost btn-sm" style="border-color: rgba(255,255,255,0.2); color: #e2e8f0; background: rgba(255,255,255,0.06);"><i class="fas fa-file-export"></i> Export CSV</button>
            <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
        </div>
    </header>

    <div class="admin-body">
        <?php if($success_msg): ?>
            <div class="pay-alert success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="pay-alert danger"><i class="fas fa-times-circle"></i> <?= $error_msg ?></div>
        <?php endif; ?>

        <div class="pay-stats-grid">
            <div class="pay-stat-card">
                <div class="pay-stat-icon green"><i class="fas fa-vault"></i></div>
                <div><div class="pay-stat-val">KES <?= number_format($total_collected) ?></div><div class="pay-stat-lbl">Total Revenue</div></div>
            </div>
            <div class="pay-stat-card">
                <div class="pay-stat-icon orange"><i class="fas fa-clock"></i></div>
                <div><div class="pay-stat-val"><?= $pending_count ?></div><div class="pay-stat-lbl">Pending Verifications</div></div>
            </div>
            <div class="pay-stat-card">
                <div class="pay-stat-icon blue"><i class="fas fa-calendar-check"></i></div>
                <div><div class="pay-stat-val">100%</div><div class="pay-stat-lbl">Verification Rate</div></div>
            </div>
            <div class="pay-stat-card">
                <div class="pay-stat-icon blue"><i class="fas fa-users-viewfinder"></i></div>
                <div><div class="pay-stat-val"><?= count($payments) ?></div><div class="pay-stat-lbl">Total Transactions</div></div>
            </div>
        </div>

        <!-- ── Print-Only Fiscal Ledger Audit ── -->
        <div class="print-only" style="padding: 50px; font-family: 'Inter', sans-serif;">
            <div style="text-align: center; border-bottom: 3px double #0f172a; padding-bottom: 32px; margin-bottom: 48px;">
                <img src="../assets/images/Skope Digital  logo.png" style="height: 70px; margin-bottom: 20px; filter: grayscale(1);">
                <h1 style="font-size: 2.2rem; font-weight: 900; margin: 0; color: #0f172a; text-transform: uppercase;">Official Fiscal Ledger Audit</h1>
                <p style="font-size: 0.85rem; color: #94a3b8; letter-spacing: 4px; text-transform: uppercase; margin-top: 8px;">Treasury Archives • Skope Digital Academy</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 48px;">
                <div style="border: 1px solid #e2e8f0; padding: 24px; border-radius: 4px;">
                    <div style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; margin-bottom: 8px;">Total Verified Revenue</div>
                    <div style="font-size: 1.8rem; font-weight: 900; color: #10B981;">KES <?= number_format($total_collected) ?></div>
                </div>
                <div style="border: 1px solid #e2e8f0; padding: 24px; border-radius: 4px; background: #f8fafc;">
                    <div style="font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; margin-bottom: 8px;">Audit Status</div>
                    <div style="font-size: 1.2rem; font-weight: 900; color: #0f172a;">Fiscal Year 2026 Registry</div>
                    <div style="font-size: 0.8rem; color: #475569; margin-top: 4px;">Registry ID: SDA-PY-<?= date('Ymd') ?></div>
                </div>
            </div>

            <h3 style="font-size: 1rem; text-transform: uppercase; letter-spacing: 2.5px; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 24px;">Transaction History Registry</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 48px; font-size: 0.8rem;">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid #cbd5e1;">Transaction Ref</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid #cbd5e1;">Scholar Name</th>
                        <th style="text-align: left; padding: 12px; border-bottom: 1px solid #cbd5e1;">Curriculum Track</th>
                        <th style="text-align: right; padding: 12px; border-bottom: 1px solid #cbd5e1;">Gross Amount</th>
                        <th style="text-align: center; padding: 12px; border-bottom: 1px solid #cbd5e1;">Status</th>
                        <th style="text-align: right; padding: 12px; border-bottom: 1px solid #cbd5e1;">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payments as $p): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; font-family: monospace; font-size: 0.75rem; font-weight: 700;"><?= $p['transaction_ref'] ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: 700;"><?= htmlspecialchars($p['student_name']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0;"><?= htmlspecialchars($p['course_title']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: 800; color: #1e293b;">KES <?= number_format($p['amount']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: center;">
                            <span style="font-size: 0.65rem; font-weight: 900; color: <?= $p['status'] === 'verified' ? '#166534' : '#92400e' ?>;">
                                <?= strtoupper($p['status']) ?>
                            </span>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: right; color: #94a3b8;"><?= date('M j, Y H:i', strtotime($p['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 80px; margin-top: 100px; text-align: center;">
                <div>
                    <div style="border-top: 2px solid #0f172a; padding-top: 12px; font-size: 0.95rem; font-weight: 900; font-family: 'Poppins', sans-serif;">Finance Comptroller</div>
                    <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;">Institutional Treasury</div>
                </div>
                <div>
                    <div style="border-top: 2px solid #0f172a; padding-top: 12px; font-size: 0.95rem; font-weight: 900; font-family: 'Poppins', sans-serif;">Audit Board Seal</div>
                    <div style="font-size: 0.7rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;">Verified Fiscal Registry</div>
                </div>
            </div>

            <div style="text-align: center; margin-top: 80px; font-size: 0.7rem; color: #b4becb;">
                This ledger is for institutional auditing purposes. All transaction state changes are logged. <br>
                Audit System ID: SDA-FIS-<?= strtoupper(substr(md5(count($payments)), 0, 10)) ?> • Generated <?= date('F j, Y \a\t g:i a') ?>.
            </div>
        </div>

        <div class="pay-table-card">
            <div class="pay-table-header">
                <h3><i class="fas fa-receipt" style="color: var(--primary); margin-right: 8px;"></i>Transaction History</h3>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Filter Status:</span>
                    <select onchange="location.href='?status=' + this.value">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Ecosystem Records</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending Audits</option>
                        <option value="verified" <?= $status_filter == 'verified' ? 'selected' : '' ?>>Verified Payments</option>
                        <option value="failed" <?= $status_filter == 'failed' ? 'selected' : '' ?>>Failed Attempts</option>
                    </select>
                </div>
            </div>
            <div class="pay-table-scroll">
                <table class="pay-table">
                    <thead>
                        <tr>
                            <th>Student</th><th>Course</th><th>Amount</th>
                            <th>Proof</th><th>Status</th><th>Date</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:36px;height:36px;border-radius:50%;background:var(--primary-glow);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.85rem;flex-shrink:0;"><?= strtoupper(substr($p['student_name'],0,1)) ?></div>
                                    <div>
                                        <div style="font-weight:700;color:#0f172a;"><?= htmlspecialchars($p['student_name']) ?></div>
                                        <div style="font-size:0.7rem;color:#94a3b8;">TXN #P-<?= $p['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($p['course_title']) ?></td>
                            <td><span style="font-weight:800;color:#0f172a;font-family:'Poppins',sans-serif;">KES <?= number_format($p['amount']) ?></span></td>
                            <td>
                                <?php if($p['proof_file']): ?>
                                    <a href="../uploads/proofs/<?= $p['proof_file'] ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-size:0.72rem;border-radius:8px;"><i class="fas fa-file-image"></i> View</a>
                                <?php else: ?>
                                    <span style="font-size:0.72rem;color:#94a3b8;font-style:italic;">No proof</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="pay-badge <?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                            <td style="color:#64748b;font-size:0.8rem;"><?= date('M j, Y · g:i a', strtotime($p['created_at'])) ?></td>
                            <td>
                                <?php if($p['status'] === 'pending'): ?>
                                    <a href="?verify_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" style="border-radius:8px;font-size:0.75rem;" onclick="return confirm('Verify this payment and enroll the student?')"><i class="fas fa-check"></i> Verify</a>
                                <?php else: ?>
                                    <span style="display:inline-flex;align-items:center;gap:5px;font-size:0.75rem;color:#059669;font-weight:700;"><i class="fas fa-check-circle"></i> Done</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($payments)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:60px;color:#94a3b8;"><i class="fas fa-receipt" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:12px;"></i>No transactions recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
