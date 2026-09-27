<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';
$success = '';
$valid_token = false;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = "Invalid or missing password reset token.";
} else {
    try {
        // Find valid token
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $reset_record = $stmt->fetch();

        if ($reset_record) {
            $valid_token = true;
            $email = $reset_record['email'];
        } else {
            $error = "This password reset token is invalid or has expired. Please request a new one.";
        }
    } catch (Exception $e) {
        $error = "Database error verifying token.";
        error_log($e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (empty($new_pass) || empty($confirm_pass)) {
            $error = 'Please fill in all fields.';
        } elseif ($new_pass !== $confirm_pass) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_pass) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            try {
                // Update password
                $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE email = ?");
                $stmt->execute([$hashed_pass, $email]);

                // Delete token
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

                header("Location: login.php?msg=pw_updated");
                exit;
            } catch (Exception $e) {
                $error = 'Something went wrong updating your password.';
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
    <title>Set New Password – Skope Digital Academy</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
    <style>
        :root {
            --base: #f8fafc;
            --primary-btn: #0f172a;
            --primary-btn-hover: #1e293b;
            --text-main: #0f172a;
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

        .auth-card {
            width: 100%;
            max-width: 440px;
            background: white;
            border-radius: 24px;
            padding: 40px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.05);
            text-align: center;
        }

        .icon-shield {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #10b981;
        }

        h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        p.subtitle {
            font-size: 0.9rem;
            color: var(--text-dim);
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .input-neu {
            width: 100%;
            height: 54px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            outline: none;
            border-radius: 14px;
            padding: 0 45px 0 20px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            transition: 0.3s;
        }

        .input-neu:focus {
            background: white;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
        }

        .input-group i.fa-lock {
            position: absolute;
            right: 18px;
            top: 42px;
            color: #94a3b8;
        }

        .btn-update {
            width: 100%;
            height: 56px;
            background: var(--primary-btn);
            color: white;
            border: none;
            border-radius: 14px;
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1rem;
            margin-top: 10px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-update:hover {
            background: var(--primary-btn-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.15);
        }

        #error-msg {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }

        .back-home { margin-top: 25px; font-size: 0.85rem; font-weight: 800; }
        .back-home a { color: var(--text-dim); text-decoration: none; transition: 0.3s; }
        .back-home a:hover { color: #0284c7; }
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="icon-shield">
            <i class="fas fa-unlock-alt"></i>
        </div>
        
        <h1>Set New Password</h1>
        
        <?php if ($error): ?>
            <div id="error-msg"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
            <?php if (!$valid_token): ?>
                <div class="back-home">
                    <a href="forgot-password.php">Request new link <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($valid_token): ?>
            <p class="subtitle">Securely enter the new password you want to use for your account.</p>
            <form action="reset-password.php?token=<?= htmlspecialchars($token) ?>" method="POST" id="resetForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <div class="input-group">
                    <label>New Private Password</label>
                    <input type="password" name="new_password" class="input-neu" placeholder="Enter at least 8 characters" required>
                    <i class="fas fa-lock"></i>
                </div>

                <div class="input-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="input-neu" placeholder="Re-enter password" required>
                    <i class="fas fa-lock"></i>
                </div>

                <button type="submit" class="btn-update" id="submitBtn">Save New Password</button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const btn = document.getElementById('submitBtn');
                btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving...';
                btn.style.opacity = '0.8';
            });
        }
    </script>
</body>
</html>
