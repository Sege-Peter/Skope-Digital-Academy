<?php
/** Admin dashboard – what needs attention, how learning is going, money, and recent activity. */
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

$q = fn(string $sql, array $p = []) => ax_scalar($pdo, $sql, $p);
$rows = function (string $sql, array $p = []) use ($pdo): array {
    try { $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchAll(); } catch (Throwable $e) { return []; }
};

// ── Needs attention ──────────────────────────────────────────
$attention = [
    ['verifications', 'shield-halved', 'payments to approve', $q("SELECT COUNT(*) FROM payments WHERE status = 'pending'"), 'amber'],
    ['courses?status=pending', 'book', 'courses awaiting review', $q("SELECT COUNT(*) FROM courses WHERE status = 'pending'"), 'violet'],
    ['messages?filter=unread', 'inbox', 'unread messages', $q("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'"), ''],
    ['tickets', 'headset', 'open support tickets', $q("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'"), 'red'],
    ['scholarships', 'graduation-cap', 'scholarship applications', $q("SELECT COUNT(*) FROM scholarship_applications WHERE status = 'pending'"), 'green'],
    ['users?status=pending', 'user-clock', 'accounts pending activation', $q("SELECT COUNT(*) FROM users WHERE status = 'pending'"), 'slate'],
];
$attentionTotal = array_sum(array_column($attention, 3));

// ── Headline numbers ─────────────────────────────────────────
$revAll    = $q("SELECT SUM(amount) FROM payments WHERE status = 'verified'");
$revMonth  = $q("SELECT SUM(amount) FROM payments WHERE status = 'verified' AND COALESCE(verified_at, created_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$certRev   = $q("SELECT SUM(amount) FROM payments WHERE status = 'verified' AND payment_type = 'certificate'");
$learners  = $q("SELECT COUNT(*) FROM users WHERE role = 'student'");
$newLearn  = $q("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= NOW() - INTERVAL 30 DAY");
$tutors    = $q("SELECT COUNT(*) FROM users WHERE role = 'tutor'");
$active7   = $q("SELECT COUNT(DISTINCT le.user_id) FROM learning_events le JOIN users u ON u.id = le.user_id AND u.role = 'student' WHERE le.created_at >= NOW() - INTERVAL 7 DAY");
$lessons30 = $q("SELECT COUNT(*) FROM learning_events WHERE event_type = 'lesson_complete' AND created_at >= NOW() - INTERVAL 30 DAY");
$enrolls   = $q("SELECT COUNT(*) FROM enrollments WHERE status IN ('active','completed')");
$completed = $q("SELECT COUNT(*) FROM enrollments WHERE status = 'completed' OR progress_percent >= 100");
$certsOk   = $q("SELECT COUNT(*) FROM certificates WHERE status = 'approved'");
$certsWait = $q("SELECT COUNT(*) FROM certificates WHERE status = 'pending'");
$liveByType = [
    'paid'     => $q("SELECT COUNT(*) FROM courses WHERE status = 'published' AND source IS NULL"),
    'free'     => $q("SELECT COUNT(*) FROM courses WHERE status = 'published' AND source = 'course'"),
    'tutorial' => $q("SELECT COUNT(*) FROM courses WHERE status = 'published' AND source = 'tutorial'"),
];
$avgRating = $q("SELECT ROUND(AVG(rating), 1) FROM course_ratings");
$reviewsN  = $q("SELECT COUNT(*) FROM course_ratings");

// ── Charts: daily active learners (14 days) and revenue (6 months) ──
$dailyRaw = [];
foreach ($rows("SELECT DATE(created_at) d, COUNT(DISTINCT user_id) n FROM learning_events
                WHERE created_at >= CURDATE() - INTERVAL 13 DAY GROUP BY DATE(created_at)") as $r) $dailyRaw[$r['d']] = (int)$r['n'];
$daily = [];
for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $daily[$d] = $dailyRaw[$d] ?? 0; }
$dailyMax = max(1, max($daily));

$monthRaw = [];
foreach ($rows("SELECT DATE_FORMAT(COALESCE(verified_at, created_at), '%Y-%m') m, SUM(amount) s FROM payments
                WHERE status = 'verified' AND COALESCE(verified_at, created_at) >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH, '%Y-%m-01')
                GROUP BY m") as $r) $monthRaw[$r['m']] = (float)$r['s'];
$months = [];
for ($i = 5; $i >= 0; $i--) { $m = date('Y-m', strtotime(date('Y-m-01') . " -$i month")); $months[$m] = $monthRaw[$m] ?? 0; }
$monthMax = max(1, max($months));

// ── Lists ────────────────────────────────────────────────────
$topCourses = $rows("SELECT c.id, c.title, c.source, c.is_open_tutorial,
        COUNT(e.id) enrolled,
        SUM(e.status = 'completed' OR e.progress_percent >= 100) done,
        ROUND(AVG(e.progress_percent)) avg_progress
    FROM courses c JOIN enrollments e ON e.course_id = c.id
    GROUP BY c.id ORDER BY enrolled DESC, done DESC LIMIT 6");
$feed = $rows("SELECT le.event_type, le.xp, le.created_at, u.id uid, u.name, u.avatar, c.title course
    FROM learning_events le JOIN users u ON u.id = le.user_id LEFT JOIN courses c ON c.id = le.course_id
    ORDER BY le.id DESC LIMIT 8");
$recentUsers = $rows("SELECT id, name, email, role, status, avatar, created_at, last_login, xp FROM users ORDER BY created_at DESC LIMIT 6");
$recentPay = $rows("SELECT p.id, p.amount, p.status, p.payment_type, p.created_at, p.verified_at, u.name, c.title course
    FROM payments p LEFT JOIN users u ON u.id = p.student_id LEFT JOIN courses c ON c.id = p.course_id
    ORDER BY p.created_at DESC LIMIT 6");

$eventLabel = [
    'lesson_complete' => ['circle-check', 'finished a lesson'], 'lesson_view' => ['eye', 'opened a lesson'],
    'quiz_pass' => ['clipboard-check', 'passed a quiz'], 'quiz_perfect' => ['star', 'aced a quiz'], 'quiz_attempt' => ['clipboard-question', 'tried a quiz'],
    'exercise_pass' => ['pen-to-square', 'solved an exercise'], 'course_complete' => ['flag-checkered', 'completed a course'],
    'badge' => ['medal', 'earned a badge'], 'daily_streak' => ['fire', 'kept a streak going'], 'review' => ['star-half-stroke', 'reviewed a course'],
    'path_complete' => ['route', 'completed a learning path'],
];
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first = explode(' ', trim($user['name'] ?? 'Admin'))[0];

require_once 'includes/sidebar.php';
$actions = '<button type="button" class="ax-btn ghost sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>'
         . '<a class="ax-btn sm" href="announcements"><i class="fas fa-bullhorn"></i> Announce</a>';
?>
<style>
    .dash-hello { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
    .dash-hello h2 { font: 800 1.5rem Poppins, sans-serif; color: var(--text-main); letter-spacing: -.4px; }
    .dash-hello p { color: var(--text-dim); font-size: .9rem; margin-top: 4px; }
    .attn { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 26px; }
    .attn a { --c: var(--primary); display: flex; align-items: center; gap: 12px; padding: 14px 15px; background: var(--surface); border: 1px solid var(--border); border-radius: 14px; text-decoration: none; transition: border-color .15s, transform .15s; min-width: 0; }
    .attn a:hover { border-color: var(--c); transform: translateY(-2px); }
    .attn a.zero { opacity: .6; }
    .attn a.hot { border-color: color-mix(in srgb, var(--c) 45%, transparent); background: linear-gradient(0deg, color-mix(in srgb, var(--c) 9%, transparent), color-mix(in srgb, var(--c) 9%, transparent)), var(--surface); }
    .attn .go { margin-left: auto; color: var(--text-muted); font-size: .75rem; }
    .attn a.hot .go { color: var(--c); }
    .attn .ic { width: 38px; height: 38px; border-radius: 11px; display: flex; align-items: center; justify-content: center; color: var(--c); background: color-mix(in srgb, var(--c) 15%, transparent); flex-shrink: 0; }
    .attn b { display: block; font: 800 1.3rem Inter, sans-serif; color: var(--text-main); line-height: 1.1; }
    .attn small { display: block; font-size: .74rem; color: var(--text-dim); font-weight: 600; line-height: 1.3; }
    .attn a.amber { --c: var(--warning); } .attn a.violet { --c: var(--accent); } .attn a.red { --c: var(--danger); } .attn a.green { --c: var(--success); } .attn a.slate { --c: #8592ad; }
    .chart-legend { display: flex; gap: 16px; font-size: .75rem; color: var(--text-dim); font-weight: 600; }
    .feed-ic { width: 34px; height: 34px; border-radius: 10px; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .85rem; }
    .feed-t { font-size: .86rem; color: var(--text-dim); line-height: 1.4; }
    .feed-t b { color: var(--text-main); }
    .xp-chip { font-size: .7rem; font-weight: 800; color: var(--warning); background: color-mix(in srgb, var(--warning) 15%, transparent); border-radius: 20px; padding: 2px 8px; white-space: nowrap; }
    .quick { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .quick a { display: flex; align-items: center; gap: 10px; padding: 12px; border: 1px solid var(--border); border-radius: 12px; text-decoration: none; color: var(--text-main); font-weight: 600; font-size: .82rem; background: var(--surface-2); }
    .quick a:hover { border-color: var(--secondary); color: var(--secondary); }
    .quick a i { color: var(--secondary); width: 18px; text-align: center; }
    .dash-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    @media (max-width: 1100px) { .dash-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .print-head { display: none; }
    @media print { .print-head { display: block; margin-bottom: 20px; } .attn, .quick { display: none; } }
    @media (max-width: 640px) { .attn { grid-template-columns: repeat(2, minmax(0, 1fr)); } .dash-hello h2 { font-size: 1.2rem; } }
</style>

<main class="main-content">
    <?php ax_header('Dashboard', '', 'Welcome back – here is what is happening at the academy today', $actions); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="print-head"><h1 style="font:800 1.6rem Poppins,sans-serif">Skope Digital Academy – summary</h1><p>Printed <?= date('j M Y, H:i') ?></p></div>

        <div class="ax-hero">
            <div style="position:relative;z-index:1">
                <h2><?= e($greet . ', ' . $first) ?> 👋</h2>
                <p><?= $attentionTotal ? e($attentionTotal . ' item' . ($attentionTotal == 1 ? '' : 's') . ' need your attention.') : 'Nothing is waiting on you right now.' ?>
                   <?= e(' ' . number_format((float)$active7) . ' learner' . ($active7 == 1 ? ' was' : 's were') . ' active this week · ' . number_format((float)$lessons30) . ' lessons finished in 30 days.') ?></p>
            </div>
            <div class="ax-hero-actions">
                <a class="ax-btn white" href="verifications"><i class="fas fa-shield-halved"></i> Payment approvals<?php $pp = (int)$attention[0][3]; if ($pp): ?> <span class="n"><?= $pp ?></span><?php endif; ?></a>
                <a class="ax-btn glass" href="courses"><i class="fas fa-book"></i> Courses</a>
                <a class="ax-btn glass" href="../" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> View website</a>
            </div>
        </div>

        <div class="ax-section">Needs attention</div>
        <div class="attn">
            <?php foreach ($attention as [$href, $icon, $label, $n, $color]): ?>
            <a href="<?= e($href) ?>" class="<?= e($color) ?> <?= $n ? 'hot' : 'zero' ?>"><span class="ic"><i class="fas fa-<?= e($icon) ?>"></i></span>
               <span style="min-width:0"><b><?= (int)$n ?></b><small><?= e($label) ?></small></span><i class="fas fa-chevron-right go"></i></a>
            <?php endforeach; ?>
        </div>

        <div class="ax-section">Key numbers</div>
        <div class="ax-stats dash-stats">
            <?= ax_stat('sack-dollar', ax_money($revAll), 'Total revenue', ax_money($revMonth) . ' this month · ' . ax_money($certRev) . ' from certificates', 'green') ?>
            <?= ax_stat('user-graduate', number_format((float)$learners), 'Learners', '+' . number_format((float)$newLearn) . ' in 30 days · ' . number_format((float)$tutors) . ' tutors') ?>
            <?= ax_stat('bolt', number_format((float)$active7), 'Active this week', number_format((float)$lessons30) . ' lessons finished in 30 days', 'orange') ?>
            <?= ax_stat('certificate', number_format((float)$certsOk), 'Certificates', number_format((float)$certsWait) . ' awaiting payment', 'violet') ?>
            <?= ax_stat('book-open', number_format(array_sum($liveByType)), 'Live courses', $liveByType['paid'] . ' paid · ' . $liveByType['free'] . ' free · ' . $liveByType['tutorial'] . ' tutorials', 'slate') ?>
            <?= ax_stat('star', $reviewsN ? $avgRating . ' / 5' : '—', 'Average rating', number_format((float)$reviewsN) . ' reviews · ' . number_format((float)$enrolls) . ' enrolments', 'amber') ?>
        </div>

        <div class="ax-grid" style="margin-bottom:24px">
            <section class="ax-panel ax-span-7">
                <div class="ax-panel-h"><div><h2><i class="fas fa-chart-column"></i> Learners active per day</h2><p>Distinct learners who did anything in a lesson, last 14 days.</p></div></div>
                <div class="ax-panel-b">
                    <div class="ax-chart">
                        <?php foreach ($daily as $d => $n): ?>
                        <div class="col" title="<?= e(date('D j M', strtotime($d)) . ': ' . $n) ?>"><em><?= $n ?: '' ?></em><i style="height:<?= round($n / $dailyMax * 100) ?>%"></i><span><?= date('j', strtotime($d)) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <section class="ax-panel ax-span-5">
                <div class="ax-panel-h"><div><h2><i class="fas fa-coins"></i> Revenue by month</h2><p>Verified payments, last 6 months.</p></div><a class="ax-btn ghost sm" href="revenue">Details</a></div>
                <div class="ax-panel-b">
                    <div class="ax-chart">
                        <?php foreach ($months as $m => $s): ?>
                        <div class="col alt" title="<?= e(date('F Y', strtotime($m . '-01')) . ': ' . ax_money($s)) ?>"><em><?= $s ? e(number_format($s / 1000, $s < 10000 ? 1 : 0) . 'k') : '' ?></em><i style="height:<?= round($s / $monthMax * 100) ?>%"></i><span><?= date('M', strtotime($m . '-01')) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>

        <div class="ax-grid" style="margin-bottom:24px">
            <section class="ax-panel ax-span-7">
                <div class="ax-panel-h"><div><h2><i class="fas fa-trophy"></i> Most popular courses</h2><p>By enrolments, with how far learners have got.</p></div><a class="ax-btn ghost sm" href="courses">All courses</a></div>
                <div class="ax-panel-b flush">
                    <?php if (!$topCourses): ?>
                        <div class="ax-empty"><i class="fas fa-book-open"></i><b>No enrolments yet</b>Courses appear here once learners join them.</div>
                    <?php else: ?>
                    <div class="ax-table-wrap"><table class="ax-table cards">
                        <thead><tr><th>Course</th><th>Learners</th><th>Average progress</th><th>Completed</th></tr></thead>
                        <tbody>
                        <?php foreach ($topCourses as $c):
                            $type = $c['source'] === 'tutorial' ? ['Tutorial', 'info'] : ($c['source'] === 'course' ? ['Free course', 'ok'] : ['Paid course', 'violet']); ?>
                            <tr>
                                <td data-label="Course"><a href="course_details?id=<?= (int)$c['id'] ?>" class="strong" style="text-decoration:none"><?= e($c['title']) ?></a><div style="margin-top:4px"><span class="ax-pill <?= $type[1] ?>"><?= $type[0] ?></span></div></td>
                                <td data-label="Learners" class="num strong"><?= (int)$c['enrolled'] ?></td>
                                <td data-label="Average progress" style="min-width:150px"><div style="display:flex;align-items:center;gap:10px"><div class="ax-bar" style="flex:1"><i style="width:<?= (int)$c['avg_progress'] ?>%"></i></div><span class="num"><?= (int)$c['avg_progress'] ?>%</span></div></td>
                                <td data-label="Completed" class="num"><?= (int)$c['done'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                    <?php endif; ?>
                </div>
            </section>
            <section class="ax-panel ax-span-5">
                <div class="ax-panel-h"><div><h2><i class="fas fa-wave-square"></i> Latest learning activity</h2><p>Tracked automatically as people learn.</p></div><a class="ax-btn ghost sm" href="leaderboard">Leaderboards</a></div>
                <div class="ax-panel-b" style="padding-top:6px;padding-bottom:6px">
                    <?php if (!$feed): ?>
                        <div class="ax-empty"><i class="fas fa-wave-square"></i><b>No activity yet</b>It shows up as soon as someone opens a lesson.</div>
                    <?php else: ?>
                    <ul class="ax-list">
                        <?php foreach ($feed as $f): [$ic, $txt] = $eventLabel[$f['event_type']] ?? ['circle', str_replace('_', ' ', $f['event_type'])]; ?>
                        <li>
                            <span class="feed-ic"><i class="fas fa-<?= e($ic) ?>"></i></span>
                            <div class="grow feed-t"><a href="user-profile?id=<?= (int)$f['uid'] ?>" style="text-decoration:none"><b><?= e($f['name']) ?></b></a> <?= e($txt) ?><?= $f['course'] ? ' in <b>' . e($f['course']) . '</b>' : '' ?>
                                <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px"><?= e(ax_ago($f['created_at'])) ?></div></div>
                            <?php if ((int)$f['xp'] > 0): ?><span class="xp-chip">+<?= (int)$f['xp'] ?> XP</span><?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="ax-grid">
            <section class="ax-panel ax-span-6">
                <div class="ax-panel-h"><div><h2><i class="fas fa-user-plus"></i> Newest accounts</h2></div><a class="ax-btn ghost sm" href="users">All users</a></div>
                <div class="ax-panel-b flush">
                    <div class="ax-table-wrap"><table class="ax-table cards">
                        <thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td data-label=""><a href="user-profile?id=<?= (int)$u['id'] ?>" class="ax-who" style="text-decoration:none"><?= ax_avatar($u['name'], $u['avatar']) ?><div><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?></small></div></a></td>
                                <td data-label="Role"><span class="ax-pill <?= $u['role'] === 'admin' ? 'dark' : ($u['role'] === 'tutor' ? 'violet' : 'info') ?>"><?= e($u['role']) ?></span></td>
                                <td data-label="Status"><span class="ax-pill <?= $u['status'] === 'active' ? 'ok' : ($u['status'] === 'pending' ? 'warn' : 'bad') ?>"><?= e($u['status']) ?></span></td>
                                <td data-label="Joined" class="num"><?= ax_date($u['created_at'], 'j M Y') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div>
            </section>
            <section class="ax-panel ax-span-6">
                <div class="ax-panel-h"><div><h2><i class="fas fa-receipt"></i> Latest payments</h2></div><a class="ax-btn ghost sm" href="payments">Ledger</a></div>
                <div class="ax-panel-b flush">
                    <?php if (!$recentPay): ?>
                        <div class="ax-empty"><i class="fas fa-receipt"></i><b>No payments yet</b></div>
                    <?php else: ?>
                    <div class="ax-table-wrap"><table class="ax-table cards">
                        <thead><tr><th>Learner</th><th>For</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentPay as $p): ?>
                            <tr>
                                <td data-label="Learner"><span class="strong"><?= e($p['name'] ?? 'Deleted account') ?></span><div class="muted"><?= e(ax_ago($p['created_at'])) ?></div></td>
                                <td data-label="For"><?= $p['payment_type'] === 'certificate' ? '<span class="ax-pill warn">Certificate</span> ' : '' ?><?= e($p['course'] ?? '—') ?></td>
                                <td data-label="Amount" class="num strong"><?= ax_money($p['amount']) ?></td>
                                <td data-label="Status"><span class="ax-pill <?= $p['status'] === 'verified' ? 'ok' : ($p['status'] === 'pending' ? 'warn' : 'bad') ?>"><?= e($p['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                    <?php endif; ?>
                </div>
            </section>
            <section class="ax-panel ax-span-12">
                <div class="ax-panel-h"><div><h2><i class="fas fa-bolt"></i> Shortcuts</h2></div></div>
                <div class="ax-panel-b">
                    <div class="quick" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                        <a href="courses"><i class="fas fa-book"></i> Manage courses</a>
                        <a href="content"><i class="fas fa-book-open-reader"></i> Import lessons</a>
                        <a href="course-pricing"><i class="fas fa-money-bill-wave"></i> Set prices</a>
                        <a href="certificates"><i class="fas fa-certificate"></i> Certificates</a>
                        <a href="badges"><i class="fas fa-medal"></i> Award a badge</a>
                        <a href="learning-settings"><i class="fas fa-sliders"></i> Certificate fee</a>
                        <a href="users"><i class="fas fa-users"></i> Users</a>
                        <a href="settings"><i class="fas fa-cog"></i> Platform settings</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
