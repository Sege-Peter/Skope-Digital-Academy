<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$user = isLoggedIn() ? currentUser() : null;

// Status from contact-handler (shown above the form).
$msg = $_GET['msg'] ?? '';
$notices = [
    'sent'                => ['ok',   'fa-circle-check',         'Message sent. Thank you!', 'We usually reply within one working day. Check your inbox (and spam folder) for our answer.'],
    'all_fields_required' => ['warn', 'fa-circle-exclamation',   'A few details are missing.', 'Please enter your name, a valid email address and a message.'],
    'expired'             => ['warn', 'fa-rotate',               'Your session expired.', 'Please send your message again.'],
    'error'               => ['err',  'fa-triangle-exclamation', 'We couldn\'t send that.', 'Something went wrong on our side. Please try again, or call us instead.'],
];
$notice = $notices[$msg] ?? null;

$topics = [
    'Course Inquiry'     => ['fa-graduation-cap', 'Courses'],
    'Payments & Billing' => ['fa-receipt', 'Payments'],
    'Technical Support'  => ['fa-screwdriver-wrench', 'Tech support'],
    'Scholarship Q&A'    => ['fa-award', 'Scholarships'],
    'Partnerships'       => ['fa-handshake', 'Partnerships'],
];
$preselect = $_GET['topic'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Contact Us', 'path' => 'contact', 'description' => 'Contact Skope Digital Academy in Kisumu, Kenya: call or WhatsApp 0742 380 183, or email info@skopedigital.co.ke for admissions, payments and support.', 'breadcrumbs' => [['Contact', 'contact']], 'schema' => [['@type' => 'ContactPage', 'name' => 'Contact Skope Digital Academy', 'url' => sda_seo_abs('contact')]]]); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
    <style>
        .ct {
            --ct-cyan: #00BFFF;
            --ct-cyan-deep: #0080FF;
            --ct-orange: #FF8C00;
            --ct-ink: #0f172a;
            --ct-ink-2: #334155;
            --ct-muted: #64748b;
            --ct-line: #e2e8f0;
            --ct-soft: #f8fafc;
            --ct-navy: #0b1324;
            font-family: 'Inter', sans-serif;
            color: var(--ct-ink-2);
        }
        .ct h1, .ct h2, .ct h3 { font-family: 'Poppins', sans-serif; color: var(--ct-ink); letter-spacing: -0.02em; }

        /* ── Hero ── */
        .ct-hero {
            position: relative; overflow: hidden; text-align: center;
            padding: 80px 0 110px;
            background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 55%, #fff7ed 100%);
            border-bottom: 1px solid var(--ct-line);
        }
        .ct-hero::before, .ct-hero::after {
            content: ''; position: absolute; border-radius: 50%; pointer-events: none;
        }
        .ct-hero::before { width: 460px; height: 460px; top: -180px; right: -120px; background: radial-gradient(circle, rgba(0,191,255,.16), transparent 70%); }
        .ct-hero::after  { width: 380px; height: 380px; bottom: -200px; left: -120px; background: radial-gradient(circle, rgba(255,140,0,.12), transparent 70%); }
        .ct-hero-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); gap: 48px; align-items: center; text-align: left; }
        .ct-hero-text p { margin: 0 !important; }
        .ct-hero-text .ct-quick { justify-content: flex-start; }
        .ct-hero-art { position: relative; }
        .ct-hero-art img { display: block; width: 100%; height: auto; aspect-ratio: 1100 / 733; border-radius: 26px; border: 6px solid #fff; box-shadow: 0 30px 60px -28px rgba(15,23,42,.45); }
        .ct-hero-chip { position: absolute; left: -18px; bottom: 22px; display: flex; gap: 10px; align-items: center; padding: 10px 14px 10px 10px; border-radius: 14px; background: #fff; box-shadow: 0 14px 34px -14px rgba(15,23,42,.35); }
        .ct-hero-chip i { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: #e0f2fe; color: #0284c7; }
        .ct-hero-chip b { display: block; font-size: .85rem; color: #0f172a; } .ct-hero-chip small { display: block; font-size: .72rem; color: #64748b; }
        @media (max-width: 900px) { .ct-hero-grid { grid-template-columns: 1fr; gap: 30px; text-align: center; } .ct-hero-text p { margin: 0 auto !important; } .ct-hero-text .ct-quick { justify-content: center; } .ct-hero-art { max-width: 560px; margin: 0 auto; } .ct-hero-chip { left: 10px; bottom: 10px; } }
        .ct-badge {
            display: inline-flex; align-items: center; gap: 8px; padding: 7px 16px; border-radius: 99px;
            background: rgba(0,191,255,.1); border: 1px solid rgba(0,191,255,.25); color: #0284c7;
            font-size: .72rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
        }
        .ct-hero h1 { font-size: clamp(2.3rem, 5.5vw, 3.8rem); font-weight: 900; line-height: 1.08; margin: 18px 0 16px; }
        .ct-hero h1 .c { color: var(--ct-cyan); }
        .ct-hero h1 .o { color: var(--ct-orange); }
        .ct-hero p { max-width: 620px; margin: 0 auto; font-size: 1.12rem; line-height: 1.7; color: var(--ct-muted); }
        .ct-quick { display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; margin-top: 34px; position: relative; z-index: 1; }
        .ct-quick a {
            display: inline-flex; align-items: center; gap: 10px; padding: 13px 22px; border-radius: 14px;
            background: #fff; border: 1px solid var(--ct-line); color: var(--ct-ink); font-weight: 700; font-size: .95rem;
            text-decoration: none; box-shadow: 0 8px 24px -14px rgba(15,23,42,.25); transition: transform .2s, box-shadow .2s, border-color .2s;
        }
        .ct-quick a:hover { transform: translateY(-2px); border-color: var(--ct-cyan); box-shadow: 0 14px 30px -14px rgba(0,128,255,.35); }
        .ct-quick a i { font-size: 1.05rem; }
        .ct-quick .call i { color: var(--ct-cyan-deep); }
        .ct-quick .mail i { color: var(--ct-orange); }
        .ct-quick .wa i { color: #25D366; }

        /* ── Contact card ── */
        .ct-main { padding: 0 0 90px; }
        .ct-card {
            position: relative; z-index: 2; margin-top: -60px;
            display: grid; grid-template-columns: 0.85fr 1.15fr;
            background: #fff; border: 1px solid var(--ct-line); border-radius: 28px; overflow: hidden;
            box-shadow: 0 40px 80px -40px rgba(15,23,42,.35);
        }
        .ct-info {
            position: relative; overflow: hidden; padding: 48px 42px; color: #cbd5e1;
            background: radial-gradient(circle at 110% -10%, rgba(0,191,255,.28), transparent 45%),
                        radial-gradient(circle at -20% 120%, rgba(255,140,0,.22), transparent 45%), var(--ct-navy);
        }
        .ct-info h2 { color: #fff; font-size: 1.6rem; margin: 0 0 8px; }
        .ct-info > p { margin: 0 0 32px; line-height: 1.65; color: #94a3b8; }
        .ct-rows { display: grid; gap: 22px; }
        .ct-row { display: flex; gap: 16px; align-items: flex-start; }
        .ct-row .ic {
            flex: 0 0 46px; height: 46px; border-radius: 14px; display: grid; place-items: center;
            background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.1); color: var(--ct-cyan); font-size: 1.05rem;
        }
        .ct-row:nth-child(even) .ic { color: var(--ct-orange); }
        .ct-row small { display: block; font-size: .7rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
        .ct-row a, .ct-row strong { color: #fff; font-weight: 700; font-size: 1.02rem; text-decoration: none; }
        .ct-row a:hover { color: var(--ct-cyan); }
        .ct-row .sub { display: block; color: #94a3b8; font-size: .88rem; margin-top: 2px; }
        .ct-hours { margin-top: 34px; padding: 18px 20px; border-radius: 16px; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.09); }
        .ct-hours div { display: flex; justify-content: space-between; gap: 12px; font-size: .92rem; padding: 4px 0; }
        .ct-hours span:last-child { color: #fff; font-weight: 700; }
        .ct-hours .closed span:last-child { color: #f87171; }
        .ct-open-now { display: inline-flex; align-items: center; gap: 8px; margin-top: 12px; font-size: .82rem; font-weight: 700; }
        .ct-open-now i { font-size: .55rem; }

        .ct-form { padding: 48px 46px; }
        .ct-form h2 { font-size: 1.7rem; margin: 0 0 6px; }
        .ct-form > p { margin: 0 0 28px; color: var(--ct-muted); }
        .ct-notice { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 14px; margin-bottom: 24px; font-size: .94rem; line-height: 1.5; }
        .ct-notice i { margin-top: 3px; }
        .ct-notice strong { display: block; }
        .ct-notice.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .ct-notice.warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .ct-notice.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .ct-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .ct-field { margin-bottom: 20px; }
        .ct-field label, .ct-legend { display: block; font-size: .82rem; font-weight: 700; color: var(--ct-ink); margin-bottom: 8px; }
        .ct-field label em { font-style: normal; font-weight: 500; color: #94a3b8; }
        .ct-input {
            width: 100%; padding: 14px 16px; border-radius: 12px; border: 1.5px solid var(--ct-line); background: var(--ct-soft);
            font: inherit; font-size: .98rem; color: var(--ct-ink); outline: none; transition: border-color .2s, box-shadow .2s, background .2s;
        }
        .ct-input::placeholder { color: #94a3b8; }
        .ct-input:focus { border-color: var(--ct-cyan); background: #fff; box-shadow: 0 0 0 4px rgba(0,191,255,.14); }
        textarea.ct-input { resize: vertical; min-height: 140px; line-height: 1.6; }
        .ct-count { text-align: right; font-size: .75rem; color: #94a3b8; margin-top: 6px; }

        .ct-topics { display: flex; flex-wrap: wrap; gap: 8px; border: 0; padding: 0; margin: 0 0 22px; }
        .ct-topic { position: relative; }
        .ct-topic input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
        .ct-topic span {
            display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border-radius: 99px;
            border: 1.5px solid var(--ct-line); background: #fff; font-size: .86rem; font-weight: 600; color: var(--ct-ink-2);
            transition: all .2s; cursor: pointer;
        }
        .ct-topic span i { color: #94a3b8; transition: color .2s; }
        .ct-topic input:checked + span { border-color: var(--ct-cyan); background: rgba(0,191,255,.08); color: #0369a1; }
        .ct-topic input:checked + span i { color: var(--ct-cyan-deep); }
        .ct-topic input:focus-visible + span { box-shadow: 0 0 0 4px rgba(0,191,255,.2); }

        .ct-submit {
            width: 100%; height: 56px; border: 0; border-radius: 14px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            background: linear-gradient(135deg, var(--ct-cyan), var(--ct-cyan-deep)); color: #fff;
            font: 800 1.02rem 'Poppins', sans-serif; box-shadow: 0 14px 30px -12px rgba(0,128,255,.55); transition: transform .2s, box-shadow .2s;
        }
        .ct-submit:hover { transform: translateY(-2px); box-shadow: 0 18px 34px -12px rgba(0,128,255,.6); }
        .ct-submit:disabled { opacity: .7; transform: none; cursor: progress; }
        .ct-privacy { text-align: center; font-size: .8rem; color: #94a3b8; margin-top: 14px; }
        .ct-privacy a { color: var(--ct-muted); }

        /* ── FAQ ── */
        .ct-faq { padding: 10px 0 90px; }
        .ct-head { text-align: center; margin-bottom: 36px; }
        .ct-head h2 { font-size: clamp(1.7rem, 3.5vw, 2.3rem); margin: 10px 0 8px; }
        .ct-head p { color: var(--ct-muted); margin: 0; }
        .ct-faq-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; max-width: 980px; margin: 0 auto; }
        .ct-faq details { background: #fff; border: 1px solid var(--ct-line); border-radius: 16px; padding: 0 22px; transition: border-color .2s, box-shadow .2s; }
        .ct-faq details[open] { border-color: rgba(0,191,255,.5); box-shadow: 0 16px 30px -22px rgba(0,128,255,.5); }
        .ct-faq summary { list-style: none; cursor: pointer; padding: 18px 0; font-weight: 700; color: var(--ct-ink); display: flex; justify-content: space-between; gap: 12px; align-items: center; }
        .ct-faq summary::-webkit-details-marker { display: none; }
        .ct-faq summary i { color: var(--ct-cyan-deep); transition: transform .2s; }
        .ct-faq details[open] summary i { transform: rotate(45deg); }
        .ct-faq details p { margin: 0 0 18px; line-height: 1.65; color: var(--ct-muted); }
        .ct-faq details a { color: var(--ct-cyan-deep); font-weight: 700; }

        /* ── Map ── */
        .ct-map { padding-bottom: 100px; }
        .ct-map-card { position: relative; border-radius: 28px; overflow: hidden; border: 1px solid var(--ct-line); height: 420px; background: #e2e8f0; box-shadow: 0 30px 60px -40px rgba(15,23,42,.4); }
        .ct-map-card iframe { width: 100%; height: 100%; border: 0; display: block; }
        .ct-map-tag {
            position: absolute; left: 20px; top: 20px; background: #fff; border-radius: 14px; padding: 12px 16px;
            display: flex; gap: 12px; align-items: center; box-shadow: 0 12px 30px -12px rgba(15,23,42,.35);
        }
        .ct-map-tag i { color: var(--ct-orange); font-size: 1.2rem; }
        .ct-map-tag strong { display: block; color: var(--ct-ink); }
        .ct-map-tag a { font-size: .82rem; color: var(--ct-cyan-deep); font-weight: 700; text-decoration: none; }

        @media (max-width: 980px) {
            .ct-card { grid-template-columns: 1fr; }
            .ct-faq-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .ct-hero { padding: 104px 0 96px; }
            .ct-hero p { font-size: 1rem; }
            .ct-quick a { flex: 1 1 100%; justify-content: center; }
            .ct-card { border-radius: 22px; margin-top: -50px; }
            .ct-info, .ct-form { padding: 32px 22px; }
            .ct-grid2 { grid-template-columns: 1fr; gap: 0; }
            .ct-map-card { height: 320px; border-radius: 22px; }
        }
    </style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<div class="ct">
    <!-- Hero -->
    <section class="ct-hero">
        <div class="container ct-hero-grid" style="position: relative; z-index: 1;">
          <div class="ct-hero-text">
            <h1>Let's <span class="c">Connect</span> &amp; <span class="o">Learn</span></h1>
            <p>Questions about a course, a payment, your account or a partnership? Send us a message, or reach the admissions team directly.</p>
            <div class="ct-quick">
                <a class="call" href="tel:+254742380183"><i class="fas fa-phone-alt"></i> Call 0742 380 183</a>
                <a class="mail" href="mailto:info@skopedigital.co.ke"><i class="fas fa-envelope"></i> info@skopedigital.co.ke</a>
                <a class="wa" href="https://chat.whatsapp.com/IT9spiypi6q3VJX2hn4hhT" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> Join our community</a>
            </div>
          </div>
          <div class="ct-hero-art">
            <picture><source type="image/webp" srcset="assets/images/promo/contact.webp"><img src="assets/images/promo/contact.jpg" width="1100" height="733" alt="A smiling Skope Digital Academy team member on the phone at her laptop, ready to help." fetchpriority="high"></picture>
            <div class="ct-hero-chip"><i class="fas fa-headset"></i><span><b>We're here to help</b><small>Call, WhatsApp or email us</small></span></div>
          </div>
        </div>
    </section>

    <!-- Contact card -->
    <main class="ct-main">
        <div class="container">
            <div class="ct-card">
                <aside class="ct-info">
                    <h2>Contact information</h2>
                    <p>Talk to a real person at the Academy. We answer messages within one working day.</p>
                    <div class="ct-rows">
                        <div class="ct-row">
                            <div class="ic"><i class="fas fa-phone-alt"></i></div>
                            <div><small>Admissions hotline</small><a href="tel:+254742380183">0742 380 183</a><span class="sub">Calls and M-Pesa payment queries</span></div>
                        </div>
                        <div class="ct-row">
                            <div class="ic"><i class="fas fa-envelope"></i></div>
                            <div><small>Email</small><a href="mailto:info@skopedigital.co.ke">info@skopedigital.co.ke</a><span class="sub">General and course enquiries</span></div>
                        </div>
                        <div class="ct-row">
                            <div class="ic"><i class="fas fa-map-marker-alt"></i></div>
                            <div><small>Main campus</small><strong>Kisumu, Kenya</strong><span class="sub">Visits by appointment</span></div>
                        </div>
                    </div>
                    <div class="ct-hours" aria-label="Opening hours">
                        <div><span>Mon – Fri</span><span>08:00 – 18:00</span></div>
                        <div><span>Sunday</span><span>08:00 – 18:00</span></div>
                        <div class="closed"><span>Saturday</span><span>Closed</span></div>
                        <div class="ct-open-now" id="openNow" hidden></div>
                    </div>
                </aside>

                <section class="ct-form" aria-labelledby="formTitle">
                    <h2 id="formTitle">Send us a message</h2>
                    <p>Fill in the form and the right team will get back to you by email.</p>

                    <?php if ($notice): [$kind, $icon, $title, $text] = $notice; ?>
                        <div class="ct-notice <?= $kind ?>" role="status">
                            <i class="fas <?= $icon ?>"></i>
                            <div><strong><?= htmlspecialchars($title) ?></strong><?= htmlspecialchars($text) ?></div>
                        </div>
                    <?php endif; ?>

                    <form action="contact-handler" method="POST" id="contactForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                        <fieldset class="ct-topics">
                            <legend class="ct-legend">What is it about?</legend>
                            <?php $first = true; foreach ($topics as $value => [$icon, $label]):
                                $checked = $preselect !== '' ? $preselect === $value : $first; $first = false; ?>
                                <label class="ct-topic">
                                    <input type="radio" name="subject" value="<?= htmlspecialchars($value) ?>" <?= $checked ? 'checked' : '' ?>>
                                    <span><i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>

                        <div class="ct-grid2">
                            <div class="ct-field">
                                <label for="ctName">Full name</label>
                                <input class="ct-input" type="text" id="ctName" name="name" autocomplete="name" maxlength="120" placeholder="Your full name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                            </div>
                            <div class="ct-field">
                                <label for="ctEmail">Email address</label>
                                <input class="ct-input" type="email" id="ctEmail" name="email" autocomplete="email" maxlength="190" placeholder="you@example.com" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="ct-field">
                            <label for="ctPhone">Phone number <em>(optional)</em></label>
                            <input class="ct-input" type="tel" id="ctPhone" name="phone" autocomplete="tel" maxlength="30" placeholder="e.g. 0712 345 678">
                        </div>
                        <div class="ct-field">
                            <label for="ctMessage">Message</label>
                            <textarea class="ct-input" id="ctMessage" name="message" maxlength="5000" placeholder="Tell us how we can help. Include a course name or payment reference if you have one." required></textarea>
                            <div class="ct-count"><span id="ctCount">0</span> / 5000</div>
                        </div>

                        <button type="submit" class="ct-submit" id="submitBtn"><i class="fas fa-paper-plane"></i> Send message</button>
                        <p class="ct-privacy">We only use your details to reply. See our <a href="privacy">privacy policy</a>.</p>
                    </form>
                </section>
            </div>
        </div>
    </main>

    <!-- FAQ -->
    <section class="ct-faq">
        <div class="container">
            <div class="ct-head">
                <span class="ct-badge"><i class="fas fa-lightbulb"></i> Quick answers</span>
                <h2>Before you write</h2>
                <p>The questions we hear most often.</p>
            </div>
            <div class="ct-faq-grid">
                <details>
                    <summary>What's the difference between self-paced and structured? <i class="fas fa-plus"></i></summary>
                    <p>Self-paced opens every lesson straight away, with no deadlines. The structured track releases a new week of lessons on a schedule, with due dates and quiz checkpoints, alongside a cohort. Each course page shows the tracks and prices on offer.</p>
                </details>
                <details>
                    <summary>How do I pay for a course? <i class="fas fa-plus"></i></summary>
                    <p>Choose your track on the course page, then pay online or by M-Pesa or bank transfer and upload your proof. Manual payments are verified within 24 hours and your access opens automatically.</p>
                </details>
                <details>
                    <summary>Can I try lessons before paying? <i class="fas fa-plus"></i></summary>
                    <p>Yes. Our <a href="learn">free tutorials</a> are open to everyone, and many courses have free preview lessons you can read first.</p>
                </details>
                <details>
                    <summary>Do you offer scholarships? <i class="fas fa-plus"></i></summary>
                    <p>We run merit-based scholarship cycles. See <a href="scholarships">current scholarships</a>, or pick “Scholarships” above to ask about the next intake.</p>
                </details>
                <details>
                    <summary>I can't sign in to my account. <i class="fas fa-plus"></i></summary>
                    <p>Use <a href="forgot-password">Forgot password</a> to reset it. If that doesn't work, send us a message with “Tech support” selected and the email you registered with.</p>
                </details>
                <details>
                    <summary>Can my organisation train its team with you? <i class="fas fa-plus"></i></summary>
                    <p>Yes. Choose “Partnerships” in the form and tell us about your team size and goals, and we'll get back to you with options.</p>
                </details>
            </div>
        </div>
    </section>

    <!-- Map -->
    <section class="ct-map">
        <div class="container">
            <div class="ct-map-card">
                <iframe src="https://maps.google.com/maps?q=Kisumu%2C%20Kenya&amp;z=13&amp;output=embed" title="Map of Kisumu, Kenya" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                <div class="ct-map-tag">
                    <i class="fas fa-map-marker-alt"></i>
                    <div><strong>Kisumu, Kenya</strong><a href="https://maps.google.com/?q=Kisumu%2C%20Kenya" target="_blank" rel="noopener">Open in Google Maps <i class="fas fa-arrow-up-right-from-square" style="font-size:.7rem;color:inherit"></i></a></div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
(() => {
    const form = document.getElementById('contactForm');
    const msg = document.getElementById('ctMessage');
    const count = document.getElementById('ctCount');
    const btn = document.getElementById('submitBtn');
    const update = () => { count.textContent = msg.value.length; };
    msg.addEventListener('input', update); update();

    form.addEventListener('submit', (ev) => {
        // Use the browser's own messages for missing/invalid fields.
        if (!form.checkValidity()) { ev.preventDefault(); form.reportValidity(); return; }
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
    });

    // Open / closed right now, in Kenya time (Mon–Fri & Sun 08:00–18:00, Saturday closed).
    const el = document.getElementById('openNow');
    try {
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Africa/Nairobi', weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false })
            .formatToParts(new Date()).reduce((o, p) => (o[p.type] = p.value, o), {});
        const mins = (+parts.hour % 24) * 60 + (+parts.minute);
        const open = parts.weekday !== 'Sat' && mins >= 480 && mins < 1080;
        el.innerHTML = '<i class="fas fa-circle"></i> ' + (open ? 'Open now' : 'Closed now – leave a message and we\'ll reply when we open');
        el.style.color = open ? '#34d399' : '#fbbf24';
        el.hidden = false;
    } catch (e) { /* older browsers: hours table is enough */ }
})();
</script>

</body>
</html>
