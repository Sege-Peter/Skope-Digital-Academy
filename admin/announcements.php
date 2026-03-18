<?php
$pageTitle = 'Manage Announcements';
require_once 'includes/header.php';

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
                $stmt->execute([$title, $content, $start_date, $end_date, $is_pinned, $admin['id']]);
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
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Portal <span class="text-primary">Notices</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">System-wide announcements and instructional bulletins.</p>
            </div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('announceFormWrap').scrollIntoView({behavior:'smooth'})">
            <i class="fas fa-plus"></i> New Announcement
        </button>
    </header>
    
    <div class="admin-body">

        <div class="grid-2" style="grid-template-columns: 1fr 1.5fr; gap: 32px; align-items: start;">
            <!-- Form Area -->
            <div id="announceFormWrap" class="table-card" style="position: sticky; top: 100px;">
                <div class="table-header">
                    <h3 style="font-size: 1rem;"><?= $edit_data ? 'Edit' : 'Create' ?> Announcement</h3>
                </div>
                <div style="padding: 24px;">
                    <form method="POST" action="announcements.php">
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. New Web Dev Course Launch!" value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="form-control" style="min-height: 150px;" placeholder="Details about notice..." required><?= htmlspecialchars($edit_data['content'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="grid-2" style="gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" value="<?= $edit_data['start_date'] ?? date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Expiry Date (Optional)</label>
                                <input type="date" name="end_date" class="form-control" value="<?= $edit_data['end_date'] ?? '' ?>">
                            </div>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                            <input type="checkbox" name="is_pinned" id="is_pinned" style="width: 18px; height: 18px;" <?= ($edit_data['is_pinned'] ?? 0) ? 'checked' : '' ?>>
                            <label for="is_pinned" style="font-size: 0.9rem; cursor: pointer;">Pin to Homepage Banner</label>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 24px;">
                            <button type="submit" name="save_announcement" class="btn btn-primary btn-block">
                                <?= $edit_data ? 'Update' : 'Post' ?> Announcement
                            </button>
                            <?php if($edit_data): ?>
                                <a href="announcements.php" class="btn btn-ghost" style="flex: 0 0 auto;">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List Area -->
            <div class="table-card">
                <div class="table-header">
                    <h3 style="font-size: 1rem;">Past Announcements</h3>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Dates</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($announcements as $a): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; font-size: 0.92rem;"><?= htmlspecialchars($a['title']) ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-dim); line-height: 1.4; margin-top: 4px;">
                                        <?= htmlspecialchars(substr($a['content'], 0, 80)) ?>...
                                    </div>
                                </td>
                                <td>
                                    <?php if($a['is_pinned']): ?>
                                        <span class="badge badge-primary"><i class="fas fa-thumbtack"></i> Pinned</span>
                                    <?php else: ?>
                                        <span class="badge badge-muted">Standard</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.8rem;">
                                        <span style="color: var(--success);">Start:</span> <?= date('M j', strtotime($a['start_date'])) ?><br>
                                        <span style="color: var(--danger);">Expiry:</span> <?= $a['end_date'] ? date('M j', strtotime($a['end_date'])) : 'Never' ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="?edit_id=<?= $a['id'] ?>" class="btn btn-ghost btn-sm" style="padding: 6px;"><i class="fas fa-edit"></i></a>
                                        <a href="?delete_id=<?= $a['id'] ?>" class="btn btn-ghost btn-sm" style="padding: 6px; color: var(--danger);" onclick="return confirm('Delete this announcement?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($announcements)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 60px; color: var(--text-dim);">No announcements yet.</td>
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
        SDA.confirmAction('Are you sure you want to permanently delete this announcement? This action is irreversible.', () => {
            window.location.href = 'announcements.php?delete_id=' + id;
        });
    }
</script>
</body>
</html>
