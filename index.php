<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';

// ── Live numbers straight from the database (no padding) ───────
$total_lessons = $total_tutorials = $total_paths = 0;

try {
    // 0. Fetch latest active scholarship (Isolated priority)
    $stmt = $pdo->query("SELECT * FROM scholarships WHERE (expiry_date >= CURDATE() OR expiry_date IS NULL) ORDER BY created_at DESC LIMIT 1");
    $featured_scholarship = $stmt->fetch();

    $db_students  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $db_courses   = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn();
    $db_tutors    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tutor' AND status = 'active'")->fetchColumn();

    $total_students = $db_students;
    $total_courses  = $db_courses;
    $total_tutors   = $db_tutors;
    $total_lessons  = (int)$pdo->query("SELECT COUNT(*) FROM lessons l JOIN courses c ON c.id = l.course_id WHERE c.status = 'published'")->fetchColumn();
    $total_tutorials = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published' AND is_open_tutorial = 1")->fetchColumn();
    try { $total_paths = (int)$pdo->query("SELECT COUNT(*) FROM learning_paths")->fetchColumn(); } catch (Throwable $e) { $total_paths = 0; }

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
    $total_students = $total_courses = $total_tutors = 0;
    $recent_courses = [];
    $featured_scholarship = $featured_scholarship ?? null; // Keep if already fetched
}

$user = isLoggedIn() ? currentUser() : null;
$enrolled_ids = ($user && $user['role'] === 'student') ? array_map('intval', $pdo->query("SELECT course_id FROM enrollments WHERE student_id = " . (int)$user['id'] . " AND status IN ('active','completed')")->fetchAll(PDO::FETCH_COLUMN)) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Skope Digital Academy – Knowledge Without Limits</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Skope Digital Academy – Learn Coding & Digital Skills Online in Kenya', 'path' => '', 'description' => 'Online academy in Kisumu, Kenya: free coding tutorials, self-paced and weekly classes in digital and income skills, scholarships and verifiable certificates.', 'keywords' => 'online courses Kenya, learn coding Kenya, digital skills, Kisumu, free tutorials, certificates, scholarships']); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
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

/* ── Hero photo (branded) ── */
.hv { position: relative; max-width: 500px; margin: 0 auto; width: 100%; animation: fadeUp .7s .15s ease both; }
.hv-blob { position: absolute; inset: 8% 4% 0 4%; border-radius: 42% 58% 46% 54% / 52% 44% 56% 48%; background: linear-gradient(140deg, #00BFFF 0%, #0080FF 45%, #FF8C00 100%); opacity: .95; box-shadow: 0 30px 70px -30px rgba(0,128,255,.6); }
.hv-ring { position: absolute; inset: 2% -2% -4% -2%; border-radius: 50%; border: 2px dashed rgba(0,128,255,.25); animation: hvSpin 40s linear infinite; }
.hv-photo { position: relative; display: block; width: 100%; height: auto; border-radius: 34px; box-shadow: 0 24px 60px -24px rgba(15,23,42,.45); border: 6px solid #fff; }
.hv-card { position: absolute; z-index: 2; display: flex; align-items: center; gap: 10px; padding: 10px 14px 10px 10px; border-radius: 14px; background: rgba(255,255,255,.96); box-shadow: 0 14px 34px -14px rgba(15,23,42,.35); font-family: 'Inter', sans-serif; white-space: nowrap; animation: hvFloat 5s ease-in-out infinite; }
.hv-card b { display: block; font-size: .82rem; color: #0f172a; }
.hv-card small { display: block; font-size: .7rem; color: #64748b; }
.hv-card img { border-radius: 9px; }
.hv-ic { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; font-size: .95rem; flex: none; }
.hv-c1 { top: 12%; left: -6%; }
.hv-c2 { bottom: 20%; right: -6%; animation-delay: -1.6s; }
.hv-c3 { bottom: -4%; left: 8%; animation-delay: -3.2s; }
@keyframes hvFloat { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-8px) } }
@keyframes hvSpin { to { transform: rotate(360deg) } }
@media (prefers-reduced-motion: reduce) { .hv-card, .hv-ring { animation: none; } }
@media (max-width: 1024px) { .hv { max-width: 420px; margin-top: 10px; } .hv-c1 { left: -2%; } .hv-c2 { right: -2%; } }
@media (max-width: 480px) { .hv { max-width: 330px; } .hv-card { padding: 8px 10px 8px 8px; } .hv-card b { font-size: .74rem; } .hv-card small { display: none; } .hv-ic, .hv-card img { width: 28px !important; height: 28px !important; } .hv-c1 { top: 6%; left: -4%; } .hv-c2 { right: -4%; bottom: 24%; } .hv-c3 { left: 4%; } }
.schol-wrap { max-width: 900px; margin-top: 40px; margin-bottom: 10px; }
.schol-wrap .schol-card { margin: 0; }

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
.skills-kicker { font-size: .72rem; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: #94a3b8; text-align: center; margin: 0 0 26px; }
.skill-row { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px 12px; max-width: 980px; margin: 0 auto; }
.skill-chip { display: inline-flex; align-items: center; gap: 9px; padding: 10px 16px; border-radius: 999px; background: #fff; border: 1px solid #e2e8f0;
  color: #0f172a; font: 600 .88rem Inter, sans-serif; text-decoration: none; box-shadow: 0 6px 16px -12px rgba(15,23,42,.35); transition: transform .2s, border-color .2s, box-shadow .2s; }
.skill-chip i { font-size: 1.1rem; width: 18px; text-align: center; }
.skill-chip:hover { transform: translateY(-2px); border-color: #93c5fd; box-shadow: 0 10px 22px -12px rgba(0,128,255,.45); color: #0f172a; }
@media (max-width: 480px) { .skill-row { gap: 8px; } .skill-chip { padding: 8px 12px; font-size: .8rem; } }
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
  padding: 80px 0; text-align: center; color: #fff;
  position: relative; overflow: hidden;
}
.achieve-banner::after {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle at 70% 30%, rgba(0,191,255,0.08) 0%, transparent 60%);
  pointer-events: none;
}
.achieve-grid {
  display: flex; justify-content: center; gap: 60px; flex-wrap: wrap;
  position: relative; z-index: 1;
}
.ach-num {
  font-family: 'Poppins', sans-serif;
  font-size: 3rem; font-weight: 900; line-height: 1; display: block;
  color: #00BFFF;
}
.ach-num sup { font-size: 1.3rem; }
.ach-lbl { font-size: 0.88rem; opacity: 0.75; margin-top: 10px; font-weight: 500; }

/* ═══════════ COURSE CARD (Elite Master Standard) ═══════════ */
.c-card {
  background: #fff; border: 1px solid #E2E8F0;
  border-radius: 20px; overflow: hidden;
  transition: 0.3s; display: flex; flex-direction: column;
  position: relative;
}
.c-card:hover { transform: translateY(-6px); box-shadow: 0 15px 35px rgba(0,0,0,0.06); border-color: var(--primary); }
.c-thumb { height: 180px; position: relative; overflow: hidden; background: #f1f5f9; }
.c-thumb img { width: 100%; height: 100%; object-fit: cover; transition: 0.4s; }
.c-card:hover .c-thumb img { transform: scale(1.03); }
.c-cat {
  position: absolute; top: 12px; left: 12px;
  background: rgba(255,255,255,0.98); color: var(--primary);
  padding: 6px 14px; border-radius: 10px;
  font-size: 0.62rem; font-weight: 900; text-transform: uppercase; letter-spacing: 1.2px;
}
.c-body { padding: 24px; flex-grow: 1; display: flex; flex-direction: column; }
.c-title { font-family: 'Poppins',sans-serif; font-size: 1.05rem; font-weight: 800; color: #0F172A; margin-bottom: 8px; line-height: 1.4; }
.c-meta { 
  display: flex; align-items: center; gap: 8px; font-size: 0.76rem; 
  color: #64748B; margin-bottom: 16px; font-weight: 600; 
  flex-wrap: wrap; line-height: 1.4;
}
.c-pills { 
  display: flex; gap: 10px; font-size: 0.72rem; color: #94a3b8; 
  font-weight: 700; margin-bottom: auto; flex-wrap: wrap;
}
.c-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 18px; margin-top: 18px; border-top: 1px solid #f1f5f9; }
.c-price { font-size: 1.2rem; font-weight: 900; color: #0f172a; }

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
@media (max-width: 1200px) {
  .hero-inner { gap: 40px; }
  .why-grid { grid-template-columns: repeat(2,1fr); }
}

@media (max-width: 991px) {
  .hero-inner { grid-template-columns: 1fr; text-align: center; }
  .hero-sub { margin-left: auto; margin-right: auto; }
  .hero-trust-badges, .cta-row, .stats-bar { justify-content: center; }
  .hero-headline { font-size: clamp(2.2rem, 6vw, 3.2rem); }
}

@media (max-width: 768px) {
  .hero-section { padding: 40px 0 40px; }
  .stats-bar { gap: 24px; padding-top: 24px; }
  .stat-num { font-size: 1.6rem; }
  .stat-lbl { font-size: 0.7rem; }
  
  .schol-card { padding: 32px 24px; }
  .schol-mini-stats { flex-direction: column; gap: 20px; }
  .schol-mini-stats div[style*="width:1px"] { display: none; }
  
  .ai-strip { padding: 16px; margin: 0; }
  .why-grid { grid-template-columns: 1fr; }
  .grid-3 { gap: 20px !important; }
  .navbar-logo img { height: 40px !important; }
}

@media (max-width: 480px) {
  .hero-headline { font-size: 2rem !important; margin-bottom: 16px; }
  .hero-sub { font-size: 0.95rem; }
  .btn-cta-primary, .btn-cta-outline { width: 100%; justify-content: center; }
  .achieve-grid { flex-direction: column; gap: 32px; }
  .ach-num { font-size: 2.4rem; }
  .schol-tag { font-size: 0.65rem; }
  .navbar-logo img { height: 32px !important; }
}
.c-enroll{padding:10px 18px!important;border-radius:10px!important;font-size:.82rem!important;border:0;display:inline-flex;align-items:center;gap:7px;white-space:nowrap;text-decoration:none}.c-enroll.is-enrolled{background:#10b981!important}
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

        <h1 class="hero-headline">
          Learn <span class="c-blue">Smart.</span><br>
          Achieve <span class="c-orange">More.</span>
        </h1>

        <p class="hero-sub">
          Learn in-demand coding and digital skills online: free interactive tutorials, self-paced or weekly classes, and certificates anyone can verify.
        </p>

        <!-- Trust badges -->
        <div class="hero-trust-badges">
          <span class="trust-badge cyan"><i class="fas fa-check-circle"></i> 100% Online</span>
          <span class="trust-badge orange"><i class="fas fa-clock"></i> Flexible Schedule</span>
          <span class="trust-badge green"><i class="fas fa-award"></i> Verifiable Certificates</span>
        </div>

        <div class="cta-row">
          <a href="courses" class="btn-cta-primary">
            <i class="fas fa-rocket"></i> Start Learning Free
          </a>
          <a href="register?role=tutor" class="btn-cta-outline">
            <i class="fas fa-chalkboard-teacher"></i> Become a Tutor
          </a>
        </div>

        <!-- LIVE STATS -->
        <div class="stats-bar">
          <div class="stat-chip">
            <span class="stat-num" data-count="<?= $total_courses ?>">0</span>
            <div class="stat-lbl"><span>📚</span> Courses</div>
          </div>
          <div class="stat-chip">
            <span class="stat-num" data-count="<?= $total_lessons ?>">0</span>
            <div class="stat-lbl"><span>📖</span> Lessons</div>
          </div>
          <div class="stat-chip">
            <span class="stat-num" data-count="<?= $total_tutorials ?>">0</span>
            <div class="stat-lbl"><span>💻</span> Free Coding Tutorials</div>
          </div>
          <div class="stat-chip">
            <span class="stat-num" data-count="<?= $total_paths ?>">0</span>
            <div class="stat-lbl"><span>🧭</span> Learning Paths</div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Learner photo, branded -->
      <div class="hv" aria-hidden="false">
        <div class="hv-blob"></div>
        <div class="hv-ring"></div>
        <picture>
          <source srcset="assets/images/hero-learner.webp" type="image/webp">
          <img class="hv-photo" src="assets/images/hero-learner.jpg" width="626" height="626" alt="A smiling young African learner holding a laptop with the Skope Digital Academy logo" fetchpriority="high">
        </picture>
        <div class="hv-card hv-c1"><span class="hv-ic" style="background:#e0f7ff;color:#0080FF"><i class="fas fa-code"></i></span><span><b>Free coding tutorials</b><small>Run code in your browser</small></span></div>
        <div class="hv-card hv-c2"><span class="hv-ic" style="background:#fff3e0;color:#FF8C00"><i class="fas fa-award"></i></span><span><b>Earn a certificate</b><small>Verifiable online</small></span></div>
        <div class="hv-card hv-c3"><img src="assets/pwa/icon-192.png" alt="" width="34" height="34"><span><b>Skope Digital Academy</b><small>Kisumu, Kenya</small></span></div>
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
      <a href="courses?search=<?= urlencode($name) ?>" class="ai-chip">
        <i class="<?= $ico ?>" style="color:<?= $clr ?>;font-size:0.85rem;"></i>
        <?= $name ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════
     SCHOLARSHIPS
══════════════════════════════════ -->
<div class="container schol-wrap">
      <div class="schol-card">
        <?php if($featured_scholarship): ?>
            <div class="schol-tag"><i class="fas fa-star"></i> Featured Program 2026</div>
            <h2 style="font-family:'Poppins',sans-serif; font-size:1.8rem; font-weight:900; color:#0f172a; line-height:1.15; margin-bottom:14px;">
              <?= htmlspecialchars($featured_scholarship['title']) ?>
            </h2>
            <p style="color:#475569; font-size:0.92rem; line-height:1.7; margin-bottom:24px;">
              <?= nl2br(htmlspecialchars($featured_scholarship['description'])) ?>
            </p>
            <a href="scholarships?id=<?= $featured_scholarship['id'] ?>" class="btn-cta-primary" style="width:100%; justify-content:center;">
              <i class="fas fa-graduation-cap"></i> View Active Scholarships
            </a>
            <div class="schol-deadline">
              <i class="fas fa-calendar-alt" style="color:#FF8C00;"></i>
              <span>Deadline: <strong style="color:#0f172a;"><?= $featured_scholarship['expiry_date'] ? date('M j, Y', strtotime($featured_scholarship['expiry_date'])) : 'Rolling Admission' ?></strong></span>
            </div>
            <div class="schol-mini-stats">
              <div>
                <div class="val" style="color:#00BFFF;">80%+</div>
                <div class="lbl">Funding Grade</div>
              </div>
              <div style="width:1px;background:#e2e8f0;"></div>
              <div>
                <div class="val" style="color:#FF8C00;">KES <?= number_format($featured_scholarship['amount']/1000) ?>k</div>
                <div class="lbl">Max Award</div>
              </div>
              <div style="width:1px;background:#e2e8f0;"></div>
              <div>
                <div class="val" style="color:#10b981;"><?= $total_courses ?>+</div>
                <div class="lbl">Eligible Paths</div>
              </div>
            </div>
        <?php else: ?>
            <div class="schol-tag"><i class="fas fa-info-circle"></i> Scholarships</div>
            <h2 style="font-family:'Poppins',sans-serif; font-size:1.8rem; font-weight:900; color:#0f172a; line-height:1.15; margin-bottom:14px;">
              No open round<br>right now.
            </h2>
            <p style="color:#475569; font-size:0.97rem; line-height:1.75; margin-bottom:24px;">
              New scholarship rounds are announced on our scholarships page and to registered learners by email. Create a free account so you hear first, and start with the free tutorials today.
            </p>
            <a href="register" class="btn-cta-primary" style="width:100%; justify-content:center;">
              <i class="fas fa-user-plus"></i> Create a free account
            </a>
        <?php endif; ?>
      </div>
</div>

<!-- ══════════════════════════════════
     TRUST STRIP / PARTNERS
══════════════════════════════════ -->
<section class="trust-strip">
  <div class="container">
    <p class="skills-kicker">Skills &amp; tools you'll learn here</p>
    <div class="skill-row">
      <?php
      // Real courses and tutorials on the academy (each chip opens it)
      $skills = [
        ['fab fa-html5', '#e34f26', 'HTML', 'learn/html'],
        ['fab fa-css3-alt', '#1572b6', 'CSS', 'learn/css'],
        ['fab fa-js', '#d4a20a', 'JavaScript', 'learn/javascript'],
        ['fab fa-python', '#3776ab', 'Python', 'learn/python'],
        ['fab fa-php', '#777bb4', 'PHP', 'learn/php'],
        ['fas fa-database', '#00758f', 'SQL', 'learn/sql'],
        ['fas fa-file-excel', '#1d6f42', 'Word, Excel &amp; PowerPoint', 'lab/computer-packages'],
        ['fas fa-bullhorn', '#FF8C00', 'Digital Marketing', 'lab/digital-marketing'],
        ['fab fa-google', '#4285f4', 'Google Ads &amp; Analytics', 'lab/google-ads-analytics'],
        ['fas fa-palette', '#d946ef', 'Graphic Design', 'lab/graphic-design'],
        ['fas fa-robot', '#0080FF', 'Artificial Intelligence', 'lab/intro-to-ai'],
        ['fas fa-shield-halved', '#ef4444', 'Cybersecurity', 'lab/intro-cybersecurity'],
      ];
      foreach ($skills as [$ico, $clr, $label, $href]): ?>
        <a class="skill-chip" href="<?= $href ?>"><i class="<?= $ico ?>" style="color:<?= $clr ?>"></i><span><?= $label ?></span></a>
      <?php endforeach; ?>
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
      <h2 class="sec-heading">Why Learners<br>Choose Skope</h2>
      <p class="sec-sub">Clear lessons, real practice and help when you get stuck. No jargon, no fluff.</p>
    </div>
    <div class="why-grid">
      <div class="why-card">
        <div class="why-icon" style="background:rgba(0,191,255,0.09);"><i class="fas fa-bolt" style="color:#00BFFF;"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:12px;">Learn by Doing</h3>
        <p style="color:#475569;font-size:0.88rem;line-height:1.75;">Edit and run code in your browser, complete practice tasks and build projects, with an AI mentor that explains and gives hints when you're stuck.</p>
      </div>
      <div class="why-card">
        <div class="why-icon" style="background:rgba(255,140,0,0.09);"><i class="fas fa-certificate" style="color:#FF8C00;"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:12px;">Verifiable Certificates</h3>
        <p style="color:#475569;font-size:0.88rem;line-height:1.75;">Finish a course to earn a certificate with a verification code anyone can check online, ready to share on LinkedIn or your CV.</p>
      </div>
      <div class="why-card">
        <div class="why-icon" style="background:rgba(16,185,129,0.09);"><i class="fas fa-users" style="color:#10b981;"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:12px;">Learn Your Way</h3>
        <p style="color:#475569;font-size:0.88rem;line-height:1.75;">Go self-paced, or join a weekly track with a cohort, deadlines and tests. Works on your phone, even on weak data.</p>
      </div>
      <div class="why-card">
        <div class="why-icon" style="background:rgba(139,92,246,0.09);"><i class="fas fa-hand-holding-heart" style="color:#8b5cf6;"></i></div>
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;margin-bottom:12px;">Scholarship Access</h3>
        <p style="color:#475569;font-size:0.88rem;line-height:1.75;">Your finances shouldn't limit your potential. Many courses are free to learn, and scholarship rounds help talented learners with fees.</p>
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
      What you can learn here — live numbers
    </p>
    <div class="achieve-grid">
      <div>
        <span class="ach-num" data-count="<?= $total_courses ?>"><?= number_format($total_courses) ?></span>
        <div class="ach-lbl">📚 Courses</div>
      </div>
      <div>
        <span class="ach-num" data-count="<?= $total_lessons ?>"><?= number_format($total_lessons) ?></span>
        <div class="ach-lbl">📖 Lessons</div>
      </div>
      <div>
        <span class="ach-num" data-count="<?= $total_tutorials ?>"><?= number_format($total_tutorials) ?></span>
        <div class="ach-lbl">💻 Free Coding Tutorials</div>
      </div>
      <div>
        <span class="ach-num" data-count="<?= $total_paths ?>"><?= number_format($total_paths) ?></span>
        <div class="ach-lbl">🧭 Learning Paths</div>
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
      <a href="courses" style="display:inline-flex;align-items:center;gap:8px;color:#00BFFF;font-weight:700;text-decoration:none;font-size:0.92rem;border:1.5px solid #00BFFF;padding:10px 22px;border-radius:10px;transition:0.2s;"
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
              <?php if ((int)($c['enrolled_count'] ?? 0) > 0): ?><span><i class="fas fa-users" style="color:#00BFFF;"></i> <?= number_format((int)$c['enrolled_count']) ?> enrolled</span><?php endif; ?>
              <?php if ((float)($c['duration_hours'] ?? 0) > 0): ?><span><i class="fas fa-clock" style="color:#94a3b8;"></i> <?= (float)$c['duration_hours'] ?>h</span><?php endif; ?>
              <span><i class="fas fa-award" style="color:#10b981;"></i> Certificate</span>
            </div>
            <div class="c-footer">
              <?php $from = course_from_price($c); ?>
              <span class="c-price"><?= $from <= 0 ? 'Free' : e(money($from)) ?></span>
              <?= sda_enroll_button($c, in_array((int)$c['id'], $enrolled_ids, true), 'btn-cta-primary c-enroll') ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div style="grid-column:1/-1;text-align:center;padding:80px;background:#fff;border-radius:20px;border:2px dashed #E2E8F0;">
          <i class="fas fa-book-open" style="font-size:3rem;color:#E2E8F0;margin-bottom:16px;"></i>
          <h3 style="color:#94a3b8;">Courses Are Being Curated</h3>
          <p style="color:#94a3b8;margin-top:8px;font-size:0.9rem;">New courses are on the way. Check back soon, or start with the free tutorials.</p>
          <a href="contact" class="btn-cta-primary" style="margin-top:24px;">Notify Me When Ready</a>
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

<!-- ══════════════════════════════════
     JOIN BANNER
══════════════════════════════════ -->
<section class="join-banner-sec">
  <div class="container">
    <a class="join-banner" href="<?= $user ? 'courses' : 'register' ?>" aria-label="<?= $user ? 'Browse courses' : 'Join Skope Digital Academy today' ?>">
      <picture><source type="image/webp" srcset="assets/images/promo/join-banner-1600.webp 1600w, assets/images/promo/join-banner-900.webp 900w" sizes="(max-width: 900px) 100vw, 1200px"><img src="assets/images/promo/join-banner-1600.jpg" alt="Skope Digital Academy: Build skills for a digital future. Expert-led training, hands-on projects and flexible online learning. Join today at skopedigital.co.ke." class="" loading="lazy" decoding="async"></picture>
    </a>
  </div>
</section>
<style>
.join-banner-sec { padding: 10px 0 70px; background: #fff; }
.join-banner { display: block; border-radius: 24px; overflow: hidden; box-shadow: 0 30px 60px -34px rgba(15,23,42,.45); transition: transform .25s, box-shadow .25s; }
.join-banner:hover { transform: translateY(-3px); box-shadow: 0 36px 70px -34px rgba(0,128,255,.5); }
.join-banner img { display: block; width: 100%; height: auto; aspect-ratio: 16 / 9; }
@media (max-width: 600px) { .join-banner-sec { padding: 0 0 48px; } .join-banner { border-radius: 16px; } }
</style>

<!-- ══════════════════════════════════
     SUPPORT OUR LEARNERS (CTA)
══════════════════════════════════ -->
<section class="section" style="background: linear-gradient(135deg, #003274ff 0%, #0c1f40 100%); color: white; overflow: hidden; position: relative;">
  <div style="position: absolute; inset: 0; background: radial-gradient(circle at 70% 30%, rgba(0,191,255,0.15) 0%, transparent 60%); pointer-events: none;"></div>
  <div class="container" style="position: relative; z-index: 5;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 40px; flex-wrap: wrap;">
      <div style="max-width: 600px;">
        <div style="display: inline-block; background: rgba(255,255,255,0.1); padding: 6px 14px; border-radius: 50px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 20px;">Empowerment Initiative</div>
        <h2 style="font-family: 'Poppins', sans-serif; font-weight: 950; font-size: clamp(1.8rem, 5vw, 2.8rem); margin-bottom: 16px; line-height: 1.1; letter-spacing: -1.5px;">Support Our <span style="color: #00BFFF;">Learners.</span></h2>
        <p style="font-size: 1.1rem; opacity: 0.85; line-height: 1.6; margin-bottom: 0;">Your contribution directly funds tuition and certifications for talented students in need. Join us in breaking the barriers to elite digital education.</p>
      </div>
      <div class="donate-cta-wrap" style="display: flex; gap: 16px;">
        <a href="donate" class="btn-premium donate-cta">Donate Now <i class="fas fa-heart"></i></a>
        <style>
          .donate-cta { display: inline-flex; align-items: center; justify-content: center; gap: 10px; background: #00BFFF; color: #fff !important; text-decoration: none;
            font: 800 1.05rem 'Poppins', sans-serif; border: none; padding: 18px 44px; border-radius: 18px; box-shadow: 0 10px 30px rgba(0,191,255,0.25); transition: transform .2s, background .2s; }
          .donate-cta:hover { background: #0099d6; transform: translateY(-2px); }
          .donate-cta i { color: #fff; }
          @media (max-width: 600px) { .donate-cta-wrap { width: 100%; } .donate-cta { width: 100%; padding: 16px 24px; } }
        </style>
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
    if(el.childNodes[0].nodeType === 3) {
      el.childNodes[0].textContent = v.toLocaleString();
    } else {
      el.innerText = v.toLocaleString() + isSuffix;
    }
    if (p < 1) requestAnimationFrame(tick);
    else {
      if(el.childNodes[0].nodeType === 3) el.childNodes[0].textContent = target.toLocaleString();
      else el.innerText = target.toLocaleString() + isSuffix;
    }
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
