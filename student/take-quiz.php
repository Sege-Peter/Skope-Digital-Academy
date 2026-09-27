<?php
$pageTitle = 'Examination Center';
require_once 'includes/header.php';

$qid = (int)($_GET['id'] ?? 0);
if (!$qid) { header('Location: quizzes.php'); exit; }

try {
    // 1. Fetch Quiz Info
    $stmt = $pdo->prepare("SELECT q.*, c.title as course_title, c.thumbnail as course_thumb 
                           FROM quizzes q 
                           JOIN courses c ON q.course_id = c.id 
                           WHERE q.id = ?");
    $stmt->execute([$qid]);
    $quiz = $stmt->fetch();
    if (!$quiz) { header('Location: quizzes.php'); exit; }

    // 2. Fetch Questions
    $stmt = $pdo->prepare("SELECT id, question, type, options_json, points 
                           FROM quiz_questions 
                           WHERE quiz_id = ? 
                           ORDER BY order_num ASC");
    $stmt->execute([$qid]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Fetch Previous Attempts
    $stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY completed_at DESC");
    $stmt->execute([$qid, $student['id']]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { 
    error_log($e->getMessage());
    header('Location: quizzes.php'); 
    exit; 
}

require_once 'includes/sidebar.php'; 
?>

<style>
    /* Quiz Specific Overrides & Extensions */
    .quiz-container { max-width: 1000px; margin: 0 auto; }
    
    /* Attempt Table */
    .attempt-table { width: 100%; border-collapse: separate; border-spacing: 0 12px; margin-top: 24px; }
    .attempt-table th { text-align: left; padding: 12px 24px; color: var(--text-dim); font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
    .attempt-table td { background: white; padding: 20px 24px; font-size: 0.9rem; font-weight: 600; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
    .attempt-table td:first-child { border-left: 1px solid var(--border); border-radius: 12px 0 0 12px; }
    .attempt-table td:last-child { border-right: 1px solid var(--border); border-radius: 0 12px 12px 0; }

    /* Player UI */
    .quiz-player-layout { display: grid; grid-template-columns: 1fr 300px; gap: 32px; }
    .question-card { background: white; border-radius: var(--radius-md); padding: 40px; border: 1px solid var(--border); box-shadow: var(--shadow-md); min-height: 500px; display: flex; flex-direction: column; }
    
    .q-numbering { font-size: 0.75rem; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 16px; display: block; }
    .q-text { font-size: 1.5rem; font-weight: 800; line-height: 1.4; color: var(--text-main); margin-bottom: 32px; }

    .option-item { 
        display: flex; align-items: center; gap: 16px; padding: 20px 24px; 
        background: #f8fafc; border: 2px solid transparent; border-radius: 16px; 
        margin-bottom: 12px; cursor: pointer; transition: 0.2s; 
    }
    .option-item:hover { background: #f1f5f9; border-color: var(--border); }
    .option-item.selected { background: var(--primary-light); border-color: var(--primary); }
    
    .opt-key { width: 32px; height: 32px; border-radius: 8px; background: white; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem; color: var(--text-dim); }
    .selected .opt-key { background: var(--primary); color: white; border-color: var(--primary); }

    /* Sidebar Navigation */
    .quiz-nav-panel { background: white; border-radius: var(--radius-md); padding: 24px; border: 1px solid var(--border); position: sticky; top: 40px; }
    .q-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 16px; }
    .q-link { width: 100%; aspect-ratio: 1; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 700; background: #f1f5f9; color: var(--text-dim); text-decoration: none; cursor: pointer; border: none; }
    .q-link.answered { background: #e2e8f0; color: var(--text-main); }
    .q-link.current { border: 2px solid var(--primary); color: var(--primary); background: var(--primary-light); }
    .q-link.flagged { position: relative; }
    .q-link.flagged::after { content: ''; position: absolute; top: -2px; right: -2px; width: 8px; height: 8px; background: var(--danger); border-radius: 50%; border: 2px solid white; }

    /* Timer Widget */
    .timer-widget { background: #fff1f2; padding: 12px 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; border: 1px solid #fecdd3; }
    .timer-time { font-family: monospace; font-size: 1.25rem; font-weight: 800; color: #be123c; }

    /* Summary Table */
    .summary-table { width: 100%; border-collapse: collapse; margin-top: 24px; }
    .summary-table th { text-align: left; padding: 12px; border-bottom: 2px solid var(--border); }
    .summary-table td { padding: 16px 12px; border-bottom: 1px solid var(--border); }
</style>

<main class="main-content">
    <div class="quiz-container">
        
        <!-- --- VIEW: INTRO & HISTORY --- -->
        <div id="view-intro">
            <header class="portal-header">
                <div class="greeting">
                    <h1><?= htmlspecialchars($quiz['title']) ?></h1>
                    <p><?= htmlspecialchars($quiz['course_title']) ?> – Assessment</p>
                </div>
                <div class="header-tools">
                    <button class="btn-premium" onclick="startQuiz()">
                        <i class="fas fa-play"></i> Attempt Quiz Now
                    </button>
                </div>
            </header>

            <div class="grid-3" style="margin-bottom: 40px;">
                <div class="card">
                    <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Time Limit</span>
                    <h3 style="margin: 8px 0;"><?= $quiz['time_limit_mins'] ?> Minutes</h3>
                </div>
                <div class="card">
                    <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Pass Mark</span>
                    <h3 style="margin: 8px 0;"><?= $quiz['pass_score'] ?>%</h3>
                </div>
                <div class="card">
                    <span style="font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Total Questions</span>
                    <h3 style="margin: 8px 0;"><?= count($questions) ?> Units</h3>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0; font-weight: 800;">Your attempts</h3>
                <?php if($attempts): ?>
                <table class="attempt-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Date / Time</th>
                            <th>Marks</th>
                            <th>Grade</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($attempts as $a): 
                            $dt = new DateTime($a['completed_at']);
                        ?>
                        <tr>
                            <td><span style="color: var(--success); font-weight: 800;"><i class="fas fa-check-circle"></i> Finished</span></td>
                            <td><?= $dt->format('l, j F Y, g:i A') ?></td>
                            <td><?= round($a['score']) ?> / 100</td>
                            <td style="color: <?= $a['passed'] ? 'var(--success)' : 'var(--danger)' ?>; font-weight: 800;">
                                <?= $a['passed'] ? 'PASS' : 'RETAKE' ?>
                            </td>
                            <td>Review not permitted</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: var(--text-dim); padding: 20px 0;">No attempts have been recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- --- VIEW: PLAYER --- -->
        <div id="view-player" style="display: none;">
            <div class="quiz-player-layout">
                <div class="player-main">
                    <div id="questions-pool">
                        <?php foreach($questions as $idx => $q): 
                            $opts = json_decode($q['options_json'], true) ?: [];
                        ?>
                        <div class="question-view" id="qv-<?= $idx ?>" style="display: none;">
                            <div class="question-card">
                                <span class="q-numbering">Question <?= $idx + 1 ?> of <?= count($questions) ?></span>
                                <h2 class="q-text"><?= htmlspecialchars($q['question']) ?></h2>
                                
                                <div class="options-container" style="flex: 1;">
                                    <?php if($q['type'] == 'mcq' || $q['type'] == 'tf'): ?>
                                        <?php foreach($opts as $k => $o): ?>
                                        <div class="option-item" onclick="saveAnswer(<?= $idx ?>, '<?= addslashes($o) ?>', this)">
                                            <div class="opt-key"><?= chr(65 + $k) ?></div>
                                            <div style="font-weight: 700;"><?= htmlspecialchars($o) ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <textarea class="card" style="width: 100%; min-height: 200px; padding: 24px; font-family: inherit; font-size: 1rem; border-color: var(--border); resize: vertical;" placeholder="Type your response here..." oninput="saveAnswer(<?= $idx ?>, this.value)"></textarea>
                                    <?php endif; ?>
                                </div>

                                <div style="margin-top: 40px; display: flex; justify-content: space-between; align-items: center;">
                                    <button class="btn-premium" style="background: #f1f5f9; color: var(--text-main); box-shadow: none;" onclick="prevQ()" id="prevBtn" disabled>
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </button>
                                    <button class="btn-premium" style="background: #fef2f2; color: var(--danger); box-shadow: none; font-size: 0.8rem;" onclick="toggleFlag(<?= $idx ?>)">
                                        <i class="fas fa-flag"></i> <span id="flag-text-<?= $idx ?>">Flag question</span>
                                    </button>
                                    <button class="btn-premium" onclick="nextQ()" id="nextBtn">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="player-sidebar">
                    <div class="quiz-nav-panel">
                        <div class="timer-widget">
                            <span style="font-weight: 800; font-size: 0.7rem; color: #be123c;">TIME LEFT</span>
                            <div id="countdown" class="timer-time">--:--</div>
                        </div>
                        
                        <h4 style="margin: 0; font-size: 0.9rem; font-weight: 800;">Quiz Navigation</h4>
                        <div class="q-grid">
                            <?php for($i=0; $i<count($questions); $i++): ?>
                            <button class="q-link" id="nav-<?= $i ?>" onclick="goToQ(<?= $i ?>)"><?= $i+1 ?></button>
                            <?php endfor; ?>
                        </div>
                        
                        <button class="btn-premium" style="width: 100%; margin-top: 24px; justify-content: center;" onclick="showSummary()">
                            Finish attempt...
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- --- VIEW: SUMMARY --- -->
        <div id="view-summary" style="display: none;">
            <header class="portal-header">
                <div class="greeting">
                    <h1>Summary of attempt</h1>
                    <p><?= htmlspecialchars($quiz['title']) ?></p>
                </div>
            </header>

            <div class="card">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="summary-body">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 40px; text-align: center;">
                <button class="btn-premium" style="background: #f1f5f9; color: var(--text-main); margin-right: 20px; box-shadow: none;" onclick="resumeQuiz()">
                    Return to attempt
                </button>
                <button class="btn-premium" style="background: var(--success);" onclick="submitQuiz()">
                    Submit all and finish
                </button>
            </div>
        </div>

        <!-- --- VIEW: RESULT --- -->
        <div id="view-result" style="display: none; text-align: center; padding: 60px 0;">
            <div id="res-icon" style="font-size: 5rem; margin-bottom: 24px;"></div>
            <h1 id="res-title" style="margin-bottom: 12px; font-weight: 800;"></h1>
            <p id="res-desc" style="color: var(--text-dim); max-width: 600px; margin: 0 auto 40px;"></p>
            
            <div class="card" style="display: inline-block; padding: 40px 80px; min-width: 300px;">
                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Final Grade</span>
                <div id="res-score" style="font-size: 4rem; font-weight: 800; color: var(--primary); margin: 10px 0;">0%</div>
                <div id="res-badge"></div>
            </div>

            <div style="margin-top: 60px;">
                <a href="quizzes.php" class="btn-premium">Done</a>
                <button class="btn-premium" style="background: #f1f5f9; color: var(--text-main); margin-left:15px;" onclick="location.reload()">Re-attempt</button>
            </div>
        </div>

    </div>
</main>

<script>
let currentIdx = 0;
const totalQs = <?= count($questions) ?>;
const limitSecs = <?= $quiz['time_limit_mins'] ?> * 60;
let remaining = limitSecs;
let timerId = null;
let answers = {};
let flagged = new Set();

function startQuiz() {
    document.getElementById('view-intro').style.display = 'none';
    document.getElementById('view-player').style.display = 'block';
    goToQ(0);
    startTimer();
}

function goToQ(n) {
    document.querySelectorAll('.question-view').forEach(v => v.style.display = 'none');
    document.getElementById('qv-' + n).style.display = 'block';
    
    document.querySelectorAll('.q-link').forEach(l => l.classList.remove('current'));
    document.getElementById('nav-' + n).classList.add('current');
    
    currentIdx = n;
    
    // Update Buttons
    document.getElementById('prevBtn').disabled = (n === 0);
    document.getElementById('prevBtn').style.opacity = (n === 0) ? '0.5' : '1';
    
    const nextBtn = document.getElementById('nextBtn');
    if (n === totalQs - 1) {
        nextBtn.innerHTML = 'Finish <i class="fas fa-flag-checkered"></i>';
        nextBtn.onclick = showSummary;
    } else {
        nextBtn.innerHTML = 'Next <i class="fas fa-chevron-right"></i>';
        nextBtn.onclick = nextQ;
    }
}

function nextQ() { if(currentIdx < totalQs - 1) goToQ(currentIdx + 1); }
function prevQ() { if(currentIdx > 0) goToQ(currentIdx - 1); }

function saveAnswer(idx, val, el = null) {
    answers[idx] = val;
    document.getElementById('nav-' + idx).classList.add('answered');
    
    if(el) {
        const parent = el.parentElement;
        parent.querySelectorAll('.option-item').forEach(opt => opt.classList.remove('selected'));
        el.classList.add('selected');
    }
}

function toggleFlag(idx) {
    const nav = document.getElementById('nav-' + idx);
    const txt = document.getElementById('flag-text-' + idx);
    if(flagged.has(idx)) {
        flagged.delete(idx);
        nav.classList.remove('flagged');
        txt.innerText = 'Flag question';
    } else {
        flagged.add(idx);
        nav.classList.add('flagged');
        txt.innerText = 'Unflag';
    }
}

function startTimer() {
    timerId = setInterval(() => {
        remaining--;
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        document.getElementById('countdown').innerText = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
        
        if(remaining <= 0) {
            clearInterval(timerId);
            submitQuiz();
        }
    }, 1000);
}

function showSummary() {
    document.getElementById('view-player').style.display = 'none';
    document.getElementById('view-summary').style.display = 'block';
    
    const body = document.getElementById('summary-body');
    body.innerHTML = '';
    for(let i=0; i<totalQs; i++) {
        const status = answers[i] ? 'Answer saved' : 'Not yet answered';
        body.innerHTML += `<tr>
            <td>Question ${i+1}</td>
            <td style="font-weight:800; color: ${answers[i] ? 'var(--text-main)' : 'var(--danger)'}">${status}</td>
        </tr>`;
    }
}

function resumeQuiz() {
    document.getElementById('view-summary').style.display = 'none';
    document.getElementById('view-player').style.display = 'block';
}

function submitQuiz() {
    clearInterval(timerId);
    
    // Transform indices back to question IDs for the handler
    const finalAnswers = {};
    const qIds = [<?php foreach($questions as $q) echo $q['id'].','; ?>];
    for(let idx in answers) {
        finalAnswers[qIds[idx]] = answers[idx];
    }

    fetch('submit-quiz-handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quiz_id: <?= $qid ?>, answers: finalAnswers })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('view-summary').style.display = 'none';
        document.getElementById('view-player').style.display = 'none';
        document.getElementById('view-result').style.display = 'block';
        
        document.getElementById('res-score').innerText = data.score + '%';
        
        if (data.passed) {
            document.getElementById('res-icon').innerHTML = '🏆';
            document.getElementById('res-title').innerText = 'Exceptional Achievement!';
            document.getElementById('res-desc').innerText = 'You have successfully navigated this academic challenge. Your points have been synchronized with your profile.';
            document.getElementById('res-score').style.color = 'var(--success)';
            document.getElementById('res-badge').innerHTML = '<span style="background: rgba(16,185,129,0.1); color: var(--success); padding: 8px 24px; border-radius: 99px; font-weight: 800; font-size: 0.8rem;">OFFICIAL PASS</span>';
        } else {
            document.getElementById('res-icon').innerHTML = '💡';
            document.getElementById('res-title').innerText = 'Almost There!';
            document.getElementById('res-desc').innerText = 'You didn\'t reach the pass mark this time. Review the course material and attempt again to strengthen your knowledge.';
            document.getElementById('res-score').style.color = 'var(--danger)';
            document.getElementById('res-badge').innerHTML = '<span style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 8px 24px; border-radius: 99px; font-weight: 800; font-size: 0.8rem;">RETAKE RECOMMENDED</span>';
        }
    });
}
</script>
</body>
</html>
