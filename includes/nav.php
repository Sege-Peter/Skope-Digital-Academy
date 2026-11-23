<?php $user = isLoggedIn() ? currentUser() : null;
$navCur = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
if ($navCur === 'course-details') $navCur = 'courses';
if (in_array($navCur, ['careers-apply', 'careers-guide', 'careers-practice'], true)) $navCur = 'careers';
$navAct = fn($p) => $navCur === $p ? ' class="active" aria-current="page"' : ''; ?>

<!-- Top Bar -->
<div class="top-bar" style="background: #f8fafc; border-bottom: 1px solid #eee; padding: 10px 0; font-size: 0.85rem; color: #64748b;">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; gap: 24px; flex-wrap: wrap;">
            <span style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-map-marker-alt" style="color: #FF8C00;"></i> Kisumu, Kenya</span>
            <span style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-envelope" style="color: #FF8C00;"></i> info@skopedigital.co.ke</span>
        </div>
        <div style="display: flex; gap: 16px; align-items: center;">
            <a href="#" style="color: #64748b; transition: 0.3s;" onmouseover="this.style.color='#FF8C00'" onmouseout="this.style.color='#64748b'"><i class="fab fa-facebook-f"></i></a>
            <a href="#" style="color: #64748b; transition: 0.3s;" onmouseover="this.style.color='#FF8C00'" onmouseout="this.style.color='#64748b'"><i class="fab fa-twitter"></i></a>
            <a href="#" style="color: #64748b; transition: 0.3s;" onmouseover="this.style.color='#FF8C00'" onmouseout="this.style.color='#64748b'"><i class="fab fa-instagram"></i></a>
            <a href="#" style="color: #64748b; transition: 0.3s;" onmouseover="this.style.color='#FF8C00'" onmouseout="this.style.color='#64748b'"><i class="fab fa-youtube"></i></a>
        </div>
    </div>
</div>

<nav class="navbar" id="navbar">
  <div class="navbar-inner">
    
    <!-- Mobile Hamburger -->
    <button type="button" class="nav-hamburger" id="navToggle" aria-label="Open menu" aria-controls="mobileMenu" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Logo -->
    <a href="./" class="navbar-logo" style="display: flex; align-items: center; gap: 15px; text-decoration: none;">
      <img src="assets/images/skope-logo-512.png" alt="Skope Digital" style="height: 54px;">
      <div style="line-height: 1.1;">
        <div style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.2rem; color: #0f172a; letter-spacing: -0.5px;">SKOPE <span style="color:#FF8C00">DIGITAL</span></div>
        <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase; letter-spacing: 3px; font-weight: 700;">ACADEMY</div>
      </div>
    </a>

    <!-- Desktop Navigation -->
    <ul class="navbar-menu">
      <li><a href="./"<?= $navAct('index') ?>>Home</a></li>
      <li><a href="about"<?= $navAct('about') ?>>About Us</a></li>
      <li><a href="courses"<?= $navAct('courses') ?>>Courses</a></li>
      <li><a href="scholarships"<?= $navAct('scholarships') ?>>Scholarships</a></li>
      <li><a href="careers"<?= $navAct('careers') ?>>Careers</a></li>
      <li><a href="donate" class="nav-support<?= $navCur === 'donate' ? ' active' : '' ?>"<?= $navCur === 'donate' ? ' aria-current="page"' : '' ?>>Support Us</a></li>
      <li><a href="contact"<?= $navAct('contact') ?>>Contact Us</a></li>
    </ul>

    <!-- Desktop Actions -->
    <div class="navbar-actions" style="display: flex; align-items: center; gap: 15px;">
        <a href="https://chat.whatsapp.com/IT9spiypi6q3VJX2hn4hhT" target="_blank" class="btn btn-ghost btn-sm" style="color: #25D366; border: 1px solid #25D366; border-radius: 10px; font-weight: 800;">
            <i class="fab fa-whatsapp"></i> Community
        </a>
        <?php if (isset($user) && $user): ?>
            <a href="<?= htmlspecialchars($user['role']) ?>/" class="btn btn-sm nav-cta"><i class="fas fa-gauge-high"></i> My Portal</a>
        <?php else: ?>
            <a href="login" class="btn btn-sm nav-cta"><i class="fas fa-right-to-bracket"></i> Get Started</a>
        <?php endif; ?>
    </div>
    <style>
      /* Matches the Community button: same height, 10px corners, bold label + icon */
      .navbar-actions .nav-cta {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 8px 18px; border-radius: 10px !important; border: 1px solid transparent;
        font-weight: 800; font-size: .85rem; line-height: 1.5; color: #fff !important; text-decoration: none;
        background: linear-gradient(135deg, #00BFFF 0%, #0080FF 100%);
        box-shadow: 0 8px 20px -8px rgba(0, 128, 255, .65);
        transition: transform .2s, box-shadow .2s, filter .2s;
      }
      .navbar-actions .nav-cta:hover { transform: translateY(-2px); filter: brightness(1.06); box-shadow: 0 12px 26px -8px rgba(0, 128, 255, .75); }
      .navbar-actions .nav-cta:focus-visible { outline: 2px solid #00BFFF; outline-offset: 3px; }

      /* Sticky header: stays in flow under the top bar, sticks on scroll */
      :root { --nav-h: 76px; }
      #navbar.navbar { position: sticky !important; top: 0; left: auto; right: auto; height: var(--nav-h); }
      #navbar .navbar-inner { gap: 24px; }
      #navbar .navbar-logo > div > div:first-child { white-space: nowrap; }
      #navbar .navbar-menu a { white-space: nowrap; position: relative; padding: 6px 0; }
      #navbar .navbar-menu a:hover, #navbar .navbar-menu a.active { color: #FF8C00; }
      #navbar .navbar-menu a.active::after { content: ""; position: absolute; left: 0; right: 0; bottom: -2px; height: 2px; border-radius: 2px; background: #FF8C00; }
      #navbar .navbar-menu a.nav-support { color: #00BFFF; font-weight: 800; }
      #navbar .navbar-menu a.nav-support.active, #navbar .navbar-menu a.nav-support:hover { color: #FF8C00; }
      .mobile-menu { top: var(--nav-h); max-height: calc(100vh - var(--nav-h)); max-height: calc(100dvh - var(--nav-h)); overflow-y: auto; }

      /* Page tops: balanced headline wraps, consistent spacing now that the eyebrow labels are gone */
      .hero-section h1, .about-hero h1, .ct-hero h1, .page-hero h1, .sch-hero h1, .donate-hero h1, .careers-hero h1 { text-wrap: balance; margin-top: 0; }
      .hero-section p, .about-hero p, .ct-hero p, .page-hero p, .sch-hero p, .donate-hero p, .careers-hero p { text-wrap: pretty; }
      @media (max-width: 768px) {
        .about-hero, .ct-hero, .page-hero, .sch-hero, .donate-hero, .careers-hero { padding-top: 48px !important; }
        .about-hero h1, .ct-hero h1, .page-hero h1, .sch-hero h1, .donate-hero h1, .careers-hero h1 { font-size: clamp(1.9rem, 8.5vw, 2.4rem) !important; line-height: 1.12 !important; }
        .about-hero-inner, .sch-hero-inner { padding-left: 20px; padding-right: 20px; }
        .about-hero p, .sch-hero p { font-size: 1rem !important; }
        .about-hero .hero-cta-row { flex-direction: column; align-items: stretch; max-width: 320px; margin: 0 auto; }
        .about-hero .hero-cta-row a { justify-content: center; }
      }
      #navbar .navbar-logo, #navbar .navbar-brand { flex-shrink: 0; }
      @media (max-width: 1440px) and (min-width: 1101px) { #navbar .navbar-menu { gap: 24px; } }
      @media (max-width: 1340px) and (min-width: 1101px) {
        #navbar .navbar-menu { gap: 18px; }
        #navbar .navbar-menu a { font-size: .9rem; }
        #navbar .navbar-actions .btn-ghost { display: none; }
      }
      @media (max-width: 1220px) and (min-width: 1101px) {
        #navbar .navbar-menu { gap: 14px; }
        #navbar .navbar-menu a { font-size: .86rem; }
      }

      /* Tablet + mobile header: logo left, menu button right */
      @media (max-width: 1100px) {
        #navbar .navbar-menu, #navbar .navbar-actions, .top-bar { display: none !important; }
        #navToggle { display: flex !important; }
        .mobile-menu { max-width: 100%; }
      }
      @media (max-width: 768px) { :root { --nav-h: 68px; } }
      @media (max-width: 1024px) {
        .navbar-inner { padding: 0 16px; gap: 12px; }
        .navbar-logo { order: 1; gap: 10px !important; min-width: 0; }
        .navbar-logo img { height: 40px !important; }
        .navbar-logo > div > div:first-child { font-size: 1.05rem !important; white-space: nowrap; }
        .navbar-logo > div > div:last-child { font-size: .6rem !important; letter-spacing: 3.5px !important; margin-top: 2px; }
        #navToggle {
          order: 2; margin-left: auto; flex-shrink: 0;
          width: 44px; height: 44px; padding: 0;
          align-items: center; justify-content: center;
          border-radius: 12px; background: #f1f5f9; border: 1px solid #e2e8f0;
          transition: background .2s, border-color .2s;
        }
        #navToggle:hover { background: #e2e8f0; }
        #navToggle.open { background: #0f172a; border-color: #0f172a; }
        #navToggle:focus-visible { outline: 2px solid #00BFFF; outline-offset: 2px; }
      }

      /* Hamburger icon: three bars that morph into an X */
      #navToggle { position: relative; cursor: pointer; }
      #navToggle span {
        position: absolute; left: 50%; top: 50%; width: 20px; height: 2px; margin: -1px 0 0 -10px;
        border-radius: 2px; background: #0f172a;
        transition: transform .3s cubic-bezier(.2,.8,.2,1), opacity .2s, background .2s, width .3s, margin .3s;
      }
      #navToggle span:nth-child(1) { transform: translateY(-6px); }
      #navToggle span:nth-child(2) { width: 14px; margin-left: -4px; }
      #navToggle span:nth-child(3) { transform: translateY(6px); }
      #navToggle:hover span:nth-child(2) { width: 20px; margin-left: -10px; }
      #navToggle.open span { background: #fff; }
      #navToggle.open span:nth-child(1) { transform: rotate(45deg); }
      #navToggle.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
      #navToggle.open span:nth-child(3) { transform: rotate(-45deg); }

      /* Slide-down menu sheet */
      .mobile-menu-backdrop {
        position: fixed; inset: 0; z-index: 997; background: rgba(15,23,42,.45);
        backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px); opacity: 0; transition: opacity .25s;
      }
      .mobile-menu-backdrop.show { opacity: 1; }
      .mobile-menu {
        display: flex !important; flex-direction: column; visibility: hidden; position: fixed; top: var(--nav-h); left: 0; right: 0;
        padding: 12px 16px calc(18px + env(safe-area-inset-bottom)); gap: 0;
        background: #fff; border: 0; border-top: 1px solid #eef2f7; border-radius: 0 0 22px 22px;
        box-shadow: 0 24px 40px -20px rgba(15,23,42,.45);
        transform: translateY(-12px); opacity: 0; pointer-events: none;
        transition: transform .3s cubic-bezier(.2,.8,.2,1), opacity .2s, visibility 0s linear .3s;
      }
      .mobile-menu.open { visibility: visible; transform: none; opacity: 1; pointer-events: auto; transition-delay: 0s; }
      .mm-links { display: grid; gap: 2px; }
      .mobile-menu .mm-links a {
        display: flex; align-items: center; gap: 14px; padding: 10px 12px; border-radius: 12px;
        color: #0f172a !important; font-weight: 600; font-size: .98rem; text-decoration: none;
        opacity: 0; transform: translateY(-6px); transition: background .15s, opacity .3s, transform .3s;
      }
      .mobile-menu.open .mm-links a { opacity: 1; transform: none; }
      .mobile-menu.open .mm-links a:nth-child(2) { transition-delay: .03s; }
      .mobile-menu.open .mm-links a:nth-child(3) { transition-delay: .06s; }
      .mobile-menu.open .mm-links a:nth-child(4) { transition-delay: .09s; }
      .mobile-menu.open .mm-links a:nth-child(5) { transition-delay: .12s; }
      .mobile-menu.open .mm-links a:nth-child(6) { transition-delay: .15s; }
      .mm-links a > i:first-child {
        width: 36px; height: 36px; flex: 0 0 36px; border-radius: 10px; display: grid; place-items: center;
        background: #f1f5f9; color: #0080FF; font-size: .9rem; transition: background .15s, color .15s;
      }
      .mm-links a span { flex: 1; }
      .mm-links a .mm-go { font-size: .7rem; color: #cbd5e1; transition: transform .15s, color .15s; }
      .mobile-menu .mm-links a:hover, .mobile-menu .mm-links a:active { background: #f8fafc; }
      .mm-links a:hover .mm-go { transform: translateX(3px); color: #94a3b8; }
      .mobile-menu .mm-links a.active { background: #fff7ed; color: #c2410c !important; font-weight: 700; }
      .mm-links a.active > i:first-child { background: #FF8C00; color: #fff; }
      .mm-links a.active .mm-go { color: #FF8C00; }
      .mm-links a.mm-support > i:first-child { background: #fff1f2; color: #e11d48; }
      .mobile-menu .mobile-cta { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; padding-top: 14px; border-top: 1px solid #eef2f7; }
      .mobile-menu .mm-btn {
        display: flex; align-items: center; justify-content: center; gap: 8px; height: 48px; padding: 0 12px;
        border-radius: 12px; font-weight: 800; font-size: .9rem; text-decoration: none; white-space: nowrap;
      }
      .mobile-menu .mm-primary { background: linear-gradient(135deg, #FF8C00, #ff6a00); color: #fff !important; box-shadow: 0 10px 22px -10px rgba(255,120,0,.7); }
      .mobile-menu .mm-whatsapp { background: #ecfdf3; color: #15803d !important; border: 1px solid #bbf7d0; }
      .mobile-menu .mm-logout { grid-column: 1 / -1; display: flex; justify-content: center; gap: 8px; align-items: center; padding: 8px; font-size: .88rem; font-weight: 700; color: #dc2626 !important; text-decoration: none; }
      @media (max-width: 340px) { .mobile-menu .mobile-cta { grid-template-columns: 1fr; } }
      @media (min-width: 1025px) { .mobile-menu, .mobile-menu-backdrop { display: none !important; } }
      @media (prefers-reduced-motion: reduce) { #navToggle span, .mobile-menu, .mobile-menu .mm-links a { transition: none !important; } }
      @media (max-width: 360px) {
        .navbar-logo img { height: 34px !important; }
        .navbar-logo > div > div:first-child { font-size: .95rem !important; }
      }
    </style>
  </div>
</nav>

<!-- Mobile Slide-Down Menu -->
<div class="mobile-menu-backdrop" id="mobileMenuBackdrop" hidden></div>
<div class="mobile-menu" id="mobileMenu" aria-label="Site menu">
  <nav class="mm-links">
    <a href="./"<?= $navAct('index') ?>><i class="fas fa-house"></i><span>Home</span><i class="fas fa-chevron-right mm-go"></i></a>
    <a href="about"<?= $navAct('about') ?>><i class="fas fa-circle-info"></i><span>About Us</span><i class="fas fa-chevron-right mm-go"></i></a>
    <a href="courses"<?= $navAct('courses') ?>><i class="fas fa-graduation-cap"></i><span>Courses</span><i class="fas fa-chevron-right mm-go"></i></a>
    <a href="scholarships"<?= $navAct('scholarships') ?>><i class="fas fa-award"></i><span>Scholarships</span><i class="fas fa-chevron-right mm-go"></i></a>
    <a href="careers"<?= $navAct('careers') ?>><i class="fas fa-briefcase"></i><span>Careers</span><i class="fas fa-chevron-right mm-go"></i></a>
    <a href="donate" class="mm-support<?= $navCur === 'donate' ? ' active' : '' ?>"><i class="fas fa-heart"></i><span>Support Us</span><i class="fas fa-chevron-right mm-go"></i></a>
    <a href="contact"<?= $navAct('contact') ?>><i class="fas fa-envelope"></i><span>Contact Us</span><i class="fas fa-chevron-right mm-go"></i></a>
  </nav>
  <div class="mobile-cta">
    <?php if ($user): ?>
      <a href="<?= htmlspecialchars($user['role']) ?>/" class="mm-btn mm-primary"><i class="fas fa-gauge-high"></i> My Portal</a>
    <?php else: ?>
      <a href="login" class="mm-btn mm-primary"><i class="fas fa-right-to-bracket"></i> Get Started</a>
    <?php endif; ?>
    <a href="https://chat.whatsapp.com/IT9spiypi6q3VJX2hn4hhT" target="_blank" rel="noopener" class="mm-btn mm-whatsapp"><i class="fab fa-whatsapp"></i> Community</a>
    <?php if ($user): ?>
      <a href="logout" class="mm-logout"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('navToggle');
    const menu = document.getElementById('mobileMenu');
    const backdrop = document.getElementById('mobileMenuBackdrop');
    if (!btn || !menu) return;

    const setOpen = (open) => {
        btn.classList.toggle('open', open);
        menu.classList.toggle('open', open);
        btn.setAttribute('aria-expanded', String(open));
        btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        document.body.style.overflow = open ? 'hidden' : '';
        if (backdrop) {
            if (open) { backdrop.hidden = false; requestAnimationFrame(() => backdrop.classList.add('show')); }
            else { backdrop.classList.remove('show'); setTimeout(() => { if (!menu.classList.contains('open')) backdrop.hidden = true; }, 250); }
        }
    };
    btn.addEventListener('click', () => setOpen(!menu.classList.contains('open')));
    if (backdrop) backdrop.addEventListener('click', () => setOpen(false));
    menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && menu.classList.contains('open')) { setOpen(false); btn.focus(); } });
    window.addEventListener('resize', () => { if (window.innerWidth > 1024 && menu.classList.contains('open')) setOpen(false); });
});
</script>
