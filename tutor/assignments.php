<?php
/**
 * Tutor: assignments (post, see deadlines) and grading of learner submissions.
 * DB notes: assignments use due_date; grading writes `feedback` + graded_at (`notes` is the learner's own note).
 * Learners are emailed through sda_notify() when an assignment is posted and when their work is graded.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];
$error = '';

// 1. Grade a submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'grade') {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $score = (float)($_POST['score'] ?? 0);
    $notes = trim((string)($_POST['feedback'] ?? ''));
    try {
        // The submission must belong to one of this tutor's courses
        $stmt = $pdo->prepare("SELECT s.student_id, s.status, a.title, a.max_score
                               FROM assignment_submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id
                               WHERE s.id = ? AND c.tutor_id = ?");
        $stmt->execute([$submission_id, $tid]);
        $sub = $stmt->fetch();
        if (!verifyCsrf()) {
            $error = 'Your session expired. Please refresh the page and try again.';
        } elseif (!$sub) {
            $error = 'That submission was not found in your courses.';
        } elseif ($score < 0 || $score > (float)$sub['max_score']) {
            $error = 'The score must be between 0 and ' . (int)$sub['max_score'] . '.';
        } else {
            $student_id = (int)$sub['student_id'];
            $pdo->prepare("UPDATE assignment_submissions SET score=?, feedback=?, status='graded', graded_at=NOW() WHERE id=?")
                ->execute([$score, $notes, $submission_id]);

            // Points only the first time a submission is graded (a re-grade must not pay out twice)
            if ($sub['status'] !== 'graded') {
                require_once __DIR__ . '/../includes/gamified_logic.php';
                rewardStudentPoints($student_id, $score, $pdo);
            }

            require_once __DIR__ . '/../includes/notify.php';
            sda_notify('assignment_graded', $student_id, ['assignment' => $sub['title'], 'score' => rtrim(rtrim(number_format($score, 1), '0'), '.') . ' / ' . (int)$sub['max_score'],
                'feedback' => $notes !== '' ? 'Feedback: ' . $notes : ''], ['title' => 'Assignment graded', 'message' => "Your submission for \"{$sub['title']}\" was graded. Score: {$score}"]);

            td_flash('ok', 'Assignment graded. The learner has been notified.');
            td_redirect('assignments' . (!empty($_POST['return']) && preg_match('/^[a-z0-9=&_]*$/i', $_POST['return']) ? '?' . $_POST['return'] : ''));
        }
    } catch (Throwable $e) {
        error_log('tutor grade: ' . $e->getMessage());
        $error = 'The grade could not be saved. Please try again.';
    }
}

// 2. Post a new assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignment'])) {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $max_score = max(1, (int)($_POST['max_score'] ?? 100));
    $deadline = trim((string)($_POST['due_date'] ?? ''));
    $deadline = $deadline !== '' && strtotime($deadline) ? date('Y-m-d H:i:s', strtotime($deadline)) : '';
    try {
        if (!verifyCsrf()) $error = 'Your session expired. Please refresh the page and try again.';
        elseif (!td_owns_course($pdo, $tid, $course_id)) $error = 'You can only post assignments in your own courses.';
        elseif ($title === '') $error = 'Give the assignment a title.';
        else {
            $pdo->prepare("INSERT INTO assignments (course_id, title, description, max_score, due_date) VALUES (?, ?, ?, ?, ?)")
                ->execute([$course_id, $title, $desc, $max_score, $deadline !== '' ? $deadline : null]);
            // Tell every learner enrolled in the course
            require_once __DIR__ . '/../includes/notify.php';
            $aid = (int)$pdo->lastInsertId();
            $ct = $pdo->prepare("SELECT title FROM courses WHERE id = ?"); $ct->execute([$course_id]); $ctitle = (string)$ct->fetchColumn();
            $en = $pdo->prepare("SELECT student_id FROM enrollments WHERE course_id = ? AND status = 'active'"); $en->execute([$course_id]);
            $told = 0;
            foreach ($en->fetchAll(PDO::FETCH_COLUMN) as $sid) {
                sda_notify('new_assignment', (int)$sid, ['assignment' => $title, 'course' => $ctitle, 'due' => $deadline !== '' ? date('D j M Y, H:i', strtotime($deadline)) : 'no deadline'], ['dedupe' => "newasg:$aid:$sid"]);
                $told++;
            }
            td_flash('ok', 'Assignment posted. ' . ($told === 1 ? '1 learner was' : $told . ' learners were') . ' notified.');
            td_redirect('assignments?view=list');
        }
    } catch (Throwable $e) {
        error_log('tutor assignment save: ' . $e->getMessage());
        $error = 'The assignment could not be saved. Please try again.';
    }
}

$pendingCount = td_pending_submission_count($pdo, $tid);
$view = in_array($_GET['view'] ?? '', ['pending', 'all', 'list'], true) ? $_GET['view'] : ($pendingCount ? 'pending' : 'list');
$courseFilter = (int)($_GET['course_id'] ?? 0);
if ($courseFilter && !isset($_GET['view'])) $view = 'list';

$pageTitle = 'Assignments & grading';
$pageSub = 'Post assignments with a deadline, then grade what learners hand in. Learners get an email when you post and when you grade.';
$pageActions = '<button type="button" class="tp-btn primary" data-open="newAssModal"><i class="fas fa-plus"></i> Post assignment</button>';
require_once 'includes/header.php';

$my_courses = td_course_options($pdo, $tid);
$cw = $courseFilter ? ' AND c.id = ' . $courseFilter : '';
$st = $pdo->prepare("SELECT s.*, u.name AS student_name, u.avatar, a.title AS assignment_title, a.max_score, a.due_date, c.title AS course_title
                     FROM assignment_submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id
                     JOIN users u ON s.student_id = u.id
                     WHERE c.tutor_id = ?$cw" . ($view === 'pending' ? " AND s.status = 'pending'" : '') . "
                     ORDER BY " . ($view === 'pending' ? 's.submitted_at ASC' : 's.submitted_at DESC'));
$st->execute([$tid]);
$submissions = $st->fetchAll();

$st = $pdo->prepare("SELECT a.*, c.title AS course_title,
                            (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) AS n_sub,
                            (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'pending') AS n_pending,
                            (SELECT AVG(s.score) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'graded') AS avg_score,
                            (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN users u ON u.id = e.student_id AND u.role = 'student'
                               WHERE e.course_id = a.course_id AND e.status IN ('active','completed')) AS n_learners
                     FROM assignments a JOIN courses c ON c.id = a.course_id
                     WHERE c.tutor_id = ?$cw ORDER BY (a.due_date IS NULL), a.due_date DESC, a.id DESC");
$st->execute([$tid]);
$assignments = $st->fetchAll();

// Submission to open straight away (?grade=ID from the dashboard)
$gradeId = (int)($_GET['grade'] ?? 0);
$gradeRow = null;
if ($gradeId) {
    $g = $pdo->prepare("SELECT s.*, u.name AS student_name, a.title AS assignment_title, a.max_score, c.title AS course_title
                        FROM assignment_submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id JOIN users u ON u.id = s.student_id
                        WHERE s.id = ? AND c.tutor_id = ?");
    $g->execute([$gradeId, $tid]);
    $gradeRow = $g->fetch() ?: null;
}
$csrf = csrfToken();
$qs = fn(array $over) => http_build_query(array_filter(array_merge(['view' => $view, 'course_id' => $courseFilter ?: null], $over)));
$fileUrl = fn($f) => $f ? '../uploads/assignments/' . rawurlencode(basename((string)$f)) : null;
?>
<?php if ($error): ?><div class="tp-alert err"><i class="fas fa-circle-exclamation"></i><div><?= e($error) ?></div></div><?php endif; ?>

<div class="tp-filter" style="margin-bottom:16px;justify-content:space-between">
    <nav class="tp-tabs" aria-label="Show">
        <a href="assignments?<?= $qs(['view' => 'pending']) ?>" class="<?= $view === 'pending' ? 'active' : '' ?>">To grade <?php if ($pendingCount): ?><span class="tp-count"><?= $pendingCount ?></span><?php endif; ?></a>
        <a href="assignments?<?= $qs(['view' => 'all']) ?>" class="<?= $view === 'all' ? 'active' : '' ?>">All submissions</a>
        <a href="assignments?<?= $qs(['view' => 'list']) ?>" class="<?= $view === 'list' ? 'active' : '' ?>">Assignments</a>
    </nav>
    <?php if (count($my_courses) > 1): ?>
    <form method="get" action="assignments" class="tp-field" style="flex:0 1 280px">
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <label for="as-course" class="tp-sr">Course</label>
        <select id="as-course" name="course_id" onchange="this.form.submit()">
            <option value="">All courses</option>
            <?php foreach ($my_courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $courseFilter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if ($view === 'list'): ?>
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-clipboard-list"></i> Your assignments</h2><p><?= count($assignments) ?> posted<?= $courseFilter ? ' in this course' : '' ?></p></div></div>
        <?php if ($assignments): ?>
            <div class="tp-table-wrap">
                <table class="tp-table stack">
                    <thead><tr><th>Assignment</th><th>Due</th><th class="num">Handed in</th><th class="num">To grade</th><th class="num">Average score</th></tr></thead>
                    <tbody>
                    <?php foreach ($assignments as $a): $past = $a['due_date'] && strtotime($a['due_date']) < time(); ?>
                        <tr>
                            <td data-label=""><b style="color:var(--tp-text)"><?= e($a['title']) ?></b><div class="tp-note"><?= e($a['course_title']) ?> · out of <?= (int)$a['max_score'] ?></div></td>
                            <td data-label="Due"><?php if ($a['due_date']): ?><?= e(date('D j M Y, g:i a', strtotime($a['due_date']))) ?> <span class="tp-pill <?= $past ? 'muted' : 'info' ?>"><?= $past ? 'closed' : e(td_ago($a['due_date'])) ?></span><?php else: ?><span class="tp-note">No deadline</span><?php endif; ?></td>
                            <td data-label="Handed in" class="num"><?= (int)$a['n_sub'] ?> of <?= (int)$a['n_learners'] ?></td>
                            <td data-label="To grade" class="num"><?= (int)$a['n_pending'] ? '<a href="assignments?' . $qs(['view' => 'pending', 'course_id' => (int)$a['course_id']]) . '">' . (int)$a['n_pending'] . '</a>' : '0' ?></td>
                            <td data-label="Average score" class="num"><?= $a['avg_score'] !== null ? round((float)$a['avg_score'], 1) . ' / ' . (int)$a['max_score'] : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="tp-empty"><i class="fas fa-clipboard-list"></i><h3>No assignments yet</h3>
                <p><?= $my_courses ? 'Post a task with a deadline. Learners in the course are emailed straight away and can upload their work from their portal.' : 'Create a course first, then post assignments in it.' ?></p>
                <div class="tp-actions"><?php if ($my_courses): ?><button type="button" class="tp-btn primary sm" data-open="newAssModal"><i class="fas fa-plus"></i> Post assignment</button><?php else: ?><a class="tp-btn sm" href="courses?action=new">New course</a><?php endif; ?></div>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-inbox"></i> <?= $view === 'pending' ? 'Waiting for a grade' : 'All submissions' ?></h2><p><?= count($submissions) ?> submission<?= count($submissions) === 1 ? '' : 's' ?><?= $view === 'pending' ? ', oldest first' : '' ?></p></div></div>
        <?php if ($submissions): ?>
            <div class="tp-table-wrap">
                <table class="tp-table stack">
                    <thead><tr><th>Learner</th><th>Assignment</th><th>Handed in</th><th>Status</th><th class="actions"><span class="tp-sr">Actions</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($submissions as $s): $late = $s['due_date'] && strtotime($s['submitted_at']) > strtotime($s['due_date']); $url = $fileUrl($s['file_url']); ?>
                        <tr>
                            <td data-label=""><span class="tp-who"><?= td_avatar($s['avatar'], $s['student_name']) ?><span><b><?= e($s['student_name']) ?></b><small><?= e($s['course_title']) ?></small></span></span></td>
                            <td data-label="Assignment"><?= e($s['assignment_title']) ?></td>
                            <td data-label="Handed in"><?= e(date('j M Y, g:i a', strtotime($s['submitted_at']))) ?><?= $late ? ' <span class="tp-pill warn">late</span>' : '' ?></td>
                            <td data-label="Status"><?php if ($s['status'] === 'graded'): ?><span class="tp-pill ok">Graded · <?= e(rtrim(rtrim(number_format((float)$s['score'], 1), '0'), '.')) ?>/<?= (int)$s['max_score'] ?></span><?php else: ?><span class="tp-pill warn">Waiting</span><?php endif; ?></td>
                            <td class="actions">
                                <?php if ($url): ?><a class="tp-btn sm" href="<?= e($url) ?>" target="_blank" rel="noopener"><i class="fas fa-download"></i> Open work</a><?php endif; ?>
                                <button type="button" class="tp-btn sm <?= $s['status'] === 'graded' ? '' : 'primary' ?>" data-grade
                                    data-id="<?= (int)$s['id'] ?>" data-name="<?= e($s['student_name']) ?>" data-title="<?= e($s['assignment_title']) ?>"
                                    data-max="<?= (int)$s['max_score'] ?>" data-notes="<?= e((string)$s['notes']) ?>" data-file="<?= e((string)$url) ?>"
                                    data-score="<?= $s['score'] !== null ? e((string)(float)$s['score']) : '' ?>" data-feedback="<?= e((string)$s['feedback']) ?>">
                                    <i class="fas fa-pen-to-square"></i> <?= $s['status'] === 'graded' ? 'Change grade' : 'Grade' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="tp-empty"><i class="fas fa-check-double"></i>
                <h3><?= $view === 'pending' ? 'Nothing to grade' : 'No submissions yet' ?></h3>
                <p><?= $view === 'pending' ? 'Every submission has a grade. New work from learners will show up here.' : 'When learners upload work for your assignments it will be listed here.' ?></p>
                <div class="tp-actions"><a class="tp-btn sm" href="assignments?view=list">See your assignments</a></div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Post assignment -->
<div class="tp-modal" id="newAssModal" role="dialog" aria-modal="true" aria-labelledby="newAssT"<?= isset($_GET['new']) || ($error && isset($_POST['save_assignment'])) ? ' data-autoopen' : '' ?>>
    <div class="tp-modal-box">
        <div class="tp-modal-head"><div><h2 id="newAssT">Post an assignment</h2><p>Active learners in the course are emailed when you post it.</p></div><button type="button" class="tp-iconbtn" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="tp-modal-body">
            <?php if ($my_courses): ?>
            <form method="post" action="assignments" class="tp-form">
                <input type="hidden" name="save_assignment" value="1"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="tp-field"><label for="na-c">Course</label>
                    <select id="na-c" name="course_id" required><?php foreach ($my_courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($courseFilter === (int)$c['id'] || (int)($_POST['course_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
                <div class="tp-field"><label for="na-t">Title</label><input id="na-t" type="text" name="title" maxlength="255" placeholder="e.g. Build a to-do list page" value="<?= e($_POST['title'] ?? '') ?>" required></div>
                <div class="tp-field"><label for="na-d">Instructions</label><textarea id="na-d" name="description" placeholder="What should learners hand in, and how will you grade it?"><?= e($_POST['description'] ?? '') ?></textarea></div>
                <div class="tp-form-row">
                    <div class="tp-field"><label for="na-due">Deadline</label><input id="na-due" type="datetime-local" name="due_date" required></div>
                    <div class="tp-field"><label for="na-max">Marked out of</label><input id="na-max" type="number" name="max_score" min="1" value="100"></div>
                </div>
                <div class="tp-modal-foot"><button type="button" class="tp-btn" data-close>Cancel</button><button type="submit" class="tp-btn primary"><i class="fas fa-paper-plane"></i> Post assignment</button></div>
            </form>
            <?php else: ?>
                <div class="tp-empty"><i class="fas fa-book"></i><h3>Create a course first</h3><p>Assignments belong to a course.</p><div class="tp-actions"><a class="tp-btn primary sm" href="courses?action=new">New course</a></div></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Grade -->
<div class="tp-modal" id="gradeModal" role="dialog" aria-modal="true" aria-labelledby="gradeT">
    <div class="tp-modal-box">
        <div class="tp-modal-head"><div><h2 id="gradeT">Grade submission</h2><p id="gMeta"></p></div><button type="button" class="tp-iconbtn" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="tp-modal-body">
            <form method="post" action="assignments" class="tp-form">
                <input type="hidden" name="action" value="grade"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="submission_id" id="gId"><input type="hidden" name="return" value="<?= e($qs([])) ?>">
                <div class="tp-alert info" style="margin:0"><i class="fas fa-comment"></i><div><b>Learner's note</b><br><span id="gNotes"></span><div id="gFileWrap" style="margin-top:6px"><a id="gFile" href="#" target="_blank" rel="noopener"><i class="fas fa-download"></i> Open the handed-in file</a></div></div></div>
                <div class="tp-field"><label for="gScore">Score (out of <span id="gMax">100</span>)</label><input id="gScore" type="number" name="score" step="0.5" min="0" required></div>
                <div class="tp-field"><label for="gFb">Feedback for the learner</label><textarea id="gFb" name="feedback" placeholder="What went well, and what to improve."></textarea></div>
                <div class="tp-modal-foot"><button type="button" class="tp-btn" data-close>Cancel</button><button type="submit" class="tp-btn primary"><i class="fas fa-check"></i> Save grade and notify</button></div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function openGrade(d) {
        document.getElementById('gId').value = d.id;
        document.getElementById('gMeta').textContent = d.name + ' · ' + d.title;
        document.getElementById('gMax').textContent = d.max;
        var sc = document.getElementById('gScore'); sc.max = d.max; sc.value = d.score || '';
        document.getElementById('gFb').value = d.feedback || '';
        document.getElementById('gNotes').textContent = d.notes || 'No note was added.';
        var fw = document.getElementById('gFileWrap');
        if (d.file) { fw.style.display = ''; document.getElementById('gFile').href = d.file; } else { fw.style.display = 'none'; }
        tpOpenModal('gradeModal');
    }
    document.querySelectorAll('[data-grade]').forEach(function (b) { b.addEventListener('click', function () { openGrade(b.dataset); }); });
    <?php if ($gradeRow): ?>
    openGrade(<?= json_encode(['id' => (int)$gradeRow['id'], 'name' => $gradeRow['student_name'], 'title' => $gradeRow['assignment_title'], 'max' => (int)$gradeRow['max_score'],
        'notes' => (string)$gradeRow['notes'], 'file' => (string)$fileUrl($gradeRow['file_url']), 'score' => $gradeRow['score'] !== null ? (string)(float)$gradeRow['score'] : '', 'feedback' => (string)$gradeRow['feedback']], JSON_HEX_TAG | JSON_HEX_AMP) ?>);
    <?php endif; ?>
});
</script>
<?php require 'includes/footer.php'; ?>
