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

    // 2. Fetch all lessons with completion status and group by week
    $stmt = $pdo->prepare("SELECT l.*, q.id as attached_quiz_id, q.title as attached_quiz_title,
                           (SELECT status FROM lesson_progress WHERE student_id = ? AND lesson_id = l.id) as progress_status,
                           (SELECT score FROM quiz_attempts WHERE student_id = ? AND quiz_id = q.id ORDER BY score DESC LIMIT 1) as quiz_score
                           FROM lessons l 
                           LEFT JOIN quizzes q ON q.linked_lesson_id = l.id
                           WHERE l.course_id = ? 
                           ORDER BY l.week_num ASC, l.order_num ASC");
    $stmt->execute([$student['id'], $student['id'], $course_id]);
    $lessons = $stmt->fetchAll();

    $lessons_by_week = [];
    foreach($lessons as $idx => &$l) {
        $l['is_locked'] = false;
        $l['lock_reason'] = '';

        if (!empty($l['release_date'])) {
            $release_ts = strtotime($l['release_date']);
            if ($release_ts > time()) {
                $l['is_locked'] = true;
                $l['lock_reason'] = 'Locked until ' . date('M d, Y', $release_ts);
            }
        }

        if (!$l['is_locked'] && $idx > 0) {
            $prev = $lessons[$idx - 1];
            $prev_completed = ($prev['progress_status'] === 'completed');
            $prev_quiz_passed = true;
            if ($prev['attached_quiz_id']) {
                $prev_quiz_passed = ($prev['quiz_score'] >= 70); 
            }
            if (!$prev_completed || !$prev_quiz_passed) {
                $l['is_locked'] = true;
                $l['lock_reason'] = !$prev_completed ? 'Complete previous lesson first' : 'Pass previous quiz first';
            }
        }
        $lessons_by_week[$l['week_num']][] = $l;
    }

    $active_lesson = null;
    if ($lesson_id) {
        foreach($lessons as $l) { if($l['id'] == $lesson_id) { $active_lesson = $l; break; } }
    }
    
    if (!$active_lesson && !empty($lessons)) {
        header("Location: classroom.php?id=$course_id&lesson=" . $lessons[0]['id']);
        exit;
    }

    if ($active_lesson && (!$active_lesson['progress_status'] || $active_lesson['progress_status'] === 'not_started')) {
        $stmt = $pdo->prepare("INSERT INTO lesson_progress (student_id, lesson_id, course_id, status) 
                               VALUES (?, ?, ?, 'in_progress') 
                               ON DUPLICATE KEY UPDATE status = IF(status = 'completed', 'completed', 'in_progress')");
        $stmt->execute([$student['id'], $active_lesson['id'], $course_id]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_lesson'])) {
        $l_to_complete = (int)$_POST['complete_lesson'];
        $stmt = $pdo->prepare("UPDATE lesson_progress SET status = 'completed', completed_at = NOW() 
                               WHERE student_id = ? AND lesson_id = ?");
        $stmt->execute([$student['id'], $l_to_complete]);
        
        $total_lessons = count($lessons);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE student_id = ? AND course_id = ? AND status = 'completed'");
        $stmt->execute([$student['id'], $course_id]);
        $completed_count = $stmt->fetchColumn();
        $new_progress = ($total_lessons > 0) ? ($completed_count / $total_lessons) * 100 : 0;
        
        $stmt = $pdo->prepare("UPDATE enrollments SET progress_percent = ? WHERE id = ?");
        $stmt->execute([$new_progress, $enrollment['id']]);

        if ($new_progress >= 100) {
            $upd = $pdo->prepare("UPDATE enrollments SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $upd->execute([$enrollment['id']]);
        }
        header("Location: classroom.php?id=$course_id&lesson=$l_to_complete&success=1");
        exit;
    }

    // 5. Fetch linked C.A.T Assessment (Quiz type 'cat')
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE (linked_lesson_id = ? OR (course_id = ? AND linked_week_num = ?)) AND type = 'cat' LIMIT 1");
    $stmt->execute([$active_lesson['id'], $course_id, $active_lesson['week_num']]);
    $cat_quiz = $stmt->fetch();

    // 6. Check progress status for checkboxes
    $stmt = $pdo->prepare("SELECT status FROM lesson_progress WHERE student_id = ? AND lesson_id = ?");
    $stmt->execute([$student['id'], $active_lesson['id']]);
    $lesson_done = ($stmt->fetchColumn() === 'completed');

} catch (Exception $e) { 
    error_log($e->getMessage()); 
    $lessons = []; $active_lesson = null; $cat_quiz = null; $lesson_done = false; 
}

require_once 'includes/layout-top.php';
?>

<style>
    :root { 
        --studio-vid-shadow: 0 40px 100px rgba(0,0,0,0.3); 
        --glass: rgba(255, 255, 255, 0.8);
        --glass-border: rgba(255, 255, 255, 0.5);
    }
    .studio-container { display: grid; grid-template-columns: 1fr 400px; gap: 40px; min-height: 90vh; transition: 0.5s cubic-bezier(0.4, 0, 0.2, 1); padding-bottom: 60px; }
    .studio-container.focus-mode { grid-template-columns: 1fr 0; gap: 0; }
    .studio-container.focus-mode .playlist-card { opacity: 0; pointer-events: none; transform: translateX(40px); }
    
    /* Studio Header Hero */
    .studio-hero { margin-bottom: 40px; }
    .breadcrumb { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; color: var(--text-dim); display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
    .breadcrumb i { font-size: 0.6rem; opacity: 0.5; }
    .studio-title { font-size: 2.5rem; font-weight: 950; letter-spacing: -1.5px; line-height: 1.1; color: var(--text-main); margin: 0; }

    /* Premium Content Cards */
    .premium-card { background: var(--glass); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid var(--glass-border); border-radius: 30px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.06); transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .premium-card:hover { transform: translateY(-5px); box-shadow: 0 35px 70px -15px rgba(0,0,0,0.1); }

    .content-group-card { background: white; border: 1px solid var(--border); border-radius: 24px; overflow: hidden; margin-bottom: 32px; box-shadow: 0 10px 30px -5px rgba(0,0,0,0.03); }
    .group-header { padding: 24px 32px; display: flex; align-items: center; gap: 18px; background: linear-gradient(to right, #fdfdfd, #ffffff); border-bottom: 1px solid #f1f5f9; }
    .group-chevron { width: 32px; height: 32px; border-radius: 12px; background: #F0F9FF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; box-shadow: 0 4px 12px rgba(0, 174, 239, 0.12); }
    
    .resource-item { padding: 24px 32px; display: flex; align-items: center; gap: 28px; transition: 0.3s; border-bottom: 1px solid #f1f5f9; position: relative; }
    .resource-item:hover { background: #fafafa; }
    .resource-item:last-child { border-bottom: none; }
    
    .resource-icon { width: 56px; height: 56px; border-radius: 18px; background: #f8fafc; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; transition: 0.4s; color: #475569; }
    .resource-item:hover .resource-icon { transform: scale(1.15) rotate(-8deg); background: white; box-shadow: 0 15px 30px rgba(0,0,0,0.08); }

    .resource-title { display: block; font-size: 1.05rem; font-weight: 900; color: var(--text-main); text-decoration: none; margin-bottom: 5px; transition: 0.2s; }
    .resource-title:hover { color: var(--primary); }
    .resource-info { font-size: 0.78rem; color: #64748b; font-weight: 800; display: flex; align-items: center; gap: 10px; }
    
    .mark-done-btn { padding: 12px 24px; border-radius: 15px; border: 1px solid #e2e8f0; background: white; color: #475569; font-size: 0.85rem; font-weight: 900; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 10px rgba(0,0,0,0.05); }
    .mark-done-btn:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); transform: translateY(-3px); box-shadow: 0 12px 25px rgba(0, 174, 239, 0.2); }
    .mark-done-btn.done { border-color: #10b981; background: #10b981; color: white; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); }

    .video-theater { background: #000; border-radius: 40px; overflow: hidden; box-shadow: var(--studio-vid-shadow); aspect-ratio: 16/9; position: relative; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 48px; animation: playerReveal 1s cubic-bezier(0.19, 1, 0.22, 1); }
    @keyframes playerReveal { from { transform: scale(0.95) translateY(30px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

    /* Playlist High-Density Roadmap */
    .playlist-card { background: white; border: 1px solid var(--border); border-radius: 35px; height: 100%; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 40px 80px rgba(2, 32, 71, 0.08); background: linear-gradient(180deg, #ffffff 0%, #f9fbff 100%); }
    .playlist-header { padding: 32px; border-bottom: 1px solid #f1f5f9; }
    .playlist-scroll { flex: 1; overflow-y: auto; padding: 20px 0; }
    .playlist-scroll::-webkit-scrollbar { width: 5px; }
    .playlist-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }


    .lesson-item { display: flex; align-items: center; gap: 20px; padding: 20px 24px; border-radius: 24px; text-decoration: none; color: inherit; transition: 0.3s; margin: 0 20px 10px; border: 1px solid transparent; }
    .lesson-item:hover { background: rgba(0, 174, 239, 0.04); transform: translateX(5px); }
    .lesson-item.active { background: #F0F9FF; border-color: rgba(0, 174, 239, 0.3); box-shadow: 0 15px 30px rgba(0, 174, 239, 0.1); }
    .item-num { width: 44px; height: 44px; border-radius: 16px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 900; color: #475569; flex-shrink: 0; transition: 0.3s; position: relative; }
    .active .item-num { background: #00AEEF; color: white; transform: rotate(8deg); box-shadow: 0 10px 25px rgba(0, 174, 239, 0.4); }
    .item-check { width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; margin-left: auto; transition: 0.3s; flex-shrink: 0; }
    .completed .item-check { background: #10b981; box-shadow: 0 5px 12px rgba(16, 185, 129, 0.3); }
    .lesson-meta-label { font-size: 0.7rem; color: #94a3b8; font-weight: 800; display: flex; align-items: center; gap: 6px; margin-top: 4px; }
    
    .pillar-divider { padding: 24px 24px 12px; font-size: 0.65rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; display: flex; align-items: center; gap: 10px; }
    .pillar-divider::before { content: ''; width: 4px; height: 4px; background: #e2e8f0; border-radius: 50%; }

    .p-badge { background: linear-gradient(135deg, var(--primary), #0072FF); color: white; padding: 6px 14px; border-radius: 10px; font-size: 0.7rem; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; }

    /* Floating Study Widget */
    .ai-study-buddy { position: fixed; bottom: 40px; right: 40px; width: 380px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 30px; box-shadow: 0 30px 100px rgba(0,0,0,0.18); border: 1px solid var(--border); z-index: 2000; display: none; flex-direction: column; overflow: hidden; animation: floatIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    @keyframes floatIn { from { transform: translateY(100px) scale(0.8); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }

    @media (max-width: 1100px) { 
        .studio-container { grid-template-columns: 1fr; gap: 24px; padding: 12px; } 
        .playlist-card { order: 2; height: 500px; border-radius: 24px; }
        .studio-main { order: 1; }
        .studio-title { font-size: 1.6rem; letter-spacing: -0.8px; }
        .breadcrumb { font-size: 0.65rem; }
        .premium-card { border-radius: 20px; padding: 24px !important; }
        .resource-item { padding: 16px 20px; gap: 16px; }
        .resource-icon { width: 44px; height: 44px; font-size: 1.2rem; }
        .studio-hero { margin-bottom: 24px; }
        .video-theater { border-radius: 20px; margin-bottom: 24px; }
        .group-header { padding: 16px 20px; }
    }

    @media (max-width: 900px) {
        .studio-content-layout { display: flex !important; flex-direction: column; gap: 24px !important; }
        .module-brief-card { order: 1; }
        .studio-sidebar-meta { order: 2; width: 100%; }
        .studio-hero div { flex-direction: column; align-items: flex-start !important; gap: 12px; }
        .ai-study-buddy { width: 92%; right: 4%; bottom: 20px; }
    }
</style>

<div id="statusToast" class="status-toast"></div>

<div class="studio-container" id="studioContainer">
    <div class="studio-main">
        <div class="studio-hero">
            <nav class="breadcrumb" style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; font-size: 0.72rem; font-weight: 950; text-transform: uppercase; letter-spacing: 1.2px;">
                <a href="courses.php" style="text-decoration: none; color: #94a3b8; transition: 0.2s;">Learning Path</a> 
                <i class="fas fa-chevron-right" style="font-size: 0.55rem; color: #cbd5e1; opacity: 0.6;"></i>
                <span style="color: var(--primary); opacity: 0.9; cursor: default;"><?= htmlspecialchars($enrollment['title']) ?></span> 
                <i class="fas fa-chevron-right" style="font-size: 0.55rem; color: #cbd5e1; opacity: 0.6;"></i>
                <span style="color: #64748b; opacity: 0.4; font-weight: 800;">Module <?= $active_lesson['order_num'] ?></span>
            </nav>
            <div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 20px;">
                <h1 class="studio-title"><?= htmlspecialchars($active_lesson['title']) ?></h1>
                <div style="display: flex; gap: 12px; margin-bottom: 6px;">
                    <button onclick="toggleStudioFocus()" class="btn-action" style="background: white; border: 1px solid var(--border); color: var(--text-dim); padding: 12px 20px; font-weight: 800;"><i class="fas fa-magic"></i> Studio Focus</button>
                </div>
            </div>
        </div>

        <div class="video-theater">
            <?php 
            $is_quiz_lesson = ($active_lesson && ($active_lesson['lesson_type'] === 'quiz' || strpos(strtoupper($active_lesson['title']), 'QUIZ') !== false));
            $media_url = $active_lesson['video_url'] ?: $active_lesson['file_url'];
            $pdf_url = $active_lesson['pdf_resource'];

            if($is_quiz_lesson): 
            ?>
                <div class="vid-placeholder" style="background: linear-gradient(135deg, #0b0f19 0%, #1e1b4b 100%);">
                    <div style="background: rgba(0, 174, 239, 0.1); width: 100px; height: 100px; border-radius: 30px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; border: 1px solid rgba(0, 174, 239, 0.2);">
                        <i class="fas fa-brain fa-3x" style="color: var(--primary);"></i>
                    </div>
                    <h2 style="font-weight: 950; letter-spacing: -1.5px; margin-bottom: 8px;">KNOWLEDGE CHALLENGE UNLOCKED</h2>
                    <p style="opacity: 0.7; margin-bottom: 32px; font-weight: 500;">Please proceed to the assessment to validate your module mastery.</p>
                    <a href="take-quiz.php?id=<?= $active_lesson['attached_quiz_id'] ?: '4' ?>" class="btn-premium" style="padding: 18px 48px; border-radius: 50px;">START CHALLENGE NOW</a>
                </div>
            <?php elseif($media_url): ?>
                <?php if(strpos($media_url, 'youtube') !== false || strpos($media_url, 'vimeo') !== false): ?>
                    <iframe width="100%" height="100%" src="<?= strpos($media_url, 'youtube') !== false ? str_replace('watch?v=', 'embed/', $media_url) : $media_url ?>" frameborder="0" allowfullscreen></iframe>
                <?php else: ?>
                    <video controls style="width: 100%; height: 100%;"><source src="../<?= htmlspecialchars($media_url) ?>" type="video/mp4"></video>
                <?php endif; ?>
            <?php elseif($pdf_url): ?>
                <div class="vid-placeholder" style="background: #ffffff; color: var(--text-main); display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                    <div style="width: 100px; height: 100px; background: #FFF1F2; color: #ef4444; border-radius: 30px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; font-size: 2.8rem; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.1);"><i class="fas fa-file-pdf"></i></div>
                    <h3 style="font-weight: 950; margin: 0 0 8px; letter-spacing: -1px; text-transform: uppercase; font-size: 1.4rem;">Academic Resource Hub</h3>
                    <p style="opacity: 0.6; margin: 0 0 32px; font-weight: 700; font-size: 0.9rem; max-width: 400px; text-align: center;">The instructor has digitized a strategic physical resource for this practical module.</p>
                    <a href="../uploads/lessons/<?= htmlspecialchars($pdf_url) ?>" target="_blank" class="btn-premium" style="background: #ef4444; box-shadow: 0 15px 30px rgba(239, 68, 68, 0.25); border: none; padding: 18px 40px; border-radius: 50px;"><i class="fas fa-cloud-download-alt"></i> ACCESS LECTURE PDF</a>
                </div>
            <?php else: ?>
                <div class="vid-placeholder" style="background: #f8fafc; color: var(--text-dim);">
                    <div style="width: 80px; height: 80px; background: white; border: 1px solid #e2e8f0; border-radius: 20px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; font-size: 1.8rem; color: #cbd5e1;"><i class="fas fa-chalkboard"></i></div>
                    <h4 style="font-weight: 950; letter-spacing: -0.5px; margin: 0 0 8px; color: var(--text-main);">Technical Documentation Only</h4>
                    <p style="margin: 0; font-size: 0.85rem; font-weight: 700;">Please review the module concepts and technical brief below.</p>
                </div>
            <?php endif; ?>
        </div>

            <!-- Academic Learning Materials (Tutor Only) -->
            <?php if($active_lesson && ($active_lesson['pdf_resource'] || $active_lesson['lesson_type'] === 'pdf')): 
                $file_name = !empty($active_lesson['pdf_resource']) ? $active_lesson['pdf_resource'] : $active_lesson['file_url'];
                $display_name = !empty($active_lesson['title']) ? $active_lesson['title'] : "Practical Exercise";
                if(!empty($file_name)):
            ?>
            <div class="content-group-card">
                <div class="group-header">
                    <div class="group-chevron"><i class="fas fa-chevron-down"></i></div>
                    <h5 style="margin: 0; font-weight: 800; font-size: 1.1rem;">Academic Learning Materials</h5>
                </div>
                
                <div class="resource-item">
                    <div class="resource-icon"><i class="far fa-file-pdf" style="color: #ef4444;"></i></div>
                    <div class="resource-meta">
                        <a href="../uploads/lessons/<?= htmlspecialchars($file_name) ?>" class="resource-title" target="_blank"><?= htmlspecialchars($display_name) ?> PDF</a>
                        <span class="resource-info">Digital Resource (Academic Material)</span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="complete_lesson" value="<?= $active_lesson['id'] ?>">
                        <button type="submit" class="mark-done-btn <?= $lesson_done ? 'done' : '' ?>">
                            <?= $lesson_done ? 'Done' : 'Mark as done' ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; endif; ?>

            <!-- C.A.T Assessments (Tutor Only) -->
            <?php if($cat_quiz): ?>
            <div class="content-group-card">
                <div class="group-header">
                    <div class="group-chevron"><i class="fas fa-chevron-down"></i></div>
                    <h5 style="margin: 0; font-weight: 800; font-size: 1.1rem;">Continuous Assessment Test (C.A.T)</h5>
                </div>
                
                <div class="resource-item">
                    <div class="resource-icon"><i class="far fa-file-alt" style="color: #8b5cf6;"></i></div>
                    <div class="resource-meta">
                        <a href="take-quiz.php?id=<?= $cat_quiz['id'] ?>" class="resource-title"><?= htmlspecialchars($cat_quiz['title']) ?></a>
                        <span class="resource-info">Time Allocation: <?= $cat_quiz['time_limit_mins'] ?> mins | Min. Score: <?= $cat_quiz['pass_score'] ?>%</span>
                        
                        <?php 
                        $is_locked = (isset($active_lesson['release_date']) && strtotime($active_lesson['release_date']) > time());
                        if($is_locked): ?>
                        <div class="locked-notice">
                            <i class="fas fa-lock"></i>
                            <div>
                                Not available unless: It is after **<?= date('j F Y, g:i A', strtotime($active_lesson['release_date'])) ?>**
                                <a href="#" style="color: var(--primary); text-decoration: none; margin-left: auto; font-weight: 800;" onclick="event.preventDefault()">Show more <i class="fas fa-chevron-down"></i></a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="studio-content-layout" style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px; margin-top: 40px;">
                <div class="premium-card module-brief-card" style="padding: 40px;">
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
                        <div style="width: 50px; height: 50px; border-radius: 16px; background: #F0F9FF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <div>
                            <h4 style="font-weight: 950; margin: 0; font-size: 1.2rem; letter-spacing: -0.5px;">Module Brief</h4>
                            <p style="margin: 0; font-size: 0.75rem; font-weight: 700; color: var(--text-dim);">Core concepts & strategic objectives</p>
                        </div>
                    </div>
                    <div style="line-height: 2; color: var(--text-main); font-size: 1rem; font-weight: 500;">
                        <?= nl2br(htmlspecialchars($active_lesson['content'] ?? 'This lecture focuses on the foundational concepts of the module. Please follow along with the media above.')) ?>
                    </div>
                </div>

                <div class="studio-sidebar-meta">
                    <div class="premium-card" style="padding: 32px 24px; text-align: center; margin-bottom: 24px;">
                        <div style="position: relative; width: 80px; height: 80px; margin: 0 auto 16px;">
                            <img src="../<?= htmlspecialchars($enrollment['tutor_avatar'] ?: 'assets/images/user-placeholder.png') ?>" style="width: 100%; height: 100%; border-radius: 20px; object-fit: cover; border: 3px solid white; box-shadow: 0 10px 20px rgba(0,0,0,0.1);">
                            <div style="width: 16px; height: 16px; background: #22c55e; border: 3px solid white; border-radius: 50%; position: absolute; bottom: -2px; right: -2px;"></div>
                        </div>
                        <h6 style="margin: 0; font-weight: 950; font-size: 1.1rem; letter-spacing: -0.5px;"><?= htmlspecialchars($enrollment['tutor_name']) ?></h6>
                        <span style="font-size: 0.65rem; font-weight: 900; color: var(--primary); text-transform: uppercase; letter-spacing: 1.5px; display: block; margin-top: 4px;">Faculty Lead</span>
                        <div style="margin-top: 24px;">
                            <a href="messages.php?to=<?= $enrollment['tutor_id'] ?>" class="btn-action" style="display: block; width: 100%; background: #F8FAFC; border: 1px solid #E2E8F0; padding: 12px; font-size: 0.75rem; font-weight: 950; border-radius: 12px; transition: 0.3s; text-decoration: none; color: #1e293b; text-align: center;"><i class="fas fa-envelope"></i> MESSAGE TUTOR</a>
                        </div>
                    </div>

                    <button onclick="toggleAIStudyBuddy()" class="btn-premium" style="width: 100%; padding: 28px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3); border-radius: 30px; cursor: pointer; text-align: center; justify-content: center; gap: 12px;">
                        <i class="fas fa-sparkles"></i> <span>AI STUDY ASSISTANT</span>
                    </button>
                </div>
            </div>
    </div>

    <aside class="playlist-card">
        <div class="playlist-header">
            <h5 style="margin: 0; font-weight: 900; font-size: 1rem;">Curriculum Archive</h5>
            <div style="margin-top: 16px;">
                <div style="display: flex; justify-content: space-between; font-size: 0.7rem; font-weight: 900; text-transform: uppercase; color: var(--text-dim); margin-bottom: 8px;">
                    <span>Course Progress</span>
                    <span><?= (int)$enrollment['progress_percent'] ?>%</span>
                </div>
                <div style="height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                    <div style="height: 100%; background: linear-gradient(90deg, var(--primary), #0072FF); width: <?= (int)$enrollment['progress_percent'] ?>%; transition: 1s cubic-bezier(0.19, 1, 0.22, 1);"></div>
                </div>
            </div>
        </div>
        <div class="playlist-scroll">
            <?php foreach($lessons_by_week as $wnum => $wlessons): 
                $is_active_week = false;
                foreach($wlessons as $l) { if($active_lesson && $l['id'] == $active_lesson['id']) { $is_active_week = true; break; } }
            ?>
                <div class="pillar-divider" onclick="toggleWeek(<?= $wnum ?>)" style="cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none;">
                    <span>Level Pillar <?= $wnum ?></span>
                    <i class="fas fa-chevron-<?= $is_active_week ? 'down' : 'right' ?>" id="week-icon-<?= $wnum ?>" style="font-size: 0.7rem; opacity: 0.5;"></i>
                </div>
                <div id="week-content-<?= $wnum ?>" style="display: <?= $is_active_week ? 'block' : 'none' ?>;">
                    <?php foreach($wlessons as $l): ?>
                        <a href="?id=<?= $course_id ?>&lesson=<?= $l['id'] ?>" class="lesson-item <?= ($active_lesson && $l['id'] == $active_lesson['id']) ? 'active' : '' ?> <?= $l['progress_status'] === 'completed' ? 'completed' : '' ?>">
                            <div class="item-num"><?= $l['order_num'] ?></div>
                            <div class="lesson-info">
                                <h6 style="margin: 0; color: var(--text-main); font-weight: 900; font-size: 0.9rem; line-height: 1.3;"><?= htmlspecialchars($l['title']) ?></h6>
                                <div class="lesson-meta-label">
                                    <i class="<?= $l['lesson_type'] == 'video' ? 'fas fa-play-circle' : 'far fa-file-alt' ?>"></i> 
                                    <?= $l['duration_mins'] ?? '15' ?>m Explorer
                                </div>
                            </div>
                            <div class="item-check"><i class="fas fa-check"></i></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
</div>

<!-- AI Study Buddy Interface -->
<div class="ai-study-buddy" id="aiBuddy">
    <div style="padding: 24px; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="position: relative;">
                <div style="width: 12px; height: 12px; background: #22c55e; border-radius: 50%; border: 3px solid white; position: absolute; bottom: 0; right: 0; z-index: 5;"></div>
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fas fa-microchip"></i>
                </div>
            </div>
            <div>
                <h6 style="margin: 0; font-weight: 950; font-size: 0.85rem; letter-spacing: 0.5px;">SDA QUANTUM AI</h6>
                <span style="font-size: 0.65rem; font-weight: 800; opacity: 0.8; text-transform: uppercase;">Active Mentor</span>
            </div>
        </div>
        <button onclick="toggleAIStudyBuddy()" style="background: rgba(255,255,255,0.1); border: none; color: white; width: 32px; height: 32px; border-radius: 10px; cursor: pointer; transition: 0.3s;"><i class="fas fa-times"></i></button>
    </div>
    <div class="ai-chat" id="aiChat" style="padding: 24px; height: 350px; overflow-y: auto; display: flex; flex-direction: column; gap: 16px;">
        <div style="background: #f1f5f9; padding: 16px; border-radius: 18px 18px 18px 4px; border: 1px solid #e2e8f0; font-size: 0.85rem; font-weight: 600; line-height: 1.6; color: #334155;">
            Greetings! I'm synchronized with **Module <?= $active_lesson['order_num'] ?>: <?= htmlspecialchars($active_lesson['title']) ?>**. 
            How can I assist your learning today?
        </div>
    </div>
    <div style="padding: 24px; border-top: 1px solid #f1f5f9; display: flex; gap: 12px;">
        <input type="text" id="aiQuery" placeholder="Ask about this module..." style="flex: 1; padding: 15px 20px; border: 1px solid #e2e8f0; border-radius: 16px; outline: none; font-size: 0.85rem; font-weight: 600; background: #f8fafc; transition: 0.3s;" onkeypress="if(event.key === 'Enter') askAIBuddy()">
        <button onclick="askAIBuddy()" class="btn-premium" style="width: 54px; height: 54px; border-radius: 16px; border: none; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; cursor: pointer; transform-origin: center; transition: 0.3s; box-shadow: 0 10px 20px rgba(99,102,241,0.25);"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script>
    function toggleStudioFocus() {
        const container = document.getElementById('studioContainer');
        const sidebar = document.getElementById('dashSidebar');
        const btn = event.currentTarget;
        container.classList.toggle('focus-mode');
        
        if (container.classList.contains('focus-mode')) {
            sidebar.style.display = 'none';
            document.body.style.marginLeft = '0';
            btn.innerHTML = '<i class="fas fa-compress"></i> STANDARD';
            btn.style.background = 'var(--primary)';
            btn.style.color = 'white';
        } else {
            sidebar.style.display = 'flex';
            document.body.style.marginLeft = '280px';
            btn.innerHTML = '<i class="fas fa-magic"></i> STUDIO FOCUS';
            btn.style.background = 'white';
            btn.style.color = 'var(--text-dim)';
        }
    }

    function toggleAIStudyBuddy() {
        const buddy = document.getElementById('aiBuddy');
        buddy.style.display = (buddy.style.display === 'flex') ? 'none' : 'flex';
    }

    function askAIBuddy() {
        const q = document.getElementById('aiQuery');
        const chat = document.getElementById('aiChat');
        if(!q.value.trim()) return;

        const uMsg = document.createElement('div');
        uMsg.style.cssText = 'background: #F0F9FF; padding: 14px 18px; border-radius: 20px 20px 4px 20px; align-self: flex-end; max-width: 85%; font-size: 0.85rem; font-weight: 600; color: #0369a1; border: 1px solid #bae6fd;';
        uMsg.textContent = q.value;
        chat.appendChild(uMsg);

        const aiStatus = document.createElement('div');
        aiStatus.style.cssText = 'background: #f1f5f9; padding: 14px 18px; border-radius: 20px 20px 20px 4px; align-self: flex-start; font-size: 0.85rem; font-weight: 600; color: #475569;';
        aiStatus.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing query...';
        chat.appendChild(aiStatus);
        chat.scrollTop = chat.scrollHeight;

        const formData = new FormData();
        formData.append('action', 'mentor_chat');
        formData.append('query', q.value);
        formData.append('context', 'Classroom Mode. Lesson: <?= addslashes($active_lesson["title"]) ?>. Module Content: <?= addslashes(substr(strip_tags($active_lesson["content"]), 0, 500)) ?>');

        fetch('../includes/ai_controller.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                aiStatus.innerHTML = data.response || 'System calibration required. Please retry.';
                chat.scrollTop = chat.scrollHeight;
            });
        
        q.value = '';
    }

    function toggleWeek(wnum) {
        const content = document.getElementById('week-content-' + wnum);
        const icon = document.getElementById('week-icon-' + wnum);
        if(content.style.display === 'none') {
            content.style.display = 'block';
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
        } else {
            content.style.display = 'none';
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
        }
    }

    window.onload = function() {
        <?php if(isset($_GET['success'])): ?>
            const toast = document.getElementById('statusToast');
            toast.innerHTML = '<div style="display:flex; align-items:center; gap:12px; padding: 16px 24px; background: #10b981; color: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);"><i class="fas fa-check-circle"></i> Milestone Synchronized! Lesson Completed.</div>';
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 4000);
        <?php endif; ?>
    }
</script>

<style>
    .status-toast { position: fixed; top: 30px; left: 50%; transform: translateX(-50%); z-index: 9999; display: none; animation: slideDown 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    @keyframes slideDown { from { transform: translate(-50%, -100px); opacity: 0; } to { transform: translate(-50%, 0); opacity: 1; } }
</style>


<?php require_once 'includes/layout-bottom.php'; ?>
