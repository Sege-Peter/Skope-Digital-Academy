<?php
$pageTitle = 'Master Instructor Profile';
require_once 'includes/header.php';

// 1. Fetch the most up-to-date user info from DB (since session may not have bio/phone)
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$tutor['id']]);
    $tutor_data = $stmt->fetch();
    if (!$tutor_data) {
        logoutUser();
        header('Location: ../login.php');
        exit;
    }
} catch (Exception $e) {
    $tutor_data = $tutor; // fallback
}

$message = '';

// 2. Handle Profile Info Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $bio = trim($_POST['bio']);

    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, bio = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $bio, $tutor_data['id']]);
        
        // Refresh session data
        $_SESSION['user_name'] = $name;
        
        // Refresh local variable
        $tutor_data['name'] = $name;
        $tutor_data['phone'] = $phone;
        $tutor_data['bio'] = $bio;
        
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

    $filename = "AVATAR_" . $tutor_data['id'] . "_" . time() . "." . $ext;
    $path = "../uploads/avatars/";
    
    if (!is_dir($path)) mkdir($path, 0777, true);

    if (move_uploaded_file($file['tmp_name'], $path . $filename)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->execute([$filename, $tutor_data['id']]);
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
    $stmt->execute([$tutor_data['id']]);
    $course_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(p.id) FROM payments p 
                           JOIN courses c ON p.course_id = c.id 
                           WHERE c.tutor_id = ? AND p.status = 'verified'");
    $stmt->execute([$tutor_data['id']]);
    $student_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT SUM(p.amount * 0.8) FROM payments p 
                           JOIN courses c ON p.course_id = c.id 
                           WHERE c.tutor_id = ? AND p.status = 'verified'");
    $stmt->execute([$tutor_data['id']]);
    $total_earnings = $stmt->fetchColumn() ?: 0;

} catch (Exception $e) { $course_count = $student_count = $total_earnings = 0; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    @media (max-width: 1024px) {
        .profile-layout { grid-template-columns: 1fr !important; gap: 32px; }
    }
</style>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Professional Portfolio<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Update your <span style="color: var(--primary); font-weight: 700;">instructional credentials</span> and brand identity.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <button type="reset" form="profileForm" class="btn-premium" style="background: white; color: var(--text-dim); border: 1px solid #E2E8F0; box-shadow: none; font-size: 0.8rem; padding: 12px 24px;">
            DISCARD CHANGES
        </button>
        <button type="submit" form="profileForm" class="btn-premium" style="padding: 12px 32px;">
            <i class="fas fa-shield-check"></i> SAVE PORTFOLIO
        </button>
    </div>
</header>

<?php if($message): ?>
    <div style="padding: 20px 32px; background: #ECFDF5; color: #065F46; border-radius: 20px; margin-bottom: 40px; display: flex; align-items: center; gap: 16px; border: 1px solid #D1FAE5; font-weight: 700;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: #10B981; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;"><i class="fas fa-check"></i></div>
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="profile-layout" style="display: grid; grid-template-columns: 380px 1fr; gap: 40px;">
    <!-- Profile Sidebar -->
    <div style="display: flex; flex-direction: column; gap: 40px;">
        <div class="premium-card" style="padding: 48px 32px; text-align: center;">
            <div style="position: relative; width: 200px; height: 200px; margin: 0 auto 32px;">
                <div style="width: 100%; height: 100%; border-radius: 60px; background: #F8FAFC; border: 8px solid white; box-shadow: var(--shadow-premium); overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 4rem; font-weight: 950; color: var(--primary);">
                    <?php if($tutor_data['avatar']): ?>
                        <img src="../uploads/avatars/<?= htmlspecialchars($tutor_data['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?= strtoupper(substr($tutor_data['name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <button onclick="document.getElementById('avatarInput').click()" style="position: absolute; bottom: 10px; right: 10px; width: 52px; height: 52px; border-radius: 20px; background: var(--primary); color: white; border: 4px solid white; cursor: pointer; font-size: 1.1rem; box-shadow: var(--shadow-sm); transition: 0.3s;" onmouseover="this.style.transform='scale(1.1)'">
                    <i class="fas fa-camera"></i>
                </button>
                <input type="file" id="avatarInput" style="display: none;" accept="image/*" onchange="uploadAvatar(this)">
            </div>
            
            <h2 style="margin: 0; font-size: 1.5rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.5px;"><?= htmlspecialchars($tutor_data['name']) ?></h2>
            <div style="display: inline-block; padding: 6px 14px; background: #F1F5F9; color: var(--text-dim); border-radius: 50px; font-size: 0.7rem; font-weight: 950; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 12px;">Professor Identity</div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-top: 48px; padding-top: 40px; border-top: 1px solid #F1F5F9;">
                <div>
                    <div style="font-size: 1.25rem; font-weight: 950; color: var(--text-main);"><?= $course_count ?></div>
                    <div style="font-size: 0.6rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Courses</div>
                </div>
                <div>
                    <div style="font-size: 1.25rem; font-weight: 950; color: var(--primary);"><?= $student_count ?></div>
                    <div style="font-size: 0.6rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Scholars</div>
                </div>
                <div>
                    <div style="font-size: 1.25rem; font-weight: 950; color: #10B981;">KES <?= number_format($total_earnings/1000, 1) ?>k</div>
                    <div style="font-size: 0.6rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px;">Impact</div>
                </div>
            </div>
        </div>

        <div class="premium-card" style="padding: 32px; background: var(--grad-premium); color: white;">
            <div style="font-size: 0.65rem; font-weight: 950; text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,0.6); margin-bottom: 20px;">Institutional Rank</div>
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 52px; height: 52px; border-radius: 16px; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #FFD700;"><i class="fas fa-award"></i></div>
                <div>
                    <div style="font-weight: 950; font-size: 1.1rem; letter-spacing: -0.3px;">Elite Academic</div>
                    <div style="font-size: 0.75rem; font-weight: 600; color: rgba(255,255,255,0.7);">Level 5 Instruction Hub</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Form -->
    <div class="premium-card" style="padding: 48px;">
        <form id="profileForm" method="POST">
            <input type="hidden" name="update_profile" value="1">
            
            <h3 style="margin: 0 0 40px; font-size: 1.25rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
                <span style="width: 8px; height: 32px; background: var(--primary); border-radius: 4px;"></span>
                Master Credentials
            </h3>

            <div class="grid-2" style="margin-bottom: 32px;">
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Full Professional Name</label>
                    <input type="text" name="name" style="width: 100%; padding: 16px 24px; border-radius: 16px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-size: 1rem; font-weight: 700; color: var(--text-main); outline: none; transition: 0.3s;" value="<?= htmlspecialchars($tutor_data['name']) ?>" required onfocus="this.style.borderColor='var(--primary)'; this.style.background='white'">
                </div>
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Institutional Email (Secured)</label>
                    <input type="email" style="width: 100%; padding: 16px 24px; border-radius: 16px; border: 1.5px solid #F1F5F9; background: #F1F5F9; font-size: 1rem; font-weight: 700; color: var(--text-dim); outline: none; cursor: not-allowed;" value="<?= htmlspecialchars($tutor_data['email']) ?>" disabled>
                </div>
            </div>

            <div class="grid-2" style="margin-bottom: 32px;">
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Direct Contact Node</label>
                    <input type="text" name="phone" style="width: 100%; padding: 16px 24px; border-radius: 16px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-size: 1rem; font-weight: 700; color: var(--text-main); outline: none; transition: 0.3s;" value="<?= htmlspecialchars($tutor_data['phone'] ?? '') ?>" placeholder="+254 7XX XXX XXX" onfocus="this.style.borderColor='var(--primary)'; this.style.background='white'">
                </div>
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Professional Rank</label>
                    <input type="text" style="width: 100%; padding: 16px 24px; border-radius: 16px; border: 1.5px solid #F1F5F9; background: #F1F5F9; font-size: 1rem; font-weight: 700; color: var(--text-dim); outline: none;" value="Certified Academic Instructor" disabled>
                </div>
            </div>

            <div style="margin-bottom: 40px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px;">Instructional Mission Statement / Biography</label>
                <textarea name="bio" style="width: 100%; min-height: 200px; padding: 24px; border-radius: 16px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-family: inherit; font-size: 1rem; font-weight: 600; color: var(--text-main); outline: none; resize: none; transition: 0.3s;" placeholder="Share your academic mission and professional expertise with your scholars..." onfocus="this.style.borderColor='var(--primary)'; this.style.background='white'"><?= htmlspecialchars($tutor_data['bio'] ?? '') ?></textarea>
            </div>

            <div style="padding: 24px; background: #FFFBEB; border: 1px solid #FEF3C7; border-radius: 20px; display: flex; gap: 20px; align-items: flex-start;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: white; color: #F59E0B; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: var(--shadow-sm);"><i class="fas fa-info-circle"></i></div>
                <div>
                    <div style="font-weight: 950; font-size: 0.9rem; color: #92400E; margin-bottom: 4px;">Public Branding Note</div>
                    <p style="margin: 0; font-size: 0.85rem; color: #B45309; line-height: 1.6; font-weight: 600;">Your professional name and mission statement are visible to all scholars on their dashboard and course enrollment pages.</p>
                </div>
            </div>
        </form>
    </div>
</div>
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
                alert(data.message || "Upload failed");
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
