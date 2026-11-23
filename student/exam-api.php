<?php
/**
 * Test session API (JSON, POST + CSRF) used by take-quiz.
 *   action=start      {quiz_id}                -> {attempt, seconds_left}
 *   action=save       {attempt, answers}       -> {ok}
 *   action=violation  {attempt, kind}          -> {violations, max, submit: bool}
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
$out = function (array $d, int $code = 200): never { http_response_code($code); echo json_encode($d); exit; };

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') $out(['ok' => false, 'error' => 'Please sign in again.'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verifyCsrf()) $out(['ok' => false, 'error' => 'Your session expired. Reload the page.'], 403);
sda_exam_ensure($pdo);

$uid = (int)$_SESSION['user_id'];
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$action = (string)($in['action'] ?? '');

if ($action === 'start') {
    $qid = (int)($in['quiz_id'] ?? 0);
    $st = $pdo->prepare("SELECT q.* FROM quizzes q JOIN enrollments e ON e.course_id = q.course_id AND e.student_id = ? AND e.status IN ('active','completed') WHERE q.id = ?");
    $st->execute([$uid, $qid]);
    $quiz = $st->fetch();
    if (!$quiz) $out(['ok' => false, 'error' => 'You need to be enrolled in this course to take the test.'], 403);
    // A test left open earlier is finished first, with what was saved
    if ($open = sda_exam_open_attempt($pdo, $qid, $uid)) sda_exam_finalize($pdo, (int)$open['id'], $uid, null, 'closed_early');
    $mins = max(1, (int)$quiz['time_limit_mins']);
    $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, student_id, score, passed, answers_json, status, started_at, deadline_at)
                   VALUES (?, ?, 0, 0, '{}', 'in_progress', NOW(), NOW() + INTERVAL ? MINUTE)")->execute([$qid, $uid, $mins]);
    $aid = (int)$pdo->lastInsertId();
    sda_exam_log($pdo, $aid, 'started');
    $out(['ok' => true, 'attempt' => $aid, 'seconds_left' => $mins * 60, 'max_violations' => SDA_EXAM_MAX_VIOLATIONS]);
}

$aid = (int)($in['attempt'] ?? 0);
$st = $pdo->prepare("SELECT *, TIMESTAMPDIFF(SECOND, NOW(), deadline_at) AS secs_left FROM quiz_attempts WHERE id = ? AND student_id = ?");
$st->execute([$aid, $uid]);
$a = $st->fetch();
if (!$a) $out(['ok' => false, 'error' => 'Test not found.'], 404);
if ($a['status'] !== 'in_progress') $out(['ok' => false, 'closed' => true, 'error' => 'This test has already been submitted.'], 409);

if ($action === 'save') {
    $answers = is_array($in['answers'] ?? null) ? array_slice($in['answers'], 0, 200, true) : [];
    $clean = [];
    foreach ($answers as $k => $v) $clean[(int)$k] = mb_substr((string)$v, 0, 20000);
    $pdo->prepare("UPDATE quiz_attempts SET answers_json = ? WHERE id = ? AND status = 'in_progress'")->execute([json_encode($clean), $aid]);
    // Past the deadline (plus grace): finish it now
    if ((int)$a['secs_left'] < -SDA_EXAM_GRACE_SECS) {
        $r = sda_exam_finalize($pdo, $aid, $uid, $clean, 'time_up');
        $out(['ok' => true, 'submitted' => true, 'result' => $r]);
    }
    $out(['ok' => true, 'seconds_left' => max(0, (int)$a['secs_left'])]);
}

if ($action === 'violation') {
    $kind = preg_replace('/[^a-z_]/', '', (string)($in['kind'] ?? 'left_tab')) ?: 'left_tab';
    $pdo->prepare("UPDATE quiz_attempts SET violations = violations + 1 WHERE id = ?")->execute([$aid]);
    sda_exam_log($pdo, $aid, 'warning: ' . $kind);
    $n = (int)$a['violations'] + 1;
    $out(['ok' => true, 'violations' => $n, 'max' => SDA_EXAM_MAX_VIOLATIONS, 'submit' => $n >= SDA_EXAM_MAX_VIOLATIONS]);
}

$out(['ok' => false, 'error' => 'Unknown action.'], 400);
