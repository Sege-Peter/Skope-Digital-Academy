<?php
/** Staff dashboard: what needs attention in this person's area, their tools and recent items. Admins can preview any department. */
$pageTitle = 'My dashboard';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/staff.php';
sda_staff_ensure($pdo);

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$rec = $isAdmin ? null : sda_staff_record($pdo, (int)$user['id']);
$dept = $isAdmin ? (array_key_exists($_GET['dept'] ?? '', SDA_STAFF_DEPTS) ? $_GET['dept'] : 'academics') : ($rec['department'] ?? 'general');
$mods = $isAdmin ? SDA_STAFF_DEPTS[$dept][4] : ($rec['mods'] ?? []);
$D = SDA_STAFF_DEPTS[$dept] ?? SDA_STAFF_DEPTS['general'];
$has = fn(string $m) => in_array($m, $mods, true);
$n = function (string $sql, array $p = []) use ($pdo) { try { $st = $pdo->prepare($sql); $st->execute($p); return (int)$st->fetchColumn(); } catch (Throwable $e) { return 0; } };
$rows = function (string $sql) use ($pdo) { try { return $pdo->query($sql)->fetchAll(); } catch (Throwable $e) { return []; } };

// "Needs attention" and headline numbers, only for tools this person has
$todo = []; $kpis = [];
if ($has('courses'))      { $c = $n("SELECT COUNT(*) FROM courses WHERE status = 'pending'"); $todo[] = ['courses', $c, 'course' . ($c === 1 ? '' : 's') . ' waiting for approval', 'courses?status=pending']; $kpis[] = ['book', $n("SELECT COUNT(*) FROM courses WHERE status = 'published'"), 'Published courses', '']; }
if ($has('projects'))     { $c = $n("SELECT COUNT(*) FROM project_submissions WHERE status IN ('flagged','staff_review')"); $todo[] = ['projects', $c, 'project' . ($c === 1 ? '' : 's') . ' to review', 'projects']; }
if ($has('scholarships')) { $c = $n("SELECT COUNT(*) FROM scholarship_applications WHERE status = 'pending'"); $todo[] = ['scholarships', $c, 'scholarship application' . ($c === 1 ? '' : 's') . ' to decide', 'scholarships']; }
if ($has('tickets'))      { $c = $n("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'"); $todo[] = ['tickets', $c, 'open support ticket' . ($c === 1 ? '' : 's'), 'tickets']; }
if ($has('messages'))     { $c = $n("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'"); $todo[] = ['messages', $c, 'unread contact message' . ($c === 1 ? '' : 's'), 'messages']; }
if ($has('careers'))      { $c = $n("SELECT COUNT(*) FROM job_applications WHERE status = 'submitted' AND archived = 0"); $todo[] = ['careers', $c, 'assessment' . ($c === 1 ? '' : 's') . ' to mark', 'careers']; }
if ($has('enrollments'))  { $kpis[] = ['user-check', $n("SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= NOW() - INTERVAL 7 DAY"), 'New enrolments (7 days)', 'green']; $kpis[] = ['graduation-cap', $n("SELECT COUNT(*) FROM enrollments WHERE status = 'completed'"), 'Course completions', '']; }
if ($has('certificates')) $kpis[] = ['certificate', $n("SELECT COUNT(*) FROM certificates WHERE issued_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"), 'Certificates this month', 'violet'];
if ($has('reviews'))      $kpis[] = ['star', $n("SELECT COUNT(*) FROM course_ratings WHERE created_at >= NOW() - INTERVAL 30 DAY"), 'Reviews (30 days)', 'amber'];
if ($has('coupons'))      $kpis[] = ['ticket', $n("SELECT COUNT(*) FROM coupons WHERE active = 1 AND (expires_at IS NULL OR expires_at > NOW())"), 'Active coupons', 'amber'];
if ($has('announcements')) $kpis[] = ['bullhorn', $n("SELECT COUNT(*) FROM announcements WHERE (end_date IS NULL OR end_date >= CURDATE())"), 'Live announcements', ''];
if ($has('activity'))     $kpis[] = ['clock-rotate-left', $n("SELECT COUNT(*) FROM audit_log WHERE created_at >= CURDATE()"), 'Logged actions today', ''];
if ($has('email'))        $kpis[] = ['envelope-circle-check', $n("SELECT COUNT(*) FROM mail_queue WHERE status = 'failed' AND created_at >= NOW() - INTERVAL 7 DAY"), 'Failed emails (7 days)', 'amber'];
$kpis[] = ['users', $n("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'"), 'Active learners', 'violet'];
$kpis = array_slice($kpis, 0, 4);
$open = array_sum(array_column($todo, 1));

// Recent items (up to three lists)
$lists = [];
if ($has('tickets'))      $lists[] = ['Open support tickets', 'life-ring', 'tickets', $rows("SELECT t.subject AS t, u.name AS s, t.created_at AS d FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id WHERE t.status = 'open' ORDER BY t.created_at DESC LIMIT 5")];
if ($has('messages'))     $lists[] = ['Unread messages', 'envelope', 'messages', $rows("SELECT COALESCE(NULLIF(subject, ''), LEFT(message, 60)) AS t, name AS s, created_at AS d FROM contact_messages WHERE status = 'unread' ORDER BY created_at DESC LIMIT 5")];
if ($has('scholarships')) $lists[] = ['Scholarship applications', 'graduation-cap', 'scholarships', $rows("SELECT COALESCE(s.title, 'Scholarship') AS t, u.name AS s, a.created_at AS d FROM scholarship_applications a LEFT JOIN users u ON u.id = a.user_id LEFT JOIN scholarships s ON s.id = a.scholarship_id WHERE a.status = 'pending' ORDER BY a.created_at DESC LIMIT 5")];
if ($has('courses'))      $lists[] = ['Courses awaiting approval', 'book', 'courses?status=pending', $rows("SELECT c.title AS t, u.name AS s, c.created_at AS d FROM courses c LEFT JOIN users u ON u.id = c.tutor_id WHERE c.status = 'pending' ORDER BY c.created_at DESC LIMIT 5")];
if ($has('enrollments'))  $lists[] = ['Latest enrolments', 'user-check', 'enrollments', $rows("SELECT c.title AS t, u.name AS s, e.enrolled_at AS d FROM enrollments e JOIN users u ON u.id = e.student_id JOIN courses c ON c.id = e.course_id ORDER BY e.enrolled_at DESC LIMIT 5")];
if ($has('careers'))      $lists[] = ['Recruitment', 'briefcase', 'careers', $rows("SELECT j.title AS t, a.name AS s, a.created_at AS d FROM job_applications a JOIN job_vacancies j ON j.id = a.job_id WHERE a.archived = 0 ORDER BY a.created_at DESC LIMIT 5")];
if ($has('announcements')) $lists[] = ['Announcements', 'bullhorn', 'announcements', $rows("SELECT title AS t, '' AS s, created_at AS d FROM announcements ORDER BY created_at DESC LIMIT 5")];
$lists = array_slice($lists, 0, 3);

// Their role, from the matching job advert (if any)
$roleLines = [];
if (!empty($rec['job_title'])) {
    try { $st = $pdo->prepare("SELECT responsibilities FROM job_vacancies WHERE title = ? LIMIT 1"); $st->execute([$rec['job_title']]); $roleLines = sda_lines((string)$st->fetchColumn()); } catch (Throwable $e) {}
}
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$first = strtok($user['name'] ?? 'there', ' ');

require_once 'includes/sidebar.php';
?>
<style>
  .sh-hero { border-radius: 22px; padding: 26px 28px; color: #fff; position: relative; overflow: hidden; background: linear-gradient(135deg, #0b1324, #12305a); }
  .sh-hero::after { content: ''; position: absolute; width: 320px; height: 320px; right: -90px; top: -140px; border-radius: 50%; background: radial-gradient(circle, <?= e($D[2]) ?>55, transparent 70%); }
  .sh-hero h1 { font: 800 clamp(1.4rem, 3vw, 1.9rem) 'Poppins', sans-serif; margin: 8px 0 6px; color: #fff; }
  .sh-hero p { margin: 0; color: #cbd5e1; max-width: 640px; line-height: 1.6; }
  .sh-badge { display: inline-flex; gap: 8px; align-items: center; padding: 6px 12px; border-radius: 99px; font-size: .78rem; font-weight: 800; background: rgba(255,255,255,.1); color: #fff; }
  .sh-badge i { color: <?= e($D[2]) ?>; }
  .sh-sum { margin-top: 16px; display: inline-flex; gap: 10px; align-items: center; background: rgba(255,255,255,.08); padding: 10px 14px; border-radius: 12px; font-weight: 700; position: relative; z-index: 1; }
  .sh-todo { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 12px; }
  .sh-t { display: flex; gap: 14px; align-items: center; padding: 16px; border-radius: 16px; border: 1px solid var(--border); background: var(--surface); text-decoration: none; color: inherit; transition: border-color .15s, transform .15s; }
  .sh-t:hover { border-color: <?= e($D[2]) ?>; transform: translateY(-2px); }
  .sh-t .ic { width: 44px; height: 44px; border-radius: 13px; display: grid; place-items: center; font-size: 1.05rem; flex: 0 0 auto; background: var(--surface-2); color: var(--text-dim); }
  .sh-t.hot .ic { background: <?= e($D[2]) ?>; color: #fff; }
  .sh-t b { display: block; font-size: 1.5rem; line-height: 1; color: var(--text-main); } .sh-t span { font-size: .82rem; color: var(--text-dim); }
  .sh-t.done b { color: #10b981; font-size: 1rem; }
  .sh-tools { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 10px; }
  .sh-tool { display: flex; gap: 12px; padding: 14px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); text-decoration: none; color: inherit; align-items: flex-start; }
  .sh-tool:hover { border-color: <?= e($D[2]) ?>; }
  .sh-tool i { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: var(--surface-2); color: <?= e($D[2]) ?>; flex: 0 0 auto; }
  .sh-tool b { display: block; color: var(--text-main); font-size: .9rem; } .sh-tool small { color: var(--text-muted); font-size: .78rem; line-height: 1.4; }
  .sh-lists { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
  .sh-li { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border-light); font-size: .86rem; }
  .sh-li:last-child { border-bottom: 0; } .sh-li b { color: var(--text-main); display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px; } .sh-li small { color: var(--text-muted); }
  .sh-role { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; font-size: .88rem; }
  .sh-role li { display: flex; gap: 10px; } .sh-role i { color: <?= e($D[2]) ?>; margin-top: 4px; font-size: .75rem; }
  .sh-prev { display: flex; gap: 6px; flex-wrap: wrap; }
</style>
<main class="main-content">
    <?php ax_header('My dashboard', '', $D[0], $isAdmin ? '<span style="font-size:.8rem;color:var(--text-muted)">Admin preview</span>' : ''); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <?php if ($isAdmin): ?>
        <section class="ax-panel" style="padding:14px 18px">
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap"><b style="font-size:.86rem"><i class="fas fa-eye"></i> Preview a department's dashboard:</b>
                <div class="sh-prev"><?php foreach (SDA_STAFF_DEPTS as $k => $d): if ($k === 'general') continue; ?><a class="ax-btn <?= $k === $dept ? '' : 'ghost' ?> sm" href="staff-home?dept=<?= $k ?>"><?= e($d[0]) ?></a><?php endforeach; ?></div>
                <a class="ax-btn ghost sm" href="staff" style="margin-left:auto"><i class="fas fa-id-badge"></i> Manage staff</a></div>
        </section>
        <?php endif; ?>

        <section class="sh-hero">
            <span class="sh-badge"><i class="fas fa-<?= e($D[1]) ?>"></i> <?= e($D[0]) ?><?= !empty($rec['job_title']) ? ' · ' . e($rec['job_title']) : '' ?></span>
            <h1><?= e($greet) ?>, <?= e($isAdmin ? 'preview' : $first) ?> 👋</h1>
            <p><?= e($D[3]) ?></p>
            <div class="sh-sum"><i class="fas fa-<?= $open ? 'list-check' : 'circle-check' ?>"></i> <?= $open ? $open . ' item' . ($open === 1 ? '' : 's') . ' need' . ($open === 1 ? 's' : '') . ' your attention' : ($todo ? 'All caught up – nothing waiting' : 'Welcome to your dashboard') ?> · <?= e(date('l, j F')) ?></div>
        </section>

        <?php if (!$mods): ?>
        <section class="ax-panel"><div class="ax-empty"><i class="fas fa-toolbox"></i><b>No tools assigned yet</b>An admin will choose which tools you can use. Once they do, they'll appear here.</div></section>
        <?php endif; ?>

        <?php if ($todo): ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-bell"></i> Needs attention</h2><p>Click a card to go straight to the work.</p></div></div>
            <div class="ax-panel-b"><div class="sh-todo">
                <?php foreach ($todo as [$m, $c, $label, $href]): ?>
                <a class="sh-t <?= $c ? 'hot' : 'done' ?>" href="<?= e($href) ?>"><span class="ic"><i class="fas fa-<?= e(SDA_STAFF_MODULES[$m][1]) ?>"></i></span>
                    <div><?= $c ? '<b>' . $c . '</b><span>' . e($label) . '</span>' : '<b><i class="fas fa-check"></i> All clear</b><span>No ' . e(preg_replace('/^[a-z]+\s/', '', $label) ?: $label) . '</span>' ?></div></a>
                <?php endforeach; ?>
            </div></div>
        </section>
        <?php endif; ?>

        <div class="ax-stats"><?php foreach ($kpis as [$ic, $v, $l, $col]): ?><?= ax_stat($ic, number_format($v), $l, '', $col) ?><?php endforeach; ?></div>

        <?php if ($lists): ?>
        <div class="sh-lists">
            <?php foreach ($lists as [$title, $ic, $href, $items]): ?>
            <section class="ax-panel" style="margin:0">
                <div class="ax-panel-h"><div><h2><i class="fas fa-<?= e($ic) ?>"></i> <?= e($title) ?></h2></div><a class="ax-btn ghost sm" href="<?= e($href) ?>">Open</a></div>
                <div class="ax-panel-b" style="padding-top:4px">
                    <?php if (!$items): ?><p style="color:var(--text-muted);font-size:.85rem;margin:8px 0">Nothing here right now.</p><?php endif; ?>
                    <?php foreach ($items as $it): ?><div class="sh-li"><div style="min-width:0"><b><?= e($it['t']) ?></b><?php if ($it['s']): ?><small><?= e($it['s']) ?></small><?php endif; ?></div><small style="white-space:nowrap"><?= e(ax_date($it['d'], 'M j')) ?></small></div><?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($mods): ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-toolbox"></i> Your tools</h2><p>Everything you have access to. Ask an admin if you need something else.</p></div></div>
            <div class="ax-panel-b"><div class="sh-tools">
                <?php foreach ($mods as $m): $M = SDA_STAFF_MODULES[$m]; ?>
                <a class="sh-tool" href="<?= e(basename($M[2][0], '.php')) ?>"><i class="fas fa-<?= e($M[1]) ?>"></i><div><b><?= e($M[0]) ?></b><small><?= e($M[3]) ?></small></div></a>
                <?php endforeach; ?>
                <a class="sh-tool" href="profile"><i class="fas fa-user-gear"></i><div><b>My profile</b><small>Your name, photo and password</small></div></a>
            </div></div>
        </section>
        <?php endif; ?>

        <?php if ($roleLines): ?>
        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-bullseye"></i> Your role</h2><p><?= e($rec['job_title']) ?></p></div></div>
            <div class="ax-panel-b"><ul class="sh-role"><?php foreach ($roleLines as $l): ?><li><i class="fas fa-circle-check"></i><span><?= e($l) ?></span></li><?php endforeach; ?></ul></div>
        </section>
        <?php endif; ?>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
