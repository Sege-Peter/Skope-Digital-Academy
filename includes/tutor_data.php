<?php
/**
 * Tutor portal data – Skope Digital Academy
 *
 * One place for every number the tutor portal shows, so the dashboard, Learners,
 * Analytics, Leaderboard and Earnings pages can never disagree.
 *
 * Definitions (used everywhere):
 *  - An enrolment counts only when its course belongs to the tutor (courses.tutor_id) AND the learner's
 *    user row still exists with role 'student'. Enrolments of deleted accounts are reported separately
 *    as "orphaned" and never counted.
 *  - Learner = a distinct student with at least one enrolment that is 'active' or 'completed'.
 *  - Pending enrolments (awaiting payment/approval) are shown separately; cancelled ones are ignored.
 *  - Earnings = verified course-fee payments (payment_type 'course') for the tutor's courses, dated by
 *    COALESCE(verified_at, created_at) – the same rule admin/payroll.php uses – times the tutor share
 *    (platform setting tutor_revenue_share, default 80%). Certificate fees stay with the academy.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/learning.php';

const TD_LEARNER_STATUSES = ['active', 'completed'];

/** Tutor share as a fraction (0.8 = 80%). */
function td_share_rate(): float {
    return max(0.0, min(100.0, (float)setting('tutor_revenue_share', '80'))) / 100;
}

/** Tutor share as a display percentage, e.g. "80". */
function td_share_pct(): string {
    $p = td_share_rate() * 100;
    return rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.');
}

/** "KES 2,000" */
function td_money(float $amount): string {
    return setting('currency', 'KES') . ' ' . number_format($amount, abs($amount - round($amount)) < 0.005 ? 0 : 2);
}

/** Every course owned by the tutor, newest first, with live counts. */
function td_courses(PDO $pdo, int $tid): array {
    $st = $pdo->prepare(
        "SELECT c.*, cat.name AS category_name,
                (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count,
                (SELECT COUNT(*) FROM quizzes q WHERE q.course_id = c.id) AS quiz_count,
                (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) AS assignment_count,
                (SELECT COUNT(DISTINCT e.student_id) FROM enrollments e JOIN users u ON u.id = e.student_id AND u.role = 'student'
                   WHERE e.course_id = c.id AND e.status IN ('active','completed')) AS learner_count,
                (SELECT COUNT(*) FROM enrollments e JOIN users u ON u.id = e.student_id AND u.role = 'student'
                   WHERE e.course_id = c.id AND e.status = 'pending') AS pending_count,
                (SELECT AVG(e.progress_percent) FROM enrollments e JOIN users u ON u.id = e.student_id AND u.role = 'student'
                   WHERE e.course_id = c.id AND e.status IN ('active','completed')) AS avg_progress,
                (SELECT AVG(r.rating) FROM course_ratings r WHERE r.course_id = c.id) AS rating_avg,
                (SELECT COUNT(*) FROM course_ratings r WHERE r.course_id = c.id) AS rating_count
         FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id
         WHERE c.tutor_id = ?
         ORDER BY c.created_at DESC, c.id DESC");
    $st->execute([$tid]);
    return $st->fetchAll();
}

/** Course ids → titles for selects. */
function td_course_options(PDO $pdo, int $tid): array {
    $st = $pdo->prepare("SELECT id, title, status FROM courses WHERE tutor_id = ? ORDER BY title");
    $st->execute([$tid]);
    return $st->fetchAll();
}

/** Does the tutor own this course? */
function td_owns_course(PDO $pdo, int $tid, int $courseId): bool {
    $st = $pdo->prepare("SELECT 1 FROM courses WHERE id = ? AND tutor_id = ?");
    $st->execute([$courseId, $tid]);
    return (bool)$st->fetchColumn();
}

/**
 * Enrolments in the tutor's courses (existing student accounts only).
 * $opt: statuses (array), course_id, student_id, search, limit, order ('recent'|'name'|'progress')
 */
function td_enrolments(PDO $pdo, int $tid, array $opt = []): array {
    $where = ['c.tutor_id = ?'];
    $params = [$tid];
    $statuses = $opt['statuses'] ?? TD_LEARNER_STATUSES;
    if ($statuses) {
        $where[] = 'e.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        array_push($params, ...$statuses);
    }
    if (!empty($opt['course_id']))  { $where[] = 'e.course_id = ?';  $params[] = (int)$opt['course_id']; }
    if (!empty($opt['student_id'])) { $where[] = 'e.student_id = ?'; $params[] = (int)$opt['student_id']; }
    if (($opt['search'] ?? '') !== '') {
        $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
        $params[] = '%' . $opt['search'] . '%';
        $params[] = '%' . $opt['search'] . '%';
    }
    $order = match ($opt['order'] ?? 'recent') {
        'name'     => 'u.name ASC, e.enrolled_at DESC',
        'progress' => 'e.progress_percent DESC, u.name ASC',
        default    => 'e.enrolled_at DESC, e.id DESC',
    };
    $limit = isset($opt['limit']) ? ' LIMIT ' . max(1, (int)$opt['limit']) : '';
    $st = $pdo->prepare(
        "SELECT e.id AS enrolment_id, e.student_id, e.course_id, e.status, e.progress_percent, e.enrolled_at, e.completed_at,
                e.learning_mode, e.cohort_id, e.starts_on, e.expires_at,
                u.name AS student_name, u.email AS student_email, u.avatar, u.last_login,
                c.title AS course_title, cc.name AS cohort_name,
                (SELECT AVG(qa.score) FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
                   WHERE qa.student_id = e.student_id AND q.course_id = e.course_id) AS avg_quiz,
                (SELECT MAX(le.created_at) FROM learning_events le WHERE le.user_id = e.student_id AND le.course_id = e.course_id) AS last_active
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         JOIN users u ON u.id = e.student_id AND u.role = 'student'
         LEFT JOIN course_cohorts cc ON cc.id = e.cohort_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY $order$limit");
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * The single learner summary every page uses.
 * Returns learners, enrolments, active, completed, pending, orphaned, avg_progress (null when no learners).
 */
function td_learner_summary(PDO $pdo, int $tid, ?int $courseId = null): array {
    $cw = $courseId ? ' AND c.id = ' . (int)$courseId : '';
    $st = $pdo->prepare(
        "SELECT COUNT(DISTINCT CASE WHEN e.status IN ('active','completed') THEN e.student_id END) AS learners,
                SUM(e.status IN ('active','completed')) AS enrolments,
                SUM(e.status = 'active') AS active,
                SUM(e.status = 'completed') AS completed,
                SUM(e.status = 'pending') AS pending,
                AVG(CASE WHEN e.status IN ('active','completed') THEN e.progress_percent END) AS avg_progress
         FROM enrollments e
         JOIN courses c ON c.id = e.course_id
         JOIN users u ON u.id = e.student_id AND u.role = 'student'
         WHERE c.tutor_id = ?$cw");
    $st->execute([$tid]);
    $r = $st->fetch() ?: [];
    $o = $pdo->prepare("SELECT COUNT(*) FROM enrollments e JOIN courses c ON c.id = e.course_id
                        LEFT JOIN users u ON u.id = e.student_id
                        WHERE c.tutor_id = ?$cw AND u.id IS NULL");
    $o->execute([$tid]);
    return [
        'learners'     => (int)($r['learners'] ?? 0),
        'enrolments'   => (int)($r['enrolments'] ?? 0),
        'active'       => (int)($r['active'] ?? 0),
        'completed'    => (int)($r['completed'] ?? 0),
        'pending'      => (int)($r['pending'] ?? 0),
        'orphaned'     => (int)$o->fetchColumn(),
        'avg_progress' => isset($r['avg_progress']) && $r['avg_progress'] !== null ? (float)$r['avg_progress'] : null,
    ];
}

/** One plain sentence explaining why some enrolments are not counted ('' when nothing to explain). */
function td_orphan_note(array $sum): string {
    if ($sum['orphaned'] < 1) return '';
    return $sum['orphaned'] === 1
        ? '1 enrolment belongs to an account that no longer exists, so it is not counted.'
        : $sum['orphaned'] . ' enrolments belong to accounts that no longer exist, so they are not counted.';
}

/** Earnings (gross + tutor share) from verified course payments between two dates (inclusive, Y-m-d), or all time. */
function td_earnings(PDO $pdo, int $tid, ?string $from = null, ?string $to = null, ?int $courseId = null): array {
    $sql = "SELECT COUNT(p.id) AS payments, COALESCE(SUM(p.amount), 0) AS gross
            FROM payments p JOIN courses c ON c.id = p.course_id
            WHERE c.tutor_id = ? AND p.status = 'verified' AND p.payment_type = 'course'";
    $params = [$tid];
    if ($from) { $sql .= " AND COALESCE(p.verified_at, p.created_at) >= ?"; $params[] = $from . ' 00:00:00'; }
    if ($to)   { $sql .= " AND COALESCE(p.verified_at, p.created_at) <= ?"; $params[] = $to . ' 23:59:59'; }
    if ($courseId) { $sql .= " AND c.id = ?"; $params[] = $courseId; }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    $gross = (float)$r['gross'];
    return ['payments' => (int)$r['payments'], 'gross' => $gross, 'share' => $gross * td_share_rate()];
}

/** Course payments still awaiting verification (gross + tutor share if approved). */
function td_pending_earnings(PDO $pdo, int $tid): array {
    $st = $pdo->prepare("SELECT COUNT(p.id) n, COALESCE(SUM(p.amount), 0) gross FROM payments p JOIN courses c ON c.id = p.course_id
                         WHERE c.tutor_id = ? AND p.status = 'pending' AND p.payment_type = 'course'");
    $st->execute([$tid]);
    $r = $st->fetch();
    return ['payments' => (int)$r['n'], 'gross' => (float)$r['gross'], 'share' => (float)$r['gross'] * td_share_rate()];
}

/** This month vs last month. 'change' is a percentage only when last month had earnings, else null. */
function td_month_compare(PDO $pdo, int $tid): array {
    $now = td_earnings($pdo, $tid, date('Y-m-01'), date('Y-m-t'));
    $lm = strtotime('first day of last month');
    $prev = td_earnings($pdo, $tid, date('Y-m-01', $lm), date('Y-m-t', $lm));
    $change = $prev['share'] > 0 ? (($now['share'] - $prev['share']) / $prev['share']) * 100 : null;
    return ['this' => $now, 'last' => $prev, 'change' => $change];
}

/** Earnings per calendar month for the last $months months (oldest first), zero-filled. */
function td_monthly_earnings(PDO $pdo, int $tid, int $months = 12, ?int $courseId = null): array {
    $start = date('Y-m-01', strtotime('first day of -' . ($months - 1) . ' months'));
    $st = $pdo->prepare("SELECT DATE_FORMAT(COALESCE(p.verified_at, p.created_at), '%Y-%m') ym, COUNT(*) n, SUM(p.amount) gross
                         FROM payments p JOIN courses c ON c.id = p.course_id
                         WHERE c.tutor_id = ? AND p.status = 'verified' AND p.payment_type = 'course'
                           AND COALESCE(p.verified_at, p.created_at) >= ? " . ($courseId ? ' AND c.id = ' . (int)$courseId : '') . "
                         GROUP BY ym");
    $st->execute([$tid, $start . ' 00:00:00']);
    $by = [];
    foreach ($st->fetchAll() as $r) $by[$r['ym']] = $r;
    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $t = strtotime('first day of -' . $i . ' months');
        $ym = date('Y-m', $t);
        $g = (float)($by[$ym]['gross'] ?? 0);
        $out[] = ['ym' => $ym, 'label' => date('M', $t), 'long' => date('F Y', $t), 'payments' => (int)($by[$ym]['n'] ?? 0),
                  'gross' => $g, 'share' => $g * td_share_rate()];
    }
    return $out;
}

/** New learner enrolments (active/completed, existing students) per month for the last $months months. */
function td_monthly_enrolments(PDO $pdo, int $tid, int $months = 6, ?int $courseId = null): array {
    $start = date('Y-m-01', strtotime('first day of -' . ($months - 1) . ' months'));
    $st = $pdo->prepare("SELECT DATE_FORMAT(e.enrolled_at, '%Y-%m') ym, COUNT(*) n
                         FROM enrollments e JOIN courses c ON c.id = e.course_id
                         JOIN users u ON u.id = e.student_id AND u.role = 'student'
                         WHERE c.tutor_id = ? AND e.status IN ('active','completed') AND e.enrolled_at >= ?" . ($courseId ? ' AND c.id = ' . (int)$courseId : '') . "
                         GROUP BY ym");
    $st->execute([$tid, $start . ' 00:00:00']);
    $by = array_column($st->fetchAll(), 'n', 'ym');
    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $t = strtotime('first day of -' . $i . ' months');
        $out[] = ['label' => date('M', $t), 'long' => date('F Y', $t), 'n' => (int)($by[date('Y-m', $t)] ?? 0)];
    }
    return $out;
}

/** Submissions waiting for a grade, oldest first. */
function td_pending_submissions(PDO $pdo, int $tid, ?int $limit = null): array {
    $st = $pdo->prepare("SELECT s.id, s.submitted_at, s.student_id, a.id AS assignment_id, a.title AS assignment_title, a.due_date, a.max_score,
                                c.title AS course_title, u.name AS student_name
                         FROM assignment_submissions s
                         JOIN assignments a ON a.id = s.assignment_id
                         JOIN courses c ON c.id = a.course_id
                         JOIN users u ON u.id = s.student_id
                         WHERE c.tutor_id = ? AND s.status = 'pending'
                         ORDER BY s.submitted_at ASC, s.id ASC" . ($limit ? ' LIMIT ' . (int)$limit : ''));
    $st->execute([$tid]);
    return $st->fetchAll();
}

function td_pending_submission_count(PDO $pdo, int $tid): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM assignment_submissions s JOIN assignments a ON a.id = s.assignment_id
                         JOIN courses c ON c.id = a.course_id JOIN users u ON u.id = s.student_id
                         WHERE c.tutor_id = ? AND s.status = 'pending'");
    $st->execute([$tid]);
    return (int)$st->fetchColumn();
}

/** Unread direct messages sent to the tutor. */
function td_unread_messages(PDO $pdo, int $tid, int $limit = 5): array {
    $n = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $n->execute([$tid]);
    $st = $pdo->prepare("SELECT m.id, m.sender_id, m.content, m.created_at, u.name AS sender_name, u.avatar
                         FROM messages m LEFT JOIN users u ON u.id = m.sender_id
                         WHERE m.receiver_id = ? AND m.is_read = 0
                         ORDER BY m.created_at DESC, m.id DESC LIMIT " . (int)$limit);
    $st->execute([$tid]);
    return ['count' => (int)$n->fetchColumn(), 'items' => $st->fetchAll()];
}

/** Unread personal notifications (broadcasts share one read flag for all recipients, so they are not counted). */
function td_unread_notifications(PDO $pdo, int $tid): int {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE read_status = 0 AND target_user_id = ?");
        $st->execute([$tid]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

/** Latest course reviews. */
function td_recent_reviews(PDO $pdo, int $tid, int $limit = 4): array {
    $st = $pdo->prepare("SELECT r.rating, r.review, COALESCE(r.updated_at, r.created_at) AS at, c.title AS course_title, u.name AS student_name
                         FROM course_ratings r JOIN courses c ON c.id = r.course_id LEFT JOIN users u ON u.id = r.student_id
                         WHERE c.tutor_id = ? ORDER BY at DESC LIMIT " . (int)$limit);
    $st->execute([$tid]);
    return $st->fetchAll();
}

/**
 * Everything dated in the next $days days: assignment deadlines, cohort starts, weekly lesson releases
 * for structured cohorts (via load_course_lessons()/apply_lesson_schedule()), and dated lesson releases.
 * Each item: at (timestamp), kind, title, detail, link. Sorted soonest first.
 */
function td_upcoming(PDO $pdo, int $tid, int $days = 14): array {
    $now = time();
    $until = strtotime("+$days days 23:59:59");
    $items = [];

    // Assignment deadlines
    $st = $pdo->prepare("SELECT a.id, a.title, a.due_date, c.title AS course_title, a.course_id,
                                (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) AS submitted
                         FROM assignments a JOIN courses c ON c.id = a.course_id
                         WHERE c.tutor_id = ? AND a.due_date BETWEEN ? AND ?");
    $st->execute([$tid, date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $until)]);
    foreach ($st->fetchAll() as $a) {
        $items[] = ['at' => strtotime($a['due_date']), 'kind' => 'deadline', 'icon' => 'fa-clipboard-check',
                    'title' => 'Assignment due: ' . $a['title'],
                    'detail' => $a['course_title'] . ' · ' . (int)$a['submitted'] . ' submitted so far',
                    'link' => 'assignments?course_id=' . (int)$a['course_id']];
    }

    // Cohorts: start dates, and weekly releases for cohorts already running
    $st = $pdo->prepare("SELECT cc.*, c.title AS course_title,
                                (SELECT COUNT(*) FROM enrollments e JOIN users u ON u.id = e.student_id AND u.role = 'student'
                                  WHERE e.cohort_id = cc.id AND e.status IN ('active','completed')) AS learners
                         FROM course_cohorts cc JOIN courses c ON c.id = cc.course_id
                         WHERE c.tutor_id = ? AND cc.status <> 'archived' AND cc.start_date IS NOT NULL AND cc.start_date <= ?
                           AND (cc.end_date IS NULL OR cc.end_date >= CURDATE())");
    $st->execute([$tid, date('Y-m-d', $until)]);
    $lessonCache = [];
    foreach ($st->fetchAll() as $co) {
        $start = strtotime($co['start_date']);
        $who = (int)$co['learners'] === 1 ? '1 learner' : (int)$co['learners'] . ' learners';
        if ($start >= strtotime('today') && $start <= $until) {
            $items[] = ['at' => $start, 'kind' => 'cohort', 'icon' => 'fa-users',
                        'title' => $co['name'] . ' starts', 'detail' => $co['course_title'] . ' · ' . $who . ' enrolled',
                        'link' => 'students?course_id=' . (int)$co['course_id']];
        }
        $cid = (int)$co['course_id'];
        $lessonCache[$cid] ??= load_course_lessons($pdo, $cid, null);
        $sched = apply_lesson_schedule($lessonCache[$cid], ['learning_mode' => 'structured', 'starts_on' => $co['start_date'], 'expires_at' => null]);
        $weeks = [];
        foreach ($sched as $l) {
            $w = (int)($l['week_num'] ?: 1);
            if (!empty($l['opens_at']) && $l['opens_at'] > $now && $l['opens_at'] <= $until && $l['opens_at'] > $start) {
                $weeks[$w] ??= ['at' => $l['opens_at'], 'n' => 0];
                $weeks[$w]['n']++;
            }
        }
        foreach ($weeks as $w => $info) {
            $items[] = ['at' => $info['at'], 'kind' => 'release', 'icon' => 'fa-unlock',
                        'title' => 'Week ' . $w . ' opens · ' . $co['name'],
                        'detail' => $co['course_title'] . ' · ' . $info['n'] . ' lesson' . ($info['n'] === 1 ? '' : 's') . ' · ' . $who,
                        'link' => 'lessons?course_id=' . $cid];
        }
    }

    // Lessons with their own release date
    $st = $pdo->prepare("SELECT l.title, l.release_date, l.course_id, c.title AS course_title FROM lessons l JOIN courses c ON c.id = l.course_id
                         WHERE c.tutor_id = ? AND l.release_date BETWEEN ? AND ?");
    $st->execute([$tid, date('Y-m-d H:i:s', $now), date('Y-m-d H:i:s', $until)]);
    foreach ($st->fetchAll() as $l) {
        $items[] = ['at' => strtotime($l['release_date']), 'kind' => 'release', 'icon' => 'fa-calendar-day',
                    'title' => 'Lesson released: ' . $l['title'], 'detail' => $l['course_title'],
                    'link' => 'lessons?course_id=' . (int)$l['course_id']];
    }

    usort($items, fn($a, $b) => $a['at'] <=> $b['at']);
    return $items;
}

/** The next cohort start after the window (for a helpful empty state), or null. */
function td_next_cohort(PDO $pdo, int $tid): ?array {
    $st = $pdo->prepare("SELECT cc.name, cc.start_date, c.title AS course_title FROM course_cohorts cc JOIN courses c ON c.id = cc.course_id
                         WHERE c.tutor_id = ? AND cc.status = 'open' AND cc.start_date > CURDATE() ORDER BY cc.start_date LIMIT 1");
    $st->execute([$tid]);
    return $st->fetch() ?: null;
}

/** Ranked learners of one course (tutor view: everyone enrolled, by XP earned in the course). */
function td_course_ranking(PDO $pdo, int $tid, int $courseId, string $period = 'all'): array {
    if (!td_owns_course($pdo, $tid, $courseId)) return [];
    $since = $period === 'week' ? "AND le.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : '';
    $st = $pdo->prepare("SELECT u.id, u.name, u.avatar, u.show_on_leaderboard, e.progress_percent, e.status, e.enrolled_at,
                                COALESCE((SELECT SUM(le.xp) FROM learning_events le WHERE le.user_id = u.id AND le.course_id = e.course_id $since), 0) AS course_xp,
                                (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id
                                   WHERE lp.student_id = u.id AND l.course_id = e.course_id AND lp.status = 'completed') AS lessons_done,
                                (SELECT AVG(qa.score) FROM quiz_attempts qa JOIN quizzes q ON q.id = qa.quiz_id
                                   WHERE qa.student_id = u.id AND q.course_id = e.course_id) AS avg_quiz,
                                (SELECT MAX(le.created_at) FROM learning_events le WHERE le.user_id = u.id AND le.course_id = e.course_id) AS last_active
                         FROM enrollments e JOIN users u ON u.id = e.student_id AND u.role = 'student'
                         WHERE e.course_id = ? AND e.status IN ('active','completed')
                         ORDER BY course_xp DESC, e.progress_percent DESC, u.name ASC");
    $st->execute([$courseId]);
    $rows = $st->fetchAll();
    foreach ($rows as $i => &$r) $r['rank'] = $i + 1;
    unset($r);
    return $rows;
}

/** Is this student enrolled (any status) in one of the tutor's courses? */
function td_teaches_student(PDO $pdo, int $tid, int $studentId): bool {
    $st = $pdo->prepare("SELECT 1 FROM enrollments e JOIN courses c ON c.id = e.course_id
                         JOIN users u ON u.id = e.student_id AND u.role = 'student'
                         WHERE c.tutor_id = ? AND e.student_id = ? LIMIT 1");
    $st->execute([$tid, $studentId]);
    return (bool)$st->fetchColumn();
}

/* ── Small view helpers ───────────────────────────────────────────────── */

/** Relative time in plain words: "just now", "5 min ago", "3 days ago", "in 2 days". */
function td_ago(?string $when): string {
    if (!$when) return '—';
    $t = strtotime($when);
    if (!$t) return '—';
    $d = time() - $t;
    $future = $d < 0;
    $d = abs($d);
    if ($d < 60) return $future ? 'in a moment' : 'just now';
    $units = [[86400 * 365, 'year'], [86400 * 30, 'month'], [86400 * 7, 'week'], [86400, 'day'], [3600, 'hour'], [60, 'min']];
    foreach ($units as [$s, $name]) {
        if ($d >= $s) {
            $n = (int)floor($d / $s);
            $label = $n . ' ' . $name . ($n === 1 || $name === 'min' ? '' : 's');
            return $future ? "in $label" : "$label ago";
        }
    }
    return '';
}

/** Initials for avatars: "Test Tutor" → "TT". */
function td_initials(?string $name): string {
    $parts = preg_split('/\s+/', trim((string)$name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) $i .= mb_strtoupper(mb_substr($p, 0, 1));
    return $i !== '' ? $i : '?';
}

/** Public URL of an uploaded file in uploads/<dir>/ if it exists on disk, else null. */
function td_upload_url(string $dir, ?string $file, string $prefix = '../'): ?string {
    if (!$file) return null;
    $base = basename($file);
    if ($base === '' || !is_file(__DIR__ . '/../uploads/' . $dir . '/' . $base)) return null;
    return $prefix . 'uploads/' . $dir . '/' . rawurlencode($base);
}

/** Avatar HTML: photo when the file exists, else initials. */
function td_avatar(?string $avatar, ?string $name, string $cls = 'tp-avatar'): string {
    $url = td_upload_url('avatars', $avatar);
    if ($url) return '<span class="' . $cls . '"><img src="' . e($url) . '" alt="" loading="lazy"></span>';
    return '<span class="' . $cls . '" aria-hidden="true">' . e(td_initials($name)) . '</span>';
}

/** Category → gradient for generated course covers. */
function td_cover_style(?string $category, int $seed = 0): string {
    $palettes = [
        'technology' => ['#0ea5e9', '#2563eb'], 'business' => ['#f59e0b', '#ea580c'], 'design' => ['#ec4899', '#8b5cf6'],
        'artificial' => ['#8b5cf6', '#4f46e5'], 'financial' => ['#10b981', '#0d9488'], 'career' => ['#f97316', '#e11d48'],
    ];
    $key = strtolower((string)$category);
    foreach ($palettes as $k => $p) if ($k !== '' && str_contains($key, $k)) return "background:linear-gradient(135deg,{$p[0]},{$p[1]})";
    $fallback = array_values($palettes)[abs($seed) % count($palettes)];
    return "background:linear-gradient(135deg,{$fallback[0]},{$fallback[1]})";
}

/** Status label + pill class for a course status. */
function td_course_status(string $s): array {
    return match ($s) {
        'published' => ['Live', 'ok'],
        'pending'   => ['In review', 'warn'],
        'archived'  => ['Archived', 'muted'],
        default     => ['Draft', 'muted'],
    };
}

/** Pill for an enrolment status. */
function td_enrol_status(string $s): array {
    return match ($s) {
        'active'    => ['Active', 'info'],
        'completed' => ['Completed', 'ok'],
        'pending'   => ['Pending', 'warn'],
        default     => ['Cancelled', 'muted'],
    };
}

/** Flash message helpers (session based, so nothing user-controlled is echoed from the URL). */
function td_flash(string $type, string $msg): void { $_SESSION['tp_flash'] = ['type' => $type, 'msg' => $msg]; }
function td_take_flash(): ?array { $f = $_SESSION['tp_flash'] ?? null; unset($_SESSION['tp_flash']); return $f; }

/** Redirect within the tutor portal and stop. */
function td_redirect(string $to): never { header('Location: ' . $to); exit; }

/**
 * Simple accessible bar chart. $items: list of ['label' => 'Nov', 'long' => 'November 2026', ...];
 * $key: value field; $fmt: value formatter; $alt: use the orange palette.
 */
function td_bars(array $items, string $key, callable $fmt, bool $alt = false, string $caption = ''): string {
    $max = 0.0;
    foreach ($items as $it) $max = max($max, (float)$it[$key]);
    $rows = '';
    $html = '<div class="tp-bars" role="img" aria-label="' . e($caption) . '">';
    foreach ($items as $it) {
        $v = (float)$it[$key];
        $h = $max > 0 ? max(2, round($v / $max * 100)) : 2;
        $cls = 'tp-bar-fill' . ($v <= 0 ? ' zero' : ($alt ? ' alt' : ''));
        $html .= '<div class="tp-bar" title="' . e(($it['long'] ?? $it['label']) . ': ' . $fmt($v)) . '"><div class="' . $cls . '" style="height:' . $h . '%">'
               . ($v > 0 ? '<em>' . e($fmt($v)) . '</em>' : '') . '</div><small>' . e($it['label']) . '</small></div>';
        $rows .= '<tr><td>' . e($it['long'] ?? $it['label']) . '</td><td>' . e($fmt($v)) . '</td></tr>';
    }
    return $html . '</div><table class="tp-sr"><caption>' . e($caption) . '</caption>' . $rows . '</table>';
}
