<?php
/**
 * Unlock a certificate: /certificate-pay?code=SDA-…
 * The learner finished the course (certificate generated, status 'pending'); they pay the
 * certificate fee set by the admin – online (Paystack) or by M-Pesa/bank with proof that an
 * admin verifies (admin/payments, admin/verifications).
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/progress.php';

$code = strtoupper(trim((string)($_GET['code'] ?? $_POST['code'] ?? '')));
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'certificate-pay?code=' . rawurlencode($code);
    header('Location: login');
    exit;
}
$uid = (int)$_SESSION['user_id'];
$st = $pdo->prepare("SELECT c.*, co.title AS course_title, co.slug AS course_slug FROM certificates c LEFT JOIN courses co ON co.id = c.course_id
                     WHERE c.verification_code = ? AND c.student_id = ? LIMIT 1");
$st->execute([$code, $uid]);
$cert = $st->fetch();
if (!$cert) { http_response_code(404); }
if ($cert && $cert['status'] === 'approved') { header('Location: certificate?code=' . rawurlencode($code)); exit; }

$fee = $cert ? (float)$cert['fee'] : 0.0;
$pending = null;
if ($cert) {
    $p = $pdo->prepare("SELECT * FROM payments WHERE certificate_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $p->execute([$cert['id']]);
    $pending = $p->fetch() ?: null;
}
$error = '';
$success = '';

// Coupon: "Have a coupon?" applies a discount to the certificate fee (validated server-side).
require_once 'includes/coupons.php';
$coupon_code = coupon_normalize($_POST['coupon'] ?? $_GET['coupon'] ?? '');
$coupon = null;
$coupon_error = '';
$pay_fee = $fee;
if ($cert && $coupon_code !== '' && $cert['status'] === 'pending' && $fee > 0 && !$pending) {
    $cc = coupon_check($pdo, $coupon_code, $uid, 'certificate', $cert['course_id'] ? (int)$cert['course_id'] : null, $fee);
    if ($cc['ok']) { $coupon = $cc; $pay_fee = $cc['final']; }
    else $coupon_error = $cc['error'];
}

if ($cert && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['free_coupon'])) {
    // The coupon covers the whole fee: unlock now, no payment needed.
    if (!verifyCsrf()) $error = 'Your session expired. Please try again.';
    elseif (!$coupon || $pay_fee > 0) $error = $coupon_error ?: 'That coupon does not cover the full fee.';
    else {
        $pdo->beginTransaction();
        if (unlock_certificate($pdo, (int)$cert['id'], $fee)) {
            coupon_redeem($pdo, (int)$coupon['coupon']['id'], $uid, $coupon['discount'], null, null, (int)$cert['id']);
        }
        $pdo->commit();
        header('Location: certificate?code=' . rawurlencode($code));
        exit;
    }
}

if ($cert && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $file = $_FILES['payment_proof'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime = $file['error'] === 0 ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
    if (!verifyCsrf()) $error = 'Your session expired. Please try again.';
    elseif ($pending) $error = 'You already sent a payment for this certificate. We\'ll unlock it as soon as it is verified.';
    elseif ($coupon_code !== '' && !$coupon) $error = $coupon_error;
    elseif ($amount + 0.001 < $pay_fee) $error = 'The amount entered (' . money($amount) . ') is less than the certificate fee (' . money($pay_fee) . ').';
    elseif ($file['error'] !== 0) $error = 'Please attach your proof of payment.';
    elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true) || !in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) $error = 'Only JPG, PNG or PDF files are allowed.';
    elseif ($file['size'] > 5 * 1024 * 1024) $error = 'The file must be smaller than 5 MB.';
    else {
        $name = 'PROOF_CERT_' . $uid . '_' . time() . '.' . $ext;
        if (!is_dir('uploads/proofs/')) mkdir('uploads/proofs/', 0755, true);
        if (move_uploaded_file($file['tmp_name'], 'uploads/proofs/' . $name)) {
            $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, proof_file, transaction_message, status, payment_type, certificate_id, coupon_id, discount)
                           VALUES (?, ?, ?, ?, ?, 'pending', 'certificate', ?, ?, ?)")
                ->execute([$uid, $cert['course_id'], $amount, $name, mb_substr(trim($_POST['transaction_message'] ?? ''), 0, 500), $cert['id'],
                           $coupon ? (int)$coupon['coupon']['id'] : null, $coupon ? $coupon['discount'] : 0]);
            $success = 'Thank you! We received your proof of payment. Your certificate will unlock as soon as our team verifies it (usually within 24 hours).';
            require_once __DIR__ . '/includes/notify.php';
            $cpUser = $pdo->prepare("SELECT name, email FROM users WHERE id = ?"); $cpUser->execute([$uid]); $cpUser = $cpUser->fetch() ?: ['name' => '', 'email' => ''];
            sda_notify('payment_received', (int)$uid, ['item' => 'Certificate fee', 'amount' => money($amount)], ['inapp' => false]);
            sda_notify_admins('admin_new_payment', ['name' => $cpUser['name'], 'email' => $cpUser['email'], 'amount' => money($amount), 'item' => 'Certificate fee']);
            $p = $pdo->prepare("SELECT * FROM payments WHERE certificate_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
            $p->execute([$cert['id']]);
            $pending = $p->fetch() ?: null;
        } else {
            $error = 'Upload failed. Please try again.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="<?= e(sda_base_href()) ?>">
<title>Unlock your certificate – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Unlock your certificate', 'path' => 'certificate-pay', 'noindex' => true]); ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@700;800&family=Great+Vibes&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="icon" href="favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="assets/pwa/favicon-32.png">
<style>
  :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --blue: #0080ff; --gold: #c9a227; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Inter', sans-serif; background: #f1f5f9; color: var(--ink); }
  .top { display: flex; align-items: center; gap: 10px; max-width: 980px; margin: 0 auto; padding: 18px 16px; }
  .top a { display: inline-flex; align-items: center; gap: 10px; color: var(--ink); text-decoration: none; font-weight: 800; }
  .top img { height: 34px; }
  .top .back { margin-left: auto; font-weight: 600; color: var(--muted); }
  .wrap { max-width: 980px; margin: 0 auto 60px; padding: 0 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 22px; align-items: start; }
  .card { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 24px; }
  h1 { font: 800 1.7rem 'Poppins', sans-serif; margin: 0 0 6px; }
  .muted { color: var(--muted); }
  .preview { position: relative; aspect-ratio: 297/210; border-radius: 10px; overflow: hidden; background: #fffdf8; border: 2px solid var(--gold); margin: 18px 0; text-align: center; padding: 8% 6%; }
  .preview .k { font-size: .62rem; letter-spacing: .3em; color: var(--gold); font-weight: 800; }
  .preview .n { font: 400 2.1rem 'Great Vibes', cursive; margin: 8px 0 4px; }
  .preview .c { font: 700 1rem 'Poppins', sans-serif; color: var(--blue); }
  .preview .lock { position: absolute; inset: 0; display: grid; place-items: center; background: repeating-linear-gradient(-35deg, rgba(15,23,42,.05) 0 14px, transparent 14px 28px); }
  .preview .lock span { background: #0f172a; color: #fff; padding: 8px 14px; border-radius: 99px; font-weight: 700; font-size: .85rem; }
  .fee { display: flex; align-items: baseline; justify-content: space-between; padding: 16px 18px; border-radius: 14px; background: #eff6ff; border: 1px solid #bfdbfe; margin-bottom: 18px; }
  .fee b { font: 800 1.8rem 'Poppins', sans-serif; color: #1d4ed8; }
  .list { margin: 0; padding-left: 18px; color: #334155; line-height: 1.8; font-size: .92rem; }
  .btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 50px; border-radius: 12px; border: 0; font: 700 1rem 'Inter', sans-serif; cursor: pointer; text-decoration: none; }
  .btn.primary { background: linear-gradient(135deg, #00bfff, var(--blue)); color: #fff; }
  .btn.ghost { background: #fff; border: 1.5px dashed #94a3b8; color: var(--ink); }
  .method { border: 1px solid var(--line); border-radius: 14px; padding: 16px; margin: 14px 0; font-size: .92rem; line-height: 1.6; }
  .method h3 { margin: 0 0 6px; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
  .or { text-align: center; color: #94a3b8; font-weight: 700; font-size: .78rem; letter-spacing: .1em; margin: 18px 0 4px; }
  label { display: block; font-weight: 700; font-size: .85rem; margin: 12px 0 6px; }
  input, textarea { width: 100%; padding: 11px 12px; border-radius: 10px; border: 1.5px solid var(--line); font: inherit; }
  .alert { padding: 12px 14px; border-radius: 12px; margin-bottom: 14px; font-size: .92rem; }
  .alert.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
  .alert.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
  .fee s { color: var(--muted); font-size: 1rem; font-weight: 600; margin-right: 6px; font-family: 'Inter', sans-serif; }
  .coupon { border: 1.5px dashed #cbd5e1; border-radius: 14px; padding: 12px 16px; margin-bottom: 16px; font-size: .92rem; }
  .coupon summary { cursor: pointer; font-weight: 700; list-style: none; display: flex; align-items: center; gap: 8px; color: #1d4ed8; }
  .coupon summary::-webkit-details-marker { display: none; }
  .coupon form { display: flex; gap: 8px; margin-top: 10px; }
  .coupon input { flex: 1; min-width: 0; text-transform: uppercase; }
  .coupon button { flex-shrink: 0; padding: 0 18px; border-radius: 10px; border: 0; background: var(--blue); color: #fff; font: 700 .92rem 'Inter', sans-serif; cursor: pointer; }
  .coupon .ok { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; color: #065f46; font-weight: 600; }
  .coupon .ok a { color: var(--muted); font-weight: 600; }
  .coupon .bad { color: #991b1b; font-weight: 600; margin-top: 8px; font-size: .88rem; }
  @media (max-width: 820px) { .wrap { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="top"><a href="./"><img src="assets/images/skope-logo-512.png" alt=""> Skope Digital Academy</a><a class="back" href="student/achievements"><i class="fas fa-arrow-left"></i> My achievements</a></div>
<?php if (!$cert): ?>
  <div class="wrap" style="grid-template-columns:1fr"><div class="card"><h1>Certificate not found</h1><p class="muted">We couldn't find that certificate on your account. Open it from <a href="student/achievements">My achievements</a>.</p></div></div>
<?php else: ?>
<div class="wrap">
  <section class="card">
    <h1>Unlock your certificate</h1>
    <p class="muted">Congratulations on completing <strong><?= e($cert['course_title']) ?></strong>! Your certificate has been generated. Pay the certificate fee to unlock, download and share it.</p>
    <div class="preview" aria-label="Certificate preview">
      <div class="k">CERTIFICATE OF COMPLETION</div>
      <div class="n"><?= e($_SESSION['user_name'] ?? '') ?></div>
      <div class="c"><?= e($cert['course_title']) ?></div>
      <div class="lock"><span><i class="fas fa-lock"></i> Locked preview</span></div>
    </div>
    <ul class="list">
      <li>Your name, the course, completion date<?= $cert['score'] !== null ? ' and your final score (' . (int)$cert['score'] . '%)' : '' ?></li>
      <li>A unique verification code and QR code employers can check</li>
      <li>Download as PDF, print, and add it to LinkedIn</li>
    </ul>
  </section>

  <section class="card">
    <div class="fee"><span>Certificate fee</span><b><?php if ($coupon): ?><s><?= e(money($fee)) ?></s><?php endif; ?><?= $coupon && $pay_fee <= 0 ? 'Free' : e(money($pay_fee)) ?></b></div>
    <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert ok"><?= e($success) ?></div><?php endif; ?>
    <?php if ($pending): ?>
      <div class="alert ok"><i class="fas fa-clock"></i> Payment of <?= e(money((float)$pending['amount'])) ?> received on <?= date('j M Y', strtotime($pending['created_at'])) ?> and awaiting verification. Your certificate will unlock automatically.</div>
    <?php else: ?>
      <div class="coupon">
        <?php if ($coupon): ?>
          <div class="ok"><span><i class="fas fa-circle-check"></i> Coupon <b><?= e($coupon['coupon']['code']) ?></b>: <?= e(coupon_label($coupon['coupon'])) ?> – you save <?= e(money($coupon['discount'])) ?></span><a href="certificate-pay?code=<?= e(rawurlencode($code)) ?>">Remove</a></div>
        <?php else: ?>
          <details <?= $coupon_error ? 'open' : '' ?>>
            <summary><i class="fas fa-ticket"></i> Have a coupon?</summary>
            <form method="get" action="certificate-pay">
              <input type="hidden" name="code" value="<?= e($code) ?>">
              <input type="text" name="coupon" placeholder="Enter coupon code" maxlength="40" value="<?= e($coupon_code) ?>" aria-label="Coupon code" required>
              <button>Apply</button>
            </form>
            <?php if ($coupon_error): ?><div class="bad"><i class="fas fa-circle-exclamation"></i> <?= e($coupon_error) ?></div><?php endif; ?>
          </details>
        <?php endif; ?>
      </div>
      <?php if ($coupon && $pay_fee <= 0): ?>
      <form method="post" action="certificate-pay?code=<?= e(rawurlencode($code)) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="coupon" value="<?= e($coupon['coupon']['code']) ?>">
        <button class="btn primary" name="free_coupon" value="1"><i class="fas fa-unlock"></i> Unlock my certificate – covered by your coupon</button>
      </form>
      <?php else: ?>
      <a class="btn primary" href="checkout?type=certificate&amp;id=<?= (int)$cert['id'] ?><?= $coupon ? '&amp;coupon=' . e(rawurlencode($coupon['coupon']['code'])) : '' ?>"><i class="fas fa-bolt"></i> Pay <?= e(money($pay_fee)) ?> online – instant unlock</a>
      <div class="or">— OR PAY BY M-PESA / BANK —</div>
      <div class="method"><h3><i class="fas fa-mobile-alt" style="color:#16a34a"></i> M-Pesa</h3>Send <b><?= e(money($pay_fee)) ?></b> to <b>0742380183</b> (Peter Sege), then upload the confirmation below.</div>
      <div class="method"><h3><i class="fas fa-university" style="color:#2563eb"></i> Bank transfer</h3>KCB Bank · Account name <b>Peter Sege</b> · Account no. <b>1318989760</b> · Reference <b><?= e($cert['verification_code']) ?></b></div>
      <form method="post" enctype="multipart/form-data" action="certificate-pay?code=<?= e(rawurlencode($code)) ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <?php if ($coupon): ?><input type="hidden" name="coupon" value="<?= e($coupon['coupon']['code']) ?>"><?php endif; ?>
        <label for="amount">Amount paid (<?= e(setting('currency')) ?>)</label>
        <input id="amount" type="number" name="amount" min="<?= $pay_fee ?>" step="0.01" value="<?= $pay_fee ?>" required>
        <label for="proof">Proof of payment (screenshot or PDF)</label>
        <input id="proof" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" required>
        <label for="msg">M-Pesa code or note (optional)</label>
        <input id="msg" name="transaction_message" placeholder="e.g. RKJ123ABC">
        <button class="btn ghost" style="margin-top:16px"><i class="fas fa-upload"></i> Submit proof of payment</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
</body>
</html>
