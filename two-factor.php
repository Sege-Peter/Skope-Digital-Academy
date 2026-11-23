<?php
/**
 * Admin sign-in check by email: after the password, a 6-digit code and a one-click link are emailed.
 *  - Enter the code here, or open the link (same browser: signs straight in; another device, e.g. the phone:
 *    this page notices within a few seconds and continues).
 *  - GET ?t=<token>  the emailed link. GET ?poll=1  JSON: has the link been opened? GET ?resend=1  new email.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';
require_once 'includes/security.php';

// The emailed link: works without a session (opened on the phone), approves that sign-in.
if (isset($_GET['t'])) {
    $c = sda_mfa_approve_token($pdo, (string)$_GET['t']);
    $sameBrowser = $c && (int)($_SESSION['user_id'] ?? 0) === (int)$c['user_id'] && (int)($_SESSION['mfa_challenge'] ?? 0) === (int)$c['id'];
    if ($sameBrowser && sda_mfa_link_approved($pdo, (int)$c['id'], (int)$c['user_id'])) {
        // No new session id here: the password step already issued one, and overlapping requests
        // (polling, a double submit) carrying the old id would otherwise sign the admin out.
        $_SESSION['mfa_ok'] = time();
        $_SESSION['mfa_uid'] = (int)$c['user_id'];
        unset($_SESSION['mfa_challenge']);
        $next = (string)($_SESSION['mfa_next'] ?? '');
        unset($_SESSION['mfa_next']);
        header('Location: ' . sda_path(sda_is_local_path($next) && !str_starts_with($next, 'two-factor') ? $next : 'admin/'));
        exit;
    }
    if (!$c && !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin' && sda_mfa_ok()) {
        header('Location: ' . sda_path('admin/'));      // already signed in here: just open the admin panel
        exit;
    }
    $linkState = $c ? 'approved' : sda_mfa_token_state($pdo, (string)$_GET['t']);
}

if (!isset($linkState)) {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') redirectByRole((string)($_SESSION['role'] ?? ''));
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$goNext = function (): never {
    $next = (string)($_SESSION['mfa_next'] ?? '');
    unset($_SESSION['mfa_next'], $_SESSION['mfa_challenge']);
    header('Location: ' . sda_path(sda_is_local_path($next) && !str_starts_with($next, 'two-factor') ? $next : 'admin/'));
    exit;
};
$pass = function () use ($uid, $goNext): never {
    $_SESSION['mfa_ok'] = time();
    $_SESSION['mfa_uid'] = $uid;
    sda_throttle_clear('mfa', 'uid:' . $uid);
    $goNext();
};

if (!isset($linkState)) {
    if (sda_mfa_ok()) {
        if (isset($_GET['poll'])) {                      // another tab or request already finished the check
            header('Content-Type: application/json');
            $next = (string)($_SESSION['mfa_next'] ?? '');
            echo json_encode(['ok' => true, 'go' => sda_path(sda_is_local_path($next) && !str_starts_with($next, 'two-factor') ? $next : 'admin/')]);
            exit;
        }
        $goNext();
    }

    $st = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $st->execute([$uid]);
    $me = $st->fetch();
    if (!$me) { logoutUser(); header('Location: login'); exit; }
    $cid = (int)($_SESSION['mfa_challenge'] ?? 0);

    // Polling from this page: has the emailed link been opened on another device?
    if (isset($_GET['poll'])) {
        header('Content-Type: application/json');
        if ($cid && sda_mfa_link_approved($pdo, $cid, $uid)) {
            $_SESSION['mfa_ok'] = time();
            $_SESSION['mfa_uid'] = $uid;
            $next = (string)($_SESSION['mfa_next'] ?? '');
            unset($_SESSION['mfa_next'], $_SESSION['mfa_challenge']);
            echo json_encode(['ok' => true, 'go' => sda_path(sda_is_local_path($next) && !str_starts_with($next, 'two-factor') ? $next : 'admin/')]);
        } else {
            echo json_encode(['ok' => false]);
        }
        exit;
    }

    $error = '';
    $notice = '';
    // Send (first visit, or "send a new code")
    if (!$cid || isset($_GET['resend'])) {
        if (!sda_rate_ok('mfa_send', 5, 15, 'uid:' . $uid)) {
            $error = 'Too many codes requested. Please wait 15 minutes, then try again.';
        } else {
            [$cid, $sendErr] = sda_mfa_send($pdo, $me);
            $_SESSION['mfa_challenge'] = $cid;
            if ($sendErr !== '') $error = 'We could not send the sign-in email (' . $sendErr . '). Check the academy mailbox connection, then tap "Resend code".';
            elseif (isset($_GET['resend'])) $notice = 'A new code is on its way. Codes from earlier emails no longer work.';
        }
        if (isset($_GET['resend']) && !$error) { $_SESSION['mfa_notice'] = $notice; header('Location: two-factor'); exit; }
    }
    if (!empty($_SESSION['mfa_notice'])) { $notice = $_SESSION['mfa_notice']; unset($_SESSION['mfa_notice']); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrf()) {
            $error = 'Your session expired. Please try again.';
        } elseif (!sda_rate_ok('mfa', 8, 15, 'uid:' . $uid)) {
            $error = 'Too many attempts. Please wait 15 minutes and try again.';
        } elseif ($cid && sda_mfa_check_code($pdo, $cid, $uid, (string)($_POST['code'] ?? ''))) {
            $pass();
        } else {
            $error = 'That code is not right or has expired. Check the newest email, or tap "Resend code".';
        }
    }

    // Show where the code went without revealing the whole address
    [$local, $domain] = array_pad(explode('@', (string)$me['email'], 2), 2, '');
    $masked = mb_substr($local, 0, 2) . str_repeat('•', min(5, max(1, mb_strlen($local) - 2))) . '@' . $domain;

    // Timers for the page: how long the code still works, and when "Resend" unlocks (30 s after sending)
    $expiresIn = 0; $resendIn = 0; $triesLeft = SDA_MFA_MAX_TRIES;
    if ($cid) {
        $t = $pdo->prepare("SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS left_s,
                                   GREATEST(0, 30 - TIMESTAMPDIFF(SECOND, created_at, NOW())) AS resend_s, attempts
                            FROM mfa_challenges WHERE id = ? AND user_id = ? AND used_at IS NULL");
        $t->execute([$cid, $uid]);
        if ($row = $t->fetch()) { $expiresIn = (int)$row['left_s']; $resendIn = (int)$row['resend_s']; $triesLeft = max(0, SDA_MFA_MAX_TRIES - (int)$row['attempts']); }
    }
    if ($error === '' && $cid && $expiresIn === 0 && $_SERVER['REQUEST_METHOD'] !== 'POST') $error = 'This code has expired. Tap "Resend code" for a new one.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title><?= isset($linkState) ? ($linkState === 'approved' ? 'Sign-in confirmed' : ($linkState === 'used' ? 'Already confirmed' : 'Link no longer works')) : 'Check your email' ?> – Skope Digital Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php require_once __DIR__ . '/includes/pwa.php'; sda_pwa_head(); ?>
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --cyan: #00BFFF; --blue: #0080FF; --orange: #FF8C00; --ok: #10b981; --bad: #dc2626; }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #334155; min-height: 100vh;
               display: flex; flex-direction: column; align-items: center; justify-content: center;
               padding: max(24px, env(safe-area-inset-top)) 16px max(24px, env(safe-area-inset-bottom));
               background: radial-gradient(900px 500px at 10% -10%, rgba(0,191,255,.18), transparent 60%),
                           radial-gradient(800px 500px at 110% 110%, rgba(255,140,0,.14), transparent 60%), #f4f7fb; }
        .tf-brand { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; margin-bottom: 18px; }
        .tf-brand img { width: 40px; height: 40px; border-radius: 11px; background: #fff; box-shadow: 0 6px 16px -8px rgba(15,23,42,.35); }
        .tf-brand span { font: 800 1rem Poppins, sans-serif; color: var(--ink); letter-spacing: .2px; }
        .tf-brand em { font-style: normal; color: var(--orange); }
        .tf-card { width: 100%; max-width: 440px; background: #fff; border-radius: 24px; overflow: hidden;
                   box-shadow: 0 30px 60px -30px rgba(15,23,42,.35), 0 0 0 1px rgba(15,23,42,.04); }
        .tf-bar { height: 4px; background: linear-gradient(90deg, var(--cyan), var(--blue) 55%, var(--orange)); }
        .tf-body { padding: 30px 28px 26px; text-align: center; }
        .tf-icon { position: relative; width: 74px; height: 74px; margin: 0 auto 16px; border-radius: 22px; display: grid; place-items: center;
                   background: linear-gradient(135deg, var(--cyan), var(--blue)); color: #fff; font-size: 1.7rem; box-shadow: 0 16px 30px -14px rgba(0,128,255,.7); }
        .tf-icon::after { content: ''; position: absolute; inset: -8px; border-radius: 28px; border: 2px solid rgba(0,191,255,.35); animation: tfRing 2s ease-out infinite; }
        .tf-icon.ok { background: linear-gradient(135deg, #34d399, var(--ok)); box-shadow: 0 16px 30px -14px rgba(16,185,129,.7); }
        .tf-icon.warn { background: linear-gradient(135deg, #fb923c, #ea580c); box-shadow: 0 16px 30px -14px rgba(234,88,12,.6); }
        .tf-icon.ok::after, .tf-icon.warn::after { display: none; }
        @keyframes tfRing { 0% { transform: scale(.9); opacity: .9 } 100% { transform: scale(1.25); opacity: 0 } }
        h1 { font: 800 1.5rem/1.25 Poppins, sans-serif; color: var(--ink); margin: 0 0 8px; }
        .tf-lead { margin: 0 auto 6px; max-width: 340px; line-height: 1.6; font-size: .95rem; color: var(--muted); }
        .tf-to { display: inline-flex; align-items: center; gap: 8px; margin: 6px 0 20px; padding: 7px 12px; border-radius: 99px; background: #f1f5f9; color: var(--ink); font-weight: 600; font-size: .9rem; max-width: 100%; }
        .tf-to i { color: var(--blue); }
        .tf-to span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tf-alert { display: flex; gap: 10px; align-items: flex-start; text-align: left; padding: 12px 14px; border-radius: 12px; margin: 0 0 16px; font-size: .9rem; line-height: 1.5; }
        .tf-alert.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .tf-alert.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .tf-alert i { margin-top: 3px; }
        .tf-otp { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 8px; margin: 0 auto 8px; max-width: 360px; }
        .tf-otp input { width: 100%; aspect-ratio: 5 / 6; min-height: 48px; border: 2px solid var(--line); border-radius: 14px; background: #f8fafc;
                        text-align: center; font: 700 1.6rem 'JetBrains Mono', ui-monospace, monospace; color: var(--ink); outline: none; transition: border-color .15s, box-shadow .15s, background .15s; caret-color: var(--blue); padding: 0; }
        .tf-otp input:focus { border-color: var(--blue); background: #fff; box-shadow: 0 0 0 4px rgba(0,128,255,.14); }
        .tf-otp input.filled { border-color: #93c5fd; background: #fff; }
        .tf-otp.bad input { border-color: #fca5a5; background: #fff7f7; }
        .tf-otp.shake { animation: tfShake .4s; }
        @keyframes tfShake { 20%, 60% { transform: translateX(-7px) } 40%, 80% { transform: translateX(7px) } }
        .tf-meta { display: flex; justify-content: space-between; gap: 10px; max-width: 360px; margin: 0 auto 18px; font-size: .8rem; color: var(--muted); }
        .tf-meta b { color: var(--ink); font-variant-numeric: tabular-nums; }
        .tf-meta .low b { color: var(--bad); }
        .tf-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 52px; border: 0; border-radius: 14px; cursor: pointer;
                  background: linear-gradient(135deg, var(--cyan), var(--blue)); color: #fff; font: 700 1rem Inter, sans-serif; box-shadow: 0 14px 26px -14px rgba(0,128,255,.8); transition: transform .15s, opacity .15s; text-decoration: none; }
        .tf-btn:hover { transform: translateY(-1px); }
        .tf-btn:disabled { opacity: .55; cursor: default; transform: none; }
        .tf-wait { display: flex; align-items: center; justify-content: center; gap: 10px; margin: 16px 0 0; padding: 11px 14px; border-radius: 12px; background: #f0f9ff; color: #0369a1; font-size: .86rem; }
        .tf-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--cyan); animation: tfPulse 1.2s infinite; flex: none; }
        @keyframes tfPulse { 0%, 100% { opacity: .35; transform: scale(.8) } 50% { opacity: 1; transform: scale(1.2) } }
        .tf-actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .tf-actions a { color: var(--blue); text-decoration: none; font: 600 .88rem Inter, sans-serif; display: inline-flex; gap: 7px; align-items: center; }
        .tf-actions .dim { color: var(--muted); }
        .tf-actions [aria-disabled="true"] { color: #94a3b8; pointer-events: none; }
        .tf-tips { margin: 14px 0 0; font-size: .8rem; color: #94a3b8; line-height: 1.55; }
        .tf-foot { margin-top: 18px; font-size: .78rem; color: #94a3b8; text-align: center; }
        .tf-foot b { color: var(--muted); }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
        @media (max-width: 400px) { .tf-body { padding: 26px 18px 22px; } .tf-otp { gap: 6px; } .tf-otp input { font-size: 1.35rem; border-radius: 12px; } h1 { font-size: 1.3rem; } }
        @media (prefers-reduced-motion: reduce) { .tf-icon::after, .tf-dot, .tf-otp.shake { animation: none; } }
    </style>
</head>
<body>
    <a class="tf-brand" href="./"><img src="assets/pwa/icon-192.png" alt=""><span>SKOPE <em>DIGITAL</em> ACADEMY</span></a>

    <main class="tf-card">
        <div class="tf-bar"></div>
        <div class="tf-body">
        <?php if (isset($linkState)): ?>
            <?php if ($linkState === 'approved'): ?>
                <div class="tf-icon ok"><i class="fas fa-check"></i></div>
                <h1>Sign-in confirmed</h1>
                <p class="tf-lead">Go back to the screen where you were signing in. It continues on its own within a few seconds, and you can close this page.</p>
            <?php elseif ($linkState === 'used'): ?>
                <div class="tf-icon ok"><i class="fas fa-circle-check"></i></div>
                <h1>Already confirmed</h1>
                <p class="tf-lead">This sign-in was already finished, with the code or this link. Each link works once. If you're not in the admin panel yet, sign in again.</p>
                <a class="tf-btn" href="admin/" style="margin-top:18px"><i class="fas fa-gauge-high"></i> Open the admin panel</a>
            <?php elseif ($linkState === 'superseded'): ?>
                <div class="tf-icon warn"><i class="fas fa-envelopes-bulk"></i></div>
                <h1>A newer email was sent</h1>
                <p class="tf-lead">Tapping "Resend code" replaces earlier emails, so this link no longer works. Open the <b>newest</b> "Your sign-in code" email and use its code or link.</p>
                <a class="tf-btn" href="two-factor" style="margin-top:18px"><i class="fas fa-arrow-left"></i> Back to the code screen</a>
            <?php else: ?>
                <div class="tf-icon warn"><i class="fas fa-link-slash"></i></div>
                <h1><?= $linkState === 'expired' ? 'This link has expired' : 'This link is not valid' ?></h1>
                <p class="tf-lead">Sign-in links work once and only for <?= SDA_MFA_TTL_MIN ?> minutes. Sign in again and we'll email you a new one.</p>
                <a class="tf-btn" href="login" style="margin-top:18px"><i class="fas fa-right-to-bracket"></i> Sign in again</a>
            <?php endif; ?>

        <?php else: ?>
            <div class="tf-icon"><i class="fas fa-envelope-open-text"></i></div>
            <h1>Check your email</h1>
            <p class="tf-lead">To keep the admin panel safe, enter the 6-digit code we sent, or tap the link in the email.</p>
            <div class="tf-to" title="Sent to the email address on your admin account"><i class="fas fa-at"></i><span><?= e($masked) ?></span></div>

            <?php if ($error): ?><div class="tf-alert err" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= e($error) ?></span></div><?php endif; ?>
            <?php if ($notice): ?><div class="tf-alert ok" role="status"><i class="fas fa-paper-plane"></i><span><?= e($notice) ?></span></div><?php endif; ?>

            <form method="post" id="tfForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="code" id="tfCode">
                <label class="sr-only" for="d0">6-digit sign-in code</label>
                <div class="tf-otp<?= $error && $_SERVER['REQUEST_METHOD'] === 'POST' ? ' bad shake' : '' ?>" id="tfOtp">
                    <?php for ($d = 0; $d < 6; $d++): ?>
                        <input id="d<?= $d ?>" inputmode="numeric" pattern="[0-9]*" maxlength="6" aria-label="Digit <?= $d + 1 ?>" <?= $d === 0 ? 'autocomplete="one-time-code" autofocus' : 'autocomplete="off"' ?>>
                    <?php endfor; ?>
                </div>
                <div class="tf-meta">
                    <span id="tfExp"<?= $expiresIn < 120 ? ' class="low"' : '' ?>><?php if ($expiresIn > 0): ?>Code expires in <b id="tfExpT"><?= sprintf('%d:%02d', intdiv($expiresIn, 60), $expiresIn % 60) ?></b><?php else: ?><b>Code expired</b><?php endif; ?></span>
                    <span><?= (int)$triesLeft ?> tr<?= $triesLeft === 1 ? 'y' : 'ies' ?> left</span>
                </div>
                <button class="tf-btn" id="tfBtn" disabled><i class="fas fa-shield-halved"></i> Confirm sign-in</button>
            </form>

            <div class="tf-wait" aria-live="polite"><span class="tf-dot"></span><span>Waiting… opening the email link on your phone works too.</span></div>

            <div class="tf-actions">
                <a href="two-factor?resend=1" id="tfResend"<?= $resendIn > 0 ? ' aria-disabled="true"' : '' ?>><i class="fas fa-rotate-right"></i> <span id="tfResendT"><?= $resendIn > 0 ? 'Resend in 0:' . sprintf('%02d', $resendIn) : 'Resend code' ?></span></a>
                <a href="logout" class="dim"><i class="fas fa-arrow-right-from-bracket"></i> Use another account</a>
            </div>
            <p class="tf-tips">Can't find it? Check Spam or Promotions. Each code and link works once.</p>
        <?php endif; ?>
        </div>
    </main>
    <p class="tf-foot">Protected sign-in · <b>Skope Digital Academy</b></p>

<?php if (!isset($linkState)): ?>
<script>
(function () {
    var boxes = [].slice.call(document.querySelectorAll('#tfOtp input')), code = document.getElementById('tfCode'),
        form = document.getElementById('tfForm'), btn = document.getElementById('tfBtn'), otp = document.getElementById('tfOtp'), sent = false;
    function sync() {
        var v = boxes.map(function (b) { return b.value; }).join('');
        code.value = v;
        boxes.forEach(function (b) { b.classList.toggle('filled', b.value !== ''); });
        btn.disabled = v.length !== 6;
        if (v.length === 6 && !sent) { sent = true; btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Checking…'; form.submit(); }
    }
    function fill(from, digits) {
        for (var i = 0; i < digits.length && from + i < 6; i++) boxes[from + i].value = digits[i];
        boxes[Math.min(5, from + digits.length)].focus();
        sync();
    }
    boxes.forEach(function (b, i) {
        b.addEventListener('input', function () {
            var d = b.value.replace(/\D/g, '');
            if (d.length > 1) { b.value = ''; fill(i, d); return; }   // phone autofill / paste lands in one box
            b.value = d;
            if (d && i < 5) boxes[i + 1].focus();
            otp.classList.remove('bad');
            sync();
        });
        b.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !b.value && i > 0) { boxes[i - 1].value = ''; boxes[i - 1].focus(); sync(); e.preventDefault(); }
            if (e.key === 'ArrowLeft' && i > 0) boxes[i - 1].focus();
            if (e.key === 'ArrowRight' && i < 5) boxes[i + 1].focus();
        });
        b.addEventListener('paste', function (e) {
            var t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            if (t) { e.preventDefault(); fill(0, t.slice(0, 6)); }
        });
        b.addEventListener('focus', function () { b.select(); });
    });
    form.addEventListener('submit', function (e) { if (code.value.length !== 6) e.preventDefault(); });

    // Countdowns: code expiry and the resend wait
    var exp = <?= (int)$expiresIn ?>, rs = <?= (int)$resendIn ?>, expT = document.getElementById('tfExpT'), expBox = document.getElementById('tfExp'),
        rLink = document.getElementById('tfResend'), rT = document.getElementById('tfResendT');
    setInterval(function () {
        if (exp > 0) {
            exp--;
            if (expT) expT.textContent = Math.floor(exp / 60) + ':' + ('0' + exp % 60).slice(-2);
            if (exp < 120) expBox.classList.add('low');
            if (exp === 0) expBox.innerHTML = '<b>Code expired</b> · tap Resend code';
        }
        if (rs > 0) {
            rs--;
            rT.textContent = rs > 0 ? 'Resend in 0:' + ('0' + rs).slice(-2) : 'Resend code';
            if (rs === 0) rLink.removeAttribute('aria-disabled');
        }
    }, 1000);

    // The emailed link opened on another device: continue here
    var n = 0, timer = setInterval(function () {
        if (++n > 220) return clearInterval(timer);
        fetch('two-factor?poll=1', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); }).then(function (d) { if (d && d.ok) { clearInterval(timer); location.href = d.go; } }).catch(function () {});
    }, 3000);
})();
</script>
<?php endif; ?>
</body>
</html>
