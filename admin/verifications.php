<?php
$pageTitle = 'Revenue Audit & Verifications';
require_once 'includes/header.php';

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
            $message = "Institutional clearance granted for Payment ID #$payment_id. Stakeholder access synchronized.";
        } 
        elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE id = ?");
            $stmt->execute([$payment_id]);
            $message = "Fiscal validation failed for Payment ID #$payment_id. Transaction archived as rejected.";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "Audit Error: " . $e->getMessage();
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

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Fiscal <span>Verification</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Revenue Audit Hub • Skope Digital Academy</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 16px;">
            <button class="btn-premium" style="background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none;" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i> Synchronize Pool
            </button>
        </div>
    </header>

    <div class="admin-body">
        <div class="dash-stats-grid">
            <div class="premium-card">
                <div class="stat-icon" style="background: var(--warning-glow); color: var(--warning);"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?= count($pending) ?></div>
                <div class="stat-label">AWAITING CLEARANCE</div>
            </div>
            <div class="premium-card">
                <div class="stat-icon" style="background: var(--success-glow); color: var(--success);"><i class="fas fa-user-check"></i></div>
                <div class="stat-value"><?= number_format($total_verified) ?></div>
                <div class="stat-label">TOTAL VERIFIED ENROLLS</div>
            </div>
            <div class="premium-card">
                <div class="stat-icon" style="background: var(--primary-glow); color: var(--primary);"><i class="fas fa-wallet"></i></div>
                <div class="stat-value">KES <?= number_format($total_revenue) ?></div>
                <div class="stat-label">INSTITUTIONAL NET REVENUE</div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="premium-card glass-effect" style="border-color: var(--primary); margin-bottom: 32px; padding: 20px 24px; color: var(--primary); font-weight: 700; display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-shield-alt"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Pending Verification Pool -->
        <div class="premium-card" style="padding: 0; overflow: hidden; margin-bottom: 48px;">
            <div style="padding: 24px 32px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px;">Priority Verification Pool</h3>
                    <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 4px;">Incoming fiscal declarations requiring administrative validation.</p>
                </div>
                <div class="badge-premium" style="background: var(--warning-glow); color: var(--warning); border: none;">
                    <?= count($pending) ?> ACTIONABLE ITEMS
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>STAKEHOLDER</th>
                            <th>TARGET SCHEME</th>
                            <th>INVESTMENT</th>
                            <th>PROOF ARTIFACT</th>
                            <th style="text-align: right;">GOVERNANCE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($pending)): ?>
                            <?php foreach($pending as $p): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($p['student_name']) ?></div>
                                    <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($p['student_email']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);"><?= htmlspecialchars($p['course_title']) ?></div>
                                    <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">TxID: #<?= $p['id'] ?></div>
                                </td>
                                <td style="font-weight: 900; color: var(--primary);">KES <?= number_format($p['amount']) ?></td>
                                <td>
                                    <button class="badge-premium" style="background: var(--bg-main); color: var(--primary); border: 1.5px solid var(--primary-glow); cursor: pointer; transition: 0.2s;" onmouseover="this.style.background='var(--primary-glow)'" onmouseout="this.style.background='var(--bg-main)'" onclick="viewProof('<?= $p['proof_file'] ?>', 'KES <?= number_format($p['amount']) ?> from <?= htmlspecialchars($p['student_name']) ?>')">
                                        <i class="fas fa-eye" style="margin-right: 6px;"></i> VALIDATE PROOF
                                    </button>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="verifications.php?action=verify&id=<?= $p['id'] ?>" class="avatar-sm" style="width: 32px; height: 32px; background: var(--success-glow); color: var(--success); border: none; cursor: pointer;" title="Authorize Access">
                                            <i class="fas fa-check" style="font-size: 0.8rem;"></i>
                                        </a>
                                        <a href="verifications.php?action=reject&id=<?= $p['id'] ?>" class="avatar-sm" style="width: 32px; height: 32px; background: var(--danger-glow); color: var(--danger); border: none; cursor: pointer;" title="Invalidate Transaction">
                                            <i class="fas fa-times" style="font-size: 0.8rem;"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 80px 40px;">
                                    <div style="width: 64px; height: 64px; background: var(--bg-main); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-muted); margin: 0 auto 20px; font-size: 1.5rem; border: 1.5px solid var(--border-light);">
                                        <i class="fas fa-check-double"></i>
                                    </div>
                                    <h4 style="font-size: 1.1rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.3px;">Audit Queue Empty</h4>
                                    <p style="color: var(--text-muted); font-size: 0.85rem; font-weight: 600; margin-top: 4px;">Institutional revenue stream is fully synchronized.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Audit History -->
        <div class="premium-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 24px 32px; border-bottom: 1px solid var(--border-light);">
                <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px;">Recent Audit Decisions</h3>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 4px;">Historical record of administrative fiscal clearances.</p>
            </div>
            <div style="overflow-x: auto;">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>TRANS. ID</th>
                            <th>STAKEHOLDER</th>
                            <th>SCHEME</th>
                            <th>INVESTMENT</th>
                            <th>AUTHORIZING AGENT</th>
                            <th>TIMESTAMP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $h): ?>
                        <tr>
                            <td><span style="font-weight: 900; color: var(--text-muted); font-size: 0.7rem;">#<?= $h['id'] ?></span></td>
                            <td style="font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($h['student_name']) ?></td>
                            <td style="font-size: 0.82rem; font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($h['course_title']) ?></td>
                            <td style="font-weight: 900; color: var(--success);">KES <?= number_format($h['amount']) ?></td>
                            <td>
                                <div class="badge-premium" style="background: var(--bg-main); color: var(--primary); border: 1.5px solid var(--border-light); font-size: 0.65rem;">
                                    <i class="fas fa-user-shield" style="margin-right: 6px;"></i> <?= htmlspecialchars($h['admin_name'] ?: 'SYSTEM_CORE') ?>
                                </div>
                            </td>
                            <td style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;"><?= date('M j, g:i a', strtotime($h['verified_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- High-Fidelity Proof Viewer -->
<div id="proofModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(12px); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div class="premium-card" style="width: 100%; max-width: 800px; padding: 40px; animation: modal-pop 0.4s cubic-bezier(0.4, 0, 0.2, 1); max-height: 95vh; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border-light);">
            <div>
                <h2 id="proofTitle" style="font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px; margin: 0;">Fiscal Validation</h2>
                <p id="proofMeta" style="color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; font-weight: 600;"></p>
            </div>
            <button onclick="closeProof()" class="avatar-sm" style="width: 40px; height: 40px; background: transparent; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        
        <div style="flex: 1; overflow: auto; background: var(--bg-main); border-radius: 20px; border: 1.5px solid var(--border); margin-bottom: 30px; display: flex; align-items: center; justify-content: center; padding: 20px;">
            <img id="proofImg" src="" alt="Proof Artifact" style="max-width: 100%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
        </div>

        <div style="display: flex; gap: 16px;">
            <button class="btn-premium" style="flex: 1; justify-content: center; height: 52px;" onclick="window.print()"><i class="fas fa-print"></i> Generate Hardcopy</button>
            <button class="btn-premium" onclick="closeProof()" style="flex: 1; background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none; height: 52px; justify-content: center;">Close Audit View</button>
        </div>
    </div>
</div>

<style>
@keyframes modal-pop { 0% { transform: scale(0.9) translateY(20px); opacity: 0; } 100% { transform: scale(1) translateY(0); opacity: 1; } }
</style>

<script src="../assets/js/main.js"></script>
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

    window.onclick = function(e) {
        const modal = document.getElementById('proofModal');
        if(e.target == modal) closeProof();
    }

    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
