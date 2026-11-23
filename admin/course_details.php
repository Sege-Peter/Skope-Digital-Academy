<?php
/** Admin: one course in depth – lessons, learners, quizzes, certificates, reviews and money. */
$pageTitle = 'Course details';
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT c.*, u.name AS owner_name, u.email AS owner_email, u.role AS owner_role, u.avatar AS owner_avatar,
                            cat.name AS category_name
                     FROM courses c LEFT JOIN users u ON u.id = c.tutor_id LEFT JOIN categories cat ON cat.id = c.category_id
                     WHERE c.id = ?");
$st->execute([$id]);
$course = $id ? $st->fetch() : null;
if (!$course) {
    ax_flash('err', $id ? 'That course was not found – it may have been removed.' : 'Choose a course to see its details.');
    ax_back('courses');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    if ($act === 'status') {
        $new = $_POST['status'] ?? '';
        if (!in_array($new, ['published', 'pending', 'draft', 'archived'], true)) { ax_flash('err', 'Unknown status.'); ax_back(); }
        $pdo->prepare("UPDATE courses SET status = ? WHERE id = ?")->execute([$new, $id]);
        $labels = ['published' => 'live', 'pending' => 'pending review', 'draft' => 'a draft', 'archived' => 'archived'];
        ax_flash('ok', "The course is now {$labels[$new]}."); ax_back();
    }
    if ($act === 'message') {
        $msg = trim($_POST['message'] ?? '');
        $title = trim($_POST['title'] ?? '') ?: 'About your course "' . $course['title'] . '"';
        if ($msg === '') { ax_flash('err', 'Write a message first.'); ax_back(); }
        if ($course['owner_name'] === null) { ax_flash('err', 'The owner account no longer exists, so there is nobody to message.'); ax_back(); }
        $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, ?, ?)")
            ->execute([mb_substr($title, 0, 255), $msg, $course['owner_role'] === 'admin' ? 'admin' : 'tutor', (int)$course['tutor_id']]);
        ax_flash('ok', 'Message sent to ' . $course['owner_name'] . "'s notifications."); ax_back();
    }
    ax_flash('err', 'Unknown action.'); ax_back();
}

$q = function (string $sql, array $p = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($p); return $s; };

// Lessons by module
$lessons = $q("SELECT id, title, slug, lesson_type, order_num, week_num, module_title, duration_mins, is_preview, release_date, video_url, file_url, pdf_resource,
                      (content LIKE '%```quiz%') AS has_quiz, (content LIKE '%```exercise%') AS has_ex, source_key
               FROM lessons WHERE course_id = ? ORDER BY COALESCE(week_num, 1), order_num, id", [$id])->fetchAll();
$modules = [];
foreach ($lessons as $l) {
    $m = (int)($l['week_num'] ?: 1);
    $modules[$m]['lessons'][] = $l;
    if (!empty($l['module_title']) && empty($modules[$m]['title'])) $modules[$m]['title'] = $l['module_title'];
}
$totalMins = array_sum(array_column($lessons, 'duration_mins'));
$doneByLesson = array_column($q("SELECT lp.lesson_id k, COUNT(DISTINCT lp.student_id) v FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id
                                 WHERE l.course_id = ? AND lp.status = 'completed' GROUP BY lp.lesson_id", [$id])->fetchAll(), 'v', 'k');

// Learners
$learners = $q("SELECT e.*, u.name, u.email, u.avatar,
                       (SELECT MAX(created_at) FROM learning_events le WHERE le.user_id = e.student_id AND le.course_id = e.course_id) AS last_event,
                       (SELECT MAX(COALESCE(lp.last_seen_at, lp.completed_at)) FROM lesson_progress lp WHERE lp.student_id = e.student_id AND lp.course_id = e.course_id) AS last_seen,
                       (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id WHERE lp.student_id = e.student_id AND l.course_id = e.course_id AND lp.status = 'completed') AS lessons_done
                FROM enrollments e LEFT JOIN users u ON u.id = e.student_id
                WHERE e.course_id = ? ORDER BY e.enrolled_at DESC", [$id])->fetchAll();
$certs = $q("SELECT c.*, u.name FROM certificates c LEFT JOIN users u ON u.id = c.student_id
             WHERE c.course_id = ? AND c.certificate_type = 'course' ORDER BY c.issued_at DESC", [$id])->fetchAll();
$certByStudent = [];
foreach ($certs as $c) if ($c['status'] !== 'revoked') $certByStudent[$c['student_id']] = $c;

$completedSet = [];
foreach ($learners as $l) if ((float)$l['progress_percent'] >= 100 || $l['status'] === 'completed') $completedSet[$l['student_id']] = 1;
foreach ($certByStudent as $sid => $c) $completedSet[$sid] = 1;
$avgProgress = $learners ? array_sum(array_map(fn($l) => (float)$l['progress_percent'], $learners)) / count($learners) : 0;
$modeCounts = ['self_paced' => 0, 'structured' => 0];
foreach ($learners as $l) if (isset($modeCounts[$l['learning_mode']])) $modeCounts[$l['learning_mode']]++;

// Quizzes: markdown quizzes (learning_events) + tutor quizzes (quiz_attempts)
$tried = $passed = [];
foreach ($q("SELECT user_id, event_type, CAST(meta AS UNSIGNED) pct FROM learning_events WHERE course_id = ? AND event_type IN ('quiz_attempt','quiz_pass')", [$id])->fetchAll() as $r) {
    $tried[$r['user_id']] = 1;
    if ($r['event_type'] === 'quiz_pass' || (int)$r['pct'] >= QUIZ_PASS_PCT) $passed[$r['user_id']] = 1;
}
$quizAttempts = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM learning_events WHERE course_id = ? AND event_type IN ('quiz_attempt','quiz_pass')", [$id]);
try {
    foreach ($q("SELECT qa.student_id, qa.passed FROM quiz_attempts qa JOIN quizzes z ON z.id = qa.quiz_id WHERE z.course_id = ?", [$id])->fetchAll() as $r) {
        $tried[$r['student_id']] = 1; $quizAttempts++;
        if ($r['passed']) $passed[$r['student_id']] = 1;
    }
} catch (Throwable $e) { /* legacy quiz tables missing */ }
$passRate = $tried ? round(count($passed) / count($tried) * 100) : null;
$hasQuiz = course_has_quiz($pdo, $id) || ax_scalar($pdo, "SELECT COUNT(*) FROM quizzes WHERE course_id = ?", [$id]) > 0;

// Reviews
$reviews = $q("SELECT r.*, u.name, u.avatar FROM course_ratings r LEFT JOIN users u ON u.id = r.student_id WHERE r.course_id = ? ORDER BY COALESCE(r.updated_at, r.created_at) DESC LIMIT 8", [$id])->fetchAll();
$rateAgg = $q("SELECT AVG(rating) a, COUNT(*) n FROM course_ratings WHERE course_id = ?", [$id])->fetch();
$stars = array_fill(1, 5, 0);
foreach ($q("SELECT rating, COUNT(*) n FROM course_ratings WHERE course_id = ? GROUP BY rating", [$id])->fetchAll() as $r) if (isset($stars[(int)$r['rating']])) $stars[(int)$r['rating']] = (int)$r['n'];

// Money
$money = $q("SELECT
               SUM(CASE WHEN status = 'verified' AND (payment_type IS NULL OR payment_type <> 'certificate') THEN amount ELSE 0 END) sales,
               SUM(CASE WHEN status = 'verified' AND payment_type = 'certificate' THEN amount ELSE 0 END) certfees,
               SUM(status = 'pending') pending_n, SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) pending_amt
             FROM payments WHERE course_id = ?", [$id])->fetch();
$payments = $q("SELECT p.*, u.name FROM payments p LEFT JOIN users u ON u.id = p.student_id WHERE p.course_id = ? ORDER BY p.created_at DESC LIMIT 8", [$id])->fetchAll();

$type = $course['source'] === 'course' ? ['violet', 'Free skills course'] : ($course['source'] === 'tutorial' ? ['ok', 'Open tutorial'] : ['info', 'Paid course']);
$statusPill = ['published' => ['ok', 'Live'], 'pending' => ['warn', 'Pending review'], 'draft' => ['', 'Draft'], 'archived' => ['dark', 'Archived']][$course['status']] ?? ['', $course['status']];
$outcomes = (array)(json_decode((string)$course['outcomes'], true) ?: []);
$fee = certificate_fee_for($course);
$certIssued = count(array_filter($certs, fn($c) => $c['status'] === 'approved'));
$certAwait  = count(array_filter($certs, fn($c) => $c['status'] === 'pending'));
$certRevoked = count(array_filter($certs, fn($c) => $c['status'] === 'revoked'));

require_once 'includes/sidebar.php';
$actions = '<a class="ax-btn ghost" href="courses"><i class="fas fa-arrow-left"></i> All courses</a>'
         . '<a class="ax-btn ghost" href="course-pricing?id=' . $id . '"><i class="fas fa-money-bill-wave"></i> Pricing</a>'
         . '<a class="ax-btn" href="../' . e(learn_url($course)) . '" target="_blank" rel="noopener"><i class="fas fa-book-open-reader"></i> Open reader</a>';
?>
<style>
.main-content { min-width: 0; }
.axd-stats { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
@media (max-width: 640px) { .axd-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.axd-hero { display: grid; grid-template-columns: 220px 1fr auto; gap: 24px; align-items: center; }
.axd-thumb { width: 220px; aspect-ratio: 16/10; border-radius: 16px; overflow: hidden; background: linear-gradient(135deg, #0080ff, #0f172a); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2.6rem; }
.axd-thumb img { width: 100%; height: 100%; object-fit: cover; }
.axd-hero h2 { font: 800 1.45rem 'Poppins', sans-serif; color: var(--text-main); letter-spacing: -.4px; margin: 8px 0 6px; line-height: 1.25; }
.axd-hero .meta { display: flex; flex-wrap: wrap; gap: 6px 16px; color: var(--text-dim); font-size: .84rem; }
.axd-hero .desc { color: var(--text-dim); font-size: .88rem; line-height: 1.55; margin-top: 10px; max-width: 760px; }
.axd-hero .acts { display: flex; flex-direction: column; gap: 8px; min-width: 170px; }
.axd-hero .acts form, .axd-hero .acts .ax-btn { width: 100%; }
.axd-mod { padding: 12px 22px; background: var(--surface-2); border-bottom: 1px solid var(--border); font: 800 .78rem 'Inter', sans-serif; color: var(--text-main); display: flex; justify-content: space-between; gap: 10px; }
.axd-mod span { color: var(--text-muted); font-weight: 700; }
.axd-ls { list-style: none; }
.axd-ls li { display: flex; gap: 12px; align-items: center; padding: 11px 22px; border-bottom: 1px solid var(--border); font-size: .86rem; }
.axd-ls .n { width: 26px; height: 26px; border-radius: 8px; background: var(--surface-2); color: var(--text-dim); font-weight: 800; font-size: .72rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.axd-ls .t { flex: 1; min-width: 0; }
.axd-ls .t b { display: block; color: var(--text-main); font-weight: 700; overflow-wrap: anywhere; }
.axd-ls .t small { color: var(--text-muted); font-size: .74rem; }
.axd-ls .f { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.axd-stars li { padding: 5px 0; border: 0; font-size: .8rem; }
.axd-stars .ax-bar { flex: 1; }
.axd-rev { border-bottom: 1px solid var(--border); padding: 12px 0; }
.axd-rev:last-child { border: 0; }
.axd-rev p { font-size: .86rem; color: var(--text-dim); margin-top: 6px; line-height: 1.5; }
.axd-st { color: #f59e0b; font-size: .72rem; letter-spacing: 1px; }
.axd-prog { display: flex; align-items: center; gap: 8px; min-width: 120px; }
.axd-prog .ax-bar { flex: 1; }
.axd-out { padding-left: 18px; color: var(--text-dim); font-size: .86rem; line-height: 1.6; }
@media (max-width: 1100px) { .axd-hero { grid-template-columns: 160px 1fr; } .axd-thumb { width: 160px; } .axd-hero .acts { grid-column: 1 / -1; flex-direction: row; flex-wrap: wrap; } .axd-hero .acts form, .axd-hero .acts .ax-btn { width: auto; } }
@media (max-width: 640px) { .axd-hero { grid-template-columns: 1fr; } .axd-thumb { width: 100%; } .axd-ls li { flex-wrap: wrap; padding: 11px 16px; } .axd-ls .f { width: 100%; justify-content: flex-start; padding-left: 38px; } .axd-mod { padding: 12px 16px; } }
</style>
<main class="main-content">
    <?php ax_header('Course', 'Details', 'Lessons, learners & results', $actions); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <section class="ax-panel">
            <div class="ax-panel-b axd-hero">
                <div class="axd-thumb"><?php if ($course['thumbnail']): ?><img src="../uploads/courses/<?= e($course['thumbnail']) ?>" alt=""><?php else: ?><i class="fas fa-book-open"></i><?php endif; ?></div>
                <div style="min-width:0">
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <span class="ax-pill <?= $type[0] ?>"><?= $type[1] ?></span><span class="ax-pill <?= $statusPill[0] ?>"><?= e($statusPill[1]) ?></span>
                        <?php if ($course['is_featured']): ?><span class="ax-pill warn"><i class="fas fa-star"></i> Featured</span><?php endif; ?>
                    </div>
                    <h2><?= e($course['title']) ?></h2>
                    <div class="meta">
                        <span><i class="fas fa-user"></i> <?= e($course['owner_name'] ?? '— (owner account deleted)') ?></span>
                        <span><i class="fas fa-tag"></i> <?= e($course['category_name'] ?? 'Uncategorised') ?></span>
                        <span><i class="fas fa-signal"></i> <?= e(ucfirst((string)$course['level'])) ?></span>
                        <span><i class="fas fa-clock"></i> <?= $totalMins ? round($totalMins / 60, 1) . ' h' : '—' ?></span>
                    </div>
                    <?php if ($course['description']): ?><p class="desc"><?= e(mb_strimwidth($course['description'], 0, 320, '…')) ?></p><?php endif; ?>
                </div>
                <div class="acts">
                    <?php if ($course['status'] !== 'published'): ?>
                        <form method="post" data-confirm="Make this course live?"><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="published"><button class="ax-btn ok block"><i class="fas fa-check"></i> <?= $course['status'] === 'pending' ? 'Approve' : 'Make live' ?></button></form>
                    <?php endif; ?>
                    <?php if ($course['status'] === 'pending'): ?>
                        <form method="post" data-confirm="Send this course back to the owner as a draft?" data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="draft"><button class="ax-btn danger block"><i class="fas fa-xmark"></i> Reject</button></form>
                    <?php endif; ?>
                    <?php if ($course['status'] !== 'archived'): ?>
                        <form method="post" data-confirm="Archive this course? It disappears from the catalogue; learners keep their records." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="archived"><button class="ax-btn ghost block"><i class="fas fa-box-archive"></i> Archive</button></form>
                    <?php endif; ?>
                    <a class="ax-btn ghost" href="../course-details?id=<?= $id ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> Public page</a>
                </div>
            </div>
        </section>

        <div class="ax-stats axd-stats">
            <?= ax_stat('user-graduate', number_format(count($learners)), 'Learners', $modeCounts['self_paced'] . ' self-paced · ' . $modeCounts['structured'] . ' structured') ?>
            <?= ax_stat('flag-checkered', number_format(count($completedSet)), 'Completed', 'Average progress ' . round($avgProgress) . '%', 'green') ?>
            <?= ax_stat('list-check', number_format(count($lessons)), 'Lessons', count($modules) . ' module' . (count($modules) === 1 ? '' : 's'), 'slate') ?>
            <?= ax_stat('clipboard-question', $passRate === null ? '—' : $passRate . '%', 'Quiz pass rate', $hasQuiz ? count($passed) . ' of ' . count($tried) . ' learners passed · ' . $quizAttempts . ' attempts' : 'No quiz in this course', 'amber') ?>
            <?= ax_stat('star', $rateAgg['n'] ? number_format((float)$rateAgg['a'], 1) : '—', 'Rating', (int)$rateAgg['n'] . ' review' . ((int)$rateAgg['n'] === 1 ? '' : 's'), 'orange') ?>
            <?= ax_stat('sack-dollar', ax_money($money['sales'] ?? 0), 'Course sales', 'Certificate fees ' . ax_money($money['certfees'] ?? 0), 'violet') ?>
        </div>

        <div class="ax-grid">
            <section class="ax-panel ax-span-7">
                <div class="ax-panel-h"><div><h2><i class="fas fa-layer-group"></i> Lessons</h2><p><?= count($lessons) ?> lessons · <?= number_format($totalMins) ?> min reading/watching</p></div></div>
                <div class="ax-panel-b flush">
                    <?php if (!$lessons): ?>
                        <div class="ax-empty"><i class="fas fa-layer-group"></i><b>No lessons yet</b><?= $course['source'] ? 'Run the importer from the Content Library.' : 'The owner adds lessons from the tutor studio.' ?></div>
                    <?php endif; ?>
                    <?php foreach ($modules as $m => $mod): ?>
                        <div class="axd-mod"><div>Module <?= $m ?><?= !empty($mod['title']) ? ' · ' . e($mod['title']) : '' ?></div><span><?= count($mod['lessons']) ?> lessons</span></div>
                        <ul class="axd-ls">
                            <?php foreach ($mod['lessons'] as $i => $l):
                                $open = $course['source'] === 'tutorial' || $course['is_open_tutorial'] || $l['is_preview']; ?>
                                <li>
                                    <span class="n"><?= (int)$l['order_num'] ?: $i + 1 ?></span>
                                    <div class="t"><b><?= e($l['title']) ?></b>
                                        <small><i class="fas fa-<?= ['video' => 'circle-play', 'pdf' => 'file-pdf', 'audio' => 'headphones', 'image' => 'image'][$l['lesson_type']] ?? 'file-lines' ?>"></i> <?= e(ucfirst((string)$l['lesson_type'])) ?> · <?= (int)$l['duration_mins'] ?> min · <?= (int)($doneByLesson[$l['id']] ?? 0) ?> completed<?= $l['release_date'] ? ' · opens ' . ax_date($l['release_date']) : '' ?></small></div>
                                    <div class="f">
                                        <?php if ($l['has_quiz']): ?><span class="ax-pill warn"><i class="fas fa-clipboard-question"></i> Quiz</span><?php endif; ?>
                                        <?php if ($l['has_ex']): ?><span class="ax-pill violet"><i class="fas fa-keyboard"></i> Exercises</span><?php endif; ?>
                                        <?= $open ? '<span class="ax-pill ok"><i class="fas fa-lock-open"></i> ' . ($l['is_preview'] && $course['source'] !== 'tutorial' ? 'Free preview' : 'Open') . '</span>' : '<span class="ax-pill"><i class="fas fa-lock"></i> Enrolled</span>' ?>
                                        <a class="ax-btn ghost sm icon" href="../<?= e(learn_url($course, $l)) ?>" target="_blank" rel="noopener" title="Open lesson" aria-label="Open lesson"><i class="fas fa-eye"></i></a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="ax-span-5 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-circle-info"></i> Overview</h2></div><a class="ax-btn ghost sm" href="course-pricing?id=<?= $id ?>">Edit pricing</a></div>
                    <div class="ax-panel-b">
                        <dl class="ax-kv">
                            <dt>Slug</dt><dd><span class="ax-code"><?= e($course['slug'] ?: '—') ?></span></dd>
                            <dt>Owner</dt><dd><?= e($course['owner_name'] ?? '—') ?><?= $course['owner_email'] ? ' <small style="color:var(--text-muted)">' . e($course['owner_email']) . '</small>' : '' ?></dd>
                            <dt>Tracks</dt><dd><?= e(implode(' + ', array_map('mode_label', course_modes($course)))) ?></dd>
                            <?php foreach (course_modes($course) as $md): ?>
                                <dt><?= e(mode_label($md)) ?> price</dt><dd><?= course_list_price($course, $md) > 0 ? ax_money(course_list_price($course, $md)) : 'Free' ?><?= $md === 'self_paced' && sale_active($course) ? ' → <b style="color:var(--success)">' . ax_money($course['sale_price']) . ' sale</b>' . ($course['sale_ends_at'] ? ' until ' . ax_date($course['sale_ends_at']) : '') : '' ?></dd>
                            <?php endforeach; ?>
                            <dt>Access</dt><dd><?= (int)$course['access_days'] > 0 ? (int)$course['access_days'] . ' days' : 'Lifetime' ?></dd>
                            <dt>Certificate fee</dt><dd><?= $fee > 0 ? ax_money($fee) : 'Free' ?><?= $course['certificate_fee'] === null ? ' <small style="color:var(--text-muted)">(platform default)</small>' : '' ?></dd>
                            <dt>Created</dt><dd><?= ax_date($course['created_at']) ?></dd>
                            <dt>Pricing changed</dt><dd><?= ax_date($course['pricing_updated_at'] ?? null) ?></dd>
                        </dl>
                        <?php if ($outcomes): ?>
                            <h4 style="font:800 .72rem Inter,sans-serif;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin:18px 0 8px">What learners will learn</h4>
                            <ul class="axd-out"><?php foreach ($outcomes as $o): ?><li><?= e((string)$o) ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-certificate"></i> Certificates</h2><p><?= $certIssued ?> issued · <?= $certAwait ?> awaiting payment<?= $certRevoked ? ' · ' . $certRevoked . ' revoked' : '' ?></p></div><a class="ax-btn ghost sm" href="certificates">Manage</a></div>
                    <div class="ax-panel-b">
                        <?php if (!$certs): ?><div class="ax-empty" style="padding:20px"><i class="fas fa-certificate"></i>No certificates for this course yet.</div>
                        <?php else: ?><ul class="ax-list">
                            <?php foreach (array_slice($certs, 0, 6) as $c): ?>
                                <li><div class="grow"><b style="color:var(--text-main);font-size:.88rem"><?= e($c['name'] ?? '—') ?></b><br><span class="ax-code"><?= e($c['verification_code']) ?></span> <small style="color:var(--text-muted)"><?= ax_date($c['issued_at']) ?></small></div>
                                    <?= ['approved' => '<span class="ax-pill ok">Issued</span>', 'pending' => '<span class="ax-pill warn">Awaiting ' . ax_money($c['fee']) . '</span>', 'revoked' => '<span class="ax-pill bad">Revoked</span>'][$c['status']] ?? '' ?>
                                    <?php if ($c['status'] !== 'revoked'): ?><a class="ax-btn ghost sm icon" href="../certificate?code=<?= urlencode($c['verification_code']) ?>" target="_blank" rel="noopener" title="View certificate" aria-label="View certificate"><i class="fas fa-eye"></i></a><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul><?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="ax-panel ax-span-12">
                <div class="ax-panel-h"><div><h2><i class="fas fa-users"></i> Enrolled learners</h2><p>Progress, track and latest activity</p></div>
                    <?php if ($learners): ?><div class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Filter learners…" data-filter-table="learnersTable" aria-label="Filter learners"></div><?php endif; ?></div>
                <?php if (!$learners): ?>
                    <div class="ax-empty"><i class="fas fa-user-graduate"></i><b>No learners yet</b>Enrolments appear here as soon as someone joins.</div>
                <?php else: ?>
                <div class="ax-table-wrap"><table class="ax-table cards" id="learnersTable">
                    <thead><tr><th>Learner</th><th>Track</th><th>Progress</th><th>Status</th><th>Enrolled</th><th>Last activity</th><th>Certificate</th></tr></thead>
                    <tbody>
                    <?php foreach ($learners as $l):
                        $last = max((string)$l['last_event'], (string)$l['last_seen']) ?: null;
                        $pc = (float)$l['progress_percent']; $cert = $certByStudent[$l['student_id']] ?? null; ?>
                        <tr>
                            <td data-label="Learner"><a class="ax-who" style="text-decoration:none" href="user-profile?id=<?= (int)$l['student_id'] ?>"><?= ax_avatar($l['name'], $l['avatar']) ?><div><b><?= e($l['name'] ?? 'Deleted account') ?></b><small><?= e($l['email'] ?? '') ?></small></div></a></td>
                            <td data-label="Track"><span class="ax-pill <?= $l['learning_mode'] === 'structured' ? 'violet' : 'info' ?>"><?= e(mode_label((string)$l['learning_mode'])) ?></span></td>
                            <td data-label="Progress"><div class="axd-prog"><div class="ax-bar<?= $pc >= 100 ? ' green' : '' ?>"><i style="width:<?= min(100, round($pc)) ?>%"></i></div><b class="num" style="font-size:.8rem;color:var(--text-main)"><?= round($pc) ?>%</b></div><div class="muted"><?= (int)$l['lessons_done'] ?>/<?= count($lessons) ?> lessons</div></td>
                            <td data-label="Status"><span class="ax-pill <?= ['active' => 'info', 'completed' => 'ok', 'pending' => 'warn', 'cancelled' => 'bad'][$l['status']] ?? '' ?>"><?= e(ucfirst((string)$l['status'])) ?></span></td>
                            <td data-label="Enrolled" class="num"><?= ax_date($l['enrolled_at']) ?></td>
                            <td data-label="Last activity" class="num"><?= ax_ago($last) ?></td>
                            <td data-label="Certificate"><?= $cert ? ($cert['status'] === 'approved' ? '<span class="ax-pill ok">Issued</span>' : '<span class="ax-pill warn">Awaiting fee</span>') : '<span class="muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>
            </section>

            <section class="ax-panel ax-span-6">
                <div class="ax-panel-h"><div><h2><i class="fas fa-star"></i> Reviews</h2><p><?= $rateAgg['n'] ? number_format((float)$rateAgg['a'], 1) . ' average from ' . (int)$rateAgg['n'] . ' review' . ((int)$rateAgg['n'] === 1 ? '' : 's') : 'No reviews yet' ?></p></div></div>
                <div class="ax-panel-b">
                    <ul class="ax-list axd-stars" style="margin-bottom:10px">
                        <?php for ($s = 5; $s >= 1; $s--): ?><li><span style="width:46px"><?= $s ?> <i class="fas fa-star" style="color:#f59e0b;font-size:.7rem"></i></span><div class="ax-bar orange"><i style="width:<?= $rateAgg['n'] ? round($stars[$s] / $rateAgg['n'] * 100) : 0 ?>%"></i></div><span class="num" style="width:24px;text-align:right"><?= $stars[$s] ?></span></li><?php endfor; ?>
                    </ul>
                    <?php foreach ($reviews as $r): ?>
                        <div class="axd-rev">
                            <div class="ax-who"><?= ax_avatar($r['name'], $r['avatar']) ?><div><b><?= e($r['name'] ?? 'Deleted account') ?></b><small><span class="axd-st"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span> · <?= ax_ago($r['updated_at'] ?? $r['created_at']) ?></small></div></div>
                            <?php if (trim((string)$r['review']) !== ''): ?><p><?= nl2br(e($r['review'])) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="ax-span-6 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-receipt"></i> Payments</h2><p><?= ax_money($money['sales'] ?? 0) ?> sales · <?= ax_money($money['certfees'] ?? 0) ?> certificate fees<?= (int)$money['pending_n'] ? ' · ' . (int)$money['pending_n'] . ' pending (' . ax_money($money['pending_amt']) . ')' : '' ?></p></div><a class="ax-btn ghost sm" href="payments">All payments</a></div>
                    <div class="ax-panel-b">
                        <?php if (!$payments): ?><div class="ax-empty" style="padding:20px"><i class="fas fa-receipt"></i>No payments for this course.</div>
                        <?php else: ?><ul class="ax-list">
                            <?php foreach ($payments as $p): ?>
                                <li><div class="grow"><b style="color:var(--text-main);font-size:.88rem"><?= e($p['name'] ?? 'Deleted account') ?></b><br><small style="color:var(--text-muted)"><?= e(ucfirst((string)($p['payment_type'] ?: 'course'))) ?> · <?= ax_date($p['created_at']) ?></small></div>
                                    <b class="num" style="color:var(--text-main)"><?= ax_money($p['amount']) ?></b>
                                    <span class="ax-pill <?= ['verified' => 'ok', 'pending' => 'warn', 'failed' => 'bad'][$p['status']] ?? '' ?>"><?= e($p['status']) ?></span></li>
                            <?php endforeach; ?>
                        </ul><?php endif; ?>
                    </div>
                </section>
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-paper-plane"></i> Message the owner</h2><p>Goes to <?= e($course['owner_name'] ?? 'nobody – the owner account was deleted') ?>'s notifications.</p></div></div>
                    <form method="post" class="ax-panel-b ax-form">
                        <?= ax_csrf() ?><input type="hidden" name="action" value="message">
                        <div class="ax-field"><label for="msg-t">Subject</label><input type="text" id="msg-t" name="title" maxlength="200" placeholder="About your course &quot;<?= e($course['title']) ?>&quot;"></div>
                        <div class="ax-field"><label for="msg-b">Message</label><textarea id="msg-b" name="message" maxlength="2000" required placeholder="e.g. Learners are asking about lesson 4 – please reply in the course Q&amp;A."></textarea></div>
                        <div class="ax-form-actions"><button class="ax-btn" <?= $course['owner_name'] === null ? 'disabled' : '' ?>><i class="fas fa-paper-plane"></i> Send</button></div>
                    </form>
                </section>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
