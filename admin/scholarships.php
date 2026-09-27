<?php
$pageTitle = 'Manage Scholarships';
require_once 'includes/header.php';

$success_msg = '';
$error_msg = '';

// 1. Handle Application Decision (Approve/Reject)
if (isset($_GET['action']) && isset($_GET['aid'])) {
    $aid = (int)$_GET['aid'];
    $action = $_GET['action'];
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    
    try {
        $stmt = $pdo->prepare("UPDATE scholarship_applications SET status = ? WHERE id = ?");
        $stmt->execute([$status, $aid]);
        
        // Notify student
        $stmt = $pdo->prepare("SELECT user_id, scholarship_id FROM scholarship_applications WHERE id = ?");
        $stmt->execute([$aid]);
        $app = $stmt->fetch();
        if ($app) {
            $stmt = $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, 'student', ?)");
            $stmt->execute(["Scholarship Decision", "Your application for the scholarship has been ".strtoupper($status).". Check your dashboard for details.", $app['user_id']]);
        }
        
        $success_msg = "Application ".ucfirst($status)." successfully.";
    } catch (Exception $e) { $error_msg = "Error: " . $e->getMessage(); }
}

// 2. Handle Add/Edit Scholarship
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scholarship'])) {
    $sid = $_POST['scholarship_id'] ?? null;
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $amount = (float)$_POST['amount'];
    $expiry = $_POST['expiry_date'] ?: null;

    try {
        if ($sid) {
            $stmt = $pdo->prepare("UPDATE scholarships SET title = ?, description = ?, amount = ?, expiry_date = ? WHERE id = ?");
            $stmt->execute([$title, $desc, $amount, $expiry, $sid]);
            $success_msg = "Scholarship updated successfully.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO scholarships (title, description, amount, expiry_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $amount, $expiry]);
            $success_msg = "New scholarship posted.";
        }
    } catch (Exception $e) { $error_msg = $e->getMessage(); }
}

// 3. Handle Delete Scholarship
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['sid'])) {
    $sid = (int)$_GET['sid'];
    try {
        $stmt = $pdo->prepare("DELETE FROM scholarships WHERE id = ?");
        $stmt->execute([$sid]);
        $success_msg = "Scholarship program deleted.";
    } catch (Exception $e) { $error_msg = "Cannot delete: This scholarship may have active applications."; }
}

// 4. Fetch Data with Filtering
try {
    $scholarships_list = $pdo->query("SELECT * FROM scholarships ORDER BY created_at DESC")->fetchAll();
    
    $status_filter = $_GET['status'] ?? 'all';
    $where = $status_filter !== 'all' ? "WHERE sa.status = ?" : "WHERE 1=1";
    $params = $status_filter !== 'all' ? [$status_filter] : [];

    $stmt = $pdo->prepare("SELECT sa.*, u.name as student_name, u.email as student_email, s.title as scholarship_title 
                         FROM scholarship_applications sa 
                         JOIN users u ON sa.user_id = u.id 
                         JOIN scholarships s ON sa.scholarship_id = s.id 
                         $where
                         ORDER BY sa.created_at DESC");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
} catch (Exception $e) { $scholarships_list = []; $applications = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Scholarship <span>Management</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Institutional Funding Hub • Skope Digital Academy</p>
            </div>
        </div>
        <button class="btn-premium" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Post New Scheme
        </button>
    </header>

    <div class="admin-body">
        <!-- Active Schemes Grid -->
        <div style="margin-bottom: 48px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                <h2 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.5px;">Active Funding Tracks</h2>
                <span class="badge-premium" style="background: var(--primary-glow); color: var(--primary);"><?= count($scholarships_list) ?> Live Programs</span>
            </div>
            
            <div class="dash-stats-grid">
                <?php foreach($scholarships_list as $s): ?>
                <div class="premium-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px;">
                        <h3 style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.4;"><?= htmlspecialchars($s['title']) ?></h3>
                        <div style="display: flex; gap: 10px;">
                            <button onclick='openEditModal(<?= json_encode($s) ?>)' class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border-color: var(--border); color: var(--primary); cursor: pointer;">
                                <i class="fas fa-edit" style="font-size: 0.8rem;"></i>
                            </button>
                            <button onclick="confirmDelete(<?= $s['id'] ?>)" class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border-color: var(--border); color: var(--danger); cursor: pointer;">
                                <i class="fas fa-trash-alt" style="font-size: 0.8rem;"></i>
                            </button>
                        </div>
                    </div>
                    <p style="font-size: 0.85rem; color: var(--text-dim); line-height: 1.6; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        <?= htmlspecialchars($s['description']) ?>
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-light); padding-top: 16px;">
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 900; color: var(--text-main);">KES <?= number_format($s['amount']) ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">Award Value</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-dim);"><i class="far fa-calendar-alt" style="margin-right: 4px;"></i> <?= $s['expiry_date'] ? date('M j', strtotime($s['expiry_date'])) : 'Perpetual' ?></div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">Deadline</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($scholarships_list)): ?>
                <div class="premium-card" style="grid-column: 1/-1; text-align: center; border: 2px dashed var(--border); background: transparent; box-shadow: none;">
                    <i class="fas fa-graduation-cap" style="font-size: 2rem; color: var(--text-muted); opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                    <p style="color: var(--text-dim); font-weight: 600;">No institutional funding schemes registered.</p>
                    <button class="btn-premium" style="margin-top: 20px;" onclick="openAddModal()">Create First Scheme</button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Applications Registry -->
        <div class="table-card">
            <div class="table-header">
                <h2>Application <span>Registry</span></h2>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Status Filter:</span>
                    <select onchange="location.href='?status=' + this.value" style="padding: 10px 16px; border-radius: 12px; border: 1.5px solid var(--border); font-size: 0.85rem; font-weight: 700; outline: none; background: #fff; cursor: pointer;">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Reviews</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved Awards</option>
                        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected Applications</option>
                    </select>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Applicant</th>
                            <th>Target Scheme</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($applications as $a): ?>
                        <tr>
                            <td>
                                <div class="user-identity">
                                    <div class="avatar-sm"><?= strtoupper(substr($a['student_name'], 0, 1)) ?></div>
                                    <div>
                                        <div style="font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($a['student_name']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($a['student_email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($a['scholarship_title']) ?></td>
                            <td>
                                <span class="badge-premium" style="background: <?= $a['status'] === 'approved' ? 'rgba(16, 185, 129, 0.1)' : ($a['status'] === 'pending' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(239, 68, 68, 0.1)') ?>; color: <?= $a['status'] === 'approved' ? 'var(--success)' : ($a['status'] === 'pending' ? 'var(--warning)' : 'var(--danger)') ?>;">
                                    <?= ucfirst($a['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn-premium" style="background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none; padding: 8px 16px; font-size: 0.75rem;" onclick="viewApp('<?= $a['id'] ?>', '<?= addslashes($a['sop']) ?>', '<?= $a['document_file'] ?>')">
                                        <i class="fas fa-eye"></i> Review
                                    </button>
                                    <?php if($a['status'] == 'pending'): ?>
                                        <a href="?action=approve&aid=<?= $a['id'] ?>" class="avatar-sm" style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.1); color: var(--success); border: none; text-decoration: none;" title="Approve">
                                            <i class="fas fa-check" style="font-size: 0.8rem;"></i>
                                        </a>
                                        <a href="?action=reject&aid=<?= $a['id'] ?>" class="avatar-sm" style="width: 32px; height: 32px; background: rgba(239, 68, 68, 0.1); color: var(--danger); border: none; text-decoration: none;" title="Reject">
                                            <i class="fas fa-times" style="font-size: 0.8rem;"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($applications)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 60px; color: var(--text-muted);">
                                <i class="fas fa-inbox" style="font-size: 2rem; opacity: 0.2; margin-bottom: 12px; display: block;"></i>
                                No applicant registry found for the current selection.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add/Edit Modal -->
<div id="schForm" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.8); backdrop-filter:blur(12px); z-index:2000; align-items:center; justify-content:center; padding: 20px;">
    <div class="premium-card" style="width:100%; max-width:540px; padding:40px; animation: modal-pop 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;padding-bottom:16px;border-bottom:1px solid var(--border-light);">
            <h3 id="modalTitle" style="font-size:1.3rem;font-weight:900;letter-spacing:-0.5px;margin:0;">Post New Scheme</h3>
            <button onclick="document.getElementById('schForm').style.display='none'" class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border: none; color: var(--text-muted); cursor: pointer;"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="scholarships.php">
            <input type="hidden" name="scholarship_id" id="sch_id">
            <div style="margin-bottom:24px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Scheme Identity</label>
                <input type="text" name="title" id="sch_title" placeholder="e.g. Merit-Based Tech Grant 2026" style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;box-sizing:border-box;" required>
            </div>
            <div style="margin-bottom:24px;">
                <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Brief Intelligence</label>
                <textarea name="description" id="sch_desc" rows="4" placeholder="Describe the eligibility and focus of this funding scheme..." style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;resize:none;box-sizing:border-box;" required></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:32px;">
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Award Value (KES)</label>
                    <input type="number" name="amount" id="sch_amount" value="0" style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block;font-size:0.7rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Grant Deadline</label>
                    <input type="date" name="expiry_date" id="sch_date" style="width:100%;padding:14px 20px;border-radius:14px;border:1.5px solid var(--border);font-family:inherit;font-size:0.95rem;font-weight:600;outline:none;box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex;gap:16px;">
                <button type="submit" name="save_scholarship" id="modalSubmit" class="btn-premium" style="flex:1; justify-content: center; height: 52px;">Broadcast Scheme</button>
                <button type="button" class="btn-premium" onclick="document.getElementById('schForm').style.display='none'" style="background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none; height: 52px; justify-content: center;">Abort</button>
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

    function viewApp(id, sop, file) {
        const content = `
            <div style="text-align:left; line-height:1.8; color:var(--text-main);">
                <h4 style="font-weight:800; margin-bottom:10px; text-transform:uppercase; font-size:0.7rem; letter-spacing:1px; color:var(--text-muted);">Statement of Purpose</h4>
                <div style="background:#f8fafc; padding:24px; border-radius:16px; border:1px solid var(--border); white-space:pre-wrap;">${sop}</div>
                ${file ? `
                <div style="margin-top:20px; padding:16px; background:var(--primary-glow); border-radius:12px; display:flex; align-items:center; gap:12px;">
                    <i class="fas fa-file-pdf" style="font-size:1.5rem; color:var(--primary);"></i>
                    <div>
                        <div style="font-weight:800; font-size:0.85rem;">SUPPORTING_DOCUMENT.PDF</div>
                        <a href="../uploads/scholarships/${file}" target="_blank" style="font-size:0.75rem; font-weight:700; color:var(--primary); text-decoration:none;">View Attachment Registry</a>
                    </div>
                </div>` : ''}
            </div>
        `;
        SDA.confirmAction(content, null, "Grant Application Review", "Close Registry");
    }

    function openAddModal() {
        document.getElementById('modalTitle').innerHTML = 'Post New Scheme';
        document.getElementById('modalSubmit').innerText = 'Broadcast Scheme';
        document.getElementById('sch_id').value = '';
        document.getElementById('sch_title').value = '';
        document.getElementById('sch_desc').value = '';
        document.getElementById('sch_amount').value = '0';
        document.getElementById('sch_date').value = '';
        document.getElementById('schForm').style.display = 'flex';
    }

    function openEditModal(data) {
        document.getElementById('modalTitle').innerHTML = 'Update Intelligence';
        document.getElementById('modalSubmit').innerText = 'Save Changes';
        document.getElementById('sch_id').value = data.id;
        document.getElementById('sch_title').value = data.title;
        document.getElementById('sch_desc').value = data.description;
        document.getElementById('sch_amount').value = data.amount;
        document.getElementById('sch_date').value = data.expiry_date || '';
        document.getElementById('schForm').style.display = 'flex';
    }

    function confirmDelete(sid) {
        SDA.confirmAction("Are you sure you want to decommission this institutional grant? All associated registry data will be archived.", () => {
            window.location.href = `scholarships.php?action=delete&sid=${sid}`;
        }, "Security Clearance", "Confirm Purge");
    }

    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>    }
</script>
</body></html>
