<?php
/** Admin: platform settings – general details, payments, email, AI and a security check-up. */
$pageTitle = 'Platform Settings';
require_once 'includes/header.php';

/* key => [label, type, hint, section]; type: text|email|url|number|select:a,b|bool|secret */
$fields = [
    'academy_name'      => ['Academy name', 'text', 'Used in emails and page titles.', 'general'],
    'support_email'     => ['Support email', 'email', 'Where learners can write for help.', 'general'],
    'support_phone'     => ['Support phone', 'text', 'Shown to learners who need to call.', 'general'],
    'paystack_public_key' => ['Paystack public key', 'text', 'Starts with pk_test_ or pk_live_.', 'payments'],
    'paystack_secret_key' => ['Paystack secret key', 'secret', 'Starts with sk_test_ or sk_live_. Never share it.', 'payments'],
    'mail_from_name'    => ['Sender name', 'text', 'The name people see on emails from the academy.', 'email'],
    'mail_from_address' => ['Sender address', 'email', 'e.g. no-reply@yourdomain.com', 'email'],
    'smtp_host'         => ['SMTP server', 'text', 'e.g. mail.yourdomain.com. Leave empty to use the server\'s built-in mail.', 'email'],
    'smtp_port'         => ['SMTP port', 'number', 'Usually 465 (SSL) or 587 (TLS).', 'email'],
    'smtp_encryption'   => ['Encryption', 'select:tls,ssl,none', '', 'email'],
    'smtp_username'     => ['SMTP username', 'text', 'For Gmail: the full Gmail address.', 'email'],
    'smtp_password'     => ['SMTP password', 'secret', 'For Gmail: a 16-letter App password from myaccount.google.com/apppasswords (needs 2-Step Verification), not the normal password.', 'email'],
    'google_client_id'  => ['Google Client ID', 'text', 'From Google Cloud Console > APIs & Services > Credentials (OAuth client, type Web application). Ends in .apps.googleusercontent.com. Leave empty to hide the Google button.', 'signin'],
    'ai_enabled'        => ['AI features on', 'bool', 'Turns the AI study helpers on or off.', 'ai'],
    'gemini_api_key'    => ['Gemini API key', 'secret', 'From Google AI Studio.', 'ai'],
    'gemini_model'      => ['Gemini model', 'text', 'e.g. gemini-1.5-pro', 'ai'],
];
$sections = [
    'general'  => ['General', 'building-columns', 'Basic details about the academy.'],
    'payments' => ['Payments', 'credit-card', 'Paystack keys for card and M-Pesa checkout.'],
    'email'    => ['Email', 'envelope', 'How the academy sends email (password resets, welcome emails, receipts).'],
    'signin'   => ['Sign-in', 'right-to-bracket', '"Continue with Google" on the sign-in and sign-up pages.'],
    'ai'       => ['AI', 'wand-magic-sparkles', 'Google Gemini powers the AI helpers.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    ax_post_guard();
    require_once __DIR__ . '/../includes/mailer.php';
    $to = trim((string)$_POST['test_email']);
    $err = '';
    $ok = sda_mail($to, 'Test email from Skope Digital Academy',
        '<h2 style="margin:0 0 12px;color:#0f172a">It works!</h2><p>This test was sent from Admin &gt; Settings &gt; Email at ' . date('j M Y, H:i') . '.</p>'
        . '<p>Password resets and welcome emails will be delivered the same way.</p>', '', $err);
    ax_flash($ok ? 'ok' : 'err', $ok ? 'Test email sent to ' . $to . '. Check that inbox (and Spam).' : 'Could not send: ' . $err);
    ax_back('settings#email');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $sec = $_POST['section'] ?? '';
    if (!isset($sections[$sec])) ax_back();
    $changed = 0;
    foreach ($fields as $key => [$label, $type, , $s]) {
        if ($s !== $sec) continue;
        $v = trim((string)($_POST[$key] ?? ''));
        if ($type === 'secret') {
            if (!empty($_POST['clear_' . $key])) { save_setting($pdo, $key, ''); $changed++; continue; }
            if ($v === '') continue;                         // blank = keep the saved value
            $v = mb_substr($v, 0, 500);
        } elseif ($type === 'bool') {
            $v = isset($_POST[$key]) ? '1' : '0';
        } elseif ($type === 'email') {
            if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { ax_flash('err', $label . ' is not a valid email address.'); ax_back(); }
        } elseif ($type === 'number') {
            $v = $v === '' ? '' : (string)max(0, min(65535, (int)$v));
        } elseif (str_starts_with($type, 'select:')) {
            if (!in_array($v, explode(',', substr($type, 7)), true)) $v = explode(',', substr($type, 7))[0];
        } else {
            $v = mb_substr($v, 0, 255);
        }
        if ($key === 'google_client_id' && $v !== '' && !preg_match('/^[\w-]+\.apps\.googleusercontent\.com$/', $v)) { ax_flash('err', 'The Google Client ID should end in .apps.googleusercontent.com.'); ax_back(); }
        if ($key === 'paystack_public_key' && $v !== '' && !preg_match('/^pk_(test|live)_\w+$/', $v)) { ax_flash('err', 'The Paystack public key should start with pk_test_ or pk_live_.'); ax_back(); }
        if ($key === 'paystack_secret_key' && !preg_match('/^sk_(test|live)_\w+$/', $v)) { ax_flash('err', 'The Paystack secret key should start with sk_test_ or sk_live_.'); ax_back(); }
        save_setting($pdo, $key, $v);
        $changed++;
    }
    ax_flash('ok', $sections[$sec][0] . ' settings saved.');
    ax_back('settings#' . $sec);
}

/* Current values straight from the table (setting() caches; fine here, but be explicit). */
$saved = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM platform_settings")->fetchAll() as $r) $saved[$r['setting_key']] = (string)$r['setting_value'];
$val = fn($k) => $saved[$k] ?? '';
$last4 = fn(string $s) => $s === '' ? '' : '•••• ' . substr($s, -4);

/* What the code uses today (keys defined in PHP files) – read as text, never executed here. */
$fileConst = function (string $file, string $name): string {
    $src = @file_get_contents(__DIR__ . '/../includes/' . $file);
    return ($src && preg_match("/define\(\s*'" . preg_quote($name, '/') . "'\s*,\s*'([^']*)'/", $src, $m)) ? $m[1] : '';
};
$liveKeys = [
    'paystack_public_key' => $fileConst('paystack.php', 'PAYSTACK_PUBLIC_KEY'),
    'paystack_secret_key' => $fileConst('paystack.php', 'PAYSTACK_SECRET_KEY'),
    'gemini_api_key'      => $fileConst('ai_config.php', 'GEMINI_API_KEY'),
];
$liveModel = preg_match('~models/([\w.-]+):~', $fileConst('ai_config.php', 'GEMINI_API_URL'), $mm) ? $mm[1] : '';
$payMode = str_starts_with($liveKeys['paystack_secret_key'], 'sk_live_') ? 'Live' : ($liveKeys['paystack_secret_key'] ? 'Test' : 'Not set');

/* Security check-up (read-only, real values) */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$cookie = session_get_cookie_params();
$checks = [
    ['Site served over HTTPS', $https, $https ? 'Connections are encrypted.' : 'This copy runs on plain HTTP – fine locally, but the live site must use HTTPS.'],
    ['Secure session cookie', !empty($cookie['secure']), !empty($cookie['secure']) ? 'Sign-in cookie only travels over HTTPS.' : 'Turn on cookie_secure in includes/auth.php once the site is on HTTPS.'],
    ['PHP errors hidden from visitors', !ini_get('display_errors'), ini_get('display_errors') ? 'display_errors is on – switch it off on the live server.' : 'Errors go to the log, not the page.'],
    ['Public site address set', setting('site_url') !== '', setting('site_url') !== '' ? setting('site_url') : 'Set it in Learning Settings so emailed links work.'],
];
$sec = [
    'admins'  => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'"),
    'temp'    => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE requires_password_change = 1"),
    'susp'    => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE status = 'suspended'"),
    'resets'  => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM password_resets WHERE created_at >= NOW() - INTERVAL 7 DAY"),
];
$recentLog = [];
try { $recentLog = $pdo->query("SELECT a.action, a.description, a.created_at, u.name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 6")->fetchAll(); } catch (Throwable $e) {}

require_once 'includes/sidebar.php';
?>
<style>
  .main-content { min-width: 0; }
  .st-nav { position: sticky; top: 90px; }
  .st-nav a { display: flex; gap: 10px; align-items: center; padding: 10px 12px; border-radius: 12px; text-decoration: none; color: var(--text-dim); font-weight: 700; font-size: .86rem; }
  .st-nav a:hover { background: var(--surface-2); color: var(--text-main); }
  .st-nav a i { width: 18px; color: var(--primary); }
  .st-sec { scroll-margin-top: 90px; }
  .st-note { display: flex; gap: 10px; align-items: flex-start; font-size: .8rem; color: var(--text-dim); background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 10px 12px; margin-bottom: 16px; line-height: 1.5; }
  .st-note i { color: var(--warning); margin-top: 2px; }
  .st-saved { font-size: .74rem; color: var(--text-muted); margin-top: 5px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
  .st-link { display: flex; gap: 14px; align-items: center; text-decoration: none; }
  .st-link b { display: block; color: var(--text-main); }
  .st-link small { color: var(--text-dim); font-size: .8rem; }
  .st-check { display: flex; gap: 12px; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid var(--border); }
  .st-check:last-child { border-bottom: 0; }
  .st-check b { display: block; color: var(--text-main); font-size: .88rem; }
  .st-check small { color: var(--text-dim); font-size: .78rem; overflow-wrap: anywhere; }
  .st-check .ok { color: var(--success); } .st-check .no { color: var(--warning); }
  @media (max-width: 1200px) { .st-nav { position: static; } .st-nav nav { display: flex; flex-wrap: wrap; gap: 4px; } }
</style>
<main class="main-content">
    <?php ax_header('Platform', 'Settings', 'General · Payments · Email · Sign-in · AI · Security'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-grid">
            <aside class="ax-span-4 ax-stack st-nav">
                <section class="ax-panel"><div class="ax-panel-b" style="padding:12px">
                    <nav aria-label="Settings sections">
                        <?php foreach ($sections as $k => [$l, $ic]): ?><a href="#<?= $k ?>"><i class="fas fa-<?= $ic ?>"></i><?= $l ?></a><?php endforeach; ?>
                        <a href="#security"><i class="fas fa-shield-halved"></i>Security</a>
                    </nav>
                </div></section>
                <a class="ax-panel ax-panel-b st-link" href="learning-settings" style="display:flex">
                    <span class="ax-icon-badge" style="background:var(--primary-glow);color:var(--primary)"><i class="fas fa-sliders"></i></span>
                    <span style="flex:1;min-width:0"><b>Learning settings</b><small>Currency, certificate &amp; registration fees, access periods, structured tracks, site address.</small></span>
                    <i class="fas fa-arrow-right" style="color:var(--text-muted)"></i>
                </a>
            </aside>

            <div class="ax-span-8">
            <?php foreach ($sections as $sk => [$sl, $si, $sd]): ?>
                <section class="ax-panel st-sec" id="<?= $sk ?>">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-<?= $si ?>"></i> <?= $sl ?></h2><p><?= e($sd) ?></p></div>
                        <?php if ($sk === 'payments'): ?><span class="ax-pill <?= $payMode === 'Live' ? 'ok' : 'warn' ?>">Paystack: <?= $payMode ?> mode</span><?php endif; ?>
                    </div>
                    <form method="post" class="ax-panel-b ax-form" autocomplete="off">
                        <?= ax_csrf() ?><input type="hidden" name="section" value="<?= $sk ?>">
                        <?php if ($sk === 'payments' || $sk === 'ai'): ?>
                        <div class="st-note"><i class="fas fa-circle-info"></i><div><?= $sk === 'payments' ? 'Checkout currently reads its keys' : 'The AI helpers currently read their key' ?> from <span class="ax-code"><?= $sk === 'payments' ? 'includes/paystack.php' : 'includes/ai_config.php' ?></span><?php if ($sk === 'payments'): ?> (in use: public <?= e($last4($liveKeys['paystack_public_key']) ?: '—') ?>, secret <?= e($last4($liveKeys['paystack_secret_key']) ?: '—') ?>)<?php else: ?> (in use: key <?= e($last4($liveKeys['gemini_api_key']) ?: '—') ?><?= $liveModel ? ', model ' . e($liveModel) : '' ?>)<?php endif; ?>. Keys saved here are stored for the switch-over to admin-managed keys.</div></div>
                        <?php elseif ($sk === 'email'): ?>
                        <div class="st-note"><i class="fas fa-circle-info"></i><div><?= $val('smtp_host') !== '' && $val('smtp_username') !== '' && $val('smtp_password') !== '' ? 'Emails are sent through <b>' . e($val('smtp_host')) . '</b> as ' . e($val('smtp_username')) . '.' : 'SMTP is not complete, so emails fall back to the server’s built-in mail (often lands in spam or fails). For Gmail use smtp.gmail.com, port 587, TLS.' ?></div></div>
                        <?php elseif ($sk === 'signin'): ?>
                        <div class="st-note"><i class="fas fa-circle-info"></i><div>In the OAuth client, add these under <b>Authorized JavaScript origins</b>: <span class="ax-code"><?= e(setting('site_url') !== '' ? preg_replace('~^(https?://[^/]+).*$~', '$1', setting('site_url')) : 'https://your-domain') ?></span>, <span class="ax-code">http://localhost</span> and <span class="ax-code">http://localhost:80</span>. New Google sign-ups become learner accounts and go to the activation checkout like normal sign-ups.</div></div>
                        <?php endif; ?>
                        <div class="ax-row">
                        <?php foreach ($fields as $key => [$label, $type, $hint, $s]): if ($s !== $sk) continue; $id = 'f-' . $key; $cur = $val($key); ?>
                            <?php if ($type === 'bool'): ?>
                            <div class="ax-field" style="grid-column:1/-1"><label class="ax-check"><input type="checkbox" name="<?= $key ?>" <?= ($cur === '' ? '1' : $cur) === '1' ? 'checked' : '' ?>> <?= e($label) ?></label><?= $hint ? '<div class="hint">' . e($hint) . '</div>' : '' ?></div>
                            <?php elseif (str_starts_with($type, 'select:')): ?>
                            <div class="ax-field"><label for="<?= $id ?>"><?= e($label) ?></label><select id="<?= $id ?>" name="<?= $key ?>"><?php foreach (explode(',', substr($type, 7)) as $o): ?><option value="<?= $o ?>" <?= $cur === $o ? 'selected' : '' ?>><?= $o === 'none' ? 'None' : strtoupper($o) ?></option><?php endforeach; ?></select><?= $hint ? '<div class="hint">' . e($hint) . '</div>' : '' ?></div>
                            <?php elseif ($type === 'secret'): ?>
                            <div class="ax-field"><label for="<?= $id ?>"><?= e($label) ?></label><input id="<?= $id ?>" type="password" name="<?= $key ?>" value="" placeholder="<?= $cur !== '' ? 'Leave blank to keep the saved key' : 'Not saved yet' ?>" autocomplete="new-password">
                                <div class="st-saved"><?php if ($cur !== ''): ?><span>Saved: <span class="ax-code"><?= e($last4($cur)) ?></span></span><label class="ax-check" style="font-size:.74rem"><input type="checkbox" name="clear_<?= $key ?>" style="width:14px;height:14px"> Remove</label><?php else: ?><span>Nothing saved</span><?php endif; ?></div>
                                <?= $hint ? '<div class="hint">' . e($hint) . '</div>' : '' ?></div>
                            <?php else: ?>
                            <div class="ax-field"><label for="<?= $id ?>"><?= e($label) ?></label><input id="<?= $id ?>" type="<?= $type ?>" name="<?= $key ?>" value="<?= e($cur) ?>" <?= $type === 'number' ? 'min="0" max="65535"' : '' ?> placeholder="<?= e($key === 'academy_name' ? 'Skope Digital Academy' : ($key === 'gemini_model' ? $liveModel : '')) ?>"><?= $hint ? '<div class="hint">' . e($hint) . '</div>' : '' ?></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </div>
                        <div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save <?= $sk === 'ai' ? 'AI' : strtolower($sl) ?> settings</button></div>
                    </form>
                    <?php if ($sk === 'email'): ?>
                    <form method="post" class="ax-panel-b ax-form" style="border-top:1px solid var(--border)">
                        <?= ax_csrf() ?>
                        <div class="ax-row"><div class="ax-field"><label for="f-test">Send a test email to</label><input id="f-test" type="email" name="test_email" required value="<?= e($val('support_email') ?: ($_SESSION['user_email'] ?? '')) ?>"></div></div>
                        <div class="ax-form-actions"><button class="ax-btn ghost"><i class="fas fa-paper-plane"></i> Send test email</button></div>
                        <?php $mlog = []; try { $mlog = $pdo->query("SELECT recipient, subject, status, error, created_at FROM mail_log ORDER BY id DESC LIMIT 8")->fetchAll(); } catch (Throwable $e) {} ?>
                        <?php if ($mlog): ?>
                        <h3 style="font:800 .72rem Inter,sans-serif;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin:18px 0 6px">Recent emails</h3>
                        <ul class="ax-list"><?php foreach ($mlog as $m): ?><li><div class="grow"><b style="font-size:.84rem;color:var(--text-main)"><?= e($m['subject']) ?></b><div style="font-size:.74rem;color:var(--text-muted)"><?= e($m['recipient']) ?> · <?= ax_ago($m['created_at']) ?><?= $m['error'] ? ' · ' . e($m['error']) : '' ?></div></div><span class="ax-pill <?= $m['status'] === 'sent' ? 'ok' : 'warn' ?>"><?= e($m['status']) ?></span></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

                <section class="ax-panel st-sec" id="security">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-shield-halved"></i> Security</h2><p>A live check-up of this installation. Nothing here changes on its own.</p></div></div>
                    <div class="ax-panel-b">
                        <div class="ax-stats" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:18px">
                            <?= ax_stat('user-shield', $sec['admins'], 'Active admins', '', 'red') ?>
                            <?= ax_stat('key', $sec['temp'], 'On a temp password', '', 'amber') ?>
                            <?= ax_stat('user-slash', $sec['susp'], 'Suspended', '', 'slate') ?>
                            <?= ax_stat('unlock-keyhole', $sec['resets'], 'Reset requests (7 d)', '', 'violet') ?>
                        </div>
                        <?php foreach ($checks as [$l, $ok, $d]): ?>
                        <div class="st-check"><i class="fas fa-<?= $ok ? 'circle-check ok' : 'triangle-exclamation no' ?>" style="margin-top:3px"></i><div><b><?= e($l) ?></b><small><?= e($d) ?></small></div></div>
                        <?php endforeach; ?>
                        <?php if ($recentLog): ?>
                        <h3 style="font:800 .72rem Inter,sans-serif;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin:20px 0 6px">Recent admin actions</h3>
                        <ul class="ax-list"><?php foreach ($recentLog as $l): ?><li><div class="grow"><b style="font-size:.84rem;color:var(--text-main)"><?= e($l['description'] ?: $l['action']) ?></b><div style="font-size:.74rem;color:var(--text-muted)"><?= e($l['name'] ?? 'System') ?> · <?= ax_ago($l['created_at']) ?></div></div></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                        <div class="ax-form-actions" style="margin-top:14px"><a class="ax-btn ghost" href="users?status=suspended"><i class="fas fa-users"></i> Review accounts</a><a class="ax-btn ghost" href="profile"><i class="fas fa-lock"></i> Change my password</a></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
