<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Please enter your email address.';
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Generate secure reset token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Invalidate previous tokens for this email (optional, or just insert new one)
                    $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                    // Store token
                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$email, $token, $expires_at]);

                    // Send Email
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/Skope Digital Academy/reset-password.php?token=" . $token;
                    
                    $subject = "Skope Digital Academy - Password Reset";
                    $message = "
                    <html>
                    <head><title>Password Reset Request</title></head>
                    <body style='font-family: Inter, sans-serif; background: #f8fafc; padding: 30px;'>
                        <div style='background: white; padding: 40px; border-radius: 12px; max-width: 600px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                            <h2 style='color: #0f172a;'>Password Reset Request</h2>
                            <p style='color: #475569; line-height: 1.6;'>Hello <strong>".htmlspecialchars($user['name'])."</strong>,</p>
                            <p style='color: #475569; line-height: 1.6;'>We received a request to reset your password. Click the secure link below to proceed:</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='$reset_link' style='background: #00BFFF; color: white; padding: 14px 28px; text-decoration: none; border-radius: 12px; font-weight: 800; font-family: Poppins, sans-serif;'>RESET PASSWORD</a>
                            </div>
                            <p style='color: #64748b; font-size: 13px;'>If you did not request a password reset, you can safely ignore this email.</p>
                            <p style='color: #64748b; font-size: 13px;'>This link will expire in 1 hour.</p>
                        </div>
                    </body>
                    </html>
                    ";

                    $headers = "MIME-Version: 1.0" . "\r\n";
                    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                    $headers .= "From: Skope Support <support@skopedigital.com>" . "\r\n";

                    @mail($email, $subject, $message, $headers);
                }

                // Show success message regardless of existence to prevent email scraping
                $success = 'If an account exists with that email, a password reset link has been sent.';
                
            } catch (Exception $e) {
                $error = 'Something went wrong. Please try again later.';
                error_log($e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – Skope Digital Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
    <style>
        :root {
            --base: #f1f3f6;
            --shadow-dark: #d1d9e6;
            --shadow-light: #ffffff;
            --primary-btn: #67b7d1;
            --primary-btn-hover: #5aa9c3;
            --text-main: #31456a;
            --text-dim: #64748b;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--base);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text-main);
        }

        .neu-card {
            width: 100%;
            max-width: 420px;
            background: var(--base);
            border-radius: 50px;
            padding: 50px 40px;
            box-shadow: 20px 20px 60px var(--shadow-dark), 
                       -20px -20px 60px var(--shadow-light);
            text-align: center;
        }

        .logo-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            background: var(--base);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 8px 8px 16px var(--shadow-dark), 
                       -8px -8px 16px var(--shadow-light);
            padding: 5px;
            border: 4px solid var(--base);
        }
        
        .logo-wrapper i {
            font-size: 2rem;
            color: var(--primary-btn);
        }

        h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        p.desc {
            font-size: 0.85rem;
            color: var(--text-dim);
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .input-group {
            position: relative;
            margin-bottom: 25px;
        }
        .input-neu {
            width: 100%;
            height: 60px;
            background: var(--base);
            border: none;
            outline: none;
            border-radius: 30px;
            padding: 0 50px 0 60px;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            box-shadow: inset 6px 6px 12px var(--shadow-dark), 
                       inset -6px -6px 12px var(--shadow-light);
            transition: 0.3s;
        }
        .input-neu:focus {
            box-shadow: inset 2px 2px 5px var(--shadow-dark), 
                       inset -2px -2px 5px var(--shadow-light);
        }
        .input-group i {
            position: absolute;
            left: 25px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 1.1rem;
        }

        .btn-neu {
            width: 100%;
            height: 60px;
            background: var(--primary-btn);
            color: #fff;
            border: none;
            border-radius: 30px;
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            margin-top: 5px;
            cursor: pointer;
            box-shadow: 6px 6px 12px var(--shadow-dark), 
                       -6px -6px 12px var(--shadow-light);
            transition: 0.3s;
        }
        .btn-neu:hover {
            background: var(--primary-btn-hover);
            transform: translateY(-2px);
            box-shadow: 8px 8px 16px var(--shadow-dark), 
                       -8px -8px 16px var(--shadow-light);
        }
        .btn-neu:active {
            transform: translateY(0);
            box-shadow: inset 4px 4px 8px rgba(0,0,0,0.1);
        }

        .back-home {
            margin-top: 30px;
            font-size: 0.85rem;
            font-weight: 800;
        }
        .back-home a {
            color: var(--text-dim);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.3s;
        }
        .back-home a:hover { color: var(--primary-btn); }

        #error-overlay { margin-bottom: 20px; font-size: 0.85rem; color: #ef4444; font-weight: 700; }
        #success-overlay { margin-bottom: 20px; font-size: 0.85rem; color: #10b981; font-weight: 700; background: #dcfce7; padding: 12px; border-radius: 12px; border: 1px solid #bbf7d0; }

        @media (max-width: 480px) {
            .neu-card { padding: 40px 25px; border-radius: 40px; }
        }
    </style>
</head>
<body>

    <div class="neu-card">
        <div class="logo-wrapper">
            <i class="fas fa-key"></i>
        </div>
        
        <h1>Forgot Password</h1>
        <p class="desc">Enter your registered email address below and we'll send you a secure link to reset your password.</p>

        <?php if ($error): ?>
            <div id="error-overlay">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div id="success-overlay">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php else: ?>
            <form action="forgot-password.php" method="POST" id="resetForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" class="input-neu" placeholder="Email Address" required>
                </div>

                <button type="submit" class="btn-neu" id="submitBtn">Send Reset Link</button>
            </form>
        <?php endif; ?>

        <div class="back-home">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function() {
                const btn = document.getElementById('submitBtn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                btn.style.opacity = '0.8';
            });
        }
    </script>
</body>
</html>
