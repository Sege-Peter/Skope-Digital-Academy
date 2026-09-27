<?php
$pageTitle = 'Examination Center';
require_once 'includes/header.php';

$tutor = currentUser();

$course_id = (int)($_GET['course_id'] ?? 0);
if (!$course_id) { header('Location: courses.php'); exit; }

// Verify ownership & identity
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND tutor_id = ?");
    $stmt->execute([$course_id, $tutor['id']]);
    $course = $stmt->fetch();
    if (!$course) { header('Location: courses.php'); exit; }
} catch (Exception $e) { header('Location: courses.php'); exit; }

// 🤖 2.5 Handle AI Auto-Bulk Questions (AJAX branch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_bulk_questions'])) {
    $quiz_id = (int)$_POST['quiz_id'];
    $topic_title = trim($_POST['topic_title']);
    
    try {
        require_once '../includes/ai-handler.php';
        $ai_pack = SDAC_AI::generateQuiz($topic_title, 5);
        
        if (is_array($ai_pack)) {
            $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, type, options_json, correct_answer, points) VALUES (?, ?, 'mcq', ?, ?, 10)");
            foreach($ai_pack as $q) {
                $stmt->execute([
                    $quiz_id, 
                    $q['question'],
                    json_encode($q['options']),
                    $q['correct']
                ]);
            }
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['error' => 'SDAC AI: Payload mismatch.']);
        exit;
    } catch (Exception $e) { 
        echo json_encode(['error' => 'SDAC AI: ' . $e->getMessage()]);
        exit;
    }
}

$message = '';
$error = '';

// 1. Handle New Quiz / CAT / Final
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quiz'])) {
    $title = trim($_POST['title']);
    $time = (int)$_POST['time_limit'];
    $pass = (int)$_POST['pass_score'];
    $type = $_POST['assessment_type'] ?? 'quiz';
    $week = !empty($_POST['linked_week_num']) ? (int)$_POST['linked_week_num'] : null;
    $lesson_link = !empty($_POST['linked_lesson_id']) ? (int)$_POST['linked_lesson_id'] : null;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, title, time_limit_mins, pass_score, type, linked_week_num, linked_lesson_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $title, $time, $pass, $type, $week, $lesson_link]);
        $message = "Assessment tier initialized successfully!";
        header("Location: quizzes.php?course_id=$course_id&msg=success");
        exit;
    } catch (Exception $e) { $error = "Setup Error: " . $e->getMessage(); }
}

// 2. Handle Question Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_question'])) {
    $qid = (int)$_POST['quiz_id'];
    $question = trim($_POST['question']);
    $type = $_POST['question_type'] ?? 'mcq';
    $points = (int)$_POST['points'];
    $correct = trim($_POST['correct_answer']);
    $options = [];

    if ($type == 'mcq') {
        $options = [trim($_POST['opt1']), trim($_POST['opt2']), trim($_POST['opt3']), trim($_POST['opt4'])];
    } elseif ($type == 'tf') {
        $options = ['True', 'False'];
    } elseif ($type == 'text') {
        $correct = trim($_POST['keywords']);
        $options = [];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, type, options_json, correct_answer, points) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$qid, $question, $type, json_encode($options), $correct, $points]);
        $message = "Question synchronized within assessment pool.";
        header("Location: quizzes.php?course_id=$course_id&quiz_id=$qid&msg=q_success");
        exit;
    } catch (Exception $e) { $error = "Pool Error: " . $e->getMessage(); }
}

// 3. Fetch Data
try {
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE course_id = ? ORDER BY FIELD(type, 'quiz', 'cat', 'final') ASC, title ASC");
    $stmt->execute([$course_id]);
    $quizzes = $stmt->fetchAll();
    
    $active_quiz_id = (int)($_GET['quiz_id'] ?? ($quizzes[0]['id'] ?? 0));
    
    $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY order_num ASC");
    $stmt->execute([$active_quiz_id]);
    $questions = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT title FROM quizzes WHERE id = ?");
    $stmt->execute([$active_quiz_id]);
    $active_title = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, title, week_num FROM lessons WHERE course_id = ? ORDER BY week_num ASC, order_num ASC");
    $stmt->execute([$course_id]);
    $course_lessons = $stmt->fetchAll();

} catch (Exception $e) { $quizzes = []; $questions = []; $course_lessons = []; }

require_once 'includes/sidebar.php';
?>

<main class="main-content">

<style>
    :root {
        --primary-blue: #00BFFF;
        --secondary-orange: #FF8C00;
        --border-color: #e2e8f0;
        --bg-light: #f8fafc;
    }
    .main-content { background: #f8fafc; padding: 32px 40px; }
    
    .quiz-layout { display: grid; grid-template-columns: 380px 1fr; gap: 40px; align-items: start; }
    
    /* Quiz List Branding */
    .assessment-list { background: #fff; border: 1px solid var(--border-color); border-radius: 24px; overflow: hidden; }
    .list-header { padding: 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #fafafa; }
    
    .quiz-entry { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: 0.3s; text-decoration: none; display: flex; align-items: center; gap: 16px; border-left: 4px solid transparent; }
    .quiz-entry:hover { background: #f0f9ff; }
    .quiz-entry.active { border-left-color: var(--primary-blue); background: rgba(0,191,255,0.05); }
    .quiz-entry:last-child { border-bottom: none; }
    
    .entry-icon { width: 44px; height: 44px; border-radius: 14px; background: rgba(0,191,255,0.08); display: flex; align-items: center; justify-content: center; color: var(--primary-blue); font-size: 1.1rem; }
    .quiz-entry.is-final .entry-icon { background: rgba(15,23,42,0.08); color: #0f172a; }
    
    .entry-info .title { font-weight: 700; color: #0f172a; margin-bottom: 2px; font-size: 0.95rem; }
    .entry-info .meta { font-size: 0.73rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Builder View */
    .pool-card { border: 1px solid var(--border-color); border-radius: 24px; overflow: hidden; background: #fff; }
    .pool-header { padding: 32px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }

    .question-card { padding: 32px; border-bottom: 1px solid #f1f5f9; }
    .question-card:last-child { border-bottom: none; }
    .q-head { display: flex; justify-content: space-between; margin-bottom: 16px; align-items: center; }
    .q-type { font-size: 0.72rem; font-weight: 800; color: var(--primary-blue); background: rgba(0,191,255,0.08); padding: 5px 12px; border-radius: 99px; text-transform: uppercase; }
    .q-points { font-size: 0.72rem; font-weight: 800; color: #64748b; }
    .q-text { font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 800; color: #0f172a; line-height: 1.4; margin-bottom: 24px; }

    .opt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .opt-item { padding: 14px 20px; border-radius: 12px; border: 1px solid #f1f5f9; background: var(--bg-light); font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 10px; }
    .opt-item.correct { border-color: #10b981; background: #ecfdf5; color: #065f46; position: relative; }
    .opt-item.correct::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; margin-left: auto; }

    /* Forms */
    .form-group { margin-bottom: 24px; }
    /* ══ Premium Compact Modals ══ */
    .modal-overlay { 
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); 
        backdrop-filter: blur(8px); display: none; align-items: center; 
        justify-content: center; z-index: 1000; padding: 20px; 
        animation: fadeIn 0.3s ease;
    }
    .modal-content { 
        background: white; width: 100%; max-width: 550px; 
        border-radius: 28px; padding: 40px; position: relative; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        transform: translateY(20px); animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { to { transform: translateY(0); } }

    .form-label { display: block; font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
    .form-input { 
        width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; 
        font-size: 0.9rem; font-weight: 600; background: #f8fafc; transition: 0.3s;
    }
    .form-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 4px rgba(0, 191, 255, 0.1); background: white; outline: none; }
    
    .modal-head { margin-bottom: 32px; }
    .modal-title { font-family: 'Poppins', sans-serif; font-size: 1.4rem; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; }

    @media (max-width: 600px) {
        .modal-content { padding: 32px 24px; border-radius: 24px; }
        .modal-title { font-size: 1.2rem; }
    }

    @media (max-width: 1024px) { .quiz-layout { grid-template-columns: 1fr; } }
</style>

<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
            <a href="courses.php" style="color: var(--primary); text-decoration: none; font-size: 0.8rem; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                <i class="fas fa-arrow-left"></i> CURRICULUM
            </a>
            <span style="color: #cbd5e1; font-weight: 900;">/</span>
            <span style="color: var(--text-dim); font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Assessment Center</span>
        </div>
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Examination Center<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Architecting evaluations for <span style="color: var(--primary); font-weight: 700;"><?= htmlspecialchars($course['title']) ?></span>.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <button onclick="openAssessmentModal()" class="btn-premium" style="padding: 12px 24px;">
            <i class="fas fa-plus"></i> INITIALIZE ASSESSMENT
        </button>
    </div>
</header>

<?php if ($message): ?>
    <div style="padding: 20px 24px; background: #ECFDF5; border: 1px solid #10B981; border-radius: 16px; color: #065F46; font-size: 0.85rem; font-weight: 800; margin-bottom: 40px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.4s ease;">
        <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="quiz-layout" style="display: grid; grid-template-columns: 380px 1fr; gap: 40px; align-items: start;">
    <!-- Assessment List -->
    <div class="premium-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 24px 32px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1rem; font-weight: 950; color: var(--text-main);">Assigned Tasks</h3>
            <span style="padding: 4px 10px; background: white; border: 1px solid #e2e8f0; border-radius: 50px; font-size: 0.65rem; font-weight: 950; color: var(--primary);"><?= count($quizzes) ?> ACTIVE</span>
        </div>
        <div class="list-body">
            <?php foreach($quizzes as $q): ?>
            <a href="?course_id=<?= $course_id ?>&quiz_id=<?= $q['id'] ?>" style="padding: 24px 32px; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: 0.3s; text-decoration: none; display: flex; align-items: center; gap: 20px; border-left: 4px solid transparent; <?= $active_quiz_id == $q['id'] ? 'background: #E0F7FF; border-left-color: var(--primary);' : '' ?>" onmouseover="if(<?= $active_quiz_id != $q['id'] ? 'true' : 'false' ?>) this.style.background='#F9FAFB'" onmouseout="if(<?= $active_quiz_id != $q['id'] ? 'true' : 'false' ?>) this.style.background='transparent'">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: <?= $q['type'] == 'final' ? '#1E293B' : 'white' ?>; color: <?= $q['type'] == 'final' ? 'white' : 'var(--primary)' ?>; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; border: 1px solid #e2e8f0; box-shadow: var(--shadow-sm);">
                    <i class="fas <?= $q['type'] == 'cat' ? 'fa-flag-checkered' : ($q['type'] == 'final' ? 'fa-medal' : 'fa-brain') ?>"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 900; color: var(--text-main); font-size: 0.95rem; letter-spacing: -0.3px; margin-bottom: 4px;"><?= htmlspecialchars($q['title']) ?></div>
                    <div style="font-size: 0.65rem; color: #94A3B8; font-weight: 850; text-transform: uppercase; letter-spacing: 0.8px;">
                        <?= strtoupper($q['type']) ?> ⋅ <?= $q['time_limit_mins'] ?>M ⋅ <?= $q['pass_score'] ?>% PASS
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Question Pool Builder -->
    <?php if($active_quiz_id): ?>
    <div class="premium-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 32px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 1.2rem; font-weight: 950; color: var(--text-main);"><?= htmlspecialchars($active_title) ?> Pool</h3>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: var(--text-dim); font-weight: 600;"><?= count($questions) ?> challenges synchronized</p>
            </div>
            <button onclick="openQuestionModal()" class="btn-premium" style="background: var(--secondary); border: none; padding: 10px 20px; font-size: 0.8rem;">
                <i class="fas fa-plus"></i> ADD CHALLENGE
            </button>
        </div>

        <div class="pool-body">
            <?php if(empty($questions)): ?>
                <div style="padding: 100px; text-align: center;">
                    <div style="width: 80px; height: 80px; background: #F8FAFC; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 2rem; color: #CBD5E1;"><i class="fas fa-layer-group"></i></div>
                    <h4 style="margin: 0 0 8px; font-weight: 950; font-size: 1.2rem;">Empty Question Pool</h4>
                    <p style="color: var(--text-dim); font-weight: 600;">Begin by adding MCQs, True/False, or Short Text challenges.</p>
                </div>
            <?php else: ?>
                <?php foreach($questions as $idx => $q): 
                    $opts = json_decode($q['options_json'], true);
                ?>
                <div style="padding: 40px; border-bottom: 1px solid #f1f5f9; transition: 0.2s;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                        <span style="font-size: 0.65rem; font-weight: 950; color: var(--primary); background: #E0F7FF; padding: 6px 14px; border-radius: 50px; text-transform: uppercase; letter-spacing: 1px; border: 1px solid #E0F7FF;">
                            <?= str_replace(['mcq','tf','text'], ['Multiple Choice','True / False', 'Short Text'], $q['type']) ?>
                        </span>
                        <span style="font-size: 0.75rem; font-weight: 900; color: #94A3B8; letter-spacing: 0.5px;">PROJECTION: <?= $q['points'] ?> Pts</span>
                    </div>
                    <h3 style="font-size: 1.2rem; font-weight: 950; color: var(--text-main); line-height: 1.5; margin-bottom: 32px; letter-spacing: -0.5px;"><?= htmlspecialchars($q['question']) ?></h3>
                    
                    <?php if($q['type'] == 'mcq' || $q['type'] == 'tf'): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                            <?php foreach($opts as $o): 
                                $isCorrect = ($o == $q['correct_answer']);
                            ?>
                                <div style="padding: 16px 20px; border-radius: 16px; border: 1px solid <?= $isCorrect ? '#10B981' : '#E2E8F0' ?>; background: <?= $isCorrect ? '#ECFDF5' : 'white' ?>; color: <?= $isCorrect ? '#065F46' : 'var(--text-main)' ?>; font-size: 0.9rem; font-weight: 700; display: flex; align-items: center; gap: 12px; transition: 0.2s;">
                                    <div style="width: 24px; height: 24px; border-radius: 50%; background: <?= $isCorrect ? '#10B981' : '#F1F5F9' ?>; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.7rem;">
                                        <i class="fas <?= $isCorrect ? 'fa-check' : 'fa-circle' ?>" style="opacity: <?= $isCorrect ? '1' : '0.2' ?>;"></i>
                                    </div>
                                    <?= htmlspecialchars($o) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="background: #F8FAFC; border: 1.5px dashed var(--primary); border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 20px;">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: white; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: var(--shadow-sm);"><i class="fas fa-key"></i></div>
                            <div>
                                <div style="font-size: 0.65rem; font-weight: 950; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Institutional Keywords</div>
                                <div style="font-weight: 900; color: var(--text-main); font-size: 1rem;"><?= htmlspecialchars($q['correct_answer']) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Assessment Setup Modal -->
<div id="quizModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(12px); z-index: 9999; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;">
    <div class="premium-card" style="width: 90%; max-width: 550px; padding: 48px; position: relative; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <button onclick="closeAllModals()" style="position: absolute; right: 24px; top: 24px; background: none; border: none; font-size: 1.5rem; color: #94A3B8; cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='var(--text-main)'">&times;</button>
        
        <h2 style="margin: 0 0 12px; font-size: 1.6rem; font-weight: 950; letter-spacing: -0.8px; color: var(--text-main);">Initialize Assessment</h2>
        <p style="color: var(--text-dim); margin-bottom: 40px; font-weight: 600;">Define the architectural constraints for this evaluation tier.</p>

        <form method="POST">
            <input type="hidden" name="save_quiz" value="1">
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Institutional Title</label>
                <input type="text" name="title" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" placeholder="e.g. Unit 1 Performance Review" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Tier Tier</label>
                    <select name="assessment_type" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; background: #f8fafc;" required onchange="document.getElementById('catWeekRow').style.display = (this.value === 'cat') ? 'block' : 'none'">
                        <option value="quiz">Standard Quiz</option>
                        <option value="cat">CAT (Milestone)</option>
                        <option value="final">Final Unit Exam</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Pass Threshold (%)</label>
                    <input type="number" name="pass_score" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" value="70" required>
                </div>
            </div>
            
            <div id="catWeekRow" style="display: none; margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Target Milestone Week</label>
                <input type="number" name="linked_week_num" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" placeholder="e.g. 4" min="1">
            </div>

            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Curriculum Alignment</label>
                <select name="linked_lesson_id" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; background: #f8fafc;">
                    <option value="">Global Assessment</option>
                    <?php foreach($course_lessons as $cl): ?>
                        <option value="<?= $cl['id'] ?>">Week <?= $cl['week_num'] ?>: <?= htmlspecialchars($cl['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom: 40px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Temporal Limit (Minutes)</label>
                <input type="number" name="time_limit" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" value="30" required>
            </div>

            <button type="submit" class="btn-premium" style="width: 100%; height: 58px;">DEPLOY ASSESSMENT</button>
        </form>
    </div>
</div>

<!-- Question Pool Modal -->
<div id="qModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(12px); z-index: 9999; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;">
    <div class="premium-card" style="width: 90%; max-width: 600px; padding: 48px; position: relative; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <button onclick="closeAllModals()" style="position: absolute; right: 24px; top: 24px; background: none; border: none; font-size: 1.5rem; color: #94A3B8; cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='var(--text-main)'">&times;</button>
        
        <h2 style="margin: 0 0 12px; font-size: 1.6rem; font-weight: 950; letter-spacing: -0.8px; color: var(--text-main);">Assemble Challenge</h2>
        <p style="color: var(--text-dim); margin-bottom: 32px; font-weight: 600;">Inject a new academic evaluation into the pool.</p>

        <div style="background: linear-gradient(135deg, rgba(0, 191, 255, 0.05) 0%, rgba(0, 191, 255, 0.05) 100%); border: 1px solid rgba(0, 191, 255, 0.1); padding: 20px; border-radius: 18px; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <div style="flex: 1;">
                <h4 style="margin: 0; font-size: 0.8rem; font-weight: 950; color: var(--text-main); text-transform: uppercase; letter-spacing: 1px;"><i class="fas fa-sparkles" style="color: var(--primary); margin-right: 8px;"></i> AI Intelligence Suite</h4>
                <p style="margin: 4px 0 0; font-size: 0.75rem; color: var(--text-dim); font-weight: 600;">Build 5 high-fidelity MCQs instantly for this topic.</p>
            </div>
            <button type="button" onclick="generateAIPack()" id="aiGenerateBtn" class="btn-premium" style="padding: 10px 18px; font-size: 0.75rem; white-space: nowrap;">EXECUTE AI GENERATION</button>
        </div>

        <form method="POST">
            <input type="hidden" name="save_question" value="1">
            <input type="hidden" name="quiz_id" value="<?= $active_quiz_id ?>">
            
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Challenge Prompt / Text</label>
                <textarea name="question" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; min-height: 100px; resize: none;" required placeholder="Formulate the academic prompt..."></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Evaluation Protocol</label>
                    <select name="question_type" id="qTypeSelect" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; background: #f8fafc;" onchange="toggleTypeFields(this.value)">
                        <option value="mcq">Multiple Choice</option>
                        <option value="tf">True / False Verdict</option>
                        <option value="text">Institutional Keywords</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Merit Weight (Pts)</label>
                    <input type="number" name="points" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" value="10">
                </div>
            </div>

            <!-- MCQ Fields -->
            <div id="mcqFields">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 40px;">
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 950; color: #10B981; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Institutional Answer</label>
                        <input type="text" name="correct_answer" id="correct_mcq" style="width: 100%; padding: 12px 16px; border: 1.5px solid #10B98133; border-radius: 12px; font-size: 0.9rem; font-weight: 600; outline: none; background: #ECFDF5;" placeholder="Primary Option">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Option B (Distractor)</label>
                        <input type="text" name="opt1" style="width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.9rem; font-weight: 600; outline: none;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Option C (Distractor)</label>
                        <input type="text" name="opt2" style="width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.9rem; font-weight: 600; outline: none;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.65rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Option D (Distractor)</label>
                        <input type="text" name="opt4" style="width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.9rem; font-weight: 600; outline: none;">
                    </div>
                </div>
            </div>

            <!-- T/F Fields -->
            <div id="tfFields" style="display: none; margin-bottom: 40px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Institutional Verdict</label>
                <select name="correct_answer_tf" id="correct_tf" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; background: #f8fafc;">
                    <option value="True">True</option>
                    <option value="False">False</option>
                </select>
            </div>

            <!-- Text Fields -->
            <div id="textFields" style="display: none; margin-bottom: 40px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Mandatory Data Keywords</label>
                <input type="text" name="keywords" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" placeholder="e.g. blockchain, decentralization, consensus">
                <p style="margin: 8px 0 0; font-size: 0.75rem; color: var(--text-dim); font-weight: 600;">Auto-evaluator will award merit based on keyword frequency.</p>
            </div>

            <button type="submit" class="btn-premium" style="width: 100%; height: 58px; background: var(--secondary);">SYNC TO POOL</button>
        </form>
    </div>
</div>

</main>

<script>
function openAssessmentModal() { document.getElementById('quizModal').style.display = 'flex'; }
function openQuestionModal() { document.getElementById('qModal').style.display = 'flex'; }
function closeAllModals() { document.querySelectorAll('#quizModal, #qModal').forEach(m => m.style.display = 'none'); }

function toggleTypeFields(val) {
    document.getElementById('mcqFields').style.display = val === 'mcq' ? 'block' : 'none';
    document.getElementById('tfFields').style.display = val === 'tf' ? 'block' : 'none';
    document.getElementById('textFields').style.display = val === 'text' ? 'block' : 'none';
    
    const mcqCorrect = document.getElementById('correct_mcq');
    const tfCorrect = document.getElementById('correct_tf');
    
    if(val === 'mcq') {
        mcqCorrect.setAttribute('name', 'correct_answer');
        tfCorrect.setAttribute('name', '');
    } else if(val === 'tf') {
        tfCorrect.setAttribute('name', 'correct_answer');
        mcqCorrect.setAttribute('name', '');
    } else {
        mcqCorrect.setAttribute('name', '');
        tfCorrect.setAttribute('name', '');
    }
}

async function generateAIPack() {
    const btn = document.getElementById('aiGenerateBtn');
    const originalText = btn.innerHTML;
    
    if(!confirm("Execute AI Pack generation for this topic? Challenges will be instantly synchronized to the pool.")) return;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> GENERATING...';

    try {
        const formData = new FormData();
        formData.append('ai_bulk_questions', '1');
        formData.append('quiz_id', '<?= $active_quiz_id ?>');
        formData.append('topic_title', '<?= addslashes($active_title) ?>');

        const resp = await fetch('quizzes.php?course_id=<?= $course_id ?>&quiz_id=<?= $active_quiz_id ?>', {
            method: 'POST',
            body: formData
        });

        const data = await resp.json();
        if(data.success) {
            location.reload();
        } else {
            alert("AI Payload Error: " + (data.error || "Generation mismatch"));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch(e) {
        alert("SDAC AI Data Stream Interrupted.");
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// Close modal on backdrop click
window.addEventListener('click', (e) => {
    const quizModal = document.getElementById('quizModal');
    const qModal = document.getElementById('qModal');
    if (e.target === quizModal) quizModal.style.display = 'none';
    if (e.target === qModal) qModal.style.display = 'none';
});

document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg')) {
        if (typeof SDAC !== 'undefined') {
            SDAC.showToast('Assessment pool synchronized.', 'success');
            window.history.replaceState({}, document.title, window.location.pathname + "?course_id=<?= $course_id ?>&quiz_id=<?= $active_quiz_id ?>");
        }
    }
    if (urlParams.get('auto_open')) {
        openAssessmentModal();
    }
});
</script>
</body>
</html>

