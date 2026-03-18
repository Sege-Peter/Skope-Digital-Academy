<?php
$pageTitle = 'Academic Challenges';
require_once 'includes/header.php';

try {
    // 1. Fetch Student Point Summary
    $stmt = $pdo->prepare("SELECT merit_points FROM users WHERE id = ?");
    $stmt->execute([$student['id']]);
    $points = $stmt->fetchColumn() ?: 0;

    // 2. Fetch quizzes for enrolled courses
    // We need to know if all CATs are passed and if all lessons are viewed for the unlocking logic
    $stmt = $pdo->prepare("
        SELECT q.*, c.title as course_title, c.thumbnail as course_thumb,
        (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.id) as q_count,
        (SELECT MAX(score) FROM quiz_attempts WHERE quiz_id = q.id AND student_id = :sid) as best_score,
        
        -- Check if all CATs in this course are passed by this student
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

        -- Calculate Lesson Progress
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
    error_log($e->getMessage());
    $quizzes = []; 
    $points = 0;
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    :root {
        --primary: #00BFFF;
        --secondary: #FF8C00;
        --dark-bg: #0f172a;
    }
    .main-content { background: #fff; padding: 40px; }
    
    .quiz-hero { 
        background: linear-gradient(135deg, var(--primary) 0%, #0080FF 100%); 
        border-radius: 32px; padding: 60px; color: white; margin-bottom: 48px; 
        display: flex; justify-content: space-between; align-items: center;
        box-shadow: 0 20px 40px rgba(0,191,255,0.15); position: relative; overflow: hidden;
    }
    .quiz-hero::after { content: '\f19d'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -20px; bottom: -20px; font-size: 15rem; opacity: 0.1; }

    .points-card { background: rgba(255,255,255,0.15); backdrop-filter: blur(12px); padding: 24px 32px; border-radius: 24px; border: 1px solid rgba(255,255,255,0.2); text-align: right; }

    .quiz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 32px; }
    
    .quiz-card { background: #fff; border: 1px solid #f1f5f9; border-radius: 28px; padding: 32px; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1); display: flex; flex-direction: column; position: relative; }
    .quiz-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.05); border-color: var(--primary); }
    
    .type-badge { position: absolute; top: 32px; right: 32px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; padding: 6px 14px; border-radius: 99px; letter-spacing: 1px; }
    .type-quiz { background: rgba(0,191,255,0.08); color: var(--primary); }
    .type-cat { background: rgba(255,140,0,0.08); color: var(--secondary); }
    .type-final { background: #0f172a; color: #fff; }

    .quiz-title { font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 24px 0 16px; line-height: 1.4; flex: 1; }
    
    .lock-overlay { position: absolute; inset: 0; background: rgba(255,255,255,0.9); backdrop-filter: blur(4px); z-index: 10; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 28px; padding: 40px; text-align: center; }
    .lock-icon { width: 64px; height: 64px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 1.5rem; margin-bottom: 20px; }
    
    .progress-mini { height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden; margin: 12px 0; }
    .progress-fill { height: 100%; background: var(--primary); border-radius: 10px; }

    .btn-locked { background: #f1f5f9 !important; color: #94a3b8 !important; cursor: not-allowed; border: none; }
</style>

<main class="main-content">
    <header style="margin-bottom: 40px;">
        <h1 style="font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 800;">Academic <span style="color: var(--primary);">Challenges</span></h1>
        <p style="color: #64748b; margin-top: 4px;">Validate your knowledge, earn merit points, and unlock your credentials.</p>
    </header>

    <div class="quiz-hero">
        <div>
            <h2 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.8rem; margin-bottom: 12px;">The Road to Excellence</h2>
            <p style="opacity: 0.9; max-width: 500px; line-height: 1.6;">Complete your lessons and pass your CAT assessments to unlock the <strong>Final Unit Examination</strong>.</p>
        </div>
        <div class="points-card">
            <div style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; opacity: 0.8; margin-bottom: 4px;">Scholarly Merit</div>
            <div style="font-size: 2.5rem; font-weight: 900; line-height: 1;"><i class="fas fa-award" style="color: #FFD700;"></i> <?= number_format($points) ?></div>
        </div>
    </div>

    <div class="quiz-grid">
        <?php foreach($quizzes as $q): 
            $progress = ($q['total_lessons'] > 0) ? ($q['completed_lessons'] / $q['total_lessons']) * 100 : 0;
            $is_locked = false;
            $lock_reason = '';

            if ($q['type'] == 'final') {
                if ($progress < 100) {
                    $is_locked = true;
                    $lock_reason = "Curriculum incomplete (" . round($progress) . "%)";
                } elseif ($q['pending_cats'] > 0) {
                    $is_locked = true;
                    $lock_reason = "Pending CAT Assessments";
                }
            }
        ?>
        <div class="quiz-card">
            <span class="type-badge type-<?= $q['type'] ?>"><?= strtoupper($q['type']) ?></span>
            
            <div style="display: flex; align-items: center; gap: 10px; color: #94a3b8; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                <i class="fas fa-book-reader"></i> <?= htmlspecialchars($q['course_title']) ?>
            </div>

            <h3 class="quiz-title"><?= htmlspecialchars($q['title']) ?></h3>

            <?php if($is_locked): ?>
                <div class="lock-overlay">
                    <div class="lock-icon"><i class="fas fa-lock"></i></div>
                    <h4 style="font-weight: 800; color: #0f172a; margin-bottom: 8px;">Tier Locked</h4>
                    <p style="font-size: 0.85rem; color: #64748b;"><?= $lock_reason ?></p>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 12px; margin-bottom: 24px;">
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.65rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">
                        <span>Progress</span>
                        <span><?= round($progress) ?>%</span>
                    </div>
                    <div class="progress-mini"><div class="progress-fill" style="width: <?= $progress ?>%"></div></div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: flex-end; padding-top: 24px; border-top: 1px solid #f1f5f9; margin-top: auto;">
                <div>
                    <?php if($q['best_score'] !== null): ?>
                        <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; margin-bottom: 4px;">Best Score</div>
                        <div style="font-size: 1.4rem; font-weight: 900; color: <?= $q['best_score'] >= $q['pass_score'] ? '#10B981' : '#EF4444' ?>;"><?= round($q['best_score']) ?>%</div>
                    <?php else: ?>
                        <div style="font-size: 0.85rem; font-weight: 700; color: #64748b;">Not Attempted</div>
                    <?php endif; ?>
                </div>
                
                <a href="take-quiz.php?id=<?= $q['id'] ?>" class="btn <?= $is_locked ? 'btn-locked' : 'btn-primary' ?>" style="padding: 12px 24px; border-radius: 12px; font-weight: 800; text-decoration: none;">
                    <?= $q['best_score'] !== null ? 'Re-attempt' : 'Start Task' ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
