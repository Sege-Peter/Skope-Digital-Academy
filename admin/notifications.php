<?php
$pageTitle = 'Strategic Communications';
require_once '../includes/header.php';
requireRole('admin');

// 1. Handle Notification Deployment
$message = '';
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
        } catch (Exception $e) { $message = "Protocol Error: " . $e->getMessage(); }
    } else { $message = "Protocol Warning: Communication title and content are required."; }
}

// 2. Fetch Sent History
try {
    $notifications = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20")->fetchAll();
} catch (Exception $e) { $notifications = []; }
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .notification-form-card { background: white; border: 1px solid var(--dark-border); border-radius: 24px; padding: 40px; margin-bottom: 48px; box-shadow: var(--shadow-sm); }
    .form-group { margin-bottom: 24px; }
    .form-group label { display: block; font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px; }
    .notif-input { width: 100%; padding: 16px 20px; border-radius: 12px; border: 1px solid var(--dark-border); background: #f8fafc; font-size: 0.95rem; font-family: 'Inter', sans-serif; transition: 0.3s; }
    .notif-input:focus { border-color: var(--primary); outline: none; background: white; box-shadow: 0 0 0 5px var(--primary-glow); }
    
    .history-card { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px; }
    .history-item { display: flex; align-items: flex-start; gap: 20px; padding: 24px 0; border-bottom: 1px solid var(--bg-light); transition: 0.2s; }
    .history-item:last-child { border: none; }
    .history-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
    .history-content { flex: 1; }
    .history-title { font-weight: 800; color: var(--dark); font-size: 1rem; margin-bottom: 4px; }
    .history-msg { font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Platform <span class="text-primary">Broadcasting</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Direct community engagement through targeted system-wide notifications.</p>
            </div>
        </div>
    </header>

    <?php if($message): ?>
        <div style="padding: 16px 24px; background: #DCFCE7; color: #166534; border-radius: 16px; margin-bottom: 32px; border: 1px solid #BBF7D0; font-weight: 700;">
            <i class="fas fa-satellite-dish"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="grid-2" style="grid-template-columns: 1.2fr 1.8fr; gap: 40px; align-items: start;">
        <div class="notification-form-card">
            <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 800; margin-bottom: 32px; border-bottom: 2px solid var(--primary-glow); padding-bottom: 12px; color: var(--dark);">Deploy Broadcast</h3>
            <form method="POST">
                <input type="hidden" name="send_notification" value="1">
                
                <div class="form-group">
                    <label>Intelligence Title</label>
                    <input type="text" name="title" class="notif-input" placeholder="e.g. Scheduled System Upgrade" required>
                </div>

                <div class="form-group">
                    <label>Broadcast Segment</label>
                    <select name="user_role" class="notif-input" style="height: 56px; font-weight: 600;">
                        <option value="all">🌐 All Stakeholders (Portal-Wide)</option>
                        <option value="student">🎓 Students Only</option>
                        <option value="tutor">👨‍🏫 Instructors / Tutors</option>
                        <option value="admin">🏢 Administration Only</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Targeted Message Content</label>
                    <textarea name="message" class="notif-input" style="min-height: 160px; resize: none;" placeholder="Draft the high-priority communication for the community..." required></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" style="border-radius: 16px; font-weight: 900; letter-spacing: 0.5px;">
                    <i class="fas fa-paper-plane" style="margin-right: 10px;"></i> Deploy System Broadcast
                </button>
            </form>
        </div>

        <div class="history-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 800; color: var(--dark);">Intelligence History</h3>
                <span class="badge badge-ghost" style="border-radius: 8px;">Latest 20 Deployments</span>
            </div>

            <div class="history-list">
                <?php foreach($notifications as $n): ?>
                <div class="history-item">
                    <div class="history-icon">
                        <?php if($n['user_role'] === 'all'): ?><i class="fas fa-globe"></i>
                        <?php elseif($n['user_role'] === 'student'): ?><i class="fas fa-graduation-cap"></i>
                        <?php else: ?><i class="fas fa-briefcase"></i><?php endif; ?>
                    </div>
                    <div class="history-content">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <div class="history-title"><?= htmlspecialchars($n['title']) ?></div>
                            <span style="font-size: 0.65rem; color: var(--text-dim); font-weight: 800; text-transform: uppercase;">Sent <?= date('M j, H:i', strtotime($n['created_at'])) ?></span>
                        </div>
                        <div class="history-msg"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                        <div style="margin-top: 10px;">
                            <span class="badge <?= ($n['user_role'] === 'all' ? 'badge-primary' : 'badge-ghost') ?>" style="font-size: 0.6rem; padding: 4px 10px;">
                                Segment: <?= strtoupper($n['user_role']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if(empty($notifications)): ?>
                    <div style="text-align: center; padding: 60px 0; color: var(--text-dim);">
                        <i class="fas fa-satellite" style="font-size: 2.5rem; opacity: 0.2; margin-bottom: 16px;"></i>
                        <p>No previous broadcasts found in the system archives.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

</body>
</html>
