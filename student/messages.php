<?php
$pageTitle = 'Messaging Studio';
require_once 'includes/header.php';

$to_id = isset($_GET['to']) ? (int)$_GET['to'] : 0;
$receiver = null;

if($to_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, avatar, role FROM users WHERE id = ?");
        $stmt->execute([$to_id]);
        $receiver = $stmt->fetch();
    } catch(Exception $e) {}
}

// Fetch recent conversations
try {
    $stmt = $pdo->prepare("SELECT u.id, u.name, u.avatar, u.role, 
                           (SELECT content FROM messages WHERE (sender_id = :sid AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = :sid) ORDER BY created_at DESC LIMIT 1) as last_msg,
                           (SELECT created_at FROM messages WHERE (sender_id = :sid AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = :sid) ORDER BY created_at DESC LIMIT 1) as last_time
                           FROM users u
                           WHERE u.id IN (SELECT sender_id FROM messages WHERE receiver_id = :sid UNION SELECT receiver_id FROM messages WHERE sender_id = :sid)
                           ORDER BY last_time DESC");
    $stmt->execute(['sid' => $student['id']]);
    $recent = $stmt->fetchAll();
} catch(Exception $e) { $recent = []; }

require_once 'includes/layout-top.php';
?>

<div class="messaging-studio" style="display: grid; grid-template-columns: 320px 1fr; height: calc(100vh - 160px); background: white; border: 1px solid var(--border); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-sm);">
    <!-- Sidebar -->
    <div style="border-right: 1px solid #f1f5f9; display: flex; flex-direction: column;">
        <div style="padding: 24px; border-bottom: 1px solid #f1f5f9;">
            <h5 style="margin: 0; font-weight: 900; letter-spacing: -0.5px;">Institutional Contacts</h5>
        </div>
        <div style="flex: 1; overflow-y: auto;">
            <?php foreach($recent as $r): ?>
                <a href="?to=<?= $r['id'] ?>" style="display: flex; gap: 12px; padding: 16px 24px; text-decoration: none; border-bottom: 1px solid #f8fafc; background: <?= $to_id == $r['id'] ? '#F0F9FF' : 'transparent' ?>; transition: 0.2s;">
                    <img src="../<?= htmlspecialchars($r['avatar'] ?: 'assets/images/user-placeholder.png') ?>" style="width: 44px; height: 44px; border-radius: 12px; object-fit: cover;">
                    <div style="flex: 1; min-width: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: baseline;">
                            <h6 style="margin: 0; font-size: 0.85rem; font-weight: 800; color: #1e293b;"><?= htmlspecialchars($r['name']) ?></h6>
                            <span style="font-size: 0.55rem; color: #94a3b8;"><?= $r['last_time'] ? date('H:i', strtotime($r['last_time'])) : '' ?></span>
                        </div>
                        <p style="margin: 3px 0 0; font-size: 0.75rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($r['last_msg'] ?: 'No messages yet') ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
            <?php if(empty($recent) && !$to_id): ?>
                <div style="padding: 40px 24px; text-align: center; opacity: 0.5;">
                    <i class="far fa-comments fa-2x"></i>
                    <p style="font-size: 0.8rem; margin-top: 10px;">Select a course tutor to start a discussion.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Discussion -->
    <div style="display: flex; flex-direction: column;">
        <?php if($receiver): ?>
            <!-- Discussion Header -->
            <div style="padding: 16px 32px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; background: #fff;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <img src="../<?= htmlspecialchars($receiver['avatar'] ?: 'assets/images/user-placeholder.png') ?>" style="width: 40px; height: 40px; border-radius: 10px; object-fit: cover;">
                    <div>
                        <h6 style="margin: 0; font-weight: 950; font-size: 0.95rem;"><?= htmlspecialchars($receiver['name']) ?></h6>
                        <span style="font-size: 0.62rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px;"><?= $receiver['role'] ?></span>
                    </div>
                </div>
                <div style="display: flex; gap: 16px; color: #cbd5e1;">
                    <i class="fas fa-video" style="cursor: pointer;"></i>
                    <i class="fas fa-info-circle" style="cursor: pointer;"></i>
                </div>
            </div>

            <!-- Messages Area (Placeholder for real-time feed) -->
            <div style="flex: 1; padding: 32px; background: #fbfcfd; overflow-y: auto; display: flex; flex-direction: column; gap: 16px;">
                <div style="text-align: center; margin-bottom: 24px;">
                    <span style="background: rgba(0,0,0,0.04); padding: 4px 12px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Discussion Initialized</span>
                </div>
                <!-- Logic to fetch and display messages would go here -->
                <p style="text-align: center; color: #94a3b8; font-size: 0.85rem; padding-top: 60px;">Your secure communication line is encrypted and ready.</p>
            </div>

            <!-- Input Area -->
            <div style="padding: 24px 32px; border-top: 1px solid #f1f5f9; background: #fff;">
                <form action="send-message.php" method="POST" style="display: flex; gap: 12px; align-items: center;">
                    <input type="hidden" name="to_id" value="<?= $receiver['id'] ?>">
                    <button type="button" style="background: none; border: none; color: #94a3b8; font-size: 1.2rem;"><i class="far fa-smile"></i></button>
                    <input type="text" name="message" placeholder="Type your inquiry for the faculty lead..." style="flex: 1; padding: 14px 20px; border: 1px solid #e2e8f0; border-radius: 14px; outline: none; font-size: 0.88rem; font-weight: 600; background: #f8fafc;">
                    <button type="submit" class="btn-premium" style="width: 50px; height: 50px; padding: 0; border: none; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; border-radius: 12px; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(0,174,239,0.25);"><i class="fas fa-paper-plane" style="font-size: 1.1rem;"></i></button>
                </form>
            </div>
        <?php else: ?>
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; opacity: 0.5;">
                <i class="fas fa-inbox fa-3x" style="color: #cbd5e1; margin-bottom: 24px;"></i>
                <h4 style="font-weight: 950; margin: 0;">Institutional Inbox</h4>
                <p style="font-size: 0.85rem; max-width: 300px; margin-top: 10px;">Select a conversation to start or manage your academic inquiries.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
