<?php
/** Admin: one person's full profile – identity, account actions, learning record, payments, courses taught. */
$pageTitle = 'User Profile';
require_once 'includes/header.php';

$uid = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$uid]);
$u = $st->fetch();
if (!$u) { ax_flash('err', 'That account could not be found. It may have been deleted.'); ax_back('users'); }

$me     = (int)$user['id'];
$isSelf = $uid === $me;
$activeAdmins = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'");
$protect = $u['role'] === 'admin' && ($isSelf || ($u['status'] === 'active' && $activeAdmins <= 1));
$back = 'user-profile?id=' . $uid;

$lv = xp_level((int)$u['xp']);
$stats = user_stats($pdo, $uid);

$q = function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); };

$enrolments = $q("SELECT e.*, c.title, c.slug, c.source, c.id AS cid,
        (SELECT MAX(le.created_at) FROM learning_events le WHERE le.user_id = e.student_id AND le.course_id = e.course_id) AS last_activity
    FROM enrollments e LEFT JOIN courses c ON c.id = e.course_id WHERE e.student_id = ? ORDER BY e.enrolled_at DESC", [$uid]);
$badges = $q("SELECT b.name, b.description, b.icon, b.color, b.kind, sb.awarded_at, sb.awarded_by_role
    FROM student_badges sb JOIN badges b ON b.id = sb.badge_id WHERE sb.student_id = ? ORDER BY sb.awarded_at DESC", [$uid]);
$certs = $q("SELECT ce.*, c.title AS course_title, lp.title AS path_title FROM certificates ce
    LEFT JOIN courses c ON c.id = ce.course_id LEFT JOIN learning_paths lp ON lp.id = ce.path_id
    WHERE ce.student_id = ? ORDER BY ce.issued_at DESC", [$uid]);
$events = $q("SELECT le.*, c.title AS course_title, l.title AS lesson_title FROM learning_events le
    LEFT JOIN courses c ON c.id = le.course_id LEFT JOIN lessons l ON l.id = le.lesson_id
    WHERE le.user_id = ? ORDER BY le.created_at DESC, le.id DESC LIMIT 25", [$uid]);
$badgeNames = [];
foreach ($pdo->query("SELECT id, name FROM badges")->fetchAll() as $b) $badgeNames[(int)$b['id']] = $b['name'];
foreach ($events as &$ev) {
    if ($ev['event_type'] === 'badge' && preg_match('/^badge:(\d+)/', (string)$ev['event_key'], $m) && isset($badgeNames[(int)$m[1]])) {
        $ev['lesson_title'] = $badgeNames[(int)$m[1]];
    }
}
unset($ev);
$payments = $q("SELECT p.*, c.title AS course_title FROM payments p LEFT JOIN courses c ON c.id = p.course_id
    WHERE p.student_id = ? ORDER BY p.created_at DESC LIMIT 50", [$uid]);
$taught = $u['role'] === 'tutor' || ax_scalar($pdo, "SELECT COUNT(*) FROM courses WHERE tutor_id = ?", [$uid])
    ? $q("SELECT c.id, c.title, c.status, c.source, c.price, c.enrolled_count, c.avg_rating, c.created_at,
            (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students
          FROM courses c WHERE c.tutor_id = ? ORDER BY c.created_at DESC", [$uid]) : [];
$paidTotal = array_sum(array_map(fn($p) => $p['status'] === 'verified' ? (float)$p['amount'] : 0, $payments));
$completed = count(array_filter($enrolments, fn($e) => $e['status'] === 'completed'));
$tickets = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM support_tickets WHERE user_id = ?", [$uid]);

$eventLabels = [
    'lesson_view' => ['eye', 'Opened a lesson'], 'lesson_complete' => ['circle-check', 'Completed a lesson'],
    'quiz_attempt' => ['clipboard-question', 'Tried a quiz'], 'quiz_pass' => ['clipboard-check', 'Passed a quiz'],
    'quiz_perfect' => ['star', 'Perfect quiz score'], 'exercise_pass' => ['code', 'Passed an exercise'],
    'course_complete' => ['flag-checkered', 'Finished a course'], 'daily_streak' => ['fire', 'Kept the daily streak'],
    'badge' => ['medal', 'Earned a badge'], 'review' => ['star-half-stroke', 'Reviewed a course'],
];
$rolePill = ['admin' => 'bad', 'tutor' => 'info', 'student' => '', 'staff' => 'violet'];
$statusPill = ['active' => 'ok', 'pending' => 'warn', 'suspended' => 'bad'];
$payPill = ['verified' => 'ok', 'pending' => 'warn', 'failed' => 'bad'];
$certPill = ['approved' => 'ok', 'pending' => 'warn', 'revoked' => 'bad'];

function up_badge_icon(?string $icon): string {
    $icon = (string)$icon;
    if (preg_match('/^fa-[a-z0-9-]+$/', $icon)) return '<i class="fas ' . e($icon) . '"></i>';
    if ($icon === '' || str_contains($icon, '?')) return '<i class="fas fa-medal"></i>';
    return e($icon);
}

require_once 'includes/sidebar.php';
$actions = '<a class="ax-btn ghost" href="users"><i class="fas fa-arrow-left"></i> All users</a>';
?>
<style>
  .main-content { min-width: 0; }
  .ax-table th, .ax-table td { padding-left: 14px; padding-right: 14px; }
  .up-hero { text-align: center; padding: 30px 22px; }
  .up-hero .ax-avatar { width: 96px; height: 96px; border-radius: 28px; font-size: 2rem; margin-bottom: 14px; }
  .up-hero h2 { font: 800 1.25rem Poppins, sans-serif; color: var(--text-main); }
  .up-pills { display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; margin: 10px 0 6px; }
  .up-acts { display: grid; gap: 8px; margin-top: 18px; }
  .up-acts form { margin: 0; }
  .up-level { display: flex; gap: 16px; align-items: center; }
  .up-level .lv { width: 64px; height: 64px; border-radius: 18px; background: var(--grad-primary); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; font: 800 1.4rem Poppins, sans-serif; line-height: 1; }
  .up-level .lv small { font: 800 .55rem Inter, sans-serif; letter-spacing: 1px; text-transform: uppercase; opacity: .85; margin-bottom: 2px; }
  .up-level .ax-bar { height: 10px; margin: 8px 0 4px; }
  .up-mini { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px; margin-top: 18px; }
  .up-mini div { background: var(--surface-2); border: 1px solid var(--border-light); border-radius: 14px; padding: 12px; }
  .up-mini b { display: block; font: 800 1.15rem Poppins, sans-serif; color: var(--text-main); }
  .up-mini span { font-size: .68rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: .6px; }
  .up-badges { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
  .up-badge { display: flex; gap: 10px; align-items: center; border: 1px solid var(--border-light); border-radius: 14px; padding: 10px; min-width: 0; }
  .up-badge .ax-icon-badge { font-size: 1.05rem; }
  .up-badge b { display: block; font-size: .82rem; color: var(--text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .up-badge small { font-size: .7rem; color: var(--text-muted); }
  .up-tl { list-style: none; position: relative; }
  .up-tl li { display: flex; gap: 12px; padding: 9px 0; position: relative; }
  .up-tl li::before { content: ''; position: absolute; left: 15px; top: 40px; bottom: -9px; width: 2px; background: var(--border); }
  .up-tl li:last-child::before { display: none; }
  .up-tl .dot { width: 32px; height: 32px; border-radius: 10px; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: .8rem; flex-shrink: 0; z-index: 1; }
  .up-tl .t { flex: 1; min-width: 0; font-size: .86rem; color: var(--text-main); font-weight: 600; }
  .up-tl .t small { display: block; color: var(--text-muted); font-weight: 500; font-size: .75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .up-tl .xp { font-size: .72rem; font-weight: 800; color: var(--success); white-space: nowrap; }
  .up-bio { white-space: pre-wrap; line-height: 1.65; color: var(--text-dim); font-size: .9rem; }
</style>
<main class="main-content">
    <?php ax_header($u['name'], '', ucfirst($u['role']) . ' profile', $actions); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <?php if (!empty($_SESSION['usr_temp_pw'])): $tp = $_SESSION['usr_temp_pw']; unset($_SESSION['usr_temp_pw']); ?>
        <div class="ax-alert info" role="status"><i class="fas fa-key"></i><div>Temporary password for <?= e($tp['email']) ?>: <span class="ax-code" style="font-size:.95rem;user-select:all"><?= e($tp['pw']) ?></span><br><small>Shown only once. Share it privately – they must choose a new password when they sign in.</small></div></div>
        <?php endif; ?>

        <div class="ax-grid">
            <!-- Left: identity + account -->
            <div class="ax-span-4 ax-stack">
                <section class="ax-panel">
                    <div class="up-hero">
                        <?= ax_avatar($u['name'], $u['avatar']) ?>
                        <h2><?= e($u['name']) ?></h2>
                        <div class="up-pills">
                            <span class="ax-pill <?= $rolePill[$u['role']] ?>"><?= e(ucfirst($u['role'])) ?></span>
                            <span class="ax-pill <?= $statusPill[$u['status']] ?? '' ?>"><?= e(ucfirst($u['status'])) ?></span>
                            <?php if ($u['requires_password_change']): ?><span class="ax-pill warn"><i class="fas fa-key"></i> Temp password</span><?php endif; ?>
                            <?php if ($isSelf): ?><span class="ax-pill info">You</span><?php endif; ?>
                        </div>
                        <dl class="ax-kv" style="text-align:left;margin-top:18px">
                            <dt>Email</dt><dd><a href="mailto:<?= e($u['email']) ?>"><?= e($u['email']) ?></a><?= $u['email_verified'] ? ' <i class="fas fa-circle-check" style="color:var(--success)" title="Verified"></i>' : '' ?></dd>
                            <dt>Phone</dt><dd><?= $u['phone'] ? '<a href="tel:' . e($u['phone']) . '">' . e($u['phone']) . '</a>' : '—' ?></dd>
                            <dt>Admission no.</dt><dd><?= $u['admission_number'] ? '<span class="ax-code">' . e($u['admission_number']) . '</span>' : '—' ?></dd>
                            <dt>Joined</dt><dd><?= ax_date($u['created_at']) ?></dd>
                            <dt>Last sign-in</dt><dd><?= $u['last_login'] ? ax_date($u['last_login'], 'M j, Y g:i a') : 'Never' ?></dd>
                            <dt>Leaderboard</dt><dd><?= $u['show_on_leaderboard'] ? 'Shown' : 'Hidden' ?></dd>
                        </dl>
                        <div class="up-acts">
                            <?php if ($u['status'] !== 'active'): ?>
                            <form method="post" action="users"><?= ax_csrf() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="uid" value="<?= $uid ?>"><input type="hidden" name="status" value="active">
                                <button class="ax-btn ok block"><i class="fas fa-user-check"></i> <?= $u['status'] === 'pending' ? 'Approve account' : 'Re-activate account' ?></button></form>
                            <?php elseif (!$protect): ?>
                            <form method="post" action="users" data-confirm="Suspend <?= e($u['name']) ?>? They won't be able to sign in until re-activated." data-danger><?= ax_csrf() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="uid" value="<?= $uid ?>"><input type="hidden" name="status" value="suspended">
                                <button class="ax-btn danger block"><i class="fas fa-user-slash"></i> Suspend account</button></form>
                            <?php endif; ?>
                            <button type="button" class="ax-btn ghost block" data-open="dlgEdit"><i class="fas fa-pen"></i> Edit details &amp; role</button>
                            <form method="post" action="users" data-confirm="Reset <?= e($u['name']) ?>'s password? Their current password stops working immediately." data-danger><?= ax_csrf() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="uid" value="<?= $uid ?>">
                                <button class="ax-btn ghost block"><i class="fas fa-key"></i> Reset password</button></form>
                            <?php if ($u['role'] === 'student' && $u['status'] === 'active'): ?>
                            <form method="post" action="users" data-confirm="Open the student portal as <?= e($u['name']) ?>? Use “Stop” in the red banner to come back to your admin account."><?= ax_csrf() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="impersonate"><input type="hidden" name="uid" value="<?= $uid ?>">
                                <button class="ax-btn ghost block"><i class="fas fa-user-secret"></i> View the site as this student</button></form>
                            <?php endif; ?>
                            <?php if ($u['role'] !== 'admin'): ?>
                            <button type="button" class="ax-btn danger block" data-open="dlgDelete"><i class="fas fa-trash"></i> Delete account…</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><h2><i class="fas fa-id-card"></i> Personal details</h2></div>
                    <div class="ax-panel-b">
                        <dl class="ax-kv">
                            <dt>Gender</dt><dd><?= e($u['gender'] ? ucfirst($u['gender']) : '—') ?></dd>
                            <dt>Date of birth</dt><dd><?= ax_date($u['dob']) ?></dd>
                            <dt>County</dt><dd><?= e($u['county'] ?: '—') ?></dd>
                            <dt>Nationality</dt><dd><?= e($u['nationality'] ?: '—') ?></dd>
                            <dt>National ID</dt><dd><?= e($u['national_id'] ?: 'Not given') ?></dd>
                            <dt>Education</dt><dd><?= e($u['education_level'] ?: '—') ?></dd>
                            <dt>ID document</dt><dd><?= $u['id_document'] ? '<a href="../uploads/id_docs/' . e(rawurlencode($u['id_document'])) . '" target="_blank" rel="noopener">View upload <i class="fas fa-arrow-up-right-from-square"></i></a>' : '<span class="ax-pill">Not uploaded</span>' ?></dd>
                            <dt>Referral code</dt><dd><?= $u['referral_code'] ? '<span class="ax-code">' . e($u['referral_code']) . '</span>' : '—' ?></dd>
                            <dt>Merit points</dt><dd><?= number_format((int)$u['merit_points']) ?> · <?= number_format((float)$u['merit_coins'], 1) ?> coins</dd>
                            <dt>Support tickets</dt><dd><a href="tickets"><?= $tickets ?></a></dd>
                        </dl>
                        <?php if ($u['bio']): ?><h3 style="font:800 .72rem Inter,sans-serif;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin:18px 0 6px">Bio</h3><div class="up-bio"><?= e($u['bio']) ?></div><?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- Right: learning -->
            <div class="ax-span-8 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><h2><i class="fas fa-chart-line"></i> Learning summary</h2><span class="ax-pill violet"><?= e($lv['title']) ?></span></div>
                    <div class="ax-panel-b">
                        <div class="up-level">
                            <div class="lv"><small>Level</small><?= $lv['level'] ?></div>
                            <div style="flex:1;min-width:0">
                                <b style="color:var(--text-main)"><?= number_format($lv['xp']) ?> XP</b>
                                <div class="ax-bar"><i style="width:<?= $lv['pct'] ?>%"></i></div>
                                <small style="color:var(--text-muted)"><?= number_format(max(0, $lv['to'] - $lv['xp'])) ?> XP to level <?= $lv['level'] + 1 ?> (<?= $lv['pct'] ?>%)</small>
                            </div>
                        </div>
                        <div class="up-mini">
                            <div><b><?= (int)$u['streak_days'] ?> <i class="fas fa-fire" style="color:var(--secondary);font-size:.9rem"></i></b><span>Day streak</span></div>
                            <div><b><?= (int)$u['best_streak'] ?></b><span>Best streak</span></div>
                            <div><b><?= $stats['lessons'] ?></b><span>Lessons done</span></div>
                            <div><b><?= $stats['quizzes'] ?></b><span>Quizzes passed</span></div>
                            <div><b><?= $stats['exercises'] ?></b><span>Exercises</span></div>
                            <div><b><?= count($enrolments) ?> / <?= $completed ?></b><span>Enrolled / done</span></div>
                            <div><b><?= $stats['seconds'] >= 3600 ? round($stats['seconds'] / 3600, 1) . ' h' : round($stats['seconds'] / 60) . ' min' ?></b><span>Time learning</span></div>
                            <div><b><?= ax_money($paidTotal) ?></b><span>Paid (verified)</span></div>
                        </div>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><h2><i class="fas fa-book-open"></i> Enrolments</h2><span class="ax-pill"><?= count($enrolments) ?></span></div>
                    <?php if (!$enrolments): ?><div class="ax-empty"><i class="fas fa-book"></i><b>No enrolments yet</b></div><?php else: ?>
                    <div class="ax-table-wrap"><table class="ax-table cards">
                        <thead><tr><th>Course</th><th>Track</th><th>Progress</th><th>Status</th><th>Enrolled</th><th>Last activity</th></tr></thead>
                        <tbody><?php foreach ($enrolments as $en): $pct = (int)round((float)$en['progress_percent']); ?>
                        <tr>
                            <td data-label="Course"><?php if ($en['cid']): ?><a class="strong" href="../course-details?id=<?= (int)$en['cid'] ?>" target="_blank" rel="noopener"><?= e($en['title']) ?></a><?php else: ?><span class="muted">Deleted course</span><?php endif; ?></td>
                            <td data-label="Track"><span class="ax-pill <?= $en['learning_mode'] === 'structured' ? 'violet' : 'info' ?>"><?= e(mode_label((string)$en['learning_mode'])) ?></span></td>
                            <td data-label="Progress"><div style="display:flex;align-items:center;gap:8px;min-width:120px"><div class="ax-bar <?= $pct >= 100 ? 'green' : '' ?>" style="flex:1"><i style="width:<?= min(100, $pct) ?>%"></i></div><span class="num strong"><?= $pct ?>%</span></div></td>
                            <td data-label="Status"><span class="ax-pill <?= ['active' => 'info', 'completed' => 'ok', 'pending' => 'warn', 'cancelled' => 'bad'][$en['status']] ?? '' ?>"><?= e(ucfirst($en['status'])) ?></span></td>
                            <td data-label="Enrolled" class="num"><?= ax_date($en['enrolled_at']) ?></td>
                            <td data-label="Last activity" class="num"><?= ax_ago($en['last_activity']) ?></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div>
                    <?php endif; ?>
                </section>

                <div class="ax-grid">
                    <section class="ax-panel ax-span-6">
                        <div class="ax-panel-h"><h2><i class="fas fa-medal"></i> Badges</h2><span class="ax-pill"><?= count($badges) ?></span></div>
                        <div class="ax-panel-b">
                        <?php if (!$badges): ?><div class="ax-empty" style="padding:24px"><i class="fas fa-medal"></i><b>No badges yet</b></div><?php else: ?>
                            <div class="up-badges"><?php foreach ($badges as $b): $col = preg_match('/^#[0-9a-f]{3,8}$/i', (string)$b['color']) ? $b['color'] : '#0ea5e9'; ?>
                                <div class="up-badge" title="<?= e($b['description']) ?>"><span class="ax-icon-badge" style="background:<?= e($col) ?>1f;color:<?= e($col) ?>"><?= up_badge_icon($b['icon']) ?></span><div style="min-width:0"><b><?= e($b['name']) ?></b><small><?= ax_date($b['awarded_at']) ?><?= $b['awarded_by_role'] !== 'system' ? ' · by ' . e($b['awarded_by_role']) : '' ?></small></div></div>
                            <?php endforeach; ?></div>
                        <?php endif; ?>
                        </div>
                    </section>
                    <section class="ax-panel ax-span-6">
                        <div class="ax-panel-h"><h2><i class="fas fa-certificate"></i> Certificates</h2><a class="ax-btn ghost sm" href="certificates">Manage</a></div>
                        <div class="ax-panel-b">
                        <?php if (!$certs): ?><div class="ax-empty" style="padding:24px"><i class="fas fa-certificate"></i><b>No certificates yet</b></div><?php else: ?>
                            <ul class="ax-list"><?php foreach ($certs as $c): ?>
                                <li><span class="ax-icon-badge" style="background:color-mix(in srgb, var(--warning) 16%, transparent);color:var(--warning)"><i class="fas fa-<?= $c['certificate_type'] === 'path' ? 'route' : 'award' ?>"></i></span>
                                    <div class="grow"><b style="font-size:.86rem;color:var(--text-main)"><?= e($c['course_title'] ?? $c['path_title'] ?? 'Certificate') ?></b>
                                        <div style="font-size:.74rem;color:var(--text-muted)"><a href="../certificate?code=<?= e(urlencode($c['verification_code'])) ?>" target="_blank" rel="noopener" class="ax-code"><?= e($c['verification_code']) ?></a> · <?= ax_date($c['issued_at']) ?><?= (float)$c['fee'] > 0 ? ' · fee ' . ax_money($c['fee']) . ($c['paid_at'] ? ' paid' : ' unpaid') : ' · free' ?></div></div>
                                    <span class="ax-pill <?= $certPill[$c['status']] ?? '' ?>"><?= e($c['status'] === 'pending' ? 'Locked' : ucfirst($c['status'])) ?></span></li>
                            <?php endforeach; ?></ul>
                        <?php endif; ?>
                        </div>
                    </section>
                </div>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-clock-rotate-left"></i> Recent activity</h2><p>The last 25 learning events and the XP they earned.</p></div></div>
                    <div class="ax-panel-b">
                    <?php if (!$events): ?><div class="ax-empty" style="padding:24px"><i class="fas fa-clock"></i><b>No learning activity yet</b></div><?php else: ?>
                        <ul class="up-tl"><?php foreach ($events as $ev): [$ic, $lab] = $eventLabels[$ev['event_type']] ?? ['circle', ucfirst(str_replace('_', ' ', $ev['event_type']))]; ?>
                            <li><span class="dot"><i class="fas fa-<?= e($ic) ?>"></i></span>
                                <div class="t"><?= e($lab) ?><small><?= e(implode(' · ', array_filter([$ev['lesson_title'] ?? '', $ev['course_title'] ?? '', ax_ago($ev['created_at'])]))) ?></small></div>
                                <?php if ((int)$ev['xp']): ?><span class="xp">+<?= (int)$ev['xp'] ?> XP</span><?php endif; ?></li>
                        <?php endforeach; ?></ul>
                    <?php endif; ?>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><h2><i class="fas fa-receipt"></i> Payments</h2><a class="ax-btn ghost sm" href="payments">Ledger</a></div>
                    <?php if (!$payments): ?><div class="ax-empty"><i class="fas fa-receipt"></i><b>No payments</b></div><?php else: ?>
                    <div class="ax-table-wrap"><table class="ax-table cards">
                        <thead><tr><th>Date</th><th>For</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
                        <tbody><?php foreach ($payments as $p): ?>
                        <tr>
                            <td data-label="Date" class="num"><?= ax_date($p['created_at']) ?></td>
                            <td data-label="For"><?= e($p['course_title'] ?? '—') ?></td>
                            <td data-label="Type"><span class="ax-pill"><?= e(ucfirst((string)$p['payment_type'])) ?></span></td>
                            <td data-label="Amount" class="num strong"><?= ax_money($p['amount']) ?></td>
                            <td data-label="Status"><span class="ax-pill <?= $payPill[$p['status']] ?? '' ?>"><?= e(ucfirst((string)$p['status'])) ?></span></td>
                            <td data-label="Reference"><?= $p['paystack_reference'] ? '<span class="ax-code">' . e($p['paystack_reference']) . '</span>' : ($p['proof_file'] ? 'Proof uploaded' : '—') ?></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div>
                    <?php endif; ?>
                </section>

                <?php if ($taught): ?>
                <section class="ax-panel">
                    <div class="ax-panel-h"><h2><i class="fas fa-chalkboard-user"></i> Courses they own</h2><span class="ax-pill"><?= count($taught) ?></span></div>
                    <div class="ax-table-wrap"><table class="ax-table cards">
                        <thead><tr><th>Course</th><th>Status</th><th>Price</th><th>Students</th><th>Rating</th><th>Created</th></tr></thead>
                        <tbody><?php foreach ($taught as $c): ?>
                        <tr>
                            <td data-label="Course"><a class="strong" href="course_details?id=<?= (int)$c['id'] ?>"><?= e($c['title']) ?></a><?= $c['source'] ? '<div class="muted">' . ($c['source'] === 'tutorial' ? 'Free tutorial' : 'Free course') . '</div>' : '' ?></td>
                            <td data-label="Status"><span class="ax-pill <?= ['published' => 'ok', 'pending' => 'warn', 'archived' => 'dark'][$c['status']] ?? '' ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                            <td data-label="Price" class="num"><?= (float)$c['price'] > 0 ? ax_money($c['price']) : 'Free' ?></td>
                            <td data-label="Students" class="num"><?= (int)$c['students'] ?></td>
                            <td data-label="Rating" class="num"><?= (float)$c['avg_rating'] > 0 ? number_format((float)$c['avg_rating'], 1) . ' <i class="fas fa-star" style="color:var(--warning)"></i>' : '—' ?></td>
                            <td data-label="Created" class="num"><?= ax_date($c['created_at']) ?></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<dialog class="ax-modal" id="dlgEdit" aria-labelledby="dlgEditT">
    <div class="ax-panel-h"><h2 id="dlgEditT"><i class="fas fa-user-pen"></i> Edit <?= e($u['name']) ?></h2><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>
    <div class="ax-panel-b">
        <form method="post" action="users" class="ax-form">
            <?= ax_csrf() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="uid" value="<?= $uid ?>">
            <div class="ax-row">
                <div class="ax-field"><label for="e-name">Full name</label><input id="e-name" type="text" name="name" value="<?= e($u['name']) ?>" required maxlength="150"></div>
                <div class="ax-field"><label for="e-email">Email</label><input id="e-email" type="email" name="email" value="<?= e($u['email']) ?>" required></div>
                <div class="ax-field"><label for="e-phone">Phone</label><input id="e-phone" type="text" name="phone" value="<?= e($u['phone'] ?? '') ?>" maxlength="20"></div>
            </div>
            <label class="ax-check" style="margin-bottom:12px"><input type="checkbox" name="show_on_leaderboard" <?= $u['show_on_leaderboard'] ? 'checked' : '' ?>> Show on leaderboards</label>
            <div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save details</button></div>
        </form>
        <hr style="border:0;border-top:1px solid var(--border-light);margin:18px 0">
        <form method="post" action="users" class="ax-form" data-confirm="Change <?= e($u['name']) ?>'s role? Admins get full control of the platform." data-danger>
            <?= ax_csrf() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="uid" value="<?= $uid ?>">
            <div class="ax-field"><label for="e-role">Role</label>
                <select id="e-role" name="role" <?= $protect ? 'disabled' : '' ?>><?php foreach (['student' => 'Student', 'tutor' => 'Tutor', 'staff' => 'Staff (set department on the Staff page)', 'admin' => 'Admin'] as $k => $l): ?><option value="<?= $k ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
                <?php if ($protect): ?><div class="hint">Your own account and the last active admin keep their role.</div><?php endif; ?></div>
            <div class="ax-form-actions"><button class="ax-btn ghost" <?= $protect ? 'disabled' : '' ?>><i class="fas fa-user-tag"></i> Change role</button></div>
        </form>
    </div>
</dialog>

<?php if ($u['role'] !== 'admin'): ?>
<dialog class="ax-modal" id="dlgDelete" aria-labelledby="dlgDelT" style="width:min(480px,calc(100vw - 32px))">
    <div class="ax-panel-h"><h2 id="dlgDelT" style="color:var(--danger)"><i class="fas fa-triangle-exclamation" style="color:var(--danger)"></i> Delete this account?</h2><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>
    <form method="post" action="users" class="ax-panel-b ax-form">
        <?= ax_csrf() ?><input type="hidden" name="back" value="<?= e($back) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="uid" value="<?= $uid ?>">
        <p style="font-size:.9rem;color:var(--text-dim);line-height:1.6;margin-bottom:14px">This permanently removes <b><?= e($u['name']) ?></b> and can't be undone. Their enrolments, progress and certificates may be removed with it. Suspending is usually the better choice.</p>
        <div class="ax-field"><label for="d-email">Type <?= e($u['email']) ?> to confirm</label><input id="d-email" type="text" name="confirm_email" required autocomplete="off"></div>
        <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn warn" style="background:var(--danger)"><i class="fas fa-trash"></i> Delete permanently</button></div>
    </form>
</dialog>
<?php endif; ?>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
