<?php
$pageTitle = 'Identity & User Governance';
require_once 'includes/header.php';

$success_msg = '';
$error_msg = '';

// Handle Status Change
if (isset($_GET['action']) && isset($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
    $action = $_GET['action'];
    $status_map = ['approve' => 'active', 'suspend' => 'suspended', 'activate' => 'active', 'delete' => 'deleted'];
    
    if (isset($status_map[$action])) {
        try {
            $new_status = $status_map[$action];
            if ($new_status === 'deleted') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$uid]);
                $success_msg = "User record permanently purged from academy archives.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $uid]);
                $success_msg = "User status morphed to ".ucfirst($new_status)." successfully.";
            }
        } catch (Exception $e) {
            $error_msg = "Governance Error: " . $e->getMessage();
        }
    }
}

// Fetch all users
try {
    $role_filter = $_GET['role'] ?? 'all';
    $where = $role_filter !== 'all' ? "WHERE role = '$role_filter'" : "WHERE 1=1";
    
    $stmt = $pdo->query("SELECT * FROM users $where ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
    
} catch (Exception $e) { $users = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .user-stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
    .u-stat-card { background: white; border: 1px solid var(--dark-border); border-radius: 16px; padding: 20px; text-align: center; }
    .u-stat-val { font-size: 1.5rem; font-weight: 800; color: var(--dark); display: block; }
    .u-stat-lbl { font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 700; }
    
    .status-badge { padding: 4px 10px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
    .status-active { background: #DCFCE7; color: #166534; }
    .status-pending { background: #FEF9C3; color: #854D0E; }
    .status-suspended { background: #FEE2E2; color: #991B1B; }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Identity <span class="text-primary">Governance</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Universal oversight of all academy stakeholders and academic personas.</p>
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <div style="position: relative;">
                <input type="text" placeholder="Search identities..." id="userSearch" style="padding: 10px 16px 10px 40px; border-radius: 12px; border: 1px solid var(--dark-border); font-size: 0.88rem; outline: none; width: 260px;">
                <i class="fas fa-search" style="position: absolute; left: 16px; top: 13px; color: var(--text-dim);"></i>
            </div>
        </div>
    </header>
    
    <div class="admin-body">
        <div class="user-stat-row">
            <div class="u-stat-card">
                <span class="u-stat-val"><?= count($users) ?></span>
                <span class="u-stat-lbl">Total Stakeholders</span>
            </div>
            <div class="u-stat-card">
                <span class="u-stat-val" style="color: var(--primary);"><?= count(array_filter($users, fn($u) => $u['role'] == 'student')) ?></span>
                <span class="u-stat-lbl">Active Students</span>
            </div>
            <div class="u-stat-card">
                <span class="u-stat-val" style="color: var(--secondary);"><?= count(array_filter($users, fn($u) => $u['role'] == 'tutor')) ?></span>
                <span class="u-stat-lbl">Certified Tutors</span>
            </div>
            <div class="u-stat-card">
                <span class="u-stat-val" style="color: #10B981;"><?= count(array_filter($users, fn($u) => $u['status'] == 'active')) ?></span>
                <span class="u-stat-lbl">Verified Access</span>
            </div>
        </div>

        <div class="filter-pills" style="margin-bottom: 32px;">
            <a href="users.php" class="filter-pill <?= $role_filter == 'all' ? 'active' : '' ?>">All Ecosystem</a>
            <a href="users.php?role=student" class="filter-pill <?= $role_filter == 'student' ? 'active' : '' ?>">Students Only</a>
            <a href="users.php?role=tutor" class="filter-pill <?= $role_filter == 'tutor' ? 'active' : '' ?>">Tutor Faculty</a>
            <a href="users.php?role=admin" class="filter-pill <?= $role_filter == 'admin' ? 'active' : '' ?>">Administration</a>
        </div>

        <div class="table-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Identity Details</th>
                            <th>Credentials</th>
                            <th>Security Status</th>
                            <th>Merrit Points</th>
                            <th>Acquisition Date</th>
                            <th style="text-align: right;">Authorization</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr class="user-row">
                            <td>
                                <div class="user-identity">
                                    <div class="avatar-sm" style="background: <?= $u['role'] == 'tutor' ? 'var(--primary-glow)' : 'var(--secondary-glow)' ?>; color: <?= $u['role'] == 'tutor' ? 'var(--primary)' : 'var(--secondary)' ?>;">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="user-name" style="font-weight: 700; color: var(--dark);"><?= htmlspecialchars($u['name']) ?></div>
                                        <div style="font-size: 0.72rem; color: var(--text-dim);"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $u['role'] == 'tutor' ? 'badge-primary' : ($u['role'] == 'admin' ? 'badge-danger' : 'badge-ghost') ?>" style="font-weight: 800; font-size: 0.65rem;">
                                    <?= strtoupper($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $u['status'] ?>">
                                    <?= $u['status'] ?>
                                </span>
                            </td>
                            <td><i class="fas fa-crown text-secondary" style="font-size: 0.8rem; margin-right: 4px;"></i> <?= number_format($u['points']) ?></td>
                            <td style="font-size: 0.82rem; color: var(--text-dim);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <?php if($u['status'] == 'pending'): ?>
                                        <a href="users.php?action=approve&uid=<?= $u['id'] ?>" class="btn btn-primary btn-sm" style="background: #10B981; border-color: #10B981; font-size: 0.7rem;">Approve</a>
                                    <?php endif; ?>
                                    
                                    <?php if($u['status'] == 'active' && $u['role'] != 'admin'): ?>
                                        <button onclick="SDA.confirmAction('Are you sure you want to suspend this user? They will lose all access immediately.', () => window.location.href='users.php?action=suspend&uid=<?= $u['id'] ?>')" class="btn btn-ghost btn-sm" style="color: var(--danger); font-size: 0.7rem;">Suspend</button>
                                    <?php elseif($u['status'] == 'suspended'): ?>
                                        <a href="users.php?action=activate&uid=<?= $u['id'] ?>" class="btn btn-ghost btn-sm" style="color: #10B981; font-size: 0.7rem;">Activate</a>
                                    <?php endif; ?>
                                    
                                    <a href="user-profile.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm" style="padding: 6px;"><i class="fas fa-eye"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if($success_msg): ?>
            SDA.showToast("<?= $success_msg ?>", "success");
        <?php endif; ?>
        <?php if($error_msg): ?>
            SDA.showToast("<?= $error_msg ?>", "danger");
        <?php endif; ?>

        // Identity Search Filter
        document.getElementById('userSearch').addEventListener('keyup', function() {
            let q = this.value.toLowerCase();
            document.querySelectorAll('.user-row').forEach(row => {
                let name = row.querySelector('.user-name').textContent.toLowerCase();
                row.style.display = name.includes(q) ? '' : 'none';
            });
        });
    });
</script>
</body>
</html>
