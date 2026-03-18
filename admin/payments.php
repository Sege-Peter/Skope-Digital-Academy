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

// Fetch all payments
try {
    $stmt = $pdo->query("SELECT p.*, u.name as student_name, c.title as course_title, c.price as course_price
                         FROM payments p 
                         JOIN users u ON p.student_id = u.id 
                         JOIN courses c ON p.course_id = c.id 
                         ORDER BY p.created_at DESC");
    $payments = $stmt->fetchAll();
    
    // Summary Stats
    $total_collected = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?: 0;
    $pending_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn() ?: 0;
} catch (Exception $e) { $payments = []; $total_collected = 0; $pending_count = 0; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h1 style="font-size: 1.25rem; font-weight: 700;">Revenue & Payments</h1>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="btn btn-outline btn-sm"><i class="fas fa-file-export"></i> Export CSV</button>
            <button class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print Report</button>
        </div>
    </header>
    
    <div class="admin-body">
        <?php if($success_msg): ?> <div class="alert alert-success"><i class="fas fa-check"></i> <?= $success_msg ?></div> <?php endif; ?>
        <?php if($error_msg): ?> <div class="alert alert-danger"><i class="fas fa-times"></i> <?= $error_msg ?></div> <?php endif; ?>

        <!-- Summary Row -->
        <div class="dash-stats-grid">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-vault"></i></div>
                <div>
                    <div class="stat-value">KES <?= number_format($total_collected) ?></div>
                    <div class="stat-label">Total Verified Revenue</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-value"><?= $pending_count ?></div>
                    <div class="stat-label">Pending Verifications</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
                <div>
                    <div class="stat-value">100%</div>
                    <div class="stat-label">Verification Rate</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users-viewfinder"></i></div>
                <div>
                    <div class="stat-value"><?= count($payments) ?></div>
                    <div class="stat-label">Total Transactions</div>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 style="font-size: 1rem;">Transaction History</h3>
                <div class="flex gap-12" style="align-items: center;">
                    <span style="font-size: 0.82rem; color: var(--text-dim);">Filter by:</span>
                    <select class="form-control" style="width: 140px; padding: 4px 10px; font-size: 0.8rem;">
                        <option>All Status</option>
                        <option>Pending</option>
                        <option>Verified</option>
                        <option>Failed</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Amount</th>
                            <th>Proof</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p): ?>
                        <tr>
                            <td>
                                <div class="user-identity">
                                    <div class="avatar-sm"><?= strtoupper(substr($p['student_name'], 0, 1)) ?></div>
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($p['student_name']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-dim);">ID: #P-<?= $p['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($p['course_title']) ?></td>
                            <td style="font-weight: 700;">KES <?= number_format($p['amount']) ?></td>
                            <td>
                                <?php if($p['proof_file']): ?>
                                    <a href="../uploads/proofs/<?= $p['proof_file'] ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-size: 0.75rem;">
                                        <i class="fas fa-file-image"></i> View Proof
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--text-dim);">No proof attached</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_class = [
                                    'pending' => 'badge-warning',
                                    'verified' => 'badge-success',
                                    'failed' => 'badge-danger'
                                ][$p['status']];
                                ?>
                                <span class="badge <?= $status_class ?>"><?= ucfirst($p['status']) ?></span>
                            </td>
                            <td><?= date('M j, Y, g:i a', strtotime($p['created_at'])) ?></td>
                            <td>
                                <?php if($p['status'] === 'pending'): ?>
                                    <a href="?verify_id=<?= $p['id'] ?>" class="btn btn-primary btn-sm" onclick="return confirm('Verify this payment and enroll the student?')">
                                        Verify
                                    </a>
                                <?php else: ?>
                                    <i class="fas fa-check-circle text-success" title="Verified at <?= $p['verified_at'] ?>"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($payments)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 48px;">No transactions recorded.</td>
                        </tr>
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
