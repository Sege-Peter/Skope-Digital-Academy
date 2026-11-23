<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        require_once __DIR__ . '/includes/security.php';

        if (empty($email)) {
            $error = 'Please enter your email address.';
        } elseif (!sda_rate_ok('reset_ip', 10, 60) || !sda_rate_ok('reset_email', 3, 60, $email)) {
            // Stops reset-email flooding; the generic message keeps account existence private.
            $error = 'Too many reset requests. Please wait an hour and try again, or check your inbox and spam folder for an earlier link.';
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Generate secure reset token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Invalidate previous tokens for this email (optional, or just insert new one)
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                    // Store token
                    // Only a hash of the token is stored; the plain token exists only in the email link.
                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$email, hash('sha256', $token), $expires_at]);

                    // Send Email
                    // Build the link from the configured site URL, never from the request's Host header.
                    $site = rtrim(setting('site_url'), '/');
                    if ($site === '') {
                        $site = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://localhost' . rtrim(sda_base_href(), '/');
                    }
                    $reset_link = $site . '/reset-password?token=' . $token;
                    
                    require_once __DIR__ . '/includes/mailer.php';
                    sda_mail($email, 'Reset your Skope Digital Academy password',
                        '<h2 style="margin:0 0 12px;color:#0f172a">Reset your password</h2>'
                        . '<p>Hello <strong>' . htmlspecialchars($user['name']) . '</strong>,</p>'
                        . '<p>We received a request to reset the password on your account. Click the button below to choose a new one.</p>'
                        . sda_mail_button($reset_link, 'Reset password')
                        . '<p style="color:#64748b;font-size:13px">This link expires in 1 hour. If you did not ask for this, you can ignore this email; your password stays the same.</p>');
                }

                // Show success message regardless of existence to prevent email scraping
                $success = 'If an account exists with that email, a password reset link has been sent.';
                
            } catch (Exception $e) {
                $error = 'Something went wrong. Please try again later.';
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
    <title>Forgot Password – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Forgot Password', 'path' => 'forgot-password', 'noindex' => true]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css?v=9">
    <link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
</head>
<body>
<div class="auth">
    <aside class="auth-brand">
        <a class="auth-logo" href="./">
            <img src="assets/images/skope-logo-512.png" alt="">
            <span>SKOPE <em>DIGITAL</em><small>ACADEMY</small></span>
        </a>
        <div class="auth-pitch">
            <h1>Locked out?<br><span>It happens.</span></h1>
            <p>Enter your email and we'll send you a secure link to choose a new password. Your courses and progress stay exactly where they are.</p>
            <ul class="auth-points">
                <li><i class="fas fa-envelope-open-text"></i><div><strong>Check your inbox</strong><span>The link arrives within a few minutes. Look in spam too.</span></div></li>
                <li><i class="fas fa-hourglass-half"></i><div><strong>Valid for 1 hour</strong><span>Each link works once and expires after an hour.</span></div></li>
                <li><i class="fas fa-headset"></i><div><strong>Still stuck?</strong><span>Our team can help from the contact page.</span></div></li>
            </ul>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
        <?php if ($success): ?>
            <div class="done">
                <div class="auth-icon"><i class="fas fa-paper-plane"></i></div>
                <h2>Check your email</h2>
                <p><?= htmlspecialchars($success) ?> The link expires in 1 hour. Didn't get it? Check your spam folder, or try again in a few minutes.</p>
                <a class="submit" href="login"><i class="fas fa-arrow-left"></i> Back to sign in</a>
                <div class="auth-foot"><a href="forgot-password">Use a different email</a> · <a href="contact?topic=Technical%20Support">Contact support</a></div>
            </div>
        <?php else: ?>
            <div class="auth-icon"><i class="fas fa-key"></i></div>
            <h2>Forgot your password?</h2>
            <p>Enter the email address on your account and we'll email you a reset link.</p>

            <?php if ($error): ?>
                <div class="alert err" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <form action="forgot-password" method="POST" id="fpForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="field">
                    <div class="field-top"><label for="email">Email address</label></div>
                    <div class="control">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                </div>
                <button type="submit" class="submit" id="fpBtn"><i class="fas fa-paper-plane"></i> Send reset link</button>
            </form>

            <div class="auth-switch">Remembered it? <a href="login">Sign in</a></div>
            <div class="auth-foot"><a href="./"><i class="fas fa-arrow-left"></i> Back to homepage</a></div>
        <?php endif; ?>
        </div>
    </main>
</div>
<script>
(() => {
    const form = document.getElementById('fpForm');
    if (!form) return;
    form.addEventListener('submit', (ev) => {
        if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
        const btn = document.getElementById('fpBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
    });
})();
</script>
</body>
</html>
