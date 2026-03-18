<?php
$pageTitle = 'Course Repository Management';
require_once '../includes/header.php';

// Auth check
if ($user['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_course'])) {
    $c_id = $_POST['id'];
    try {
        $stmt = $pdo->prepare("UPDATE courses SET status = 'published' WHERE id = ?");
        $stmt->execute([$c_id]);
        $success_msg = "Course approved and published successfully.";
    } catch (Exception $e) { $error_msg = "Error: " . $e->getMessage(); }
}

$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Global Course Query
    $query = "SELECT c.*, u.name as tutor_name, cat.name as category_name,
                     (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as actual_enrolled,
                     (SELECT SUM(amount) FROM payments WHERE course_id = c.id AND status = 'verified') as revenue
              FROM courses c
              JOIN users u ON c.tutor_id = u.id
              LEFT JOIN categories cat ON c.category_id = cat.id
              WHERE 1=1";
    
    $params = [];
    if ($status_filter) {
        $query .= " AND c.status = ?";
        $params[] = $status_filter;
    }
    if ($search) {
        $query .= " AND (c.title LIKE ? OR u.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $query .= " ORDER BY c.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $courses = $stmt->fetchAll();

    // Stats
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    $pending_review = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'pending'")->fetchColumn();

} catch (Exception $e) { $courses = []; }
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .admin-courses-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 40px; }
    .course-mini-card { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 24px; display: flex; flex-direction: column; transition: 0.3s; position: relative; }
    .course-mini-card:hover { transform: translateY(-5px); box-shadow: var(--shadow); border-color: var(--primary); }
    
    .course-badge { position: absolute; top: 20px; right: 20px; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
    .status-published { background: #DCFCE7; color: #166534; }
    .status-pending { background: #FEF9C3; color: #854D0E; }
    .status-draft { background: #F1F5F9; color: #475569; }
    
    .course-meta { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .tutor-avatar-sm { width: 32px; height: 32px; border-radius: 50%; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; }
    
    .course-stats-row { display: flex; justify-content: space-between; padding-top: 16px; border-top: 1px solid #f1f5f9; margin-top: auto; }
    .stat-item { text-align: center; }
    .stat-val { display: block; font-weight: 800; color: var(--dark); font-size: 0.95rem; }
    .stat-lbl { font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; }

    .filter-pills { display: flex; gap: 12px; margin-bottom: 32px; overflow-x: auto; padding-bottom: 10px; -webkit-overflow-scrolling: touch; }
    .filter-pill { padding: 10px 24px; border-radius: 12px; font-size: 0.88rem; font-weight: 600; text-decoration: none; background: white; border: 1px solid var(--dark-border); color: var(--text-muted); transition: 0.3s; white-space: nowrap; }
    .filter-pill:hover, .filter-pill.active { background: var(--primary); color: white; border-color: var(--primary); }

    .search-container { position: relative; width: 100%; max-width: 320px; }
    .search-input { width: 100%; padding: 12px 16px 12px 42px; border-radius: 14px; border: 1px solid var(--dark-border); font-size: 0.9rem; outline: none; transition: 0.3s; }
    .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--primary-glow); }

    @media (max-width: 1200px) { .admin-courses-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 768px) { 
        .admin-header { flex-direction: column; align-items: flex-start !important; gap: 24px; }
        .desktop-actions { width: 100%; flex-direction: column; }
        .search-container { max-width: 100%; }
        .admin-courses-grid { grid-template-columns: 1fr; }
        .main-content { padding: 30px 20px; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Academy <span class="text-primary">Catalog</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px; font-size: 0.9rem;">Global curriculum oversight and instructional quality control.</p>
            </div>
        </div>
        <div class="desktop-actions" style="display: flex; gap: 12px;">
            <form action="courses.php" method="GET" class="search-container">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Filter by title or instructor..." class="search-input">
                <i class="fas fa-search" style="position: absolute; left: 16px; top: 15px; color: var(--text-dim);"></i>
            </form>
            <button class="btn btn-primary" style="border-radius: 12px; padding: 0 24px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-plus"></i> <span>Manual Add</span></button>
        </div>
    </header>

    <div class="filter-pills">
        <a href="courses.php" class="filter-pill <?= !$status_filter ? 'active' : '' ?>">All Streams (<?= $total_courses ?>)</a>
        <a href="courses.php?status=pending" class="filter-pill <?= $status_filter == 'pending' ? 'active' : '' ?>">Review Required (<?= $pending_review ?>)</a>
        <a href="courses.php?status=published" class="filter-pill <?= $status_filter == 'published' ? 'active' : '' ?>">Published</a>
        <a href="courses.php?status=archived" class="filter-pill <?= $status_filter == 'archived' ? 'active' : '' ?>">Archive</a>
    </div>

    <div class="admin-courses-grid">
        <?php if(!empty($courses)): ?>
            <?php foreach($courses as $c): ?>
            <div class="course-mini-card">
                <span class="course-badge status-<?= $c['status'] ?>"><?= $c['status'] ?></span>
                
                <div class="course-meta">
                    <div class="tutor-avatar-sm"><?= strtoupper(substr($c['tutor_name'], 0, 1)) ?></div>
                    <div>
                        <div style="font-size: 0.8rem; color: var(--text-dim);"><?= htmlspecialchars($c['category_name']) ?></div>
                        <div style="font-weight: 700; font-size: 0.85rem; color: var(--dark);"><?= htmlspecialchars($c['tutor_name']) ?></div>
                    </div>
                </div>

                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.05rem; margin-bottom: 12px; line-height: 1.4; flex: 1;"><?= htmlspecialchars($c['title']) ?></h3>

                <div class="course-stats-row">
                    <div class="stat-item">
                        <span class="stat-val"><?= number_format($c['actual_enrolled']) ?></span>
                        <span class="stat-lbl">Enrolls</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-val">KES <?= number_format($c['revenue'] ?: 0) ?></span>
                        <span class="stat-lbl">Revenue</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-val"><?= $c['avg_rating'] ?> <i class="fas fa-star" style="color:#F7941D; font-size: 0.7rem;"></i></span>
                        <span class="stat-lbl">Rating</span>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 20px;">
                    <a href="course_details.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm" style="border-radius: 8px;">View Stats</a>
                    <?php if($c['status'] === 'pending'): ?>
                        <form method="POST" action="courses.php">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button name="approve_course" class="btn btn-primary btn-sm" style="border-radius: 8px; background: #10B981; border-color: #10B981; width: 100%;">Approve</button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-ghost btn-sm" style="border-radius: 8px;">Edit Settings</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 80px 0; background: white; border-radius: 24px; border: 1px dashed var(--dark-border);">
                <i class="fas fa-layer-group" style="font-size: 3rem; color: var(--primary-glow); margin-bottom: 20px;"></i>
                <h3 style="color: var(--dark);">No courses found.</h3>
                <p style="color: var(--text-dim);">Try adjusting your search or filters.</p>
            </div>
        <?php endif; ?>
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
</script>
</body>
</html>
