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

        if (empty($email)) {
            $error = 'Please enter your email address.';
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
                    
                    $subject = "Skope Digital Academy - Password Reset";
                    $message = "
                    <html>
                    <head><title>Password Reset Request</title></head>
                    <body style='font-family: Inter, sans-serif; background: #f8fafc; padding: 30px;'>
                        <div style='background: white; padding: 40px; border-radius: 12px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                            <h2 style='color: #0f172a;'>Password Reset Request</h2>
                            <p style='color: #475569; line-height: 1.6;'>Hello <strong>".htmlspecialchars($user['name'])."</strong>,</p>
                            <p style='color: #475569; line-height: 1.6;'>We received a request to reset your password. Click the secure link below to proceed:</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='$reset_link' style='background: #00BFFF; color: white; padding: 14px 28px; text-decoration: none; border-radius: 12px; font-weight: 800; font-family: Poppins, sans-serif;'>RESET PASSWORD</a>
                            </div>
                            <p style='color: #64748b; font-size: 13px;'>If you did not request a password reset, you can safely ignore this email.</p>
                            <p style='color: #64748b; font-size: 13px;'>This link will expire in 1 hour.</p>
                        </div>
                    </body>
                    </html>
                    ";

                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: Skope Support <support@skopedigital.com>" . "\r\n";

                    @mail($email, $subject, $message, $headers);
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
