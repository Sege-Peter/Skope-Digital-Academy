<?php
/**
 * Receives the Google ID token from the "Continue with Google" button on login/register.
 * Existing account with that (verified) email -> signed in, same rules as the password login.
 * No account yet -> a learner account is created (like register.php) and goes to the activation checkout.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/google_auth.php';

$mode = ($_POST['mode'] ?? '') === 'register' ? 'register' : 'login';
$back = function (string $msg) use ($mode) {
    $_SESSION['google_error'] = $msg;
    header('Location: ' . $mode);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: login'); exit; }
if (isLoggedIn()) redirectByRole($_SESSION['role']);

$nonce = (string)($_SESSION['g_nonce'] ?? '');
unset($_SESSION['g_nonce']);

if (!verifyCsrf()) $back('Your session expired. Please try again.');
if (!sda_google_on()) $back('Google sign-in is not switched on yet. Please use your email and password.');
$t = sda_google_verify((string)($_POST['credential'] ?? ''));
if (!$t || $nonce === '' || !hash_equals($nonce, (string)($t['nonce'] ?? ''))) {
    $back('Google could not confirm your sign-in. Please try again.');
}

$email = mb_strtolower(trim((string)$t['email']));

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        if ($user['status'] === 'suspended') $back('Your account has been suspended. Please contact support.');
        if ($user['status'] === 'pending') {
            if ($user['role'] !== 'student') $back('Your tutor account is pending admin approval. Please check back later.');
            if (empty($user['email_verified'])) $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?")->execute([$user['id']]);
            loginUser($user);
            header('Location: checkout?type=registration');
            exit;
        }
        if (!empty($user['requires_password_change'])) {
            $_SESSION['force_password_change_uid'] = $user['id'];
            header('Location: force_password_change');
            exit;
        }
        // Daily streak, same as the password login
        $today = date('Y-m-d');
        $last = $user['last_streak_date'] ?? null;
        if ($last !== $today) {
            $days = $last === date('Y-m-d', strtotime('-1 day')) ? (int)$user['streak_days'] + 1 : 1;
            $pdo->prepare("UPDATE users SET last_login = NOW(), streak_days = ?, last_streak_date = ?, email_verified = 1 WHERE id = ?")->execute([$days, $today, $user['id']]);
        } else {
            $pdo->prepare("UPDATE users SET last_login = NOW(), email_verified = 1 WHERE id = ?")->execute([$user['id']]);
        }
        loginUser($user);
        redirectByRole($user['role']);
    }

    // New learner account
    $name = trim((string)($t['name'] ?? '')) ?: trim(($t['given_name'] ?? '') . ' ' . ($t['family_name'] ?? '')) ?: explode('@', $email)[0];
    $name = mb_substr($name, 0, 150);
    $referred_by = null;
    $ref_code = trim((string)($_POST['ref_code'] ?? ''));
    if ($ref_code !== '') {
        $r = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $r->execute([$ref_code]);
        $referred_by = $r->fetchColumn() ?: null;
    }
    $new_ref = 'SDA' . random_int(100, 999) . strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
    // Random password nobody knows: they sign in with Google, or use "Forgot password" to set one.
    $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (name, email, password, role, status, email_verified, referral_code, referred_by) VALUES (?, ?, ?, 'student', 'pending', 1, ?, ?)")
        ->execute([$name, $email, $hash, $new_ref, $referred_by]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE users SET admission_number = ? WHERE id = ?")->execute(['SDA/' . date('Y') . '/' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), $id]);

    require_once 'includes/notify.php';
    sda_notify('welcome', $id);

    $row = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $row->execute([$id]);
    loginUser($row->fetch());
    header('Location: checkout?type=registration');
    exit;
} catch (Throwable $e) {
    error_log('[google-auth] ' . $e->getMessage());
    $back('Something went wrong signing you in with Google. Please try again.');
}
