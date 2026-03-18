<?php
$pageTitle = 'Curriculum & Lessons';
require_once 'includes/header.php';

$course_id = (int)($_GET['course_id'] ?? 0);
if (!$course_id) { header('Location: courses.php'); exit; }

// Verify ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND tutor_id = ?");
    $stmt->execute([$course_id, $tutor['id']]);
    $course = $stmt->fetch();
    if (!$course) { header('Location: courses.php'); exit; }
} catch (Exception $e) { header('Location: courses.php'); exit; }

$message = '';
$error = '';

// 1. Handle Create/Update Lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $video_url = trim($_POST['video_url']);
    $type = $_POST['lesson_type'] ?? 'video';
    $duration = (int)($_POST['duration_mins'] ?? 0);
    $order = (int)($_POST['order_num'] ?? 1);

    try {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE lessons SET title=?, content=?, file_url=?, lesson_type=?, duration_mins=?, order_num=? WHERE id=?");
            $stmt->execute([$title, $content, $video_url, $type, $duration, $order, $id]);
            $message = "Lesson details synchronized successfully!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, content, file_url, lesson_type, duration_mins, order_num) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $title, $content, $video_url, $type, $duration, $order]);
            $message = "New lesson added to curriculum!";
            
            // Increment lesson count
            $pdo->prepare("UPDATE courses SET total_lessons = total_lessons + 1 WHERE id = ?")->execute([$course_id]);
        }
        // Redirect to avoid resubmit
        header("Location: lessons.php?course_id=$course_id&msg=success");
        exit;
    } catch (Exception $e) { $error = "System Error: " . $e->getMessage(); }
}

// 2. Handle Delete Lesson
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM lessons WHERE id=?");
        $stmt->execute([$_GET['delete_id']]);
        $pdo->prepare("UPDATE courses SET total_lessons = total_lessons - 1 WHERE id = ?")->execute([$course_id]);
        header("Location: lessons.php?course_id=$course_id&msg=deleted");
        exit;
    } catch (Exception $e) { $error = "Error during removal: " . $e->getMessage(); }
}

// 3. Fetch Lessons
$stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_num ASC");
$stmt->execute([$course_id]);
$lessons = $stmt->fetchAll();

// Edit mode fetch
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id=?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_data = $stmt->fetch();
}

$msg_type = $_GET['msg'] ?? '';
if ($msg_type == 'success') $message = "Curriculum synchronized successfully!";
if ($msg_type == 'deleted') $message = "Lesson removed from sequence.";
?>

<?php require_once 'includes/sidebar.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (new URLSearchParams(window.location.search).get('msg')) {
        if (typeof SDAC !== 'undefined') {
            SDAC.showToast('Curriculum synchronized!', 'success');
        }
    }
});
</script>

<style>
    :root {
        --primary-blue: #00BFFF;
        --secondary-orange: #FF8C00;
        --border-light: #e2e8f0;
        --bg-light: #f8fafc;
    }
    .main-content { padding: 40px; background: #fff; }
    
    .lesson-grid { display: grid; grid-template-columns: 1fr 420px; gap: 40px; align-items: start; }
    
    .curriculum-wrap { background: #fff; border: 1px solid var(--border-light); border-radius: 24px; overflow: hidden; }
    .curriculum-header { padding: 24px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
    
    .lesson-card {
        padding: 20px 24px; border-bottom: 1px solid var(--border-light);
        display: flex; align-items: center; gap: 20px;
        transition: 0.3s; cursor: grab;
    }
    .lesson-card:hover { background: #f0f9ff; }
    .lesson-card:last-child { border-bottom: none; }
    .lesson-order {
        width: 32px; height: 32px; border-radius: 10px;
        background: rgba(0,191,255,0.1); color: var(--primary-blue);
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 0.85rem;
    }
    .lesson-info { flex: 1; }
    .lesson-title { font-weight: 700; color: #0f172a; margin-bottom: 4px; font-size: 0.95rem; }
    .lesson-meta { display: flex; gap: 12px; font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .lesson-meta i { color: var(--primary-blue); }

    .lesson-actions { display: flex; gap: 8px; }
    .btn-icon {
        width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border-light);
        display: flex; align-items: center; justify-content: center; color: #64748b;
        background: #fff; transition: 0.2s;
    }
    .btn-icon:hover { background: #f1f5f9; color: var(--primary-blue); border-color: var(--primary-blue); }
    .btn-icon.delete:hover { border-color: #ef4444; color: #ef4444; }

    .form-card {
        background: #fff; border: 1px solid var(--border-light); border-radius: 24px;
        padding: 40px; position: sticky; top: 40px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    }
    .form-label { font-size: 0.73rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: block; }
    .form-input {
        width: 100%; padding: 14px 18px; border-radius: 14px; border: 1px solid var(--border-light);
        background: #f8fafc; font-family: inherit; font-size: 0.93rem; transition: 0.3s; margin-bottom: 24px;
    }
    .form-input:focus { outline: none; border-color: var(--primary-blue); background: #fff; box-shadow: 0 0 0 4px rgba(0,191,255,0.1); }

    .alert { padding: 16px 24px; border-radius: 16px; margin-bottom: 32px; font-weight: 600; font-size: 0.93rem; display: flex; align-items: center; gap: 12px; }
    .alert-success { background: #ecfdf5; border: 1px solid #10b98133; color: #065f46; }
    .alert-error { background: #fef2f2; border: 1px solid #ef444433; color: #991b1b; }

    @media (max-width: 1100px) {
        .lesson-grid { grid-template-columns: 1fr; }
        .form-card { position: static; }
    }
</style>

<main class="main-content">
    <header style="margin-bottom: 48px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                <a href="courses.php" style="color: var(--primary-blue); text-decoration: none; font-size: 0.85rem; font-weight: 700;"><i class="fas fa-chevron-left"></i> My Courses</a>
                <span style="color: #cbd5e1;">/</span>
                <span style="color: #64748b; font-size: 0.85rem; font-weight: 600;">Course Curriculum</span>
            </div>
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem; font-weight: 800; color: #0f172a;">Manage <span style="color: var(--primary-blue);">Lessons</span></h1>
            <p style="color: #64748b; margin-top: 4px;">Organize the learning journey for <strong><?= htmlspecialchars($course['title']) ?></strong></p>
        </div>
        <button onclick="window.scrollTo({top: document.querySelector('.form-card').offsetTop - 40, behavior:'smooth'})" class="btn btn-primary" style="background: var(--primary-blue); border: none; padding: 14px 28px; border-radius: 12px; font-weight: 700;">
            <i class="fas fa-plus"></i> New Lesson
        </button>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="lesson-grid">
        <!-- Curriculum View -->
        <div class="curriculum-wrap">
            <div class="curriculum-header">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a;">Course Modules</h3>
                    <p style="font-size: 0.78rem; color: #64748b; margin-top: 2px;"><?= count($lessons) ?> Lessons total</p>
                </div>
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--primary-blue); background: rgba(0,191,255,0.08); padding: 6px 14px; border-radius: 99px;">CURRICULUM ACTIVE</div>
            </div>

            <div class="curriculum-list">
                <?php if (empty($lessons)): ?>
                    <div style="padding: 60px; text-align: center; color: #94a3b8;">
                        <i class="fas fa-book-open" style="font-size: 2.5rem; margin-bottom: 20px; opacity: 0.3;"></i>
                        <p>Start building your course structure by adding your first lesson.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($lessons as $l): ?>
                        <div class="lesson-card">
                            <div class="lesson-order"><?= htmlspecialchars($l['order_num']) ?></div>
                            <div class="lesson-info">
                                <div class="lesson-title"><?= htmlspecialchars($l['title']) ?></div>
                                <div class="lesson-meta">
                                    <span><i class="fas <?= $l['lesson_type'] == 'video' ? 'fa-video' : ($l['lesson_type'] == 'quiz' ? 'fa-puzzle-piece' : 'fa-file-alt') ?>"></i> <?= ucfirst($l['lesson_type']) ?></span>
                                    <span><i class="fas fa-clock"></i> <?= $l['duration_mins'] ?> Mins</span>
                                </div>
                            </div>
                            <div class="lesson-actions">
                                <a href="?course_id=<?= $course_id ?>&edit_id=<?= $l['id'] ?>" class="btn-icon" title="Edit Content"><i class="fas fa-edit"></i></a>
                                <a href="?course_id=<?= $course_id ?>&delete_id=<?= $l['id'] ?>" class="btn-icon delete" title="Remove" onclick="return confirm('Delete this lesson from curriculum?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Sidebar -->
        <div class="form-card">
            <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.3rem; font-weight: 800; color: #0f172a; margin-bottom: 32px; display: flex; align-items: center; gap: 12px;">
                <span style="width: 8px; height: 24px; background: var(--secondary-orange); border-radius: 4px;"></span>
                <?= $edit_data ? 'Synchronize' : 'Initialize' ?> Lesson
            </h3>

            <form method="POST" action="lessons.php?course_id=<?= $course_id ?>">
                <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                <input type="hidden" name="save_lesson" value="1">

                <label class="form-label">Lesson Title</label>
                <input type="text" name="title" class="form-input" placeholder="e.g. Master React Hooks" value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>" required>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label class="form-label">Content Type</label>
                        <select name="lesson_type" class="form-input">
                            <option value="video" <?= ($edit_data['lesson_type'] ?? '') == 'video' ? 'selected' : '' ?>>🎥 Video Lecture</option>
                            <option value="text" <?= ($edit_data['lesson_type'] ?? '') == 'text' ? 'selected' : '' ?>>📖 Reading Task</option>
                            <option value="quiz" <?= ($edit_data['lesson_type'] ?? '') == 'quiz' ? 'selected' : '' ?>>🧩 Quiz Challenge</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Duration (Mins)</label>
                        <input type="number" name="duration_mins" class="form-input" value="<?= $edit_data['duration_mins'] ?? 15 ?>">
                    </div>
                </div>

                <label class="form-label">Sequence (Order #)</label>
                <input type="number" name="order_num" class="form-input" value="<?= $edit_data['order_num'] ?? (count($lessons) + 1) ?>">

                <label class="form-label">Learning Resource (URL)</label>
                <div style="position: relative; margin-bottom: 24px;">
                    <i class="fas fa-link" style="position: absolute; left: 18px; top: 18px; color: #94a3b8; font-size: 0.9rem;"></i>
                    <input type="url" name="video_url" class="form-input" style="padding-left: 48px; margin-bottom: 0;" placeholder="https://youtube.com/..." value="<?= htmlspecialchars($edit_data['file_url'] ?? '') ?>">
                </div>

                <label class="form-label">Lesson Context / Objectives</label>
                <textarea name="content" class="form-input" style="min-height: 120px; resize: none;" placeholder="What will the student learn in this module?"><?= htmlspecialchars($edit_data['content'] ?? '') ?></textarea>

                <div style="display: flex; gap: 12px; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; height: 58px; border-radius: 14px; font-weight: 800; background: var(--primary-blue); border: none;">
                        <?= $edit_data ? 'Update Module' : 'Add to Curriculum' ?>
                    </button>
                    <?php if ($edit_data): ?>
                        <a href="lessons.php?course_id=<?= $course_id ?>" class="btn btn-ghost" style="height: 58px; display: flex; align-items: center; justify-content: center; padding: 0 20px; border-radius: 14px; background: #f1f5f9; color: #64748b; text-decoration: none; font-weight: 700;">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
