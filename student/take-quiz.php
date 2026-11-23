<?php
$pageTitle = 'Quiz';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/exam.php';

$qid = (int)($_GET['id'] ?? 0);
if (!$qid) { header('Location: quizzes'); exit; }
sda_exam_ensure($pdo);

$closedNotice = null;
try {
    $stmt = $pdo->prepare("SELECT q.*, c.title as course_title, c.thumbnail as course_thumb FROM quizzes q JOIN courses c ON q.course_id = c.id WHERE q.id = ?");
    $stmt->execute([$qid]);
    $quiz = $stmt->fetch();
    if (!$quiz) { header('Location: quizzes'); exit; }

    // A test that was closed or reloaded before submitting is finished now, with the answers saved so far.
    if ($open = sda_exam_open_attempt($pdo, $qid, (int)$student['id'])) {
        $closedNotice = sda_exam_finalize($pdo, (int)$open['id'], (int)$student['id'], null, 'closed_early');
    }

    $stmt = $pdo->prepare("SELECT id, question, type, options_json, points FROM quiz_questions WHERE quiz_id = ? ORDER BY order_num ASC, id ASC");
    $stmt->execute([$qid]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? AND status = 'completed' ORDER BY completed_at DESC");
    $stmt->execute([$qid, $student['id']]);
    $attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: quizzes');
    exit;
}

require_once 'includes/layout-top.php';
$q_total = count($questions);
$kind = $quiz['type'] === 'final' ? 'exam' : ($quiz['type'] === 'cat' ? 'test' : 'quiz');
$reasonText = ['closed_early' => 'Closed before submitting', 'violations' => 'Left the test too often', 'time_up' => 'Time ran out', 'submitted' => 'Submitted'];
$langs = ['html' => 'HTML', 'css' => 'CSS', 'javascript' => 'JavaScript', 'python' => 'Python', 'php' => 'PHP', 'sql' => 'SQL'];
?>

<style>
    .pg-quiz { max-width: 1040px; margin: 0 auto; }
    .pg-quiz .tq-crumbs { font-size: .8rem; font-weight: 600; color: var(--text-dim); margin-bottom: 6px; }
    .pg-quiz .tq-crumbs a { color: var(--text-dim); text-decoration: none; }
    .pg-quiz .quiz-player-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 20px; align-items: start; }
    .pg-quiz .tq-question { padding: 28px; display: flex; flex-direction: column; min-height: 420px; }
    .pg-quiz .q-numbering { display: inline-block; font-size: .78rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 10px; }
    .pg-quiz .q-text { font-size: 1.25rem; font-weight: 800; line-height: 1.45; margin: 0 0 22px; overflow-wrap: anywhere; white-space: pre-wrap; }
    .pg-quiz .option-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; margin-bottom: 10px; background: var(--surface); border: 1.5px solid var(--border); border-radius: 14px; cursor: pointer; transition: border-color .15s, background .15s; font-weight: 600; overflow-wrap: anywhere; }
    .pg-quiz .option-item:hover { border-color: var(--primary); }
    .pg-quiz .option-item.selected { background: var(--primary-soft); border-color: var(--primary); }
    .pg-quiz .opt-key { width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0; background: var(--surface-2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: .8rem; color: var(--text-dim); }
    .pg-quiz .selected .opt-key { background: var(--primary); color: #fff; border-color: var(--primary); }
    .pg-quiz .tq-answer { width: 100%; min-height: 180px; padding: 14px; border-radius: 12px; border: 1px solid var(--border-strong); font: inherit; font-size: .95rem; resize: vertical; outline: none; box-sizing: border-box; }
    .pg-quiz .tq-answer:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
    .pg-quiz .tq-flag { margin-top: 6px; background: none; border: 0; padding: 6px 0; cursor: pointer; font: inherit; font-size: .8rem; font-weight: 700; color: var(--danger); }
    .pg-quiz .tq-nav { margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; gap: 10px; }
    .pg-quiz #prevBtn:disabled { opacity: .5; pointer-events: none; }
    .pg-quiz .quiz-nav-panel { position: sticky; top: calc(var(--topbar-h) + 16px); }
    .pg-quiz .timer-widget { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 12px; background: var(--danger-soft); color: #9B1C1C; margin-bottom: 12px; font-size: .8rem; font-weight: 700; }
    .pg-quiz .timer-time { font-family: ui-monospace, Consolas, monospace; font-size: 1.3rem; font-weight: 800; }
    .pg-quiz .tq-guard { display: flex; gap: 8px; align-items: center; font-size: .76rem; color: var(--text-dim); margin-bottom: 14px; }
    .pg-quiz .tq-guard b { color: var(--text-main, #0f172a); }
    .pg-quiz .q-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 12px; }
    .pg-quiz .q-link { position: relative; aspect-ratio: 1; border-radius: 9px; border: 1px solid var(--border); background: var(--surface-2); color: var(--text-dim); font: inherit; font-size: .85rem; font-weight: 700; cursor: pointer; }
    .pg-quiz .q-link.answered { background: var(--primary-soft); color: var(--primary-dark); border-color: transparent; }
    .pg-quiz .q-link.current { border: 2px solid var(--primary); color: var(--primary-dark); }
    .pg-quiz .q-link.flagged::after { content: ''; position: absolute; top: -3px; right: -3px; width: 9px; height: 9px; background: var(--danger); border-radius: 50%; border: 2px solid #fff; }
    .pg-quiz .tq-legend { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; font-size: .72rem; color: var(--text-dim); }
    .pg-quiz .tq-legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 4px; vertical-align: -1px; }
    .pg-quiz #view-result { text-align: center; }
    .pg-quiz #res-icon { font-size: 3.4rem; line-height: 1; margin-bottom: 12px; }
    .pg-quiz #res-score { font-size: 3.2rem; font-weight: 800; color: var(--primary); line-height: 1.1; margin: 6px 0 10px; }

    /* Rules */
    .pg-quiz .tq-rules { border: 1.5px solid #fed7aa; background: #fffbf5; }
    .pg-quiz .tq-rules h2 { margin: 0 0 10px; font-size: 1.05rem; display: flex; gap: 10px; align-items: center; }
    .pg-quiz .tq-rules ul { margin: 0 0 14px; padding-left: 0; list-style: none; display: grid; gap: 8px; }
    .pg-quiz .tq-rules li { display: flex; gap: 10px; align-items: flex-start; line-height: 1.5; font-size: .92rem; }
    .pg-quiz .tq-rules li i { color: #ea580c; margin-top: 4px; width: 16px; text-align: center; flex: none; }
    .pg-quiz .tq-agree { display: flex; gap: 10px; align-items: center; font-weight: 700; cursor: pointer; }
    .pg-quiz .tq-agree input { width: 20px; height: 20px; accent-color: var(--primary); }

    /* Locked test: no highlighting or copying of questions */
    .exam-lock .question-view, .exam-lock .option-item, .exam-lock .q-text { -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }
    .exam-lock textarea, .exam-lock .code-area { -webkit-user-select: text; user-select: text; }
    @media print { .pg-quiz { display: none !important; } body::after { content: 'Tests cannot be printed.'; } }

    /* Programming answer: in-app editor + output */
    .pg-quiz .code-box { border: 1px solid #1e293b; border-radius: 14px; overflow: hidden; background: #0b1220; }
    .pg-quiz .code-bar { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 10px 8px 14px; background: #111a2e; color: #94a3b8; font: 600 .78rem ui-monospace, Consolas, monospace; }
    .pg-quiz .code-bar b { color: #7dd3fc; }
    .pg-quiz .code-run { display: inline-flex; gap: 6px; align-items: center; border: 0; border-radius: 8px; padding: 7px 12px; background: #10b981; color: #fff; font: 700 .8rem Inter, sans-serif; cursor: pointer; }
    .pg-quiz .code-run:disabled { background: #334155; color: #94a3b8; cursor: default; }
    .pg-quiz .code-area { display: block; width: 100%; min-height: 240px; padding: 14px; border: 0; outline: none; resize: vertical; box-sizing: border-box; background: #0b1220; color: #e2e8f0; caret-color: #7dd3fc;
                          font: 500 .9rem/1.6 ui-monospace, 'JetBrains Mono', Consolas, monospace; tab-size: 2; white-space: pre; overflow: auto; }
    .pg-quiz .code-out { border-top: 1px solid #1e293b; background: #fff; }
    .pg-quiz .code-out[hidden] { display: none; }
    .pg-quiz .code-out iframe { display: block; width: 100%; height: 200px; border: 0; background: #fff; }
    .pg-quiz .code-console { margin: 0; padding: 10px 14px; max-height: 160px; overflow: auto; background: #020617; color: #a7f3d0; font: .82rem/1.5 ui-monospace, Consolas, monospace; white-space: pre-wrap; }
    .pg-quiz .code-console:empty { display: none; }

    /* Warning dialog */
    .tq-modal { position: fixed; inset: 0; z-index: 10000; display: grid; place-items: center; padding: 16px; background: rgba(15,23,42,.72); }
    .tq-modal[hidden] { display: none; }
    .tq-modal-box { max-width: 440px; width: 100%; background: #fff; border-radius: 20px; padding: 26px 24px; text-align: center; box-shadow: 0 30px 70px -20px rgba(0,0,0,.6); }
    .tq-modal-box .ic { width: 64px; height: 64px; border-radius: 18px; display: grid; place-items: center; margin: 0 auto 14px; background: #fff7ed; color: #ea580c; font-size: 1.6rem; }
    .tq-modal-box h3 { margin: 0 0 8px; font-size: 1.25rem; }
    .tq-modal-box p { margin: 0 0 18px; color: #64748b; line-height: 1.6; }

    @media (max-width: 900px) {
        .pg-quiz .quiz-player-layout { grid-template-columns: minmax(0, 1fr); }
        .pg-quiz .player-sidebar { order: -1; }
        .pg-quiz .quiz-nav-panel { position: static; }
        .pg-quiz .q-grid { grid-template-columns: repeat(auto-fill, minmax(40px, 1fr)); }
    }
    @media (max-width: 640px) { .pg-quiz .tq-question { padding: 18px; min-height: 0; } .pg-quiz .q-text { font-size: 1.08rem; } }
</style>

<div class="pg-quiz">

    <!-- Intro: details, rules and past attempts -->
    <div id="view-intro">
        <div class="sp-page-head">
            <div style="min-width: 0;">
                <div class="tq-crumbs"><a href="quizzes">Quizzes</a> · <?= htmlspecialchars($quiz['course_title']) ?></div>
                <h1><?= htmlspecialchars($quiz['title']) ?></h1>
                <p>Read the rules below, then start when you're ready. The timer starts as soon as you begin.</p>
            </div>
        </div>

        <?php if ($closedNotice): ?>
            <div class="sp-alert warning" style="margin-bottom:16px"><i class="fas fa-door-closed" style="margin-top:3px"></i><div><b>Your last attempt was closed before you submitted it.</b> As the test rules say, it was submitted with the answers you had saved: <b><?= round($closedNotice['score']) ?>%</b>.</div></div>
        <?php endif; ?>

        <section class="sp-grid cols-3 sp-section">
            <div class="sp-stat"><span class="sp-stat-icon orange"><i class="far fa-clock"></i></span><div><strong><?= (int)$quiz['time_limit_mins'] ?> min</strong><span>Time limit</span></div></div>
            <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-bullseye"></i></span><div><strong><?= (int)$quiz['pass_score'] ?>%</strong><span>Pass mark</span></div></div>
            <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-list-ol"></i></span><div><strong><?= $q_total ?></strong><span>Question<?= $q_total === 1 ? '' : 's' ?></span></div></div>
        </section>

        <?php if ($q_total === 0): ?>
            <div class="sp-alert warning"><i class="fas fa-triangle-exclamation" style="margin-top: 3px;"></i><div>Your tutor hasn't added questions to this <?= $kind ?> yet. Check back later.</div></div>
        <?php else: ?>
        <section class="sp-card tq-rules sp-section">
            <h2><i class="fas fa-scale-balanced" style="color:#ea580c"></i> <?= ucfirst($kind) ?> rules</h2>
            <ul>
                <li><i class="fas fa-window-maximize"></i><span><b>Stay on this tab until you submit.</b> Switching tabs or apps, or leaving the window, counts as a warning. After <?= SDA_EXAM_MAX_VIOLATIONS ?> warnings your <?= $kind ?> is submitted automatically.</span></li>
                <li><i class="fas fa-door-closed"></i><span><b>Don't close or reload the page.</b> A <?= $kind ?> that is closed is submitted with the answers saved so far.</span></li>
                <li><i class="fas fa-ban"></i><span><b>No copying, pasting or highlighting.</b> Questions can't be copied, and answers must be typed by you.</span></li>
                <li><i class="fas fa-code"></i><span><b>Programming questions are answered here</b>, in the built-in editor. You don't need any other app or website.</span></li>
                <li><i class="fas fa-robot"></i><span><b>The AI mentor is paused</b> while your <?= $kind ?> is open. Use it before and after, to learn.</span></li>
                <li><i class="far fa-clock"></i><span>Your answers save automatically. When time runs out, what you have is submitted.</span></li>
            </ul>
            <label class="tq-agree"><input type="checkbox" id="agreeRules"> I understand and agree to these rules</label>
            <div style="margin-top:16px"><button type="button" class="sp-btn lg" id="startBtn" onclick="startQuiz()" disabled><i class="fas fa-play"></i> <?= $attempts ? 'Start a new attempt' : 'Start ' . $kind ?></button></div>
        </section>
        <?php endif; ?>

        <section class="sp-section">
            <div class="sp-section-head"><div><h2>Your attempts</h2></div></div>
            <?php if ($attempts): ?>
            <div class="sp-table-wrap">
                <table class="sp-table">
                    <thead><tr><th>Date</th><th>Score</th><th>Result</th><th>Ended</th></tr></thead>
                    <tbody>
                        <?php foreach ($attempts as $a): $dt = new DateTime($a['completed_at'] ?? 'now'); ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= $dt->format('D, j M Y, g:i A') ?></td>
                            <td><strong><?= round($a['score']) ?>%</strong></td>
                            <td><?= $a['passed'] ? '<span class="sp-badge green">Passed</span>' : '<span class="sp-badge red">Try again</span>' ?></td>
                            <td><span class="sp-badge gray"><?= htmlspecialchars($reasonText[$a['end_reason'] ?? ''] ?? 'Submitted') ?></span><?= (int)($a['violations'] ?? 0) > 0 ? ' <span class="sp-badge red" title="Times the test tab was left">' . (int)$a['violations'] . ' warning' . ((int)$a['violations'] === 1 ? '' : 's') . '</span>' : '' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="sp-card"><p class="sp-muted" style="margin: 0;">You haven't attempted this <?= $kind ?> yet.</p></div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Player -->
    <div id="view-player" class="exam-lock" style="display: none;">
        <div class="sp-page-head">
            <div style="min-width: 0;"><h1><?= htmlspecialchars($quiz['title']) ?></h1><p>Answer each question. Stay on this tab until you submit.</p></div>
        </div>
        <div class="quiz-player-layout">
            <div class="player-main">
                <div class="sp-card tq-question">
                    <div id="questions-pool">
                        <?php foreach ($questions as $idx => $q):
                            $opts = json_decode((string)$q['options_json'], true) ?: [];
                        ?>
                        <div class="question-view" id="qv-<?= $idx ?>" style="display: none;">
                            <span class="q-numbering">Question <?= $idx + 1 ?> of <?= $q_total ?></span>
                            <h2 class="q-text"><?= htmlspecialchars($q['question']) ?></h2>
                            <div class="options-container">
                                <?php if ($q['type'] == 'mcq' || $q['type'] == 'tf'): ?>
                                    <?php foreach ($opts as $k => $o): ?>
                                    <div class="option-item" role="button" tabindex="0" onclick="saveAnswer(<?= $idx ?>, <?= htmlspecialchars(json_encode((string)$o, JSON_INVALID_UTF8_SUBSTITUTE)) ?>, this)" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }">
                                        <div class="opt-key"><?= chr(65 + $k) ?></div>
                                        <div><?= htmlspecialchars((string)$o) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php elseif ($q['type'] == 'code'):
                                    $lang = isset($langs[$opts['lang'] ?? '']) ? $opts['lang'] : 'javascript';
                                    $runnable = in_array($lang, ['html', 'css', 'javascript'], true); ?>
                                    <div class="code-box" data-lang="<?= $lang ?>">
                                        <div class="code-bar"><span><i class="fas fa-code"></i> Your answer · <b><?= $langs[$lang] ?></b></span>
                                            <button type="button" class="code-run" onclick="runCode(<?= $idx ?>)" <?= $runnable ? '' : 'disabled title="Run is available for HTML, CSS and JavaScript"' ?>><i class="fas fa-play"></i> Run</button></div>
                                        <textarea class="code-area" id="code-<?= $idx ?>" spellcheck="false" autocapitalize="off" autocomplete="off" aria-label="Your code" oninput="saveAnswer(<?= $idx ?>, this.value)"><?= htmlspecialchars((string)($opts['starter'] ?? '')) ?></textarea>
                                        <div class="code-out" id="out-<?= $idx ?>" hidden><iframe sandbox="allow-scripts" title="Result"<?= $lang === 'javascript' ? ' style="display:none"' : '' ?>></iframe><pre class="code-console"></pre></div>
                                    </div>
                                <?php else: ?>
                                    <textarea class="tq-answer" placeholder="Type your answer here..." oninput="saveAnswer(<?= $idx ?>, this.value)" aria-label="Your answer"></textarea>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="tq-flag" onclick="toggleFlag(<?= $idx ?>)"><i class="fas fa-flag"></i> <span id="flag-text-<?= $idx ?>">Flag question</span></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="tq-nav">
                        <button type="button" class="sp-btn ghost" onclick="prevQ()" id="prevBtn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
                        <button type="button" class="sp-btn" onclick="nextQ()" id="nextBtn">Next <i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
            </div>

            <div class="player-sidebar">
                <div class="sp-card quiz-nav-panel">
                    <div class="timer-widget"><span><i class="far fa-clock"></i> Time left</span><div id="countdown" class="timer-time">--:--</div></div>
                    <div class="tq-guard"><i class="fas fa-shield-halved"></i><span>Warnings: <b id="warnCount">0</b> of <?= SDA_EXAM_MAX_VIOLATIONS ?> · <span id="saveState">Saved</span></span></div>
                    <h4 style="margin: 0; font-size: .9rem; font-weight: 800;">Questions</h4>
                    <div class="q-grid">
                        <?php for ($i = 0; $i < $q_total; $i++): ?>
                        <button type="button" class="q-link" id="nav-<?= $i ?>" onclick="goToQ(<?= $i ?>)" aria-label="Question <?= $i + 1 ?>"><?= $i + 1 ?></button>
                        <?php endfor; ?>
                    </div>
                    <div class="tq-legend"><span><i style="background: var(--primary-soft);"></i>Answered</span><span><i style="background: var(--danger); border-radius: 50%;"></i>Flagged</span></div>
                    <button type="button" class="sp-btn block" style="margin-top: 18px;" onclick="showSummary()">Finish <?= $kind ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary before submitting -->
    <div id="view-summary" class="exam-lock" style="display: none;">
        <div class="sp-page-head"><div style="min-width: 0;"><h1>Check your answers</h1><p>Go back to any question you missed, or submit now.</p></div></div>
        <div class="sp-table-wrap"><table class="sp-table"><thead><tr><th>Question</th><th>Status</th></tr></thead><tbody id="summary-body"></tbody></table></div>
        <div class="sp-row sp-mt" style="justify-content: center; margin-top: 24px;">
            <button type="button" class="sp-btn ghost" onclick="resumeQuiz()"><i class="fas fa-chevron-left"></i> Back to the <?= $kind ?></button>
            <button type="button" class="sp-btn" style="background: var(--success);" onclick="submitQuiz('submitted')"><i class="fas fa-paper-plane"></i> Submit answers</button>
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
                <a href="take-quiz?id=<?= $qid ?>" class="sp-btn ghost"><i class="fas fa-rotate-right"></i> Try again</a>
            </div>
        </div>
    </div>
</div>

<div class="tq-modal" id="warnModal" role="alertdialog" aria-modal="true" aria-labelledby="warnTitle" hidden>
    <div class="tq-modal-box">
        <div class="ic"><i class="fas fa-triangle-exclamation"></i></div>
        <h3 id="warnTitle">You left the <?= $kind ?></h3>
        <p id="warnText"></p>
        <button type="button" class="sp-btn block" onclick="closeWarn()">Back to my <?= $kind ?></button>
    </div>
</div>

<script>
const QUIZ_ID = <?= $qid ?>;
const CSRF = <?= json_encode(csrfToken()) ?>;
const KIND = <?= json_encode($kind) ?>;
const MAX_WARN = <?= SDA_EXAM_MAX_VIOLATIONS ?>;
const qIds = [<?= implode(',', array_map(fn($q) => (int)$q['id'], $questions)) ?>];
const totalQs = qIds.length;
let currentIdx = 0, remaining = 0, timerId = null, answers = {}, flagged = new Set();
let attemptId = 0, active = false, warnings = 0, saveTimer = null, lastWarnAt = 0, submitting = false;

document.getElementById('agreeRules')?.addEventListener('change', e => { document.getElementById('startBtn').disabled = !e.target.checked; });

function api(body) {
    return fetch('exam-api', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, Accept: 'application/json' }, body: JSON.stringify(body) })
        .then(r => r.json());
}

async function startQuiz() {
    const btn = document.getElementById('startBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Starting…';
    try {
        const d = await api({ action: 'start', quiz_id: QUIZ_ID });
        if (!d.ok) throw new Error(d.error || 'Could not start.');
        attemptId = d.attempt; remaining = d.seconds_left;
    } catch (e) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Start'; alert(e.message || 'Could not start. Check your connection.'); return; }
    // Starter code counts as the first saved answer for programming questions
    document.querySelectorAll('.code-area').forEach(t => { const i = +t.id.split('-')[1]; if (t.value.trim()) answers[i] = t.value; });
    document.getElementById('view-intro').style.display = 'none';
    document.getElementById('view-player').style.display = 'block';
    active = true; document.getElementById('sda-install')?.remove(); window.__sdaBip = null;
    try { if (document.documentElement.requestFullscreen && matchMedia('(pointer: fine)').matches) await document.documentElement.requestFullscreen(); } catch (e) {}
    goToQ(0); startTimer(); window.scrollTo(0, 0);
}

function goToQ(n) {
    document.querySelectorAll('.question-view').forEach(v => v.style.display = 'none');
    document.getElementById('qv-' + n).style.display = 'block';
    document.querySelectorAll('.q-link').forEach(l => l.classList.remove('current'));
    document.getElementById('nav-' + n).classList.add('current');
    currentIdx = n;
    document.getElementById('prevBtn').disabled = (n === 0);
    const nextBtn = document.getElementById('nextBtn');
    if (n === totalQs - 1) { nextBtn.innerHTML = 'Finish <i class="fas fa-flag-checkered"></i>'; nextBtn.onclick = showSummary; }
    else { nextBtn.innerHTML = 'Next <i class="fas fa-chevron-right"></i>'; nextBtn.onclick = nextQ; }
}
function nextQ() { if (currentIdx < totalQs - 1) goToQ(currentIdx + 1); }
function prevQ() { if (currentIdx > 0) goToQ(currentIdx - 1); }

function saveAnswer(idx, val, el = null) {
    answers[idx] = val;
    document.getElementById('nav-' + idx).classList.toggle('answered', String(val).trim() !== '');
    if (el) { el.parentElement.querySelectorAll('.option-item').forEach(o => o.classList.remove('selected')); el.classList.add('selected'); }
    queueSave();
}
function payload() { const o = {}; for (const i in answers) o[qIds[i]] = answers[i]; return o; }
function queueSave() {
    document.getElementById('saveState').textContent = 'Saving…';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        api({ action: 'save', attempt: attemptId, answers: payload() }).then(d => {
            document.getElementById('saveState').textContent = d.ok ? 'Saved' : 'Not saved';
            if (d.submitted) finish(d.result ? { score: Math.round(d.result.score), passed: d.result.passed, reason: 'time_up' } : null);
        }).catch(() => { document.getElementById('saveState').textContent = 'Offline – will retry'; });
    }, 1200);
}

function toggleFlag(idx) {
    const nav = document.getElementById('nav-' + idx), txt = document.getElementById('flag-text-' + idx);
    if (flagged.has(idx)) { flagged.delete(idx); nav.classList.remove('flagged'); txt.innerText = 'Flag question'; }
    else { flagged.add(idx); nav.classList.add('flagged'); txt.innerText = 'Remove flag'; }
}

function startTimer() {
    const tick = () => {
        const m = Math.floor(Math.max(0, remaining) / 60), s = Math.max(0, remaining) % 60;
        document.getElementById('countdown').innerText = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        if (remaining <= 0) { clearInterval(timerId); submitQuiz('time_up'); }
        remaining--;
    };
    tick(); timerId = setInterval(tick, 1000);
}

function showSummary() {
    document.getElementById('view-player').style.display = 'none';
    document.getElementById('view-summary').style.display = 'block';
    const body = document.getElementById('summary-body'); body.innerHTML = '';
    for (let i = 0; i < totalQs; i++) {
        const done = answers[i] !== undefined && String(answers[i]).trim() !== '';
        body.innerHTML += `<tr><td>Question ${i + 1}${flagged.has(i) ? ' <i class="fas fa-flag" style="color: var(--danger); font-size: .75rem;"></i>' : ''}</td><td>${done ? '<span class="sp-badge green">Answered</span>' : '<span class="sp-badge red">Not answered</span>'}</td></tr>`;
    }
    window.scrollTo(0, 0);
}
function resumeQuiz() { document.getElementById('view-summary').style.display = 'none'; document.getElementById('view-player').style.display = 'block'; }

function submitQuiz(reason) {
    if (submitting || !attemptId) return;
    submitting = true; clearInterval(timerId); clearTimeout(saveTimer);
    fetch('submit-quiz-handler', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ quiz_id: QUIZ_ID, attempt: attemptId, answers: payload(), reason: reason || 'submitted' }) })
        .then(r => r.json()).then(data => {
            if (data.error) throw new Error(data.error);
            finish({ score: data.score, passed: data.passed, reason: reason });
        })
        .catch(() => {
            submitting = false;
            alert('Sorry, we could not submit your answers. Check your connection and press "Submit answers" again. Your answers are saved.');
            document.getElementById('view-player').style.display = 'none';
            document.getElementById('view-summary').style.display = 'block';
        });
}

function finish(r) {
    active = false; clearInterval(timerId);
    document.getElementById('warnModal').hidden = true;
    try { if (document.fullscreenElement) document.exitFullscreen(); } catch (e) {}
    ['view-summary', 'view-player'].forEach(id => document.getElementById(id).style.display = 'none');
    document.getElementById('view-result').style.display = 'block';
    window.scrollTo(0, 0);
    if (!r) { location.reload(); return; }
    const ended = r.reason === 'violations' ? ' It was submitted automatically because the test tab was left ' + MAX_WARN + ' times.' : (r.reason === 'time_up' ? ' Time ran out, so your saved answers were submitted.' : '');
    document.getElementById('res-score').innerText = r.score + '%';
    if (r.passed) {
        document.getElementById('res-icon').innerHTML = '🏆';
        document.getElementById('res-title').innerText = 'You passed!';
        document.getElementById('res-desc').innerText = 'Well done. Your result has been saved to your profile.' + ended;
        document.getElementById('res-score').style.color = 'var(--success)';
        document.getElementById('res-badge').innerHTML = '<span class="sp-badge green">Passed</span>';
    } else {
        document.getElementById('res-icon').innerHTML = '💡';
        document.getElementById('res-title').innerText = 'Not quite there yet';
        document.getElementById('res-desc').innerText = "You didn't reach the pass mark this time. Review the lessons (the AI mentor can help) and try again." + ended;
        document.getElementById('res-score').style.color = 'var(--danger)';
        document.getElementById('res-badge').innerHTML = '<span class="sp-badge red">Try again</span>';
    }
}

/* ── Test rules: leaving the tab, closing, copying ── */
function warn(kind) {
    if (!active || submitting) return;
    const now = Date.now();
    if (now - lastWarnAt < 1500) return;           // one leave = one warning (blur + hidden fire together)
    lastWarnAt = now;
    api({ action: 'violation', attempt: attemptId, kind }).then(d => {
        warnings = d.violations || warnings + 1;
        document.getElementById('warnCount').textContent = warnings;
        if (d.submit || warnings >= MAX_WARN) { submitQuiz('violations'); return; }
        document.getElementById('warnText').innerHTML = 'Leaving the ' + KIND + ' tab is against the rules. This is warning <b>' + warnings + ' of ' + MAX_WARN + '</b>. At ' + MAX_WARN + ' your ' + KIND + ' is submitted automatically.';
        document.getElementById('warnModal').hidden = false;
    }).catch(() => {});
}
function closeWarn() { document.getElementById('warnModal').hidden = true; }
document.addEventListener('visibilitychange', () => { if (document.hidden) warn('left_tab'); });
window.addEventListener('blur', () => { setTimeout(() => { if (!document.hasFocus()) warn('left_window'); }, 300); });
window.addEventListener('beforeunload', e => { if (active && !submitting) { e.preventDefault(); e.returnValue = ''; } });
window.addEventListener('pagehide', () => { if (active && !submitting && attemptId) navigator.sendBeacon && navigator.sendBeacon('exam-api', new Blob([JSON.stringify({ action: 'save', attempt: attemptId, answers: payload() })], { type: 'application/json' })); });

const inTest = () => active;
['copy', 'cut', 'paste', 'contextmenu', 'dragstart', 'drop'].forEach(ev => document.addEventListener(ev, e => { if (inTest()) e.preventDefault(); }, true));
document.addEventListener('selectstart', e => { if (inTest() && !e.target.closest('textarea')) e.preventDefault(); }, true);
document.addEventListener('keydown', e => {
    if (!inTest()) return;
    const k = (e.key || '').toLowerCase();
    if ((e.ctrlKey || e.metaKey) && ['c', 'x', 'v', 'a', 'p', 's', 'u', 'f'].includes(k)) e.preventDefault();
    if (k === 'f12' || ((e.ctrlKey || e.metaKey) && e.shiftKey && ['i', 'j', 'c'].includes(k)) || k === 'printscreen') e.preventDefault();
    // Tab inside the code editor indents instead of leaving it
    if (k === 'tab' && e.target.classList.contains('code-area')) {
        e.preventDefault();
        const t = e.target, s = t.selectionStart, en = t.selectionEnd;
        t.value = t.value.slice(0, s) + '  ' + t.value.slice(en); t.selectionStart = t.selectionEnd = s + 2;
        t.dispatchEvent(new Event('input'));
    }
}, true);

/* ── Run programming answers (HTML / CSS / JavaScript) in a sandbox on this page ── */
function runCode(idx) {
    const box = document.getElementById('code-' + idx).closest('.code-box'), lang = box.dataset.lang;
    const code = document.getElementById('code-' + idx).value, out = document.getElementById('out-' + idx);
    const con = out.querySelector('.code-console'); con.textContent = ''; out.hidden = false;
    // Everything runs in a sandboxed frame (no access to this page); console output comes back by message
    const hook = '<script>(function(){var s=function(t){parent.postMessage({sdaRun:' + idx + ',line:String(t)},"*")};'
        + '["log","info","warn","error"].forEach(function(m){console[m]=function(){s(Array.prototype.map.call(arguments,function(x){try{return typeof x==="object"?JSON.stringify(x):String(x)}catch(e){return String(x)}}).join(" "))}});'
        + 'window.onerror=function(m){s("Error: "+m)};})();<\/script>';
    const safe = code.replace(/<\/script/gi, '<\/script');
    let doc;
    if (lang === 'javascript') doc = hook + '<script>' + safe + '<\/script><script>parent.postMessage({sdaRun:' + idx + ',done:1},"*")<\/script>';
    else if (lang === 'css') doc = '<style>' + code + '</style><h1>Heading</h1><p class="note">A paragraph with <a href="#">a link</a>.</p><button>Button</button><div class="box">Box</div>';
    else doc = hook + code;
    out.querySelector('iframe').srcdoc = doc;
}
window.addEventListener('message', e => {
    const d = e.data; if (!d || typeof d.sdaRun !== 'number') return;
    const con = document.querySelector('#out-' + d.sdaRun + ' .code-console'); if (!con) return;
    if (d.line !== undefined) con.textContent += (con.textContent ? '\n' : '') + d.line;
    if (d.done && !con.textContent) con.textContent = '(no output – use console.log to print)';
});
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
