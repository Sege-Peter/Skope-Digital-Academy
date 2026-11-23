<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/paystack.php';
require_once 'includes/learning.php';
require_once 'includes/coupons.php';

if (!isLoggedIn()) { header('Location: login'); exit; }

$user = currentUser();
$type = $_GET['type'] ?? 'course';
$id = (int)($_GET['id'] ?? 0);

$title = "Payment";
$amount = 0;
$desc = "";
$mode = '';
$cohort_id = 0;

if ($type === 'course') {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND status = 'published'");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    if (!$course) { header('Location: courses'); exit; }
    [$mode, $cohort, $err] = resolve_mode_choice($pdo, $course, $_GET['mode'] ?? null, $_GET['cohort'] ?? null);
    if ($err) { header('Location: enroll?id=' . $id); exit; }
    $cohort_id = (int)$cohort;
    $existing = get_enrollment($pdo, (int)$user['id'], $id);
    $amount = amount_due($course, $mode, $existing);
    $title = "Payment for " . $course['title'];
    $desc = mode_label($mode) . " – " . $course['title'];
} elseif ($type === 'certificate') {
    require_once 'includes/progress.php';
    $st = $pdo->prepare("SELECT c.*, co.title AS course_title FROM certificates c LEFT JOIN courses co ON co.id = c.course_id WHERE c.id = ? AND c.student_id = ?");
    $st->execute([$id, $user['id']]);
    $cert = $st->fetch();
    if (!$cert) { header('Location: student/achievements'); exit; }
    if ($cert['status'] !== 'pending') { header('Location: certificate?code=' . rawurlencode($cert['verification_code'])); exit; }
    $amount = (float)$cert['fee'];
    $title = "Unlock your certificate";
    $desc = "Certificate for " . $cert['course_title'];
} elseif ($type === 'registration') {
    $title = "Registration Fee";
    $amount = (float)setting('registration_fee');
    $desc = "Account activation fee for " . $user['name'];
} else {
    die("Invalid payment type");
}

// Coupon (course enrolments and certificate fees): re-validated here and again when the payment is verified.
$coupon_code = coupon_normalize($_GET['coupon'] ?? '');
$coupon = null;
$full_amount = $amount;
if ($coupon_code !== '' && in_array($type, ['course', 'certificate'], true) && $amount > 0) {
    $cc = coupon_check($pdo, $coupon_code, (int)$user['id'], $type,
                       $type === 'course' ? $id : ($cert['course_id'] ? (int)$cert['course_id'] : null), $amount);
    $back = $type === 'course'
        ? "enroll?id=$id&mode=" . urlencode($mode) . "&cohort=$cohort_id&coupon=" . urlencode($coupon_code)
        : 'certificate-pay?code=' . rawurlencode($cert['verification_code']) . '&coupon=' . urlencode($coupon_code);
    // Invalid, or the coupon covers everything: confirm on the page that owns the free path (POST + CSRF).
    if (!$cc['ok'] || $cc['final'] <= 0) { header('Location: ' . $back); exit; }
    $coupon = $cc;
    $amount = $cc['final'];
}

if ($amount <= 0) {
    if ($type === 'certificate') {
        unlock_certificate($pdo, (int)$cert['id'], 0);
        header('Location: certificate?code=' . rawurlencode($cert['verification_code']));
    } elseif ($type === 'course') {
        // Free or already covered: confirm on the enrollment page (POST + CSRF), never on a GET.
        header("Location: enroll?id=$id&mode=" . urlencode($mode) . "&cohort=$cohort_id");
    } else {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$user['id']]);
        header("Location: student/");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?> – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Checkout', 'path' => 'checkout', 'noindex' => true]); ?>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .checkout-card { background: white; border-radius: 32px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); width: 100%; max-width: 500px; padding: 48px; text-align: center; border: 1px solid #e2e8f0; }
        .icon-circle { width: 80px; height: 80px; background: var(--primary-glow); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 2rem; margin: 0 auto 32px; }
        h1 { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.75rem; color: #1e293b; margin-bottom: 8px; }
        .price { font-size: 3rem; font-weight: 900; color: #1e293b; margin: 24px 0; font-family: 'Poppins', sans-serif; }
        .price span { font-size: 1rem; color: #64748b; font-weight: 600; }
        .desc { color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 40px; }
        .btn-pay { background: #09a5db; color: white; border: none; padding: 18px 40px; border-radius: 16px; font-weight: 800; font-size: 1.1rem; width: 100%; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 12px; }
        .btn-pay:hover { background: #088dbb; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(9,165,219,0.2); }
        .footer-note { margin-top: 32px; font-size: 0.8rem; color: #94a3b8; font-weight: 600; }
    </style>
</head>
<body>
    <div class="checkout-card">
        <div class="icon-circle">
            <i class="fas <?= $type === 'course' ? 'fa-graduation-cap' : 'fa-user-check' ?>"></i>
        </div>
        <h1><?= e($title) ?></h1>
        <p class="desc"><?= e($desc) ?></p>
        
        <div class="price"><span><?= e(setting('currency')) ?></span> <?= number_format($amount, 2) ?></div>
        <?php if ($coupon): ?>
        <p class="desc" style="margin:-12px 0 28px;color:#065f46;font-weight:600"><i class="fas fa-ticket"></i> Coupon <?= e($coupon['coupon']['code']) ?> applied: <s style="color:#94a3b8"><?= e(money($full_amount)) ?></s> → <?= e(money($amount)) ?> (you save <?= e(money($coupon['discount'])) ?>)</p>
        <?php endif; ?>

        <button id="pay-button" class="btn-pay">
            Pay with Paystack <i class="fas fa-arrow-right"></i>
        </button>
        
        <div class="footer-note">
            <i class="fas fa-lock"></i> Secured by Paystack Checkout
        </div>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        const payButton = document.getElementById('pay-button');
        const pay = <?= json_encode([
            'key' => PAYSTACK_PUBLIC_KEY,
            'email' => $user['email'],
            'amount' => (int)round($amount * 100),
            'currency' => setting('currency'),
            'type' => $type,
            'id' => $id,
            'mode' => $mode,
            'cohort' => $cohort_id,
            'coupon' => $coupon ? $coupon['coupon']['code'] : '',
        ]) ?>;
        payButton.addEventListener('click', function(e) {
            e.preventDefault();
            let handler = PaystackPop.setup({
                key: pay.key,
                email: pay.email,
                amount: pay.amount,
                currency: pay.currency,
                ref: 'SDA-' + Date.now() + '-' + Math.floor(Math.random() * 1e6),
                metadata: {
                    payment_type: pay.type, item_id: pay.id, learning_mode: pay.mode, cohort_id: pay.cohort, coupon: pay.coupon,
                    custom_fields: [
                        { display_name: "Payment Type", variable_name: "payment_type", value: pay.type },
                        { display_name: "Item ID", variable_name: "item_id", value: String(pay.id) },
                        { display_name: "Learning track", variable_name: "learning_mode", value: pay.mode }
                    ]
                },
                callback: function(response) {
                    const q = new URLSearchParams({ reference: response.reference, type: pay.type, id: pay.id, mode: pay.mode, cohort: pay.cohort });
                    if (pay.coupon) q.set('coupon', pay.coupon);
                    window.location.href = "verify-payment?" + q.toString();
                }
            });
            handler.openIframe();
        });
    </script>
</body>
</html>
