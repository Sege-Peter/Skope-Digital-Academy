<?php
/**
 * Practice (demo) assessment for applicants: same screen, rules and warnings as the real test
 * (careers-assessment.php) but runs entirely in the browser – nothing is saved, recorded or scored.
 */
require_once 'includes/db.php';
require_once 'includes/careers.php';
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer');
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$back = preg_match('/^[a-f0-9]{48}$/', (string)($_GET['t'] ?? '')) ? 'careers-assessment?t=' . $_GET['t'] : '';
$MAX = SDA_AX_MAX_STRIKES;

// Sample questions – general workplace topics, not taken from any real assessment.
$demo = [
    ['knowledge', 'mcq', 'You receive an email that looks like it\'s from the academy\'s IT team, asking you to reply with your password to "verify your account". What should you do?',
        ['Reply quickly so your account isn\'t locked', 'Don\'t reply; report it to IT as a likely phishing email', 'Forward it to colleagues to check if they got it too', 'Reply with an old password'], 1,
        'Real IT teams never ask for your password. Reporting it protects everyone.'],
    ['knowledge', 'mcq', 'Which is the best way to manage several tasks with different deadlines?',
        ['Work on whatever arrives first', 'List the tasks, prioritise by urgency and importance, and plan your time', 'Do the easiest tasks first and hope the rest fit', 'Wait for your manager to remind you'], 1,
        'Prioritising and planning lets you meet deadlines and flag risks early.'],
    ['situational', 'mcq', 'A learner emails a complaint at 5 pm on Friday. You can\'t resolve it until Monday. What is the best response?',
        ['Ignore it until Monday', 'Reply promptly to acknowledge it, and say when and how you will follow up', 'Forward it to your manager without comment', 'Tell the learner the office is closed'], 1,
        'A quick, honest acknowledgement with a clear next step builds trust.'],
    ['situational', 'mcq', 'In a team meeting, a colleague strongly disagrees with your idea. What do you do?',
        ['Insist your idea is right', 'Listen, ask questions to understand their view, and look for common ground', 'Stay silent and drop the idea', 'Complain about them after the meeting'], 1,
        'Listening and finding common ground usually leads to a better solution.'],
    ['professional', 'written', 'In about 50–100 words, tell us about one skill you would like to improve this year, and how you plan to do it.', [], null,
        'In the real assessment, written answers are marked by the recruitment panel against a marking guide: clear, specific and realistic answers score best.'],
];
// Shuffle the options each visit (keep track of the right one)
foreach ($demo as &$dq) {
    if ($dq[1] !== 'mcq') continue;
    $order = array_keys($dq[3]); shuffle($order);
    $dq[4] = array_search($dq[4], $order, true);
    $dq[3] = array_map(fn($i) => $dq[3][$i], $order);
}
unset($dq);
$secs = ['knowledge' => 'Knowledge (sample)', 'situational' => 'Situational judgement (sample)', 'professional' => 'Written task (sample)'];
$secIcon = ['knowledge' => 'book-open', 'situational' => 'people-arrows', 'professional' => 'user-tie'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0b1324">
<title>Practice Test – Skope Digital Academy Careers</title>
<meta name="description" content="Try a free 5-minute practice version of the Skope Digital Academy online recruitment assessment. Nothing is saved or recorded.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/careers-test.css?v=1">
<link rel="icon" href="favicon.ico" sizes="any">
<style>
    .demo-badge { display: inline-flex; gap: 6px; align-items: center; padding: 5px 10px; border-radius: 99px; background: #FF8C00; color: #fff; font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .tbar .demo-badge { margin-left: 8px; }
    .demo-strip { background: #fff7ed; border-bottom: 1px solid #fed7aa; color: #9a3412; text-align: center; font-size: .82rem; font-weight: 600; padding: 7px 12px; }
    .res-top { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 18px 0; }
    .res-top div { background: var(--soft); border: 1px solid var(--line); border-radius: 14px; padding: 14px; font-size: .8rem; color: var(--mut); text-align: center; }
    .res-top b { display: block; font-size: 1.5rem; color: var(--ink); }
    .res-q { border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; margin-bottom: 10px; text-align: left; }
    .res-q .h { display: flex; justify-content: space-between; gap: 10px; font-size: .76rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--mut); margin-bottom: 6px; }
    .res-q p { margin: 0 0 8px; color: var(--ink); line-height: 1.55; font-size: .92rem; }
    .res-q .a { font-size: .86rem; line-height: 1.5; }
    .res-q .ok { color: var(--ok); font-weight: 700; } .res-q .no { color: var(--bad); font-weight: 700; }
    .res-q .why { font-size: .84rem; color: var(--mut); margin-top: 6px; background: #f0f9ff; border-radius: 10px; padding: 8px 10px; }
    .learned { display: grid; gap: 8px; text-align: left; margin-top: 8px; }
    .learned div { display: flex; gap: 10px; font-size: .88rem; line-height: 1.5; } .learned i { color: var(--ok); margin-top: 4px; }
    #view-test, #view-done { display: none; }
    @media (max-width: 640px) { .res-top { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>

<!-- ── Intro ── -->
<div id="view-intro" class="ax-shell">
    <div class="ax-brand"><img src="assets/pwa/icon-192.png" alt=""> SKOPE <span>DIGITAL</span> ACADEMY <em>· Careers</em></div>
    <div class="card">
        <div class="hero-top"><div class="hero-ic" style="background:linear-gradient(135deg,#ffa733,#FF8C00)"><i class="fas fa-flask"></i></div>
            <div><span class="demo-badge"><i class="fas fa-flask"></i> Practice mode</span><h1 style="margin:6px 0 0">Practice test</h1></div></div>
        <p class="muted" style="margin:16px 0 0;line-height:1.65">Get comfortable with the online assessment before the real thing. This short demo works exactly like the real test – full screen, timer, question list, autosave and warnings – but <b>nothing is saved, recorded or sent to us</b>, and it doesn't affect your application. Take it as many times as you like.</p>
        <div class="kv">
            <div><b>5 min</b>Time limit</div>
            <div><b><?= count($demo) ?> questions</b>Sample, not real ones</div>
            <div><b>0 risk</b>Not recorded</div>
        </div>
    </div>
    <div class="card">
        <h3><i class="fas fa-hand-pointer"></i> Things to try</h3>
        <ul class="rules">
            <li><i class="fas fa-table-cells"></i><span>Jump between questions with the <b>question list</b>, then answer them in any order.</span></li>
            <li><i class="fas fa-pen"></i><span>Type the <b>written answer</b> and watch the word count.</span></li>
            <li class="warn"><i class="fas fa-triangle-exclamation"></i><span>Switch to another tab or press <b>Esc</b> to leave full screen, to see what a <b>warning</b> looks like. In the real test, <?= $MAX ?> warnings submit your test automatically.</span></li>
            <li class="warn"><i class="fas fa-ban"></i><span>Try to <b>copy or paste</b> – it's switched off, just like in the real test.</span></li>
            <li><i class="fas fa-paper-plane"></i><span>Press <b>Review &amp; submit</b> to see how unanswered questions are shown, then see your practice results.</span></li>
        </ul>
        <button class="btn big orange" type="button" id="startDemo" style="margin-top:20px"><i class="fas fa-play"></i> Start the practice test</button>
        <p class="muted" style="font-size:.8rem;text-align:center;margin:10px 0 0">The timer starts when you press Start. <a href="careers-guide" style="color:#0284c7">Read the full applicant rules</a><?= $back ? ' · <a href="' . $e($back) . '" style="color:#0284c7">Back to my real assessment</a>' : '' ?></p>
    </div>
</div>

<!-- ── Test (same layout as the real one) ── -->
<div id="view-test">
    <div class="tbar"><div class="tbar-in">
        <div class="ttl"><i class="fas fa-flask" style="color:#FF8C00"></i> Practice test<span class="demo-badge">Not recorded</span></div>
        <span class="save" id="saveState"><i class="fas fa-cloud"></i>Saved</span>
        <button class="pill navbtn" type="button" id="mapBtn" aria-label="Question list"><i class="fas fa-table-cells"></i> <span id="mapN">0</span>/<?= count($demo) ?></button>
        <span class="pill strk" id="strk" title="Practice warnings"><i class="fas fa-triangle-exclamation"></i> <span id="strkN">0</span>/<?= $MAX ?></span>
        <span class="pill time" id="timer"><i class="fas fa-clock"></i> <span id="timeT">5:00</span></span>
    </div><div class="prog"><i id="progBar"></i></div></div>
    <div class="demo-strip"><i class="fas fa-flask"></i> Practice mode – sample questions; nothing is saved or recorded.</div>
    <div class="twrap">
    <main>
        <?php $n = 0; $cur = ''; foreach ($demo as $q): $n++;
            if ($q[0] !== $cur): $cur = $q[0]; $cnt = count(array_filter($demo, fn($x) => $x[0] === $cur)); ?>
            <div class="sec-h"><i class="fas fa-<?= $secIcon[$cur] ?>"></i><div><h2><?= $e($secs[$cur]) ?></h2><small><?= $cnt ?> question<?= $cnt === 1 ? '' : 's' ?></small></div></div>
        <?php endif; ?>
        <section class="q" id="q-<?= $n ?>" data-n="<?= $n ?>" data-type="<?= $q[1] ?>">
            <div class="q-h"><span>Question <?= $n ?> of <?= count($demo) ?></span><span class="st">Not answered</span></div>
            <p class="prompt"><?= $e($q[2]) ?></p>
            <?php if ($q[1] === 'mcq'): foreach ($q[3] as $i => $o): ?>
                <label class="opt"><input type="radio" name="d<?= $n ?>" value="<?= $i ?>"><span class="let"><?= chr(65 + $i) ?></span><span><?= $e($o) ?></span></label>
            <?php endforeach; ?><button type="button" class="clear" data-clear>Clear my answer</button>
            <?php else: ?>
                <textarea class="w" data-min="50" placeholder="Type your answer here, in your own words." style="min-height:160px"></textarea>
                <div class="wc"><span>Suggested length: about 50–100 words</span><span class="wcS"><b class="wcN">0</b> words</span></div>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
        <div class="tfoot"><p><b>Finished?</b> <span class="muted" id="footInfo"></span></p><button class="btn orange" type="button" id="submitBtn"><i class="fas fa-paper-plane"></i> Review &amp; submit</button></div>
    </main>
    <aside class="qmap" id="qmap">
        <h4>Questions</h4><div class="sum"><b id="mapDone">0</b> of <?= count($demo) ?> answered</div>
        <?php $n = 0; $cur = ''; foreach ($demo as $q): $n++;
            if ($q[0] !== $cur): if ($cur !== '') echo '</div>'; $cur = $q[0]; ?><div class="sl"><?= $e($secs[$cur]) ?></div><div class="grid"><?php endif; ?>
            <a href="#q-<?= $n ?>" data-go="<?= $n ?>"><?= $n ?></a>
        <?php endforeach; echo '</div>'; ?>
        <div class="legend"><span class="d">Answered</span><span>Not yet</span></div>
        <button class="btn orange" type="button" id="submitBtn2" style="width:100%;margin-top:14px;padding:12px"><i class="fas fa-paper-plane"></i> Review &amp; submit</button>
    </aside>
    </div>
    <div class="gate" id="gate"><div class="box">
        <div class="gi" id="gateI"><i class="fas fa-expand"></i></div>
        <h2 id="gateH"></h2>
        <div class="dots" id="gateDots" hidden><?= str_repeat('<i></i>', $MAX) ?></div>
        <p id="gateP"></p>
        <button class="btn" type="button" id="gateBtn"></button>
    </div></div>
    <div class="modal" id="confirmM"><div class="card">
        <h2 style="margin-top:0;font-size:1.3rem">Submit your practice test?</h2>
        <p class="muted" id="confirmT" style="line-height:1.6;margin:0"></p>
        <div class="chips" id="confirmChips"></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;margin-top:20px"><button class="btn ghost" type="button" id="cancelSub">Keep working</button><button class="btn orange" type="button" id="doSub"><i class="fas fa-paper-plane"></i> Submit</button></div>
    </div></div>
    <div class="toast" id="toast" role="status"></div>
</div>

<!-- ── Results ── -->
<div id="view-done" class="ax-shell">
    <div class="ax-brand"><img src="assets/pwa/icon-192.png" alt=""> SKOPE <span>DIGITAL</span> ACADEMY <em>· Careers</em></div>
    <div class="card state">
        <div class="ic" style="background:#dcfce7;color:var(--ok)"><i class="fas fa-flag-checkered"></i></div>
        <span class="demo-badge"><i class="fas fa-flask"></i> Practice results</span>
        <h1 style="margin-top:10px">Practice complete!</h1>
        <p class="muted" id="doneWhy"></p>
        <div class="res-top"><div><b id="rScore"></b>Multiple choice</div><div><b id="rWords"></b>Words written</div><div><b id="rWarn"></b>Warnings</div></div>
        <div id="rList"></div>
    </div>
    <div class="card">
        <h3><i class="fas fa-graduation-cap"></i> You're ready if you now know…</h3>
        <div class="learned">
            <div><i class="fas fa-circle-check"></i><span>The test runs in full screen, and leaving it, switching tabs or reloading gives a warning. <b>In the real test, <?= $MAX ?> warnings submit it automatically.</b></span></div>
            <div><i class="fas fa-circle-check"></i><span>Copy, paste and right-click don't work during the test.</span></div>
            <div><i class="fas fa-circle-check"></i><span>You can move between questions with the question list, and your answers save automatically.</span></div>
            <div><i class="fas fa-circle-check"></i><span>The real test has about 15 multiple-choice questions written for your position, in 20 minutes, and your score is emailed to you when you finish.</span></div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
            <?php if ($back): ?><a class="btn big" href="<?= $e($back) ?>" style="flex:1"><i class="fas fa-arrow-right"></i> Go to my real assessment</a><?php else: ?><a class="btn" href="careers#openings" style="flex:1"><i class="fas fa-briefcase"></i> View open positions</a><?php endif; ?>
            <button class="btn ghost" type="button" onclick="location.reload()" style="flex:1"><i class="fas fa-rotate"></i> Practise again</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var KEY = <?= json_encode(array_map(fn($q) => ['type' => $q[1], 'prompt' => $q[2], 'opts' => $q[3], 'correct' => $q[4], 'why' => $q[5]], $demo), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var MAX = <?= $MAX ?>, SECS = 300;
    var $ = function (id) { return document.getElementById(id); };
    var qs = Array.prototype.slice.call(document.querySelectorAll('.q')), mapLinks = Array.prototype.slice.call(document.querySelectorAll('.qmap a[data-go]'));
    var running = false, done = false, strikes = 0, lastStrike = 0, end = 0, wasFS = false, fsOK = !!document.documentElement.requestFullscreen, endReason = 'submitted';

    function toast(msg, kind, icon) { var t = $('toast'); t.className = 'toast on ' + (kind || ''); t.innerHTML = '<i class="fas fa-' + (icon || 'circle-info') + '"></i><span></span>'; t.lastChild.textContent = msg; clearTimeout(toast.h); toast.h = setTimeout(function () { t.classList.remove('on'); }, 5500); }
    function inFS() { return !!document.fullscreenElement; }
    function gate(mode, msg) {
        var g = $('gate'), warn = mode === 'warn';
        g.classList.toggle('warn', warn); $('gateDots').hidden = !warn;
        Array.prototype.forEach.call($('gateDots').children, function (d, i) { d.classList.toggle('on', i < strikes); });
        $('gateI').innerHTML = '<i class="fas fa-' + (warn ? 'triangle-exclamation' : 'expand') + '"></i>';
        if (warn) {
            $('gateH').textContent = 'Practice warning ' + Math.min(strikes, MAX) + ' of ' + MAX;
            $('gateP').textContent = strikes >= MAX
                ? msg + '. In the real assessment, your test would now be submitted automatically and you could not continue. In practice mode you can carry on.'
                : msg + '. In the real assessment this is recorded, and at ' + MAX + ' warnings your test is submitted automatically. (Practice mode: nothing was recorded.)';
            $('gateBtn').innerHTML = '<i class="fas fa-arrow-rotate-left"></i> Back to the test';
        } else {
            $('gateH').textContent = 'Ready? Let\'s begin';
            $('gateP').textContent = 'Just like the real assessment, the practice test opens in full screen. Your 5-minute timer is already running.';
            $('gateBtn').innerHTML = '<i class="fas fa-expand"></i> Open full screen & begin';
        }
        g.classList.add('on');
    }
    $('gateBtn').addEventListener('click', function () {
        if (fsOK && !inFS()) document.documentElement.requestFullscreen({ navigationUI: 'hide' }).then(function () { $('gate').classList.remove('on'); }).catch(function () { fsOK = false; $('gate').classList.remove('on'); });
        else $('gate').classList.remove('on');
    });
    function strike(msg) {
        if (!running || done) return;
        var now = Date.now(); if (now - lastStrike < 2500) return; lastStrike = now;
        strikes++; $('strkN').textContent = strikes; $('strk').classList.add('hit');
        gate('warn', msg);
    }
    document.addEventListener('fullscreenchange', function () { if (inFS()) { wasFS = true; return; } if (wasFS) strike('You left full screen'); });
    document.addEventListener('visibilitychange', function () { if (document.hidden) strike('You left the test page'); });
    window.addEventListener('blur', function () { setTimeout(function () { if (running && !document.hidden && !document.hasFocus()) strike('You switched to another window'); }, 400); });

    // Copy/paste blocked, like the real test
    ['copy', 'cut', 'paste'].forEach(function (t) { document.addEventListener(t, function (ev) { if (!running) return; ev.preventDefault(); toast('Copy and paste are switched off during the assessment – just like the real one.', '', 'ban'); }); });
    document.addEventListener('contextmenu', function (ev) { if (running) ev.preventDefault(); });
    document.addEventListener('drop', function (ev) { if (running) ev.preventDefault(); });
    document.addEventListener('selectstart', function (ev) { if (running && !(ev.target.closest && ev.target.closest('textarea'))) ev.preventDefault(); });
    document.addEventListener('keydown', function (ev) {
        var k = (ev.key || '').toLowerCase(), mod = ev.ctrlKey || ev.metaKey;
        if (running && (k === 'f12' || (mod && ['c', 'v', 'x', 'p', 's', 'u'].indexOf(k) > -1))) { ev.preventDefault(); if (['c', 'v', 'x'].indexOf(k) > -1) toast('Copy and paste are switched off during the assessment.', '', 'ban'); }
    });

    // Progress, map, word count, fake autosave
    function isDone(q) { return q.dataset.type === 'mcq' ? !!q.querySelector('input:checked') : q.querySelector('textarea').value.trim().length > 0; }
    function words(t) { t = t.trim(); return t ? t.split(/\s+/).length : 0; }
    function progress() {
        var d = 0;
        qs.forEach(function (q, i) {
            var v = isDone(q); if (v) d++;
            q.classList.toggle('answered', v); q.querySelector('.st').textContent = v ? 'Answered' : 'Not answered';
            mapLinks[i].classList.toggle('done', v);
            if (q.dataset.type !== 'mcq') { var n = words(q.querySelector('textarea').value); q.querySelector('.wcN').textContent = n; q.querySelector('.wcS').classList.toggle('good', n >= 50); }
        });
        $('progBar').style.width = d / qs.length * 100 + '%'; $('mapDone').textContent = d; $('mapN').textContent = d;
        var left = qs.length - d; $('footInfo').textContent = left ? left + ' question' + (left === 1 ? '' : 's') + ' still unanswered.' : 'Every question has an answer. Nice work!';
        return d;
    }
    var saveT;
    function fakeSave() { var s = $('saveState'); s.innerHTML = '<i class="fas fa-rotate fa-spin"></i>Saving…'; clearTimeout(saveT); saveT = setTimeout(function () { var d = new Date(); s.innerHTML = '<i class="fas fa-cloud"></i>Saved ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }, 500); }
    document.addEventListener('change', function () { if (running) { progress(); fakeSave(); } });
    document.querySelectorAll('textarea.w').forEach(function (t) { t.addEventListener('input', progress); t.addEventListener('blur', fakeSave); });
    document.querySelectorAll('[data-clear]').forEach(function (b) { b.addEventListener('click', function () { var c = b.closest('.q').querySelector('input:checked'); if (c) { c.checked = false; progress(); fakeSave(); } }); });
    function go(n) { var q = $('q-' + n); if (!q) return; $('qmap').classList.remove('open'); $('confirmM').classList.remove('on'); q.scrollIntoView({ behavior: 'smooth', block: 'start' }); q.classList.add('flash'); setTimeout(function () { q.classList.remove('flash'); }, 1600); }
    document.addEventListener('click', function (ev) { var a = ev.target.closest && ev.target.closest('[data-go]'); if (a) { ev.preventDefault(); go(a.dataset.go); } });
    $('mapBtn').addEventListener('click', function () { $('qmap').classList.toggle('open'); });

    // Timer
    var told = false;
    function tick() {
        if (!running) return;
        var s = Math.max(0, Math.round((end - Date.now()) / 1000));
        $('timeT').textContent = Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2);
        $('timer').classList.toggle('low', s <= 60);
        if (s <= 60 && s > 0 && !told) { told = true; toast('1 minute left! In the real test you get reminders at 10 and 2 minutes.', 'bad', 'clock'); }
        if (s <= 0) { endReason = 'time_up'; finish(); return; }
        setTimeout(tick, 1000);
    }

    // Submit + results
    function openSubmit() {
        var missing = qs.filter(function (q) { return !isDone(q); });
        $('confirmT').textContent = missing.length ? 'You still have ' + missing.length + ' unanswered question' + (missing.length === 1 ? '' : 's') + '. In the real test, unanswered questions score 0. Tap a number to go back, or submit anyway.' : 'You\'ve answered every question. In the real test you can\'t change answers after submitting.';
        $('confirmChips').innerHTML = missing.map(function (q) { return '<a href="#q-' + q.dataset.n + '" data-go="' + q.dataset.n + '">Question ' + q.dataset.n + '</a>'; }).join('');
        $('qmap').classList.remove('open'); $('confirmM').classList.add('on');
    }
    $('submitBtn').addEventListener('click', openSubmit); $('submitBtn2').addEventListener('click', openSubmit);
    $('cancelSub').addEventListener('click', function () { $('confirmM').classList.remove('on'); });
    $('doSub').addEventListener('click', function () { finish(); });

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function finish() {
        if (done) return; done = true; running = false;
        window.removeEventListener('beforeunload', guard);
        $('confirmM').classList.remove('on'); $('gate').classList.remove('on');
        if (inFS() && document.exitFullscreen) document.exitFullscreen().catch(function () {});
        var right = 0, mcq = 0, w = 0, html = '';
        qs.forEach(function (q, i) {
            var k = KEY[i];
            if (k.type === 'mcq') {
                mcq++;
                var c = q.querySelector('input:checked'), pick = c ? +c.value : null, ok = pick === k.correct; if (ok) right++;
                html += '<div class="res-q"><div class="h"><span>Question ' + (i + 1) + '</span><span class="' + (ok ? 'ok' : 'no') + '">' + (ok ? '✓ Correct' : pick === null ? 'Not answered' : '✗ Not quite') + '</span></div><p>' + esc(k.prompt) + '</p>'
                    + '<div class="a">' + (pick !== null && !ok ? 'Your answer: ' + esc(k.opts[pick]) + '<br>' : '') + '<span class="ok">Best answer: ' + esc(k.opts[k.correct]) + '</span></div><div class="why">' + esc(k.why) + '</div></div>';
            } else {
                w = words(q.querySelector('textarea').value);
                html += '<div class="res-q"><div class="h"><span>Question ' + (i + 1) + ' · written</span><span>' + w + ' words</span></div><p>' + esc(k.prompt) + '</p><div class="why">' + esc(k.why) + '</div></div>';
            }
        });
        $('rScore').textContent = right + ' / ' + mcq; $('rWords').textContent = w; $('rWarn').textContent = strikes;
        $('rList').innerHTML = html;
        $('doneWhy').textContent = (endReason === 'time_up' ? 'Time ran out – in the real test, your saved answers would be submitted automatically, just like this. ' : '') + (strikes ? 'You had ' + strikes + ' practice warning' + (strikes === 1 ? '' : 's') + '. In the real test, try to have none!' : 'No warnings – great, that\'s how to do it in the real test.');
        $('view-test').style.display = 'none'; $('view-done').style.display = 'block'; document.body.classList.remove('testing'); scrollTo(0, 0);
    }
    function guard(ev) { ev.preventDefault(); ev.returnValue = ''; }

    // Start
    $('startDemo').addEventListener('click', function () {
        $('view-intro').style.display = 'none'; $('view-test').style.display = 'block'; document.body.classList.add('testing');
        running = true; end = Date.now() + SECS * 1000; progress(); tick();
        window.addEventListener('beforeunload', guard);
        if (fsOK) document.documentElement.requestFullscreen({ navigationUI: 'hide' }).catch(function () { gate('start'); });
    });
})();
</script>
</body>
</html>
