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

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'town' => '', 'cover_letter' => '', 'portfolio_url' => ''];
$done = $_GET['done'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
    foreach ($old as $k => $_) $old[$k] = trim((string)($_POST[$k] ?? ''));
    if (!verifyCsrf()) $errors[] = 'Your session expired. Please submit the form again.';
    elseif (trim((string)($_POST['website'] ?? '')) !== '') { header('Location: careers-apply?job=' . $jid . '&done=1'); exit; } // bot trap
    elseif (!sda_rate_ok('career_apply', 6, 60)) $errors[] = 'Too many applications from your connection. Please try again in an hour.';
    else {
        $email = mb_strtolower($old['email']);
        if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > 120) $errors[] = 'Enter your full name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors[] = 'Enter a valid email address.';
        if (!preg_match('/^\+?[0-9 ]{9,16}$/', $old['phone'])) $errors[] = 'Enter a valid phone number, e.g. 0712 345 678.';
        if (mb_strlen($old['cover_letter']) < 150) $errors[] = 'Your cover letter should be at least 150 characters (a few sentences on why you fit this role).';
        if (mb_strlen($old['cover_letter']) > 6000) $errors[] = 'Please keep the cover letter under 6,000 characters.';
        if ($old['portfolio_url'] !== '' && !(preg_match('#^https?://#i', $old['portfolio_url']) && filter_var($old['portfolio_url'], FILTER_VALIDATE_URL))) $errors[] = 'The portfolio link must be a full web address starting with https://';
        if (empty($_POST['consent']) || empty($_POST['declare'])) $errors[] = 'Please tick both confirmations at the bottom of the form.';

        // CV: PDF or Word, max 5 MB, checked by content not by name
        $cv = $_FILES['cv'] ?? null;
        $cvExt = null;
        if (!$cv || ($cv['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) $errors[] = 'Attach your CV (PDF or Word).';
        elseif ($cv['error'] !== UPLOAD_ERR_OK || $cv['size'] > 5 * 1024 * 1024) $errors[] = 'Your CV must be a PDF or Word file under 5 MB.';
        else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($cv['tmp_name']);
            $cvExt = ['application/pdf' => 'pdf', 'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'][$mime] ?? null;
            if (!$cvExt) $errors[] = 'Your CV must be a PDF or Word document.';
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
                $errors[] = 'We could not save your CV. Please try again.';
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
                header('Location: careers-apply?job=' . $jid . '&done=1');
                exit;
            }
        }
    }
}
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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
    .ap { font-family: 'Inter', sans-serif; color: #334155; padding: 48px 0 80px; }
    .ap h1, .ap h2 { font-family: 'Poppins', sans-serif; color: #0f172a; letter-spacing: -.02em; }
    .ap-wrap { max-width: 820px; margin: 0 auto; }
    .ap-back { display: inline-flex; gap: 8px; align-items: center; color: #0284c7; font-weight: 700; text-decoration: none; font-size: .9rem; margin-bottom: 18px; }
    .ap-head { background: linear-gradient(135deg, #0b1324, #12305a); color: #cbd5e1; border-radius: 22px; padding: 28px; margin-bottom: 22px; }
    .ap-head h1 { color: #fff; font-size: clamp(1.5rem, 3.5vw, 2rem); margin: 6px 0 8px; }
    .ap-head .k { color: #7dd3fc; font-size: .75rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
    .ap-steps { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
    .ap-steps span { font-size: .78rem; font-weight: 700; padding: 6px 12px; border-radius: 99px; background: rgba(255,255,255,.08); color: #e2e8f0; }
    .ap-steps span b { color: #FF8C00; }
    .ap-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 22px; padding: 28px; }
    .ap-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .ap-f { display: grid; gap: 6px; margin-bottom: 16px; }
    .ap-f label { font-weight: 700; font-size: .86rem; color: #0f172a; }
    .ap-f label small { font-weight: 500; color: #64748b; }
    .ap-f input, .ap-f textarea { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 12px; font: inherit; background: #fff; }
    .ap-f input:focus, .ap-f textarea:focus { outline: 3px solid rgba(0,191,255,.2); border-color: #00BFFF; }
    .ap-f .hint { font-size: .78rem; color: #64748b; }
    .ap-check { display: flex; gap: 10px; align-items: flex-start; font-size: .86rem; line-height: 1.5; margin-bottom: 12px; }
    .ap-check input { margin-top: 3px; width: 18px; height: 18px; flex: 0 0 auto; accent-color: #0080FF; }
    .ap-btn { display: inline-flex; align-items: center; gap: 9px; padding: 14px 24px; border: 0; border-radius: 12px; font-weight: 800; font-size: .95rem; color: #fff; background: linear-gradient(135deg, #00BFFF, #0080FF); cursor: pointer; }
    .ap-btn[disabled] { opacity: .6; cursor: wait; }
    .ap-err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 14px; padding: 14px 16px; margin-bottom: 18px; font-size: .9rem; }
    .ap-err ul { margin: 6px 0 0 18px; }
    .ap-done { text-align: center; padding: 40px 28px; }
    .ap-done .ic { width: 70px; height: 70px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 16px; background: #dcfce7; color: #16a34a; font-size: 1.8rem; }
    .ap-rules { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 14px; padding: 14px 16px; font-size: .86rem; line-height: 1.6; margin-bottom: 18px; }
    .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    @media (max-width: 640px) { .ap-grid { grid-template-columns: 1fr; gap: 0; } .ap-card, .ap-head { padding: 20px; } .ap-btn { width: 100%; justify-content: center; } }
</style>
</head>
<body>
<?php require_once 'includes/nav.php'; ?>
<main class="ap"><div class="container ap-wrap">
    <a class="ap-back" href="careers#job-<?= (int)$job['id'] ?>"><i class="fas fa-arrow-left"></i> All positions</a>
    <div class="ap-head">
        <div class="k">Application</div>
        <h1><?= $e($job['title']) ?></h1>
        <div><?= $e($job['job_type']) ?><?= $job['location'] ? ' · ' . $e($job['location']) : '' ?><?= $job['deadline'] ? ' · Apply by ' . $e(date('j M Y', strtotime($job['deadline']))) : '' ?></div>
        <div class="ap-steps"><span><b>1</b> Apply</span><?php if ($assess): ?><span><b>2</b> Online assessment (<?= (int)$job['assess_minutes'] ?> min)</span><span><b>3</b> Evaluation</span><span><b>4</b> Interview</span><?php else: ?><span><b>2</b> Review</span><span><b>3</b> Interview</span><?php endif; ?></div>
    </div>

    <?php if ($done): ?>
        <div class="ap-card ap-done">
            <?php if ($done === 'dup'): ?>
                <div class="ic" style="background:#e0f2fe;color:#0284c7"><i class="fas fa-circle-info"></i></div>
                <h2>You've already applied</h2>
                <p style="max-width:520px;margin:10px auto 0;line-height:1.7">We already have your application for this role and your assessment has been started. We'll be in touch by email.</p>
            <?php else: ?>
                <div class="ic"><i class="fas fa-paper-plane"></i></div>
                <h2><?= $done === 'resent' ? 'We\'ve resent your link' : 'Application received!' ?></h2>
                <p style="max-width:540px;margin:10px auto 0;line-height:1.7">
                <?php if ($assess): ?>
                    Check your email (and the spam folder) for a message with your <b>private assessment link</b>. It takes <?= (int)$job['assess_minutes'] ?> minutes in one sitting, works on one device, and expires in <?= SDA_AX_INVITE_DAYS ?> days.
                <?php else: ?>
                    Thank you. We've emailed you a confirmation and will review your application. Only shortlisted candidates will be contacted.
                <?php endif; ?></p>
            <?php endif; ?>
            <p style="margin-top:22px"><a class="ap-back" href="careers"><i class="fas fa-briefcase"></i> Back to careers</a></p>
        </div>
    <?php elseif (!$open): ?>
        <div class="ap-card ap-done"><div class="ic" style="background:#fef3c7;color:#b45309"><i class="fas fa-lock"></i></div><h2>Applications are closed</h2><p>This position is no longer accepting applications. See <a href="careers">current openings</a>.</p></div>
    <?php else: ?>
        <form class="ap-card" method="post" enctype="multipart/form-data" id="apForm" novalidate>
            <?php if ($errors): ?><div class="ap-err" role="alert"><b><i class="fas fa-triangle-exclamation"></i> Please fix the following:</b><ul><?php foreach ($errors as $er): ?><li><?= $e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= $e(csrfToken()) ?>"><input type="hidden" name="job" value="<?= (int)$job['id'] ?>">
            <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="ap-grid">
                <div class="ap-f"><label for="name">Full name</label><input id="name" name="name" required maxlength="120" value="<?= $e($old['name']) ?>" autocomplete="name"></div>
                <div class="ap-f"><label for="email">Email <small>(your assessment link is sent here)</small></label><input id="email" type="email" name="email" required maxlength="190" value="<?= $e($old['email']) ?>" autocomplete="email"></div>
                <div class="ap-f"><label for="phone">Phone</label><input id="phone" type="tel" name="phone" required maxlength="16" value="<?= $e($old['phone']) ?>" placeholder="0712 345 678" autocomplete="tel"></div>
                <div class="ap-f"><label for="town">Town / county <small>(optional)</small></label><input id="town" name="town" maxlength="80" value="<?= $e($old['town']) ?>" placeholder="e.g. Kisumu"></div>
            </div>
            <div class="ap-f"><label for="cover">Cover letter</label><textarea id="cover" name="cover_letter" rows="8" required minlength="150" maxlength="6000" placeholder="Why this role? Your relevant experience, qualifications and what you would bring to the academy."><?= $e($old['cover_letter']) ?></textarea><div class="hint"><span id="cvCount">0</span> characters · at least 150</div></div>
            <div class="ap-grid">
                <div class="ap-f"><label for="cv">CV <small>(PDF or Word, max 5 MB)</small></label><input id="cv" type="file" name="cv" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"><div class="hint">Include your academic and professional qualifications.</div></div>
                <div class="ap-f"><label for="pf">Portfolio or LinkedIn <small>(optional)</small></label><input id="pf" type="url" name="portfolio_url" maxlength="300" value="<?= $e($old['portfolio_url']) ?>" placeholder="https://"></div>
            </div>
            <?php if ($assess): ?>
            <div class="ap-rules"><b><i class="fas fa-shield-halved"></i> About the assessment.</b> After you apply, we email you a private link to a <?= (int)$job['assess_minutes'] ?>-minute online assessment for this role (knowledge, practical tasks, situational judgement and professional competency). It is timed, must be completed by you alone in one sitting, and is monitored: switching tabs or windows, leaving full screen or reloading are recorded, and after <?= SDA_AX_MAX_STRIKES ?> warnings it submits automatically.</div>
            <?php endif; ?>
            <label class="ap-check"><input type="checkbox" name="declare" value="1" required> <span>I confirm the information I've given is true<?= $assess ? ', and I will complete the assessment myself without help or outside tools' : '' ?>.</span></label>
            <label class="ap-check"><input type="checkbox" name="consent" value="1" required> <span>I agree that Skope Digital Academy may store and use my details and documents for this recruitment process.</span></label>
            <button class="ap-btn" id="apBtn"><i class="fas fa-paper-plane"></i> Submit application</button>
        </form>
    <?php endif; ?>
</div></main>
<?php require_once 'includes/footer.php'; ?>
<script>
(function () {
    var f = document.getElementById('apForm'); if (!f) return;
    var c = document.getElementById('cover'), n = document.getElementById('cvCount');
    var up = function () { n.textContent = c.value.length; }; c.addEventListener('input', up); up();
    f.addEventListener('submit', function (ev) {
        if (!f.checkValidity()) { ev.preventDefault(); f.reportValidity(); return; }
        var file = document.getElementById('cv').files[0];
        if (file && file.size > 5 * 1024 * 1024) { ev.preventDefault(); alert('Your CV must be under 5 MB.'); return; }
        var b = document.getElementById('apBtn'); b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
    });
})();
</script>
</body>
</html>
