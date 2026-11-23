<?php
/**
 * Admin: Analytics – traffic (anonymous page views), searches (incl. ones with no matching course),
 * course performance, audience and learner behaviour. Data: includes/site_stats.php + the learning tables.
 */
$pageTitle = 'Analytics';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/site_stats.php';
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: staff-home'); exit; }
sda_stats_ensure($pdo);

$ranges = [7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'];
$days = array_key_exists((int)($_GET['range'] ?? 30), $ranges) ? (int)($_GET['range'] ?? 30) : 30;
$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
$prevFrom = date('Y-m-d', strtotime('-' . (2 * $days - 1) . ' days'));
$prevTo = date('Y-m-d', strtotime('-' . $days . ' days'));

$q = function (string $sql, array $p = []) use ($pdo) { try { $st = $pdo->prepare($sql); $st->execute($p); return $st; } catch (Throwable $e) { error_log('[analytics] ' . $e->getMessage()); return null; } };
$one = fn(string $sql, array $p = []) => ($st = $q($sql, $p)) ? $st->fetchColumn() : 0;
$all = fn(string $sql, array $p = []) => ($st = $q($sql, $p)) ? $st->fetchAll() : [];
$pairs = fn(string $sql, array $p = []) => ($st = $q($sql, $p)) ? $st->fetchAll(PDO::FETCH_KEY_PAIR) : [];

/* ── Headline numbers (this period vs the one before) ── */
$kp = function (string $a, string $b) use ($one) {
    return [
        'views'   => (int)$one("SELECT COUNT(*) FROM site_visits WHERE day BETWEEN ? AND ?", [$a, $b]),
        'visits'  => (int)$one("SELECT COUNT(DISTINCT day, visitor) FROM site_visits WHERE day BETWEEN ? AND ?", [$a, $b]),
        'signups' => (int)$one("SELECT COUNT(*) FROM users WHERE role = 'student' AND DATE(created_at) BETWEEN ? AND ?", [$a, $b]),
        'enrol'   => (int)$one("SELECT COUNT(*) FROM enrollments WHERE DATE(enrolled_at) BETWEEN ? AND ?", [$a, $b]),
        'revenue' => (float)$one("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified' AND DATE(created_at) BETWEEN ? AND ?", [$a, $b]),
        'searches'=> (int)$one("SELECT COUNT(*) FROM search_log WHERE day BETWEEN ? AND ?", [$a, $b]),
    ];
};
$today = date('Y-m-d');
$K = $kp($from, $today);
$P = $kp($prevFrom, $prevTo);
$since0 = $one("SELECT MIN(day) FROM site_visits");
// Only meaningful once visit tracking covers the whole period (sign-ups go back further than tracking)
$conv = $K['visits'] && $since0 && $since0 <= $from ? $K['signups'] / $K['visits'] * 100 : null;
$delta = function (string $k) use ($K, $P) {
    if (!$P[$k]) return $K[$k] ? '<span class="an-d up">new</span>' : '<span class="an-d">—</span>';
    $d = ($K[$k] - $P[$k]) / $P[$k] * 100;
    return '<span class="an-d ' . ($d >= 0 ? 'up' : 'down') . '"><i class="fas fa-arrow-' . ($d >= 0 ? 'up' : 'down') . '"></i> ' . abs(round($d)) . '% <small>vs previous ' . ($d >= 0 ? '' : '') . '</small></span>';
};
$tracking = (int)$one("SELECT COUNT(*) FROM site_visits") > 0;
$since = $one("SELECT MIN(day) FROM site_visits");

/* ── Traffic over time ── */
$byDay = [];
foreach ($all("SELECT day, COUNT(*) v, COUNT(DISTINCT visitor) u FROM site_visits WHERE day >= ? GROUP BY day", [$from]) as $r) $byDay[$r['day']] = [(int)$r['u'], (int)$r['v']];
$series = ['labels' => [], 'visits' => [], 'views' => []];
for ($t = strtotime($from); $t <= strtotime($today); $t += 86400) {
    $d = date('Y-m-d', $t);
    $series['labels'][] = date($days > 90 ? 'j M' : 'D j M', $t);
    $series['visits'][] = $byDay[$d][0] ?? 0;
    $series['views'][] = $byDay[$d][1] ?? 0;
}

$pageNames = ['home' => 'Home', 'courses' => 'Courses', 'course-details' => 'Course pages', 'learn' => 'Free tutorials', 'about' => 'About', 'contact' => 'Contact', 'scholarships' => 'Scholarships',
    'donate' => 'Support us', 'careers' => 'Careers', 'careers-apply' => 'Job applications', 'careers-guide' => 'Applicant guide', 'careers-practice' => 'Practice test', 'register' => 'Sign-up',
    'login' => 'Sign-in', 'checkout' => 'Checkout', 'tryit' => 'Try-it editor', 'student' => 'Student dashboard', 'tutor' => 'Tutor dashboard', 'verify' => 'Certificate check', 'certificate' => 'Certificates'];
$pageLabel = fn($p) => $pageNames[$p] ?? (str_starts_with($p, 'student/') ? 'Student: ' . ucwords(str_replace(['student/', '-', '_'], ['', ' ', ' '], $p)) : (str_starts_with($p, 'tutor/') ? 'Tutor: ' . ucwords(str_replace(['tutor/', '-', '_'], ['', ' ', ' '], $p)) : (str_starts_with($p, 'learn/') ? 'Tutorial: ' . ucwords(str_replace(['learn/', '-', '/'], ['', ' ', ' › '], $p)) : ucwords(str_replace(['-', '_', '/'], [' ', ' ', ' › '], $p)))));
$topPages = $all("SELECT path, COUNT(*) v, COUNT(DISTINCT day, visitor) u FROM site_visits WHERE day >= ? GROUP BY path ORDER BY v DESC LIMIT 12", [$from]);
$sources = $all("SELECT source k, COUNT(DISTINCT day, visitor) n FROM site_visits WHERE day >= ? GROUP BY source ORDER BY n DESC", [$from]);
$refs = $all("SELECT ref_host k, COUNT(DISTINCT day, visitor) n FROM site_visits WHERE day >= ? AND ref_host IS NOT NULL AND source <> 'Direct' GROUP BY ref_host ORDER BY n DESC LIMIT 8", [$from]);
$camps = $all("SELECT CONCAT(COALESCE(utm_source, '?'), ' · ', COALESCE(utm_campaign, '(no campaign)')) k, COUNT(DISTINCT day, visitor) n FROM site_visits WHERE day >= ? AND utm_source IS NOT NULL GROUP BY k ORDER BY n DESC LIMIT 8", [$from]);
$devices = $all("SELECT device k, COUNT(DISTINCT day, visitor) n FROM site_visits WHERE day >= ? GROUP BY device ORDER BY n DESC", [$from]);
$browsers = $all("SELECT browser k, COUNT(DISTINCT day, visitor) n FROM site_visits WHERE day >= ? GROUP BY browser ORDER BY n DESC LIMIT 6", [$from]);
$oses = $all("SELECT os k, COUNT(DISTINCT day, visitor) n FROM site_visits WHERE day >= ? GROUP BY os ORDER BY n DESC LIMIT 6", [$from]);
$countries = $all("SELECT country k, COUNT(DISTINCT day, visitor) n FROM site_visits WHERE day >= ? AND country IS NOT NULL GROUP BY country ORDER BY n DESC LIMIT 10", [$from]);
$hours = array_fill(0, 24, 0);
foreach ($all("SELECT HOUR(ts) h, COUNT(*) n FROM site_visits WHERE day >= ? GROUP BY h", [$from]) as $r) $hours[(int)$r['h']] = (int)$r['n'];
$roles = $pairs("SELECT COALESCE(role, 'visitor') k, COUNT(*) n FROM site_visits WHERE day >= ? GROUP BY k", [$from]);

/* ── Search ── */
$topSearch = $all("SELECT q, COUNT(*) n, ROUND(AVG(results)) r, MAX(day) last FROM search_log WHERE day >= ? GROUP BY q ORDER BY n DESC LIMIT 15", [$from]);
$noResult = $all("SELECT q, COUNT(*) n, GROUP_CONCAT(DISTINCT source) src, MAX(day) last FROM search_log WHERE day >= ? AND results = 0 GROUP BY q ORDER BY n DESC, last DESC LIMIT 15", [$from]);
$zeroShare = $K['searches'] ? (int)$one("SELECT COUNT(*) FROM search_log WHERE day >= ? AND results = 0", [$from]) / $K['searches'] * 100 : null;

/* ── Courses ── */
$courses = $all("SELECT c.id, c.title, c.price, c.avg_rating,
        (SELECT COUNT(DISTINCT v.day, v.visitor) FROM site_visits v WHERE v.path = 'course-details' AND v.item = c.id AND v.day >= ?) AS views,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND DATE(e.enrolled_at) >= ?) AS enrol_new,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS enrol_all,
        (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = 'completed') AS done,
        (SELECT ROUND(AVG(e.progress_percent)) FROM enrollments e WHERE e.course_id = c.id) AS avg_prog,
        (SELECT COUNT(*) FROM course_ratings r WHERE r.course_id = c.id) AS n_rate,
        (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.course_id = c.id AND p.status = 'verified' AND DATE(p.created_at) >= ?) AS revenue
    FROM courses c WHERE c.status = 'published' ORDER BY views DESC, enrol_new DESC, enrol_all DESC LIMIT 60", [$from, $from, $from]);

/* ── Audience (learner accounts) ── */
$students = (int)$one("SELECT COUNT(*) FROM users WHERE role = 'student'");
$gender = $pairs("SELECT COALESCE(NULLIF(gender, ''), 'not given') k, COUNT(*) n FROM users WHERE role = 'student' GROUP BY k ORDER BY n DESC");
$ageRows = $pairs("SELECT CASE WHEN dob IS NULL THEN 'not given' WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 18 THEN 'Under 18' WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 25 THEN '18–24'
        WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 35 THEN '25–34' WHEN TIMESTAMPDIFF(YEAR, dob, CURDATE()) < 45 THEN '35–44' ELSE '45+' END k, COUNT(*) n FROM users WHERE role = 'student' GROUP BY k");
$ageOrder = ['Under 18', '18–24', '25–34', '35–44', '45+', 'not given'];
$age = []; foreach ($ageOrder as $a) if (isset($ageRows[$a])) $age[$a] = (int)$ageRows[$a];
$county = $pairs("SELECT COALESCE(NULLIF(TRIM(county), ''), 'not given') k, COUNT(*) n FROM users WHERE role = 'student' GROUP BY k ORDER BY (k = 'not given'), n DESC LIMIT 10");
$edu = $pairs("SELECT COALESCE(NULLIF(TRIM(education_level), ''), 'not given') k, COUNT(*) n FROM users WHERE role = 'student' GROUP BY k ORDER BY (k = 'not given'), n DESC LIMIT 8");
$modes = $pairs("SELECT CASE learning_mode WHEN 'structured' THEN 'Weekly track (cohort)' WHEN 'self_paced' THEN 'Self-paced' ELSE COALESCE(learning_mode, 'not set') END k, COUNT(*) n FROM enrollments GROUP BY k ORDER BY n DESC");
$refd = (int)$one("SELECT COUNT(*) FROM users WHERE role = 'student' AND referred_by IS NOT NULL");
$status = $pairs("SELECT status k, COUNT(*) n FROM users WHERE role = 'student' GROUP BY status");

/* ── Behaviour ── */
$act = fn(int $d) => (int)$one("SELECT COUNT(DISTINCT student_id) FROM lesson_progress WHERE last_seen_at >= NOW() - INTERVAL $d DAY");
$active = ['1' => $act(1), '7' => $act(7), '30' => $act(30)];
$lessonsDone = (int)$one("SELECT COUNT(*) FROM lesson_progress WHERE status = 'completed' AND DATE(completed_at) >= ?", [$from]);
$activeRange = (int)$one("SELECT COUNT(DISTINCT student_id) FROM lesson_progress WHERE DATE(last_seen_at) >= ?", [$from]);
$mins = (int)round((float)$one("SELECT COALESCE(SUM(COALESCE(time_spent_secs, time_spent_mins * 60)), 0) FROM lesson_progress WHERE DATE(last_seen_at) >= ?", [$from]) / 60);
$quiz = $all("SELECT COUNT(*) n, SUM(passed = 1) p, ROUND(AVG(score)) s FROM quiz_attempts WHERE (status = 'completed' OR status IS NULL) AND DATE(completed_at) >= ?", [$from])[0] ?? ['n' => 0, 'p' => 0, 's' => null];
$heat = array_fill(0, 7, array_fill(0, 24, 0));
foreach ($all("SELECT WEEKDAY(last_seen_at) d, HOUR(last_seen_at) h, COUNT(*) n FROM lesson_progress WHERE DATE(last_seen_at) >= ? GROUP BY d, h", [$from]) as $r) $heat[(int)$r['d']][(int)$r['h']] = (int)$r['n'];
foreach ($all("SELECT WEEKDAY(created_at) d, HOUR(created_at) h, COUNT(*) n FROM learning_events WHERE DATE(created_at) >= ? GROUP BY d, h", [$from]) as $r) $heat[(int)$r['d']][(int)$r['h']] += (int)$r['n'];
$prog = $pairs("SELECT CASE WHEN progress_percent >= 100 OR status = 'completed' THEN '100% (done)' WHEN progress_percent >= 75 THEN '75–99%' WHEN progress_percent >= 50 THEN '50–74%'
        WHEN progress_percent >= 25 THEN '25–49%' WHEN progress_percent > 0 THEN '1–24%' ELSE 'Not started' END k, COUNT(*) n FROM enrollments GROUP BY k");
$progOrder = ['Not started', '1–24%', '25–49%', '50–74%', '75–99%', '100% (done)'];
$progD = []; foreach ($progOrder as $k) $progD[$k] = (int)($prog[$k] ?? 0);
$funnel = [
    ['Signed up', (int)$one("SELECT COUNT(*) FROM users WHERE role = 'student' AND DATE(created_at) >= ?", [$from])],
    ['Activated account', (int)$one("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active' AND DATE(created_at) >= ?", [$from])],
    ['Enrolled in a course', (int)$one("SELECT COUNT(DISTINCT u.id) FROM users u JOIN enrollments e ON e.student_id = u.id WHERE u.role = 'student' AND DATE(u.created_at) >= ?", [$from])],
    ['Started learning', (int)$one("SELECT COUNT(DISTINCT u.id) FROM users u JOIN lesson_progress l ON l.student_id = u.id WHERE u.role = 'student' AND DATE(u.created_at) >= ?", [$from])],
    ['Completed a course', (int)$one("SELECT COUNT(DISTINCT u.id) FROM users u JOIN enrollments e ON e.student_id = u.id AND e.status = 'completed' WHERE u.role = 'student' AND DATE(u.created_at) >= ?", [$from])],
    ['Earned a certificate', (int)$one("SELECT COUNT(DISTINCT u.id) FROM users u JOIN certificates c ON c.student_id = u.id WHERE u.role = 'student' AND DATE(u.created_at) >= ?", [$from])],
];
$events = $pairs("SELECT event_type k, COUNT(*) n FROM learning_events WHERE DATE(created_at) >= ? GROUP BY event_type ORDER BY n DESC LIMIT 10", [$from]);
$eventNames = ['lesson_view' => 'Opened a lesson', 'lesson_complete' => 'Completed a lesson', 'quiz_attempt' => 'Took a quiz', 'quiz_pass' => 'Passed a quiz', 'quiz_perfect' => 'Perfect quiz score',
    'exercise_pass' => 'Passed a coding exercise', 'course_complete' => 'Finished a course', 'badge' => 'Earned a badge', 'daily_streak' => 'Kept a daily streak', 'review' => 'Wrote a review'];
$streak = $pairs("SELECT CASE WHEN streak_days >= 7 THEN '7+ days' WHEN streak_days >= 3 THEN '3–6 days' WHEN streak_days >= 1 THEN '1–2 days' ELSE 'No streak' END k, COUNT(*) n FROM users WHERE role = 'student' AND status = 'active' GROUP BY k");
$streakD = []; foreach (['No streak', '1–2 days', '3–6 days', '7+ days'] as $k) $streakD[$k] = (int)($streak[$k] ?? 0);

$fmt = fn($n) => number_format((float)$n);
$pct = fn($a, $b) => $b ? round($a / $b * 100) . '%' : '—';
$flag = fn($cc) => $cc && strlen($cc) === 2 ? mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65) : '';
$countryNames = ['KE' => 'Kenya', 'UG' => 'Uganda', 'TZ' => 'Tanzania', 'RW' => 'Rwanda', 'NG' => 'Nigeria', 'GH' => 'Ghana', 'ZA' => 'South Africa', 'ET' => 'Ethiopia', 'SO' => 'Somalia', 'SS' => 'South Sudan',
    'US' => 'United States', 'GB' => 'United Kingdom', 'IN' => 'India', 'DE' => 'Germany', 'NL' => 'Netherlands', 'FR' => 'France', 'CA' => 'Canada', 'AE' => 'UAE', 'CN' => 'China', 'IE' => 'Ireland', 'SG' => 'Singapore'];
$rowsOf = fn(array $r, string $k = 'k', string $n = 'n') => array_map(fn($x) => [(string)$x[$k], (int)$x[$n]], $r);
$rowsKV = fn(array $kv) => array_map(fn($k, $v) => [(string)$k, (int)$v], array_keys($kv), array_values($kv));

require_once 'includes/sidebar.php';
?>
<style>
  .an { --s1: #3987e5; --s2: #d95926; --s3: #199e70; --grid: rgba(148,163,184,.16); --track: rgba(148,163,184,.14); }
  html.ax-light .an { --s1: #2a78d6; --s2: #eb6834; --s3: #1baf7a; --grid: rgba(100,116,139,.16); --track: rgba(100,116,139,.12); }
  .an-bar-top { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: space-between; margin-bottom: 16px; }
  .an-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
  .an-nav { display: flex; gap: 6px; flex-wrap: wrap; position: sticky; top: calc(var(--header-h, 72px) + 4px); z-index: 5; background: var(--bg-main); padding: 8px 0; margin-bottom: 8px; }
  .an-nav a { font-size: .8rem; font-weight: 700; padding: 7px 12px; border-radius: 99px; border: 1px solid var(--border); color: var(--text-dim); text-decoration: none; background: var(--surface); }
  .an-nav a:hover { color: var(--text-main); border-color: var(--primary); }
  .an-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; margin-bottom: 18px; }
  .an-k { border: 1px solid var(--border); background: var(--surface); border-radius: 16px; padding: 14px 16px; }
  .an-k .l { font-size: .76rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
  .an-k .v { font: 800 1.6rem 'Poppins', sans-serif; color: var(--text-main); margin: 4px 0 2px; font-variant-numeric: tabular-nums; }
  .an-d { font-size: .76rem; font-weight: 700; color: var(--text-muted); } .an-d.up { color: #10b981; } .an-d.down { color: #f97316; } .an-d small { font-weight: 500; color: var(--text-muted); }
  .an-sec { scroll-margin-top: 130px; }
  .an-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
  .an-grid .ax-panel { margin: 0; }
  .an-h3 { font: 800 .82rem 'Inter', sans-serif; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); margin: 0 0 10px; }
  .an-legend { display: flex; gap: 14px; flex-wrap: wrap; font-size: .8rem; color: var(--text-dim); margin-bottom: 8px; }
  .an-legend span::before { content: ''; display: inline-block; width: 12px; height: 3px; border-radius: 2px; margin-right: 6px; vertical-align: middle; background: var(--c); }
  .an-line { position: relative; } .an-line svg { display: block; width: 100%; height: 240px; overflow: visible; }
  .an-line text { fill: var(--text-muted); font: 11px Inter, sans-serif; }
  .an-hbars { display: grid; gap: 8px; }
  .an-hb { display: grid; grid-template-columns: minmax(90px, 38%) 1fr auto; gap: 10px; align-items: center; font-size: .84rem; color: var(--text-main); }
  .an-hb .nm { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .an-hb .tr { height: 10px; border-radius: 6px; background: var(--track); overflow: hidden; }
  .an-hb .fl { height: 100%; border-radius: 0 4px 4px 0; background: var(--s1); min-width: 2px; }
  .an-hb .n { font-variant-numeric: tabular-nums; color: var(--text-dim); font-size: .8rem; min-width: 64px; text-align: right; }
  .an-cols { display: flex; align-items: flex-end; gap: 2px; height: 150px; padding-top: 6px; }
  .an-cols .c { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; position: relative; cursor: default; }
  .an-cols .c i { display: block; background: var(--s1); border-radius: 4px 4px 0 0; min-height: 2px; }
  .an-cols .c:hover i { filter: brightness(1.15); }
  .an-cols-x { display: flex; gap: 2px; font-size: .68rem; color: var(--text-muted); margin-top: 4px; } .an-cols-x span { flex: 1; text-align: center; }
  .an-heat { display: grid; grid-template-columns: 38px repeat(24, 1fr); gap: 2px; font-size: .66rem; color: var(--text-muted); }
  .an-heat .cell { aspect-ratio: 1; border-radius: 3px; background: var(--s1); min-height: 10px; }
  .an-heat .rl { display: flex; align-items: center; } .an-heat .hl { text-align: center; }
  .an-funnel { display: grid; gap: 8px; }
  .an-funnel .st { display: grid; grid-template-columns: 170px 1fr 110px; gap: 10px; align-items: center; font-size: .85rem; color: var(--text-main); }
  .an-funnel .tr { height: 26px; border-radius: 6px; background: var(--track); overflow: hidden; }
  .an-funnel .fl { height: 100%; border-radius: 0 4px 4px 0; background: var(--s1); }
  .an-funnel .n { font-size: .8rem; color: var(--text-dim); text-align: right; font-variant-numeric: tabular-nums; }
  .an-table { width: 100%; border-collapse: collapse; font-size: .84rem; }
  .an-table th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); padding: 8px 10px; border-bottom: 1px solid var(--border); white-space: nowrap; }
  .an-table td { padding: 9px 10px; border-bottom: 1px solid var(--border-light); color: var(--text-main); vertical-align: middle; }
  .an-table td.num, .an-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
  .an-table tr:hover td { background: var(--surface-2); }
  .an-mini { display: inline-block; width: 70px; height: 6px; border-radius: 4px; background: var(--track); vertical-align: middle; margin-right: 6px; overflow: hidden; } .an-mini i { display: block; height: 100%; background: var(--s3); }
  .an-empty { color: var(--text-muted); font-size: .86rem; padding: 10px 0; }
  .an-note { font-size: .8rem; color: var(--text-muted); margin-top: 10px; line-height: 1.5; }
  .an-tip { position: fixed; z-index: 9999; pointer-events: none; background: #0b1324; color: #fff; font: 600 .78rem Inter, sans-serif; padding: 8px 10px; border-radius: 10px; box-shadow: 0 10px 24px -10px rgba(0,0,0,.5); white-space: nowrap; display: none; }
  .an-tip b { display: block; font-size: .72rem; color: #94a3b8; font-weight: 700; margin-bottom: 2px; }
  .an-tip .sw { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; }
  details.an-tbl summary { cursor: pointer; font-size: .78rem; color: var(--text-dim); margin-top: 8px; }
  .an-demand td:first-child { font-weight: 700; }
  @media (max-width: 640px) { .an-hb { grid-template-columns: 1fr auto; } .an-hb .tr { grid-column: 1 / -1; order: 3; } .an-funnel .st { grid-template-columns: 1fr auto; } .an-funnel .tr { grid-column: 1 / -1; } .an-heat { font-size: .55rem; } }
</style>
<main class="main-content an">
    <?php ax_header('Analytics', '', 'Traffic, searches, courses, audience and learner behaviour'); ?>
    <div class="ax-body">
        <div class="an-bar-top">
            <nav class="ax-tabs" aria-label="Time range"><?php foreach ($ranges as $d => $l): ?><a href="analytics?range=<?= $d ?>" class="<?= $d === $days ? 'on' : '' ?>">Last <?= e($l) ?></a><?php endforeach; ?></nav>
            <span style="font-size:.8rem;color:var(--text-muted)"><?= e(date('j M Y', strtotime($from))) ?> – <?= e(date('j M Y')) ?></span>
        </div>
        <?php if (!$tracking): ?><div class="ax-panel" style="padding:12px 16px;border-color:#0ea5e9;font-size:.86rem"><i class="fas fa-circle-info" style="color:#0ea5e9"></i> Visit tracking has just been switched on, so traffic and search numbers start from now. Course, audience and learner figures already include all past data.</div>
        <?php elseif ($since > $from): ?><div class="ax-panel" style="padding:10px 16px;font-size:.82rem;color:var(--text-dim)"><i class="fas fa-circle-info"></i> Visit tracking started on <?= e(date('j M Y', strtotime($since))) ?>, so earlier days show no traffic.</div><?php endif; ?>

        <nav class="an-nav" aria-label="Sections"><a href="#traffic">Traffic</a><a href="#search">Searches</a><a href="#courses">Courses</a><a href="#audience">Audience</a><a href="#behaviour">Learner behaviour</a></nav>

        <div class="an-kpis">
            <div class="an-k"><div class="l">Visits</div><div class="v"><?= $fmt($K['visits']) ?></div><?= $delta('visits') ?></div>
            <div class="an-k"><div class="l">Page views</div><div class="v"><?= $fmt($K['views']) ?></div><?= $delta('views') ?></div>
            <div class="an-k"><div class="l">Pages per visit</div><div class="v"><?= $K['visits'] ? number_format($K['views'] / $K['visits'], 1) : '—' ?></div><span class="an-d">average</span></div>
            <div class="an-k"><div class="l">New learners</div><div class="v"><?= $fmt($K['signups']) ?></div><?= $delta('signups') ?></div>
            <div class="an-k"><div class="l">Visit → sign-up</div><div class="v"><?= $conv === null ? '—' : number_format($conv, 1) . '%' ?></div><span class="an-d"><?= $conv === null ? 'after a full period of tracking' : 'of visits' ?></span></div>
            <div class="an-k"><div class="l">Enrolments</div><div class="v"><?= $fmt($K['enrol']) ?></div><?= $delta('enrol') ?></div>
            <div class="an-k"><div class="l">Revenue (verified)</div><div class="v" style="font-size:1.35rem">KES <?= $fmt($K['revenue']) ?></div><?= $delta('revenue') ?></div>
            <div class="an-k"><div class="l">Searches</div><div class="v"><?= $fmt($K['searches']) ?></div><span class="an-d"><?= $zeroShare === null ? '—' : round($zeroShare) . '% found nothing' ?></span></div>
        </div>

        <!-- ── Traffic ── -->
        <section class="an-sec" id="traffic">
            <section class="ax-panel">
                <div class="ax-panel-h"><div><h2><i class="fas fa-chart-line"></i> Traffic</h2><p>A visit is one person on one day. Page views count every page they open.</p></div></div>
                <div class="ax-panel-b">
                    <div class="an-legend"><span style="--c:var(--s1)">Visits</span><span style="--c:var(--s2)">Page views</span></div>
                    <div class="an-line" id="chTraffic"></div>
                    <details class="an-tbl"><summary>View as table</summary><div style="max-height:240px;overflow:auto"><table class="an-table"><thead><tr><th>Day</th><th class="num">Visits</th><th class="num">Page views</th></tr></thead><tbody>
                        <?php foreach (array_reverse(array_keys($series['labels'])) as $i): ?><tr><td><?= e($series['labels'][$i]) ?></td><td class="num"><?= $series['visits'][$i] ?></td><td class="num"><?= $series['views'][$i] ?></td></tr><?php endforeach; ?>
                    </tbody></table></div></details>
                </div>
            </section>
            <div class="an-grid">
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-file-lines"></i> Top pages</h2><p>Page views · visits</p></div></div><div class="ax-panel-b">
                    <?php if (!$topPages): ?><div class="an-empty">No visits recorded yet.</div><?php else: ?><div class="an-hbars" data-hb='<?= e(json_encode(array_map(fn($r) => [$pageLabel($r['path']), (int)$r['v'], (int)$r['u'] . ' visits'], $topPages))) ?>'></div><?php endif; ?></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-signs-post"></i> Where visitors come from</h2><p>Visits by source</p></div></div><div class="ax-panel-b">
                    <?php if (!$sources): ?><div class="an-empty">No visits recorded yet.</div><?php else: ?><div class="an-hbars" data-hb='<?= e(json_encode($rowsOf($sources))) ?>' data-pct="1"></div><?php endif; ?>
                    <?php if ($refs): ?><h3 class="an-h3" style="margin-top:16px">Top referring sites</h3><div class="an-hbars" data-hb='<?= e(json_encode($rowsOf($refs))) ?>'></div><?php endif; ?>
                    <?php if ($camps): ?><h3 class="an-h3" style="margin-top:16px">Campaigns (utm links)</h3><div class="an-hbars" data-hb='<?= e(json_encode($rowsOf($camps))) ?>'></div><?php endif; ?>
                    <p class="an-note">Tip: add <code>?utm_source=facebook&amp;utm_campaign=careers</code> to links you share to see exactly which posts bring visitors.</p></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-mobile-screen"></i> Devices &amp; browsers</h2><p>Visits</p></div></div><div class="ax-panel-b">
                    <?php if (!$devices): ?><div class="an-empty">No visits recorded yet.</div><?php else: ?>
                    <div class="an-hbars" data-hb='<?= e(json_encode(array_map(fn($r) => [ucfirst($r['k']), (int)$r['n']], $devices))) ?>' data-pct="1"></div>
                    <h3 class="an-h3" style="margin-top:16px">Operating system</h3><div class="an-hbars" data-hb='<?= e(json_encode($rowsOf($oses))) ?>' data-pct="1"></div>
                    <h3 class="an-h3" style="margin-top:16px">Browser</h3><div class="an-hbars" data-hb='<?= e(json_encode($rowsOf($browsers))) ?>' data-pct="1"></div><?php endif; ?></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-earth-africa"></i> Countries</h2><p>Visits</p></div></div><div class="ax-panel-b">
                    <?php if (!$countries): ?><div class="an-empty">No country data yet. Countries come from Cloudflare, so they appear on the live site only.</div><?php else: ?><div class="an-hbars" data-hb='<?= e(json_encode(array_map(fn($r) => [$flag($r['k']) . ' ' . ($countryNames[$r['k']] ?? $r['k']), (int)$r['n']], $countries))) ?>' data-pct="1"></div><?php endif; ?>
                    <h3 class="an-h3" style="margin-top:16px">Who is browsing</h3><div class="an-hbars" data-hb='<?= e(json_encode(array_map(fn($k, $v) => [['visitor' => 'Visitors (not signed in)', 'student' => 'Learners', 'tutor' => 'Tutors', 'staff' => 'Staff', 'admin' => 'Admins'][$k] ?? ucfirst($k), (int)$v], array_keys($roles), array_values($roles)))) ?>' data-pct="1"></div></div></section>
            </div>
            <section class="ax-panel" style="margin-top:16px"><div class="ax-panel-h"><div><h2><i class="fas fa-clock"></i> Busiest hours</h2><p>Page views by hour of day (East Africa Time)</p></div></div><div class="ax-panel-b">
                <div class="an-cols" data-cols='<?= e(json_encode($hours)) ?>' data-unit="page views"></div>
                <div class="an-cols-x"><?php for ($h = 0; $h < 24; $h++): ?><span><?= $h % 3 === 0 ? sprintf('%02d', $h) : '' ?></span><?php endfor; ?></div></div></section>
        </section>

        <!-- ── Searches ── -->
        <section class="an-sec" id="search" style="margin-top:22px">
            <div class="an-grid">
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-magnifying-glass"></i> Top searches</h2><p>On the Courses and Tutorials pages</p></div></div><div class="ax-panel-b">
                    <?php if (!$topSearch): ?><div class="an-empty">No searches yet in this period.</div><?php else: ?>
                    <table class="an-table"><thead><tr><th>Search</th><th class="num">Times</th><th class="num">Avg results</th></tr></thead><tbody>
                    <?php foreach ($topSearch as $s): ?><tr><td><?= e($s['q']) ?></td><td class="num"><?= (int)$s['n'] ?></td><td class="num"><?= (int)$s['r'] === 0 ? '<span class="ax-pill warn">0</span>' : (int)$s['r'] ?></td></tr><?php endforeach; ?>
                    </tbody></table><?php endif; ?></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-lightbulb"></i> Demand: searched but not offered</h2><p>Searches that found nothing – ideas for new courses</p></div></div><div class="ax-panel-b">
                    <?php if (!$noResult): ?><div class="an-empty">Every search found something. 🎉</div><?php else: ?>
                    <table class="an-table an-demand"><thead><tr><th>Searched for</th><th class="num">Times</th><th>Last</th><th></th></tr></thead><tbody>
                    <?php foreach ($noResult as $s): $prompt = 'Learners on our site searched for "' . $s['q'] . '" ' . (int)$s['n'] . ' time(s) and we have no matching course. Suggest a practical course we could add: title, who it is for, level, a week-by-week outline (4–6 weeks), a fair price in KES for Kenya, and how to promote it.'; ?>
                        <tr><td><?= e($s['q']) ?></td><td class="num"><?= (int)$s['n'] ?></td><td style="color:var(--text-muted);font-size:.78rem"><?= e(date('j M', strtotime($s['last']))) ?></td>
                            <td class="num"><a class="ax-btn ghost sm" href="ai-assistant?p=<?= rawurlencode($prompt) ?>" title="Ask the AI assistant for a course plan"><i class="fas fa-wand-magic-sparkles"></i> Plan a course</a></td></tr>
                    <?php endforeach; ?></tbody></table><?php endif; ?>
                    <p class="an-note">Add a course from <a href="courses">Courses</a> once you've planned it. Repeated searches are the strongest signal.</p></div></section>
            </div>
        </section>

        <!-- ── Courses ── -->
        <section class="an-sec ax-panel" id="courses" style="margin-top:22px">
            <div class="ax-panel-h"><div><h2><i class="fas fa-book"></i> Course performance</h2><p>Views and new enrolments are for the selected period; completion, progress and rating are all-time.</p></div></div>
            <div class="ax-table-wrap" style="max-height:520px;overflow:auto"><table class="an-table">
                <thead><tr><th>Course</th><th class="num">Page visits</th><th class="num">New enrolments</th><th class="num">View → enrol</th><th class="num">All enrolments</th><th>Completion</th><th class="num">Avg progress</th><th class="num">Rating</th><th class="num">Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($courses as $c): $cr = $c['enrol_all'] ? $c['done'] / $c['enrol_all'] : 0; ?>
                    <tr><td style="max-width:280px"><a href="course_details?id=<?= (int)$c['id'] ?>" style="color:var(--text-main);font-weight:600"><?= e($c['title']) ?></a></td>
                        <td class="num"><?= (int)$c['views'] ?></td><td class="num"><?= (int)$c['enrol_new'] ?></td>
                        <td class="num"><?= $c['views'] ? $pct($c['enrol_new'], $c['views']) : '—' ?></td><td class="num"><?= (int)$c['enrol_all'] ?></td>
                        <td><?php if ($c['enrol_all']): ?><span class="an-mini"><i style="width:<?= round($cr * 100) ?>%"></i></span><?= round($cr * 100) ?>%<?php else: ?>—<?php endif; ?></td>
                        <td class="num"><?= $c['avg_prog'] !== null ? (int)$c['avg_prog'] . '%' : '—' ?></td>
                        <td class="num"><?= $c['n_rate'] ? number_format((float)$c['avg_rating'], 1) . ' ★ <small style="color:var(--text-muted)">(' . (int)$c['n_rate'] . ')</small>' : '—' ?></td>
                        <td class="num"><?= $c['revenue'] > 0 ? 'KES ' . $fmt($c['revenue']) : '—' ?></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
        </section>

        <!-- ── Audience ── -->
        <section class="an-sec" id="audience" style="margin-top:22px">
            <div class="ax-panel" style="padding:12px 18px;font-size:.84rem;color:var(--text-dim)"><i class="fas fa-users"></i> <b style="color:var(--text-main)"><?= $fmt($students) ?> learner accounts</b> · <?= (int)($status['active'] ?? 0) ?> active, <?= (int)($status['pending'] ?? 0) ?> awaiting activation · <?= $pct($refd, $students) ?> joined through a referral. Profile details are optional, so "not given" is common.</div>
            <div class="an-grid" style="margin-top:16px">
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-venus-mars"></i> Gender</h2></div></div><div class="ax-panel-b"><div class="an-hbars" data-hb='<?= e(json_encode(array_map(fn($k, $v) => [ucfirst((string)$k), (int)$v], array_keys($gender), array_values($gender)))) ?>' data-pct="1"></div></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-cake-candles"></i> Age</h2></div></div><div class="ax-panel-b"><div class="an-hbars" data-hb='<?= e(json_encode($rowsKV($age))) ?>' data-pct="1"></div></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-location-dot"></i> County</h2><p>Top 10</p></div></div><div class="ax-panel-b"><div class="an-hbars" data-hb='<?= e(json_encode($rowsKV($county))) ?>' data-pct="1"></div></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-graduation-cap"></i> Education level</h2></div></div><div class="ax-panel-b"><div class="an-hbars" data-hb='<?= e(json_encode($rowsKV($edu))) ?>' data-pct="1"></div></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-route"></i> How they learn</h2><p>Enrolments by learning mode</p></div></div><div class="ax-panel-b"><div class="an-hbars" data-hb='<?= e(json_encode($rowsKV($modes))) ?>' data-pct="1"></div></div></section>
            </div>
        </section>

        <!-- ── Behaviour ── -->
        <section class="an-sec" id="behaviour" style="margin-top:22px">
            <div class="an-kpis">
                <div class="an-k"><div class="l">Active today</div><div class="v"><?= $fmt($active['1']) ?></div><span class="an-d">learners studying</span></div>
                <div class="an-k"><div class="l">Active this week</div><div class="v"><?= $fmt($active['7']) ?></div><span class="an-d">last 7 days</span></div>
                <div class="an-k"><div class="l">Active this month</div><div class="v"><?= $fmt($active['30']) ?></div><span class="an-d">last 30 days</span></div>
                <div class="an-k"><div class="l">Lessons completed</div><div class="v"><?= $fmt($lessonsDone) ?></div><span class="an-d">in this period</span></div>
                <div class="an-k"><div class="l">Study time</div><div class="v"><?= $mins >= 120 ? $fmt(round($mins / 60)) . ' h' : $fmt($mins) . ' min' ?></div><span class="an-d"><?= $activeRange ? round($mins / $activeRange) . ' min per active learner' : '—' ?></span></div>
                <div class="an-k"><div class="l">Quiz pass rate</div><div class="v"><?= $quiz['n'] ? round($quiz['p'] / $quiz['n'] * 100) . '%' : '—' ?></div><span class="an-d"><?= (int)$quiz['n'] ?> attempts<?= $quiz['s'] !== null ? ' · avg ' . (int)$quiz['s'] . '%' : '' ?></span></div>
            </div>
            <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-filter"></i> Learner journey</h2><p>Of the learners who signed up in this period, how far they got</p></div></div><div class="ax-panel-b">
                <div class="an-funnel"><?php $top = max(1, $funnel[0][1]); foreach ($funnel as $i => [$l, $n]): ?>
                    <div class="st"><span><?= e($l) ?></span><div class="tr"><div class="fl" style="width:<?= round($n / $top * 100) ?>%;opacity:<?= 1 - $i * .11 ?>"></div></div><span class="n"><b style="color:var(--text-main)"><?= $fmt($n) ?></b> · <?= $pct($n, $funnel[0][1]) ?></span></div>
                <?php endforeach; ?></div>
                <p class="an-note">The biggest drop between two steps is where learners need the most help.</p></div></section>
            <div class="an-grid" style="margin-top:16px">
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-calendar-week"></i> When learners study</h2><p>Learning activity by day and hour (EAT). Darker = busier.</p></div></div><div class="ax-panel-b">
                    <div class="an-heat" id="chHeat" data-heat='<?= e(json_encode($heat)) ?>'></div></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-bars-progress"></i> Course progress</h2><p>All enrolments by how far learners are</p></div></div><div class="ax-panel-b">
                    <div class="an-hbars" data-hb='<?= e(json_encode($rowsKV($progD))) ?>' data-pct="1"></div>
                    <h3 class="an-h3" style="margin-top:16px">Daily learning streaks (active learners)</h3><div class="an-hbars" data-hb='<?= e(json_encode($rowsKV($streakD))) ?>' data-pct="1"></div></div></section>
                <section class="ax-panel"><div class="ax-panel-h"><div><h2><i class="fas fa-person-running"></i> What learners do</h2><p>Learning actions in this period</p></div></div><div class="ax-panel-b">
                    <?php if (!$events): ?><div class="an-empty">No learning activity in this period.</div><?php else: ?><div class="an-hbars" data-hb='<?= e(json_encode(array_map(fn($k, $v) => [$eventNames[$k] ?? ucfirst(str_replace('_', ' ', (string)$k)), (int)$v], array_keys($events), array_values($events)))) ?>'></div><?php endif; ?></div></section>
            </div>
        </section>
        <p class="an-note" style="text-align:center;margin-top:24px"><i class="fas fa-user-shield"></i> Visits are counted without cookies, and no IP addresses or personal details are stored. A visitor's anonymous id changes every day.</p>
    </div>
</main>
<div class="an-tip" id="anTip" role="tooltip"></div>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
    'use strict';
    var TR = <?= json_encode($series, JSON_UNESCAPED_UNICODE) ?>;
    var tip = document.getElementById('anTip');
    var nf = function (n) { return Number(n).toLocaleString('en-KE'); };
    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function show(e, html) { tip.innerHTML = html; tip.style.display = 'block'; var x = e.clientX + 14, y = e.clientY + 14, w = tip.offsetWidth, h = tip.offsetHeight; if (x + w > innerWidth - 8) x = e.clientX - w - 14; if (y + h > innerHeight - 8) y = e.clientY - h - 14; tip.style.left = x + 'px'; tip.style.top = y + 'px'; }
    function hide() { tip.style.display = 'none'; }
    var css = getComputedStyle(document.querySelector('.an'));
    function col(v) { return css.getPropertyValue(v).trim(); }

    // Horizontal bar lists: [[label, value, extra?], ...]
    document.querySelectorAll('[data-hb]').forEach(function (el) {
        var rows = JSON.parse(el.dataset.hb || '[]'), pct = el.dataset.pct === '1';
        var max = Math.max.apply(null, rows.map(function (r) { return r[1]; }).concat([1])), total = rows.reduce(function (a, r) { return a + r[1]; }, 0) || 1;
        if (!rows.length || !total) { el.innerHTML = '<div class="an-empty">No data yet.</div>'; return; }
        el.innerHTML = rows.map(function (r) {
            var share = Math.round(r[1] / total * 100);
            return '<div class="an-hb" data-t="' + esc(r[0]) + '" data-v="' + r[1] + '" data-x="' + esc(r[2] || (pct ? share + '% of total' : '')) + '"><span class="nm" title="' + esc(r[0]) + '">' + esc(r[0]) + '</span><div class="tr"><div class="fl" style="width:' + Math.max(1, r[1] / max * 100) + '%"></div></div><span class="n">' + nf(r[1]) + (pct ? ' · ' + share + '%' : '') + '</span></div>';
        }).join('');
        el.querySelectorAll('.an-hb').forEach(function (row) {
            row.addEventListener('mousemove', function (e) { show(e, '<b>' + row.dataset.t + '</b>' + nf(row.dataset.v) + (row.dataset.x ? ' · ' + row.dataset.x : '')); });
            row.addEventListener('mouseleave', hide);
        });
    });

    // Column chart (hours)
    document.querySelectorAll('[data-cols]').forEach(function (el) {
        var v = JSON.parse(el.dataset.cols), max = Math.max.apply(null, v.concat([1]));
        el.innerHTML = v.map(function (n, i) { return '<div class="c" data-i="' + i + '"><i style="height:' + (n / max * 100) + '%"></i></div>'; }).join('');
        el.querySelectorAll('.c').forEach(function (c) {
            var i = +c.dataset.i;
            c.addEventListener('mousemove', function (e) { show(e, '<b>' + ('0' + i).slice(-2) + ':00–' + ('0' + i).slice(-2) + ':59</b>' + nf(v[i]) + ' ' + el.dataset.unit); });
            c.addEventListener('mouseleave', hide);
        });
    });

    // Heatmap: weekday × hour
    var hm = document.getElementById('chHeat');
    if (hm) {
        var H = JSON.parse(hm.dataset.heat), mx = 1, days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], html = '<span></span>';
        H.forEach(function (r) { r.forEach(function (n) { if (n > mx) mx = n; }); });
        for (var h = 0; h < 24; h++) html += '<span class="hl">' + (h % 6 === 0 ? ('0' + h).slice(-2) : '') + '</span>';
        H.forEach(function (r, d) {
            html += '<span class="rl">' + days[d] + '</span>';
            r.forEach(function (n, h) { html += '<span class="cell" data-d="' + d + '" data-h="' + h + '" style="opacity:' + (n ? .18 + .82 * n / mx : .06) + '"></span>'; });
        });
        hm.innerHTML = html;
        hm.querySelectorAll('.cell').forEach(function (c) {
            c.addEventListener('mousemove', function (e) { var d = +c.dataset.d, h = +c.dataset.h; show(e, '<b>' + days[d] + ' ' + ('0' + h).slice(-2) + ':00</b>' + nf(H[d][h]) + ' learning actions'); });
            c.addEventListener('mouseleave', hide);
        });
    }

    // Line chart: visits + page views over time, with crosshair tooltip
    var box = document.getElementById('chTraffic');
    function drawLine() {
        var W = box.clientWidth || 600, Hh = 240, pl = 40, pr = 10, pt = 12, pb = 26, n = TR.labels.length;
        var max = Math.max.apply(null, TR.views.concat(TR.visits, [4])), step = Math.pow(10, Math.floor(Math.log10(max))), nice = Math.ceil(max / step) * step;
        var x = function (i) { return pl + (n < 2 ? 0 : i * (W - pl - pr) / (n - 1)); }, y = function (v) { return pt + (Hh - pt - pb) * (1 - v / nice); };
        var s = '<svg viewBox="0 0 ' + W + ' ' + Hh + '" role="img" aria-label="Visits and page views per day">';
        for (var g = 0; g <= 4; g++) { var gv = nice * g / 4, gy = y(gv); s += '<line x1="' + pl + '" x2="' + (W - pr) + '" y1="' + gy + '" y2="' + gy + '" stroke="' + col('--grid') + '"/><text x="' + (pl - 6) + '" y="' + (gy + 4) + '" text-anchor="end">' + nf(Math.round(gv)) + '</text>'; }
        var every = Math.max(1, Math.ceil(n / 7));
        for (var i = 0; i < n; i += every) s += '<text x="' + x(i) + '" y="' + (Hh - 6) + '" text-anchor="middle">' + esc(TR.labels[i]) + '</text>';
        [['views', '--s2'], ['visits', '--s1']].forEach(function (k) {
            var d = TR[k[0]].map(function (v, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1); }).join(' ');
            s += '<path d="' + d + '" fill="none" stroke="' + col(k[1]) + '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
        });
        s += '<line id="anX" y1="' + pt + '" y2="' + (Hh - pb) + '" stroke="' + col('--grid') + '" stroke-width="1.5" style="display:none"/>';
        s += '<circle id="anD1" r="4.5" fill="' + col('--s1') + '" stroke="var(--surface)" stroke-width="2" style="display:none"/><circle id="anD2" r="4.5" fill="' + col('--s2') + '" stroke="var(--surface)" stroke-width="2" style="display:none"/>';
        s += '<rect x="' + pl + '" y="0" width="' + (W - pl - pr) + '" height="' + Hh + '" fill="transparent" id="anHit"/></svg>';
        box.innerHTML = s;
        var hit = box.querySelector('#anHit'), xl = box.querySelector('#anX'), d1 = box.querySelector('#anD1'), d2 = box.querySelector('#anD2');
        hit.addEventListener('mousemove', function (e) {
            var r = box.getBoundingClientRect(), px = (e.clientX - r.left) * (W / r.width), i = Math.max(0, Math.min(n - 1, Math.round((px - pl) / ((W - pl - pr) / Math.max(1, n - 1)))));
            [xl, d1, d2].forEach(function (el) { el.style.display = ''; });
            xl.setAttribute('x1', x(i)); xl.setAttribute('x2', x(i));
            d1.setAttribute('cx', x(i)); d1.setAttribute('cy', y(TR.visits[i])); d2.setAttribute('cx', x(i)); d2.setAttribute('cy', y(TR.views[i]));
            show(e, '<b>' + esc(TR.labels[i]) + '</b><span class="sw" style="background:' + col('--s1') + '"></span>Visits ' + nf(TR.visits[i]) + '<br><span class="sw" style="background:' + col('--s2') + '"></span>Page views ' + nf(TR.views[i]));
        });
        hit.addEventListener('mouseleave', function () { [xl, d1, d2].forEach(function (el) { el.style.display = 'none'; }); hide(); });
    }
    if (box) { drawLine(); var rt; addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(drawLine, 150); }); }
    var tg = document.getElementById('axTheme'); if (tg) tg.addEventListener('click', function () { setTimeout(function () { css = getComputedStyle(document.querySelector('.an')); if (box) drawLine(); }, 30); });
})();
</script>
</body></html>
