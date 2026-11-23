<?php
$pageTitle = 'Classroom';
require_once 'includes/header.php';

$course_id = (int)($_GET['id'] ?? 0);
$lesson_id = (int)($_GET['lesson'] ?? 0);

if (!$course_id) { 
    header('Location: ./'); 
    exit; 
}

require_once __DIR__ . '/../includes/learning.php';

try {
    // 1. Check enrollment (completed learners keep access to review)
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.description, c.tutor_id, c.learning_modes, c.price, c.structured_price, c.sale_price, c.sale_ends_at,
                                  u.name as tutor_name, u.avatar as tutor_avatar, co.name AS cohort_name
                           FROM enrollments e 
                           JOIN courses c ON e.course_id = c.id 
                           JOIN users u ON c.tutor_id = u.id
                           LEFT JOIN course_cohorts co ON co.id = e.cohort_id
                           WHERE e.student_id = ? AND e.course_id = ? AND e.status IN ('active','completed')");
    $stmt->execute([$student['id'], $course_id]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        header('Location: ../enroll?id=' . $course_id);
        exit;
    }
    $is_structured = $enrollment['learning_mode'] === 'structured';

    // Tutorial-library and free skills courses use the full reader (exercises, quizzes, Try it,
    // auto-tracking). It applies the same track rules (weekly release, due dates, locks).
    $lib = $pdo->prepare("SELECT id, slug, source FROM courses WHERE id = ?");
    $lib->execute([$course_id]);
    $libCourse = $lib->fetch();
    if ($libCourse && in_array($libCourse['source'], ['tutorial', 'course'], true)) {
        $ls = $pdo->prepare("SELECT id, slug FROM lessons WHERE id = ? AND course_id = ?");
        $ls->execute([$lesson_id, $course_id]);
        $toLesson = $ls->fetch() ?: null;
        header('Location: ' . sda_base_href() . learn_url($libCourse, $toLesson));
        exit;
    }

    // 2. Lessons with progress, then the track's schedule (self-paced: open; structured: weekly + sequential + quiz gates)
    $lessons = apply_lesson_schedule(load_course_lessons($pdo, $course_id, (int)$student['id']), $enrollment);

    $lessons_by_week = [];
    foreach ($lessons as $l) { $lessons_by_week[$l['week_num']][] = $l; }

    $active_lesson = null;
    $active_idx = 0;
    foreach ($lessons as $i => $l) { if ($l['id'] == $lesson_id) { $active_lesson = $l; $active_idx = $i; break; } }

    if (!$active_lesson && !empty($lessons)) {
        // Resume at the first open lesson not yet completed.
        $resume = $lessons[0];
        foreach ($lessons as $l) { if (!$l['is_locked'] && $l['progress_status'] !== 'completed') { $resume = $l; break; } }
        header("Location: classroom?id=$course_id&lesson=" . $resume['id']);
        exit;
    }
    $prev_lesson = $active_lesson && $active_idx > 0 ? $lessons[$active_idx - 1] : null;
    $next_lesson = $active_lesson && $active_idx < count($lessons) - 1 ? $lessons[$active_idx + 1] : null;

    if ($active_lesson && !$active_lesson['is_locked'] && (!$active_lesson['progress_status'] || $active_lesson['progress_status'] === 'not_started')) {
        $stmt = $pdo->prepare("INSERT INTO lesson_progress (student_id, lesson_id, course_id, status) 
                               VALUES (?, ?, ?, 'in_progress') 
                               ON DUPLICATE KEY UPDATE status = IF(status = 'completed', 'completed', 'in_progress')");
        $stmt->execute([$student['id'], $active_lesson['id'], $course_id]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_lesson'])) {
        $l_to_complete = (int)$_POST['complete_lesson'];
        $target = null;
        foreach ($lessons as $l) { if ($l['id'] == $l_to_complete) { $target = $l; break; } }
        // Lessons can be read any time; completing one needs the previous lesson done and this lesson's tests passed.
        if (verifyCsrf() && $target && !$target['is_locked'] && lesson_completion_block($pdo, (int)$student['id'], $lessons, $l_to_complete) === null) {
            complete_lesson($pdo, $enrollment, $l_to_complete);
            $go = (!empty($_POST['and_next']) && $next_lesson) ? $next_lesson['id'] : $l_to_complete;
            header("Location: classroom?id=$course_id&lesson=$go&success=1");
        } else {
            header("Location: classroom?id=$course_id&lesson=$l_to_complete");
        }
        exit;
    }

    // 5. Fetch linked C.A.T Assessment (Quiz type 'cat')
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE (linked_lesson_id = ? OR (course_id = ? AND linked_week_num = ?)) AND type = 'cat' LIMIT 1");
    $stmt->execute([$active_lesson['id'] ?? 0, $course_id, $active_lesson['week_num'] ?? 0]);
    $cat_quiz = $stmt->fetch();

    $lesson_done = ($active_lesson['progress_status'] ?? '') === 'completed';

} catch (Exception $e) { 
    error_log($e->getMessage()); 
    $lessons = []; $lessons_by_week = []; $active_lesson = null; $cat_quiz = null; $lesson_done = false; $prev_lesson = $next_lesson = null; $is_structured = false;
}

require_once 'includes/layout-top.php';
if (!$active_lesson) {
    echo '<div class="sp-page-head"><div><h1>' . e($enrollment['title'] ?? 'Course') . '</h1><p>Your course classroom.</p></div></div>'
       . '<div class="sp-empty"><div class="sp-ico"><i class="fas fa-hourglass-half"></i></div><h3>Lessons are on the way</h3>'
       . '<p>Lessons for this course are being prepared. Check back soon.</p><a href="courses" class="sp-btn">Back to my courses</a></div>';
    require_once 'includes/layout-bottom.php';
    exit;
}
?>
<link rel="stylesheet" href="../assets/css/learn.css">
<?php
$lesson_total = count($lessons);
$lesson_done_count = count(array_filter($lessons, fn($l) => ($l['progress_status'] ?? '') === 'completed'));
$course_pct = max(0, min(100, (int)$enrollment['progress_percent']));
$is_quiz_lesson = (!empty($active_lesson['attached_quiz_id']) && ($active_lesson['lesson_type'] === 'quiz' || strpos(strtoupper((string)$active_lesson['title']), 'QUIZ') !== false));
$media_url = $active_lesson['video_url'] ?: $active_lesson['file_url'];
$pdf_url = $active_lesson['pdf_resource'];
$lesson_kind = $is_quiz_lesson ? 'Quiz' : ($active_lesson['lesson_type'] === 'video' ? 'Video' : ($active_lesson['lesson_type'] === 'pdf' ? 'Reading (PDF)' : 'Reading'));
?>
<style>
    .pg-classroom .cr-crumbs { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: .8rem; font-weight: 600; color: var(--text-dim); margin-bottom: 6px; }
    .pg-classroom .cr-crumbs a { color: var(--text-dim); text-decoration: none; }
    .pg-classroom .cr-crumbs a:hover { color: var(--primary-dark); }
    .pg-classroom .cr-crumbs i { font-size: .6rem; opacity: .6; }
    .pg-classroom .cr-crumbs span { color: var(--primary-dark); }
    .pg-classroom .sp-page-head { margin-bottom: 16px; }
    .pg-classroom .sp-page-head h1 { overflow-wrap: anywhere; }

    .pg-classroom .cr-track { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; padding: 12px 16px; margin-bottom: 20px; font-size: .84rem; }
    .pg-classroom .cr-track .cr-meta { color: var(--text-dim); font-weight: 600; }
    .pg-classroom .cr-track .cr-links { margin-left: auto; display: flex; gap: 16px; flex-wrap: wrap; }

    .pg-classroom .cr-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
    .pg-classroom.focus .cr-layout { grid-template-columns: minmax(0, 1fr); }
    .pg-classroom.focus .cr-side { display: none; }
    .pg-classroom .cr-main > * + * { margin-top: 16px; }

    .pg-classroom .cr-video { background: #000; border-radius: var(--radius-lg); overflow: hidden; aspect-ratio: 16 / 9; }
    .pg-classroom .cr-video iframe, .pg-classroom .cr-video video { width: 100%; height: 100%; display: block; border: 0; }
    .pg-classroom .cr-feature { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .pg-classroom .cr-feature .sp-ico { width: 52px; height: 52px; border-radius: 14px; font-size: 1.3rem; }
    .pg-classroom .cr-feature h3 { margin: 0; font-size: 1.05rem; font-weight: 800; }
    .pg-classroom .cr-feature p { margin: 2px 0 0; color: var(--text-dim); font-size: .86rem; }
    .pg-classroom .cr-feature.dark p { color: rgba(255,255,255,.75); }

    .pg-classroom .cr-lesson { padding: 28px; }
    .pg-classroom .cr-lesson .lr-content { color: var(--text-main); font-size: 1rem; overflow-wrap: anywhere; }
    .pg-classroom .cr-lesson .lr-content img { max-width: 100%; height: auto; }
    .pg-classroom .cr-lesson pre { overflow-x: auto; max-width: 100%; }

    .pg-classroom .cr-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 16px; }
    .pg-classroom .cr-actions form { margin: 0; }
    .pg-classroom .cr-actions .cr-spacer { flex: 1; }

    /* outline */
    .pg-classroom .cr-side { position: sticky; top: calc(var(--topbar-h) + 16px); display: flex; flex-direction: column; gap: 16px; }
    .pg-classroom .cr-outline { padding: 0; overflow: hidden; display: flex; flex-direction: column; max-height: calc(100vh - var(--topbar-h) - 140px); }
    .pg-classroom .cr-outline-head { padding: 16px 18px; border-bottom: 1px solid var(--border); }
    .pg-classroom .cr-outline-head h3 { margin: 0 0 10px; font-size: 1rem; font-weight: 800; }
    .pg-classroom .cr-outline-scroll { overflow-y: auto; padding: 6px 8px 10px; }
    .pg-classroom .cr-week { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 10px 6px; border: 0; background: none; cursor: pointer; font: inherit; font-size: .74rem; font-weight: 700; color: var(--text-dim); text-align: left; }
    .pg-classroom .cr-week i { font-size: .65rem; }
    .pg-classroom .cr-item { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 12px; text-decoration: none; color: var(--text-main); border: 1px solid transparent; }
    .pg-classroom .cr-item:hover { background: var(--surface-2); }
    .pg-classroom .cr-item.active { background: var(--primary-soft); border-color: rgba(0,163,224,.35); }
    .pg-classroom .cr-item.is-locked { opacity: .55; }
    .pg-classroom .cr-num { width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .78rem; font-weight: 800; background: #EEF2F6; color: var(--text-dim); }
    .pg-classroom .cr-item.active .cr-num { background: var(--primary); color: #fff; }
    .pg-classroom .cr-item.completed .cr-num { background: var(--success); color: #fff; }
    .pg-classroom .cr-item h5 { margin: 0; font-size: .86rem; font-weight: 700; line-height: 1.3; }
    .pg-classroom .cr-item small { display: flex; align-items: center; gap: 6px; margin-top: 2px; font-size: .72rem; color: var(--text-dim); }

    .pg-classroom .cr-tutor { display: flex; align-items: center; gap: 12px; }
    .pg-classroom .cr-tutor img { width: 48px; height: 48px; border-radius: 14px; object-fit: cover; flex-shrink: 0; background: var(--primary-soft); }

    /* AI study helper */
    .cr-ai { position: fixed; bottom: 24px; right: 24px; width: 380px; max-width: calc(100vw - 32px); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: var(--shadow-premium); z-index: 2000; display: none; flex-direction: column; overflow: hidden; }
    .cr-ai-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 16px; border-bottom: 1px solid var(--border); }
    .cr-ai-head strong { display: block; font-size: .9rem; }
    .cr-ai-head span { font-size: .74rem; color: var(--text-dim); }
    .cr-ai-chat { padding: 16px; height: 320px; max-height: 50vh; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; font-size: .86rem; }
    .cr-ai-chat .msg { padding: 10px 14px; border-radius: 14px; max-width: 88%; line-height: 1.5; overflow-wrap: anywhere; }
    .cr-ai-chat .msg.bot { background: var(--surface-2); border: 1px solid var(--border); align-self: flex-start; border-bottom-left-radius: 4px; }
    .cr-ai-chat .msg.me { background: var(--primary-soft); color: #0B4F73; align-self: flex-end; border-bottom-right-radius: 4px; }
    .cr-ai-foot { display: flex; gap: 8px; padding: 12px; border-top: 1px solid var(--border); }
    .cr-ai-foot input { flex: 1; min-width: 0; height: 42px; padding: 0 14px; border-radius: 12px; border: 1px solid var(--border-strong); font: inherit; font-size: .88rem; outline: none; }
    .cr-ai-foot input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }

    .status-toast { position: fixed; top: 84px; left: 50%; transform: translateX(-50%); z-index: 9999; display: none; max-width: calc(100vw - 32px); }
    .status-toast > div { display: flex; align-items: center; gap: 10px; padding: 12px 18px; background: var(--success); color: #fff; border-radius: 12px; box-shadow: var(--shadow-md); font-weight: 700; font-size: .88rem; }

    @media (max-width: 1100px) {
        .pg-classroom .cr-layout { grid-template-columns: minmax(0, 1fr); }
        .pg-classroom .cr-side { position: static; }
        .pg-classroom .cr-outline { max-height: 520px; }
    }
    @media (max-width: 640px) {
        .pg-classroom .cr-lesson { padding: 18px; }
        .pg-classroom .cr-track .cr-links { margin-left: 0; }
        .pg-classroom .cr-actions .sp-btn { flex: 1 1 auto; }
        .pg-classroom .cr-actions .cr-spacer { display: none; }
        .cr-ai { right: 16px; bottom: 16px; }
    }
</style>

<div id="statusToast" class="status-toast" role="status"></div>

<div class="pg-classroom" id="studioContainer">
    <div class="sp-page-head">
        <div style="min-width: 0;">
            <nav class="cr-crumbs" aria-label="Breadcrumb">
                <a href="courses">My courses</a> <i class="fas fa-chevron-right"></i>
                <span><?= htmlspecialchars($enrollment['title']) ?></span>
            </nav>
            <h1><?= htmlspecialchars($active_lesson['title']) ?></h1>
            <p>Lesson <?= $active_idx + 1 ?> of <?= $lesson_total ?> · <?= $lesson_kind ?><?php if (!empty($active_lesson['duration_mins'])): ?> · <?= (int)$active_lesson['duration_mins'] ?> min<?php endif; ?></p>
        </div>
        <div class="sp-actions">
            <button type="button" class="sp-btn ghost sm" onclick="toggleStudioFocus(this)" id="focusBtn"><i class="fas fa-expand"></i> <span>Hide outline</span></button>
        </div>
    </div>

    <div class="sp-card cr-track">
        <span class="sp-badge <?= $is_structured ? 'orange' : '' ?>"><i class="fas <?= $is_structured ? 'fa-calendar-week' : 'fa-person-walking' ?>"></i> <?= e(mode_label($enrollment['learning_mode'])) ?></span>
        <?php if ($is_structured): ?>
            <span class="cr-meta"><?= $enrollment['cohort_name'] ? e($enrollment['cohort_name']) . ' · ' : '' ?>Started <?= date('M j, Y', strtotime($enrollment['starts_on'] ?: 'today')) ?></span>
            <?php if (!empty($active_lesson['due_at'])): ?><span class="cr-meta"><i class="far fa-clock"></i> Due <?= date('D, M j', $active_lesson['due_at']) ?></span><?php endif; ?>
        <?php else: ?>
            <span class="cr-meta">Every lesson is open. Go in any order.</span>
        <?php endif; ?>
        <?php if (!empty($enrollment['expires_at'])): ?><span class="cr-meta">Access until <?= date('M j, Y', strtotime($enrollment['expires_at'])) ?></span><?php endif; ?>
        <span class="cr-links">
            <?php if (count(course_modes($enrollment)) > 1): ?><a class="sp-link" href="../enroll?id=<?= $course_id ?>">Change track</a><?php endif; ?>
            <a class="sp-link" href="../learn?course=<?= $course_id ?>&lesson=<?= (int)$active_lesson['id'] ?>"><i class="fas fa-book-open"></i> Reading view</a>
        </span>
    </div>

    <div class="cr-layout">
        <div class="cr-main">
        <?php if (!empty($active_lesson['is_locked'])): ?>
            <div class="sp-alert warning" style="margin: 0;"><i class="fas fa-lock" style="margin-top: 3px;"></i><div>
                <strong><?= e($active_lesson['lock_reason']) ?></strong>
                <?= $is_structured ? 'You are on the structured track, so lessons open on a weekly schedule and in order.' : '' ?>
                <?php if ($is_structured && in_array('self_paced', course_modes($enrollment), true)): ?>
                    Prefer to go faster? <a href="../enroll?id=<?= $course_id ?>&mode=self_paced" style="font-weight: 700;">Switch to self-paced</a>.
                <?php endif; ?>
                <?php if ($prev_lesson): ?><div style="margin-top: 12px;"><a class="sp-btn sm" href="?id=<?= $course_id ?>&lesson=<?= (int)$prev_lesson['id'] ?>"><i class="fas fa-chevron-left"></i> Back to <?= e($prev_lesson['title']) ?></a></div><?php endif; ?>
            </div></div>
        <?php else: ?>

            <?php if ($is_quiz_lesson): ?>
                <div class="sp-card dark cr-feature">
                    <span class="sp-ico" style="background: rgba(255,255,255,.1); color: #fff;"><i class="fas fa-circle-question"></i></span>
                    <div class="sp-grow" style="flex: 1; min-width: 200px;">
                        <h3>Quiz time</h3>
                        <p>Check what you have learned in this part of the course.</p>
                    </div>
                    <a href="take-quiz?id=<?= (int)$active_lesson['attached_quiz_id'] ?>" class="sp-btn">Start quiz <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php elseif ($media_url): ?>
                <div class="cr-video">
                    <?php if (strpos($media_url, 'youtube') !== false || strpos($media_url, 'vimeo') !== false): ?>
                        <iframe src="<?= e(video_embed_url($media_url)) ?>" title="<?= e($active_lesson['title']) ?>" allowfullscreen></iframe>
                    <?php else: ?>
                        <video controls><source src="../<?= htmlspecialchars($media_url) ?>" type="video/mp4"></video>
                    <?php endif; ?>
                </div>
            <?php elseif ($pdf_url): ?>
                <div class="sp-card cr-feature">
                    <span class="sp-ico red"><i class="fas fa-file-pdf"></i></span>
                    <div style="flex: 1; min-width: 200px;">
                        <h3>Lesson notes (PDF)</h3>
                        <p>Your tutor shared the notes for this lesson as a PDF.</p>
                    </div>
                    <a href="../uploads/lessons/<?= htmlspecialchars($pdf_url) ?>" target="_blank" rel="noopener" class="sp-btn"><i class="fas fa-download"></i> Open PDF</a>
                </div>
            <?php endif; ?>

            <?php if ($active_lesson['pdf_resource'] || $active_lesson['lesson_type'] === 'pdf'):
                $file_name = !empty($active_lesson['pdf_resource']) ? $active_lesson['pdf_resource'] : $active_lesson['file_url'];
                $display_name = !empty($active_lesson['title']) ? $active_lesson['title'] : 'Practical exercise';
                if (!empty($file_name)):
            ?>
            <div class="sp-card">
                <div class="sp-card-head" style="margin-bottom: 4px;"><h3>Learning materials</h3></div>
                <div class="sp-list">
                    <div class="sp-list-item">
                        <span class="sp-ico red"><i class="far fa-file-pdf"></i></span>
                        <div class="sp-grow">
                            <h4><a href="../uploads/lessons/<?= htmlspecialchars($file_name) ?>" target="_blank" rel="noopener" style="text-decoration: none;"><?= htmlspecialchars($display_name) ?> (PDF)</a></h4>
                            <p>Download or open in a new tab</p>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                            <input type="hidden" name="complete_lesson" value="<?= (int)$active_lesson['id'] ?>">
                            <button type="submit" class="sp-btn sm <?= $lesson_done ? '' : 'ghost' ?>" <?= $lesson_done ? 'style="background: var(--success);"' : '' ?>>
                                <?= $lesson_done ? '<i class="fas fa-check"></i> Done' : 'Mark as done' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; endif; ?>

            <?php if ($cat_quiz):
                $cat_locked = (isset($active_lesson['release_date']) && strtotime($active_lesson['release_date']) > time());
            ?>
            <div class="sp-card">
                <div class="sp-card-head" style="margin-bottom: 4px;"><h3>Assessment test (CAT)</h3></div>
                <div class="sp-list">
                    <div class="sp-list-item">
                        <span class="sp-ico violet"><i class="far fa-file-lines"></i></span>
                        <div class="sp-grow">
                            <h4><?= htmlspecialchars($cat_quiz['title']) ?></h4>
                            <p><?= (int)$cat_quiz['time_limit_mins'] ?> min · Pass mark <?= (int)$cat_quiz['pass_score'] ?>%</p>
                            <?php if ($cat_locked): ?>
                                <p style="color: #A16207;"><i class="fas fa-lock"></i> Opens <?= date('j F Y, g:i A', strtotime($active_lesson['release_date'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <a href="take-quiz?id=<?= (int)$cat_quiz['id'] ?>" class="sp-btn sm">Open test</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <article class="sp-card cr-lesson">
                <div class="lr-content">
                    <?= trim((string)$active_lesson['content']) !== '' ? render_lesson_text($active_lesson['content']) : '<p>Follow along with the video or notes above.</p>' ?>
                </div>
                <?php if (!empty($active_lesson['code_example'])): ?>
                <div class="lr-example"><h3>Example</h3><pre><code><?= e($active_lesson['code_example']) ?></code></pre>
                    <a class="lr-btn lr-btn-ok" href="../tryit?lesson=<?= (int)$active_lesson['id'] ?>" target="_blank" rel="noopener"><?= tryit_runnable($active_lesson['code_lang']) ? 'Try it yourself' : 'Open in editor' ?> <i class="fas fa-arrow-up-right-from-square"></i></a></div>
                <?php endif; ?>
            </article>

            <div class="sp-card cr-actions">
                <?php if ($prev_lesson): ?><a class="sp-btn ghost" href="?id=<?= $course_id ?>&lesson=<?= (int)$prev_lesson['id'] ?>"><i class="fas fa-chevron-left"></i> Previous</a><?php endif; ?>
                <?php if (!empty($active_lesson['code_example'])): ?><a class="sp-btn ghost" href="../tryit?lesson=<?= (int)$active_lesson['id'] ?>" target="_blank" rel="noopener"><i class="fas fa-code"></i> Try it yourself</a><?php endif; ?>
                <span class="cr-spacer"></span>
                <?php if ($lesson_done): ?>
                    <span class="sp-badge green"><i class="fas fa-check"></i> Completed</span>
                    <?php if ($next_lesson): ?>
                        <a class="sp-btn" href="?id=<?= $course_id ?>&lesson=<?= (int)$next_lesson['id'] ?>">Next lesson <i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <a class="sp-btn" href="courses">Back to my courses</a>
                    <?php endif; ?>
                <?php elseif (($crBlock = lesson_completion_block($pdo, (int)$student['id'], $lessons, (int)$active_lesson['id'])) !== null): ?>
                    <div class="sp-alert warning" style="margin:0;flex:1 1 100%"><i class="fas fa-lock" style="margin-top:3px"></i><div><?= e($crBlock) ?></div></div>
                    <?php if ($next_lesson): ?><a class="sp-btn ghost" href="?id=<?= $course_id ?>&lesson=<?= (int)$next_lesson['id'] ?>">Read next lesson <i class="fas fa-chevron-right"></i></a><?php endif; ?>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                        <input type="hidden" name="complete_lesson" value="<?= (int)$active_lesson['id'] ?>">
                        <button class="sp-btn" style="background: var(--success);" name="and_next" value="1"><i class="fas fa-check"></i> Mark complete<?= $next_lesson ? ' &amp; next lesson' : '' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>

        <aside class="cr-side" id="courseOutline">
            <div class="sp-card cr-outline">
                <div class="cr-outline-head">
                    <h3>Course outline</h3>
                    <div class="sp-between sp-small" style="margin-bottom: 6px;">
                        <span class="sp-muted"><?= $lesson_done_count ?> of <?= $lesson_total ?> lessons done</span>
                        <strong><?= $course_pct ?>%</strong>
                    </div>
                    <div class="sp-progress <?= $course_pct >= 100 ? 'green' : '' ?>"><span style="width: <?= max(2, $course_pct) ?>%;"></span></div>
                </div>
                <div class="cr-outline-scroll">
                    <?php foreach ($lessons_by_week as $wnum => $wlessons):
                        $wnum = (int)$wnum;
                        $is_active_week = false;
                        foreach ($wlessons as $l) { if ($l['id'] == $active_lesson['id']) { $is_active_week = true; break; } }
                    ?>
                        <button type="button" class="cr-week" onclick="toggleWeek(<?= $wnum ?>)" aria-expanded="<?= $is_active_week ? 'true' : 'false' ?>">
                            <span><?= $is_structured ? 'Week' : 'Module' ?> <?= $wnum ?><?php if ($is_structured && !empty($wlessons[0]['opens_at'])): ?> · <?= $wlessons[0]['opens_at'] > time() ? 'opens ' : 'opened ' ?><?= date('M j', $wlessons[0]['opens_at']) ?><?php endif; ?></span>
                            <i class="fas fa-chevron-<?= $is_active_week ? 'down' : 'right' ?>" id="week-icon-<?= $wnum ?>"></i>
                        </button>
                        <div id="week-content-<?= $wnum ?>" style="display: <?= $is_active_week ? 'block' : 'none' ?>;">
                            <?php foreach ($wlessons as $l):
                                $l_done = $l['progress_status'] === 'completed';
                                $l_active = $l['id'] == $active_lesson['id'];
                            ?>
                                <a href="?id=<?= $course_id ?>&lesson=<?= (int)$l['id'] ?>" class="cr-item <?= $l_active ? 'active' : '' ?> <?= $l_done ? 'completed' : '' ?> <?= $l['is_locked'] ? 'is-locked' : '' ?>" <?= $l['is_locked'] ? 'title="' . e($l['lock_reason']) . '"' : '' ?> <?= $l_active ? 'aria-current="page"' : '' ?>>
                                    <span class="cr-num"><?= $l_done ? '<i class="fas fa-check"></i>' : (int)$l['order_num'] ?></span>
                                    <span style="min-width: 0;">
                                        <h5><?= htmlspecialchars($l['title']) ?></h5>
                                        <small>
                                            <i class="<?= $l['lesson_type'] == 'video' ? 'fas fa-play-circle' : 'far fa-file-alt' ?>"></i>
                                            <?= $l['is_locked'] ? '<i class="fas fa-lock"></i> ' . e($l['lock_reason']) : (int)($l['duration_mins'] ?: 15) . ' min' ?>
                                        </small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sp-card">
                <div class="cr-tutor">
                    <img src="../<?= htmlspecialchars($enrollment['tutor_avatar'] ?: 'assets/images/user-placeholder.png') ?>" alt="" onerror="this.style.visibility='hidden'">
                    <div style="min-width: 0;">
                        <span class="sp-muted sp-small">Your tutor</span>
                        <strong style="display: block;" class="sp-truncate"><?= htmlspecialchars($enrollment['tutor_name']) ?></strong>
                    </div>
                </div>
                <div style="display: grid; gap: 8px; margin-top: 14px;">
                    <a href="messages?to=<?= (int)$enrollment['tutor_id'] ?>" class="sp-btn ghost block"><i class="far fa-envelope"></i> Message tutor</a>
                    <button type="button" onclick="toggleAIStudyBuddy()" class="sp-btn soft block"><i class="fas fa-wand-magic-sparkles"></i> Ask the AI study helper</button>
                </div>
            </div>
        </aside>
    </div>
</div>

<!-- AI study helper -->
<div class="cr-ai" id="aiBuddy" role="dialog" aria-label="AI study helper">
    <div class="cr-ai-head">
        <div class="sp-row" style="gap: 10px; flex-wrap: nowrap;">
            <span class="sp-ico violet" style="width: 36px; height: 36px;"><i class="fas fa-wand-magic-sparkles"></i></span>
            <div><strong>AI study helper</strong><span>Ask about this lesson</span></div>
        </div>
        <button type="button" class="sp-icon-btn" onclick="toggleAIStudyBuddy()" aria-label="Close" style="width: 34px; height: 34px;"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="cr-ai-chat" id="aiChat">
        <div class="msg bot">Hi! I can help with "<?= htmlspecialchars($active_lesson['title']) ?>". What would you like to know?</div>
    </div>
    <div class="cr-ai-foot">
        <input type="text" id="aiQuery" placeholder="Ask a question..." onkeypress="if(event.key === 'Enter') askAIBuddy()" aria-label="Your question">
        <button type="button" onclick="askAIBuddy()" class="sp-btn" aria-label="Send" style="padding: 0 14px;"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script>
    // Focus mode: hide the course outline so the lesson gets the full width.
    function toggleStudioFocus(btn) {
        const container = document.getElementById('studioContainer');
        const on = container.classList.toggle('focus');
        btn.innerHTML = on ? '<i class="fas fa-compress"></i> <span>Show outline</span>' : '<i class="fas fa-expand"></i> <span>Hide outline</span>';
    }

    function toggleAIStudyBuddy() {
        const buddy = document.getElementById('aiBuddy');
        buddy.style.display = (buddy.style.display === 'flex') ? 'none' : 'flex';
        if (buddy.style.display === 'flex') document.getElementById('aiQuery').focus();
    }

    function askAIBuddy() {
        const q = document.getElementById('aiQuery');
        const chat = document.getElementById('aiChat');
        if (!q.value.trim()) return;

        const uMsg = document.createElement('div');
        uMsg.className = 'msg me';
        uMsg.textContent = q.value;
        chat.appendChild(uMsg);

        const aiStatus = document.createElement('div');
        aiStatus.className = 'msg bot';
        aiStatus.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Thinking...';
        chat.appendChild(aiStatus);
        chat.scrollTop = chat.scrollHeight;

        const formData = new FormData();
        formData.append('action', 'mentor_chat');
        formData.append('query', q.value);
        formData.append('context', <?= json_encode('Classroom Mode. Lesson: ' . $active_lesson['title'] . '. Module Content: ' . mb_substr(strip_tags((string)$active_lesson['content']), 0, 500), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>);

        fetch('../includes/ai_controller', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                aiStatus.innerHTML = data.response || 'Sorry, I could not answer that. Please try again.';
                chat.scrollTop = chat.scrollHeight;
            })
            .catch(() => { aiStatus.textContent = 'Sorry, the study helper is not available right now. Please try again.'; });

        q.value = '';
    }

    function toggleWeek(wnum) {
        const content = document.getElementById('week-content-' + wnum);
        const icon = document.getElementById('week-icon-' + wnum);
        const open = content.style.display === 'none';
        content.style.display = open ? 'block' : 'none';
        icon.classList.toggle('fa-chevron-right', !open);
        icon.classList.toggle('fa-chevron-down', open);
        if (icon.parentElement) icon.parentElement.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    window.addEventListener('load', function () {
        const cur = document.querySelector('.cr-item.active');
        const box = document.querySelector('.cr-outline-scroll');
        if (cur && box && window.innerWidth > 1100) box.scrollTop = cur.offsetTop - box.offsetTop - 60;
        <?php if (isset($_GET['success'])): ?>
            const toast = document.getElementById('statusToast');
            toast.innerHTML = '<div><i class="fas fa-check-circle"></i> Lesson marked complete. Nice work!</div>';
            toast.style.display = 'block';
            setTimeout(() => toast.style.display = 'none', 4000);
        <?php endif; ?>
    });
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
