<?php
$pageTitle = 'Profile & settings';
require_once 'includes/header.php';

$sid = (int)$student['id'];
$error = '';

// Form handling runs before any page output so the redirects work.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Your session expired. Please try again.';
    }

    // Personal details
    elseif (isset($_POST['update_profile'])) {
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $bio   = trim((string)($_POST['bio'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter your name and a valid email address.';
        } else {
            try {
                $dup = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
                $dup->execute([$email, $sid]);
                if ($dup->fetch()) {
                    $error = 'Another account already uses that email address.';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, bio = ? WHERE id = ?");
                    $stmt->execute([mb_substr($name, 0, 150), $email, $phone !== '' ? mb_substr($phone, 0, 20) : null, $bio, $sid]);
                    // The portal reads name/email from the session first, so keep it in step.
                    $_SESSION['user_name']  = mb_substr($name, 0, 150);
                    $_SESSION['user_email'] = $email;
                    header('Location: profile?success=1');
                    exit;
                }
            } catch (Exception $e) { $error = 'Could not update your profile. Please try again.'; }
        }
    }

    // Password change
    elseif (isset($_POST['change_password'])) {
        $new = (string)($_POST['new_password'] ?? '');
        try {
            $row = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $row->execute([$sid]);
            $hash = (string)$row->fetchColumn();
            if (!password_verify((string)($_POST['current_password'] ?? ''), $hash)) {
                $error = 'Your current password is not correct.';
            } elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
                $error = 'Your new password needs at least 8 characters, with letters and numbers.';
            } elseif ($new !== (string)($_POST['confirm_password'] ?? '')) {
                $error = 'The new passwords do not match.';
            } else {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new, PASSWORD_DEFAULT), $sid]);
                header('Location: profile?password_success=1');
                exit;
            }
        } catch (Exception $e) { $error = 'Could not change your password. Please try again.'; }
    }

    // Avatar upload
    elseif (isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'The photo could not be uploaded. Please try again.';
        } elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) || $file['size'] > 2 * 1024 * 1024 || !@getimagesize($file['tmp_name'])) {
            $error = 'Your photo must be a JPG, PNG, WebP or GIF image under 2 MB.';
        } else {
            $dir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $newName = 'avatar_' . $sid . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $newName)) {
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$newName, $sid]);
                $_SESSION['avatar'] = $newName;
                header('Location: profile?avatar_success=1');
                exit;
            }
            $error = 'The photo could not be saved. Please try again.';
        }
    }
}

// The session can hold an empty avatar that hides the saved one; prefer the database value.
if (!empty($student_info['avatar'])) $student['avatar'] = $student_info['avatar'];

$initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string)$student['name'])), 0, 2))));
$csrf = htmlspecialchars(csrfToken());

require_once 'includes/layout-top.php';
?>

<style>
.pg-profile .pf-avatar { position: relative; width: 120px; height: 120px; margin: 4px auto 16px; }
.pg-profile .pf-avatar .sp-avatar { width: 120px; height: 120px; font-size: 2.4rem; }
.pg-profile .pf-avatar label {
    position: absolute; right: 0; bottom: 0; width: 38px; height: 38px; border-radius: 50%;
    background: var(--primary); color: #fff; border: 3px solid var(--surface); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.pg-profile .pf-avatar label:hover { background: var(--primary-dark); }
.pg-profile .pf-two { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
@media (max-width: 640px) { .pg-profile .pf-two { grid-template-columns: 1fr; } }
.pg-profile .sp-form input[readonly] { background: var(--surface-2); color: var(--text-dim); cursor: not-allowed; }
</style>

<div class="pg-profile">
    <div class="sp-page-head">
        <div><h1>Profile & settings</h1><p>Update your photo, your contact details and your password.</p></div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="sp-alert success" role="status"><i class="fas fa-circle-check"></i> Your profile has been saved.</div>
    <?php elseif (isset($_GET['avatar_success'])): ?>
        <div class="sp-alert success" role="status"><i class="fas fa-circle-check"></i> Your new photo has been saved.</div>
    <?php elseif (isset($_GET['password_success'])): ?>
        <div class="sp-alert success" role="status"><i class="fas fa-circle-check"></i> Your password has been changed.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="sp-alert error" role="alert"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="sp-split">
        <div>
            <!-- Personal details -->
            <div class="sp-card">
                <div class="sp-card-head"><h3>Personal details</h3></div>
                <form method="POST" class="sp-form">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="pf-two">
                        <div class="sp-field">
                            <label for="pf-name">Full name</label>
                            <input type="text" id="pf-name" name="name" value="<?= htmlspecialchars($student['name'] ?? '') ?>" required maxlength="150" autocomplete="name">
                        </div>
                        <div class="sp-field">
                            <label for="pf-email">Email address</label>
                            <input type="email" id="pf-email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>" required autocomplete="email">
                        </div>
                        <div class="sp-field">
                            <label for="pf-phone">Phone number</label>
                            <input type="tel" id="pf-phone" name="phone" value="<?= htmlspecialchars($student['phone'] ?? '') ?>" maxlength="20" placeholder="e.g. 0712 345 678" autocomplete="tel">
                        </div>
                        <div class="sp-field">
                            <label for="pf-track">Programme</label>
                            <input type="text" id="pf-track" value="Professional certification programme" readonly>
                        </div>
                    </div>
                    <div class="sp-field">
                        <label for="pf-bio">About you</label>
                        <textarea id="pf-bio" name="bio" rows="4" placeholder="What are you learning, and what would you like to do next?"><?= htmlspecialchars($student['bio'] ?? '') ?></textarea>
                        <div class="sp-help">Optional. A few lines is plenty.</div>
                    </div>
                    <div class="sp-row" style="justify-content: flex-end;">
                        <button type="submit" name="update_profile" class="sp-btn"><i class="fas fa-floppy-disk"></i> Save changes</button>
                    </div>
                </form>
            </div>

            <!-- Password -->
            <div class="sp-card">
                <div class="sp-card-head"><h3>Change password</h3></div>
                <form method="POST" class="sp-form" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="sp-field">
                        <label for="pw-cur">Current password</label>
                        <input type="password" id="pw-cur" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="pf-two">
                        <div class="sp-field">
                            <label for="pw-new">New password</label>
                            <input type="password" id="pw-new" name="new_password" required minlength="8" autocomplete="new-password">
                            <div class="sp-help">At least 8 characters, with letters and numbers.</div>
                        </div>
                        <div class="sp-field">
                            <label for="pw-conf">Confirm new password</label>
                            <input type="password" id="pw-conf" name="confirm_password" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="sp-row" style="justify-content: flex-end;">
                        <button type="submit" name="change_password" class="sp-btn ghost"><i class="fas fa-lock"></i> Change password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Photo + summary -->
        <div>
            <div class="sp-card" style="text-align: center;">
                <div class="pf-avatar">
                    <span class="sp-avatar">
                        <?php if (!empty($student['avatar'])): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" alt="Your profile photo" onerror="this.remove()">
                        <?php endif; ?>
                        <?= htmlspecialchars($initials ?: 'S') ?>
                    </span>
                    <label for="avatarInput" title="Change photo" aria-label="Change photo"><i class="fas fa-camera"></i></label>
                </div>
                <form id="avatarForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden onchange="document.getElementById('avatarForm').submit()">
                </form>
                <h3 style="margin: 0; font-size: 1.1rem;"><?= htmlspecialchars($student['name'] ?? '') ?></h3>
                <p class="sp-muted sp-small" style="margin: 4px 0 0;"><?= htmlspecialchars($student['email'] ?? '') ?></p>
                <p class="sp-help" style="margin-top: 12px;">Tap the camera to change your photo (JPG, PNG, WebP or GIF, up to 2 MB).</p>
            </div>

            <div class="sp-card">
                <div class="sp-list">
                    <div class="sp-list-item">
                        <span class="sp-ico yellow"><i class="fas fa-bolt"></i></span>
                        <div class="sp-grow"><h4>Merit points</h4><p>Earned from lessons and quizzes</p></div>
                        <strong><?= number_format((int)($student['merit_points'] ?? 0)) ?></strong>
                    </div>
                    <div class="sp-list-item">
                        <span class="sp-ico green"><i class="fas fa-user-check"></i></span>
                        <div class="sp-grow"><h4>Account</h4><p>Student</p></div>
                        <?php $st = (string)($student_info['status'] ?? $student['status'] ?? 'active'); ?>
                        <span class="sp-badge <?= $st === 'active' ? 'green' : 'yellow' ?>"><?= htmlspecialchars(ucfirst($st)) ?></span>
                    </div>
                    <a href="certificates" class="sp-list-item" style="text-decoration: none;">
                        <span class="sp-ico violet"><i class="fas fa-certificate"></i></span>
                        <div class="sp-grow"><h4>Certificates</h4><p>View and download yours</p></div>
                        <i class="fas fa-chevron-right sp-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
