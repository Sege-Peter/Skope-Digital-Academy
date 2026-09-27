<?php
/**
 * Paystack Webhook Handler
 */
require_once 'includes/db.php';
require_once 'includes/paystack.php';

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
            // Record the payment
            $stmt = $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at) VALUES (?, ?, ?, 'verified', ?, ?, 'course', NOW())");
            $stmt->execute([$user_id, $item_id, $amount, "Paystack Webhook Ref: " . $reference, $reference]);
            
            // Enroll the student
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active'");
            $stmt->execute([$user_id, $item_id]);
            
            // Update course enrollment count
            $stmt = $pdo->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?");
            $stmt->execute([$item_id]);
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
