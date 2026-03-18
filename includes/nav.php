<?php $user = isLoggedIn() ? currentUser() : null; ?>

<!-- Top Bar -->
<div class="top-bar" style="background: #f8fafc; border-bottom: 1px solid #eee; padding: 10px 0; font-size: 0.85rem; color: #64748b;">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; gap: 24px;">
            <span style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-map-marker-alt" style="color: #FF8C00;"></i> Kisumu, Kenya</span>
            <span style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-envelope" style="color: #FF8C00;"></i> info@skopedigital.ac.ke</span>
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
    
    <!-- Logo -->
    <a href="index.php" class="navbar-logo" style="display: flex; align-items: center; gap: 15px; text-decoration: none;">
      <img src="assets/images/Skope Digital  logo.png" alt="Skope Digital" style="height: 54px;">
      <div style="line-height: 1.1;">
        <div style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.2rem; color: #0f172a; letter-spacing: -0.5px;">SKOPE <span style="color:#FF8C00">DIGITAL</span></div>
        <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase; letter-spacing: 3px; font-weight: 700;">ACADEMY</div>
      </div>
    </a>

    <!-- Desktop Navigation -->
    <ul class="navbar-menu">
      <li><a href="index.php">Home</a></li>
      <li><a href="about.php">About Us</a></li>
      <li><a href="courses.php">Courses</a></li>
      <li><a href="scholarships.php">Scholarships</a></li>
      <li><a href="contact.php">Contact Us</a></li>
    </ul>

    <!-- Desktop Actions -->
    <div class="navbar-actions" style="display: flex; align-items: center; gap: 15px;">
        <a href="https://chat.whatsapp.com/IT9spiypi6q3VJX2hn4hhT" target="_blank" class="btn btn-ghost btn-sm" style="color: #25D366; border: 1px solid #25D366; border-radius: 10px; font-weight: 800;">
            <i class="fab fa-whatsapp"></i> Community
        </a>
        <?php if (isset($user) && $user): ?>
            <a href="<?= htmlspecialchars($user['role']) ?>/index.php" class="btn btn-primary btn-sm">Portal</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary btn-sm">Get Started</a>
        <?php endif; ?>
    </div>

    <!-- Mobile Hamburger -->
    <button class="nav-hamburger" id="navToggle" aria-label="Toggle Navigation">
        <span></span>
        <span></span>
        <span></span>
    </button>
  </div>
</nav>

<!-- Mobile Slide-Down Menu -->
<div class="mobile-menu" id="mobileMenu">
  <div style="display: flex; flex-direction: column; gap: 8px;">
    <a href="index.php">Home</a>
    <a href="about.php">About Us</a>
    <a href="courses.php">Courses</a>
    <a href="scholarships.php">Scholarships</a>
    <a href="contact.php">Contact Us</a>
    <?php if ($user): ?>
      <a href="logout.php" style="color: var(--danger);">Logout</a>
    <?php endif; ?>
  </div>
  <div class="mobile-cta">
    <?php if ($user): ?>
      <a href="<?= htmlspecialchars($user['role']) ?>/index.php" class="btn btn-primary btn-block" style="background: #FF8C00; border: none;">Enter My Portal</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-primary btn-block" style="background: #FF8C00; border: none;">Get Started</a>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const navToggle = document.getElementById('navToggle');
    const mobileMenu = document.getElementById('mobileMenu');
    
    if (navToggle && mobileMenu) {
        navToggle.addEventListener('click', function() {
            navToggle.classList.toggle('open');
            mobileMenu.classList.toggle('open');
            document.body.style.overflow = mobileMenu.classList.contains('open') ? 'hidden' : '';
        });
        
        // Close menu when clicking links
        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navToggle.classList.remove('open');
                mobileMenu.classList.remove('open');
                document.body.style.overflow = '';
            });
        });
    }
});
</script>
