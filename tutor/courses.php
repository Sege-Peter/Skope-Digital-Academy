<?php
$pageTitle = 'Course Creation Center';
require_once 'includes/header.php';

$message = '';
$error = '';

// 1. Handle Create/Update Course
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $level = $_POST['level'];
    $cat_id = (int)$_POST['category_id'];
    $status = $_POST['status'] ?? 'draft';

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE courses SET title=?, description=?, price=?, level=?, category_id=?, status=? WHERE id=? AND tutor_id=?");
            $stmt->execute([$title, $desc, $price, $level, $cat_id, $status, $id, $tutor['id']]);
            $message = "Academic track synchronized successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO courses (title, description, price, level, category_id, tutor_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $price, $level, $cat_id, $tutor['id'], $status]);
            $id = $pdo->lastInsertId();
            $message = "New scholarly track initialized!";
        }
        
        // Handle Thumbnail Upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === 0) {
            $file = $_FILES['thumbnail'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $filename = "COURSE_" . $id . "." . $ext;
                if (!is_dir('../uploads/courses/')) mkdir('../uploads/courses/', 0777, true);
                if (move_uploaded_file($file['tmp_name'], "../uploads/courses/" . $filename)) {
                    $pdo->prepare("UPDATE courses SET thumbnail=? WHERE id=?")->execute([$filename, $id]);
                }
            }
        }
        header("Location: courses.php?msg=success");
        exit;
    } catch (Exception $e) { $error = "Track Error: " . $e->getMessage(); }
}

// 2. Fetch Data
try {
    $stmt = $pdo->prepare("SELECT c.*, cat.name as category_name FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.tutor_id = ? ORDER BY c.created_at DESC");
    $stmt->execute([$tutor['id']]);
    $my_courses = $stmt->fetchAll();
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
} catch (Exception $e) { $my_courses = []; $categories = []; }

if (isset($_GET['msg']) && $_GET['msg'] == 'success') $message = "Academic track synchronized successfully!";

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id=? AND tutor_id=?");
    $stmt->execute([$_GET['edit_id'], $tutor['id']]);
    $edit_data = $stmt->fetch();
}
?>

<?php require_once 'includes/sidebar.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (new URLSearchParams(window.location.search).get('msg')) {
        if (typeof SDAC !== 'undefined') {
            SDAC.showToast('Academic track synchronized!', 'success');
        }
    }
});
</script>

<style>
    :root {
        --primary-blue: #00BFFF;
        --secondary-orange: #FF8C00;
        --border-color: #e2e8f0;
        --bg-light: #f8fafc;
    }
    .main-content { background: #fff; padding: 40px; }
    
    .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 32px; margin-bottom: 60px; }
    
    .course-card { background: #fff; border: 1px solid var(--border-color); border-radius: 28px; overflow: hidden; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); position: relative; }
    .course-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.06); border-color: var(--primary-blue); }
    
    .card-thumb { height: 200px; background: #f1f5f9; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .card-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .status-chip { position: absolute; top: 16px; right: 16px; padding: 6px 14px; border-radius: 10px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .status-published { color: #10b981; }
    .status-draft { color: #f59e0b; }

    .card-body { padding: 32px; }
    .card-cat { font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px; display: block; }
    .card-title { font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 16px; line-height: 1.3; height: 52px; overflow: hidden; }
    
    .card-stats { display: flex; justify-content: space-between; padding: 16px 0; border-top: 1px solid #f1f5f9; border-bottom: 1px solid #f1f5f9; margin-bottom: 24px; }
    .stat-item { display: flex; flex-direction: column; gap: 4px; }
    .stat-label { font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
    .stat-val { font-size: 0.9rem; font-weight: 800; color: #475569; }

    /* Action Buttons */
    .card-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
    .act-btn { height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; text-decoration: none; transition: 0.2s; border: 1px solid var(--border-color); color: #64748b; }
    .act-btn i { font-size: 0.9rem; margin-right: 6px; }
    .act-btn:hover { background: #f8fafc; color: var(--primary-blue); border-color: var(--primary-blue); }
    .act-btn.primary { background: var(--primary-blue); color: #fff; border: none; }
    .act-btn.primary:hover { opacity: 0.9; transform: scale(1.05); }

    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
    .modal-content { background: #fff; border-radius: 32px; width: 100%; max-width: 700px; padding: 48px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }

    .form-input { width: 100%; border: 1px solid var(--border-color); border-radius: 14px; padding: 14px 18px; background: var(--bg-light); font-size: 0.93rem; transition: 0.3s; margin-bottom: 24px; }
    .form-input:focus { outline: none; border-color: var(--primary-blue); background: #fff; box-shadow: 0 0 0 4px rgba(0,191,255,0.08); }
</style>

<main class="main-content">
    <header style="margin-bottom: 48px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 800; color: #0f172a;">Track <span style="color: var(--primary-blue);">Management</span></h1>
            <p style="color: #64748b; margin-top: 4px;">Initialize, update, and manage your academic offerings within the SDAC platform.</p>
        </div>
        <button onclick="document.getElementById('courseModal').style.display='flex'" class="btn btn-primary" style="height: 58px; padding: 0 32px; border-radius: 18px; font-weight: 800; background: var(--primary-blue); border: none; box-shadow: 0 10px 20px rgba(0,191,255,0.2);">
            <i class="fas fa-rocket"></i> Launch New Track
        </button>
    </header>

    <?php if ($message): ?>
        <div style="padding: 18px 24px; border-radius: 16px; background: #ecfdf5; color: #065f46; font-weight: 700; margin-bottom: 40px; border: 1px solid #10b98133; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-certificate"></i> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="course-grid">
        <?php foreach($my_courses as $c): ?>
        <div class="course-card">
            <div class="card-thumb">
                <?php if($c['thumbnail']): ?>
                    <img src="../uploads/courses/<?= $c['thumbnail'] ?>" alt="">
                <?php else: ?>
                    <i class="fas fa-terminal" style="font-size: 3rem; color: #cbd5e1;"></i>
                <?php endif; ?>
                <span class="status-chip <?= $c['status'] == 'published' ? 'status-published' : 'status-draft' ?>">
                    <i class="fas <?= $c['status'] == 'published' ? 'fa-eye' : 'fa-eye-slash' ?>"></i> <?= ucfirst($c['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <span class="card-cat"><?= htmlspecialchars($c['category_name']) ?> ⋅ <?= ucfirst($c['level']) ?></span>
                <h3 class="card-title"><?= htmlspecialchars($c['title']) ?></h3>
                
                <div class="card-stats">
                    <div class="stat-item">
                        <span class="stat-label">Enrollment</span>
                        <span class="stat-val"><?= $c['enrolled_count'] ?> Scholars</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Curriculum</span>
                        <span class="stat-val"><?= $c['total_lessons'] ?> Modules</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Value</span>
                        <span class="stat-val">KES <?= number_format($c['price']) ?></span>
                    </div>
                </div>

                <div class="card-actions">
                    <a href="?edit_id=<?= $c['id'] ?>#courseModalTrigger" onclick="document.getElementById('courseModal').style.display='flex'" class="act-btn" title="Edit Metadata"><i class="fas fa-cog"></i> Edit</a>
                    <a href="lessons.php?course_id=<?= $c['id'] ?>" class="act-btn primary" title="Manage Content"><i class="fas fa-book-open"></i> Lessons</a>
                    <a href="quizzes.php?course_id=<?= $c['id'] ?>" class="act-btn" title="Assessment Center"><i class="fas fa-brain"></i> Quizzes</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if(empty($my_courses)): ?>
            <div style="grid-column: 1/-1; padding: 120px 40px; text-align: center; border: 2px dashed #e2e8f0; border-radius: 40px;">
                <i class="fas fa-map-marked-alt" style="font-size: 4rem; color: #f1f5f9; margin-bottom: 24px;"></i>
                <h3 style="color: #64748b; font-weight: 800;">No Academic Tracks Initialized</h3>
                <p style="color: #94a3b8; margin-top: 10px;">Begin your journey as a mentor by launching your first course today.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Unified Launch/Edit Modal -->
<div class="modal-overlay" id="courseModal" style="<?= isset($_GET['edit_id']) ? 'display:flex' : '' ?>">
    <div class="modal-content">
        <h2 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.6rem; margin-bottom: 32px; color: #0f172a;"><?= $edit_data ? 'Synchronize Track' : 'Launch New Track' ?></h2>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
            <input type="hidden" name="save_course" value="1">

            <div style="font-size: 0.75rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Track Essentials</div>
            <input type="text" name="title" class="form-input" placeholder="Title (e.g. Advanced Cybersecurity Frameworks)" value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>" required>
            <textarea name="description" class="form-input" style="min-height: 100px; resize: none;" placeholder="Educational Objectives..."><?= htmlspecialchars($edit_data['description'] ?? '') ?></textarea>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <div style="font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Domain</div>
                    <select name="category_id" class="form-input" required>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($edit_data['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= $cat['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <div style="font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Intensity Level</div>
                    <select name="level" class="form-input">
                        <option value="beginner" <?= ($edit_data['level'] ?? '') == 'beginner' ? 'selected' : '' ?>>Beginner</option>
                        <option value="intermediate" <?= ($edit_data['level'] ?? '') == 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                        <option value="advanced" <?= ($edit_data['level'] ?? '') == 'advanced' ? 'selected' : '' ?>>Advanced</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <div style="font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Tuition (KES)</div>
                    <input type="number" name="price" class="form-input" value="<?= $edit_data['price'] ?? 1500 ?>">
                </div>
                <div class="form-group">
                    <div style="font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Visibility</div>
                    <select name="status" class="form-input">
                        <option value="draft" <?= ($edit_data['status'] ?? '') == 'draft' ? 'selected' : '' ?>>Draft Mode</option>
                        <option value="published" <?= ($edit_data['status'] ?? '') == 'published' ? 'selected' : '' ?>>Live Access</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <div style="font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">Track Asset (Thumbnail)</div>
                <input type="file" name="thumbnail" class="form-input" style="padding: 10px;">
            </div>

            <div style="display: flex; gap: 12px; margin-top: 10px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; height: 58px; background: var(--primary-blue); border: none; border-radius: 16px; font-weight: 800;">
                    <?= $edit_data ? 'Synchronize Data' : 'Launch Academic Track' ?>
                </button>
                <button type="button" onclick="location.href='courses.php'" class="btn" style="height: 58px; background: #f1f5f9; color: #475569; border-radius: 16px; padding: 0 24px; font-weight: 700; border: none;">Dismiss</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/main.js"></script>

</body>
</html>
