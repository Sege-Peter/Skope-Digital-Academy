<?php
/**
 * Auth & Session helpers – Skope Digital Academy
 */

// Signed-in sessions end after this much inactivity (admins sooner).
const SDA_IDLE_MINUTES = 120;
const SDA_IDLE_MINUTES_ADMIN = 60;

if (session_status() === PHP_SESSION_NONE) {
    $sdaHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $sdaHttps,
        // Lax (not Strict): the sign-in cookie must survive coming back from Google (sign-in, Gmail connect)
        // and payment pages. Forms are still protected by CSRF tokens.
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,        // never accept a session id the server did not issue
        'use_only_cookies' => true,
    ]);
    // Idle timeout: a signed-in session left alone too long is closed.
    if (!empty($_SESSION['user_id'])) {
        $sdaIdle = (in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true) ? SDA_IDLE_MINUTES_ADMIN : SDA_IDLE_MINUTES) * 60;
        if (!empty($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > $sdaIdle) {
            session_unset();
            session_regenerate_id(true);
            $_SESSION['session_expired'] = 1;
        } else {
            $_SESSION['last_activity'] = time();
        }
    }
}

/**
 * URL of a page in this app, e.g. sda_path('student/') -> "/Skope Digital Academy/student/" locally,
 * "/student/" on the live domain. Works out where the app lives instead of hard-coding the local folder.
 */
function sda_path(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $appRoot = realpath(__DIR__ . '/..') ?: '';
        $rel = ($docRoot !== '' && PHP_SAPI !== 'cli' && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '';
        $base = rtrim(str_replace('\\', '/', $rel), '/');
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login – redirect if not authenticated
 */
function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        header('Location: ' . ($redirect !== '' ? $redirect : sda_path('login')));
        exit;
    }
    // Admins and staff must pass two-factor sign-in before any signed-in page.
    if (in_array($_SESSION['role'] ?? '', ['admin', 'staff'], true) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'two-factor.php') {
        require_once __DIR__ . '/security.php';
        if (!sda_mfa_ok()) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                $_SESSION['mfa_next'] = ltrim(substr(rawurldecode((string)($_SERVER['REQUEST_URI'] ?? '')), strlen(rtrim(sda_path(''), '/'))), '/');
            }
            $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
            if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'two_factor_required']);
                exit;
            }
            header('Location: ' . sda_path('two-factor'));
            exit;
        }
    }
}

/**
 * Require specific role
 */
function requireRole(string|array $roles, string $redirect = ''): void {
    if ($redirect === '') $redirect = sda_path('login');
    requireLogin($redirect);
    $userRole = $_SESSION['role'] ?? '';
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($userRole, $roles)) {
        header('Location: ' . $redirect . '?error=unauthorized');
        exit;
    }
}

/**
 * Get current user info from session
 */
function currentUser(): array {
    return [
        'id'            => $_SESSION['user_id'] ?? null,
        'name'          => $_SESSION['user_name'] ?? '',
        'email'         => $_SESSION['user_email'] ?? '',
        'role'          => $_SESSION['role'] ?? '',
        'avatar'        => $_SESSION['avatar'] ?? null,
        'referral_code' => $_SESSION['referral_code'] ?? '',
        'status'        => $_SESSION['status'] ?? 'pending',
    ];
}

/**
 * Login a user (set session)
 */
function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['avatar']        = $user['avatar'] ?? null;
    $_SESSION['referral_code']  = $user['referral_code'] ?? '';
    $_SESSION['status']         = $user['status'] ?? 'pending';
    $_SESSION['last_activity']  = time();
    unset($_SESSION['session_expired']);
}

/**
 * Logout
 */
function logoutUser(): void {
    session_unset();
    session_destroy();
}

/** Is $p a safe in-site path like "enroll-me?id=4" (no scheme, host or "//")? */
function sda_is_local_path(string $p): bool {
    return (bool)preg_match('~^[a-z0-9][a-z0-9_/-]*(\?[a-z0-9_=&%.-]*)?$~i', $p);
}

/**
 * Where to send a learner once their account is activated (registration fee paid):
 * back to what they were doing (e.g. "Enroll me" on a course), else $default.
 */
function sda_after_activation(string $default = 'student/'): string {
    $next = (string)($_SESSION['after_activation'] ?? '');
    unset($_SESSION['after_activation']);
    $_SESSION['status'] = 'active';
    return sda_is_local_path($next) ? $next : $default;
}

/**
 * Redirect based on role
 */
function redirectByRole(string $role): void {
    // Back to what the learner was doing before signing in (e.g. "Enroll me"); local paths only.
    $next = (string)($_SESSION['redirect_after_login'] ?? '');
    unset($_SESSION['redirect_after_login']);
    if ($role === 'student' && sda_is_local_path($next)) {
        header('Location: ' . sda_path($next));
        exit;
    }
    $map = [
        'admin'   => sda_path('admin/'),
        'tutor'   => sda_path('tutor/'),
        'student' => sda_path('student/'),
        'staff'   => sda_path('admin/staff-home'),
    ];
    header('Location: ' . ($map[$role] ?? sda_path('')));
    exit;
}

/**
 * CSRF token generation
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token validation
 */
function verifyCsrf(): bool {
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    // An empty session token must never match an empty submitted token.
    return $expected !== '' && $token !== '' && hash_equals($expected, $token);
}

/**
 * Flash messages
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
