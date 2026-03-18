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

// 2. Handle Add/Delete Scholarship
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scholarship'])) {
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $amount = (float)$_POST['amount'];
    $expiry = $_POST['expiry_date'] ?: null;

    try {
        $stmt = $pdo->prepare("INSERT INTO scholarships (title, description, amount, expiry_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $desc, $amount, $expiry]);
        $success_msg = "New scholarship posted.";
    } catch (Exception $e) { $error_msg = $e->getMessage(); }
}

// 3. Fetch Data
try {
    $scholarships_list = $pdo->query("SELECT * FROM scholarships ORDER BY created_at DESC")->fetchAll();
    
    $stmt = $pdo->query("SELECT sa.*, u.name as student_name, u.email as student_email, s.title as scholarship_title 
                         FROM scholarship_applications sa 
                         JOIN users u ON sa.user_id = u.id 
                         JOIN scholarships s ON sa.scholarship_id = s.id 
                         ORDER BY sa.created_at DESC");
    $applications = $stmt->fetchAll();
} catch (Exception $e) { $scholarships_list = []; $applications = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Scholarship <span class="text-primary">Management</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Oversee student aid programs and application decision workflows.</p>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('schForm').style.display='block'">
            <i class="fas fa-plus"></i> Post New Program
        </button>
    </header>
    
    <div class="admin-body">

        <!-- Active Programs -->
        <div style="margin-bottom: 40px;">
           <h3 style="margin-bottom: 20px; font-size: 1.1rem;">Active Programs</h3>
           <div class="grid-3">
               <?php foreach($scholarships_list as $s): ?>
               <div class="table-card" style="padding: 24px;">
                   <h4 style="margin-bottom: 8px; font-size: 1rem;"><?= htmlspecialchars($s['title']) ?></h4>
                   <p style="font-size: 0.82rem; color: var(--text-dim); margin-bottom: 16px; height: 40px; overflow: hidden;"><?= htmlspecialchars($s['description']) ?></p>
                   <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.88rem; color: var(--text-muted); border-top: 1px solid var(--dark-border); padding-top: 16px;">
                       <span>Award: <strong>KES <?= number_format($s['amount']) ?></strong></span>
                       <span style="font-size: 0.75rem;"><i class="fas fa-clock"></i> <?= $s['expiry_date'] ? date('M j, Y', strtotime($s['expiry_date'])) : 'Infinity' ?></span>
                   </div>
               </div>
               <?php endforeach; ?>
           </div>
        </div>

        <!-- Applications Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 style="font-size: 1rem;">Student Applications</h3>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Scholarship</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($applications as $a): ?>
                        <tr class="user-row">
                            <td>
                                <div>
                                    <div style="font-weight: 600; font-size: 0.92rem;"><?= htmlspecialchars($a['student_name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-dim);"><?= htmlspecialchars($a['student_email']) ?></div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($a['scholarship_title']) ?></td>
                            <td>
                                <span class="badge <?= $a['status'] == 'pending' ? 'badge-warning' : ($a['status'] == 'approved' ? 'badge-success' : 'badge-danger') ?>">
                                    <?= ucfirst($a['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn btn-ghost btn-sm" onclick="viewApp('<?= $a['id'] ?>', '<?= addslashes($a['sop']) ?>', '<?= $a['document_file'] ?>')">Review</button>
                                    <?php if($a['status'] == 'pending'): ?>
                                        <a href="?action=approve&aid=<?= $a['id'] ?>" class="btn btn-success btn-sm" style="background:var(--success); color:#fff; font-size:0.75rem;">Approve</a>
                                        <a href="?action=reject&aid=<?= $a['id'] ?>" class="btn btn-ghost btn-sm" style="color:var(--danger); font-size:0.75rem;">Reject</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

</main>

<!-- Add Program Form (Overlay simple) -->
<div id="schForm" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center;">
        <div class="card" style="width: 500px; padding: 32px; background: white; border-radius: 20px;">
            <h3 style="margin-bottom: 24px; font-family: 'Poppins', sans-serif;">Post New Scholarship</h3>
            <form method="POST" action="scholarships.php">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="display:block; font-size: 0.75rem; font-weight:800; text-transform:uppercase; margin-bottom:8px;">Scheme Title</label>
                    <input type="text" name="title" class="form-control" style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd;" required>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="display:block; font-size: 0.75rem; font-weight:800; text-transform:uppercase; margin-bottom:8px;">Detailed Description</label>
                    <textarea name="description" class="form-control" style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd; min-height: 80px;" required></textarea>
                </div>
                <div class="grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" style="display:block; font-size: 0.75rem; font-weight:800; text-transform:uppercase; margin-bottom:8px;">Award Value</label>
                        <input type="number" name="amount" class="form-control" style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd;" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display:block; font-size: 0.75rem; font-weight:800; text-transform:uppercase; margin-bottom:8px;">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd;">
                    </div>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" name="save_scholarship" class="btn btn-primary btn-block">Broadcast Scheme</button>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('schForm').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
</div>

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
        // More elegant view?
        SDA.confirmAction("STATEMENT OF PURPOSE:\n\n" + sop + (file ? "\n\nAsset Attached: " + file : ""), null, "Application Review", "Close Viewing");
    }
</script>
</body>
</html>
