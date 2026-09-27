<?php
$pageTitle = 'Support Intelligence Center';
require_once 'includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Handle actions (Status update)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $new_status = $_POST['status'] ?? 'open';
    try {
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $ticket_id]);
        $message = "Ticket #$ticket_id workflow status updated to " . strtoupper(str_replace('_', ' ', $new_status));
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

// Fetch tickets
try {
    $status_filter = $_GET['status'] ?? 'all';
    $query = "SELECT st.*, u.name as user_name, u.email as user_email 
              FROM support_tickets st 
              LEFT JOIN users u ON st.user_id = u.id";
    
    $where = [];
    if ($status_filter !== 'all') {
        $where[] = "st.status = " . $pdo->quote($status_filter);
    }
    
    if (!empty($where)) {
        $query .= " WHERE " . implode(' AND ', $where);
    }
    
    $query .= " ORDER BY st.created_at DESC";
    $tickets = $pdo->query($query)->fetchAll();

} catch (Exception $e) { $tickets = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Resolution <span>Command</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Support Intelligence Hub • Skope Digital Academy</p>
            </div>
        </div>
        
        <div style="display: flex; gap: 16px;">
            <div class="badge-premium" style="background: var(--bg-main); color: var(--text-muted); border: 1.5px solid var(--border-light);">
                <i class="fas fa-circle" style="color: var(--success); font-size: 0.5rem; margin-right: 8px;"></i>
                <?= count($tickets) ?> PENDING RESOLUTIONS
            </div>
        </div>
    </header>

    <div class="admin-body">
        <!-- Workflow Filters -->
        <div class="premium-card glass-effect" style="padding: 16px 24px; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-right: 12px;">Resolution Queue:</span>
                <a href="tickets.php?status=all" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $status_filter == 'all' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $status_filter == 'all' ? 'white' : '#64748b' ?>;">FULL STREAM</a>
                <a href="tickets.php?status=open" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $status_filter == 'open' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $status_filter == 'open' ? 'white' : '#64748b' ?>;">OPEN QUEUE</a>
                <a href="tickets.php?status=in_progress" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $status_filter == 'in_progress' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $status_filter == 'in_progress' ? 'white' : '#64748b' ?>;">ACTIVE CASES</a>
                <a href="tickets.php?status=closed" style="text-decoration:none; padding: 8px 16px; border-radius: 10px; font-size: 0.75rem; font-weight: 800; background: <?= $status_filter == 'closed' ? 'var(--grad-primary)' : '#f1f5f9' ?>; color: <?= $status_filter == 'closed' ? 'white' : '#64748b' ?>;">ARCHIVED</a>
            </div>
        </div>

        <div class="tickets-container">
            <?php foreach($tickets as $t): ?>
            <div class="premium-card" style="margin-bottom: 24px; border-left: 4px solid <?= $t['status'] === 'open' ? 'var(--danger)' : ($t['status'] === 'in_progress' ? 'var(--warning)' : 'var(--success)') ?>;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="avatar-sm" style="width: 44px; height: 44px; background: var(--bg-main); border-color: var(--border); color: var(--text-dim); font-size: 1rem;">
                            <?= strtoupper(substr($t['user_name'] ?? 'G', 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-size: 1rem; font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($t['user_name'] ?? 'External Stakeholder') ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;"><?= htmlspecialchars($t['user_email'] ?? 'Contact restricted') ?></div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge-premium" style="background: <?= $t['status'] === 'open' ? 'var(--danger-glow)' : ($t['status'] === 'in_progress' ? 'var(--warning-glow)' : 'var(--success-glow)') ?>; color: <?= $t['status'] === 'open' ? 'var(--danger)' : ($t['status'] === 'in_progress' ? 'var(--warning)' : 'var(--success)') ?>; border: none; font-weight: 900;">
                            <?= strtoupper(str_replace('_', ' ', $t['status'])) ?>
                        </span>
                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px; font-weight: 800; letter-spacing: 0.5px;">#<?= $t['id'] ?> • <?= date('M j, Y | g:i a', strtotime($t['created_at'])) ?></div>
                    </div>
                </div>

                <div style="background: var(--bg-main); border: 1.5px solid var(--border-light); border-radius: 16px; padding: 24px; margin-bottom: 24px;">
                    <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-main); margin-bottom: 12px; letter-spacing: -0.3px;"><?= htmlspecialchars($t['subject']) ?></h3>
                    <p style="font-size: 0.95rem; color: var(--text-main); line-height: 1.7; white-space: pre-wrap; margin: 0; font-family: 'Inter', sans-serif;"><?= nl2br(htmlspecialchars($t['message'])) ?></p>
                </div>

                <form method="POST" style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap; border-top: 1px solid var(--border-light); padding-top: 24px;">
                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div style="display: flex; align-items: center; gap: 16px; flex: 1; min-width: 300px;">
                        <label style="font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; white-space: nowrap;">Workflow Transition:</label>
                        <select name="status" style="flex: 1; padding: 12px 16px; border-radius: 12px; border: 1.5px solid var(--border); background: white; font-weight: 700; font-size: 0.85rem; outline: none; cursor: pointer;">
                            <option value="open" <?= ($t['status'] === 'open') ? 'selected' : '' ?>>Move to Open Queue</option>
                            <option value="in_progress" <?= ($t['status'] === 'in_progress') ? 'selected' : '' ?>>Authorize Investigation (Active)</option>
                            <option value="closed" <?= ($t['status'] === 'closed') ? 'selected' : '' ?>>Execute Resolution (Archive)</option>
                        </select>
                        <button type="submit" class="btn-premium" style="height: 46px; padding: 0 24px; font-size: 0.8rem;">Sync Status</button>
                    </div>
                    
                    <?php if($t['user_email']): ?>
                        <a href="mailto:<?= $t['user_email'] ?>?subject=Re: <?= urlencode($t['subject']) ?>" class="btn-premium" style="height: 46px; background: var(--bg-main); color: var(--primary); border: 1.5px solid var(--primary-glow); box-shadow: none; text-decoration: none; padding: 0 24px; font-size: 0.8rem;">
                            <i class="fas fa-paper-plane" style="margin-right: 8px;"></i> Dispatch Response
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <?php endforeach; ?>

            <?php if(empty($tickets)): ?>
            <div style="text-align: center; padding: 80px 40px; background: var(--bg-main); border: 2.5px dashed var(--border); border-radius: 32px;">
                <div style="width: 72px; height: 72px; background: white; border: 1.5px solid var(--border-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--success); margin: 0 auto 24px; font-size: 2rem;">
                    <i class="fas fa-check-double"></i>
                </div>
                <h3 style="font-size: 1.4rem; font-weight: 900; color: var(--text-main); margin-bottom: 8px; letter-spacing: -0.5px;">Institutional Queue Cleared</h3>
                <p style="color: var(--text-muted); max-width: 380px; margin: 0 auto; font-size: 0.95rem; font-weight: 600;">All stakeholder support requests have been addressed and synchronized with the academy's resolution protocols.</p>
                <a href="index.php" class="btn-premium" style="margin-top: 32px; text-decoration: none;">Return to Command Center</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if($message): ?>
            SDA.showToast("<?= $message ?>", "success");
        <?php endif; ?>
    });

    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
