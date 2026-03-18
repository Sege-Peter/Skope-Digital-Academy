<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$user = isLoggedIn() ? currentUser() : null;

// Handle form submission if needed (simplified for styling task)
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us – Skope Digital Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        :root {
            --base: #f1f3f6;
            --shadow-dark: #d1d9e6;
            --shadow-light: #ffffff;
            --cyan: #00BFFF;
            --orange: #FF8C00;
            --dark: #0D1117;
            --text-main: #31456a;
            --text-dim: #64748b;
        }

        body { background: var(--base); color: var(--text-main); font-family: 'Inter', sans-serif; overflow-x: hidden; }

        /* ══ HERO SECTION ══ */
        .contact-hero {
            padding: 140px 0 100px;
            background: var(--dark);
            color: #fff;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .contact-hero::before {
            content: ''; position: absolute; top: -100px; right: -100px;
            width: 400px; height: 400px; border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 191, 255, 0.1) 0%, transparent 70%);
        }
        .contact-hero h1 { 
            font-family: 'Poppins', sans-serif; 
            font-size: clamp(2.5rem, 6vw, 4rem); 
            font-weight: 900; 
            margin-bottom: 20px;
            color: #ffffff;
        }
        .contact-hero h1 span.cyan { color: var(--cyan); }
        .contact-hero h1 span.orange { color: var(--orange); }
        .contact-hero p { 
            font-size: 1.25rem; 
            color: #94a3b8; 
            max-width: 700px; 
            margin: 0 auto; 
            line-height: 1.6;
        }

        /* ══ MAIN GRID ══ */
        .contact-main { padding: 100px 0; }
        .contact-grid {
            display: grid;
            grid-template-columns: 0.8fr 1.2fr;
            gap: 60px;
            align-items: start;
        }

        /* ══ INFO CARDS (Left) ══ */
        .info-card-container { display: grid; gap: 30px; }
        .neu-info-card {
            background: var(--base);
            padding: 30px;
            border-radius: 30px;
            box-shadow: 10px 10px 20px var(--shadow-dark), 
                       -10px -10px 20px var(--shadow-light);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: 0.3s;
        }
        .neu-info-card:hover { transform: translateY(-5px); }
        .info-icon {
            width: 60px; height: 60px;
            background: var(--base);
            border-radius: 20px;
            box-shadow: 5px 5px 10px var(--shadow-dark), 
                       -5px -5px 10px var(--shadow-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: var(--cyan);
        }
        .neu-info-card:nth-child(even) .info-icon { color: var(--orange); }
        .info-content .label { display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: var(--text-dim); letter-spacing: 1px; margin-bottom: 5px; }
        .info-content .value { font-size: 1.1rem; font-weight: 700; color: var(--text-main); }

        /* ══ FORM (Right) ══ */
        .neu-form-container {
            background: var(--base);
            padding: 50px;
            border-radius: 50px;
            box-shadow: 20px 20px 40px var(--shadow-dark), 
                       -20px -20px 40px var(--shadow-light);
        }
        .form-heading { margin-bottom: 40px; }
        .form-heading h2 { font-family: 'Poppins', sans-serif; font-size: 2rem; font-weight: 800; margin-bottom: 10px; }
        .form-heading div { width: 60px; height: 5px; background: var(--cyan); border-radius: 10px; }

        .input-neu-group { margin-bottom: 25px; }
        .input-neu-group label { display: block; font-weight: 800; font-size: 0.85rem; color: var(--text-main); margin-bottom: 12px; padding-left: 20px; }
        .input-neu {
            width: 100%;
            background: var(--base);
            border: none;
            outline: none;
            border-radius: 20px;
            padding: 18px 25px;
            font-size: 1rem;
            color: var(--text-main);
            box-shadow: inset 6px 6px 12px var(--shadow-dark), 
                       inset -6px -6px 12px var(--shadow-light);
            transition: 0.3s;
        }
        .input-neu:focus {
            box-shadow: inset 2px 2px 5px var(--shadow-dark), 
                       inset -2px -2px 5px var(--shadow-light);
        }
        textarea.input-neu { border-radius: 30px; resize: none; }

        .btn-neu-send {
            width: 100%;
            height: 60px;
            background: var(--cyan);
            color: #fff;
            border: none;
            border-radius: 30px;
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 1.1rem;
            cursor: pointer;
            box-shadow: 6px 6px 15px var(--shadow-dark), 
                       -6px -6px 15px var(--shadow-light);
            transition: 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 12px;
        }
        .btn-neu-send:hover {
            background: #009ACD; transform: translateY(-2px);
            box-shadow: 8px 8px 18px var(--shadow-dark), 
                       -8px -8px 18px var(--shadow-light);
        }
        .btn-neu-send:active { transform: translateY(0); box-shadow: inset 4px 4px 8px rgba(0,0,0,0.1); }

        /* ══ MAP ══ */
        .neu-map-wrapper {
            margin-top: 100px;
            padding: 20px;
            background: var(--base);
            border-radius: 50px;
            box-shadow: 15px 15px 30px var(--shadow-dark), 
                       -15px -15px 30px var(--shadow-light);
            overflow: hidden;
            height: 500px;
        }
        .neu-map-wrapper iframe {
            width: 100%; height: 100%; border-radius: 35px; border: none;
            filter: grayscale(1) invert(0.9) hue-rotate(180deg) brightness(1.2); /* Tech look */
        }

        @media (max-width: 968px) {
            .contact-grid { grid-template-columns: 1fr; }
            .neu-form-container { padding: 35px 25px; border-radius: 40px; }
        }
    </style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<!-- Hero -->
<section class="contact-hero">
    <div class="container">
        <h1>Let's <span class="cyan">Connect</span> & <span class="orange">Learn</span></h1>
        <p>Expert support for your digital career journey. Reach out to the Academy for technical, academic, or corporate training partnerships.</p>
    </div>
</section>

<!-- Main Main -->
<main class="contact-main">
    <div class="container">
        <div class="contact-grid">
            
            <!-- Left Side: Info -->
            <div class="info-card-container">
                <div class="neu-info-card">
                    <div class="info-icon"><i class="fas fa-phone-alt"></i></div>
                    <div class="info-content">
                        <span class="label">Admissions Hotline</span>
                        <span class="value">0742380183</span>
                    </div>
                </div>

                <div class="neu-info-card">
                    <div class="info-icon"><i class="fas fa-envelope"></i></div>
                    <div class="info-content">
                        <span class="label">Official Inquiry</span>
                        <span class="value">info@skopedigital.ac.ke</span>
                    </div>
                </div>

                <div class="neu-info-card">
                    <div class="info-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="info-content">
                        <span class="label">Main Campus</span>
                        <span class="value">Kisumu, Kenya</span>
                    </div>
                </div>

                <!-- Socials -->
                <div class="neu-info-card" style="padding: 40px 30px; flex-direction: column; align-items: flex-start; gap: 25px;">
                    <span class="label" style="margin: 0;">Follow Academy Updates</span>
                    <div style="display: flex; gap: 20px;">
                        <a href="#" style="width: 45px; height: 45px; border-radius: 12px; background: var(--base); box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light); display: flex; align-items: center; justify-content: center; color: var(--cyan); font-size: 1.2rem; transition: 0.3s;"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" style="width: 45px; height: 45px; border-radius: 12px; background: var(--base); box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light); display: flex; align-items: center; justify-content: center; color: var(--orange); font-size: 1.2rem; transition: 0.3s;"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" style="width: 45px; height: 45px; border-radius: 12px; background: var(--base); box-shadow: 4px 4px 8px var(--shadow-dark), -4px -4px 8px var(--shadow-light); display: flex; align-items: center; justify-content: center; color: var(--cyan); font-size: 1.2rem; transition: 0.3s;"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>

            <!-- Right Side: Form -->
            <div class="neu-form-container">
                <div class="form-heading">
                    <h2>Send a Message</h2>
                    <div></div>
                </div>

                <form action="contact-handler.php" method="POST" id="contactForm">
                    <div class="input-neu-group">
                        <label>Full Name</label>
                        <input type="text" name="name" class="input-neu" placeholder="e.g., Mzalendo" required>
                    </div>

                    <div class="input-neu-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="input-neu" placeholder="example@email.com" required>
                    </div>

                    <div class="input-neu-group">
                        <label>Nature of Inquiry</label>
                        <select name="subject" class="input-neu" style="appearance: none;" required>
                            <option value="" disabled selected>Select a topic</option>
                            <option value="Course Inquiry">Course Inquiry</option>
                            <option value="Technical Support">Technical Support</option>
                            <option value="Scholarship Q&A">Scholarship Q&A</option>
                            <option value="Partnerships">Partnerships</option>
                        </select>
                    </div>

                    <div class="input-neu-group">
                        <label>Detailed Message</label>
                        <textarea name="message" class="input-neu" rows="5" placeholder="How can we help you achieve your goals?" required></textarea>
                    </div>

                    <button type="submit" class="btn-neu-send" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Initiate Contact
                    </button>
                </form>
            </div>

        </div>

        <!-- Map -->
        <div class="neu-map-wrapper">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15959.349607831!2d34.7679!3d-0.1022!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x182aa489439f0447%3A0xe5449ed7ec0364!2sKisumu!5e0!3m2!1sen!2ske!4v1710432000000!5m2!1sen!2ske" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>

<script>
    document.getElementById('contactForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Dispatching...';
        btn.disabled = true;
    });
</script>

</body>
</html>
