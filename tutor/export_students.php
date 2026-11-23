<?php
/**
 * CSV export of the learners in the signed-in tutor's courses (same rows as the Learners page).
 */
require_once __DIR__ . '/../includes/tutor_data.php';

if (!isLoggedIn() || !in_array($_SESSION['role'] ?? '', ['tutor', 'admin'], true)) {
    http_response_code(403);
    exit('Please sign in as a tutor.');
}
$user = currentUser();
$courseId = (int)($_GET['course_filter'] ?? 0);

try {
    $rows = td_enrolments($pdo, (int)$user['id'], ['course_id' => $courseId ?: null, 'order' => 'name']);
} catch (Throwable $e) {
    error_log('tutor export: ' . $e->getMessage());
    http_response_code(500);
    exit('The export failed. Please try again.');
}

/** Stop spreadsheet apps treating a cell as a formula. */
function tp_csv_cell($v): string {
    $v = (string)$v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=learners_' . date('Y-m-d') . '.csv');
$out = fopen('php://output', 'w');
fputcsv($out, ['Learner', 'Email', 'Course', 'Status', 'Track', 'Progress %', 'Quiz average %', 'Enrolled on', 'Last active']);
foreach ($rows as $r) {
    fputcsv($out, array_map('tp_csv_cell', [
        $r['student_name'], $r['student_email'], $r['course_title'], ucfirst($r['status']), mode_label($r['learning_mode'] ?: 'self_paced'),
        round((float)$r['progress_percent'], 1),
        $r['avg_quiz'] !== null ? round((float)$r['avg_quiz'], 1) : '',
        date('Y-m-d', strtotime($r['enrolled_at'])),
        $r['last_active'] ? date('Y-m-d H:i', strtotime($r['last_active'])) : '',
    ]));
}
fclose($out);
exit;
