<?php
/**
 * Security helpers for Skope Digital Academy.
 *  - Throttling: sign-in, sign-up and password-reset attempts are counted per email and per IP;
 *    too many failures lock that email/IP for a while and admins get one alert email.
 *  - Two-factor sign-in (TOTP, RFC 6238) for admins: authenticator app codes + one-time backup codes.
 * Tables/columns are created once (flag: security_schema) so DDL never runs inside a transaction.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';

const SDA_LOGIN_MAX_PER_EMAIL = 5;    // failed sign-ins per email in the window
const SDA_LOGIN_MAX_PER_IP = 20;      // failed sign-ins per IP in the window
const SDA_LOGIN_WINDOW_MIN = 15;      // minutes

function sda_sec_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (setting('security_schema') === '3') return;
    if ($pdo->inTransaction()) { $done = false; return; }
    // Email sign-in checks for admins: a 6-digit code and a one-click link (hashes only)
    $pdo->exec("CREATE TABLE IF NOT EXISTS mfa_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL, code_hash VARCHAR(255) NOT NULL, token_hash CHAR(64) NOT NULL,
        attempts INT NOT NULL DEFAULT 0, ip VARCHAR(64) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL,
        approved_at DATETIME NULL, used_at DATETIME NULL,
        UNIQUE KEY uq_token (token_hash), KEY idx_user (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS auth_throttle (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        bucket VARCHAR(40) NOT NULL, ident CHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_lookup (bucket, ident, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([
        "ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(255) NULL",
        "ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0",
        "ADD COLUMN IF NOT EXISTS totp_backup TEXT NULL",
        "ADD COLUMN IF NOT EXISTS totp_last_step BIGINT NULL",
    ] as $col) {
        try { $pdo->exec("ALTER TABLE users $col"); } catch (Throwable $e) { error_log('[security] ' . $e->getMessage()); }
    }
    save_setting($pdo, 'security_schema', '3');
}

/** The visitor's IP (Cloudflare's real-visitor header; the server only accepts traffic from Cloudflare). */
function sda_client_ip(): string
{
    $cf = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* ── Throttling ─────────────────────────────────────────────────────────── */

function sda_throttle_key(string $s): string { return hash('sha256', mb_strtolower(trim($s))); }

function sda_throttle_count(string $bucket, string $ident, int $minutes): int
{
    global $pdo;
    sda_sec_ensure($pdo);
    $st = $pdo->prepare("SELECT COUNT(*) FROM auth_throttle WHERE bucket = ? AND ident = ? AND created_at > NOW() - INTERVAL ? MINUTE");
    $st->execute([$bucket, sda_throttle_key($ident), $minutes]);
    return (int)$st->fetchColumn();
}

function sda_throttle_hit(string $bucket, string $ident): void
{
    global $pdo;
    sda_sec_ensure($pdo);
    $pdo->prepare("INSERT INTO auth_throttle (bucket, ident) VALUES (?, ?)")->execute([$bucket, sda_throttle_key($ident)]);
    if (random_int(1, 50) === 1) $pdo->exec("DELETE FROM auth_throttle WHERE created_at < NOW() - INTERVAL 2 DAY");
}

function sda_throttle_clear(string $bucket, string $ident): void
{
    global $pdo;
    sda_sec_ensure($pdo);
    $pdo->prepare("DELETE FROM auth_throttle WHERE bucket = ? AND ident = ?")->execute([$bucket, sda_throttle_key($ident)]);
}

/** Null when this sign-in may be tried, else the message to show. */
function sda_login_blocked(string $email): ?string
{
    try {
        if (sda_throttle_count('login_email', $email, SDA_LOGIN_WINDOW_MIN) >= SDA_LOGIN_MAX_PER_EMAIL
            || sda_throttle_count('login_ip', sda_client_ip(), SDA_LOGIN_WINDOW_MIN) >= SDA_LOGIN_MAX_PER_IP) {
            return 'Too many sign-in attempts. For your security, please wait ' . SDA_LOGIN_WINDOW_MIN . ' minutes and try again, or reset your password.';
        }
    } catch (Throwable $e) { error_log('[security] ' . $e->getMessage()); }
    return null;
}

function sda_login_failed(string $email): void
{
    try {
        sda_throttle_hit('login_email', $email);
        sda_throttle_hit('login_ip', sda_client_ip());
        // One alert to the admins when an account gets locked
        if (sda_throttle_count('login_email', $email, SDA_LOGIN_WINDOW_MIN) === SDA_LOGIN_MAX_PER_EMAIL) {
            sda_security_alert('Sign-in locked after ' . SDA_LOGIN_MAX_PER_EMAIL . ' failed attempts',
                'The account <b>' . htmlspecialchars($email) . '</b> had ' . SDA_LOGIN_MAX_PER_EMAIL . ' failed sign-in attempts in '
                . SDA_LOGIN_WINDOW_MIN . ' minutes and is locked for ' . SDA_LOGIN_WINDOW_MIN . ' minutes.<br>Last attempt from IP '
                . htmlspecialchars(sda_client_ip()) . ' at ' . date('j M Y, H:i') . '.<br><br>If this was not the account owner, no action is needed: the lock stops password guessing. '
                . 'If it keeps happening, consider asking the learner to reset their password.');
        }
    } catch (Throwable $e) { error_log('[security] ' . $e->getMessage()); }
}

function sda_login_succeeded(string $email): void
{
    try { sda_throttle_clear('login_email', $email); } catch (Throwable $e) {}
}

/** Generic limiter for other forms (sign-up, password reset): true when the action may go ahead. */
function sda_rate_ok(string $bucket, int $max, int $minutes, ?string $ident = null): bool
{
    try {
        $ident ??= sda_client_ip();
        if (sda_throttle_count($bucket, $ident, $minutes) >= $max) return false;
        sda_throttle_hit($bucket, $ident);
    } catch (Throwable $e) { error_log('[security] ' . $e->getMessage()); }
    return true;
}

/** Email the admins about a security event (queued, sent by the background worker). */
function sda_security_alert(string $subject, string $html): void
{
    global $pdo;
    try {
        require_once __DIR__ . '/notify.php';
        require_once __DIR__ . '/mailer.php';
        $to = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (array_unique(array_filter($to, fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL))) as $addr) {
            sda_mail_enqueue('security_alert', $addr, '[Security] ' . $subject,
                sda_mail_layout($subject, '<h2 style="margin:0 0 12px">' . htmlspecialchars($subject) . '</h2><p>' . $html . '</p>'));
        }
        if (function_exists('sda_spawn_worker')) sda_spawn_worker();
    } catch (Throwable $e) { error_log('[security alert] ' . $e->getMessage()); }
}

/* ── Two-factor sign-in (TOTP) ──────────────────────────────────────────── */

function sda_b32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function sda_b32_decode(string $b32): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function sda_totp_code(string $secretB32, int $step): string
{
    $h = hash_hmac('sha1', pack('J', $step), sda_b32_decode($secretB32), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** The matching 30-second step (allowing ±1 for clock drift), or null. */
function sda_totp_match(string $secretB32, string $code): ?int
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return null;
    $now = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) if (hash_equals(sda_totp_code($secretB32, $now + $d), $code)) return $now + $d;
    return null;
}

function sda_totp_new_secret(): string { return sda_b32_encode(random_bytes(20)); }

function sda_totp_uri(string $secretB32, string $email): string
{
    $issuer = 'Skope Digital Academy';
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?secret=' . $secretB32 . '&issuer=' . rawurlencode($issuer) . '&digits=6&period=30';
}

/** Ten one-time backup codes: returns [plain codes to show once, hashes to store]. */
function sda_backup_codes(): array
{
    $plain = [];
    for ($i = 0; $i < 10; $i++) $plain[] = substr(strtoupper(bin2hex(random_bytes(5))), 0, 5) . '-' . substr(strtoupper(bin2hex(random_bytes(5))), 0, 5);
    return [$plain, array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $plain)];
}

/** Is the email sign-in check required for this role? Admins, unless setting admin_2fa_required is '0'. */
function sda_mfa_required(string $role): bool
{
    if (!in_array($role, ['admin', 'staff'], true) || setting('admin_2fa_required') === '0') return false;
    // Only while the academy mailbox can send the code; a disconnected mailbox must never lock admins out.
    require_once __DIR__ . '/mail_account.php';
    return sda_mail_ready();
}

/* ── Email sign-in check (code + link) ──────────────────────────────────── */

const SDA_MFA_TTL_MIN = 10;
const SDA_MFA_MAX_TRIES = 5;

/** Create a challenge and email the code + link. Returns [challenge id, error message or ''] */
function sda_mfa_send(PDO $pdo, array $user): array
{
    sda_sec_ensure($pdo);
    require_once __DIR__ . '/mailer.php';
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $token = bin2hex(random_bytes(24));
    $pdo->prepare("UPDATE mfa_challenges SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([$user['id']]);
    $pdo->prepare("INSERT INTO mfa_challenges (user_id, code_hash, token_hash, ip, expires_at) VALUES (?, ?, ?, ?, NOW() + INTERVAL ? MINUTE)")
        ->execute([$user['id'], password_hash($code, PASSWORD_DEFAULT), hash('sha256', $token), mb_substr(sda_client_ip(), 0, 64), SDA_MFA_TTL_MIN]);
    $id = (int)$pdo->lastInsertId();
    $link = rtrim(sda_site_base(), '/') . '/two-factor?t=' . $token;
    $first = explode(' ', trim((string)$user['name']))[0] ?: 'there';
    $html = '<h2 style="margin:0 0 10px">Confirm your admin sign-in</h2>'
        . '<p>Hi ' . htmlspecialchars($first) . ', someone (hopefully you) just signed in to the Skope Digital Academy admin panel. Enter this code on the sign-in screen:</p>'
        . '<p style="text-align:center;margin:22px 0"><span style="display:inline-block;font:800 32px/1 Consolas,Menlo,monospace;letter-spacing:10px;padding:16px 22px;border-radius:14px;background:#f1f5f9;color:#0f172a">' . $code . '</span></p>'
        . '<p style="text-align:center;margin:0 0 6px;color:#64748b">or confirm with one tap:</p>'
        . sda_mail_button($link, 'Confirm sign-in')
        . '<p style="font-size:13px;color:#64748b">Requested ' . date('j M Y, H:i') . ' from IP ' . htmlspecialchars(sda_client_ip()) . '. The code and link work once and expire in ' . SDA_MFA_TTL_MIN . ' minutes.</p>'
        . '<p style="font-size:13px;color:#b91c1c"><b>Not you?</b> Don\'t use the code or link. Change your password straight away.</p>';
    $err = '';
    $ok = sda_mail((string)$user['email'], 'Your sign-in code: ' . $code, $html, '', $err);
    return [$id, $ok ? '' : ($err ?: 'The email could not be sent.')];
}

/** Check a typed code against the session's challenge. Returns true when it passes. */
function sda_mfa_check_code(PDO $pdo, int $challengeId, int $uid, string $code): bool
{
    sda_sec_ensure($pdo);
    $st = $pdo->prepare("SELECT * FROM mfa_challenges WHERE id = ? AND user_id = ? AND used_at IS NULL AND expires_at > NOW()");
    $st->execute([$challengeId, $uid]);
    $c = $st->fetch();
    if (!$c || (int)$c['attempts'] >= SDA_MFA_MAX_TRIES) return false;
    $pdo->prepare("UPDATE mfa_challenges SET attempts = attempts + 1 WHERE id = ?")->execute([$challengeId]);
    if (!password_verify(preg_replace('/\D/', '', $code), $c['code_hash'])) return false;
    $pdo->prepare("UPDATE mfa_challenges SET used_at = NOW() WHERE id = ?")->execute([$challengeId]);
    return true;
}

/** Has the emailed link for this challenge been opened (on any device)? Consumes it when yes. */
function sda_mfa_link_approved(PDO $pdo, int $challengeId, int $uid): bool
{
    sda_sec_ensure($pdo);
    $st = $pdo->prepare("UPDATE mfa_challenges SET used_at = NOW() WHERE id = ? AND user_id = ? AND approved_at IS NOT NULL AND used_at IS NULL AND expires_at > NOW() - INTERVAL 1 MINUTE");
    $st->execute([$challengeId, $uid]);
    return $st->rowCount() > 0;
}

/** Mark a challenge approved from its emailed link. Returns the challenge row, or null if invalid/expired. */
function sda_mfa_approve_token(PDO $pdo, string $token): ?array
{
    sda_sec_ensure($pdo);
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
    $st = $pdo->prepare("SELECT * FROM mfa_challenges WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()");
    $st->execute([hash('sha256', $token)]);
    $c = $st->fetch();
    if (!$c) return null;
    $pdo->prepare("UPDATE mfa_challenges SET approved_at = COALESCE(approved_at, NOW()) WHERE id = ?")->execute([$c['id']]);
    return $c;
}

/** Why a sign-in link can't be used: 'used' (that sign-in already finished), 'superseded' (a newer email was sent), 'expired' or 'invalid'. */
function sda_mfa_token_state(PDO $pdo, string $token): string
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) return 'invalid';
    $st = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM mfa_challenges n WHERE n.user_id = c.user_id AND n.id > c.id) AS newer
                         FROM mfa_challenges c WHERE c.token_hash = ?");
    $st->execute([hash('sha256', $token)]);
    $c = $st->fetch();
    if (!$c) return 'invalid';
    if ((int)$c['newer'] > 0 && empty($c['approved_at']) && (int)$c['attempts'] === 0) return 'superseded';
    if ($c['used_at'] !== null) return (int)$c['newer'] > 0 ? 'superseded' : 'used';
    return 'expired';
}

/** Has this session passed the second step (or doesn't need it)? */
function sda_mfa_ok(): bool
{
    $role = (string)($_SESSION['role'] ?? '');
    if (!sda_mfa_required($role)) return true;
    // Tied to the user id, so returning from a portal view keeps it and a different sign-in needs its own.
    return !empty($_SESSION['mfa_ok']) && (int)($_SESSION['mfa_uid'] ?? 0) === (int)($_SESSION['user_id'] ?? -1);
}
