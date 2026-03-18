<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Must be logged in to enroll
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = "enroll.php?id=" . ($_GET['id'] ?? 0);
    header('Location: login.php?msg=login_to_enroll');
    exit;
}

$user = currentUser();
$id = (int)$_GET['id'] ?? 0;

if (!$id) { header('Location: courses.php'); exit; }

try {
    // 1. Fetch Course Info
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND status = 'published'");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    
    if (!$c) { header('Location: courses.php'); exit; }

    // 2. Check if already enrolled or pending
    $stmt = $pdo->prepare("SELECT status FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$user['id'], $id]);
    $existing_enrollment = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT status FROM payments WHERE student_id = ? AND course_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user['id'], $id]);
    $payment_status = $stmt->fetchColumn();

    if ($existing_enrollment === 'active') {
        header('Location: student/classroom.php?id=' . $id);
        exit;
    }

    // 3. Handle Free Enrollment
    if ($c['price'] == 0) {
        $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active'");
        $stmt->execute([$user['id'], $id]);
        
        $stmt = $pdo->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        header('Location: student/classroom.php?id=' . $id . '&welcome=1');
        exit;
    }

} catch (Exception $e) { error_log($e->getMessage()); $c = []; }

$error = '';
$success = '';

// 4. Handle Paid Enrollment (Proof Upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    $amount = (float)$_POST['amount'];
    $transaction_message = trim($_POST['transaction_message'] ?? '');
    
    $file = $_FILES['payment_proof'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    
    if ($amount < $c['price']) {
        $error = "The amount entered (KES ".number_format($amount).") is less than the course price.";
    } elseif (!in_array($ext, $allowed)) {
        $error = "Only JPG, PNG and PDF files are allowed for proof of payment.";
    } elseif ($file['size'] > 5*1024*1024) {
        $error = "File size must be less than 5MB.";
    } else {
        try {
            $filename = "PROOF_" . $user['id'] . "_" . time() . "." . $ext;
            $upload_path = "uploads/proofs/" . $filename;
            
            if (!is_dir('uploads/proofs/')) { mkdir('uploads/proofs/', 0777, true); }
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $stmt = $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, proof_file, transaction_message, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                if ($stmt->execute([$user['id'], $id, $amount, $filename, $transaction_message])) {
                    // Update enrollment status to 'pending'
                    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'pending') ON DUPLICATE KEY UPDATE status = 'pending'");
                    $stmt->execute([$user['id'], $id]);
                    
                    $success = "Proof of payment uploaded successfully! Our admin team will verify it within 24 hours.";
                } else {
                    $error = "Failed to record payment details. Please try again.";
                }
            } else {
                $error = "Failed to upload file. Please check server permissions.";
            }
        } catch (Exception $e) { $error = "System error: " . $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enrollment – Skope Digital Academy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" type="image/png" href="Skope Digital  logo.png">
<style>
  .enroll-page { min-height: 100vh; background: var(--dark); padding: 80px 0; }
  .payment-method { border: 1px solid var(--dark-border); border-radius: 12px; padding: 24px; background: var(--dark-card2); margin-bottom: 24px; }
  .payment-method h3 { margin-bottom: 16px; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; color: var(--primary); }
  .payment-method p { font-size: 0.92rem; color: var(--text-muted); line-height: 1.6; }
  .amount-card { background: var(--primary-glow); border: 1px solid var(--primary); padding: 32px; border-radius: 16px; text-align: center; margin-bottom: 32px; }
  .amount-card h2 { color: var(--text-primary); margin-bottom: 8px; font-weight: 800; font-size: 2.2rem; }
  .amount-card p { color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; font-size: 0.82rem; }
</style>
</head>
<body>

<div class="enroll-page">
    <div class="container container-sm">
        <a href="course-details.php?id=<?= $id ?>" style="display:inline-flex; align-items:center; gap:8px; color:var(--text-muted); text-decoration:none; margin-bottom:32px;">
            <i class="fas fa-arrow-left"></i> Back to Course
        </a>

        <?php if($success): ?>
            <div class="card" style="text-align: center; padding: 60px;">
                <div class="success-icon" style="width: 80px; height: 80px; background: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2.5rem; margin: 0 auto 24px;">
                    <i class="fas fa-check"></i>
                </div>
                <h2 style="margin-bottom: 16px;">Payment Submitted!</h2>
                <p style="color: var(--text-muted); margin-bottom: 32px;"><?= $success ?></p>
                <a href="student/index.php" class="btn btn-primary btn-block">Go to Student Dashboard</a>
            </div>
        <?php elseif ($payment_status === 'pending'): ?>
            <div class="card" style="text-align: center; padding: 60px;">
                <div style="font-size: 4rem; color: var(--warning); margin-bottom: 24px;"><i class="fas fa-clock"></i></div>
                <h2 style="margin-bottom: 16px;">Already Pending</h2>
                <p style="color: var(--text-muted); margin-bottom: 32px;">You have already submitted a proof of payment for this course. Please wait for our admin team to verify it.</p>
                <a href="student/index.php" class="btn btn-primary btn-block">Go to My Dashboard</a>
            </div>
        <?php else: ?>
            <div class="card" style="padding: 40px;">
                <h1 style="margin-bottom: 32px; text-align: center;">Enrollment Checkout</h1>
                
                <div class="amount-card">
                    <p>Course Price</p>
                    <h2>KES <?= number_format($c['price']) ?></h2>
                    <div style="font-size: 0.88rem; color: var(--text-muted); margin-top: 10px;">For: <?= htmlspecialchars($c['title']) ?></div>
                </div>

                <div class="payment-method">
                    <h3><i class="fas fa-mobile-alt"></i> M-Pesa Payment Instruction</h3>
                    <p>1. Go to your M-Pesa menu or App.</p>
                    <p>2. Select <strong>Lipa Na M-Pesa</strong> > <strong>Paybill</strong> / <strong>Till</strong>.</p>
                    <p>3. Use Paybill Number: <strong>123456</strong> (or Till: <strong>987654</strong>).</p>
                    <p>4. Use Account Number: <strong>SKOPE-<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></strong>.</p>
                    <p>5. Enter amount exactly: <strong>KES <?= number_format($c['price']) ?></strong>.</p>
                </div>
                
                <div class="payment-method">
                    <h3><i class="fas fa-university"></i> Bank Transfer Instruction</h3>
                    <p>Bank: <strong>Equity Bank</strong> / <strong>KCB</strong></p>
                    <p>Account Name: <strong>Skope Digital Academy Ltd.</strong></p>
                    <p>Account Number: <strong>0123 4567 8901</strong></p>
                    <p>Reference: <strong><?= $user['name'] ?> - CID-<?= $id ?></strong></p>
                </div>

                <?php if($error): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            SDA.showToast(<?= json_encode($error) ?>, 'danger');
                        });
                    </script>
                <?php endif; ?>

                <form method="POST" action="enroll.php?id=<?= $id ?>" enctype="multipart/form-data" style="margin-top: 32px;">
                    <div class="form-group">
                        <label class="form-label">Amount Paid (KES)</label>
                        <input type="number" name="amount" class="form-control" placeholder="e.g. <?= $c['price'] ?>" required value="<?= $c['price'] ?>">
                        <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 6px;">Must match the course price.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Proof of Payment (Screenshot/PDF)</label>
                        <input type="file" name="payment_proof" class="form-control" required style="padding-top: 8px;">
                        <div style="font-size: 0.78rem; color: var(--text-dim); margin-top: 6px;">Upload a screenshot of M-Pesa message or Bank slip.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Additional Message (Optional)</label>
                        <textarea name="transaction_message" class="form-control" style="min-height: 80px;" placeholder="e.g. M-Pesa Code: RKJ123ABC"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: 24px;">Submit Proof of Payment <i class="fas fa-upload"></i></button>
                    <div style="text-align: center; color: var(--text-dim); font-size: 0.8rem; margin-top: 16px;">
                        Secure Payment System | Skope Digital Academy
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
