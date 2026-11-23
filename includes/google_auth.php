<?php
/**
 * Sign in / sign up with Google (Google Identity Services).
 * The button returns a Google ID token; google-auth.php asks Google to check it, then confirms it was made
 * for this site's Client ID, is fresh, carries our one-time nonce and has a verified email.
 * The Client ID is set in Admin > Settings > Sign-in (setting google_client_id).
 */
require_once __DIR__ . '/learning.php';

function sda_google_client_id(): string { return trim(setting('google_client_id')); }
function sda_google_on(): bool { return (bool)preg_match('/^[\w-]+\.apps\.googleusercontent\.com$/', sda_google_client_id()); }

/** Returns Google's token claims when valid, else null. */
function sda_google_verify(string $jwt): ?array
{
    if (!sda_google_on() || $jwt === '' || strlen($jwt) > 4096) return null;
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($jwt);
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
        $raw = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) $raw = false;
        curl_close($ch);
    } else {
        $raw = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10]]));
    }
    $t = json_decode((string)$raw, true);
    if (!is_array($t)) return null;
    if (($t['aud'] ?? '') !== sda_google_client_id()) return null;
    if (!in_array($t['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) return null;
    if ((int)($t['exp'] ?? 0) < time()) return null;
    if (!in_array($t['email_verified'] ?? '', ['true', true], true) || empty($t['email'])) return null;
    return $t;
}

/**
 * The "Continue with Google" block for login/register pages.
 * $mode: 'login' or 'register'. Prints nothing when Google sign-in is not set up.
 */
function sda_google_block(string $mode, string $ref = ''): void
{
    if (!sda_google_on()) return;
    $_SESSION['g_nonce'] = $nonce = bin2hex(random_bytes(16));
    $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES);
    ?>
    <div class="gsi-wrap">
        <div id="gsi-btn" class="gsi-slot" data-client="<?= $h(sda_google_client_id()) ?>" data-nonce="<?= $h($nonce) ?>" data-text="<?= $mode === 'register' ? 'signup_with' : 'continue_with' ?>"></div>
        <p class="gsi-note" id="gsi-note" role="status" hidden></p>
        <?php if ($mode === 'register'): ?><p class="gsi-terms">Continuing with Google creates a learner account and means you agree to our <a href="terms" target="_blank">Terms</a> and <a href="privacy" target="_blank">Privacy Policy</a>.</p><?php endif; ?>
        <form method="post" action="google-auth" id="gsi-form" hidden>
            <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>">
            <input type="hidden" name="mode" value="<?= $h($mode) ?>">
            <input type="hidden" name="ref_code" value="<?= $h($ref) ?>">
            <input type="hidden" name="credential" id="gsi-cred">
        </form>
    </div>
    <div class="divider">or use your email</div>
    <script src="https://accounts.google.com/gsi/client?hl=en" async defer></script>
    <script>
    (function () {
        var slot = document.getElementById('gsi-btn'), note = document.getElementById('gsi-note'), tries = 0;
        (function boot() {
            if (!(window.google && google.accounts && google.accounts.id)) {
                if (++tries > 60) { slot.hidden = true; note.textContent = 'Google sign-in could not load. Check your connection, or use your email below.'; note.hidden = false; return; }
                return setTimeout(boot, 150);
            }
            google.accounts.id.initialize({
                client_id: slot.dataset.client,
                nonce: slot.dataset.nonce,
                ux_mode: 'popup',
                callback: function (r) {
                    document.getElementById('gsi-cred').value = r.credential;
                    note.textContent = 'Signing you in…'; note.hidden = false;
                    document.getElementById('gsi-form').submit();
                }
            });
            // Google's button is at most 400px wide and 40px tall: render it at the form width (up to 400)
            // and scale it up evenly when the form is wider, so it lines up with the fields below.
            var draw = function () {
                var w = slot.clientWidth || 360, bw = Math.min(400, Math.floor(w));
                slot.innerHTML = '';
                google.accounts.id.renderButton(slot, { theme: 'outline', size: 'large', shape: 'pill', text: slot.dataset.text, logo_alignment: 'center', width: bw, locale: 'en' });
                var s = Math.min(1.2, w / bw);
                slot.style.setProperty('--gsi-scale', s.toFixed(3));
                slot.style.height = Math.round(44 * s) + 'px';
            };
            draw();
            var lastW = slot.clientWidth, t;
            window.addEventListener('resize', function () {
                clearTimeout(t);
                t = setTimeout(function () { if (Math.abs(slot.clientWidth - lastW) > 8) { lastW = slot.clientWidth; draw(); } }, 250);
            });
        })();
    })();
    </script>
    <?php
}
