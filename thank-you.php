<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$type = $_GET['type'] ?? 'payment';
$ref = $_GET['ref'] ?? '';

$title = ($type === 'donation') ? "Thank You for Your Support!" : "Payment Successful!";
$message = ($type === 'donation') 
    ? "Your generous contribution helps us provide quality education and certifications to students who need it most. Together, we are building a brighter future."
    : "Your payment has been processed successfully. You now have full access to your resources.";

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
        .success-card { background: white; border-radius: 32px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); width: 100%; max-width: 600px; padding: 60px; text-align: center; border: 1px solid #e2e8f0; }
        .success-icon { width: 100px; height: 100px; background: #dcfce7; color: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 32px; }
        h1 { font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 2.2rem; color: #1e293b; margin-bottom: 20px; letter-spacing: -1px; }
        p { color: #64748b; font-size: 1.1rem; line-height: 1.7; margin-bottom: 40px; }
        .ref-badge { display: inline-block; background: #f1f5f9; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 40px; }
        .btn-home { background: #003274ff; color: white; border: none; padding: 18px 40px; border-radius: 16px; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.3s; text-decoration: none; display: inline-block; }
        .btn-home:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,50,116,0.2); }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon"><i class="fas fa-check-circle"></i></div>
        <h1><?= $title ?></h1>
        <p><?= $message ?></p>
        
        <?php if($ref): ?>
        <div class="ref-badge">Transaction Reference: <?= htmlspecialchars($ref) ?></div>
        <?php endif; ?>

        <div>
            <a href="index.php" class="btn-home">Return to Homepage</a>
        </div>
    </div>
</body>
</html>
