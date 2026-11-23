<?php
/**
 * Skope Digital Academy - Gmail-style admin webmail helpers (admin/mail, admin/api-mailbox.php).
 * Ported from the HAMASTA MEDIA mailbox (hm_mbx_*), for ONE academy mailbox.
 *
 *  - IMAP: sda_imap() from includes/mail_account.php (Gmail OAuth XOAUTH2, or an IMAP password).
 *    One connection per request, logged out at shutdown (sda_mbx_close()).
 *  - Sending: sda_mime_build() + sda_smtp_deliver() from includes/mailer.php. Gmail files sent mail in
 *    "[Gmail]/Sent Mail" by itself, so a copy is appended to Sent only for non-Gmail accounts.
 *  - Labels: on Gmail (capability X-GM-EXT-1) the real Gmail labels (X-GM-LABELS; each label is a LIST folder),
 *    colours kept in platform_settings 'mbx_label_colors'. Other servers: IMAP keywords sda_xxxxxxxx with names
 *    and colours in platform_settings 'mbx_labels_main' (the source's approach).
 *  - Signatures per admin (table mailbox_signatures, see sda_mbx_signature*()).
 *  - Scheduled send and snooze: tables mailbox_scheduled / mailbox_snoozed, processed by sda_mbx_tick($pdo)
 *    whenever an admin uses the webmail (at most once a minute) and by a cron script if one is set up.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';
require_once __DIR__ . '/mail_account.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/imap_client.php';

const SDA_MBX_BOX = 'main';   // one academy mailbox; the column stays so the tables match the source
const SDA_MBX_PAGE = 50;
const SDA_MBX_MAX_RCPT = 50;
const SDA_MBX_LABEL_COLORS = ['#1fb5f0', '#ff9b26', '#22c55e', '#f05252', '#8b7cf6', '#db2777', '#0891b2', '#d97706', '#64748b'];

class SdaMbxError extends RuntimeException {
    public array $extra;
    public function __construct(string $message, int $code = 400, array $extra = []) { parent::__construct($message, $code); $this->extra = $extra; }
}

function sda_mbx_fail(string $message, int $code = 400, array $extra = []): never { throw new SdaMbxError($message, $code, $extra); }

/** Attachments in one message (JSON base64 in, so keep well under post_max_size) */
function sda_mbx_max_attach(): int {
    $v = trim((string)ini_get('post_max_size'));
    $n = (int)$v; $u = strtolower(substr($v, -1));
    $bytes = $u === 'g' ? $n << 30 : ($u === 'm' ? $n << 20 : ($u === 'k' ? $n << 10 : $n));
    if ($bytes <= 0) $bytes = 8 << 20;
    // base64 grows ~37%; Gmail accepts up to 25 MB per message
    return (int)min(20 << 20, max(2 << 20, floor($bytes * 0.7 / 1.37)));
}

// ── Tables (created on first use) ───────────────────────────────────────────────

function sda_mbx_ensure(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("CREATE TABLE IF NOT EXISTS mailbox_signatures (
        user_id INT NOT NULL, box VARCHAR(20) NOT NULL, data TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, box)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mailbox_contacts (
        email VARCHAR(190) NOT NULL PRIMARY KEY, name VARCHAR(120) NULL, uses INT NOT NULL DEFAULT 0,
        last_used TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_last (last_used)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mailbox_scheduled (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, box VARCHAR(20) NOT NULL, user_id INT NULL,
        send_at DATETIME NOT NULL, subject VARCHAR(255) NOT NULL DEFAULT '', recipients VARCHAR(500) NOT NULL DEFAULT '',
        payload LONGTEXT NOT NULL, status VARCHAR(12) NOT NULL DEFAULT 'pending', error VARCHAR(255) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, sent_at DATETIME NULL,
        KEY idx_due (status, send_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mailbox_snoozed (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, box VARCHAR(20) NOT NULL, message_id VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL DEFAULT '', from_addr VARCHAR(255) NOT NULL DEFAULT '', until_at DATETIME NOT NULL,
        user_id INT NULL, status VARCHAR(12) NOT NULL DEFAULT 'snoozed', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_due (status, until_at), KEY idx_box (box, message_id(100))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mailbox_state (
        box VARCHAR(20) NOT NULL PRIMARY KEY, unseen INT NOT NULL DEFAULT 0, last_uid INT NOT NULL DEFAULT 0,
        checked_at DATETIME NULL, auth_failed_at DATETIME NULL, auth_failures INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── The mailbox ─────────────────────────────────────────────────────────────────

/** The academy mailbox: [key, address, name, ready, auth, gmail] */
function sda_mbx_box(): array {
    $a = sda_mail_account();
    return ['key' => SDA_MBX_BOX, 'address' => (string)$a['address'], 'name' => (string)$a['name'], 'ready' => sda_mail_ready(),
            'auth' => $a['auth'], 'gmail' => (bool)$a['gmail']];
}

/** Holder for the one connection of this request */
function &sda_mbx_conn(): ?SdaImap { static $imap = null; return $imap; }

/** Open (and cache for this request) the signed-in IMAP connection */
function sda_mbx_open(PDO $pdo): SdaImap {
    $c = &sda_mbx_conn();
    if ($c && $c->connected()) return $c;
    sda_mbx_ensure($pdo);
    if (!sda_mail_ready()) sda_mbx_fail('The academy mailbox is not connected yet. Open Settings in Webmail and connect Gmail (or save a mailbox password).', 409, ['setup' => true]);

    // Don't keep hammering the server with a password it rejected (Gmail and cPanel lock out repeated failures)
    // (only while the sign-in is unchanged: reconnecting Gmail or saving a new password lifts the pause at once)
    $fp = sda_mbx_cred_fp();
    $st = $pdo->prepare("SELECT auth_failures, auth_failed_at FROM mailbox_state WHERE box = ?");
    $st->execute([SDA_MBX_BOX]);
    $state = $st->fetch(PDO::FETCH_ASSOC);
    if ($state && (int)$state['auth_failures'] >= 3 && strtotime((string)$state['auth_failed_at']) > time() - 900 && sda_setting_fresh('mbx_auth_fail_fp') === $fp) {
        sda_mbx_fail('The mail server rejected the saved sign-in several times, so Webmail is paused for 15 minutes. Check the connection in Webmail > Settings.', 409, ['setup' => true]);
    }
    try {
        $imap = sda_imap();
    } catch (RuntimeException $e) {
        $msg = $e->getMessage();
        if (preg_match('/rejected|refused|expired|not connected|reconnect/i', $msg)) {
            if (sda_setting_fresh('mbx_auth_fail_fp') !== $fp) {   // a different sign-in than the one that failed before: count afresh
                $pdo->prepare("UPDATE mailbox_state SET auth_failures = 0 WHERE box = ?")->execute([SDA_MBX_BOX]);
                save_setting($pdo, 'mbx_auth_fail_fp', $fp);
            }
            $pdo->prepare("INSERT INTO mailbox_state (box, auth_failures, auth_failed_at) VALUES (?, 1, NOW())
                           ON DUPLICATE KEY UPDATE auth_failures = auth_failures + 1, auth_failed_at = NOW()")->execute([SDA_MBX_BOX]);
            sda_mbx_fail($msg, 409, ['setup' => true]);
        }
        sda_mbx_fail('The mail server is busy or unreachable right now. Please try again in a minute. (' . mb_substr($msg, 0, 120) . ')', 503, ['retry' => true]);
    }
    $pdo->prepare("INSERT INTO mailbox_state (box, auth_failures) VALUES (?, 0) ON DUPLICATE KEY UPDATE auth_failures = 0")->execute([SDA_MBX_BOX]);
    $c = $imap;
    static $hooked = false;
    if (!$hooked) { $hooked = true; register_shutdown_function('sda_mbx_close'); }
    return $c;
}

/** Fingerprint of the current sign-in (auth method, address, stored secret, server) */
function sda_mbx_cred_fp(): string {
    $a = sda_mail_account();
    $secret = $a['auth'] === 'google' ? sda_setting_fresh('gmail_refresh_token') : sda_setting_fresh('smtp_password');
    return sha1($a['auth'] . '|' . $a['address'] . '|' . $secret . '|' . $a['imap_host'] . ':' . $a['imap_port']);
}

/** Log out of the request's IMAP connection (also runs at shutdown) */
function sda_mbx_close(): void {
    $c = &sda_mbx_conn();
    if ($c) { try { $c->logout(); } catch (Throwable $e) {} $c = null; }
}

/** Gmail's IMAP extensions are available (X-GM-LABELS, X-GM-RAW, X-GM-THRID) */
function sda_mbx_is_gmail(SdaImap $imap): bool { return $imap->has('X-GM-EXT-1'); }

/** Gmail's system folders live under "[Gmail]/" or (localized accounts) "[Google Mail]/" */
function sda_mbx_gm_system(string $folder): bool { return (bool)preg_match('#^\[(Gmail|Google Mail)\]($|/)#i', $folder); }

/**
 * Folder for each role: inbox, sent, drafts, trash, spam, archive (Gmail: All Mail), starred, important, snoozed.
 * SPECIAL-USE flags first, names second. Non-Gmail servers get missing basics created; Snoozed is only created when used.
 */
function sda_mbx_roles(SdaImap $imap, bool $createSnoozed = false): array {
    $folders = $imap->listFolders();
    $gmail = sda_mbx_is_gmail($imap);
    $prefix = '';
    foreach ($folders as $f) {
        if ($f['delimiter'] !== '' && stripos($f['name'], 'INBOX' . $f['delimiter']) === 0) { $prefix = 'INBOX' . $f['delimiter']; break; }
    }
    if (!$gmail && $prefix === '' && $folders && ($folders[0]['delimiter'] ?? '') === '.') $prefix = 'INBOX.';
    $want = [
        'sent'      => ['\\Sent',      ['Sent Mail', 'Sent', 'Sent Items', 'Sent Messages']],
        'drafts'    => ['\\Drafts',    ['Drafts']],
        'trash'     => ['\\Trash',     ['Trash', 'Bin', 'Deleted Items', 'Deleted Messages']],
        'spam'      => ['\\Junk',      ['Spam', 'Junk', 'Junk E-mail']],
        'archive'   => [$gmail ? '\\All' : '\\Archive', $gmail ? ['All Mail'] : ['Archive', 'All Mail']],
        'starred'   => ['\\Flagged',   $gmail ? ['Starred'] : []],
        'important' => ['\\Important', $gmail ? ['Important'] : []],
        'snoozed'   => [null,          ['Snoozed', 'Webmail Snoozed']],
    ];
    $roles = ['inbox' => 'INBOX'];
    $created = false;
    foreach ($want as $role => [$special, $names]) {
        $found = null;
        if ($special) foreach ($folders as $f) {
            if (stripos($f['flags'], '\\Noselect') === false && preg_match('/(^|\s)' . preg_quote($special, '/') . '(\s|$)/i', $f['flags'])) { $found = $f['name']; break; }
        }
        if (!$found) foreach ($names as $n) foreach ($folders as $f) {
            if (stripos($f['flags'], '\\Noselect') !== false) continue;
            $leaf = preg_replace('#^(INBOX[./]|\[(Gmail|Google Mail)\]/)#i', '', $f['name']);
            // On Gmail the system names only count inside [Gmail]/; a user label called "Sent" is just a label
            if ($gmail && in_array($role, ['sent', 'drafts', 'trash', 'spam', 'archive', 'starred', 'important'], true) && !sda_mbx_gm_system($f['name'])) continue;
            if (strcasecmp($leaf, $n) === 0) { $found = $f['name']; break 2; }
        }
        $create = $role === 'snoozed' ? $createSnoozed : (!$gmail && in_array($role, ['sent', 'drafts', 'trash', 'spam', 'archive'], true));
        if (!$found && $create) {
            foreach ($names as $n) {
                $name = $prefix . $n;
                if ($imap->createFolder($name)) { $found = $name; $created = true; break; }
                if ($role !== 'snoozed') break;   // Gmail may refuse "Snoozed" (reserved): try the second name
            }
        }
        $roles[$role] = $found;
    }
    if ($created) $imap->forgetFolders();
    return $roles;
}

function sda_mbx_folder_ok(SdaImap $imap, string $folder): bool {
    if ($folder === '') return false;
    foreach ($imap->listFolders() as $f) if ($f['name'] === $folder && stripos($f['flags'], '\\Noselect') === false) return true;
    return false;
}

function sda_mbx_role_of(array $roles, ?string $folder): string {
    if ($folder === null) return 'folder';
    foreach ($roles as $r => $name) if ($name !== null && $name === $folder) return $r;
    return 'folder';
}

/** "Hello" -> modified UTF-7 when needed (IMAP folder names) */
function sda_mbx_utf7(string $name): string {
    return function_exists('mb_convert_encoding') && preg_match('/[^\x20-\x7e]/', $name) ? mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8') : $name;
}

// ── Gmail labels (X-GM-LABELS) ──────────────────────────────────────────────────

/** Tokens of a parenthesised IMAP list such as (\Inbox "Work/Clients" foo {5}<lit>) */
function sda_mbx_imap_list(string $s, array $literals): array {
    $out = []; $i = 0; $n = strlen($s);
    while ($i < $n) {
        $c = $s[$i];
        if ($c === ' ') { $i++; continue; }
        if ($c === '"') {
            $j = $i + 1; $v = '';
            while ($j < $n && $s[$j] !== '"') { if ($s[$j] === '\\' && $j + 1 < $n) $j++; $v .= $s[$j]; $j++; }
            $out[] = $v; $i = $j + 1; continue;
        }
        if ($c === "\x00" && preg_match('/^\x00LIT(\d+)\x00/', substr($s, $i), $m)) { $out[] = (string)($literals[(int)$m[1]] ?? ''); $i += strlen($m[0]); continue; }
        $j = $i; while ($j < $n && $s[$j] !== ' ') $j++;
        $out[] = substr($s, $i, $j - $i); $i = $j;
    }
    return $out;
}

/** [uid => ['labels' => [user labels], 'system' => [\Inbox, \Important ...], 'thrid' => '...']] for UIDs in the selected folder */
function sda_mbx_gm_meta(SdaImap $imap, array $uids): array {
    $uids = array_values(array_filter(array_map('intval', $uids)));
    if (!$uids) return [];
    $r = $imap->command('UID FETCH ' . implode(',', $uids) . ' (UID X-GM-LABELS X-GM-THRID)');
    $out = [];
    foreach ($r['lines'] as $l) {
        if (!preg_match('/UID (\d+)/', $l, $mu)) continue;
        $labels = []; $system = [];
        if (preg_match('/X-GM-LABELS \(((?:[^()"\x00]|"(?:[^"\\\\]|\\\\.)*"|\x00LIT\d+\x00)*)\)/', $l, $ml)) {
            foreach (sda_mbx_imap_list($ml[1], $r['literals']) as $t) {
                if ($t === '') continue;
                if ($t[0] === '\\') $system[] = $t; else $labels[] = $t;
            }
        }
        preg_match('/X-GM-THRID (\d+)/', $l, $mt);
        $out[(int)$mu[1]] = ['labels' => $labels, 'system' => $system, 'thrid' => $mt[1] ?? ''];
    }
    return $out;
}

/** Add or remove one Gmail label (raw folder name, or \Inbox / \Important) on UIDs of the selected folder */
function sda_mbx_gm_label(SdaImap $imap, array $uids, string $label, bool $on): bool {
    $set = implode(',', array_map('intval', $uids));
    if ($set === '') return true;
    $arg = $label !== '' && $label[0] === '\\' ? $label : $imap->quote($label);
    return $imap->command(['UID STORE ' . $set . ($on ? ' +' : ' -') . 'X-GM-LABELS (', $arg, ')'])['ok'];
}

/** User labels on Gmail: every selectable folder that is not INBOX, a [Gmail]/ folder or the Snoozed folder */
function sda_mbx_gm_labels(SdaImap $imap, array $roles): array {
    $colors = json_decode(sda_setting_fresh('mbx_label_colors'), true) ?: [];
    $out = []; $i = 0;
    foreach ($imap->listFolders() as $f) {
        if (stripos($f['flags'], '\\Noselect') !== false || strcasecmp($f['name'], 'INBOX') === 0 || sda_mbx_gm_system($f['name'])) continue;
        if ($f['name'] === ($roles['snoozed'] ?? null)) continue;
        $c = $colors[$f['name']] ?? '';
        $out[] = ['id' => $f['name'], 'name' => $f['label'], 'color' => in_array($c, SDA_MBX_LABEL_COLORS, true) ? $c : SDA_MBX_LABEL_COLORS[$i % count(SDA_MBX_LABEL_COLORS)]];
        $i++;
    }
    return $out;
}

function sda_mbx_gm_label_color(PDO $pdo, string $id, ?string $color, ?string $renameTo = null): void {
    $colors = json_decode(sda_setting_fresh('mbx_label_colors'), true) ?: [];
    if ($renameTo !== null) { $colors[$renameTo] = $colors[$id] ?? $color; unset($colors[$id]); $id = $renameTo; }
    if ($color === null) unset($colors[$id]); else $colors[$id] = $color;
    save_setting($pdo, 'mbx_label_colors', json_encode($colors, JSON_UNESCAPED_UNICODE));
}

/**
 * Gmail-aware move of UIDs from the selected folder $f to $target.
 * Gmail folders are labels: expunging from All Mail / Starred / Important / Sent would trash, unstar or unlabel the
 * message, so from those views a "move" only adds the target label (and takes the message out of the Inbox).
 */
function sda_mbx_gm_move(SdaImap $imap, array $roles, string $f, array $uids, string $target): bool {
    $role = sda_mbx_role_of($roles, $f);
    $tRole = sda_mbx_role_of($roles, $target);
    if ($tRole === 'archive') {   // Archive = take out of the Inbox
        if (in_array($role, ['trash', 'spam'], true)) return $imap->move($uids, $target);
        return sda_mbx_gm_label($imap, $uids, '\\Inbox', false);
    }
    if ($tRole === 'inbox' && !in_array($role, ['trash', 'spam', 'folder', 'snoozed'], true)) return sda_mbx_gm_label($imap, $uids, '\\Inbox', true);
    if (in_array($tRole, ['trash', 'spam'], true) || in_array($role, ['inbox', 'folder', 'snoozed', 'trash', 'spam'], true)) return $imap->move($uids, $target);
    // From All Mail / Starred / Important / Sent / Drafts: add the label, leave the Inbox
    $ok = $imap->command(['UID COPY ' . implode(',', array_map('intval', $uids)) . ' ', $imap->quote($target)])['ok'];
    if ($ok && $tRole !== 'inbox') sda_mbx_gm_label($imap, $uids, '\\Inbox', false);
    return $ok;
}

// ── Search ──────────────────────────────────────────────────────────────────────

/**
 * Gmail-style search text to IMAP SEARCH keys. Gmail servers get X-GM-RAW (Gmail understands every operator);
 * other servers: from: to: cc: subject: has:attachment is:unread is:read is:starred after: before: older_than:
 * newer_than: larger: smaller:, "quoted phrases", -negation and plain words.
 * @return array parts for SdaImap::searchUids (strings and literal arrays)
 */
function sda_mbx_search_criteria(SdaImap $imap, string $q): array {
    $q = trim(mb_substr(str_replace(["\r", "\n", "\0"], ' ', $q), 0, 200));
    if ($q === '') return ['ALL'];
    if (sda_mbx_is_gmail($imap)) return ['X-GM-RAW ', $imap->quote($q)];
    $parts = [];
    $add = function (...$p) use (&$parts) { if ($parts) $parts[] = ' '; foreach ($p as $x) $parts[] = $x; };
    $date = function (string $v): ?string { $t = strtotime(str_replace('/', '-', $v)); return $t ? date('j-M-Y', $t) : null; };
    preg_match_all('/(-)?(?:(\w+):)?("([^"]*)"|\S+)/u', $q, $mm, PREG_SET_ORDER);
    foreach ($mm as $m) {
        $neg = $m[1] === '-';
        $op = strtolower($m[2]);
        $val = isset($m[4]) && $m[4] !== '' ? $m[4] : trim($m[3], '"');
        if ($val === '') continue;
        $start = count($parts);
        $lv = strtolower($val);
        switch ($op) {
            case 'from': case 'to': case 'cc': case 'bcc': case 'subject':
                $add(strtoupper($op) . ' ', $imap->quote($val)); break;
            case 'has':
                if ($lv === 'attachment') $add('OR HEADER Content-Type "multipart/mixed" HEADER Content-Disposition "attachment"');
                elseif ($lv === 'star' || $lv === 'stars') $add('FLAGGED');
                break;
            case 'is':
                $map = ['unread' => 'UNSEEN', 'read' => 'SEEN', 'starred' => 'FLAGGED', 'unstarred' => 'UNFLAGGED', 'answered' => 'ANSWERED', 'replied' => 'ANSWERED'];
                if (isset($map[$lv])) $add($map[$lv]);
                break;
            case 'after': case 'since': if ($d = $date($val)) $add('SINCE ' . $d); break;
            case 'before': if ($d = $date($val)) $add('BEFORE ' . $d); break;
            case 'older_than': case 'newer_than':
                if (preg_match('/^(\d+)([dmy])$/', $lv, $x)) {
                    $d = date('j-M-Y', strtotime('-' . $x[1] . ' ' . ['d' => 'days', 'm' => 'months', 'y' => 'years'][$x[2]]));
                    $add(($op === 'older_than' ? 'BEFORE ' : 'SINCE ') . $d);
                }
                break;
            case 'larger': case 'smaller':
                if (preg_match('/^(\d+)([km]?)$/', $lv, $x)) $add(strtoupper($op) . ' ' . ((int)$x[1] * ['' => 1, 'k' => 1024, 'm' => 1048576][$x[2]]));
                break;
            case '':
                $add('TEXT ', $imap->quote($val)); break;
            default:
                $add('TEXT ', $imap->quote($m[0]));   // unknown "word:thing" is just text
        }
        if ($neg && count($parts) > $start) {
            $piece = array_slice($parts, $start + ($start ? 1 : 0));
            array_splice($parts, $start + ($start ? 1 : 0), count($piece), array_merge(['NOT ('], $piece, [')']));
        }
        if (count($parts) > 40) break;
    }
    return $parts ?: ['ALL'];
}

/** Two criteria lists joined (AND) */
function sda_mbx_and(array $a, array $b): array {
    if ($a === ['ALL']) return $b;
    if ($b === ['ALL']) return $a;
    return array_merge($a, [' '], $b);
}

/** Subject without Re:/Fwd:/[tags], lower-cased (threading) */
function sda_mbx_base_subject(string $s): string {
    $s = trim($s);
    do { $prev = $s; $s = trim(preg_replace('/^((re|fw|fwd|aw|sv|tr)(\[\d+\])?\s*:\s*|\[[^\]]{1,30}\]\s*)/i', '', $s)); } while ($s !== $prev);
    return mb_strtolower($s);
}

/** Conversation key: the first Message-ID in References, else In-Reply-To, else its own Message-ID */
function sda_mbx_thread_key(array $m): string {
    foreach (['references', 'in_reply_to', 'message_id'] as $k) {
        if (preg_match('/<[^>]+>/', (string)($m[$k] ?? ''), $x)) return strtolower($x[0]);
    }
    return 's:' . sda_mbx_base_subject((string)($m['subject'] ?? ''));
}

function sda_mbx_ids(string ...$headers): array {
    $ids = [];
    foreach ($headers as $h) { preg_match_all('/<[^>]+>/', $h, $m); foreach ($m[0] as $id) $ids[strtolower($id)] = true; }
    return array_keys($ids);
}

// ── Addresses ───────────────────────────────────────────────────────────────────

/** "Name <a@b.com>, c@d.com" or [{email,name}] -> [['email' => ..., 'name' => ...]] (validated, no header injection) */
function sda_mbx_parse_addresses($raw): array {
    $items = is_array($raw) ? $raw : preg_split('/[,;\n]+(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string)$raw);
    $out = [];
    foreach ($items as $item) {
        $email = is_array($item) ? (string)($item['email'] ?? '') : (string)$item;
        $name = is_array($item) ? (string)($item['name'] ?? '') : '';
        if (!is_array($item) && preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>\s*$/', $email, $m)) { $name = $m[1]; $email = $m[2]; }
        $email = strtolower(trim($email));
        $name = trim(preg_replace('/[\r\n\t"<>]+/', ' ', $name));
        if ($email === '') continue;
        if (preg_match('/[\r\n]/', $email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) sda_mbx_fail('Invalid email address: ' . mb_substr($email, 0, 80));
        $out[$email] = ['email' => $email, 'name' => mb_substr($name, 0, 100)];
    }
    return array_values($out);
}

/** [{email,name}] -> ["Name <email>"] for sda_mime_build() */
function sda_mbx_addr_strings(array $list): array {
    return array_map(fn($a) => $a['name'] !== '' ? $a['name'] . ' <' . $a['email'] . '>' : $a['email'], $list);
}

function sda_mbx_remember_contacts(PDO $pdo, array $list): void {
    if (!$list) return;
    sda_mbx_ensure($pdo);
    $st = $pdo->prepare("INSERT INTO mailbox_contacts (email, name, uses) VALUES (?, ?, 1)
                         ON DUPLICATE KEY UPDATE uses = uses + 1, last_used = CURRENT_TIMESTAMP, name = COALESCE(NULLIF(VALUES(name), ''), name)");
    foreach (array_slice($list, 0, 60) as $a) {
        if (!empty($a['email']) && filter_var($a['email'], FILTER_VALIDATE_EMAIL)) $st->execute([strtolower($a['email']), mb_substr((string)($a['name'] ?? ''), 0, 120) ?: null]);
    }
}

// ── HTML ────────────────────────────────────────────────────────────────────────

/**
 * Incoming HTML for the reading pane (shown in a sandboxed iframe with scripts blocked by CSP as well):
 * scripts, embeds, forms, event handlers, javascript: links and meta refresh are removed here too.
 */
function sda_mbx_clean_incoming(string $html): string {
    if (strlen($html) > 3000000) $html = substr($html, 0, 3000000);
    $html = preg_replace('#<(script|iframe|frame|frameset|object|embed|applet|form|base|link|meta)\b[^>]*>(.*?</\1\s*>)?#is', '', $html);
    $html = preg_replace('#</?(script|iframe|frame|object|embed|applet|form|base|meta|link)\b[^>]*>#i', '', $html);
    $html = preg_replace('#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#(href|src|action|formaction|background|xlink:href)\s*=\s*(["\']?)\s*(javascript|vbscript|data:text/html)[^"\'>\s]*\2#i', '$1="#"', $html);
    $html = preg_replace('#expression\s*\(#i', 'x(', $html);
    return (string)$html;
}

/** Outgoing HTML from the compose editor (and quoted originals) reduced to safe email HTML. */
function sda_mbx_clean_html(string $html): string {
    $html = trim($html);
    if ($html === '' || strlen($html) > 2000000 || !class_exists('DOMDocument')) return '';
    $keep = ['b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del', 'sub', 'sup', 'br', 'p', 'div', 'span', 'font',
             'ul', 'ol', 'li', 'blockquote', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'pre', 'code', 'hr', 'img',
             'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption', 'center', 'small', 'big'];
    $drop = ['script', 'style', 'iframe', 'frame', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select',
             'option', 'svg', 'math', 'video', 'audio', 'picture', 'source', 'link', 'meta', 'base', 'head', 'title', 'template', 'noscript'];
    $styleProps = ['color', 'background-color', 'background', 'font-family', 'font-weight', 'font-style', 'font-size', 'text-decoration',
                   'text-decoration-line', 'text-align', 'margin', 'margin-left', 'margin-top', 'margin-bottom', 'padding', 'padding-left',
                   'border', 'border-left', 'border-top', 'width', 'max-width', 'height', 'line-height', 'vertical-align', 'display',
                   'padding-top', 'padding-right', 'padding-bottom', 'border-bottom', 'border-right', 'border-radius', 'letter-spacing',
                   'margin-right', 'min-width', 'white-space'];
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = null;
    foreach ($doc->childNodes as $n) if ($n instanceof DOMElement) { $root = $n; break; }
    if (!$root) return '';
    $cleanStyle = function ($style) use ($styleProps) {
        $out = [];
        foreach (explode(';', (string)$style) as $decl) {
            if (strpos($decl, ':') === false) continue;
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (!in_array($prop, $styleProps, true)) continue;
            $ok = $prop === 'font-family' ? '/^[a-z0-9 ,\'"-]{1,100}$/i' : '/^[#a-z0-9 .,%()\-]{1,80}$/i';
            if (!preg_match($ok, $val) || preg_match('/url|expression|javascript|position|fixed/i', $val)) continue;
            $out[] = $prop . ':' . $val;
        }
        return implode(';', $out);
    };
    $walk = function (DOMNode $node) use (&$walk, $keep, $drop, $cleanStyle) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, $drop, true)) { $node->removeChild($child); continue; }
                $walk($child);
                if (!in_array($tag, $keep, true)) {
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                $keepAttr = [];
                foreach (['href', 'src', 'alt', 'width', 'height', 'colspan', 'rowspan', 'align', 'valign', 'color', 'size', 'face', 'cellpadding', 'cellspacing', 'border', 'bgcolor'] as $a) {
                    if ($child->hasAttribute($a)) $keepAttr[$a] = trim($child->getAttribute($a));
                }
                $style = $cleanStyle($child->getAttribute('style'));
                foreach (iterator_to_array($child->attributes) as $attr) $child->removeAttribute($attr->name);
                if ($style !== '') $child->setAttribute('style', $style);
                foreach ($keepAttr as $a => $v) {
                    if ($a === 'href') {
                        if (preg_match('#^(https?://|mailto:|tel:|\#)#i', $v)) { $child->setAttribute('href', $v); $child->setAttribute('target', '_blank'); $child->setAttribute('rel', 'noopener'); }
                    } elseif ($a === 'src') {
                        if ($tag === 'img' && (preg_match('#^(https?://|cid:)#i', $v) || preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $v))) $child->setAttribute('src', $v);
                    } elseif (in_array($a, ['color', 'bgcolor'], true)) {
                        if (preg_match('/^#?[0-9a-z]{3,20}$/i', $v)) $child->setAttribute($a, $v);
                    } elseif ($a === 'face') {
                        if (preg_match('/^[a-z0-9 ,-]{1,80}$/i', $v)) $child->setAttribute($a, $v);
                    } elseif ($a === 'alt') {
                        $child->setAttribute($a, mb_substr($v, 0, 200));
                    } elseif (preg_match('/^[0-9a-z%]{1,10}$/i', $v)) {
                        $child->setAttribute($a, $v);
                    }
                }
                if ($tag === 'img' && !$child->hasAttribute('src')) { $node->removeChild($child); continue; }
            } elseif ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $n) $out .= $doc->saveHTML($n);
    return trim(strip_tags($out)) === '' && !preg_match('/<(hr|img)/i', $out) ? '' : $out;
}

function sda_mbx_html_to_text(string $html): string {
    $t = preg_replace('#<(br|/p|/div|/h\d|/tr|/li|/blockquote)\b[^>]*>#i', "\n", $html);
    $t = preg_replace('#<li\b[^>]*>#i', '• ', $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $t)));
}

// ── Keyword labels (non-Gmail servers) ──────────────────────────────────────────

function sda_mbx_kw_labels(): array {
    $data = json_decode(sda_setting_fresh('mbx_labels_' . SDA_MBX_BOX), true);
    return is_array($data) ? array_values(array_filter($data, fn($l) => isset($l['id'], $l['name'], $l['color']) && preg_match('/^sda_[a-f0-9]{8}$/', $l['id']))) : [];
}

function sda_mbx_kw_save_labels(PDO $pdo, array $labels): void {
    save_setting($pdo, 'mbx_labels_' . SDA_MBX_BOX, json_encode(array_values($labels), JSON_UNESCAPED_UNICODE));
}

/** Labels for the UI: Gmail labels or keyword labels */
function sda_mbx_labels(PDO $pdo, SdaImap $imap, array $roles): array {
    return sda_mbx_is_gmail($imap) ? sda_mbx_gm_labels($imap, $roles) : sda_mbx_kw_labels();
}

// ── The signed-in admin ─────────────────────────────────────────────────────────

function sda_mbx_me(PDO $pdo): array {
    static $me = null;
    if ($me !== null) return $me;
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $row = [];
    if ($uid) {
        $st = $pdo->prepare("SELECT id, name, email, role, phone FROM users WHERE id = ?");
        $st->execute([$uid]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    return $me = ['id' => $uid, 'name' => trim((string)($row['name'] ?? ($_SESSION['user_name'] ?? ''))) ?: 'Admin',
                  'email' => (string)($row['email'] ?? ''), 'title' => 'Administrator', 'phone' => (string)($row['phone'] ?? '')];
}

// ── Building, sending, drafts ───────────────────────────────────────────────────

/**
 * Message array -> raw MIME. $msg: to, cc, bcc ([{email,name}]), subject, html, text, attachments [[name,type,data]],
 * in_reply_to, references, message_id, from_name
 */
function sda_mbx_build_raw(array $msg): string {
    $b = sda_mbx_box();
    $refOk = fn($v) => ($v = trim(preg_replace('/[\r\n]+/', ' ', (string)$v))) !== '' && preg_match('/^(<[^<>\s]+>\s*)+$/', $v) ? mb_substr($v, 0, 2000) : '';
    $m = [
        'from' => $b['address'], 'from_name' => trim(preg_replace('/[\r\n"<>]+/', ' ', (string)($msg['from_name'] ?? ''))) ?: $b['name'],
        'to' => sda_mbx_addr_strings($msg['to'] ?? []), 'cc' => sda_mbx_addr_strings($msg['cc'] ?? []),
        'subject' => trim(preg_replace('/[\r\n]+/', ' ', (string)($msg['subject'] ?? ''))),
        'html' => (string)($msg['html'] ?? ''), 'text' => (string)($msg['text'] ?? ''),
        'attachments' => array_map(fn($a) => ['name' => trim(preg_replace('/[\r\n\/\\\\"]+/', '_', (string)($a['name'] ?? 'attachment'))) ?: 'attachment',
                                              'type' => (string)($a['type'] ?? ''), 'data' => (string)$a['data']], $msg['attachments'] ?? []),
        'message_id' => !empty($msg['message_id']) && preg_match('/^<[^<>\s]+@[^<>\s]+>$/', $msg['message_id']) ? $msg['message_id'] : sda_message_id($b['address']),
    ];
    if ($m['text'] === '' && $m['html'] !== '') $m['text'] = sda_mbx_html_to_text($m['html']);
    if ($v = $refOk($msg['in_reply_to'] ?? '')) $m['in_reply_to'] = $v;
    if ($v = $refOk($msg['references'] ?? '')) $m['references'] = $v;
    return sda_mime_build($m);
}

/** Send now. Non-Gmail accounts keep a copy in Sent (Gmail files it by itself). @return array{ok:bool, error:string, message_id:string} */
function sda_mbx_send(PDO $pdo, array $msg, ?SdaImap $imap = null): array {
    $b = sda_mbx_box();
    $msg['message_id'] = $msg['message_id'] ?? sda_message_id($b['address']);
    try {
        $raw = sda_mbx_build_raw($msg);
        $rcpts = array_column(array_merge($msg['to'] ?? [], $msg['cc'] ?? [], $msg['bcc'] ?? []), 'email');
        sda_smtp_deliver($rcpts, $raw);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'message_id' => ''];
    }
    if (!$b['gmail']) {
        try {
            $imap = $imap ?: sda_mbx_open($pdo);
            if (!sda_mbx_is_gmail($imap)) {
                $roles = sda_mbx_roles($imap);
                if ($roles['sent']) $imap->append($roles['sent'], $raw, '\\Seen');
            }
        } catch (Throwable $e) { /* sent anyway; the Sent copy is best effort */ }
    }
    sda_mbx_remember_contacts($pdo, array_merge($msg['to'] ?? [], $msg['cc'] ?? [], $msg['bcc'] ?? []));
    return ['ok' => true, 'error' => '', 'message_id' => $msg['message_id']];
}

// ── Scheduled send / snooze (cron + while the webmail is open) ──────────────────

/**
 * Send scheduled messages that are due and bring snoozed conversations back to the Inbox.
 * Safe to call from a cron script: require includes/mailbox.php, then sda_mbx_tick($pdo).
 */
function sda_mbx_tick(PDO $pdo, int $max = 15): array {
    sda_mbx_ensure($pdo);
    $res = ['sent' => 0, 'failed' => 0, 'unsnoozed' => 0];
    if (!sda_mail_ready()) return $res;
    // Claim due messages one by one so two runs can't send the same one
    $due = $pdo->query("SELECT id FROM mailbox_scheduled WHERE status = 'pending' AND send_at <= NOW() ORDER BY send_at LIMIT " . (int)$max)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($due as $id) {
        $c = $pdo->prepare("UPDATE mailbox_scheduled SET status = 'sending' WHERE id = ? AND status = 'pending'");
        $c->execute([$id]);
        if (!$c->rowCount()) continue;
        $st = $pdo->prepare("SELECT * FROM mailbox_scheduled WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $msg = json_decode((string)$row['payload'], true) ?: [];
        foreach ($msg['attachments'] ?? [] as $i => $a) $msg['attachments'][$i]['data'] = base64_decode((string)($a['data'] ?? ''));
        $r = $msg ? sda_mbx_send($pdo, $msg) : ['ok' => false, 'error' => 'Empty message'];
        $pdo->prepare("UPDATE mailbox_scheduled SET status = ?, error = ?, sent_at = NOW(), payload = IF(? = 'sent', '{}', payload) WHERE id = ?")
            ->execute([$r['ok'] ? 'sent' : 'failed', $r['ok'] ? null : mb_substr($r['error'], 0, 250), $r['ok'] ? 'sent' : 'failed', $id]);
        $res[$r['ok'] ? 'sent' : 'failed']++;
    }
    // Snoozed conversations come back to the Inbox, unread, at their time
    $rows = $pdo->query("SELECT * FROM mailbox_snoozed WHERE status = 'snoozed' AND until_at <= NOW() ORDER BY until_at LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) {
        try {
            $imap = sda_mbx_open($pdo);
            $roles = sda_mbx_roles($imap);
            if ($roles['snoozed']) {
                $imap->select($roles['snoozed']);
                foreach ($rows as $r) {
                    $uids = $imap->searchUids(['HEADER Message-ID ', $imap->quote($r['message_id'])]);
                    if ($uids) { $imap->store($uids, '\\Seen', false); $imap->move($uids, 'INBOX'); }
                    $pdo->prepare("UPDATE mailbox_snoozed SET status = 'returned' WHERE id = ?")->execute([$r['id']]);
                    $res['unsnoozed']++;
                }
            }
        } catch (Throwable $e) { /* try again next run */ }
    }
    return $res;
}

/** Run the tick at most once a minute across admins (called from the API) */
function sda_mbx_maybe_tick(PDO $pdo): ?array {
    $now = time();
    $pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES ('mbx_last_tick', ?)
                   ON DUPLICATE KEY UPDATE setting_value = IF(CAST(setting_value AS UNSIGNED) < ?, VALUES(setting_value), setting_value)")
        ->execute([(string)$now, $now - 60]);
    if (sda_setting_fresh('mbx_last_tick') !== (string)$now) return null;
    try { return sda_mbx_tick($pdo, 5); } catch (Throwable $e) { return null; }
}

require_once __DIR__ . '/mailbox_signature.php';
