<?php
/**
 * Background jobs for Skope Digital Academy.
 *
 *   php cron.php           all jobs (reminders, digests, queue, webmail scheduled send/snooze)
 *   php cron.php queue     only send queued emails (started automatically after pages queue mail)
 *
 * Live server: add a cPanel cron job every 15 minutes:
 *   /usr/local/bin/php /home/<account>/public_html/cron.php >/dev/null 2>&1
 * Locally (XAMPP) the site also runs the jobs itself at most every 15 minutes when an admin page loads.
 * Over HTTP it only runs with ?key=<setting cron_key> (shown in Admin > Email Notifications).
 */
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/learning.php';
    $key = setting('cron_key');
    if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) { http_response_code(403); exit('Forbidden'); }
    header('Content-Type: text/plain');
}
@set_time_limit(300);
ignore_user_abort(true);
require_once __DIR__ . '/includes/notify.php';

$mode = PHP_SAPI === 'cli' ? ($argv[1] ?? 'all') : ($_GET['mode'] ?? 'all');

// One worker at a time
$lock = @fopen(__DIR__ . '/includes/private/cron-' . ($mode === 'queue' ? 'queue' : 'all') . '.lock', 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) exit("busy\n");

$log = [];
if ($mode !== 'queue') {
    save_setting($pdo, 'cron_last_run', (string)time());
    foreach (['sda_job_lessons_opening', 'sda_job_cohort_starting', 'sda_job_assignments_due', 'sda_job_access_expiring', 'sda_job_weekly_digest', 'sda_job_inactive_nudge', 'sda_job_server_health'] as $job) {
        try { $log[] = $job . ': ' . $job($pdo); } catch (Throwable $e) { $log[] = "$job FAILED: " . $e->getMessage(); error_log("[cron] $job " . $e->getMessage()); }
    }
    if (file_exists(__DIR__ . '/includes/mailbox.php')) {
        try { require_once __DIR__ . '/includes/mailbox.php'; if (function_exists('sda_mbx_tick')) { sda_mbx_tick($pdo); $log[] = 'webmail tick: ok'; } }
        catch (Throwable $e) { $log[] = 'webmail tick FAILED: ' . $e->getMessage(); }
    }
}
[$s, $f] = sda_mail_queue_process($mode === 'queue' ? 40 : 60);
$log[] = "queue: sent $s, failed $f";
save_setting($pdo, 'cron_last_queue', (string)time());
echo implode("\n", $log), "\n";

/* ── jobs ───────────────────────────────────────────────────────────────── */

/** Active learners on a structured track (one row per enrolment). */
function sda_cron_structured(PDO $pdo): array
{
    return $pdo->query("SELECT DISTINCT student_id FROM enrollments e JOIN users u ON u.id = e.student_id
                        WHERE e.status = 'active' AND e.learning_mode = 'structured' AND u.status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
}

function sda_job_lessons_opening(PDO $pdo): string
{
    $n = 0;
    $from = strtotime('today'); $to = strtotime('tomorrow') - 1;
    foreach (sda_cron_structured($pdo) as $uid) {
        foreach (sda_student_schedule($pdo, (int)$uid, $from, $to) as $it) {
            if ($it['type'] !== 'lessons') continue;
            $list = '<ul>' . implode('', array_map(fn($t) => '<li>' . htmlspecialchars($t) . '</li>', $it['lessons'])) . '</ul>';
            $ics = $it['due'] ? sda_ics([['uid' => $it['uid'] . '-due', 'title' => $it['course'] . ': week ' . $it['week'] . ' due', 'start' => $it['due'], 'all_day' => true, 'url' => $it['url'], 'description' => implode("\n", $it['lessons'])]]) : '';
            if (sda_notify('lessons_opening', (int)$uid, ['course' => $it['course'], 'course_id' => $it['course_id'], 'lessons' => $list,
                'due' => $it['due'] ? date('l, j M', $it['due']) : 'the end of the week'], ['dedupe' => 'lessons:' . $it['uid'], 'ics' => $ics])) $n++;
        }
    }
    return "$n sent";
}

function sda_job_cohort_starting(PDO $pdo): string
{
    $n = 0;
    $rows = $pdo->query("SELECT e.student_id, e.course_id, c.title, cc.id AS cohort_id, cc.name, cc.start_date FROM enrollments e
                         JOIN course_cohorts cc ON cc.id = e.cohort_id JOIN courses c ON c.id = e.course_id
                         WHERE e.status = 'active' AND cc.start_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 3 DAY")->fetchAll();
    foreach ($rows as $r) {
        $start = strtotime($r['start_date']);
        // Calendar invite with every week of the class
        $sched = array_filter(sda_student_schedule($pdo, (int)$r['student_id'], $start - 86400, $start + 200 * 86400), fn($i) => (int)$i['course_id'] === (int)$r['course_id']);
        $ics = sda_ics(array_map(fn($i) => $i + ['alarm' => 60 * 8], array_values($sched)), $r['title']);
        $days = (int)round(($start - strtotime('today')) / 86400);
        if (sda_notify('cohort_starting', (int)$r['student_id'], ['course' => $r['title'], 'course_id' => $r['course_id'], 'cohort' => $r['name'],
            'start' => date('l, j M Y', $start), 'when' => $days <= 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days")],
            ['dedupe' => 'cohort:' . $r['cohort_id'] . ':' . $r['student_id'], 'ics' => $ics])) $n++;
    }
    return "$n sent";
}

function sda_job_assignments_due(PDO $pdo): string
{
    $n = 0;
    $rows = $pdo->query("SELECT a.id, a.title, a.due_date, c.title AS course, e.student_id FROM assignments a
                         JOIN courses c ON c.id = a.course_id
                         JOIN enrollments e ON e.course_id = a.course_id AND e.status = 'active'
                         WHERE a.due_date BETWEEN NOW() AND NOW() + INTERVAL 26 HOUR
                           AND NOT EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = e.student_id)")->fetchAll();
    foreach ($rows as $r) {
        $ts = strtotime($r['due_date']);
        $hours = max(1, (int)round(($ts - time()) / 3600));
        if (sda_notify('assignment_due', (int)$r['student_id'], ['assignment' => $r['title'], 'course' => $r['course'],
            'due' => date('D j M, H:i', $ts), 'when' => $hours <= 24 ? "in $hours hours" : 'tomorrow'],
            ['dedupe' => 'asgdue:' . $r['id'] . ':' . $r['student_id']])) $n++;
    }
    return "$n sent";
}

function sda_job_access_expiring(PDO $pdo): string
{
    $n = 0;
    foreach ([7, 1] as $d) {
        $rows = $pdo->query("SELECT e.id, e.student_id, e.course_id, e.expires_at, e.progress_percent, c.title FROM enrollments e JOIN courses c ON c.id = e.course_id
                             WHERE e.status = 'active' AND e.expires_at BETWEEN NOW() + INTERVAL " . ($d - 1) . " DAY AND NOW() + INTERVAL $d DAY")->fetchAll();
        foreach ($rows as $r) {
            if (sda_notify('access_expiring', (int)$r['student_id'], ['course' => $r['title'], 'course_id' => $r['course_id'],
                'expires' => date('l, j M Y', strtotime($r['expires_at'])), 'when' => $d === 1 ? 'tomorrow' : "in $d days",
                'progress' => (int)$r['progress_percent']], ['dedupe' => "exp$d:" . $r['id'] . ':' . substr($r['expires_at'], 0, 10)])) $n++;
        }
    }
    return "$n sent";
}

function sda_job_weekly_digest(PDO $pdo): string
{
    if (date('N') !== '1' || setting('weekly_digest') === '0') return 'not Monday';
    $n = 0;
    $week = date('o-W');
    $uids = $pdo->query("SELECT DISTINCT e.student_id FROM enrollments e JOIN users u ON u.id = e.student_id WHERE e.status = 'active' AND u.status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($uids as $uid) {
        $s = $pdo->prepare("SELECT COALESCE(SUM(xp),0) xp, SUM(event_type = 'lesson_complete') done FROM learning_events WHERE user_id = ? AND created_at >= NOW() - INTERVAL 7 DAY");
        $s->execute([$uid]);
        $st = $s->fetch();
        $up = sda_student_schedule($pdo, (int)$uid, time(), time() + 7 * 86400);
        if ((int)$st['xp'] === 0 && !$up) continue;   // nothing to say
        $streak = (int)$pdo->query("SELECT streak_days FROM users WHERE id = " . (int)$uid)->fetchColumn();
        $list = $up ? '<ul>' . implode('', array_map(fn($i) => '<li>' . date('D j M', $i['start']) . ' — ' . htmlspecialchars($i['title']) . '</li>', array_slice($up, 0, 8))) . '</ul>' : '<p>Nothing scheduled — a great week to start a new course.</p>';
        if (sda_notify('weekly_digest', (int)$uid, ['xp' => (int)$st['xp'], 'lessons_done' => (int)$st['done'], 'streak' => $streak, 'upcoming' => $list],
            ['dedupe' => "digest:$uid:$week", 'inapp' => false])) $n++;
    }
    return "$n sent";
}

function sda_job_inactive_nudge(PDO $pdo): string
{
    $n = 0;
    $week = date('o-W');
    $rows = $pdo->query("SELECT e.student_id, e.course_id, e.progress_percent, c.title,
                                (SELECT MAX(created_at) FROM learning_events le WHERE le.user_id = e.student_id) AS last_seen
                         FROM enrollments e JOIN courses c ON c.id = e.course_id
                         WHERE e.status = 'active' AND e.progress_percent < 100 AND e.enrolled_at < NOW() - INTERVAL 7 DAY")->fetchAll();
    $done = [];
    foreach ($rows as $r) {
        if (isset($done[$r['student_id']]) || !$r['last_seen']) continue;
        $idle = (time() - strtotime($r['last_seen'])) / 86400;
        if ($idle < 7 || $idle > 30) continue;
        $done[$r['student_id']] = true;
        if (sda_notify('inactive_nudge', (int)$r['student_id'], ['course' => $r['title'], 'course_id' => $r['course_id'], 'progress' => (int)$r['progress_percent']],
            ['dedupe' => 'nudge:' . $r['student_id'] . ':' . $week, 'inapp' => false])) $n++;
    }
    return "$n sent";
}

/** Server health: alert the admins (at most once a day) when disk space runs low or a backup is overdue. */
function sda_job_server_health(PDO $pdo): string
{
    $issues = [];
    $free = @disk_free_space(__DIR__); $total = @disk_total_space(__DIR__);
    if ($free && $total && $free / $total < 0.10) $issues[] = 'Disk space is low: ' . round($free / 1073741824, 1) . ' GB free of ' . round($total / 1073741824, 1) . ' GB.';
    $last = (int)setting('backup_last_ok');
    if (PHP_OS_FAMILY === 'Linux' && $last > 0 && time() - $last > 36 * 3600) $issues[] = 'The daily database backup has not succeeded since ' . date('j M Y, H:i', $last) . '.';
    if (!$issues) return 'ok';
    if (setting('health_alert_day') === date('Y-m-d')) return 'issues (already alerted today)';
    require_once __DIR__ . '/includes/security.php';
    sda_security_alert('Server needs attention', implode('<br>', array_map('htmlspecialchars', $issues)));
    save_setting($pdo, 'health_alert_day', date('Y-m-d'));
    return 'alerted: ' . implode(' ', $issues);
}
