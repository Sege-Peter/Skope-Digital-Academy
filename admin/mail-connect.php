<?php
/**
 * Admin: connect the academy Gmail with Google OAuth (or disconnect it).
 *   GET  mail-connect?start=1   -> redirect to Google's consent screen
 *   GET  mail-connect?code=...  -> Google sends the admin back here; tokens are saved encrypted
 *   POST mail-connect (disconnect=1) -> revoke and forget the tokens
 * Back to Webmail settings afterwards.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/../includes/mail_account.php';
// This address is also the registered return address for "Continue with Google" sign-in (anyone, not just
// admins): hand those returns to the sign-in code before the admin check.
require_once __DIR__ . '/../includes/google_auth.php';
if (sda_google_is_login_return()) sda_google_oauth_return();
requireRole('admin');
$user = currentUser();

$back = 'mail#settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    if (!empty($_POST['disconnect'])) {
        sda_gmail_disconnect();
        if (function_exists('ax_log')) ax_log('mail.disconnect', 'mailbox', null, 'Disconnected Gmail from Webmail');
        ax_flash('ok', 'Gmail disconnected. System emails will not send until a mailbox is connected again.');
    }
    ax_back($back);
}

if (isset($_GET['start'])) {
    if (setting('google_client_id') === '' || setting('google_client_secret') === '') {
        ax_flash('err', 'Add the Google Client ID and Client secret in Settings > Sign-in first.');
        ax_back($back);
    }
    $_SESSION['gmail_oauth_state'] = $state = bin2hex(random_bytes(16));
    header('Location: ' . sda_gmail_auth_url($state));
    exit;
}

if (isset($_GET['error'])) {
    ax_flash('err', $_GET['error'] === 'access_denied' ? 'Gmail was not connected: access was declined on the Google screen.' : 'Google returned an error: ' . mb_substr((string)$_GET['error'], 0, 80));
    ax_back($back);
}

if (isset($_GET['code'])) {
    $state = (string)($_SESSION['gmail_oauth_state'] ?? '');
    unset($_SESSION['gmail_oauth_state']);
    if ($state === '' || !hash_equals($state, (string)($_GET['state'] ?? ''))) {
        ax_flash('err', 'That Google sign-in link expired. Please click Connect Gmail again.');
        ax_back($back);
    }
    try {
        $email = sda_gmail_connect((string)$_GET['code']);
        // Prove it works: sign in to IMAP once
        $imap = sda_imap();
        $imap->logout();
        if (function_exists('ax_log')) ax_log('mail.connect', 'mailbox', null, 'Connected Gmail ' . $email . ' to Webmail');
        ax_flash('ok', 'Connected ' . $email . '. Webmail and all system emails now use this mailbox.');
    } catch (Throwable $e) {
        ax_flash('err', $e->getMessage());
    }
    ax_back($back);
}

ax_back($back);
