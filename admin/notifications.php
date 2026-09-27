<?php
$pageTitle = 'Institutional Alert Command';
require_once 'includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// 1. Handle Notification Deployment
$message = '';
$msg_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $title    = trim($_POST['title'] ?? '');
    $content  = trim($_POST['message'] ?? '');
    $role     = $_POST['user_role'] ?? 'all';
    $target_id = !empty($_POST['target_user_id']) ? $_POST['target_user_id'] : null;

    if (!empty($title) && !empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $content, $role, $target_id]);
            $message = "Platform broadcast deployed successfully to " . ($role === 'all' ? "the entire community" : "all " . ucfirst($role) . "s") . ".";
        } catch (Exception $e) { 
            $message = "Protocol Error: " . $e->getMessage(); 
            $msg_type = 'danger';
        }
    } else { 
        $message = "Protocol Warning: Communication title and content are required."; 
        $msg_type = 'warning';
    }
}

// 2. Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notif'])) {
    $notif_id = (int)$_POST['notif_id'];
    try {
        $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$notif_id]);
        $message = "Intelligence archive purged successfully.";
    } catch (Exception $e) { 
        $message = "Protocol Error: " . $e->getMessage(); 
        $msg_type = 'danger';
    }
}

// 3. Fetch Sent History
try {
    $notifications = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
} catch (Exception $e) { $notifications = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Platform <span>Broadcasting</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Alert Command • Skope Digital Academy</p>
            </div>
        </div>
        
        <button class="btn-premium" onclick="document.querySelector('.premium-card form').scrollIntoView({behavior:'smooth'})">
            <i class="fas fa-satellite-dish"></i> New Alert
        </button>
    </header>

    <div class="admin-body">
        <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 40px; align-items: start;">
            <!-- Broadcast Deployer -->
            <div class="premium-card" style="padding: 0; overflow: hidden; position: sticky; top: 100px;">
                <div style="padding: 24px 32px; border-bottom: 1.5px solid var(--border-light);">
                    <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px;">Broadcast Deployer</h3>
                    <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Direct Community Engagement</p>
                </div>
                <div style="padding: 32px;">
                    <form method="POST" style="display: flex; flex-direction: column; gap: 24px;">
                        <input type="hidden" name="send_notification" value="1">
                        
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Intelligence Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Scheduled System Optimization" required style="background: var(--bg-main); border: 1.5px solid var(--border); font-weight: 700;">
                        </div>

                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Broadcast Segment</label>
                            <select name="user_role" class="form-control" style="background: var(--bg-main); border: 1.5px solid var(--border); font-weight: 700; height: 50px;">
                                <option value="all">🌐 ALL STAKEHOLDERS</option>
                                <option value="student">🎓 SCHOLARS ONLY</option>
                                <option value="tutor">👨‍🏫 FACULTY MEMBERS</option>
                                <option value="admin">🏢 ADMINISTRATION ONLY</option>
                            </select>
                        </div>

                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Targeted Intelligence</label>
                            <textarea name="message" class="form-control" style="min-height: 180px; background: var(--bg-main); border: 1.5px solid var(--border); font-weight: 600; line-height: 1.6; resize: none;" placeholder="Draft high-priority communication..." required></textarea>
                        </div>

                        <button type="submit" class="btn-premium" style="justify-content: center; width: 100%; height: 54px; font-size: 0.95rem;">
                            <i class="fas fa-paper-plane"></i> DEPLOY BROADCAST
                        </button>
                    </form>
                </div>
            </div>

            <!-- Intelligence Archive -->
            <div class="premium-card" style="padding: 0; overflow: hidden;">
                <div style="padding: 24px 32px; border-bottom: 1.5px solid var(--border-light); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px;">Intelligence Archive</h3>
                        <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Strategic Communications Log</p>
                    </div>
                    <div class="badge-premium" style="background: var(--bg-main); color: var(--text-muted); border: 1.5px solid var(--border-light);">LATEST 20 DEPLOYMENTS</div>
                </div>

                <div style="display: flex; flex-direction: column;">
                    <?php foreach($notifications as $n): ?>
                    <div style="padding: 32px; border-bottom: 1.5px solid var(--border-light); transition: 0.3s; position: relative;" class="history-item">
                        <div style="display: flex; gap: 24px; align-items: flex-start;">
                            <div class="avatar-sm" style="width: 44px; height: 44px; flex-shrink: 0; background: var(--bg-main); border: 1.5px solid var(--border-light); color: var(--primary);">
                                <?php if($n['user_role'] === 'all'): ?><i class="fas fa-globe"></i>
                                <?php elseif($n['user_role'] === 'student'): ?><i class="fas fa-graduation-cap"></i>
                                <?php else: ?><i class="fas fa-id-badge"></i><?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <h4 style="font-size: 1rem; font-weight: 900; color: var(--text-main); margin: 0;"><?= htmlspecialchars($n['title']) ?></h4>
                                    <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;"><?= date('M j, H:i', strtotime($n['created_at'])) ?></span>
                                </div>
                                <div style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.7; font-weight: 600;">
                                    <?= nl2br(htmlspecialchars($n['message'])) ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
                                    <div class="badge-premium" style="font-size: 0.6rem; background: var(--bg-main); color: var(--text-muted);">TARGET: <?= strtoupper($n['user_role']) ?></div>
                                    <form method="POST" onsubmit="return SDA.confirmAction('Purge this intelligence artifact from institutional archives?', () => { this.submit(); })">
                                        <input type="hidden" name="delete_notif" value="1">
                                        <input type="hidden" name="notif_id" value="<?= $n['id'] ?>">
                                        <button type="submit" class="avatar-sm" style="width: 32px; height: 32px; background: transparent; border: none; color: var(--danger); cursor: pointer; opacity: 0.5; transition: 0.3s;" onmouseover="this.style.opacity=1">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if(empty($notifications)): ?>
                        <div style="text-align: center; padding: 100px 32px;">
                            <div class="avatar-sm" style="width: 80px; height: 80px; margin: 0 auto 24px; background: var(--bg-main); color: var(--text-muted); font-size: 2rem;"><i class="fas fa-satellite-dish"></i></div>
                            <h4 style="font-size: 1rem; font-weight: 900; color: var(--text-main);">No Intelligence Archived</h4>
                            <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-top: 8px;">Institutional broadcast history is currently empty.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if($message): ?>
            SDA.showToast("<?= $message ?>", "<?= $msg_type ?>");
        <?php endif; ?>
    });

    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
