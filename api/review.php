<?php
/**
 * Course rating & review (POST JSON {course_id, rating 1-5, review}). Signed-in students
 * who are enrolled and have finished at least a third of the lessons (or the course).
 * Keeps courses.avg_rating up to date and awards review XP once.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/progress.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'POST only'], 405);
if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') out(['ok' => false, 'error' => 'Please sign in as a student.'], 401);
if (!verifyCsrf()) out(['ok' => false, 'error' => 'Your session expired. Refresh the page.'], 403);

$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$uid = (int)$_SESSION['user_id'];
$courseId = (int)($in['course_id'] ?? 0);
$rating = (int)($in['rating'] ?? 0);
$review = trim(mb_substr((string)($in['review'] ?? ''), 0, 1500));
if ($rating < 1 || $rating > 5) out(['ok' => false, 'error' => 'Choose 1 to 5 stars.'], 422);

$e = get_enrollment($pdo, $uid, $courseId);
if (!$e || !in_array($e['status'], ['active', 'completed'], true)) out(['ok' => false, 'error' => 'Only learners taking this course can review it.'], 403);
$tot = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
$tot->execute([$courseId]);
$done = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id WHERE lp.student_id = ? AND l.course_id = ? AND lp.status = 'completed'");
$done->execute([$uid, $courseId]);
if ($e['status'] !== 'completed' && (int)$done->fetchColumn() < max(1, (int)ceil($tot->fetchColumn() / 3))) {
    out(['ok' => false, 'error' => 'Finish a few more lessons before reviewing.'], 403);
}

$pdo->prepare("INSERT INTO course_ratings (student_id, course_id, rating, review, created_at) VALUES (?, ?, ?, ?, NOW())
               ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review), updated_at = NOW()")
    ->execute([$uid, $courseId, $rating, $review !== '' ? $review : null]);
$pdo->prepare("UPDATE courses SET avg_rating = (SELECT ROUND(AVG(rating), 2) FROM course_ratings WHERE course_id = ?) WHERE id = ?")->execute([$courseId, $courseId]);
$xp = record_event($pdo, $uid, 'review', 'review:' . $courseId, XP_RULES['review'], $courseId);
$badges = check_badges($pdo, $uid);
$st = $pdo->prepare("SELECT ROUND(AVG(rating), 1) avg_r, COUNT(*) n FROM course_ratings WHERE course_id = ?");
$st->execute([$courseId]);
out(['ok' => true, 'xp' => $xp, 'badges' => $badges, 'stats' => $st->fetch()]);
