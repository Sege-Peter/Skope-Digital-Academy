<?php
/**
 * Student: View & Download Certificate (Standalone, Full-Screen Diploma)
 * Fixed: correct include paths, proper auth check, operator precedence bug
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Must be logged in
if (!isLoggedIn()) {
    header('Location: /Skope Digital Academy/login');
    exit;
}

$currentUser = currentUser();

// Fix operator precedence bug — was: (int)$_GET['id'] ?? 0
$cid = (int)($_GET['id'] ?? 0);
if (!$cid) {
    header('Location: certificates');
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
        header('Location: certificates');
        exit;
    }
    if ($currentUser['role'] === 'student' && $cert['student_id'] != $currentUser['id']) {
        header('Location: certificates');
        exit;
    }

    // A certificate still waiting for its fee stays locked for the student: send them to the pay page
    // (same rule as the public /certificate page).
    if ($currentUser['role'] === 'student' && ($cert['status'] ?? '') === 'pending') {
        header('Location: ../certificate-pay?code=' . rawurlencode((string)$cert['verification_code']));
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
    header('Location: certificates');
    exit;
}

// Back-link differs by role
$backLink = match($currentUser['role']) {
    'admin'  => '/Skope Digital Academy/admin/certificates',
    'tutor'  => '/Skope Digital Academy/tutor/students',
    default  => '/Skope Digital Academy/student/certificates',
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
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;800&family=Great+Vibes&family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" type="image/png" href="../assets/images/Skope Digital  logo.png">

<style>
/* ─── Reset & Base (page around the certificate: student portal look) ─── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --gold:       #D4AF37;
    --gold-light: #F0D060;
    --blue:       #00AEEF;
    --orange:     #F7941D;
    --dark:       #0D1117;
    --ink:        #1c1c2e;

    /* portal tokens (student-ui.css) */
    --primary: #00A3E0;
    --primary-dark: #0077C2;
    --primary-soft: #E6F6FD;
    --bg-main: #F4F7FB;
    --surface: #FFFFFF;
    --text-main: #0F172A;
    --text-dim: #5B6B82;
    --border: #E5EAF1;
    --border-strong: #D5DDE8;
}

body {
    font-family: 'Plus Jakarta Sans', 'Inter', system-ui, sans-serif;
    background: var(--bg-main);
    color: var(--text-main);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 0 20px 48px;
    overflow-x: hidden;
}

/* ─── Toolbar ──────────────────────────────────────────────── */
.cert-nav {
    position: sticky;
    top: 0;
    z-index: 100;
    width: calc(100% + 40px);
    margin: 0 -20px 28px;
    padding: 12px 20px;
    background: rgba(244, 247, 251, .9);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: center;
}
.cert-nav-inner {
    width: 100%;
    max-width: 1060px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.cert-nav-title { min-width: 0; flex: 1; }
.cert-nav-title strong { display: block; font-size: 1rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cert-nav-title span { display: block; font-size: .78rem; color: var(--text-dim); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nav-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }

/* ─── Buttons (same as the portal's sp-btn) ────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 42px;
    padding: 0 18px;
    border-radius: 12px;
    border: 1px solid transparent;
    font-family: inherit;
    font-size: .88rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: background .15s, border-color .15s, color .15s;
}
.btn-primary { background: var(--primary); color: #fff; box-shadow: 0 1px 2px rgba(0,119,194,.25); }
.btn-primary:hover { background: var(--primary-dark); box-shadow: 0 4px 12px rgba(0,119,194,.25); }
.btn-ghost { background: var(--surface); color: var(--text-main); border-color: var(--border-strong); }
.btn-ghost:hover { border-color: var(--primary); color: var(--primary-dark); }
.btn-icon { width: 42px; padding: 0; }

/* ─── Status banners ───────────────────────────────────────── */
.revoked-banner {
    width: 100%;
    max-width: 1060px;
    background: #FDECEC;
    color: #9B1C1C;
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: .9rem;
}
.revoked-banner.warn { background: #FEF6E4; color: #8A5300; }
.cert-hint { width: 100%; max-width: 1060px; margin-top: 14px; font-size: .8rem; color: var(--text-dim); text-align: center; }

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
@page { size: A4 landscape; margin: 8mm; }
@media print {
    body {
        background: white !important;
        padding: 0 !important;
        display: block !important;
        min-height: 0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .cert-nav, .revoked-banner, .cert-hint { display: none !important; }
    .diploma-outer {
        box-shadow: none !important;
        max-width: 100% !important;
        border: none !important;
        animation: none !important;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .diploma-wrap {
        border: none !important;
        padding: 50px !important;
        aspect-ratio: auto !important;
        height: 193mm;
    }
}

/* ─── Responsive ───────────────────────────────────────────── */
@media (max-width: 768px) {
    .diploma-wrap { padding: 30px 24px; aspect-ratio: auto; min-height: 500px; }
    .dc-logo { height: 34px; }
    .btn { height: 38px; padding: 0 14px; font-size: .82rem; }
    .btn-icon { width: 38px; padding: 0; }
}
@media (max-width: 480px) {
    .btn .lbl { display: none; }
    .btn { padding: 0 12px; }
}
</style>
</head>
<body>

<!-- ── Toolbar ─────────────────────────────────────────────── -->
<nav class="cert-nav" id="certActions">
    <div class="cert-nav-inner">
        <a href="<?= htmlspecialchars($backLink) ?>" class="btn btn-ghost btn-icon" title="Back" aria-label="<?= $currentUser['role'] === 'student' ? 'Back to my certificates' : 'Back' ?>"><i class="fas fa-arrow-left"></i></a>
        <div class="cert-nav-title">
            <strong><?= htmlspecialchars($cert['course_title']) ?></strong>
            <span>Certificate &middot; code <?= htmlspecialchars((string)$cert['verification_code']) ?></span>
        </div>
        <div class="nav-actions">
            <?php if ($currentUser['role'] === 'student'): ?>
            <a href="transcript" class="btn btn-ghost"><i class="fas fa-file-lines"></i> <span class="lbl">Transcript</span></a>
            <?php endif; ?>
            <button type="button" onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> <span class="lbl">Print / save PDF</span></button>
        </div>
    </div>
</nav>

<?php if ($revoked): ?>
<div class="revoked-banner">
    <i class="fas fa-ban" style="font-size: 1.1rem; margin-top: 2px;"></i>
    <div>
        <strong>Certificate revoked.</strong> The academy has revoked this certificate and it is no longer valid.
    </div>
</div>
<?php elseif (($cert['status'] ?? '') === 'pending'): ?>
<div class="revoked-banner warn">
    <i class="fas fa-lock" style="font-size: 1.1rem; margin-top: 2px;"></i>
    <div>
        <strong>Not unlocked yet.</strong> The learner has not paid the certificate fee, so this certificate is still locked for them.
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
                <div class="dc-subtitle">Awarded by Skope Digital Academy &nbsp;•&nbsp; <?= htmlspecialchars(ucfirst($cert['course_level'] ?? 'Professional')) ?> Level</div>
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
                Verification Code: <strong><?= htmlspecialchars((string)$cert['verification_code']) ?></strong>
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

<p class="cert-hint"><i class="fas fa-circle-info"></i> Tip: in the print window, choose "Save as PDF" and landscape to download a copy.</p>

<script>
// Opened from the download button on the certificates page: open the print dialog straight away
if (new URLSearchParams(location.search).get('print') === '1') {
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
}
</script>

</body>
</html>
