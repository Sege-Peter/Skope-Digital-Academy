<?php
$pageTitle = 'Executive Command Center';
require_once 'includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Fetch Global Academy Stats
try {
    // 1. Institutional Revenue
    $total_revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?: 0;

    // 2. Pending Verifications (Critical Audit)
    $pending_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn() ?: 0;

    // 3. Active Scholar Population
    $student_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn() ?: 0;
    $tutor_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tutor' AND status = 'active'")->fetchColumn() ?: 0;
    $total_population = $student_count + $tutor_count;
    
    // 4. Live Curriculum Repository
    $live_courses = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn() ?: 0;

    // 5. Scholarship Allocation
    $active_scholarships = $pdo->query("SELECT COUNT(*) FROM scholarships WHERE expiry_date >= CURDATE() OR expiry_date IS NULL")->fetchColumn() ?: 0;

    // 6. Recent Institutional Growth (Users)
    $recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // 7. Recent Revenue Stream (Last 5)
    $recent_revenue = $pdo->query("SELECT p.*, u.name as student_name 
                                  FROM payments p 
                                  JOIN users u ON p.student_id = u.id 
                                  WHERE p.status = 'verified' 
                                  ORDER BY p.verified_at DESC LIMIT 5")->fetchAll();

} catch (Exception $e) { 
    $total_revenue = $pending_payments = $total_population = $live_courses = $active_scholarships = 0; 
    $recent_users = $recent_revenue = []; 
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .admin-main-grid {
        display: grid;
        grid-template-columns: 1.8fr 1fr;
        gap: 32px;
        margin-top: 32px;
    }
    .admin-header-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    .admin-header-actions {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }
    @media (max-width: 1024px) {
        .admin-main-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .admin-header-wrapper {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<main class="main-content no-print">
    <header class="admin-header admin-header-wrapper">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Executive <span>Dashboard</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Institutional Command • Skope Digital Academy</p>
            </div>
        </div>
        
        <div class="admin-header-actions">
            <button class="btn-premium" style="background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none;" onclick="window.print()">
                <i class="fas fa-file-invoice"></i> Generate Intelligence Report
            </button>
            <a href="announcements.php" class="btn-premium" style="text-decoration: none;">
                <i class="fas fa-broadcast-tower"></i> New Broadcast
            </a>
        </div>
    </header>

    <div class="admin-body">
        <!-- Strategic Metrics Grid -->
        <div class="dash-stats-grid">
            <div class="premium-card" style="border-bottom: 4px solid var(--primary);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div class="stat-icon" style="background: var(--primary-glow); color: var(--primary);"><i class="fas fa-vault"></i></div>
                    <div class="badge-premium" style="background: var(--success-glow); color: var(--success); border: none;">+8.4% ↑</div>
                </div>
                <div class="stat-label">TOTAL INSTITUTIONAL REVENUE</div>
                <div class="stat-value" style="font-size: 1.8rem;">KES <?= number_format($total_revenue) ?></div>
            </div>

            <div class="premium-card" style="border-bottom: 4px solid var(--warning);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div class="stat-icon" style="background: var(--warning-glow); color: var(--warning);"><i class="fas fa-shield-check"></i></div>
                    <a href="verifications.php" class="avatar-sm" style="width: 32px; height: 32px; background: var(--bg-main); border: none; color: var(--warning); cursor: pointer;"><i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="stat-label">PENDING FISCAL AUDITS</div>
                <div class="stat-value" style="font-size: 1.8rem; color: var(--warning);"><?= $pending_payments ?></div>
            </div>

            <div class="premium-card" style="border-bottom: 4px solid var(--accent);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div class="stat-icon" style="background: var(--accent-glow); color: var(--accent);"><i class="fas fa-users-crown"></i></div>
                </div>
                <div class="stat-label">ACADEMY POPULATION</div>
                <div class="stat-value" style="font-size: 1.8rem;"><?= number_format($total_population) ?></div>
                <div style="margin-top: 12px; display: flex; gap: 8px;">
                    <div class="badge-premium" style="font-size: 0.6rem;"><?= $student_count ?> Scholars</div>
                    <div class="badge-premium" style="font-size: 0.6rem;"><?= $tutor_count ?> Faculty</div>
                </div>
            </div>

            <div class="premium-card" style="border-bottom: 4px solid var(--success);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div class="stat-icon" style="background: var(--success-glow); color: var(--success);"><i class="fas fa-graduation-cap"></i></div>
                </div>
                <div class="stat-label">ACTIVE FUNDING TRACKS</div>
                <div class="stat-value" style="font-size: 1.8rem;"><?= $active_scholarships ?></div>
                <div style="margin-top: 12px; font-size: 0.7rem; color: var(--text-muted); font-weight: 700;">LIVE SCHOLARSHIP SCHEMES</div>
            </div>
        </div>

        <div class="admin-main-grid">
            <!-- Stakeholder Intelligence -->
            <div class="premium-card" style="padding: 0; overflow: hidden;">
                <div style="padding: 24px 32px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px;">Stakeholder Intelligence</h3>
                        <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 4px;">Real-time monitoring of institutional onboarding.</p>
                    </div>
                    <a href="users.php" class="badge-premium" style="background: var(--bg-main); color: var(--primary); border: 1.5px solid var(--primary-glow); text-decoration: none;">FULL REGISTRY</a>
                </div>
                <div style="overflow-x: auto;">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>STAKEHOLDER</th>
                                <th>ROLE</th>
                                <th>STATUS</th>
                                <th>ONBOARDING</th>
                                <th style="text-align: right;">AUDIT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_users as $u): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="avatar-sm" style="width: 32px; height: 32px; background: var(--bg-main); border: none; color: var(--text-dim); font-size: 0.8rem; font-weight: 900;">
                                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem;"><?= htmlspecialchars($u['name']) ?></div>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="badge-premium" style="background: var(--bg-main); color: var(--text-muted); font-size: 0.65rem;"><?= strtoupper($u['role']) ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <div style="width: 6px; height: 6px; border-radius: 50%; background: <?= $u['status'] === 'active' ? 'var(--success)' : 'var(--warning)' ?>;"></div>
                                        <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-main); text-transform: uppercase;"><?= $u['status'] ?></span>
                                    </div>
                                </td>
                                <td style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <a href="user-profile.php?id=<?= $u['id'] ?>" class="avatar-sm" style="width: 28px; height: 28px; background: transparent; border-color: var(--border-light); color: var(--text-muted); cursor: pointer;">
                                        <i class="fas fa-chevron-right" style="font-size: 0.6rem;"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Infrastructure & Activity -->
            <div style="display: flex; flex-direction: column; gap: 32px;">
                <div class="premium-card">
                    <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px; margin-bottom: 24px;">Platform Pulse</h3>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--bg-main); border-radius: 12px; border: 1.5px solid var(--border-light);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="avatar-sm" style="width: 32px; height: 32px; background: var(--success-glow); color: var(--success); border: none;"><i class="fas fa-server"></i></div>
                                <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main);">CORE CLUSTER</div>
                            </div>
                            <div class="badge-premium" style="background: var(--success); color: white; border: none; font-size: 0.6rem;">OPERATIONAL</div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--bg-main); border-radius: 12px; border: 1.5px solid var(--border-light);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="avatar-sm" style="width: 32px; height: 32px; background: var(--primary-glow); color: var(--primary); border: none;"><i class="fas fa-database"></i></div>
                                <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main);">NEURAL MESH</div>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 900; color: var(--primary);">LIVE</div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--bg-main); border-radius: 12px; border: 1.5px solid var(--border-light);">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="avatar-sm" style="width: 32px; height: 32px; background: var(--accent-glow); color: var(--accent); border: none;"><i class="fas fa-cloud-upload"></i></div>
                                <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main);">MEDIA SERVER</div>
                            </div>
                            <div style="font-size: 0.8rem; font-weight: 900; color: var(--text-muted);">42% LOAD</div>
                        </div>
                    </div>
                </div>

                <div class="premium-card" style="background: var(--grad-dark); color: white; border: none; padding: 40px; position: relative; overflow: hidden;">
                    <div style="position: absolute; top: -20px; right: -20px; font-size: 8rem; opacity: 0.05; transform: rotate(-15deg);"><i class="fas fa-shield-crown"></i></div>
                    <h3 style="font-size: 1.2rem; font-weight: 900; margin-bottom: 8px; position: relative;">Institutional Registry</h3>
                    <p style="font-size: 0.85rem; color: #94a3b8; line-height: 1.6; margin-bottom: 24px; position: relative;">Generate a high-fidelity performance audit for executive review.</p>
                    <button class="btn-premium" style="width: 100%; justify-content: center; background: white; color: var(--bg-dark); border: none; font-weight: 900;" onclick="window.print()">
                        <i class="fas fa-print"></i> EXPORT OVERSIGHT
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- High-Fidelity Print Artifact -->
<div class="print-only" style="padding: 60px; font-family: 'Inter', sans-serif; color: #000;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #000; padding-bottom: 40px; margin-bottom: 60px;">
        <div>
            <h1 style="font-size: 2.5rem; font-weight: 900; letter-spacing: -1px; margin: 0;">Institutional Performance Registry</h1>
            <p style="font-size: 0.9rem; font-weight: 800; color: #666; text-transform: uppercase; letter-spacing: 2px; margin-top: 8px;">Executive Oversight Protocol • Skope Digital Academy</p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 1.2rem; font-weight: 900; color: #000;">VERIFIED_AUDIT_<?= date('Ymd_His') ?></div>
            <div style="font-size: 0.8rem; font-weight: 700; color: #666; margin-top: 4px;">Timestamp: <?= date('M j, Y H:i:s T') ?></div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; margin-bottom: 80px;">
        <div style="border: 2px solid #000; padding: 32px;">
            <p style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #666; margin-bottom: 12px;">Academy Net Revenue</p>
            <p style="font-size: 2.2rem; font-weight: 900; margin: 0;">KES <?= number_format($total_revenue) ?></p>
        </div>
        <div style="border: 2px solid #000; padding: 32px;">
            <p style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #666; margin-bottom: 12px;">Total Population</p>
            <p style="font-size: 2.2rem; font-weight: 900; margin: 0;"><?= number_format($total_population) ?></p>
        </div>
        <div style="border: 2px solid #000; padding: 32px;">
            <p style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #666; margin-bottom: 12px;">Curriculum Tracks</p>
            <p style="font-size: 2.2rem; font-weight: 900; margin: 0;"><?= $live_courses ?></p>
        </div>
    </div>

    <h2 style="font-size: 1.2rem; font-weight: 900; margin-bottom: 24px; text-transform: uppercase; letter-spacing: 1px;">Stakeholder Intelligence Registry (Recent Onboarding)</h2>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8fafc; border-bottom: 2px solid #000;">
                <th style="text-align: left; padding: 16px; font-size: 0.75rem; font-weight: 900; text-transform: uppercase;">Stakeholder Identity</th>
                <th style="text-align: left; padding: 16px; font-size: 0.75rem; font-weight: 900; text-transform: uppercase;">Institutional Role</th>
                <th style="text-align: left; padding: 16px; font-size: 0.75rem; font-weight: 900; text-transform: uppercase;">Status</th>
                <th style="text-align: right; padding: 16px; font-size: 0.75rem; font-weight: 900; text-transform: uppercase;">Onboarding</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($recent_users as $u): ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 16px; font-weight: 800; font-size: 0.9rem;"><?= htmlspecialchars($u['name']) ?> <span style="font-weight: 400; color: #666; font-size: 0.8rem;">(<?= htmlspecialchars($u['email']) ?>)</span></td>
                <td style="padding: 16px; font-weight: 700; font-size: 0.85rem; text-transform: uppercase;"><?= $u['role'] ?></td>
                <td style="padding: 16px; font-weight: 700; font-size: 0.85rem; text-transform: uppercase;"><?= $u['status'] ?></td>
                <td style="padding: 16px; text-align: right; font-weight: 700; font-size: 0.85rem;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 120px; text-align: center; padding-top: 40px; border-top: 1px solid #eee;">
        <p style="font-size: 0.65rem; font-weight: 700; color: #999; letter-spacing: 1px;">INSTITUTIONAL SEAL • SKOPE DIGITAL ACADEMY EXECUTIVE INTELLIGENCE HUB • END OF REGISTRY</p>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>

