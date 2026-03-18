<?php
$pageTitle = 'Revenue Audit & Verifications';
require_once '../includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle Verification Actions
$message = '';
if (isset($_GET['action']) && isset($_GET['id'])) {
    $payment_id = (int)$_GET['id'];
    $action = $_GET['action']; // 'verify' or 'reject'
    
    try {
        if ($action === 'verify') {
            $pdo->beginTransaction();
            
            // 1. Update Payment Status
            $stmt = $pdo->prepare("UPDATE payments SET status = 'verified', verified_by = ?, verified_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id'], $payment_id]);

            // 2. Fetch Payment Details for Enrollment
            $stmt = $pdo->prepare("SELECT student_id, course_id FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $pay = $stmt->fetch();

            if ($pay) {
                // 3. Create/Update Enrollment
                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active') 
                                       ON DUPLICATE KEY UPDATE status = 'active'");
                $stmt->execute([$pay['student_id'], $pay['course_id']]);
                
                // 4. Update Course Enrolled Count
                $stmt = $pdo->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?");
                $stmt->execute([$pay['course_id']]);
            }

            $pdo->commit();
            $message = "Payment ID #$payment_id successfully verified! Access granted.";
        } 
        elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE id = ?");
            $stmt->execute([$payment_id]);
            $message = "Payment ID #$payment_id has been rejected.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

try {
    // 1. Pending Payments (Priority)
    $stmt = $pdo->query("SELECT p.*, u.name as student_name, u.email as student_email, c.title as course_title 
                         FROM payments p 
                         JOIN users u ON p.student_id = u.id 
                         JOIN courses c ON p.course_id = c.id 
                         WHERE p.status = 'pending' 
                         ORDER BY p.created_at DESC");
    $pending = $stmt->fetchAll();

    // 2. Verified History (Recent 10)
    $stmt = $pdo->query("SELECT p.*, u.name as student_name, c.title as course_title, admin.name as admin_name
                         FROM payments p 
                         JOIN users u ON p.student_id = u.id 
                         JOIN courses c ON p.course_id = c.id 
                         LEFT JOIN users admin ON p.verified_by = admin.id
                         WHERE p.status = 'verified' 
                         ORDER BY p.verified_at DESC LIMIT 10");
    $history = $stmt->fetchAll();

    // 3. Stats
    $total_verified = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'verified'")->fetchColumn();
    $total_revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?: 0;

} catch (Exception $e) {
    error_log($e->getMessage());
    $pending = $history = [];
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .audit-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 40px; }
    .audit-stat-card { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 24px; }
    
    .proof-cell { cursor: pointer; color: var(--primary); font-weight: 700; transition: 0.2s; }
    .proof-cell:hover { opacity: 0.7; transform: scale(1.05); }
    
    .status-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
    .status-pending { background: #FEF9C3; color: #854D0E; }
    .status-verified { background: #DCFCE7; color: #166534; }
    
    /* Document Viewer Modal */
    .doc-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    .doc-content { background: white; padding: 20px; border-radius: 24px; max-width: 90%; max-height: 90%; text-align: center; position: relative; }
    .doc-content img { max-width: 100%; max-height: 70vh; border-radius: 12px; margin-bottom: 20px; }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Revenue <span class="text-primary">Audit Trail</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Verify manual payment proofs and unlock student access instantly.</p>
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="btn btn-ghost btn-sm" onclick="window.location.reload()"><i class="fas fa-sync"></i> Refresh Pool</button>
        </div>
    </header>

    <div class="audit-stats">
        <div class="audit-stat-card" style="border-left: 5px solid var(--secondary);">
            <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Awaiting Verification</div>
            <div style="font-family: 'Poppins', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--secondary);"><?= count($pending) ?></div>
        </div>
        <div class="audit-stat-card" style="border-left: 5px solid #10B981;">
            <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Total Verified Enrolls</div>
            <div style="font-family: 'Poppins', sans-serif; font-size: 1.8rem; font-weight: 800;"><?= number_format($total_verified) ?></div>
        </div>
        <div class="audit-stat-card" style="border-left: 5px solid var(--primary);">
            <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Academy Net Revenue</div>
            <div style="font-family: 'Poppins', sans-serif; font-size: 1.8rem; font-weight: 800;">KES <?= number_format($total_revenue) ?></div>
        </div>
    </div>

    <?php if($message): ?>
        <div style="padding: 16px 24px; background: var(--primary-glow); color: var(--primary); border-radius: 12px; margin-bottom: 32px; font-weight: 600; border: 1px solid var(--primary);">
            <i class="fas fa-info-circle"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- Pending Verification Table -->
    <div style="margin-bottom: 48px;">
        <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; margin-bottom: 24px;">📥 Pending Verifications Pool</h3>
        <div class="table-card">
            <table class="admin-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Student Details</th>
                        <th>Target Course</th>
                        <th>Amount</th>
                        <th>Transaction Info</th>
                        <th>Proof Doc</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($pending)): ?>
                        <?php foreach($pending as $p): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--dark);"><?= htmlspecialchars($p['student_name']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-dim);"><?= htmlspecialchars($p['student_email']) ?></div>
                            </td>
                            <td>
                                <div style="font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($p['course_title']) ?></div>
                                <div style="font-size: 0.72rem; color: var(--text-dim);">ID #<?= $p['id'] ?></div>
                            </td>
                            <td style="font-weight: 800; color: var(--primary);">KES <?= number_format($p['amount']) ?></td>
                            <td>
                                <div style="font-size: 0.82rem; color: var(--text-muted); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($p['transaction_message']) ?>">
                                    <?= htmlspecialchars($p['transaction_message'] ?: 'N/A') ?>
                                </div>
                            </td>
                            <td>
                                <div class="proof-cell" onclick="viewProof('<?= $p['proof_file'] ?>', 'KES <?= number_format($p['amount']) ?> from <?= htmlspecialchars($p['student_name']) ?>')">
                                    <i class="fas fa-file-invoice"></i> View Proof
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <a href="verifications.php?action=verify&id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" style="background: #10B981; border-color: #10B981; border-radius: 8px;">Approve</a>
                                    <a href="verifications.php?action=reject&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" style="color: #EF4444; border-radius: 8px;">Reject</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 60px; color: var(--text-dim);">
                                <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 20px; display: block; opacity: 0.2;"></i>
                                Excellent! No pending verifications at this time.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit History -->
    <div>
        <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; margin-bottom: 24px;">📋 Recent Verified Activity</h3>
        <div class="table-card">
            <table class="admin-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Amount</th>
                        <th>Verified By</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($history as $h): ?>
                    <tr>
                        <td><span style="font-weight: 800; color: var(--text-dim);">#<?= $h['id'] ?></span></td>
                        <td style="font-weight: 700;"><?= htmlspecialchars($h['student_name']) ?></td>
                        <td style="font-size: 0.82rem;"><?= htmlspecialchars($h['course_title']) ?></td>
                        <td style="font-weight: 800; color: #10B981;">KES <?= number_format($h['amount']) ?></td>
                        <td>
                            <div style="font-size: 0.8rem; font-weight: 600; color: var(--primary);"><?= htmlspecialchars($h['admin_name'] ?: 'System') ?></div>
                        </td>
                        <td style="font-size: 0.82rem; color: var(--text-dim);"><?= date('M j, g:i a', strtotime($h['verified_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Proof Viewer Modal -->
<div class="doc-modal" id="proofModal">
    <div class="doc-content">
        <button onclick="closeProof()" style="position: absolute; top: -15px; right: -15px; width: 40px; height: 40px; border-radius: 50%; border: none; background: white; color: var(--dark); font-size: 1.2rem; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.2);"><i class="fas fa-times"></i></button>
        <h4 id="proofTitle" style="margin-bottom: 20px; font-family: 'Poppins', sans-serif;">Payment Verification</h4>
        <img id="proofImg" src="" alt="Proof of Payment">
        <div id="proofMeta" style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 20px;"></div>
        <div style="display: flex; gap: 12px;">
            <button class="btn btn-ghost" onclick="closeProof()" style="flex: 1;">Back to Audit</button>
            <button class="btn btn-primary" onclick="window.print()" style="flex: 1;"><i class="fas fa-print"></i> Print Proof</button>
        </div>
    </div>
</div>

<script>
    function viewProof(file, meta) {
        const modal = document.getElementById('proofModal');
        const img = document.getElementById('proofImg');
        const metaDiv = document.getElementById('proofMeta');
        
        img.src = '../uploads/proofs/' + file;
        metaDiv.innerText = meta;
        modal.style.display = 'flex';
    }

    function closeProof() {
        document.getElementById('proofModal').style.display = 'none';
    }

    // Close on backdrop
    window.onclick = function(e) {
        if(e.target == document.getElementById('proofModal')) closeProof();
    }
</script>

</body>
</html>
