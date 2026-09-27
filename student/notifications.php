<?php
$pageTitle = 'Institutional Intelligence';
require_once '../includes/header.php';

// Auth Check: Student only
if ($user['role'] !== 'student') { header('Location: ../login.php'); exit; }

// 1. Mark all as read if requested
if (isset($_GET['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE (user_role = 'student' OR user_role = 'all' OR target_user_id = ?) AND read_status = 0");
    $stmt->execute([$user['id']]);
    header('Location: notifications.php');
    exit;
}

// 2. Fetch Notifications (Global student, targeted student, or all)
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications 
                           WHERE (user_role = 'student' OR user_role = 'all' OR target_user_id = ?) 
                           ORDER BY created_at DESC 
                           LIMIT 50");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) { $notifications = []; }
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .notif-card { background: white; border: 1px solid var(--dark-border); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-sm); }
    .notif-item { display: flex; align-items: flex-start; gap: 24px; padding: 32px; border-bottom: 1px solid var(--bg-light); transition: 0.3s; position: relative; }
    .notif-item:last-child { border: none; }
    .notif-item.unread { background: #f0f9ff; border-left: 4px solid var(--primary); }
    .notif-item:hover { background: #f8fafc; }
    
    .notif-icon { width: 56px; height: 56px; border-radius: 16px; background: white; border: 1px solid var(--dark-border); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: var(--primary); flex-shrink: 0; box-shadow: var(--shadow-sm); }
    .unread .notif-icon { background: var(--primary); color: white; border-color: var(--primary); }
    
    .notif-content { flex: 1; }
    .notif-title { font-family: 'Poppins', sans-serif; font-weight: 800; color: var(--dark); font-size: 1.15rem; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
    .notif-badge { font-size: 0.65rem; font-weight: 800; background: var(--primary-glow); color: var(--primary); padding: 4px 10px; border-radius: 6px; text-transform: uppercase; letter-spacing: 1px; }
    .notif-msg { font-size: 1rem; color: var(--text-muted); line-height: 1.7; }
    .notif-time { font-size: 0.75rem; color: var(--text-dim); margin-top: 16px; display: flex; align-items: center; gap: 6px; font-weight: 500; }
</style>

<main class="main-content">
    <header class="admin-header" style="background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); border-bottom: 1px solid var(--dark-border); padding: 0 40px; height: 90px;">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()" style="background: var(--bg-light); width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.6rem; font-weight: 800; color: var(--text-primary); letter-spacing: -0.5px;">Institutional <span style="color: var(--primary);">Intelligence</span></h1>
                <p style="color: var(--text-dim); font-size: 0.85rem; font-weight: 500; margin-top: 2px;">Official platform broadcasts and administrative alerts.</p>
            </div>
        </div>
        <div>
            <a href="?mark_all_read=1" class="btn btn-ghost" style="font-size: 0.85rem; font-weight: 700; color: var(--text-dim);">
                <i class="fas fa-check-double"></i> Mark all as read
            </a>
        </div>
    </header>

    <div class="notif-card" data-aos>
        <?php if(!empty($notifications)): ?>
            <?php foreach($notifications as $n): ?>
            <div class="notif-item <?= $n['read_status'] ? '' : 'unread' ?>">
                <div class="notif-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-title">
                        <?= htmlspecialchars($n['title']) ?>
                        <?php if(!$n['read_status']): ?>
                            <span class="notif-badge">New Alert</span>
                        <?php endif; ?>
                    </div>
                    <div class="notif-msg"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
                    <div class="notif-time">
                        <i class="far fa-clock"></i> 
                        <?= date('F j, Y \a\t H:i', strtotime($n['created_at'])) ?>
                        <span style="margin: 0 8px; opacity: 0.3;">•</span>
                        <span style="color: var(--primary); font-weight: 700;">Platform Broadcast</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 120px 40px;">
                <div style="width: 100px; height: 100px; background: var(--bg-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: var(--text-dim); font-size: 2.5rem;">
                    <i class="fas fa-satellite"></i>
                </div>
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--dark); margin-bottom: 12px;">No incoming alerts</h3>
                <p style="color: var(--text-dim); max-width: 400px; margin: 0 auto;">High-priority institutional communications will appear here once deployed by the administration.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
