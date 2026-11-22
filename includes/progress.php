<?php
/**
 * Learning engine – Skope Digital Academy
 *
 * Every learning action goes through track_*() functions here. Each action is an
 * idempotent event (learning_events, unique per user + event_key) worth some XP.
 * After an event we re-check badges, course completion, learning paths and
 * certificates, so learners get credit automatically – nothing needs a button.
 *
 *   XP          → users.xp (sum of their events), levels from xp_level()
 *   Badges      → badges (kind: skill | achievement | path | manual) + student_badges
 *   Certificates→ certificates (auto-issued, verification_code, public /certificate?code=)
 *   Leaderboard → course_leaderboard(): XP earned in a course by everyone enrolled in it
 */

require_once __DIR__ . '/learning.php';

const XP_RULES = [
    'lesson_view'     => 2,
    'lesson_complete' => 10,
    'exercise_pass'   => 5,
    'quiz_pass'       => 25,
    'quiz_perfect'    => 15,
    'course_complete' => 100,
    'path_complete'   => 250,
    'daily_streak'    => 5,
    'review'          => 10,
];
const QUIZ_PASS_PCT = 70;

/* ── Levels ───────────────────────────────────────────────────────────── */

/** XP needed to *reach* level n (1-based): 0, 100, 300, 600, 1000, … */
function xp_for_level(int $n): int { return (int)(50 * ($n - 1) * $n); }

function xp_level(int $xp): array {
    $level = 1;
    while (xp_for_level($level + 1) <= $xp) $level++;
    $titles = [1 => 'Newcomer', 2 => 'Explorer', 3 => 'Learner', 4 => 'Builder', 5 => 'Coder', 6 => 'Achiever',
               8 => 'Specialist', 10 => 'Expert', 13 => 'Master', 16 => 'Legend'];
    $title = 'Newcomer';
    foreach ($titles as $min => $t) if ($level >= $min) $title = $t;
    $from = xp_for_level($level);
    $to = xp_for_level($level + 1);
    return ['level' => $level, 'title' => $title, 'xp' => $xp, 'from' => $from, 'to' => $to,
            'pct' => $to > $from ? (int)floor(($xp - $from) / ($to - $from) * 100) : 100];
}

/* ── Events ───────────────────────────────────────────────────────────── */

/** Record an event once. Returns XP gained (0 if this event was already recorded). */
function record_event(PDO $pdo, int $uid, string $type, string $key, int $xp, ?int $courseId = null, ?int $lessonId = null, ?string $meta = null): int {
    $st = $pdo->prepare("INSERT IGNORE INTO learning_events (user_id, course_id, lesson_id, event_type, event_key, xp, meta) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $st->execute([$uid, $courseId, $lessonId, $type, mb_substr($key, 0, 190), $xp, $meta !== null ? mb_substr($meta, 0, 255) : null]);
    if ($st->rowCount() === 0) return 0;
    if ($xp) $pdo->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$xp, $uid]);
    return $xp;
}

/** Daily activity streak (also XP once per day). */
function touch_streak(PDO $pdo, int $uid): int {
    $u = $pdo->prepare("SELECT streak_days, last_streak_date, best_streak FROM users WHERE id = ?");
    $u->execute([$uid]);
    $row = $u->fetch();
    if (!$row) return 0;
    $today = date('Y-m-d');
    if ($row['last_streak_date'] !== $today) {
        $streak = ($row['last_streak_date'] === date('Y-m-d', strtotime('-1 day'))) ? (int)$row['streak_days'] + 1 : 1;
        $pdo->prepare("UPDATE users SET streak_days = ?, last_streak_date = ?, best_streak = GREATEST(best_streak, ?) WHERE id = ?")
            ->execute([$streak, $today, $streak, $uid]);
    }
    return record_event($pdo, $uid, 'daily_streak', 'day:' . $today, XP_RULES['daily_streak']);
}

/* ── Tracking entry points (called from api/track and complete_lesson) ── */

/** Result object handed back to the browser. */
function progress_result(): array { return ['xp' => 0, 'badges' => [], 'certificate' => null, 'completed' => false, 'progress' => null]; }

function merge_result(array &$r, array $more): void {
    $r['xp'] += $more['xp'] ?? 0;
    $r['badges'] = array_merge($r['badges'], $more['badges'] ?? []);
    if (!empty($more['certificate'])) $r['certificate'] = $more['certificate'];
    if (!empty($more['completed'])) $r['completed'] = true;
    if (isset($more['progress']) && $more['progress'] !== null) $r['progress'] = $more['progress'];
}

/** Seconds spent on a lesson page (heartbeat). Capped per call to stop abuse. */
function track_time(PDO $pdo, int $uid, array $lesson, int $secs): void {
    $secs = max(0, min(90, $secs));
    if (!$secs) return;
    $pdo->prepare("INSERT INTO lesson_progress (student_id, lesson_id, course_id, status, time_spent_secs, time_spent_mins, last_seen_at)
                   VALUES (?, ?, ?, 'in_progress', ?, 0, NOW())
                   ON DUPLICATE KEY UPDATE time_spent_secs = time_spent_secs + VALUES(time_spent_secs),
                                           time_spent_mins = FLOOR((time_spent_secs + VALUES(time_spent_secs)) / 60),
                                           last_seen_at = NOW(),
                                           status = IF(status = 'not_started', 'in_progress', status)")
        ->execute([$uid, $lesson['id'], $lesson['course_id'], $secs]);
}

function track_view(PDO $pdo, int $uid, array $lesson): array {
    $r = progress_result();
    $r['xp'] += touch_streak($pdo, $uid);
    $r['xp'] += record_event($pdo, $uid, 'lesson_view', 'view:' . $lesson['id'], XP_RULES['lesson_view'], (int)$lesson['course_id'], (int)$lesson['id']);
    merge_result($r, ['badges' => check_badges($pdo, $uid)]);
    return $r;
}

/** Lesson finished (auto when read to the end, or the "Mark complete" button). */
function track_lesson_complete(PDO $pdo, int $uid, array $lesson): array {
    $r = progress_result();
    $r['xp'] += record_event($pdo, $uid, 'lesson_complete', 'lesson:' . $lesson['id'], XP_RULES['lesson_complete'], (int)$lesson['course_id'], (int)$lesson['id']);
    merge_result($r, check_course_completion($pdo, $uid, (int)$lesson['course_id']));
    merge_result($r, ['badges' => check_badges($pdo, $uid, (int)$lesson['course_id'])]);
    return $r;
}

function track_exercise(PDO $pdo, int $uid, array $lesson, int $n): array {
    $r = progress_result();
    $r['xp'] += record_event($pdo, $uid, 'exercise_pass', 'exercise:' . $lesson['id'] . ':' . $n, XP_RULES['exercise_pass'], (int)$lesson['course_id'], (int)$lesson['id']);
    merge_result($r, ['badges' => check_badges($pdo, $uid)]);
    return $r;
}

function track_quiz(PDO $pdo, int $uid, array $lesson, int $n, int $score, int $total): array {
    $r = progress_result();
    if ($total <= 0) return $r;
    $pct = (int)round($score / $total * 100);
    $courseId = (int)$lesson['course_id'];
    // Every attempt is kept (for the best-score rule); XP only once per quiz.
    record_event($pdo, $uid, 'quiz_attempt', 'quizattempt:' . $lesson['id'] . ':' . $n . ':' . microtime(true), 0, $courseId, (int)$lesson['id'], (string)$pct);
    if ($pct >= QUIZ_PASS_PCT) {
        $r['xp'] += record_event($pdo, $uid, 'quiz_pass', 'quiz:' . $lesson['id'] . ':' . $n, XP_RULES['quiz_pass'], $courseId, (int)$lesson['id'], (string)$pct);
    }
    if ($pct === 100) {
        $r['xp'] += record_event($pdo, $uid, 'quiz_perfect', 'quizperfect:' . $lesson['id'] . ':' . $n, XP_RULES['quiz_perfect'], $courseId, (int)$lesson['id']);
    }
    merge_result($r, check_course_completion($pdo, $uid, $courseId));
    merge_result($r, ['badges' => check_badges($pdo, $uid, $courseId)]);
    return $r;
}

/* ── Course completion & certificates ─────────────────────────────────── */

/** Does the course have an end-of-course quiz (a ```quiz block in a lesson)? */
function course_has_quiz(PDO $pdo, int $courseId): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ? AND content LIKE '%```quiz%'");
    $st->execute([$courseId]);
    return (int)$st->fetchColumn() > 0;
}

function best_quiz_pct(PDO $pdo, int $uid, int $courseId): ?int {
    $st = $pdo->prepare("SELECT MAX(CAST(meta AS UNSIGNED)) FROM learning_events WHERE user_id = ? AND course_id = ? AND event_type IN ('quiz_attempt','quiz_pass')");
    $st->execute([$uid, $courseId]);
    $v = $st->fetchColumn();
    return $v === null ? null : (int)$v;
}

/** Completed = every lesson done (+ final quiz ≥ 70% when the course has one). Issues the certificate. */
function check_course_completion(PDO $pdo, int $uid, int $courseId): array {
    $r = progress_result();
    $total = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
    $total->execute([$courseId]);
    $n = (int)$total->fetchColumn();
    if (!$n) return $r;
    $done = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id
                           WHERE lp.student_id = ? AND l.course_id = ? AND lp.status = 'completed'");
    $done->execute([$uid, $courseId]);
    $d = (int)$done->fetchColumn();
    $pct = round(min(100, $d / $n * 100), 2);
    $pdo->prepare("UPDATE enrollments SET progress_percent = ? WHERE student_id = ? AND course_id = ?")->execute([$pct, $uid, $courseId]);
    $r['progress'] = $pct;
    if ($d < $n) return $r;
    $quizPct = null;
    if (course_has_quiz($pdo, $courseId)) {
        $quizPct = best_quiz_pct($pdo, $uid, $courseId);
        if ($quizPct === null || $quizPct < QUIZ_PASS_PCT) return $r;   // finish the quiz to complete
    }
    $pdo->prepare("UPDATE enrollments SET status = 'completed', completed_at = COALESCE(completed_at, NOW()) WHERE student_id = ? AND course_id = ? AND status IN ('active','completed')")
        ->execute([$uid, $courseId]);
    $r['xp'] += record_event($pdo, $uid, 'course_complete', 'course:' . $courseId, XP_RULES['course_complete'], $courseId);
    $r['completed'] = true;
    $r['certificate'] = issue_certificate($pdo, $uid, $courseId, null, $quizPct);
    merge_result($r, check_paths($pdo, $uid));
    return $r;
}

function new_cert_code(PDO $pdo): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $c = '';
        for ($i = 0; $i < 10; $i++) $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $code = 'SDA-' . date('y') . '-' . substr($c, 0, 5) . '-' . substr($c, 5);
        $st = $pdo->prepare("SELECT 1 FROM certificates WHERE verification_code = ?");
        $st->execute([$code]);
    } while ($st->fetchColumn());
    return $code;
}

/** Issue (once) and return ['id','code','title']. Course certificate if $courseId, else path certificate. */
function issue_certificate(PDO $pdo, int $uid, ?int $courseId, ?int $pathId = null, ?int $score = null): ?array {
    if ($courseId) {
        $st = $pdo->prepare("SELECT c.id, c.verification_code, c.status, c.fee, co.title FROM certificates c JOIN courses co ON co.id = c.course_id WHERE c.student_id = ? AND c.course_id = ? AND c.certificate_type = 'course' AND c.status <> 'revoked' LIMIT 1");
        $st->execute([$uid, $courseId]);
    } else {
        $st = $pdo->prepare("SELECT c.id, c.verification_code, c.status, c.fee, p.title FROM certificates c JOIN learning_paths p ON p.id = c.path_id WHERE c.student_id = ? AND c.path_id = ? AND c.certificate_type = 'path' AND c.status <> 'revoked' LIMIT 1");
        $st->execute([$uid, $pathId]);
    }
    if ($row = $st->fetch()) return ['id' => (int)$row['id'], 'code' => $row['verification_code'], 'title' => $row['title'], 'new' => false,
                                     'locked' => $row['status'] === 'pending', 'fee' => (float)$row['fee'], 'fee_label' => $row['status'] === 'pending' ? money((float)$row['fee']) : null];

    // Course certificates may carry a fee (set by the admin): they are generated now but stay
    // locked ('pending') until paid. Path certificates are a free reward.
    $fee = 0.0;
    if ($courseId) {
        $cr = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $cr->execute([$courseId]);
        $fee = certificate_fee_for($cr->fetch() ?: []);
    }
    $status = $fee > 0 ? 'pending' : 'approved';
    $code = new_cert_code($pdo);
    $pdo->prepare("INSERT INTO certificates (student_id, course_id, path_id, certificate_type, verification_code, issued_at, issued_by_role, notes, status, score, fee, paid_at)
                   VALUES (?, ?, ?, ?, ?, NOW(), 'system', ?, ?, ?, ?, ?)")
        ->execute([$uid, $courseId, $pathId, $courseId ? 'course' : 'path', $code,
                   $fee > 0 ? 'Generated on completion; unlocks when the certificate fee is paid' : 'Issued automatically on completion',
                   $status, $score, $fee, null]);
    $id = (int)$pdo->lastInsertId();
    if ($courseId) {
        $t = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
        $t->execute([$courseId]);
        $title = (string)$t->fetchColumn();
        try {
            $pdo->prepare("INSERT INTO transcript_entries (student_id, course_id, entry_type, title, score, max_score, credits, notes) VALUES (?, ?, 'course_completion', ?, ?, 100, 1.0, ?)")
                ->execute([$uid, $courseId, $title, $score, 'Certificate ' . $code]);
        } catch (Exception $e) { error_log($e->getMessage()); }
    } else {
        $t = $pdo->prepare("SELECT title FROM learning_paths WHERE id = ?");
        $t->execute([$pathId]);
        $title = (string)$t->fetchColumn();
    }
    if ($fee > 0) {
        notify($pdo, $uid, 'Certificate ready to unlock 🎓', "You completed \"$title\"! Unlock your certificate for " . money($fee) . " from My Achievements or the course page.");
    } else {
        notify($pdo, $uid, 'Certificate earned 🎓', "Your certificate for \"$title\" is ready. Verification code: $code");
    }
    return ['id' => $id, 'code' => $code, 'title' => $title, 'new' => true, 'locked' => $fee > 0, 'fee' => $fee, 'fee_label' => $fee > 0 ? money($fee) : null];
}

/** Unlock a paid certificate once its fee is covered. Returns true if it was unlocked now. */
function unlock_certificate(PDO $pdo, int $certId, float $amountPaid): bool {
    $st = $pdo->prepare("SELECT c.*, COALESCE(co.title, p.title) AS title FROM certificates c LEFT JOIN courses co ON co.id = c.course_id LEFT JOIN learning_paths p ON p.id = c.path_id WHERE c.id = ?");
    $st->execute([$certId]);
    $c = $st->fetch();
    if (!$c || $c['status'] !== 'pending' || $amountPaid + 0.001 < (float)$c['fee']) return false;
    $pdo->prepare("UPDATE certificates SET status = 'approved', paid_at = NOW() WHERE id = ? AND status = 'pending'")->execute([$certId]);
    notify($pdo, (int)$c['student_id'], 'Certificate unlocked 🎉', "Your certificate for \"{$c['title']}\" is unlocked. Download and share it any time. Code: {$c['verification_code']}");
    return true;
}

/** Queue a result (XP, badges, certificate) to celebrate on the next page load. */
function progress_flash_add(array $res): void {
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $f = $_SESSION['progress_flash'] ?? progress_result();
    merge_result($f, $res);
    $_SESSION['progress_flash'] = $f;
}
function progress_flash_take(): ?array {
    $f = $_SESSION['progress_flash'] ?? null;
    unset($_SESSION['progress_flash']);
    return ($f && ($f['xp'] || $f['badges'] || $f['certificate'])) ? $f : null;
}

function notify(PDO $pdo, int $uid, string $title, string $msg): void {
    try {
        $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, 'student', ?)")->execute([$title, $msg, $uid]);
    } catch (Exception $e) { error_log($e->getMessage()); }
}

/* ── Learning paths ───────────────────────────────────────────────────── */

function learning_paths(PDO $pdo, ?int $uid = null): array {
    $paths = $pdo->query("SELECT * FROM learning_paths ORDER BY sort_order, id")->fetchAll();
    foreach ($paths as &$p) {
        $st = $pdo->prepare("SELECT c.id, c.slug, c.title, c.tutorial_short, c.total_lessons, lpc.position
                             FROM learning_path_courses lpc JOIN courses c ON c.slug = lpc.course_slug AND c.status = 'published'
                             WHERE lpc.path_id = ? ORDER BY lpc.position");
        $st->execute([$p['id']]);
        $p['courses'] = $st->fetchAll();
        $p['done'] = 0;
        if ($uid) {
            foreach ($p['courses'] as &$c) {
                $e = $pdo->prepare("SELECT status, progress_percent FROM enrollments WHERE student_id = ? AND course_id = ?");
                $e->execute([$uid, $c['id']]);
                $row = $e->fetch();
                $c['progress'] = $row ? (float)$row['progress_percent'] : 0;
                $c['completed'] = $row && $row['status'] === 'completed';
                if ($c['completed']) $p['done']++;
            }
            unset($c);
        }
        $p['pct'] = $p['courses'] ? (int)round($p['done'] / count($p['courses']) * 100) : 0;
    }
    unset($p);
    return $paths;
}

function check_paths(PDO $pdo, int $uid): array {
    $r = progress_result();
    foreach (learning_paths($pdo, $uid) as $p) {
        if (!$p['courses'] || $p['done'] < count($p['courses'])) continue;
        $r['xp'] += record_event($pdo, $uid, 'path_complete', 'path:' . $p['id'], XP_RULES['path_complete']);
        issue_certificate($pdo, $uid, null, (int)$p['id']);
    }
    return $r;
}

/* ── Badges ───────────────────────────────────────────────────────────── */

/** Achievement badges: code => [name, description, icon, color, rule, threshold, xp]. */
const ACHIEVEMENTS = [
    'first-steps'    => ['First Steps',     'Completed your first lesson.',              'fa-shoe-prints',     '#0ea5e9', 'lessons', 1, 10],
    'bookworm'       => ['Bookworm',        'Completed 10 lessons.',                     'fa-book-open',       '#6366f1', 'lessons', 10, 25],
    'scholar'        => ['Scholar',         'Completed 50 lessons.',                     'fa-graduation-cap',  '#8b5cf6', 'lessons', 50, 75],
    'centurion'      => ['Centurion',       'Completed 100 lessons.',                    'fa-landmark',        '#a855f7', 'lessons', 100, 150],
    'hands-on'       => ['Hands-on',        'Solved 10 exercises.',                      'fa-keyboard',        '#10b981', 'exercises', 10, 25],
    'problem-solver' => ['Problem Solver',  'Solved 50 exercises.',                      'fa-puzzle-piece',    '#059669', 'exercises', 50, 75],
    'quiz-whiz'      => ['Quiz Whiz',       'Passed your first quiz.',                   'fa-circle-check',    '#f59e0b', 'quizzes', 1, 15],
    'perfectionist'  => ['Perfectionist',   'Scored 100% on a quiz.',                    'fa-bullseye',        '#ef4444', 'perfect', 1, 30],
    'on-fire'        => ['On Fire',         'Learned 3 days in a row.',                  'fa-fire',            '#f97316', 'streak', 3, 15],
    'unstoppable'    => ['Unstoppable',     'Learned 7 days in a row.',                  'fa-fire-flame-curved', '#ea580c', 'streak', 7, 50],
    'dedicated'      => ['Dedicated',       'Learned 30 days in a row.',                 'fa-calendar-check',  '#c2410c', 'streak', 30, 200],
    'graduate'       => ['Graduate',        'Completed your first course.',              'fa-award',           '#0080ff', 'courses', 1, 50],
    'polymath'       => ['Polymath',        'Completed 3 courses.',                      'fa-layer-group',     '#0369a1', 'courses', 3, 100],
    'rising-star'    => ['Rising Star',     'Earned 1,000 XP.',                          'fa-star',            '#eab308', 'xp', 1000, 0],
    'superstar'      => ['Superstar',       'Earned 5,000 XP.',                          'fa-meteor',          '#ca8a04', 'xp', 5000, 0],
    'critic'         => ['Helpful Critic',  'Reviewed a course you took.',               'fa-comment-dots',    '#14b8a6', 'reviews', 1, 10],
];

function user_stats(PDO $pdo, int $uid): array {
    $q = function (string $sql) use ($pdo, $uid): int { $s = $pdo->prepare($sql); $s->execute([$uid]); return (int)$s->fetchColumn(); };
    return [
        'lessons'   => $q("SELECT COUNT(*) FROM lesson_progress WHERE student_id = ? AND status = 'completed'"),
        'exercises' => $q("SELECT COUNT(*) FROM learning_events WHERE user_id = ? AND event_type = 'exercise_pass'"),
        'quizzes'   => $q("SELECT COUNT(*) FROM learning_events WHERE user_id = ? AND event_type = 'quiz_pass'"),
        'perfect'   => $q("SELECT COUNT(*) FROM learning_events WHERE user_id = ? AND event_type = 'quiz_perfect'"),
        'streak'    => max($q("SELECT streak_days FROM users WHERE id = ?"), $q("SELECT best_streak FROM users WHERE id = ?")),
        'courses'   => $q("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'completed'"),
        'xp'        => $q("SELECT xp FROM users WHERE id = ?"),
        'reviews'   => $q("SELECT COUNT(*) FROM course_ratings WHERE student_id = ?"),
        'seconds'   => $q("SELECT COALESCE(SUM(time_spent_secs),0) FROM lesson_progress WHERE student_id = ?"),
    ];
}

/** Make sure seeded badges exist (achievements; a skill badge per tutorial/course; a badge per path). */
function ensure_badges(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $ins = $pdo->prepare("INSERT INTO badges (code, name, description, icon, color, kind, criteria_type, criteria_value, xp_reward, course_id, path_id, sort_order)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), icon = VALUES(icon), color = VALUES(color),
                                                  criteria_value = VALUES(criteria_value), xp_reward = VALUES(xp_reward), sort_order = VALUES(sort_order)");
    $i = 0;
    foreach (ACHIEVEMENTS as $code => [$name, $desc, $icon, $color, $rule, $n, $xp]) {
        $ins->execute([$code, $name, $desc, $icon, $color, 'achievement', $rule, $n, $xp, null, null, ++$i]);
    }
    $palette = ['html' => '#e34c26', 'css' => '#2965f1', 'javascript' => '#d4a900', 'python' => '#3776ab', 'sql' => '#00758f', 'php' => '#777bb3'];
    foreach ($pdo->query("SELECT id, slug, title, tutorial_short FROM courses WHERE status = 'published'")->fetchAll() as $c) {
        $short = $c['tutorial_short'] ?: $c['title'];
        $ins->execute(['skill-course-' . $c['id'], $short . ' Skill Badge', 'Completed every lesson' . (course_has_quiz($pdo, (int)$c['id']) ? ' and passed the final quiz' : '') . ' of ' . $c['title'] . '.',
                       'fa-certificate', $palette[$c['slug']] ?? '#0080ff', 'skill', 'course', 1, 0, $c['id'], null, 100 + (int)$c['id']]);
    }
    foreach ($pdo->query("SELECT * FROM learning_paths")->fetchAll() as $p) {
        $ins->execute(['path-' . $p['slug'], $p['title'], 'Completed every course in the ' . $p['title'] . ' learning path.', $p['icon'], $p['color'], 'path', 'path', 1, 0, null, $p['id'], 200 + (int)$p['sort_order']]);
    }
}

/** Award any badges the user now qualifies for. Returns the newly earned badges. */
function check_badges(PDO $pdo, int $uid, ?int $courseId = null): array {
    ensure_badges($pdo);
    $have = $pdo->prepare("SELECT badge_id FROM student_badges WHERE student_id = ?");
    $have->execute([$uid]);
    $owned = array_flip($have->fetchAll(PDO::FETCH_COLUMN));
    $stats = user_stats($pdo, $uid);
    $earned = [];
    $candidates = $pdo->query("SELECT * FROM badges WHERE code IS NOT NULL")->fetchAll();
    $completedCourses = null;
    $paths = null;
    foreach ($candidates as $b) {
        if (isset($owned[$b['id']])) continue;
        $ok = false;
        if ($b['kind'] === 'achievement') {
            $ok = ($stats[$b['criteria_type']] ?? 0) >= (int)$b['criteria_value'];
        } elseif ($b['kind'] === 'skill' && $b['course_id']) {
            if ($completedCourses === null) {
                $s = $pdo->prepare("SELECT course_id FROM enrollments WHERE student_id = ? AND status = 'completed'");
                $s->execute([$uid]);
                $completedCourses = array_flip($s->fetchAll(PDO::FETCH_COLUMN));
            }
            $ok = isset($completedCourses[$b['course_id']]);
        } elseif ($b['kind'] === 'path' && $b['path_id']) {
            $paths ??= learning_paths($pdo, $uid);
            foreach ($paths as $p) if ((int)$p['id'] === (int)$b['path_id'] && $p['courses'] && $p['done'] === count($p['courses'])) $ok = true;
        }
        if (!$ok) continue;
        $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id, awarded_by_role, notes) VALUES (?, ?, 'system', 'Earned automatically')")->execute([$uid, $b['id']]);
        if ($b['xp_reward']) record_event($pdo, $uid, 'badge', 'badge:' . $b['id'], (int)$b['xp_reward'], $b['course_id'] ? (int)$b['course_id'] : null);
        notify($pdo, $uid, 'New badge: ' . $b['name'], $b['description']);
        $earned[] = ['name' => $b['name'], 'description' => $b['description'], 'icon' => $b['icon'], 'color' => $b['color'], 'kind' => $b['kind']];
    }
    return $earned;
}

/* ── Leaderboard ──────────────────────────────────────────────────────── */

/** Everyone enrolled in a course, ranked by XP earned in that course (optionally in the last 7 days). */
function course_leaderboard(PDO $pdo, int $courseId, string $period = 'all', int $limit = 50): array {
    $since = $period === 'week' ? "AND le.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" : '';
    $st = $pdo->prepare("SELECT u.id, u.name, u.avatar, e.progress_percent, e.status, e.enrolled_at,
                                COALESCE((SELECT SUM(le.xp) FROM learning_events le WHERE le.user_id = u.id AND le.course_id = e.course_id $since), 0) AS course_xp,
                                (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id WHERE lp.student_id = u.id AND l.course_id = e.course_id AND lp.status = 'completed') AS lessons_done,
                                (SELECT MAX(le.created_at) FROM learning_events le WHERE le.user_id = u.id AND le.course_id = e.course_id) AS last_active
                         FROM enrollments e JOIN users u ON u.id = e.student_id
                         WHERE e.course_id = ? AND e.status IN ('active','completed') AND u.role = 'student' AND u.show_on_leaderboard = 1
                         ORDER BY course_xp DESC, e.progress_percent DESC, last_active ASC
                         LIMIT " . (int)$limit);
    $st->execute([$courseId]);
    $rows = $st->fetchAll();
    foreach ($rows as $i => &$r) {
        $r['rank'] = $i + 1;
        $r['display_name'] = leaderboard_name($r['name']);
    }
    unset($r);
    return $rows;
}

/** "Amina Otieno" → "Amina O." (public boards show first name + initial only). */
function leaderboard_name(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: ['Learner'];
    return $parts[0] . (isset($parts[1]) ? ' ' . mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1)) . '.' : '');
}

function user_rank_in_course(PDO $pdo, int $courseId, int $uid): ?array {
    foreach (course_leaderboard($pdo, $courseId, 'all', 100000) as $r) if ((int)$r['id'] === $uid) return $r;
    return null;
}

/* ── Seed data (called by migrate_learning_platform.php) ──────────────── */

function seed_learning_engine(PDO $pdo): void {
    $paths = [
        ['front-end-developer', 'Front-End Web Developer', 'Build and style interactive websites: structure with HTML, design with CSS, behaviour with JavaScript.', 'fa-laptop-code', '#0080ff', 1, ['html', 'css', 'javascript']],
        ['data-analyst', 'Data Analyst', 'Collect, query and analyse data with Python and SQL.', 'fa-chart-line', '#10b981', 2, ['python', 'sql']],
        ['back-end-developer', 'Back-End Web Developer', 'Power websites on the server with PHP and databases with SQL, on top of solid HTML.', 'fa-server', '#8b5cf6', 3, ['html', 'php', 'sql']],
    ];
    foreach ($paths as [$slug, $title, $desc, $icon, $color, $order, $courses]) {
        $pdo->prepare("INSERT INTO learning_paths (slug, title, description, icon, color, sort_order) VALUES (?, ?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), icon = VALUES(icon), color = VALUES(color), sort_order = VALUES(sort_order)")
            ->execute([$slug, $title, $desc, $icon, $color, $order]);
        $id = $pdo->prepare("SELECT id FROM learning_paths WHERE slug = ?");
        $id->execute([$slug]);
        $pid = (int)$id->fetchColumn();
        foreach ($courses as $pos => $c) {
            $pdo->prepare("INSERT INTO learning_path_courses (path_id, course_slug, position) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE position = VALUES(position)")
                ->execute([$pid, $c, $pos + 1]);
        }
    }
    ensure_badges($pdo);
    // Backfill XP for progress made before the engine existed (idempotent: events are unique).
    foreach ($pdo->query("SELECT student_id, lesson_id, course_id FROM lesson_progress WHERE status = 'completed'")->fetchAll() as $p) {
        record_event($pdo, (int)$p['student_id'], 'lesson_complete', 'lesson:' . $p['lesson_id'], XP_RULES['lesson_complete'], (int)$p['course_id'], (int)$p['lesson_id']);
    }
    foreach ($pdo->query("SELECT DISTINCT student_id FROM enrollments")->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        check_badges($pdo, (int)$uid);
    }
}
