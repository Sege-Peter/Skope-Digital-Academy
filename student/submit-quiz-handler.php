<?php
/**
 * Submit a test: grades the attempt that exam-api started (answers saved as the learner went),
 * records it once, then rewards points and the transcript entry on a pass.
 * POST JSON {quiz_id, attempt, answers, reason} + X-CSRF-Token.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') { http_response_code(401); echo json_encode(['error' => 'Please sign in again.']); exit; }
if (!verifyCsrf()) { http_response_code(403); echo json_encode(['error' => 'Your session expired. Reload the page.']); exit; }

$data = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($data) || empty($data['quiz_id'])) { http_response_code(400); echo json_encode(['error' => 'Missing test details.']); exit; }

sda_exam_ensure($pdo);
$qid = (int)$data['quiz_id'];
$aid = (int)($data['attempt'] ?? 0);
$student_id = (int)$_SESSION['user_id'];
$answers = [];
foreach ((is_array($data['answers'] ?? null) ? $data['answers'] : []) as $k => $v) $answers[(int)$k] = mb_substr((string)$v, 0, 20000);
$reason = in_array($data['reason'] ?? '', ['submitted', 'time_up', 'violations'], true) ? $data['reason'] : 'submitted';

try {
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->execute([$qid]);
    $quiz = $stmt->fetch();
    if (!$quiz) throw new RuntimeException('Test not found.');

    // The test must have been started (exam-api); a finished one is never re-graded
    $st = $pdo->prepare("SELECT id, status, score, passed FROM quiz_attempts WHERE id = ? AND student_id = ? AND quiz_id = ?");
    $st->execute([$aid, $student_id, $qid]);
    $att = $st->fetch();
    if (!$att) throw new RuntimeException('This test was not started properly. Reload the page and start again.');
    if ($att['status'] !== 'in_progress') {
        echo json_encode(['score' => round((float)$att['score']), 'passed' => (int)$att['passed'], 'already' => true]);
        exit;
    }

    $res = sda_exam_finalize($pdo, $aid, $student_id, $answers, $reason);
    if (!$res) throw new RuntimeException('This test has already been submitted.');
    $score_percent = (float)$res['score'];
    $passed = (int)$res['passed'];

    if ($passed) {
        // Merit points: never let a reward problem lose a test result
        try {
            require_once __DIR__ . '/../includes/gamified_logic.php';
            if (function_exists('rewardStudentPoints')) rewardStudentPoints($student_id, $res['earned'], $pdo);
        } catch (Throwable $e) { error_log('[quiz reward] ' . $e->getMessage()); }

        // Transcript: keep the highest score for this test
        try {
            $grade = $score_percent >= 90 ? 'A+' : ($score_percent >= 80 ? 'A' : ($score_percent >= 70 ? 'B' : ($score_percent >= 60 ? 'C' : ($score_percent >= 50 ? 'D' : 'F'))));
            $entry_title = ($quiz['type'] == 'cat' ? 'CAT: ' : ($quiz['type'] == 'final' ? 'FINAL: ' : 'QUIZ: ')) . $quiz['title'];
            $t = $pdo->prepare("SELECT tutor_id FROM courses WHERE id = ?");
            $t->execute([$quiz['course_id']]);
            $tutor_id = $t->fetchColumn();
            $c = $pdo->prepare("SELECT id, score FROM transcript_entries WHERE student_id = ? AND title = ? AND course_id = ?");
            $c->execute([$student_id, $entry_title, $quiz['course_id']]);
            $existing = $c->fetch();
            if ($existing) {
                if ($score_percent > $existing['score']) $pdo->prepare("UPDATE transcript_entries SET score = ?, grade = ?, recorded_at = NOW() WHERE id = ?")->execute([$score_percent, $grade, $existing['id']]);
            } else {
                $pdo->prepare("INSERT INTO transcript_entries (student_id, course_id, entry_type, title, score, max_score, grade, credits, recorded_by) VALUES (?, ?, 'quiz_pass', ?, ?, 100, ?, 1.0, ?)")
                    ->execute([$student_id, $quiz['course_id'], $entry_title, $score_percent, $grade, $tutor_id]);
            }
        } catch (Throwable $e) { error_log('[quiz transcript] ' . $e->getMessage()); }
    }

    echo json_encode(['score' => round($score_percent), 'points' => round($res['earned']), 'passed' => $passed, 'reason' => $reason]);
} catch (Throwable $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
}
