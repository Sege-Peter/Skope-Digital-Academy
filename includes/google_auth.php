<?php
/**
 * Sign in / sign up with Google: full-page OAuth redirect (works the same on every phone and browser).
 *
 *   Button  -> google-auth?start=1&mode=login|register&ref=CODE
 *           -> Google's account page
 *           -> back to the registered redirect URI with ?code&state
 *           -> code exchanged for an ID token (server to server), token checked, then signed in / signed up.
 *
 * The return address is setting google_login_redirect (path), default "admin/mail-connect" (already registered
 * in Google Cloud). Add https://<site>/google-auth as an Authorised redirect URI and set
 * google_login_redirect = google-auth to use the cleaner address.
 * Client ID / secret: Admin > Settings > Sign-in (google_client_id, google_client_secret).
 */
require_once __DIR__ . '/learning.php';
require_once __DIR__ . '/mail_account.php';   // sda_site_base(), sda_http_post(), sda_decrypt()

function sda_google_client_id(): string { return trim(setting('google_client_id')); }
function sda_google_on(): bool { return (bool)preg_match('/^[\w-]+\.apps\.googleusercontent\.com$/', sda_google_client_id()); }
function sda_google_redirect_ready(): bool { return sda_google_on() && setting('google_client_secret') !== ''; }

function sda_google_login_redirect_uri(): string
{
    $path = trim(setting('google_login_redirect')) ?: 'admin/mail-connect';
    return rtrim(sda_site_base(), '/') . '/' . ltrim($path, '/');
}

/** Google's token check: right client, right issuer, not expired, verified email. Returns the claims or null. */
function sda_google_verify(string $jwt): ?array
{
    if (!sda_google_on() || $jwt === '' || strlen($jwt) > 4096) return null;
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($jwt));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
    $raw = curl_exec($ch);
    $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    $t = $ok ? json_decode((string)$raw, true) : null;
    if (!is_array($t)) return null;
    if (($t['aud'] ?? '') !== sda_google_client_id()) return null;
    if (!in_array($t['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) return null;
    if ((int)($t['exp'] ?? 0) < time()) return null;
    if (!in_array($t['email_verified'] ?? '', ['true', true], true) || empty($t['email'])) return null;
    return $t;
}

/** Start: remember what we expect back, then send the visitor to Google. */
function sda_google_login_url(string $mode, string $ref = ''): string
{
    $_SESSION['g_oauth'] = [
        'state' => bin2hex(random_bytes(16)), 'nonce' => bin2hex(random_bytes(16)),
        'mode' => $mode === 'register' ? 'register' : 'login', 'ref' => mb_substr($ref, 0, 60), 't' => time(),
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => sda_google_client_id(), 'redirect_uri' => sda_google_login_redirect_uri(),
        'response_type' => 'code', 'scope' => 'openid email profile',
        'state' => $_SESSION['g_oauth']['state'], 'nonce' => $_SESSION['g_oauth']['nonce'],
        'prompt' => 'select_account',
    ], '', '&', PHP_QUERY_RFC3986);
}

/** Is this request Google coming back from a sign-in we started? */
function sda_google_is_login_return(): bool
{
    $g = $_SESSION['g_oauth'] ?? null;
    return isset($_GET['state']) && is_array($g) && hash_equals((string)$g['state'], (string)$_GET['state']);
}

function sda_google_fail(string $mode, string $msg): never
{
    $_SESSION['google_error'] = $msg;
    header('Location: ' . sda_path($mode === 'register' ? 'register' : 'login'));
    exit;
}

/** Finish a redirect sign-in: exchange the code, check the token, then sign in / sign up. */
function sda_google_oauth_return(): never
{
    $g = $_SESSION['g_oauth'];
    unset($_SESSION['g_oauth']);
    $mode = $g['mode'];
    if (isset($_GET['error'])) {
        sda_google_fail($mode, $_GET['error'] === 'access_denied' ? 'Google sign-in was cancelled.' : 'Google could not sign you in (' . mb_substr((string)$_GET['error'], 0, 60) . ').');
    }
    if (time() - (int)$g['t'] > 900) sda_google_fail($mode, 'That Google sign-in took too long. Please try again.');
    [$status, $tok] = sda_http_post('https://oauth2.googleapis.com/token', [
        'code' => (string)($_GET['code'] ?? ''), 'client_id' => sda_google_client_id(),
        'client_secret' => sda_decrypt(setting('google_client_secret')),
        'redirect_uri' => sda_google_login_redirect_uri(), 'grant_type' => 'authorization_code',
    ]);
    if ($status !== 200 || empty($tok['id_token'])) {
        error_log('[google sign-in] token exchange failed: ' . json_encode($tok));
        sda_google_fail($mode, 'Google could not confirm your sign-in. Please try again.');
    }
    $t = sda_google_verify((string)$tok['id_token']);
    if (!$t || !hash_equals((string)$g['nonce'], (string)($t['nonce'] ?? ''))) {
        sda_google_fail($mode, 'Google could not confirm your sign-in. Please try again.');
    }
    sda_google_complete($t, $mode, (string)$g['ref']);
}

/** Signed-in Google identity -> existing account (same rules as password sign-in) or a new learner account. */
function sda_google_complete(array $t, string $mode, string $ref = ''): never
{
    global $pdo;
    $email = mb_strtolower(trim((string)$t['email']));
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['status'] === 'suspended') sda_google_fail($mode, 'Your account has been suspended. Please contact support.');
            if ($user['status'] === 'pending') {
                if ($user['role'] !== 'student') sda_google_fail($mode, 'Your tutor account is pending admin approval. Please check back later.');
                if (empty($user['email_verified'])) $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = ?")->execute([$user['id']]);
                loginUser($user);
                header('Location: ' . sda_path('checkout?type=registration'));
                exit;
            }
            if (!empty($user['requires_password_change'])) {
                $_SESSION['force_password_change_uid'] = $user['id'];
                header('Location: ' . sda_path('force_password_change'));
                exit;
            }
            // Daily streak, same as the password sign-in
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
        if ($ref !== '') {
            $r = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
            $r->execute([$ref]);
            $referred_by = $r->fetchColumn() ?: null;
        }
        $new_ref = 'SDA' . random_int(100, 999) . strtoupper(substr(bin2hex(random_bytes(6)), 0, 8));
        // Random password nobody knows: they sign in with Google, or use "Forgot password" to set one.
        $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (name, email, password, role, status, email_verified, referral_code, referred_by) VALUES (?, ?, ?, 'student', 'pending', 1, ?, ?)")
            ->execute([$name, $email, $hash, $new_ref, $referred_by]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE users SET admission_number = ? WHERE id = ?")->execute(['SDA/' . date('Y') . '/' . str_pad((string)$id, 4, '0', STR_PAD_LEFT), $id]);

        require_once __DIR__ . '/notify.php';
        sda_notify('welcome', $id);

        $row = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $row->execute([$id]);
        loginUser($row->fetch());
        header('Location: ' . sda_path('checkout?type=registration'));
        exit;
    } catch (Throwable $e) {
        error_log('[google sign-in] ' . $e->getMessage());
        sda_google_fail($mode, 'Something went wrong signing you in with Google. Please try again.');
    }
}

/** The "Continue with Google" button for the login / register pages (plain link: instant, no Google script). */
function sda_google_block(string $mode, string $ref = ''): void
{
    if (!sda_google_redirect_ready()) return;
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    $label = $mode === 'register' ? 'Sign up with Google' : 'Continue with Google';
    $href = sda_path('google-auth') . '?' . http_build_query(['start' => 1, 'mode' => $mode] + ($ref !== '' ? ['ref' => $ref] : []));
    ?>
    <div class="gsi-wrap">
        <div class="gsi-box" id="gsi-box">
            <a class="gsi-ph" id="gsi-ph" href="<?= $h($href) ?>" rel="nofollow">
                <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.5 0 6.6 1.2 9.1 3.6l6.8-6.8C35.8 2.4 30.3 0 24 0 14.6 0 6.6 5.4 2.7 13.3l7.9 6.1C12.5 13.6 17.8 9.5 24 9.5z"/><path fill="#4285F4" d="M46.1 24.5c0-1.6-.1-3.1-.4-4.5H24v9h12.4c-.5 2.9-2.2 5.3-4.6 6.9l7.4 5.7c4.3-4 6.9-9.9 6.9-17.1z"/><path fill="#FBBC05" d="M10.6 28.6c-.5-1.4-.8-3-.8-4.6s.3-3.2.8-4.6l-7.9-6.1C1 16.6 0 20.2 0 24s1 7.4 2.7 10.7l7.9-6.1z"/><path fill="#34A853" d="M24 48c6.5 0 11.9-2.1 15.9-5.8l-7.4-5.7c-2.1 1.4-4.8 2.2-8.5 2.2-6.2 0-11.5-4.1-13.4-9.9l-7.9 6.1C6.6 42.6 14.6 48 24 48z"/></svg>
                <span><?= $h($label) ?></span>
            </a>
            <div class="gsi-busy" id="gsi-busy" hidden><span class="gsi-spin" aria-hidden="true"></span> Opening Google…</div>
        </div>
        <?php if ($mode === 'register'): ?><p class="gsi-terms">Continuing with Google creates a learner account and means you agree to our <a href="terms" target="_blank">Terms</a> and <a href="privacy" target="_blank">Privacy Policy</a>.</p><?php endif; ?>
    </div>
    <div class="divider">or use your email</div>
    <script>
    (function () {
        var a = document.getElementById('gsi-ph'), busy = document.getElementById('gsi-busy');
        a.addEventListener('click', function () { busy.hidden = false; });
        window.addEventListener('pageshow', function () { busy.hidden = true; });   // back button from Google
    })();
    </script>
    <?php
}
