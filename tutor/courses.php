<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('tutor');
$tutor = currentUser();

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

    // ── CSRF Verification (Security Protocol) ───────────────────
    if (!verifyCsrf()) {
        $error = "Security validation failed. Please refresh and try again.";
        goto end_processing;
    }
    // ────────────────────────────────────────────────────────────

    // Scholarly Slug Generator
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE courses SET title=?, slug=?, description=?, price=?, level=?, category_id=?, status=? WHERE id=? AND tutor_id=?");
            $stmt->execute([$title, $slug, $desc, $price, $level, $cat_id, $status, $id, $tutor['id']]);
            $message = "Academic track synchronized successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO courses (title, slug, description, price, level, category_id, tutor_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $desc, $price, $level, $cat_id, $tutor['id'], $status]);
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
        header("Location: courses.php?msg=" . urlencode($message));
        exit;
    } catch (Exception $e) { $error = "Track Error: " . $e->getMessage(); }
}

end_processing:

$pageTitle = 'Course Creation Center';
require_once 'includes/header.php';
?>

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

<main class="main-content">
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if (msg) {
        if (typeof SDAC !== 'undefined') {
            SDAC.showToast(msg, 'success');
        }
        window.history.replaceState(null, null, window.location.pathname);
    }
});
</script>

<style>
    /* ── Instructional Registry: Visual Architecture ── */
    :root {
        --primary: #00BFFF;
        --secondary: #FF8C00;
        --dark-card: #ffffff;
        --text-main: #0f172a;
        --text-dim: #64748b;
        --bg-institutional: #f8fafc;
        --border-elite: #e2e8f0;
        --premium-glow: 0 20px 40px -15px rgba(0, 191, 255, 0.15);
    }

    body { background: var(--bg-institutional); }

    /* Layout Header */
    .registry-header { 
        display: flex; 
        justify-content: space-between; 
        align-items: flex-end; 
        margin-bottom: 50px; 
        position: relative;
    }
    .header-info h1 { 
        font-family: 'Poppins', sans-serif; 
        font-size: 2.4rem; 
        font-weight: 800; 
        color: var(--text-main); 
        letter-spacing: -1.5px; 
        margin: 0;
    }
    .header-info p { color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1.05rem; }

    /* Course Master Grid */
    .course-registry-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); 
        gap: 32px; 
        margin-bottom: 48px; 
        max-width: 1600px;
    }

    /* Elite Course Card */
    .elite-course-card { 
        background: var(--dark-card); 
        border: 1px solid var(--border-elite); 
        border-radius: 18px; 
        overflow: hidden; 
        transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); 
        display: flex; 
        flex-direction: column; 
        position: relative;
        box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }
    .elite-course-card:hover { 
        transform: translateY(-6px); 
        box-shadow: var(--premium-glow); 
        border-color: var(--primary); 
    }

    .card-visual-hub { 
        height: 120px; 
        background: #f1f5f9; 
        position: relative; 
        overflow: hidden; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
    }
    .card-visual-hub img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
    .elite-course-card:hover .card-visual-hub img { transform: scale(1.06); }

    .status-badge-elite { 
        position: absolute; 
        top: 20px; 
        right: 20px; 
        padding: 6px 14px; 
        border-radius: 12px; 
        font-size: 0.65rem; 
        font-weight: 900; 
        text-transform: uppercase; 
        background: rgba(255,255,255,0.9); 
        backdrop-filter: blur(8px); 
        box-shadow: 0 8px 16px rgba(0,0,0,0.06);
        display: flex;
        align-items: center;
        gap: 6px;
        z-index: 10;
    }
    .status-published { color: #10b981; }
    .status-draft { color: #f59e0b; }
    .status-pending { color: var(--primary); }

    .card-intel { padding: 16px 18px; flex: 1; display: flex; flex-direction: column; }
    .intel-meta { 
        font-size: 0.63rem; 
        font-weight: 800; 
        color: var(--text-dim); 
        text-transform: uppercase; 
        letter-spacing: 1px; 
        margin-bottom: 6px; 
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .intel-title { 
        font-family: 'Poppins', sans-serif; 
        font-size: 0.95rem; 
        font-weight: 800; 
        color: var(--text-main); 
        margin-bottom: 10px; 
        line-height: 1.35; 
        min-height: 2.7rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .stat-matrix { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 8px; 
        padding: 12px 0; 
        border-top: 1px solid #f1f5f9; 
        margin-top: auto;
    }
    .matrix-item { text-align: left; }
    .matrix-lbl { display: block; font-size: 0.55rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; margin-bottom: 2px; }
    .matrix-val { font-size: 0.82rem; font-weight: 900; color: var(--text-main); }

    /* Action Dock */
    .action-dock-elite { 
        display: grid; 
        grid-template-columns: 1fr 1fr 1fr; 
        gap: 8px; 
        margin-top: 12px; 
    }
    .dock-btn { 
        height: 36px; 
        border-radius: 10px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-size: 0.72rem; 
        font-weight: 800; 
        color: var(--text-dim); 
        background: #f8fafc; 
        border: 1px solid var(--border-elite); 
        transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        text-decoration: none;
    }
    .dock-btn i { font-size: 0.85rem; }
    .dock-btn:hover { 
        background: var(--primary); 
        color: white; 
        border-color: var(--primary); 
        transform: translateY(-2px); 
        box-shadow: 0 6px 12px rgba(0, 191, 255, 0.15);
    }
    .dock-btn.primary { background: var(--primary); color: white; border: none; flex: 2; }
    .dock-btn.primary:hover { opacity: 0.95; box-shadow: 0 8px 16px rgba(0, 191, 255, 0.25); }

    /* Modal Styling Overhaul */
    .modal-elite { 
        background: white; 
        border-radius: 36px; 
        width: 100%; 
        max-width: 680px; 
        padding: 50px; 
        background: white; width: 100%; max-width: 520px; 
        border-radius: 28px; padding: 40px; position: relative; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        max-height: 90vh; overflow-y: auto;
        transform: scale(0.95); opacity: 0; animation: modalPop 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes modalPop { to { transform: scale(1); opacity: 1; } }

    .label-elite { display: block; font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
    .input-elite { 
        width: 100%; padding: 14px 18px; border: 1.5px solid #f1f5f9; border-radius: 12px; 
        font-size: 0.9rem; font-weight: 600; background: #f8fafc; transition: 0.3s;
        box-sizing: border-box; outline: none;
    }
    .input-elite:focus { border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(0, 191, 255, 0.1); }
    
    .form-group-elite { margin-bottom: 24px; }

    @media (max-width: 600px) {
        .modal-elite { padding: 32px 20px; border-radius: 20px; }
    }

    /* Custom Scrollbar */
    ::-webkit-scrollbar { width: 8px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

    @media (max-width: 768px) {
        .main-content { padding: 20px; }
        .registry-header { flex-direction: column; align-items: flex-start; gap: 24px; }
        .header-info h1 { font-size: 1.8rem; }
        .course-registry-grid { grid-template-columns: 1fr; }
    }
</style>


    <header class="registry-header">
        <div class="header-info">
            <div style="display:flex; align-items:center; gap:16px;">
                <button class="nav-toggle" onclick="toggleSidebar()" style="background:none; border:none; color:var(--text-main); font-size:1.4rem; cursor:pointer;"><i class="fas fa-bars"></i></button>
                <h1>Instructional <span style="color:var(--primary);">Registry</span></h1>
            </div>
            <p>Architect and synchronize your global curriculum tracks.</p>
        </div>
        <div>
            <button onclick="document.getElementById('courseModal').style.display='flex'" class="btn btn-primary" style="height: 54px; border-radius: 16px; padding: 0 32px; font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-plus-circle" style="font-size: 1.1rem;"></i> 
                <span>INITIATE NEW TRACK</span>
            </button>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert-elite success" style="margin-bottom: 40px; padding: 20px 28px; border-radius: 20px; background: #ecfdf5; color: #065f46; font-weight: 700; border: 1px solid #10b98133; display: flex; align-items: center; gap: 15px; animation: slideIn 0.4s ease-out;">
            <i class="fas fa-certificate" style="font-size: 1.2rem;"></i> 
            <span><?= htmlspecialchars($message) ?></span>
        </div>
        <style>@keyframes slideIn { from { transform: translateX(-20px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }</style>
    <?php endif; ?>

    <div class="course-registry-grid">
        <?php foreach($my_courses as $c): ?>
        <div class="elite-course-card">
            <div class="card-visual-hub">
                <?php if($c['thumbnail']): ?>
                    <img src="../uploads/courses/<?= $c['thumbnail'] ?>" alt="<?= htmlspecialchars($c['title']) ?>">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #cbd5e1; background: #f1f5f9;">
                        <i class="fas fa-compass" style="font-size: 3rem;"></i>
                    </div>
                <?php endif; ?>
                <div class="status-badge-elite status-<?= $c['status'] ?>">
                    <i class="fas <?= ($c['status'] == 'published' ? 'fa-globe-africa' : ($c['status'] == 'pending' ? 'fa-hourglass-half' : 'fa-pen-nib')) ?>"></i> 
                    <?= strtoupper($c['status']) ?>
                </div>
            </div>
            
            <div class="card-intel">
                <div class="intel-meta">
                    <span><?= htmlspecialchars($c['category_name']) ?></span>
                    <span style="color: var(--primary); font-family: 'Poppins'; border-left: 2px solid #e2e8f0; padding-left: 10px;"><?= strtoupper($c['level']) ?></span>
                </div>
                <h3 class="intel-title"><?= htmlspecialchars($c['title']) ?></h3>
                
                <div class="stat-matrix">
                    <div class="matrix-item">
                        <span class="matrix-lbl">ENROLLS</span>
                        <span class="matrix-val"><?= number_format($c['enrolled_count']) ?></span>
                    </div>
                    <div class="matrix-item">
                        <span class="matrix-lbl">UNITS</span>
                        <span class="matrix-val"><?= $c['total_lessons'] ?></span>
                    </div>
                    <div class="matrix-item">
                        <span class="matrix-lbl">MODEL</span>
                        <span class="matrix-val" style="color:var(--secondary);">KES <?= number_format($c['price']) ?></span>
                    </div>
                </div>

                <div class="action-dock-elite">
                    <a href="?edit_id=<?= $c['id'] ?>#courseModalTrigger" class="dock-btn" title="Track Configuration">
                        <i class="fas fa-sliders-h"></i>
                    </a>
                    <a href="lessons.php?course_id=<?= $c['id'] ?>" class="dock-btn primary">
                        <span>CURRICULUM</span>
                    </a>
                    <a href="quizzes.php?course_id=<?= $c['id'] ?>" class="dock-btn" title="Assessment Logic">
                        <i class="fas fa-lightbulb"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if(empty($my_courses)): ?>
            <div style="grid-column: 1/-1; padding: 100px; text-align: center; border: 2px dashed #e2e8f0; border-radius: 36px; background: white; box-shadow: 0 10px 30px rgba(0,0,0,0.02);">
                <div style="width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: #94a3b8;">
                    <i class="fas fa-rocket" style="font-size: 2rem;"></i>
                </div>
                <h3 style="color: #0f172a; font-weight: 800; font-size: 1.4rem;">Initiate Your Influence</h3>
                <p style="color: #94a3b8; margin: 12px auto 32px; font-size: 1rem; max-width: 400px;">Launch your first academic track and start building the future of Africa through elite education.</p>
                <button onclick="document.getElementById('courseModal').style.display='flex'" class="btn btn-primary" style="height: 50px; border-radius: 14px; padding: 0 40px; font-weight: 800;">START ARCHITECTING</button>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Unified Launch/Edit Modal -->
<div class="modal-overlay" id="courseModal" style="<?= (isset($_GET['edit_id']) || (isset($_GET['action']) && $_GET['action'] == 'new')) ? 'display:flex' : 'display:none' ?>; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
    <span id="courseModalTrigger"></span>
    <div class="modal-elite">
        <div class="modal-header-elite" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; gap: 20px;">
            <div>
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -1px;"><?= $edit_data ? 'Synchronize Track' : 'Initialize Track' ?></h3>
                <p style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">Define the core identity of this academic path.</p>
            </div>
            <button type="button" onclick="location.href='courses.php'" style="background: none; border: none; font-size: 1.4rem; color: #94a3b8; cursor: pointer; transition: 0.3s; height: 32px; width: 32px; display: flex; align-items: center; justify-content: center;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'"><i class="fas fa-times-circle"></i></button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
            <input type="hidden" name="save_course" value="1">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-group-elite">
                <label class="label-elite">Academic Title</label>
                <input type="text" name="title" class="input-elite" placeholder="e.g. Masterclass in Global Cybersecurity" value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>" required>
            </div>

            <div class="form-group-elite">
                <label class="label-elite">Curriculum Scope</label>
                <textarea name="description" class="input-elite" style="min-height: 120px; resize: none;" placeholder="Outline the learning trajectory..."><?= htmlspecialchars($edit_data['description'] ?? '') ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group-elite">
                    <label class="label-elite">Domain</label>
                    <select name="category_id" class="input-elite" required style="height: 52px; padding: 0 16px;">
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($edit_data['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>><?= $cat['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-elite">
                    <label class="label-elite">Intensity</label>
                    <select name="level" class="input-elite" style="height: 52px; padding: 0 16px;">
                        <option value="beginner" <?= ($edit_data['level'] ?? '') == 'beginner' ? 'selected' : '' ?>>Introductory</option>
                        <option value="intermediate" <?= ($edit_data['level'] ?? '') == 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                        <option value="advanced" <?= ($edit_data['level'] ?? '') == 'advanced' ? 'selected' : '' ?>>Advanced</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group-elite">
                    <label class="label-elite">Tuition (KES)</label>
                    <input type="number" name="price" class="input-elite" value="<?= $edit_data['price'] ?? 1500 ?>">
                </div>
                <div class="form-group-elite">
                    <label class="label-elite">Visibility Pillar</label>
                    <select name="status" class="input-elite" style="height: 52px; padding: 0 16px;">
                        <option value="draft" <?= ($edit_data['status'] ?? '') == 'draft' ? 'selected' : '' ?>>Draft / Internal</option>
                        <option value="pending" <?= ($edit_data['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Audit Request</option>
                        <?php if (($edit_data['status'] ?? '') == 'published'): ?>
                            <option value="published" selected>Live & Verified</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="form-group-elite">
                <label class="label-elite">Identity Visual (Thumbnail)</label>
                <input type="file" name="thumbnail" style="font-size: 0.9rem; color: #64748b; margin-top: 8px;">
            </div>

            <div style="display: flex; gap: 16px; margin-top: 40px; padding-top: 32px; border-top: 1px solid #f1f5f9;">
                <button type="button" onclick="location.href='courses.php'" class="btn btn-ghost" style="flex: 1; border-radius: 18px; height: 56px; font-weight: 700;">Dismiss</button>
                <button type="submit" class="btn btn-primary" style="flex: 2; border-radius: 18px; height: 56px; font-weight: 800; font-size: 0.95rem;">
                    <i class="fas <?= $edit_data ? 'fa-sync-alt' : 'fa-rocket' ?>" style="margin-right: 10px;"></i>
                    <?= $edit_data ? 'SYNCHRONIZE TRACK' : 'LAUNCH TRACK' ?>
                </button>
            </div>
        </form>
    </div>
</div>

</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
