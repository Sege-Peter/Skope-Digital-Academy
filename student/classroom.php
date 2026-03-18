<?php
$pageTitle = 'Classroom Studio';
require_once 'includes/header.php';

$course_id = (int)($_GET['id'] ?? 0);
$lesson_id = (int)($_GET['lesson'] ?? 0);

if (!$course_id) { 
    header('Location: index.php'); 
    exit; 
}

try {
    // 1. Check enrollment
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.description, c.tutor_id, u.name as tutor_name, u.avatar as tutor_avatar
                           FROM enrollments e 
                           JOIN courses c ON e.course_id = c.id 
                           JOIN users u ON c.tutor_id = u.id
                           WHERE e.student_id = ? AND e.course_id = ? AND e.status = 'active'");
    $stmt->execute([$student['id'], $course_id]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        header('Location: ../course-details.php?id=' . $course_id . '&msg=not_enrolled');
        exit;
    }

    // 2. Fetch all lessons with completion status
    $stmt = $pdo->prepare("SELECT l.*, 
                           (SELECT status FROM lesson_progress WHERE student_id = ? AND lesson_id = l.id) as progress_status
                           FROM lessons l 
                           WHERE l.course_id = ? 
                           ORDER BY l.order_num ASC");
    $stmt->execute([$student['id'], $course_id]);
    $lessons = $stmt->fetchAll();

    // 3. Select active lesson
    $active_lesson = null;
    if ($lesson_id) {
        foreach($lessons as $l) { if($l['id'] == $lesson_id) { $active_lesson = $l; break; } }
    }
    
    if (!$active_lesson && !empty($lessons)) {
        $success = isset($_GET['success']) ? '&success=1' : '';
        header("Location: classroom.php?id=$course_id&lesson=" . $lessons[0]['id'] . $success);
        exit;
    }

    // 4. Mark active lesson as "in_progress" if not already started
    if ($active_lesson && (!$active_lesson['progress_status'] || $active_lesson['progress_status'] === 'not_started')) {
        $stmt = $pdo->prepare("INSERT INTO lesson_progress (student_id, lesson_id, course_id, status) 
                               VALUES (?, ?, ?, 'in_progress') 
                               ON DUPLICATE KEY UPDATE status = IF(status = 'completed', 'completed', 'in_progress')");
        $stmt->execute([$student['id'], $active_lesson['id'], $course_id]);
    }

    // 5. Check if lesson was just marked as completed via POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_lesson'])) {
        $l_to_complete = (int)$_POST['complete_lesson'];
        $stmt = $pdo->prepare("UPDATE lesson_progress SET status = 'completed', completed_at = NOW() 
                               WHERE student_id = ? AND lesson_id = ?");
        $stmt->execute([$student['id'], $l_to_complete]);
        
        // Recalculate course progress percentage
        $total_lessons = count($lessons);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = ? AND course_id = ? AND status = 'completed'");
        $stmt->execute([$student['id'], $course_id]);
        $completed_count = $stmt->fetchColumn();
        
        $new_progress = ($total_lessons > 0) ? ($completed_count / $total_lessons) * 100 : 0;
        
        $stmt = $pdo->prepare("UPDATE enrollments SET progress_percent = ? WHERE id = ?");
        $stmt->execute([$new_progress, $enrollment['id']]);

        // Automate Badge Awards
        require_once '../includes/gamified_logic.php';
        awardBadgeIfEligible($student['id'], 'lessons_completed', $pdo);
        
        if ($new_progress >= 100) {
            $upd = $pdo->prepare("UPDATE enrollments SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $upd->execute([$enrollment['id']]);
            awardBadgeIfEligible($student['id'], 'courses_completed', $pdo);
        }
        
        header("Location: classroom.php?id=$course_id&lesson=$l_to_complete&success=1");
        exit;
    }

} catch (Exception $e) { 
    error_log($e->getMessage()); 
    $enrollment = []; 
    $lessons = []; 
    $active_lesson = null; 
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    :root {
        --studio-bg: #f8fafc;
        --studio-dark: #0f172a;
        --studio-border: #e2e8f0;
    }

    .classroom-main-wrap {
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden;
    }

    .classroom-layout { 
        display: grid; 
        grid-template-columns: 1fr 380px; 
        height: 100%; 
        overflow: hidden; 
        background: var(--studio-bg);
    }

    /* Left: Player Area */
    .classroom-main {
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }

    .video-container {
        width: 100%;
        aspect-ratio: 16/9;
        background: #000;
        position: sticky;
        top: 0;
        z-index: 10;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .video-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    }

    .video-placeholder i { font-size: 4rem; opacity: 0.2; margin-bottom: 20px; }

    .lesson-meta {
        padding: 40px;
        background: #fff;
        flex: 1;
    }

    .curric-tag {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--primary-glow);
        color: var(--primary);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 16px;
    }

    .lesson-title {
        font-family: 'Poppins', sans-serif;
        font-size: 2rem;
        font-weight: 800;
        color: var(--studio-dark);
        margin-bottom: 12px;
        line-height: 1.2;
    }

    .tutor-strip {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 32px;
        padding-bottom: 32px;
        border-bottom: 1px solid var(--studio-border);
    }

    .tutor-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }

    .lesson-body {
        font-size: 1.05rem;
        line-height: 1.8;
        color: #475569;
        max-width: 900px;
    }

    /* Right: Playlist Area */
    .classroom-playlist {
        background: #fff;
        border-left: 1px solid var(--studio-border);
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .playlist-header {
        padding: 24px;
        background: #fff;
        border-bottom: 1px solid var(--studio-border);
        z-index: 5;
    }

    .playlist-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 12px;
    }

    .playlist-item {
        display: grid;
        grid-template-columns: 48px 1fr 24px;
        gap: 16px;
        padding: 16px;
        border-radius: 16px;
        margin-bottom: 8px;
        text-decoration: none;
        color: #64748b;
        transition: 0.3s;
        align-items: center;
        border: 1px solid transparent;
    }

    .playlist-item:hover { background: #f1f5f9; }
    .playlist-item.active { 
        background: #f8fafc; 
        border-color: var(--primary-glow);
        color: var(--studio-dark);
    }

    .item-num {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.8rem;
    }

    .active .item-num { background: var(--primary); color: #fff; }

    .item-check { color: #10B981; font-size: 0.85rem; opacity: 0; }
    .item-check.completed { opacity: 1; }

    .playlist-footer {
        padding: 24px;
        background: #f8fafc;
        border-top: 1px solid var(--studio-border);
    }

    @media (max-width: 1200px) {
        .classroom-main-wrap { height: auto; overflow: visible; }
        .classroom-layout { grid-template-columns: 1fr; height: auto; overflow: visible; }
        .classroom-playlist { height: auto; border-left: none; border-top: 1px solid var(--studio-border); overflow: visible; }
        .classroom-main { height: auto; overflow: visible; }
        .video-container { position: relative; }
        .lesson-meta { padding: 30px 20px; }
        .lesson-title { font-size: 1.5rem; }
    }
    @media (max-width: 640px) {
        .tutor-strip { flex-direction: column; align-items: flex-start; gap: 16px; }
        .lesson-title { font-size: 1.25rem; }
        .lesson-body { font-size: 0.95rem; }
    }
</style>

<main class="main-content classroom-main-wrap" style="padding: 0;">
    <header class="admin-header" style="margin-bottom: 0; border-bottom: 1px solid var(--dark-border); background: #fff; flex-shrink: 0; padding: 15px 30px;">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.4rem; margin: 0;">Classroom <span class="text-primary">Studio</span></h1>
                <p style="color: var(--text-dim); margin-top: 2px; font-size: 0.75rem;"><?= htmlspecialchars($enrollment['title'] ?? 'Course') ?></p>
            </div>
        </div>
        <div>
            <a href="courses.php" class="btn btn-ghost btn-sm" style="background: var(--bg-light); border-color: var(--dark-border);"><i class="fas fa-arrow-left"></i> Exit</a>
        </div>
    </header>

<div class="classroom-layout" style="flex: 1; min-height: 0;">
    <!-- Main Content -->
    <div class="classroom-main">
        <?php if (!$active_lesson): ?>
            <div style="padding: 60px 40px; text-align: center; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <div style="width: 80px; height: 80px; background: var(--bg-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 24px;">
                    <i class="fas fa-folder-open" style="font-size: 2rem; color: var(--text-dim);"></i>
                </div>
                <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; margin-bottom: 12px; color: var(--studio-dark);">No Lessons Available</h2>
                <p style="color: var(--text-dim); max-width: 400px; line-height: 1.6;">This course currently has no lessons uploaded by the instructor. Please check back later.</p>
            </div>
        <?php else: ?>
        <?php if($active_lesson && $active_lesson['file_url']): ?>
        <div class="video-container">
            <?php if($active_lesson['lesson_type'] === 'video'): ?>
                <?php if(strpos($active_lesson['file_url'], 'youtube') !== false): ?>
                    <iframe width="100%" height="100%" src="<?= str_replace('watch?v=', 'embed/', $active_lesson['file_url']) ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                <?php else: ?>
                    <video controls controlsList="nodownload" style="width: 100%; height: 100%; background: #000;">
                        <source src="../<?= htmlspecialchars($active_lesson['file_url']) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                <?php endif; ?>
            <?php else: ?>
                <div class="video-placeholder">
                    <i class="fas fa-file-pdf"></i>
                    <h3>Resource Material</h3>
                    <p style="opacity: 0.7; font-size: 0.9rem;">Scroll down to read the content or download the primary file below.</p>
                    <a href="../<?= $active_lesson['file_url'] ?>" target="_blank" class="btn btn-primary btn-sm" style="margin-top: 20px;">
                        <i class="fas fa-download"></i> Open Document
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="lesson-meta">
            <div class="curric-tag">
                <i class="fas fa-graduation-cap"></i> Lesson <?= array_search($active_lesson, $lessons) + 1 ?> of <?= count($lessons) ?>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 40px; margin-bottom: 24px;">
                <h1 class="lesson-title"><?= htmlspecialchars($active_lesson['title']) ?></h1>
                
                <form method="POST">
                    <input type="hidden" name="complete_lesson" value="<?= $active_lesson['id'] ?>">
                    <?php if($active_lesson['progress_status'] === 'completed'): ?>
                        <button type="button" class="btn btn-ghost" style="color: #10B981; border: 1px solid #10B981; cursor: default;">
                            <i class="fas fa-check-double"></i> Completed
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Mark as Complete
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <div class="tutor-strip">
                <img src="../<?= $enrollment['tutor_avatar'] ? 'uploads/avatars/'.$enrollment['tutor_avatar'] : 'assets/images/default-avatar.png' ?>" class="tutor-avatar" alt="">
                <div>
                    <div style="font-size: 0.9rem; font-weight: 700; color: var(--studio-dark);"><?= htmlspecialchars($enrollment['tutor_name']) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-dim);">Skope Certified Instructor</div>
                </div>
                <div style="margin-left: auto; display: flex; gap: 8px;">
                    <button class="btn btn-ghost btn-sm" title="Take Notes"><i class="far fa-edit"></i></button>
                    <button class="btn btn-ghost btn-sm" title="Ask Question"><i class="far fa-comment-alt"></i></button>
                </div>
            </div>

            <div class="lesson-body">
                <?= nl2br(htmlspecialchars($active_lesson['content'])) ?>
                
                <?php if(empty($active_lesson['content'])): ?>
                    <div style="padding: 40px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 16px; text-align: center;">
                        <p style="color: var(--text-dim); font-style: italic;">Detailed theory for this lesson is contained in the attached study material above.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar Playlist -->
    <div class="classroom-playlist">
        <div class="playlist-header">
            <h4 style="font-family: 'Poppins', sans-serif; font-weight: 800; margin-bottom: 12px; font-size: 1rem;">Course Curriculum</h4>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-dim); text-transform: uppercase;">Your Progress</span>
                <span style="font-size: 0.8rem; font-weight: 800; color: var(--primary);"><?= round($enrollment['progress_percent']) ?>%</span>
            </div>
            <div style="height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                <div style="height: 100%; background: var(--primary); width: <?= $enrollment['progress_percent'] ?>%; transition: 1s ease-in-out;"></div>
            </div>
        </div>

        <div class="playlist-scroll">
            <?php foreach($lessons as $i => $l): ?>
            <a href="?id=<?= $course_id ?>&lesson=<?= $l['id'] ?>" class="playlist-item <?= ($active_lesson && $l['id'] == $active_lesson['id']) ? 'active' : '' ?>">
                <div class="item-num"><?= $i + 1 ?></div>
                <div style="min-width: 0;">
                    <div style="font-weight: 700; font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($l['title']) ?></div>
                    <div style="font-size: 0.72rem; opacity: 0.8; display: flex; align-items: center; gap: 6px; margin-top: 4px;">
                        <i class="fas <?= $l['lesson_type'] == 'video' ? 'fa-play-circle' : 'fa-file-alt' ?>"></i>
                        <?= $l['duration_mins'] ?> mins
                    </div>
                </div>
                <div class="item-check <?= $l['progress_status'] === 'completed' ? 'completed' : '' ?>">
                    <i class="fas fa-check-circle"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="playlist-footer">
            <a href="quizzes.php?course_id=<?= $course_id ?>" class="btn btn-secondary btn-block">
                <i class="fas fa-brain"></i> Final Assessment
            </a>
            <p style="text-align: center; font-size: 0.72rem; color: var(--text-dim); margin-top: 12px;">Earn certificate at 100% progress</p>
        </div>
    </div>
</div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('success') === '1') {
            SDA.showToast('Excellent! Lesson marked as completed.', 'success');
        }
        
        // Auto-scroll to active lesson in playlist
        const activeItem = document.querySelector('.playlist-item.active');
        if (activeItem) {
            activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
</body>
</html>
