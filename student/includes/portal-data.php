<?php
/**
 * portal-data.php
 * Fetches common student portal data (notifications, stats, calendar, etc.)
 * This allows all pages to have the same look/feel as the main dashboard.
 */

try {
    // 1. Fetch Student Point Summary & Basic Stats
    $stmt = $pdo->prepare("SELECT merit_points, merit_coins, streak_days, referral_code, avatar, name FROM users WHERE id = ?");
    $stmt->execute([$student['id']]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student_info) {
        $student = array_merge($student_info, $student);
    }

    // 2. Fetch active course count & enrollments
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.thumbnail, 
                           (SELECT name FROM users WHERE id = c.tutor_id) as tutor_name,
                           (SELECT (COUNT(lp.id) / COUNT(l.id)) * 100 
                            FROM lessons l 
                            LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.student_id = e.student_id AND lp.status = 'completed'
                            WHERE l.course_id = e.course_id
                           ) as progress_percent
                           FROM enrollments e 
                           JOIN courses c ON e.course_id = c.id 
                           WHERE e.student_id = ? AND e.status = 'active' AND c.status = 'published'");
    $stmt->execute([$student['id']]);
    $my_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $active_course_count = count($my_courses);

    // 3. Live Notifications
    $stmt = $pdo->prepare("SELECT * FROM notifications 
                           WHERE (user_role = 'student' OR user_role = 'all' OR target_user_id = ?) 
                           ORDER BY created_at DESC LIMIT 8");
    $stmt->execute([$student['id']]);
    $portal_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $portal_unread_count = count(array_filter($portal_notifications, fn($n) => empty($n['read_status'])));

    // 4. New Assignments
    $stmt = $pdo->prepare("SELECT a.id, a.title, a.due_date, c.title as course_name
                           FROM assignments a
                           JOIN courses c ON a.course_id = c.id
                           JOIN enrollments e ON e.course_id = c.id
                           WHERE e.student_id = ? AND e.status = 'active' AND c.status = 'published'
                           AND a.id NOT IN (SELECT assignment_id FROM assignment_submissions WHERE student_id = ?)
                           ORDER BY a.due_date ASC LIMIT 5");
    $stmt->execute([$student['id'], $student['id']]);
    $portal_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Live CAT Quizzes
    $stmt = $pdo->prepare("SELECT q.id, q.title, c.title as course_name, q.created_at
                           FROM quizzes q
                           JOIN enrollments e ON q.course_id = e.course_id
                           JOIN courses c ON q.course_id = c.id
                           WHERE e.student_id = ? AND q.type IN ('cat', 'final') AND c.status = 'published'
                           AND q.id NOT IN (SELECT quiz_id FROM quiz_attempts WHERE student_id = ?)
                           ORDER BY q.created_at DESC LIMIT 5");
    $stmt->execute([$student['id'], $student['id']]);
    $portal_cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Calendar Events
    $stmt = $pdo->prepare("SELECT * FROM calendar_events WHERE student_id = ? OR target_role = 'student' OR target_role = 'all' ORDER BY event_date ASC");
    $stmt->execute([$student['id']]);
    $portal_cal_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Portal Data Error: " . $e->getMessage());
    $portal_notifications = $portal_assignments = $portal_cats = $portal_cal_events = [];
    $portal_unread_count = 0;
}
