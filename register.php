<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';
$success = '';
if (!empty($_SESSION['google_error'])) { $error = (string)$_SESSION['google_error']; unset($_SESSION['google_error']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/security.php';
    if (!verifyCsrf()) {
        $error = 'Invalid security token.';
    } elseif (!sda_rate_ok('register_ip', 8, 60)) {
        $error = 'Too many sign-ups from this connection. Please try again in an hour.';
    } else {
        $name           = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $password       = $_POST['password'] ?? '';
        $confirm        = $_POST['confirm_password'] ?? '';
        $role           = $_POST['role'] ?? 'student'; // student or tutor
        $terms          = isset($_POST['terms']);

        // Basic validation
        if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!$terms) {
            $error = 'You must agree to the Terms and Conditions.';
        } elseif (!in_array($role, ['student', 'tutor'])) {
            $error = 'Invalid role selected.';
        } else {
            try {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'This email is already registered.';
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $status = 'pending'; // All new users start as pending until payment/approval
                    
                    // Handle Referral
                    $referred_by = null;
                    $ref_code = $_GET['ref'] ?? $_POST['ref_code'] ?? null;
                    if ($ref_code) {
                        $refStmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                        $refStmt->execute([$ref_code]);
                        $referred_by = $refStmt->fetchColumn() ?: null;
                    }

                    $new_ref_code = 'SDA' . rand(100, 999) . strtoupper(substr(md5(uniqid()), 0, 8));
                    
                    $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, status, email_verified, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
                    if ($insert->execute([$name, $email, $hashed, $role, $status, $new_ref_code, $referred_by])) {
                        $new_user_id = $pdo->lastInsertId();
                        require_once __DIR__ . '/includes/notify.php';
                        if ($role === 'student') {
                            sda_notify('welcome', (int)$new_user_id);
                        } else {
                            sda_notify('tutor_application_received', (int)$new_user_id, [], ['inapp' => false]);
                            sda_notify_admins('admin_new_tutor', ['name' => $name, 'email' => $email]);
                        }
                        
                        if ($role === 'student') {
                            $adm_number = 'SDA/' . date('Y') . '/' . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
                            $pdo->prepare("UPDATE users SET admission_number = ? WHERE id = ?")->execute([$adm_number, $new_user_id]);
                        }

                        if ($role === 'student') {
                            // Sign the student in properly (fresh session id, full profile) and go to the activation fee.
                            $row = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                            $row->execute([$new_user_id]);
                            loginUser($row->fetch());
                            header("Location: checkout?type=registration");
                            exit;
                        }
                        // Tutors stay signed out until an admin approves the account.
                        $success = 'Thanks for applying to teach with us! An admin will review your tutor account and email you once it is approved.';
                    } else {
                        $error = 'Failed to create account. Please try again.';
                    }
                }
            } catch (Exception $e) {
                $error = 'Something went wrong creating your account. Please try again.';
                error_log($e->getMessage());
            }
        }
    }
}
?>
<?php
require_once __DIR__ . '/includes/learning.php';
$fee = (float)setting('registration_fee');
$role_in = in_array($_POST['role'] ?? '', ['student', 'tutor'], true) ? $_POST['role'] : (($_GET['role'] ?? '') === 'tutor' ? 'tutor' : 'student');
$ref = $_POST['ref_code'] ?? $_GET['ref'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Create Your Free Account', 'path' => 'register', 'description' => 'Join Skope Digital Academy free: start coding tutorials, enrol in skills courses, track your progress and earn certificates.']); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css?v=11">
    <link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
</head>
<body class="auth-compact">
<div class="auth">
    <aside class="auth-brand">
        <a class="auth-logo" href="./">
            <img src="assets/images/skope-logo-512.png" alt="">
            <span>SKOPE <em>DIGITAL</em><small>ACADEMY</small></span>
        </a>
        <div class="auth-pitch">
            <h1>Start learning.<br><span>Build real skills.</span></h1>
            <p>Join learners across Kenya and East Africa building careers in tech, one lesson at a time.</p>
            <ul class="auth-points">
                <li><i class="fas fa-code"></i><div><strong>Learn by doing</strong><span>Every lesson has examples you can edit and run in your browser.</span></div></li>
                <li><i class="fas fa-route"></i><div><strong>Self-paced or structured</strong><span>Go at your own speed, or join a weekly cohort with deadlines.</span></div></li>
                <li><i class="fas fa-chalkboard-user"></i><div><strong>Teach with us</strong><span>Tutors build courses and reach new students.</span></div></li>
            </ul>
        </div>
        <div class="auth-visual" aria-hidden="true">
            <picture><source type="image/webp" srcset="assets/images/promo/study-session.webp"><img src="assets/images/promo/study-session.jpg" alt="" class="photo"></picture>
        </div>
        <div class="auth-trust"><span><i class="fas fa-book-open"></i> 6 free tutorials</span><span><i class="fas fa-code"></i> Live code editor</span><span><i class="fas fa-location-dot"></i> Kisumu, Kenya</span></div>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
        <?php if ($success): ?>
            <div class="done">
                <div class="auth-icon"><i class="fas fa-circle-check"></i></div>
                <h2>Application received</h2>
                <p><?= htmlspecialchars($success) ?></p>
                <a class="submit" href="login"><i class="fas fa-right-to-bracket"></i> Go to sign in</a>
                <div class="auth-foot"><a href="learn"><i class="fas fa-book-open"></i> Browse the free tutorials meanwhile</a></div>
            </div>
        <?php else: ?>
            <h2>Create your account</h2>

            <?php if ($error): ?>
                <div class="alert err" role="alert"><i class="fas fa-circle-exclamation"></i><span><?= htmlspecialchars($error) ?><?= $error === 'This email is already registered.' ? ' <a href="login">Sign in instead</a> or <a href="forgot-password">reset your password</a>.' : '' ?></span></div>
            <?php elseif ($ref !== ''): ?>
                <div class="alert info" role="status"><i class="fas fa-gift"></i><span>You were invited by a Skope learner. Welcome!</span></div>
            <?php elseif (($_GET['msg'] ?? '') === 'signup_to_enroll'): ?>
                <div class="alert info" role="status"><i class="fas fa-user-plus"></i><span>Create your account to enroll. As soon as your registration is complete, the course opens. Already a learner? <a href="login">Sign in</a>.</span></div>
            <?php endif; ?>

            <?php require_once __DIR__ . '/includes/google_auth.php'; sda_google_block('register', $ref); ?>

            <form action="register" method="POST" id="regForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="ref_code" value="<?= htmlspecialchars($ref) ?>">

                <fieldset class="roles">
                    <legend class="sr-only">I want to</legend>
                    <label class="role">
                        <input type="radio" name="role" value="student" <?= $role_in === 'student' ? 'checked' : '' ?>>
                        <span><strong><i class="fas fa-user-graduate"></i> Learn</strong><small><?= $fee > 0 ? 'One-time activation fee of ' . htmlspecialchars(money($fee)) : 'Free to join' ?></small></span>
                    </label>
                    <label class="role">
                        <input type="radio" name="role" value="tutor" <?= $role_in === 'tutor' ? 'checked' : '' ?>>
                        <span><strong><i class="fas fa-chalkboard-user"></i> Teach</strong><small>Reviewed by an admin before you can sign in</small></span>
                    </label>
                </fieldset>

                <div class="grid2">
                <div class="field">
                    <div class="field-top"><label for="full_name">Full name</label></div>
                    <div class="control">
                        <i class="fas fa-user"></i>
                        <input type="text" id="full_name" name="full_name" placeholder="e.g. Amina Otieno" autocomplete="name" maxlength="120" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="field">
                    <div class="field-top"><label for="email">Email address</label></div>
                    <div class="control">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com" autocomplete="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                </div>

                <div class="grid2">
                <div class="field">
                    <div class="field-top"><label for="password">Password</label></div>
                    <div class="control">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required aria-describedby="pwHint">
                        <button type="button" class="peek" data-for="password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="meter" id="meter" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
                    <div class="hint" id="pwHint">8+ characters; mix letters, numbers &amp; symbols.</div>
                </div>

                <div class="field">
                    <div class="field-top"><label for="confirm_password">Confirm password</label></div>
                    <div class="control">
                        <i class="fas fa-shield-halved"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Type it again" autocomplete="new-password" required>
                        <button type="button" class="peek" data-for="confirm_password" aria-label="Show password"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="hint" id="matchHint" aria-live="polite"></div>
                </div>
                </div>

                <label class="check">
                    <input type="checkbox" name="terms" <?= isset($_POST['terms']) ? 'checked' : '' ?> required>
                    <span>I agree to the <a href="terms" target="_blank">Terms of Service</a> and <a href="privacy" target="_blank">Privacy Policy</a>.</span>
                </label>

                <button type="submit" class="submit" id="regBtn"><i class="fas fa-user-plus"></i> <span>Create account</span></button>
            </form>

            <div class="auth-switch">Already have an account? <a href="login">Sign in</a></div>
            <div class="auth-foot"><a href="./"><i class="fas fa-arrow-left"></i> Back to homepage</a></div>
        <?php endif; ?>
        </div>
    </main>
</div>

<script>
(() => {
    const form = document.getElementById('regForm');
    if (!form) return;
    const pw = document.getElementById('password');
    const cf = document.getElementById('confirm_password');
    const meter = document.getElementById('meter');
    const pwHint = document.getElementById('pwHint');
    const matchHint = document.getElementById('matchHint');
    const btnText = document.querySelector('#regBtn span');

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
        return Math.max(v.length ? 1 : 0, Math.min(4, s));
    };
    const labels = ['', 'Too weak', 'Okay', 'Good', 'Strong'];
    const check = () => {
        const s = strength(pw.value);
        meter.dataset.s = s;
        if (!pw.value) { pwHint.className = 'hint'; pwHint.textContent = '8+ characters; mix letters, numbers & symbols.'; }
        else if (pw.value.length < 8) { pwHint.className = 'hint bad'; pwHint.textContent = `${8 - pw.value.length} more character${8 - pw.value.length === 1 ? '' : 's'} needed`; }
        else { pwHint.className = 'hint ' + (s >= 3 ? 'good' : 'warn'); pwHint.textContent = 'Strength: ' + labels[s]; }
        if (!cf.value) { matchHint.textContent = ''; cf.classList.remove('bad'); }
        else if (cf.value === pw.value) { matchHint.className = 'hint good'; matchHint.textContent = '✓ Passwords match'; cf.classList.remove('bad'); }
        else { matchHint.className = 'hint bad'; matchHint.textContent = 'Passwords don\'t match yet'; cf.classList.add('bad'); }
    };
    pw.addEventListener('input', check);
    cf.addEventListener('input', check);

    const setRole = () => { btnText.textContent = form.role.value === 'tutor' ? 'Apply to teach' : 'Create account'; };
    form.querySelectorAll('input[name="role"]').forEach((r) => r.addEventListener('change', setRole));
    setRole();

    form.addEventListener('submit', (ev) => {
        cf.setCustomValidity(cf.value && cf.value !== pw.value ? 'Passwords do not match' : '');
        if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
        const btn = document.getElementById('regBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating your account…';
    });
    window.addEventListener('pageshow', () => { const btn = document.getElementById('regBtn'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-user-plus"></i> <span>' + (form.role.value === 'tutor' ? 'Apply to teach' : 'Create account') + '</span>'; });
})();
</script>
</body>
</html>
