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
    
    if (isset($status_map[$action]) || $action === 'make_tutor') {
        try {
            if ($action === 'make_tutor') {
                $stmt = $pdo->prepare("UPDATE users SET role = 'tutor' WHERE id = ?");
                $stmt->execute([$uid]);
                $success_msg = "Student identity successfully upgraded to Faculty/Instructor.";
            } else {
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
            }
        } catch (Exception $e) {
            $error_msg = "Governance Error: " . $e->getMessage();
        }
    }
}

// Handle Faculty Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_faculty'])) {
    $f_name = trim($_POST['f_name']);
    $f_email = trim($_POST['f_email']);
    $f_pass = password_hash('SkopeFaculty123!', PASSWORD_DEFAULT);
    
    try {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$f_email]);
        if ($chk->fetch()) {
            $error_msg = "A professional identity with this email already exists.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, requires_password_change) VALUES (?, ?, ?, 'tutor', 'active', 1)");
            $stmt->execute([$f_name, $f_email, $f_pass]);
            $success_msg = "Faculty identity authorized! Initial password for $f_email is <strong>SkopeFaculty123!</strong>";
        }
    } catch (Exception $e) {
        $error_msg = "Database Exception: " . $e->getMessage();
    }
}

// Fetch all users with secure filtering
try {
    $role_filter = $_GET['role'] ?? 'all';
    $status_filter = $_GET['status'] ?? 'all';
    $search = trim($_GET['search'] ?? '');
    
    $where_clauses = ["1=1"];
    $params = [];
    
    if ($role_filter !== 'all') {
        $where_clauses[] = "role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter !== 'all') {
        $where_clauses[] = "status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where_clauses[] = "(name LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_sql = implode(" AND ", $where_clauses);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE $where_sql ORDER BY created_at DESC");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    $total_students = count(array_filter($users, fn($u) => $u['role'] == 'student'));
    $total_tutors = count(array_filter($users, fn($u) => $u['role'] == 'tutor'));
    $total_active = count(array_filter($users, fn($u) => $u['status'] == 'active'));

} catch (Exception $e) { $users = []; $total_students = $total_tutors = $total_active = 0; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Identity <span>Governance</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Stakeholder Registry • Skope Digital Academy</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 16px;">
            <form action="users.php" method="GET" class="header-search" style="display: flex; gap: 10px;">
                <input type="hidden" name="role" value="<?= htmlspecialchars($role_filter) ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="text" name="search" placeholder="Search Identity..." value="<?= htmlspecialchars($search) ?>" style="padding-right: 48px;">
                <i class="fas fa-search"></i>
            </form>
            <button class="btn-premium" onclick="document.getElementById('facultyModal').style.display='flex'">
                <i class="fas fa-plus-circle"></i> Create Faculty ID
            </button>
        </div>
    </header>

    <div class="admin-body">
        <!-- Governance Stats -->
        <div class="dash-stats-grid">
            <div class="premium-card">
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Total Ecosystem</div>
                <div style="font-size: 2.2rem; font-weight: 900; letter-spacing: -1px;"><?= count($users) ?></div>
                <div class="badge-premium" style="background: rgba(16, 185, 129, 0.1); color: var(--success); margin-top: 12px; display: inline-flex;">Registry Connected</div>
            </div>
            <div class="premium-card" style="border-left: 4px solid var(--primary);">
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Active Scholars</div>
                <div style="font-size: 2.2rem; font-weight: 900; letter-spacing: -1px; color: var(--primary);"><?= $total_students ?></div>
            </div>
            <div class="premium-card" style="border-left: 4px solid var(--secondary);">
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Faculty Registry</div>
                <div style="font-size: 2.2rem; font-weight: 900; letter-spacing: -1px; color: var(--secondary);"><?= $total_tutors ?></div>
            </div>
            <div class="premium-card">
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Verified Access</div>
                <div style="font-size: 2.2rem; font-weight: 900; letter-spacing: -1px;"><?= $total_active ?></div>
            </div>
        </div>

        <!-- Filtering Registry -->
        <div class="premium-card glass-effect" style="padding: 20px; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-right: 12px;">Governance Tier:</span>
                <a href="users.php?role=all&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $role_filter == 'all' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $role_filter == 'all' ? 'white' : '#64748b' ?>;">ALL</a>
                <a href="users.php?role=student&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $role_filter == 'student' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $role_filter == 'student' ? 'white' : '#64748b' ?>;">SCHOLARS</a>
                <a href="users.php?role=tutor&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $role_filter == 'tutor' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $role_filter == 'tutor' ? 'white' : '#64748b' ?>;">FACULTY</a>
            </div>
            
            <div style="display: flex; gap: 16px; align-items: center;">
                <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Security Status:</span>
                <select onchange="location.href='users.php?role=<?= $role_filter ?>&search=<?= urlencode($search) ?>&status=' + this.value" style="padding: 10px 16px; border-radius: 12px; border: 1.5px solid var(--border); font-size: 0.85rem; font-weight: 700; outline: none; background: #fff; cursor: pointer;">
                    <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Statuses</option>
                    <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active Access</option>
                    <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending Review</option>
                    <option value="suspended" <?= $status_filter == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
        </div>

        <div class="table-card">
            <div class="table-header">
                <h2>Identity <span>Registry</span></h2>
                <span class="badge-premium" style="background: var(--bg-main); color: var(--text-dim);"><?= count($users) ?> Entries Found</span>
            </div>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Stakeholder Identity</th>
                            <th>Credential</th>
                            <th>SEC Status</th>
                            <th>Merit</th>
                            <th>Registry Date</th>
                            <th style="text-align: right;">Governance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td>
                                <div class="user-identity">
                                    <div class="avatar-sm" style="--id-bg: <?= $u['role'] == 'tutor' ? 'var(--primary-glow)' : ($u['role'] == 'admin' ? '#fee2e2' : '#f1f5f9') ?>; --id-color: <?= $u['role'] == 'tutor' ? 'var(--primary)' : ($u['role'] == 'admin' ? '#dc2626' : '#64748b') ?>;">
                                        <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($u['name']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge-premium" style="background: <?= $u['role'] == 'admin' ? 'rgba(239, 68, 68, 0.1)' : ($u['role'] == 'tutor' ? 'rgba(0, 174, 239, 0.1)' : '#f1f5f9') ?>; color: <?= $u['role'] == 'admin' ? 'var(--danger)' : ($u['role'] == 'tutor' ? 'var(--primary)' : '#64748b') ?>;">
                                    <?= strtoupper($u['role']) ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.5px; color: <?= $u['status'] === 'active' ? 'var(--success)' : ($u['status'] === 'pending' ? 'var(--warning)' : 'var(--danger)') ?>;">
                                    <span style="width: 8px; height: 8px; border-radius: 50%; background: currentColor; box-shadow: 0 0 0 4px rgba(0,0,0,0.03);"></span>
                                    <?= strtoupper($u['status']) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 800; color: var(--secondary); font-size: 0.85rem;"><i class="fas fa-crown" style="margin-right: 4px; font-size: 0.7rem;"></i> <?= number_format($u['points'] ?? 0) ?></div>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <?php if($u['status'] == 'pending'): ?>
                                        <a href="users.php?action=approve&uid=<?= $u['id'] ?>" class="avatar-sm" style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.1); color: var(--success); border: none; text-decoration: none;" title="Approve"><i class="fas fa-check" style="font-size: 0.8rem;"></i></a>
                                    <?php endif; ?>
                                    
                                    <?php if($u['role'] == 'student'): ?>
                                        <button onclick="SDA.confirmAction('Upgrade this scholar to Faculty status?', () => window.location.href='users.php?action=make_tutor&uid=<?= $u['id'] ?>')" class="avatar-sm" style="width: 32px; height: 32px; background: rgba(0, 174, 239, 0.1); color: var(--primary); border: none; cursor: pointer;" title="Promote"><i class="fas fa-chalkboard-teacher" style="font-size: 0.8rem;"></i></button>
                                    <?php endif; ?>
                                    
                                    <a href="user-profile.php?id=<?= $u['id'] ?>" class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border-color: var(--border); color: var(--text-dim); text-decoration: none;" title="View"><i class="fas fa-user-shield" style="font-size: 0.8rem;"></i></a>
                                    
                                    <?php if($u['role'] != 'admin'): ?>
                                        <button onclick="SDA.confirmAction('Permanently purge this record?', () => window.location.href='users.php?action=delete&uid=<?= $u['id'] ?>')" class="avatar-sm" style="width: 32px; height: 32px; background: rgba(239, 68, 68, 0.05); color: var(--danger); border: none; cursor: pointer;" title="Purge"><i class="fas fa-trash-alt" style="font-size: 0.8rem;"></i></button>
                                    <?php endif; ?>
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

<!-- Faculty Modal -->
<div id="facultyModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.8); backdrop-filter:blur(12px); z-index:2000; align-items:center; justify-content:center; padding: 20px;">
    <div class="premium-card" style="width:100%; max-width:480px; padding:40px; animation: modal-pop 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;padding-bottom:16px;border-bottom:1px solid var(--border-light);">
            <h3 style="font-size:1.3rem;font-weight:900;letter-spacing:-0.5px;margin:0;">Faculty Registration</h3>
            <button onclick="document.getElementById('facultyModal').style.display='none'" class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="create_faculty" value="1">
            <div style="margin-bottom:24px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Full Legal Name</label>
                <input type="text" name="f_name" required style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:24px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Institutional Email</label>
                <input type="email" name="f_email" required style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;box-sizing:border-box;">
            </div>
            <div style="margin-bottom:32px; background: var(--bg-main); border: 1px dashed var(--primary); padding: 20px; border-radius: 16px; text-align: center;">
                <div style="font-size: 0.75rem; color: var(--primary); font-weight: 800; margin-bottom: 8px;"><i class="fas fa-lock"></i> Temporary Access Pass</div>
                <div style="font-family: monospace; font-size: 1rem; font-weight: 900; color: var(--text-main);">SkopeFaculty123!</div>
                <p style="font-size: 0.65rem; color: var(--text-dim); margin-top: 10px; font-weight: 600;">System enforcement will require immediate pass-change.</p>
            </div>
            <div style="display:flex;gap:16px;">
                <button type="submit" class="btn-premium" style="flex:1; justify-content: center; height: 52px;">Authorize Identity</button>
                <button type="button" class="btn-premium" onclick="document.getElementById('facultyModal').style.display='none'" style="background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none; height: 52px; justify-content: center;">Abort</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes modal-pop { 0% { transform: scale(0.9) translateY(20px); opacity: 0; } 100% { transform: scale(1) translateY(0); opacity: 1; } }
</style>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if($success_msg): ?>
            SDA.showToast("<?= $success_msg ?>", "success");
        <?php endif; ?>
        <?php if($error_msg): ?>
            SDA.showToast("<?= $error_msg ?>", "danger");
        <?php endif; ?>
    });

    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
