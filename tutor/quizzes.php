<?php
$pageTitle = 'Examination Center';
require_once 'includes/header.php';

$course_id = (int)($_GET['course_id'] ?? 0);
if (!$course_id) { header('Location: courses.php'); exit; }

// Verify ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND tutor_id = ?");
    $stmt->execute([$course_id, $tutor['id']]);
    $course = $stmt->fetch();
    if (!$course) { header('Location: courses.php'); exit; }
} catch (Exception $e) { header('Location: courses.php'); exit; }

$message = '';
$error = '';

// 1. Handle New Quiz / CAT / Final
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quiz'])) {
    $title = trim($_POST['title']);
    $time = (int)$_POST['time_limit'];
    $pass = (int)$_POST['pass_score'];
    $type = $_POST['assessment_type'] ?? 'quiz';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, title, time_limit_mins, pass_score, type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $title, $time, $pass, $type]);
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
        // Correct answer here is a comma-separated list of keywords
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

} catch (Exception $e) { $quizzes = []; $questions = []; }

if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'success') $message = "Assessment initialized successfully!";
    if ($_GET['msg'] == 'q_success') $message = "Question synchronized within assessment pool.";
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    :root {
        --primary-blue: #00BFFF;
        --secondary-orange: #FF8C00;
        --border-color: #e2e8f0;
        --bg-light: #f8fafc;
    }
    .main-content { background: #fff; padding: 40px; }
    
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
    .form-label { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: block; }
    .form-input { width: 100%; padding: 14px 18px; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-light); font-size: 0.93rem; transition: 0.3s; }
    .form-input:focus { outline: none; border-color: var(--primary-blue); background: #fff; box-shadow: 0 0 0 4px rgba(0,191,255,0.08); }

    .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
    .modal-content { background: #fff; border-radius: 32px; width: 100%; max-width: 500px; padding: 48px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative; }

    @media (max-width: 1024px) { .quiz-layout { grid-template-columns: 1fr; } }
</style>

<main class="main-content">
    <header style="margin-bottom: 48px; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                <a href="courses.php" style="color: var(--primary-blue); text-decoration: none; font-size: 0.85rem; font-weight: 700;"><i class="fas fa-chevron-left"></i> My Courses</a>
                <span style="color: #cbd5e1;">/</span>
                <span style="color: #64748b; font-size: 0.85rem; font-weight: 600;">Assessment Center</span>
            </div>
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem; font-weight: 800; color: #0f172a;">Manage <span style="color: var(--primary-blue);">Assessments</span></h1>
            <p style="color: #64748b; margin-top: 4px;">Initialize Quizzes and CATs for <strong><?= htmlspecialchars($course['title']) ?></strong></p>
        </div>
        <button onclick="openAssessmentModal()" class="btn btn-primary" style="background: var(--primary-blue); border: none; padding: 16px 32px; border-radius: 16px; font-weight: 800; box-shadow: 0 10px 20px rgba(0,191,255,0.2);">
            <i class="fas fa-plus"></i> Initialize Assessment
        </button>
    </header>

    <?php if ($message): ?>
        <div style="padding: 18px 24px; border-radius: 16px; background: #ecfdf5; color: #065f46; font-weight: 700; margin-bottom: 32px; border: 1px solid #10b98133; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="quiz-layout">
        <!-- Assessment List -->
        <div class="assessment-list">
            <div class="list-header">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a;">Assigned Tasks</h3>
                <span style="font-size: 0.72rem; font-weight: 800; color: #94a3b8; background: #f1f5f9; padding: 5px 12px; border-radius: 99px;"><?= count($quizzes) ?> Active</span>
            </div>
            <div class="list-body">
                <?php foreach($quizzes as $q): ?>
                <a href="?course_id=<?= $course_id ?>&quiz_id=<?= $q['id'] ?>" class="quiz-entry <?= $active_quiz_id == $q['id'] ? 'active' : '' ?> <?= 'is-' . $q['type'] ?>">
                    <div class="entry-icon">
                        <i class="fas <?= $q['type'] == 'cat' ? 'fa-flag-checkered' : ($q['type'] == 'final' ? 'fa-medal' : 'fa-brain') ?>"></i>
                    </div>
                    <div class="entry-info">
                        <div class="title"><?= htmlspecialchars($q['title']) ?></div>
                        <div class="meta"><?= strtoupper($q['type']) ?> ASSESSMENT ⋅ <?= $q['time_limit_mins'] ?>M ⋅ <?= $q['pass_score'] ?>% PASS</div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Question Pool Builder -->
        <?php if($active_quiz_id): ?>
        <div class="pool-card">
            <div class="pool-header">
                <div>
                    <h3 style="font-size: 1.25rem; font-weight: 800; color: #0f172a;"><?= htmlspecialchars($active_title) ?> Pool</h3>
                    <p style="font-size: 0.85rem; color: #64748b;"><?= count($questions) ?> questions synchronized</p>
                </div>
                <button onclick="openQuestionModal()" class="btn btn-primary" style="background: var(--secondary-orange); border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700;">
                    <i class="fas fa-plus"></i> Add Question
                </button>
            </div>

            <div class="pool-body">
                <?php if(empty($questions)): ?>
                    <div style="padding: 100px; text-align: center; color: #94a3b8;">
                        <i class="fas fa-layer-group" style="font-size: 3rem; margin-bottom: 24px; opacity: 0.2;"></i>
                        <h4 style="font-weight: 800;">Empty Question Pool</h4>
                        <p>Begin by adding MCQs, True/False, or Short Text questions.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($questions as $idx => $q): 
                        $opts = json_decode($q['options_json'], true);
                    ?>
                    <div class="question-card">
                        <div class="q-head">
                            <span class="q-type"><?= str_replace(['mcq','tf','text'], ['Multiple Choice','True / False', 'Short Text'], $q['type']) ?></span>
                            <span class="q-points">Potential Merit: <?= $q['points'] ?> Pts</span>
                        </div>
                        <h3 class="q-text"><?= htmlspecialchars($q['question']) ?></h3>
                        
                        <?php if($q['type'] == 'mcq' || $q['type'] == 'tf'): ?>
                            <div class="opt-grid">
                                <?php foreach($opts as $o): ?>
                                    <div class="opt-item <?= ($o == $q['correct_answer']) ? 'correct' : '' ?>">
                                        <?= htmlspecialchars($o) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="background: #f8fafc; border: 1px dashed var(--primary-blue); border-radius: 14px; padding: 20px;">
                                <div style="font-size: 0.72rem; font-weight: 800; color: var(--primary-blue); text-transform: uppercase; margin-bottom: 8px;">Auto-Marking Keywords</div>
                                <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($q['correct_answer']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Quiz Setup Modal -->
<div class="modal-overlay" id="quizModal">
    <div class="modal-content">
        <button type="button" onclick="closeAllModals()" style="position: absolute; top: 24px; right: 24px; background: none; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer;"><i class="fas fa-times"></i></button>
        <h2 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.5rem; margin-bottom: 32px; color: #0f172a;">Initialize Assessment</h2>
        <form method="POST">
            <input type="hidden" name="save_quiz" value="1">
            <div class="form-group">
                <label class="form-label">Assessment Title</label>
                <input type="text" name="title" class="form-input" placeholder="e.g. Continuous Assessment Test 1" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Assessment Tier</label>
                    <select name="assessment_type" class="form-input" required>
                        <option value="quiz">Standard Quiz</option>
                        <option value="cat">Continuous Assessment Test (CAT)</option>
                        <option value="final">Final Unit Examination</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Pass Score (%)</label>
                    <input type="number" name="pass_score" class="form-input" value="70" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Duration (Minutes)</label>
                <input type="number" name="time_limit" class="form-input" value="30" required>
            </div>
            <div style="display: flex; gap: 12px; margin-top: 32px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; height: 58px; background: var(--primary-blue); border: none; border-radius: 16px; font-weight: 800;">Define Assessment</button>
                <button type="button" onclick="this.closest('.modal-overlay').style.display='none'" class="btn" style="height: 58px; background: #f1f5f9; color: #475569; border-radius: 16px; padding: 0 24px; font-weight: 700;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Question Builder Modal -->
<div class="modal-overlay" id="qModal">
    <div class="modal-content" style="max-width: 600px;">
        <button type="button" onclick="closeAllModals()" style="position: absolute; top: 24px; right: 24px; background: none; border: none; font-size: 1.2rem; color: #94a3b8; cursor: pointer;"><i class="fas fa-times"></i></button>
        <h2 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.5rem; margin-bottom: 32px; color: #0f172a;">Synchronize Question</h2>
        <form method="POST">
            <input type="hidden" name="save_question" value="1">
            <input type="hidden" name="quiz_id" value="<?= $active_quiz_id ?>">
            
            <div class="form-group">
                <label class="form-label">Question Content / Text</label>
                <textarea name="question" class="form-input" style="min-height: 100px; resize: none;" required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Question Type</label>
                    <select name="question_type" id="qTypeSelect" class="form-input" onchange="toggleTypeFields(this.value)">
                        <option value="mcq">Multiple Choice</option>
                        <option value="tf">True / False</option>
                        <option value="text">Short Text Answer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Merit Points</label>
                    <input type="number" name="points" class="form-input" value="10">
                </div>
            </div>

            <!-- MCQ Fields -->
            <div id="mcqFields">
                <div class="grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" style="color: #10b981;">Correct Answer</label>
                        <input type="text" name="correct_answer" id="correct_mcq" class="form-input" placeholder="Option A">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Option B</label>
                        <input type="text" name="opt1" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Option C</label>
                        <input type="text" name="opt2" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Option D</label>
                        <input type="text" name="opt4" class="form-input">
                    </div>
                </div>
            </div>

            <!-- T/F Fields -->
            <div id="tfFields" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Correct Verdict</label>
                    <select name="correct_answer_tf" class="form-input" id="correct_tf">
                        <option value="True">True</option>
                        <option value="False">False</option>
                    </select>
                </div>
            </div>

            <!-- Text Fields -->
            <div id="textFields" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Mandatory Keywords (Comma Separated)</label>
                    <input type="text" name="keywords" class="form-input" placeholder="e.g. software, lifecycle, testing">
                    <p style="font-size: 0.72rem; color: #94a3b8; margin-top: 8px;">Auto-marking will award partial credit based on these terms.</p>
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 32px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; height: 58px; background: var(--secondary-orange); border: none; border-radius: 16px; font-weight: 800;">Sync Question</button>
                <button type="button" onclick="closeAllModals()" class="btn" style="height: 58px; background: #f1f5f9; color: #475569; border-radius: 16px; padding: 0 24px; font-weight: 700;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAssessmentModal() { document.getElementById('quizModal').style.display = 'flex'; }
function openQuestionModal() { document.getElementById('qModal').style.display = 'flex'; }
function closeAllModals() { document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none'); }

function toggleTypeFields(val) {
    document.getElementById('mcqFields').style.display = val === 'mcq' ? 'block' : 'none';
    document.getElementById('tfFields').style.display = val === 'tf' ? 'block' : 'none';
    document.getElementById('textFields').style.display = val === 'text' ? 'block' : 'none';
    
    // Ensure correct input name is used for the correct answer
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

// Handle msg param
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('msg')) {
    window.history.replaceState({}, document.title, window.location.pathname + "?course_id=<?= $course_id ?>&quiz_id=<?= $active_quiz_id ?>");
}
</script>

<script src="../assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg')) {
        // SDAC is now guaranteed to be defined
        if (typeof SDAC !== 'undefined') {
            SDAC.showToast('Data synchronized successfully!', 'success');
        }
    }
});
</script>
</body>
</html>
