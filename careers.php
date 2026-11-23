<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/careers.php';
$user = isLoggedIn() ? currentUser() : null;

$jobs = [];
try { sda_careers_ensure($pdo); $jobs = sda_open_vacancies($pdo); } catch (Throwable $e) { error_log('careers: ' . $e->getMessage()); }
$applyEmail = sda_careers_email();
$withDeadline = array_filter(array_column($jobs, 'deadline'));
$deadlines = array_values(array_unique($withDeadline));
$sharedDeadline = count($deadlines) === 1 && count($withDeadline) === count($jobs) ? $deadlines[0] : null;

function cr_date(string $d): string { return date('j M Y', strtotime($d)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Careers – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Careers', 'path' => 'careers', 'image' => 'assets/images/promo/careers.jpg', 'image_alt' => 'The Skope Digital Academy team', 'description' => 'Join the Skope Digital Academy team. See open positions in academics, technology, student affairs, marketing and partnerships, and how to apply.', 'breadcrumbs' => [['Careers', 'careers']]]); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
<style>
    body { background: #fff; }
    .cr { --c: #00BFFF; --c2: #0080FF; --o: #FF8C00; --ink: #0f172a; --ink2: #334155; --mut: #64748b; --line: #e2e8f0; --soft: #f8fafc; font-family: 'Inter', sans-serif; color: var(--ink2); }
    .cr h1, .cr h2, .cr h3 { font-family: 'Poppins', sans-serif; color: var(--ink); letter-spacing: -0.02em; }
    .cr-kick { display: inline-flex; align-items: center; gap: 8px; padding: 7px 16px; border-radius: 99px; background: rgba(0,191,255,.1); border: 1px solid rgba(0,191,255,.25); color: #0284c7; font-size: .72rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }

    /* Hero */
    .careers-hero { position: relative; overflow: hidden; padding: 76px 0 90px; background: linear-gradient(135deg, #f0f9ff 0%, #fff 55%, #fff7ed 100%); border-bottom: 1px solid var(--line); }
    .careers-hero::before { content: ''; position: absolute; width: 460px; height: 460px; top: -180px; right: -120px; border-radius: 50%; background: radial-gradient(circle, rgba(0,191,255,.16), transparent 70%); pointer-events: none; }
    .cr-hero-grid { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); gap: 48px; align-items: center; }
    .careers-hero h1 { font-size: clamp(2.3rem, 5.2vw, 3.6rem); font-weight: 900; line-height: 1.08; margin: 18px 0 16px; }
    .careers-hero h1 .c { color: var(--c); } .careers-hero h1 .o { color: var(--o); }
    .careers-hero p { font-size: 1.08rem; line-height: 1.7; color: var(--mut); max-width: 540px; }
    .cr-tag { font-family: 'Poppins', sans-serif; font-weight: 800; color: var(--o); font-size: 1rem; margin-top: 10px; }
    .cr-cta { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 28px; }
    .cr-btn { display: inline-flex; align-items: center; gap: 9px; padding: 13px 22px; border-radius: 12px; font-weight: 800; font-size: .92rem; text-decoration: none; transition: transform .15s, box-shadow .15s; }
    .cr-btn.pri { background: linear-gradient(135deg, var(--c), var(--c2)); color: #fff; box-shadow: 0 12px 26px -12px rgba(0,128,255,.7); }
    .cr-btn.ghost { background: #fff; color: var(--ink); border: 1px solid var(--line); }
    .cr-btn:hover { transform: translateY(-2px); }
    .cr-art { position: relative; }
    .cr-art img { display: block; width: 100%; height: auto; aspect-ratio: 1100 / 619; object-fit: cover; border-radius: 26px; border: 6px solid #fff; box-shadow: 0 30px 60px -28px rgba(15,23,42,.45); }
    .cr-chip { position: absolute; left: -18px; bottom: 22px; display: flex; gap: 10px; align-items: center; padding: 10px 14px 10px 10px; border-radius: 14px; background: #fff; box-shadow: 0 14px 34px -14px rgba(15,23,42,.35); }
    .cr-chip i { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: #fff7ed; color: var(--o); }
    .cr-chip b { display: block; font-size: .85rem; color: var(--ink); } .cr-chip small { display: block; font-size: .72rem; color: var(--mut); }

    .cr-sec { padding: 84px 0; }
    .cr-sec.soft { background: var(--soft); border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
    .cr-head { text-align: center; max-width: 680px; margin: 0 auto 44px; }
    .cr-head h2 { font-size: clamp(1.8rem, 3.6vw, 2.5rem); font-weight: 800; margin: 14px 0 10px; }
    .cr-head h2 span { color: var(--c); }
    .cr-head p { color: var(--mut); line-height: 1.7; }

    /* Jobs */
    .cr-jobs { max-width: 920px; margin: 0 auto; display: grid; gap: 18px; }
    .cr-job { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 24px 26px; transition: border-color .2s, box-shadow .2s; }
    .cr-job:hover { border-color: rgba(0,191,255,.45); box-shadow: 0 18px 40px -26px rgba(15,23,42,.35); }
    .cr-job-top { display: flex; gap: 18px; align-items: flex-start; }
    .cr-num { flex: 0 0 auto; width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; font: 800 1rem 'Poppins', sans-serif; color: #fff; background: linear-gradient(135deg, var(--c), var(--c2)); }
    .cr-job:nth-child(even) .cr-num { background: linear-gradient(135deg, #ffa733, var(--o)); }
    .cr-job h3 { font-size: 1.15rem; font-weight: 800; margin: 2px 0 8px; line-height: 1.3; }
    .cr-meta { display: flex; flex-wrap: wrap; gap: 6px 8px; margin-bottom: 10px; }
    .cr-pill { display: inline-flex; align-items: center; gap: 6px; font-size: .74rem; font-weight: 700; padding: 4px 10px; border-radius: 99px; background: #f1f5f9; color: #475569; }
    .cr-pill.dl { background: #fff7ed; color: #c2410c; }
    .cr-job p.sum { color: var(--mut); line-height: 1.65; font-size: .93rem; margin: 0; }
    .cr-job details { margin-top: 14px; border-top: 1px dashed var(--line); padding-top: 12px; }
    .cr-job summary { cursor: pointer; font-weight: 700; font-size: .88rem; color: #0284c7; list-style: none; display: inline-flex; align-items: center; gap: 8px; }
    .cr-job summary::-webkit-details-marker { display: none; }
    .cr-job summary i { transition: transform .2s; } .cr-job details[open] summary i { transform: rotate(90deg); }
    .cr-job h4 { font: 800 .78rem 'Inter', sans-serif; text-transform: uppercase; letter-spacing: .08em; color: var(--ink); margin: 14px 0 8px; }
    .cr-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 8px; }
    .cr-list li { display: flex; gap: 10px; line-height: 1.55; font-size: .92rem; }
    .cr-list li i { color: var(--c); margin-top: 4px; font-size: .8rem; flex: 0 0 auto; }
    .cr-job-foot { display: flex; justify-content: flex-end; margin-top: 16px; }
    .cr-job-foot .cr-btn { padding: 10px 18px; font-size: .86rem; }
    .cr-empty { max-width: 640px; margin: 0 auto; text-align: center; background: #fff; border: 1px dashed #cbd5e1; border-radius: 20px; padding: 40px 24px; }
    .cr-empty > i { font-size: 2rem; color: var(--c); margin-bottom: 12px; }

    /* Qualities + how to apply */
    .cr-two { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 1040px; margin: 0 auto; }
    .cr-box { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 28px; }
    .cr-box h3 { font-size: 1.2rem; font-weight: 800; margin: 0 0 10px; display: flex; gap: 10px; align-items: center; }
    .cr-box h3 i { width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; background: #e0f2fe; color: #0284c7; font-size: .95rem; }
    .cr-box.apply h3 i { background: #fff7ed; color: var(--o); }
    .cr-box > p { color: var(--mut); font-size: .92rem; line-height: 1.6; margin: 0 0 16px; }
    .cr-how { margin-top: 20px; padding: 16px; border-radius: 14px; background: var(--soft); display: grid; gap: 10px; font-size: .9rem; }
    .cr-how div { display: flex; gap: 10px; align-items: baseline; } .cr-how b { color: var(--ink); min-width: 92px; }
    .cr-how a { color: #0284c7; font-weight: 700; overflow-wrap: anywhere; }
    .cr-note { font-size: .82rem; color: var(--mut); margin: 14px 0 0; display: flex; gap: 8px; }

    /* Closing band */
    .cr-band { background: linear-gradient(135deg, #0b1324, #12305a); color: #cbd5e1; text-align: center; padding: 72px 0; }
    .cr-band h2 { color: #fff; font-size: clamp(1.7rem, 3.6vw, 2.4rem); font-weight: 800; margin: 0 0 12px; }
    .cr-band h2 span { color: var(--c); }
    .cr-band p { max-width: 620px; margin: 0 auto 26px; line-height: 1.7; }
    .cr-band .tag { font-family: 'Poppins', sans-serif; color: var(--o); font-weight: 800; letter-spacing: .04em; margin-top: 22px; }

    @media (max-width: 900px) {
        .cr-hero-grid { grid-template-columns: 1fr; gap: 30px; text-align: center; }
        .careers-hero p { margin: 0 auto; } .cr-cta { justify-content: center; }
        .cr-art { max-width: 580px; margin: 0 auto; } .cr-chip { left: 10px; bottom: 10px; }
        .cr-two { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .cr-sec { padding: 56px 0; }
        .cr-job { padding: 18px; } .cr-job-top { gap: 12px; } .cr-num { width: 38px; height: 38px; border-radius: 11px; font-size: .9rem; }
        .cr-job-foot .cr-btn { width: 100%; justify-content: center; }
        .cr-box { padding: 20px; } .cr-how div { flex-direction: column; gap: 2px; }
    }
</style>
</head>
<body>

<?php require_once 'includes/nav.php'; ?>

<main class="cr">
<section class="careers-hero">
    <div class="container cr-hero-grid">
        <div>
            <span class="cr-kick"><i class="fas fa-briefcase"></i> Career Opportunities</span>
            <h1>Join the <span class="c">Skope Digital</span> <span class="o">Academy</span> Team</h1>
            <p>We are looking for passionate, innovative, committed and results-oriented people who share our vision of transforming education through technology, empowering learners and creating opportunities through digital skills.</p>
            <div class="cr-tag">Learn Smart. Achieve More.</div>
            <div class="cr-cta">
                <a href="#openings" class="cr-btn pri"><i class="fas fa-list-check"></i> <?= $jobs ? 'View open positions' : 'Current openings' ?></a>
                <a href="#apply" class="cr-btn ghost"><i class="fas fa-paper-plane"></i> How to apply</a>
            </div>
        </div>
        <div class="cr-art">
            <picture>
                <source srcset="assets/images/promo/careers.webp" type="image/webp">
                <img src="assets/images/promo/careers.jpg" width="1100" height="619" alt="Smiling Skope Digital Academy team members in branded shirts holding up certificates of completion." fetchpriority="high">
            </picture>
            <div class="cr-chip"><i class="fas fa-user-plus"></i><div><b><?= $jobs ? 'We\'re hiring' : 'Join our team' ?></b><small><?= $jobs ? count($jobs) . ' open position' . (count($jobs) === 1 ? '' : 's') : 'New roles are posted here' ?></small></div></div>
        </div>
    </div>
</section>

<section class="cr-sec" id="openings">
    <div class="container">
        <div class="cr-head">
            <span class="cr-kick"><i class="fas fa-layer-group"></i> Available Positions</span>
            <h2>Current <span>Openings</span></h2>
            <p>Open a role to see its key responsibilities, then apply online. Shortlisting is based on a role-specific online assessment.</p>
        </div>
        <?php if (!$jobs): ?>
            <div class="cr-empty"><i class="fas fa-hourglass-half"></i><h3>No open positions right now</h3>
                <p style="color:#64748b;line-height:1.6;margin:8px 0 0">New roles are posted on this page. You can still send your CV to <a href="mailto:<?= e($applyEmail) ?>" style="color:#0284c7;font-weight:700"><?= e($applyEmail) ?></a> for future openings.</p></div>
        <?php else: ?>
        <div class="cr-jobs">
            <?php foreach ($jobs as $i => $j): $resp = sda_lines($j['responsibilities']); $reqs = sda_lines($j['requirements']); ?>
            <article class="cr-job" id="job-<?= (int)$j['id'] ?>">
                <div class="cr-job-top">
                    <div class="cr-num"><?= $i + 1 ?></div>
                    <div style="min-width:0;flex:1">
                        <h3><?= e($j['title']) ?></h3>
                        <div class="cr-meta">
                            <?php if ($j['department']): ?><span class="cr-pill"><i class="fas fa-tag"></i> <?= e($j['department']) ?></span><?php endif; ?>
                            <span class="cr-pill"><i class="fas fa-briefcase"></i> <?= e($j['job_type']) ?></span>
                            <?php if ($j['location']): ?><span class="cr-pill"><i class="fas fa-location-dot"></i> <?= e($j['location']) ?></span><?php endif; ?>
                            <?php if ($j['deadline']): ?><span class="cr-pill dl"><i class="fas fa-calendar-day"></i> Apply by <?= e(cr_date($j['deadline'])) ?></span><?php endif; ?>
                        </div>
                        <p class="sum"><?= e($j['summary']) ?></p>
                    </div>
                </div>
                <?php if ($resp || $reqs): ?>
                <details>
                    <summary><i class="fas fa-chevron-right"></i> Key responsibilities<?= $reqs ? ' &amp; requirements' : '' ?></summary>
                    <?php if ($resp): ?><h4>Key responsibilities</h4><ul class="cr-list"><?php foreach ($resp as $l): ?><li><i class="fas fa-circle-check"></i><span><?= e($l) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
                    <?php if ($reqs): ?><h4>Requirements</h4><ul class="cr-list"><?php foreach ($reqs as $l): ?><li><i class="fas fa-star"></i><span><?= e($l) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
                </details>
                <?php endif; ?>
                <div class="cr-job-foot"><a class="cr-btn pri" href="careers-apply?job=<?= (int)$j['id'] ?>"><i class="fas fa-paper-plane"></i> Apply for this role</a></div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="cr-sec soft" id="apply">
    <div class="container">
        <div class="cr-two">
            <div class="cr-box">
                <h3><i class="fas fa-user-check"></i> Who we're looking for</h3>
                <p>We welcome applications from people who show:</p>
                <ul class="cr-list">
                    <li><i class="fas fa-circle-check"></i><span>Strong communication and interpersonal skills.</span></li>
                    <li><i class="fas fa-circle-check"></i><span>Professionalism, creativity and innovation.</span></li>
                    <li><i class="fas fa-circle-check"></i><span>Excellent organisational and teamwork abilities.</span></li>
                    <li><i class="fas fa-circle-check"></i><span>A commitment to education and digital transformation.</span></li>
                    <li><i class="fas fa-circle-check"></i><span>Initiative, accountability and a results-oriented mindset.</span></li>
                </ul>
            </div>
            <div class="cr-box apply">
                <h3><i class="fas fa-paper-plane"></i> How to apply</h3>
                <p>Apply online with the button on each role. Have ready:</p>
                <ul class="cr-list">
                    <li><i class="fas fa-file-lines"></i><span>An updated Curriculum Vitae (CV).</span></li>
                    <li><i class="fas fa-envelope-open-text"></i><span>A cover letter for the position you are applying for.</span></li>
                    <li><i class="fas fa-graduation-cap"></i><span>Relevant academic and professional qualifications.</span></li>
                    <li><i class="fas fa-folder-open"></i><span>A portfolio or supporting documents, where applicable.</span></li>
                </ul>
                <div class="cr-how">
                    <div><b>Step 1</b><span>Submit the online application with your CV.</span></div>
                    <div><b>Step 2</b><span>Complete the timed online assessment sent to your email.</span></div>
                    <div><b>Step 3</b><span>Shortlisted candidates are invited to interview.</span></div>
                    <div><b>Questions</b><a href="mailto:<?= e($applyEmail) ?>"><?= e($applyEmail) ?></a></div>
                    <?php if ($sharedDeadline): ?><div><b>Deadline</b><span style="color:#c2410c;font-weight:700"><?= e(cr_date($sharedDeadline)) ?></span></div>
                    <?php elseif ($deadlines): ?><div><b>Deadline</b><span>See the closing date on each role.</span></div><?php endif; ?>
                </div>
                <p class="cr-note"><i class="fas fa-circle-info" style="margin-top:3px"></i> Only shortlisted candidates will be contacted.</p>
            </div>
        </div>
    </div>
</section>

<section class="cr-band">
    <div class="container">
        <h2>Be part of the future of <span>digital learning</span>.</h2>
        <p>Join a team committed to equipping people with relevant digital skills, expanding access to education and creating opportunities for the next generation.</p>
        <a href="<?= $jobs ? '#openings' : 'mailto:' . e($applyEmail) ?>" class="cr-btn pri"><i class="fas fa-rocket"></i> <?= $jobs ? 'See open positions' : 'Send us your CV' ?></a>
        <div class="tag">SKOPE DIGITAL ACADEMY · Learn Smart. Achieve More.</div>
    </div>
</section>
</main>

<?php require_once 'includes/footer.php'; ?>

</body>
</html>
