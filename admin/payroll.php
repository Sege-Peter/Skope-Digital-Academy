<?php
$pageTitle = 'Payroll & Tutor Remuneration';
require_once 'includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Fetch Tutor Payroll Data
try {
    $stmt = $pdo->query("SELECT 
                            u.id, 
                            u.name, 
                            u.email,
                            COUNT(DISTINCT c.id) as total_courses,
                            COUNT(p.id) as total_enrollments,
                            SUM(p.amount) as gross_revenue,
                            SUM(p.amount * 0.8) as tutor_net,
                            SUM(p.amount * 0.2) as academy_share
                         FROM users u
                         LEFT JOIN courses c ON u.id = c.tutor_id
                         LEFT JOIN payments p ON c.id = p.course_id AND p.status = 'verified'
                         WHERE u.role = 'tutor'
                         GROUP BY u.id
                         ORDER BY gross_revenue DESC");
    $payroll = $stmt->fetchAll();

    // Global Stats
    $total_gross = array_sum(array_column($payroll, 'gross_revenue'));
    $total_payout = array_sum(array_column($payroll, 'tutor_net'));
    $total_academy = array_sum(array_column($payroll, 'academy_share'));

} catch (Exception $e) {
    $payroll = [];
    $total_gross = $total_payout = $total_academy = 0;
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
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Payroll <span>Command</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Faculty Settlement Matrix • Skope Digital Academy</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 16px;">
            <button class="btn-premium" onclick="window.print()">
                <i class="fas fa-file-invoice-dollar"></i> Generate Payroll Statement
            </button>
        </div>
    </header>

    <div class="admin-body">
        <div class="dash-stats-grid">
            <div class="premium-card">
                <div class="stat-icon" style="background: var(--primary-glow); color: var(--primary);"><i class="fas fa-hand-holding-dollar"></i></div>
                <div class="stat-value">KES <?= number_format($total_gross) ?></div>
                <div class="stat-label">INSTITUTIONAL GROSS REVENUE</div>
            </div>
            <div class="premium-card">
                <div class="stat-icon" style="background: var(--success-glow); color: var(--success);"><i class="fas fa-user-check"></i></div>
                <div class="stat-value">KES <?= number_format($total_payout) ?></div>
                <div class="stat-label">FACULTY SETTLEMENTS (80%)</div>
            </div>
            <div class="premium-card">
                <div class="stat-icon" style="background: var(--warning-glow); color: var(--warning);"><i class="fas fa-building-columns"></i></div>
                <div class="stat-value">KES <?= number_format($total_academy) ?></div>
                <div class="stat-label">ACADEMY RETENTION (20%)</div>
            </div>
        </div>

        <div class="premium-card" style="padding: 0; overflow: hidden;">
            <div style="padding: 24px 32px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px;">Faculty Remuneration Registry</h3>
                    <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-top: 4px;">Comprehensive audit of scholarly compensation and fiscal contributions.</p>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>FACULTY MEMBER</th>
                            <th>CURRICULUM</th>
                            <th>ENROLLMENTS</th>
                            <th>GROSS GENERATED</th>
                            <th>FACULTY NET (80%)</th>
                            <th>ACADEMY SHARE (20%)</th>
                            <th style="text-align: right;">AUDIT</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payroll as $p): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div class="avatar-sm" style="width: 40px; height: 40px; background: var(--bg-main); border-color: var(--border); color: var(--text-dim); font-size: 0.9rem;">
                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem;"><?= htmlspecialchars($p['name']) ?></div>
                                        <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($p['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 800; color: var(--text-main);"><?= $p['total_courses'] ?> Tracks</div>
                                <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Published CONTENT</div>
                            </td>
                            <td>
                                <div style="font-weight: 800; color: var(--text-main);"><?= number_format($p['total_enrollments']) ?></div>
                                <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">VERIFIED ALUMNI</div>
                            </td>
                            <td>
                                <div class="badge-premium" style="background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border-light); font-weight: 800;">
                                    KES <?= number_format($p['gross_revenue'] ?: 0) ?>
                                </div>
                            </td>
                            <td>
                                <div class="badge-premium" style="background: var(--success-glow); color: var(--success); border: none; font-weight: 900;">
                                    KES <?= number_format($p['tutor_net'] ?: 0) ?>
                                </div>
                            </td>
                            <td>
                                <div class="badge-premium" style="background: var(--warning-glow); color: var(--warning); border: none; font-weight: 900;">
                                    KES <?= number_format($p['academy_share'] ?: 0) ?>
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <button onclick="SDA.showToast('Generating granular settlement audit for <?= addslashes($p['name']) ?>...', 'info')" class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border-color: var(--border); color: var(--text-dim); cursor: pointer;" title="Audit Ledger">
                                    <i class="fas fa-file-invoice" style="font-size: 0.75rem;"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($payroll)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 60px; color: var(--text-muted);">
                                    <div style="width: 64px; height: 64px; background: var(--bg-main); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.5rem; border: 1.5px solid var(--border-light);">
                                        <i class="fas fa-users-slash"></i>
                                    </div>
                                    <h4 style="font-size: 1.1rem; font-weight: 900; color: var(--text-main);">No Faculty Data</h4>
                                    <p style="font-size: 0.85rem; font-weight: 600;">The institutional payroll registry is currently empty.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
