<?php
/**
 * Email preferences / one-click unsubscribe (linked from every non-essential email).
 *   email-preferences?u=<user id>&t=<token>   works without signing in (token = HMAC of the user id)
 *   Signed-in users can open email-preferences with no parameters.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notify.php';
sda_notify_ensure($pdo);

$uid = (int)($_GET['u'] ?? $_POST['u'] ?? 0);
$tok = (string)($_GET['t'] ?? $_POST['t'] ?? '');
if ($uid > 0 && hash_equals(sda_pref_token($uid), $tok)) {
    // link from an email
} elseif (isLoggedIn()) {
    $uid = (int)$_SESSION['user_id'];
    $tok = sda_pref_token($uid);
} else {
    header('Location: login');
    exit;
}
$u = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
$u->execute([$uid]);
$user = $u->fetch();
if (!$user) { http_response_code(404); exit('Not found'); }

$cats = array_diff_key(SDA_NOTIFY_CATEGORIES, ['account' => 1, 'admin' => 1]);
$saved = false;
// RFC 8058 one-click unsubscribe (mail apps POST "List-Unsubscribe=One-Click") or the "unsubscribe from all" button
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $all = isset($_POST['unsubscribe_all']) || ($_POST['List-Unsubscribe'] ?? '') === 'One-Click';
    foreach (array_keys($cats) as $c) {
        $on = $all ? 0 : (isset($_POST['cat'][$c]) ? 1 : 0);
        $pdo->prepare("INSERT INTO notification_prefs (user_id, category, email_on) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE email_on = VALUES(email_on)")->execute([$uid, $c, $on]);
    }
    $saved = $all ? 'all' : 'saved';
}
$prefs = [];
$s = $pdo->prepare("SELECT category, email_on FROM notification_prefs WHERE user_id = ?");
$s->execute([$uid]);
foreach ($s->fetchAll() as $r) $prefs[$r['category']] = (int)$r['email_on'];
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Email preferences – Skope Digital Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
    <style>
        :root { --ink:#0f172a; --dim:#64748b; --line:#e2e8f0; --cyan:#00BFFF; --bg:#f1f5f9; --card:#fff; }
        @media (prefers-color-scheme: dark) { :root { --ink:#e2e8f0; --dim:#94a3b8; --line:#1e293b; --bg:#0b1220; --card:#111a2e; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.6 Inter, system-ui, sans-serif; }
        .wrap { max-width: 620px; margin: 0 auto; padding: 32px 16px 48px; }
        .brand { font: 700 1rem Poppins, sans-serif; letter-spacing: .5px; margin-bottom: 18px; } .brand em { color: var(--cyan); font-style: normal; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 26px; }
        h1 { font: 700 1.4rem Poppins, sans-serif; margin: 0 0 6px; }
        p.sub { color: var(--dim); margin: 0 0 20px; }
        .row { display: flex; gap: 14px; align-items: flex-start; padding: 14px 0; border-top: 1px solid var(--line); }
        .row input { width: 20px; height: 20px; margin-top: 3px; accent-color: var(--cyan); flex: none; }
        .row b { display: block; } .row small { color: var(--dim); }
        .row.locked { opacity: .7; }
        .btns { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        button { font: 600 .95rem Inter, sans-serif; border-radius: 10px; padding: 11px 18px; cursor: pointer; border: 1px solid var(--line); background: transparent; color: var(--ink); }
        button.primary { background: var(--cyan); border-color: var(--cyan); color: #fff; }
        .ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; }
        a { color: var(--cyan); }
    </style>
<?php require_once __DIR__ . '/includes/pwa.php'; sda_pwa_head(); ?>
</head>
<body>
<div class="wrap">
    <div class="brand">SKOPE <em>DIGITAL</em> ACADEMY</div>
    <div class="card">
        <h1>Email preferences</h1>
        <p class="sub">For <?= $h($user['email']) ?>. Choose which emails you want. In-app notifications on the portal are not affected.</p>
        <?php if ($saved === 'all'): ?><div class="ok" role="status"><i class="fas fa-circle-check"></i> You are unsubscribed from all optional emails.</div>
        <?php elseif ($saved): ?><div class="ok" role="status"><i class="fas fa-circle-check"></i> Preferences saved.</div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="u" value="<?= (int)$uid ?>"><input type="hidden" name="t" value="<?= $h($tok) ?>">
            <label class="row locked"><input type="checkbox" checked disabled><span><b><?= $h(SDA_NOTIFY_CATEGORIES['account'][0]) ?></b><small>Always on: <?= $h(SDA_NOTIFY_CATEGORIES['account'][1]) ?></small></span></label>
            <?php foreach ($cats as $k => [$label, $desc]): ?>
            <label class="row"><input type="checkbox" name="cat[<?= $h($k) ?>]" value="1" <?= ($prefs[$k] ?? 1) ? 'checked' : '' ?>><span><b><?= $h($label) ?></b><small><?= $h($desc) ?></small></span></label>
            <?php endforeach; ?>
            <div class="btns">
                <button class="primary" type="submit"><i class="fas fa-floppy-disk"></i> Save preferences</button>
                <button type="submit" name="unsubscribe_all" value="1">Unsubscribe from all optional emails</button>
            </div>
        </form>
        <?php if ($user['role'] === 'student'): ?>
        <p class="sub" style="margin:22px 0 0"><i class="fas fa-calendar-days"></i> Put your class schedule in your own calendar:
            <a href="<?= $h(sda_google_calendar_link($uid)) ?>" target="_blank" rel="noopener">Add to Google Calendar</a> ·
            <a href="<?= $h(sda_calendar_url($uid, true)) ?>">Apple / Outlook</a></p>
        <?php endif; ?>
    </div>
    <p class="sub" style="text-align:center;margin-top:18px"><a href="./">Back to Skope Digital Academy</a></p>
</div>
</body>
</html>
