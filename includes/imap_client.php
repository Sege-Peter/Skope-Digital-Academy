<?php
/**
 * Skope Digital Academy - small IMAP client for the admin webmail (admin/mail).
 * Talks to the cPanel mail server (Dovecot) over SSL with plain sockets, so the PHP imap extension
 * is not needed. Ported from the WeCare dashboard mailbox and extended for the Gmail-style features:
 * literals in commands (UTF-8 search, APPEND), SORT, previews, UID sets, APPENDUID.
 */

class SdaImap {
    private $fp = null;
    private int $tag = 0;
    private ?array $caps = null;
    public string $lastError = '';
    /** True only when the last login was rejected because of the address/password */
    public bool $authFailed = false;

    public function connect(string $host, int $port = 993, bool $verify = false): bool {
        // Loopback (127.0.0.1) to this server's own mail service: the certificate is for the domain, not the IP
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'SNI_enabled' => true]]);
        $this->fp = @stream_socket_client("ssl://$host:$port", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->fp) { $this->lastError = "Could not reach the mail server ($errstr)"; return false; }
        stream_set_timeout($this->fp, 25);
        $greeting = (string)fgets($this->fp);
        if ($greeting === '' || stripos($greeting, '* BYE') === 0) {
            // Refusing connections for now (e.g. too many recent logins)
            $this->lastError = 'The mail server is busy right now. Please try again in a minute.';
            @fclose($this->fp); $this->fp = null;
            return false;
        }
        return true;
    }

    public function login(string $user, string $pass): bool {
        $this->authFailed = false;
        $r = $this->command(['LOGIN ', $this->quote($user), ' ', $this->quote($pass)]);
        if (!$r['ok']) {
            $s = (string)$r['status'];
            // Dovecot: "NO [AUTHENTICATIONFAILED]" = wrong credentials; [UNAVAILABLE]/[INUSE]/BYE are temporary
            $this->authFailed = stripos($s, 'AUTHENTICATIONFAILED') !== false || stripos($s, 'AUTHORIZATIONFAILED') !== false
                || (stripos($s, 'NO') === 0 && strpos($s, '[') === false && stripos($s, 'authentication failed') !== false);
        }
        return $r['ok'];
    }

    /** Gmail OAuth sign-in (SASL XOAUTH2) with an access token instead of a password */
    public function loginXoauth2(string $user, string $accessToken): bool {
        $this->authFailed = false;
        if (!$this->fp) return false;
        $tag = 'S' . (++$this->tag);
        fwrite($this->fp, "$tag AUTHENTICATE XOAUTH2 " . base64_encode("user=$user\x01auth=Bearer $accessToken\x01\x01") . "\r\n");
        while (($line = fgets($this->fp)) !== false) {
            if ($line[0] === '+') { fwrite($this->fp, "\r\n"); continue; }   // error details follow; empty reply ends the exchange
            if (strpos($line, "$tag ") === 0) {
                $ok = stripos(substr($line, strlen($tag) + 1), 'OK') === 0;
                if (!$ok) { $this->authFailed = true; $this->lastError = 'Gmail refused the saved sign-in. Reconnect Gmail in Webmail settings.'; }
                $this->caps = null;
                return $ok;
            }
        }
        $this->lastError = 'Mail server connection closed';
        return false;
    }

    public function logout(): void {
        if ($this->fp) { @$this->command('LOGOUT'); @fclose($this->fp); $this->fp = null; }
    }

    public function connected(): bool { return (bool)$this->fp; }

    /** "quoted" string for ASCII values; anything else goes as a literal (see command()) */
    public function quote(string $s) {
        if (preg_match('/^[\x20-\x7e]*$/', $s) && strlen($s) < 1000) return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
        return ['lit' => $s];
    }

    /**
     * Send a command and collect the response. $cmd is a string, or a list of strings and ['lit' => bytes]
     * parts (each literal waits for the server's "+" continuation).
     * @return array{ok:bool, lines:array, literals:array, status:string}
     */
    public function command($cmd): array {
        if (!$this->fp) return ['ok' => false, 'lines' => [], 'literals' => [], 'status' => 'Not connected'];
        $tag = "S" . (++$this->tag);
        $parts = is_array($cmd) ? $cmd : [$cmd];
        $buf = "$tag ";
        foreach ($parts as $p) {
            if (is_array($p)) {
                $data = (string)$p['lit'];
                fwrite($this->fp, $buf . '{' . strlen($data) . "}\r\n");
                $buf = '';
                $cont = fgets($this->fp);
                if ($cont === false || $cont[0] !== '+') {
                    $this->lastError = trim((string)$cont);
                    // The tagged NO for the aborted command may already be in $cont
                    return ['ok' => false, 'lines' => [], 'literals' => [], 'status' => $this->lastError];
                }
                fwrite($this->fp, $data);
            } else {
                $buf .= $p;
            }
        }
        fwrite($this->fp, $buf . "\r\n");

        $lines = []; $literals = [];
        while (($line = fgets($this->fp)) !== false) {
            // A line ending in {n} is followed by n raw bytes
            while (preg_match('/\{(\d+)\}\r\n$/', $line, $m)) {
                $len = (int)$m[1]; $data = '';
                while (strlen($data) < $len && !feof($this->fp)) {
                    $chunk = fread($this->fp, $len - strlen($data));
                    if ($chunk === false || $chunk === '') break;
                    $data .= $chunk;
                }
                $literals[] = $data;
                $line = substr(rtrim($line, "\r\n"), 0, -strlen($m[0]) + 2) . "\x00LIT" . (count($literals) - 1) . "\x00" . fgets($this->fp);
            }
            if (strpos($line, "$tag ") === 0) {
                $status = trim(substr($line, strlen($tag) + 1));
                $ok = stripos($status, 'OK') === 0;
                if (!$ok) $this->lastError = $status;
                return ['ok' => $ok, 'lines' => $lines, 'literals' => $literals, 'status' => $status];
            }
            $lines[] = rtrim($line, "\r\n");
        }
        $this->lastError = 'Mail server connection closed';
        @fclose($this->fp); $this->fp = null;
        return ['ok' => false, 'lines' => $lines, 'literals' => $literals, 'status' => $this->lastError];
    }

    public function capabilities(): array {
        if ($this->caps !== null) return $this->caps;
        $r = $this->command('CAPABILITY');
        $caps = [];
        foreach ($r['lines'] as $l) if (stripos($l, '* CAPABILITY') === 0) $caps = array_map('strtoupper', preg_split('/\s+/', trim(substr($l, 12))));
        return $this->caps = $caps;
    }

    public function has(string $cap): bool { return in_array(strtoupper($cap), $this->capabilities(), true); }

    /** Folders as [['name' => raw IMAP name, 'label' => readable UTF-8, 'flags' => ..., 'delimiter' => ...]] */
    private ?array $folders = null;

    public function listFolders(): array {
        if ($this->folders !== null) return $this->folders;
        $r = $this->command('LIST "" "*"');
        $out = [];
        foreach ($r['lines'] as $i => $l) {
            if (!preg_match('/^\* LIST \(([^)]*)\) ("(?:[^"\\\\]|\\\\.)*"|NIL) (.+)$/', $l, $m)) continue;
            $name = trim($m[3], " \t\r\n");   // not plain trim(): it would strip the NUL literal markers
            if (preg_match('/^\x00LIT(\d+)\x00$/', $name, $lm)) $name = $r['literals'][(int)$lm[1]] ?? '';
            elseif ($name !== '' && $name[0] === '"') $name = stripcslashes(substr($name, 1, -1));
            $delim = $m[2] === 'NIL' ? '' : stripcslashes(substr($m[2], 1, -1));
            $out[] = ['name' => $name, 'label' => self::decodeFolder($name), 'flags' => $m[1], 'delimiter' => $delim];
        }
        return $this->folders = $out;
    }

    /** Drop the cached folder list after CREATE / RENAME / DELETE */
    public function forgetFolders(): void { $this->folders = null; }

    /** IMAP folder names are modified UTF-7 */
    public static function decodeFolder(string $name): string {
        if (strpos($name, '&') === false) return $name;
        if (function_exists('mb_convert_encoding')) {
            $d = @mb_convert_encoding($name, 'UTF-8', 'UTF7-IMAP');
            if (is_string($d) && $d !== '') return $d;
        }
        return $name;
    }

    /** MESSAGES / UNSEEN of a folder without selecting it */
    public function status(string $folder): array {
        $r = $this->command(['STATUS ', $this->quote($folder), ' (MESSAGES UNSEEN)']);
        $out = ['messages' => 0, 'unseen' => 0];
        foreach ($r['lines'] as $l) {
            if (preg_match('/MESSAGES (\d+)/', $l, $m)) $out['messages'] = (int)$m[1];
            if (preg_match('/UNSEEN (\d+)/', $l, $m)) $out['unseen'] = (int)$m[1];
        }
        return $out;
    }

    private ?string $selected = null;

    public function select(string $folder): array {
        if ($this->selected === $folder) return ['ok' => true, 'exists' => -1];
        $r = $this->command(['SELECT ', $this->quote($folder)]);
        $info = ['ok' => $r['ok'], 'exists' => 0];
        foreach ($r['lines'] as $l) if (preg_match('/^\* (\d+) EXISTS/', $l, $m)) $info['exists'] = (int)$m[1];
        $this->selected = $r['ok'] ? $folder : null;
        return $info;
    }

    public function unselect(): void { if ($this->selected !== null) { $this->command('CLOSE'); $this->selected = null; } }

    /**
     * UIDs matching $criteria (list of strings / literal parts), newest first.
     * Uses SORT (REVERSE ARRIVAL) when the server supports it, otherwise UID order.
     */
    public function searchUids($criteria = 'ALL'): array {
        $crit = is_array($criteria) ? $criteria : [$criteria];
        $utf8 = false;
        foreach ($crit as $c) if (is_array($c)) $utf8 = true;
        if ($this->has('SORT')) {
            $r = $this->command(array_merge(['UID SORT (REVERSE ARRIVAL) UTF-8 '], $crit));
            if ($r['ok']) {
                foreach ($r['lines'] as $l) if (strpos($l, '* SORT') === 0) return array_map('intval', array_values(array_filter(explode(' ', trim(substr($l, 6))))));
                return [];
            }
        }
        $r = $this->command(array_merge([$utf8 ? 'UID SEARCH CHARSET UTF-8 ' : 'UID SEARCH '], $crit));
        $uids = [];
        foreach ($r['lines'] as $l) if (strpos($l, '* SEARCH') === 0) $uids = array_map('intval', array_values(array_filter(explode(' ', trim(substr($l, 8))))));
        rsort($uids);
        return $uids;
    }

    /** List rows (headers, flags, size, preview) for UIDs in the selected folder, in the given order */
    public function fetchSummaries(array $uids, bool $preview = true): array {
        $uids = array_values(array_filter(array_map('intval', $uids)));
        if (!$uids) return [];
        $items = 'UID FLAGS RFC822.SIZE INTERNALDATE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES CONTENT-TYPE CONTENT-TRANSFER-ENCODING)]'
            . ($preview ? ' BODY.PEEK[TEXT]<0.2500>' : '');
        $r = $this->command('UID FETCH ' . implode(',', $uids) . " ($items)");
        $out = [];
        foreach ($r['lines'] as $l) {
            if (strpos($l, '* ') !== 0 || stripos($l, 'FETCH') === false || !preg_match('/UID (\d+)/', $l, $mu)) continue;
            $uid = (int)$mu[1];
            preg_match('/FLAGS \(([^)]*)\)/', $l, $mf);
            preg_match('/RFC822\.SIZE (\d+)/', $l, $ms);
            preg_match('/INTERNALDATE "([^"]+)"/', $l, $mi);
            $hdr = '';
            if (preg_match('/BODY\[HEADER\.FIELDS[^\]]*\]\s*\x00LIT(\d+)\x00/', $l, $mh)) $hdr = $r['literals'][(int)$mh[1]] ?? '';
            $text = '';
            if (preg_match('/BODY\[TEXT\](?:<\d+>)?\s*\x00LIT(\d+)\x00/', $l, $mt)) $text = $r['literals'][(int)$mt[1]] ?? '';
            elseif (preg_match('/BODY\[TEXT\](?:<\d+>)?\s*"((?:[^"\\\\]|\\\\.)*)"/', $l, $mt)) $text = stripcslashes($mt[1]);
            $h = SdaMime::parseHeaders($hdr);
            $flags = isset($mf[1]) ? preg_split('/\s+/', trim($mf[1])) : [];
            $has = fn($f) => in_array(strtolower($f), array_map('strtolower', $flags), true);
            $ctype = $h['content-type'] ?? 'text/plain';
            $date = ($ts = strtotime($h['date'] ?? '')) ? $ts : (isset($mi[1]) ? strtotime($mi[1]) : 0);
            $out[$uid] = [
                'uid' => $uid,
                'seen' => $has('\\Seen'), 'answered' => $has('\\Answered'), 'flagged' => $has('\\Flagged'), 'draft' => $has('\\Draft'),
                'keywords' => array_values(array_filter($flags, fn($k) => $k !== '' && $k[0] !== '\\' && $k[0] !== '$')),
                'from' => SdaMime::decodeHeader($h['from'] ?? ''),
                'to' => SdaMime::decodeHeader($h['to'] ?? ''),
                'cc' => SdaMime::decodeHeader($h['cc'] ?? ''),
                'subject' => SdaMime::decodeHeader($h['subject'] ?? ''),
                'date' => $date ? date('c', $date) : null,
                'size' => (int)($ms[1] ?? 0),
                'message_id' => trim($h['message-id'] ?? ''),
                'in_reply_to' => trim($h['in-reply-to'] ?? ''),
                'references' => trim($h['references'] ?? ''),
                'attach' => (bool)preg_match('#multipart/(mixed|related; *type="?application)#i', $ctype),
                'preview' => $preview ? SdaMime::snippet($ctype, $h['content-transfer-encoding'] ?? '', $text) : '',
            ];
        }
        $sorted = [];
        foreach ($uids as $u) if (isset($out[$u])) $sorted[] = $out[$u];
        return $sorted;
    }

    /** Full raw message without marking it read */
    public function fetchRaw(int $uid): ?string {
        $r = $this->command('UID FETCH ' . $uid . ' (BODY.PEEK[])');
        return $r['literals'][0] ?? null;
    }

    /** Header fields of one message (MESSAGE-ID, REFERENCES...) */
    public function fetchHeaders(int $uid, string $fields = 'MESSAGE-ID REFERENCES IN-REPLY-TO SUBJECT'): array {
        $r = $this->command('UID FETCH ' . $uid . " (BODY.PEEK[HEADER.FIELDS ($fields)])");
        return isset($r['literals'][0]) ? SdaMime::parseHeaders($r['literals'][0]) : [];
    }

    /** +FLAGS / -FLAGS on a set of UIDs (e.g. "4,7,9") */
    public function store($uids, string $flags, bool $on = true): bool {
        $set = is_array($uids) ? implode(',', array_map('intval', $uids)) : (string)$uids;
        if ($set === '') return true;
        return $this->command('UID STORE ' . $set . ($on ? ' +' : ' -') . 'FLAGS.SILENT (' . $flags . ')')['ok'];
    }

    /** Move UIDs to another folder (MOVE, or COPY + delete for servers without it) */
    public function move($uids, string $folder): bool {
        $set = is_array($uids) ? implode(',', array_map('intval', $uids)) : (string)$uids;
        if ($set === '') return true;
        if ($this->has('MOVE') && $this->command(['UID MOVE ' . $set . ' ', $this->quote($folder)])['ok']) return true;
        if (!$this->command(['UID COPY ' . $set . ' ', $this->quote($folder)])['ok']) return false;
        $this->store($set, '\\Deleted');
        return $this->expunge($set);
    }

    public function expunge($uids): bool {
        $set = is_array($uids) ? implode(',', array_map('intval', $uids)) : (string)$uids;
        if ($this->has('UIDPLUS')) return $this->command('UID EXPUNGE ' . $set)['ok'];
        return $this->command('EXPUNGE')['ok'];
    }

    /** Delete for good (Trash / Spam / drafts) */
    public function destroy($uids): bool {
        $set = is_array($uids) ? implode(',', array_map('intval', $uids)) : (string)$uids;
        if ($set === '') return true;
        return $this->store($set, '\\Deleted') && $this->expunge($set);
    }

    /** Append a message; returns the new UID when the server reports it (UIDPLUS), true when it can't, false on failure */
    public function append(string $folder, string $raw, string $flags = '\\Seen') {
        $this->selected = null;
        $r = $this->command(['APPEND ', $this->quote($folder), " ($flags) ", ['lit' => $raw]]);
        if (!$r['ok']) return false;
        if (preg_match('/APPENDUID \d+ (\d+)/', $r['status'], $m)) return (int)$m[1];
        return true;
    }

    public function createFolder(string $name): bool {
        $ok = $this->command(['CREATE ', $this->quote($name)])['ok'];
        if ($ok) $this->command(['SUBSCRIBE ', $this->quote($name)]);
        return $ok;
    }
}

/**
 * Just enough MIME to show messages: headers, text/html parts, attachments, cid: inline images, previews.
 */
class SdaMime {
    public static function parseHeaders(string $block): array {
        $block = preg_replace("/\r?\n[ \t]+/", ' ', $block); // unfold
        $h = [];
        foreach (preg_split("/\r?\n/", (string)$block) as $line) {
            if (strpos($line, ':') === false) continue;
            [$k, $v] = explode(':', $line, 2);
            $k = strtolower(trim($k));
            if ($k !== '' && !isset($h[$k])) $h[$k] = trim($v);
        }
        return $h;
    }

    public static function decodeHeader(string $v): string {
        if ($v === '') return '';
        if (strpos($v, '=?') === false) return self::toUtf8($v, null);
        $d = @iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if ($d === false && function_exists('mb_decode_mimeheader')) $d = @mb_decode_mimeheader($v);
        return $d !== false ? $d : $v;
    }

    private static function toUtf8(string $s, ?string $charset): string {
        if ($charset && strcasecmp($charset, 'utf-8') !== 0 && strcasecmp($charset, 'us-ascii') !== 0) {
            $c = @mb_convert_encoding($s, 'UTF-8', $charset);
            if (is_string($c)) return $c;
        }
        if (!mb_check_encoding($s, 'UTF-8')) {
            $c = @mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
            if (is_string($c)) return $c;
        }
        return $s;
    }

    public static function param(string $header, string $name): ?string {
        // RFC 2231 name*=utf-8''encoded, then plain name="value" / name=value
        if (preg_match('/' . $name . '\*=\s*([^\';]*)\'[^\']*\'([^;\s]+)/i', $header, $m)) {
            return self::toUtf8(rawurldecode($m[2]), $m[1] ?: 'utf-8');
        }
        if (preg_match('/(?:^|;)\s*' . $name . '\s*=\s*"((?:[^"\\\\]|\\\\.)*)"/i', $header, $m)) return stripcslashes($m[1]);
        if (preg_match('/(?:^|;)\s*' . $name . '\s*=\s*([^";\s]+)/i', $header, $m)) return $m[1];
        return null;
    }

    private static function decodeBody(string $body, string $encoding, ?string $charset, bool $text = true): string {
        $encoding = strtolower(trim($encoding));
        if ($encoding === 'base64') $body = (string)base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body));
        elseif ($encoding === 'quoted-printable') $body = quoted_printable_decode($body);
        return $text ? self::toUtf8($body, $charset) : $body;
    }

    /**
     * Parse a raw message into headers, text, html, attachments (name, type, data, cid) and inline
     * images keyed by Content-ID (the HTML references them as "cid:...").
     */
    public static function parse(string $raw): array {
        $result = ['headers' => [], 'text' => '', 'html' => '', 'attachments' => [], 'inline' => [], 'calendar' => ''];
        self::walk($raw, $result, true, 0);
        return $result;
    }

    private static function walk(string $raw, array &$result, bool $top, int $depth): void {
        if ($depth > 12) return;
        $split = preg_split("/\r?\n\r?\n/", $raw, 2);
        $headers = self::parseHeaders($split[0]);
        $body = $split[1] ?? '';
        if ($top) $result['headers'] = $headers;

        $ctype = $headers['content-type'] ?? 'text/plain';
        $disp = $headers['content-disposition'] ?? '';
        $mime = strtolower(trim(explode(';', $ctype)[0]));
        $cte = $headers['content-transfer-encoding'] ?? '';

        if (strpos($mime, 'multipart/') === 0) {
            $boundary = self::param($ctype, 'boundary');
            if (!$boundary) return;
            $parts = explode('--' . $boundary, $body);
            array_shift($parts); // preamble
            foreach ($parts as $part) {
                if (strpos($part, '--') === 0) break; // closing boundary
                self::walk(ltrim($part, "\r\n"), $result, false, $depth + 1);
            }
            return;
        }
        if ($mime === 'message/rfc822' && stripos($disp, 'attachment') !== 0) {
            // Forwarded message inline: show its body too
            self::walk(self::decodeBody($body, $cte, null, false), $result, false, $depth + 1);
            return;
        }

        $filename = self::param($disp, 'filename') ?: self::param($ctype, 'name');
        $cid = trim($headers['content-id'] ?? '', " \t<>");
        if ($cid !== '' && strpos($mime, 'image/') === 0 && stripos($disp, 'attachment') !== 0) {
            $result['inline'][$cid] = ['type' => $mime, 'data' => self::decodeBody($body, $cte, null, false)];
            if (!$filename) return; // purely inline (e.g. a signature logo)
        }
        if ($mime === 'text/calendar' && $result['calendar'] === '') $result['calendar'] = self::decodeBody($body, $cte, self::param($ctype, 'charset'));
        if ($filename || stripos($disp, 'attachment') === 0) {
            $result['attachments'][] = [
                'name' => self::decodeHeader($filename ?: ($mime === 'message/rfc822' ? 'message.eml' : 'attachment')),
                'type' => $mime,
                'data' => self::decodeBody($body, $cte, null, false),
                'cid' => $cid,
            ];
            return;
        }
        $decoded = self::decodeBody($body, $cte, self::param($ctype, 'charset'));
        if ($mime === 'text/html' && $result['html'] === '') $result['html'] = $decoded;
        elseif ($mime === 'text/plain' && $result['text'] === '') $result['text'] = $decoded;
    }

    /** About 160 characters of readable text from the start of a message body (list previews) */
    public static function snippet(string $ctype, string $cte, string $partial, int $depth = 0): string {
        if ($partial === '' || $depth > 4) return '';
        $mime = strtolower(trim(explode(';', $ctype)[0]));
        if (strpos($mime, 'multipart/') === 0) {
            $boundary = self::param($ctype, 'boundary');
            if (!$boundary) return '';
            $parts = explode('--' . $boundary, $partial);
            array_shift($parts);
            $fallback = '';
            foreach ($parts as $part) {
                $split = preg_split("/\r?\n\r?\n/", ltrim($part, "\r\n"), 2);
                $h = self::parseHeaders($split[0]);
                $t = self::snippet($h['content-type'] ?? 'text/plain', $h['content-transfer-encoding'] ?? '', $split[1] ?? '', $depth + 1);
                if ($t !== '' && stripos($h['content-type'] ?? 'text/plain', 'text/plain') !== false) return $t;
                if ($t !== '' && $fallback === '') $fallback = $t;
            }
            return $fallback;
        }
        if (strpos($mime, 'text/') !== 0) return '';
        $enc = strtolower(trim($cte));
        if ($enc === 'base64') {
            $b = preg_replace('/[^A-Za-z0-9+\/=]/', '', $partial);
            $partial = (string)base64_decode(substr($b, 0, intdiv(strlen($b), 4) * 4));
        } elseif ($enc === 'quoted-printable') {
            $partial = quoted_printable_decode(preg_replace('/=[0-9A-F]?$/i', '', $partial));
        }
        $partial = self::toUtf8($partial, self::param($ctype, 'charset'));
        if ($mime === 'text/html') {
            $partial = preg_replace('#<(style|script|head|title)\b[^>]*>.*?(</\1>|$)#is', ' ', $partial);
            $partial = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', ' ', $partial)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $partial = preg_replace('/^>.*$/m', '', $partial);          // quoted lines
        $t = trim(preg_replace('/\s+/u', ' ', $partial));
        $t = mb_substr($t, 0, 180);
        return mb_check_encoding($t, 'UTF-8') ? $t : '';
    }
}
