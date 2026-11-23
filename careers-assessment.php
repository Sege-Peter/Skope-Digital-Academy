<?php
/**
 * Applicant assessment (private link from the invite email: careers-assessment?t=TOKEN).
 * Integrity rules, all enforced or recorded on the server:
 *  - one attempt; the first browser that starts it owns it (httponly device cookie), other devices are refused
 *  - server-side clock: answers are refused after the deadline (+ short grace); overdue attempts are closed
 *  - questions and options shuffled per applicant; correct answers never leave the server
 *  - tab/window switches, leaving full screen and reloading/reopening are strikes; 3 strikes auto-submit
 *  - copy/paste/right-click/shortcuts are blocked and logged; large text dropped in at once is flagged for reviewers
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/careers.php';
sda_careers_ensure($pdo);
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$raw = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$app = sda_app_by_token($pdo, $raw);

function ax_json(array $d, int $code = 200): never { http_response_code($code); header('Content-Type: application/json'); echo json_encode($d); exit; }
function ax_left(array $app): int { return max(0, strtotime($app['deadline_at']) - time()); }
function ax_reload(PDO $pdo, string $raw): array { return sda_app_by_token($pdo, $raw); }

/* ── API (fetch from the test page) ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'start') {
    if (!$app) ax_json(['ok' => false, 'closed' => true, 'msg' => 'Invalid link.'], 404);
    if (!sda_rate_ok('career_ax_api', 900, 60, 'app' . $app['id'])) ax_json(['ok' => false, 'msg' => 'Slow down.'], 429);
    if ($app['status'] !== 'in_progress') ax_json(['ok' => false, 'closed' => true]);
    if (!sda_ax_device_ok($app)) ax_json(['ok' => false, 'closed' => true, 'msg' => 'This assessment is open on another device.'], 403);
    $id = (int)$app['id'];
    $over = time() > strtotime($app['deadline_at']) + SDA_AX_GRACE_SECS;
    $act = $_POST['action'] ?? '';

    // Answers: {display question id: value}. MCQ values are display positions, mapped back to original option indexes.
    if (in_array($act, ['save', 'submit'], true) && !$over) {
        $in = json_decode((string)($_POST['answers'] ?? '{}'), true);
        if (is_array($in)) {
            $order = json_decode((string)$app['qorder'], true) ?: [];
            $perm = [];
            foreach ($order as $o) $perm[(int)$o['q']] = $o['p'];
            $up = $pdo->prepare("INSERT INTO job_answers (application_id, question_id, answer) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE answer = VALUES(answer)");
            foreach ($in as $qid => $val) {
                $qid = (int)$qid;
                if (!array_key_exists($qid, $perm)) continue;
                if ($perm[$qid] !== null) {
                    if ($val === '' || $val === null) { $up->execute([$id, $qid, null]); continue; }
                    $d = (int)$val;
                    if (!isset($perm[$qid][$d])) continue;
                    $up->execute([$id, $qid, (string)$perm[$qid][$d]]);
                } else {
                    $up->execute([$id, $qid, mb_substr((string)$val, 0, 20000)]);
                }
            }
        }
    }
    if ($act === 'event') {
        $kind = (string)($_POST['kind'] ?? '');
        $strikes = ['hidden' => 'Left the test tab or minimised the window', 'blur' => 'Switched to another window or app', 'fullscreen' => 'Left full screen'];
        $notes = ['copy' => 'Tried to copy', 'paste' => 'Tried to paste', 'cut' => 'Tried to cut', 'menu' => 'Tried to open the right-click menu', 'keys' => 'Used a blocked keyboard shortcut', 'bulk' => 'A large block of text appeared in one go (possible paste or dictation)', 'print' => 'Tried to print or screenshot', 'drop' => 'Tried to drag text in'];
        if (isset($strikes[$kind])) {
            $n = sda_app_log($pdo, $id, $strikes[$kind], true);
            if ($n >= SDA_AX_MAX_STRIKES) { sda_ax_finalize($pdo, $id, 'violations'); ax_json(['ok' => true, 'closed' => true, 'strikes' => $n]); }
            ax_json(['ok' => true, 'strikes' => $n, 'left' => ax_left($app)]);
        }
        if (isset($notes[$kind])) sda_app_log($pdo, $id, $notes[$kind] . (isset($_POST['detail']) ? ' (' . mb_substr((string)$_POST['detail'], 0, 60) . ')' : ''));
        ax_json(['ok' => true, 'strikes' => (int)$app['violations'], 'left' => ax_left($app)]);
    }
    if ($act === 'submit' || $over) {
        $timeUp = $over || (($_POST['reason'] ?? '') === 'time_up' && time() >= strtotime($app['deadline_at']) - 5);
        sda_ax_finalize($pdo, $id, $timeUp ? 'time_up' : 'submitted');
        ax_json(['ok' => true, 'closed' => true]);
    }
    ax_json(['ok' => true, 'strikes' => (int)$app['violations'], 'left' => ax_left($app)]);
}

/* ── Start ─────────────────────────────────────────────────────────── */
$flash = '';
if ($app && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start') {
    $canStart = !$app['started_at'] && $app['assess_on'] && $app['invite_expires_at'] && strtotime($app['invite_expires_at']) > time()
        && in_array($app['status'], ['received', 'invited'], true) && count(sda_ax_questions($pdo, (int)$app['job_id'])) > 0;
    if ($canStart && !empty($_POST['agree']) && hash_equals((string)($_SESSION['ax_start_' . $app['id']] ?? ''), (string)($_POST['nonce'] ?? ''))) {
        $key = sda_ax_start($pdo, $app);
        if ($key !== '') {
            sda_ax_set_device_cookie((int)$app['id'], $key);
            $pdo->prepare("UPDATE job_applications SET opens = 0 WHERE id = ?")->execute([$app['id']]);
        }
    } elseif ($canStart) $flash = empty($_POST['agree']) ? 'Please tick the box to agree to the rules before you start.' : 'This page had expired. Please tick the box and press Start again.';
    if (!$flash) { header('Location: careers-assessment?t=' . $raw); exit; }
}

/* ── Work out what to show ─────────────────────────────────────────── */
$view = 'invalid';
if ($app) {
    sda_ax_close_overdue($pdo);
    $app = ax_reload($pdo, $raw);
    if ($app['status'] === 'in_progress') {
        if (!sda_ax_device_ok($app)) {
            sda_app_log($pdo, (int)$app['id'], 'Link opened on another device or browser (IP ' . sda_client_ip() . ')', true);
            $app = ax_reload($pdo, $raw);
            if ((int)$app['violations'] >= SDA_AX_MAX_STRIKES) { sda_ax_finalize($pdo, (int)$app['id'], 'violations'); $app = ax_reload($pdo, $raw); $view = 'done'; }
            else $view = 'other_device';
        } else {
            $pdo->prepare("UPDATE job_applications SET opens = opens + 1 WHERE id = ?")->execute([$app['id']]);
            if ((int)$app['opens'] >= 1) {
                $n = sda_app_log($pdo, (int)$app['id'], 'Reloaded or reopened the test page', true);
                if ($n >= SDA_AX_MAX_STRIKES) sda_ax_finalize($pdo, (int)$app['id'], 'violations');
            }
            $app = ax_reload($pdo, $raw);
            $view = $app['status'] === 'in_progress' ? 'test' : 'done';
        }
    } elseif ($app['started_at']) {
        $view = 'done';
    } elseif (!$app['assess_on'] || !$app['invite_expires_at']) {
        $view = 'status';
    } elseif (strtotime($app['invite_expires_at']) <= time()) {
        $view = 'expired';
    } else {
        $view = 'intro';
        $_SESSION['ax_start_' . $app['id']] = $nonce = bin2hex(random_bytes(16));
    }
}

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$items = [];
if ($view === 'test') {
    $qs = [];
    foreach (sda_ax_questions($pdo, (int)$app['job_id']) as $q) $qs[(int)$q['id']] = $q;
    $saved = $pdo->prepare("SELECT question_id, answer FROM job_answers WHERE application_id = ?");
    $saved->execute([$app['id']]);
    $saved = $saved->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach (json_decode((string)$app['qorder'], true) ?: [] as $o) {
        $q = $qs[(int)$o['q']] ?? null;
        if (!$q) continue;
        $item = ['id' => (int)$q['id'], 'section' => $q['section'], 'type' => $q['qtype'], 'prompt' => $q['prompt'], 'marks' => (float)$q['marks'], 'value' => ''];
        if ($q['qtype'] === 'mcq') {
            $opts = json_decode((string)$q['options'], true) ?: [];
            $item['options'] = array_map(fn($i) => $opts[$i] ?? '', $o['p'] ?? []);
            $sv = $saved[$q['id']] ?? null;
            if ($sv !== null && $sv !== '') { $pos = array_search((int)$sv, $o['p'] ?? [], true); $item['value'] = $pos === false ? '' : (string)$pos; }
        } else $item['value'] = (string)($saved[$q['id']] ?? '');
        $items[] = $item;
    }
    $totalMarks = array_sum(array_column($items, 'marks'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= $app ? 'Assessment: ' . $e($app['job_title']) : 'Assessment' ?> – Skope Digital Academy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" href="favicon.ico" sizes="any">
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', sans-serif; color: #334155; background: #f1f5f9; }
    h1, h2, h3 { font-family: 'Poppins', sans-serif; color: #0f172a; letter-spacing: -.02em; }
    .ax-shell { max-width: 760px; margin: 0 auto; padding: 40px 16px 60px; }
    .ax-brand { display: flex; align-items: center; gap: 10px; font: 800 1rem 'Poppins', sans-serif; color: #0f172a; margin-bottom: 22px; }
    .ax-brand img { width: 38px; height: 38px; }
    .ax-brand span { color: #FF8C00; }
    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 22px; padding: 28px; box-shadow: 0 20px 50px -34px rgba(15,23,42,.4); }
    .card h1 { font-size: clamp(1.4rem, 3.4vw, 1.9rem); margin: 0 0 8px; }
    .muted { color: #64748b; }
    .kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 20px 0; }
    .kv div { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 12px 14px; font-size: .8rem; color: #64748b; }
    .kv b { display: block; font-size: 1.05rem; color: #0f172a; }
    .rules { list-style: none; padding: 0; margin: 16px 0; display: grid; gap: 10px; }
    .rules li { display: flex; gap: 12px; font-size: .92rem; line-height: 1.55; }
    .rules i { color: #0080FF; margin-top: 4px; width: 16px; text-align: center; }
    .rules li.warn i { color: #dc2626; }
    .agree { display: flex; gap: 10px; align-items: flex-start; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 14px; padding: 14px; font-size: .9rem; margin: 18px 0; }
    .agree input { width: 18px; height: 18px; margin-top: 2px; accent-color: #FF8C00; flex: 0 0 auto; }
    .btn { display: inline-flex; align-items: center; gap: 9px; padding: 13px 22px; border-radius: 12px; border: 0; font: 800 .95rem 'Inter', sans-serif; color: #fff; background: linear-gradient(135deg, #00BFFF, #0080FF); cursor: pointer; text-decoration: none; }
    .btn.ghost { background: #fff; color: #0f172a; border: 1px solid #e2e8f0; }
    .btn.orange { background: linear-gradient(135deg, #ffa733, #FF8C00); }
    .btn[disabled] { opacity: .5; cursor: not-allowed; }
    .state { text-align: center; }
    .state .ic { width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px; font-size: 1.8rem; background: #e0f2fe; color: #0284c7; }
    .flash { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-size: .9rem; }

    /* Test */
    body.testing { user-select: none; -webkit-user-select: none; background: #f8fafc; }
    .tbar { position: sticky; top: 0; z-index: 20; background: #0b1324; color: #e2e8f0; display: flex; align-items: center; gap: 14px; padding: 10px 18px; flex-wrap: wrap; }
    .tbar .ttl { font-weight: 700; font-size: .9rem; flex: 1; min-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tbar .pill { display: inline-flex; gap: 8px; align-items: center; padding: 6px 12px; border-radius: 99px; background: rgba(255,255,255,.08); font-weight: 800; font-size: .85rem; }
    .tbar .pill.time { background: rgba(0,191,255,.15); color: #7dd3fc; font-variant-numeric: tabular-nums; }
    .tbar .pill.time.low { background: rgba(220,38,38,.2); color: #fca5a5; }
    .tbar .pill.strk.hit { background: rgba(234,88,12,.25); color: #fdba74; }
    .tbar .save { font-size: .75rem; color: #94a3b8; }
    .prog { height: 4px; background: #1e293b; } .prog i { display: block; height: 100%; width: 0; background: linear-gradient(90deg, #00BFFF, #FF8C00); transition: width .3s; }
    .tmain { max-width: 860px; margin: 0 auto; padding: 26px 16px 120px; position: relative; }
    .sec-h { margin: 34px 0 12px; display: flex; align-items: baseline; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .sec-h h2 { font-size: 1.15rem; margin: 0; } .sec-h small { color: #64748b; font-weight: 700; }
    .q { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 20px 22px; margin-bottom: 14px; }
    .q.answered { border-color: #bae6fd; }
    .q-h { display: flex; justify-content: space-between; gap: 10px; font-size: .78rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }
    .q p { margin: 0 0 14px; font-size: .98rem; line-height: 1.6; color: #0f172a; white-space: pre-wrap; }
    .opt { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 8px; cursor: pointer; font-size: .93rem; line-height: 1.5; }
    .opt:hover { background: #f8fafc; } .opt input { margin-top: 3px; accent-color: #0080FF; width: 17px; height: 17px; flex: 0 0 auto; }
    .opt:has(input:checked) { border-color: #00BFFF; background: #f0f9ff; }
    textarea.w { width: 100%; min-height: 220px; padding: 14px; border: 1px solid #cbd5e1; border-radius: 12px; font: .95rem/1.6 'Inter', sans-serif; resize: vertical; user-select: text; -webkit-user-select: text; }
    textarea.w:focus { outline: 3px solid rgba(0,191,255,.2); border-color: #00BFFF; }
    .wc { font-size: .76rem; color: #64748b; margin-top: 6px; text-align: right; }
    .tfoot { display: flex; justify-content: flex-end; margin-top: 26px; }
    .wm { position: fixed; inset: 0; pointer-events: none; z-index: 15; opacity: .05; overflow: hidden; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 60px 80px; padding: 40px; transform: rotate(-18deg) scale(1.4); font: 700 15px 'Inter', sans-serif; color: #0f172a; }
    .gate { position: fixed; inset: 0; z-index: 50; background: rgba(11,19,36,.96); display: none; place-items: center; padding: 20px; color: #e2e8f0; text-align: center; }
    .gate.on { display: grid; }
    .gate .box { max-width: 460px; } .gate h2 { color: #fff; } .gate p { line-height: 1.6; }
    .modal { position: fixed; inset: 0; z-index: 60; background: rgba(15,23,42,.6); display: none; place-items: center; padding: 16px; }
    .modal.on { display: grid; } .modal .card { max-width: 440px; }
    .toast { position: fixed; left: 50%; bottom: 22px; transform: translateX(-50%); z-index: 70; background: #b91c1c; color: #fff; padding: 12px 18px; border-radius: 12px; font-weight: 700; font-size: .9rem; display: none; max-width: calc(100vw - 32px); }
    .toast.on { display: block; }
    @media (max-width: 600px) { .card { padding: 20px; } .q { padding: 16px; } .tbar { gap: 8px; padding: 8px 12px; } .tbar .save { display: none; } .btn { width: 100%; justify-content: center; } }
</style>
</head>
<body class="<?= $view === 'test' ? 'testing' : '' ?>">

<?php if ($view !== 'test'): ?>
<div class="ax-shell">
    <div class="ax-brand"><img src="assets/pwa/icon-192.png" alt=""> SKOPE <span>DIGITAL</span> ACADEMY · Recruitment</div>

    <?php if ($view === 'invalid'): ?>
        <div class="card state"><div class="ic" style="background:#fee2e2;color:#dc2626"><i class="fas fa-link-slash"></i></div><h1>This link isn't valid</h1>
            <p class="muted">It may have been replaced by a newer link. Use the most recent email we sent you, or contact <?= $e(sda_careers_email()) ?>.</p></div>

    <?php elseif ($view === 'expired'): ?>
        <div class="card state"><div class="ic" style="background:#fef3c7;color:#b45309"><i class="fas fa-hourglass-end"></i></div><h1>This assessment link has expired</h1>
            <p class="muted">Links are valid for <?= SDA_AX_INVITE_DAYS ?> days. If you still want to be considered, email <?= $e(sda_careers_email()) ?> with the position name.</p></div>

    <?php elseif ($view === 'other_device'): ?>
        <div class="card state"><div class="ic" style="background:#fee2e2;color:#dc2626"><i class="fas fa-ban"></i></div><h1>Already open on another device</h1>
            <p class="muted">This assessment was started in a different browser or device and can only be continued there. This attempt to open it has been recorded as a warning.</p></div>

    <?php elseif ($view === 'intro'): $nq = count(sda_ax_questions($pdo, (int)$app['job_id'])); ?>
        <div class="card">
            <?php if ($flash): ?><div class="flash"><?= $e($flash) ?></div><?php endif; ?>
            <div class="muted" style="font-weight:700;font-size:.8rem;letter-spacing:.1em;text-transform:uppercase">Online assessment</div>
            <h1><?= $e($app['job_title']) ?></h1>
            <p class="muted" style="margin:0">Hi <?= $e(strtok($app['name'], ' ')) ?>, read the rules carefully. The clock starts as soon as you press Start.</p>
            <div class="kv">
                <div><b><?= (int)$app['assess_minutes'] ?> min</b>Time limit</div>
                <div><b><?= $nq ?></b>Questions</div>
                <div><b>4 parts</b>Knowledge · Practical · Situational · Professional</div>
                <div><b><?= $e(date('j M, H:i', strtotime($app['invite_expires_at']))) ?></b>Link expires</div>
            </div>
            <h3 style="margin:22px 0 4px">Rules</h3>
            <ul class="rules">
                <li><i class="fas fa-laptop"></i><span>Use a laptop or desktop with a stable connection. The test runs in full screen.</span></li>
                <li><i class="fas fa-user-lock"></i><span>Work alone, without help, notes, other websites or AI tools. Your answers must be your own words.</span></li>
                <li class="warn"><i class="fas fa-triangle-exclamation"></i><span><b>Stay on the test.</b> Switching tabs or windows, leaving full screen, reloading or reopening the page each count as a warning. After <b><?= SDA_AX_MAX_STRIKES ?> warnings the test submits automatically.</b></span></li>
                <li class="warn"><i class="fas fa-clipboard"></i><span>Copying, pasting and right-click are disabled and attempts are recorded.</span></li>
                <li><i class="fas fa-mobile-screen"></i><span>One attempt, on one device. Once started it cannot be opened on another device or restarted.</span></li>
                <li><i class="fas fa-clock"></i><span>Answers save automatically. When time runs out, what you've saved is submitted.</span></li>
            </ul>
            <form method="post" id="startForm">
                <input type="hidden" name="t" value="<?= $e($raw) ?>"><input type="hidden" name="action" value="start"><input type="hidden" name="nonce" value="<?= $e($nonce) ?>">
                <label class="agree"><input type="checkbox" name="agree" value="1" id="agree"> <span>I have read the rules. I will complete this assessment myself, in one sitting, without help or outside tools, and I understand that integrity events are recorded and shared with the recruitment team.</span></label>
                <button class="btn" id="startBtn" disabled><i class="fas fa-play"></i> Start assessment</button>
            </form>
        </div>
        <script>
            var a = document.getElementById('agree'), b = document.getElementById('startBtn');
            a.addEventListener('change', function () { b.disabled = !a.checked; });
            document.getElementById('startForm').addEventListener('submit', function () { b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting…'; });
        </script>

    <?php elseif ($view === 'done'): [$sl] = sda_app_status_label($app['status']);
        $why = ['submitted' => 'You submitted your assessment.', 'time_up' => 'Time ran out and your saved answers were submitted.', 'violations' => 'Your assessment was submitted automatically after ' . SDA_AX_MAX_STRIKES . ' integrity warnings.'][$app['end_reason'] ?? ''] ?? 'Your assessment is closed.'; ?>
        <div class="card state"><div class="ic" style="background:#dcfce7;color:#16a34a"><i class="fas fa-circle-check"></i></div>
            <h1>Assessment complete</h1>
            <p class="muted"><?= $e($why) ?> Thank you for applying for <b><?= $e($app['job_title']) ?></b>.</p>
            <div class="kv" style="max-width:420px;margin:20px auto"><div><b><?= $e(in_array($app['status'], ['submitted', 'evaluated'], true) ? 'Under review' : $sl) ?></b>Application status</div><div><b><?= $app['submitted_at'] ? $e(date('j M, H:i', strtotime($app['submitted_at']))) : '—' ?></b>Submitted</div></div>
            <p class="muted" style="font-size:.88rem">Our team reviews every assessment, including the written answers. We'll email you with the outcome. Only shortlisted candidates are invited to interview.</p></div>

    <?php else: [$sl] = sda_app_status_label($app['status']); ?>
        <div class="card state"><div class="ic"><i class="fas fa-file-circle-check"></i></div><h1>Application received</h1>
            <p class="muted">Your application for <b><?= $e($app['job_title']) ?></b> is being reviewed. Status: <b><?= $e($sl) ?></b>.</p></div>
    <?php endif; ?>
</div>

<?php else: /* ── The test ── */ $left = ax_left($app); ?>
<div class="tbar">
    <div class="ttl"><i class="fas fa-shield-halved" style="color:#00BFFF"></i> <?= $e($app['job_title']) ?></div>
    <span class="save" id="saveState">All answers saved</span>
    <span class="pill strk<?= $app['violations'] ? ' hit' : '' ?>" id="strk" title="Integrity warnings"><i class="fas fa-triangle-exclamation"></i> <span id="strkN"><?= (int)$app['violations'] ?></span>/<?= SDA_AX_MAX_STRIKES ?></span>
    <span class="pill time" id="timer"><i class="fas fa-clock"></i> <span id="timeT">--:--</span></span>
</div>
<div class="prog"><i id="progBar"></i></div>
<div class="wm" aria-hidden="true"><?= str_repeat('<span>' . $e($app['name'] . ' · ' . $app['email']) . '</span>', 40) ?></div>

<main class="tmain" id="test">
    <?php $n = 0; $cur = ''; foreach ($items as $it): $n++;
        if ($it['section'] !== $cur): $cur = $it['section']; $secMarks = array_sum(array_map(fn($x) => $x['section'] === $cur ? $x['marks'] : 0, $items)); ?>
        <div class="sec-h"><h2><?= $e(SDA_AX_SECTIONS[$cur] ?? $cur) ?></h2><small><?= rtrim(rtrim(number_format($secMarks, 1), '0'), '.') ?> marks</small></div>
    <?php endif; ?>
    <section class="q<?= $it['value'] !== '' ? ' answered' : '' ?>" data-q="<?= $it['id'] ?>" data-type="<?= $it['type'] ?>">
        <div class="q-h"><span>Question <?= $n ?> of <?= count($items) ?></span><span><?= rtrim(rtrim(number_format($it['marks'], 1), '0'), '.') ?> marks</span></div>
        <p><?= $e($it['prompt']) ?></p>
        <?php if ($it['type'] === 'mcq'): foreach ($it['options'] as $i => $opt): ?>
            <label class="opt"><input type="radio" name="q<?= $it['id'] ?>" value="<?= $i ?>" <?= $it['value'] === (string)$i ? 'checked' : '' ?>> <span><?= $e($opt) ?></span></label>
        <?php endforeach; else: ?>
            <textarea class="w" name="q<?= $it['id'] ?>" spellcheck="true" autocomplete="off" placeholder="Type your answer here. Write in your own words."><?= $e($it['value']) ?></textarea>
            <div class="wc"><span class="wcN">0</span> words</div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
    <div class="tfoot"><button class="btn orange" type="button" id="submitBtn"><i class="fas fa-paper-plane"></i> Submit assessment</button></div>
</main>

<div class="gate" id="gate"><div class="box">
    <div style="font-size:2.4rem;color:#00BFFF;margin-bottom:10px"><i class="fas fa-expand"></i></div>
    <h2 id="gateH">Enter full screen to continue</h2>
    <p id="gateP">The assessment runs in full screen. Leaving full screen counts as a warning.</p>
    <button class="btn" type="button" id="gateBtn"><i class="fas fa-expand"></i> Enter full screen</button>
</div></div>
<div class="modal" id="confirmM"><div class="card">
    <h2 style="margin-top:0">Submit your assessment?</h2>
    <p class="muted" id="confirmT">You can't change your answers after submitting.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;margin-top:18px"><button class="btn ghost" type="button" id="cancelSub">Keep working</button><button class="btn orange" type="button" id="doSub"><i class="fas fa-paper-plane"></i> Submit now</button></div>
</div></div>
<div class="toast" id="toast" role="alert"></div>

<script>
(function () {
    'use strict';
    var T = <?= json_encode($raw) ?>, MAX = <?= SDA_AX_MAX_STRIKES ?>;
    var end = Date.now() + <?= $left ?> * 1000, closed = false, dirty = false, lastStrike = 0, saving = false;
    var $ = function (id) { return document.getElementById(id); };
    var qs = Array.prototype.slice.call(document.querySelectorAll('.q'));

    function post(data) {
        var fd = new FormData(); fd.append('t', T);
        for (var k in data) fd.append(k, data[k]);
        return fetch('careers-assessment', { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true })
            .then(function (r) { return r.json(); }).then(function (j) {
                if (j && typeof j.strikes === 'number') setStrikes(j.strikes);
                if (j && j.closed) finish();
                return j;
            });
    }
    function answers() {
        var o = {};
        qs.forEach(function (q) {
            var id = q.dataset.q;
            if (q.dataset.type === 'mcq') { var c = q.querySelector('input:checked'); o[id] = c ? c.value : ''; }
            else o[id] = q.querySelector('textarea').value;
        });
        return o;
    }
    function save(force) {
        if (closed || saving || (!dirty && !force)) return Promise.resolve();
        saving = true; dirty = false; $('saveState').textContent = 'Saving…';
        return post({ action: 'save', answers: JSON.stringify(answers()) })
            .then(function () { $('saveState').textContent = 'All answers saved'; })
            .catch(function () { dirty = true; $('saveState').textContent = 'Offline – will retry'; })
            .then(function () { saving = false; });
    }
    function finish() {
        if (closed) return; closed = true;
        window.removeEventListener('beforeunload', guard);
        if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {});
        location.replace('careers-assessment?t=' + encodeURIComponent(T));
    }
    function setStrikes(n) { $('strkN').textContent = n; $('strk').classList.toggle('hit', n > 0); }
    function toast(msg) { var t = $('toast'); t.textContent = msg; t.classList.add('on'); clearTimeout(toast.h); toast.h = setTimeout(function () { t.classList.remove('on'); }, 5000); }
    function strike(kind, msg) {
        if (closed) return;
        var now = Date.now(); if (now - lastStrike < 2000) return; lastStrike = now;
        post({ action: 'event', kind: kind }).then(function (j) {
            if (j && !j.closed) toast('Warning ' + j.strikes + ' of ' + MAX + ': ' + msg + '. At ' + MAX + ' the test submits automatically.');
        });
    }
    function note(kind, detail) { if (!closed) post({ action: 'event', kind: kind, detail: detail || '' }).catch(function () {}); }

    // Progress + word counts
    function progress() {
        var done = 0;
        qs.forEach(function (q) {
            var v = q.dataset.type === 'mcq' ? !!q.querySelector('input:checked') : q.querySelector('textarea').value.trim().length > 0;
            q.classList.toggle('answered', v); if (v) done++;
            if (q.dataset.type !== 'mcq') { var w = q.querySelector('textarea').value.trim(); q.querySelector('.wcN').textContent = w ? w.split(/\s+/).length : 0; }
        });
        $('progBar').style.width = (qs.length ? done / qs.length * 100 : 0) + '%';
        return done;
    }
    document.addEventListener('change', function () { dirty = true; progress(); save(); });
    document.querySelectorAll('textarea.w').forEach(function (ta) {
        var prev = ta.value.length;
        ta.addEventListener('input', function (ev) {
            dirty = true; progress();
            var grew = ta.value.length - prev; prev = ta.value.length;
            if (grew > 60 && ev.inputType !== 'insertText') note('bulk', grew + ' characters');
        });
    });
    progress();
    setInterval(function () { save(); }, 15000);

    // Timer (server clock is the authority; this is the display)
    function tick() {
        var s = Math.max(0, Math.round((end - Date.now()) / 1000));
        $('timeT').textContent = Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2);
        $('timer').classList.toggle('low', s <= 300);
        if (s <= 0 && !closed) { closed = true; post({ action: 'submit', reason: 'time_up', answers: JSON.stringify(answers()) }).then(function () { closed = false; finish(); }, function () { closed = false; finish(); }); return; }
        setTimeout(tick, 1000);
    }
    tick();

    // Integrity: leaving the page, focus and full screen
    var fsOK = !!(document.documentElement.requestFullscreen);
    var gate = $('gate');
    function needFS() { return fsOK && !document.fullscreenElement; }
    function showGate(again) {
        if (!needFS() || closed) { gate.classList.remove('on'); return; }
        $('gateH').textContent = again ? 'You left full screen' : 'Enter full screen to continue';
        $('gateP').textContent = again ? 'This was recorded as a warning. Return to full screen to keep going.' : 'The assessment runs in full screen. Leaving full screen counts as a warning.';
        gate.classList.add('on');
    }
    $('gateBtn').addEventListener('click', function () {
        document.documentElement.requestFullscreen({ navigationUI: 'hide' }).then(function () { gate.classList.remove('on'); }).catch(function () { gate.classList.remove('on'); fsOK = false; });
    });
    var wasFS = false;
    document.addEventListener('fullscreenchange', function () {
        if (document.fullscreenElement) { wasFS = true; gate.classList.remove('on'); return; }
        if (wasFS && !closed) { strike('fullscreen', 'you left full screen'); showGate(true); }
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) { save(true); strike('hidden', 'you left the test tab'); } });
    window.addEventListener('blur', function () { setTimeout(function () { if (!document.hidden && !document.hasFocus()) strike('blur', 'you switched to another window'); }, 400); });
    showGate(false);

    // Integrity: copying, pasting and shortcuts
    ['copy', 'cut', 'paste'].forEach(function (t) { document.addEventListener(t, function (ev) { ev.preventDefault(); note(t); toast('Copying and pasting are disabled during the assessment.'); }); });
    document.addEventListener('contextmenu', function (ev) { ev.preventDefault(); note('menu'); });
    document.addEventListener('drop', function (ev) { ev.preventDefault(); note('drop'); });
    document.addEventListener('dragstart', function (ev) { ev.preventDefault(); });
    document.addEventListener('selectstart', function (ev) { if (!(ev.target.closest && ev.target.closest('textarea'))) ev.preventDefault(); });
    document.addEventListener('keydown', function (ev) {
        var k = (ev.key || '').toLowerCase(), mod = ev.ctrlKey || ev.metaKey;
        if (k === 'printscreen') { note('print', 'PrintScreen'); return; }
        if (k === 'f12' || (mod && ev.shiftKey && ['i', 'j', 'c'].indexOf(k) > -1) || (mod && ['c', 'v', 'x', 'p', 's', 'u'].indexOf(k) > -1)) {
            ev.preventDefault(); note(k === 'p' ? 'print' : 'keys', (mod ? 'Ctrl+' : '') + (ev.shiftKey ? 'Shift+' : '') + ev.key);
        }
    });
    function guard(ev) { save(true); ev.preventDefault(); ev.returnValue = ''; }
    window.addEventListener('beforeunload', guard);

    // Submit
    $('submitBtn').addEventListener('click', function () {
        var left = qs.length - progress();
        $('confirmT').textContent = (left ? left + ' question' + (left === 1 ? ' is' : 's are') + ' still unanswered. ' : 'All questions answered. ') + 'You can\'t change your answers after submitting.';
        $('confirmM').classList.add('on');
    });
    $('cancelSub').addEventListener('click', function () { $('confirmM').classList.remove('on'); });
    $('doSub').addEventListener('click', function () {
        var b = $('doSub'); b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
        post({ action: 'submit', answers: JSON.stringify(answers()) }).then(finish, function () { b.disabled = false; b.textContent = 'Try again'; toast('Could not reach the server. Check your connection and try again.'); });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
