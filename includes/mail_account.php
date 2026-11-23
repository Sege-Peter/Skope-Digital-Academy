<?php
/**
 * The academy's mail account (used by the admin webmail and by every system email).
 *
 * Two ways to sign in, chosen in Admin > Webmail > Settings:
 *  - 'google'   : "Connect Gmail" (OAuth). A refresh token is stored encrypted; IMAP and SMTP sign in with
 *                 XOAUTH2. No app password or 2-Step Verification needed.
 *  - 'password' : any IMAP/SMTP mailbox (Gmail with an App password, or a cPanel mailbox like info@domain).
 *
 * Secrets (refresh token, mailbox password) are encrypted with includes/private/app.key (AES-256-GCM).
 * includes/ is blocked from the web by .htaccess and includes/private/ is git-ignored.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';
require_once __DIR__ . '/imap_client.php';

const SDA_GMAIL_SCOPE = 'https://mail.google.com/ openid email profile';

/* ── encryption ─────────────────────────────────────────────────────────── */

function sda_app_key(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $dir = __DIR__ . '/private';
    $file = "$dir/app.key";
    if (!is_file($file)) {
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        @file_put_contents("$dir/.htaccess", "Require all denied\n");
        file_put_contents($file, base64_encode(random_bytes(32)));
        @chmod($file, 0600);
    }
    return $key = base64_decode(trim((string)file_get_contents($file)));
}

function sda_encrypt(string $plain): string
{
    if ($plain === '') return '';
    $iv = random_bytes(12);
    $ct = openssl_encrypt($plain, 'aes-256-gcm', sda_app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'enc1:' . base64_encode($iv . $tag . $ct);
}

function sda_decrypt(string $stored): string
{
    if ($stored === '' || strncmp($stored, 'enc1:', 5) !== 0) return $stored; // older plain values
    $raw = base64_decode(substr($stored, 5));
    if (strlen($raw) < 29) return '';
    $out = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', sda_app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $out === false ? '' : $out;
}

/** Read a setting fresh from the table (setting() caches for the request). */
function sda_setting_fresh(string $key): string
{
    global $pdo;
    $s = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
    $s->execute([$key]);
    return (string)($s->fetchColumn() ?: '');
}

/* ── account ────────────────────────────────────────────────────────────── */

/** Everything needed to talk to the mailbox. */
function sda_mail_account(): array
{
    $auth = sda_setting_fresh('mail_auth') ?: 'password';
    $address = $auth === 'google' ? sda_setting_fresh('gmail_address') : setting('smtp_username');
    $smtpHost = setting('smtp_host') ?: ($auth === 'google' ? 'smtp.gmail.com' : '');
    $imapHost = setting('imap_host') ?: (str_contains($smtpHost, 'gmail') || $auth === 'google' ? 'imap.gmail.com' : preg_replace('/^(smtp|mail)\./', 'mail.', $smtpHost));
    return [
        'auth'        => $auth,
        'address'     => $address,
        'name'        => setting('mail_from_name') ?: (setting('academy_name') ?: 'Skope Digital Academy'),
        'imap_host'   => $auth === 'google' ? 'imap.gmail.com' : $imapHost,
        'imap_port'   => (int)(setting('imap_port') ?: 993),
        'smtp_host'   => $auth === 'google' ? 'smtp.gmail.com' : $smtpHost,
        'smtp_port'   => $auth === 'google' ? 587 : (int)(setting('smtp_port') ?: 587),
        'smtp_secure' => $auth === 'google' ? 'tls' : (setting('smtp_encryption') ?: 'tls'),
        'password'    => $auth === 'google' ? '' : sda_decrypt(setting('smtp_password')),
        'gmail'       => $auth === 'google' || str_contains($smtpHost, 'gmail'),
    ];
}

/** True when the account can sign in (Gmail connected, or a password saved). */
function sda_mail_ready(): bool
{
    $a = sda_mail_account();
    if ($a['address'] === '') return false;
    return $a['auth'] === 'google' ? sda_setting_fresh('gmail_refresh_token') !== '' : ($a['smtp_host'] !== '' && $a['password'] !== '');
}

/* ── Gmail OAuth ───────────────────────────────────────────────────────── */

function sda_gmail_redirect_uri(): string
{
    $base = rtrim(sda_site_base(), '/');
    return $base . '/admin/mail-connect';
}

/** Site address including the folder (http://localhost/Skope%20Digital%20Academy or https://domain). */
function sda_site_base(): string
{
    $site = rtrim(setting('site_url'), '/');
    if (PHP_SAPI === 'cli') {
        // Background jobs: no web request to look at. Use the configured address, else this local folder.
        return $site !== '' ? $site : 'http://localhost/' . rawurlencode(basename(dirname(__DIR__)));
    }
    $isLocal = in_array(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'), ['localhost', '127.0.0.1'], true);
    if ($site !== '' && !$isLocal) return $site;
    $base = function_exists('sda_base_href') ? rtrim(sda_base_href(), '/') : '/Skope%20Digital%20Academy';
    return 'http://localhost' . str_replace(' ', '%20', $base);
}

function sda_gmail_auth_url(string $state): string
{
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => setting('google_client_id'),
        'redirect_uri'  => sda_gmail_redirect_uri(),
        'response_type' => 'code',
        'scope'         => SDA_GMAIL_SCOPE,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
        'login_hint'    => setting('smtp_username') ?: '',
    ], '', '&', PHP_QUERY_RFC3986);
}

function sda_http_post(string $url, array $fields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$raw, true);
    return [$code, is_array($data) ? $data : ['error' => $err ?: 'bad_response']];
}

/** Exchange the code from Google for tokens and save them. Returns the connected address. */
function sda_gmail_connect(string $code): string
{
    global $pdo;
    [$status, $t] = sda_http_post('https://oauth2.googleapis.com/token', [
        'code' => $code, 'client_id' => setting('google_client_id'), 'client_secret' => sda_decrypt(setting('google_client_secret')),
        'redirect_uri' => sda_gmail_redirect_uri(), 'grant_type' => 'authorization_code',
    ]);
    if ($status !== 200 || empty($t['access_token'])) throw new RuntimeException('Google did not accept the sign-in: ' . ($t['error_description'] ?? $t['error'] ?? 'unknown error'));
    if (empty($t['refresh_token'])) throw new RuntimeException('Google did not return a long-term token. Remove the app at myaccount.google.com/permissions and connect again.');
    if (!str_contains((string)($t['scope'] ?? ''), 'https://mail.google.com/')) throw new RuntimeException('Mail access was not allowed. Tick the "Read, compose, send and delete" box when Google asks.');
    $claims = json_decode(base64_decode(strtr(explode('.', (string)($t['id_token'] ?? '') . '..')[1], '-_', '+/')), true) ?: [];
    $email = strtolower((string)($claims['email'] ?? ''));
    if ($email === '') throw new RuntimeException('Google did not say which address was connected.');
    save_setting($pdo, 'mail_auth', 'google');
    save_setting($pdo, 'gmail_address', $email);
    save_setting($pdo, 'gmail_refresh_token', sda_encrypt($t['refresh_token']));
    save_setting($pdo, 'gmail_access_token', sda_encrypt($t['access_token']));
    save_setting($pdo, 'gmail_token_expires', (string)(time() + (int)($t['expires_in'] ?? 3600) - 120));
    return $email;
}

function sda_gmail_disconnect(): void
{
    global $pdo;
    $rt = sda_decrypt(sda_setting_fresh('gmail_refresh_token'));
    if ($rt !== '') sda_http_post('https://oauth2.googleapis.com/revoke', ['token' => $rt]);
    foreach (['gmail_refresh_token', 'gmail_access_token', 'gmail_token_expires', 'gmail_address'] as $k) save_setting($pdo, $k, '');
    save_setting($pdo, 'mail_auth', 'password');
}

/** A valid access token (refreshed when it is about to expire). */
function sda_gmail_token(): string
{
    global $pdo;
    $exp = (int)sda_setting_fresh('gmail_token_expires');
    $tok = sda_decrypt(sda_setting_fresh('gmail_access_token'));
    if ($tok !== '' && $exp > time()) return $tok;
    $rt = sda_decrypt(sda_setting_fresh('gmail_refresh_token'));
    if ($rt === '') throw new RuntimeException('Gmail is not connected. Open Admin > Webmail and click Connect Gmail.');
    [$status, $t] = sda_http_post('https://oauth2.googleapis.com/token', [
        'client_id' => setting('google_client_id'), 'client_secret' => sda_decrypt(setting('google_client_secret')),
        'refresh_token' => $rt, 'grant_type' => 'refresh_token',
    ]);
    if ($status !== 200 || empty($t['access_token'])) {
        throw new RuntimeException(($t['error'] ?? '') === 'invalid_grant'
            ? 'The Gmail connection expired or was removed. Reconnect Gmail in Admin > Webmail.'
            : 'Could not refresh the Gmail sign-in (' . ($t['error'] ?? 'network') . ').');
    }
    save_setting($pdo, 'gmail_access_token', sda_encrypt($t['access_token']));
    save_setting($pdo, 'gmail_token_expires', (string)(time() + (int)($t['expires_in'] ?? 3600) - 120));
    return $t['access_token'];
}

/* ── connections ───────────────────────────────────────────────────────── */

/** A signed-in IMAP connection to the academy mailbox. Throws with a readable message on failure. */
function sda_imap(): SdaImap
{
    $a = sda_mail_account();
    if ($a['address'] === '') throw new RuntimeException('No mailbox is set up yet. Open Webmail > Settings.');
    $imap = new SdaImap();
    if (!$imap->connect($a['imap_host'], $a['imap_port'], true)) throw new RuntimeException($imap->lastError);
    $ok = $a['auth'] === 'google' ? $imap->loginXoauth2($a['address'], sda_gmail_token()) : $imap->login($a['address'], $a['password']);
    if (!$ok) {
        $msg = $imap->authFailed
            ? ($a['auth'] === 'google' ? $imap->lastError : 'The mailbox password was rejected. For Gmail use an App password.')
            : ($imap->lastError ?: 'Could not sign in to the mailbox.');
        $imap->logout();
        throw new RuntimeException($msg);
    }
    return $imap;
}

/** SMTP AUTH step for sda_smtp_send(): returns the commands to run after EHLO (STARTTLS done). */
function sda_smtp_auth(callable $cmd): void
{
    $a = sda_mail_account();
    if ($a['auth'] === 'google') {
        $cmd('AUTH XOAUTH2 ' . base64_encode("user={$a['address']}\x01auth=Bearer " . sda_gmail_token() . "\x01\x01"), [235], 'login');
        return;
    }
    $pass = $a['gmail'] ? str_replace(' ', '', $a['password']) : $a['password'];
    $cmd('AUTH LOGIN', [334], 'login');
    $cmd(base64_encode($a['address']), [334], 'login');
    $cmd(base64_encode($pass), [235], 'login');
}
