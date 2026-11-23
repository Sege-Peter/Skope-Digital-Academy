<?php
// Public site footer. Contact details: phone from Admin > Settings > General (support phone), emails fixed below.
if (!function_exists('setting')) require_once __DIR__ . '/learning.php';
$ftPhone = trim(setting('support_phone')) ?: '0742 380 183';
$ftEmails = ['info@skopedigital.co.ke', 'skopedigitalacademy@gmail.com'];
$ftDigits = preg_replace('/\D+/', '', $ftPhone);
$ftWa = str_starts_with($ftDigits, '0') ? '254' . substr($ftDigits, 1) : $ftDigits;   // Kenyan 07.. -> 2547..
$ftTel = '+' . $ftWa;
$ftFacebook = 'https://www.facebook.com/profile.php?id=100090014799182';
$ftH = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<footer class="sf" role="contentinfo">
    <div class="sf-glow sf-glow-a" aria-hidden="true"></div>
    <div class="sf-glow sf-glow-b" aria-hidden="true"></div>

    <div class="container sf-inner">
        <div class="sf-grid">
            <!-- Brand -->
            <div class="sf-brand">
                <a href="./" class="sf-logo" aria-label="Skope Digital Academy home">
                    <img src="assets/images/skope-logo-512.png" alt="" width="52" height="52">
                    <span><b>SKOPE <em>DIGITAL</em></b><small>Academy</small></span>
                </a>
                <p class="sf-about">Practical coding and digital skills for Kenya and East Africa: free tutorials, self-paced or weekly classes, and certificates you can verify.</p>
                <div class="sf-social" aria-label="Contact and social links">
                    <a href="<?= $ftH($ftFacebook) ?>" target="_blank" rel="noopener" title="Facebook" aria-label="Skope Digital Academy on Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://wa.me/<?= $ftH($ftWa) ?>" target="_blank" rel="noopener" title="WhatsApp" aria-label="Chat on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="tel:<?= $ftH($ftTel) ?>" title="Call" aria-label="Call us"><i class="fas fa-phone"></i></a>
                    <a href="mailto:<?= $ftH($ftEmails[0]) ?>" title="Email" aria-label="Email us"><i class="fas fa-envelope"></i></a>
                </div>
            </div>

            <!-- Quick links -->
            <nav class="sf-col" aria-labelledby="sf-h-quick">
                <h4 class="sf-h" id="sf-h-quick">Quick Links</h4>
                <ul class="sf-links">
                    <li><a href="./">Home</a></li>
                    <li><a href="about">About Academy</a></li>
                    <li><a href="courses">Explore Courses</a></li>
                    <li><a href="scholarships">Scholarships</a></li>
                    <li><a href="careers">Careers</a></li>
                    <li><a href="contact">Support Center</a></li>
                </ul>
            </nav>

            <!-- Learn -->
            <nav class="sf-col" aria-labelledby="sf-h-learn">
                <h4 class="sf-h" id="sf-h-learn">Learn</h4>
                <ul class="sf-links">
                    <li><a href="learn">Free Coding Tutorials</a></li>
                    <li><a href="courses">Skills Courses</a></li>
                    <li><a href="register">Create a Free Account</a></li>
                    <li><a href="register?role=tutor">Teach With Us</a></li>
                    <li><a href="verify">Verify a Certificate</a></li>
                </ul>
            </nav>

            <!-- Contact -->
            <div class="sf-col sf-contact">
                <h4 class="sf-h">Get in Touch</h4>
                <ul class="sf-cards">
                    <li>
                        <span class="sf-ic"><i class="fas fa-phone"></i></span>
                        <div><small>Phone &amp; WhatsApp</small><a href="tel:<?= $ftH($ftTel) ?>"><?= $ftH($ftPhone) ?></a></div>
                    </li>
                    <li>
                        <span class="sf-ic"><i class="fas fa-envelope"></i></span>
                        <div><small>Email</small><?php foreach ($ftEmails as $e): ?><a href="mailto:<?= $ftH($e) ?>"><?= str_replace('@', '<wbr>@', $ftH($e)) ?></a><?php endforeach; ?></div>
                    </li>
                    <li>
                        <span class="sf-ic"><i class="fas fa-location-dot"></i></span>
                        <div><small>Location</small><span class="sf-v">Kisumu, Kenya</span><span class="sf-note">Learn online from anywhere</span></div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="sf-bottom">
            <p>&copy; <?= date('Y') ?> Skope Digital Academy. All rights reserved.</p>
            <nav class="sf-legal" aria-label="Legal">
                <a href="privacy">Privacy Policy</a>
                <a href="terms">Terms of Service</a>
            </nav>
            <p class="sf-credit">Powered by <a href="https://skopedesigns.co.ke" target="_blank" rel="noopener">Skope Designs</a></p>
        </div>
    </div>
</footer>

<style>
.sf { --sf-bg: #0b1017; --sf-line: rgba(255,255,255,.07); --sf-text: #94a3b8; --sf-dim: #64748b; --sf-white: #f8fafc; --sf-gold: #eab308; --sf-cyan: #00bfff;
      position: relative; overflow: hidden; background: var(--sf-bg); color: var(--sf-text); border-top: 1px solid var(--sf-line);
      padding: clamp(56px, 8vw, 96px) 0 0; font-size: .95rem; line-height: 1.6; }
.sf-glow { position: absolute; pointer-events: none; border-radius: 50%; filter: blur(10px); }
.sf-glow-a { width: 520px; height: 520px; right: -180px; bottom: -220px; background: radial-gradient(circle, rgba(0,174,239,.10), transparent 65%); }
.sf-glow-b { width: 420px; height: 420px; left: -160px; top: -200px; background: radial-gradient(circle, rgba(247,148,29,.07), transparent 65%); }
.sf-inner { position: relative; z-index: 1; }
.sf-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, .75fr) minmax(0, .9fr) minmax(0, 1.45fr); gap: clamp(28px, 4vw, 56px); padding-bottom: clamp(40px, 6vw, 64px); }

/* Brand */
.sf-logo { display: inline-flex; align-items: center; gap: 14px; text-decoration: none; margin-bottom: 20px; }
.sf-logo img { width: 52px; height: 52px; object-fit: contain; filter: brightness(0) invert(1); }
.sf-logo b { display: block; font: 800 1.2rem/1.1 'Poppins', sans-serif; color: #fff; letter-spacing: -.3px; }
.sf-logo em { font-style: normal; color: var(--sf-gold); }
.sf-logo small { display: block; font-size: .64rem; font-weight: 700; letter-spacing: .32em; text-transform: uppercase; color: var(--sf-dim); margin-top: 3px; }
.sf-about { margin: 0 0 24px; max-width: 360px; color: var(--sf-dim); line-height: 1.75; }
.sf-social { display: flex; gap: 10px; flex-wrap: wrap; }
.sf-social a { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; color: var(--sf-text); text-decoration: none;
               background: rgba(255,255,255,.04); border: 1px solid var(--sf-line); transition: background .2s, color .2s, transform .2s, border-color .2s; }
.sf-social a:hover, .sf-social a:focus-visible { background: var(--sf-gold); border-color: var(--sf-gold); color: #0b1017; transform: translateY(-3px); outline: none; }

/* Columns */
.sf-h { margin: 6px 0 18px; font: 800 .78rem 'Poppins', sans-serif; letter-spacing: .18em; text-transform: uppercase; color: #fff; }
.sf-links { list-style: none; margin: 0; padding: 0; display: grid; gap: 11px; }
.sf-links a { color: var(--sf-text); text-decoration: none; font-weight: 500; transition: color .2s, padding-left .2s; display: inline-block; }
.sf-links a:hover, .sf-links a:focus-visible { color: var(--sf-gold); padding-left: 4px; outline: none; }

/* Contact cards: every row uses the same icon box, so all values line up */
.sf-cards { list-style: none; margin: 0; padding: 0; display: grid; gap: 16px; }
.sf-cards li { display: grid; grid-template-columns: 38px minmax(0, 1fr); gap: 12px; align-items: start; }
.sf-ic { width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center; font-size: .88rem; color: var(--sf-gold);
         background: rgba(234,179,8,.09); border: 1px solid rgba(234,179,8,.18); }
.sf-cards small { display: block; font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--sf-dim); margin: 1px 0 3px; }
.sf-cards a, .sf-cards .sf-v { display: block; color: var(--sf-white); font-weight: 700; text-decoration: none; font-size: .93rem; line-height: 1.5;
                               overflow-wrap: break-word; }
.sf-cards a + a { margin-top: 2px; }
.sf-cards a:hover, .sf-cards a:focus-visible { color: var(--sf-gold); outline: none; }
.sf-note { display: block; font-size: .82rem; color: var(--sf-dim); }

/* Bottom bar */
.sf-bottom { display: flex; align-items: center; justify-content: space-between; gap: 12px 28px; flex-wrap: wrap;
             border-top: 1px solid var(--sf-line); padding: 22px 0 26px; font-size: .84rem; color: #556274; }
.sf-bottom p { margin: 0; }
.sf-legal { display: flex; gap: 22px; flex-wrap: wrap; }
.sf-legal a, .sf-credit a { color: var(--sf-text); text-decoration: none; font-weight: 600; transition: color .2s; }
.sf-legal a:hover, .sf-credit a:hover { color: var(--sf-gold); }
.sf-credit a { color: #cbd5e1; }

/* Tablet: brand + contact on top, links below */
@media (max-width: 1024px) {
    .sf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .sf-brand { grid-column: 1 / -1; }
    .sf-about { max-width: 560px; margin-bottom: 18px; }
    .sf-contact { grid-column: 1 / -1; }
    .sf-contact .sf-cards { grid-template-columns: minmax(0, .8fr) minmax(0, 1.3fr) minmax(0, .9fr); }
}
/* Phone: single column, links side by side */
@media (max-width: 640px) {
    .sf { font-size: .92rem; }
    .sf-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 32px 20px; }
    .sf-brand { display: block; }
    .sf-about { margin-bottom: 20px; }
    .sf-contact { grid-column: 1 / -1; }
    .sf-contact .sf-cards { grid-template-columns: 1fr; }
    /* Phone bottom bar: centred stack - legal links, copyright, credit */
    .sf-bottom { flex-direction: column; align-items: center; text-align: center; gap: 12px; padding: 22px 0 28px; }
    .sf-legal { order: 1; justify-content: center; gap: 0; }
    .sf-legal a { padding: 4px 12px; font-size: .86rem; }
    .sf-legal a + a { border-left: 1px solid var(--sf-line); }
    .sf-bottom > p:first-child { order: 2; font-size: .78rem; line-height: 1.5; }
    .sf-credit { order: 3; font-size: .78rem; padding: 7px 14px; border: 1px solid var(--sf-line); border-radius: 999px; background: rgba(255,255,255,.03); }
}
@media (max-width: 360px) {
    .sf-grid { grid-template-columns: 1fr; }
}
</style>
