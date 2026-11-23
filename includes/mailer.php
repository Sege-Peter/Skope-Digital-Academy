<?php
/**
 * Outgoing email for the academy.
 *  - sda_mail()          one system email (notifications, password resets) in the academy layout
 *  - sda_mime_build()    build a full MIME message (cc/bcc, reply threading, attachments, .ics invites)
 *  - sda_smtp_deliver()  send a raw message through the academy mailbox (Gmail OAuth or SMTP password)
 * The mailbox is set up in Admin > Webmail > Settings (see includes/mail_account.php).
 * Every system email is logged in mail_log; failed ones can be retried from Admin > Email log.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';
require_once __DIR__ . '/mail_account.php';

/**
 * Send an HTML email. $html is the inner content; it is wrapped in the academy's email layout.
 * $opts: reply_to, attachments [[name, type, data]], ics (calendar text), event (notification key), user_id
 */
function sda_mail(string $to, string $subject, string $html, string $reply_to = '', ?string &$err = null, array $opts = []): bool
{
    $err = '';
    $to = trim($to);
    $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $err = 'Invalid recipient address.'; return false; }
    if ($reply_to !== '' && !filter_var($reply_to, FILTER_VALIDATE_EMAIL)) $reply_to = '';

    // Local copy safety net: never email real learners from a test database.
    if (($sandbox = sda_mail_sandbox_target($to)) === '') {
        $err = 'Local test copy: held (no academy mailbox connected to redirect it to).';
        sda_mail_log($to, $subject, 'failed', $err, $opts);
        return false;
    }
    if ($sandbox !== null) {
        $html = '<p style="background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:10px 12px;border-radius:8px;font-size:13px">Local test copy: this email was meant for <strong>' . htmlspecialchars($to) . '</strong> and was redirected here.</p>' . $html;
        $subject = '[Test → ' . $to . '] ' . $subject;
        $to = $sandbox;
    }
    $body = sda_mail_layout($subject, $html, $opts['unsubscribe'] ?? '');
    try {
        if (sda_mail_ready()) {
            $a = sda_mail_account();
            $raw = sda_mime_build([
                'from' => $a['address'], 'from_name' => $a['name'], 'to' => [$to], 'subject' => $subject,
                'html' => $body, 'text' => sda_mail_text($html), 'reply_to' => $reply_to,
                'attachments' => $opts['attachments'] ?? [], 'ics' => $opts['ics'] ?? '',
                'list_unsubscribe' => $opts['unsubscribe'] ?? '',
            ]);
            sda_smtp_deliver([$to], $raw);
        } else {
            $from = setting('mail_from_address') ?: 'no-reply@skopedigital.co.ke';
            $h = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
               . 'From: ' . sda_mail_encode(setting('mail_from_name') ?: 'Skope Digital Academy') . " <$from>\r\n" . ($reply_to ? "Reply-To: $reply_to\r\n" : '');
            if (!@mail($to, sda_mail_encode($subject), $body, $h)) {
                throw new RuntimeException('Email is not set up: connect the mailbox in Admin > Webmail > Settings.');
            }
        }
        sda_mail_log($to, $subject, 'sent', '', $opts);
        return true;
    } catch (Throwable $e) {
        $err = $e->getMessage();
        error_log('[sda mailer] ' . $err);
        sda_mail_log($to, $subject, 'failed', $err, $opts + ['html' => $html, 'reply_to' => $reply_to]);
        return false;
    }
}

/**
 * Sandbox: on a local copy (site address is localhost) mail to anyone except the academy mailbox and
 * active admins goes to the academy mailbox instead. Setting mail_sandbox: 'auto' (default), '1' always, '0' never.
 * Returns the address to redirect to, or null to send normally.
 */
function sda_mail_sandbox_target(string $to): ?string
{
    global $pdo;
    $mode = setting('mail_sandbox') ?: 'auto';
    if ($mode === '0') return null;
    if ($mode === 'auto' && !preg_match('#^https?://(localhost|127\.0\.0\.1)([:/]|$)#', sda_site_base())) return null;
    $a = sda_mail_account();
    $allowed = array_map('strtolower', array_filter([$a['address'], setting('support_email')]));
    try { $allowed = array_merge($allowed, array_map('strtolower', $pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN))); } catch (Throwable $e) {}
    if (in_array(strtolower($to), $allowed, true)) return null;
    return $a['address'] !== '' ? $a['address'] : '';   // '' = hold: no mailbox to redirect to
}

/* ── MIME ───────────────────────────────────────────────────────────────── */

function sda_mail_encode(string $s): string
{
    return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

function sda_addr(string $email, string $name = ''): string
{
    $name = trim(str_replace(['"', "\r", "\n"], '', $name));
    return $name === '' ? "<$email>" : (preg_match('/[^\x20-\x7e]/', $name) ? sda_mail_encode($name) : '"' . $name . '"') . " <$email>";
}

function sda_message_id(string $from): string
{
    return '<sda-' . bin2hex(random_bytes(10)) . '@' . (explode('@', $from)[1] ?? 'skopedigital.co.ke') . '>';
}

/**
 * Build a raw RFC 5322 message.
 * $m: from, from_name, to[], cc[], bcc[] (not written as a header), subject, html, text, reply_to,
 *     in_reply_to, references, message_id, attachments [[name,type,data,cid?]], ics, list_unsubscribe
 * Entries in to/cc may be "Name <a@b>" strings or plain addresses.
 */
function sda_mime_build(array $m): string
{
    $from = $m['from'];
    $msgid = $m['message_id'] ?? sda_message_id($from);
    $list = fn(array $xs) => implode(', ', array_map(fn($x) => preg_match('/^(.*)<([^>]+)>$/', trim($x), $mm) ? sda_addr(trim($mm[2]), trim($mm[1], " \"")) : '<' . trim($x) . '>', $xs));
    $h = [
        'Date: ' . date('r'),
        'From: ' . sda_addr($from, $m['from_name'] ?? ''),
        'To: ' . ($m['to'] ? $list($m['to']) : 'undisclosed-recipients:;'),
    ];
    if (!empty($m['cc'])) $h[] = 'Cc: ' . $list($m['cc']);
    $h[] = 'Subject: ' . sda_mail_encode($m['subject'] ?? '');
    $h[] = "Message-ID: $msgid";
    if (!empty($m['reply_to'])) $h[] = 'Reply-To: <' . $m['reply_to'] . '>';
    if (!empty($m['in_reply_to'])) $h[] = 'In-Reply-To: ' . $m['in_reply_to'];
    if (!empty($m['references'])) $h[] = 'References: ' . $m['references'];
    if (!empty($m['list_unsubscribe'])) { $h[] = 'List-Unsubscribe: <' . $m['list_unsubscribe'] . '>'; $h[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click'; }
    $h[] = 'MIME-Version: 1.0';
    $h[] = 'X-Mailer: Skope Digital Academy';

    // One pass. str_replace(["\r\n", "\r", "\n"], "\r\n") runs the pairs in turn and turns \r\n into \r\r\n\r\n.
    $nl = fn(string $s) => preg_replace('/\r\n|\r|\n/', "\r\n", $s);
    $html = (string)($m['html'] ?? '');
    $text = (string)($m['text'] ?? ($html !== '' ? sda_mail_text($html) : ''));
    $b64 = fn(string $s) => chunk_split(base64_encode($s));

    $alt = 'alt-' . bin2hex(random_bytes(8));
    $body = "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n"
        . "--$alt\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($nl($text)) . "\r\n";
    if ($html !== '') $body .= "--$alt\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($html) . "\r\n";
    if (!empty($m['ics'])) $body .= "--$alt\r\nContent-Type: text/calendar; charset=utf-8; method=PUBLISH\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($nl($m['ics'])) . "\r\n";
    $body .= "--$alt--\r\n";

    $atts = $m['attachments'] ?? [];
    if (!empty($m['ics'])) $atts[] = ['name' => 'invite.ics', 'type' => 'text/calendar', 'data' => $nl($m['ics'])];
    if ($atts) {
        $mix = 'mix-' . bin2hex(random_bytes(8));
        $out = "Content-Type: multipart/mixed; boundary=\"$mix\"\r\n\r\n--$mix\r\n" . $body . "\r\n";
        foreach ($atts as $at) {
            $name = str_replace(['"', "\r", "\n"], '', (string)$at['name']);
            $enc = preg_match('/[^\x20-\x7e]/', $name) ? "filename*=utf-8''" . rawurlencode($name) : "filename=\"$name\"";
            $type = preg_match('#^[\w.+-]+/[\w.+-]+$#', (string)($at['type'] ?? '')) ? $at['type'] : 'application/octet-stream';
            $cid = !empty($at['cid']) ? "Content-ID: <{$at['cid']}>\r\n" : '';
            $out .= "--$mix\r\nContent-Type: $type; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n{$cid}Content-Disposition: "
                . (!empty($at['cid']) ? 'inline' : 'attachment') . "; $enc\r\n\r\n" . $b64((string)$at['data']) . "\r\n";
        }
        $body = $out . "--$mix--\r\n";
    }
    return implode("\r\n", $h) . "\r\n" . $body;
}

/* ── SMTP ───────────────────────────────────────────────────────────────── */

/** Send a raw message to the given recipients (to + cc + bcc) through the academy mailbox. */
function sda_smtp_deliver(array $rcpts, string $raw): void
{
    $a = sda_mail_account();
    $rcpts = array_values(array_unique(array_filter(array_map(function ($x) {
        $x = trim($x);
        if (preg_match('/<([^>]+)>/', $x, $m)) $x = $m[1];
        return filter_var($x, FILTER_VALIDATE_EMAIL) ? strtolower($x) : '';
    }, $rcpts))));
    if (!$rcpts) throw new RuntimeException('No valid recipient.');
    if ($a['smtp_host'] === '') throw new RuntimeException('No outgoing mail server is set.');

    $host = ($a['smtp_secure'] === 'ssl' ? 'ssl://' : '') . $a['smtp_host'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = false;
    for ($try = 0; $try < 2 && !$fp; $try++) {
        if ($try) sleep(2);
        $fp = @stream_socket_client("$host:{$a['smtp_port']}", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    }
    if (!$fp) throw new RuntimeException("Cannot reach {$a['smtp_host']}:{$a['smtp_port']} ($errstr)");
    stream_set_timeout($fp, 30);

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) { $out .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
        return $out;
    };
    $cmd = function (string $c, array $ok, string $label = '') use ($fp, $read): string {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) {
            $msg = trim($r);
            if (preg_match('/^(535|534|334)/', $msg)) {
                $msg = 'The mailbox sign-in was rejected. Reconnect Gmail (or check the App password) in Webmail > Settings.';
            }
            throw new RuntimeException('SMTP error' . ($label ? " at $label" : '') . ': ' . $msg);
        }
        return $r;
    };
    try {
        $me = parse_url(sda_site_base(), PHP_URL_HOST) ?: 'localhost';
        $cmd('', [220], 'connect');
        $cmd("EHLO $me", [250], 'EHLO');
        if ($a['smtp_secure'] === 'tls') {
            $cmd('STARTTLS', [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('Could not start a secure (TLS) connection.');
            }
            $cmd("EHLO $me", [250], 'EHLO');
        }
        sda_smtp_auth($cmd);
        $cmd("MAIL FROM:<{$a['address']}>", [250], 'sender');
        foreach ($rcpts as $r) $cmd("RCPT TO:<$r>", [250, 251], 'recipient ' . $r);
        $cmd('DATA', [354], 'DATA');
        // Dot-stuffing: a line starting with "." gets an extra "."
        $data = preg_replace('/^\./m', '..', preg_replace('/\r\n|\r|\n/', "\r\n", rtrim($raw, "\r\n")));
        $cmd($data . "\r\n.", [250], 'send');
        @fwrite($fp, "QUIT\r\n");
    } finally {
        @fclose($fp);
    }
}

/* ── log ────────────────────────────────────────────────────────────────── */

function sda_mail_log_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    // Never run DDL inside a transaction (it would COMMIT it): schema is created once, then remembered.
    if (sda_setting_fresh('mail_log_schema') === '2') return;
    if ($pdo->inTransaction()) { $done = false; return; }
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient VARCHAR(191) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        status ENUM('sent','failed') NOT NULL,
        error VARCHAR(500) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Columns added later: which notification it was, for whom, and what to resend
    $cols = array_column($pdo->query("SHOW COLUMNS FROM mail_log")->fetchAll(), 'Field');
    if (!in_array('event', $cols, true)) $pdo->exec("ALTER TABLE mail_log ADD event VARCHAR(60) NULL AFTER subject, ADD user_id INT NULL AFTER event, ADD payload MEDIUMTEXT NULL, ADD retries INT NOT NULL DEFAULT 0, ADD INDEX (status), ADD INDEX (event)");
    save_setting($pdo, 'mail_log_schema', '2');
}

function sda_mail_log(string $to, string $subject, string $status, string $error, array $opts = []): void
{
    global $pdo;
    try {
        sda_mail_log_ensure($pdo);
        // Keep what is needed to resend a failed email (small: no attachments over 1 MB)
        $payload = null;
        if ($status === 'failed' && isset($opts['html'])) {
            $p = ['html' => $opts['html'], 'reply_to' => $opts['reply_to'] ?? '', 'ics' => $opts['ics'] ?? ''];
            $payload = json_encode($p);
            if (strlen($payload) > 1000000) $payload = null;
        }
        if (!empty($opts['log_id'])) {
            $pdo->prepare("UPDATE mail_log SET status = ?, error = ?, retries = retries + 1 WHERE id = ?")
                ->execute([$status, $error === '' ? null : mb_substr($error, 0, 500), (int)$opts['log_id']]);
            return;
        }
        $pdo->prepare("INSERT INTO mail_log (recipient, subject, event, user_id, status, error, payload) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([mb_substr($to, 0, 191), mb_substr($subject, 0, 255), $opts['event'] ?? null, $opts['user_id'] ?? null, $status, $error === '' ? null : mb_substr($error, 0, 500), $payload]);
    } catch (Throwable $e) { /* logging must never break sending */ }
}

/** Retry failed system emails (from the log). Returns [sent, failed]. */
function sda_mail_retry(int $max = 20, ?int $onlyId = null): array
{
    global $pdo;
    sda_mail_log_ensure($pdo);
    $sql = "SELECT * FROM mail_log WHERE status = 'failed' AND payload IS NOT NULL AND retries < 5" . ($onlyId ? ' AND id = ' . (int)$onlyId : '') . " ORDER BY id LIMIT " . (int)$max;
    $sent = $failed = 0;
    foreach ($pdo->query($sql)->fetchAll() as $row) {
        $p = json_decode((string)$row['payload'], true) ?: [];
        $ok = sda_mail($row['recipient'], $row['subject'], (string)($p['html'] ?? ''), (string)($p['reply_to'] ?? ''), $err, ['ics' => $p['ics'] ?? '', 'log_id' => (int)$row['id']]);
        $ok ? $sent++ : $failed++;
    }
    return [$sent, $failed];
}

/* ── layout helpers ─────────────────────────────────────────────────────── */

/** Absolute site address for links in emails. */
function sda_site_url(): string
{
    return sda_site_base();
}

/** Public address used for images and links in emails (a local copy still shows the live logo). */
function sda_mail_public_base(): string
{
    $b = rtrim(sda_site_base(), '/');
    return preg_match('#^https?://(localhost|127\.0\.0\.1)([:/]|$)#', $b) ? 'https://skopedigital.co.ke' : $b;
}

/** A button for use inside email content (brand orange, works in Outlook too). */
function sda_mail_button(string $href, string $label): string
{
    $h = htmlspecialchars($href);
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:28px auto"><tr>'
        . '<td align="center" bgcolor="#FF8C00" style="border-radius:12px;background:#FF8C00;background-image:linear-gradient(135deg,#FF8C00,#FF6A00)">'
        . '<a href="' . $h . '" style="display:inline-block;padding:14px 30px;font:700 15px/1.2 Arial,Helvetica,sans-serif;color:#ffffff;text-decoration:none;border-radius:12px">'
        . htmlspecialchars($label) . ' &rarr;</a></td></tr></table>';
}

/**
 * The academy's branded email: navy header with logo, cyan-to-orange accent bar, white card, footer with
 * contacts and links. Table layout with inline styles so it holds up in Gmail, Outlook and phone mail apps.
 */
function sda_mail_layout(string $subject, string $html, string $unsubscribe = ''): string
{
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $base = sda_mail_public_base();
    $name = setting('academy_name') ?: 'Skope Digital Academy';
    $phone = trim(setting('support_phone')) ?: '0742 380 183';
    $wa = preg_replace('/^0/', '254', preg_replace('/\D+/', '', $phone));
    $emails = array_values(array_unique(array_filter(['info@skopedigital.co.ke', setting('support_email'), 'skopedigitalacademy@gmail.com'])));
    $emails = array_slice($emails, 0, 2);
    // Inbox preview line: the first words of the message (hidden in the body)
    $pre = mb_substr(trim(preg_replace('/\s+/', ' ', sda_mail_text($html))), 0, 140);
    $font = "font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $link = fn($href, $label) => '<a href="' . $e($href) . '" style="color:#00BFFF;text-decoration:none;font-weight:600">' . $e($label) . '</a>';

    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . $e($subject) . '</title>'
        . '<style>a{color:#0080FF}h1,h2,h3{color:#0f172a}@media (max-width:620px){.sda-pad{padding:24px 20px!important}.sda-wrap{padding:16px 10px!important}}</style></head>'
        . '<body style="margin:0;padding:0;background:#eef2f7;' . $font . ';color:#334155">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">' . $e($pre) . str_repeat('&#847;&zwnj;&nbsp;', 30) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#eef2f7" style="background:#eef2f7"><tr><td align="center" class="sda-wrap" style="padding:28px 16px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%">'
        // Header
        . '<tr><td bgcolor="#0b1220" style="background:#0b1220;background-image:linear-gradient(135deg,#0b1220 0%,#0f2847 100%);border-radius:16px 16px 0 0;padding:22px 28px">'
        . '<a href="' . $e($base) . '/" style="text-decoration:none"><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="padding-right:14px"><img src="' . $e($base) . '/assets/images/email-logo.png?v=2" width="52" height="52" alt="Skope Digital Academy" style="display:block;width:52px;height:52px;border:0;border-radius:12px;background:#ffffff"></td>'
        . '<td style="' . $font . ';line-height:1.15"><div style="font-size:19px;font-weight:800;letter-spacing:.3px;color:#ffffff">SKOPE <span style="color:#FF8C00">DIGITAL</span></div>'
        . '<div style="font-size:11px;font-weight:700;letter-spacing:4px;color:#7dd3fc;margin-top:3px">ACADEMY</div></td>'
        . '</tr></table></a></td></tr>'
        // Accent bar
        . '<tr><td height="4" bgcolor="#00BFFF" style="height:4px;line-height:4px;font-size:0;background:#00BFFF;background-image:linear-gradient(90deg,#00BFFF,#0080FF 55%,#FF8C00)">&nbsp;</td></tr>'
        // Content
        . '<tr><td bgcolor="#ffffff" class="sda-pad" style="background:#ffffff;padding:34px 32px;border-radius:0 0 16px 16px;font-size:15px;line-height:1.65;color:#334155;' . $font . '">' . $html . '</td></tr>'
        // Footer
        . '<tr><td align="center" style="padding:26px 12px 6px;' . $font . ';font-size:12.5px;line-height:1.7;color:#64748b">'
        . '<div style="margin-bottom:8px">' . $link($base . '/courses', 'Courses') . ' &nbsp;·&nbsp; ' . $link($base . '/learn', 'Free tutorials') . ' &nbsp;·&nbsp; '
        . $link('https://www.facebook.com/profile.php?id=100090014799182', 'Facebook') . ' &nbsp;·&nbsp; ' . $link('https://wa.me/' . $wa, 'WhatsApp') . '</div>'
        . '<div><b style="color:#0f172a">' . $e($name) . '</b> · Kisumu, Kenya · <a href="tel:+' . $e($wa) . '" style="color:#64748b;text-decoration:none">' . $e($phone) . '</a></div>'
        . '<div>' . implode(' · ', array_map(fn($m) => '<a href="mailto:' . $e($m) . '" style="color:#64748b">' . $e($m) . '</a>', $emails)) . '</div>'
        . ($unsubscribe ? '<div style="margin-top:8px"><a href="' . $e($unsubscribe) . '" style="color:#94a3b8">Email preferences / unsubscribe</a></div>' : '')
        . '<div style="margin-top:12px;font-size:11px;color:#94a3b8">Powered by <a href="https://skopedesigns.co.ke" style="color:#94a3b8;font-weight:700">Skope Designs</a></div>'
        . '</td></tr></table></td></tr></table></body></html>';
}

/** Plain-text version for mail apps that do not show HTML. */
function sda_mail_text(string $html): string
{
    $t = preg_replace('#<(style|script|head|title)\b[^>]*>.*?</\1>#is', '', $html);
    $t = preg_replace('~<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>~is', '$2 ($1)', $t);
    $t = preg_replace('~<(br|/p|/h\d|/div|/li|/tr)\b[^>]*>~i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $t)));
}
