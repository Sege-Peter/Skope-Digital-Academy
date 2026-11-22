<?php
$course_id = (int)($_GET['course_id'] ?? 0);

// 1. Fetch available courses based on role
if ($user['role'] === 'admin') {
    $courses = $pdo->query("SELECT id, title FROM courses ORDER BY title ASC")->fetchAll();
} elseif ($user['role'] === 'tutor') {
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id = ? ORDER BY title ASC");
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
} else {
    // For student, show leaderboard for courses they are enrolled in
    $stmt = $pdo->prepare("SELECT c.id, c.title FROM courses c JOIN enrollments e ON c.id = e.course_id WHERE e.student_id = ? ORDER BY c.title ASC");
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
}

if (!$course_id && !empty($courses)) {
    $course_id = $courses[0]['id'];
}

// 2. Leaderboard for the selected course, from the learning engine (XP earned in the course).
$leaderboard = [];
if ($course_id) {
    try {
        require_once __DIR__ . '/progress.php';
        foreach (course_leaderboard($pdo, (int)$course_id, ($_GET['period'] ?? '') === 'week' ? 'week' : 'all', 50) as $row) {
            $best = best_quiz_pct($pdo, (int)$row['id'], (int)$course_id);
            $badges = $pdo->prepare("SELECT COUNT(*) FROM student_badges sb JOIN badges b ON b.id = sb.badge_id WHERE sb.student_id = ? AND b.course_id = ?");
            $badges->execute([$row['id'], $course_id]);
            $row['avg_quiz_score'] = $best ?? 0;
            $row['course_badges'] = (int)$badges->fetchColumn();
            if ($user['role'] === 'student') $row['name'] = $row['display_name'];
            $leaderboard[] = $row;
        }
    } catch (Exception $e) { error_log($e->getMessage()); $leaderboard = []; }
}
