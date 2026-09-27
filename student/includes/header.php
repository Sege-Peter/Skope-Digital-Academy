<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access control: Student only
requireRole('student');

$student = currentUser();

// Fetch student extra info (points, etc)
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student['id']]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student_info) {
        $student = array_merge($student_info, $student);
        
        // Final fallback: Ensure referral_code exists
        if (empty($student['referral_code'])) {
            $new_code = 'SDA' . strtoupper(substr(md5($student['id'] . time()), 0, 8));
            $upd = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $upd->execute([$new_code, $student['id']]);
            $student['referral_code'] = $new_code;
        }
    }
} catch (Exception $e) { $student_info = []; }
$user = $student; // Shared variable for sidebar compatibility
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'My Learning' ?> – SDAC Academy</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css"> <!-- Consistent dashboard look -->
    <link rel="stylesheet" href="../assets/css/student-portal.css"> <!-- New Premium Theme -->
    
    <link rel="icon" type="image/png" href="../assets/images/Skope Digital  logo.png">

</head>
<body class="dashboard-body student-portal">
<?php if(isset($_SESSION['admin_impersonator_id'])): ?>
    <div style="background: #ef4444; color: white; text-align: center; padding: 12px; font-weight: 800; font-size: 0.85rem; z-index: 10000; position: fixed; top: 0; left: 0; right: 0; display: flex; align-items: center; justify-content: center; gap: 20px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); backdrop-filter: blur(8px);">
        <span><i class="fas fa-user-secret"></i> DIAGNOSTIC MODE: VIEWING AS <?= htmlspecialchars($student['name']) ?></span>
        <a href="../impersonate.php?action=stop" style="background: white; color: #ef4444; padding: 4px 14px; border-radius: 8px; text-decoration: none; font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 700;">STOP IMPERSONATION</a>
    </div>
    <div style="height: 48px;"></div>
<?php endif; ?>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('dashSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
    
    if(sidebar.classList.contains('open')) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}

function toggleDesktopFocus() {
    document.body.classList.toggle('studio-focus');
}
</script>
