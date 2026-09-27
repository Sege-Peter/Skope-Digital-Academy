<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// If no session UID, someone got here incorrectly. Redirect to login.
if (empty($_SESSION['force_password_change_uid'])) {
    header("Location: login.php");
    exit;
}

$uid = (int)$_SESSION['force_password_change_uid'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in both fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            try {
                $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, requires_password_change = 0 WHERE id = ?");
                $stmt->execute([$hashed_pass, $uid]);

                // Destroy this temporary auth session and make them log in fresh with the new password
                unset($_SESSION['force_password_change_uid']);
                
                // Store a temp session message 
                $_SESSION['login_success'] = "Password updated successfully. Please log in.";
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
    <title>Secure Password Update – Skope</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
            color: #ef4444;
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
    </style>
</head>
<body>

    <div class="auth-card">
        <div class="icon-shield">
            <i class="fas fa-shield-alt"></i>
        </div>
        
        <h1>Account Security</h1>
        <p class="subtitle">For security reasons, your organization requires you to set a permanent, private password before accessing your dashboard.</p>

        <?php if ($error): ?>
            <div id="error-msg"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="force_password_change.php" method="POST" id="updateForm">
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

            <button type="submit" class="btn-update" id="updateBtn">Update & Finalize Setup</button>
        </form>
    </div>

    <script>
        document.getElementById('updateForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('updateBtn');
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Finalizing...';
            btn.style.opacity = '0.8';
        });
    </script>
</body>
</html>
