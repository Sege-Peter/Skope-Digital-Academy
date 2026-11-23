<?php
/**
 * Private iCalendar feed of a learner's schedule: weekly lesson releases and due dates of structured
 * classes, class start dates, assignment deadlines, course access end dates and portal reminders.
 * Subscribe from Google Calendar / Outlook / iPhone with the link in Student > Calendar or any schedule email.
 *   calendar-feed?u=<user id>&t=<token>        (token = HMAC of the user id, see sda_calendar_token)
 */
require_once __DIR__ . '/includes/notify.php';

$uid = (int)($_GET['u'] ?? 0);
$tok = (string)($_GET['t'] ?? '');
if ($uid <= 0 || !hash_equals(sda_calendar_token($uid), $tok)) {
    http_response_code(404);
    exit('Calendar not found.');
}
$name = $pdo->prepare("SELECT name FROM users WHERE id = ? AND status <> 'suspended'");
$name->execute([$uid]);
$who = $name->fetchColumn();
if ($who === false) { http_response_code(404); exit('Calendar not found.'); }

$items = sda_student_schedule($pdo, $uid, strtotime('-60 days'), strtotime('+240 days'));
foreach ($items as &$it) if ($it['type'] === 'lessons' || $it['type'] === 'cohort_start') $it['alarm'] = 60 * 8;
unset($it);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="skope-academy.ics"');
header('Cache-Control: private, max-age=900');
echo sda_ics($items, 'Skope Academy – ' . strtok((string)$who, ' '));
