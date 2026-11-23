<?php
/**
 * Public certificate page: /certificate?code=SDA-26-ABCDE-FGHJK
 * Anyone with the code can view and verify it (like Udemy / Credly). Printable to PDF.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/progress.php';

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
$cert = null;
if ($code !== '' && preg_match('/^[A-Z0-9-]{6,40}$/', $code)) {
    $st = $pdo->prepare("SELECT c.*, u.name AS student_name,
                                co.title AS course_title, co.slug AS course_slug, co.duration_hours, co.total_lessons, co.level AS course_level, co.tutorial_short,
                                p.title AS path_title, p.slug AS path_slug, p.description AS path_description
                         FROM certificates c
                         JOIN users u ON u.id = c.student_id
                         LEFT JOIN courses co ON co.id = c.course_id
                         LEFT JOIN learning_paths p ON p.id = c.path_id
                         WHERE c.verification_code = ? LIMIT 1");
    $st->execute([$code]);
    $cert = $st->fetch() ?: null;
}
// A certificate awaiting its fee is not issued yet: the owner goes to the unlock page, everyone else sees "not found".
if ($cert && $cert['status'] === 'pending') {
    if (isLoggedIn() && (int)$_SESSION['user_id'] === (int)$cert['student_id']) {
        header('Location: certificate-pay?code=' . rawurlencode($code));
        exit;
    }
    $cert = null;
}
$valid = $cert && $cert['status'] === 'approved';
$isPath = $cert && $cert['certificate_type'] === 'path';
$title = $cert ? ($isPath ? $cert['path_title'] : $cert['course_title']) : '';
$pathCourses = [];
if ($isPath) {
    $st = $pdo->prepare("SELECT c.title FROM learning_path_courses lpc JOIN courses c ON c.slug = lpc.course_slug WHERE lpc.path_id = ? ORDER BY lpc.position");
    $st->execute([$cert['path_id']]);
    $pathCourses = $st->fetchAll(PDO::FETCH_COLUMN);
}
$site = rtrim(setting('site_url'), '/');
if ($site === '') $site = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(sda_base_href(), '/');
$publicUrl = $site . '/certificate?code=' . rawurlencode($code);
$issued = $cert ? strtotime($cert['issued_at']) : time();
$linkedin = $cert ? 'https://www.linkedin.com/profile/add?' . http_build_query([
    'startTask' => 'CERTIFICATION_NAME', 'name' => $title, 'organizationName' => 'Skope Digital Academy',
    'issueYear' => date('Y', $issued), 'issueMonth' => date('n', $issued), 'certUrl' => $publicUrl, 'certId' => $code,
]) : '';
$owner = isLoggedIn() && $cert && (int)$_SESSION['user_id'] === (int)$cert['student_id'];
http_response_code($cert ? 200 : 404);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="<?= e(sda_base_href()) ?>">
<title><?= $cert ? e($title) . ' – Certificate – ' . e($cert['student_name']) : 'Certificate not found' ?> – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Verify a Certificate', 'path' => sda_seo_current_path(), 'description' => 'Check that a Skope Digital Academy certificate is genuine using its verification code.', 'noindex' => !empty($_GET['code'])]); ?>
<meta name="robots" content="noindex">
<?php if ($cert): ?>
<meta property="og:title" content="<?= e($cert['student_name']) ?> earned a certificate in <?= e($title) ?>">
<meta property="og:description" content="Verified certificate from Skope Digital Academy · <?= e($code) ?>">
<?php endif; ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Great+Vibes&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
<style>
  :root { --navy: #0b1324; --blue: #0080ff; --cyan: #00bfff; --gold: #c9a227; --gold-2: #e9c75b; --orange: #ff8c00; --ink: #0f172a; --muted: #64748b; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Inter', sans-serif; background: #e9eef5; color: var(--ink); }
  .bar { max-width: 1120px; margin: 0 auto; padding: 18px 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
  .bar a.home { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; color: var(--ink); font-weight: 800; margin-right: auto; }
  .bar a.home img { height: 34px; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: var(--ink); font: 600 .9rem 'Inter', sans-serif; cursor: pointer; text-decoration: none; }
  .btn:hover { border-color: var(--blue); }
  .btn.primary { background: var(--blue); border-color: var(--blue); color: #fff; }
  .btn.li { background: #0a66c2; border-color: #0a66c2; color: #fff; }
  .verify { max-width: 1120px; margin: 0 auto 14px; padding: 0 16px; }
  .verify div { display: flex; gap: 12px; align-items: center; padding: 12px 16px; border-radius: 12px; font-size: .92rem; }
  .verify .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
  .verify .bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
  .verify i { font-size: 1.2rem; }

  .sheet-wrap { max-width: 1120px; margin: 0 auto 40px; padding: 0 16px; }
  .sheet { position: relative; aspect-ratio: 297 / 210; background: #fffdf8; border-radius: 6px; overflow: hidden; box-shadow: 0 30px 60px -30px rgba(15,23,42,.45); container-type: inline-size; }
  .sheet::before { content: ''; position: absolute; inset: 2.2cqw; border: .25cqw solid var(--gold); border-radius: .4cqw; pointer-events: none; }
  .sheet::after { content: ''; position: absolute; inset: 2.9cqw; border: .1cqw solid rgba(201,162,39,.55); pointer-events: none; }
  .corner { position: absolute; width: 22cqw; height: 22cqw; background: radial-gradient(circle at 0 0, rgba(0,128,255,.14), transparent 70%); }
  .corner.tl { top: 0; left: 0; } .corner.br { right: 0; bottom: 0; transform: rotate(180deg); background: radial-gradient(circle at 0 0, rgba(255,140,0,.14), transparent 70%); }
  .stripe { position: absolute; left: 0; top: 0; bottom: 0; width: 1.4cqw; background: linear-gradient(180deg, var(--cyan), var(--blue) 50%, var(--orange)); }
  .content { position: absolute; inset: 5cqw 7cqw 4.5cqw; display: flex; flex-direction: column; align-items: center; text-align: center; }
  .head { display: flex; align-items: center; gap: 1.2cqw; }
  .head img { height: 5.2cqw; }
  .head span { font: 800 1.7cqw 'Inter', sans-serif; letter-spacing: .35cqw; color: var(--navy); text-align: left; line-height: 1.15; }
  .head span em { font-style: normal; color: var(--orange); }
  .kicker { margin-top: 2.6cqw; font: 700 1.15cqw 'Inter', sans-serif; letter-spacing: .55cqw; text-transform: uppercase; color: var(--gold); }
  .title { font: 700 4.4cqw 'Playfair Display', serif; color: var(--navy); margin: .6cqw 0 0; }
  .present { margin-top: 1.8cqw; font-size: 1.35cqw; color: var(--muted); }
  .name { font: 400 6.2cqw 'Great Vibes', cursive; color: var(--navy); line-height: 1.1; margin: .6cqw 0 .2cqw; padding: 0 3cqw .4cqw; border-bottom: .12cqw solid rgba(201,162,39,.6); min-width: 42cqw; }
  .for { margin-top: 1.4cqw; font-size: 1.35cqw; color: var(--muted); }
  .course { font: 700 2.6cqw 'Playfair Display', serif; color: var(--blue); margin: .4cqw 0 0; max-width: 70cqw; }
  .meta { margin-top: 1.4cqw; display: flex; gap: 2.6cqw; font-size: 1.15cqw; color: #475569; flex-wrap: wrap; justify-content: center; }
  .meta b { color: var(--ink); }
  .pathlist { margin-top: .8cqw; font-size: 1.1cqw; color: #475569; }
  .foot { margin-top: auto; width: 100%; display: grid; grid-template-columns: 1fr auto 1fr; align-items: end; gap: 3cqw; }
  .sig { text-align: center; }
  .sig .line { border-top: .12cqw solid #94a3b8; padding-top: .6cqw; font-size: 1.1cqw; color: #475569; }
  .sig .script { font: 400 2.6cqw 'Great Vibes', cursive; color: var(--navy); }
  .seal { width: 11cqw; height: 11cqw; border-radius: 50%; display: grid; place-items: center; text-align: center;
          background: radial-gradient(circle, var(--gold-2), var(--gold)); color: #3b2f06; box-shadow: 0 0 0 .5cqw rgba(201,162,39,.25), inset 0 0 0 .35cqw rgba(255,255,255,.45); }
  .seal div { font: 800 .9cqw 'Inter', sans-serif; letter-spacing: .12cqw; text-transform: uppercase; line-height: 1.35; }
  .seal i { font-size: 2.4cqw; display: block; margin-bottom: .3cqw; }
  .verify-box { display: flex; gap: 1.2cqw; align-items: center; justify-content: flex-end; text-align: left; }
  .verify-box .qr { width: 8cqw; height: 8cqw; background: #fff; padding: .4cqw; border-radius: .4cqw; }
  .verify-box .qr img, .verify-box .qr canvas { width: 100% !important; height: 100% !important; }
  .verify-box small { display: block; font-size: .95cqw; color: var(--muted); line-height: 1.5; }
  .verify-box b { font-size: 1.05cqw; letter-spacing: .05cqw; }
  .void { position: absolute; inset: 0; display: grid; place-items: center; font: 900 12cqw 'Inter', sans-serif; color: rgba(220,38,38,.14); transform: rotate(-18deg); pointer-events: none; }

  .missing { max-width: 560px; margin: 60px auto; background: #fff; border-radius: 16px; padding: 32px; text-align: center; box-shadow: 0 20px 40px -24px rgba(15,23,42,.4); }
  .missing form { display: flex; gap: 8px; margin-top: 18px; }
  .missing input { flex: 1; padding: 12px 14px; border-radius: 10px; border: 1px solid #cbd5e1; font: 600 1rem 'Inter', sans-serif; text-transform: uppercase; }

  /* Sticky top bar (full-width background behind the centred row) + phone fixes */
  .bar { position: sticky; top: 0; z-index: 20; background: rgba(233,238,245,.94); backdrop-filter: blur(8px);
         box-shadow: 0 0 0 100vmax rgba(233,238,245,.94); clip-path: inset(0 -100vmax); }
  .missing { width: calc(100% - 32px); }
  .missing input { min-width: 0; }
  @media (max-width: 480px) {
    .missing { margin-top: 32px; padding: 26px 18px; }
    .missing h1 { font-size: 1.6rem; }
    .missing form { flex-direction: column; }
    .missing .btn { justify-content: center; padding: 12px 16px; }
  }

  @page { size: A4 landscape; margin: 0; }
  @media print {
    body { background: #fff; }
    .bar, .verify, .noprint { display: none !important; }
    .sheet-wrap { max-width: none; margin: 0; padding: 0; }
    .sheet { box-shadow: none; border-radius: 0; width: 297mm; height: 210mm; aspect-ratio: auto; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
<div class="bar noprint">
  <a class="home" href="./"><img src="assets/images/Skope Digital  logo.png" alt=""> Skope Digital Academy</a>
  <?php if ($cert && $valid): ?>
    <button class="btn primary" type="button" onclick="window.print()"><i class="fas fa-file-pdf"></i> Download / Print PDF</button>
    <button class="btn" type="button" id="copyLink"><i class="fas fa-link"></i> Copy link</button>
    <a class="btn li" href="<?= e($linkedin) ?>" target="_blank" rel="noopener"><i class="fab fa-linkedin"></i> Add to LinkedIn</a>
  <?php endif; ?>
  <?php if ($owner): ?><a class="btn" href="student/achievements"><i class="fas fa-trophy"></i> My achievements</a><?php endif; ?>
</div>

<?php if (!$cert): ?>
  <div class="missing">
    <i class="fas fa-magnifying-glass" style="font-size:2rem;color:#94a3b8"></i>
    <h1>Verify a certificate</h1>
    <p style="color:#64748b"><?= $code !== '' ? 'We couldn\'t find a certificate with the code <b>' . e($code) . '</b>. Check the code and try again.' : 'Enter the verification code printed on a Skope Digital Academy certificate.' ?></p>
    <form action="certificate" method="get"><input name="code" placeholder="SDA-26-XXXXX-XXXXX" value="<?= e($code) ?>" required aria-label="Verification code"><button class="btn primary">Verify</button></form>
  </div>
<?php else: ?>
  <div class="verify">
    <?php if ($valid): ?>
      <div class="ok"><i class="fas fa-circle-check"></i><span><b>Verified.</b> This certificate was issued by Skope Digital Academy to <b><?= e($cert['student_name']) ?></b> on <?= date('j F Y', $issued) ?>. Code <b><?= e($code) ?></b>.</span></div>
    <?php else: ?>
      <div class="bad"><i class="fas fa-ban"></i><span><b>Not valid.</b> This certificate has been <?= e($cert['status']) ?> and should not be relied on.</span></div>
    <?php endif; ?>
  </div>
  <div class="sheet-wrap">
    <article class="sheet" aria-label="Certificate">
      <div class="corner tl"></div><div class="corner br"></div><div class="stripe"></div>
      <div class="content">
        <div class="head"><img src="assets/images/Skope Digital  logo.png" alt=""><span>SKOPE <em>DIGITAL</em><br>ACADEMY</span></div>
        <div class="kicker"><?= $isPath ? 'Learning Path Certificate' : 'Certificate of Completion' ?></div>
        <h1 class="title"><?= $isPath ? 'Professional Certificate' : 'Certificate of Achievement' ?></h1>
        <div class="present">This is proudly presented to</div>
        <div class="name"><?= e($cert['student_name']) ?></div>
        <div class="for"><?= $isPath ? 'for successfully completing every course in the learning path' : 'for successfully completing the course' ?></div>
        <h2 class="course"><?= e($title) ?></h2>
        <?php if ($isPath && $pathCourses): ?><div class="pathlist"><?= e(implode('  ·  ', $pathCourses)) ?></div><?php endif; ?>
        <div class="meta">
          <span><i class="far fa-calendar"></i> Issued <b><?= date('j F Y', $issued) ?></b></span>
          <?php if (!$isPath && (float)$cert['duration_hours'] > 0): ?><span><i class="far fa-clock"></i> <b><?= rtrim(rtrim(number_format((float)$cert['duration_hours'], 1), '0'), '.') ?> hours</b> of study</span><?php endif; ?>
          <?php if (!$isPath && (int)$cert['total_lessons'] > 0): ?><span><i class="fas fa-book-open"></i> <b><?= (int)$cert['total_lessons'] ?></b> lessons</span><?php endif; ?>
          <?php if ($cert['score'] !== null): ?><span><i class="fas fa-star"></i> Final assessment <b><?= (int)$cert['score'] ?>%</b></span><?php endif; ?>
        </div>
        <div class="foot">
          <div class="sig"><div class="script">Skope Digital Academy</div><div class="line">Issued by Skope Digital Academy<br>Kisumu, Kenya</div></div>
          <div class="seal"><div><i class="fas fa-award"></i>Verified<br>Achievement</div></div>
          <div class="verify-box">
            <div><small>Verify at</small><b><?= e(rawurldecode(preg_replace('~^https?://~', '', $site))) ?>/certificate</b><small>Code</small><b><?= e($code) ?></b></div>
            <div class="qr" id="qr" aria-label="QR code linking to this certificate"></div>
          </div>
        </div>
      </div>
      <?php if (!$valid): ?><div class="void">VOID</div><?php endif; ?>
    </article>
  </div>
<?php endif; ?>

<?php if ($cert): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  (function () {
    var url = <?= json_encode($publicUrl) ?>;
    if (window.QRCode) new QRCode(document.getElementById('qr'), { text: url, width: 256, height: 256, colorDark: '#0b1324', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    var b = document.getElementById('copyLink');
    if (b) b.addEventListener('click', function () {
      (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () { b.innerHTML = '<i class="fas fa-check"></i> Link copied'; }, function () { window.prompt('Copy this link', url); });
    });
  })();
</script>
<?php endif; ?>
</body>
</html>
