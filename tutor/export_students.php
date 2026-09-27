<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Access control: Tutor or Admin
if (!isLoggedIn() || ($_SESSION['role'] !== 'tutor' && $_SESSION['role'] !== 'admin')) {
    die("Unauthorized access.");
}

$user = currentUser();
$course_filter = $_GET['course_filter'] ?? '';

try {
    $query = "SELECT u.name as student_name, u.email as student_email, 
                     c.title as course_title, e.progress_percent, e.enrolled_at,
                     (SELECT AVG(score) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id WHERE qa.student_id = u.id AND q.course_id = c.id) as avg_score
              FROM enrollments e
              JOIN users u ON e.student_id = u.id
              JOIN courses c ON e.course_id = c.id
              WHERE c.tutor_id = ?";
    
    $params = [$user['id']];

    if ($course_filter) {
        $query .= " AND e.course_id = ?";
        $params[] = $course_filter;
    }

    $query .= " ORDER BY e.enrolled_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // CSV Headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Student_Gradebook_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student Name', 'Email', 'Course', 'Progress %', 'Avg Quiz Score', 'Enrolled Date']);

    foreach ($students as $row) {
        fputcsv($output, [
            $row['student_name'],
            $row['student_email'],
            $row['course_title'],
            round($row['progress_percent'], 2) . '%',
            ($row['avg_score'] !== null) ? round($row['avg_score'], 2) . '%' : 'N/A',
            date('Y-m-d', strtotime($row['enrolled_at']))
        ]);
    }
    fclose($output);
    exit;

} catch (Exception $e) {
    die("Export Error: " . $e->getMessage());
}
