<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';
$success = '';
$valid_token = false;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Invalid or missing password reset token.";
} else {
    try {
        // Find valid token
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([hash('sha256', $token)]);
        $reset_record = $stmt->fetch();

        if ($reset_record) {
            $valid_token = true;
            $email = $reset_record['email'];
        } else {
            $error = "This password reset token is invalid or has expired. Please request a new one.";
        }
    } catch (Exception $e) {
        $error = "We couldn't check that link. Please try again.";
        error_log($e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (empty($new_pass) || empty($confirm_pass)) {
            $error = 'Please fill in all fields.';
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_pass) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            try {
                // Update password
                $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE email = ?");
                $stmt->execute([$hashed_pass, $email]);

                // Delete token
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                header("Location: login?msg=pw_updated");
                exit;
            } catch (Exception $e) {
                $error = 'Something went wrong updating your password.';
                error_log($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Choose a New Password – Skope Digital Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css?v=3">
    <link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
</head>
<body>
<div class="auth">
    <aside class="auth-brand">
        <a class="auth-logo" href="./">
            <img src="assets/images/Skope Digital  logo.png" alt="">
            <span>SKOPE <em>DIGITAL</em><small>ACADEMY</small></span>
        </a>
        <div class="auth-pitch">
            <h1>Almost there.<br><span>Choose a new password.</span></h1>
            <p>Pick something you haven't used here before. You'll sign in with it straight after.</p>
            <ul class="auth-points">
                <li><i class="fas fa-ruler-horizontal"></i><div><strong>At least 8 characters</strong><span>Longer is stronger. A short sentence works well.</span></div></li>
                <li><i class="fas fa-shuffle"></i><div><strong>Mix it up</strong><span>Combine upper and lower case letters, numbers and symbols.</span></div></li>
                <li><i class="fas fa-user-shield"></i><div><strong>Keep it to yourself</strong><span>Skope staff will never ask for your password.</span></div></li>
            </ul>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
        <?php if (!$valid_token): ?>
            <div class="done">
                <div class="auth-icon" style="color:#b45309;background:#fffbeb"><i class="fas fa-link-slash"></i></div>
                <h2>This link has expired</h2>
                <p><?= htmlspecialchars($error ?: 'This password reset link is no longer valid.') ?> Reset links work once and expire after 1 hour.</p>
                <a class="submit" href="forgot-password"><i class="fas fa-paper-plane"></i> Send a new link</a>
                <div class="auth-foot"><a href="login"><i class="fas fa-arrow-left"></i> Back to sign in</a></div>
            </div>
        <?php else: ?>
            <div class="auth-icon"><i class="fas fa-lock-open"></i></div>
            <h2>Set a new password</h2>
            <p>For <strong><?= htmlspecialchars($email) ?></strong></p>

            <?php if ($error): ?>
                <div class="alert err" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <form action="reset-password?token=<?= htmlspecialchars(urlencode($token)) ?>" method="POST" id="rpForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="field">
                    <div class="field-top"><label for="new_password">New password</label></div>
                    <div class="control">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="new_password" name="new_password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required autofocus>
                        <button type="button" class="peek" data-for="new_password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="meter" id="meter" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                    <div class="hint" id="pwHint">Use 8+ characters with a mix of letters, numbers and symbols.</div>
                </div>
                <div class="field">
                    <div class="field-top"><label for="confirm_password">Confirm new password</label></div>
                    <div class="control">
                        <i class="fas fa-shield-halved"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Type it again" autocomplete="new-password" required>
                        <button type="button" class="peek" data-for="confirm_password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="hint" id="matchHint" aria-live="polite"></div>
                </div>
                <button type="submit" class="submit" id="rpBtn"><i class="fas fa-check"></i> Save new password</button>
            </form>
            <div class="auth-foot"><a href="login"><i class="fas fa-arrow-left"></i> Back to sign in</a></div>
        <?php endif; ?>
        </div>
    </main>
</div>
<script>
(() => {
    const form = document.getElementById('rpForm');
    if (!form) return;
    const pw = document.getElementById('new_password');
    const cf = document.getElementById('confirm_password');
    const meter = document.getElementById('meter');
    const pwHint = document.getElementById('pwHint');
    const matchHint = document.getElementById('matchHint');
    document.querySelectorAll('.peek').forEach((b) => b.addEventListener('click', () => {
        const input = document.getElementById(b.dataset.for);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        b.innerHTML = '<i class="fas ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
    }));
    const strength = (v) => {
        if (!v) return 0;
        let s = v.length >= 8 ? 1 : 0;
        if (v.length >= 12) s++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
        if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) s++;
        return Math.max(1, Math.min(4, s));
    };
    const labels = ['', 'Too weak', 'Okay', 'Good', 'Strong'];
    const check = () => {
        const s = strength(pw.value);
        meter.dataset.s = s;
        if (!pw.value) { pwHint.className = 'hint'; pwHint.textContent = 'Use 8+ characters with a mix of letters, numbers and symbols.'; }
        else if (pw.value.length < 8) { pwHint.className = 'hint bad'; pwHint.textContent = `${8 - pw.value.length} more character${8 - pw.value.length === 1 ? '' : 's'} needed`; }
        else { pwHint.className = 'hint ' + (s >= 3 ? 'good' : 'warn'); pwHint.textContent = 'Strength: ' + labels[s]; }
        if (!cf.value) { matchHint.textContent = ''; cf.classList.remove('bad'); }
        else if (cf.value === pw.value) { matchHint.className = 'hint good'; matchHint.textContent = '✓ Passwords match'; cf.classList.remove('bad'); }
        else { matchHint.className = 'hint bad'; matchHint.textContent = 'Passwords don\'t match yet'; cf.classList.add('bad'); }
    };
    pw.addEventListener('input', check);
    cf.addEventListener('input', check);
    form.addEventListener('submit', (ev) => {
        cf.setCustomValidity(cf.value && cf.value !== pw.value ? 'Passwords do not match' : '');
        if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
        const btn = document.getElementById('rpBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    });
})();
</script>
</body>
</html>
