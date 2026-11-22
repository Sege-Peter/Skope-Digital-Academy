<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/paystack.php';
require_once 'includes/learning.php';

// No strict isLoggedIn() check here to allow guest donations and handle verification if session is lost

$reference = $_GET['reference'] ?? null;
$type      = $_GET['type'] ?? 'course';
$item_id   = (int)($_GET['id'] ?? $_GET['course_id'] ?? 0);

if (!$reference || (!$item_id && $type !== 'donation')) {
    header('Location: ./?msg=invalid_payment');
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
        
        // A Paystack reference can only ever be credited once.
        $dup = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE paystack_reference = ?");
        $dup->execute([$reference]);
        if ($dup->fetchColumn() > 0) {
            $pdo->rollBack();
            header($type === 'course' ? "Location: student/classroom?id=$item_id" : "Location: ./");
            exit;
        }

        if ($type === 'course') {
            if (!$user) { $pdo->rollBack(); header('Location: login'); exit; }
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->execute([$item_id]);
            $course = $stmt->fetch();
            if (!$course) throw new RuntimeException("Paid for unknown course $item_id (ref $reference)");
            [$mode, $cohort_id] = resolve_mode_choice($pdo, $course, $_GET['mode'] ?? null, $_GET['cohort'] ?? null);
            $due = amount_due($course, $mode, get_enrollment($pdo, (int)$user['id'], $item_id));
            $currency_ok = strtoupper($result['data']['currency'] ?? setting('currency')) === strtoupper(setting('currency'));
            $ok = $currency_ok && $amount + 0.001 >= $due;

            // 1. Record the payment (underpaid / wrong currency → pending for an admin to review)
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at, learning_mode, cohort_id) VALUES (?, ?, ?, ?, ?, ?, 'course', IF(? = 'verified', NOW(), NULL), ?, ?)");
            $status = $ok ? 'verified' : 'pending';
            $stmt->execute([$user['id'], $item_id, $amount, $status,
                            "Paystack Ref: " . $reference . ($ok ? '' : " – paid $amount, due $due; needs review"),
                            $reference, $status, $mode, $cohort_id]);
            if (!$ok) {
                $pdo->commit();
                header("Location: enroll?id=$item_id");
                exit;
            }

            // 2. Enroll the student on the chosen track
            enroll_student($pdo, (int)$user['id'], $course, $mode, $cohort_id, $amount);

            $pdo->commit();
            header("Location: student/classroom?id=$item_id&welcome=1");
        } elseif ($type === 'certificate') {
            if (!$user) { $pdo->rollBack(); header('Location: login'); exit; }
            require_once 'includes/progress.php';
            $st = $pdo->prepare("SELECT * FROM certificates WHERE id = ? AND student_id = ?");
            $st->execute([$item_id, $user['id']]);
            $cert = $st->fetch();
            if (!$cert) throw new RuntimeException("Certificate payment for unknown certificate $item_id (ref $reference)");
            $currency_ok = strtoupper($result['data']['currency'] ?? setting('currency')) === strtoupper(setting('currency'));
            $ok = $currency_ok && $amount + 0.001 >= (float)$cert['fee'];
            $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, paystack_reference, payment_type, certificate_id, verified_at)
                           VALUES (?, ?, ?, ?, ?, ?, 'certificate', ?, IF(? = 'verified', NOW(), NULL))")
                ->execute([$user['id'], $cert['course_id'], $amount, $ok ? 'verified' : 'pending',
                           "Certificate fee – Paystack Ref: " . $reference . ($ok ? '' : " – paid $amount, fee {$cert['fee']}; needs review"),
                           $reference, $cert['id'], $ok ? 'verified' : 'pending']);
            if ($ok) unlock_certificate($pdo, (int)$cert['id'], $amount);
            $pdo->commit();
            header('Location: ' . ($ok ? 'certificate?code=' : 'certificate-pay?code=') . rawurlencode($cert['verification_code']));
        } elseif ($type === 'registration') {
            // 1. Record the payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at) VALUES (?, ?, 'verified', ?, ?, 'registration', NOW())");
            $stmt->execute([$user['id'], $amount, "Registration Fee Ref: " . $reference, $reference]);
            
            // 2. Activate the user
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            $pdo->commit();
            header("Location: student/?msg=account_activated");
        } elseif ($type === 'donation') {
            // Record the donation
            // If logged in, associate with student_id, otherwise use email in transaction_message
            $uid = $user ? $user['id'] : null;
            $msg = "Donation from " . ($user ? $user['name'] : $donor_email);
            
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at) VALUES (?, ?, 'verified', ?, ?, 'donation', NOW())");
            $stmt->execute([$uid, $amount, $msg, $reference, $reference]);
            
            $pdo->commit();
            header("Location: thank-you?type=donation&ref=$reference");
        }
        exit;
    } else {
        $red = "./";
        if($type === 'course') $red = "enroll?id=$item_id";
        if($type === 'registration') $red = "register";
        if($type === 'donation') $red = "donate";
        
        header("Location: $red&msg=payment_failed");
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    header("Location: ./?msg=system_error");
    exit;
}
?>
