<?php
$pageTitle = 'Tutor Notifications';
require_once 'includes/header.php';

$tutor = currentUser();
// Check if tutor
if ($tutor['role'] !== 'tutor') {
    header('Location: ../index.php');
    exit;
}

// Mark all as read if requested
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE target_user_id = ? OR user_role = 'tutor' OR user_role = 'all'");
    $stmt->execute([$tutor['id']]);
    header('Location: notifications.php?msg=read');
    exit;
}

// Fetch notifications
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications 
                           WHERE target_user_id = ? OR user_role = 'tutor' OR user_role = 'all' 
                           ORDER BY created_at DESC");
    $stmt->execute([$tutor['id']]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    $notifications = [];
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Alerts Center<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Directing <span style="color: var(--primary); font-weight: 700;">administrative mandates</span> and student requests.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <?php if (count($notifications) > 0): ?>
        <form method="POST">
            <button type="submit" name="mark_all_read" class="btn-premium" style="background: white; color: var(--text-main); border: 1px solid #E2E8F0; box-shadow: none; font-size: 0.8rem; padding: 12px 24px;">
                <i class="fas fa-check-double" style="color: var(--primary);"></i> MARK ALL AS READ
            </button>
        </form>
        <?php endif; ?>
    </div>
</header>

<div class="notif-container" style="max-width: 900px; margin: 0 auto;">
    <?php if (empty($notifications)): ?>
        <div style="text-align:center; padding: 120px 40px; background: white; border-radius: 32px; border: 1px solid #F1F5F9; box-shadow: var(--shadow-soft);">
            <div style="width: 80px; height: 80px; background: #F8FAFC; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 2rem; color: #CBD5E1;"><i class="fas fa-bell-slash"></i></div>
            <h3 style="margin: 0 0 12px; font-weight: 950; font-size: 1.4rem; letter-spacing: -0.5px;">Institutional Silence</h3>
            <p style="color: var(--text-dim); font-weight: 600; max-width: 400px; margin: 0 auto;">You have no new mandates or requests within your priority pool.</p>
        </div>
    <?php else: ?>
        <?php foreach($notifications as $n): ?>
            <?php 
                $isUnread = ($n['read_status'] == 0);
                $icon = 'fas fa-bell';
                $color = 'var(--primary)';
                if (stripos($n['title'], 'assignment') !== false) { $icon = 'fas fa-file-invoice'; $color = '#00BFFF'; }
                if (stripos($n['title'], 'admin') !== false) { $icon = 'fas fa-shield-check'; $color = '#1E293B'; }
                if (stripos($n['title'], 'request') !== false) { $icon = 'fas fa-paper-plane'; $color = '#10B981'; }
            ?>
            <div class="premium-card flex-responsive" style="padding: 32px; margin-bottom: 24px; transition: 0.3s; <?= $isUnread ? 'background: #E0F7FF; border-left: 6px solid var(--primary);' : 'background: white;' ?>">
                <div style="width: 60px; height: 60px; border-radius: 18px; background: <?= $isUnread ? 'var(--primary)' : '#F8FAFC' ?>; color: <?= $isUnread ? 'white' : $color ?>; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; box-shadow: <?= $isUnread ? '0 8px 20px rgba(0, 191, 255, 0.25)' : 'none' ?>; border: 1px solid <?= $isUnread ? 'transparent' : '#E2E8F0' ?>;">
                    <i class="<?= $icon ?>"></i>
                </div>
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <h4 style="margin: 0; font-weight: 950; font-size: 1.15rem; color: var(--text-main); letter-spacing: -0.5px;"><?= htmlspecialchars($n['title']) ?></h4>
                        <?php if($isUnread): ?>
                            <span style="font-size: 0.6rem; font-weight: 950; color: white; background: var(--primary); padding: 4px 10px; border-radius: 50px; text-transform: uppercase; letter-spacing: 1px;">Priority Alert</span>
                        <?php endif; ?>
                    </div>
                    <p style="margin: 0 0 20px; font-size: 1rem; color: var(--text-dim); font-weight: 500; line-height: 1.6;"><?= nl2br(htmlspecialchars($n['message'])) ?></p>
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div style="font-size: 0.7rem; color: #94A3B8; font-weight: 850; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px;">
                            <i class="far fa-clock" style="font-size: 0.8rem;"></i> <?= date('M d, Y • h:i A', strtotime($n['created_at'])) ?>
                        </div>
                        <div style="height: 4px; width: 4px; border-radius: 50%; background: #CBD5E1;"></div>
                        <div style="font-size: 0.7rem; color: var(--primary); font-weight: 950; text-transform: uppercase; letter-spacing: 1px;">Institutional Protocol</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</main>
</body>
</html>
