<?php
/**
 * Student: View & Download Certificate (Standalone, Full-Screen Diploma)
 * Fixed: correct include paths, proper auth check, operator precedence bug
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Must be logged in
if (!isLoggedIn()) {
    header('Location: /Skope Digital Academy/login.php');
    exit;
}

$currentUser = currentUser();

// Fix operator precedence bug — was: (int)$_GET['id'] ?? 0
$cid = (int)($_GET['id'] ?? 0);
if (!$cid) {
    header('Location: certificates.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT c.*,
               co.title    AS course_title,
               co.duration_hours,
               co.level    AS course_level,
               tu.name     AS tutor_name,
               s.name      AS student_name,
               iss.name    AS issued_by_name,
               c.issued_by_role,
               c.notes     AS cert_notes
        FROM   certificates c
        JOIN   courses co ON c.course_id  = co.id
        JOIN   users   tu ON co.tutor_id  = tu.id
        JOIN   users   s  ON c.student_id = s.id
        LEFT JOIN users iss ON c.issued_by = iss.id
        WHERE  c.id = ?
        LIMIT  1
    ");
    $stmt->execute([$cid]);
    $cert = $stmt->fetch();

    // 404 guard + ownership check (admin/tutor can view any; students only their own)
    if (!$cert) {
        header('Location: certificates.php');
        exit;
    }
    if ($currentUser['role'] === 'student' && $cert['student_id'] != $currentUser['id']) {
        header('Location: certificates.php');
        exit;
    }

    // Status guard
    if (($cert['status'] ?? 'approved') === 'revoked') {
        $revoked = true;
    } else {
        $revoked = false;
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: certificates.php');
    exit;
}

// Back-link differs by role
$backLink = match($currentUser['role']) {
    'admin'  => '/Skope Digital Academy/admin/certificates.php',
    'tutor'  => '/Skope Digital Academy/tutor/students.php',
    default  => '/Skope Digital Academy/student/certificates.php',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate – <?= htmlspecialchars($cert['course_title']) ?> | Skope Digital Academy</title>

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;800&family=Great+Vibes&family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" type="image/png" href="../assets/images/Skope Digital  logo.png">

<style>
/* ─── Reset & Base ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --gold:       #D4AF37;
    --gold-light: #F0D060;
    --blue:       #00AEEF;
    --orange:     #F7941D;
    --dark:       #0D1117;
    --ink:        #1c1c2e;
}

body {
    font-family: 'Inter', sans-serif;
    background: radial-gradient(ellipse at top, #1a1a2e 0%, #0d0d1a 60%, #000 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 40px 20px 120px;
    position: relative;
    overflow-x: hidden;
}

/* Ambient orbs */
body::before {
    content: '';
    position: fixed;
    top: -200px;
    left: -200px;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(0,174,239,0.06) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
body::after {
    content: '';
    position: fixed;
    bottom: -200px;
    right: -200px;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(247,148,29,0.05) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

/* ─── Top Nav Bar ──────────────────────────────────────────── */
.cert-nav {
    width: 100%;
    max-width: 1060px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 40px;
    gap: 16px;
    flex-wrap: wrap;
}
.nav-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: rgba(255,255,255,0.6);
    font-size: 0.88rem;
    font-weight: 600;
    transition: 0.2s;
}
.nav-brand:hover { color: #fff; }
.nav-brand i { font-size: 0.9rem; }

.nav-actions { display: flex; gap: 12px; align-items: center; }

/* ─── Buttons ──────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
}
.btn-primary {
    background: linear-gradient(135deg, #00AEEF, #0080c0);
    color: #fff;
    box-shadow: 0 4px 20px rgba(0,174,239,0.35);
}
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,174,239,0.45); }

.btn-gold {
    background: linear-gradient(135deg, var(--gold), var(--gold-light));
    color: #1a1000;
    box-shadow: 0 4px 20px rgba(212,175,55,0.4);
}
.btn-gold:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(212,175,55,0.5); }

.btn-ghost {
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.75);
    border: 1px solid rgba(255,255,255,0.14);
    backdrop-filter: blur(10px);
}
.btn-ghost:hover { background: rgba(255,255,255,0.14); color: #fff; }

/* ─── Revoked Banner ───────────────────────────────────────── */
.revoked-banner {
    width: 100%;
    max-width: 1060px;
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.4);
    color: #fca5a5;
    padding: 14px 24px;
    border-radius: 12px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* ─── Diploma Frame ────────────────────────────────────────── */
.diploma-outer {
    width: 100%;
    max-width: 1060px;
    position: relative;
    /* Glowing frame */
    border-radius: 4px;
    box-shadow:
        0  0   0  1px  rgba(212,175,55,0.6),
        0  0   0  6px  rgba(212,175,55,0.08),
        0  0  60px     rgba(212,175,55,0.15),
        0 40px 100px   rgba(0,0,0,0.9);
    animation: fadeSlideUp 0.7s ease both;
}

@keyframes fadeSlideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}

.diploma-wrap {
    background: #fff;
    position: relative;
    padding: 70px 80px 60px;
    overflow: hidden;
    aspect-ratio: 1.42 / 1;
}

/* Aged paper texture gradient */
.diploma-wrap::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at top left,    rgba(212,175,55,0.06) 0%, transparent 50%),
        radial-gradient(ellipse at bottom right, rgba(247,148,29,0.05) 0%, transparent 50%),
        linear-gradient(180deg, #fffef8, #fff 40%, #fffdf5);
    pointer-events: none;
    z-index: 0;
}

/* Watermark */
.diploma-wrap::after {
    content: 'SDA';
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    font-family: 'Cinzel', serif;
    font-size: 18rem;
    font-weight: 800;
    color: rgba(212,175,55,0.04);
    white-space: nowrap;
    z-index: 1;
    pointer-events: none;
    letter-spacing: 20px;
}

/* Gold border frame */
.diploma-border {
    position: absolute;
    inset: 16px;
    border: 1.5px solid rgba(212,175,55,0.55);
    z-index: 2;
    pointer-events: none;
}
.diploma-border-inner {
    position: absolute;
    inset: 22px;
    border: 0.5px solid rgba(212,175,55,0.25);
    z-index: 2;
    pointer-events: none;
}

/* Ornate corners */
.corner {
    position: absolute;
    width: 70px;
    height: 70px;
    z-index: 3;
    pointer-events: none;
}
.corner::before, .corner::after {
    content: '';
    position: absolute;
    background: var(--gold);
    opacity: 0.7;
}
.corner.tl { top: 16px; left: 16px; }
.corner.tr { top: 16px; right: 16px; transform: scaleX(-1); }
.corner.bl { bottom: 16px; left: 16px; transform: scaleY(-1); }
.corner.br { bottom: 16px; right: 16px; transform: scale(-1); }

.corner::before { width: 40px; height: 2px; top: 0; left: 0; }
.corner::after  { width: 2px; height: 40px; top: 0; left: 0; }

/* ─── Diploma Content ──────────────────────────────────────── */
.diploma-content {
    position: relative;
    z-index: 10;
    text-align: center;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
}

.dc-top { display: flex; flex-direction: column; align-items: center; gap: 6px; }

.dc-logo { height: 48px; object-fit: contain; filter: grayscale(1) brightness(0); margin-bottom: 8px; }

.dc-title {
    font-family: 'Cinzel', serif;
    font-size: clamp(1.4rem, 3vw, 2.6rem);
    font-weight: 700;
    color: var(--ink);
    text-transform: uppercase;
    letter-spacing: 6px;
    line-height: 1.1;
}
.dc-subtitle {
    font-size: clamp(0.55rem, 1.1vw, 0.82rem);
    text-transform: uppercase;
    letter-spacing: 4px;
    color: #888;
    margin-top: 6px;
    padding-bottom: 14px;
    border-bottom: 1px solid #e0e0e0;
}

.dc-mid { display: flex; flex-direction: column; align-items: center; flex: 1; justify-content: center; gap: 6px; }

.dc-certify {
    font-size: clamp(0.65rem, 1.2vw, 0.9rem);
    color: #999;
    text-transform: uppercase;
    letter-spacing: 3px;
}

.dc-name {
    font-family: 'Great Vibes', cursive;
    font-size: clamp(2rem, 5vw, 3.8rem);
    color: var(--orange);
    line-height: 1.15;
    text-shadow: 0 2px 8px rgba(247,148,29,0.15);
}

.dc-body {
    font-size: clamp(0.6rem, 1.1vw, 0.85rem);
    color: #555;
    line-height: 1.7;
    max-width: 600px;
    margin: 4px 0;
}

.dc-course {
    font-family: 'Cinzel', serif;
    font-size: clamp(0.75rem, 1.6vw, 1.3rem);
    font-weight: 600;
    color: var(--blue);
    letter-spacing: 1px;
    padding: 10px 28px;
    border: 1px solid rgba(0,174,239,0.25);
    border-radius: 4px;
    background: rgba(0,174,239,0.03);
    margin: 8px 0;
}

.dc-notes {
    font-size: clamp(0.55rem, 0.95vw, 0.78rem);
    color: var(--gold);
    font-style: italic;
    font-weight: 600;
    letter-spacing: 1px;
}

/* Footer: signatures + seal */
.dc-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    width: 100%;
    padding-top: 16px;
    border-top: 1px solid #eee;
}

.sign-block { text-align: center; min-width: 160px; }
.sign-script {
    font-family: 'Great Vibes', cursive;
    font-size: clamp(1rem, 2vw, 1.6rem);
    color: var(--ink);
    line-height: 1.2;
    margin-bottom: 6px;
}
.sign-rule { width: 160px; height: 1px; background: #ccc; margin: 0 auto 6px; }
.sign-name { font-size: clamp(0.6rem, 1vw, 0.78rem); font-weight: 700; color: var(--ink); }
.sign-role { font-size: clamp(0.5rem, 0.85vw, 0.65rem); color: #999; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 2px; }

.diploma-seal {
    width: clamp(70px, 10vw, 110px);
    height: clamp(70px, 10vw, 110px);
    border-radius: 50%;
    background: conic-gradient(from 0deg, #D4AF37, #F0D060, #D4AF37, #A08020, #D4AF37);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 0 4px #fff, 0 0 0 6px var(--gold), 0 8px 24px rgba(212,175,55,0.3);
    transform: rotate(-12deg);
    flex-shrink: 0;
}
.diploma-seal i {
    color: #fff;
    font-size: clamp(1.2rem, 2.5vw, 2rem);
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.3));
}

.dc-code {
    font-size: clamp(0.45rem, 0.75vw, 0.62rem);
    color: #bbb;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-top: auto;
    padding-top: 10px;
}

/* ─── Print Styles ─────────────────────────────────────────── */
@media print {
    body {
        background: white !important;
        padding: 0 !important;
    }
    body::before, body::after { display: none !important; }
    .cert-nav, .cert-actions, .revoked-banner { display: none !important; }
    .diploma-outer {
        box-shadow: none !important;
        max-width: 100% !important;
        border: none !important;
    }
    .diploma-wrap {
        border: none !important;
        padding: 50px !important;
    }
    page { size: A4 landscape; margin: 0; }
}

/* ─── Bottom Action Bar ────────────────────────────────────── */
.cert-actions {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 100;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(20px);
    border-top: 1px solid rgba(255,255,255,0.08);
    padding: 16px 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    flex-wrap: wrap;
}

/* ─── Responsive ───────────────────────────────────────────── */
@media (max-width: 768px) {
    .diploma-wrap { padding: 30px 24px; aspect-ratio: auto; min-height: 500px; }
    .dc-logo { height: 34px; }
    .cert-actions { padding: 14px 20px; }
    .btn { padding: 10px 16px; font-size: 0.82rem; }
}
</style>
</head>
<body>

<!-- ── Top Nav ──────────────────────────────────────────────── -->
<nav class="cert-nav">
    <a href="<?= $backLink ?>" class="nav-brand">
        <i class="fas fa-arrow-left"></i>
        <?= $currentUser['role'] === 'student' ? 'My Certificates' : 'Back' ?>
    </a>
    <div class="nav-actions">
        <img src="../assets/images/Skope Digital  logo.png" style="height:36px;filter:brightness(0) invert(1);opacity:0.6;" alt="SDA">
    </div>
</nav>

<?php if ($revoked): ?>
<div class="revoked-banner">
    <i class="fas fa-ban" style="font-size:1.2rem;"></i>
    <div>
        <strong>Certificate Revoked</strong> — This certificate has been revoked by the academy and is no longer valid.
    </div>
</div>
<?php endif; ?>

<!-- ── Diploma ──────────────────────────────────────────────── -->
<div class="diploma-outer" id="diploma">
    <div class="diploma-wrap">

        <!-- Decorative layers (z-index:0-3) -->
        <div class="diploma-border"></div>
        <div class="diploma-border-inner"></div>
        <div class="corner tl"></div>
        <div class="corner tr"></div>
        <div class="corner bl"></div>
        <div class="corner br"></div>

        <!-- Main Content -->
        <div class="diploma-content">

            <!-- Top: Logo + Title -->
            <div class="dc-top">
                <img src="../assets/images/Skope Digital  logo.png" class="dc-logo" alt="Skope Digital Academy" onerror="this.style.display='none'">
                <div class="dc-title">Certificate of Completion</div>
                <div class="dc-subtitle">Awarded by Skope Digital Academy &nbsp;•&nbsp; <?= ucfirst($cert['course_level'] ?? 'Professional') ?> Level</div>
            </div>

            <!-- Middle: Student + Course -->
            <div class="dc-mid">
                <div class="dc-certify">This is to certify that</div>
                <div class="dc-name"><?= htmlspecialchars($cert['student_name']) ?></div>
                <div class="dc-body">
                    has successfully completed the required curriculum and demonstrated<br>
                    exceptional performance in the professional course of study:
                </div>
                <div class="dc-course"><?= htmlspecialchars($cert['course_title']) ?></div>

                <?php if (!empty($cert['cert_notes'])): ?>
                <div class="dc-notes"><i class="fas fa-star" style="font-size:0.7em;margin-right:4px;"></i><?= htmlspecialchars($cert['cert_notes']) ?></div>
                <?php endif; ?>
            </div>

            <!-- Footer: Signatures + Seal -->
            <div class="dc-footer">
                <!-- Academy Signature -->
                <div class="sign-block">
                    <div class="sign-script">Academy Director</div>
                    <div class="sign-rule"></div>
                    <div class="sign-name">Skope Digital Academy</div>
                    <div class="sign-role">Director of Education</div>
                </div>

                <!-- Seal (centre) -->
                <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                    <div class="diploma-seal">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div style="font-family:'Cinzel',serif;font-size:clamp(0.45rem,0.7vw,0.58rem);color:var(--gold);text-align:center;letter-spacing:2px;text-transform:uppercase;opacity:0.8;">O F F I C I A L</div>
                </div>

                <!-- Instructor Signature -->
                <div class="sign-block">
                    <div class="sign-script"><?= htmlspecialchars($cert['tutor_name']) ?></div>
                    <div class="sign-rule"></div>
                    <div class="sign-name"><?= htmlspecialchars($cert['tutor_name']) ?></div>
                    <div class="sign-role">Course Instructor</div>
                </div>
            </div>

            <!-- Verification Code + Date -->
            <div class="dc-code">
                Verification Code: <strong><?= $cert['verification_code'] ?></strong>
                &nbsp;|&nbsp; Issued: <?= date('d / m / Y', strtotime($cert['issued_at'])) ?>
                <?php if (!empty($cert['issued_by_name'])): ?>
                &nbsp;|&nbsp; Certified by: <?= htmlspecialchars($cert['issued_by_name']) ?>
                <?php endif; ?>
                <?php if ($revoked): ?>
                &nbsp;|&nbsp; <span style="color:#EF4444;font-weight:700;">⚠ REVOKED</span>
                <?php endif; ?>
            </div>

        </div><!-- /.diploma-content -->
    </div><!-- /.diploma-wrap -->
</div><!-- /.diploma-outer -->

<!-- ── Fixed Bottom Action Bar ─────────────────────────────── -->
<div class="cert-actions" id="certActions">
    <button onclick="window.print()" class="btn btn-gold">
        <i class="fas fa-download"></i> Download / Print PDF
    </button>
    <a href="<?= $backLink ?>" class="btn btn-ghost">
        <i class="fas fa-th-large"></i>
        <?= $currentUser['role'] === 'student' ? 'All Certificates' : 'Back' ?>
    </a>
    <?php if ($currentUser['role'] === 'student'): ?>
    <a href="transcript.php" class="btn btn-ghost">
        <i class="fas fa-scroll"></i> View Transcript
    </a>
    <?php endif; ?>
</div>

<script>
// Hide action bar on print, restore after
window.addEventListener('beforeprint', function() {
    document.getElementById('certActions').style.display = 'none';
});
window.addEventListener('afterprint', function() {
    document.getElementById('certActions').style.display = 'flex';
});
</script>

</body>
</html>
