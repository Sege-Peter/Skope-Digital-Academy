<?php
/**
 * Tutor: lessons of a course (grouped by week), add/edit/delete, optional AI draft.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
$error = '';

// AI lesson draft (AJAX, JSON only)
if (isset($_POST['ai_lesson_gen'])) {
    header('Content-Type: application/json');
    if (!verifyCsrf()) { echo json_encode(['error' => 'Session expired. Refresh the page.']); exit; }
    try {
        require_once __DIR__ . '/../includes/ai-handler.php';
        echo json_encode(SDAC_AI::generateLesson((string)($_POST['topic'] ?? '')));
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

function tp_sync_lesson_count(PDO $pdo, int $courseId): void {
    $pdo->prepare("UPDATE courses SET total_lessons = (SELECT COUNT(*) FROM lessons WHERE course_id = ?) WHERE id = ?")->execute([$courseId, $courseId]);
}

// Save a lesson
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {
    $id = (int)($_POST['id'] ?? 0) ?: null;
    $title = trim((string)($_POST['title'] ?? ''));
    $type = in_array($_POST['lesson_type'] ?? '', ['video', 'pdf', 'text', 'audio', 'image'], true) ? $_POST['lesson_type'] : 'text';
    $duration = max(0, (int)($_POST['duration_mins'] ?? 0));
    $week = max(1, (int)($_POST['week_num'] ?? 1));
    $order = (int)($_POST['order_num'] ?? 0);
    $video_url = trim((string)($_POST['video_url'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $code_lang = in_array($_POST['code_lang'] ?? '', ['html', 'css', 'javascript', 'python', 'php', 'sql', 'text'], true) ? $_POST['code_lang'] : null;
    $code_example = $code_lang ? rtrim((string)($_POST['code_example'] ?? '')) : '';
    if ($code_example === '') { $code_lang = null; $code_example = null; }
    $is_preview = !empty($_POST['is_preview']) ? 1 : 0;
    $release_date = !empty($_POST['release_date']) && strtotime($_POST['release_date']) ? date('Y-m-d H:i:s', strtotime($_POST['release_date'])) : null;

    if (!verifyCsrf()) $error = 'Your session expired. Please refresh the page and try again.';
    elseif (!$course_id || !td_owns_course($pdo, $tid, $course_id)) $error = 'You can only change lessons in your own courses.';
    elseif ($title === '') $error = 'Give the lesson a title.';

    // Existing file stays unless a new PDF is uploaded
    $file_url = null;
    if (!$error && $id) {
        $ex = $pdo->prepare("SELECT file_url FROM lessons WHERE id = ? AND course_id = ?");
        $ex->execute([$id, $course_id]);
        $file_url = $ex->fetchColumn();
        if ($file_url === false) $error = 'That lesson was not found.';
    }
    if (!$error && isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['pdf_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $head = (string)@file_get_contents($file['tmp_name'], false, null, 0, 5);
        if ($ext !== 'pdf' || $head !== '%PDF-') $error = 'Only PDF files can be uploaded.';
        elseif ($file['size'] > 20 * 1024 * 1024) $error = 'The PDF is larger than 20 MB.';
        else {
            $dir = __DIR__ . '/../uploads/lessons/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $filename = 'LESSON_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) { $file_url = $filename; $type = 'pdf'; }
            else $error = 'The PDF could not be uploaded. Please try again.';
        }
    }

    if (!$error) {
        try {
            if ($id) {
                $pdo->prepare("UPDATE lessons SET title=?, lesson_type=?, duration_mins=?, week_num=?, order_num=?, file_url=?, video_url=?, content=?, code_lang=?, code_example=?, is_preview=?, release_date=? WHERE id=? AND course_id=?")
                    ->execute([$title, $type, $duration, $week, $order, $file_url, $video_url, $content, $code_lang, $code_example, $is_preview, $release_date, $id, $course_id]);
                td_flash('ok', 'Lesson saved.');
            } else {
                $pdo->prepare("INSERT INTO lessons (course_id, title, lesson_type, duration_mins, week_num, order_num, file_url, video_url, content, code_lang, code_example, is_preview, release_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$course_id, $title, $type, $duration, $week, $order, $file_url, $video_url, $content, $code_lang, $code_example, $is_preview, $release_date]);
                td_flash('ok', 'Lesson added.');
            }
            tp_sync_lesson_count($pdo, $course_id);
            td_redirect("lessons?course_id=$course_id");
        } catch (Throwable $e) {
            error_log('tutor lesson save: ' . $e->getMessage());
            $error = 'The lesson could not be saved. Please try again.';
        }
    }
}

// Delete a lesson (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (verifyCsrf() && $course_id && td_owns_course($pdo, $tid, $course_id)) {
        $pdo->prepare("DELETE FROM lessons WHERE id = ? AND course_id = ?")->execute([(int)$_POST['delete_id'], $course_id]);
        tp_sync_lesson_count($pdo, $course_id);
        td_flash('ok', 'Lesson deleted.');
    } else {
        td_flash('err', 'The lesson could not be deleted.');
    }
    td_redirect($course_id ? "lessons?course_id=$course_id" : 'lessons');
}

$course = null;
if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND tutor_id = ?");
    $stmt->execute([$course_id, $tid]);
    $course = $stmt->fetch();
    if (!$course) td_redirect('lessons');
}

$pageTitle = $course ? 'Lessons · ' . $course['title'] : 'Lessons';
$pageSub = $course ? 'Lessons are grouped by week. On a structured track one week opens at a time.' : 'Pick a course to add, edit or reorder its lessons.';
if ($course) {
    $pageCrumb = ['href' => 'lessons', 'label' => 'All courses'];
    $pageActions = '<a class="tp-btn" href="quizzes?course_id=' . (int)$course_id . '"><i class="fas fa-circle-question"></i> Quizzes</a>'
                 . '<a class="tp-btn primary" href="lessons?course_id=' . (int)$course_id . '#lesson-form"><i class="fas fa-plus"></i> Add lesson</a>';
}
require_once 'includes/header.php';

$my_all_courses = td_courses($pdo, $tid);
$lessons = $lessons_by_week = $weekly_cats = [];
$edit_data = null;
if ($course_id) {
    $stmt = $pdo->prepare("SELECT l.*, (SELECT q.title FROM quizzes q WHERE q.linked_lesson_id = l.id ORDER BY q.id LIMIT 1) AS quiz_title
                           FROM lessons l WHERE l.course_id = ? ORDER BY l.week_num ASC, l.order_num ASC, l.id ASC");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
    foreach ($lessons as $l) $lessons_by_week[(int)($l['week_num'] ?: 1)][] = $l;
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? AND type = 'cat'");
    $stmt->execute([$course_id]);
    foreach ($stmt->fetchAll() as $c) if ($c['linked_week_num']) $weekly_cats[(int)$c['linked_week_num']] = $c;
    if (isset($_GET['edit_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM lessons WHERE id = ? AND course_id = ?");
        $stmt->execute([(int)$_GET['edit_id'], $course_id]);
        $edit_data = $stmt->fetch() ?: null;
    }
    if ($error && !empty($_POST['save_lesson'])) $edit_data = array_merge($edit_data ?? [], array_intersect_key($_POST, array_flip(['id', 'title', 'lesson_type', 'duration_mins', 'week_num', 'order_num', 'video_url', 'content', 'code_lang', 'code_example'])));
}
$typeIcon = fn($t) => match ($t) { 'video' => 'fa-video', 'pdf' => 'fa-file-pdf', 'audio' => 'fa-headphones', 'image' => 'fa-image', default => 'fa-file-lines' };
$csrf = csrfToken();
?>
<?php if ($error): ?><div class="tp-alert err"><i class="fas fa-circle-exclamation"></i><div><?= e($error) ?></div></div><?php endif; ?>

<?php if (!$course_id): ?>
    <?php if ($my_all_courses): ?>
        <div class="tp-grid cards">
            <?php foreach ($my_all_courses as $c): [$sl, $sc] = td_course_status($c['status']); ?>
                <a class="tp-card" href="lessons?course_id=<?= (int)$c['id'] ?>" style="color:inherit;text-decoration:none">
                    <div class="tp-card-body" style="display:grid;gap:10px">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                            <h3 style="font-size:1rem"><?= e($c['title']) ?></h3>
                            <span class="tp-pill <?= $sc ?>"><?= $sl ?></span>
                        </div>
                        <div class="tp-course-meta">
                            <span><i class="fas fa-layer-group"></i> <?= (int)$c['lesson_count'] ?> lesson<?= (int)$c['lesson_count'] === 1 ? '' : 's' ?></span>
                            <span><i class="fas fa-circle-question"></i> <?= (int)$c['quiz_count'] ?> quiz<?= (int)$c['quiz_count'] === 1 ? '' : 'zes' ?></span>
                            <span><i class="fas fa-user-graduate"></i> <?= (int)$c['learner_count'] ?> learner<?= (int)$c['learner_count'] === 1 ? '' : 's' ?></span>
                        </div>
                        <span class="tp-btn sm" style="justify-self:start">Manage lessons <i class="fas fa-arrow-right"></i></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="tp-card"><div class="tp-empty" style="padding:56px 22px">
            <i class="fas fa-layer-group"></i><h3>Create a course first</h3>
            <p>Lessons belong to a course. Create your first course, then come back here to add lessons.</p>
            <div class="tp-actions"><a class="tp-btn primary" href="courses?action=new"><i class="fas fa-plus"></i> New course</a></div>
        </div></div>
    <?php endif; ?>
<?php else: ?>
    <div class="tp-grid main-side">
        <section class="tp-card">
            <div class="tp-card-head">
                <div><h2><i class="fas fa-list-ol"></i> Lesson order</h2><p><?= count($lessons) ?> lesson<?= count($lessons) === 1 ? '' : 's' ?> in <?= count($lessons_by_week) ?> week<?= count($lessons_by_week) === 1 ? '' : 's' ?></p></div>
            </div>
            <?php if (!$lessons): ?>
                <div class="tp-empty"><i class="fas fa-book-open"></i><h3>No lessons yet</h3><p>Use the form to add the first lesson. Start with week 1, lesson 1.</p></div>
            <?php else: ?>
                <?php foreach ($lessons_by_week as $wnum => $wlessons): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 20px;background:var(--tp-soft);border-bottom:1px solid var(--tp-border)">
                        <b style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;color:var(--tp-muted)">Week <?= (int)$wnum ?></b>
                        <?php if (isset($weekly_cats[$wnum])): ?><span class="tp-pill violet"><i class="fas fa-flag-checkered"></i> Test: <?= e($weekly_cats[$wnum]['title']) ?></span><?php endif; ?>
                    </div>
                    <ul class="tp-list" style="border-bottom:1px solid var(--tp-border)">
                    <?php foreach ($wlessons as $l): ?>
                        <li><div class="tp-row">
                            <span class="tp-row-icon"><i class="fas <?= $typeIcon($l['lesson_type']) ?>"></i></span>
                            <span class="tp-row-main">
                                <span class="tp-row-title"><?= (int)$l['order_num'] ?>. <?= e($l['title']) ?></span>
                                <span class="tp-row-meta">
                                    <?= e(ucfirst($l['lesson_type'] ?: 'text')) ?> · <?= (int)$l['duration_mins'] ?> min
                                    <?= $l['is_preview'] ? ' · free preview' : '' ?>
                                    <?= $l['release_date'] ? ' · released ' . e(date('j M Y', strtotime($l['release_date']))) : '' ?>
                                    <?= $l['quiz_title'] ? ' · quiz: ' . e($l['quiz_title']) : '' ?>
                                    <?php if ($l['lesson_type'] === 'pdf' && $l['file_url']): ?> · <a href="../uploads/lessons/<?= e(rawurlencode(basename($l['file_url']))) ?>" target="_blank" rel="noopener">Open PDF</a><?php endif; ?>
                                </span>
                            </span>
                            <span class="tp-row-side" style="display:flex;gap:6px">
                                <a class="tp-btn sm" href="lessons?course_id=<?= (int)$course_id ?>&edit_id=<?= (int)$l['id'] ?>#lesson-form" aria-label="Edit <?= e($l['title']) ?>"><i class="fas fa-pen"></i> Edit</a>
                                <form method="post" action="lessons?course_id=<?= (int)$course_id ?>" data-confirm="Delete the lesson “<?= e($l['title']) ?>”? Learner progress on it will be lost.">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="delete_id" value="<?= (int)$l['id'] ?>">
                                    <button class="tp-btn sm danger" type="submit" aria-label="Delete <?= e($l['title']) ?>"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </span>
                        </div></li>
                    <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="tp-card" id="lesson-form">
            <div class="tp-card-head"><div><h2><i class="fas <?= $edit_data && !empty($edit_data['id']) ? 'fa-pen' : 'fa-plus' ?>"></i> <?= $edit_data && !empty($edit_data['id']) ? 'Edit lesson' : 'Add a lesson' ?></h2></div></div>
            <div class="tp-card-body">
                <form method="post" action="lessons?course_id=<?= (int)$course_id ?>" enctype="multipart/form-data" class="tp-form">
                    <input type="hidden" name="id" value="<?= e((string)($edit_data['id'] ?? '')) ?>">
                    <input type="hidden" name="save_lesson" value="1">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>" id="csrfTok">
                    <div class="tp-field">
                        <label for="lessTitle">Lesson title</label>
                        <input type="text" name="title" id="lessTitle" maxlength="255" placeholder="e.g. Variables and data types" value="<?= e($edit_data['title'] ?? '') ?>" required>
                        <button type="button" class="tp-btn sm" id="aiLessonBtn" style="justify-self:start"><i class="fas fa-wand-magic-sparkles"></i> Draft with AI from the title</button>
                    </div>
                    <div class="tp-form-row">
                        <div class="tp-field">
                            <label for="lessonType">Type</label>
                            <select name="lesson_type" id="lessonType">
                                <?php foreach (['text' => 'Reading', 'video' => 'Video', 'pdf' => 'PDF document', 'audio' => 'Audio', 'image' => 'Image'] as $v => $lbl): ?>
                                    <option value="<?= $v ?>" <?= ($edit_data['lesson_type'] ?? 'text') === $v ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="tp-field">
                            <label for="l-dur">Length (minutes)</label>
                            <input id="l-dur" type="number" name="duration_mins" min="0" value="<?= (int)($edit_data['duration_mins'] ?? 15) ?>">
                        </div>
                    </div>
                    <div class="tp-form-row">
                        <div class="tp-field"><label for="l-week">Week</label><input id="l-week" type="number" name="week_num" min="1" value="<?= (int)($edit_data['week_num'] ?? 1) ?>" required></div>
                        <div class="tp-field"><label for="l-order">Order in week</label><input id="l-order" type="number" name="order_num" value="<?= (int)($edit_data['order_num'] ?? (count($lessons) + 1)) ?>"></div>
                    </div>
                    <div class="tp-field" id="videoUrlSection">
                        <label for="l-video">Video or audio link</label>
                        <input id="l-video" type="url" name="video_url" placeholder="https://youtube.com/watch?v=…" value="<?= e($edit_data['video_url'] ?? '') ?>">
                    </div>
                    <div class="tp-field" id="pdfUploadSection">
                        <label for="l-pdf">PDF file <span class="tp-hint">(up to 20 MB)</span></label>
                        <input id="l-pdf" type="file" name="pdf_file" accept=".pdf,application/pdf">
                        <?php if (!empty($edit_data['file_url']) && ($edit_data['lesson_type'] ?? '') === 'pdf'): ?>
                            <span class="tp-hint">Current file: <a href="../uploads/lessons/<?= e(rawurlencode(basename($edit_data['file_url']))) ?>" target="_blank" rel="noopener"><?= e(basename($edit_data['file_url'])) ?></a>. Upload a new one to replace it.</span>
                        <?php endif; ?>
                    </div>
                    <div class="tp-field">
                        <label for="lessContent">Lesson text</label>
                        <textarea name="content" id="lessContent" style="min-height:200px" placeholder="Write what learners will read."><?= e($edit_data['content'] ?? '') ?></textarea>
                        <span class="tp-hint">Formatting: <code># Heading</code> <code>## Sub-heading</code> <code>- bullet</code> <code>**bold**</code> <code>`code`</code>, and <code>```</code> on its own line to start or end a code block.</span>
                    </div>
                    <div class="tp-field">
                        <label for="l-lang">“Try it yourself” example <span class="tp-hint">(optional)</span></label>
                        <select id="l-lang" name="code_lang">
                            <?php foreach (['' => 'No example', 'html' => 'HTML (runs in the browser)', 'css' => 'CSS (runs in the browser)', 'javascript' => 'JavaScript (runs in the browser)', 'python' => 'Python', 'php' => 'PHP (copy and run locally)', 'sql' => 'SQL', 'text' => 'Other / plain text'] as $v => $lbl): ?>
                                <option value="<?= $v ?>" <?= ($edit_data['code_lang'] ?? '') === $v ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="code_example" class="tp-code" spellcheck="false" style="min-height:120px" placeholder="<h1>Hello world</h1>" aria-label="Example code"><?= e($edit_data['code_example'] ?? '') ?></textarea>
                    </div>
                    <div class="tp-form-row">
                        <label class="tp-check"><input type="checkbox" name="is_preview" value="1" <?= !empty($edit_data['is_preview']) ? 'checked' : '' ?>><span><b>Free preview</b><br><span class="tp-note">Anyone can read it before enrolling.</span></span></label>
                        <div class="tp-field">
                            <label for="l-rel">Release date <span class="tp-hint">(optional)</span></label>
                            <input id="l-rel" type="datetime-local" name="release_date" value="<?= !empty($edit_data['release_date']) && strtotime((string)$edit_data['release_date']) ? date('Y-m-d\TH:i', strtotime($edit_data['release_date'])) : '' ?>">
                        </div>
                    </div>
                    <div class="tp-modal-foot">
                        <?php if (!empty($edit_data['id'])): ?><a class="tp-btn" href="lessons?course_id=<?= (int)$course_id ?>">Cancel</a><?php endif; ?>
                        <button type="submit" class="tp-btn primary"><i class="fas fa-check"></i> <?= !empty($edit_data['id']) ? 'Save lesson' : 'Add lesson' ?></button>
                    </div>
                </form>
            </div>
        </section>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('lessonType');
        function toggle() {
            var t = sel.value;
            document.getElementById('videoUrlSection').style.display = (t === 'video' || t === 'audio') ? '' : 'none';
            document.getElementById('pdfUploadSection').style.display = t === 'pdf' ? '' : 'none';
        }
        sel.addEventListener('change', toggle); toggle();
        document.getElementById('aiLessonBtn').addEventListener('click', async function () {
            var title = document.getElementById('lessTitle').value.trim();
            if (!title) { SDAC.showToast('Type a lesson title first.', 'error'); return; }
            var btn = this, old = btn.innerHTML;
            btn.disabled = true; btn.textContent = 'Drafting…';
            try {
                var fd = new FormData();
                fd.append('ai_lesson_gen', '1'); fd.append('topic', title); fd.append('csrf_token', document.getElementById('csrfTok').value);
                var res = await fetch('lessons?course_id=<?= (int)$course_id ?>', { method: 'POST', body: fd });
                var data = await res.json();
                if (data && data.title) {
                    document.getElementById('lessTitle').value = data.title;
                    document.getElementById('lessContent').value = data.content || '';
                    if (data.duration) document.getElementById('l-dur').value = data.duration;
                    SDAC.showToast('Draft added. Check it before saving.');
                } else {
                    SDAC.showToast((data && data.error) || 'The AI assistant did not return a draft.', 'error');
                }
            } catch (e) {
                SDAC.showToast('The AI assistant is not available right now.', 'error');
            } finally { btn.disabled = false; btn.innerHTML = old; }
        });
    });
    </script>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
