<?php
/**
 * Tutor: leaderboard of one of the tutor's courses, ranked by XP earned in that course.
 * Tutors see every learner (td_course_ranking); the public board hides learners who opted out.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
require_once __DIR__ . '/../includes/progress.php'; // leaderboard_name()
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

$courses = td_courses($pdo, $tid);
$course_id = (int)($_GET['course_id'] ?? 0);
if ($course_id && !td_owns_course($pdo, $tid, $course_id)) $course_id = 0;
if (!$course_id && $courses) {
    // default to the course with the most learners
    $best = $courses;
    usort($best, fn($a, $b) => (int)$b['learner_count'] <=> (int)$a['learner_count']);
    $course_id = (int)$best[0]['id'];
}
$period = ($_GET['period'] ?? '') === 'week' ? 'week' : 'all';

$sum = td_learner_summary($pdo, $tid);
$pageTitle = 'Leaderboard';
$pageSub = 'Learners ranked by the XP they earned in a course (lessons, exercises and quizzes). You teach ' . $sum['learners'] . ' learner' . ($sum['learners'] === 1 ? '' : 's') . ' in total.';
require_once 'includes/header.php';

$rows = $course_id ? td_course_ranking($pdo, $tid, $course_id, $period) : [];
$cSum = $course_id ? td_learner_summary($pdo, $tid, $course_id) : null;
?>
<?php if ($courses): ?>
<form method="get" action="leaderboard" class="tp-filter" style="margin-bottom:16px">
    <div class="tp-field" style="flex:0 1 320px"><label for="lb-c">Course</label>
        <select id="lb-c" name="course_id" onchange="this.form.submit()">
            <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $course_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?> (<?= (int)$c['learner_count'] ?>)</option><?php endforeach; ?>
        </select></div>
    <div class="tp-field" style="flex:0 1 200px"><label for="lb-p">Period</label>
        <select id="lb-p" name="period" onchange="this.form.submit()"><option value="all">All time</option><option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Last 7 days</option></select></div>
    <noscript><button class="tp-btn" type="submit">Show</button></noscript>
</form>
<?php endif; ?>

<section class="tp-card">
    <?php if ($cSum): ?>
        <div class="tp-card-head"><div><h2><i class="fas fa-trophy"></i> Ranking</h2><p><?= $cSum['learners'] ?> learner<?= $cSum['learners'] === 1 ? '' : 's' ?> in this course<?= $period === 'week' ? ' · XP earned in the last 7 days' : '' ?></p></div></div>
    <?php endif; ?>
    <?php if ($rows): ?>
        <div class="tp-table-wrap"><table class="tp-table stack">
            <thead><tr><th>Rank</th><th>Learner</th><th class="num">XP</th><th>Progress</th><th class="num">Lessons done</th><th class="num">Quiz average</th><th>Last active</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td data-label="Rank"><b style="font-size:1.05rem;color:<?= $r['rank'] === 1 ? '#b45309' : 'var(--tp-text)' ?>">#<?= (int)$r['rank'] ?></b><?= $r['rank'] <= 3 ? ' <i class="fas fa-medal" style="color:' . ['#f59e0b', '#94a3b8', '#c2410c'][$r['rank'] - 1] . '"></i>' : '' ?></td>
                    <td data-label=""><a class="tp-who" href="student_profile?id=<?= (int)$r['id'] ?>" style="color:inherit"><?= td_avatar($r['avatar'], $r['name']) ?><span><b><?= e($r['name']) ?></b><small><?= (int)$r['show_on_leaderboard'] ? 'Shown on the public board as ' . e(leaderboard_name($r['name'])) : 'Hidden from the public board' ?></small></span></a></td>
                    <td data-label="XP" class="num"><b><?= number_format((int)$r['course_xp']) ?></b></td>
                    <td data-label="Progress"><div class="tp-progress"><div class="tp-progress-bar"><span style="width:<?= max(0, min(100, (float)$r['progress_percent'])) ?>%"></span></div><b><?= round((float)$r['progress_percent']) ?>%</b></div></td>
                    <td data-label="Lessons done" class="num"><?= (int)$r['lessons_done'] ?></td>
                    <td data-label="Quiz average" class="num"><?= $r['avg_quiz'] !== null ? round((float)$r['avg_quiz']) . '%' : '—' ?></td>
                    <td data-label="Last active"><?= $r['last_active'] ? e(td_ago($r['last_active'])) : '<span class="tp-note">Not yet</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php else: ?>
        <div class="tp-empty" style="padding:44px 22px">
            <i class="fas fa-trophy"></i>
            <?php if (!$courses): ?>
                <h3>No courses yet</h3><p>Once learners join your courses they are ranked here by what they complete.</p><div class="tp-actions"><a class="tp-btn sm primary" href="courses?action=new">New course</a></div>
            <?php else: ?>
                <h3>No learners to rank yet</h3><p>Learners appear here once they enrol in this course and start earning XP from lessons, exercises and quizzes.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
