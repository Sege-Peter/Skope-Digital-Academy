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

    $nl = fn(string $s) => str_replace(["\r\n", "\r", "\n"], "\r\n", $s);
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
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", rtrim($raw, "\r\n")));
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

/** A button for use inside email content. */
function sda_mail_button(string $href, string $label): string
{
    return '<p style="text-align:center;margin:28px 0"><a href="' . htmlspecialchars($href) . '" style="background:#00BFFF;color:#ffffff;padding:14px 28px;text-decoration:none;border-radius:10px;font-weight:700;display:inline-block">'
        . htmlspecialchars($label) . '</a></p>';
}

function sda_mail_layout(string $subject, string $html, string $unsubscribe = ''): string
{
    $name = htmlspecialchars(setting('academy_name') ?: 'Skope Digital Academy');
    $support = htmlspecialchars(setting('support_email') ?: (setting('smtp_username') ?: ''));
    return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($subject) . '</title></head>'
        . '<body style="margin:0;background:#f1f5f9;font-family:Inter,Segoe UI,Arial,sans-serif;color:#334155">'
        . '<div style="max-width:600px;margin:0 auto;padding:28px 16px">'
        . '<div style="background:#0b1220;color:#ffffff;padding:18px 28px;border-radius:14px 14px 0 0;font-weight:800;letter-spacing:.5px">SKOPE <span style="color:#00BFFF">DIGITAL</span> ACADEMY</div>'
        . '<div style="background:#ffffff;padding:32px 28px;border-radius:0 0 14px 14px;line-height:1.6;font-size:15px">' . $html . '</div>'
        . '<p style="text-align:center;color:#94a3b8;font-size:12px;margin-top:18px">' . $name . ' · Kisumu, Kenya'
        . ($support ? '<br>Questions? Write to <a href="mailto:' . $support . '" style="color:#64748b">' . $support . '</a>' : '')
        . ($unsubscribe ? '<br><a href="' . htmlspecialchars($unsubscribe) . '" style="color:#94a3b8">Email preferences / unsubscribe</a>' : '') . '</p>'
        . '</div></body></html>';
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
