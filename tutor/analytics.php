<?php
/**
 * Tutor: analytics across all courses or one course (?course_id=). Every number is computed from the database;
 * learner counts use td_learner_summary() so they match the dashboard and the Learners page.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

$courseId = (int)($_GET['course_id'] ?? 0);
if ($courseId && !td_owns_course($pdo, $tid, $courseId)) td_redirect('analytics');

$allCourses = td_courses($pdo, $tid);
$course = null;
foreach ($allCourses as $c) if ((int)$c['id'] === $courseId) $course = $c;

$pageTitle = $course ? 'Insights · ' . $course['title'] : 'Analytics';
$pageSub = $course ? 'How this course is doing: learners, progress, quiz results and earnings.' : 'How your courses are doing: learners, progress, quiz results and earnings. All figures are live from the database.';
$pageActions = '<button type="button" class="tp-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>';
if ($course) $pageCrumb = ['href' => 'analytics', 'label' => 'All courses'];
require_once 'includes/header.php';

$sum = td_learner_summary($pdo, $tid, $courseId ?: null);
$earn = td_earnings($pdo, $tid, null, null, $courseId ?: null);
$months = td_monthly_earnings($pdo, $tid, 6, $courseId ?: null);
$enrolMonths = td_monthly_enrolments($pdo, $tid, 6, $courseId ?: null);
$courses = $course ? [$course] : $allCourses;
$cw = $courseId ? ' AND c.id = ' . $courseId : '';

// Quiz results
$st = $pdo->prepare("SELECT q.id, q.title, q.type, c.title AS course_title, COUNT(qa.id) AS attempts, AVG(qa.score) AS avg_score,
                            SUM(qa.passed = 1) AS passed, COUNT(DISTINCT qa.student_id) AS learners
                     FROM quizzes q JOIN courses c ON c.id = q.course_id LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id
                     WHERE c.tutor_id = ?$cw GROUP BY q.id ORDER BY attempts DESC, q.title LIMIT 10");
$st->execute([$tid]);
$quizStats = $st->fetchAll();

// Per-course earnings
$st = $pdo->prepare("SELECT c.id, COUNT(p.id) n, COALESCE(SUM(p.amount), 0) gross FROM courses c
                     LEFT JOIN payments p ON p.course_id = c.id AND p.status = 'verified' AND p.payment_type = 'course'
                     WHERE c.tutor_id = ?$cw GROUP BY c.id");
$st->execute([$tid]);
$earnBy = [];
foreach ($st->fetchAll() as $r) $earnBy[(int)$r['id']] = $r;

// Lesson completion (where learners stop)
$lessonStats = [];
if ($course) {
    $st = $pdo->prepare("SELECT l.id, l.title, l.week_num, l.order_num,
                                (SELECT COUNT(*) FROM lesson_progress lp JOIN users u ON u.id = lp.student_id AND u.role = 'student'
                                   JOIN enrollments e ON e.student_id = lp.student_id AND e.course_id = l.course_id AND e.status IN ('active','completed')
                                   WHERE lp.lesson_id = l.id AND lp.status = 'completed') AS done
                         FROM lessons l WHERE l.course_id = ? ORDER BY l.week_num, l.order_num, l.id");
    $st->execute([$courseId]);
    $lessonStats = $st->fetchAll();
}
$fmtMoney = fn($v) => td_money($v);
$fmtInt = fn($v) => number_format($v);
?>
<?php if ($allCourses && !$course && count($allCourses) > 1): ?>
<form method="get" action="analytics" class="tp-filter" style="margin-bottom:16px">
    <div class="tp-field" style="flex:0 1 320px"><label for="an-c">Show</label>
        <select id="an-c" name="course_id" onchange="this.form.submit()"><option value="">All courses</option>
            <?php foreach ($allCourses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
</form>
<?php endif; ?>

<div class="tp-stats">
    <div class="tp-stat"><span class="tp-stat-icon"><i class="fas fa-user-graduate"></i></span><div><div class="tp-stat-value"><?= $sum['learners'] ?></div><div class="tp-stat-label">Learners</div><div class="tp-stat-sub"><?= $sum['enrolments'] ?> enrolment<?= $sum['enrolments'] === 1 ? '' : 's' ?><?= $sum['pending'] ? ' · ' . $sum['pending'] . ' pending' : '' ?></div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon ok"><i class="fas fa-chart-line"></i></span><div><div class="tp-stat-value"><?= $sum['avg_progress'] === null ? '—' : round($sum['avg_progress']) . '%' ?></div><div class="tp-stat-label">Average completion</div><div class="tp-stat-sub"><?= $sum['active'] ?> still learning</div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon violet"><i class="fas fa-graduation-cap"></i></span><div><div class="tp-stat-value"><?= $sum['completed'] ?></div><div class="tp-stat-label">Completed</div><div class="tp-stat-sub"><?= $sum['enrolments'] ? round($sum['completed'] / $sum['enrolments'] * 100) . '% of enrolments' : 'No enrolments yet' ?></div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon warn"><i class="fas fa-wallet"></i></span><div><div class="tp-stat-value"><?= e(td_money($earn['share'])) ?></div><div class="tp-stat-label">Earned (all time)</div><div class="tp-stat-sub"><?= td_share_pct() ?>% of <?= e(td_money($earn['gross'])) ?> from <?= $earn['payments'] ?> payment<?= $earn['payments'] === 1 ? '' : 's' ?></div></div></div>
</div>
<?php if ($note = td_orphan_note($sum)): ?><p class="tp-note" style="margin-top:10px"><i class="fas fa-circle-info"></i> <?= e($note) ?></p><?php endif; ?>

<div class="tp-grid two" style="margin-top:18px">
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-wallet"></i> Your earnings, last 6 months</h2><p>Your <?= td_share_pct() ?>% share of verified course payments.</p></div></div>
        <div class="tp-card-body"><?= td_bars($months, 'share', $fmtMoney, false, 'Earnings per month') ?></div>
    </section>
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-user-plus"></i> New learners, last 6 months</h2><p>Active or completed enrolments by the month they started.</p></div></div>
        <div class="tp-card-body"><?= td_bars($enrolMonths, 'n', $fmtInt, true, 'New enrolments per month') ?></div>
    </section>
</div>

<section class="tp-card" style="margin-top:18px">
    <div class="tp-card-head"><div><h2><i class="fas fa-book-open"></i> <?= $course ? 'This course' : 'Courses compared' ?></h2></div></div>
    <?php if ($courses): ?>
    <div class="tp-table-wrap"><table class="tp-table stack">
        <thead><tr><th>Course</th><th class="num">Learners</th><th>Average progress</th><th class="num">Completed</th><th class="num">Rating</th><th class="num">Your earnings</th></tr></thead>
        <tbody>
        <?php foreach ($courses as $c): $cs = td_learner_summary($pdo, $tid, (int)$c['id']); $g = (float)($earnBy[(int)$c['id']]['gross'] ?? 0); [$sl, $sc] = td_course_status($c['status']); ?>
            <tr>
                <td data-label=""><a href="analytics?course_id=<?= (int)$c['id'] ?>" style="font-weight:600"><?= e($c['title']) ?></a> <span class="tp-pill <?= $sc ?>"><?= $sl ?></span></td>
                <td data-label="Learners" class="num"><?= $cs['learners'] ?><?= $cs['pending'] ? ' <span class="tp-note">(+' . $cs['pending'] . ' pending)</span>' : '' ?></td>
                <td data-label="Average progress"><?php if ($cs['avg_progress'] === null): ?><span class="tp-note">No learners</span><?php else: ?><div class="tp-progress"><div class="tp-progress-bar"><span style="width:<?= round($cs['avg_progress']) ?>%"></span></div><b><?= round($cs['avg_progress']) ?>%</b></div><?php endif; ?></td>
                <td data-label="Completed" class="num"><?= $cs['completed'] ?></td>
                <td data-label="Rating" class="num"><?= (int)$c['rating_count'] ? number_format((float)$c['rating_avg'], 1) . ' ★ (' . (int)$c['rating_count'] . ')' : '—' ?></td>
                <td data-label="Your earnings" class="num"><?= e(td_money($g * td_share_rate())) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php else: ?>
        <div class="tp-empty"><i class="fas fa-book"></i><h3>No courses yet</h3><p>Analytics appear once you have a course with learners.</p><div class="tp-actions"><a class="tp-btn sm primary" href="courses?action=new">New course</a></div></div>
    <?php endif; ?>
</section>

<div class="tp-grid <?= $course ? 'two' : '' ?>" style="margin-top:18px">
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-circle-question"></i> Quiz results</h2><p>Average score and pass rate per quiz.</p></div></div>
        <?php $taken = array_filter($quizStats, fn($q) => (int)$q['attempts'] > 0); ?>
        <?php if ($taken): ?>
            <div class="tp-table-wrap"><table class="tp-table stack">
                <thead><tr><th>Quiz</th><th class="num">Attempts</th><th>Average score</th><th class="num">Pass rate</th></tr></thead>
                <tbody>
                <?php foreach ($taken as $q): ?>
                    <tr>
                        <td data-label=""><b style="color:var(--tp-text)"><?= e($q['title']) ?></b><?php if (!$course): ?><div class="tp-note"><?= e($q['course_title']) ?></div><?php endif; ?></td>
                        <td data-label="Attempts" class="num"><?= (int)$q['attempts'] ?> by <?= (int)$q['learners'] ?></td>
                        <td data-label="Average score"><div class="tp-progress"><div class="tp-progress-bar"><span style="width:<?= round((float)$q['avg_score']) ?>%"></span></div><b><?= round((float)$q['avg_score']) ?>%</b></div></td>
                        <td data-label="Pass rate" class="num"><?= round((int)$q['passed'] / max(1, (int)$q['attempts']) * 100) ?>%</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <div class="tp-empty"><i class="fas fa-circle-question"></i><h3>No quiz attempts yet</h3><p><?= $quizStats ? 'You have quizzes, but no learner has taken one yet.' : 'Add a quiz to a course to see how learners score.' ?></p></div>
        <?php endif; ?>
    </section>

    <?php if ($course): ?>
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-list-check"></i> Lesson completion</h2><p>How many of the <?= $sum['enrolments'] ?> enrolled learners finished each lesson.</p></div></div>
        <?php if ($lessonStats): ?>
            <ul class="tp-list">
                <?php foreach ($lessonStats as $l): $pct = $sum['enrolments'] ? round((int)$l['done'] / $sum['enrolments'] * 100) : 0; ?>
                    <li><div class="tp-row no-indent"><span class="tp-row-main"><span class="tp-row-title">Week <?= (int)$l['week_num'] ?> · <?= e($l['title']) ?></span>
                        <span class="tp-row-meta"><div class="tp-progress" style="margin-top:6px"><div class="tp-progress-bar"><span style="width:<?= $pct ?>%"></span></div><b><?= (int)$l['done'] ?></b></div></span></span></div></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?><div class="tp-empty"><i class="fas fa-layer-group"></i><h3>No lessons yet</h3></div><?php endif; ?>
    </section>
    <?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>
