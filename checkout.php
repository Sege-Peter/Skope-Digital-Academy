<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/paystack.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$user = currentUser();
$type = $_GET['type'] ?? 'course';
$id = (int)($_GET['id'] ?? 0);

$title = "Payment";
$amount = 0;
$desc = "";

if ($type === 'course') {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    if (!$course) { die("Course not found"); }
    $title = "Payment for " . $course['title'];
    $amount = $course['price'];
    $desc = "Enrollment fee for " . $course['title'];
} elseif ($type === 'registration') {
    $title = "Registration Fee";
    $amount = 10; // Hardcoded default registration fee for now
    $desc = "Account activation fee for " . $user['name'];
} else {
    die("Invalid payment type");
}

if ($amount <= 0) {
    // If free, handle enrollment/activation immediately
    if ($type === 'course') {
        $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE status = 'active'")->execute([$user['id'], $id]);
        header("Location: student/classroom.php?id=$id");
    } else {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$user['id']]);
        header("Location: student/index.php");
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
        <h1><?= $title ?></h1>
        <p class="desc"><?= $desc ?></p>
        
        <div class="price"><span>KES</span> <?= number_format($amount) ?></div>
        
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
        payButton.addEventListener('click', function(e) {
            e.preventDefault();
            
            let handler = PaystackPop.setup({
                key: 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                email: '<?= $user['email'] ?>',
                amount: <?= $amount * 100 ?>,
                currency: 'KES',
                ref: 'SDA-' + Math.floor((Math.random() * 1000000000) + 1),
                metadata: {
                    custom_fields: [
                        { display_name: "Payment Type", variable_name: "payment_type", value: "<?= $type ?>" },
                        { display_name: "Item ID", variable_name: "item_id", value: "<?= $id ?>" }
                    ]
                },
                callback: function(response) {
                    window.location.href = "verify-payment.php?reference=" + response.reference + "&type=<?= $type ?>&id=<?= $id ?>";
                }
            });
            handler.openIframe();
        });
    </script>
</body>
</html>
