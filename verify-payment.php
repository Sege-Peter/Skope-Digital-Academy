<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/paystack.php';

// No strict isLoggedIn() check here to allow guest donations and handle verification if session is lost

$reference = $_GET['reference'] ?? null;
$type      = $_GET['type'] ?? 'course';
$item_id   = (int)($_GET['id'] ?? $_GET['course_id'] ?? 0);

if (!$reference || (!$item_id && $type !== 'donation')) {
    header('Location: index.php?msg=invalid_payment');
    exit;
}

try {
    $user = isLoggedIn() ? currentUser() : null;
    $donor_email = $_GET['email'] ?? ($user ? $user['email'] : '');
    
    // Verify with Paystack
    $result = paystack_verify_transaction($reference);
    
    if ($result && isset($result['data']) && $result['data']['status'] === 'success') {
        $amount = $result['data']['amount'] / 100;
        
        $pdo->beginTransaction();
        
        if ($type === 'course') {
            // 1. Record the payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at) VALUES (?, ?, ?, 'verified', ?, ?, 'course', NOW())");
            $stmt->execute([$user['id'], $item_id, $amount, "Paystack Ref: " . $reference, $reference]);
            
            // 2. Enroll the student
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active'");
            $stmt->execute([$user['id'], $item_id]);
            
            // 3. Update course enrollment count
            $stmt = $pdo->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?");
            $stmt->execute([$item_id]);
            
            $pdo->commit();
            header("Location: student/classroom.php?id=$item_id&welcome=1");
        } elseif ($type === 'registration') {
            // 1. Record the payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at) VALUES (?, ?, 'verified', ?, ?, 'registration', NOW())");
            $stmt->execute([$user['id'], $amount, "Registration Fee Ref: " . $reference, $reference]);
            
            // 2. Activate the user
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            $pdo->commit();
            header("Location: student/index.php?msg=account_activated");
        } elseif ($type === 'donation') {
            // Record the donation
            // If logged in, associate with student_id, otherwise use email in transaction_message
            $uid = $user ? $user['id'] : null;
            $msg = "Donation from " . ($user ? $user['name'] : $donor_email);
            
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at) VALUES (?, ?, 'verified', ?, ?, 'donation', NOW())");
            $stmt->execute([$uid, $amount, $msg, $reference, $reference]);
            
            $pdo->commit();
            header("Location: thank-you.php?type=donation&ref=$reference");
        }
        exit;
    } else {
        $red = "index.php";
        if($type === 'course') $red = "enroll.php?id=$item_id";
        if($type === 'registration') $red = "register.php";
        if($type === 'donation') $red = "donate.php";
        
        header("Location: $red&msg=payment_failed");
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header("Location: index.php?msg=system_error");
    exit;
}
?>
