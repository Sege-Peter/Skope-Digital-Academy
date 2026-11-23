<?php
/**
 * Admin webmail JSON API (admin/mail -> admin/api-mailbox). Admins only; every POST needs the CSRF token
 * (header X-CSRF-Token). Helpers: includes/mailbox.php. One academy mailbox (box 'main').
 *
 * GET  ?action=boxes                                   the mailbox, me, limits
 * GET  ?action=folders                                 folders by role, custom folders, labels, counts, gmail
 * GET  ?action=list&view=folder|starred|all|label|scheduled&folder=&label=&q=&page=
 * GET  ?action=read&f=&uid=[&single=1]                 the conversation of a message (marks it read)
 * GET  ?action=attachment&f=&uid=&i=[&inline=1]        download / preview an attachment
 * GET  ?action=contacts&q=                             address autocomplete (users + mailbox_contacts)
 * GET  ?action=poll&since=                             new mail check + scheduled/snooze processing
 * GET  ?action=signature                               my signature, preview, templates
 * POST {action: op, items:[{f,u}], op, target?, label?, until?}
 *        op: read unread star unstar label unlabel archive inbox notspam unsnooze trash spam move snooze delete_forever
 * POST {action: send|draft_save, to, cc, bcc, subject, html, quoted_html, signature, attachments, refs, reply, draft, schedule_at}
 * POST {action: draft_delete|empty|scheduled_cancel|folder_create|folder_rename|folder_delete|label_save|label_delete|
 *               signature_save|signature_preview, ...}
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/ui.php';          // ax_log()
require_once __DIR__ . '/../includes/mailbox.php';

$out = function (array $d, int $code = 200): never { http_response_code($code); echo json_encode($d, JSON_INVALID_UTF8_SUBSTITUTE); exit; };

if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'admin') $out(['success' => false, 'message' => 'Your session has ended. Please sign in again.'], 401);
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && !verifyCsrf()) $out(['success' => false, 'message' => 'Your session expired or the page is out of date. Refresh the page and try again.'], 403);
$user = currentUser();                 // ax_log() reads the global $user
$me = sda_mbx_me($pdo);
session_write_close();                 // don't block the admin's other tabs while IMAP works
@set_time_limit(120);

$in = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
$action = (string)($in['action'] ?? '');

/** [{f,u}] -> [folder => [uids]] (validated, max 300) */
function mbx_items(SdaImap $imap, array $items): array {
    $by = [];
    foreach (array_slice($items, 0, 300) as $it) {
        $f = (string)($it['f'] ?? ''); $u = (int)($it['u'] ?? 0);
        if ($f === '' || $u <= 0) continue;
        $by[$f][$u] = $u;
    }
    if (!$by) sda_mbx_fail('Select at least one conversation.');
    foreach (array_keys($by) as $f) if (!sda_mbx_folder_ok($imap, $f)) sda_mbx_fail('Unknown folder. Refresh the page.');
    return array_map('array_values', $by);
}

/** Messages from several folders merged newest first (non-Gmail Starred, All mail, labels) */
function mbx_multi_list(SdaImap $imap, array $folders, array $criteria, int $page): array {
    $all = []; $total = 0;
    $need = $page * SDA_MBX_PAGE;
    foreach (array_unique(array_filter($folders)) as $f) {
        if (!sda_mbx_folder_ok($imap, $f)) continue;
        $imap->select($f);
        $uids = $imap->searchUids($criteria);
        $total += count($uids);
        foreach ($imap->fetchSummaries(array_slice($uids, 0, min($need, 300))) as $m) { $m['f'] = $f; $all[] = $m; }
    }
    usort($all, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
    return [array_slice($all, ($page - 1) * SDA_MBX_PAGE, SDA_MBX_PAGE), $total];
}

/** One parsed message for the reading pane */
function mbx_message_view(array $sum, string $raw, string $folder): array {
    $msg = SdaMime::parse($raw);
    $h = $msg['headers'];
    $html = $msg['html'] !== '' ? sda_mbx_clean_incoming($msg['html']) : '';
    if ($html !== '' && $msg['inline']) {
        $budget = 6 * 1048576;
        $html = preg_replace_callback('/cid:([^"\'\s)>]+)/i', function ($m) use ($msg, &$budget) {
            $img = $msg['inline'][rawurldecode($m[1])] ?? null;
            if (!$img || !preg_match('#^image/(png|jpe?g|gif|webp)$#', $img['type']) || strlen($img['data']) > $budget) return $m[0];
            $budget -= strlen($img['data']);
            return 'data:' . $img['type'] . ';base64,' . base64_encode($img['data']);
        }, $html);
    }
    $atts = [];
    foreach ($msg['attachments'] as $i => $a) {
        $inline = ($a['cid'] ?? '') !== '' && stripos($msg['html'], 'cid:' . $a['cid']) !== false;
        if ($inline) continue;
        $atts[] = ['i' => $i, 'name' => $a['name'], 'size' => strlen($a['data']), 'type' => $a['type'],
                   'preview' => (bool)preg_match('#^(image/(png|jpe?g|gif|webp)|application/pdf)$#', $a['type'])];
    }
    return [
        'f' => $folder, 'uid' => (int)$sum['uid'],
        'from' => SdaMime::decodeHeader($h['from'] ?? ''), 'to' => SdaMime::decodeHeader($h['to'] ?? ''),
        'cc' => SdaMime::decodeHeader($h['cc'] ?? ''), 'bcc' => SdaMime::decodeHeader($h['bcc'] ?? ''),
        'reply_to' => SdaMime::decodeHeader($h['reply-to'] ?? ''),
        'subject' => SdaMime::decodeHeader($h['subject'] ?? ''),
        'date' => ($ts = strtotime($h['date'] ?? '')) ? date('c', $ts) : $sum['date'],
        'message_id' => trim($h['message-id'] ?? ''), 'references' => trim($h['references'] ?? ''),
        'seen' => $sum['seen'], 'flagged' => $sum['flagged'], 'draft' => $sum['draft'], 'keywords' => $sum['keywords'],
        'html' => $html, 'text' => $html === '' ? $msg['text'] : '', 'attachments' => $atts,
        'invite' => $msg['calendar'] !== '' && preg_match('/^SUMMARY[^:]*:(.*)$/mi', $msg['calendar'], $mm) ? trim($mm[1]) : null,
        'unsubscribe' => preg_match('/<(https?:[^>]+)>/', (string)($h['list-unsubscribe'] ?? ''), $um) ? $um[1] : null,
    ];
}

/** Compose payload -> message array for sda_mbx_build_raw() (validates everything) */
function mbx_compose(PDO $pdo, SdaImap $imap, array $in, bool $draft = false): array {
    $to = sda_mbx_parse_addresses($in['to'] ?? []);
    $cc = sda_mbx_parse_addresses($in['cc'] ?? []);
    $bcc = sda_mbx_parse_addresses($in['bcc'] ?? []);
    if (!$draft && !$to && !$cc && !$bcc) sda_mbx_fail('Add at least one recipient.');
    if (count($to) + count($cc) + count($bcc) > SDA_MBX_MAX_RCPT) sda_mbx_fail('Up to ' . SDA_MBX_MAX_RCPT . ' recipients per message.');
    $subject = mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', (string)($in['subject'] ?? ''))), 0, 250);
    $body = sda_mbx_clean_html((string)($in['html'] ?? ''));
    $quoted = sda_mbx_clean_html((string)($in['quoted_html'] ?? ''));
    if (!$draft && $subject === '' && $body === '' && $quoted === '') sda_mbx_fail('The message is empty.');

    $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#1e293b">' . $body . '</div>';
    $text = sda_mbx_html_to_text($body);
    if (!$draft && !empty($in['signature'])) {
        $sig = sda_mbx_signature($pdo);
        $isReply = !empty($in['reply']) || $quoted !== '';
        if (!empty($sig['enabled']) && (empty($sig['skip_replies']) || !$isReply)) {
            $html .= sda_mbx_signature_html($sig);
            $text .= "\n\n" . sda_mbx_signature_text($sig);
        }
    }
    if ($quoted !== '') { $html .= '<div style="margin-top:18px">' . $quoted . '</div>'; $text .= "\n\n" . sda_mbx_html_to_text($quoted); }

    // Attachments: new files (base64) and ones carried over from an existing message (forward / draft)
    $atts = []; $size = 0; $max = sda_mbx_max_attach();
    foreach (array_slice((array)($in['attachments'] ?? []), 0, 25) as $a) {
        $data = base64_decode((string)($a['data'] ?? ''), true);
        if ($data === false) sda_mbx_fail('An attachment could not be read.');
        $size += strlen($data);
        $atts[] = ['name' => mb_substr((string)($a['name'] ?? 'attachment'), 0, 150), 'type' => (string)($a['type'] ?? ''), 'data' => $data];
    }
    $refs = [];
    foreach (array_slice((array)($in['refs'] ?? []), 0, 25) as $r) $refs[(string)($r['f'] ?? '')][(int)($r['u'] ?? 0)][] = (int)($r['i'] ?? -1);
    foreach ($refs as $f => $byUid) {
        if (!sda_mbx_folder_ok($imap, $f)) continue;
        $imap->select($f);
        foreach ($byUid as $uid => $idx) {
            $raw = $uid > 0 ? $imap->fetchRaw($uid) : null;
            if ($raw === null) continue;
            $parsed = SdaMime::parse($raw)['attachments'];
            foreach (array_unique($idx) as $i) {
                if (!isset($parsed[$i])) continue;
                $size += strlen($parsed[$i]['data']);
                $atts[] = ['name' => $parsed[$i]['name'], 'type' => $parsed[$i]['type'], 'data' => $parsed[$i]['data']];
            }
        }
    }
    if ($size > $max) sda_mbx_fail('Attachments are too large (up to ' . round($max / 1048576, 1) . ' MB per message).');

    $msg = ['to' => $to, 'cc' => $cc, 'bcc' => $bcc, 'subject' => $subject, 'html' => $html, 'text' => trim($text), 'attachments' => $atts,
            'from_name' => sda_mbx_box()['name']];
    // Threading for replies
    $reply = (array)($in['reply'] ?? []);
    if (!empty($reply['f']) && !empty($reply['u']) && sda_mbx_folder_ok($imap, (string)$reply['f'])) {
        $imap->select((string)$reply['f']);
        $oh = $imap->fetchHeaders((int)$reply['u']);
        if (!empty($oh['message-id'])) {
            $msg['in_reply_to'] = trim($oh['message-id']);
            $msg['references'] = trim(trim($oh['references'] ?? '') . ' ' . trim($oh['message-id']));
        }
    }
    return $msg;
}

/** Label rows on list/read for Gmail: user labels (raw folder names) and Important */
function mbx_gm_decorate(SdaImap $imap, array &$msgs): void {
    if (!$msgs) return;
    $meta = sda_mbx_gm_meta($imap, array_column($msgs, 'uid'));
    foreach ($msgs as &$m) {
        $x = $meta[$m['uid']] ?? null;
        if (!$x) continue;
        $m['keywords'] = $x['labels'];
        $m['important'] = in_array('\\Important', $x['system'], true);
        $m['inbox'] = in_array('\\Inbox', $x['system'], true);
        $m['thrid'] = $x['thrid'];
    }
    unset($m);
}

try {
    sda_mbx_ensure($pdo);

    // ── Reads ──────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        switch ($action) {
            case 'boxes': {
                $b = sda_mbx_box();
                $b['unseen'] = (int)$pdo->query("SELECT unseen FROM mailbox_state WHERE box = '" . SDA_MBX_BOX . "'")->fetchColumn();
                $out(['success' => true, 'boxes' => [$b], 'me' => ['name' => $me['name'], 'email' => $me['email']],
                      'max_attach' => sda_mbx_max_attach(), 'label_colors' => SDA_MBX_LABEL_COLORS, 'page_size' => SDA_MBX_PAGE]);
            }
            case 'folders': {
                $imap = sda_mbx_open($pdo);
                $roles = sda_mbx_roles($imap);
                $gmail = sda_mbx_is_gmail($imap);
                $folders = [];
                foreach ($imap->listFolders() as $f) {
                    if (stripos($f['flags'], '\\Noselect') !== false) continue;
                    $role = sda_mbx_role_of($roles, $f['name']);
                    // STATUS only where a count is shown (Gmail limits commands; labels show no counts there)
                    $want = in_array($role, ['inbox', 'drafts', 'spam', 'snoozed'], true) || ($role === 'folder' && !$gmail);
                    $st = $want ? $imap->status($f['name']) : ['messages' => null, 'unseen' => 0];
                    $label = preg_replace('#^(INBOX[./]|\[(Gmail|Google Mail)\]/)#i', '', $f['label']);
                    $folders[] = ['name' => $f['name'], 'label' => $label, 'role' => $role, 'unseen' => $st['unseen'], 'total' => $st['messages'],
                                  'system' => $gmail && sda_mbx_gm_system($f['name'])];
                }
                $inbox = array_values(array_filter($folders, fn($x) => $x['role'] === 'inbox'))[0] ?? ['unseen' => 0];
                $pdo->prepare("INSERT INTO mailbox_state (box, unseen, checked_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE unseen = VALUES(unseen), checked_at = NOW()")
                    ->execute([SDA_MBX_BOX, $inbox['unseen']]);
                $sch = $pdo->prepare("SELECT COUNT(*) FROM mailbox_scheduled WHERE box = ? AND status IN ('pending','failed')");
                $sch->execute([SDA_MBX_BOX]);
                $out(['success' => true, 'gmail' => $gmail, 'roles' => $roles, 'folders' => $folders, 'labels' => sda_mbx_labels($pdo, $imap, $roles),
                      'scheduled' => (int)$sch->fetchColumn()]);
            }
            case 'list': {
                $view = (string)($in['view'] ?? 'folder');
                $page = max(1, min(200, (int)($in['page'] ?? 1)));
                if ($view === 'scheduled') {
                    $st = $pdo->prepare("SELECT id, send_at, subject, recipients, status, error FROM mailbox_scheduled WHERE box = ? AND status IN ('pending','failed','sending') ORDER BY send_at LIMIT 200");
                    $st->execute([SDA_MBX_BOX]);
                    $out(['success' => true, 'scheduled' => $st->fetchAll(PDO::FETCH_ASSOC), 'messages' => [], 'total' => 0, 'pages' => 1, 'page' => 1]);
                }
                $imap = sda_mbx_open($pdo);
                $roles = sda_mbx_roles($imap);
                $gmail = sda_mbx_is_gmail($imap);
                $crit = sda_mbx_search_criteria($imap, (string)($in['q'] ?? ''));
                $label = (string)($in['label'] ?? '');
                $folder = null;
                if ($gmail) {
                    // Gmail: every view is a folder (Starred, All Mail and each label are IMAP folders)
                    if ($view === 'label') {
                        if (!in_array($label, array_column(sda_mbx_gm_labels($imap, $roles), 'id'), true)) sda_mbx_fail('That label no longer exists.', 404);
                        $folder = $label;
                    } elseif ($view === 'starred') {
                        $folder = $roles['starred'];
                        if (!$folder) { $folder = 'INBOX'; $crit = sda_mbx_and(['FLAGGED'], $crit); }
                    } elseif ($view === 'all') {
                        $folder = $roles['archive'] ?: 'INBOX';
                    } else {
                        $folder = (string)($in['folder'] ?? 'INBOX');
                    }
                    if (!sda_mbx_folder_ok($imap, $folder)) sda_mbx_fail('Unknown folder. Refresh the page.', 404);
                    $imap->select($folder);
                    $uids = $imap->searchUids($crit);
                    $total = count($uids);
                    $msgs = $imap->fetchSummaries(array_slice($uids, ($page - 1) * SDA_MBX_PAGE, SDA_MBX_PAGE));
                    foreach ($msgs as &$m) $m['f'] = $folder;
                    unset($m);
                    mbx_gm_decorate($imap, $msgs);
                } else {
                    if ($view === 'label') {
                        if (!in_array($label, array_column(sda_mbx_kw_labels(), 'id'), true)) sda_mbx_fail('That label no longer exists.', 404);
                        $crit = sda_mbx_and(['KEYWORD ' . $label], $crit);
                    }
                    if ($view === 'starred') $crit = sda_mbx_and(['FLAGGED'], $crit);
                    if (in_array($view, ['starred', 'all', 'label'], true)) {
                        [$msgs, $total] = mbx_multi_list($imap, [$roles['inbox'], $roles['archive'], $roles['sent'], $view === 'all' ? null : $roles['snoozed']], $crit, $page);
                    } else {
                        $folder = (string)($in['folder'] ?? 'INBOX');
                        if (!sda_mbx_folder_ok($imap, $folder)) sda_mbx_fail('Unknown folder. Refresh the page.', 404);
                        $imap->select($folder);
                        $uids = $imap->searchUids($crit);
                        $total = count($uids);
                        $msgs = $imap->fetchSummaries(array_slice($uids, ($page - 1) * SDA_MBX_PAGE, SDA_MBX_PAGE));
                        foreach ($msgs as &$m) $m['f'] = $folder;
                        unset($m);
                    }
                }
                // Snooze times for the Snoozed folder
                $snooze = [];
                if ($folder !== null && $folder === $roles['snoozed']) {
                    $st = $pdo->prepare("SELECT message_id, until_at FROM mailbox_snoozed WHERE box = ? AND status = 'snoozed'");
                    $st->execute([SDA_MBX_BOX]);
                    $snooze = $st->fetchAll(PDO::FETCH_KEY_PAIR);
                }
                foreach ($msgs as &$m) {
                    $m['thread'] = $gmail && !empty($m['thrid']) ? 'gm:' . $m['thrid'] : sda_mbx_thread_key($m);
                    $m['role'] = sda_mbx_role_of($roles, $m['f']);
                    if ($snooze && isset($snooze[$m['message_id']])) $m['snoozed_until'] = date('c', strtotime($snooze[$m['message_id']]));
                    unset($m['references'], $m['in_reply_to']);
                }
                unset($m);
                $out(['success' => true, 'messages' => $msgs, 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / SDA_MBX_PAGE)), 'folder' => $folder]);
            }
            case 'read': {
                $imap = sda_mbx_open($pdo);
                $roles = sda_mbx_roles($imap);
                $gmail = sda_mbx_is_gmail($imap);
                $f = (string)($in['f'] ?? 'INBOX'); $uid = (int)($in['uid'] ?? 0);
                if (!sda_mbx_folder_ok($imap, $f)) sda_mbx_fail('Unknown folder.', 404);
                $imap->select($f);
                $first = $imap->fetchSummaries([$uid], false)[0] ?? null;
                if (!$first) sda_mbx_fail('This message is no longer here. It may have been moved or deleted.', 404);
                $first['f'] = $f;
                $role = sda_mbx_role_of($roles, $f);
                $isDraft = $role === 'drafts';
                $firstMeta = $gmail ? (sda_mbx_gm_meta($imap, [$uid])[$uid] ?? null) : null;
                $related = [$f . "\n" . $uid => $first];
                if (!$isDraft && empty($in['single'])) {
                    if ($gmail && $firstMeta && $firstMeta['thrid'] !== '') {
                        // Gmail knows the conversation: X-GM-THRID in this folder, then the rest from All Mail
                        $mids = [strtolower($first['message_id']) => 1];
                        $look = [$f];
                        if (!in_array($role, ['trash', 'spam', 'archive'], true) && $roles['archive']) $look[] = $roles['archive'];
                        foreach ($look as $cf) {
                            $imap->select($cf);
                            $uids = array_slice($imap->searchUids('X-GM-THRID ' . $firstMeta['thrid']), 0, 50);
                            foreach ($imap->fetchSummaries($uids, false) as $s) {
                                $mid = strtolower($s['message_id']);
                                if (isset($related[$cf . "\n" . $s['uid']]) || ($mid !== '' && isset($mids[$mid]))) continue;
                                $s['f'] = $cf; $related[$cf . "\n" . $s['uid']] = $s;
                                if ($mid !== '') $mids[$mid] = 1;
                            }
                        }
                    } else {
                        // The rest of the conversation: same base subject in Inbox, Sent, Archive and this folder, linked by Message-ID
                        $base = sda_mbx_base_subject($first['subject']);
                        if ($base !== '') {
                            $ids = array_flip(sda_mbx_ids($first['message_id'], $first['references'], $first['in_reply_to']));
                            $cands = [];
                            foreach (array_unique(array_filter([$f, $roles['inbox'], $roles['sent'], $roles['archive']])) as $cf) {
                                if ($cf === $roles['trash'] || $cf === $roles['spam'] || !sda_mbx_folder_ok($imap, $cf)) continue;
                                $imap->select($cf);
                                $uids = array_slice($imap->searchUids(['SUBJECT ', $imap->quote(mb_substr($base, 0, 120))]), 0, 40);
                                foreach ($imap->fetchSummaries($uids, false) as $s) { $s['f'] = $cf; $cands[] = $s; }
                            }
                            for ($pass = 0; $pass < 3; $pass++) {
                                foreach ($cands as $s) {
                                    $key = $s['f'] . "\n" . $s['uid'];
                                    if (isset($related[$key]) || sda_mbx_base_subject($s['subject']) !== $base) continue;
                                    $sids = sda_mbx_ids($s['message_id'], $s['references'], $s['in_reply_to']);
                                    if (array_intersect_key(array_flip($sids), $ids)) { $related[$key] = $s; foreach ($sids as $x) $ids[$x] = 1; }
                                }
                            }
                        }
                    }
                }
                // Same message in two folders (e.g. sent to ourselves) once only
                $seenIds = []; $list = [];
                foreach ($related as $s) {
                    $mid = strtolower($s['message_id']);
                    if ($mid !== '' && isset($seenIds[$mid]) && $s['f'] !== $f) continue;
                    $seenIds[$mid] = 1; $list[] = $s;
                }
                usort($list, fn($a, $b) => strcmp((string)$a['date'], (string)$b['date']));
                $list = array_slice($list, -25);
                $views = []; $bytes = 0;
                foreach ($list as $s) {
                    $imap->select($s['f']);
                    $raw = $imap->fetchRaw((int)$s['uid']);
                    if ($raw === null) continue;
                    $bytes += strlen($raw);
                    if ($bytes > 25 * 1048576 && $views) break;
                    $views[] = mbx_message_view($s, $raw, $s['f']);
                    if (!$s['seen'] && !$isDraft) $imap->store([(int)$s['uid']], '\\Seen');
                }
                // People who wrote to us become autocomplete suggestions
                $seenPeople = [];
                foreach ($views as $v) {
                    if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^<>\s]+@[^<>\s]+)>/', $v['from'], $pm) || preg_match('/^\s*()([^<>\s]+@[^<>\s]+)\s*$/', $v['from'], $pm)) {
                        if (filter_var($pm[2], FILTER_VALIDATE_EMAIL)) $seenPeople[] = ['email' => strtolower($pm[2]), 'name' => trim($pm[1])];
                    }
                }
                sda_mbx_remember_contacts($pdo, $seenPeople);
                $out(['success' => true, 'messages' => $views, 'subject' => $first['subject'], 'folder' => $f, 'role' => $role,
                      'labels' => $gmail ? ($firstMeta['labels'] ?? []) : $first['keywords'],
                      'important' => $gmail && $firstMeta && in_array('\\Important', $firstMeta['system'], true)]);
            }
            case 'attachment': {
                $imap = sda_mbx_open($pdo);
                $f = (string)($in['f'] ?? '');
                if (!sda_mbx_folder_ok($imap, $f)) sda_mbx_fail('Unknown folder.', 404);
                $imap->select($f);
                $raw = $imap->fetchRaw((int)($in['uid'] ?? 0));
                $att = $raw === null ? null : (SdaMime::parse($raw)['attachments'][(int)($in['i'] ?? -1)] ?? null);
                if (!$att) sda_mbx_fail('Attachment not found.', 404);
                sda_mbx_close();
                $name = preg_replace('/[^\w .()+\-]/u', '_', $att['name']) ?: 'attachment';
                $safe = (bool)preg_match('#^(image/(png|jpe?g|gif|webp)|application/pdf)$#', $att['type']);
                $inline = !empty($in['inline']) && $safe;
                header_remove('Content-Type');
                header('Content-Type: ' . ($inline ? $att['type'] : 'application/octet-stream'));
                header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox");
                header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($att['name']));
                header('Content-Length: ' . strlen($att['data']));
                header('Cache-Control: private, max-age=300');
                echo $att['data'];
                exit;
            }
            case 'contacts': {
                $q = trim((string)($in['q'] ?? ''));
                if (mb_strlen($q) < 1) $out(['success' => true, 'contacts' => []]);
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($q, 0, 60)) . '%';
                $st = $pdo->prepare("(SELECT LOWER(email) AS email, name, role, 2 AS w, 0 AS uses FROM users
                                        WHERE email <> '' AND status <> 'suspended' AND (email LIKE ? OR name LIKE ?) ORDER BY (role = 'admin') DESC, name LIMIT 20)
                                     UNION ALL
                                     (SELECT email, COALESCE(name, ''), '' AS role, 1 AS w, uses FROM mailbox_contacts WHERE email LIKE ? OR name LIKE ? ORDER BY uses DESC, last_used DESC LIMIT 20)");
                $st->execute([$like, $like, $like, $like]);
                $seen = []; $list = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    if (isset($seen[$r['email']])) continue;
                    $seen[$r['email']] = 1; $list[] = ['email' => $r['email'], 'name' => (string)$r['name'], 'role' => (string)$r['role']];
                }
                $out(['success' => true, 'contacts' => array_slice($list, 0, 12)]);
            }
            case 'poll': {
                // Scheduled sends and snoozes are processed here too (at most once a minute across admins)
                $tick = sda_mbx_maybe_tick($pdo);
                $imap = sda_mbx_open($pdo);
                $st = $imap->status('INBOX');
                $since = (int)($in['since'] ?? 0);
                $new = [];
                if ($since > 0) {
                    $imap->select('INBOX');
                    $uids = array_filter($imap->searchUids('UID ' . ($since + 1) . ':* UNSEEN'), fn($u) => $u > $since);
                    $new = array_map(fn($m) => ['uid' => $m['uid'], 'from' => $m['from'], 'subject' => $m['subject']], $imap->fetchSummaries(array_slice($uids, 0, 5), false));
                }
                $pdo->prepare("INSERT INTO mailbox_state (box, unseen, checked_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE unseen = VALUES(unseen), checked_at = NOW()")
                    ->execute([SDA_MBX_BOX, $st['unseen']]);
                $out(['success' => true, 'unseen' => $st['unseen'], 'new' => $new, 'counts' => [SDA_MBX_BOX => $st['unseen']], 'tick' => $tick]);
            }
            case 'signature': {
                $sig = sda_mbx_signature($pdo);
                $out(['success' => true, 'signature' => $sig, 'preview' => sda_mbx_signature_html(['enabled' => true] + $sig),
                      'templates' => SDA_SIG_TEMPLATES, 'socials' => SDA_SIG_SOCIALS, 'colors' => SDA_SIG_COLORS]);
            }
        }
        sda_mbx_fail('Unknown action.', 404);
    }

    // ── Writes ─────────────────────────────────────────────────────────────────
    if ($method !== 'POST') sda_mbx_fail('Method not allowed.', 405);
    $boxInfo = sda_mbx_box();

    switch ($action) {
        case 'op': {
            $imap = sda_mbx_open($pdo);
            $op = (string)($in['op'] ?? '');
            $roles = sda_mbx_roles($imap, $op === 'snooze');
            $gmail = sda_mbx_is_gmail($imap);
            $by = mbx_items($imap, (array)($in['items'] ?? []));
            $flagOps = ['read' => ['\\Seen', true], 'unread' => ['\\Seen', false], 'star' => ['\\Flagged', true], 'unstar' => ['\\Flagged', false]];
            $count = 0;
            $label = '';
            if (in_array($op, ['label', 'unlabel'], true)) {
                $label = (string)($in['label'] ?? '');
                $known = array_column(sda_mbx_labels($pdo, $imap, $roles), 'id');
                if (!in_array($label, $known, true)) sda_mbx_fail('That label no longer exists. Refresh the page.');
                if (!$gmail) $flagOps[$op] = [$label, $op === 'label'];
            }
            $until = 0;
            if ($op === 'snooze') {
                $until = strtotime((string)($in['until'] ?? ''));
                if (!$until || $until < time() + 60 || $until > time() + 366 * 86400) sda_mbx_fail('Choose a time in the future for the snooze.');
                if (!$roles['snoozed']) sda_mbx_fail('The Snoozed folder could not be created on the mail server.', 502);
            }
            foreach ($by as $f => $uids) {
                $imap->select($f);
                $role = sda_mbx_role_of($roles, $f);
                if (isset($flagOps[$op])) {
                    [$flag, $on] = $flagOps[$op];
                    if (!$imap->store($uids, $flag, $on)) sda_mbx_fail('The mail server did not accept the change.', 502);
                } elseif ($gmail && in_array($op, ['label', 'unlabel', 'important', 'unimportant'], true)) {
                    // Gmail labels (X-GM-LABELS); removing the label of the folder we are in drops the messages from it
                    $gl = in_array($op, ['important', 'unimportant'], true) ? '\Important' : $label;
                    if (!sda_mbx_gm_label($imap, $uids, $gl, in_array($op, ['label', 'important'], true))) sda_mbx_fail('Gmail did not accept the change.', 502);
                } elseif ($op === 'delete_forever') {
                    if (!in_array($role, ['trash', 'spam', 'drafts'], true)) sda_mbx_fail('Only messages in Trash, Spam or Drafts can be deleted forever.');
                    $imap->destroy($uids);
                } elseif ($op === 'snooze') {
                    $sums = $imap->fetchSummaries($uids, false);
                    $ins = $pdo->prepare("INSERT INTO mailbox_snoozed (box, message_id, subject, from_addr, until_at, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($sums as $s) {
                        if ($s['message_id'] === '') continue;   // can't be found again without a Message-ID
                        $pdo->prepare("UPDATE mailbox_snoozed SET status = 'cancelled' WHERE box = ? AND message_id = ? AND status = 'snoozed'")->execute([SDA_MBX_BOX, $s['message_id']]);
                        $ins->execute([SDA_MBX_BOX, $s['message_id'], mb_substr($s['subject'], 0, 250), mb_substr($s['from'], 0, 250), date('Y-m-d H:i:s', $until), $me['id'] ?: null]);
                    }
                    $imap->store($uids, '\\Seen');
                    if ($f !== $roles['snoozed']) {
                        $ok = $gmail ? sda_mbx_gm_move($imap, $roles, $f, $uids, $roles['snoozed']) : $imap->move($uids, $roles['snoozed']);
                        if (!$ok) sda_mbx_fail('The mail server could not snooze the messages.', 502);
                    }
                } else {
                    switch ($op) {
                        case 'archive': $target = $roles['archive']; break;
                        case 'inbox': case 'notspam': case 'unsnooze': $target = $roles['inbox']; break;
                        case 'trash': $target = $roles['trash']; break;
                        case 'spam': $target = $roles['spam']; break;
                        case 'move':
                            $target = (string)($in['target'] ?? '');
                            if (!sda_mbx_folder_ok($imap, $target)) sda_mbx_fail('Unknown folder.');
                            break;
                        default: sda_mbx_fail('Unknown action.');
                    }
                    if (!$target) sda_mbx_fail('That folder is missing on the mail server.', 502);
                    if ($target === $f) continue;
                    if ($op === 'unsnooze' || $f === $roles['snoozed']) {
                        $sums = $imap->fetchSummaries($uids, false);
                        $up = $pdo->prepare("UPDATE mailbox_snoozed SET status = 'cancelled' WHERE box = ? AND message_id = ? AND status = 'snoozed'");
                        foreach ($sums as $s) $up->execute([SDA_MBX_BOX, $s['message_id']]);
                    }
                    $ok = $gmail ? sda_mbx_gm_move($imap, $roles, $f, $uids, $target) : $imap->move($uids, $target);
                    if (!$ok) sda_mbx_fail('The mail server could not move the messages.', 502);
                }
                $count += count($uids);
            }
            $out(['success' => true, 'count' => $count]);
        }
        case 'empty': {
            $imap = sda_mbx_open($pdo);
            $roles = sda_mbx_roles($imap);
            $role = (string)($in['role'] ?? '');
            if (!in_array($role, ['trash', 'spam'], true) || !$roles[$role]) sda_mbx_fail('Only Trash and Spam can be emptied.');
            $imap->select($roles[$role]);
            $uids = $imap->searchUids('ALL');
            foreach (array_chunk($uids, 500) as $chunk) $imap->destroy($chunk);
            ax_log('mail.empty', 'mailbox', null, 'Emptied ' . $role . ' of ' . $boxInfo['address'] . ' (' . count($uids) . ' messages)');
            $out(['success' => true, 'count' => count($uids)]);
        }
        case 'send': {
            $imap = sda_mbx_open($pdo);
            $roles = sda_mbx_roles($imap);
            $msg = mbx_compose($pdo, $imap, $in);
            $when = trim((string)($in['schedule_at'] ?? ''));
            $draftUid = (int)($in['draft'] ?? 0);
            $rcpt = implode(', ', array_column(array_merge($msg['to'], $msg['cc'], $msg['bcc']), 'email'));
            if ($when !== '') {
                $ts = strtotime($when);
                if (!$ts || $ts < time() + 60 || $ts > time() + 366 * 86400) sda_mbx_fail('Choose a send time at least a minute from now (and within a year).');
                $msg['message_id'] = sda_message_id($boxInfo['address']);
                $payload = $msg;
                foreach ($payload['attachments'] as $i => $a) $payload['attachments'][$i]['data'] = base64_encode($a['data']);
                $json = json_encode($payload);
                if ($json === false || strlen($json) > 30 * 1048576) sda_mbx_fail('This message is too large to schedule.');
                $pdo->prepare("INSERT INTO mailbox_scheduled (box, user_id, send_at, subject, recipients, payload) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([SDA_MBX_BOX, $me['id'] ?: null, date('Y-m-d H:i:s', $ts), mb_substr($msg['subject'], 0, 250), mb_substr($rcpt, 0, 495), $json]);
                if ($draftUid && $roles['drafts']) { $imap->select($roles['drafts']); $imap->destroy([$draftUid]); }
                ax_log('mail.schedule', 'mailbox', null, 'Scheduled "' . mb_substr($msg['subject'], 0, 80) . '" to ' . mb_substr($rcpt, 0, 120) . ' for ' . date('j M Y H:i', $ts));
                $out(['success' => true, 'scheduled' => date('c', $ts), 'id' => (int)$pdo->lastInsertId()]);
            }
            $r = sda_mbx_send($pdo, $msg, $imap);
            if (!$r['ok']) sda_mbx_fail('The mail server did not accept the message: ' . mb_substr($r['error'], 0, 200), 502);
            if ($draftUid && $roles['drafts']) { $imap->select($roles['drafts']); $imap->destroy([$draftUid]); }
            $reply = (array)($in['reply'] ?? []);
            if (!empty($reply['f']) && !empty($reply['u']) && sda_mbx_folder_ok($imap, (string)$reply['f'])) {
                $imap->select((string)$reply['f']);
                $imap->store([(int)$reply['u']], '\\Answered');
            }
            ax_log('mail.send', 'mailbox', null, 'Sent "' . mb_substr($msg['subject'], 0, 80) . '" to ' . mb_substr($rcpt, 0, 150));
            $out(['success' => true, 'message_id' => $r['message_id']]);
        }
        case 'draft_save': {
            $imap = sda_mbx_open($pdo);
            $roles = sda_mbx_roles($imap);
            if (!$roles['drafts']) sda_mbx_fail('The Drafts folder is missing on the mail server.', 502);
            $msg = mbx_compose($pdo, $imap, $in, true);
            $raw = sda_mbx_build_raw($msg);
            if (strlen($raw) > 30 * 1048576) sda_mbx_fail('This draft is too large to save.');
            $uid = $imap->append($roles['drafts'], $raw, '\\Draft \\Seen');
            if ($uid === false) sda_mbx_fail('The draft could not be saved.', 502);
            $old = (int)($in['draft'] ?? 0);
            if ($old && $old !== $uid) { $imap->select($roles['drafts']); $imap->destroy([$old]); }
            $out(['success' => true, 'draft' => is_int($uid) ? $uid : 0, 'folder' => $roles['drafts'], 'saved_at' => date('c')]);
        }
        case 'draft_delete': {
            $imap = sda_mbx_open($pdo);
            $roles = sda_mbx_roles($imap);
            $uid = (int)($in['draft'] ?? 0);
            if ($uid && $roles['drafts']) { $imap->select($roles['drafts']); $imap->destroy([$uid]); }
            $out(['success' => true]);
        }
        case 'scheduled_cancel': {
            $st = $pdo->prepare("SELECT * FROM mailbox_scheduled WHERE id = ? AND box = ? AND status IN ('pending','failed')");
            $st->execute([(int)($in['id'] ?? 0), SDA_MBX_BOX]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) sda_mbx_fail('That message was already sent or cancelled.', 404);
            $pdo->prepare("UPDATE mailbox_scheduled SET status = 'cancelled', payload = '{}' WHERE id = ?")->execute([$row['id']]);
            $p = json_decode((string)$row['payload'], true) ?: [];
            // The body was saved with its wrapper div (and signature); give back what is inside the wrapper
            $html = (string)($p['html'] ?? '');
            if (preg_match('#^<div style="font-family:Arial[^"]*">(.*?)</div>(?=<table role="presentation"|<div style="margin-top:18px">|$)#s', $html, $hm)) $html = $hm[1];
            $out(['success' => true, 'message' => ['to' => $p['to'] ?? [], 'cc' => $p['cc'] ?? [], 'bcc' => $p['bcc'] ?? [], 'subject' => $p['subject'] ?? '',
                  'html' => $html, 'attachments' => array_map(fn($a) => ['name' => $a['name'], 'type' => $a['type'], 'data' => $a['data']], $p['attachments'] ?? [])]]);
        }
        case 'folder_create': case 'folder_rename': case 'folder_delete': {
            $imap = sda_mbx_open($pdo);
            $roles = sda_mbx_roles($imap);
            $gmail = sda_mbx_is_gmail($imap);
            $clean = function () use ($in, $gmail) {
                $name = trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? '')));
                $re = $gmail ? '/^[\p{L}\p{N}][\p{L}\p{N} _&()\-.\/]{0,59}$/u' : '/^[\p{L}\p{N}][\p{L}\p{N} _&()\-]{0,39}$/u';
                if (!preg_match($re, $name) || str_contains($name, '//') || str_ends_with($name, '/')) sda_mbx_fail('Names can use letters, numbers, spaces and - _ & ( )' . ($gmail ? ' . / (for nested labels)' : '') . ', up to ' . ($gmail ? 60 : 40) . ' characters.');
                if (strcasecmp($name, 'INBOX') === 0 || preg_match('#^\[(Gmail|Google Mail)\]#i', $name)) sda_mbx_fail('That name is reserved.');
                return sda_mbx_utf7($name);
            };
            $prefix = '';
            if (!$gmail) foreach ($imap->listFolders() as $f) if ($f['delimiter'] !== '' && stripos($f['name'], 'INBOX' . $f['delimiter']) === 0) { $prefix = 'INBOX' . $f['delimiter']; break; }
            if ($action === 'folder_create') {
                $full = $prefix . $clean();
                foreach ($imap->listFolders() as $f) if (strcasecmp($f['name'], $full) === 0) sda_mbx_fail('A folder with that name already exists.');
                if (!$imap->createFolder($full)) sda_mbx_fail('The mail server could not create that folder: ' . mb_substr($imap->lastError, 0, 120), 502);
                $imap->forgetFolders();
                $out(['success' => true, 'folder' => $full]);
            }
            $old = (string)($in['folder'] ?? '');
            if (!sda_mbx_folder_ok($imap, $old)) sda_mbx_fail('Unknown folder.');
            if (in_array($old, $roles, true) || ($gmail && sda_mbx_gm_system($old))) sda_mbx_fail('Built-in folders cannot be renamed or deleted.');
            if ($action === 'folder_rename') {
                $full = $prefix . $clean();
                if (!$imap->command(['RENAME ', $imap->quote($old), ' ', $imap->quote($full)])['ok']) sda_mbx_fail('The mail server could not rename that folder.', 502);
                $imap->command(['UNSUBSCRIBE ', $imap->quote($old)]); $imap->command(['SUBSCRIBE ', $imap->quote($full)]);
                $out(['success' => true, 'folder' => $full]);
            }
            // Delete: only when empty (deleting a folder on a normal server deletes its mail for good)
            $st = $imap->status($old);
            if (!$gmail && $st['messages'] > 0) sda_mbx_fail('This folder still has ' . $st['messages'] . ' message(s). Move or delete them first.');
            foreach ($imap->listFolders() as $f) if ($f['delimiter'] !== '' && strpos($f['name'], $old . $f['delimiter']) === 0) sda_mbx_fail('This folder has folders inside it. Delete those first.');
            if (!$imap->command(['DELETE ', $imap->quote($old)])['ok']) sda_mbx_fail('The mail server could not delete that folder.', 502);
            $imap->command(['UNSUBSCRIBE ', $imap->quote($old)]);
            ax_log('mail.folder_delete', 'mailbox', null, 'Deleted folder ' . SdaImap::decodeFolder($old) . ' in ' . $boxInfo['address']);
            $out(['success' => true]);
        }
        case 'label_save': {
            $name = trim(preg_replace('/\s+/', ' ', (string)($in['name'] ?? '')));
            $color = in_array($in['color'] ?? '', SDA_MBX_LABEL_COLORS, true) ? $in['color'] : SDA_MBX_LABEL_COLORS[0];
            $id = (string)($in['id'] ?? '');
            $imap = sda_mbx_open($pdo);
            $roles = sda_mbx_roles($imap);
            if (sda_mbx_is_gmail($imap)) {
                // Gmail labels are folders: create / rename the folder, keep the colour here
                if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _&()\-.\/]{0,59}$/u', $name) || str_contains($name, '//') || str_ends_with($name, '/')) sda_mbx_fail('Label names can use letters, numbers, spaces and - _ & ( ) . / (for nested labels), up to 60 characters.');
                if (strcasecmp($name, 'INBOX') === 0 || preg_match('#^\[(Gmail|Google Mail)\]#i', $name)) sda_mbx_fail('That name is reserved.');
                $raw = sda_mbx_utf7($name);
                $labels = sda_mbx_gm_labels($imap, $roles);
                foreach ($imap->listFolders() as $f) if (strcasecmp($f['name'], $raw) === 0 && $f['name'] !== $id) sda_mbx_fail('There is already a label with that name.');
                if ($id === '') {
                    if (!$imap->createFolder($raw)) sda_mbx_fail('Gmail could not create that label: ' . mb_substr($imap->lastError, 0, 120), 502);
                    sda_mbx_gm_label_color($pdo, $raw, $color);
                } else {
                    if (!in_array($id, array_column($labels, 'id'), true)) sda_mbx_fail('That label no longer exists.', 404);
                    if ($raw !== $id) {
                        if (!$imap->command(['RENAME ', $imap->quote($id), ' ', $imap->quote($raw)])['ok']) sda_mbx_fail('Gmail could not rename that label.', 502);
                        sda_mbx_gm_label_color($pdo, $id, $color, $raw);
                    } else {
                        sda_mbx_gm_label_color($pdo, $id, $color);
                    }
                }
                $imap->forgetFolders();
                $out(['success' => true, 'labels' => sda_mbx_gm_labels($imap, $roles), 'id' => $raw]);
            }
            if ($name === '' || mb_strlen($name) > 30) sda_mbx_fail('Give the label a name (up to 30 characters).');
            $labels = sda_mbx_kw_labels();
            foreach ($labels as $l) if (strcasecmp($l['name'], $name) === 0 && $l['id'] !== $id) sda_mbx_fail('There is already a label with that name.');
            if ($id === '') {
                if (count($labels) >= 40) sda_mbx_fail('Up to 40 labels.');
                $labels[] = ['id' => $id = 'sda_' . bin2hex(random_bytes(4)), 'name' => $name, 'color' => $color];
            } else {
                $found = false;
                foreach ($labels as &$l) if ($l['id'] === $id) { $l['name'] = $name; $l['color'] = $color; $found = true; }
                unset($l);
                if (!$found) sda_mbx_fail('That label no longer exists.', 404);
            }
            sda_mbx_kw_save_labels($pdo, $labels);
            $out(['success' => true, 'labels' => $labels, 'id' => $id]);
        }
        case 'label_delete': {
            $id = (string)($in['id'] ?? '');
            $imap = sda_mbx_open($pdo);
            $roles = sda_mbx_roles($imap);
            if (sda_mbx_is_gmail($imap)) {
                // Deleting a Gmail label keeps its messages (they stay in All Mail and any other labels)
                if (!in_array($id, array_column(sda_mbx_gm_labels($imap, $roles), 'id'), true)) sda_mbx_fail('That label no longer exists.', 404);
                foreach ($imap->listFolders() as $f) if ($f['delimiter'] !== '' && strpos($f['name'], $id . $f['delimiter']) === 0) sda_mbx_fail('This label has nested labels. Delete those first.');
                if (!$imap->command(['DELETE ', $imap->quote($id)])['ok']) sda_mbx_fail('Gmail could not delete that label.', 502);
                sda_mbx_gm_label_color($pdo, $id, null);
                $imap->forgetFolders();
                ax_log('mail.label_delete', 'mailbox', null, 'Deleted Gmail label ' . SdaImap::decodeFolder($id));
                $out(['success' => true, 'labels' => sda_mbx_gm_labels($imap, $roles)]);
            }
            $labels = sda_mbx_kw_labels();
            if (!in_array($id, array_column($labels, 'id'), true)) sda_mbx_fail('That label no longer exists.', 404);
            foreach ($imap->listFolders() as $f) {
                if (stripos($f['flags'], '\\Noselect') !== false) continue;
                $imap->select($f['name']);
                $uids = $imap->searchUids('KEYWORD ' . $id);
                if ($uids) $imap->store($uids, $id, false);
            }
            $labels = array_values(array_filter($labels, fn($l) => $l['id'] !== $id));
            sda_mbx_kw_save_labels($pdo, $labels);
            $out(['success' => true, 'labels' => $labels]);
        }
        case 'signature_save': {
            $sig = sda_mbx_save_signature($pdo, (array)($in['signature'] ?? []));
            $out(['success' => true, 'signature' => $sig, 'preview' => sda_mbx_signature_html(['enabled' => true] + $sig)]);
        }
        case 'signature_preview': {
            $sig = sda_sig_clean((array)($in['signature'] ?? []), sda_sig_defaults($pdo));
            $out(['success' => true, 'preview' => sda_mbx_signature_html(['enabled' => true] + $sig), 'text' => sda_mbx_signature_text(['enabled' => true] + $sig)]);
        }
    }
    sda_mbx_fail('Unknown action.', 404);
} catch (SdaMbxError $e) {
    $out(['success' => false, 'message' => $e->getMessage()] + $e->extra, $e->getCode() ?: 400);
} catch (Throwable $e) {
    error_log('admin/api-mailbox: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    $out(['success' => false, 'message' => 'Something went wrong. Please try again.'], 500);
}
