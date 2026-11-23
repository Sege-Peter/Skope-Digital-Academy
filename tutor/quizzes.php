<?php
/**
 * Tutor: quizzes, weekly tests and final exams for a course, and their questions.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

$course_id = (int)($_GET['course_id'] ?? 0);
$course = null;
if ($course_id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND tutor_id = ?");
    $stmt->execute([$course_id, $tid]);
    $course = $stmt->fetch() ?: null;
    if (!$course) td_redirect('quizzes');
}

/** Quiz belongs to this course (and so to this tutor)? */
function tp_quiz_ok(PDO $pdo, int $quizId, int $courseId): bool {
    $st = $pdo->prepare("SELECT 1 FROM quizzes WHERE id = ? AND course_id = ?");
    $st->execute([$quizId, $courseId]);
    return (bool)$st->fetchColumn();
}

// AI: add 5 multiple-choice questions (AJAX, JSON only)
if ($course && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_bulk_questions'])) {
    header('Content-Type: application/json');
    $quiz_id = (int)($_POST['quiz_id'] ?? 0);
    if (!verifyCsrf() || !tp_quiz_ok($pdo, $quiz_id, $course_id)) { echo json_encode(['error' => 'Not allowed. Refresh the page and try again.']); exit; }
    try {
        require_once __DIR__ . '/../includes/ai-handler.php';
        $pack = SDAC_AI::generateQuiz(trim((string)($_POST['topic_title'] ?? '')), 5);
        if (!is_array($pack) || !$pack) { echo json_encode(['error' => 'The AI assistant returned no questions.']); exit; }
        $ins = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, type, options_json, correct_answer, points) VALUES (?, ?, 'mcq', ?, ?, 10)");
        $n = 0;
        foreach ($pack as $q) {
            if (empty($q['question']) || empty($q['options']) || !isset($q['correct'])) continue;
            $ins->execute([$quiz_id, $q['question'], json_encode(array_values($q['options'])), $q['correct']]);
            $n++;
        }
        td_flash('ok', "$n question" . ($n === 1 ? '' : 's') . ' added. Check them before learners take the quiz.');
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$error = '';
if ($course && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (isset($_POST['save_quiz'])) {
        $title = trim((string)($_POST['title'] ?? ''));
        $type = in_array($_POST['assessment_type'] ?? '', ['quiz', 'cat', 'final'], true) ? $_POST['assessment_type'] : 'quiz';
        $week = $type === 'cat' && !empty($_POST['linked_week_num']) ? (int)$_POST['linked_week_num'] : null;
        $lesson_link = !empty($_POST['linked_lesson_id']) ? (int)$_POST['linked_lesson_id'] : null;
        if ($lesson_link) {
            $chk = $pdo->prepare("SELECT 1 FROM lessons WHERE id = ? AND course_id = ?");
            $chk->execute([$lesson_link, $course_id]);
            if (!$chk->fetchColumn()) $lesson_link = null;
        }
        if ($title === '') $error = 'Give the quiz a title.';
        else {
            $pdo->prepare("INSERT INTO quizzes (course_id, title, time_limit_mins, pass_score, type, linked_week_num, linked_lesson_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$course_id, $title, max(1, (int)($_POST['time_limit'] ?? 30)), max(0, min(100, (int)($_POST['pass_score'] ?? 70))), $type, $week, $lesson_link]);
            $qid = (int)$pdo->lastInsertId();
            td_flash('ok', 'Quiz created. Now add its questions.');
            td_redirect("quizzes?course_id=$course_id&quiz_id=$qid");
        }
    } elseif (isset($_POST['save_question'])) {
        $qid = (int)($_POST['quiz_id'] ?? 0);
        $question = trim((string)($_POST['question'] ?? ''));
        $type = in_array($_POST['question_type'] ?? '', ['mcq', 'tf', 'text', 'code'], true) ? $_POST['question_type'] : 'mcq';
        if ($type === 'code') { require_once __DIR__ . '/../includes/exam.php'; sda_exam_ensure($pdo); }
        $points = max(1, (int)($_POST['points'] ?? 10));
        $options = [];
        $correct = '';
        if ($type === 'mcq') {
            $correct = trim((string)($_POST['correct_answer'] ?? ''));
            $wrong = array_values(array_filter(array_map(fn($k) => trim((string)($_POST[$k] ?? '')), ['opt1', 'opt2', 'opt3']), fn($v) => $v !== ''));
            $options = array_values(array_unique(array_merge([$correct], $wrong)));
            shuffle($options); // the right answer is not always first
            if ($correct === '' || count($options) < 2) $error = 'Enter the correct answer and at least one wrong answer.';
        } elseif ($type === 'tf') {
            $options = ['True', 'False'];
            $correct = ($_POST['correct_tf'] ?? 'True') === 'False' ? 'False' : 'True';
        } elseif ($type === 'code') {
            // Programming question: answered in the in-app editor; graded by the code it must contain
            $lang = in_array($_POST['code_lang'] ?? '', ['html', 'css', 'javascript', 'python', 'php', 'sql'], true) ? $_POST['code_lang'] : 'javascript';
            $options = ['lang' => $lang, 'starter' => mb_substr((string)($_POST['code_starter'] ?? ''), 0, 5000)];
            $correct = trim((string)($_POST['code_keywords'] ?? ''));
            if ($correct === '') $error = 'Enter the code a correct answer must contain (e.g. function, return, console.log).';
        } else {
            $correct = trim((string)($_POST['keywords'] ?? ''));
            if ($correct === '') $error = 'Enter the keywords a correct answer must contain.';
        }
        if (!tp_quiz_ok($pdo, $qid, $course_id)) $error = 'That quiz was not found.';
        if ($question === '') $error = 'Type the question.';
        if (!$error) {
            $ord = $pdo->prepare("SELECT COALESCE(MAX(order_num), 0) + 1 FROM quiz_questions WHERE quiz_id = ?");
            $ord->execute([$qid]);
            $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, type, options_json, correct_answer, points, order_num) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$qid, $question, $type, json_encode($options), $correct, $points, (int)$ord->fetchColumn()]);
            td_flash('ok', 'Question added.');
            td_redirect("quizzes?course_id=$course_id&quiz_id=$qid");
        }
    } elseif (isset($_POST['delete_question'])) {
        $qid = (int)($_POST['quiz_id'] ?? 0);
        if (tp_quiz_ok($pdo, $qid, $course_id)) {
            $pdo->prepare("DELETE FROM quiz_questions WHERE id = ? AND quiz_id = ?")->execute([(int)$_POST['delete_question'], $qid]);
            td_flash('ok', 'Question deleted.');
        }
        td_redirect("quizzes?course_id=$course_id&quiz_id=$qid");
    }
}

$pageTitle = $course ? 'Quizzes · ' . $course['title'] : 'Quizzes';
$pageSub = $course ? 'Quizzes check understanding; weekly tests gate a week on a structured track; the final exam closes the course.' : 'Pick a course to create quizzes and add questions.';
if ($course) {
    $pageCrumb = ['href' => 'quizzes', 'label' => 'All courses'];
    $pageActions = '<a class="tp-btn" href="lessons?course_id=' . $course_id . '"><i class="fas fa-layer-group"></i> Lessons</a>'
                 . '<button type="button" class="tp-btn primary" data-open="quizModal"><i class="fas fa-plus"></i> New quiz</button>';
}
require_once 'includes/header.php';
$csrf = csrfToken();
$typeLabel = ['quiz' => 'Quiz', 'cat' => 'Weekly test', 'final' => 'Final exam'];

if (!$course):
    $all = td_courses($pdo, $tid);
?>
    <?php if ($all): ?>
        <div class="tp-grid cards">
            <?php foreach ($all as $c): ?>
                <a class="tp-card" href="quizzes?course_id=<?= (int)$c['id'] ?>" style="color:inherit;text-decoration:none">
                    <div class="tp-card-body" style="display:grid;gap:10px">
                        <h3 style="font-size:1rem"><?= e($c['title']) ?></h3>
                        <div class="tp-course-meta"><span><?= (int)$c['quiz_count'] ?> quiz<?= (int)$c['quiz_count'] === 1 ? '' : 'zes' ?></span><span><?= (int)$c['lesson_count'] ?> lessons</span></div>
                        <span class="tp-btn sm" style="justify-self:start">Open quizzes <i class="fas fa-arrow-right"></i></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="tp-card"><div class="tp-empty" style="padding:56px 22px"><i class="fas fa-circle-question"></i><h3>Create a course first</h3><p>Quizzes belong to a course.</p><div class="tp-actions"><a class="tp-btn primary" href="courses?action=new"><i class="fas fa-plus"></i> New course</a></div></div></div>
    <?php endif; ?>
<?php
else:
    require_once __DIR__ . '/../includes/exam.php';
    sda_exam_ensure($pdo);
    $stmt = $pdo->prepare("SELECT q.*, (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.id) AS n_questions,
                                  (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.status = 'completed') AS n_attempts,
                                  (SELECT AVG(qa.score) FROM quiz_attempts qa WHERE qa.quiz_id = q.id AND qa.status = 'completed') AS avg_score
                           FROM quizzes q WHERE q.course_id = ? ORDER BY FIELD(q.type, 'quiz', 'cat', 'final'), q.title");
    $stmt->execute([$course_id]);
    $quizzes = $stmt->fetchAll();
    $active_quiz_id = (int)($_GET['quiz_id'] ?? ($quizzes[0]['id'] ?? 0));
    $active = null;
    foreach ($quizzes as $q) if ((int)$q['id'] === $active_quiz_id) $active = $q;
    $questions = [];
    if ($active) {
        $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_num ASC, id ASC");
        $stmt->execute([$active_quiz_id]);
        $questions = $stmt->fetchAll();
        // Results: every finished attempt, with how it ended and the test-rule warnings
        $stmt = $pdo->prepare("SELECT qa.*, u.name AS learner, u.email AS learner_email FROM quiz_attempts qa JOIN users u ON u.id = qa.student_id
                               WHERE qa.quiz_id = ? AND qa.status = 'completed' ORDER BY qa.completed_at DESC LIMIT 100");
        $stmt->execute([$active_quiz_id]);
        $results = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = ? AND status = 'in_progress' AND deadline_at > NOW()");
        $stmt->execute([$active_quiz_id]);
        $live_now = (int)$stmt->fetchColumn();
    }
    $stmt = $pdo->prepare("SELECT id, title, week_num FROM lessons WHERE course_id = ? ORDER BY week_num, order_num");
    $stmt->execute([$course_id]);
    $course_lessons = $stmt->fetchAll();
?>
    <?php if ($error): ?><div class="tp-alert err"><i class="fas fa-circle-exclamation"></i><div><?= e($error) ?></div></div><?php endif; ?>
    <div class="tp-grid side-main">
        <section class="tp-card">
            <div class="tp-card-head"><div><h2><i class="fas fa-list"></i> Quizzes</h2><p><?= count($quizzes) ?> in this course</p></div></div>
            <?php if ($quizzes): ?>
                <ul class="tp-list">
                    <?php foreach ($quizzes as $q): $on = (int)$q['id'] === $active_quiz_id; ?>
                        <li><a class="tp-row no-indent" href="quizzes?course_id=<?= $course_id ?>&quiz_id=<?= (int)$q['id'] ?>" style="<?= $on ? 'background:var(--tp-primary-soft)' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>>
                            <span class="tp-row-icon <?= $q['type'] === 'final' ? 'violet' : ($q['type'] === 'cat' ? 'accent' : '') ?>"><i class="fas <?= $q['type'] === 'cat' ? 'fa-flag-checkered' : ($q['type'] === 'final' ? 'fa-medal' : 'fa-circle-question') ?>"></i></span>
                            <span class="tp-row-main">
                                <span class="tp-row-title"><?= e($q['title']) ?></span>
                                <span class="tp-row-meta"><?= $typeLabel[$q['type']] ?? 'Quiz' ?><?= $q['type'] === 'cat' && $q['linked_week_num'] ? ' (week ' . (int)$q['linked_week_num'] . ')' : '' ?> · <?= (int)$q['n_questions'] ?> question<?= (int)$q['n_questions'] === 1 ? '' : 's' ?> · pass <?= (int)$q['pass_score'] ?>%</span>
                            </span>
                        </a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="tp-empty"><i class="fas fa-circle-question"></i><h3>No quizzes yet</h3><p>Create a quiz, then add multiple-choice, true/false or short-answer questions.</p><div class="tp-actions"><button type="button" class="tp-btn sm primary" data-open="quizModal"><i class="fas fa-plus"></i> New quiz</button></div></div>
            <?php endif; ?>
        </section>

        <?php if ($active): ?>
        <section class="tp-card">
            <div class="tp-card-head">
                <div>
                    <h2><i class="fas fa-clipboard-question"></i> <?= e($active['title']) ?></h2>
                    <p><?= count($questions) ?> question<?= count($questions) === 1 ? '' : 's' ?> · <?= (int)$active['time_limit_mins'] ?> min · pass mark <?= (int)$active['pass_score'] ?>%
                        · <?= (int)$active['n_attempts'] ? (int)$active['n_attempts'] . ' attempt' . ((int)$active['n_attempts'] === 1 ? '' : 's') . ', average ' . round((float)$active['avg_score']) . '%' : 'no attempts yet' ?></p>
                </div>
                <div class="tp-actions">
                    <button type="button" class="tp-btn sm" id="aiGenerateBtn"><i class="fas fa-wand-magic-sparkles"></i> Add 5 with AI</button>
                    <button type="button" class="tp-btn sm primary" data-open="qModal"><i class="fas fa-plus"></i> Add question</button>
                </div>
            </div>
            <?php if (!$questions): ?>
                <div class="tp-empty"><i class="fas fa-list-check"></i><h3>No questions yet</h3><p>Add multiple-choice, true/false or short-answer questions. Learners cannot take an empty quiz.</p></div>
            <?php else: foreach ($questions as $i => $q): $opts = json_decode((string)$q['options_json'], true) ?: []; ?>
                <div class="tp-q">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap">
                        <span class="tp-pill info">Q<?= $i + 1 ?> · <?= ['mcq' => 'Multiple choice', 'tf' => 'True / false', 'text' => 'Short answer', 'code' => 'Programming · ' . strtoupper((string)($opts['lang'] ?? 'code'))][$q['type']] ?? 'Question' ?> · <?= (int)$q['points'] ?> pts</span>
                        <form method="post" action="quizzes?course_id=<?= $course_id ?>&quiz_id=<?= $active_quiz_id ?>" data-confirm="Delete this question?">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="quiz_id" value="<?= $active_quiz_id ?>"><input type="hidden" name="delete_question" value="<?= (int)$q['id'] ?>">
                            <button type="submit" class="tp-btn sm danger" aria-label="Delete question <?= $i + 1 ?>"><i class="fas fa-trash"></i> Delete</button>
                        </form>
                    </div>
                    <h3><?= e($q['question']) ?></h3>
                    <?php if ($q['type'] === 'text'): ?>
                        <div class="tp-opt correct"><i class="fas fa-key"></i> Keywords: <?= e($q['correct_answer']) ?></div>
                    <?php elseif ($q['type'] === 'code'): ?>
                        <?php if (!empty($opts['starter'])): ?><pre style="margin:0 0 8px;padding:10px 12px;border-radius:10px;background:#0b1220;color:#e2e8f0;font:.82rem/1.5 ui-monospace,Consolas,monospace;white-space:pre-wrap"><?= e($opts['starter']) ?></pre><?php endif; ?>
                        <div class="tp-opt correct"><i class="fas fa-code"></i> Must contain: <?= e($q['correct_answer']) ?></div>
                    <?php else: ?>
                        <div class="tp-opts">
                            <?php foreach ($opts as $o): $ok = (string)$o === (string)$q['correct_answer']; ?>
                                <div class="tp-opt <?= $ok ? 'correct' : '' ?>"><i class="fas <?= $ok ? 'fa-circle-check' : 'fa-circle' ?>" style="<?= $ok ? '' : 'color:#cbd5e1' ?>"></i> <?= e((string)$o) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!in_array((string)$q['correct_answer'], array_map('strval', $opts), true)): ?>
                            <p class="tp-note" style="color:var(--tp-bad);margin-top:8px"><i class="fas fa-triangle-exclamation"></i> The correct answer (“<?= e($q['correct_answer']) ?>”) is not one of the options, so nobody can get this right. Delete and re-add it.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>
        </section>
        <?php if ($active): $endLabel = ['submitted' => ['Submitted', ''], 'time_up' => ['Time ran out', 'warn'], 'closed_early' => ['Closed early', 'bad'], 'violations' => ['Auto-submitted: left the test', 'bad']]; ?>
        <section class="tp-card" style="grid-column:1/-1">
            <div class="tp-card-head"><div><h2><i class="fas fa-square-poll-vertical"></i> Results</h2>
                <p><?= count($results) ?> finished attempt<?= count($results) === 1 ? '' : 's' ?><?= $live_now ? ' · <b style="color:var(--tp-ok,#059669)">' . $live_now . ' learner' . ($live_now === 1 ? '' : 's') . ' taking it now</b>' : '' ?>. Warnings count the times a learner left the test tab or window.</p></div></div>
            <?php if (!$results): ?>
                <div class="tp-empty"><i class="fas fa-hourglass-half"></i><h3>No results yet</h3><p>Results appear here as learners submit.</p></div>
            <?php else: ?>
            <div class="tp-table-wrap" style="overflow-x:auto">
                <table class="tp-table" style="width:100%;border-collapse:collapse;font-size:.88rem">
                    <thead><tr style="text-align:left"><th style="padding:10px">Learner</th><th style="padding:10px">Finished</th><th style="padding:10px">Score</th><th style="padding:10px">How it ended</th><th style="padding:10px">Warnings</th><th style="padding:10px">Log</th></tr></thead>
                    <tbody>
                    <?php foreach ($results as $r): [$el, $ec] = $endLabel[$r['end_reason'] ?? ''] ?? ['Submitted', '']; $wv = (int)$r['violations']; ?>
                        <tr style="border-top:1px solid var(--tp-line,#e2e8f0);vertical-align:top">
                            <td style="padding:10px"><b><?= e($r['learner']) ?></b><div class="tp-note" style="margin:0"><?= e($r['learner_email']) ?></div></td>
                            <td style="padding:10px;white-space:nowrap"><?= $r['completed_at'] ? date('j M Y, H:i', strtotime($r['completed_at'])) : '—' ?><?php if ($r['started_at'] && $r['completed_at']): ?><div class="tp-note" style="margin:0"><?= max(1, (int)round((strtotime($r['completed_at']) - strtotime($r['started_at'])) / 60)) ?> min</div><?php endif; ?></td>
                            <td style="padding:10px"><b><?= round((float)$r['score']) ?>%</b> <span class="tp-pill <?= $r['passed'] ? 'ok' : 'bad' ?>"><?= $r['passed'] ? 'Passed' : 'Not passed' ?></span></td>
                            <td style="padding:10px"><span class="tp-pill <?= $ec ?>"><?= e($el) ?></span></td>
                            <td style="padding:10px"><?= $wv ? '<span class="tp-pill ' . ($wv >= SDA_EXAM_MAX_VIOLATIONS ? 'bad' : 'warn') . '"><i class="fas fa-triangle-exclamation"></i> ' . $wv . '</span>' : '<span class="tp-note" style="margin:0">None</span>' ?></td>
                            <td style="padding:10px"><?php if (trim((string)$r['proctor_log']) !== ''): ?><details><summary style="cursor:pointer;font-weight:600">View</summary><pre style="margin:6px 0 0;font:.78rem/1.5 ui-monospace,Consolas,monospace;white-space:pre-wrap"><?= e(str_replace(['warning: left_tab', 'warning: left_window', 'started'], ['left the test tab', 'switched to another window/app', 'started the test'], trim((string)$r['proctor_log']))) ?></pre></details><?php else: ?><span class="tp-note" style="margin:0">—</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="tp-modal" id="quizModal" role="dialog" aria-modal="true" aria-labelledby="quizModalT"<?= isset($_GET['new']) || ($error && isset($_POST['save_quiz'])) ? ' data-autoopen' : '' ?>>
        <div class="tp-modal-box">
            <div class="tp-modal-head"><div><h2 id="quizModalT">New quiz</h2><p>Set the basics. You add the questions next.</p></div><button type="button" class="tp-iconbtn" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
            <div class="tp-modal-body">
                <form method="post" action="quizzes?course_id=<?= $course_id ?>" class="tp-form">
                    <input type="hidden" name="save_quiz" value="1"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="tp-field"><label for="qz-t">Title</label><input id="qz-t" type="text" name="title" placeholder="e.g. Week 1 check-in" required></div>
                    <div class="tp-form-row">
                        <div class="tp-field"><label for="qz-type">Kind</label>
                            <select id="qz-type" name="assessment_type" onchange="document.getElementById('catWeekRow').style.display = this.value === 'cat' ? '' : 'none'">
                                <option value="quiz">Quiz</option><option value="cat">Weekly test</option><option value="final">Final exam</option>
                            </select></div>
                        <div class="tp-field"><label for="qz-pass">Pass mark (%)</label><input id="qz-pass" type="number" name="pass_score" min="0" max="100" value="70" required></div>
                    </div>
                    <div class="tp-field" id="catWeekRow" style="display:none"><label for="qz-week">Week it tests</label><input id="qz-week" type="number" name="linked_week_num" min="1" placeholder="e.g. 2"></div>
                    <div class="tp-form-row">
                        <div class="tp-field"><label for="qz-lesson">Attach to lesson <span class="tp-hint">(optional)</span></label>
                            <select id="qz-lesson" name="linked_lesson_id"><option value="">Not attached</option>
                                <?php foreach ($course_lessons as $cl): ?><option value="<?= (int)$cl['id'] ?>">Week <?= (int)$cl['week_num'] ?>: <?= e($cl['title']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="tp-field"><label for="qz-time">Time limit (minutes)</label><input id="qz-time" type="number" name="time_limit" min="1" value="30" required></div>
                    </div>
                    <div class="tp-modal-foot"><button type="button" class="tp-btn" data-close>Cancel</button><button type="submit" class="tp-btn primary"><i class="fas fa-check"></i> Create quiz</button></div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($active): ?>
    <div class="tp-modal" id="qModal" role="dialog" aria-modal="true" aria-labelledby="qModalT"<?= $error && isset($_POST['save_question']) ? ' data-autoopen' : '' ?>>
        <div class="tp-modal-box">
            <div class="tp-modal-head"><div><h2 id="qModalT">Add a question</h2><p>To <?= e($active['title']) ?></p></div><button type="button" class="tp-iconbtn" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
            <div class="tp-modal-body">
                <form method="post" action="quizzes?course_id=<?= $course_id ?>&quiz_id=<?= $active_quiz_id ?>" class="tp-form">
                    <input type="hidden" name="save_question" value="1"><input type="hidden" name="quiz_id" value="<?= $active_quiz_id ?>"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <div class="tp-field"><label for="qq-text">Question</label><textarea id="qq-text" name="question" style="min-height:90px" required></textarea></div>
                    <div class="tp-form-row">
                        <div class="tp-field"><label for="qTypeSelect">Answer type</label>
                            <select name="question_type" id="qTypeSelect"><option value="mcq">Multiple choice</option><option value="tf">True / false</option><option value="text">Short answer (keywords)</option><option value="code">Programming (code editor)</option></select></div>
                        <div class="tp-field"><label for="qq-pts">Points</label><input id="qq-pts" type="number" name="points" min="1" value="10"></div>
                    </div>
                    <div id="mcqFields" class="tp-form">
                        <div class="tp-field"><label for="qq-c">Correct answer</label><input id="qq-c" type="text" name="correct_answer"></div>
                        <div class="tp-form-row three">
                            <div class="tp-field"><label for="qq-1">Wrong answer 1</label><input id="qq-1" type="text" name="opt1"></div>
                            <div class="tp-field"><label for="qq-2">Wrong answer 2</label><input id="qq-2" type="text" name="opt2"></div>
                            <div class="tp-field"><label for="qq-3">Wrong answer 3</label><input id="qq-3" type="text" name="opt3"></div>
                        </div>
                        <span class="tp-note">The answers are shuffled for learners.</span>
                    </div>
                    <div id="tfFields" class="tp-field" style="display:none"><label for="qq-tf">Correct answer</label><select id="qq-tf" name="correct_tf"><option>True</option><option>False</option></select></div>
                    <div id="codeFields" class="tp-form" style="display:none">
                        <div class="tp-form-row">
                            <div class="tp-field"><label for="qq-lang">Language</label><select id="qq-lang" name="code_lang"><option value="javascript">JavaScript</option><option value="html">HTML</option><option value="css">CSS</option><option value="python">Python</option><option value="php">PHP</option><option value="sql">SQL</option></select></div>
                        </div>
                        <div class="tp-field"><label for="qq-starter">Starter code <span class="tp-hint">(optional, shown in the learner's editor)</span></label><textarea id="qq-starter" name="code_starter" style="min-height:90px;font-family:ui-monospace,Consolas,monospace" placeholder="function add(a, b) {&#10;  // your code&#10;}"></textarea></div>
                        <div class="tp-field"><label for="qq-ck">Code a correct answer must contain</label><input id="qq-ck" type="text" name="code_keywords" placeholder="e.g. function, return, a + b"><span class="tp-hint">Comma-separated, matched exactly (case-sensitive). Learners answer in the built-in editor and can run HTML, CSS and JavaScript there.</span></div>
                    </div>
                    <div id="textFields" class="tp-field" style="display:none"><label for="qq-kw">Keywords a correct answer must contain</label><input id="qq-kw" type="text" name="keywords" placeholder="e.g. variable, value, name"><span class="tp-hint">Separate keywords with commas.</span></div>
                    <div class="tp-modal-foot"><button type="button" class="tp-btn" data-close>Cancel</button><button type="submit" class="tp-btn primary"><i class="fas fa-check"></i> Add question</button></div>
                </form>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('qTypeSelect');
        sel.addEventListener('change', function () {
            document.getElementById('mcqFields').style.display = sel.value === 'mcq' ? '' : 'none';
            document.getElementById('tfFields').style.display = sel.value === 'tf' ? '' : 'none';
            document.getElementById('textFields').style.display = sel.value === 'text' ? '' : 'none';
            document.getElementById('codeFields').style.display = sel.value === 'code' ? '' : 'none';
        });
        document.getElementById('aiGenerateBtn').addEventListener('click', async function () {
            if (!confirm('Add 5 multiple-choice questions written by the AI assistant to this quiz? Check them afterwards.')) return;
            var btn = this, old = btn.innerHTML; btn.disabled = true; btn.textContent = 'Generating…';
            try {
                var fd = new FormData();
                fd.append('ai_bulk_questions', '1'); fd.append('quiz_id', '<?= $active_quiz_id ?>');
                fd.append('topic_title', <?= json_encode($active['title'] . ' – ' . $course['title']) ?>); fd.append('csrf_token', <?= json_encode($csrf) ?>);
                var res = await fetch('quizzes?course_id=<?= $course_id ?>&quiz_id=<?= $active_quiz_id ?>', { method: 'POST', body: fd });
                var data = await res.json();
                if (data.success) { location.reload(); return; }
                SDAC.showToast(data.error || 'No questions were generated.', 'error');
            } catch (e) { SDAC.showToast('The AI assistant is not available right now.', 'error'); }
            btn.disabled = false; btn.innerHTML = old;
        });
    });
    </script>
    <?php endif; ?>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
