<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Fetch active scholarships
try {
    $stmt = $pdo->query("SELECT * FROM scholarships WHERE (expiry_date IS NULL OR expiry_date >= CURDATE()) ORDER BY created_at DESC");
    $scholarships = $stmt->fetchAll();
} catch (Exception $e) { $scholarships = []; }

$user = isLoggedIn() ? currentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Apply for scholarships at Skope Digital Academy. We offer merit-based funding and a direct path to a tech career for talented individuals in Kenya.">
<title>Scholarships & Funding – Skope Digital Academy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
<style>
/* ── Reset & Base ── */
*, *::before, *::after { box-sizing: border-box; }
body { background: #fff; color: #1e293b; font-family: 'Inter', sans-serif; overflow-x: hidden; }

/* ── Navbar Fix ── */
.navbar {
  background: rgba(255,255,255,0.97) !important;
  border-bottom: 1px solid #e2e8f0 !important;
  backdrop-filter: blur(16px) !important;
}
.top-bar { background: #f8fafc !important; border-bottom: 1px solid #e2e8f0 !important; }

/* ── HERO ── */
.sch-hero {
  padding: 130px 0 100px;
  background: linear-gradient(160deg, #f0f9ff 0%, #e8f4fd 50%, #fff8ee 100%);
  position: relative; overflow: hidden; text-align: center;
}
.sch-hero::before {
  content: ''; position: absolute; top: -100px; right: -80px;
  width: 500px; height: 500px; border-radius: 50%;
  background: radial-gradient(circle, rgba(0,191,255,0.1) 0%, transparent 65%);
  pointer-events: none;
}
.sch-hero::after {
  content: ''; position: absolute; bottom: -60px; left: -60px;
  width: 380px; height: 380px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,140,0,0.08) 0%, transparent 70%);
  pointer-events: none;
}
.sch-hero-inner { position: relative; z-index: 2; max-width: 800px; margin: 0 auto; }
.sch-tag {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(0,191,255,0.1); color: #0099cc;
  border: 1px solid rgba(0,191,255,0.3);
  padding: 7px 20px; border-radius: 999px;
  font-size: 0.73rem; font-weight: 800;
  letter-spacing: 1.5px; text-transform: uppercase;
  margin-bottom: 24px;
}
.sch-hero h1 {
  font-family: 'Poppins', sans-serif;
  font-size: clamp(2.4rem, 5vw, 3.8rem);
  font-weight: 900; line-height: 1.1;
  color: #0f172a; margin-bottom: 20px;
}
.sch-hero h1 span { color: #00BFFF; }
.sch-hero p {
  font-size: 1.1rem; color: #475569;
  line-height: 1.8; max-width: 650px; margin: 0 auto 48px;
}

/* ── STATS STRIP ── */
.sch-stats {
  display: flex; justify-content: center; gap: 60px; flex-wrap: wrap;
  margin-top: 20px;
}
.stat-box { text-align: center; }
.stat-val { font-family: 'Poppins', sans-serif; font-size: 2.2rem; font-weight: 900; color: #0f172a; line-height: 1; }
.stat-val span { color: #00BFFF; }
.stat-lbl { font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 8px; }

/* ── SCHOLARSHIP CARDS ── */
.sch-section { padding: 100px 0; background: #fff; }
.sch-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 32px; }
.sch-card {
  background: #fff; border: 1px solid #e2e8f0;
  border-radius: 28px; padding: 44px;
  transition: all 0.35s cubic-bezier(0.165, 0.84, 0.44, 1);
  position: relative; display: flex; flex-direction: column;
  box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.sch-card:hover { border-color: #00BFFF; transform: translateY(-10px); box-shadow: 0 25px 50px rgba(0,191,255,0.1); }
.sch-card-tag {
  align-self: flex-start; background: rgba(0,191,255,0.08);
  color: #00BFFF; padding: 5px 14px; border-radius: 8px;
  font-size: 0.7rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: 1px; margin-bottom: 24px;
}
.sch-card-icon {
  width: 56px; height: 56px; background: #f8fafc;
  border-radius: 14px; display: flex; align-items: center; justify-content: center;
  color: #00BFFF; font-size: 1.6rem; margin-bottom: 24px;
}
.sch-card h3 { font-family: 'Poppins',sans-serif; font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 14px; }
.sch-card p { color: #475569; line-height: 1.75; font-size: 0.95rem; margin-bottom: 32px; }

.sch-footer {
  margin-top: auto; padding-top: 32px; border-top: 1px solid #f1f5f9;
  display: flex; justify-content: space-between; align-items: center;
}
.sch-amount-lbl { font-size: 0.68rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; }
.sch-amount-val { font-size: 1.25rem; font-weight: 800; color: #FF8C00; }
.btn-apply {
  background: #00BFFF; color: #fff; padding: 12px 24px;
  border-radius: 12px; font-weight: 800; text-decoration: none;
  font-size: 0.9rem; transition: 0.3s; box-shadow: 0 4px 12px rgba(0,191,255,0.2);
}
.btn-apply:hover { background: #0099d6; transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,191,255,0.3); }

/* ── SCHOLARSHIP KIT ── */
.kit-section { padding: 100px 0; background: #f8fafc; }
.kit-card {
  background: white; border-radius: 32px; padding: 56px;
  border: 1px solid #e2e8f0; display: grid;
  grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;
  box-shadow: 0 20px 50px rgba(0,0,0,0.05);
}
.kit-icon-stack { position: relative; width: 100%; max-width: 320px; margin: 0 auto; }
.kit-img { width: 100%; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
.kit-badge {
    position: absolute; top: -20px; right: -20px;
    background: #FF8C00; color: white; padding: 12px 20px;
    border-radius: 12px; font-weight: 900; box-shadow: 0 8px 20px rgba(255,140,0,0.3);
    text-transform: uppercase; font-size: 0.8rem;
}
.kit-heading { font-family: 'Poppins',sans-serif; font-size: 2.2rem; font-weight: 800; color: #0f172a; margin-bottom: 24px; line-height: 1.2; }
.kit-heading span { color: #FF8C00; }
.kit-list { list-style: none; padding: 0; margin-bottom: 36px; display: grid; gap: 14px; }
.kit-list li { display: flex; align-items: center; gap: 12px; font-size: 0.97rem; color: #475569; }
.kit-list li i { color: #00BFFF; font-size: 1rem; }

/* ── MODAL ── */
.apply-modal {
  display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.85);
  backdrop-filter: blur(12px); z-index: 2000; align-items: center; justify-content: center; padding: 20px;
}
.apply-modal-content {
  background: #fff; border-radius: 32px; width: 100%; max-width: 600px; padding: 48px;
  position: relative; box-shadow: 0 30px 60px rgba(0,0,0,0.25);
}
.close-modal {
  position: absolute; top: 24px; right: 24px; background: #f1f5f9; border: none;
  width: 40px; height: 40px; border-radius: 50%; color: #64748b; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: 0.3s;
}
.close-modal:hover { background: #e2e8f0; color: #0f172a; }

/* ── Animations ── */
[data-aos] { opacity: 0; transform: translateY(28px); transition: all 0.6s ease; }
[data-aos].visible { opacity: 1; transform: translateY(0); }

/* ══ MOBILE COMPONENT OVERRIDES ══ */
@media (max-width: 991px) {
  .sch-grid { grid-template-columns: 1fr; }
  .kit-card { grid-template-columns: 1fr; padding: 40px; text-align: center; }
  .kit-list { justify-content: center; text-align: left; }
  .sch-stats { flex-direction: column; gap: 32px; align-items: center; }
  .sch-stats div[style*="width: 1px"] { display: none; }
}

@media (max-width: 850px) {
  #donationModal .apply-modal-content { padding: 40px; border-radius: 32px; }
  #donationModal .apply-modal-content > div { grid-template-columns: 1fr !important; gap: 40px !important; }
}

@media (max-width: 768px) {
  .sch-hero { padding: 80px 0 60px; }
  .sch-hero h1 { font-size: 2.2rem; }
  .sch-hero p { font-size: 1rem; }
  .sch-card { padding: 32px 24px; }
}

@media (max-width: 480px) {
  .sch-amount-val { font-size: 1.1rem; }
  .btn-apply { padding: 10px 18px; font-size: 0.82rem; }
  .apply-modal-content { padding: 32px 20px !important; }
  .apply-modal-content h2 { font-size: 1.6rem !important; }
  #sch-paystack-btn { height: 64px !important; font-size: 1rem !important; }
  .sch-amount-chip { padding: 12px !important; font-size: 0.95rem !important; }
}
</style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<!-- ══ HERO ══ -->
<header class="sch-hero">
  <div class="sch-hero-inner" data-aos>
    <div class="sch-tag"><i class="fas fa-hand-holding-heart"></i> Empowerment Initiative</div>
    <h1>Unlock Your Learning <span>Future</span>.</h1>
    <p>We bridge the gap between ambition and opportunity. Skope Digital Academy offers merit-based scholarships to ensure Kenyan talent isn't limited by financial barriers.</p>
    
    <div class="sch-stats">
      <div class="stat-box">
        <div class="stat-val">250<span>+</span></div>
        <div class="stat-lbl">Active Scholars</div>
      </div>
      <div style="width: 1px; background: #e2e8f0;" class="hide-mobile"></div>
      <div class="stat-box">
        <div class="stat-val">100<span>%</span></div>
        <div class="stat-lbl">Merit Based</div>
      </div>
    </div>
  </div>
</header>

<!-- ══ AVAILABLE OPPORTUNITIES ══ -->
<section class="sch-section">
  <div class="container">
    <div style="text-align: center; max-width: 600px; margin: 0 auto 64px;" data-aos>
      <div style="font-size: 0.72rem; font-weight: 800; color: #00BFFF; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 14px;">Opportunities</div>
      <h2 style="font-family:'Poppins',sans-serif; font-size: 2.2rem; font-weight: 800; color: #0f172a;">Active Scholarships</h2>
      <p style="color: #64748b; font-size: 1rem;">Apply for the funding that matches your career path and certification goals.</p>
    </div>

    <div class="sch-grid">
      <?php if(empty($scholarships)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 60px; background: #f8fafc; border-radius: 20px;">
          <i class="fas fa-info-circle" style="font-size: 2rem; color: #94a3b8; margin-bottom: 16px;"></i>
          <p style="color: #64748b;">No active scholarship cycles at the moment. Check back soon!</p>
        </div>
      <?php else: ?>
        <?php foreach($scholarships as $s): ?>
        <div class="sch-card" data-aos>
          <div class="sch-card-tag">Limited Slots</div>
          <div class="sch-card-icon"><i class="fas fa-award"></i></div>
          <h3><?= htmlspecialchars($s['title']) ?></h3>
          <p><?= htmlspecialchars($s['description']) ?></p>
          
          <div class="sch-footer">
            <div>
              <div class="sch-amount-lbl">Funding Value</div>
              <div class="sch-amount-val"><?= $s['amount'] > 0 ? 'KES '.number_format($s['amount']) : 'Full Coverage' ?></div>
            </div>
            <button class="btn-apply" onclick="openApplication('<?= $s['id'] ?>', '<?= addslashes($s['title']) ?>')">Apply Now <i class="fas fa-arrow-right" style="margin-left: 8px;"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- ══ SCHOLARSHIP KIT ══ -->
<section class="kit-section">
  <div class="container">
    <div class="kit-card" data-aos>
      <div class="kit-icon-stack">
        <img src="assets/images/elearning login bg (1).jpeg" class="kit-img" alt="Scholarship Kit">
        <div class="kit-badge">Free Access</div>
      </div>
      <div>
        <h2 class="kit-heading">Support Africa's Next <span>Tech Talents</span></h2>
        <p style="color: #475569; margin-bottom: 32px; font-size: 1.05rem;">Your contributions directly fund tuition, globally-verified certifications, and industry tools for deserving students from underprivileged backgrounds.</p>
        <ul class="kit-list">
          <li><i class="fas fa-check-circle"></i> 100% of funds go towards student certifications</li>
          <li><i class="fas fa-check-circle"></i> Support high-impact digital literacy programs</li>
          <li><i class="fas fa-check-circle"></i> Directly sponsor a deserving student's future</li>
          <li><i class="fas fa-check-circle"></i> Receive impact reports on your contributions</li>
        </ul>
        <button onclick="openDonationModal()" class="btn btn-hero-primary" style="background: #FF8C00; color: white; border: none; box-shadow: 0 10px 25px rgba(255,140,0,0.3); cursor: pointer; padding: 18px 40px; border-radius: 50px; font-family: 'Poppins', sans-serif; font-weight: 700;">
          <i class="fas fa-heart"></i> Donate to Support Learners
        </button>
      </div>
    </div>
  </div>
</section>

<!-- Application Modal -->
<div class="apply-modal" id="applyModal" onclick="if(event.target==this) closeApplication()">
  <div class="apply-modal-content">
    <button class="close-modal" onclick="closeApplication()"><i class="fas fa-times"></i></button>
    <div style="margin-bottom: 32px;">
      <div style="color: #00BFFF; font-weight: 800; text-transform: uppercase; font-size: 0.73rem; letter-spacing: 2px; margin-bottom: 8px;">Scholarship portal</div>
      <h2 style="font-family:'Poppins',sans-serif; font-size: 1.8rem; font-weight: 800; color: #0f172a; line-height: 1.2;" id="scholarshipTitle">Loading Opportunity...</h2>
    </div>

    <?php if(!$user): ?>
      <div style="text-align: center; padding: 10px 0;">
        <div style="width: 72px; height: 72px; background: rgba(0,191,255,0.08); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #00BFFF; font-size: 1.8rem;">
          <i class="fas fa-user-shield"></i>
        </div>
        <h4 style="font-family:'Poppins',sans-serif; font-weight: 800; font-size: 1.15rem; color: #0f172a; margin-bottom: 12px;">Authentication Required</h4>
        <p style="color: #64748b; margin-bottom: 32px; line-height: 1.6; font-size: 0.95rem;">To track your application and verify your academic credits, you must be signed in to your student account.</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
          <a href="login.php" class="btn btn-primary">Sign In</a>
          <a href="register.php" class="btn btn-ghost" style="border: 1px solid #e2e8f0; color: #64748b;">Create Account</a>
        </div>
      </div>
    <?php else: ?>
      <form action="apply-handler.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="scholarship_id" id="modal_sid">
        <div style="margin-bottom: 24px;">
          <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #475569; margin-bottom: 10px;">Why do you deserve this scholarship?</label>
          <textarea name="sop" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; min-height: 120px; font-family: inherit; resize: none;" placeholder="Detail your academic background and financial need..." required></textarea>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
          <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #475569; margin-bottom: 10px;">Current Level</label>
            <input type="text" name="background" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px;" placeholder="e.g. Diploma" required>
          </div>
          <div>
            <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #475569; margin-bottom: 10px;">Academic ID</label>
            <input type="file" name="document" style="width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 11px; font-size: 0.8rem;" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width: 100%; height: 58px; border-radius: 14px; font-family: 'Poppins', sans-serif; font-weight: 800;">
          Submit Application <i class="fas fa-paper-plane" style="margin-left: 10px;"></i>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Donation Modal -->
<div class="apply-modal" id="donationModal" onclick="if(event.target==this) closeDonationModal()">
  <div class="apply-modal-content" style="max-width: 900px; padding: 64px; border-radius: 40px;">
    <button class="close-modal" onclick="closeDonationModal()"><i class="fas fa-times"></i></button>
    
    <div style="display: grid; grid-template-columns: 1fr 1.1fr; gap: 60px; align-items: center;">
      <!-- Left: Brand/Context -->
      <div style="text-align: left;">
        <div style="width: 80px; height: 80px; background: rgba(0,191,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 32px; color: #00BFFF; font-size: 2.2rem;">
          <i class="fas fa-hand-holding-heart"></i>
        </div>
        <h2 style="font-family:'Poppins',sans-serif; font-size: 2.8rem; font-weight: 900; color: #0f172a; margin-bottom: 20px; line-height: 1.1; letter-spacing: -1.5px;">Support Our <span style="color: #00BFFF;">Learners.</span></h2>
        <p style="color: #64748b; line-height: 1.7; font-size: 1.1rem; margin-bottom: 0;">Your contribution directly funds tuition, world-class certifications, and digital literacy tools for talented students in need.</p>
      </div>

      <!-- Right: Form -->
      <div style="background: white; padding: 48px; border-radius: 32px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); border: 1px solid #eef2f6; text-align: left;">
        <label style="display: block; font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 20px; letter-spacing: 1.5px;">Select Amount (KES)</label>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
          <div class="sch-amount-chip" onclick="setDonationAmount(500, this)" style="background: #f8fafc; border: 2px solid #f1f5f9; padding: 18px; border-radius: 16px; font-weight: 800; cursor: pointer; text-align: center; transition: 0.3s; font-family: 'Poppins', sans-serif; font-size: 1.1rem; color: #1e293b;">500</div>
          <div class="sch-amount-chip active" onclick="setDonationAmount(1000, this)" style="background: #f8fafc; border: 2px solid #00BFFF; color: #00BFFF; padding: 18px; border-radius: 16px; font-weight: 800; cursor: pointer; text-align: center; transition: 0.3s; font-family: 'Poppins', sans-serif; font-size: 1.1rem;">1,000</div>
          <div class="sch-amount-chip" onclick="setDonationAmount(5000, this)" style="background: #f8fafc; border: 2px solid #f1f5f9; padding: 18px; border-radius: 16px; font-weight: 800; cursor: pointer; text-align: center; transition: 0.3s; font-family: 'Poppins', sans-serif; font-size: 1.1rem; color: #1e293b;">5,000</div>
        </div>
        
        <div style="margin-bottom: 24px;">
          <input type="number" id="sch-custom-amount" style="width: 100%; padding: 20px 24px; border-radius: 16px; border: 2px solid #f1f5f9; font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 1.4rem; outline: none; background: white; transition: 0.3s; color: #1e293b;" placeholder="0" value="1000">
        </div>

        <?php if(!$user): ?>
        <input type="email" id="sch-donor-email" style="width: 100%; padding: 20px 24px; border-radius: 16px; border: 2px solid #f1f5f9; margin-bottom: 24px; font-family: inherit; font-weight: 600; font-size: 1rem; outline: none; background: white; color: #1e293b;" placeholder="Your Email Address" required>
        <?php endif; ?>

        <button id="sch-paystack-btn" onclick="payWithPaystack()" style="width: 100%; height: 78px; background: #00BFFF; color: white; border: none; border-radius: 20px; font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 1.25rem; cursor: pointer; transition: 0.3s; box-shadow: 0 15px 35px rgba(0,191,255,0.3); display: flex; align-items: center; justify-content: center; gap: 14px;">
          Donate with Paystack <i class="fas fa-heart"></i>
        </button>
        
        <div style="margin-top: 28px; font-size: 0.8rem; color: #94a3b8; text-align: center; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; opacity: 0.8;">
          <i class="fas fa-lock" style="font-size: 0.7rem;"></i> Secured by Paystack.
        </div>
      </div>
    </div>
  </div>
</div>
    </div>
  </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>

<?php require_once 'includes/footer.php'; ?>

<script>
function setDonationAmount(amt, el) {
    document.querySelectorAll('.sch-amount-chip').forEach(c => {
        c.style.background = '#f8fafc';
        c.style.borderColor = '#e2e8f0';
        c.style.color = '#0f172a';
        c.classList.remove('active');
    });
    el.style.background = 'rgba(0,191,255,0.05)';
    el.style.borderColor = '#00BFFF';
    el.style.color = '#00BFFF';
    el.classList.add('active');
    document.getElementById('sch-custom-amount').value = amt;
}

function payWithPaystack() {
    const amount = document.getElementById('sch-custom-amount').value;
    const email = "<?= $user ? $user['email'] : '' ?>" || document.getElementById('sch-donor-email')?.value;
    const btn = document.getElementById('sch-paystack-btn');

    if (!amount || amount < 50) {
        alert("Please enter a valid amount (Min KES 50)");
        return;
    }
    if (!email || !email.includes('@')) {
        alert("Please provide a valid email address.");
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing...';

    let handler = PaystackPop.setup({
        key: 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', // Replace with your Public Key
        email: email,
        amount: amount * 100,
        currency: 'KES',
        ref: 'DON-' + Math.floor((Math.random() * 1000000000) + 1),
        metadata: {
            custom_fields: [
                { display_name: "Payment Type", variable_name: "payment_type", value: "donation" },
                { display_name: "Source", variable_name: "source", value: "scholarship_page" }
            ]
        },
        callback: function(response) {
            window.location.href = "verify-payment.php?reference=" + response.reference + "&type=donation&email=" + encodeURIComponent(email);
        },
        onClose: function() {
            btn.disabled = false;
            btn.innerHTML = 'Donate with Paystack <i class="fas fa-heart"></i>';
        }
    });
    handler.openIframe();
}

function openDonationModal() {
    document.getElementById('donationModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeDonationModal() {
    document.getElementById('donationModal').style.display = 'none';
    document.body.style.overflow = '';
}
function openApplication(id, title) {
    document.getElementById('modal_sid').value = id;
    document.getElementById('scholarshipTitle').textContent = title;
    document.getElementById('applyModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeApplication() {
    document.getElementById('applyModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Scroll animation observer
const els = document.querySelectorAll('[data-aos]');
const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if(e.isIntersecting) { e.target.classList.add('visible'); }
    });
}, { threshold: 0.15 });
els.forEach(el => io.observe(el));

// Handle Toast Message
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('msg') === 'success') {
        if(typeof SDAC !== 'undefined') {
            SDAC.showToast('Your scholarship application has been received. Our board will review your credentials shortly.', 'success');
        } else {
            alert('Your scholarship application has been received. Our board will review your credentials shortly.');
        }
        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>

</body>
</html>
