<?php
/** Admin: my profile – name, contact details, photo, password and my recent activity. */
$pageTitle = 'My Profile';
require_once 'includes/header.php';

$uid = (int)$user['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    if ($act === 'details') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio   = trim($_POST['bio'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ax_flash('err', 'Enter your name and a valid email address.'); ax_back();
        }
        $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $dup->execute([$email, $uid]);
        if ($dup->fetch()) { ax_flash('err', 'Another account already uses that email.'); ax_back(); }

        $avatar = null;
        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $ok  = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && $_FILES['avatar']['size'] <= 2 * 1024 * 1024
                   && @getimagesize($_FILES['avatar']['tmp_name']);
            if (!$ok) { ax_flash('err', 'Profile photo must be a JPG, PNG or WebP image under 2 MB.'); ax_back(); }
            $dir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $avatar = 'admin_' . $uid . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . $avatar);
        }
        $removePhoto = !$avatar && !empty($_POST['remove_avatar']);
        $sql = "UPDATE users SET name = ?, email = ?, phone = ?, bio = ?" . ($avatar ? ", avatar = ?" : ($removePhoto ? ", avatar = NULL" : "")) . " WHERE id = ?";
        $p = [mb_substr($name, 0, 150), $email, $phone !== '' ? mb_substr($phone, 0, 20) : null, $bio !== '' ? mb_substr($bio, 0, 1000) : null];
        if ($avatar) $p[] = $avatar;
        $p[] = $uid;
        $pdo->prepare($sql)->execute($p);
        // Keep the signed-in session in step so the top bar and sidebar show the change straight away
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        if ($avatar) $_SESSION['avatar'] = $avatar;
        if ($removePhoto) $_SESSION['avatar'] = null;
        ax_log('profile.update', 'user', $uid, 'Updated own profile');
        ax_flash('ok', 'Profile updated.'); ax_back();
    }
    if ($act === 'password') {
        $row = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $row->execute([$uid]);
        $hash = (string)$row->fetchColumn();
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['current_password'] ?? ''), $hash)) {
            ax_flash('err', 'Your current password is not correct.'); ax_back('profile#security');
        }
        if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            ax_flash('err', 'Use at least 8 characters with letters and numbers.'); ax_back('profile#security');
        }
        if ($new !== ($_POST['confirm_password'] ?? '')) { ax_flash('err', 'The new passwords do not match.'); ax_back('profile#security'); }
        $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE id = ?")
            ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        ax_log('profile.password', 'user', $uid, 'Changed own password');
        if (is_file(__DIR__ . '/../includes/notify.php')) {
            require_once __DIR__ . '/../includes/notify.php';
            sda_notify('password_changed', $uid, [], ['inapp' => false]);
        }
        ax_flash('ok', 'Password changed.'); ax_back('profile#security');
    }
}

$me = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$me->execute([$uid]);
$me = $me->fetch() ?: $user;
$stats = [
    ['bolt', 'Admin actions (30 days)', ax_scalar($pdo, "SELECT COUNT(*) FROM admin_activity WHERE admin_id = ? AND created_at > NOW() - INTERVAL 30 DAY", [$uid])],
    ['shield-halved', 'Payments you approved', ax_scalar($pdo, "SELECT COUNT(*) FROM payments WHERE verified_by = ? AND status = 'verified'", [$uid])],
    ['certificate', 'Certificates you issued', ax_scalar($pdo, "SELECT COUNT(*) FROM certificates WHERE issued_by = ?", [$uid])],
];
$recent = [];
try {
    $r = $pdo->prepare("SELECT action, summary, created_at FROM admin_activity WHERE admin_id = ? ORDER BY id DESC LIMIT 6");
    $r->execute([$uid]);
    $recent = $r->fetchAll();
} catch (Throwable $e) {}
$avatarUrl = !empty($me['avatar']) ? '../uploads/avatars/' . $me['avatar'] : '';
$initial = ax_initial($me['name']);
require_once 'includes/sidebar.php';
?>
<style>
  .pf-card { position: sticky; top: 88px; }
  .pf-hero { text-align: center; padding: 30px 22px 22px; }
  .pf-photo { position: relative; width: 104px; height: 104px; margin: 0 auto 14px; display: block; cursor: pointer; border-radius: 30px; }
  .pf-photo .pf-img, .pf-photo .pf-ini { width: 100%; height: 100%; border-radius: 30px; object-fit: cover; display: grid; place-items: center;
      background: linear-gradient(135deg, var(--secondary), color-mix(in srgb, var(--secondary) 60%, #7c2d12)); color: #fff; font: 800 2.2rem Poppins, sans-serif;
      box-shadow: 0 12px 28px -12px color-mix(in srgb, var(--secondary) 70%, transparent); }
  .pf-photo [hidden] { display: none !important; }
  .pf-photo .pf-cam { position: absolute; right: -6px; bottom: -6px; width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center;
      background: var(--primary); color: #fff; border: 3px solid var(--surface); font-size: .8rem; transition: transform .15s; }
  .pf-photo:hover .pf-cam, .pf-photo:focus-visible .pf-cam { transform: scale(1.08); }
  .pf-photo:focus-visible { outline: 2px solid var(--primary); outline-offset: 4px; }
  .pf-name { font: 800 1.25rem Poppins, sans-serif; color: var(--text-main); margin: 0; }
  .pf-mail { color: var(--text-dim); font-size: .88rem; margin: 4px 0 12px; overflow-wrap: anywhere; }
  .pf-meta { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
  .pf-stats { display: grid; gap: 1px; background: var(--border); border-top: 1px solid var(--border); }
  .pf-stat { display: flex; align-items: center; gap: 12px; padding: 13px 20px; background: var(--surface); }
  .pf-stat i { width: 32px; height: 32px; border-radius: 10px; display: grid; place-items: center; background: var(--surface-2); color: var(--primary); font-size: .82rem; }
  .pf-stat span { flex: 1; color: var(--text-dim); font-size: .84rem; }
  .pf-stat b { font: 800 1rem Poppins, sans-serif; color: var(--text-main); }
  .pf-facts { padding: 16px 20px 20px; display: grid; gap: 10px; border-top: 1px solid var(--border); font-size: .84rem; }
  .pf-facts div { display: flex; justify-content: space-between; gap: 10px; } .pf-facts dt { color: var(--text-dim); } .pf-facts dd { margin: 0; color: var(--text-main); font-weight: 600; text-align: right; }
  .pf-file { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1px dashed var(--border); border-radius: 12px; background: var(--surface-2); }
  .pf-file .pf-thumb { width: 44px; height: 44px; border-radius: 12px; object-fit: cover; background: var(--surface); display: grid; place-items: center; color: var(--text-dim); flex: none; overflow: hidden; }
  .pf-file .pf-thumb img { width: 100%; height: 100%; object-fit: cover; }
  .pf-file .grow { flex: 1; min-width: 0; font-size: .82rem; color: var(--text-dim); }
  .pf-file .grow b { display: block; color: var(--text-main); font-size: .86rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .pf-count { float: right; font-weight: 500; color: var(--text-muted); font-size: .74rem; }
  .pf-pw { position: relative; } .pf-pw input { padding-right: 42px; }
  .pf-eye { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 32px; height: 32px; border: 0; background: transparent; color: var(--text-dim); cursor: pointer; border-radius: 8px; }
  .pf-eye:hover { color: var(--text-main); background: var(--surface-2); }
  .pf-meter { height: 6px; border-radius: 6px; background: var(--surface-2); overflow: hidden; margin-top: 8px; }
  .pf-meter i { display: block; height: 100%; width: 0; border-radius: 6px; transition: width .25s, background .25s; }
  .pf-hint { font-size: .76rem; color: var(--text-dim); margin-top: 6px; min-height: 1.1em; }
  .pf-links { display: grid; gap: 8px; }
  .pf-links a { display: flex; align-items: center; gap: 12px; padding: 11px 12px; border: 1px solid var(--border); border-radius: 12px; text-decoration: none; color: var(--text-main); background: var(--surface-2); font-size: .86rem; font-weight: 600; }
  .pf-links a:hover { border-color: var(--primary); }
  .pf-links a i:first-child { width: 18px; color: var(--primary); text-align: center; }
  .pf-links a small { display: block; color: var(--text-dim); font-weight: 400; font-size: .76rem; }
  .pf-links a .fa-arrow-right { margin-left: auto; color: var(--text-muted); font-size: .75rem; }
  .pf-act li { font-size: .84rem; }
  @media (max-width: 1100px) { .pf-card { position: static; } }
</style>
<main class="main-content">
    <?php ax_header('My', 'Profile', 'Account, photo & security'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-grid">
            <div class="ax-span-4">
                <section class="ax-panel pf-card">
                    <div class="pf-hero">
                        <label class="pf-photo" for="pf-avatar" tabindex="0" title="Change profile photo" aria-label="Change profile photo">
                            <?php if ($avatarUrl): ?><img class="pf-img" id="pfHeroImg" src="<?= e($avatarUrl) ?>" alt=""><?php else: ?><span class="pf-ini" id="pfHeroIni"><?= $initial ?></span><img class="pf-img" id="pfHeroImg" alt="" hidden><?php endif; ?>
                            <span class="pf-cam"><i class="fas fa-camera"></i></span>
                        </label>
                        <h2 class="pf-name"><?= e($me['name']) ?></h2>
                        <p class="pf-mail"><?= e($me['email']) ?></p>
                        <div class="pf-meta">
                            <span class="ax-pill info"><i class="fas fa-user-shield"></i> Administrator</span>
                            <?php if (($me['status'] ?? '') === 'active'): ?><span class="ax-pill ok"><i class="fas fa-circle-check"></i> Active</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="pf-stats">
                        <?php foreach ($stats as [$ic, $label, $val]): ?>
                        <div class="pf-stat"><i class="fas fa-<?= $ic ?>"></i><span><?= e($label) ?></span><b><?= number_format((float)$val) ?></b></div>
                        <?php endforeach; ?>
                    </div>
                    <dl class="pf-facts">
                        <div><dt>Member since</dt><dd><?= ax_date($me['created_at'] ?? null) ?></dd></div>
                        <div><dt>Last sign-in</dt><dd><?= ax_ago($me['last_login'] ?? null) ?></dd></div>
                        <div><dt>Phone</dt><dd><?= e(($me['phone'] ?? '') ?: '—') ?></dd></div>
                    </dl>
                </section>
            </div>

            <div class="ax-span-8 ax-stack">
                <section class="ax-panel" id="details">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-id-card"></i> Personal details</h2><p>Shown on announcements, emails and certificates you issue.</p></div></div>
                    <form class="ax-panel-b ax-form" method="post" enctype="multipart/form-data" id="pfForm">
                        <?= ax_csrf() ?><input type="hidden" name="action" value="details">
                        <div class="ax-row">
                            <div class="ax-field"><label for="pf-name">Full name</label><input id="pf-name" type="text" name="name" value="<?= e($me['name']) ?>" required maxlength="150" autocomplete="name"></div>
                            <div class="ax-field"><label for="pf-email">Email</label><input id="pf-email" type="email" name="email" value="<?= e($me['email']) ?>" required autocomplete="email"><div class="hint">Used to sign in and for staff alerts.</div></div>
                        </div>
                        <div class="ax-row">
                            <div class="ax-field"><label for="pf-phone">Phone</label><input id="pf-phone" type="tel" name="phone" value="<?= e($me['phone'] ?? '') ?>" maxlength="20" placeholder="07XX XXX XXX" autocomplete="tel"></div>
                            <div class="ax-field"><label for="pf-avatar">Profile photo</label>
                                <div class="pf-file">
                                    <span class="pf-thumb" id="pfThumb"><?= $avatarUrl ? '<img src="' . e($avatarUrl) . '" alt="">' : '<i class="fas fa-image"></i>' ?></span>
                                    <div class="grow"><b id="pfFileName"><?= $avatarUrl ? 'Current photo' : 'No photo yet' ?></b>JPG, PNG or WebP, up to 2 MB</div>
                                    <label class="ax-btn ghost sm" for="pf-avatar" style="margin:0"><i class="fas fa-upload"></i> Choose</label>
                                </div>
                                <input id="pf-avatar" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" hidden>
                                <?php if ($avatarUrl): ?><label class="ax-check" style="margin-top:8px;font-size:.8rem"><input type="checkbox" name="remove_avatar" value="1" id="pfRemove"> Remove my photo</label><?php endif; ?>
                                <div class="hint" id="pfFileErr" style="color:var(--danger)" hidden></div>
                            </div>
                        </div>
                        <div class="ax-field"><label for="pf-bio">Short bio <span class="pf-count" id="pfBioCount"></span></label><textarea id="pf-bio" name="bio" maxlength="1000" rows="4" placeholder="A sentence or two about your role at the academy."><?= e($me['bio'] ?? '') ?></textarea></div>
                        <div class="ax-form-actions"><span class="hint" id="pfDirty" hidden style="margin-right:auto"><i class="fas fa-circle" style="font-size:.5rem;color:var(--warning)"></i> Unsaved changes</span><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save details</button></div>
                    </form>
                </section>

                <section class="ax-panel" id="security">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-lock"></i> Password</h2><p>At least 8 characters, with letters and numbers. You'll get an email confirming the change.</p></div></div>
                    <form class="ax-panel-b ax-form" method="post" id="pwForm">
                        <?= ax_csrf() ?><input type="hidden" name="action" value="password">
                        <input type="text" name="username" value="<?= e($me['email']) ?>" autocomplete="username" hidden>
                        <div class="ax-row">
                            <div class="ax-field"><label for="pw-cur">Current password</label><div class="pf-pw"><input id="pw-cur" type="password" name="current_password" required autocomplete="current-password"><button type="button" class="pf-eye" data-eye="pw-cur" aria-label="Show password"><i class="fas fa-eye"></i></button></div></div>
                            <div class="ax-field"><label for="pw-new">New password</label><div class="pf-pw"><input id="pw-new" type="password" name="new_password" required minlength="8" autocomplete="new-password"><button type="button" class="pf-eye" data-eye="pw-new" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
                                <div class="pf-meter" aria-hidden="true"><i id="pwBar"></i></div><div class="pf-hint" id="pwHint"></div></div>
                            <div class="ax-field"><label for="pw-con">Confirm new password</label><div class="pf-pw"><input id="pw-con" type="password" name="confirm_password" required minlength="8" autocomplete="new-password"><button type="button" class="pf-eye" data-eye="pw-con" aria-label="Show password"><i class="fas fa-eye"></i></button></div>
                                <div class="pf-hint" id="pwMatch"></div></div>
                        </div>
                        <div class="ax-form-actions"><button class="ax-btn" id="pwBtn"><i class="fas fa-key"></i> Change password</button></div>
                    </form>
                </section>

                <div class="ax-grid">
                    <section class="ax-panel ax-span-6">
                        <div class="ax-panel-h"><div><h2><i class="fas fa-clock-rotate-left"></i> My recent activity</h2><p>Your latest admin actions.</p></div><a class="ax-btn ghost sm" href="activity">All</a></div>
                        <div class="ax-panel-b">
                            <?php if ($recent): ?>
                            <ul class="ax-list pf-act"><?php foreach ($recent as $a): ?>
                                <li><div class="grow"><b style="color:var(--text-main);font-weight:600"><?= e($a['summary'] ?: $a['action']) ?></b><div style="font-size:.74rem;color:var(--text-muted)"><?= ax_ago($a['created_at']) ?></div></div></li>
                            <?php endforeach; ?></ul>
                            <?php else: ?><div class="ax-empty" style="padding:18px"><i class="fas fa-clock-rotate-left"></i><b>Nothing yet</b>Your actions will show here.</div><?php endif; ?>
                        </div>
                    </section>
                    <section class="ax-panel ax-span-6">
                        <div class="ax-panel-h"><div><h2><i class="fas fa-compass"></i> Shortcuts</h2><p>Things you set up once.</p></div></div>
                        <div class="ax-panel-b pf-links">
                            <a href="mail"><i class="fas fa-envelope"></i><span>Webmail<small>The academy mailbox</small></span><i class="fas fa-arrow-right"></i></a>
                            <a href="email-notifications"><i class="fas fa-envelope-open-text"></i><span>Email notifications<small>Automatic emails & reminders</small></span><i class="fas fa-arrow-right"></i></a>
                            <a href="../email-preferences"><i class="fas fa-bell"></i><span>My email preferences<small>What you receive</small></span><i class="fas fa-arrow-right"></i></a>
                            <a href="settings"><i class="fas fa-cog"></i><span>Platform settings<small>Payments, email, sign-in, AI</small></span><i class="fas fa-arrow-right"></i></a>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    // Photo: preview in the card and the field before saving
    var input = document.getElementById('pf-avatar'), thumb = document.getElementById('pfThumb'), name = document.getElementById('pfFileName'),
        err = document.getElementById('pfFileErr'), hero = document.getElementById('pfHeroImg'), ini = document.getElementById('pfHeroIni'), rm = document.getElementById('pfRemove');
    input.addEventListener('change', function () {
        var f = input.files[0]; err.hidden = true;
        if (!f) return;
        if (!/^image\/(png|jpe?g|webp)$/.test(f.type) || f.size > 2 * 1024 * 1024) { err.textContent = 'Choose a JPG, PNG or WebP image under 2 MB.'; err.hidden = false; input.value = ''; return; }
        var url = URL.createObjectURL(f);
        thumb.innerHTML = '<img src="' + url + '" alt="">';
        name.textContent = f.name;
        hero.src = url; hero.hidden = false; if (ini) ini.hidden = true;
        if (rm) rm.checked = false;
        dirty();
    });
    document.querySelector('.pf-photo').addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });

    // Bio counter + unsaved-changes hint
    var bio = document.getElementById('pf-bio'), cnt = document.getElementById('pfBioCount'), flag = document.getElementById('pfDirty');
    function count() { cnt.textContent = bio.value.length + ' / 1000'; }
    function dirty() { flag.hidden = false; }
    bio.addEventListener('input', count); count();
    document.getElementById('pfForm').addEventListener('input', dirty);

    // Show / hide password
    document.querySelectorAll('[data-eye]').forEach(function (b) {
        b.addEventListener('click', function () {
            var f = document.getElementById(b.dataset.eye), show = f.type === 'password';
            f.type = show ? 'text' : 'password';
            b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            b.innerHTML = '<i class="fas fa-eye' + (show ? '-slash' : '') + '"></i>';
        });
    });

    // Strength + match
    var pw = document.getElementById('pw-new'), con = document.getElementById('pw-con'), bar = document.getElementById('pwBar'), hint = document.getElementById('pwHint'), match = document.getElementById('pwMatch');
    function strength() {
        var v = pw.value, s = 0;
        if (v.length >= 8) s++; if (v.length >= 12) s++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++;
        var ok = v.length >= 8 && /[A-Za-z]/.test(v) && /\d/.test(v);
        var lv = !v ? 0 : (!ok ? 1 : (s <= 3 ? 2 : (s === 4 ? 3 : 4)));
        var c = ['', 'var(--danger)', 'var(--warning)', 'var(--primary)', 'var(--success)'][lv];
        bar.style.width = (lv * 25) + '%'; bar.style.background = c;
        hint.textContent = !v ? '' : (!ok ? 'Needs 8+ characters with letters and numbers' : ['', '', 'Okay', 'Good', 'Strong'][lv]);
        hint.style.color = c || '';
        checkMatch();
    }
    function checkMatch() {
        if (!con.value) { match.textContent = ''; return; }
        var same = con.value === pw.value;
        match.textContent = same ? 'Passwords match' : 'Passwords do not match yet';
        match.style.color = same ? 'var(--success)' : 'var(--danger)';
    }
    pw.addEventListener('input', strength); con.addEventListener('input', checkMatch);
})();
</script>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
