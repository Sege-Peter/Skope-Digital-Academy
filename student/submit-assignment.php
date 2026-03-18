<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) { header('Location: index.php'); exit; }

$student = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $aid = (int)$_POST['assignment_id'];
    $notes = trim($_POST['notes']);
    
    $file = $_FILES['submission_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'zip', 'rar', 'jpg', 'png'];
    
    if ($file['error'] !== 0) {
        header('Location: assignments.php?msg=upload_error');
        exit;
    }

    if (!in_array($ext, $allowed)) {
        header('Location: assignments.php?msg=invalid_file_type');
        exit;
    }

    $filename = "SUB_" . $student['id'] . "_" . $aid . "_" . time() . "." . $ext;
    $upload_path = "../uploads/assignments/" . $filename;
    
    if (!is_dir('../uploads/assignments/')) { mkdir('../uploads/assignments/', 0777, true); }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_url, notes, status) VALUES (?, ?, ?, ?, 'pending') ON DUPLICATE KEY UPDATE file_url=?, notes=?, status='pending'");
            $stmt->execute([$aid, $student['id'], $filename, $notes, $filename, $notes]);
            
            // Notify tutor
            $stmt = $pdo->prepare("SELECT tutor_id FROM courses WHERE id = (SELECT course_id FROM assignments WHERE id = ?)");
            $stmt->execute([$aid]);
            $tutor_id = $stmt->fetchColumn();
            
            if ($tutor_id) {
                $stmt = $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, 'tutor', ?)");
                $stmt->execute(["New Assignment Submission", "A student has submitted work for one of your assignments.", $tutor_id]);
            }
            
            header('Location: assignments.php?msg=success');
        } catch (Exception $e) { error_log($e->getMessage()); header('Location: assignments.php?msg=db_error'); }
    } else {
        header('Location: assignments.php?msg=upload_failed');
    }
} else {
    header('Location: assignments.php');
}
?>
