<?php
/**
 * Tutor: my profile (name, phone, bio shown on course pages, photo) and password.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

$st = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$st->execute([$tid]);
$me = $st->fetch();
if (!$me) { logoutUser(); td_redirect('../login'); }
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (isset($_POST['update_profile'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        if ($name === '') $error = 'Your name cannot be empty.';
        else {
            $pdo->prepare("UPDATE users SET name = ?, phone = ?, bio = ? WHERE id = ?")
                ->execute([mb_substr($name, 0, 150), $phone !== '' ? mb_substr($phone, 0, 20) : null, $bio, $tid]);
            $_SESSION['user_name'] = mb_substr($name, 0, 150);
            $msg = 'Profile saved.';
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $f = $_FILES['avatar'];
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !@getimagesize($f['tmp_name'])) $msg .= ' The photo was not saved: use a JPG, PNG or WebP picture.';
                elseif ($f['size'] > 3 * 1024 * 1024) $msg .= ' The photo was not saved: keep it under 3 MB.';
                else {
                    $dir = __DIR__ . '/../uploads/avatars/';
                    if (!is_dir($dir)) mkdir($dir, 0775, true);
                    $file = 'AVATAR_' . $tid . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($f['tmp_name'], $dir . $file)) {
                        $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$file, $tid]);
                        $_SESSION['avatar'] = $file;
                    }
                }
            }
            td_flash('ok', $msg);
            td_redirect('profile');
        }
    } elseif (isset($_POST['change_password'])) {
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify($cur, (string)$me['password'])) $error = 'Your current password is not correct.';
        elseif (strlen($new) < 8) $error = 'The new password must be at least 8 characters.';
        elseif ($new !== (string)($_POST['confirm_password'] ?? '')) $error = 'The two new passwords do not match.';
        else {
            $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $tid]);
            td_flash('ok', 'Password changed.');
            td_redirect('profile');
        }
    }
}

$pageTitle = 'Profile';
$pageSub = 'Your name, photo and bio appear on your public course pages.';
require_once 'includes/header.php';

$courses = td_courses($pdo, $tid);
$sum = td_learner_summary($pdo, $tid);
$earn = td_earnings($pdo, $tid);
$live = count(array_filter($courses, fn($c) => $c['status'] === 'published'));
$csrf = csrfToken();
?>
<?php if ($error): ?><div class="tp-alert err"><i class="fas fa-circle-exclamation"></i><div><?= e($error) ?></div></div><?php endif; ?>
<div class="tp-grid side-main">
    <div class="tp-stack">
        <section class="tp-card">
            <div class="tp-card-body" style="text-align:center;display:grid;gap:10px;justify-items:center">
                <?= td_avatar($me['avatar'], $me['name'], 'tp-avatar xl') ?>
                <div><h2 style="font-size:1.2rem"><?= e($me['name']) ?></h2><p class="tp-note" style="overflow-wrap:anywhere"><?= e($me['email']) ?></p></div>
                <span class="tp-pill info">Tutor since <?= e(date('M Y', strtotime($me['created_at']))) ?></span>
            </div>
            <div class="tp-mini-stats" style="margin:0 20px 18px">
                <div><b><?= $live ?></b><small>Live courses</small></div>
                <div><b><?= $sum['learners'] ?></b><small>Learners</small></div>
                <div><b style="font-size:.95rem"><?= e(td_money($earn['share'])) ?></b><small>Earned</small></div>
            </div>
        </section>
        <section class="tp-card">
            <div class="tp-card-head"><div><h2><i class="fas fa-lock"></i> Change password</h2></div></div>
            <div class="tp-card-body">
                <form method="post" action="profile" class="tp-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="change_password" value="1">
                    <div class="tp-field"><label for="pw-c">Current password</label><input id="pw-c" type="password" name="current_password" autocomplete="current-password" required></div>
                    <div class="tp-field"><label for="pw-n">New password</label><input id="pw-n" type="password" name="new_password" minlength="8" autocomplete="new-password" required><span class="tp-hint">At least 8 characters.</span></div>
                    <div class="tp-field"><label for="pw-r">Repeat new password</label><input id="pw-r" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required></div>
                    <button class="tp-btn" type="submit"><i class="fas fa-key"></i> Change password</button>
                </form>
            </div>
        </section>
    </div>

    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-id-card"></i> Your details</h2><p>Learners see your name, photo and bio on the course pages.</p></div></div>
        <div class="tp-card-body">
            <form method="post" action="profile" enctype="multipart/form-data" class="tp-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="update_profile" value="1">
                <div class="tp-form-row">
                    <div class="tp-field"><label for="p-n">Full name</label><input id="p-n" type="text" name="name" maxlength="150" value="<?= e($me['name']) ?>" required></div>
                    <div class="tp-field"><label for="p-e">Email</label><input id="p-e" type="email" value="<?= e($me['email']) ?>" readonly><span class="tp-hint">Ask the admin to change your email.</span></div>
                </div>
                <div class="tp-form-row">
                    <div class="tp-field"><label for="p-p">Phone</label><input id="p-p" type="tel" name="phone" maxlength="20" placeholder="+254 7XX XXX XXX" value="<?= e($me['phone'] ?? '') ?>"></div>
                    <div class="tp-field"><label for="p-a">Photo <span class="tp-hint">(JPG, PNG or WebP, up to 3 MB)</span></label><input id="p-a" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/*"></div>
                </div>
                <div class="tp-field"><label for="p-b">Bio</label><textarea id="p-b" name="bio" style="min-height:180px" placeholder="Your background, what you teach and why learners can trust you."><?= e($me['bio'] ?? '') ?></textarea></div>
                <div class="tp-modal-foot"><a class="tp-btn" href="profile">Discard changes</a><button class="tp-btn primary" type="submit"><i class="fas fa-check"></i> Save profile</button></div>
            </form>
        </div>
    </section>
</div>
<?php require 'includes/footer.php'; ?>
