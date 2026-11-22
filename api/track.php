<?php
/**
 * Learning tracker endpoint (POST JSON, same-origin, CSRF header).
 *   {action: "view"|"time"|"complete"|"exercise"|"quiz", lesson_id, seconds?, n?, score?, total?}
 * Signed-in students only. Free open tutorials are tracked automatically (self-paced
 * enrollment is created on first activity); other courses need an active enrollment.
 * Responds with {ok, xp, total_xp, level, badges[], certificate, completed, progress}.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/progress.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'POST only'], 405);
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') out(['ok' => false, 'error' => 'signin'], 401);
if (!verifyCsrf()) out(['ok' => false, 'error' => 'csrf'], 403);

$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$action = (string)($in['action'] ?? '');
$lessonId = (int)($in['lesson_id'] ?? 0);
$uid = (int)$_SESSION['user_id'];

$st = $pdo->prepare("SELECT l.id, l.course_id, l.is_preview, l.release_date, c.status AS course_status, c.is_open_tutorial, c.price, c.learning_modes, c.access_days, c.sale_price, c.sale_ends_at, c.structured_price, c.id AS cid
                     FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ?");
$st->execute([$lessonId]);
$lesson = $st->fetch();
if (!$lesson || $lesson['course_status'] !== 'published') out(['ok' => false, 'error' => 'lesson'], 404);

$course = ['id' => (int)$lesson['course_id'], 'is_open_tutorial' => $lesson['is_open_tutorial'], 'price' => $lesson['price'],
           'learning_modes' => $lesson['learning_modes'], 'access_days' => $lesson['access_days'], 'sale_price' => $lesson['sale_price'],
           'sale_ends_at' => $lesson['sale_ends_at'], 'structured_price' => $lesson['structured_price']];
$enrollment = get_enrollment($pdo, $uid, $course['id']);
$active = $enrollment && in_array($enrollment['status'], ['active', 'completed'], true);

// Auto-track free tutorials: enroll self-paced on first real activity.
if (!$active && $course['is_open_tutorial'] && in_array('self_paced', course_modes($course), true) && course_price($course, 'self_paced') <= 0) {
    enroll_student($pdo, $uid, $course, 'self_paced', null, 0);
    $enrollment = get_enrollment($pdo, $uid, $course['id']);
    $active = true;
}
if (!$active) out(['ok' => false, 'error' => 'not_enrolled'], 403);

// Structured-track locks apply to completion credit too.
if (in_array($action, ['complete', 'exercise', 'quiz'], true)) {
    $all = apply_lesson_schedule(load_course_lessons($pdo, $course['id'], $uid), $enrollment);
    foreach ($all as $l) {
        if ((int)$l['id'] === $lessonId && !empty($l['is_locked'])) out(['ok' => false, 'error' => 'locked', 'reason' => $l['lock_reason']], 403);
    }
}

$lessonRow = ['id' => (int)$lesson['id'], 'course_id' => $course['id']];
$res = progress_result();
try {
    switch ($action) {
        case 'view':
            $res = track_view($pdo, $uid, $lessonRow);
            break;
        case 'time':
            track_time($pdo, $uid, $lessonRow, (int)($in['seconds'] ?? 0));
            break;
        case 'complete':
            $pdo->prepare("INSERT INTO lesson_progress (student_id, lesson_id, course_id, status, completed_at) VALUES (?, ?, ?, 'completed', NOW())
                           ON DUPLICATE KEY UPDATE status = 'completed', completed_at = COALESCE(completed_at, NOW())")
                ->execute([$uid, $lessonRow['id'], $course['id']]);
            $res = track_lesson_complete($pdo, $uid, $lessonRow);
            break;
        case 'exercise':
            $res = track_exercise($pdo, $uid, $lessonRow, max(1, (int)($in['n'] ?? 1)));
            break;
        case 'quiz':
            $total = max(0, min(100, (int)($in['total'] ?? 0)));
            $score = max(0, min($total, (int)($in['score'] ?? 0)));
            $res = track_quiz($pdo, $uid, $lessonRow, max(1, (int)($in['n'] ?? 1)), $score, $total);
            break;
        default:
            out(['ok' => false, 'error' => 'action'], 400);
    }
} catch (Exception $e) {
    error_log('track: ' . $e->getMessage());
    out(['ok' => false, 'error' => 'server'], 500);
}

$xp = $pdo->prepare("SELECT xp FROM users WHERE id = ?");
$xp->execute([$uid]);
$total = (int)$xp->fetchColumn();
$res['ok'] = true;
$res['total_xp'] = $total;
$res['level'] = xp_level($total);
if ($res['certificate']) $res['certificate']['url'] = 'certificate?code=' . rawurlencode($res['certificate']['code']);
out($res);
