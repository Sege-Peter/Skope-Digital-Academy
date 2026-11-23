<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';
if (!empty($_SESSION['google_error'])) { $error = (string)$_SESSION['google_error']; unset($_SESSION['google_error']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'suspended') {
                        $error = 'Your account has been suspended. Please contact support.';
                    } elseif ($user['status'] === 'pending') {
                        if ($user['role'] === 'student') {
                            loginUser($user);
                            header("Location: checkout?type=registration");
                            exit;
                        } else {
                            $error = 'Your tutor account is pending admin approval. Please check back later.';
                        }
                    } else {
                        if (!empty($user['requires_password_change'])) {
                            $_SESSION['force_password_change_uid'] = $user['id'];
                            header("Location: force_password_change");
                            exit;
                        }
                        // Streak Logic calculation
                        $today = date('Y-m-d');
                        $last_streak = $user['last_streak_date'] ?? null;
                        $streak_days = $user['streak_days'] ?? 0;
                        if ($last_streak !== $today) {
                            if ($last_streak === date('Y-m-d', strtotime('-1 day'))) {
                                $streak_days++; // Continues consecutive days
                            } else {
                                $streak_days = 1; // Resets if more than 1 day missed
                            }
                            $pdo->prepare("UPDATE users SET last_login = NOW(), streak_days = ?, last_streak_date = ? WHERE id = ?")->execute([$streak_days, $today, $user['id']]);
                        } else {
                            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        }
                        
                        loginUser($user);
                        redirectByRole($user['role']);
                    }
                } else {
                    $error = 'Incorrect email or password. Please try again.';
                }
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
    <title>Sign In – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Sign In', 'path' => 'login', 'description' => 'Sign in to your Skope Digital Academy account.', 'noindex' => true]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
    <link rel="stylesheet" href="assets/css/auth.css?v=9">
</head>
<body>
<div class="auth">
    <aside class="auth-brand">
        <a class="auth-logo" href="./">
            <img src="assets/images/Skope Digital  logo.png" alt="">
            <span>SKOPE <em>DIGITAL</em><small>ACADEMY</small></span>
        </a>
        <div class="auth-pitch">
            <h1>Welcome back.<br><span>Pick up where you left off.</span></h1>
            <p>Your courses, progress, quizzes and certificates are waiting for you.</p>
            <ul class="auth-points">
                <li><i class="fas fa-code"></i><div><strong>Free tutorials, live code</strong><span>HTML, CSS, JavaScript, Python, SQL and PHP with examples you can run.</span></div></li>
                <li><i class="fas fa-route"></i><div><strong>Learn your way</strong><span>Self-paced, or a structured weekly track with a cohort.</span></div></li>
                <li><i class="fas fa-award"></i><div><strong>Earn certificates</strong><span>Finish a course to get a certificate you can share.</span></div></li>
            </ul>
        </div>
        <div class="auth-visual" aria-hidden="true">
            <img class="photo" src="assets/images/elearning%20aesthetic.jpeg" alt="">
            <div class="float-card cert"><span class="fi"><i class="fas fa-award"></i></span><span><b>Certificate earned</b><small>HTML Tutorial · just now</small></span></div>
            <div class="float-card progress"><span class="fi"><i class="fab fa-python"></i></span><span style="flex:1"><b>Continue learning</b><small>Python · Lesson 12 of 24</small><span class="bar"><span></span></span></span></div>
        </div>
        <div class="auth-trust"><span><i class="fas fa-book-open"></i> 6 free tutorials</span><span><i class="fas fa-code"></i> Live code editor</span><span><i class="fas fa-location-dot"></i> Kisumu, Kenya</span></div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <h2>Sign in</h2>
            <p>Use the email address you registered with.</p>

            <?php if ($error): ?>
                <div class="alert err" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
            <?php elseif (($_GET['msg'] ?? '') === 'pw_updated'): ?>
                <div class="alert ok" role="status"><i class="fas fa-circle-check"></i><span>Your password was updated. Please sign in again.</span></div>
            <?php elseif (($_GET['msg'] ?? '') === 'login_to_enroll'): ?>
                <div class="alert info" role="status"><i class="fas fa-circle-info"></i><span>Sign in (or create a free account) to enroll in this course.</span></div>
            <?php elseif (($_GET['error'] ?? '') === 'unauthorized'): ?>
                <div class="alert info" role="status"><i class="fas fa-lock"></i><span>Please sign in with an account that can open that page.</span></div>
            <?php endif; ?>

            <?php require_once __DIR__ . '/includes/google_auth.php'; sda_google_block('login'); ?>

            <form action="login" method="POST" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <div class="field">
                    <div class="field-top"><label for="email">Email address</label></div>
                    <div class="control">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="username" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <div class="field-top"><label for="password">Password</label><a href="forgot-password">Forgot password?</a></div>
                    <div class="control">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password" required>
                        <button type="button" class="peek" id="peek" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="caps" id="caps"><i class="fas fa-triangle-exclamation"></i> Caps Lock is on</div>
                </div>

                <button type="submit" class="submit" id="loginBtn"><i class="fas fa-right-to-bracket"></i> Sign in</button>
            </form>

            <div class="divider">New to Skope?</div>
            <div class="secondary">
                <a href="register"><i class="fas fa-user-plus"></i> Create account</a>
                <a href="learn"><i class="fas fa-book-open"></i> Free tutorials</a>
            </div>

            <div class="auth-foot"><a href="./"><i class="fas fa-arrow-left"></i> Back to homepage</a></div>
        </div>
    </main>
</div>

<script>
(() => {
    const form = document.getElementById('loginForm');
    const pw = document.getElementById('password');
    const peek = document.getElementById('peek');
    const btn = document.getElementById('loginBtn');
    const caps = document.getElementById('caps');

    peek.addEventListener('click', () => {
        const show = pw.type === 'password';
        pw.type = show ? 'text' : 'password';
        peek.setAttribute('aria-pressed', String(show));
        peek.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        peek.innerHTML = '<i class="fas ' + (show ? 'fa-eye-slash' : 'fa-eye') + '"></i>';
        pw.focus();
    });
    const capsCheck = (ev) => { if (ev.getModifierState) caps.style.display = ev.getModifierState('CapsLock') ? 'block' : 'none'; };
    pw.addEventListener('keydown', capsCheck);
    pw.addEventListener('keyup', capsCheck);

    form.addEventListener('submit', (ev) => {
        if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in…';
    });
    // If the browser restores the page from cache after "back", re-enable the button.
    window.addEventListener('pageshow', () => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-right-to-bracket"></i> Sign in'; });
})();
</script>
</body>
</html>
