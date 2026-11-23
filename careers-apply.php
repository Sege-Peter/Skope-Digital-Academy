<?php
/** Public job application form. On success the applicant is emailed a private assessment link. */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/careers.php';
require_once 'includes/mailer.php';
$user = isLoggedIn() ? currentUser() : null;
sda_careers_ensure($pdo);

$jid = (int)($_GET['job'] ?? $_POST['job'] ?? 0);
$st = $pdo->prepare("SELECT * FROM job_vacancies WHERE id = ?");
$st->execute([$jid]);
$job = $st->fetch();
if (!$job) { header('Location: careers'); exit; }
$open = sda_job_open($job);
$qCount = count(sda_ax_questions($pdo, $jid));
$assess = $job['assess_on'] && $qCount > 0;

$errors = [];          // field => message
$old = ['name' => '', 'email' => '', 'phone' => '', 'town' => '', 'cover_letter' => '', 'portfolio_url' => ''];
$done = $_GET['done'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
    foreach ($old as $k => $_) $old[$k] = trim((string)($_POST[$k] ?? ''));
    if (!verifyCsrf()) $errors['form'] = 'This page had been open for a while, so we couldn\'t send it. Please attach your CV again and press Submit.';
    elseif (trim((string)($_POST['website'] ?? '')) !== '') { header('Location: careers-apply?job=' . $jid . '&done=1'); exit; } // bot trap
    elseif (!sda_rate_ok('career_apply', 6, 60)) $errors['form'] = 'We\'ve received several applications from your connection in the last hour. Please try again a little later.';
    else {
        $email = mb_strtolower($old['email']);
        if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > 120) $errors['name'] = 'Please enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors['email'] = 'Please enter a valid email address – your assessment link goes here.';
        if (!preg_match('/^\+?[0-9 ]{9,16}$/', $old['phone'])) $errors['phone'] = 'Please enter a valid phone number, e.g. 0712 345 678.';
        if (mb_strlen($old['cover_letter']) < 150) $errors['cover_letter'] = 'Please write at least 150 characters – a few sentences on why you\'re a good fit.';
        if (mb_strlen($old['cover_letter']) > 6000) $errors['cover_letter'] = 'Please keep your cover letter under 6,000 characters.';
        if ($old['portfolio_url'] !== '' && !(preg_match('#^https?://#i', $old['portfolio_url']) && filter_var($old['portfolio_url'], FILTER_VALIDATE_URL))) $errors['portfolio_url'] = 'Please enter the full link, starting with https://';
        if (empty($_POST['consent']) || empty($_POST['declare'])) $errors['confirm'] = 'Please tick both boxes to continue.';

        // CV: PDF or Word, max 5 MB, checked by content not by name
        $cv = $_FILES['cv'] ?? null;
        $cvExt = null;
        if (!$cv || ($cv['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) $errors['cv'] = 'Please attach your CV (PDF or Word).';
        elseif ($cv['error'] !== UPLOAD_ERR_OK || $cv['size'] > 5 * 1024 * 1024) $errors['cv'] = 'Your CV must be a PDF or Word file under 5 MB.';
        else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($cv['tmp_name']);
            $cvExt = ['application/pdf' => 'pdf', 'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'][$mime] ?? null;
            if (!$cvExt) $errors['cv'] = 'That file isn\'t a PDF or Word document. Please save your CV as PDF and try again.';
        }

        if (!$errors) {
            $ex = $pdo->prepare("SELECT * FROM job_applications WHERE job_id = ? AND email = ?");
            $ex->execute([$jid, $email]);
            if ($prev = $ex->fetch()) {
                // Already applied: resend the link (to the same inbox) if the test hasn't been started.
                if ($assess && !$prev['started_at'] && sda_rate_ok('career_resend', 3, 1440, 'app' . $prev['id'])) sda_app_send_invite($pdo, $prev, $job['title'], (int)$job['assess_minutes']);
                header('Location: careers-apply?job=' . $jid . '&done=' . ($prev['started_at'] ? 'dup' : 'resent'));
                exit;
            }
            $file = bin2hex(random_bytes(16)) . '.' . $cvExt;
            if (!move_uploaded_file($cv['tmp_name'], sda_careers_dir() . '/' . $file)) {
                $errors['cv'] = 'We couldn\'t save your CV. Please try again.';
            } else {
                $origName = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename((string)$cv['name'])), 0, 160);
                $pdo->prepare("INSERT INTO job_applications (job_id, name, email, phone, town, cover_letter, cv_file, cv_name, portfolio_url, ip, user_agent)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$jid, $old['name'], $email, $old['phone'], mb_substr($old['town'], 0, 80) ?: null, $old['cover_letter'], $file, $origName,
                               $old['portfolio_url'] ?: null, sda_client_ip(), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
                $appId = (int)$pdo->lastInsertId();
                sda_app_history($pdo, $appId, 'Application received');
                $app = ['id' => $appId, 'name' => $old['name'], 'email' => $email];
                if ($assess) {
                    sda_app_send_invite($pdo, $app, $job['title'], (int)$job['assess_minutes']);
                } else {
                    sda_app_respond($pdo, $app, 'Application received – ' . $job['title'],
                        "Thank you for applying for the {$job['title']} position at Skope Digital Academy. We have received your application and will review it carefully.\n\nOnly shortlisted candidates will be contacted.");
                }
                $_SESSION['career_applied_email'] = $email;
                header('Location: careers-apply?job=' . $jid . '&done=1');
                exit;
            }
        }
    }
}
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$err = fn($k) => isset($errors[$k]) ? '<div class="ap-ferr"><i class="fas fa-circle-exclamation"></i> ' . $e($errors[$k]) . '</div>' : '';
$bad = fn($k) => isset($errors[$k]) ? ' bad' : '';
$sentTo = $_SESSION['career_applied_email'] ?? '';
$resp = sda_lines($job['responsibilities']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply: <?= $e($job['title']) ?> – Skope Digital Academy</title>
<meta name="robots" content="noindex">
<?php require_once __DIR__ . '/includes/pwa.php'; if (function_exists('sda_pwa_head')) sda_pwa_head(); ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" href="favicon.ico" sizes="any">
<style>
    body { background: #f8fafc; }
    .ap { --c: #00BFFF; --c2: #0080FF; --o: #FF8C00; --ink: #0f172a; --mut: #64748b; --line: #e2e8f0; font-family: 'Inter', sans-serif; color: #334155; padding: 36px 0 80px; }
    .ap h1, .ap h2, .ap h3 { font-family: 'Poppins', sans-serif; color: var(--ink); letter-spacing: -.02em; }
    .ap-wrap { max-width: 1080px; margin: 0 auto; }
    .ap-back { display: inline-flex; gap: 8px; align-items: center; color: #0284c7; font-weight: 700; text-decoration: none; font-size: .9rem; margin-bottom: 16px; }
    .ap-head { background: linear-gradient(135deg, #0b1324, #12305a); color: #cbd5e1; border-radius: 22px; padding: 28px 30px; margin-bottom: 22px; position: relative; overflow: hidden; }
    .ap-head::after { content: ''; position: absolute; width: 300px; height: 300px; right: -90px; top: -120px; border-radius: 50%; background: radial-gradient(circle, rgba(0,191,255,.25), transparent 70%); }
    .ap-head h1 { color: #fff; font-size: clamp(1.5rem, 3.5vw, 2.1rem); margin: 6px 0 10px; }
    .ap-head .k { color: #7dd3fc; font-size: .74rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
    .ap-meta { display: flex; flex-wrap: wrap; gap: 8px; }
    .ap-meta span { font-size: .8rem; font-weight: 700; padding: 6px 12px; border-radius: 99px; background: rgba(255,255,255,.08); color: #e2e8f0; }
    .ap-meta span.dl { background: rgba(255,140,0,.2); color: #fdba74; }
    .ap-steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 20px; position: relative; z-index: 1; }
    .ap-steps div { display: flex; gap: 10px; align-items: center; font-size: .8rem; color: #94a3b8; font-weight: 600; }
    .ap-steps b { flex: 0 0 auto; width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; background: rgba(255,255,255,.1); color: #e2e8f0; font-size: .8rem; }
    .ap-steps div.on { color: #fff; } .ap-steps div.on b { background: var(--o); color: #fff; }
    .ap-grid2 { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 22px; align-items: start; }
    .ap-card { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 28px; }
    .ap-sec { margin: 0 0 22px; padding-bottom: 22px; border-bottom: 1px solid #f1f5f9; }
    .ap-sec:last-of-type { border-bottom: 0; }
    .ap-sec h2 { font-size: 1.02rem; margin: 0 0 14px; display: flex; gap: 10px; align-items: center; }
    .ap-sec h2 span { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; background: #e0f2fe; color: #0284c7; font-size: .8rem; }
    .ap-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
    .ap-f { display: grid; gap: 6px; margin-bottom: 14px; }
    .ap-f label { font-weight: 700; font-size: .86rem; color: var(--ink); }
    .ap-f label small { font-weight: 500; color: var(--mut); }
    .ap-f input, .ap-f textarea { width: 100%; padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 12px; font: inherit; font-size: .95rem; background: #fff; transition: border-color .15s; }
    .ap-f textarea { line-height: 1.6; resize: vertical; }
    .ap-f input:focus, .ap-f textarea:focus { outline: 3px solid rgba(0,191,255,.18); border-color: var(--c); }
    .ap-f.bad input, .ap-f.bad textarea, .ap-drop.bad { border-color: #f87171; background: #fffafa; }
    .ap-f .hint { font-size: .78rem; color: var(--mut); display: flex; justify-content: space-between; gap: 10px; }
    .ap-ferr { font-size: .8rem; color: #b91c1c; font-weight: 600; }
    .cnt.ok { color: #16a34a; font-weight: 700; }
    .ap-drop { position: relative; border: 2px dashed #cbd5e1; border-radius: 16px; padding: 22px; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; background: #fcfdff; }
    .ap-drop:hover, .ap-drop.over { border-color: var(--c); background: #f0f9ff; }
    .ap-drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
    .ap-drop .ic { font-size: 1.7rem; color: var(--c2); margin-bottom: 6px; }
    .ap-drop b { color: var(--ink); } .ap-drop small { display: block; color: var(--mut); font-size: .78rem; margin-top: 4px; }
    .ap-file { display: none; align-items: center; gap: 12px; text-align: left; }
    .ap-drop.has .ap-pick { display: none; } .ap-drop.has .ap-file { display: flex; } .ap-drop.has { border-style: solid; border-color: #86efac; background: #f0fdf4; }
    .ap-file i { font-size: 1.6rem; color: #dc2626; } .ap-file .nm { font-weight: 700; color: var(--ink); overflow-wrap: anywhere; } .ap-file .sz { font-size: .78rem; color: var(--mut); }
    .ap-file .chg { margin-left: auto; font-size: .8rem; font-weight: 700; color: #0284c7; white-space: nowrap; }
    .ap-check { display: flex; gap: 12px; align-items: flex-start; font-size: .88rem; line-height: 1.5; margin-bottom: 10px; cursor: pointer; padding: 12px 14px; border: 1px solid var(--line); border-radius: 12px; }
    .ap-check:has(input:checked) { border-color: #bae6fd; background: #f0f9ff; }
    .ap-check input { margin-top: 2px; width: 19px; height: 19px; flex: 0 0 auto; accent-color: var(--c2); }
    .ap-btn { display: inline-flex; align-items: center; justify-content: center; gap: 9px; padding: 15px 26px; border: 0; border-radius: 12px; font-weight: 800; font-size: 1rem; color: #fff; background: linear-gradient(135deg, var(--c), var(--c2)); cursor: pointer; width: 100%; box-shadow: 0 12px 26px -12px rgba(0,128,255,.7); }
    .ap-btn[disabled] { opacity: .6; cursor: wait; }
    .ap-alert { display: flex; gap: 12px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 14px; padding: 14px 16px; margin-bottom: 20px; font-size: .9rem; line-height: 1.5; }
    .ap-side { position: sticky; top: 90px; display: grid; gap: 16px; }
    .ap-side .ap-card { padding: 22px; }
    .ap-side h3 { font-size: .98rem; margin: 0 0 10px; }
    .ap-side p { font-size: .88rem; line-height: 1.6; color: var(--mut); margin: 0 0 10px; }
    .ap-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; font-size: .86rem; line-height: 1.5; }
    .ap-list li { display: flex; gap: 9px; } .ap-list i { color: var(--c); margin-top: 4px; font-size: .75rem; }
    .ap-next li i { color: var(--o); }
    .ap-done { text-align: center; padding: 44px 28px; max-width: 700px; margin: 0 auto; }
    .ap-done .ic { width: 78px; height: 78px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 18px; background: #dcfce7; color: #16a34a; font-size: 1.9rem; }
    .ap-done h2 { font-size: 1.6rem; margin: 0 0 10px; } .ap-done > p { line-height: 1.7; color: var(--mut); max-width: 540px; margin: 0 auto; }
    .ap-todo { text-align: left; display: grid; gap: 10px; margin: 26px auto 0; max-width: 520px; }
    .ap-todo div { display: flex; gap: 14px; align-items: flex-start; padding: 14px 16px; border: 1px solid var(--line); border-radius: 14px; font-size: .9rem; line-height: 1.5; }
    .ap-todo i { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: #e0f2fe; color: #0284c7; flex: 0 0 auto; }
    .ap-todo b { display: block; color: var(--ink); }
    .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    @media (max-width: 920px) { .ap-grid2 { grid-template-columns: 1fr; } .ap-side { position: static; order: -1; } .ap-side .ap-next { display: none; } }
    @media (max-width: 640px) { .ap-grid { grid-template-columns: 1fr; } .ap-card, .ap-head { padding: 20px; } .ap-steps { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>
<?php require_once 'includes/nav.php'; ?>
<main class="ap"><div class="container ap-wrap">
    <a class="ap-back" href="careers#job-<?= (int)$job['id'] ?>"><i class="fas fa-arrow-left"></i> All positions</a>
    <div class="ap-head">
        <div class="k"><?= $done ? 'Application sent' : 'Apply for this role' ?></div>
        <h1><?= $e($job['title']) ?></h1>
        <div class="ap-meta">
            <?php if ($job['department']): ?><span><i class="fas fa-tag"></i> <?= $e($job['department']) ?></span><?php endif; ?>
            <span><i class="fas fa-briefcase"></i> <?= $e($job['job_type']) ?></span>
            <?php if ($job['location']): ?><span><i class="fas fa-location-dot"></i> <?= $e($job['location']) ?></span><?php endif; ?>
            <?php if ($job['deadline']): ?><span class="dl"><i class="fas fa-calendar-day"></i> Apply by <?= $e(date('j M Y', strtotime($job['deadline']))) ?></span><?php endif; ?>
        </div>
        <div class="ap-steps">
            <div class="<?= $done ? '' : 'on' ?>"><b><?= $done ? '<i class="fas fa-check"></i>' : '1' ?></b>Apply</div>
            <?php if ($assess): ?><div class="<?= $done ? 'on' : '' ?>"><b>2</b>Online assessment</div><div><b>3</b>Evaluation</div><div><b>4</b>Interview</div>
            <?php else: ?><div class="<?= $done ? 'on' : '' ?>"><b>2</b>Review</div><div><b>3</b>Interview</div><?php endif; ?>
        </div>
    </div>

    <?php if ($done): ?>
        <div class="ap-card ap-done">
            <?php if ($done === 'dup'): ?>
                <div class="ic" style="background:#e0f2fe;color:#0284c7"><i class="fas fa-circle-info"></i></div>
                <h2>You've already applied</h2>
                <p>We already have your application for this role, and your assessment has been started or completed. We'll be in touch by email – there's nothing more you need to do.</p>
            <?php else: ?>
                <div class="ic"><i class="fas fa-paper-plane"></i></div>
                <h2><?= $done === 'resent' ? 'We\'ve sent your link again' : 'Application sent – thank you!' ?></h2>
                <p><?= $done === 'resent' ? 'You had already applied for this role, so we\'ve emailed you a fresh assessment link. Earlier links no longer work.' : 'We\'ve received your application for <b>' . $e($job['title']) . '</b>.' ?></p>
                <?php if ($assess): ?>
                <div class="ap-todo">
                    <div><i class="fas fa-envelope-open-text"></i><span><b>Check your email</b>We've sent your private assessment link<?= $sentTo ? ' to <b style="display:inline">' . $e($sentTo) . '</b>' : '' ?>. It can take a few minutes – check your spam or promotions folder too.</span></div>
                    <div><i class="fas fa-laptop"></i><span><b>Plan <?= (int)$job['assess_minutes'] ?> quiet minutes</b>Take it on a laptop or desktop, in one sitting. Your link works for <?= SDA_AX_INVITE_DAYS ?> days, so start whenever you're ready.</span></div>
                    <div><i class="fas fa-flask" style="background:#fff7ed;color:#c2410c"></i><span><b>Practise first (optional)</b>Try the <a href="careers-practice" style="color:#0284c7;font-weight:700">5-minute practice test</a> to get used to the full-screen test and warnings. Nothing is recorded.</span></div>
                    <div><i class="fas fa-rotate"></i><span><b>No email?</b>Fill in the form again with the same email address and we'll resend your link.</span></div>
                </div>
                <?php else: ?>
                <div class="ap-todo"><div><i class="fas fa-envelope-open-text"></i><span><b>Check your email</b>We've sent you a confirmation. Only shortlisted candidates will be contacted.</span></div></div>
                <?php endif; ?>
            <?php endif; ?>
            <p style="margin-top:24px"><a class="ap-back" href="careers"><i class="fas fa-briefcase"></i> Back to careers</a></p>
        </div>
    <?php elseif (!$open): ?>
        <div class="ap-card ap-done"><div class="ic" style="background:#fef3c7;color:#b45309"><i class="fas fa-lock"></i></div><h2>Applications are closed</h2><p>This position is no longer taking applications. Have a look at our <a href="careers">current openings</a>.</p></div>
    <?php else: ?>
    <div class="ap-grid2">
        <form class="ap-card" method="post" enctype="multipart/form-data" id="apForm" novalidate>
            <?php if ($errors): ?><div class="ap-alert" role="alert"><i class="fas fa-triangle-exclamation" style="margin-top:3px"></i><div><b><?= isset($errors['form']) ? $e($errors['form']) : 'Almost there – please fix the highlighted fields.' ?></b><?php if (!isset($errors['form']) && !isset($errors['cv'])): ?><div style="margin-top:4px">For your security, please attach your CV again.</div><?php endif; ?></div></div><?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= $e(csrfToken()) ?>"><input type="hidden" name="job" value="<?= (int)$job['id'] ?>">
            <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <div class="ap-sec"><h2><span>1</span> Your details</h2>
                <div class="ap-grid">
                    <div class="ap-f<?= $bad('name') ?>"><label for="name">Full name</label><input id="name" name="name" required minlength="3" maxlength="120" value="<?= $e($old['name']) ?>" autocomplete="name" placeholder="e.g. Achieng Otieno"><?= $err('name') ?></div>
                    <div class="ap-f<?= $bad('email') ?>"><label for="email">Email address</label><input id="email" type="email" name="email" required maxlength="190" value="<?= $e($old['email']) ?>" autocomplete="email" placeholder="you@example.com"><?= $err('email') ?: '<div class="hint">We send your assessment link here.</div>' ?></div>
                    <div class="ap-f<?= $bad('phone') ?>"><label for="phone">Phone number</label><input id="phone" type="tel" name="phone" required maxlength="16" pattern="\+?[0-9 ]{9,16}" value="<?= $e($old['phone']) ?>" placeholder="0712 345 678" autocomplete="tel" inputmode="tel"><?= $err('phone') ?></div>
                    <div class="ap-f"><label for="town">Town or county <small>(optional)</small></label><input id="town" name="town" maxlength="80" value="<?= $e($old['town']) ?>" placeholder="e.g. Kisumu" autocomplete="address-level2"></div>
                </div>
            </div>

            <div class="ap-sec"><h2><span>2</span> Cover letter</h2>
                <div class="ap-f<?= $bad('cover_letter') ?>"><label for="cover">Why are you a great fit for this role?</label>
                    <textarea id="cover" name="cover_letter" rows="9" required minlength="150" maxlength="6000" placeholder="Tell us about your relevant experience and qualifications, what interests you about this role, and what you would bring to Skope Digital Academy."><?= $e($old['cover_letter']) ?></textarea>
                    <?= $err('cover_letter') ?><div class="hint"><span>Tip: mention one or two achievements you're proud of.</span><span class="cnt" id="cnt"></span></div></div>
            </div>

            <div class="ap-sec"><h2><span>3</span> CV &amp; portfolio</h2>
                <div class="ap-f"><label>Your CV <small>(PDF or Word, up to 5 MB)</small></label>
                    <label class="ap-drop<?= $bad('cv') ?>" id="drop">
                        <input id="cv" type="file" name="cv" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                        <div class="ap-pick"><div class="ic"><i class="fas fa-cloud-arrow-up"></i></div><b>Click to choose your CV</b> or drag it here<small>Include your academic and professional qualifications</small></div>
                        <div class="ap-file"><i class="fas fa-file-pdf" id="fIcon"></i><div><div class="nm" id="fName"></div><div class="sz" id="fSize"></div></div><span class="chg">Change</span></div>
                    </label>
                    <div class="ap-ferr" id="cvErr"<?= isset($errors['cv']) ? '' : ' hidden' ?>><i class="fas fa-circle-exclamation"></i> <span><?= $e($errors['cv'] ?? '') ?></span></div>
                </div>
                <div class="ap-f<?= $bad('portfolio_url') ?>"><label for="pf">Portfolio, LinkedIn or work samples <small>(optional)</small></label><input id="pf" type="url" name="portfolio_url" maxlength="300" value="<?= $e($old['portfolio_url']) ?>" placeholder="https://"><?= $err('portfolio_url') ?></div>
            </div>

            <div class="ap-sec"><h2><span>4</span> Confirm &amp; send</h2>
                <label class="ap-check"><input type="checkbox" name="declare" value="1" required> <span>I have read the <a href="careers-guide" target="_blank" rel="noopener" style="color:#0284c7;font-weight:700">Applicant rules &amp; instructions</a>. The information I've given is true<?= $assess ? ', and I will complete the online assessment myself without help or outside tools' : '' ?>.</span></label>
                <label class="ap-check"><input type="checkbox" name="consent" value="1" required> <span>Skope Digital Academy may store and use my details and documents for this recruitment process.</span></label>
                <?= $err('confirm') ?>
                <button class="ap-btn" id="apBtn" style="margin-top:8px"><i class="fas fa-paper-plane"></i> Send my application</button>
                <p style="font-size:.8rem;color:#64748b;text-align:center;margin:10px 0 0">Takes about 5 minutes. We'll email you straight away.</p>
            </div>
        </form>

        <aside class="ap-side">
            <?php if ($job['deadline']): ?><div class="ap-card" style="background:#fff7ed;border-color:#fed7aa;padding:16px 20px"><b style="color:#9a3412"><i class="fas fa-calendar-xmark"></i> Closes <?= $e(date('l, j F Y', strtotime($job['deadline']))) ?></b><div style="font-size:.84rem;color:#9a3412;margin-top:4px">at 11:59 pm East Africa Time</div></div><?php endif; ?>
            <div class="ap-card">
                <h3>About the role</h3>
                <p><?= $e($job['summary']) ?></p>
                <?php if ($resp): ?><ul class="ap-list"><?php foreach (array_slice($resp, 0, 6) as $l): ?><li><i class="fas fa-circle-check"></i><span><?= $e($l) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
            </div>
            <div class="ap-card ap-next">
                <h3>What happens next</h3>
                <ul class="ap-list">
                    <?php if ($assess): ?>
                    <li><i class="fas fa-1"></i><span>You get an email with a private link to a <b><?= (int)$job['assess_minutes'] ?>-minute online assessment</b> for this role.</span></li>
                    <li><i class="fas fa-2"></i><span>Take it on a laptop, in one sitting, within <?= SDA_AX_INVITE_DAYS ?> days. It's monitored and runs in full screen.</span></li>
                    <li><i class="fas fa-3"></i><span>Our team evaluates every answer, and shortlisted candidates are invited to interview.</span></li>
                    <?php else: ?>
                    <li><i class="fas fa-1"></i><span>Our team reviews your application.</span></li>
                    <li><i class="fas fa-2"></i><span>Shortlisted candidates are invited to interview.</span></li>
                    <?php endif; ?>
                </ul>
                <a href="careers-guide" target="_blank" rel="noopener" style="display:flex;gap:8px;align-items:center;margin-top:14px;font-weight:700;color:#0284c7;text-decoration:none;font-size:.88rem"><i class="fas fa-book-open"></i> Full rules &amp; instructions</a>
                <?php if ($assess): ?><a href="careers-practice" target="_blank" rel="noopener" style="display:flex;gap:8px;align-items:center;margin-top:8px;font-weight:700;color:#c2410c;text-decoration:none;font-size:.88rem"><i class="fas fa-flask"></i> Try the 5-minute practice test</a><?php endif; ?>
            </div>
        </aside>
    </div>
    <?php endif; ?>
</div></main>
<?php require_once 'includes/footer.php'; ?>
<script>
(function () {
    var f = document.getElementById('apForm'); if (!f) return;
    // Cover letter counter
    var c = document.getElementById('cover'), n = document.getElementById('cnt');
    function cnt() { var l = c.value.trim().length; n.textContent = l < 150 ? (150 - l) + ' more characters needed' : 'Looks good · ' + l + ' characters'; n.classList.toggle('ok', l >= 150); }
    c.addEventListener('input', cnt); cnt();
    // CV picker with drag and drop
    var drop = document.getElementById('drop'), inp = document.getElementById('cv'), cvErr = document.getElementById('cvErr');
    function cvMsg(m) { cvErr.hidden = !m; cvErr.querySelector('span').textContent = m || ''; drop.classList.toggle('bad', !!m); }
    function showFile() {
        var file = inp.files[0]; drop.classList.toggle('has', !!file); if (!file) return;
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        document.getElementById('fName').textContent = file.name;
        document.getElementById('fSize').textContent = (file.size / 1024 / 1024 < 0.1 ? Math.max(1, Math.round(file.size / 1024)) + ' KB' : (file.size / 1024 / 1024).toFixed(1) + ' MB');
        document.getElementById('fIcon').className = 'fas fa-file-' + (ext === 'pdf' ? 'pdf' : 'word');
        document.getElementById('fIcon').style.color = ext === 'pdf' ? '#dc2626' : '#2563eb';
        if (['pdf', 'doc', 'docx'].indexOf(ext) < 0) cvMsg('Please choose a PDF or Word document.');
        else if (file.size > 5 * 1024 * 1024) cvMsg('That file is larger than 5 MB. Please save a smaller PDF.');
        else cvMsg('');
    }
    inp.addEventListener('change', showFile);
    ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('over'); }); });
    // Friendly inline checks before sending
    var msgs = { name: 'Please enter your full name.', email: 'Please enter a valid email address – your assessment link goes here.', phone: 'Please enter a valid phone number, e.g. 0712 345 678.', cover: 'Please write at least 150 characters.' };
    function mark(el, m) {
        var box = el.closest('.ap-f'); if (!box) return;
        var old = box.querySelector('.ap-ferr.js'); if (old) old.remove();
        box.classList.toggle('bad', !!m);
        if (m) { var d = document.createElement('div'); d.className = 'ap-ferr js'; d.innerHTML = '<i class="fas fa-circle-exclamation"></i> '; d.appendChild(document.createTextNode(m)); el.insertAdjacentElement('afterend', d); }
    }
    ['name', 'email', 'phone', 'cover'].forEach(function (id) {
        var el = document.getElementById(id);
        el.addEventListener('blur', function () { if (el.value.trim() !== '') mark(el, el.checkValidity() ? '' : msgs[id]); });
        el.addEventListener('input', function () { if (el.closest('.ap-f').classList.contains('bad') && el.checkValidity()) mark(el, ''); });
    });
    f.addEventListener('submit', function (ev) {
        var first = null;
        ['name', 'email', 'phone', 'cover'].forEach(function (id) { var el = document.getElementById(id); var ok = el.checkValidity(); mark(el, ok ? '' : msgs[id]); if (!ok && !first) first = el; });
        if (!inp.files[0]) { cvMsg('Please attach your CV (PDF or Word).'); first = first || drop; } else showFile();
        if (!drop.classList.contains('bad') && inp.files[0]) {} else first = first || drop;
        var boxes = f.querySelectorAll('input[type=checkbox][required]'), unticked = Array.prototype.filter.call(boxes, function (b) { return !b.checked; });
        if (unticked.length) first = first || unticked[0];
        if (first) { ev.preventDefault(); first.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (first.focus) first.focus({ preventScroll: true }); if (unticked.length && first === unticked[0]) first.closest('.ap-check').style.borderColor = '#f87171'; return; }
        var b = document.getElementById('apBtn'); b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending your application…';
    });
})();
</script>
</body>
</html>
