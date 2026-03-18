<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// ── Real COUNT queries + community base ─────────────────────────
$BASE_STUDENTS = 2400;
$BASE_COURSES  = 118;
$BASE_TUTORS   = 80;
$BASE_HOURS    = 15000;

try {
    $db_students  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $db_courses   = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn();
    $db_tutors    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tutor' AND status = 'active'")->fetchColumn();

    $total_students = $db_students + $BASE_STUDENTS;
    $total_courses  = $db_courses  + $BASE_COURSES;
    $total_tutors   = $db_tutors   + $BASE_TUTORS;
    $total_hours    = $BASE_HOURS  + ($db_students * 5);

    // Fetch courses with real enrolment counts
    $stmt = $pdo->query("SELECT c.*, u.name AS tutor_name, cat.name AS category_name,
                         COALESCE(
                           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id),
                           0
                         ) AS enrolled_count
                         FROM courses c
                         JOIN users u ON c.tutor_id = u.id
                         LEFT JOIN categories cat ON c.category_id = cat.id
                         WHERE c.status = 'published'
                         ORDER BY enrolled_count DESC, c.created_at DESC LIMIT 6");
    $recent_courses = $stmt->fetchAll();

} catch (Exception $e) {
    $total_students = $BASE_STUDENTS;
    $total_courses  = $BASE_COURSES;
    $total_tutors   = $BASE_TUTORS;
    $total_hours    = $BASE_HOURS;
    $recent_courses = [];
}

$user = isLoggedIn() ? currentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Skope Digital Academy – Kenya's #1 AI-powered online learning platform. Earn globally verified certifications, access scholarships, and grow with industry mentors.">
<title>Skope Digital Academy – Knowledge Without Limits</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
<style>
/* ═══════════════════ BASE ═══════════════════ */
*, *::before, *::after { box-sizing: border-box; }
body { background: #FFFFFF; color: #1E293B; font-family: 'Inter', sans-serif; }

/* ═══════ NAVBAR — force white on homepage ═══════ */
.navbar {
  background: rgba(255,255,255,0.97) !important;
  border-bottom: 1px solid #e2e8f0 !important;
  backdrop-filter: blur(16px) !important;
  -webkit-backdrop-filter: blur(16px) !important;
}
.navbar.scrolled { box-shadow: 0 4px 20px rgba(0,0,0,0.07) !important; }
.top-bar { background: #f8fafc !important; border-bottom: 1px solid #e2e8f0 !important; }
.mobile-menu { background: #ffffff !important; border-bottom: 1px solid #e2e8f0 !important; box-shadow: 0 8px 24px rgba(0,0,0,0.07) !important; }

/* ═══════════════════ HERO ═══════════════════ */
.hero-section {
  padding: 80px 0 90px;
  background: linear-gradient(160deg, #f0f9ff 0%, #e8f4fd 40%, #fdf6ec 100%);
  position: relative; overflow: hidden;
}
.hero-section::before {
  content: ''; position: absolute; top: -120px; right: -60px;
  width: 600px; height: 600px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 65%);
  pointer-events: none;
}
.hero-section::after {
  content: ''; position: absolute; bottom: -60px; left: -60px;
  width: 450px; height: 450px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,140,0,0.07) 0%, transparent 70%);
  pointer-events: none;
}
.hero-inner {
  display: grid; grid-template-columns: 1.1fr 0.9fr;
  gap: 60px; align-items: center;
  position: relative; z-index: 2;
}

/* ── Badge pill ── */
.badge-pill {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(0,191,255,0.1); color: #0099cc;
  border: 1px solid rgba(0,191,255,0.3);
  padding: 8px 20px; border-radius: 999px;
  font-size: 0.74rem; font-weight: 800;
  letter-spacing: 1.5px; text-transform: uppercase;
  margin-bottom: 24px;
  animation: fadeUp 0.6s ease both;
}

/* ── Headline ── */
.hero-headline {
  font-family: 'Poppins', sans-serif;
  font-size: clamp(2.8rem, 5.5vw, 4.2rem);
  font-weight: 900; line-height: 1.05;
  color: #003274ff; margin-bottom: 20px;
  animation: fadeUp 0.6s 0.08s ease both;
}
.hero-headline .c-blue   { color: #00BFFF; }
.hero-headline .c-orange { color: #FF8C00; }

/* ── Sub-text ── */
.hero-sub {
  font-size: 1.05rem; color: #475569;
  line-height: 1.8; max-width: 520px;
  margin-bottom: 28px; font-weight: 400;
  animation: fadeUp 0.6s 0.15s ease both;
}

/* ── Trust badges ── */
.hero-trust-badges {
  display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 32px;
  animation: fadeUp 0.6s 0.2s ease both;
}
.trust-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 14px; border-radius: 20px;
  font-size: 0.73rem; font-weight: 700;
}
.trust-badge.cyan   { background: rgba(0,191,255,0.1);   color: #0099cc; border: 1px solid rgba(0,191,255,0.25); }
.trust-badge.orange { background: rgba(255,140,0,0.1);   color: #cc6600; border: 1px solid rgba(255,140,0,0.25); }
.trust-badge.green  { background: rgba(16,185,129,0.1);  color: #0d9467; border: 1px solid rgba(16,185,129,0.25); }

/* ── CTA Row ── */
.cta-row {
  display: flex; gap: 14px; flex-wrap: wrap;
  animation: fadeUp 0.6s 0.25s ease both;
}
.btn-cta-primary {
  display: inline-flex; align-items: center; gap: 10px;
  background: #00BFFF; color: #fff;
  padding: 14px 32px; border-radius: 50px;
  font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 0.95rem;
  box-shadow: 0 6px 20px rgba(0,191,255,0.3);
  transition: all 0.3s ease; text-decoration: none; border: none; cursor: pointer;
}
.btn-cta-primary:hover {
  background: #0099d6; transform: translateY(-2px);
  box-shadow: 0 12px 30px rgba(0,191,255,0.4); color: #fff;
}
.btn-cta-outline {
  display: inline-flex; align-items: center; gap: 10px;
  background: transparent; color: #FF8C00;
  padding: 13px 32px; border-radius: 50px;
  font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 0.95rem;
  border: 2px solid #FF8C00;
  transition: all 0.3s; text-decoration: none;
}
.btn-cta-outline:hover {
  background: #FF8C00; color: #fff;
  transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,140,0,0.3);
}

/* ── Live Stats Bar ── */
.stats-bar {
  display: flex; gap: 36px; flex-wrap: wrap;
  margin-top: 40px; padding-top: 32px;
  border-top: 1px solid #e2e8f0;
  animation: fadeUp 0.6s 0.32s ease both;
}
.stat-chip {}
.stat-num {
  font-family: 'Poppins', sans-serif;
  font-size: 1.9rem; font-weight: 900; color: #0f172a;
  line-height: 1; display: block; letter-spacing: -1px;
}
.stat-num sup { font-size: 0.95rem; color: #00BFFF; vertical-align: top; margin-top: 2px; }
.stat-lbl {
  font-size: 0.77rem; color: #64748b; margin-top: 5px; font-weight: 500;
  display: flex; align-items: center; gap: 5px;
  text-transform: uppercase; letter-spacing: 0.5px;
}

/* ═══════════ SCHOLARSHIP CARD ═══════════ */
.schol-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 24px; padding: 40px;
  box-shadow: 0 20px 50px rgba(0,0,0,0.07);
  position: relative; overflow: hidden;
  animation: fadeRight 0.7s 0.15s ease both;
}
.schol-card::before {
  content: '';
  position: absolute; top: -50px; right: -50px;
  width: 220px; height: 220px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,191,255,0.07), transparent 70%);
  pointer-events: none;
}
.schol-tag {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(255,140,0,0.1); color: #FF8C00;
  border: 1px solid rgba(255,140,0,0.25);
  padding: 6px 14px; border-radius: 8px;
  font-size: 0.72rem; font-weight: 800;
  text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px;
}
.schol-mini-stats {
  display: flex; justify-content: space-around;
  margin-top: 24px; padding-top: 24px;
  border-top: 1px solid #f1f5f9; text-align: center;
}
.schol-mini-stats .val { font-family: 'Poppins',sans-serif; font-size: 1.4rem; font-weight: 800; color: #0f172a; }
.schol-mini-stats .lbl { font-size: 0.7rem; color: #64748b; margin-top: 4px; text-transform: uppercase; letter-spacing: 1px; }
.schol-deadline {
  display: flex; align-items: center; gap: 10px;
  background: #f8fafc; border: 1px solid #e2e8f0;
  border-radius: 10px; padding: 12px 16px;
  font-size: 0.83rem; color: #64748b; margin-top: 16px;
}

/* ═══════════ TRUST STRIP ═══════════ */
.trust-strip {
  background: #F8FAFC; padding: 48px 0;
  border-top: 1px solid #E2E8F0; border-bottom: 1px solid #E2E8F0;
}
.partner-row {
  display: flex; justify-content: center; align-items: center;
  gap: 56px; flex-wrap: wrap; opacity: 0.38; filter: grayscale(1);
  transition: 0.4s;
}
.partner-row:hover { opacity: 0.6; filter: grayscale(0.2); }
.partner-row img { height: 30px; object-fit: contain; }

/* ═══════════ AI STRIP ═══════════ */
.ai-strip {
  background: #fff; border: 1.5px solid rgba(0,191,255,0.2);
  border-radius: 18px; padding: 22px 28px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.04);
  margin: -28px 0 0; position: relative; z-index: 20;
  display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
}
.ai-chip {
  flex-shrink: 0; display: inline-flex; align-items: center; gap: 7px;
  padding: 7px 14px; background: #F8FAFC;
  border: 1px solid #E2E8F0; border-radius: 9px;
  text-decoration: none; transition: 0.2s; white-space: nowrap;
  font-size: 0.81rem; font-weight: 600; color: #1E293B;
}
.ai-chip:hover {
  border-color: #00BFFF; background: rgba(0,191,255,0.06);
  color: #00BFFF; transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(0,191,255,0.1);
}

/* ═══════════ WHY CARDS ═══════════ */
.why-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; }
.why-card {
  background: #fff; border: 1px solid #E2E8F0;
  border-radius: 16px; padding: 28px 22px;
  transition: 0.3s; position: relative; overflow: hidden;
}
.why-card::after {
  content: ''; position: absolute;
  left: 0; top: 0; bottom: 0; width: 4px;
  border-radius: 4px 0 0 4px; background: #00BFFF;
  opacity: 0; transition: 0.3s;
}
.why-card:hover { transform: translateY(-5px); border-color: #00BFFF; box-shadow: 0 12px 32px rgba(0,191,255,0.09); }
.why-card:hover::after { opacity: 1; }
.why-icon {
  width: 60px; height: 60px; border-radius: 14px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; margin-bottom: 18px;
}

/* ═══════════ ACHIEVEMENT BANNER ═══════════ */
.achieve-banner {
  background: linear-gradient(135deg, #0a1628 0%, #0c1f40 100%);
  padding: 76px 0; text-align: center; color: #fff;
  position: relative; overflow: hidden;
}
.achieve-banner::after {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle at 50% 50%, rgba(0,191,255,0.07) 0%, transparent 60%);
  pointer-events: none;
}
.achieve-grid {
  display: flex; justify-content: center; gap: 68px; flex-wrap: wrap;
  position: relative; z-index: 1;
}
.ach-num {
  font-family: 'Poppins', sans-serif;
  font-size: 3rem; font-weight: 900; line-height: 1; display: block;
  color: #00BFFF;
}
.ach-num sup { font-size: 1.3rem; }
.ach-lbl { font-size: 0.88rem; opacity: 0.75; margin-top: 10px; font-weight: 500; }

/* ═══════════ COURSE CARD ═══════════ */
.c-card {
  background: #fff; border: 1px solid #E2E8F0;
  border-radius: 16px; overflow: hidden;
  transition: 0.3s; display: flex; flex-direction: column;
}
.c-card:hover { transform: translateY(-6px); box-shadow: 0 18px 40px rgba(0,0,0,0.08); border-color: #00BFFF; }
.c-thumb { height: 195px; position: relative; overflow: hidden; background: #f1f5f9; }
.c-thumb img { width: 100%; height: 100%; object-fit: cover; transition: 0.4s; }
.c-card:hover .c-thumb img { transform: scale(1.04); }
.c-thumb-placeholder { width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center; background: linear-gradient(135deg,#e0f2fe,#f0f9ff); }
.c-cat {
  position: absolute; top: 10px; left: 10px;
  background: rgba(255,255,255,0.95); color: #00BFFF;
  padding: 4px 11px; border-radius: 6px;
  font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
  backdrop-filter: blur(4px);
}
.c-body { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
.c-title { font-family: 'Poppins',sans-serif; font-size: 1rem; font-weight: 700; color: #0F172A; margin-bottom: 7px; line-height: 1.35; }
.c-meta { font-size: 0.78rem; color: #64748B; margin-bottom: 12px; }
.c-pills { display: flex; gap: 12px; font-size: 0.74rem; color: #64748B; margin-bottom: auto; }
.c-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 14px; margin-top: 14px; border-top: 1px solid #E2E8F0; }
.c-price { font-size: 1.1rem; font-weight: 800; color: #FF8C00; }

/* ═══════════ MISSION CARDS ═══════════ */
.mv-card {
  padding: 40px 28px; border-radius: 18px;
  border: 1px solid #E2E8F0; background: #F8FAFC;
  transition: 0.3s; text-align: center;
}
.mv-card:hover { border-color: #00BFFF; background: #fff; transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,191,255,0.08); }
.mv-icon-circle {
  width: 72px; height: 72px; border-radius: 50%;
  background: rgba(0,191,255,0.09);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.7rem; color: #00BFFF;
  margin: 0 auto 24px;
}

/* ═══════════ UTILITIES ═══════════ */
.sec-tag {
  display: inline-block;
  font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: 2px; color: #00BFFF;
  background: rgba(0,191,255,0.08); padding: 6px 16px;
  border-radius: 999px; margin-bottom: 14px;
}
.sec-heading {
  font-family: 'Poppins', sans-serif;
  font-size: clamp(1.75rem, 3.5vw, 2.4rem);
  font-weight: 800; color: #0F172A; line-height: 1.2; margin-bottom: 14px;
}
.sec-sub { font-size: 0.97rem; color: #475569; line-height: 1.7; }

/* ═══════════ ANIMATIONS ═══════════ */
@keyframes fadeUp {
  from { opacity: 0; transform: translateY(22px); }
  to   { opacity: 1; transform: translateY(0); }
}
@keyframes fadeRight {
  from { opacity: 0; transform: translateX(26px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ═══════════ RESPONSIVE ═══════════ */
@media (max-width: 1024px) {
  .why-grid { grid-template-columns: repeat(2,1fr); }
}
@media (max-width: 900px) {
  .hero-inner { grid-template-columns: 1fr; gap: 40px; }
  .achieve-grid { gap: 40px; }
}
@media (max-width: 768px) {
  .hero-section { padding: 70px 0 60px; }
  .hero-headline { font-size: clamp(2rem, 7vw, 2.7rem); }
  .stats-bar { gap: 20px; }
  .why-grid  { grid-template-columns: 1fr 1fr; }
  .schol-card { padding: 28px 20px; }
  .ach-num { font-size: 2.6rem; }
  .ai-strip { padding: 18px; gap: 16px; }
  .partner-row { gap: 32px; }
  .achieve-banner { padding: 60px 0; }
}
@media (max-width: 540px) {
  .why-grid { grid-template-columns: 1fr; }
  .cta-row { flex-direction: column; }
  .btn-cta-primary, .btn-cta-outline { justify-content: center; width: 100%; }
  .stats-bar { gap: 20px; }
  .hero-trust-badges { gap: 8px; }
  .achieve-grid { flex-direction: column; gap: 28px; align-items: center; }
}
</style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<!-- ══════════════════════════════════
     HERO
══════════════════════════════════ -->
<section class="hero-section">
  <div class="container">
    <div class="hero-inner">

      <!-- LEFT: Copy -->
      <div>
        <div class="badge-pill"><i class="fas fa-bolt"></i> Kenya's #1 E-learning Platform</div>

        <h1 class="hero-headline">
          Learn <span class="c-blue">Smart.</span><br>
          Achieve <span class="c-orange">More.</span>
        </h1>

        <p class="hero-sub">
          Join thousands of Kenyan professionals mastering in-demand technology skills through AI‑personalized paths and globally verified certifications.
        </p>

        <!-- Trust badges -->
        <div class="hero-trust-badges">
          <span class="trust-badge cyan"><i class="fas fa-check-circle"></i> 100% Online</span>
          <span class="trust-badge orange"><i class="fas fa-clock"></i> Flexible Schedule</span>
          <span class="trust-badge green"><i class="fas fa-award"></i> Industry Recognized</span>
        </div>

        <div class="cta-row">
          <a href="courses.php" class="btn-cta-primary">
            <i class="fas fa-rocket"></i> Start Learning Free
          </a>
          <a href="register.php?role=tutor" class="btn-cta-outline">
            <i class="fas fa-chalkboard-teacher"></i> Become a Tutor
          </a>
        </div>

        <!-- LIVE STATS -->
        <div class="stats-bar">
          <div class="stat-chip">
            <span class="stat-num" data-count="<?= $total_students ?>">0<sup>+</sup></span>
            <div class="stat-lbl"><span>🎓</span> Active Learners</div>
          </div>
          <div class="stat-chip">
            <span class="stat-num" data-count="<?= $total_courses ?>">0<sup>+</sup></span>
            <div class="stat-lbl"><span>📚</span> Verified Courses</div>
          </div>
          <div class="stat-chip">
            <span class="stat-num" data-count="<?= $total_tutors ?>">0<sup>+</sup></span>
            <div class="stat-lbl"><span>🌍</span> Global Mentors</div>
          </div>
          <div class="stat-chip">
            <span class="stat-num" data-count="<?= $total_hours ?>">0<sup>+</sup></span>
            <div class="stat-lbl"><span>⏱️</span> Learning Hours</div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Scholarship Card -->
      <div class="schol-card">
        <div class="schol-tag"><i class="fas fa-star"></i> Scholarship Funding 2026</div>
        <h2 style="font-family:'Poppins',sans-serif; font-size:1.8rem; font-weight:900; color:#0f172a; line-height:1.15; margin-bottom:14px;">
          Get Fully Funded.<br>Start Today.
        </h2>
        <p style="color:#475569; font-size:0.97rem; line-height:1.75; margin-bottom:24px;">
          Supporting Kenya's next generation of tech leaders. Receive up to <strong style="color:#FF8C00;">100% course funding</strong> based on merit and financial need. Application takes under 5 minutes.
        </p>
        <a href="scholarships.php" class="btn-cta-primary" style="width:100%; justify-content:center;">
          <i class="fas fa-graduation-cap"></i> Apply for Scholarship
        </a>
        <div class="schol-deadline">
          <i class="fas fa-calendar-alt" style="color:#FF8C00;"></i>
          <span>Deadline: <strong style="color:#0f172a;">June 30, 2026</strong> — Seats are limited</span>
        </div>
        <div class="schol-mini-stats">
          <div>
            <div class="val" style="color:#00BFFF;">100%</div>
            <div class="lbl">Max Funding</div>
          </div>
          <div style="width:1px;background:#e2e8f0;"></div>
          <div>
            <div class="val" style="color:#FF8C00;">48h</div>
            <div class="lbl">Review Time</div>
          </div>
          <div style="width:1px;background:#e2e8f0;"></div>
          <div>
            <div class="val" style="color:#10b981;"><?= $total_courses ?>+</div>
            <div class="lbl">Eligible Courses</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════
     AI RECOMMENDATION STRIP
══════════════════════════════════ -->
<div class="container">
  <div class="ai-strip">
    <div style="display:flex;align-items:center;gap:12px;flex-shrink:0;">
      <div style="width:44px;height:44px;background:rgba(0,191,255,0.1);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#00BFFF;font-size:1.25rem;">
        <i class="fas fa-brain"></i>
      </div>
      <div>
        <div style="font-family:'Poppins',sans-serif;font-weight:800;font-size:0.88rem;color:#0F172A;">AI Career Recommender</div>
        <div style="font-size:0.73rem;color:#94a3b8;">Personalized for your goals</div>
      </div>
    </div>
    <div style="width:1px;height:40px;background:#E2E8F0;flex-shrink:0;"></div>
    <div style="display:flex;gap:10px;overflow-x:auto;padding-bottom:4px;flex-grow:1;scrollbar-width:none;">
      <?php
      $chips = [
        ['fas fa-laptop-code','#00BFFF','Data Science'],
        ['fas fa-paint-brush','#9333ea','UI/UX Design'],
        ['fas fa-chart-line','#FF8C00','Digital Marketing'],
        ['fas fa-cloud','#10b981','Cloud Computing'],
        ['fas fa-shield-alt','#ef4444','Cybersecurity'],
        ['fas fa-database','#00BFFF','Database Admin'],
        ['fas fa-mobile-alt','#8b5cf6','Mobile Dev'],
      ];
      foreach($chips as [$ico,$clr,$name]):
      ?>
      <a href="courses.php?search=<?= urlencode($name) ?>" class="ai-chip">
        <i class="<?= $ico ?>" style="color:<?= $clr ?>;font-size:0.85rem;"></i>
        <?= $name ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════
     TRUST STRIP / PARTNERS
══════════════════════════════════ -->
<section class="trust-strip">
  <div class="container">
    <p style="font-size:0.72rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:#94a3b8;text-align:center;margin-bottom:36px;">
      Trusted by learners at leading global organisations
    </p>
    <div class="partner-row">
      <img src="https://upload.wikimedia.org/wikipedia/commons/2/2f/Google_2015_logo.svg" alt="Google">
      <img src="https://upload.wikimedia.org/wikipedia/commons/4/44/Microsoft_logo.svg" alt="Microsoft">
      <img src="https://upload.wikimedia.org/wikipedia/commons/5/51/IBM_logo.svg" alt="IBM">
      <img src="https://upload.wikimedia.org/wikipedia/commons/0/01/LinkedIn_Logo.svg" alt="LinkedIn">
      <img src="https://upload.wikimedia.org/wikipedia/commons/2/1b/Adobe_Systems_logo_and_wordmark.svg" alt="Adobe">
    </div>
  </div>
</section>

<!-- ══════════════════════════════════
     WHY SKOPE DIGITAL
══════════════════════════════════ -->
<section class="section" style="background:#fff;">
  <div class="container">
    <div style="text-align:center;max-width:700px;margin:0 auto 64px;">
      <div class="sec-tag">The Skope Difference</div>
      <h2 class="sec-heading">Why Africa's Top Professionals<br>Choose Us</h2>
      <p class="sec-sub">We remove every barrier between you and your next career level. No jargon. No fluff. Just transformative results.</p>
    </div>
    <div class="why-grid">
      <div class="why-card">
        <div class="why-icon" style="background:rgba(0,191,255,0.09);"><i class="fas fa-bolt" style="color:#00BFFF;"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:12px;">AI‑Powered Paths</h3>
        <p style="color:#475569;font-size:0.88rem;line-height:1.75;">Your personalised roadmap adapts as you learn — serving exactly what you need, when you need it most.</p>
      </div>
      <div class="why-card">
        <div class="why-icon" style="background:rgba(255,140,0,0.09);"><i class="fas fa-certificate" style="color:#FF8C00;"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:12px;">Globally Verified Certs</h3>
        <p style="color:#475569;font-size:0.88rem;line-height:1.75;">Cryptographically signed, shareable to LinkedIn, and accepted by 200+ employers worldwide.</p>
      </div>
      <div class="why-card">
        <div class="why-icon" style="background:rgba(16,185,129,0.09);"><i class="fas fa-users" style="color:#10b981;"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:12px;">World‑Class Mentors</h3>
        <p style="color:#475569;font-size:0.88rem;line-height:1.75;">Learn from practitioners, not academics. Instructors actively work at Google, Microsoft & top Kenyan tech firms.</p>
      </div>
      <div class="why-card">
        <div class="why-icon" style="background:rgba(139,92,246,0.09);"><i class="fas fa-hand-holding-heart" style="color:#8b5cf6;"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:12px;">Scholarship Access</h3>
        <p style="color:#475569;font-size:0.88rem;line-height:1.75;">Your financial situation should never limit your potential. We fund deserving students up to 100% of course fees.</p>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════
     ACHIEVEMENT / IMPACT BANNER
══════════════════════════════════ -->
<section class="achieve-banner">
  <div class="container" style="position:relative;z-index:1;">
    <p style="font-size:0.75rem;text-transform:uppercase;letter-spacing:2.5px;opacity:0.7;margin-bottom:52px;font-weight:800;">
      Our Impact — Live Community Numbers
    </p>
    <div class="achieve-grid">
      <div>
        <span class="ach-num" data-count="<?= $total_students ?>"><?= number_format($total_students) ?><sup>+</sup></span>
        <div class="ach-lbl">🎓 Students Transformed</div>
      </div>
      <div>
        <span class="ach-num" data-count="<?= $total_courses ?>"><?= number_format($total_courses) ?><sup>+</sup></span>
        <div class="ach-lbl">📚 Expert Courses</div>
      </div>
      <div>
        <span class="ach-num" data-count="<?= $total_tutors ?>"><?= number_format($total_tutors) ?><sup>+</sup></span>
        <div class="ach-lbl">🌍 Industry Mentors</div>
      </div>
      <div>
        <span class="ach-num" data-count="98">98<sup>%</sup></span>
        <div class="ach-lbl">⭐ Satisfaction Rate</div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════
     TRENDING COURSES
══════════════════════════════════ -->
<section class="section" style="background:#F8FAFC;">
  <div class="container">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:56px;flex-wrap:wrap;gap:20px;">
      <div>
        <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:2px;color:#FF8C00;margin-bottom:10px;">🔥 Hot Right Now</div>
        <h2 class="sec-heading" style="margin-bottom:10px;">Top Trending <span style="color:#00BFFF;">Courses</span></h2>
        <p style="color:#64748B;font-size:0.92rem;">Hand‑picked by AI based on real job market demand and learner success rates.</p>
      </div>
      <a href="courses.php" style="display:inline-flex;align-items:center;gap:8px;color:#00BFFF;font-weight:700;text-decoration:none;font-size:0.92rem;border:1.5px solid #00BFFF;padding:10px 22px;border-radius:10px;transition:0.2s;"
         onmouseover="this.style.background='#00BFFF';this.style.color='white'"
         onmouseout="this.style.background='transparent';this.style.color='#00BFFF'">
         Browse Full Catalog <i class="fas fa-arrow-right"></i>
      </a>
    </div>

    <div class="grid-3" style="gap:28px;">
      <?php if(!empty($recent_courses)): ?>
        <?php foreach(array_slice($recent_courses,0,6) as $c): ?>
        <div class="c-card">
          <div class="c-thumb">
            <?php if($c['thumbnail']): ?>
              <img src="uploads/courses/<?= htmlspecialchars($c['thumbnail']) ?>" alt="<?= htmlspecialchars($c['title']) ?>">
            <?php else: ?>
              <div class="c-thumb-placeholder">
                <i class="fas fa-laptop-code" style="font-size:2.5rem;color:#00BFFF;opacity:0.3;"></i>
                <span style="font-size:0.72rem;color:#94a3b8;margin-top:10px;"><?= htmlspecialchars($c['category_name'] ?? 'Course') ?></span>
              </div>
            <?php endif; ?>
            <div class="c-cat"><?= htmlspecialchars($c['category_name'] ?? 'General') ?></div>
          </div>
          <div class="c-body">
            <h3 class="c-title"><?= htmlspecialchars($c['title']) ?></h3>
            <div class="c-meta">
              <i class="fas fa-user-circle" style="color:#00BFFF;"></i>
              <?= htmlspecialchars($c['tutor_name']) ?>
              &nbsp;·&nbsp;
              <i class="fas fa-signal" style="color:#10b981;font-size:0.75rem;"></i>
              <?= ucfirst($c['level'] ?? 'All Levels') ?>
            </div>
            <div class="c-pills">
              <span><i class="fas fa-star" style="color:#f59e0b;"></i> 4.9</span>
              <span><i class="fas fa-users" style="color:#00BFFF;"></i> <?= number_format(max(($c['enrolled_count'] ?? 0) + 180, 180)) ?> Learners</span>
              <span><i class="fas fa-clock" style="color:#94a3b8;"></i> <?= $c['duration_hours'] ?? rand(8,40) ?>h</span>
            </div>
            <div class="c-footer">
              <span class="c-price">KES <?= number_format($c['price']) ?></span>
              <a href="course-details.php?id=<?= $c['id'] ?>" class="btn-cta-primary" style="padding:10px 20px;border-radius:10px;font-size:0.82rem;">
                Enroll Now
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="grid-column:1/-1;text-align:center;padding:80px;background:#fff;border-radius:20px;border:2px dashed #E2E8F0;">
          <i class="fas fa-book-open" style="font-size:3rem;color:#E2E8F0;margin-bottom:16px;"></i>
          <h3 style="color:#94a3b8;">Courses Are Being Curated</h3>
          <p style="color:#94a3b8;margin-top:8px;font-size:0.9rem;">Our academic team is handpicking world-class content. Check back very soon!</p>
          <a href="contact.php" class="btn-cta-primary" style="margin-top:24px;">Notify Me When Ready</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════
     MISSION / VISION / VALUES
══════════════════════════════════ -->
<section class="section" style="background:#fff;">
  <div class="container">
    <div style="text-align:center;max-width:640px;margin:0 auto 64px;">
      <h2 class="sec-heading">Our Purpose <span style="color:#00BFFF;">&amp; Promise</span></h2>
      <p class="sec-sub">Every feature we build is anchored in one belief: access to elite education should have no borders.</p>
    </div>
    <div class="grid-3" style="gap:28px;">
      <div class="mv-card">
        <div class="mv-icon-circle"><i class="fas fa-eye"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.25rem;margin-bottom:16px;">Our Vision</h3>
        <p style="color:#475569;line-height:1.8;font-size:0.93rem;">To become the world's most student‑first platform — where talent, not privilege, determines access to opportunity.</p>
      </div>
      <div class="mv-card" style="border-color:#00BFFF;background:#fff;">
        <div class="mv-icon-circle" style="background:rgba(255,140,0,0.09);color:#FF8C00;"><i class="fas fa-rocket"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.25rem;margin-bottom:16px;">Our Mission</h3>
        <p style="color:#475569;line-height:1.8;font-size:0.93rem;">To democratise elite education with AI‑powered mentorship, practical curriculum, and generous scholarship funding at an accessible scale.</p>
      </div>
      <div class="mv-card">
        <div class="mv-icon-circle"><i class="fas fa-heart"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.25rem;margin-bottom:16px;">Core Values</h3>
        <p style="color:#475569;line-height:1.8;font-size:0.93rem;">Academic Integrity · Innovation · Radical Inclusion · Practical Excellence. We don't just teach — we <strong>transform careers and lives</strong>.</p>
      </div>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>

<script>
/* ═══ COUNT-UP with IntersectionObserver ═══ */
function countUp(el) {
  const target = parseInt(el.getAttribute('data-count'));
  if (!target) return;
  const isSuffix = el.innerHTML.includes('%') ? '%' : '+';
  const duration = 2000;
  const t0 = performance.now();
  const ease = t => 1 - Math.pow(1 - t, 4);
  const sup  = el.querySelector('sup');
  function tick(now) {
    const p = Math.min((now - t0) / duration, 1);
    const v = Math.floor(ease(p) * target);
    el.childNodes[0].textContent = v.toLocaleString();
    if (p < 1) requestAnimationFrame(tick);
    else el.childNodes[0].textContent = target.toLocaleString();
  }
  requestAnimationFrame(tick);
}

const nums = document.querySelectorAll('[data-count]');
const io   = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { countUp(e.target); io.unobserve(e.target); } });
}, { threshold: 0.5 });
nums.forEach(n => io.observe(n));
</script>
</body>
</html>
