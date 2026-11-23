<?php
/** Applicant rules & instructions for the recruitment process (linked from careers, the form, the invite email and the test). */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/careers.php';
$user = isLoggedIn() ? currentUser() : null;
$jobs = [];
try { sda_careers_ensure($pdo); $jobs = sda_open_vacancies($pdo); } catch (Throwable $e) { error_log('careers-guide: ' . $e->getMessage()); }
$dls = array_filter(array_column($jobs, 'deadline'));
$close = $dls ? max($dls) : null;
$closeTxt = $close ? date('l, j F Y', strtotime($close)) : null;
$mins = $jobs ? (int)max(array_column($jobs, 'assess_minutes')) : 90;
$email = sda_careers_email();
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$toc = ['dates' => 'Key dates', 'apply' => 'Application rules', 'process' => 'How selection works', 'before' => 'Before the assessment', 'during' => 'During the assessment', 'warnings' => 'Warnings & auto-submit', 'integrity' => 'Honesty & integrity', 'problems' => 'Technical problems', 'scoring' => 'Scoring & shortlisting', 'support' => 'Adjustments, privacy & contact'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Applicant Rules &amp; Instructions – Skope Digital Academy Careers</title>
<?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Applicant Rules & Instructions', 'path' => 'careers-guide', 'image' => 'assets/images/promo/careers.jpg', 'description' => 'Everything applicants need to know: deadline, application rules, the online assessment, integrity rules, scoring and what happens next.', 'breadcrumbs' => [['Careers', 'careers'], ['Applicant guide', 'careers-guide']]]); ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" href="favicon.ico" sizes="any">
<style>
    body { background: #f8fafc; }
    .g { --c: #00BFFF; --c2: #0080FF; --o: #FF8C00; --ink: #0f172a; --mut: #64748b; --line: #e2e8f0; font-family: 'Inter', sans-serif; color: #334155; }
    .g h1, .g h2, .g h3 { font-family: 'Poppins', sans-serif; color: var(--ink); letter-spacing: -.02em; }
    .g-hero { background: linear-gradient(135deg, #0b1324, #12305a); color: #cbd5e1; padding: 56px 0 64px; position: relative; overflow: hidden; }
    .g-hero::after { content: ''; position: absolute; width: 420px; height: 420px; right: -120px; top: -170px; border-radius: 50%; background: radial-gradient(circle, rgba(0,191,255,.25), transparent 70%); }
    .g-hero h1 { color: #fff; font-size: clamp(1.9rem, 4.6vw, 2.9rem); margin: 12px 0 12px; }
    .g-hero p { max-width: 640px; line-height: 1.7; font-size: 1.04rem; margin: 0; }
    .g-kick { display: inline-flex; gap: 8px; align-items: center; color: #7dd3fc; font-size: .74rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
    .g-close { display: inline-flex; gap: 12px; align-items: center; margin-top: 24px; background: rgba(255,140,0,.15); border: 1px solid rgba(255,140,0,.4); color: #fed7aa; padding: 12px 18px; border-radius: 14px; font-weight: 700; position: relative; z-index: 1; }
    .g-close i { color: var(--o); font-size: 1.2rem; }
    .g-close b { color: #fff; }
    .g-acts { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; position: relative; z-index: 1; }
    .g-btn { display: inline-flex; gap: 9px; align-items: center; padding: 12px 20px; border-radius: 12px; font-weight: 800; font-size: .9rem; text-decoration: none; border: 0; cursor: pointer; font-family: inherit; }
    .g-btn.pri { background: linear-gradient(135deg, var(--c), var(--c2)); color: #fff; }
    .g-btn.ghost { background: rgba(255,255,255,.08); color: #e2e8f0; border: 1px solid rgba(255,255,255,.15); }
    .g-body { display: grid; grid-template-columns: 250px minmax(0, 1fr); gap: 32px; align-items: start; padding: 40px 0 80px; }
    .g-toc { position: sticky; top: 92px; background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 18px; }
    .g-toc b { display: block; font: 800 .8rem 'Inter', sans-serif; text-transform: uppercase; letter-spacing: .08em; color: var(--mut); margin-bottom: 10px; }
    .g-toc a { display: flex; gap: 10px; padding: 7px 8px; border-radius: 9px; text-decoration: none; color: #334155; font-size: .88rem; font-weight: 600; }
    .g-toc a span { color: var(--c2); font-weight: 800; min-width: 18px; }
    .g-toc a:hover { background: #f0f9ff; color: #0284c7; }
    .g-sec { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 28px 30px; margin-bottom: 18px; scroll-margin-top: 90px; }
    .g-sec h2 { font-size: 1.3rem; margin: 0 0 6px; display: flex; gap: 12px; align-items: center; }
    .g-sec h2 .n { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: #e0f2fe; color: #0284c7; font-size: .9rem; flex: 0 0 auto; }
    .g-sec > p.lead { color: var(--mut); margin: 0 0 16px; line-height: 1.65; }
    .g-sec h3 { font-size: 1rem; margin: 20px 0 10px; }
    .g-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
    .g-list li { display: flex; gap: 12px; line-height: 1.6; font-size: .94rem; }
    .g-list li > i { color: var(--c2); margin-top: 5px; width: 16px; text-align: center; flex: 0 0 auto; font-size: .85rem; }
    .g-list.no li > i { color: #dc2626; }
    .g-list.ok li > i { color: #16a34a; }
    .g-list b { color: var(--ink); }
    .g-dates { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
    .g-dates div { border: 1px solid var(--line); border-radius: 16px; padding: 16px; background: #fcfdff; }
    .g-dates i { color: var(--o); font-size: 1.2rem; margin-bottom: 8px; display: block; }
    .g-dates b { display: block; color: var(--ink); font-size: 1rem; margin-bottom: 4px; } .g-dates span { font-size: .84rem; color: var(--mut); line-height: 1.5; }
    .g-steps { counter-reset: s; display: grid; gap: 12px; }
    .g-step { display: grid; grid-template-columns: 44px 1fr; gap: 14px; align-items: start; }
    .g-step::before { counter-increment: s; content: counter(s); width: 44px; height: 44px; border-radius: 13px; display: grid; place-items: center; font: 800 1rem 'Poppins', sans-serif; color: #fff; background: linear-gradient(135deg, var(--c), var(--c2)); }
    .g-step b { display: block; color: var(--ink); margin: 2px 0 2px; } .g-step span { font-size: .9rem; color: var(--mut); line-height: 1.6; }
    .g-table { width: 100%; border-collapse: collapse; font-size: .9rem; margin-top: 6px; }
    .g-table th, .g-table td { text-align: left; padding: 11px 12px; border-bottom: 1px solid var(--line); vertical-align: top; line-height: 1.5; }
    .g-table th { font-size: .74rem; text-transform: uppercase; letter-spacing: .06em; color: var(--mut); background: #f8fafc; }
    .g-tag { display: inline-block; font-size: .74rem; font-weight: 800; padding: 3px 9px; border-radius: 99px; white-space: nowrap; }
    .g-tag.w { background: #fff7ed; color: #c2410c; } .g-tag.l { background: #f1f5f9; color: #475569; } .g-tag.ok { background: #dcfce7; color: #15803d; }
    .g-box { display: flex; gap: 14px; padding: 16px 18px; border-radius: 14px; margin-top: 16px; font-size: .92rem; line-height: 1.6; }
    .g-box i { margin-top: 4px; font-size: 1.05rem; }
    .g-box.red { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; } .g-box.red i { color: #dc2626; }
    .g-box.blue { background: #f0f9ff; border: 1px solid #bae6fd; color: #0c4a6e; } .g-box.blue i { color: #0284c7; }
    .g-box.amber { background: #fffbeb; border: 1px solid #fde68a; color: #78350f; } .g-box.amber i { color: #d97706; }
    .g-bands { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 8px; }
    .g-bands div { border-radius: 14px; padding: 14px; font-size: .82rem; line-height: 1.45; }
    .g-bands b { display: block; font-size: 1.1rem; }
    .g-end { text-align: center; padding: 34px 24px; background: linear-gradient(135deg, #0b1324, #12305a); color: #cbd5e1; border-radius: 22px; }
    .g-end h2 { color: #fff; margin: 0 0 8px; }
    @media (max-width: 960px) { .g-body { grid-template-columns: 1fr; } .g-toc { position: static; } .g-toc nav { display: grid; grid-template-columns: 1fr 1fr; } }
    @media (max-width: 640px) { .g-sec { padding: 20px; } .g-dates, .g-bands { grid-template-columns: 1fr 1fr; } .g-toc nav { grid-template-columns: 1fr; } .g-table { font-size: .84rem; } .g-table th:nth-child(3), .g-table td:nth-child(3) { display: none; } }
    @media print { .g-toc, .g-acts, nav, footer, .navbar, .mobile-menu { display: none !important; } .g-body { grid-template-columns: 1fr; } .g-sec { break-inside: avoid; border: 0; padding: 10px 0; } .g-hero { background: #fff; color: #000; padding: 10px 0; } .g-hero h1 { color: #000; } }
</style>
</head>
<body>
<?php require_once 'includes/nav.php'; ?>
<main class="g">
<section class="g-hero"><div class="container" style="position:relative;z-index:1">
    <a href="careers" class="g-kick" style="text-decoration:none"><i class="fas fa-arrow-left"></i> Careers</a>
    <h1>Applicant Rules &amp; Instructions</h1>
    <p>Please read this guide carefully before you apply and before you start your online assessment. Every applicant follows the same rules, so the process is fair for everyone.</p>
    <?php if ($closeTxt): ?><div class="g-close"><i class="fas fa-calendar-xmark"></i><span>Applications close on <b><?= $e($closeTxt) ?></b> at 11:59 pm (East Africa Time)</span></div><?php endif; ?>
    <div class="g-acts">
        <a href="careers#openings" class="g-btn pri"><i class="fas fa-briefcase"></i> View open positions</a>
        <a href="careers-practice" class="g-btn ghost"><i class="fas fa-flask"></i> Try the practice test</a>
        <button type="button" class="g-btn ghost" onclick="window.print()"><i class="fas fa-print"></i> Print or save as PDF</button>
    </div>
</div></section>

<div class="container g-body">
    <aside class="g-toc"><b>On this page</b><nav>
        <?php $i = 0; foreach ($toc as $id => $t): $i++; ?><a href="#<?= $id ?>"><span><?= $i ?></span><?= $e($t) ?></a><?php endforeach; ?>
    </nav></aside>

    <div>
        <section class="g-sec" id="dates">
            <h2><span class="n">1</span> Key dates</h2>
            <p class="lead">Plan ahead – late applications and expired assessment links can't be accepted.</p>
            <div class="g-dates">
                <div><i class="fas fa-calendar-xmark"></i><b><?= $close ? $e(date('j M Y', strtotime($close))) : 'See each role' ?></b><span>Applications close at 11:59 pm EAT. The form closes automatically after this.</span></div>
                <div><i class="fas fa-hourglass-half"></i><b><?= SDA_AX_INVITE_DAYS ?> days</b><span>to start your online assessment after you apply. The link expires after that.</span></div>
                <div><i class="fas fa-stopwatch"></i><b><?= $mins ?> minutes</b><span>to complete the assessment, in one sitting, once you press Start.</span></div>
            </div>
            <div class="g-box amber"><i class="fas fa-lightbulb"></i><span><b>Don't leave it to the last day.</b> Apply early so you have time to take the assessment calmly and to sort out any technical problem with our team.</span></div>
        </section>

        <section class="g-sec" id="apply">
            <h2><span class="n">2</span> Application rules</h2>
            <p class="lead">These apply to every position.</p>
            <ul class="g-list">
                <li><i class="fas fa-globe"></i><span><b>Apply online only.</b> Use the "Apply for this role" button on the Careers page. Applications sent by post, WhatsApp or hand delivery are not considered.</span></li>
                <li><i class="fas fa-layer-group"></i><span><b>One application per position.</b> You may apply for more than one position, but submit a separate application for each and complete each position's own assessment. If you apply twice for the same position with the same email, we simply resend your assessment link.</span></li>
                <li><i class="fas fa-envelope"></i><span><b>Use your own email address and phone number</b> that you check often. Your assessment link and all our updates go to that email.</span></li>
                <li><i class="fas fa-file-lines"></i><span><b>Required:</b> an up-to-date CV (PDF or Word, up to 5 MB) listing your academic and professional qualifications, and a cover letter written in the form (at least 150 characters) explaining why you suit the position. <b>Optional:</b> a link to your portfolio, LinkedIn profile or work samples.</span></li>
                <li><i class="fas fa-id-card"></i><span><b>Certificates:</b> you don't need to upload them now. Shortlisted candidates must bring original academic and professional certificates and a national ID to the interview.</span></li>
                <li><i class="fas fa-circle-check"></i><span><b>Your information must be true and complete.</b> False information, forged documents or impersonation lead to disqualification at any stage – even after an offer has been made.</span></li>
            </ul>
            <div class="g-box red"><i class="fas fa-shield-halved"></i><span><b>Recruitment is free.</b> Skope Digital Academy never charges any fee to apply, take the assessment, be interviewed or be hired, and never asks for money via M-Pesa or otherwise. If anyone asks you for money in our name, do not pay – report it to <a href="mailto:<?= $e($email) ?>"><?= $e($email) ?></a>. <b>Canvassing</b> (seeking influence from staff or others) leads to automatic disqualification.</span></div>
        </section>

        <section class="g-sec" id="process">
            <h2><span class="n">3</span> How selection works</h2>
            <div class="g-steps">
                <div class="g-step"><div><b>Apply online</b><span>Fill in the form, attach your CV and confirm the declarations. You'll see a confirmation straight away.</span></div></div>
                <div class="g-step"><div><b>Receive your assessment link by email</b><span>Usually within minutes. Check your spam or promotions folder. The link is private to you – don't share it.</span></div></div>
                <div class="g-step"><div><b>Take the online assessment</b><span>A timed, <?= $mins ?>-minute test made for your position, with four parts: knowledge, practical problem-solving, situational judgement and professional competency (100 marks in total).</span></div></div>
                <div class="g-step"><div><b>Evaluation</b><span>Multiple-choice answers are marked automatically. Written answers are marked by our recruitment panel against a marking guide.</span></div></div>
                <div class="g-step"><div><b>Shortlisting &amp; interview</b><span>Shortlisted candidates are invited to an interview by email with the date, time and venue or video link. Only shortlisted candidates will be contacted.</span></div></div>
                <div class="g-step"><div><b>Decision</b><span>The successful candidate receives an offer by email, followed by an offer letter.</span></div></div>
            </div>
        </section>

        <section class="g-sec" id="before">
            <h2><span class="n">4</span> Before the assessment</h2>
            <p class="lead">Set yourself up properly – most problems happen because of the device or connection.</p>
            <ul class="g-list ok">
                <li><i class="fas fa-laptop"></i><span><b>Use a laptop or desktop computer</b>, plugged in or fully charged. A phone works, but it's much harder to type the written answers, and switching apps on a phone counts as a warning.</span></li>
                <li><i class="fab fa-chrome"></i><span><b>Use an up-to-date browser:</b> Google Chrome, Microsoft Edge or Mozilla Firefox. Allow the page to go full screen when asked.</span></li>
                <li><i class="fas fa-wifi"></i><span><b>Have a stable internet connection</b> and enough data or bundles for <?= $mins ?> minutes.</span></li>
                <li><i class="fas fa-volume-xmark"></i><span><b>Find a quiet place</b> where you won't be disturbed for <?= $mins ?> minutes.</span></li>
                <li><i class="fas fa-bell-slash"></i><span><b>Close all other tabs and apps, and turn off notifications</b> (WhatsApp, email, chat, antivirus pop-ups). A pop-up that takes focus away from the test can count as a warning.</span></li>
                <li><i class="fas fa-flask"></i><span><b>Take the free practice test first.</b> The <a href="careers-practice">5-minute practice test</a> works exactly like the real one – full screen, timer, warnings – but nothing is recorded. Try it as many times as you like.</span></li>
                <li><i class="fas fa-book-open"></i><span><b>Read the start page carefully.</b> The timer begins only when you tick the agreement box and press Start, so you can read everything first.</span></li>
            </ul>
        </section>

        <section class="g-sec" id="during">
            <h2><span class="n">5</span> During the assessment</h2>
            <ul class="g-list">
                <li><i class="fas fa-expand"></i><span><b>Full screen:</b> the test opens in full screen. Stay in full screen until you submit.</span></li>
                <li><i class="fas fa-table-cells"></i><span><b>Move freely:</b> answer the questions in any order. The question list shows which ones you've answered; click a number to jump to it.</span></li>
                <li><i class="fas fa-list-ol"></i><span><b>Multiple choice:</b> choose the one best answer. You can change it or clear it at any time before you submit. Unanswered questions score 0 – there's no penalty for guessing.</span></li>
                <li><i class="fas fa-pen"></i><span><b>Written tasks:</b> type your answer in your own words. Each task shows a suggested length and a word count. Clear structure (short paragraphs or bullet points) and practical, realistic ideas earn the most marks.</span></li>
                <li><i class="fas fa-cloud"></i><span><b>Autosave:</b> your answers save automatically every few seconds; the top bar shows when they were last saved.</span></li>
                <li><i class="fas fa-clock"></i><span><b>Timer:</b> shown at the top. You'll get reminders at 10 and 2 minutes left. When time runs out, everything you've saved is submitted automatically.</span></li>
                <li><i class="fas fa-paper-plane"></i><span><b>Submitting:</b> press "Review &amp; submit". You'll see any unanswered questions before you confirm. After submitting you cannot change your answers or retake the test.</span></li>
            </ul>
        </section>

        <section class="g-sec" id="warnings">
            <h2><span class="n">6</span> Warnings &amp; auto-submit</h2>
            <p class="lead">The assessment is monitored to keep it fair. Some actions give you a <b>warning</b>; others are blocked and <b>recorded</b> for the recruitment panel.</p>
            <div style="overflow-x:auto"><table class="g-table">
                <thead><tr><th>Action</th><th>Result</th><th>Why</th></tr></thead>
                <tbody>
                    <tr><td>Switching to another tab, window or app</td><td><span class="g-tag w">Warning</span></td><td>You may be looking up answers</td></tr>
                    <tr><td>Leaving full screen</td><td><span class="g-tag w">Warning</span></td><td>Other content may be visible</td></tr>
                    <tr><td>Minimising the browser, or a pop-up taking focus</td><td><span class="g-tag w">Warning</span></td><td>The test is no longer in view</td></tr>
                    <tr><td>Reloading, closing and reopening the test page</td><td><span class="g-tag w">Warning</span></td><td>Prevents restarting or resetting</td></tr>
                    <tr><td>Opening your link on another device or browser</td><td><span class="g-tag w">Warning</span> &amp; refused</td><td>The test belongs to the device you started on</td></tr>
                    <tr><td>Copy, cut, paste, right-click, drag and drop</td><td><span class="g-tag l">Blocked &amp; recorded</span></td><td>Answers must be typed in your own words</td></tr>
                    <tr><td>Print, save page, developer tools shortcuts</td><td><span class="g-tag l">Blocked &amp; recorded</span></td><td>Protects the questions</td></tr>
                    <tr><td>A large block of text appearing at once</td><td><span class="g-tag l">Recorded</span></td><td>May indicate pasted or generated text</td></tr>
                    <tr><td>Staying on the page and typing your answers</td><td><span class="g-tag ok">Fine</span></td><td>That's all you need to do</td></tr>
                </tbody>
            </table></div>
            <div class="g-box red"><i class="fas fa-triangle-exclamation"></i><span><b>At <?= SDA_AX_MAX_STRIKES ?> warnings, your assessment is submitted automatically</b> with whatever you've saved so far, and you can't continue. Each warning is shown on screen so you always know where you stand. Your name and email appear faintly across the test screen.</span></div>
        </section>

        <section class="g-sec" id="integrity">
            <h2><span class="n">7</span> Honesty &amp; integrity</h2>
            <ul class="g-list no">
                <li><i class="fas fa-xmark"></i><span>No help from any other person – in the room, by phone or online.</span></li>
                <li><i class="fas fa-xmark"></i><span>No AI tools (such as ChatGPT, Gemini or Copilot), search engines, notes or textbooks.</span></li>
                <li><i class="fas fa-xmark"></i><span>No one else may take the assessment for you.</span></li>
                <li><i class="fas fa-xmark"></i><span>Don't copy, photograph, record or share the questions, during or after the test.</span></li>
                <li><i class="fas fa-xmark"></i><span>Don't use a second device to look things up.</span></li>
            </ul>
            <div class="g-box blue"><i class="fas fa-magnifying-glass"></i><span>The panel reads every written answer and reviews the test record. Answers that appear copied, generated by AI or identical to another applicant's, or a record showing serious breaches, can lead to <b>disqualification</b>, even with a high score. At interview you may be asked to explain or expand on your answers.</span></div>
        </section>

        <section class="g-sec" id="problems">
            <h2><span class="n">8</span> Technical problems</h2>
            <ul class="g-list">
                <li><i class="fas fa-plug-circle-xmark"></i><span><b>Power cut or lost connection:</b> your saved answers are safe. Reopen your link <b>on the same device and browser</b> as soon as possible. Reopening counts as a warning, and the timer keeps running while you're away.</span></li>
                <li><i class="fas fa-wifi"></i><span><b>"Offline – retrying"</b> in the top bar means your latest answers haven't reached us yet. Stay on the page; they're sent automatically when you're back online.</span></li>
                <li><i class="fas fa-link-slash"></i><span><b>Link not working or expired:</b> use the most recent email from us; each new link replaces the old one. If you haven't started, submit the application form again with the same email to get a new link.</span></li>
                <li><i class="fas fa-envelope-circle-check"></i><span><b>Report problems straight away</b> to <a href="mailto:<?= $e($email) ?>"><?= $e($email) ?></a> with your name, the position, the time it happened and what you saw (a photo of the screen helps). Genuine technical failures are reviewed, and a retake may be granted at the academy's discretion.</span></li>
            </ul>
        </section>

        <section class="g-sec" id="scoring">
            <h2><span class="n">9</span> Scoring &amp; shortlisting</h2>
            <p class="lead">Your final score is out of 100%. Multiple-choice answers are marked automatically; written tasks are marked by the panel against the same guide for everyone.</p>
            <div class="g-bands">
                <div style="background:#dcfce7;color:#14532d"><b>80–100%</b>Strongly recommended for shortlisting</div>
                <div style="background:#e0f2fe;color:#0c4a6e"><b>70–79%</b>Recommended</div>
                <div style="background:#fef3c7;color:#78350f"><b>60–69%</b>Further review</div>
                <div style="background:#fee2e2;color:#7f1d1d"><b>Below 60%</b>Not shortlisted automatically</div>
            </div>
            <ul class="g-list" style="margin-top:16px">
                <li><i class="fas fa-scale-balanced"></i><span>The score is important, but it isn't the only factor. Shortlisting also considers your CV, qualifications, cover letter, written work and the integrity record. The interview decides the final outcome.</span></li>
                <li><i class="fas fa-envelope"></i><span>Decisions are sent by email. The panel's decisions are final, and we can't share the questions or marking guides.</span></li>
            </ul>
        </section>

        <section class="g-sec" id="support">
            <h2><span class="n">10</span> Adjustments, privacy &amp; contact</h2>
            <ul class="g-list">
                <li><i class="fas fa-universal-access"></i><span><b>Need an adjustment?</b> If you have a disability or condition that affects how you take a timed online test (for example, you need extra time or use assistive technology), email us <b>before</b> you start the assessment so we can discuss reasonable arrangements.</span></li>
                <li><i class="fas fa-user-shield"></i><span><b>Your data:</b> your details, documents, answers and test record are used only for this recruitment process. They're stored securely and seen only by the recruitment team.</span></li>
                <li><i class="fas fa-circle-question"></i><span><b>Questions?</b> Email <a href="mailto:<?= $e($email) ?>"><?= $e($email) ?></a> and include the position title in the subject. We can't give hints about assessment content.</span></li>
            </ul>
        </section>

        <div class="g-end">
            <h2>Ready to apply?</h2>
            <p style="max-width:560px;margin:0 auto 20px;line-height:1.7">By applying, you agree to follow these rules. Good luck – we look forward to learning about you.</p>
            <a href="careers#openings" class="g-btn pri"><i class="fas fa-briefcase"></i> See open positions</a>
        </div>
    </div>
</div>
</main>
<?php require_once 'includes/footer.php'; ?>
</body>
</html>
