<?php
$pageTitle = 'Academic Challenges';
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
?>

<header class="portal-header" style="margin-bottom: 40px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Academic Challenges<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Prove your knowledge and secure institutional certification through high-intensity assessments.</p>
    </div>
</header>

<div class="premium-card glass-effect" style="background: linear-gradient(135deg, var(--primary) 0%, #0072FF 100%); color: white; border: none; margin-bottom: 40px; padding: 48px; border-radius: 35px; overflow: hidden; position: relative;">
    <div style="position: relative; z-index: 2; max-width: 600px;">
        <span style="display: inline-block; padding: 6px 14px; background: rgba(255,255,255,0.2); border-radius: 50px; font-size: 0.65rem; font-weight: 950; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 20px; backdrop-filter: blur(5px);">Certification Path</span>
        <h2 style="color: white; margin-top: 0; font-weight: 950; font-size: 2.2rem; letter-spacing: -1px; line-height: 1.1;">Course Milestones & Evaluation</h2>
        <p style="opacity: 0.9; line-height: 1.6; font-size: 1rem; font-weight: 500; margin-top: 16px;">Complete module lessons to unlock specialized CAT assessments. Successful completion of all modules enables the <strong>Unit Final Examination</strong>.</p>
    </div>
    <div style="font-size: 12rem; opacity: 0.1; position: absolute; right: -20px; bottom: -40px; transform: rotate(-15deg); pointer-events: none;"><i class="fas fa-award"></i></div>
</div>

<div class="challenges-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 32px; margin-bottom: 60px;">
    <?php foreach($quizzes as $q): 
        $progress = ($q['total_lessons'] > 0) ? ($q['completed_lessons'] / $q['total_lessons']) * 100 : 0;
        $is_locked = ($q['type'] == 'final' && ($progress < 100 || $q['pending_cats'] > 0));
        $type_label = strtoupper($q['type'] == 'cat' ? 'Practical C.A.T' : ($q['type'] == 'final' ? 'Unit Exam' : 'Knowledge Check'));
    ?>
    <div class="premium-card" style="padding: 0; position: relative; border-radius: 30px; display: flex; flex-direction: column; overflow: hidden; transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
        <?php if($is_locked): ?>
        <div style="position: absolute; inset: 0; background: rgba(255,255,255,0.96); backdrop-filter: blur(12px); z-index: 10; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px;">
            <div style="width: 70px; height: 70px; background: #f8fafc; border-radius: 22px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; color: #cbd5e1; font-size: 1.8rem; box-shadow: inset 0 2px 6px rgba(0,0,0,0.05);"><i class="fas fa-lock"></i></div>
            <h4 style="margin: 0; font-weight: 950; font-size: 1.2rem; letter-spacing: -0.5px; color: #1e293b;">MODULE LOCKED</h4>
            <p style="font-size: 0.75rem; color: #64748b; margin-top: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; line-height: 1.5;">
                Requires <?= 100 - round($progress) ?>% more curriculum coverage to initialize.
            </p>
        </div>
        <?php endif; ?>

        <div style="padding: 32px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
                <span style="display: flex; align-items: center; gap: 8px; font-size: 0.65rem; font-weight: 950; color: <?= $q['type'] == 'cat' ? '#d97706' : ($q['type'] == 'final' ? '#1e293b' : 'var(--primary)') ?>; background: <?= $q['type'] == 'cat' ? '#FFFBEB' : ($q['type'] == 'final' ? '#F1F5F9' : '#F0F9FF') ?>; padding: 6px 12px; border-radius: 50px; letter-spacing: 1px; text-transform: uppercase;">
                    <i class="fas <?= $q['type'] == 'cat' ? 'fa-fire' : ($q['type'] == 'final' ? 'fa-terminal' : 'fa-brain') ?>"></i> <?= $type_label ?>
                </span>
                <div style="font-size: 0.72rem; font-weight: 900; color: var(--text-dim);"><i class="far fa-clock" style="margin-right: 4px;"></i> <?= $q['duration_mins'] ?? '20' ?>m Allocation</div>
            </div>

            <h4 style="margin: 0 0 12px; font-size: 1.35rem; font-weight: 950; line-height: 1.3; letter-spacing: -0.5px; color: var(--text-main);"><?= htmlspecialchars($q['title']) ?></h4>
            <span style="font-size: 0.72rem; font-weight: 900; color: var(--primary); text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.8;"><?= htmlspecialchars($q['course_title'] ?? 'Strategic Path') ?></span>

            <div style="margin-top: 40px; padding-top: 28px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: flex-end; gap: 20px;">
                <div>
                    <?php if($q['best_score'] !== null): ?>
                        <span style="display: block; font-size: 0.65rem; font-weight: 950; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Peak Score</span>
                        <div style="display: flex; align-items: baseline; gap: 6px;">
                            <strong style="font-size: 1.8rem; font-weight: 950; color: <?= $q['best_score'] >= $q['pass_score'] ? '#10b981' : '#ef4444' ?>;"><?= round($q['best_score']) ?>%</strong>
                            <span style="font-size: 0.8rem; color: #cbd5e1; font-weight: 800;">Target: <?= $q['pass_score'] ?>%</span>
                        </div>
                    <?php else: ?>
                        <span style="display: block; font-size: 0.65rem; font-weight: 950; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Requirement</span>
                        <span style="font-size: 0.85rem; color: var(--text-main); font-weight: 800;">Score ≥ <?= $q['pass_score'] ?>%</span>
                    <?php endif; ?>
                </div>
                <a href="take-quiz.php?id=<?= $q['id'] ?>" class="btn-premium" style="padding: 14px 28px; border-radius: 16px; font-size: 0.75rem; font-weight: 950; text-transform: uppercase; letter-spacing: 1.5px; border: none; background: <?= $q['best_score'] !== null ? '#f8fafc' : 'var(--grad-primary)' ?>; color: <?= $q['best_score'] !== null ? '#475569' : 'white' ?>; border: <?= $q['best_score'] !== null ? '1px solid #e2e8f0' : 'none' ?>; min-width: 130px; text-align: center;">
                    <?= $q['best_score'] !== null ? 'Retake Path' : 'Start Challenge' ?>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if(empty($quizzes)): ?>
        <div class="premium-card" style="grid-column: 1 / -1; text-align: center; padding: 120px 40px; border-style: dashed; border-width: 2px;">
            <div style="width: 80px; height: 80px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: var(--text-dim); font-size: 2.2rem;">
                <i class="fas fa-meteor"></i>
            </div>
            <h3 style="font-weight: 950; font-size: 1.8rem; color: var(--text-main); margin-bottom: 16px;">The Path is Empty</h3>
            <p style="color: var(--text-dim); max-width: 450px; margin: 0 auto 32px; line-height: 1.7; font-weight: 500;">No assessments are currently active for your enrolled modules. Focus on completing your learning curriculum first.</p>
            <a href="courses.php" class="btn-premium" style="min-width: 240px;">Explore Course Modules</a>
        </div>
    <?php endif; ?>
</div>


<?php require_once 'includes/layout-bottom.php'; ?>
