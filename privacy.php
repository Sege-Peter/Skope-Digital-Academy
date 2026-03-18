<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy – Skope Digital Academy</title>
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

        .policy-hero {
            padding: 120px 0 80px;
            background: var(--dark-deep);
            color: var(--white);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        /* Decor */
        .policy-hero::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(0, 191, 255, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .policy-hero h1 { font-family: 'Poppins', sans-serif; font-size: 3.5rem; font-weight: 800; margin-bottom: 20px; }
        .policy-hero h1 span.cyan { color: var(--primary-cyan); }
        .policy-hero h1 span.orange { color: var(--secondary-orange); }
        
        .policy-content { padding: 90px 0; max-width: 950px; margin: 0 auto; line-height: 1.8; color: #334155; }
        .policy-section { margin-bottom: 60px; }
        
        .policy-section h2 { 
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
        .policy-section h2 i { 
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
        .policy-section:nth-child(even) h2 i {
            color: var(--secondary-orange);
            background: rgba(255, 140, 0, 0.1);
        }

        .policy-section p { margin-bottom: 25px; font-size: 1.1rem; }
        .policy-section strong { color: var(--dark-deep); font-weight: 800; }
        
        .policy-section ul { margin: 25px 0; padding-left: 0; }
        .policy-section li { 
            margin-bottom: 15px; 
            list-style: none; 
            padding-left: 35px; 
            position: relative;
            font-size: 1.05rem;
        }
        .policy-section li::before {
            content: "\f058";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            color: var(--primary-cyan);
        }
        .policy-section:nth-child(even) li::before {
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

        /* Contacts Box */
        .contact-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 35px;
            margin-top: 40px;
            text-align: center;
        }
    </style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<div class="policy-hero">
    <div class="container">
        <h1>Privacy <span class="cyan">Policy</span> <span class="orange">&</span> Ethics</h1>
        <p style="opacity: 0.8; font-size: 1.25rem; max-width: 800px; margin: 0 auto; color: #cbd5e1;">How we secure your path to digital excellence and safeguard your trust.</p>
        <span class="last-updated-pill">Updated: March 18, 2026</span>
    </div>
</div>

<div class="container">
    <div class="policy-content">
        <div class="policy-section">
            <h2><i class="fas fa-user-shield"></i> 1. Introduction</h2>
            <p>At <strong>Skope Digital Academy</strong>, we prioritize the protection of our academic community's information. This Privacy Policy outlines our standards for the collection, encryption, and responsible processing of student and instructor data.</p>
        </div>

        <div class="policy-section">
            <h2><i class="fas fa-server"></i> 2. Data Collection Protocols</h2>
            <p>To provide a high-end, AI-powered educational experience, we collect only the information essential for your academic success:</p>
            <ul>
                <li><strong>Identity Metrics:</strong> Name, contact emails, and professional experience for certification.</li>
                <li><strong>Learning Logs:</strong> Progress tracking through our AI-curated mastery tracks.</li>
                <li><strong>Interaction Data:</strong> Queries provided to our AI Mentors to improve instructional accuracy.</li>
                <li><strong>Secured Billings:</strong> Transaction signatures and verification logs for tuition handling.</li>
            </ul>
        </div>

        <div class="policy-section">
            <h2><i class="fas fa-brain"></i> 3. AI Processing & Personalization</h2>
            <p>Our <strong>Skope AI Core</strong> processes performance data to personalize your curriculum. We ensure that this data remains siloed within your unique learning environment, never shared externally for advertising or non-academic use.</p>
        </div>

        <div class="policy-section">
            <h2><i class="fas fa-lock"></i> 4. Digital Security Infrastructure</h2>
            <p>We leverage industry-leading encryption benchmarks (SSL/TLS v1.3) and secure database architectures to ensure your academic records and payment trails remain confidential and tamper-proof.</p>
        </div>

        <div class="policy-section">
            <h2><i class="fas fa-envelope-open-text"></i> 5. Data Governance Team</h2>
            <p>Questions regarding your digital footprint? Our specialized compliance department is available to assist you in managing your privacy rights.</p>
            
            <div class="contact-box">
                <div style="font-weight: 800; color: var(--dark-deep); margin-bottom: 10px;">Academy Compliance Desk</div>
                <div style="color: var(--primary-cyan); font-weight: 800; font-size: 1.2rem;">info@skopedigital.ac.ke</div>
                <p style="font-size: 0.9rem; color: #64748b; margin-top: 10px;">Kisumu Main Campus, Kenya</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>
