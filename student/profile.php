<?php
$pageTitle = 'My Academic Profile';
$message = '';
$error = '';
require_once 'includes/header.php';

// Fetch Latest User Record from DB
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student['id']]); // $student comes from header.php
    $student = $stmt->fetch();
    if (!$student) { header('Location: ../login.php'); exit; }
} catch (Exception $e) { $error = "Context Error: " . $e->getMessage(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $bio = trim($_POST['bio']);
    $dob = $_POST['dob'] ?: null;
    $gender = $_POST['gender'] ?: null;
    $county = trim($_POST['county']);
    $nationality = trim($_POST['nationality']);
    $national_id = trim($_POST['national_id']);
    $education = trim($_POST['education_level']);
    $avatar_name = $student['avatar'];
    $id_doc_name = $student['id_document'];

    // Handle Avatar Image Upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === 0) {
        $upload_dir = '../uploads/avatars/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed)) {
            $new_name = 'av_' . uniqid() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $new_name)) {
                if ($avatar_name && file_exists($upload_dir . $avatar_name)) @unlink($upload_dir . $avatar_name);
                $avatar_name = $new_name;
            }
        } else { $error = "Invalid avatar format. Please use JPG, PNG or WEBP."; }
    }

    // Handle ID Document Upload (Optional)
    if (isset($_FILES['id_document_file']) && $_FILES['id_document_file']['error'] === 0) {
        $id_dir = '../uploads/id_docs/';
        if (!is_dir($id_dir)) mkdir($id_dir, 0777, true);

        $file_ext = strtolower(pathinfo($_FILES['id_document_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

        if (in_array($file_ext, $allowed)) {
            $new_id_name = 'id_' . $student['id'] . '_' . time() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['id_document_file']['tmp_name'], $id_dir . $new_id_name)) {
                if ($id_doc_name && file_exists($id_dir . $id_doc_name)) @unlink($id_dir . $id_doc_name);
                $id_doc_name = $new_id_name;
            }
        } else { $error = "ID Document must be PDF, JPG or PNG."; }
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, bio = ?, avatar = ?, dob = ?, gender = ?, county = ?, nationality = ?, national_id = ?, education_level = ?, id_document = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $bio, $avatar_name, $dob, $gender, $county, $nationality, $national_id, $education, $id_doc_name, $student['id']]);
            
            $student = array_merge($student, [
                'name' => $name, 'phone' => $phone, 'bio' => $bio, 'avatar' => $avatar_name,
                'dob' => $dob, 'gender' => $gender, 'county' => $county, 'nationality' => $nationality,
                'national_id' => $national_id, 'education_level' => $education, 'id_document' => $id_doc_name
            ]);
            $message = "Institutional profile and credentials synchronized!";
        } catch (Exception $e) { $error = "System Error: " . $e->getMessage(); }
    }
}

// Stats for profile view
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'active'");
    $stmt->execute([$student['id']]);
    $active_courses = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ? AND passed = 1");
    $stmt->execute([$student['id']]);
    $quizzes_passed = $stmt->fetchColumn();

} catch (Exception $e) { $active_courses = $quizzes_passed = 0; }
?>

<?php require_once 'includes/sidebar.php'; ?>

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
    @media (max-width: 640px) {
        .profile-right div[style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
        .avatar-upload-box { width: 140px; height: 140px; border-radius: 40px; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Academic <span style="color: #00BFFF;">Identity</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Customize your digital footprint and scholarly persona within SDAC.</p>
            </div>
        </div>
    </header>

    <?php if($message): ?>
        <div style="padding: 16px 24px; background: #DCFCE7; color: #166534; border-radius: 16px; margin-bottom: 32px; font-weight: 600; display: flex; align-items: center; gap: 12px; border: 1px solid #BBF7D0;">
            <i class="fas fa-check-circle"></i> <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div style="padding: 16px 24px; background: #FEE2E2; color: #991B1B; border-radius: 16px; margin-bottom: 32px; font-weight: 600; display: flex; align-items: center; gap: 12px; border: 1px solid #FECACA;">
            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="profile-card-main" enctype="multipart/form-data">
        <div class="profile-left">
            <div class="avatar-upload-box" id="avatarPreviewContainer">
                <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                <?php if($student['avatar']): ?>
                    <img src="../uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" id="avatarPreview" alt="">
                <?php else: ?>
                    <span id="avatarFallback"><?= strtoupper(substr($student['name'], 0, 1)) ?></span>
                <?php endif; ?>
                <div class="camera-btn" onclick="document.getElementById('avatarInput').click()"><i class="fas fa-camera"></i></div>
            </div>
            
            <h2 style="font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 1.5rem; color: var(--dark);"><?= htmlspecialchars($student['name']) ?></h2>
            <?php if(!empty($student['admission_number'])): ?>
            <div style="margin: 6px 0 2px; font-weight: 800; color: var(--primary); font-family: 'Courier New', monospace; font-size: 0.95rem; background: var(--primary-glow); padding: 4px 12px; border-radius: 8px; display: inline-block;">
                <?= htmlspecialchars($student['admission_number']) ?>
            </div>
            <?php endif; ?>
            <p style="color: var(--text-dim); font-size: 0.88rem; margin-top: 4px;">Member since <?= date('F Y', strtotime($student['created_at'])) ?></p>
            
            <div class="profile-stat-strip">
                <div class="p-stat">
                    <div class="p-stat-val"><?= $active_courses ?></div>
                    <div class="p-stat-lbl">Courses</div>
                </div>
                <div class="p-stat">
                    <div class="p-stat-val" style="color: var(--primary);"><?= $student['points'] ?></div>
                    <div class="p-stat-lbl">Merit</div>
                </div>
                <div class="p-stat">
                    <div class="p-stat-val" style="color: #10B981;"><?= $quizzes_passed ?></div>
                    <div class="p-stat-lbl">Passes</div>
                </div>
            </div>

            <div style="margin-top: 48px; padding-top: 32px; border-top: 1px solid #f1f5f9;">
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Account Status</div>
                <span class="badge badge-success" style="padding: 8px 16px; border-radius: 10px;">Fully Verified Student</span>
            </div>
        </div>

        <div class="profile-right">
            <h3 class="form-section-title">Academic & Institutional Identity</h3>
            
            <input type="hidden" name="update_profile" value="1">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="profile-form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="profile-form-input" value="<?= htmlspecialchars($student['name']) ?>" required>
                </div>
                <div class="profile-form-group">
                    <label>National ID / Passport</label>
                    <input type="text" name="national_id" class="profile-form-input" value="<?= htmlspecialchars($student['national_id'] ?? '') ?>" placeholder="Required for certification">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="profile-form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" class="profile-form-input" value="<?= $student['dob'] ?>">
                </div>
                <div class="profile-form-group">
                    <label>Gender Identification</label>
                    <select name="gender" class="profile-form-input">
                        <option value="">Select Gender</option>
                        <option value="male" <?= ($student['gender']??'') == 'male' ? 'selected' : '' ?>>Male</option>
                        <option value="female" <?= ($student['gender']??'') == 'female' ? 'selected' : '' ?>>Female</option>
                        <option value="other" <?= ($student['gender']??'') == 'other' ? 'selected' : '' ?>>Other / Non-Binary</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="profile-form-group">
                    <label>County of Residence</label>
                    <input type="text" name="county" class="profile-form-input" value="<?= htmlspecialchars($student['county'] ?? '') ?>" placeholder="e.g. Nairobi">
                </div>
                <div class="profile-form-group">
                    <label>Nationality</label>
                    <input type="text" name="nationality" class="profile-form-input" value="<?= htmlspecialchars($student['nationality'] ?? 'Kenyan') ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <div class="profile-form-group">
                    <label>Highest Education Level</label>
                    <input type="text" name="education_level" class="profile-form-input" value="<?= htmlspecialchars($student['education_level'] ?? '') ?>" placeholder="e.g. University Graduate">
                </div>
                <div class="profile-form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="profile-form-input" value="<?= htmlspecialchars($student['phone'] ?? '+254 ') ?>">
                </div>
            </div>

            <div class="profile-form-group">
                <label>Institutional ID Upload (Optional)</label>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <input type="file" name="id_document_file" class="profile-form-input" accept=".pdf,.jpg,.png" style="padding: 10px;">
                    <?php if($student['id_document']): ?>
                        <span style="color: #10B981; font-size: 0.75rem; font-weight: 800; white-space: nowrap;"><i class="fas fa-check-circle"></i> Complete</span>
                    <?php else: ?>
                        <span style="color: var(--text-dim); font-size: 0.7rem; white-space: nowrap;">Pending Audit</span>
                    <?php endif; ?>
                </div>
                <p style="font-size: 0.65rem; color: var(--text-dim); margin-top: 4px;">Soft copy of National ID or Passport (PDF/JPG)</p>
            </div>

            <div class="profile-form-group">
                <label>Professional Bio & Career Goals</label>
                <textarea name="bio" class="profile-form-input" style="min-height: 100px; resize: none;" placeholder="Tell instructors about your background..."><?= htmlspecialchars($student['bio'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 16px; margin-top: 40px;">
                <button type="submit" class="btn btn-primary" style="padding: 18px 48px; border-radius: 18px; font-weight: 800; font-size: 1rem; background: #00BFFF; border-color: #00BFFF;">
                    Sync My SDAC Identity
                </button>
            </div>
        </div>
    </form>
</main>

<script src="../assets/js/main.js"></script>
<script>
    function previewAvatar(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                let img = document.getElementById('avatarPreview');
                let fallback = document.getElementById('avatarFallback');
                
                if (!img) {
                    img = document.createElement('img');
                    img.id = 'avatarPreview';
                    document.getElementById('avatarPreviewContainer').prepend(img);
                }
                
                img.src = e.target.result;
                img.style.display = 'block';
                if (fallback) fallback.style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
</body>
</html>
