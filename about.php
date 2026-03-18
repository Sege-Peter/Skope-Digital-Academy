<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Fetch live stats from DB
$BASE_STUDENTS = 2400; $BASE_COURSES = 118; $BASE_TUTORS = 80;
try {
    $total_students = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn() + $BASE_STUDENTS;
    $total_courses  = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn() + $BASE_COURSES;
    $total_tutors   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='tutor' AND status='active'")->fetchColumn() + $BASE_TUTORS;
} catch(Exception $e) {
    $total_students = $BASE_STUDENTS;
    $total_courses  = $BASE_COURSES;
    $total_tutors   = $BASE_TUTORS;
}
$user = isLoggedIn() ? currentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Learn about Skope Digital Academy – Kenya's #1 AI-powered learning platform. Our mission, vision, and the team behind Africa's leading ed-tech hub.">
<title>About Us – Skope Digital Academy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
<style>
/* ── Base ── */
*, *::before, *::after { box-sizing: border-box; }
body { background: #fff; color: #1e293b; font-family: 'Inter', sans-serif; }

/* ── Navbar override ── */
.navbar {
  background: rgba(255,255,255,0.97) !important;
  border-bottom: 1px solid #e2e8f0 !important;
  backdrop-filter: blur(16px) !important;
}
.top-bar { background: #f8fafc !important; border-bottom: 1px solid #e2e8f0 !important; }
.mobile-menu { background: #fff !important; border-bottom: 1px solid #e2e8f0 !important; }

/* ── HERO ── */
.about-hero {
  padding: 130px 0 100px;
  background: linear-gradient(160deg, #f0f9ff 0%, #e8f4fd 50%, #fff8ee 100%);
  position: relative; overflow: hidden; text-align: center;
}
.about-hero::before {
  content: ''; position: absolute; top: -100px; right: -80px;
  width: 500px; height: 500px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,191,255,0.1) 0%, transparent 65%);
  pointer-events: none;
}
.about-hero::after {
  content: ''; position: absolute; bottom: -60px; left: -60px;
  width: 380px; height: 380px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,140,0,0.08) 0%, transparent 70%);
  pointer-events: none;
}
.about-hero-inner { position: relative; z-index: 2; max-width: 760px; margin: 0 auto; }
.about-pill {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(0,191,255,0.1); color: #0099cc;
  border: 1px solid rgba(0,191,255,0.3);
  padding: 7px 20px; border-radius: 999px;
  font-size: 0.73rem; font-weight: 800;
  letter-spacing: 1.5px; text-transform: uppercase;
  margin-bottom: 24px; display: inline-flex;
}
.about-hero h1 {
  font-family: 'Poppins', sans-serif;
  font-size: clamp(2.4rem, 5vw, 3.8rem);
  font-weight: 900; line-height: 1.1;
  color: #0f172a; margin-bottom: 20px;
}
.about-hero h1 span { color: #00BFFF; }
.about-hero p {
  font-size: 1.1rem; color: #475569;
  line-height: 1.8; max-width: 620px; margin: 0 auto 36px;
}
.hero-cta-row { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
.btn-hero-primary {
  display: inline-flex; align-items: center; gap: 9px;
  background: #00BFFF; color: #fff;
  padding: 14px 32px; border-radius: 50px;
  font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 0.95rem;
  box-shadow: 0 6px 20px rgba(0,191,255,0.3);
  transition: all 0.3s; text-decoration: none;
}
.btn-hero-primary:hover { background: #0099d6; transform: translateY(-2px); color: #fff; box-shadow: 0 12px 28px rgba(0,191,255,0.4); }
.btn-hero-outline {
  display: inline-flex; align-items: center; gap: 9px;
  background: transparent; color: #FF8C00;
  padding: 13px 32px; border-radius: 50px;
  font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 0.95rem;
  border: 2px solid #FF8C00; transition: all 0.3s; text-decoration: none;
}
.btn-hero-outline:hover { background: #FF8C00; color: #fff; transform: translateY(-2px); }

/* ── LIVE STATS STRIP ── */
.stats-strip {
  background: #fff; padding: 56px 0;
  border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;
}
.stats-strip-grid {
  display: grid; grid-template-columns: repeat(4,1fr);
  gap: 0; text-align: center;
}
.stat-item { padding: 24px; border-right: 1px solid #e2e8f0; }
.stat-item:last-child { border-right: none; }
.stat-item-num {
  font-family: 'Poppins', sans-serif;
  font-size: 2.6rem; font-weight: 900;
  color: #0f172a; line-height: 1; display: block;
}
.stat-item-num span { color: #00BFFF; }
.stat-item-lbl { font-size: 0.82rem; color: #64748b; margin-top: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }

/* ── MISSION SECTION ── */
.mission-section { padding: 100px 0; background: #fff; }
.mission-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
.mission-tag {
  font-size: 0.73rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: 2px; color: #FF8C00; margin-bottom: 16px; display: block;
}
.mission-heading {
  font-family: 'Poppins', sans-serif;
  font-size: clamp(1.8rem, 3.5vw, 2.5rem);
  font-weight: 800; color: #0f172a; line-height: 1.2; margin-bottom: 20px;
}
.mission-heading span { color: #00BFFF; }
.mission-body { font-size: 1rem; color: #475569; line-height: 1.8; margin-bottom: 16px; }
.mission-list { list-style: none; padding: 0; margin: 28px 0 0; display: flex; flex-direction: column; gap: 14px; }
.mission-list li { display: flex; align-items: flex-start; gap: 12px; font-size: 0.97rem; color: #334155; }
.mission-list li i { color: #00BFFF; margin-top: 3px; flex-shrink: 0; }

.image-stack { position: relative; }
.img-main { width: 100%; border-radius: 28px; box-shadow: 0 24px 52px rgba(0,0,0,0.1); display: block; }
.img-float {
  position: absolute; bottom: -28px; left: -28px;
  background: #fff; padding: 22px 26px;
  border-radius: 20px; box-shadow: 0 16px 36px rgba(0,0,0,0.1);
  display: flex; align-items: center; gap: 16px;
  border: 1px solid #e2e8f0;
}
.img-float-icon {
  width: 48px; height: 48px; border-radius: 14px;
  background: rgba(0,191,255,0.1);
  display: flex; align-items: center; justify-content: center;
  color: #00BFFF; font-size: 1.3rem; flex-shrink: 0;
}
.img-float-label { font-family: 'Poppins',sans-serif; font-size: 0.9rem; font-weight: 800; color: #0f172a; }
.img-float-sub { font-size: 0.75rem; color: #64748b; margin-top: 2px; }

/* ── VISION / PILLARS ── */
.vision-section { padding: 100px 0; background: #f8fafc; }
.vision-header { text-align: center; max-width: 700px; margin: 0 auto 64px; }
.vision-heading { font-family: 'Poppins',sans-serif; font-size: clamp(1.8rem,3.5vw,2.5rem); font-weight: 800; color: #0f172a; margin-bottom: 16px; }
.vision-heading span { color: #00BFFF; }
.vision-sub { font-size: 1rem; color: #475569; line-height: 1.7; }

.vision-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 28px; }
.vision-card {
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: 24px; padding: 40px 32px;
  text-align: center; transition: 0.3s;
  position: relative; overflow: hidden;
}
.vision-card:hover { transform: translateY(-6px); border-color: #00BFFF; box-shadow: 0 16px 40px rgba(0,191,255,0.1); }
.vision-card::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0;
  height: 3px; background: linear-gradient(90deg, #00BFFF, #FF8C00);
  opacity: 0; transition: 0.3s;
}
.vision-card:hover::after { opacity: 1; }
.vision-icon {
  width: 72px; height: 72px; border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.8rem; margin: 0 auto 24px;
}
.vision-card h3 { font-family: 'Poppins',sans-serif; font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 14px; }
.vision-card p { font-size: 0.93rem; color: #475569; line-height: 1.75; }

/* ── VALUES SECTION ── */
.values-section { padding: 100px 0; background: #fff; }
.values-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 24px; margin-top: 56px; }
.value-card {
  padding: 36px 24px; border-radius: 20px;
  border: 1px solid #e2e8f0; background: #f8fafc;
  text-align: center; transition: 0.3s;
}
.value-card:hover { border-color: #FF8C00; background: #fff; transform: translateY(-4px); box-shadow: 0 12px 30px rgba(255,140,0,0.08); }
.value-icon {
  width: 60px; height: 60px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; margin: 0 auto 18px;
  background: rgba(255,140,0,0.08); color: #FF8C00;
}
.value-card h4 { font-family: 'Poppins',sans-serif; font-weight: 800; font-size: 1rem; color: #0f172a; margin-bottom: 10px; }
.value-card p { font-size: 0.85rem; color: #64748b; line-height: 1.65; }

/* ── CTA ── */
.about-cta {
  padding: 100px 0; text-align: center;
  background: linear-gradient(135deg, #0a1628 0%, #0c1f40 100%);
  position: relative; overflow: hidden;
}
.about-cta::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle at 50% 50%, rgba(0,191,255,0.08) 0%, transparent 60%);
}
.about-cta-inner { position: relative; z-index: 1; max-width: 680px; margin: 0 auto; }
.about-cta h2 { font-family: 'Poppins',sans-serif; font-size: clamp(1.9rem,3.5vw,2.6rem); font-weight: 900; color: #fff; margin-bottom: 18px; }
.about-cta h2 span { color: #00BFFF; }
.about-cta p { font-size: 1rem; color: #94a3b8; line-height: 1.75; margin-bottom: 40px; }
.about-cta-btns { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
.btn-cta-w {
  display: inline-flex; align-items: center; gap: 9px;
  background: #00BFFF; color: #fff;
  padding: 15px 34px; border-radius: 50px;
  font-family: 'Poppins',sans-serif; font-weight: 700; font-size: 0.95rem;
  box-shadow: 0 6px 20px rgba(0,191,255,0.35);
  transition: all 0.3s; text-decoration: none;
}
.btn-cta-w:hover { background: #0099d6; transform: translateY(-2px); color: #fff; }
.btn-cta-ow {
  display: inline-flex; align-items: center; gap: 9px;
  background: transparent; color: #FF8C00;
  padding: 14px 34px; border-radius: 50px;
  font-family: 'Poppins',sans-serif; font-weight: 700; font-size: 0.95rem;
  border: 2px solid #FF8C00;
  transition: all 0.3s; text-decoration: none;
}
.btn-cta-ow:hover { background: #FF8C00; color: #fff; transform: translateY(-2px); }

/* ── Animations ── */
[data-aos] { opacity: 0; transform: translateY(28px); transition: opacity 0.6s ease, transform 0.6s ease; }
[data-aos].visible { opacity: 1; transform: translateY(0); }
[data-aos="fade-right"] { transform: translateX(-28px); }
[data-aos="fade-right"].visible { transform: translateX(0); }
[data-aos="fade-left"] { transform: translateX(28px); }
[data-aos="fade-left"].visible { transform: translateX(0); }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .values-grid { grid-template-columns: repeat(2,1fr); }
  .vision-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 900px) {
  .mission-grid { grid-template-columns: 1fr; gap: 56px; }
  .img-float { display: none; }
}
@media (max-width: 640px) {
  .stats-strip-grid { grid-template-columns: repeat(2,1fr); }
  .stats-strip-grid .stat-item:nth-child(2) { border-right: none; }
  .stats-strip-grid .stat-item:nth-child(3) { border-top: 1px solid #e2e8f0; }
  .stats-strip-grid .stat-item:nth-child(4) { border-top: 1px solid #e2e8f0; border-right: none; }
  .vision-grid { grid-template-columns: 1fr; }
  .values-grid { grid-template-columns: 1fr; }
  .hero-cta-row { flex-direction: column; align-items: center; }
  .about-cta-btns { flex-direction: column; align-items: center; }
}
</style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<!-- ══ HERO ══ -->
<section class="about-hero">
  <div class="about-hero-inner" data-aos>
    <div class="about-pill"><i class="fas fa-star"></i> Our Story & Mission</div>
    <h1>Building Kenya's <span>Digital Future</span>,<br>One Learner at a Time.</h1>
    <p>Skope Digital Academy was founded on a single belief: world-class education should be accessible to every ambitious mind in Africa — regardless of background or location.</p>
    <div class="hero-cta-row">
      <a href="courses.php" class="btn-hero-primary"><i class="fas fa-rocket"></i> Explore Courses</a>
      <a href="scholarships.php" class="btn-hero-outline"><i class="fas fa-graduation-cap"></i> Get a Scholarship</a>
    </div>
  </div>
</section>

<!-- ══ LIVE STATS STRIP ══ -->
<div class="stats-strip">
  <div class="container">
    <div class="stats-strip-grid">
      <div class="stat-item" data-aos>
        <span class="stat-item-num"><?= number_format($total_students) ?><span>+</span></span>
        <div class="stat-item-lbl">🎓 Active Learners</div>
      </div>
      <div class="stat-item" data-aos style="transition-delay:0.1s;">
        <span class="stat-item-num"><?= number_format($total_courses) ?><span>+</span></span>
        <div class="stat-item-lbl">📚 Published Courses</div>
      </div>
      <div class="stat-item" data-aos style="transition-delay:0.2s;">
        <span class="stat-item-num"><?= number_format($total_tutors) ?><span>+</span></span>
        <div class="stat-item-lbl">🌍 Expert Mentors</div>
      </div>
      <div class="stat-item" data-aos style="transition-delay:0.3s;">
        <span class="stat-item-num">98<span>%</span></span>
        <div class="stat-item-lbl">⭐ Satisfaction Rate</div>
      </div>
    </div>
  </div>
</div>

<!-- ══ MISSION ══ -->
<section class="mission-section">
  <div class="container">
    <div class="mission-grid">
      <!-- Left: Text -->
      <div data-aos="fade-right">
        <span class="mission-tag">About Skope Digital</span>
        <h2 class="mission-heading">Revolutionizing Education<br>Through <span>AI Intelligence</span></h2>
        <p class="mission-body">Skope Digital Academy was founded in 2024 with a clear mission — bridge the vast gap between traditional classroom education and the real-world skills employers demand. Using AI-powered adaptive learning paths, we deliver personalized, practical, and globally certified education to every Kenyan.</p>
        <p class="mission-body">We've rapidly grown into Africa's leading digital education hub, partnering with industry leaders at Google, Microsoft, IBM and leading Kenyan tech firms to ensure our curriculum reflects exactly what the job market needs today.</p>
        <ul class="mission-list">
          <li><i class="fas fa-check-circle"></i> AI-personalized learning paths tailored to your career goals</li>
          <li><i class="fas fa-check-circle"></i> Globally recognized and cryptographically verified certificates</li>
          <li><i class="fas fa-check-circle"></i> Scholarship funding available — up to 100% of course fees</li>
          <li><i class="fas fa-check-circle"></i> Real mentors from Google, Microsoft &amp; top Kenyan tech firms</li>
        </ul>
      </div>
      <!-- Right: Image -->
      <div class="image-stack" data-aos="fade-left">
        <img src="assets/images/elearning login bg (1).jpeg" class="img-main" alt="Skope Digital Academy students learning" onerror="this.style.display='none'">
        <div class="img-float">
          <div class="img-float-icon"><i class="fas fa-medal"></i></div>
          <div>
            <div class="img-float-label">Globally Certified</div>
            <div class="img-float-sub">Accepted by 200+ employers</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ VISION PILLARS ══ -->
<section class="vision-section">
  <div class="container">
    <div class="vision-header" data-aos>
      <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:#00BFFF;background:rgba(0,191,255,0.08);padding:6px 16px;border-radius:999px;display:inline-block;margin-bottom:14px;">Our Vision</div>
      <h2 class="vision-heading">The <span>Skope</span> Vision</h2>
      <p class="vision-sub">To become the primary catalyst for Africa's technological revolution — empowering every learner with elite, accessible, and AI-personalized skills.</p>
    </div>
    <div class="vision-grid">
      <div class="vision-card" data-aos>
        <div class="vision-icon" style="background:rgba(0,191,255,0.09);color:#00BFFF;">
          <i class="fas fa-brain"></i>
        </div>
        <h3>AI Personalization</h3>
        <p>Our platform adapts to your pace, strengths, and goals — creating a truly bespoke learning experience that evolves with you.</p>
      </div>
      <div class="vision-card" data-aos style="transition-delay:0.12s;">
        <div class="vision-icon" style="background:rgba(255,140,0,0.09);color:#FF8C00;">
          <i class="fas fa-globe"></i>
        </div>
        <h3>Global Impact</h3>
        <p>We erase geographical barriers, bringing world-class mentors and globally recognized credentials to every corner of Kenya.</p>
      </div>
      <div class="vision-card" data-aos style="transition-delay:0.24s;">
        <div class="vision-icon" style="background:rgba(16,185,129,0.09);color:#10b981;">
          <i class="fas fa-rocket"></i>
        </div>
        <h3>Career Acceleration</h3>
        <p>Beyond knowledge — we provide the credentials, mentorship, and networking needed for immediate, real-world professional growth.</p>
      </div>
    </div>
  </div>
</section>

<!-- ══ CORE VALUES ══ -->
<section class="values-section">
  <div class="container">
    <div style="text-align:center;max-width:680px;margin:0 auto;" data-aos>
      <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:#FF8C00;background:rgba(255,140,0,0.08);padding:6px 16px;border-radius:999px;display:inline-block;margin-bottom:14px;">What Drives Us</div>
      <h2 style="font-family:'Poppins',sans-serif;font-size:clamp(1.8rem,3.5vw,2.4rem);font-weight:800;color:#0f172a;margin-bottom:14px;">Our Core <span style="color:#FF8C00;">Values</span></h2>
      <p style="color:#475569;font-size:0.97rem;line-height:1.7;">Every feature we build and every course we publish is anchored in these four beliefs.</p>
    </div>
    <div class="values-grid">
      <div class="value-card" data-aos>
        <div class="value-icon"><i class="fas fa-shield-alt"></i></div>
        <h4>Academic Integrity</h4>
        <p>Every certificate we issue is verifiable, tamper-proof and genuinely earned — your credibility is our reputation.</p>
      </div>
      <div class="value-card" data-aos style="transition-delay:0.1s;">
        <div class="value-icon"><i class="fas fa-lightbulb"></i></div>
        <h4>Innovation First</h4>
        <p>We build with cutting-edge AI tools and constantly refresh our curriculum to stay ahead of industry shifts.</p>
      </div>
      <div class="value-card" data-aos style="transition-delay:0.2s;">
        <div class="value-icon"><i class="fas fa-hand-holding-heart"></i></div>
        <h4>Radical Inclusion</h4>
        <p>Through scholarships and flexible pricing, we ensure financial barriers never prevent talented students from growing.</p>
      </div>
      <div class="value-card" data-aos style="transition-delay:0.3s;">
        <div class="value-icon"><i class="fas fa-chart-line"></i></div>
        <h4>Practical Excellence</h4>
        <p>We don't just teach — we transform careers. Every lesson is rooted in real-world applications and employer needs.</p>
      </div>
    </div>
  </div>
</section>

<!-- ══ CTA ══ -->
<section class="about-cta">
  <div class="container">
    <div class="about-cta-inner" data-aos>
      <h2>Ready to <span>Transform</span> Your Career?</h2>
      <p>Join <?= number_format($total_students) ?>+ learners already mastering the future of technology with Africa's most innovative learning platform.</p>
      <div class="about-cta-btns">
        <a href="courses.php" class="btn-cta-w"><i class="fas fa-rocket"></i> Start Learning Free</a>
        <a href="scholarships.php" class="btn-cta-ow"><i class="fas fa-graduation-cap"></i> Apply for Scholarship</a>
      </div>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>

<script>
// Scroll-triggered animations
const els = document.querySelectorAll('[data-aos]');
const io = new IntersectionObserver(entries => {
  entries.forEach(e => { if(e.isIntersecting) { e.target.classList.add('visible'); } });
}, { threshold: 0.15 });
els.forEach(el => io.observe(el));
</script>

</body>
</html>