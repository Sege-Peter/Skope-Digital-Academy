<?php
/**
 * Skope Digital Academy - email signature designer for the admin webmail (loaded by includes/mailbox.php).
 * Ported from the HAMASTA MEDIA signature designer without the photo upload / profile photo feature.
 *
 * One signature per admin (table mailbox_signatures, JSON in `data`, box 'main'). Five templates, all email-safe:
 * tables, inline styles, web-safe fonts, text links. The image is the academy logo (absolute URL) or nothing.
 */

const SDA_SIG_TEMPLATES = ['classic' => 'Classic', 'modern' => 'Modern bar', 'compact' => 'Compact', 'photo' => 'Card', 'minimal' => 'Minimal text'];
const SDA_SIG_SOCIALS = ['facebook' => 'Facebook', 'x' => 'X', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp', 'linkedin' => 'LinkedIn'];
const SDA_SIG_COLORS = ['#0a6cf0', '#1fb5f0', '#ff9b26', '#0f172a', '#15803d', '#7c3aed', '#b45309', '#dc2626'];
const SDA_SIG_DISCLAIMER = 'This email and any attachments are confidential and intended only for the person addressed. If you received it by mistake, please tell the sender and delete it.';

/** Absolute base URL for images and links in emails */
function sda_sig_base(): string {
    $site = rtrim(setting('site_url'), '/');
    return $site !== '' ? $site : rtrim(sda_site_base(), '/');
}

function sda_sig_brand(): string { return setting('academy_name') ?: 'Skope Digital Academy'; }

/** Starting values for someone who has not designed a signature yet */
function sda_sig_defaults(PDO $pdo): array {
    $me = sda_mbx_me($pdo);
    $b = sda_mbx_box();
    $site = setting('site_url');
    return [
        'enabled' => true, 'skip_replies' => false, 'template' => 'classic', 'color' => SDA_SIG_COLORS[0],
        'name' => $me['name'], 'title' => $me['title'], 'department' => '',
        'phone' => $me['phone'] ?: setting('support_phone'), 'phone2' => '', 'email' => $b['address'],
        'website' => $site !== '' ? preg_replace('~^https?://~i', '', rtrim($site, '/')) : '',
        'address' => '', 'image' => 'logo', 'tagline' => '', 'extra' => '',
        'disclaimer' => false, 'disclaimer_text' => SDA_SIG_DISCLAIMER,
        'social' => array_fill_keys(array_keys(SDA_SIG_SOCIALS), ''),
    ];
}

/** Any input (saved row or a form) reduced to a valid design */
function sda_sig_clean(array $in, array $defaults): array {
    $line = fn($v, int $max) => mb_substr(trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)$v))), 0, $max);
    $sig = $defaults;
    foreach (['name' => 100, 'title' => 100, 'department' => 100, 'phone' => 40, 'phone2' => 40, 'email' => 120, 'website' => 120, 'address' => 160, 'tagline' => 160, 'extra' => 240, 'disclaimer_text' => 500] as $f => $max) {
        if (array_key_exists($f, $in)) $sig[$f] = $line($in[$f], $max);
    }
    if ($sig['email'] !== '' && !filter_var($sig['email'], FILTER_VALIDATE_EMAIL)) $sig['email'] = '';
    $sig['website'] = preg_replace('~^https?://~i', '', $sig['website']);
    if ($sig['website'] !== '' && !preg_match('~^[a-z0-9.-]+\.[a-z]{2,}(/[^\s"<>]*)?$~i', $sig['website'])) $sig['website'] = '';
    foreach (['enabled', 'skip_replies', 'disclaimer'] as $f) if (array_key_exists($f, $in)) $sig[$f] = !empty($in[$f]);
    if (isset($in['template']) && isset(SDA_SIG_TEMPLATES[(string)$in['template']])) $sig['template'] = (string)$in['template'];
    if (isset($in['color']) && preg_match('/^#[0-9a-f]{6}$/i', (string)$in['color'])) $sig['color'] = strtolower((string)$in['color']);
    if (isset($in['image']) && in_array($in['image'], ['none', 'logo'], true)) $sig['image'] = $in['image'];
    $social = $defaults['social'];
    foreach (array_keys(SDA_SIG_SOCIALS) as $k) {
        $u = trim((string)($in['social'][$k] ?? ($sig['social'][$k] ?? '')));
        if ($u !== '' && !preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
        $social[$k] = ($u !== '' && strlen($u) <= 200 && filter_var($u, FILTER_VALIDATE_URL) && preg_match('~^https?://[a-z0-9.-]+\.[a-z]{2,}(/[^\s"<>]*)?$~i', $u)) ? $u : '';
    }
    $sig['social'] = $social;
    if ($sig['disclaimer_text'] === '') $sig['disclaimer_text'] = SDA_SIG_DISCLAIMER;
    return $sig;
}

function sda_mbx_signature(PDO $pdo): array {
    sda_mbx_ensure($pdo);
    $me = sda_mbx_me($pdo);
    $defaults = sda_sig_defaults($pdo);
    $st = $pdo->prepare("SELECT data FROM mailbox_signatures WHERE user_id = ? AND box = ?");
    $st->execute([$me['id'], SDA_MBX_BOX]);
    $saved = json_decode((string)$st->fetchColumn(), true);
    $sig = is_array($saved) ? sda_sig_clean($saved, $defaults) : $defaults;
    return $sig + ['customised' => is_array($saved)];
}

function sda_mbx_save_signature(PDO $pdo, array $in): array {
    sda_mbx_ensure($pdo);
    $me = sda_mbx_me($pdo);
    $sig = sda_sig_clean($in, sda_sig_defaults($pdo));
    $pdo->prepare("INSERT INTO mailbox_signatures (user_id, box, data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data)")
        ->execute([$me['id'], SDA_MBX_BOX, json_encode($sig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    return sda_mbx_signature($pdo);
}

// ── Rendering ───────────────────────────────────────────────────────────────────

/** Image for the signature: [url, alt] or null */
function sda_sig_image(array $sig): ?array {
    if (($sig['image'] ?? 'logo') === 'none') return null;
    return [sda_sig_base() . '/assets/images/' . rawurlencode('Skope Digital  logo.png'), sda_sig_brand()];
}

/** Branded HTML signature in the chosen template */
function sda_mbx_signature_html(array $sig): string {
    if (empty($sig['enabled'])) return '';
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $brand = $e(sda_sig_brand());
    $c = preg_match('/^#[0-9a-f]{6}$/i', (string)($sig['color'] ?? '')) ? $sig['color'] : SDA_SIG_COLORS[0];
    $tpl = isset(SDA_SIG_TEMPLATES[$sig['template'] ?? '']) ? $sig['template'] : 'classic';
    $font = 'font-family:Arial,Helvetica,sans-serif';
    $link = 'color:' . $c . ';text-decoration:none';
    $tel = fn($p) => '<a href="tel:' . $e(preg_replace('/[^\d+]/', '', $p)) . '" style="' . $link . '">' . $e($p) . '</a>';
    $sep = ' <span style="color:#94a3b8">&nbsp;|&nbsp;</span> ';

    $contact = [];
    foreach (['phone', 'phone2'] as $k) if (($sig[$k] ?? '') !== '') $contact[] = $tel($sig[$k]);
    if (($sig['email'] ?? '') !== '') $contact[] = '<a href="mailto:' . $e($sig['email']) . '" style="' . $link . '">' . $e($sig['email']) . '</a>';
    if (($sig['website'] ?? '') !== '') $contact[] = '<a href="https://' . $e($sig['website']) . '" style="' . $link . '">' . $e($sig['website']) . '</a>';
    $role = implode(', ', array_filter([(string)($sig['title'] ?? ''), (string)($sig['department'] ?? '')], fn($v) => $v !== ''));

    $social = '';
    foreach (SDA_SIG_SOCIALS as $k => $label) {
        $u = (string)($sig['social'][$k] ?? '');
        if ($u === '') continue;
        $social .= ($social === '' ? '' : $sep) . '<a href="' . $e($u) . '" style="' . $link . '">' . $e($label) . '</a>';
    }
    $img = $tpl === 'minimal' || $tpl === 'compact' ? null : sda_sig_image($sig);
    $extra = (($sig['tagline'] ?? '') !== '' ? '<div style="margin-top:6px;font-size:12px;color:#64748b;font-style:italic">' . $e($sig['tagline']) . '</div>' : '')
           . (($sig['extra'] ?? '') !== '' ? '<div style="margin-top:4px;font-size:12px;color:#64748b">' . $e($sig['extra']) . '</div>' : '');
    $disclaimer = !empty($sig['disclaimer']) ? '<tr><td style="padding-top:10px;font-size:10px;line-height:1.5;color:#94a3b8;' . $font . '">' . $e($sig['disclaimer_text'] ?? SDA_SIG_DISCLAIMER) . '</td></tr>' : '';
    $open = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:520px;margin-top:18px;' . $font . ';color:#1e293b">';

    if ($tpl === 'compact') {
        $first = array_filter(['<b style="color:#0f172a">' . $e($sig['name']) . '</b>', $role !== '' ? $e($role) : '', '<b style="color:' . $c . '">' . $brand . '</b>']);
        return $open . '<tr><td style="padding-top:10px;border-top:2px solid ' . $c . ';font-size:13px;line-height:1.6;' . $font . '">'
            . implode($sep, $first) . ($contact ? '<br>' . implode($sep, $contact) : '')
            . ($social !== '' ? '<div style="margin-top:6px">' . $social . '</div>' : '') . '</td></tr>' . $disclaimer . '</table>';
    }
    if ($tpl === 'minimal') {
        return $open . '<tr><td style="padding-top:10px;font-size:13px;line-height:1.6;' . $font . '">'
            . '<div style="color:#94a3b8">--</div><div style="font-weight:bold;color:#0f172a">' . $e($sig['name']) . '</div>'
            . ($role !== '' ? '<div style="color:#475569">' . $e($role) . '</div>' : '')
            . '<div style="font-weight:bold;color:' . $c . '">' . $brand . '</div>'
            . ($contact ? '<div>' . implode($sep, $contact) . '</div>' : '')
            . (($sig['address'] ?? '') !== '' ? '<div style="color:#64748b">' . $e($sig['address']) . '</div>' : '')
            . ($social !== '' ? '<div>' . $social . '</div>' : '') . $extra . '</td></tr>' . $disclaimer . '</table>';
    }

    $text = '<div style="font-size:16px;font-weight:bold;color:' . ($tpl === 'photo' ? $c : '#0f172a') . ';line-height:1.3">' . $e($sig['name']) . '</div>'
          . ($role !== '' ? '<div style="font-size:13px;color:#475569">' . $e($role) . '</div>' : '')
          . '<div style="font-size:13px;font-weight:bold;color:' . $c . ';letter-spacing:.3px">' . $brand . '</div>'
          . ($contact ? '<div style="margin-top:6px;font-size:12px;line-height:1.7">' . implode($sep, $contact) . '</div>' : '')
          . (($sig['address'] ?? '') !== '' ? '<div style="font-size:12px;color:#64748b">' . $e($sig['address']) . '</div>' : '')
          . ($social !== '' ? '<div style="margin-top:8px;font-size:12px">' . $social . '</div>' : '') . $extra;

    if ($tpl === 'modern') {
        return $open . '<tr><td style="border-left:4px solid ' . $c . ';padding-left:14px;font-size:13px;line-height:1.5;' . $font . '">' . $text . '</td></tr>' . $disclaimer . '</table>';
    }

    // classic / card: fluid hybrid (columns are inline-block and stack on narrow screens; Outlook gets a real table)
    $size = $tpl === 'photo' ? 84 : 72;
    $colW = $size + 18;
    $imgHtml = $img ? '<img src="' . $e($img[0]) . '" width="' . $size . '" height="' . $size . '" alt="' . $e($img[1]) . '" style="display:block;border:0;width:' . $size . 'px;height:' . $size . 'px;object-fit:contain">' : '';
    $cols = ($img ? '<!--[if mso]><table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td width="' . $colW . '" valign="top"><![endif]-->'
              . '<div style="display:inline-block;vertical-align:top;width:' . $colW . 'px;max-width:100%;margin-bottom:8px">' . $imgHtml . '</div>'
              . '<!--[if mso]></td><td width="' . (500 - $colW) . '" valign="top"><![endif]-->' : '')
          . '<div style="display:inline-block;vertical-align:top;width:100%;max-width:' . ($img ? 500 - $colW : 500) . 'px;font-size:13px;line-height:1.5;' . $font . '">' . $text . '</div>'
          . ($img ? '<!--[if mso]></td></tr></table><![endif]-->' : '');
    $cell = $tpl === 'photo'
        ? 'padding:14px;background-color:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid ' . $c . ';border-radius:10px;font-size:0;text-align:left'
        : 'padding-top:12px;border-top:3px solid ' . $c . ';font-size:0;text-align:left';
    return $open . '<tr><td style="' . $cell . '">' . $cols . '</td></tr>' . $disclaimer . '</table>';
}

/** Plain-text version for the text part of the email */
function sda_mbx_signature_text(array $sig): string {
    if (empty($sig['enabled'])) return '';
    $role = implode(', ', array_filter([(string)($sig['title'] ?? ''), (string)($sig['department'] ?? '')], fn($v) => $v !== ''));
    $lines = ['-- ', (string)$sig['name']];
    if ($role !== '') $lines[] = $role;
    $lines[] = sda_sig_brand();
    $contact = array_filter([(string)($sig['phone'] ?? ''), (string)($sig['phone2'] ?? ''), (string)($sig['email'] ?? ''), ($sig['website'] ?? '') !== '' ? 'https://' . $sig['website'] : '']);
    if ($contact) $lines[] = implode(' | ', $contact);
    if (($sig['address'] ?? '') !== '') $lines[] = (string)$sig['address'];
    foreach (SDA_SIG_SOCIALS as $k => $label) if (($sig['social'][$k] ?? '') !== '') $lines[] = $label . ': ' . $sig['social'][$k];
    if (($sig['tagline'] ?? '') !== '') $lines[] = (string)$sig['tagline'];
    if (($sig['extra'] ?? '') !== '') $lines[] = (string)$sig['extra'];
    if (!empty($sig['disclaimer'])) { $lines[] = ''; $lines[] = (string)($sig['disclaimer_text'] ?? SDA_SIG_DISCLAIMER); }
    return implode("\n", $lines);
}
