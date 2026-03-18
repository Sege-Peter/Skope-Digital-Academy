<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scholarship_id'])) {
    $sid    = (int)$_POST['scholarship_id'];
    $sop    = trim($_POST['sop']);
    $background = trim($_POST['background']);
    
    // Check if already applied
    $stmt = $pdo->prepare("SELECT id FROM scholarship_applications WHERE user_id = ? AND scholarship_id = ?");
    $stmt->execute([$user['id'], $sid]);
    if ($stmt->fetch()) {
        header('Location: scholarships.php?msg=already_applied');
        exit;
    }

    $file = $_FILES['document'] ?? null;
    $filename = null;

    if ($file && $file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (in_array($ext, $allowed)) {
            $filename = "SCH_APP_" . $user['id'] . "_" . time() . "." . $ext;
            if (!is_dir('uploads/scholarships/')) { mkdir('uploads/scholarships/', 0777, true); }
            move_uploaded_file($file['tmp_name'], "uploads/scholarships/" . $filename);
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO scholarship_applications (scholarship_id, user_id, sop, academic_background, document_file) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$sid, $user['id'], $sop, $background, $filename])) {
            header('Location: scholarships.php?msg=success');
        } else {
            header('Location: scholarships.php?msg=error');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        header('Location: scholarships.php?msg=error');
    }
} else {
    header('Location: scholarships.php');
}
?>
