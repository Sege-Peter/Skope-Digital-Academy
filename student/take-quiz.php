<?php
$pageTitle = 'Quiz';
require_once 'includes/header.php';

$qid = (int)($_GET['id'] ?? 0);
if (!$qid) { header('Location: quizzes'); exit; }

try {
    // 1. Fetch Quiz Info
    $stmt = $pdo->prepare("SELECT q.*, c.title as course_title, c.thumbnail as course_thumb 
                           FROM quizzes q 
                           JOIN courses c ON q.course_id = c.id 
                           WHERE q.id = ?");
    $stmt->execute([$qid]);
    $quiz = $stmt->fetch();
    if (!$quiz) { header('Location: quizzes'); exit; }

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
    header('Location: quizzes'); 
    exit; 
}

require_once 'includes/layout-top.php';
$q_total = count($questions);
?>

<style>
    .pg-quiz { max-width: 1040px; margin: 0 auto; }
    .pg-quiz .tq-crumbs { font-size: .8rem; font-weight: 600; color: var(--text-dim); margin-bottom: 6px; }
    .pg-quiz .tq-crumbs a { color: var(--text-dim); text-decoration: none; }
    .pg-quiz .tq-crumbs a:hover { color: var(--primary-dark); }

    /* Player */
    .pg-quiz .quiz-player-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 20px; align-items: start; }
    .pg-quiz .tq-question { padding: 28px; display: flex; flex-direction: column; min-height: 420px; }
    .pg-quiz .q-numbering { display: inline-block; font-size: .78rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 10px; }
    .pg-quiz .q-text { font-size: 1.25rem; font-weight: 800; line-height: 1.45; margin: 0 0 22px; overflow-wrap: anywhere; }
    .pg-quiz .option-item {
        display: flex; align-items: center; gap: 14px; padding: 14px 16px; margin-bottom: 10px;
        background: var(--surface); border: 1.5px solid var(--border); border-radius: 14px; cursor: pointer;
        transition: border-color .15s, background .15s; font-weight: 600; overflow-wrap: anywhere;
    }
    .pg-quiz .option-item:hover { border-color: var(--primary); }
    .pg-quiz .option-item.selected { background: var(--primary-soft); border-color: var(--primary); }
    .pg-quiz .opt-key { width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0; background: var(--surface-2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .8rem; color: var(--text-dim); }
    .pg-quiz .selected .opt-key { background: var(--primary); color: #fff; border-color: var(--primary); }
    .pg-quiz .tq-answer { width: 100%; min-height: 180px; padding: 14px; border-radius: 12px; border: 1px solid var(--border-strong); font: inherit; font-size: .95rem; resize: vertical; outline: none; box-sizing: border-box; }
    .pg-quiz .tq-answer:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
    .pg-quiz .tq-flag { margin-top: 6px; background: none; border: 0; padding: 6px 0; cursor: pointer; font: inherit; font-size: .8rem; font-weight: 700; color: var(--danger); }
    .pg-quiz .tq-nav { margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; gap: 10px; }
    .pg-quiz #prevBtn:disabled { opacity: .5; pointer-events: none; }

    /* Side panel: timer + question grid */
    .pg-quiz .quiz-nav-panel { position: sticky; top: calc(var(--topbar-h) + 16px); }
    .pg-quiz .timer-widget { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 12px; background: var(--danger-soft); color: #9B1C1C; margin-bottom: 18px; font-size: .8rem; font-weight: 700; }
    .pg-quiz .timer-time { font-family: ui-monospace, Consolas, monospace; font-size: 1.3rem; font-weight: 800; }
    .pg-quiz .q-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 12px; }
    .pg-quiz .q-link { position: relative; aspect-ratio: 1; border-radius: 9px; border: 1px solid var(--border); background: var(--surface-2); color: var(--text-dim); font: inherit; font-size: .85rem; font-weight: 700; cursor: pointer; }
    .pg-quiz .q-link.answered { background: var(--primary-soft); color: var(--primary-dark); border-color: transparent; }
    .pg-quiz .q-link.current { border: 2px solid var(--primary); color: var(--primary-dark); }
    .pg-quiz .q-link.flagged::after { content: ''; position: absolute; top: -3px; right: -3px; width: 9px; height: 9px; background: var(--danger); border-radius: 50%; border: 2px solid #fff; }
    .pg-quiz .tq-legend { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; font-size: .72rem; color: var(--text-dim); }
    .pg-quiz .tq-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; }

    /* Result */
    .pg-quiz #view-result { text-align: center; }
    .pg-quiz #res-icon { font-size: 3.4rem; line-height: 1; margin-bottom: 12px; }
    .pg-quiz #res-score { font-size: 3.2rem; font-weight: 800; color: var(--primary); line-height: 1.1; margin: 6px 0 10px; }

    @media (max-width: 900px) {
        .pg-quiz .quiz-player-layout { grid-template-columns: minmax(0, 1fr); }
        .pg-quiz .player-sidebar { order: -1; }
        .pg-quiz .quiz-nav-panel { position: static; }
        .pg-quiz .q-grid { grid-template-columns: repeat(auto-fill, minmax(40px, 1fr)); }
    }
    @media (max-width: 640px) {
        .pg-quiz .tq-question { padding: 18px; min-height: 0; }
        .pg-quiz .q-text { font-size: 1.08rem; }
    }
</style>

<div class="pg-quiz">

    <!-- Intro and past attempts -->
    <div id="view-intro">
        <div class="sp-page-head">
            <div style="min-width: 0;">
                <div class="tq-crumbs"><a href="quizzes">Quizzes</a> · <?= htmlspecialchars($quiz['course_title']) ?></div>
                <h1><?= htmlspecialchars($quiz['title']) ?></h1>
                <p>Read the details below, then start when you're ready. The timer starts as soon as you begin.</p>
            </div>
            <div class="sp-actions">
                <?php if ($q_total > 0): ?>
                    <button type="button" class="sp-btn lg" onclick="startQuiz()"><i class="fas fa-play"></i> <?= $attempts ? 'Start a new attempt' : 'Start quiz' ?></button>
                <?php else: ?>
                    <button type="button" class="sp-btn lg" disabled>No questions yet</button>
                <?php endif; ?>
            </div>
        </div>

        <section class="sp-grid cols-3 sp-section">
            <div class="sp-stat"><span class="sp-stat-icon orange"><i class="far fa-clock"></i></span><div><strong><?= (int)$quiz['time_limit_mins'] ?> min</strong><span>Time limit</span></div></div>
            <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-bullseye"></i></span><div><strong><?= (int)$quiz['pass_score'] ?>%</strong><span>Pass mark</span></div></div>
            <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-list-ol"></i></span><div><strong><?= $q_total ?></strong><span>Question<?= $q_total === 1 ? '' : 's' ?></span></div></div>
        </section>

        <?php if ($q_total === 0): ?>
            <div class="sp-alert warning"><i class="fas fa-triangle-exclamation" style="margin-top: 3px;"></i><div>Your tutor hasn't added questions to this quiz yet. Check back later.</div></div>
        <?php endif; ?>

        <section class="sp-section">
            <div class="sp-section-head"><div><h2>Your attempts</h2></div></div>
            <?php if ($attempts): ?>
            <div class="sp-table-wrap">
                <table class="sp-table">
                    <thead>
                        <tr><th>Status</th><th>Date</th><th>Score</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $a):
                            $dt = new DateTime($a['completed_at'] ?? 'now');
                        ?>
                        <tr>
                            <td><span class="sp-badge gray"><i class="fas fa-check"></i> Finished</span></td>
                            <td style="white-space: nowrap;"><?= $dt->format('D, j M Y, g:i A') ?></td>
                            <td><strong><?= round($a['score']) ?>%</strong></td>
                            <td><?= $a['passed'] ? '<span class="sp-badge green">Passed</span>' : '<span class="sp-badge red">Try again</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="sp-card"><p class="sp-muted" style="margin: 0;">You haven't attempted this quiz yet.</p></div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Player -->
    <div id="view-player" style="display: none;">
        <div class="sp-page-head">
            <div style="min-width: 0;"><h1><?= htmlspecialchars($quiz['title']) ?></h1><p>Pick an answer for each question. You can move between questions at any time.</p></div>
        </div>
        <div class="quiz-player-layout">
            <div class="player-main">
                <div class="sp-card tq-question">
                    <div id="questions-pool">
                        <?php foreach ($questions as $idx => $q):
                            $opts = json_decode($q['options_json'], true) ?: [];
                        ?>
                        <div class="question-view" id="qv-<?= $idx ?>" style="display: none;">
                            <span class="q-numbering">Question <?= $idx + 1 ?> of <?= $q_total ?></span>
                            <h2 class="q-text"><?= htmlspecialchars($q['question']) ?></h2>

                            <div class="options-container">
                                <?php if ($q['type'] == 'mcq' || $q['type'] == 'tf'): ?>
                                    <?php foreach ($opts as $k => $o): ?>
                                    <div class="option-item" role="button" tabindex="0" onclick="saveAnswer(<?= $idx ?>, <?= htmlspecialchars(json_encode((string)$o, JSON_INVALID_UTF8_SUBSTITUTE)) ?>, this)" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }">
                                        <div class="opt-key"><?= chr(65 + $k) ?></div>
                                        <div><?= htmlspecialchars($o) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <textarea class="tq-answer" placeholder="Type your answer here..." oninput="saveAnswer(<?= $idx ?>, this.value)" aria-label="Your answer"></textarea>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="tq-flag" onclick="toggleFlag(<?= $idx ?>)">
                                <i class="fas fa-flag"></i> <span id="flag-text-<?= $idx ?>">Flag question</span>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="tq-nav">
                        <button type="button" class="sp-btn ghost" onclick="prevQ()" id="prevBtn" disabled>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        <button type="button" class="sp-btn" onclick="nextQ()" id="nextBtn">
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="player-sidebar">
                <div class="sp-card quiz-nav-panel">
                    <div class="timer-widget">
                        <span><i class="far fa-clock"></i> Time left</span>
                        <div id="countdown" class="timer-time">--:--</div>
                    </div>

                    <h4 style="margin: 0; font-size: .9rem; font-weight: 800;">Questions</h4>
                    <div class="q-grid">
                        <?php for ($i = 0; $i < $q_total; $i++): ?>
                        <button type="button" class="q-link" id="nav-<?= $i ?>" onclick="goToQ(<?= $i ?>)" aria-label="Question <?= $i + 1 ?>"><?= $i + 1 ?></button>
                        <?php endfor; ?>
                    </div>
                    <div class="tq-legend">
                        <span><i style="background: var(--primary-soft);"></i>Answered</span>
                        <span><i style="background: var(--danger); border-radius: 50%;"></i>Flagged</span>
                    </div>

                    <button type="button" class="sp-btn block" style="margin-top: 18px;" onclick="showSummary()">
                        Finish quiz
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary before submitting -->
    <div id="view-summary" style="display: none;">
        <div class="sp-page-head">
            <div style="min-width: 0;"><h1>Check your answers</h1><p><?= htmlspecialchars($quiz['title']) ?>: go back to any question you missed, or submit now.</p></div>
        </div>

        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead>
                    <tr><th>Question</th><th>Status</th></tr>
                </thead>
                <tbody id="summary-body">
                    <!-- Filled by JS -->
                </tbody>
            </table>
        </div>

        <div class="sp-row sp-mt" style="justify-content: center; margin-top: 24px;">
            <button type="button" class="sp-btn ghost" onclick="resumeQuiz()"><i class="fas fa-chevron-left"></i> Back to the quiz</button>
            <button type="button" class="sp-btn" style="background: var(--success);" onclick="submitQuiz()"><i class="fas fa-paper-plane"></i> Submit answers</button>
        </div>
    </div>

    <!-- Result -->
    <div id="view-result" style="display: none;">
        <div class="sp-card" style="max-width: 560px; margin: 24px auto 0; padding: 36px 24px;">
            <div id="res-icon"></div>
            <h1 id="res-title" style="margin: 0 0 8px; font-size: 1.5rem; font-weight: 800;"></h1>
            <p id="res-desc" class="sp-muted" style="max-width: 440px; margin: 0 auto 18px;"></p>
            <span class="sp-muted sp-small" style="font-weight: 700;">Your score</span>
            <div id="res-score">0%</div>
            <div id="res-badge"></div>
            <div class="sp-row" style="justify-content: center; margin-top: 26px;">
                <a href="quizzes" class="sp-btn">Back to quizzes</a>
                <button type="button" class="sp-btn ghost" onclick="location.reload()"><i class="fas fa-rotate-right"></i> Try again</button>
            </div>
        </div>
    </div>

</div>

<script>
let currentIdx = 0;
const totalQs = <?= $q_total ?>;
const limitSecs = <?= (int)$quiz['time_limit_mins'] ?> * 60;
let remaining = limitSecs;
let timerId = null;
let answers = {};
let flagged = new Set();

function startQuiz() {
    document.getElementById('view-intro').style.display = 'none';
    document.getElementById('view-player').style.display = 'block';
    goToQ(0);
    startTimer();
    window.scrollTo(0, 0);
}

function goToQ(n) {
    document.querySelectorAll('.question-view').forEach(v => v.style.display = 'none');
    document.getElementById('qv-' + n).style.display = 'block';

    document.querySelectorAll('.q-link').forEach(l => l.classList.remove('current'));
    document.getElementById('nav-' + n).classList.add('current');

    currentIdx = n;

    // Update Buttons
    document.getElementById('prevBtn').disabled = (n === 0);

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
        txt.innerText = 'Remove flag';
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
        const status = answers[i] ? '<span class="sp-badge green">Answered</span>' : '<span class="sp-badge red">Not answered</span>';
        body.innerHTML += `<tr>
            <td>Question ${i+1}${flagged.has(i) ? ' <i class="fas fa-flag" style="color: var(--danger); font-size: .75rem;" title="Flagged"></i>' : ''}</td>
            <td>${status}</td>
        </tr>`;
    }
    window.scrollTo(0, 0);
}

function resumeQuiz() {
    document.getElementById('view-summary').style.display = 'none';
    document.getElementById('view-player').style.display = 'block';
}

function submitQuiz() {
    clearInterval(timerId);

    // Transform indices back to question IDs for the handler
    const finalAnswers = {};
    const qIds = [<?php foreach($questions as $q) echo (int)$q['id'].','; ?>];
    for(let idx in answers) {
        finalAnswers[qIds[idx]] = answers[idx];
    }

    fetch('submit-quiz-handler', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ quiz_id: <?= $qid ?>, answers: finalAnswers })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('view-summary').style.display = 'none';
        document.getElementById('view-player').style.display = 'none';
        document.getElementById('view-result').style.display = 'block';
        window.scrollTo(0, 0);

        document.getElementById('res-score').innerText = data.score + '%';

        if (data.passed) {
            document.getElementById('res-icon').innerHTML = '🏆';
            document.getElementById('res-title').innerText = 'You passed!';
            document.getElementById('res-desc').innerText = 'Well done. Your result has been saved to your profile.';
            document.getElementById('res-score').style.color = 'var(--success)';
            document.getElementById('res-badge').innerHTML = '<span class="sp-badge green">Passed</span>';
        } else {
            document.getElementById('res-icon').innerHTML = '💡';
            document.getElementById('res-title').innerText = 'Not quite there yet';
            document.getElementById('res-desc').innerText = 'You didn\'t reach the pass mark this time. Review the lessons and try again.';
            document.getElementById('res-score').style.color = 'var(--danger)';
            document.getElementById('res-badge').innerHTML = '<span class="sp-badge red">Try again</span>';
        }
    })
    .catch(() => {
        alert('Sorry, we could not submit your answers. Check your connection and press "Submit answers" again.');
        document.getElementById('view-player').style.display = 'none';
        document.getElementById('view-summary').style.display = 'block';
    });
}
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
