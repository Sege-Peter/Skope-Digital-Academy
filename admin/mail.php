<?php
/**
 * Admin > Webmail: Gmail-style webmail for the academy mailbox (ported from the HAMASTA MEDIA admin mailbox).
 * The mail UI is drawn by assets/js/sda-mailbox.js (API: admin/api-mailbox.php, helpers: includes/mailbox.php).
 * The "Mailbox connection" panel below (#settings) is plain PHP: Connect Gmail (admin/mail-connect.php),
 * Disconnect, or an IMAP/SMTP mailbox with a password (saved here).
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/ui.php';
require_once __DIR__ . '/../includes/mail_account.php';
requireRole('admin');
$user = currentUser();

/* ── Save a password mailbox (IMAP + SMTP) ─────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    if (($_POST['action'] ?? '') === 'save_password_account') {
        $host = fn($v) => strtolower(trim((string)$v));
        $imapHost = $host($_POST['imap_host'] ?? '');
        $smtpHost = $host($_POST['smtp_host'] ?? '');
        $imapPort = (int)($_POST['imap_port'] ?? 993);
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $enc = in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls';
        $address = strtolower(trim((string)($_POST['smtp_username'] ?? '')));
        $pass = (string)($_POST['smtp_password'] ?? '');
        $hostRe = '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/';
        $fail = function (string $m) { ax_flash('err', $m); ax_back('mail#settings'); };
        if (!preg_match($hostRe, $imapHost)) $fail('Enter the incoming (IMAP) server, e.g. imap.gmail.com or mail.yourdomain.com.');
        if (!preg_match($hostRe, $smtpHost)) $fail('Enter the outgoing (SMTP) server, e.g. smtp.gmail.com or mail.yourdomain.com.');
        if ($imapPort < 1 || $imapPort > 65535 || $smtpPort < 1 || $smtpPort > 65535) $fail('Ports must be between 1 and 65535.');
        if (!filter_var($address, FILTER_VALIDATE_EMAIL)) $fail('Enter the full mailbox address (it is also the sign-in name).');
        if ($pass === '') $pass = sda_decrypt(sda_setting_fresh('smtp_password'));
        if ($pass === '') $fail('Enter the mailbox password (for Gmail: a 16-letter App password).');
        if (mb_strlen($pass) > 200) $fail('That password is too long.');

        foreach (['imap_host' => $imapHost, 'imap_port' => (string)$imapPort, 'smtp_host' => $smtpHost, 'smtp_port' => (string)$smtpPort,
                  'smtp_encryption' => $enc, 'smtp_username' => $address] as $k => $v) save_setting($pdo, $k, $v);
        if (($_POST['smtp_password'] ?? '') !== '') save_setting($pdo, 'smtp_password', sda_encrypt($pass));
        save_setting($pdo, 'mail_auth', 'password');
        try { $pdo->exec("UPDATE mailbox_state SET auth_failures = 0"); } catch (Throwable $e) { /* table made on first webmail use */ }
        ax_log('mail.settings', 'mailbox', null, 'Webmail set to the password mailbox ' . $address . ' (' . $imapHost . ')');

        // Try the sign-in now with exactly what was typed (setting() caches values for this request)
        $t = new SdaImap();
        $loginPass = str_contains($imapHost, 'gmail') ? str_replace(' ', '', $pass) : $pass;
        if (!$t->connect($imapHost, $imapPort, true)) {
            ax_flash('err', 'Saved, but the incoming server could not be reached: ' . $t->lastError);
        } elseif (!$t->login($address, $loginPass)) {
            ax_flash('err', 'Saved, but the mail server refused the sign-in' . (str_contains($imapHost, 'gmail') ? ' (Gmail needs an App password and IMAP turned on).' : '. Check the address and password.'));
        } else {
            ax_flash('ok', 'Connected ' . $address . '. Webmail and the academy\'s system emails now use this mailbox.');
        }
        $t->logout();
    }
    ax_back('mail#settings');
}

$pageTitle = 'Webmail';
require_once 'includes/header.php';
$acct = sda_mail_account();
$ready = sda_mail_ready();
$googleOn = $acct['auth'] === 'google' && sda_setting_fresh('gmail_refresh_token') !== '';
$isGmail = (bool)$acct['gmail'];
$oauthReady = setting('google_client_id') !== '' && setting('google_client_secret') !== '';
$val = fn($k, $d = '') => setting($k) !== '' ? setting($k) : $d;
$v = fn($f) => (int)@filemtime(__DIR__ . '/../' . $f);
require_once 'includes/sidebar.php';
?>
<link rel="stylesheet" href="../assets/css/sda-mailbox.css?v=<?= $v('assets/css/sda-mailbox.css') ?>">
<main class="main-content">
    <?php ax_header('Webmail', '', $ready ? $acct['address'] . ($googleOn ? ' · Gmail' : ' · ' . $acct['imap_host']) : 'Not connected yet',
        $ready ? '<a class="ax-btn ghost" href="#settings" data-mx-settings><i class="fas fa-gear"></i> Settings</a>' : ''); ?>
    <div class="ax-body mx-page">
        <?php ax_alerts(); ?>

        <?php if ($ready): ?>
        <div id="mxRoot" data-api="api-mailbox" data-csrf="<?= e(csrfToken()) ?>" data-address="<?= e($acct['address']) ?>"
             data-name="<?= e($acct['name']) ?>" data-gmail="<?= $isGmail ? '1' : '0' ?>" data-brand="<?= e(setting('academy_name') ?: 'Skope Digital Academy') ?>">
            <div class="mx-boot"><i class="fas fa-circle-notch fa-spin"></i> Loading mailbox…</div>
        </div>
        <noscript><div class="ax-alert err"><i class="fas fa-circle-exclamation"></i><div>Webmail needs JavaScript.</div></div></noscript>
        <?php else: ?>
        <section class="ax-panel mx-intro">
            <div class="ax-panel-b">
                <span class="ax-icon-badge"><i class="fas fa-envelope-open-text"></i></span>
                <div>
                    <h2>Connect the academy mailbox</h2>
                    <p>Webmail reads and sends the academy's email (inbox, sent, drafts, labels, search) right here in the admin panel.
                       Connect the Gmail account <b><?= e($acct['address'] ?: 'skopedigitalacademy@gmail.com') ?></b> with Google sign-in, or use any mailbox with a password.
                       The same mailbox then sends the academy's system emails (password resets, receipts).</p>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section class="ax-panel mx-acct" id="settings" <?= $ready ? 'hidden' : '' ?>>
            <div class="ax-panel-h"><div><h2><i class="fas fa-plug"></i> Mailbox connection</h2><p>Which mailbox Webmail and the academy's emails use, and how it signs in.</p></div>
                <span class="ax-pill <?= $ready ? 'ok' : 'warn' ?>"><?= $ready ? 'Connected' : 'Not connected' ?></span></div>
            <div class="ax-panel-b">
                <div class="mx-acct-status">
                    <span class="ax-icon-badge" style="background:<?= $ready ? 'var(--success-glow);color:var(--success)' : 'var(--warning-glow);color:var(--warning)' ?>"><i class="<?= $googleOn ? 'fab fa-google' : ($ready ? 'fas fa-key' : 'fas fa-plug-circle-xmark') ?>"></i></span>
                    <div>
                        <?php if ($googleOn): ?>
                            <b><?= e($acct['address']) ?></b><small>Connected with Google sign-in (OAuth). No password is stored; Gmail can be disconnected at any time.</small>
                        <?php elseif ($ready): ?>
                            <b><?= e($acct['address']) ?></b><small>Signed in with a mailbox password · IMAP <?= e($acct['imap_host'] . ':' . $acct['imap_port']) ?> · SMTP <?= e($acct['smtp_host'] . ':' . $acct['smtp_port']) ?> (<?= e(strtoupper($acct['smtp_secure'])) ?>)</small>
                        <?php else: ?>
                            <b>No mailbox connected</b><small>Connect Gmail below (recommended), or fill in a mailbox password.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mx-acct-grid">
                    <div class="mx-acct-card">
                        <h3><i class="fab fa-google"></i> Gmail (recommended)</h3>
                        <p>Sign in with Google and allow mail access. Nothing else to set up: no App password or 2-Step Verification needed.</p>
                        <div class="ax-form-actions" style="justify-content:flex-start">
                            <a class="ax-btn" href="mail-connect?start=1"><i class="fab fa-google"></i> <?= $googleOn ? 'Reconnect Gmail' : 'Connect Gmail' ?></a>
                            <?php if ($googleOn): ?>
                            <form method="post" action="mail-connect" data-confirm="Disconnect Gmail? Webmail and the academy's system emails stop working until a mailbox is connected again." data-danger>
                                <?= ax_csrf() ?><input type="hidden" name="disconnect" value="1">
                                <button class="ax-btn danger" type="submit"><i class="fas fa-link-slash"></i> Disconnect</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!$oauthReady): ?><p class="mx-acct-hint"><i class="fas fa-triangle-exclamation"></i> Add the Google Client ID and Client secret in <a href="settings#signin">Platform Settings &rsaquo; Sign-in</a> first.</p><?php endif; ?>
                        <p class="mx-acct-hint">In Google Cloud Console, the OAuth client needs this <b>Authorized redirect URI</b>: <span class="ax-code" style="overflow-wrap:anywhere;word-break:break-all"><?= e(sda_gmail_redirect_uri()) ?></span></p>
                    </div>

                    <form method="post" class="mx-acct-card ax-form" autocomplete="off">
                        <?= ax_csrf() ?><input type="hidden" name="action" value="save_password_account">
                        <h3><i class="fas fa-key"></i> Or use a mailbox password</h3>
                        <p>Any IMAP/SMTP mailbox (e.g. info@yourdomain on cPanel), or Gmail with an App password.</p>
                        <div class="ax-row">
                            <div class="ax-field"><label for="mx-addr">Mailbox address</label><input id="mx-addr" type="email" name="smtp_username" required value="<?= e($val('smtp_username')) ?>" placeholder="info@yourdomain.com"></div>
                            <div class="ax-field"><label for="mx-pass">Password</label><input id="mx-pass" type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= setting('smtp_password') !== '' ? 'Leave blank to keep the saved password' : 'Not saved yet' ?>"></div>
                            <div class="ax-field"><label for="mx-ih">Incoming server (IMAP)</label><input id="mx-ih" type="text" name="imap_host" required value="<?= e($val('imap_host', str_contains($val('smtp_host'), 'gmail') ? 'imap.gmail.com' : '')) ?>" placeholder="imap.gmail.com"></div>
                            <div class="ax-field"><label for="mx-ip">IMAP port (SSL)</label><input id="mx-ip" type="number" name="imap_port" min="1" max="65535" value="<?= e($val('imap_port', '993')) ?>"></div>
                            <div class="ax-field"><label for="mx-sh">Outgoing server (SMTP)</label><input id="mx-sh" type="text" name="smtp_host" required value="<?= e($val('smtp_host')) ?>" placeholder="smtp.gmail.com"></div>
                            <div class="ax-field"><label for="mx-sp">SMTP port</label><input id="mx-sp" type="number" name="smtp_port" min="1" max="65535" value="<?= e($val('smtp_port', '587')) ?>"></div>
                            <div class="ax-field"><label for="mx-se">Encryption</label><select id="mx-se" name="smtp_encryption"><?php foreach (['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'] as $k => $l): ?><option value="<?= $k ?>" <?= $val('smtp_encryption', 'tls') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="ax-form-actions"><button class="ax-btn ghost" type="submit"><i class="fas fa-floppy-disk"></i> Save and test sign-in</button></div>
                    </form>
                </div>

                <?php if ($isGmail): ?>
                <div class="mx-acct-card mx-gm-links">
                    <h3><i class="fas fa-sliders"></i> Gmail's own settings</h3>
                    <p>Forwarding, filters and the vacation responder run inside Gmail. Open them while signed in to <?= e($acct['address'] ?: 'the academy Gmail') ?>:</p>
                    <div class="ax-form-actions" style="justify-content:flex-start;flex-wrap:wrap">
                        <a class="ax-btn ghost" href="https://mail.google.com/mail/u/0/#settings/fwdandpop" target="_blank" rel="noopener"><i class="fas fa-share"></i> Forwarding &amp; IMAP</a>
                        <a class="ax-btn ghost" href="https://mail.google.com/mail/u/0/#settings/filters" target="_blank" rel="noopener"><i class="fas fa-filter"></i> Filters &amp; blocked addresses</a>
                        <a class="ax-btn ghost" href="https://mail.google.com/mail/u/0/#settings/general" target="_blank" rel="noopener"><i class="fas fa-umbrella-beach"></i> Vacation responder</a>
                        <a class="ax-btn ghost" href="https://mail.google.com/mail/u/0/#settings/labels" target="_blank" rel="noopener"><i class="fas fa-tags"></i> Labels in IMAP</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<?php if ($ready): ?><script src="../assets/js/sda-mailbox.js?v=<?= $v('assets/js/sda-mailbox.js') ?>"></script><?php endif; ?>
</body></html>
