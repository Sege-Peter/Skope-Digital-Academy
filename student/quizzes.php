<?php
$pageTitle = 'Quizzes';
require_once 'includes/header.php';

try {
    // 2. Fetch quizzes for enrolled courses
    $stmt = $pdo->prepare("
        SELECT q.*, c.title as course_title, c.thumbnail as course_thumb,
        (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as q_count,
        (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = :sid) as best_score,
        (SELECT COUNT(*) FROM quizzes q2 
         WHERE q2.course_id = q.course_id 
         AND q2.type = 'cat' 
         AND NOT EXISTS (
            SELECT 1 FROM quiz_attempts qa 
            WHERE qa.quiz_id = q2.id 
            AND qa.student_id = :sid 
            AND qa.score >= q2.pass_score
         )
        ) as pending_cats,
        (SELECT COUNT(*) FROM lessons l WHERE l.course_id = q.course_id) as total_lessons,
        (SELECT COUNT(*) FROM lesson_progress lp 
         JOIN lessons l2 ON lp.lesson_id = l2.id 
         WHERE l2.course_id = q.course_id AND lp.student_id = :sid AND lp.status = 'completed'
        ) as completed_lessons
        FROM quizzes q
        JOIN courses c ON q.course_id = c.id
        JOIN enrollments e ON e.course_id = c.id
        WHERE e.student_id = :sid AND e.status = 'active'
        ORDER BY FIELD(q.type, 'quiz', 'cat', 'final') ASC, q.created_at DESC
    ");
    $stmt->execute(['sid' => $student['id']]);
    $quizzes = $stmt->fetchAll();

} catch (Exception $e) { 
    $quizzes = []; 
}

require_once 'includes/layout-top.php';

$qz_todo = $qz_passed = $qz_locked = 0;
$qz_cards = [];
foreach ($quizzes as $q) {
    $progress = ($q['total_lessons'] > 0) ? ($q['completed_lessons'] / $q['total_lessons']) * 100 : 0;
    $is_locked = ($q['type'] == 'final' && ($progress < 100 || $q['pending_cats'] > 0));
    $attempted = $q['best_score'] !== null;
    $passed = $attempted && $q['best_score'] >= $q['pass_score'];
    if ($is_locked) $qz_locked++; elseif ($passed) $qz_passed++; elseif (!$attempted) $qz_todo++;
    $qz_cards[] = [$q, $progress, $is_locked, $attempted, $passed];
}
$qz_types = [
    'cat'   => ['Assessment test (CAT)', 'yellow', 'fa-file-pen'],
    'final' => ['Final exam', 'violet', 'fa-graduation-cap'],
    'quiz'  => ['Quiz', '', 'fa-circle-question'],
];
?>
<style>
    .pg-quizzes .qz-card { display: flex; flex-direction: column; gap: 12px; position: relative; }
    .pg-quizzes .qz-card h3 { margin: 0; font-size: 1.02rem; font-weight: 800; line-height: 1.35; }
    .pg-quizzes .qz-meta { display: flex; gap: 14px; flex-wrap: wrap; font-size: .78rem; color: var(--text-dim); }
    .pg-quizzes .qz-foot { margin-top: auto; padding-top: 14px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .pg-quizzes .qz-score strong { font-size: 1.3rem; font-weight: 800; }
    .pg-quizzes .qz-card.locked { background: var(--surface-2); }
    .pg-quizzes .qz-card.locked h3, .pg-quizzes .qz-card.locked .qz-meta { opacity: .7; }
</style>

<div class="pg-quizzes">
    <div class="sp-page-head">
        <div><h1>Quizzes</h1><p>Quizzes and tests from the courses you are taking.</p></div>
    </div>

    <section class="sp-grid cols-4 sp-section">
        <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-list-check"></i></span><div><strong><?= count($quizzes) ?></strong><span>All quizzes</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon orange"><i class="fas fa-hourglass-half"></i></span><div><strong><?= $qz_todo ?></strong><span>Not started</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-circle-check"></i></span><div><strong><?= $qz_passed ?></strong><span>Passed</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon gray" style="background: #EEF2F6; color: var(--text-dim);"><i class="fas fa-lock"></i></span><div><strong><?= $qz_locked ?></strong><span>Locked</span></div></div>
    </section>

    <div class="sp-alert">
        <i class="fas fa-circle-info" style="margin-top: 3px;"></i>
        <div>Finish a course's lessons and pass its assessment tests (CATs) to unlock the <strong>final exam</strong>. You can retake a quiz to improve your score.</div>
    </div>

    <?php if (empty($quizzes)): ?>
        <div class="sp-empty">
            <div class="sp-ico"><i class="fas fa-circle-question"></i></div>
            <h3>No quizzes yet</h3>
            <p>Your courses don't have any quizzes open right now. Keep going with your lessons and they will show up here.</p>
            <a href="courses" class="sp-btn">Go to my courses</a>
        </div>
    <?php else: ?>
        <div class="sp-grid cols-3">
            <?php foreach ($qz_cards as [$q, $progress, $is_locked, $attempted, $passed]):
                [$type_label, $type_col, $type_icon] = $qz_types[$q['type']] ?? $qz_types['quiz'];
            ?>
            <div class="sp-card qz-card <?= $is_locked ? 'locked' : 'hover' ?>">
                <div class="sp-between">
                    <span class="sp-badge <?= $type_col ?>"><i class="fas <?= $type_icon ?>"></i> <?= $type_label ?></span>
                    <?php if ($is_locked): ?>
                        <span class="sp-badge gray"><i class="fas fa-lock"></i> Locked</span>
                    <?php elseif ($passed): ?>
                        <span class="sp-badge green"><i class="fas fa-check"></i> Passed</span>
                    <?php elseif ($attempted): ?>
                        <span class="sp-badge red">Not passed yet</span>
                    <?php endif; ?>
                </div>
                <div>
                    <h3><?= htmlspecialchars($q['title']) ?></h3>
                    <p class="sp-muted sp-small" style="margin: 4px 0 0;"><?= htmlspecialchars($q['course_title'] ?? 'Course') ?></p>
                </div>
                <div class="qz-meta">
                    <span><i class="far fa-clock"></i> <?= (int)($q['time_limit_mins'] ?? 20) ?> min</span>
                    <span><i class="fas fa-list-ol"></i> <?= (int)$q['q_count'] ?> question<?= (int)$q['q_count'] === 1 ? '' : 's' ?></span>
                    <span><i class="fas fa-bullseye"></i> Pass mark <?= (int)$q['pass_score'] ?>%</span>
                </div>

                <?php if ($is_locked): ?>
                    <div class="qz-foot">
                        <p class="sp-muted sp-small" style="margin: 0;">
                            <?php if ($progress < 100): ?>Finish the course lessons first (<?= (int)round($progress) ?>% done).<?php endif; ?>
                            <?php if ($q['pending_cats'] > 0): ?><?= $progress < 100 ? ' Also pass' : 'Pass' ?> <?= (int)$q['pending_cats'] ?> CAT<?= (int)$q['pending_cats'] === 1 ? '' : 's' ?> to unlock.<?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="qz-foot">
                        <div class="qz-score">
                            <?php if ($attempted): ?>
                                <span class="sp-muted sp-small">Best score</span><br>
                                <strong style="color: <?= $passed ? 'var(--success)' : 'var(--danger)' ?>;"><?= round($q['best_score']) ?>%</strong>
                            <?php else: ?>
                                <span class="sp-muted sp-small">Not attempted yet</span>
                            <?php endif; ?>
                        </div>
                        <a href="take-quiz?id=<?= (int)$q['id'] ?>" class="sp-btn sm <?= $attempted ? 'ghost' : '' ?>"><?= $attempted ? 'Retake quiz' : 'Start quiz' ?></a>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
