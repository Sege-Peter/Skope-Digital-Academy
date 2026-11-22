<?php
/**
 * Paystack Webhook Handler
 */
require_once 'includes/db.php';
require_once 'includes/paystack.php';
require_once 'includes/learning.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit;

// Retrieve the request's body and parse it as JSON
$input = @file_get_contents("php://input");
$event = json_decode($input);

// Validate the signature
if (!isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) || ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY))) {
    exit;
}

http_response_code(200);

// Check if the event is 'charge.success'
if ($event->event === 'charge.success') {
    $reference = $event->data->reference;
    $amount = $event->data->amount / 100;
    $email = $event->data->customer->email;
    $metadata = $event->data->metadata;
    
    // Find the user by email
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user_id = $stmt->fetchColumn();
    
    if ($user_id) {
        $type = $metadata->payment_type ?? 'course';
        $item_id = $metadata->item_id ?? 0;
        
        // Check if already processed
        $stmt = $pdo->prepare("SELECT id FROM payments WHERE paystack_reference = ?");
        $stmt->execute([$reference]);
        if ($stmt->fetch()) exit;
        
        $pdo->beginTransaction();
        
        if ($type === 'course' && $item_id) {
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt->execute([(int)$item_id]);
            $course = $stmt->fetch();
            if ($course) {
                [$mode, $cohort_id] = resolve_mode_choice($pdo, $course, $metadata->learning_mode ?? null, $metadata->cohort_id ?? null);
                $due = amount_due($course, $mode, get_enrollment($pdo, (int)$user_id, (int)$item_id));
                $ok = $amount + 0.001 >= $due;
                $stmt = $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at, learning_mode, cohort_id) VALUES (?, ?, ?, ?, ?, ?, 'course', IF(? = 'verified', NOW(), NULL), ?, ?)");
                $status = $ok ? 'verified' : 'pending';
                $stmt->execute([$user_id, $item_id, $amount, $status, "Paystack Webhook Ref: " . $reference . ($ok ? '' : " – paid $amount, due $due; needs review"), $reference, $status, $mode, $cohort_id]);
                if ($ok) enroll_student($pdo, (int)$user_id, $course, $mode, $cohort_id, $amount);
            }
        } elseif ($type === 'certificate' && $item_id) {
            require_once 'includes/progress.php';
            $cs = $pdo->prepare("SELECT * FROM certificates WHERE id = ? AND student_id = ?");
            $cs->execute([(int)$item_id, $user_id]);
            if ($cert = $cs->fetch()) {
                $ok = $amount + 0.001 >= (float)$cert['fee'];
                $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, paystack_reference, payment_type, certificate_id, verified_at) VALUES (?, ?, ?, ?, ?, ?, 'certificate', ?, IF(? = 'verified', NOW(), NULL))")
                    ->execute([$user_id, $cert['course_id'], $amount, $ok ? 'verified' : 'pending', "Certificate fee – Paystack Webhook Ref: " . $reference, $reference, $cert['id'], $ok ? 'verified' : 'pending']);
                if ($ok) unlock_certificate($pdo, (int)$cert['id'], $amount);
            }
        } elseif ($type === 'registration') {
            // Record the payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at) VALUES (?, ?, 'verified', ?, ?, 'registration', NOW())");
            $stmt->execute([$user_id, $amount, "Registration Fee Ref: " . $reference, $reference]);
            
            // Activate the user
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$user_id]);
        } elseif ($type === 'donation') {
            // Record the donation
            $msg = "Donation from " . ($email);
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at) VALUES (?, ?, 'verified', ?, ?, 'donation', NOW())");
            $stmt->execute([$user_id, $amount, $msg, $reference, $reference]);
        }
        
        $pdo->commit();
    }
}
?>
