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
        $error = 'Invalid security token.';
    } else {
        $name           = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $password       = $_POST['password'] ?? '';
        $confirm        = $_POST['confirm_password'] ?? '';
        $role           = $_POST['role'] ?? 'student'; // student or tutor
        $terms          = isset($_POST['terms']);

        // Basic validation
        if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!$terms) {
            $error = 'You must agree to the Terms and Conditions.';
        } elseif (!in_array($role, ['student', 'tutor'])) {
            $error = 'Invalid role selected.';
        } else {
            try {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'This email is already registered.';
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $status = ($role === 'tutor') ? 'pending' : 'active';
                    
                    // Handle Referral
                    $referred_by = null;
                    $ref_code = $_GET['ref'] ?? $_POST['ref_code'] ?? null;
                    if ($ref_code) {
                        $refStmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                        $refStmt->execute([$ref_code]);
                        $referred_by = $refStmt->fetchColumn() ?: null;
                    }

                    $new_ref_code = 'SDA' . rand(100, 999) . strtoupper(substr(md5(uniqid()), 0, 8));
                    
                    $insert = $pdo->prepare("INSERT INTO users (name, email, password, role, status, email_verified, referral_code, referred_by) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
                    if ($insert->execute([$name, $email, $hashed, $role, $status, $new_ref_code, $referred_by])) {
                        $new_user_id = $pdo->lastInsertId();
                        
                        if ($role === 'student') {
                            $adm_number = 'SDA/' . date('Y') . '/' . str_pad($new_user_id, 4, '0', STR_PAD_LEFT);
                            $pdo->prepare("UPDATE users SET admission_number = ? WHERE id = ?")->execute([$adm_number, $new_user_id]);
                        }

                        if ($role === 'tutor') {
                            $success = 'Registration successful! Your tutor account is pending admin approval.';
                        } else {
                            $success = 'Account created successfully! You can now log in.';
                        }
                    } else {
                        $error = 'Failed to create account. Please try again.';
                    }
                }
            } catch (Exception $e) {
                $error = 'A system error occurred: ' . $e->getMessage();
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
    <title>Sign Up – Skope Digital Academy</title>
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
            padding: 40px 20px;
            color: var(--text-main);
        }

        .neu-card {
            width: 100%;
            max-width: 500px;
            background: var(--base);
            border-radius: 50px;
            padding: 50px 40px;
            box-shadow: 20px 20px 60px var(--shadow-dark), 
                       -20px -20px 60px var(--shadow-light);
            text-align: center;
        }

        /* ══ LOGO ══ */
        .logo-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            background: var(--base);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 6px 6px 12px var(--shadow-dark), 
                       -6px -6px 12px var(--shadow-light);
            padding: 4px;
            border: 3px solid var(--base);
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

        /* ══ ROLE PICKER ══ */
        .role-picker { display: flex; gap: 15px; margin-bottom: 25px; }
        .role-opt {
            flex: 1;
            height: 54px;
            background: var(--base);
            border-radius: 27px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            box-shadow: 4px 4px 8px var(--shadow-dark), 
                       -4px -4px 8px var(--shadow-light);
            transition: 0.3s;
            position: relative;
        }
        .role-opt.active {
            box-shadow: inset 4px 4px 8px var(--shadow-dark), 
                       inset -4px -4px 8px var(--shadow-light);
            color: var(--primary-btn);
        }
        .role-opt input { position: absolute; opacity: 0; }

        /* ══ INPUTS ══ */
        .input-group { position: relative; margin-bottom: 20px; }
        .input-neu {
            width: 100%;
            height: 56px;
            background: var(--base);
            border: none;
            outline: none;
            border-radius: 28px;
            padding: 0 45px 0 55px;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            box-shadow: inset 5px 5px 10px var(--shadow-dark), 
                       inset -5px -5px 10px var(--shadow-light);
            transition: 0.3s;
        }
        .input-neu:focus {
            box-shadow: inset 2px 2px 4px var(--shadow-dark), 
                       inset -2px -2px 4px var(--shadow-light);
        }
        .input-group i {
            position: absolute;
            left: 22px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-dim);
            font-size: 1rem;
        }

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
            margin-top: 10px;
            cursor: pointer;
            box-shadow: 6px 6px 12px var(--shadow-dark), 
                       -6px -6px 12px var(--shadow-light);
            transition: 0.3s;
        }
        .btn-neu:hover {
            background: var(--primary-btn-hover);
            transform: translateY(-2px);
        }
        .btn-neu:active {
            transform: translateY(0);
            box-shadow: inset 4px 4px 8px rgba(0,0,0,0.1);
        }

        .terms-check {
            margin: 20px 0;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--text-dim);
            font-weight: 600;
        }
        .terms-check input { accent-color: var(--primary-btn); width: 18px; height: 18px; }
        .terms-check a { color: var(--text-main); font-weight: 700; }

        .auth-links { margin-top: 30px; font-size: 0.9rem; color: var(--text-dim); font-weight: 600; }
        .auth-links a { color: var(--text-main); font-weight: 700; text-decoration: none; }
        
        .back-home { margin-top: 25px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .back-home a { color: var(--text-dim); text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
        .back-home a:hover { color: var(--primary-btn); }

        .success-overlay {
            background: #fff;
            padding: 30px;
            border-radius: 30px;
            box-shadow: 10px 10px 20px var(--shadow-dark), -10px -10px 20px var(--shadow-light);
        }

        @media (max-width: 480px) {
            .neu-card { padding: 40px 25px; border-radius: 40px; }
            .role-picker { flex-direction: column; }
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

        <?php if ($success): ?>
            <div class="success-overlay">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: #10b981; margin-bottom: 20px;"></i>
                <h2 style="font-size: 1.5rem; margin-bottom: 10px;">Registration Successful!</h2>
                <p style="color: var(--text-dim); margin-bottom: 25px;"><?= htmlspecialchars($success) ?></p>
                <a href="login.php" class="btn-neu" style="display: flex; align-items: center; justify-content: center; text-decoration: none;">Go to Login</a>
            </div>
        <?php else: ?>
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem; margin-bottom: 30px;">Create Account</h1>

            <?php if ($error): ?>
                <div style="margin-bottom: 20px; font-size: 0.85rem; color: #ef4444; font-weight: 700;">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" id="regForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="ref_code" value="<?= htmlspecialchars($_GET['ref'] ?? '') ?>">

                <div class="role-picker">
                    <label class="role-opt active" id="student-btn">
                        <input type="radio" name="role" value="student" checked onchange="updateRoleUI('student')">
                        <i class="fas fa-user-graduate"></i> Student
                    </label>
                    <label class="role-opt" id="tutor-btn">
                        <input type="radio" name="role" value="tutor" onchange="updateRoleUI('tutor')">
                        <i class="fas fa-chalkboard-teacher"></i> Tutor
                    </label>
                </div>

                <div class="input-group">
                    <i class="fas fa-signature"></i>
                    <input type="text" name="full_name" class="input-neu" placeholder="Mzalendo (Full Name)" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>

                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" class="input-neu" placeholder="Official Email Address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="input-group">
                        <i class="fas fa-lock" style="left: 18px;"></i>
                        <input type="password" name="password" class="input-neu" placeholder="Password" style="padding-left: 45px;" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-shield-alt" style="left: 18px;"></i>
                        <input type="password" name="confirm_password" class="input-neu" placeholder="Confirm" style="padding-left: 45px;" required>
                    </div>
                </div>

                <label class="terms-check">
                    <input type="checkbox" name="terms" required>
                    <span>Accept <a href="terms.php">Terms</a> & <a href="privacy.php">Privacy</a></span>
                </label>

                <button type="submit" class="btn-neu" id="regBtn">Sign Up Now</button>
            </form>

            <div class="auth-links">
                Already member? <a href="login.php">Sign In</a>
            </div>

            <div class="back-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Homepage
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        function updateRoleUI(role) {
            document.getElementById('student-btn').classList.toggle('active', role === 'student');
            document.getElementById('tutor-btn').classList.toggle('active', role === 'tutor');
            document.getElementById('regBtn').textContent = role === 'student' ? 'Sign Up Now' : 'Join as Tutor';
        }

        document.getElementById('regForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('regBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.style.opacity = '0.8';
        });
    </script>
</body>
</html>
