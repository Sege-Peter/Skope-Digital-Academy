<?php
$pageTitle = 'My Profile';
require_once 'includes/header.php';
require_once 'includes/layout-top.php';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $bio   = trim($_POST['bio'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, bio = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $bio, $student['id']]);
        header('Location: profile.php?success=1');
        exit;
    } catch(Exception $e) { $error = "Could not update profile."; }
}

// Handle Avatar Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = 'avatar_' . $student['id'] . '_' . time() . '.' . $ext;
    $target = '../uploads/avatars/' . $newName;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$newName, $student['id']]);
        header('Location: profile.php?avatar_success=1');
        exit;
    }
}
?>

<header class="portal-header" style="margin-bottom: 40px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">My Profile Studio<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Manage your digital identity and scholarly records within the SDA ecosystem.</p>
    </div>
</header>

<div class="profile-grid" style="display: grid; grid-template-columns: 350px 1fr; gap: 40px;">
    <div class="premium-card" style="display: flex; flex-direction: column; align-items: center; text-align: center; padding: 48px 32px; height: fit-content; border-radius: 30px;">
        <div class="avatar-studio" style="position: relative; width: 160px; height: 160px; margin-bottom: 32px;">
            <?php if(!empty($student['avatar'])): ?>
                <img src="../uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" style="width: 100%; height: 100%; border-radius: 40px; object-fit: cover; border: 4px solid #fff; box-shadow: 0 20px 40px rgba(0,0,0,0.12);">
            <?php else: ?>
                <div style="width: 100%; height: 100%; border-radius: 40px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 4rem; color: #cbd5e1; border: 1px solid var(--border);"><i class="fas fa-user-astronaut"></i></div>
            <?php endif; ?>
            <label for="avatarInput" style="position: absolute; bottom: -5px; right: -5px; width: 48px; height: 48px; border-radius: 16px; background: var(--grad-primary); color: white; display: flex; align-items: center; justify-content: center; border: 4px solid #fff; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(0,174,239,0.3);"><i class="fas fa-camera"></i></label>
            <form id="avatarForm" method="POST" enctype="multipart/form-data">
                <input type="file" name="avatar" id="avatarInput" hidden onchange="document.getElementById('avatarForm').submit()">
            </form>
        </div>
        
        <h4 style="font-weight: 950; font-size: 1.4rem; margin: 0; color: var(--text-main); letter-spacing: -0.5px;"><?= htmlspecialchars($student['name']) ?></h4>
        <p style="color: var(--text-dim); font-size: 0.85rem; margin-top: 8px; font-weight: 600;"><?= htmlspecialchars($student['email']) ?></p>
        
        <div style="width: 100%; margin-top: 40px; padding-top: 40px; border-top: 1px solid #f1f5f9;">
            <div style="display: flex; flex-direction: column; gap: 20px; text-align: left;">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: #FFFBEB; border-radius: 16px; border: 1px solid #FEF3C7;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-bolt" style="color: #F59E0B;"></i>
                        <span style="font-size: 0.75rem; font-weight: 900; color: #92400E; text-transform: uppercase;">Merit Points</span>
                    </div>
                    <strong style="font-size: 1rem; font-weight: 950; color: #92400E;"><?= number_format($student['merit_points'] ?? 0) ?></strong>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; background: #F0F9FF; border-radius: 16px; border: 1px solid #BAE6FD;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-certificate" style="color: var(--primary);"></i>
                        <span style="font-size: 0.75rem; font-weight: 900; color: #0369A1; text-transform: uppercase;">Credentials</span>
                    </div>
                    <strong style="font-size: 1rem; font-weight: 950; color: #0369A1;">Verified</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="premium-card" style="padding: 48px; border-radius: 30px;">
        <h3 style="font-weight: 950; font-size: 1.5rem; margin-bottom: 40px; letter-spacing: -0.5px; color: var(--text-main);">Global Profile Synchronization</h3>
        
        <?php if(isset($_GET['success'])): ?>
        <div style="padding: 20px 24px; background: #ECFDF5; border: 1px solid #10B981; border-radius: 16px; color: #065F46; font-size: 0.85rem; font-weight: 800; margin-bottom: 32px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.4s ease;">
            <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i> Profile records successfully updated and synchronized.
        </div>
        <?php endif; ?>

        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px;">
                <div class="form-group">
                    <label style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Legal Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($student['name']) ?>" required style="width: 100%; padding: 16px 20px; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 0.95rem; font-weight: 600; outline: none; transition: 0.3s;">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Primary Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" required style="width: 100%; padding: 16px 20px; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 0.95rem; font-weight: 600; outline: none; transition: 0.3s;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px;">
                <div class="form-group">
                    <label style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Verified Contact</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($student['phone']) ?>" required style="width: 100%; padding: 16px 20px; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 0.95rem; font-weight: 600; outline: none; transition: 0.3s;">
                </div>
                <div class="form-group">
                    <label style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Institutional Track</label>
                    <input type="text" class="form-control" value="Professional Certification Program" readonly style="width: 100%; padding: 16px 20px; border: 1px solid #f1f5f9; border-radius: 16px; font-size: 0.95rem; font-weight: 700; background: #f8fafc; color: #94a3b8; cursor: not-allowed;">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 40px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Professional Bio & Aspirations</label>
                <textarea name="bio" class="form-control" rows="5" placeholder="Define your learning trajectory..." style="width: 100%; padding: 16px 20px; border: 1px solid #e2e8f0; border-radius: 16px; font-size: 0.95rem; font-weight: 600; outline: none; transition: 0.3s; resize: none;"><?= htmlspecialchars($student['bio'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" name="update_profile" class="btn-premium" style="padding: 18px 48px; border-radius: 18px; border: none; font-size: 0.95rem; box-shadow: 0 15px 30px rgba(0,174,239,0.25);"><i class="fas fa-sync" style="margin-right: 10px;"></i> Save Global Profile</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.form-control:focus { border-color: var(--primary) !important; box-shadow: 0 0 0 4px rgba(0, 174, 239, 0.1); }
@media (max-width: 1100px) { .profile-grid { grid-template-columns: 1fr !important; } }
</style>
<?php require_once 'includes/layout-bottom.php'; ?>
