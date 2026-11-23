<?php
/**
 * Tutor: one learner's progress in the tutor's own courses. Only learners enrolled with this tutor can be opened.
 */
$pageTitle = 'Learner profile';
$pageCrumb = ['href' => 'students', 'label' => 'Learners'];
require_once 'includes/header.php';

$student_id = (int)($_GET['id'] ?? 0);
$student = null;
if ($student_id && td_teaches_student($pdo, $tid, $student_id)) {
    $st = $pdo->prepare("SELECT id, name, email, avatar, phone, bio, created_at, last_login, merit_points, xp FROM users WHERE id = ? AND role = 'student'");
    $st->execute([$student_id]);
    $student = $st->fetch() ?: null;
}

if (!$student):
?>
    <div class="tp-card"><div class="tp-empty" style="padding:56px 22px"><i class="fas fa-user-slash"></i><h3>Learner not found</h3><p>This person is not enrolled in any of your courses, or their account no longer exists.</p><div class="tp-actions"><a class="tp-btn sm" href="students">Back to learners</a></div></div></div>
<?php
    require 'includes/footer.php';
    return;
endif;

$enrolments = td_enrolments($pdo, $tid, ['student_id' => $student_id, 'statuses' => ['active', 'completed', 'pending', 'cancelled']]);
$st = $pdo->prepare("SELECT q.title AS quiz_title, qa.score, qa.passed, qa.completed_at, c.title AS course_title
                     FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id JOIN courses c ON q.course_id = c.id
                     WHERE qa.student_id = ? AND c.tutor_id = ? ORDER BY qa.completed_at DESC");
$st->execute([$student_id, $tid]);
$quizzes = $st->fetchAll();
$st = $pdo->prepare("SELECT s.*, a.title AS assignment_title, a.max_score, c.title AS course_title
                     FROM assignment_submissions s JOIN assignments a ON s.assignment_id = a.id JOIN courses c ON a.course_id = c.id
                     WHERE s.student_id = ? AND c.tutor_id = ? ORDER BY s.submitted_at DESC");
$st->execute([$student_id, $tid]);
$assignments = $st->fetchAll();

$avg_quiz = $quizzes ? array_sum(array_map(fn($q) => (float)$q['score'], $quizzes)) / count($quizzes) : null;
$graded = array_filter($assignments, fn($a) => $a['status'] === 'graded');
$avg_progress = $enrolments ? array_sum(array_map(fn($e) => (float)$e['progress_percent'], $enrolments)) / count($enrolments) : null;
$csrf = csrfToken();
?>
<section class="tp-card" style="margin-bottom:18px">
    <div class="tp-card-body" style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
        <?= td_avatar($student['avatar'], $student['name'], 'tp-avatar lg') ?>
        <div style="flex:1 1 240px;min-width:0">
            <h2 style="font-size:1.3rem"><?= e($student['name']) ?></h2>
            <p class="tp-note" style="font-size:.9rem;overflow-wrap:anywhere"><?= e($student['email']) ?><?= $student['phone'] ? ' · ' . e($student['phone']) : '' ?></p>
            <p class="tp-note">Joined <?= e(date('j M Y', strtotime($student['created_at']))) ?> · last signed in <?= $student['last_login'] ? e(td_ago($student['last_login'])) : 'never' ?></p>
        </div>
        <div class="tp-actions">
            <a class="tp-btn" href="messages?to=<?= $student_id ?>"><i class="fas fa-comment"></i> Message</a>
            <a class="tp-btn" href="award_student?id=<?= $student_id ?>"><i class="fas fa-award"></i> Certificates & awards</a>
            <form method="post" action="../impersonate?action=start"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="uid" value="<?= $student_id ?>"><button class="tp-btn" type="submit"><i class="fas fa-eye"></i> View as learner</button></form>
        </div>
    </div>
    <?php if (trim((string)$student['bio']) !== ''): ?><div class="tp-card-foot" style="color:var(--tp-body)"><?= nl2br(e($student['bio'])) ?></div><?php endif; ?>
</section>

<div class="tp-stats" style="margin-bottom:18px">
    <div class="tp-stat"><span class="tp-stat-icon"><i class="fas fa-book-open"></i></span><div><div class="tp-stat-value"><?= count($enrolments) ?></div><div class="tp-stat-label">Your courses taken</div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon ok"><i class="fas fa-chart-line"></i></span><div><div class="tp-stat-value"><?= $avg_progress === null ? '—' : round($avg_progress) . '%' ?></div><div class="tp-stat-label">Average progress</div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon violet"><i class="fas fa-circle-question"></i></span><div><div class="tp-stat-value"><?= $avg_quiz === null ? '—' : round($avg_quiz) . '%' ?></div><div class="tp-stat-label">Quiz average</div><div class="tp-stat-sub"><?= count($quizzes) ?> attempt<?= count($quizzes) === 1 ? '' : 's' ?></div></div></div>
    <div class="tp-stat"><span class="tp-stat-icon warn"><i class="fas fa-clipboard-check"></i></span><div><div class="tp-stat-value"><?= count($assignments) ?></div><div class="tp-stat-label">Assignments handed in</div><div class="tp-stat-sub"><?= count($graded) ?> graded</div></div></div>
</div>

<section class="tp-card" style="margin-bottom:18px">
    <div class="tp-card-head"><div><h2><i class="fas fa-book-open"></i> Courses</h2></div></div>
    <div class="tp-table-wrap"><table class="tp-table stack">
        <thead><tr><th>Course</th><th>Track</th><th>Progress</th><th>Status</th><th class="num">Enrolled</th></tr></thead>
        <tbody>
        <?php foreach ($enrolments as $en): [$sl, $sc] = td_enrol_status($en['status']); ?>
            <tr>
                <td data-label=""><b style="color:var(--tp-text)"><?= e($en['course_title']) ?></b></td>
                <td data-label="Track"><?= e(mode_label($en['learning_mode'] ?: 'self_paced')) ?><?= $en['cohort_name'] ? ' · ' . e($en['cohort_name']) : '' ?></td>
                <td data-label="Progress"><div class="tp-progress"><div class="tp-progress-bar"><span style="width:<?= max(0, min(100, (float)$en['progress_percent'])) ?>%"></span></div><b><?= round((float)$en['progress_percent']) ?>%</b></div></td>
                <td data-label="Status"><span class="tp-pill <?= $sc ?>"><?= $sl ?></span></td>
                <td data-label="Enrolled" class="num"><?= e(date('j M Y', strtotime($en['enrolled_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>

<div class="tp-grid two">
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-circle-question"></i> Quiz attempts</h2></div></div>
        <?php if ($quizzes): ?>
            <ul class="tp-list">
                <?php foreach ($quizzes as $q): ?>
                    <li><div class="tp-row no-indent"><span class="tp-row-main"><span class="tp-row-title"><?= e($q['quiz_title']) ?></span><span class="tp-row-meta"><?= e($q['course_title']) ?> · <?= $q['completed_at'] ? e(date('j M Y', strtotime($q['completed_at']))) : '' ?></span></span>
                        <span class="tp-row-side"><span class="tp-pill <?= $q['passed'] ? 'ok' : 'bad' ?>"><?= round((float)$q['score']) ?>% · <?= $q['passed'] ? 'passed' : 'not passed' ?></span></span></div></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?><div class="tp-empty"><i class="fas fa-circle-question"></i><h3>No quiz attempts yet</h3></div><?php endif; ?>
    </section>
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-clipboard-check"></i> Assignments</h2></div></div>
        <?php if ($assignments): ?>
            <ul class="tp-list">
                <?php foreach ($assignments as $a): ?>
                    <li><a class="tp-row no-indent" href="assignments?grade=<?= (int)$a['id'] ?>"><span class="tp-row-main"><span class="tp-row-title"><?= e($a['assignment_title']) ?></span><span class="tp-row-meta"><?= e($a['course_title']) ?> · handed in <?= e(date('j M Y', strtotime($a['submitted_at']))) ?></span></span>
                        <span class="tp-row-side"><?php if ($a['status'] === 'graded'): ?><span class="tp-pill ok"><?= e(rtrim(rtrim(number_format((float)$a['score'], 1), '0'), '.')) ?>/<?= (int)$a['max_score'] ?></span><?php else: ?><span class="tp-pill warn">To grade</span><?php endif; ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?><div class="tp-empty"><i class="fas fa-clipboard"></i><h3>Nothing handed in yet</h3></div><?php endif; ?>
    </section>
</div>
<?php require 'includes/footer.php'; ?>
