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

// 2. Fetch Leaderboard Data for selected course
$leaderboard = [];
if ($course_id) {
    try {
        $stmt = $pdo->prepare("SELECT u.id, u.name, u.avatar, 
                                      e.progress_percent, e.enrolled_at,
                                      (SELECT ROUND(AVG(score), 2) FROM quiz_attempts qa 
                                       JOIN quizzes q ON qa.quiz_id = q.id 
                                       WHERE qa.student_id = u.id AND q.course_id = ?) as avg_quiz_score,
                                      (SELECT COUNT(*) FROM student_badges sb 
                                       JOIN badges b ON sb.badge_id = b.id 
                                       WHERE sb.student_id = u.id AND b.course_id = ?) as course_badges
                              FROM enrollments e
                              JOIN users u ON e.student_id = u.id
                              WHERE e.course_id = ?
                              ORDER BY avg_quiz_score DESC, progress_percent DESC, course_badges DESC
                              LIMIT 50");
        $stmt->execute([$course_id, $course_id, $course_id]);
        $leaderboard = $stmt->fetchAll();
    } catch (Exception $e) { $leaderboard = []; }
}
