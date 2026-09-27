<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('tutor');
$tutor = currentUser();

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$message = '';
$error = '';

// Handle AI Lesson Generation (AJAX)
if (isset($_POST['ai_lesson_gen'])) {
    header('Content-Type: application/json');
    $topic = $_POST['topic'] ?? '';
    try {
        require_once '../includes/ai-handler.php';
        $ai_data = SDAC_AI::generateLesson($topic);
        echo json_encode($ai_data);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 1. Handle Lesson Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title']);
    $type = $_POST['lesson_type'];
    $duration = (int)$_POST['duration_mins'];
    $week = (int)$_POST['week_num'];
    $order = (int)$_POST['order_num'];
    $video_url = trim($_POST['video_url'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // Handle PDF file upload
    $pdf_file_url = $video_url; // default: keep existing URL
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === 0) {
        $file = $_FILES['pdf_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            if ($file['size'] > 20 * 1024 * 1024) {
                $error = "PDF file size exceeds 20MB limit.";
                goto skip_save;
            }
            $uploadDir = '../uploads/lessons/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $filename = 'LESSON_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $pdf_file_url = $filename;
                $type = 'pdf'; // Force type to pdf when file is uploaded
            } else {
                $error = "Failed to upload PDF. Please check server permissions.";
                goto skip_save;
            }
        } else {
            $error = "Only PDF files are allowed.";
            goto skip_save;
        }
    }

    try {
        // Security: Ensure this tutor owns the course
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND tutor_id = ?");
        $stmt->execute([$course_id, $tutor['id']]);
        if (!$stmt->fetch()) {
            $error = "Unauthorized attempt to modify curriculum.";
            goto skip_save;
        }

        if ($id) {
            $stmt = $pdo->prepare("UPDATE lessons SET title=?, lesson_type=?, duration_mins=?, week_num=?, order_num=?, file_url=?, video_url=?, content=? WHERE id=? AND course_id=?");
            $stmt->execute([$title, $type, $duration, $week, $order, $pdf_file_url, $video_url, $content, $id, $course_id]);
            $message = "Module synchronized!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, lesson_type, duration_mins, week_num, order_num, file_url, video_url, content) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $title, $type, $duration, $week, $order, $pdf_file_url, $video_url, $content]);
            $message = "Module added to curriculum!";
        }
        header("Location: lessons.php?course_id=$course_id&msg=" . urlencode($message));
        exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
    skip_save:
}

// 2. Handle Lesson Delete
if (isset($_GET['delete_id'])) {
    $pdo->prepare("DELETE FROM lessons WHERE id=? AND course_id=?")->execute([$_GET['delete_id'], $course_id]);
    header("Location: lessons.php?course_id=$course_id&msg=Module+Deleted");
    exit;
}

$pageTitle = 'Curriculum Architect';
require_once 'includes/header.php';


// 3. Fetch Data
$course = null;
if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id=? AND tutor_id=?");
    $stmt->execute([$course_id, $tutor['id']]);
    $course = $stmt->fetch();
}

$stmt = $pdo->prepare("SELECT * FROM courses WHERE tutor_id=? ORDER BY title ASC");
$stmt->execute([$tutor['id']]);
$my_all_courses = $stmt->fetchAll();

$lessons = [];
$lessons_by_week = [];
$weekly_cats = [];

if ($course_id) {
    $stmt = $pdo->prepare("SELECT l.*, q.title as quiz_title FROM lessons l LEFT JOIN quizzes q ON l.id = q.linked_lesson_id WHERE l.course_id=? ORDER BY l.week_num ASC, l.order_num ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();

    foreach ($lessons as $l) {
        $lessons_by_week[$l['week_num']][] = $l;
    }

    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id=? AND type='cat'");
    $stmt->execute([$course_id]);
    $cats = $stmt->fetchAll();
    foreach ($cats as $c) {
        if ($c['linked_week_num']) $weekly_cats[$c['linked_week_num']] = $c;
    }
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id=? AND course_id=?");
    $stmt->execute([$_GET['edit_id'], $course_id]);
    $edit_data = $stmt->fetch();
}

if (isset($_GET['msg'])) $message = $_GET['msg'];
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .lesson-grid {
        display: grid;
        grid-template-columns: 1.8fr 1fr;
        gap: 40px;
        align-items: start;
        max-width: 1600px;
        margin: 0 auto;
    }
    .form-sticky-container {
        padding: 40px;
        position: sticky;
        top: 40px;
    }

    @media (max-width: 1200px) {
        .lesson-grid { grid-template-columns: 1.5fr 1fr; gap: 30px; }
    }

    @media (max-width: 1024px) {
        .lesson-grid { grid-template-columns: 1fr; }
        .form-sticky-container { position: static; padding: 30px; }
    }

    @media (max-width: 768px) {
        .portal-header { flex-direction: column; align-items: flex-start; gap: 24px; }
        .course-grid { grid-template-columns: 1fr; }
    }
</style>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
    <?php if (!$course_id): ?>
        <div class="greeting">
            <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Curriculum Arc<span style="color: var(--primary);">.</span></h1>
            <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Select an academic track to begin structuring your instructional modules.</p>
        </div>
    <?php else: ?>
        <div class="greeting">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <a href="lessons.php" style="color: var(--primary); text-decoration: none; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;"><i class="fas fa-chevron-left"></i> SELECTION LOBBY</a>
            </div>
            <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Manage Lessons<span style="color: var(--primary);">.</span></h1>
            <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Organizing the learning journey for <span style="color: var(--primary); font-weight: 700;"><?= htmlspecialchars($course['title']) ?></span></p>
        </div>
        <div style="display: flex; gap: 16px; align-items: center;">
            <button onclick="window.scrollTo({top: document.querySelector('.form-card-premium').offsetTop - 40, behavior:'smooth'})" class="btn-premium" style="padding: 14px 28px;">
                <i class="fas fa-plus"></i> NEW LESSON
            </button>
        </div>
    <?php endif; ?>
</header>

    <?php if (!$course_id): ?>
        <div class="course-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 32px; max-width: 1400px; margin: 0 auto;">
            <?php foreach($my_all_courses as $c): ?>
                <a href="?course_id=<?= $c['id'] ?>" style="text-decoration:none;">
                    <div class="premium-card" style="padding: 32px; height: 100%; display: flex; flex-direction: column; transition: 0.3s;">
                        <span style="display: block; font-size: 0.65rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 16px;">Track #<?= $c['id'] ?></span>
                        <h3 style="margin: 0 0 24px; font-size: 1.2rem; font-weight: 950; color: var(--text-main); line-height: 1.4;"><?= htmlspecialchars($c['title']) ?></h3>
                        <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 24px;">
                           <div style="font-size: 0.85rem; color: var(--text-dim); font-weight: 800;"><i class="fas fa-book-open" style="color: var(--primary); margin-right: 8px;"></i> <?= $c['total_lessons'] ?> Modules</div>
                           <span style="font-size: 0.75rem; font-weight: 900; color: var(--primary); text-transform: uppercase; letter-spacing: 1px;">ARCHITECT <i class="fas fa-arrow-right" style="margin-left: 6px;"></i></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
            <?php if(empty($my_all_courses)): ?>
                <div class="premium-card" style="grid-column: 1/-1; padding: 100px 40px; text-align: center; border: 2px dashed #e2e8f0; background: white; display: flex; flex-direction: column; align-items: center;">
                    <div style="width: 100px; height: 100px; background: #f8fafc; border-radius: 30px; display: flex; align-items: center; justify-content: center; margin-bottom: 32px; font-size: 2.8rem; color: var(--primary);"><i class="fas fa-layer-group"></i></div>
                    <h3 style="margin: 0 0 12px; font-weight: 950; font-size: 1.5rem; letter-spacing: -0.5px;">No Tracks Synchronized</h3>
                    <p style="color: var(--text-dim); font-size: 1rem; margin: 0 0 32px; max-width: 440px; line-height: 1.6;">You haven't launched any academic tracks yet. Begin by architecting your first global program.</p>
                    <a href="courses.php" class="btn-premium" style="min-width: 240px; text-decoration: none;">LAUNCH NOW</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="lesson-grid">
            <div class="curriculum-view">
                <div class="premium-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 32px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; font-size: 1.15rem; font-weight: 950; color: var(--text-main);">Module Sequence</h3>
                        <span style="padding: 6px 14px; background: white; border: 1px solid #e2e8f0; border-radius: 50px; font-size: 0.7rem; font-weight: 950; color: var(--primary); text-transform: uppercase; letter-spacing: 1px;"><?= count($lessons) ?> MODULES</span>
                    </div>

                    <div class="curriculum-list">
                        <?php if (empty($lessons)): ?>
                            <div style="padding: 80px 40px; text-align: center; color: var(--text-dim);">
                                <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 24px; opacity: 0.2;"></i>
                                <p style="font-weight: 700;">Start building your course structure by adding your first module.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($lessons_by_week as $wnum => $wlessons): ?>
                                <div style="background: #F8FAFC; padding: 16px 32px; border-bottom: 1px solid #f1f5f9; font-weight: 950; font-size: 0.75rem; color: #94A3B8; text-transform: uppercase; letter-spacing: 2px; display: flex; justify-content: space-between; align-items: center;">
                                    <span>Milestone Week <?= $wnum ?></span>
                                    <?php if(isset($weekly_cats[$wnum])): ?>
                                        <span style="color: var(--primary); background: white; border: 1px solid #E2E8F0; padding: 6px 14px; border-radius: 50px; font-size: 0.65rem;"><i class="fas fa-medal" style="margin-right: 6px;"></i> CAT: <?= htmlspecialchars($weekly_cats[$wnum]['title']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php foreach ($wlessons as $l): ?>
                                    <div style="padding: 24px 32px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 24px; transition: 0.2s;">
                                        <div style="width: 40px; height: 40px; background: #E0F7FF; color: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 950; font-size: 0.9rem;"><?= htmlspecialchars($l['order_num']) ?></div>
                                        <div style="flex: 1;">
                                            <h4 style="margin: 0 0 8px; font-size: 1rem; font-weight: 900; color: var(--text-main);"><?= htmlspecialchars($l['title']) ?></h4>
                                            <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 0.75rem; color: var(--text-dim); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                                <?php
                                                $typeIcon = match($l['lesson_type']) {
                                                    'video' => 'fa-video',
                                                    'quiz'  => 'fa-brain',
                                                    'pdf'   => 'fa-file-pdf',
                                                    'audio' => 'fa-headphones',
                                                    default => 'fa-file-alt'
                                                };
                                                ?>
                                                <span><i class="fas <?= $typeIcon ?>" style="color: var(--primary); margin-right: 6px;"></i> <?= ucfirst($l['lesson_type']) ?></span>
                                                <span><i class="fas fa-clock" style="color: var(--primary); margin-right: 6px;"></i> <?= $l['duration_mins'] ?> Mins</span>
                                                <?php if($l['lesson_type'] === 'pdf' && $l['file_url']): ?>
                                                    <a href="../uploads/lessons/<?= htmlspecialchars($l['file_url']) ?>" target="_blank" style="color: #E74C3C; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; background: #FEF2F2; padding: 3px 10px; border-radius: 50px; border: 1px solid #FECACA;">
                                                        <i class="fas fa-download"></i> VIEW PDF
                                                    </a>
                                                <?php endif; ?>
                                                <?php if($l['quiz_title']): ?>
                                                    <span style="color: var(--secondary);"><i class="fas fa-brain" style="margin-right: 6px;"></i> <?= htmlspecialchars($l['quiz_title']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="?course_id=<?= $course_id ?>&edit_id=<?= $l['id'] ?>" style="width: 40px; height: 40px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--text-dim); text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='var(--primary)'; this.style.borderColor='var(--primary)';"><i class="fas fa-edit"></i></a>
                                            <a href="?course_id=<?= $course_id ?>&delete_id=<?= $l['id'] ?>" style="width: 40px; height: 40px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--text-dim); text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='#EF4444'; this.style.borderColor='#EF4444';" onclick="return confirm('Delete this lesson from curriculum?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-card-premium">
                <div class="premium-card form-sticky-container">
                    <h3 style="margin: 0 0 32px; font-size: 1.3rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.5px; display: flex; align-items: center; gap: 12px;">
                        <span style="width: 8px; height: 24px; background: var(--secondary); border-radius: 4px;"></span>
                        <?= $edit_data ? 'Synchronize' : 'Initialize' ?> Module
                    </h3>

                    <form method="POST" action="lessons.php?course_id=<?= $course_id ?>" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?? '' ?>">
                        <input type="hidden" name="save_lesson" value="1">

                        <div style="display: flex; gap: 12px; align-items: flex-end; margin-bottom: 24px;">
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Module Title</label>
                                <input type="text" name="title" id="lessTitle" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" placeholder="e.g. Master React Hooks" value="<?= htmlspecialchars($edit_data['title'] ?? '') ?>" required>
                            </div>
                            <button type="button" onclick="generateAILesson()" id="aiLessonBtn" style="height: 52px; padding: 0 16px; background: #E0F7FF; border: 1px solid #BAE6FD; border-radius: 14px; color: var(--primary); font-size: 0.75rem; font-weight: 900; cursor: pointer;">
                                ✨ AI GEN
                            </button>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                            <div>
                                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Content Type</label>
                                <select name="lesson_type" id="lessonTypeSelect" onchange="toggleLessonFields(this.value)" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;">
                                    <option value="video" <?= ($edit_data['lesson_type'] ?? '') == 'video' ? 'selected' : '' ?>>🎥 Video</option>
                                    <option value="pdf" <?= ($edit_data['lesson_type'] ?? '') == 'pdf' ? 'selected' : '' ?>>📄 PDF Document</option>
                                    <option value="text" <?= ($edit_data['lesson_type'] ?? '') == 'text' ? 'selected' : '' ?>>📖 Reading</option>
                                    <option value="quiz" <?= ($edit_data['lesson_type'] ?? '') == 'quiz' ? 'selected' : '' ?>>🧩 Quiz</option>
                                    <option value="audio" <?= ($edit_data['lesson_type'] ?? '') == 'audio' ? 'selected' : '' ?>>🎧 Audio</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Duration (Mins)</label>
                                <input type="number" name="duration_mins" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" value="<?= $edit_data['duration_mins'] ?? 15 ?>">
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                            <div>
                                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Week</label>
                                <input type="number" name="week_num" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" value="<?= $edit_data['week_num'] ?? 1 ?>" min="1" required>
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Sequence</label>
                                <input type="number" name="order_num" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" value="<?= $edit_data['order_num'] ?? (count($lessons) + 1) ?>">
                            </div>
                        </div>

                        <!-- Video URL -->
                        <div style="margin-bottom: 24px;" id="videoUrlSection">
                            <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Video Delivery URL</label>
                            <input type="url" name="video_url" id="videoUrlInput" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" placeholder="https://youtube.com/..." value="<?= htmlspecialchars($edit_data['video_url'] ?? '') ?>">
                        </div>

                        <!-- PDF Upload -->
                        <div style="margin-bottom: 24px;" id="pdfUploadSection">
                            <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Upload PDF Document</label>
                            <label for="pdfFileInput" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 32px; border: 2px dashed #BAE6FD; border-radius: 16px; background: #FAFAFF; cursor: pointer; transition: 0.3s;" onmouseover="this.style.borderColor='var(--primary)'; this.style.background='#E0F7FF'" onmouseout="this.style.borderColor='#BAE6FD'; this.style.background='#FAFAFF'">
                                <i class="fas fa-file-pdf" style="font-size: 2.5rem; color: #E74C3C;"></i>
                                <div style="text-align: center;">
                                    <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem;">Click to upload PDF</div>
                                    <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 4px;">Max 20MB · PDF files only</div>
                                </div>
                                <input type="file" id="pdfFileInput" name="pdf_file" accept=".pdf" style="display: none;" onchange="showPdfName(this)">
                            </label>
                            <div id="pdfFileName" style="display: none; margin-top: 10px; padding: 12px 16px; background: #ECFDF5; border: 1px solid #10B981; border-radius: 10px; font-size: 0.85rem; font-weight: 700; color: #065F46; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-check-circle"></i>
                                <span id="pdfFileNameText"></span>
                            </div>
                            <?php if (!empty($edit_data['file_url']) && $edit_data['lesson_type'] === 'pdf'): ?>
                                <div style="margin-top: 10px; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px; font-size: 0.8rem; font-weight: 700; color: #991B1B; display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-file-pdf"></i>
                                    Current: <a href="../uploads/lessons/<?= htmlspecialchars($edit_data['file_url']) ?>" target="_blank" style="color: #DC2626;"><?= htmlspecialchars($edit_data['file_url']) ?></a>
                                    <span style="color: #6B7280; font-weight: 600;">(Upload new file to replace)</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-bottom: 32px;">
                            <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Description / Context</label>
                            <textarea name="content" id="lessContent" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; min-height: 120px; resize: none;" placeholder="What will the student learn in this module?"><?= htmlspecialchars($edit_data['content'] ?? '') ?></textarea>
                        </div>

                        <div style="display: flex; gap: 16px;">
                            <button type="submit" class="btn-premium" style="flex: 2;">
                                <?= $edit_data ? 'UPDATE MODULE' : 'ADD TO CURRICULUM' ?>
                            </button>
                            <?php if ($edit_data): ?>
                                <a href="lessons.php?course_id=<?= $course_id ?>" class="btn-premium" style="flex: 1; background: #f8fafc; color: var(--text-dim); border: 1px solid #e2e8f0; box-shadow: none; text-decoration: none; display: flex; align-items: center; justify-content: center;">CANCEL</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<script>
async function generateAILesson() {
    const title = document.getElementById('lessTitle').value;
    if(!title) { 
        if(typeof SDAC !== 'undefined') SDAC.showToast("Please provide a topic first.", "error");
        else alert("Please provide a topic/title first."); 
        return; 
    }
    const btn = document.getElementById('aiLessonBtn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';
    try {
        const formData = new FormData();
        formData.append('ai_lesson_gen', '1');
        formData.append('topic', title);
        const resp = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await resp.json();
        if(data.title) {
            document.getElementById('lessTitle').value = data.title;
            document.getElementById('lessContent').value = data.content;
            document.querySelector('[name="duration_mins"]').value = data.duration;
            if(typeof SDAC !== 'undefined') SDAC.showToast("Lesson content architected by AI!", "success");
        }
    } catch(e) {
        if(typeof SDAC !== 'undefined') SDAC.showToast("Institutional AI limit reached or offline.", "error");
        else alert("Institutional AI limit reached or offline.");
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

function toggleLessonFields(type) {
    const videoSection = document.getElementById('videoUrlSection');
    const pdfSection = document.getElementById('pdfUploadSection');
    if (type === 'pdf') {
        videoSection.style.display = 'none';
        pdfSection.style.display = 'block';
    } else if (type === 'video' || type === 'audio') {
        videoSection.style.display = 'block';
        pdfSection.style.display = 'none';
    } else {
        videoSection.style.display = 'none';
        pdfSection.style.display = 'none';
    }
}

function showPdfName(input) {
    if (input.files && input.files[0]) {
        const nameEl = document.getElementById('pdfFileName');
        const textEl = document.getElementById('pdfFileNameText');
        textEl.textContent = input.files[0].name + ' (' + (input.files[0].size / 1024 / 1024).toFixed(2) + ' MB)';
        nameEl.style.display = 'flex';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('lessonTypeSelect');
    if (select) toggleLessonFields(select.value);

    // Check for global sync message
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg')) {
        SDAC.showToast(urlParams.get('msg'), 'success');
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname + "?course_id=<?= $course_id ?>");
    }
});
</script>
</body>
</html>
