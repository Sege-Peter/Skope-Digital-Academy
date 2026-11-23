<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$user = isLoggedIn() ? currentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Careers – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Careers', 'path' => 'careers', 'description' => 'Work or teach with Skope Digital Academy. See open roles and how to become a tutor for learners across Kenya and East Africa.', 'breadcrumbs' => [['Careers', 'careers']]]); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<style>
    body { background: white; }
    .careers-hero { background: var(--bg-light); padding: 100px 0; border-bottom: 1px solid var(--dark-border); text-align: center; }
    .careers-hero h1 { font-family: 'Poppins', sans-serif; font-size: 3.5rem; margin-bottom: 24px; color: var(--dark); }
    .careers-hero p { font-size: 1.25rem; color: var(--text-muted); max-width: 700px; margin: 0 auto; line-height: 1.6; }

    .job-card { background: white; border: 1px solid var(--dark-border); border-radius: 16px; padding: 32px; transition: 0.3s; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
    .job-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: var(--shadow); }
    .job-title { font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
    .job-meta { font-size: 0.88rem; color: var(--text-dim); display: flex; gap: 20px; }
    
    .culture-section { padding: 100px 0; background: white; }
    .culture-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; }
    .culture-card { text-align: center; }
    @media (max-width: 768px) { .culture-grid { grid-template-columns: 1fr; gap: 32px; } .culture-section { padding: 64px 0; } }
    @media (max-width: 700px) {
        .careers-hero { padding: 64px 0; }
        .careers-hero h1 { font-size: clamp(2rem, 9vw, 2.6rem); }
        .careers-hero p { font-size: 1.05rem; }
        .job-card { flex-direction: column; align-items: flex-start; gap: 18px; padding: 22px; }
        .job-card .btn { width: 100%; justify-content: center; }
        .job-meta { flex-wrap: wrap; gap: 8px 16px; }
    }
    .culture-icon { font-size: 2.5rem; color: var(--secondary); margin-bottom: 24px; }
</style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<div class="careers-hero">
    <div class="container">
        <h1>Join the <span class="text-primary">Mastery</span> Team</h1>
        <p>Help us redefine professional growth for millions of learners globally. At Skope Digital Academy, we're building the future of AI-powered education.</p>
        <div style="margin-top: 40px;">
            <a href="#openings" class="btn btn-primary btn-lg">View Open Roles</a>
        </div>
    </div>
</div>

<section class="culture-section">
    <div class="container">
        <h2 style="font-family: 'Poppins', sans-serif; text-align: center; font-size: 2.5rem; margin-bottom: 60px;">Life at <span class="text-secondary">Skope</span></h2>
        <div class="culture-grid">
            <div class="culture-card">
                <div class="culture-icon"><i class="fas fa-magic"></i></div>
                <h3 style="margin-bottom: 16px;">Innovation First</h3>
                <p style="color: var(--text-muted); line-height: 1.8;">We constantly push the boundaries of what's possible with AI and educational technology.</p>
            </div>
            <div class="culture-card">
                <div class="culture-icon"><i class="fas fa-globe-africa"></i></div>
                <h3 style="margin-bottom: 16px;">Global Impact</h3>
                <p style="color: var(--text-muted); line-height: 1.8;">Your work directly influences the career trajectories of thousands across the African continent and beyond.</p>
            </div>
            <div class="culture-card">
                <div class="culture-icon"><i class="fas fa-users-viewfinder"></i></div>
                <h3 style="margin-bottom: 16px;">Inclusion & Growth</h3>
                <p style="color: var(--text-muted); line-height: 1.8;">We foster an environment where diverse perspectives drive our shared objective of academic excellence.</p>
            </div>
        </div>
    </div>
</section>

<section class="section" style="background: var(--bg-light);" id="openings">
    <div class="container">
        <div style="text-align: center; margin-bottom: 60px;">
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 2.5rem;">Current <span class="text-primary">Opportunities</span></h2>
            <p style="color: var(--text-muted); margin-top: 12px;">Be part of our mission to democratize elite skills training.</p>
        </div>

        <div style="max-width: 900px; margin: 0 auto;">
            <div class="job-card">
                <div>
                    <h3 class="job-title">Senior Learning Experience Designer</h3>
                    <div class="job-meta">
                        <span><i class="fas fa-map-marker-alt"></i> Remote (Global)</span>
                        <span><i class="fas fa-briefcase"></i> Full-Time</span>
                        <span><i class="fas fa-tag"></i> Academic Team</span>
                    </div>
                </div>
                <a href="mailto:info@skopedigital.co.ke?subject=Application%3A%20Senior%20Learning%20Experience%20Designer" class="btn btn-ghost">Apply Now</a>
            </div>

            <div class="job-card">
                <div>
                    <h3 class="job-title">AI Content Strategy Lead</h3>
                    <div class="job-meta">
                        <span><i class="fas fa-map-marker-alt"></i> Nairobi, Kenya</span>
                        <span><i class="fas fa-briefcase"></i> Full-Time</span>
                        <span><i class="fas fa-robot"></i> Technology</span>
                    </div>
                </div>
                <a href="mailto:info@skopedigital.co.ke?subject=Application%3A%20AI%20Content%20Strategy%20Lead" class="btn btn-ghost">Apply Now</a>
            </div>

            <div class="job-card">
                <div>
                    <h3 class="job-title">Student Success Coordinator</h3>
                    <div class="job-meta">
                        <span><i class="fas fa-map-marker-alt"></i> Remote</span>
                        <span><i class="fas fa-briefcase"></i> Contract</span>
                        <span><i class="fas fa-user-graduate"></i> Operations</span>
                    </div>
                </div>
                <a href="mailto:info@skopedigital.co.ke?subject=Application%3A%20Student%20Success%20Coordinator" class="btn btn-ghost">Apply Now</a>
            </div>

            <div style="text-align: center; margin-top: 60px;">
                <p style="color: var(--text-dim);">Don't see a role that fits? Send your CV to <a href="mailto:info@skopedigital.co.ke" style="color: var(--primary); font-weight: 700;">info@skopedigital.co.ke</a></p>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>
