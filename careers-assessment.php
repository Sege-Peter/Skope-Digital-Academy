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
// Intro: what's in each part, with a suggested time split by marks
$parts = [];
if ($view === 'intro') {
    $all = sda_ax_questions($pdo, (int)$app['job_id']);
    $tot = array_sum(array_map(fn($q) => (float)$q['marks'], $all)) ?: 1;
    foreach ($all as $q) {
        $p = &$parts[$q['section']];
        $p['n'] = ($p['n'] ?? 0) + 1;
        $p['marks'] = ($p['marks'] ?? 0) + (float)$q['marks'];
        $p['written'] = ($p['written'] ?? false) || $q['qtype'] === 'written';
        unset($p);
    }
    foreach ($parts as $k => $p) $parts[$k]['mins'] = max(1, (int)round((int)$app['assess_minutes'] * $p['marks'] / $tot));
    $nq = count($all);
}
$fmtM = fn($n) => rtrim(rtrim(number_format((float)$n, 1), '0'), '.');
$secIcon = ['knowledge' => 'book-open', 'practical' => 'screwdriver-wrench', 'situational' => 'people-arrows', 'professional' => 'user-tie'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0b1324">
<title><?= $app ? 'Assessment: ' . $e($app['job_title']) : 'Assessment' ?> – Skope Digital Academy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" href="favicon.ico" sizes="any">
<style>
    * { box-sizing: border-box; }
    :root { --c: #00BFFF; --c2: #0080FF; --o: #FF8C00; --ink: #0f172a; --mut: #64748b; --line: #e2e8f0; --soft: #f8fafc; --ok: #16a34a; --bad: #dc2626; }
    body { margin: 0; font-family: 'Inter', sans-serif; color: #334155; background: #f1f5f9; -webkit-font-smoothing: antialiased; }
    h1, h2, h3 { font-family: 'Poppins', sans-serif; color: var(--ink); letter-spacing: -.02em; }
    .ax-shell { max-width: 780px; margin: 0 auto; padding: 32px 16px 60px; }
    .ax-brand { display: flex; align-items: center; gap: 10px; font: 800 .95rem 'Poppins', sans-serif; color: var(--ink); margin-bottom: 20px; }
    .ax-brand img { width: 36px; height: 36px; border-radius: 9px; } .ax-brand span { color: var(--o); } .ax-brand em { font-style: normal; color: var(--mut); font-weight: 600; }
    .card { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 28px; box-shadow: 0 20px 50px -34px rgba(15,23,42,.4); }
    .card + .card { margin-top: 16px; }
    .card h1 { font-size: clamp(1.4rem, 3.4vw, 1.9rem); margin: 0 0 8px; }
    .card h3 { font-size: 1.02rem; margin: 0 0 12px; display: flex; gap: 10px; align-items: center; }
    .card h3 i { color: var(--c2); }
    .muted { color: var(--mut); }
    .eyebrow { color: var(--mut); font-weight: 800; font-size: .74rem; letter-spacing: .12em; text-transform: uppercase; }
    .hero-top { display: flex; gap: 16px; align-items: center; }
    .hero-ic { flex: 0 0 auto; width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; background: linear-gradient(135deg, var(--c), var(--c2)); color: #fff; font-size: 1.4rem; }
    .kv { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 20px 0 0; }
    .kv div { background: var(--soft); border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; font-size: .78rem; color: var(--mut); }
    .kv b { display: block; font-size: 1.1rem; color: var(--ink); }
    .parts { display: grid; gap: 8px; }
    .part { display: grid; grid-template-columns: 40px 1fr auto; gap: 12px; align-items: center; padding: 12px 14px; border: 1px solid var(--line); border-radius: 14px; }
    .part > i { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; background: #e0f2fe; color: #0284c7; }
    .part b { display: block; color: var(--ink); font-size: .92rem; } .part small { color: var(--mut); font-size: .8rem; }
    .part .r { text-align: right; font-size: .8rem; color: var(--mut); } .part .r b { font-size: .95rem; }
    .checks { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .chk { display: flex; gap: 10px; align-items: flex-start; padding: 12px; border-radius: 12px; background: var(--soft); font-size: .86rem; line-height: 1.45; }
    .chk i { color: var(--ok); margin-top: 2px; }
    .rules { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
    .rules li { display: flex; gap: 12px; font-size: .9rem; line-height: 1.55; }
    .rules i { color: var(--c2); margin-top: 4px; width: 16px; text-align: center; flex: 0 0 auto; }
    .rules li.warn i { color: var(--bad); }
    .note { display: flex; gap: 10px; padding: 12px 14px; border-radius: 12px; font-size: .86rem; line-height: 1.5; margin-bottom: 14px; }
    .note.amber { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
    .note.red { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
    .agree { display: flex; gap: 12px; align-items: flex-start; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 14px; padding: 14px; font-size: .9rem; line-height: 1.5; margin: 0 0 16px; cursor: pointer; }
    .agree input { width: 20px; height: 20px; margin-top: 1px; accent-color: var(--o); flex: 0 0 auto; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 9px; padding: 14px 22px; border-radius: 12px; border: 0; font: 800 .95rem 'Inter', sans-serif; color: #fff; background: linear-gradient(135deg, var(--c), var(--c2)); cursor: pointer; text-decoration: none; transition: transform .15s, opacity .15s; }
    .btn:hover { transform: translateY(-1px); }
    .btn.ghost { background: #fff; color: var(--ink); border: 1px solid var(--line); }
    .btn.orange { background: linear-gradient(135deg, #ffa733, var(--o)); }
    .btn.big { width: 100%; padding: 16px; font-size: 1rem; }
    .btn[disabled] { opacity: .45; cursor: not-allowed; transform: none; }
    .state { text-align: center; }
    .state .ic { width: 76px; height: 76px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px; font-size: 1.9rem; background: #e0f2fe; color: #0284c7; }
    .state p { max-width: 520px; margin-left: auto; margin-right: auto; line-height: 1.65; }
    .tl { list-style: none; padding: 0; margin: 22px auto 6px; max-width: 460px; text-align: left; }
    .tl li { position: relative; padding: 0 0 18px 38px; font-size: .9rem; line-height: 1.5; color: var(--mut); }
    .tl li::before { content: ''; position: absolute; left: 11px; top: 26px; bottom: 0; width: 2px; background: var(--line); }
    .tl li:last-child::before { display: none; }
    .tl li i { position: absolute; left: 0; top: 0; width: 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: .7rem; background: var(--line); color: var(--mut); }
    .tl li.done i { background: #dcfce7; color: var(--ok); } .tl li.now i { background: #fff7ed; color: var(--o); }
    .tl b { display: block; color: var(--ink); }

    /* ── Test ── */
    body.testing { user-select: none; -webkit-user-select: none; background: var(--soft); }
    .tbar { position: sticky; top: 0; z-index: 30; background: #0b1324; color: #e2e8f0; }
    .tbar-in { max-width: 1180px; margin: 0 auto; display: flex; align-items: center; gap: 10px; padding: 10px 16px; }
    .tbar .ttl { font-weight: 700; font-size: .9rem; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tbar .ttl small { color: #94a3b8; font-weight: 600; margin-left: 6px; }
    .pill { display: inline-flex; gap: 7px; align-items: center; padding: 7px 12px; border-radius: 99px; background: rgba(255,255,255,.08); font-weight: 800; font-size: .84rem; white-space: nowrap; border: 0; color: inherit; font-family: inherit; }
    .pill.time { background: rgba(0,191,255,.16); color: #7dd3fc; font-variant-numeric: tabular-nums; font-size: .95rem; }
    .pill.time.low { background: rgba(220,38,38,.25); color: #fecaca; animation: pulse 1.4s infinite; }
    @keyframes pulse { 50% { opacity: .65; } }
    .pill.strk.hit { background: rgba(234,88,12,.28); color: #fed7aa; }
    .pill.navbtn { cursor: pointer; display: none; }
    .save { font-size: .76rem; color: #94a3b8; white-space: nowrap; } .save.off { color: #fca5a5; } .save i { margin-right: 4px; }
    .prog { height: 4px; background: #1e293b; } .prog i { display: block; height: 100%; width: 0; background: linear-gradient(90deg, var(--c), var(--o)); transition: width .3s; }
    .twrap { max-width: 1180px; margin: 0 auto; padding: 24px 16px 120px; display: grid; grid-template-columns: minmax(0, 1fr) 250px; gap: 26px; align-items: start; }
    .sec-h { margin: 30px 0 12px; display: flex; align-items: center; gap: 12px; scroll-margin-top: 80px; }
    .sec-h:first-child { margin-top: 0; }
    .sec-h > i { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; background: #e0f2fe; color: #0284c7; flex: 0 0 auto; }
    .sec-h h2 { font-size: 1.12rem; margin: 0; } .sec-h small { display: block; color: var(--mut); font-weight: 600; font-size: .8rem; }
    .q { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 20px 22px; margin-bottom: 14px; scroll-margin-top: 80px; transition: border-color .2s, box-shadow .2s; }
    .q.answered { border-color: #bae6fd; }
    .q.flash { box-shadow: 0 0 0 4px rgba(255,140,0,.3); border-color: var(--o); }
    .q-h { display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: .76rem; font-weight: 800; color: var(--mut); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 10px; }
    .q-h .st { text-transform: none; letter-spacing: 0; font-weight: 700; padding: 3px 9px; border-radius: 99px; background: #f1f5f9; }
    .q.answered .q-h .st { background: #dcfce7; color: var(--ok); }
    .q p.prompt { margin: 0 0 14px; font-size: 1rem; line-height: 1.65; color: var(--ink); white-space: pre-wrap; }
    .opt { display: flex; gap: 12px; align-items: center; padding: 13px 14px; border: 1.5px solid var(--line); border-radius: 12px; margin-bottom: 8px; cursor: pointer; font-size: .94rem; line-height: 1.5; transition: background .15s, border-color .15s; }
    .opt:hover { background: var(--soft); }
    .opt .let { flex: 0 0 auto; width: 28px; height: 28px; border-radius: 8px; display: grid; place-items: center; font-weight: 800; font-size: .8rem; background: #f1f5f9; color: var(--mut); }
    .opt input { position: absolute; opacity: 0; pointer-events: none; }
    .opt:has(input:checked) { border-color: var(--c); background: #f0f9ff; }
    .opt:has(input:checked) .let { background: var(--c2); color: #fff; }
    .opt:has(input:focus-visible) { outline: 3px solid rgba(0,191,255,.35); }
    .clear { background: none; border: 0; color: var(--mut); font: 600 .78rem 'Inter', sans-serif; cursor: pointer; padding: 4px 0; }
    .clear:hover { color: var(--bad); }
    textarea.w { width: 100%; min-height: 240px; padding: 14px; border: 1.5px solid #cbd5e1; border-radius: 12px; font: .96rem/1.65 'Inter', sans-serif; resize: vertical; user-select: text; -webkit-user-select: text; color: var(--ink); }
    textarea.w:focus { outline: 3px solid rgba(0,191,255,.2); border-color: var(--c); }
    .wc { display: flex; justify-content: space-between; gap: 10px; font-size: .78rem; color: var(--mut); margin-top: 6px; }
    .wc b { color: var(--ink); } .wc .good b { color: var(--ok); }
    .tfoot { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 20px 22px; display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; margin-top: 22px; }
    .tfoot p { margin: 0; font-size: .9rem; }
    /* Question map */
    .qmap { position: sticky; top: 84px; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 16px; max-height: calc(100vh - 110px); overflow-y: auto; }
    .qmap h4 { margin: 0 0 4px; font: 800 .9rem 'Poppins', sans-serif; color: var(--ink); }
    .qmap .sum { font-size: .8rem; color: var(--mut); margin-bottom: 12px; }
    .qmap .sl { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; color: var(--mut); margin: 12px 0 6px; }
    .qmap .grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
    .qmap a { display: grid; place-items: center; height: 34px; border-radius: 9px; border: 1.5px solid var(--line); font-weight: 800; font-size: .82rem; color: var(--mut); text-decoration: none; background: #fff; }
    .qmap a.done { background: var(--c2); border-color: var(--c2); color: #fff; }
    .qmap a:hover { border-color: var(--c); }
    .qmap .legend { display: flex; gap: 12px; margin-top: 14px; font-size: .72rem; color: var(--mut); }
    .qmap .legend span::before { content: ''; display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; border: 1.5px solid var(--line); }
    .qmap .legend span.d::before { background: var(--c2); border-color: var(--c2); }
    .wm { position: fixed; inset: 0; pointer-events: none; z-index: 15; opacity: .045; overflow: hidden; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 60px 80px; padding: 40px; transform: rotate(-18deg) scale(1.4); font: 700 15px 'Inter', sans-serif; color: var(--ink); }
    /* Overlays */
    .gate, .modal { position: fixed; inset: 0; z-index: 60; display: none; place-items: center; padding: 18px; }
    .gate { background: rgba(11,19,36,.97); color: #e2e8f0; text-align: center; }
    .modal { background: rgba(15,23,42,.6); }
    .gate.on, .modal.on { display: grid; }
    .gate .box { max-width: 470px; }
    .gate .gi { width: 74px; height: 74px; border-radius: 50%; margin: 0 auto 16px; display: grid; place-items: center; font-size: 1.8rem; background: rgba(0,191,255,.15); color: var(--c); }
    .gate.warn .gi { background: rgba(234,88,12,.2); color: #fdba74; }
    .gate h2 { color: #fff; margin: 0 0 10px; font-size: 1.5rem; } .gate p { line-height: 1.65; color: #cbd5e1; margin: 0 0 22px; }
    .dots { display: flex; justify-content: center; gap: 8px; margin: -8px 0 20px; }
    .dots i { width: 12px; height: 12px; border-radius: 50%; background: rgba(255,255,255,.15); } .dots i.on { background: #fb923c; }
    .modal .card { max-width: 460px; width: 100%; }
    .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 12px; }
    .chips a { padding: 6px 11px; border-radius: 99px; background: #fff7ed; border: 1px solid #fed7aa; color: #c2410c; font-weight: 700; font-size: .8rem; text-decoration: none; }
    .toast { position: fixed; left: 50%; bottom: 22px; transform: translateX(-50%); z-index: 70; color: #fff; padding: 12px 18px; border-radius: 12px; font-weight: 600; font-size: .9rem; display: none; max-width: calc(100vw - 32px); box-shadow: 0 14px 34px -12px rgba(0,0,0,.4); background: #1e293b; }
    .toast.on { display: flex; gap: 10px; align-items: center; } .toast.bad { background: #b91c1c; } .toast.info { background: #0369a1; }
    @media (max-width: 1100px) {
        .twrap { grid-template-columns: 1fr; }
        .qmap { position: fixed; top: auto; left: 0; right: 0; bottom: 0; z-index: 40; border-radius: 20px 20px 0 0; max-height: 70vh; transform: translateY(110%); transition: transform .25s; box-shadow: 0 -20px 50px -20px rgba(0,0,0,.4); }
        .qmap.open { transform: none; }
        .qmap .grid { grid-template-columns: repeat(8, 1fr); }
        .pill.navbtn { display: inline-flex; }
    }
    @media (max-width: 640px) {
        .card { padding: 20px; } .q { padding: 16px; } .tbar-in { gap: 6px; padding: 8px 10px; } .save, .tbar .ttl small { display: none; }
        .kv { grid-template-columns: 1fr 1fr; } .checks { grid-template-columns: 1fr; } .part { grid-template-columns: 36px 1fr; } .part .r { grid-column: 2; text-align: left; }
        .qmap .grid { grid-template-columns: repeat(6, 1fr); } .tfoot .btn { width: 100%; }
        .pill { padding: 6px 9px; font-size: .78rem; }
    }
</style>
</head>
<body class="<?= $view === 'test' ? 'testing' : '' ?>">

<?php if ($view !== 'test'): ?>
<div class="ax-shell">
    <div class="ax-brand"><img src="assets/pwa/icon-192.png" alt=""> SKOPE <span>DIGITAL</span> ACADEMY <em>· Careers</em></div>

    <?php if ($view === 'invalid'): ?>
        <div class="card state"><div class="ic" style="background:#fee2e2;color:var(--bad)"><i class="fas fa-link-slash"></i></div><h1>This link doesn't work</h1>
            <p class="muted">It may have been replaced by a newer link. Open the most recent email we sent you. If you still can't get in, email <a href="mailto:<?= $e(sda_careers_email()) ?>"><?= $e(sda_careers_email()) ?></a> with your name and the position.</p>
            <p><a class="btn ghost" href="careers"><i class="fas fa-briefcase"></i> Careers page</a></p></div>

    <?php elseif ($view === 'expired'): ?>
        <div class="card state"><div class="ic" style="background:#fef3c7;color:#b45309"><i class="fas fa-hourglass-end"></i></div><h1>This link has expired</h1>
            <p class="muted">Assessment links are valid for <?= SDA_AX_INVITE_DAYS ?> days. If you'd still like to be considered for <b><?= $e($app['job_title']) ?></b>, email <a href="mailto:<?= $e(sda_careers_email()) ?>"><?= $e(sda_careers_email()) ?></a> and we'll let you know if a new link can be sent.</p></div>

    <?php elseif ($view === 'other_device'): ?>
        <div class="card state"><div class="ic" style="background:#fee2e2;color:var(--bad)"><i class="fas fa-laptop-medical"></i></div><h1>Your test is open somewhere else</h1>
            <p class="muted">You started this assessment in a different browser or device, and it can only continue there. Go back to that device to finish. Opening it here was recorded as a warning.</p></div>

    <?php elseif ($view === 'intro'): ?>
        <div class="card">
            <?php if ($flash): ?><div class="note red"><i class="fas fa-circle-exclamation" style="margin-top:3px"></i><span><?= $e($flash) ?></span></div><?php endif; ?>
            <div class="hero-top"><div class="hero-ic"><i class="fas fa-clipboard-check"></i></div>
                <div><div class="eyebrow">Online assessment</div><h1 style="margin:2px 0 0"><?= $e($app['job_title']) ?></h1></div></div>
            <p class="muted" style="margin:16px 0 0;line-height:1.65">Hi <?= $e(strtok($app['name'], ' ')) ?>, thanks for applying! This assessment helps us see how you'd handle the real work of this role. Take a minute to read this page, then start when you're ready. The clock starts only when you press Start.</p>
            <div class="kv">
                <div><b><?= (int)$app['assess_minutes'] ?> min</b>Time limit</div>
                <div><b><?= $nq ?> questions</b>In 4 parts</div>
                <div><b><?= $e(date('j M', strtotime($app['invite_expires_at']))) ?></b>Start before <?= $e(date('H:i', strtotime($app['invite_expires_at']))) ?></div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fas fa-layer-group"></i> What's in it</h3>
            <div class="parts">
                <?php foreach (SDA_AX_SECTIONS as $k => $lbl): if (empty($parts[$k])) continue; $p = $parts[$k]; ?>
                <div class="part"><i class="fas fa-<?= $secIcon[$k] ?>"></i>
                    <div><b><?= $e($lbl) ?></b><small><?= $p['n'] ?> <?= $p['written'] ? 'written ' . ($p['n'] === 1 ? 'task' : 'tasks') : 'multiple-choice question' . ($p['n'] === 1 ? '' : 's') ?></small></div>
                    <div class="r"><b><?= $fmtM($p['marks']) ?> marks</b>about <?= $p['mins'] ?> min</div></div>
                <?php endforeach; ?>
            </div>
            <p class="muted" style="font-size:.84rem;margin:12px 0 0"><i class="fas fa-lightbulb" style="color:var(--o)"></i> Tip: the written tasks carry the most marks, so leave enough time for them. You can move between questions freely.</p>
        </div>

        <div class="card">
            <h3><i class="fas fa-list-check"></i> Before you start</h3>
            <div class="note amber" id="mobileNote" hidden><i class="fas fa-mobile-screen" style="margin-top:3px"></i><span>You seem to be on a phone. A laptop or desktop is strongly recommended: typing the written answers is much easier, and switching apps on a phone counts as a warning.</span></div>
            <div class="checks">
                <div class="chk"><i class="fas fa-circle-check"></i><span>A laptop or desktop, plugged in or charged</span></div>
                <div class="chk"><i class="fas fa-circle-check"></i><span>A stable internet connection</span></div>
                <div class="chk"><i class="fas fa-circle-check"></i><span><?= (int)$app['assess_minutes'] ?> uninterrupted minutes in a quiet place</span></div>
                <div class="chk"><i class="fas fa-circle-check"></i><span>Other tabs and apps closed, notifications turned off</span></div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fas fa-shield-halved"></i> Test rules</h3>
            <ul class="rules">
                <li><i class="fas fa-expand"></i><span>The test runs in <b>full screen</b>. Your answers save automatically as you go.</span></li>
                <li><i class="fas fa-user-lock"></i><span>Work on your own, without help, notes, other websites or AI tools. Write in your own words.</span></li>
                <li class="warn"><i class="fas fa-triangle-exclamation"></i><span><b>Stay on the test page.</b> Switching tabs or apps, leaving full screen, reloading or reopening the page each give you a warning. <b>At <?= SDA_AX_MAX_STRIKES ?> warnings the test is submitted automatically.</b></span></li>
                <li class="warn"><i class="fas fa-ban"></i><span>Copy, paste and right-click are switched off, and attempts are recorded.</span></li>
                <li><i class="fas fa-laptop"></i><span>One attempt, on one device. Once started it can't be moved to another device or restarted.</span></li>
                <li><i class="fas fa-clock"></i><span>When time runs out, everything you've written is submitted for you.</span></li>
            </ul>
            <p style="margin:16px 0 0"><a href="careers-guide#before" target="_blank" rel="noopener" style="display:inline-flex;gap:8px;align-items:center;font-weight:700;color:#0284c7;text-decoration:none"><i class="fas fa-book-open"></i> Read the full applicant rules &amp; instructions (opens in a new tab)</a></p>
        </div>

        <form method="post" id="startForm" class="card">
            <input type="hidden" name="t" value="<?= $e($raw) ?>"><input type="hidden" name="action" value="start"><input type="hidden" name="nonce" value="<?= $e($nonce) ?>">
            <label class="agree"><input type="checkbox" name="agree" value="1" id="agree"> <span>I've read the rules. I'll complete this assessment myself, in one sitting, without help or outside tools, and I understand that integrity events are recorded and shared with the recruitment team.</span></label>
            <button class="btn big" id="startBtn" disabled><i class="fas fa-play"></i> I'm ready: start my <?= (int)$app['assess_minutes'] ?>-minute assessment</button>
            <p class="muted" style="font-size:.8rem;text-align:center;margin:10px 0 0">Not ready yet? Close this page; your link works until <?= $e(date('j M, H:i', strtotime($app['invite_expires_at']))) ?>.</p>
        </form>
        <script>
            var a = document.getElementById('agree'), b = document.getElementById('startBtn');
            a.addEventListener('change', function () { b.disabled = !a.checked; });
            document.getElementById('startForm').addEventListener('submit', function () { b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting your assessment…'; });
            if (/Android|iPhone|iPod|Mobile|Opera Mini|IEMobile/i.test(navigator.userAgent) && Math.min(screen.width, screen.height) < 700) document.getElementById('mobileNote').hidden = false;
        </script>

    <?php elseif ($view === 'done'):
        $why = ['submitted' => 'Your answers were submitted.', 'time_up' => 'Time ran out, so everything you had written was submitted for you.', 'violations' => 'Your test was submitted automatically after ' . SDA_AX_MAX_STRIKES . ' warnings. Everything you had written was saved and will be reviewed.'][$app['end_reason'] ?? ''] ?? 'Your assessment is closed.';
        $st = $app['status']; $stage = in_array($st, ['shortlisted', 'interview', 'offered'], true) ? 3 : (in_array($st, ['evaluated', 'rejected'], true) ? 2 : 1); ?>
        <div class="card state"><div class="ic" style="background:#dcfce7;color:var(--ok)"><i class="fas fa-circle-check"></i></div>
            <h1>All done – thank you!</h1>
            <p class="muted"><?= $e($why) ?> Thanks for the time you put into your application for <b><?= $e($app['job_title']) ?></b>.</p>
            <ul class="tl">
                <li class="done"><i class="fas fa-check"></i><b>Application received</b><?= $e(date('j M Y', strtotime($app['created_at']))) ?></li>
                <li class="done"><i class="fas fa-check"></i><b>Assessment completed</b><?= $app['submitted_at'] ? $e(date('j M Y, H:i', strtotime($app['submitted_at']))) : '' ?></li>
                <li class="<?= $stage >= 2 ? 'done' : 'now' ?>"><i class="fas fa-<?= $stage >= 2 ? 'check' : 'magnifying-glass' ?>"></i><b>Evaluation</b>Our team reviews every answer, including your written work.</li>
                <li class="<?= $stage >= 3 ? 'done' : '' ?>"><i class="fas fa-<?= $stage >= 3 ? 'check' : 'comments' ?>"></i><b>Interview</b>Shortlisted candidates are invited by email.</li>
            </ul>
            <p class="muted" style="font-size:.86rem">We'll email you at <b><?= $e($app['email']) ?></b>. There's nothing else you need to do for now. You can close this page.</p></div>

    <?php else: [$sl] = sda_app_status_label($app['status']); ?>
        <div class="card state"><div class="ic"><i class="fas fa-file-circle-check"></i></div><h1>Application received</h1>
            <p class="muted">Your application for <b><?= $e($app['job_title']) ?></b> is with our team. Current status: <b><?= $e($sl) ?></b>. We'll email you with any updates.</p></div>
    <?php endif; ?>
</div>

<?php else: /* ── The test ── */ $left = ax_left($app);
    $secCount = []; foreach ($items as $it) $secCount[$it['section']] = ($secCount[$it['section']] ?? 0) + 1; ?>
<div class="tbar"><div class="tbar-in">
    <div class="ttl"><i class="fas fa-shield-halved" style="color:var(--c)"></i> <?= $e($app['job_title']) ?><small><?= $e($app['name']) ?></small></div>
    <span class="save" id="saveState"><i class="fas fa-cloud"></i>Saved</span>
    <button class="pill navbtn" type="button" id="mapBtn" aria-label="Question list"><i class="fas fa-table-cells"></i> <span id="mapN">0</span>/<?= count($items) ?></button>
    <span class="pill strk<?= $app['violations'] ? ' hit' : '' ?>" id="strk" title="Warnings: at <?= SDA_AX_MAX_STRIKES ?> the test submits automatically"><i class="fas fa-triangle-exclamation"></i> <span id="strkN"><?= (int)$app['violations'] ?></span>/<?= SDA_AX_MAX_STRIKES ?></span>
    <span class="pill time" id="timer" title="Time left"><i class="fas fa-clock"></i> <span id="timeT">--:--</span></span>
</div><div class="prog"><i id="progBar"></i></div></div>
<div class="wm" aria-hidden="true"><?= str_repeat('<span>' . $e($app['name'] . ' · ' . $app['email']) . '</span>', 40) ?></div>

<div class="twrap">
<main id="test">
    <?php $n = 0; $cur = ''; foreach ($items as $it): $n++;
        if ($it['section'] !== $cur): $cur = $it['section']; $secMarks = array_sum(array_map(fn($x) => $x['section'] === $cur ? $x['marks'] : 0, $items)); ?>
        <div class="sec-h" id="sec-<?= $cur ?>"><i class="fas fa-<?= $secIcon[$cur] ?? 'circle' ?>"></i><div><h2><?= $e(SDA_AX_SECTIONS[$cur] ?? $cur) ?></h2><small><?= $secCount[$cur] ?> question<?= $secCount[$cur] === 1 ? '' : 's' ?> · <?= $fmtM($secMarks) ?> marks</small></div></div>
    <?php endif; ?>
    <section class="q<?= $it['value'] !== '' ? ' answered' : '' ?>" id="q-<?= $n ?>" data-n="<?= $n ?>" data-q="<?= $it['id'] ?>" data-type="<?= $it['type'] ?>" data-marks="<?= $it['marks'] ?>">
        <div class="q-h"><span>Question <?= $n ?> of <?= count($items) ?> · <?= $fmtM($it['marks']) ?> marks</span><span class="st"><?= $it['value'] !== '' ? 'Answered' : 'Not answered' ?></span></div>
        <p class="prompt"><?= $e($it['prompt']) ?></p>
        <?php if ($it['type'] === 'mcq'): foreach ($it['options'] as $i => $opt): ?>
            <label class="opt"><input type="radio" name="q<?= $it['id'] ?>" value="<?= $i ?>" <?= $it['value'] === (string)$i ? 'checked' : '' ?>><span class="let"><?= chr(65 + $i) ?></span><span><?= $e($opt) ?></span></label>
        <?php endforeach; ?>
            <button type="button" class="clear" data-clear>Clear my answer</button>
        <?php else: $target = $it['marks'] >= 30 ? [200, 450] : [100, 250]; ?>
            <textarea class="w" name="q<?= $it['id'] ?>" spellcheck="true" autocomplete="off" data-min="<?= $target[0] ?>" data-max="<?= $target[1] ?>" placeholder="Type your answer here, in your own words. Use short paragraphs or bullet points – whatever makes your thinking clear."><?= $e($it['value']) ?></textarea>
            <div class="wc"><span>Suggested length: about <?= $target[0] ?>–<?= $target[1] ?> words</span><span class="wcS"><b class="wcN">0</b> words</span></div>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
    <div class="tfoot"><p><b>Finished?</b> <span class="muted" id="footInfo">Check the question list for anything you've missed.</span></p><button class="btn orange" type="button" id="submitBtn"><i class="fas fa-paper-plane"></i> Review &amp; submit</button></div>
</main>
<aside class="qmap" id="qmap" aria-label="Questions">
    <h4>Questions</h4>
    <div class="sum"><b id="mapDone">0</b> of <?= count($items) ?> answered</div>
    <?php $n = 0; $cur = ''; foreach ($items as $it): $n++;
        if ($it['section'] !== $cur): if ($cur !== '') echo '</div>'; $cur = $it['section']; ?><div class="sl"><?= $e(SDA_AX_SECTIONS[$cur] ?? $cur) ?></div><div class="grid"><?php endif; ?>
        <a href="#q-<?= $n ?>" data-go="<?= $n ?>" class="<?= $it['value'] !== '' ? 'done' : '' ?>" title="Question <?= $n ?>"><?= $n ?></a>
    <?php endforeach; if ($items) echo '</div>'; ?>
    <div class="legend"><span class="d">Answered</span><span>Not yet</span></div>
    <button class="btn orange" type="button" id="submitBtn2" style="width:100%;margin-top:14px;padding:12px"><i class="fas fa-paper-plane"></i> Review &amp; submit</button>
</aside>
</div>

<div class="gate" id="gate"><div class="box">
    <div class="gi" id="gateI"><i class="fas fa-expand"></i></div>
    <h2 id="gateH">Ready? Let's begin</h2>
    <div class="dots" id="gateDots" hidden><?= str_repeat('<i></i>', SDA_AX_MAX_STRIKES) ?></div>
    <p id="gateP">The assessment opens in full screen. Your timer is already running, so take a breath and click below. Good luck!</p>
    <button class="btn" type="button" id="gateBtn"><i class="fas fa-expand"></i> Open full screen &amp; begin</button>
</div></div>
<div class="modal" id="confirmM"><div class="card">
    <h2 style="margin-top:0;font-size:1.3rem">Submit your assessment?</h2>
    <p class="muted" id="confirmT" style="line-height:1.6;margin:0"></p>
    <div class="chips" id="confirmChips"></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;margin-top:20px"><button class="btn ghost" type="button" id="cancelSub">Keep working</button><button class="btn orange" type="button" id="doSub"><i class="fas fa-paper-plane"></i> Submit now</button></div>
</div></div>
<div class="toast" id="toast" role="status"></div>

<script>
(function () {
    'use strict';
    var T = <?= json_encode($raw) ?>, MAX = <?= SDA_AX_MAX_STRIKES ?>;
    var end = Date.now() + <?= $left ?> * 1000, closed = false, dirty = false, lastStrike = 0, saving = false, strikes = <?= (int)$app['violations'] ?>;
    var $ = function (id) { return document.getElementById(id); };
    var qs = Array.prototype.slice.call(document.querySelectorAll('.q'));
    var mapLinks = Array.prototype.slice.call(document.querySelectorAll('.qmap a[data-go]'));

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
    function saveLabel(cls, html) { var s = $('saveState'); s.className = 'save' + (cls ? ' ' + cls : ''); s.innerHTML = html; }
    function save(force) {
        if (closed || saving || (!dirty && !force)) return Promise.resolve();
        saving = true; dirty = false; saveLabel('', '<i class="fas fa-rotate fa-spin"></i>Saving…');
        return post({ action: 'save', answers: JSON.stringify(answers()) })
            .then(function () { var d = new Date(); saveLabel('', '<i class="fas fa-cloud"></i>Saved ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2)); })
            .catch(function () { dirty = true; saveLabel('off', '<i class="fas fa-wifi"></i>Offline – retrying'); })
            .then(function () { saving = false; });
    }
    function finish() {
        if (closed) return; closed = true;
        window.removeEventListener('beforeunload', guard);
        if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {});
        location.replace('careers-assessment?t=' + encodeURIComponent(T));
    }
    function setStrikes(n) { strikes = n; $('strkN').textContent = n; $('strk').classList.toggle('hit', n > 0); }
    function toast(msg, kind, icon) {
        var t = $('toast'); t.className = 'toast on ' + (kind || ''); t.innerHTML = '<i class="fas fa-' + (icon || 'circle-info') + '"></i><span></span>'; t.lastChild.textContent = msg;
        clearTimeout(toast.h); toast.h = setTimeout(function () { t.classList.remove('on'); }, 5500);
    }

    // ── Full screen gate + warnings (one overlay) ──
    var fsOK = !!(document.documentElement.requestFullscreen), gate = $('gate'), wasFS = false;
    function inFS() { return !!document.fullscreenElement; }
    function showGate(mode, msg) {
        if (closed) return;
        var warn = mode === 'warn';
        gate.classList.toggle('warn', warn);
        $('gateI').innerHTML = '<i class="fas fa-' + (warn ? 'triangle-exclamation' : 'expand') + '"></i>';
        $('gateDots').hidden = !warn;
        Array.prototype.forEach.call($('gateDots').children, function (d, i) { d.classList.toggle('on', i < strikes); });
        if (warn) {
            var left = MAX - strikes;
            $('gateH').textContent = 'Warning ' + strikes + ' of ' + MAX;
            $('gateP').textContent = msg + '. This has been recorded. ' + (left === 1 ? 'One more warning and your test will be submitted automatically.' : 'You have ' + left + ' warnings left before the test is submitted automatically.') + ' Stay on this page until you finish.';
            $('gateBtn').innerHTML = '<i class="fas fa-arrow-rotate-left"></i> Back to my test';
        } else {
            $('gateH').textContent = 'Ready? Let\'s begin';
            $('gateP').textContent = 'The assessment opens in full screen. Your timer is already running, so take a breath and click below. Good luck!';
            $('gateBtn').innerHTML = '<i class="fas fa-expand"></i> Open full screen & begin';
        }
        gate.classList.add('on');
    }
    $('gateBtn').addEventListener('click', function () {
        if (fsOK && !inFS()) document.documentElement.requestFullscreen({ navigationUI: 'hide' }).then(function () { gate.classList.remove('on'); }).catch(function () { fsOK = false; gate.classList.remove('on'); });
        else gate.classList.remove('on');
    });
    function strike(kind, msg) {
        if (closed) return;
        var now = Date.now(); if (now - lastStrike < 2500) return; lastStrike = now;
        save(true);
        post({ action: 'event', kind: kind }).then(function (j) { if (j && !j.closed) showGate('warn', msg); });
    }
    function note(kind, detail) { if (!closed) post({ action: 'event', kind: kind, detail: detail || '' }).catch(function () {}); }
    document.addEventListener('fullscreenchange', function () {
        if (inFS()) { wasFS = true; return; }
        if (wasFS && !closed) strike('fullscreen', 'You left full screen');
    });
    document.addEventListener('visibilitychange', function () { if (document.hidden) strike('hidden', 'You left the test page'); });
    window.addEventListener('blur', function () { setTimeout(function () { if (!document.hidden && !document.hasFocus() && !closed) strike('blur', 'You switched to another window'); }, 400); });
    if (fsOK) showGate('start');
    else if (strikes) toast('You have ' + strikes + ' of ' + MAX + ' warnings. Stay on this page until you finish.', 'bad', 'triangle-exclamation');

    // ── Progress, question map, word counts ──
    function isDone(q) { return q.dataset.type === 'mcq' ? !!q.querySelector('input:checked') : q.querySelector('textarea').value.trim().length > 0; }
    function progress() {
        var done = 0;
        qs.forEach(function (q, i) {
            var v = isDone(q); if (v) done++;
            q.classList.toggle('answered', v);
            q.querySelector('.st').textContent = v ? 'Answered' : 'Not answered';
            if (mapLinks[i]) mapLinks[i].classList.toggle('done', v);
            if (q.dataset.type !== 'mcq') {
                var w = q.querySelector('textarea').value.trim(), n = w ? w.split(/\s+/).length : 0;
                q.querySelector('.wcN').textContent = n;
                q.querySelector('.wcS').classList.toggle('good', n >= +q.querySelector('textarea').dataset.min);
            }
        });
        $('progBar').style.width = (qs.length ? done / qs.length * 100 : 0) + '%';
        $('mapDone').textContent = done; $('mapN').textContent = done;
        var left = qs.length - done;
        $('footInfo').textContent = left ? left + ' question' + (left === 1 ? '' : 's') + ' still unanswered.' : 'Every question has an answer. Nice work!';
        return done;
    }
    document.addEventListener('change', function () { dirty = true; progress(); save(); });
    document.querySelectorAll('[data-clear]').forEach(function (b) { b.addEventListener('click', function () { var c = b.closest('.q').querySelector('input:checked'); if (c) { c.checked = false; dirty = true; progress(); save(); } }); });
    document.querySelectorAll('textarea.w').forEach(function (ta) {
        var prev = ta.value.length;
        ta.addEventListener('input', function (ev) {
            dirty = true; progress();
            var grew = ta.value.length - prev; prev = ta.value.length;
            if (grew > 60 && ev.inputType !== 'insertText') note('bulk', grew + ' characters');
        });
        ta.addEventListener('blur', function () { save(); });
    });
    progress();
    setInterval(function () { save(); }, 15000);

    function go(n) {
        var q = $('q-' + n); if (!q) return;
        $('qmap').classList.remove('open'); $('confirmM').classList.remove('on');
        q.scrollIntoView({ behavior: 'smooth', block: 'start' });
        q.classList.add('flash'); setTimeout(function () { q.classList.remove('flash'); }, 1600);
        var f = q.querySelector('textarea, input'); if (f) setTimeout(function () { f.focus({ preventScroll: true }); }, 400);
    }
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest && ev.target.closest('[data-go]');
        if (a) { ev.preventDefault(); go(a.dataset.go); }
    });
    $('mapBtn').addEventListener('click', function () { $('qmap').classList.toggle('open'); });

    // ── Timer (the server clock decides; this is the display) ──
    var told10 = false, told2 = false;
    function tick() {
        var s = Math.max(0, Math.round((end - Date.now()) / 1000)), h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60);
        $('timeT').textContent = (h ? h + ':' + ('0' + m).slice(-2) : m) + ':' + ('0' + s % 60).slice(-2);
        $('timer').classList.toggle('low', s <= 300);
        if (s <= 600 && s > 120 && !told10) { told10 = true; toast('10 minutes left. Start wrapping up your answers.', 'info', 'clock'); }
        if (s <= 120 && s > 0 && !told2) { told2 = true; toast('2 minutes left! Your answers will be submitted automatically when time runs out.', 'bad', 'clock'); }
        if (s <= 0 && !closed) { closed = true; post({ action: 'submit', reason: 'time_up', answers: JSON.stringify(answers()) }).then(function () { closed = false; finish(); }, function () { closed = false; finish(); }); return; }
        setTimeout(tick, 1000);
    }
    tick();

    // ── Copy/paste and shortcuts ──
    ['copy', 'cut', 'paste'].forEach(function (t) { document.addEventListener(t, function (ev) { ev.preventDefault(); note(t); toast('Copy and paste are switched off during the assessment. Please type your answer.', '', 'ban'); }); });
    document.addEventListener('contextmenu', function (ev) { ev.preventDefault(); note('menu'); });
    document.addEventListener('drop', function (ev) { ev.preventDefault(); note('drop'); });
    document.addEventListener('dragstart', function (ev) { ev.preventDefault(); });
    document.addEventListener('selectstart', function (ev) { if (!(ev.target.closest && ev.target.closest('textarea'))) ev.preventDefault(); });
    document.addEventListener('keydown', function (ev) {
        var k = (ev.key || '').toLowerCase(), mod = ev.ctrlKey || ev.metaKey;
        if (k === 'printscreen') { note('print', 'PrintScreen'); return; }
        if (k === 'f12' || (mod && ev.shiftKey && ['i', 'j', 'c'].indexOf(k) > -1) || (mod && ['c', 'v', 'x', 'p', 's', 'u'].indexOf(k) > -1)) {
            ev.preventDefault(); note(k === 'p' ? 'print' : 'keys', (mod ? 'Ctrl+' : '') + (ev.shiftKey ? 'Shift+' : '') + ev.key);
            if (['c', 'v', 'x'].indexOf(k) > -1) toast('Copy and paste are switched off during the assessment.', '', 'ban');
        }
    });
    function guard(ev) { save(true); ev.preventDefault(); ev.returnValue = ''; }
    window.addEventListener('beforeunload', guard);

    // ── Submit ──
    function openSubmit() {
        var missing = qs.filter(function (q) { return !isDone(q); });
        $('confirmT').textContent = missing.length
            ? 'You still have ' + missing.length + ' unanswered question' + (missing.length === 1 ? '' : 's') + '. Unanswered questions score 0. Tap a number to go back to it, or submit anyway.'
            : 'You\'ve answered every question. Once you submit, you can\'t change your answers.';
        $('confirmChips').innerHTML = missing.map(function (q) { return '<a href="#q-' + q.dataset.n + '" data-go="' + q.dataset.n + '">Question ' + q.dataset.n + '</a>'; }).join('');
        $('qmap').classList.remove('open');
        $('confirmM').classList.add('on');
    }
    $('submitBtn').addEventListener('click', openSubmit);
    $('submitBtn2').addEventListener('click', openSubmit);
    $('cancelSub').addEventListener('click', function () { $('confirmM').classList.remove('on'); });
    $('doSub').addEventListener('click', function () {
        var b = $('doSub'); b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
        post({ action: 'submit', answers: JSON.stringify(answers()) }).then(finish, function () { b.disabled = false; b.innerHTML = '<i class="fas fa-paper-plane"></i> Try again'; toast('We couldn\'t reach the server. Check your connection and try again – your answers are safe.', 'bad', 'wifi'); });
    });
})();
</script>
<?php endif; ?>
</body>
</html>
