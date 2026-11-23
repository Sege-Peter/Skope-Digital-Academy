<?php
/**
 * Learning Lab workbook endpoint (POST JSON, same-origin, X-CSRF-Token header). Signed-in students only.
 *   {action: "save_note", lesson_id, note}      -> saves the learner's notes for a lesson
 *   {action: "task", lesson_id, done: true|false} -> ticks the lesson's practice task
 * Only for lessons the learner may read (enrolled course, or a preview lesson).
 * Table lab_workbook is created on first use.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/learning.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function lab_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') lab_out(['ok' => false, 'error' => 'POST only'], 405);
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') lab_out(['ok' => false, 'error' => 'signin'], 401);
if (!verifyCsrf()) lab_out(['ok' => false, 'error' => 'csrf'], 403);

$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$action = (string)($in['action'] ?? '');
$lessonId = (int)($in['lesson_id'] ?? 0);
$uid = (int)$_SESSION['user_id'];

lab_workbook_ensure($pdo);

$st = $pdo->prepare("SELECT l.id, l.course_id, l.is_preview, c.status FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ?");
$st->execute([$lessonId]);
$lesson = $st->fetch();
if (!$lesson || $lesson['status'] !== 'published') lab_out(['ok' => false, 'error' => 'lesson'], 404);
$enr = get_enrollment($pdo, $uid, (int)$lesson['course_id']);
$enrolled = $enr && in_array($enr['status'], ['active', 'completed'], true);
if (!$enrolled && empty($lesson['is_preview'])) lab_out(['ok' => false, 'error' => 'not_enrolled'], 403);

if ($action === 'save_note') {
    $note = mb_substr(str_replace("\0", '', (string)($in['note'] ?? '')), 0, 20000);
    $pdo->prepare("INSERT INTO lab_workbook (user_id, lesson_id, course_id, note) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE note = VALUES(note), updated_at = NOW()")
        ->execute([$uid, $lessonId, (int)$lesson['course_id'], $note]);
    lab_out(['ok' => true, 'saved_at' => date('H:i')]);
}
if ($action === 'task') {
    $done = !empty($in['done']) ? 1 : 0;
    $pdo->prepare("INSERT INTO lab_workbook (user_id, lesson_id, course_id, task_done, task_done_at) VALUES (?, ?, ?, ?, IF(? = 1, NOW(), NULL))
                   ON DUPLICATE KEY UPDATE task_done = VALUES(task_done), task_done_at = VALUES(task_done_at), updated_at = NOW()")
        ->execute([$uid, $lessonId, (int)$lesson['course_id'], $done, $done]);
    lab_out(['ok' => true, 'done' => (bool)$done]);
}
lab_out(['ok' => false, 'error' => 'action'], 400);
