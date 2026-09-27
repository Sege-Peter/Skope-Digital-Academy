<?php
$pageTitle = 'Institutional Broadcast Command';
require_once 'includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle Create/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $start_date = $_POST['start_date'] ?: date('Y-m-d');
    $end_date = $_POST['end_date'] ?: null;
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        $error_msg = "Title and content are required.";
    } else {
        try {
            if ($id) {
                // Update
                $stmt = $pdo->prepare("UPDATE announcements SET title=?, content=?, start_date=?, end_date=?, is_pinned=? WHERE id=?");
                $stmt->execute([$title, $content, $start_date, $end_date, $is_pinned, $id]);
                $success_msg = "Announcement updated successfully.";
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO announcements (title, content, start_date, end_date, is_pinned, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $content, $start_date, $end_date, $is_pinned, $user['id']]);
                $success_msg = "New announcement posted.";
            }
        } catch (Exception $e) {
            $error_msg = "Error saving announcement: " . $e->getMessage();
        }
    }
}

// Handle Delete
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id=?");
        $stmt->execute([$_GET['delete_id']]);
        $success_msg = "Announcement deleted.";
    } catch (Exception $e) {
        $error_msg = "Error deleting announcement: " . $e->getMessage();
    }
}

// Fetch all announcements
try {
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY is_pinned DESC, created_at DESC")->fetchAll();
} catch (Exception $e) { $announcements = []; }

// Edit mode fetch
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id=?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_data = $stmt->fetch();
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px;">Portal <span>Notices</span></h1>
                <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Institutional Broadcast • Skope Digital Academy</p>
            </div>
        </div>
        
        <button class="btn-premium" onclick="document.getElementById('announceFormWrap').scrollIntoView({behavior:'smooth'})">
            <i class="fas fa-plus"></i> Initialize Broadcast
        </button>
    </header>

    <div class="admin-body">
        <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 32px; align-items: start;">
            <!-- Form Area -->
            <div id="announceFormWrap" class="premium-card" style="position: sticky; top: 100px; padding: 0; overflow: hidden;">
                <div style="padding: 24px 32px; border-bottom: 1.5px solid var(--border-light);">
                    <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px;"><?= $edit_data ? 'Edit Broadcast' : 'Broadcast Architect' ?></h3>
                    <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Compose Institutional Notices</p>
                </div>
                <div style="padding: 32px;">
                    <form method="POST" action="announcements.php" style="display: flex; flex-direction: column; gap: 24px;">
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                        
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Broadcast Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Q3 Academic Excellence Symposium" value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>" required style="background: var(--bg-main); border: 1.5px solid var(--border); font-weight: 700;">
                        </div>
                        
                        <div>
                            <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Scholarly Content</label>
                            <textarea name="content" class="form-control" style="min-height: 180px; background: var(--bg-main); border: 1.5px solid var(--border); font-weight: 600; line-height: 1.6;" placeholder="Disseminate critical intelligence here..." required><?= htmlspecialchars($edit_data['content'] ?? '') ?></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div>
                                <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Activation Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?= $edit_data['start_date'] ?? date('Y-m-d') ?>" style="background: var(--bg-main); border: 1.5px solid var(--border); font-weight: 700;">
                            </div>
                            <div>
                                <label style="display:block;font-size:0.65rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Deactivation Date</label>
                                <input type="date" name="end_date" class="form-control" value="<?= $edit_data['end_date'] ?? '' ?>" style="background: var(--bg-main); border: 1.5px solid var(--border); font-weight: 700;">
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--bg-main); border-radius: 12px; border: 1.5px solid var(--border-light);">
                            <input type="checkbox" name="is_pinned" id="is_pinned" style="width: 20px; height: 20px; accent-color: var(--primary);" <?= ($edit_data['is_pinned'] ?? 0) ? 'checked' : '' ?>>
                            <label for="is_pinned" style="font-size: 0.85rem; font-weight: 800; cursor: pointer; color: var(--text-main);">PIN TO EXECUTIVE BANNER</label>
                        </div>
                        
                        <div style="display: flex; gap: 12px; margin-top: 12px;">
                            <button type="submit" name="save_announcement" class="btn-premium" style="flex: 1; justify-content: center;">
                                <?= $edit_data ? 'Update Broadcast' : 'Deploy Broadcast' ?>
                            </button>
                            <?php if($edit_data): ?>
                                <a href="announcements.php" class="btn-premium" style="background: var(--bg-main); color: var(--text-main); border: 1.5px solid var(--border); box-shadow: none; text-decoration: none;">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List Area -->
            <div class="premium-card" style="padding: 0; overflow: hidden;">
                <div style="padding: 24px 32px; border-bottom: 1.5px solid var(--border-light);">
                    <h3 style="font-size: 1.1rem; font-weight: 900; letter-spacing: -0.3px;">Broadcast Registry</h3>
                    <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Institutional Intelligence Archive</p>
                </div>
                <div style="overflow-x: auto;">
                    <table class="premium-table">
                        <thead>
                            <tr>
                                <th>BROADCAST IDENTITY</th>
                                <th>STATUS</th>
                                <th>LIFESPAN</th>
                                <th style="text-align: right;">OPERATIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($announcements as $a): ?>
                            <tr>
                                <td style="max-width: 300px;">
                                    <div style="font-weight: 900; color: var(--text-main); font-size: 0.95rem; margin-bottom: 6px;"><?= htmlspecialchars($a['title']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; font-weight: 600;">
                                        <?= htmlspecialchars(substr($a['content'], 0, 100)) ?>...
                                    </div>
                                </td>
                                <td>
                                    <?php if($a['is_pinned']): ?>
                                        <div class="badge-premium" style="background: var(--primary-glow); color: var(--primary); border: none;"><i class="fas fa-thumbtack"></i> PINNED</div>
                                    <?php else: ?>
                                        <div class="badge-premium" style="background: var(--bg-main); color: var(--text-muted); border: 1.5px solid var(--border-light);">STANDARD</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.75rem; font-weight: 800;">
                                        <div style="color: var(--success); margin-bottom: 4px;">START: <?= date('M j, Y', strtotime($a['start_date'])) ?></div>
                                        <div style="color: var(--danger);">EXPIRY: <?= $a['end_date'] ? date('M j, Y', strtotime($a['end_date'])) : 'PERPETUAL' ?></div>
                                    </div>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                        <a href="?edit_id=<?= $a['id'] ?>" class="avatar-sm" style="width: 32px; height: 32px; background: var(--bg-main); border-color: var(--border-light); color: var(--text-main);">
                                            <i class="fas fa-pen-nib" style="font-size: 0.75rem;"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?= $a['id'] ?>)" class="avatar-sm" style="width: 32px; height: 32px; background: var(--danger-glow); border: none; color: var(--danger); cursor: pointer;">
                                            <i class="fas fa-trash-alt" style="font-size: 0.75rem;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($announcements)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 80px;">
                                    <div class="avatar-sm" style="width: 60px; height: 60px; margin: 0 auto 20px; background: var(--bg-main); color: var(--text-muted); font-size: 1.5rem;"><i class="fas fa-broadcast-tower"></i></div>
                                    <div style="font-weight: 800; color: var(--text-muted); font-size: 0.9rem;">No institutional broadcasts identified.</div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

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

    function confirmDelete(id) {
        SDA.confirmAction('Permanently deactivate and delete this institutional broadcast?', () => {
            window.location.href = 'announcements.php?delete_id=' + id;
        });
    }

    function toggleSidebar() {
        document.getElementById('dashSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
</script>
</body></html>
