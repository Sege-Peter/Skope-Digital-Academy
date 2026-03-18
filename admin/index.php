<?php
$pageTitle = 'Executive Administration';
require_once '../includes/header.php';

// Fetch Global Academy Stats
try {
    // 1. Total Revenue
    $total_revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'verified'")->fetchColumn() ?: 0;

    // 2. Pending Verifications
    $pending_payments = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn() ?: 0;

    // 3. Active Users
    $active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn() ?: 0;
    
    // 4. Detailed User Counts
    $student_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn() ?: 0;
    $tutor_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tutor' AND status = 'active'")->fetchColumn() ?: 0;

    // 5. Live Courses
    $live_courses = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn() ?: 0;

    // 6. Recent Users
    $recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

} catch (Exception $e) { $total_revenue = $pending_payments = $active_users = $live_courses = 0; $recent_users = []; }
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .admin-stat-card { background: white; border: 1px solid var(--dark-border); border-radius: 16px; padding: 24px; position: relative; overflow: hidden; }
    .admin-stat-card .accent { position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
    
    .revenue-chart-sim { height: 100px; display: flex; align-items: flex-end; gap: 8px; margin-top: 16px; }
    .chart-bar { flex: 1; background: var(--primary-glow); border-radius: 4px 4px 0 0; transition: 0.3s; }
    .chart-bar:hover { background: var(--primary); }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Systems <span class="text-primary">Executive Control</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Real-time oversight of revenue, academic quality, and platform growth.</p>
            </div>
        </div>
        <div style="display: flex; gap: 16px;">
            <button class="btn btn-ghost btn-sm"><i class="fas fa-file-export"></i> Export Report</button>
            <a href="announcements.php" class="btn btn-primary btn-sm"><i class="fas fa-bullhorn"></i> New Announcement</a>
        </div>
    </header>

    <div class="grid-4" style="gap: 24px; margin-bottom: 48px;">
        <div class="admin-stat-card">
            <div class="accent" style="background: var(--primary);"></div>
            <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px;">Academy Revenue</div>
            <div style="font-family: 'Poppins', sans-serif; font-size: 1.6rem; font-weight: 800;">KES <?= number_format($total_revenue) ?></div>
            <div class="revenue-chart-sim">
                <div class="chart-bar" style="height: 40%;"></div>
                <div class="chart-bar" style="height: 60%;"></div>
                <div class="chart-bar" style="height: 50%;"></div>
                <div class="chart-bar" style="height: 85%;"></div>
                <div class="chart-bar" style="height: 45%;"></div>
                <div class="chart-bar" style="height: 70%;"></div>
            </div>
        </div>
        
        <div class="admin-stat-card">
            <div class="accent" style="background: var(--secondary);"></div>
            <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px;">Pending Audits</div>
            <div style="font-family: 'Poppins', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--secondary);"><?= $pending_payments ?></div>
            <p style="font-size: 0.8rem; color: var(--text-dim); margin-top: 24px;">Unverified payment proofs needing attention.</p>
            <a href="verifications.php" class="btn btn-ghost btn-block btn-sm" style="margin-top: 16px; border-radius: 8px;">Audit Now</a>
        </div>

        <div class="admin-stat-card">
            <div class="accent" style="background: var(--success);"></div>
            <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px;">Academy Population</div>
            <div style="font-family: 'Poppins', sans-serif; font-size: 1.6rem; font-weight: 800;"><?= number_format($active_users) ?></div>
            <div style="margin-top: 32px; font-size: 0.82rem; color: var(--text-muted); display: flex; justify-content: space-between;">
                <span>Students: <?= number_format($student_count) ?></span>
                <span>Tutors: <?= number_format($tutor_count) ?></span>
            </div>
        </div>

        <div class="admin-stat-card" style="cursor: pointer;" onclick="location.href='categories.php'">
            <div class="accent" style="background: var(--info);"></div>
            <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px;">Live Repository</div>
            <div style="font-family: 'Poppins', sans-serif; font-size: 1.6rem; font-weight: 800;"><?= $live_courses ?> Courses</div>
            <p style="font-size: 0.8rem; color: var(--text-dim); margin-top: 24px;">Across Curriculum Categories <i class="fas fa-chevron-right" style="font-size: 0.6rem; margin-left: 4px;"></i></p>
        </div>
    </div>

    <div class="grid-2" style="grid-template-columns: 2fr 1fr; gap: 40px;">
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.25rem;">Stakeholder Intelligence</h3>
                <a href="users.php" class="btn btn-ghost btn-sm">Manage All Users</a>
            </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="admin-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>User Name</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_users as $u): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--dark);"><?= htmlspecialchars($u['name']) ?></td>
                                <td><span class="badge badge-ghost" style="text-transform: uppercase; font-size: 0.65rem; font-weight: 800;"><?= $u['role'] ?></span></td>
                                <td style="font-size: 0.82rem; color: var(--text-muted);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td><span class="status-badge status-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($recent_users)): ?>
                                <tr><td colspan="4" style="text-align: center; padding: 20px;">No recent stakeholders found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside>
            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 0.95rem; margin-bottom: 24px; border-bottom: 2px solid var(--primary-glow); padding-bottom: 12px;">System Health</h3>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">Database Load</span>
                        <span style="font-weight: 700; color: var(--success);">Optimal</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">Media Storage</span>
                        <span style="font-weight: 700;">45% Full</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">Payout Cycle</span>
                        <span style="font-weight: 700; color: var(--secondary);">12 Days Out</span>
                    </div>
                </div>
                <button class="btn btn-ghost btn-block btn-sm" style="margin-top: 32px;">Run System Audit</button>
            </div>
        </aside>
    </div>
</main>

</body>
</html>
