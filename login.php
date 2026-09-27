<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole($_SESSION['role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'suspended') {
                        $error = 'Your account has been suspended. Please contact support.';
                    } elseif ($user['status'] === 'pending') {
                        if ($user['role'] === 'student') {
                            loginUser($user);
                            header("Location: checkout.php?type=registration");
                            exit;
                        } else {
                            $error = 'Your tutor account is pending admin approval. Please check back later.';
                        }
                    } else {
                        if (!empty($user['requires_password_change'])) {
                            $_SESSION['force_password_change_uid'] = $user['id'];
                            header("Location: force_password_change.php");
                            exit;
                        }
                        // Streak Logic calculation
                        $today = date('Y-m-d');
                        $last_streak = $user['last_streak_date'] ?? null;
                        $streak_days = $user['streak_days'] ?? 0;
                        if ($last_streak !== $today) {
                            if ($last_streak === date('Y-m-d', strtotime('-1 day'))) {
                                $streak_days++; // Continues consecutive days
                            } else {
                                $streak_days = 1; // Resets if more than 1 day missed
                            }
                            $pdo->prepare("UPDATE users SET last_login = NOW(), streak_days = ?, last_streak_date = ? WHERE id = ?")->execute([$streak_days, $today, $user['id']]);
                        } else {
                            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        }
                        
                        loginUser($user);
                        redirectByRole($user['role']);
                    }
                } else {
                    $error = 'Incorrect email or password. Please try again.';
                }
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
    <title>Sign In – Skope Digital Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
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

        /* ══ LOGO ══ */
        .logo-wrapper {
            width: 100px;
            height: 100px;
            margin: 0 auto 40px;
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
        .logo-wrapper .logo-inner {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-wrapper img {
            width: 70%;
            height: 70%;
            object-fit: contain;
            filter: brightness(0) invert(1);
        }

        /* ══ INPUTS ══ */
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
        .input-group i:not(.toggle-password) {
            position: absolute;
            left: 25px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 1.1rem;
        }
        .toggle-password {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            cursor: pointer;
            transition: 0.3s;
            z-index: 10;
        }
        .toggle-password:hover { color: var(--primary-btn); }

        /* ══ BUTTON ══ */
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
            margin-top: 15px;
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

        .auth-links {
            margin-top: 30px;
            font-size: 0.9rem;
            color: var(--text-dim);
            font-weight: 600;
        }
        .auth-links a {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 700;
        }
        .auth-links a:hover {
            color: var(--primary-btn);
        }

        .back-home {
            margin-top: 25px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
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

        /* Error States */
        #error-overlay {
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #ef4444;
            font-weight: 700;
        }

        @media (max-width: 480px) {
            .neu-card { padding: 40px 25px; border-radius: 40px; }
        }
    </style>
</head>
<body>

    <div class="neu-card">
        <div class="logo-wrapper">
            <div class="logo-inner">
                <img src="assets/images/Skope Digital  logo.png" alt="Logo">
            </div>
        </div>

        <?php if ($error): ?>
            <div id="error-overlay" style="margin-bottom: 20px; font-size: 0.85rem; color: #ef4444; font-weight: 700;">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] === 'pw_updated'): ?>
            <div id="success-overlay" style="margin-bottom: 20px; font-size: 0.85rem; color: #059669; font-weight: 700; background: #dcfce7; border: 1px solid #bbf7d0; padding: 12px; border-radius: 12px;">
                <i class="fas fa-check-circle"></i> Password secured successfully. Please re-login.
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

            <div class="input-group">
                <i class="fas fa-user-circle"></i>
                <input type="email" name="email" class="input-neu" placeholder="Email (Username)" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" class="input-neu" placeholder="Password" required>
                <i class="fas fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
            </div>

            <button type="submit" class="btn-neu" id="loginBtn">Login</button>
        </form>

        <div class="auth-links">
            <a href="forgot-password.php">Forgot password?</a> 
            <span style="opacity: 0.5; margin: 0 5px;">or</span> 
            <a href="register.php">Sign Up</a>
        </div>

        <div class="back-home">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i> Back to Homepage
            </a>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authenticating...';
            btn.style.opacity = '0.8';
        });
    </script>
</body>
</html>
