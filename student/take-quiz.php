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
    $stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? ORDER BY completed_at ASC");
    $stmt->execute([$qid, $student['id']]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) { 
    error_log($e->getMessage());
    header('Location: quizzes.php'); 
    exit; 
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    :root {
        --primary: #00BFFF;
        --secondary: #FF8C00;
        --bg-color: #f8fafc;
        --card-shadow: 0 20px 50px rgba(0,0,0,0.08);
    }
    .main-content { background: #fff; padding: 40px; }
    .exam-layout { max-width: 900px; margin: 40px auto; }
    
    .exam-card { background: white; border: 1px solid var(--dark-border); border-radius: 40px; padding: 60px; box-shadow: var(--card-shadow); position: relative; overflow: hidden; }
    .exam-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px; background: linear-gradient(90deg, var(--primary), var(--secondary)); }

    /* Views */
    .view-container { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; text-align: center; }
    @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

    .stat-ring { width: 120px; height: 120px; border-radius: 50%; border: 4px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .stat-val { font-size: 1.6rem; font-weight: 800; color: var(--primary); }
    .stat-lbl { font-size: 0.65rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; }

    /* Player UI */
    .question-view { display: none; text-align: left; }
    .question-view.active { display: block; }
    
    .q-badge { display: inline-block; padding: 6px 14px; background: rgba(0,191,255,0.08); color: var(--primary); border-radius: 99px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 24px; }
    .q-text { font-family: 'Poppins', sans-serif; font-size: 1.85rem; font-weight: 800; color: #0f172a; margin-bottom: 40px; line-height: 1.35; }

    /* Options & Inputs */
    .option-btn { 
        display: flex; align-items: center; gap: 20px; width: 100%; padding: 24px 32px; 
        background: #f8fafc; border: 2px solid transparent; border-radius: 20px; 
        margin-bottom: 16px; cursor: pointer; transition: 0.3s; text-align: left; 
    }
    .option-btn:hover { background: #f1f5f9; border-color: #e2e8f0; }
    .option-btn.selected { background: rgba(0,191,255,0.05); border-color: var(--primary); box-shadow: 0 4px 15px rgba(0,191,255,0.1); }
    
    .opt-key { width: 36px; height: 36px; border-radius: 12px; background: white; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 0.85rem; flex-shrink: 0; color: #64748b; }
    .selected .opt-key { background: var(--primary); color: white; border-color: var(--primary); }

    .textarea-input { width: 100%; min-height: 200px; padding: 32px; border-radius: 24px; border: 2px solid #f1f5f9; background: #f8fafc; font-family: inherit; font-size: 1.1rem; line-height: 1.6; transition: 0.3s; resize: none; }
    .textarea-input:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 10px 30px rgba(0,191,255,0.08); }

    /* Global Info */
    .timer-badge { position: fixed; bottom: 40px; right: 40px; background: #0f172a; color: white; padding: 18px 36px; border-radius: 40px; font-weight: 800; font-size: 1.4rem; display: flex; align-items: center; gap: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); z-index: 100; border: 2px solid var(--primary); }

    .result-score-card { background: #f8fafc; border-radius: 40px; padding: 60px; margin-bottom: 48px; position: relative; }
    .result-score-card.pass { border-top: 8px solid #10b981; }
    .result-score-card.fail { border-top: 8px solid #ef4444; }
</style>

<main class="main-content">
    <div class="exam-layout">
        <div class="exam-card">
            <!-- Loader Overlay -->
            <div id="exam-loader" style="display: none; position: absolute; inset: 0; background: rgba(255,255,255,0.95); z-index: 200; flex-direction: column; align-items: center; justify-content: center;">
                <i class="fas fa-satellite-dish fa-spin" style="font-size: 4rem; color: var(--primary); margin-bottom: 24px;"></i>
                <h3 style="font-weight: 800; font-family: 'Poppins', sans-serif;">Synchronizing Results...</h3>
            </div>

            <!-- View 1: Onboarding -->
            <div id="view-intro" class="view-container">
                <div style="font-size: 4.5rem; color: var(--primary); margin-bottom: 32px;"><i class="fas <?= $quiz['is_cat'] ? 'fa-medal' : 'fa-brain' ?>"></i></div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 2.5rem; font-weight: 800; margin-bottom: 12px;"><?= htmlspecialchars($quiz['title']) ?></h1>
                <?php if($quiz['is_cat']): ?>
                    <div style="display: inline-block; padding: 6px 18px; background: rgba(255,140,0,0.1); color: var(--secondary); border-radius: 99px; font-size: 0.8rem; font-weight: 800; margin-bottom: 32px;">CONTINUOUS ASSESSMENT TEST (CAT)</div>
                <?php endif; ?>
                
                <div style="display: flex; justify-content: center; gap: 32px; margin-bottom: 56px;">
                    <div class="stat-ring">
                        <span class="stat-val"><?= count($questions) ?></span>
                        <span class="stat-lbl">Questions</span>
                    </div>
                    <div class="stat-ring">
                        <span class="stat-val"><?= $quiz['time_limit_mins'] ?>m</span>
                        <span class="stat-lbl">Duration</span>
                    </div>
                    <div class="stat-ring">
                        <span class="stat-val"><?= $quiz['pass_score'] ?>%</span>
                        <span class="stat-lbl">Pass Mark</span>
                    </div>
                </div>

                <div style="text-align: left; background: #fafafa; border: 1px solid #f1f5f9; border-radius: 24px; padding: 32px; margin-bottom: 56px;">
                    <h4 style="font-weight: 800; margin-bottom: 16px;"><i class="fas fa-shield-alt text-primary"></i> Assessment Protocol</h4>
                    <ul style="font-size: 0.95rem; color: #64748b; line-height: 1.8;">
                        <li>Timed Engagement: Timer cannot be paused once initiated.</li>
                        <li>Auto-Marking: Responses are evaluated instantly upon submission.</li>
                        <li>Partial Credit: Multi-keywords in text answers are scored proportionally.</li>
                    </ul>
                </div>

                <button class="btn btn-primary" onclick="startExam()" style="padding: 22px 64px; border-radius: 20px; font-weight: 800; font-size: 1.2rem; border: none; box-shadow: 0 15px 30px rgba(0,191,255,0.25);">
                    Begin Assessment Now <i class="fas fa-power-off" style="margin-left: 12px;"></i>
                </button>
            </div>

            <!-- View 2: Examination Floor -->
            <div id="view-player" style="display: none;">
                <div style="margin-bottom: 48px;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 16px;">
                        <span id="q-counter">Question 1 of <?= count($questions) ?></span>
                        <span id="p-percent">0% Completed</span>
                    </div>
                    <div style="height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                        <div id="p-bar" style="height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary)); width: 0%; transition: 0.4s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                    </div>
                </div>

                <div id="questions-pool">
                    <?php foreach($questions as $idx => $q): 
                        $opts = json_decode($q['options_json'], true) ?: [];
                    ?>
                    <div class="question-view" id="qv-<?= $idx ?>">
                        <span class="q-badge"><?= $q['type'] == 'mcq' ? 'Multiple Choice' : ($q['type'] == 'tf' ? 'True / False' : 'Expository Response') ?></span>
                        <h2 class="q-text"><?= htmlspecialchars($q['question']) ?></h2>
                        
                        <div class="response-area">
                            <?php if($q['type'] == 'mcq' || $q['type'] == 'tf'): ?>
                                <div class="options-stack">
                                    <?php foreach($opts as $k => $o): ?>
                                    <div class="option-btn" onclick="grabOption(<?= $idx ?>, '<?= addslashes($o) ?>', this)">
                                        <div class="opt-key"><?= chr(65 + $k) ?></div>
                                        <div style="font-weight: 700; font-size: 1.15rem;"><?= htmlspecialchars($o) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <textarea class="textarea-input" placeholder="Type your response here... (Academic keywords will contribute to marking)" oninput="grabOption(<?= $idx ?>, this.value)"></textarea>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; justify-content: space-between; margin-top: 64px; padding-top: 40px; border-top: 2px solid #f8fafc;">
                            <button class="btn" onclick="stepBack()" <?= $idx === 0 ? 'disabled' : '' ?> style="background: transparent; border: none; font-weight: 700; opacity: <?= $idx === 0 ? 0.3 : 1 ?>;">
                                <i class="fas fa-chevron-left"></i> Previous Module
                            </button>
                            
                            <?php if($idx === count($questions) - 1): ?>
                                <button class="btn btn-primary" onclick="wrapUpExam()" style="padding: 16px 48px; border-radius: 16px; background: #0f172a; border: none; font-weight: 800;">
                                    Final Sync & Submit <i class="fas fa-upload" style="margin-left: 10px;"></i>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary" onclick="stepForward()" style="padding: 16px 48px; border-radius: 16px; font-weight: 800;">
                                    Next Challenge <i class="fas fa-chevron-right" style="margin-left: 10px;"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- View 3: Performance Report -->
            <div id="view-result" class="view-container" style="display: none;">
                <div id="r-icon" style="font-size: 6.5rem; margin-bottom: 40px;"></div>
                <h1 id="r-title" style="font-family: 'Poppins', sans-serif; font-size: 2.5rem; font-weight: 800; margin-bottom: 12px;"></h1>
                <p id="r-desc" style="color: #64748b; font-size: 1.15rem; margin-bottom: 48px; max-width: 600px; margin-inline: auto;"></p>
                
                <div id="r-card" class="result-score-card">
                    <div style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; letter-spacing: 2px; margin-bottom: 16px;">Composite Academic Score</div>
                    <div id="r-score" style="font-size: 6rem; font-weight: 900; color: #0f172a; line-height: 1;">0%</div>
                    <div id="r-points" style="margin-top: 24px; font-weight: 800; font-size: 1.1rem; color: var(--primary);">+0 Points Earned</div>
                </div>

                <div style="display: flex; gap: 20px; justify-content: center;">
                    <a href="quizzes.php" class="btn btn-primary" style="padding: 18px 48px; border-radius: 18px; font-weight: 800;">Dismiss</a>
                    <button onclick="location.reload()" class="btn" style="padding: 18px 48px; border-radius: 18px; background: #f1f5f9; color: #0f172a; font-weight: 700; border: none;">Retake Assessment</button>
                </div>
            </div>
        </div>
    </div>
</main>

<div class="timer-badge" id="global-timer" style="display: none;">
    <i class="fas fa-hourglass-half" style="color: var(--secondary);"></i>
    <span id="time-digits">--:--</span>
</div>

<script>
let activeQ = 0;
const totalQs = <?= count($questions) ?>;
const limit = <?= $quiz['time_limit_mins'] ?> * 60;
let clock, remaining = limit, answers = {};

function startExam() {
    document.getElementById('view-intro').style.display = 'none';
    document.getElementById('view-player').style.display = 'block';
    document.getElementById('global-timer').style.display = 'flex';
    renderView(0);
    startClock();
}

function renderView(n) {
    document.querySelectorAll('.question-view').forEach(v => v.classList.remove('active'));
    document.getElementById('qv-' + n).classList.add('active');
    activeQ = n;
    const pct = Math.round(((n + 1) / totalQs) * 100);
    document.getElementById('p-bar').style.width = pct + '%';
    document.getElementById('p-percent').innerText = pct + '% Completed';
    document.getElementById('q-counter').innerText = `Challenge ${n + 1} of ${totalQs}`;
}

function grabOption(qIdx, val, el = null) {
    answers[qIdx] = val;
    if(el) {
        const pool = el.closest('.options-stack');
        pool.querySelectorAll('.option-btn').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
    }
}

function stepForward() { if (activeQ < totalQs - 1) renderView(activeQ + 1); }
function stepBack() { if (activeQ > 0) renderView(activeQ - 1); }

function startClock() {
    clock = setInterval(() => {
        remaining--;
        const m = Math.floor(remaining / 60), s = remaining % 60;
        const timeStr = `${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;
        document.getElementById('time-digits').innerText = timeStr;
        if (remaining <= 30) document.getElementById('global-timer').style.borderColor = '#ef4444';
        if (remaining <= 0) { clearInterval(clock); wrapUpExam(); }
    }, 1000);
}

function wrapUpExam() {
    clearInterval(clock);
    document.getElementById('exam-loader').style.display = 'flex';
    fetch('submit-quiz-handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quiz_id: <?= $qid ?>, answers: answers })
    })
    .then(r => r.json())
    .then(data => {
        setTimeout(() => {
            document.getElementById('exam-loader').style.display = 'none';
            document.getElementById('view-player').style.display = 'none';
            document.getElementById('global-timer').style.display = 'none';
            document.getElementById('view-result').style.display = 'block';
            document.getElementById('r-score').innerText = data.score + '%';
            document.getElementById('r-points').innerText = `+${data.points} Merit Points Earned`;
            
            const rCard = document.getElementById('r-card');
            if (data.passed) {
                rCard.className = 'result-score-card pass';
                document.getElementById('r-icon').innerHTML = '<i class="fas fa-crown" style="color: #FFD700;"></i>';
                document.getElementById('r-title').innerText = 'Elite Scholarly Achievement!';
                document.getElementById('r-desc').innerText = 'You have successfully navigated this academic challenge. Your points have been synchronized with your profile.';
                document.getElementById('r-score').style.color = '#10B981';
            } else {
                rCard.className = 'result-score-card fail';
                document.getElementById('r-icon').innerHTML = '<i class="fas fa-lightbulb" style="color: #94a3b8;"></i>';
                document.getElementById('r-title').innerText = 'Room for Growth';
                document.getElementById('r-desc').innerText = `You didn't reach the ${<?= $quiz['pass_score'] ?>}% pass mark this time. Review the course Track and attempt again to build your merit.`;
                document.getElementById('r-score').style.color = '#EF4444';
                document.getElementById('r-points').style.display = 'none';
            }
        }, 1500);
    })
    .catch(() => {
        alert('Operational sync failed. Please check your connection.');
        document.getElementById('exam-loader').style.display = 'none';
    });
}
</script>
</body>
</html>
