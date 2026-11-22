<?php
/**
 * Auth & Session helpers – Skope Digital Academy
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => false, // set true in production with HTTPS
        'cookie_samesite' => 'Strict'
    ]);
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
function requireLogin(string $redirect = '/Skope Digital Academy/login'): void {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Require specific role
 */
function requireRole(string|array $roles, string $redirect = '/Skope Digital Academy/login'): void {
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
}

/**
 * Logout
 */
function logoutUser(): void {
    session_unset();
    session_destroy();
}

/**
 * Redirect based on role
 */
function redirectByRole(string $role): void {
    $map = [
        'admin'   => '/Skope Digital Academy/admin/',
        'tutor'   => '/Skope Digital Academy/tutor/',
        'student' => '/Skope Digital Academy/student/',
    ];
    header('Location: ' . ($map[$role] ?? '/Skope Digital Academy/'));
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
