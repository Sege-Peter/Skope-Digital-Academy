<?php
$pageTitle = 'Support Intelligence Center';
require_once '../includes/header.php';
requireRole('admin');

// Handle actions (Status update)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticket_id = $_POST['ticket_id'] ?? 0;
    $new_status = $_POST['status'] ?? 'open';
    try {
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $ticket_id]);
        $message = "Ticket #$ticket_id status updated to " . ucfirst(str_replace('_', ' ', $new_status));
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

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .ticket-card { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px; margin-bottom: 24px; transition: 0.3s; position: relative; }
    .ticket-card:hover { border-color: var(--primary); box-shadow: var(--shadow); transform: translateY(-3px); }
    .ticket-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--bg-light); padding-bottom: 16px; }
    .ticket-subject { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.2rem; color: var(--dark); margin-bottom: 12px; }
    .ticket-body { font-size: 0.95rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 24px; white-space: pre-wrap; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9; }
    
    .status-pill { padding: 6px 14px; border-radius: 10px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-open { background: #fee2e2; color: #b91c1c; }
    .status-in_progress { background: #fef3c7; color: #b45309; }
    .status-closed { background: #dcfce7; color: #15803d; }
    
    .filter-bar { display: flex; gap: 12px; margin-bottom: 40px; flex-wrap: wrap; }
    .user-info-box { display: flex; align-items: center; gap: 10px; }
    .user-avatar-mini { width: 32px; height: 32px; background: var(--primary-glow); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 800; font-size: 0.8rem; }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Resolution <span class="text-primary">Hub</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Monitor and resolve technical and academic support requests from the community.</p>
            </div>
        </div>
    </header>

    <?php if($message): ?>
        <div style="padding: 16px 24px; background: var(--primary-glow); color: var(--primary); border-radius: 16px; margin-bottom: 32px; font-weight: 700; border: 1px solid var(--primary);">
            <i class="fas fa-check-circle"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="?status=all" class="btn <?= ($status_filter === 'all') ? 'btn-primary' : 'btn-ghost' ?> btn-sm" style="border-radius: 10px;">All Streams</a>
        <a href="?status=open" class="btn <?= ($status_filter === 'open') ? 'btn-primary' : 'btn-ghost' ?> btn-sm" style="border-radius: 10px;">Queue: Open</a>
        <a href="?status=in_progress" class="btn <?= ($status_filter === 'in_progress') ? 'btn-primary' : 'btn-ghost' ?> btn-sm" style="border-radius: 10px;">Active: In Progress</a>
        <a href="?status=closed" class="btn <?= ($status_filter === 'closed') ? 'btn-primary' : 'btn-ghost' ?> btn-sm" style="border-radius: 10px;">Archived: Closed</a>
    </div>

    <div class="tickets-container">
        <?php foreach($tickets as $t): ?>
            <div class="ticket-card">
                <div class="ticket-meta">
                    <div class="user-info-box">
                        <div class="user-avatar-mini"><?= strtoupper(substr($t['user_name'] ?? 'G', 0, 1)) ?></div>
                        <div>
                            <div style="font-size: 0.88rem; font-weight: 800; color: var(--dark);"><?= htmlspecialchars($t['user_name'] ?? 'External Stakeholder') ?></div>
                            <div style="font-size: 0.72rem; color: var(--text-dim);"><?= htmlspecialchars($t['user_email'] ?? 'Contact info hidden') ?></div>
                        </div>
                    </div>
                    <div>
                        <span class="status-pill status-<?= $t['status'] ?>"><?= str_replace('_', ' ', $t['status']) ?></span>
                        <div style="font-size: 0.65rem; color: var(--text-dim); text-align: right; margin-top: 6px;">#<?= $t['id'] ?> • <?= date('M j, Y H:i', strtotime($t['created_at'])) ?></div>
                    </div>
                </div>
                
                <h3 class="ticket-subject"><?= htmlspecialchars($t['subject']) ?></h3>
                <div class="ticket-body"><?= nl2br(htmlspecialchars($t['message'])) ?></div>
                
                <form method="POST" style="display: flex; gap: 16px; align-items: center; border-top: 1px solid var(--bg-light); padding-top: 20px;">
                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                    <input type="hidden" name="update_status" value="1">
                    <div style="flex: 1; display: flex; align-items: center; gap: 12px;">
                        <label style="font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Mark Status:</label>
                        <select name="status" class="form-control" style="width: 180px; height: 44px; border-radius: 12px; border: 1px solid var(--dark-border); background: white; font-weight: 600; font-size: 0.9rem;">
                            <option value="open" <?= ($t['status'] === 'open') ? 'selected' : '' ?>>Open Queue</option>
                            <option value="in_progress" <?= ($t['status'] === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                            <option value="closed" <?= ($t['status'] === 'closed') ? 'selected' : '' ?>>Resolution Found (Closed)</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" style="border-radius: 10px; padding: 10px 24px;">Update</button>
                    </div>
                    
                    <?php if($t['user_email']): ?>
                        <a href="mailto:<?= $t['user_email'] ?>?subject=Re: <?= urlencode($t['subject']) ?>" class="btn btn-ghost btn-sm" style="border-radius: 10px; padding: 10px 20px;">
                            <i class="fas fa-paper-plane" style="margin-right: 6px;"></i> Support Response
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>

        <?php if(empty($tickets)): ?>
            <div style="text-align: center; padding: 100px 40px; background: white; border: 1px dashed var(--dark-border); border-radius: 40px; box-shadow: var(--shadow-sm);">
                <div style="width: 80px; height: 80px; background: var(--bg-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-dim); margin: 0 auto 24px; font-size: 2.5rem;">
                    <i class="fas fa-check-double"></i>
                </div>
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; color: var(--dark); margin-bottom: 8px;">Zero Pending Issues</h3>
                <p style="color: var(--text-dim); max-width: 320px; margin: 0 auto;">Excellent work! All community support tickets have been architecturaly addressed.</p>
                <a href="index.php" class="btn btn-primary btn-sm" style="margin-top: 32px; border-radius: 12px;">Back to Control Center</a>
            </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
