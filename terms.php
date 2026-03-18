<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service – Skope Digital Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        :root {
            --primary-cyan: #00BFFF;
            --secondary-orange: #FF8C00;
            --dark-deep: #0D1117;
            --white: #FFFFFF;
        }

        body { background: var(--white); }

        .terms-hero {
            padding: 120px 0 80px;
            background: var(--dark-deep);
            color: var(--white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        /* Decor */
        .terms-hero::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(0, 191, 255, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .terms-hero h1 { font-family: 'Poppins', sans-serif; font-size: 3.5rem; font-weight: 800; margin-bottom: 20px; }
        .terms-hero h1 span.cyan { color: var(--primary-cyan); }
        .terms-hero h1 span.orange { color: var(--secondary-orange); }
        
        .terms-content { padding: 90px 0; max-width: 950px; margin: 0 auto; line-height: 1.8; color: #334155; }
        .terms-section { margin-bottom: 60px; }
        
        .terms-section h2 { 
            font-family: 'Poppins', sans-serif; 
            font-size: 2rem; 
            font-weight: 800; 
            color: var(--dark-deep); 
            margin-bottom: 25px; 
            border-bottom: 3px solid #f1f5f9; 
            padding-bottom: 15px; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
        }
        .terms-section h2 i { 
            color: var(--primary-cyan); 
            font-size: 1.5rem; 
            background: rgba(0, 191, 255, 0.1);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        .terms-section:nth-child(even) h2 i {
            color: var(--secondary-orange);
            background: rgba(255, 140, 0, 0.1);
        }

        .terms-section p { margin-bottom: 25px; font-size: 1.1rem; }
        .terms-section strong { color: var(--dark-deep); font-weight: 800; }
        
        .terms-section ul { margin: 25px 0; padding-left: 0; }
        .terms-section li { 
            margin-bottom: 15px; 
            list-style: none; 
            padding-left: 35px; 
            position: relative;
            font-size: 1.05rem;
        }
        .terms-section li::before {
            content: "\f058";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            color: var(--primary-cyan);
        }
        .terms-section:nth-child(even) li::before {
            color: var(--secondary-orange);
        }

        .last-updated-pill {
            display: inline-block;
            background: rgba(255,255,255,0.05);
            padding: 8px 20px;
            border-radius: 100px;
            color: #94a3b8;
            font-size: 0.85rem;
            margin-top: 20px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .terms-hero h1 { font-size: 2.5rem; }
            .terms-section h2 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<div class="terms-hero">
    <div class="container">
        <h1>Terms of <span class="cyan">Service</span> <span class="orange">&</span> Rules</h1>
        <p style="opacity: 0.8; font-size: 1.25rem; max-width: 800px; margin: 0 auto; color: #cbd5e1;">Your definitive guide to academic partnership with Skope Digital Academy.</p>
        <span class="last-updated-pill">Effective: March 18, 2026</span>
    </div>
</div>

<div class="container">
    <div class="terms-content">
        <div class="terms-section">
            <h2><i class="fas fa-file-contract"></i> 1. Acceptance of Terms</h2>
            <p>By accessing the <strong>Skope Digital Academy</strong> portal, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service. This agreement constitutes a legally binding contract between you and the Academy.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-graduation-cap"></i> 2. Academic Conduct Registry</h2>
            <p>We believe in radical integrity. All students are subject to the following behavior protocols:</p>
            <ul>
                <li>Verification of identity for all certification tracks.</li>
                <li>Prohibition of AI-assisted cheating (unless explicitly permitted for development).</li>
                <li>Professional communication within all student forums and mentor circles.</li>
                <li>Exclusive use of provided curriculum for personal growth only.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-shield-alt"></i> 3. Proprietary IP Rights</h2>
            <p>The "Skope" brand, our proprietary AI models, video curriculum, and interactive lesson plans are protected under global intellectual property laws. Users are granted a <strong>single-user educational license</strong> which prohibits:</p>
            <ul>
                <li>Screen recording or content scraping.</li>
                <li>Unauthorized redistribution of course PDFs or datasets.</li>
                <li>Reselling access to the student dashboard.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-wallet"></i> 4. Financial Settlements</h2>
            <p>Tuition fees are calculated based on the selected mastery track. We provide transparent billing through nuestro secure financial gateway:</p>
            <ul>
                <li>Full payment or verified installment plans are required for course unlocking.</li>
                <li>Scholarship grants are subject to monthly performance audits.</li>
                <li>Withdrawal within 7 days is eligible for a full refund (conditions apply).</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-certificate"></i> 5. Digital Credentials</h2>
            <p>Certification is earned, not bought. Transcripts are issued only after the <strong>Skope AI Auditor</strong> confirms 100% course coverage and passing grades in all mandatory assessment gates.</p>
        </div>

        <div class="terms-section">
            <h2><i class="fas fa-balance-scale"></i> 6. Jurisdiction</h2>
            <p>Any disputes arising under this agreement shall be handled via arbitration in <strong>Kisumu, Kenya</strong>, under Kenyan educational regulations.</p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>
