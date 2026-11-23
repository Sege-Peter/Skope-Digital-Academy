<?php
/**
 * Google sign-in entry point (logic in includes/google_auth.php).
 *   GET  google-auth?start=1&mode=login|register&ref=CODE  -> off to Google
 *   GET  google-auth?code=…&state=…                         -> back from Google (when this is the registered return address)
 *   POST credential=…                                       -> older one-tap/button token (still accepted)
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google_auth.php';

if (isLoggedIn()) redirectByRole($_SESSION['role']);

if (isset($_GET['start'])) {
    $mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
    if (!sda_google_redirect_ready()) sda_google_fail($mode, 'Google sign-in is not switched on yet. Please use your email and password.');
    header('Location: ' . sda_google_login_url($mode, (string)($_GET['ref'] ?? '')));
    exit;
}

if (sda_google_is_login_return()) sda_google_oauth_return();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['credential'])) {
    $mode = ($_POST['mode'] ?? '') === 'register' ? 'register' : 'login';
    $nonce = (string)($_SESSION['g_nonce'] ?? '');
    unset($_SESSION['g_nonce']);
    if (!verifyCsrf()) sda_google_fail($mode, 'Your session expired. Please try again.');
    $t = sda_google_verify((string)$_POST['credential']);
    if (!$t || $nonce === '' || !hash_equals($nonce, (string)($t['nonce'] ?? ''))) sda_google_fail($mode, 'Google could not confirm your sign-in. Please try again.');
    sda_google_complete($t, $mode, trim((string)($_POST['ref_code'] ?? '')));
}

// Anything else (e.g. an old or expired Google link)
if (isset($_GET['state']) || isset($_GET['code'])) sda_google_fail('login', 'That Google sign-in link has expired. Please try again.');
header('Location: ' . sda_path('login'));
exit;
