<?php
$pageTitle = 'Master Instructor Profile';
require_once '../includes/header.php';
requireRole('tutor');

// 1. Fetch the most up-to-date user info from DB (since session may not have bio/phone)
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $tutor = $stmt->fetch();
    if (!$tutor) {
        logoutUser();
        header('Location: ../login.php');
        exit;
    }
} catch (Exception $e) {
    $tutor = $user; // fallback
}

$message = '';

// 2. Handle Profile Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $bio = trim($_POST['bio']);

    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, bio = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $bio, $tutor['id']]);
        
        // Refresh session data
        $_SESSION['user_name'] = $name;
        
        // Refresh local variable
        $tutor['name'] = $name;
        $tutor['phone'] = $phone;
        $tutor['bio'] = $bio;
        
        $message = "Your professional profile has been updated!";
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

// 3. Handle AJAX Avatar Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    header('Content-Type: application/json');
    $file = $_FILES['avatar'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
        exit;
    }

    $filename = "AVATAR_" . $tutor['id'] . "_" . time() . "." . $ext;
    $path = "../uploads/avatars/";
    
    if (!is_dir($path)) mkdir($path, 0777, true);

    if (move_uploaded_file($file['tmp_name'], $path . $filename)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$filename, $tutor['id']]);
            $_SESSION['avatar'] = $filename;
            echo json_encode(['success' => true, 'avatar' => $filename]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload failed.']);
    }
    exit;
}

// 4. Instructor Stats
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE tutor_id = ?");
    $stmt->execute([$tutor['id']]);
    $course_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(p.id) FROM payments p 
                           JOIN courses c ON p.course_id = c.id 
                           WHERE c.tutor_id = ? AND p.status = 'verified'");
    $stmt->execute([$tutor['id']]);
    $student_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT SUM(p.amount * 0.8) FROM payments p 
                           JOIN courses c ON p.course_id = c.id 
                           WHERE c.tutor_id = ? AND p.status = 'verified'");
    $stmt->execute([$tutor['id']]);
    $total_earnings = $stmt->fetchColumn() ?: 0;

} catch (Exception $e) { $course_count = $student_count = $total_earnings = 0; }
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .profile-card-main {
        background: white;
        border: 1px solid var(--dark-border);
        border-radius: 32px;
        padding: 48px;
        margin-bottom: 40px;
        display: grid;
        grid-template-columns: 320px 1fr;
        gap: 60px;
    }

    .profile-left { text-align: center; border-right: 1px solid var(--dark-border); padding-right: 60px; }
    
    .avatar-upload-box {
        width: 180px;
        height: 180px;
        border-radius: 60px;
        background: var(--primary-glow);
        margin: 0 auto 32px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 5rem;
        font-weight: 800;
        color: var(--primary);
        overflow: hidden;
        border: 4px solid white;
        box-shadow: var(--shadow-lg);
    }
    .avatar-upload-box img { width: 100%; height: 100%; object-fit: cover; }
    
    .camera-btn {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 44px;
        height: 44px;
        background: var(--secondary);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 4px solid white;
        font-size: 0.9rem;
    }

    .profile-stat-strip {
        display: flex;
        justify-content: center;
        gap: 32px;
        margin-top: 32px;
    }
    .p-stat { text-align: center; }
    .p-stat-val { font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 900; color: var(--dark); }
    .p-stat-lbl { font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; }

    .form-section-title { font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--dark); margin-bottom: 32px; border-bottom: 2px solid var(--primary-glow); padding-bottom: 12px; }
    
    .profile-form-group { margin-bottom: 24px; }
    .profile-form-group label { display: block; font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
    .profile-form-input { 
        width: 100%; padding: 14px 20px; border-radius: 16px; border: 1px solid var(--dark-border); background: #f8fafc; font-family: 'Inter', sans-serif; font-size: 0.95rem; color: var(--dark); transition: 0.3s;
    }
    .profile-form-input:focus { border-color: var(--primary); outline: none; background: white; box-shadow: 0 0 0 5px var(--primary-glow); }

    @media (max-width: 1100px) {
        .profile-card-main { grid-template-columns: 1fr; padding: 32px; gap: 40px; }
        .profile-left { border-right: none; border-bottom: 2px solid var(--primary-glow); padding-right: 0; padding-bottom: 40px; margin-bottom: 20px; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Professional <span class="text-primary">Portfolio</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Update your credentials and brand identity for the students.</p>
            </div>
        </div>
    </header>

    <?php if($message): ?>
        <div style="padding: 16px 24px; background: #DCFCE7; color: #166534; border-radius: 16px; margin-bottom: 32px; display: flex; align-items: center; gap: 12px; border: 1px solid #BBF7D0;">
            <i class="fas fa-check-circle"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="profile-card-main">
        <div class="profile-left">
            <div class="avatar-upload-box">
                <?php if($tutor['avatar']): ?>
                    <img src="../uploads/avatars/<?= htmlspecialchars($tutor['avatar']) ?>" alt="">
                <?php else: ?>
                    <?= strtoupper(substr($tutor['name'], 0, 1)) ?>
                <?php endif; ?>
                <div class="camera-btn" onclick="document.getElementById('avatarInput').click()"><i class="fas fa-camera"></i></div>
                <input type="file" id="avatarInput" style="display: none;" accept="image/*" onchange="uploadAvatar(this)">
            </div>
            
            <h2 style="font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 1.5rem; color: var(--dark);"><?= htmlspecialchars($tutor['name']) ?></h2>
            <p style="color: var(--text-dim); font-size: 0.88rem; margin-top: 4px;">Professor Identity • Since <?= date('Y', strtotime($tutor['created_at'])) ?></p>
            
            <div class="profile-stat-strip">
                <div class="p-stat">
                    <div class="p-stat-val"><?= $course_count ?></div>
                    <div class="p-stat-lbl">Courses</div>
                </div>
                <div class="p-stat">
                    <div class="p-stat-val" style="color: var(--primary);"><?= $student_count ?></div>
                    <div class="p-stat-lbl">Students</div>
                </div>
                <div class="p-stat">
                    <div class="p-stat-val" style="color: #10B981;"><?= number_format($total_earnings/1000, 1) ?>k</div>
                    <div class="p-stat-lbl">Earnings</div>
                </div>
            </div>

            <div style="margin-top: 48px; padding: 24px; background: #f8fafc; border-radius: 20px; border: 1px solid #e2e8f0;">
                <div style="font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 12px;">Credential Rank</div>
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; color: var(--secondary); font-weight: 900; font-size: 1.1rem;">
                    <i class="fas fa-crown"></i> Elite Instructor
                </div>
            </div>
        </div>

        <div class="profile-right">
            <h3 class="form-section-title">Master Credentials</h3>
            
            <input type="hidden" name="update_profile" value="1">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="profile-form-group">
                    <label>Full Professional Name</label>
                    <input type="text" name="name" class="profile-form-input" value="<?= htmlspecialchars($tutor['name']) ?>" required>
                </div>
                <div class="profile-form-group">
                    <label>Contact Email (Secured)</label>
                    <input type="email" class="profile-form-input" value="<?= htmlspecialchars($tutor['email']) ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="profile-form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="profile-form-input" value="<?= htmlspecialchars($tutor['phone'] ?? '+254 ') ?>">
                </div>
                <div class="profile-form-group">
                    <label>Official Title</label>
                    <input type="text" class="profile-form-input" value="Certified Academic Instructor" disabled style="opacity: 0.6;">
                </div>
            </div>

            <div class="profile-form-group">
                <label>Professional Biography / Mission Statement</label>
                <textarea name="bio" class="profile-form-input" style="min-height: 160px; resize: none;" placeholder="Introduce yourself to your future students and share your expertise..."><?= htmlspecialchars($tutor['bio'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 16px; margin-top: 40px;">
                <button type="submit" class="btn btn-primary" style="padding: 18px 48px; border-radius: 18px; font-weight: 800; font-size: 1rem;">
                    Save Professional Profile
                </button>
                <button type="reset" class="btn btn-ghost" style="padding: 18px 32px; border-radius: 18px;">Discard Changes</button>
            </div>
        </div>
    </form>
</main>

<script>
    async function uploadAvatar(input) {
        if (!input.files || !input.files[0]) return;
        
        const formData = new FormData();
        formData.append('avatar', input.files[0]);
        
        try {
            const res = await fetch('profile.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.success) {
                location.reload();
            } else {
                if (window.SDA && window.SDA.showToast) {
                    SDA.showToast(data.message || "Upload failed", "danger");
                } else {
                    alert(data.message || "Upload failed");
                }
            }
        } catch (e) { 
            console.error(e);
            alert("Network error during upload"); 
        }
    }
</script>
<script src="../assets/js/main.js"></script>
</body>
</html>
