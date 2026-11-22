<?php
/**
 * Enrollment: step 1 choose a track (self-paced / structured + cohort),
 * step 2 pay whatever is still due (Paystack or manual proof).
 * Prices come from the admin (includes/learning.php); nothing here trusts a posted amount.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';

$id = (int)($_GET['id'] ?? 0);
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = "enroll?id=" . $id;
    header('Location: login?msg=login_to_enroll');
    exit;
}
$user = currentUser();
if ($user['role'] !== 'student') { header('Location: course-details?id=' . $id); exit; }
if (!$id) { header('Location: courses'); exit; }

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND status = 'published'");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { header('Location: courses'); exit; }

$modes = course_modes($c);
$enrollment = get_enrollment($pdo, (int)$user['id'], $id);
$is_active = $enrollment && in_array($enrollment['status'], ['active', 'completed'], true);

$stmt = $pdo->prepare("SELECT * FROM payments WHERE student_id = ? AND course_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user['id'], $id]);
$pending_payment = $stmt->fetch();

// Approved scholarship covers up to 5 new enrollments.
$scholarship = false;
if (!$is_active) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM scholarship_applications WHERE user_id = ? AND status = 'approved'");
    $s->execute([$user['id']]);
    if ($s->fetchColumn() > 0) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status IN ('active','completed')");
        $s->execute([$user['id']]);
        $scholarship = $s->fetchColumn() < 5;
    }
}

$cohorts = in_array('structured', $modes, true) ? open_cohorts($pdo, $id) : [];
$error = '';
$success = '';

// The learner's selection (GET for step 2 links, POST for forms).
$sel_mode = $_POST['mode'] ?? $_GET['mode'] ?? null;
$sel_cohort = $_POST['cohort_id'] ?? $_GET['cohort'] ?? null;
$step = ($sel_mode && in_array($sel_mode, $modes, true)) ? 2 : 1;
$cohort_id = null;
$due = 0.0;
if ($step === 2) {
    [$sel_mode, $cohort_id, $err] = resolve_mode_choice($pdo, $c, $sel_mode, $sel_cohort);
    if ($err) { $error = $err; $step = 1; }
    else {
        $due = $scholarship ? 0.0 : amount_due($c, $sel_mode, $is_active ? $enrollment : null);
        if ($is_active && $enrollment['learning_mode'] === $sel_mode && (int)$enrollment['cohort_id'] === (int)$cohort_id) {
            header('Location: student/classroom?id=' . $id);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    if (!verifyCsrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (isset($_POST['confirm_free'])) {
        if ($due > 0) {
            $error = 'This track needs a payment.';
        } elseif ($is_active && !setting('allow_mode_switch')) {
            $error = 'Track changes are currently disabled. Contact support.';
        } else {
            enroll_student($pdo, (int)$user['id'], $c, $sel_mode, $cohort_id, 0);
            header('Location: student/classroom?id=' . $id . '&welcome=1');
            exit;
        }
    } elseif (isset($_FILES['payment_proof'])) {
        $amount = (float)($_POST['amount'] ?? 0);
        $file = $_FILES['payment_proof'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = $file['error'] === 0 ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : '';
        if ($pending_payment) {
            $error = 'You already have a payment awaiting verification for this course.';
        } elseif ($due <= 0) {
            $error = 'Nothing to pay for this track.';
        } elseif ($amount < $due) {
            $error = 'The amount entered (' . money($amount) . ') is less than the amount due (' . money($due) . ').';
        } elseif ($file['error'] !== 0) {
            $error = 'Please attach your proof of payment.';
        } elseif (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true) || !in_array($finfo, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            $error = 'Only JPG, PNG and PDF files are allowed for proof of payment.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'File size must be less than 5MB.';
        } else {
            $filename = 'PROOF_' . $user['id'] . '_' . time() . '.' . $ext;
            if (!is_dir('uploads/proofs/')) mkdir('uploads/proofs/', 0755, true);
            if (move_uploaded_file($file['tmp_name'], 'uploads/proofs/' . $filename)) {
                $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, proof_file, transaction_message, status, payment_type, learning_mode, cohort_id)
                               VALUES (?, ?, ?, ?, ?, 'pending', 'course', ?, ?)")
                    ->execute([$user['id'], $id, $amount, $filename, trim($_POST['transaction_message'] ?? ''), $sel_mode, $cohort_id]);
                if (!$is_active) {
                    // Hold a pending seat; admin verification activates it with this track.
                    enroll_student($pdo, (int)$user['id'], $c, $sel_mode, $cohort_id, 0, 'pending');
                }
                $success = 'Proof of payment uploaded. Our team will verify it within 24 hours and your ' . mode_label($sel_mode) . ' access will open automatically.';
            } else {
                $error = 'Upload failed. Please try again.';
            }
        }
    }
}

function price_html(array $c, string $mode): string {
    $now = course_price($c, $mode);
    $list = course_list_price($c, $mode);
    if ($now <= 0) return 'Free';
    return ($now < $list ? '<s>' . e(money($list)) . '</s>' : '') . e(money($now));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enroll: <?= e($c['title']) ?> – Skope Digital Academy</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="assets/css/learn.css">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
<style>
  .enroll-page { min-height: 100vh; background: var(--bg-light); padding: 60px 0; }
  .payment-method { border: 1px solid var(--dark-border); border-radius: 12px; padding: 24px; background: #fff; margin-bottom: 24px; }
  .payment-method h3 { margin-bottom: 16px; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; color: var(--primary-dark); }
  .payment-method p { font-size: 0.92rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 4px; }
  .amount-card { background: var(--primary-glow); border: 1px solid var(--primary); padding: 28px; border-radius: 16px; text-align: center; margin-bottom: 28px; }
  .amount-card h2 { margin: 4px 0; font-weight: 800; font-size: 2.1rem; }
  .amount-card p { color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; font-size: 0.78rem; margin: 0; }
  .enroll-head h1 { font-family: 'Poppins', sans-serif; font-size: 1.7rem; margin: 0 0 6px; }
  .enroll-head p { color: var(--text-muted); margin: 0 0 20px; }
  .alert-box { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-weight: 600; }
  .alert-box.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
  .alert-box.info { background: #eff6ff; color: #1e3a8a; border: 1px solid #bfdbfe; }
</style>
</head>
<body>
<div class="enroll-page">
  <div class="container container-sm">
    <a href="<?= $step === 2 ? 'enroll?id=' . $id : 'course-details?id=' . $id ?>" style="display:inline-flex;align-items:center;gap:8px;color:var(--text-muted);text-decoration:none;margin-bottom:24px;">
      <i class="fas fa-arrow-left"></i> <?= $step === 2 ? 'Choose a different track' : 'Back to course' ?>
    </a>

    <?php if ($success): ?>
      <div class="card" style="text-align:center;padding:50px;">
        <div style="width:72px;height:72px;background:var(--success);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:2rem;margin:0 auto 20px;"><i class="fas fa-check"></i></div>
        <h2>Payment submitted</h2>
        <p style="color:var(--text-muted);margin-bottom:28px;"><?= e($success) ?></p>
        <a href="student/" class="btn btn-primary btn-block">Go to my dashboard</a>
      </div>

    <?php elseif ($pending_payment && !$is_active): ?>
      <div class="card" style="text-align:center;padding:50px;">
        <div style="font-size:3.4rem;color:var(--warning);margin-bottom:18px;"><i class="fas fa-clock"></i></div>
        <h2>Payment awaiting verification</h2>
        <p style="color:var(--text-muted);margin-bottom:28px;">You submitted proof of <?= e(money((float)$pending_payment['amount'])) ?><?= $pending_payment['learning_mode'] ? ' for the ' . e(mode_label($pending_payment['learning_mode'])) : '' ?>. We'll open your access as soon as it's verified.</p>
        <a href="student/" class="btn btn-primary btn-block">Go to my dashboard</a>
      </div>

    <?php elseif ($step === 1): ?>
      <div class="card" style="padding:36px;">
        <div class="enroll-head">
          <h1><?= $is_active ? 'Change your learning track' : 'How do you want to learn?' ?></h1>
          <p><?= e($c['title']) ?><?= $is_active ? ' · you are on the <strong>' . e(mode_label($enrollment['learning_mode'])) . '</strong>' : '' ?></p>
        </div>
        <?php if ($error): ?><div class="alert-box err"><?= e($error) ?></div><?php endif; ?>
        <?php if ($scholarship): ?><div class="alert-box info"><i class="fas fa-award"></i> Your approved scholarship covers this course.</div><?php endif; ?>
        <?php if ($is_active && $pending_payment): ?><div class="alert-box info">A track upgrade payment of <?= e(money((float)$pending_payment['amount'])) ?> is awaiting verification.</div><?php endif; ?>

        <form method="get" action="enroll">
          <input type="hidden" name="id" value="<?= $id ?>">
          <div class="mode-grid">
            <?php foreach ($modes as $i => $m):
              $mdue = $scholarship ? 0 : amount_due($c, $m, $is_active ? $enrollment : null);
              $current = $is_active && $enrollment['learning_mode'] === $m;
            ?>
            <label class="mode-card">
              <input type="radio" name="mode" value="<?= $m ?>" <?= ($current || (!$is_active && $i === 0)) ? 'checked' : '' ?> onchange="document.getElementById('cohortBox')?.toggleAttribute('hidden', this.value !== 'structured')">
              <div class="mode-name"><span><i class="fas <?= $m === 'structured' ? 'fa-calendar-week' : 'fa-person-walking' ?>"></i> <?= e(mode_label($m)) ?></span><span class="mode-price"><?= price_html($c, $m) ?></span></div>
              <p><?= e(mode_blurb($m)) ?></p>
              <ul>
                <?php if ($m === 'structured'): ?>
                  <li>New lessons every <?= (int)setting('structured_week_days') ?> days</li>
                  <li>Due dates and quiz checkpoints (<?= (int)setting('structured_pass_score') ?>% to move on)</li>
                <?php else: ?>
                  <li>All lessons open immediately</li>
                  <li>No deadlines – pause and resume any time</li>
                <?php endif; ?>
                <li><?= (int)$c['access_days'] > 0 ? (int)$c['access_days'] . ' days of access' : ((int)setting('default_access_days') > 0 ? (int)setting('default_access_days') . ' days of access' : 'Lifetime access') ?></li>
              </ul>
              <?php if ($current): ?><p><strong>Your current track</strong></p>
              <?php elseif ($is_active): ?><p><strong><?= $mdue > 0 ? 'Upgrade: ' . e(money($mdue)) : 'Switch free' ?></strong></p><?php endif; ?>
            </label>
            <?php endforeach; ?>
          </div>

          <?php if (course_from_price($c) <= 0): $cf = certificate_fee_for($c); ?>
            <div class="alert-box info" style="margin-top:6px"><i class="fas fa-certificate"></i> Learning is <b>free</b> on either track.
              <?= $cf > 0 ? 'When you finish, your certificate is generated and you can unlock it for <b>' . e(money($cf)) . '</b>.' : 'You get a free certificate when you finish.' ?></div>
          <?php endif; ?>
          <?php if (in_array('structured', $modes, true)): ?>
          <div class="cohort-pick" id="cohortBox" <?= (($is_active && $enrollment['learning_mode'] === 'structured') || (!$is_active && $modes[0] === 'structured')) ? '' : 'hidden' ?>>
            <?php if ($cohorts): ?>
              <label class="form-label" for="cohort">Cohort start date</label>
              <select name="cohort" id="cohort">
                <?php foreach ($cohorts as $co): ?>
                  <option value="<?= (int)$co['id'] ?>" <?= $is_active && (int)$enrollment['cohort_id'] === (int)$co['id'] ? 'selected' : '' ?>>
                    <?= e($co['name']) ?> – starts <?= date('D, M j, Y', strtotime($co['start_date'])) ?><?= $co['capacity'] ? ' (' . max(0, $co['capacity'] - $co['seats_taken']) . ' seats left)' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <div class="alert-box info" style="margin:0"><i class="fas fa-calendar-day"></i> No cohort is scheduled, so your structured track starts the day you enroll, with a new week every <?= (int)setting('structured_week_days') ?> days.</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <button class="btn btn-primary btn-block btn-lg" style="margin-top:22px;">Continue <i class="fas fa-arrow-right"></i></button>
        </form>
        <p style="text-align:center;margin-top:14px;font-size:.88rem;color:var(--text-dim)">Not sure yet? <a href="learn?course=<?= $id ?>">Read the free lessons first</a>.</p>
      </div>

    <?php elseif ($due <= 0): ?>
      <div class="card" style="padding:36px;text-align:center;">
        <h1 style="font-family:'Poppins',sans-serif;font-size:1.6rem;"><?= e(mode_label($sel_mode)) ?></h1>
        <p style="color:var(--text-muted)"><?= e($c['title']) ?><?= $cohort_id ? ' · ' . e(find_open_cohort($pdo, $id, $cohort_id)['name'] ?? '') : '' ?></p>
        <?php if ($error): ?><div class="alert-box err"><?= e($error) ?></div><?php endif; ?>
        <div class="amount-card"><p>Amount due</p><h2>Free</h2><?= $scholarship ? '<div>Covered by your scholarship</div>' : ($is_active ? '<div>Already covered by what you paid</div>' : '') ?></div>
        <?php if (course_from_price($c) <= 0 && !$is_active): $cf = certificate_fee_for($c); ?>
          <p style="color:var(--text-muted);font-size:.92rem;margin:-12px 0 20px;"><i class="fas fa-certificate" style="color:#c9a227"></i>
            <?= $cf > 0 ? 'All lessons are free. When you finish, your certificate is generated and you can unlock it for <b>' . e(money($cf)) . '</b>.' : 'All lessons are free, and so is your certificate.' ?></p>
        <?php endif; ?>
        <form method="post" action="enroll?id=<?= $id ?>">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="mode" value="<?= e($sel_mode) ?>">
          <input type="hidden" name="cohort_id" value="<?= (int)$cohort_id ?>">
          <button class="btn btn-primary btn-block btn-lg" name="confirm_free" value="1"><?= $is_active ? 'Switch track' : 'Start learning' ?> <i class="fas fa-arrow-right"></i></button>
        </form>
      </div>

    <?php else: ?>
      <div class="card" style="padding:36px;">
        <div class="enroll-head" style="text-align:center">
          <h1>Checkout</h1>
          <p><?= e($c['title']) ?> · <?= e(mode_label($sel_mode)) ?><?= $cohort_id ? ' · ' . e(find_open_cohort($pdo, $id, $cohort_id)['name'] ?? '') : '' ?></p>
        </div>
        <?php if ($error): ?><div class="alert-box err"><?= e($error) ?></div><?php endif; ?>
        <div class="amount-card">
          <p><?= $is_active ? 'Upgrade amount' : 'Amount due' ?></p>
          <h2><?= e(money($due)) ?></h2>
          <?php if ($is_active): ?><div style="font-size:.85rem;color:var(--text-muted)">You've already paid <?= e(money((float)$enrollment['amount_paid'])) ?>; this covers the difference.</div><?php endif; ?>
        </div>

        <div class="payment-method" style="border:2px solid var(--primary);">
          <h3><i class="fas fa-credit-card"></i> Pay online (instant access)</h3>
          <p style="margin-bottom:16px;">Card, bank or M-Pesa through Paystack.</p>
          <a class="btn btn-primary btn-block btn-lg" href="checkout?type=course&id=<?= $id ?>&mode=<?= e($sel_mode) ?>&cohort=<?= (int)$cohort_id ?>">Pay <?= e(money($due)) ?> with Paystack <i class="fas fa-bolt"></i></a>
        </div>

        <div style="text-align:center;margin:20px 0;color:var(--text-dim);font-weight:700;font-size:.8rem;">— OR PAY MANUALLY —</div>

        <div class="payment-method">
          <h3><i class="fas fa-mobile-alt"></i> M-Pesa</h3>
          <p>1. Open M-Pesa and choose <strong>Send Money</strong>.</p>
          <p>2. Phone number: <strong>0742380183</strong> (Peter Sege).</p>
          <p>3. Amount: <strong><?= e(money($due)) ?></strong>.</p>
        </div>
        <div class="payment-method">
          <h3><i class="fas fa-university"></i> Bank transfer</h3>
          <p>Bank: <strong>KCB Bank</strong> · Account name: <strong>Peter Sege</strong></p>
          <p>Account number: <strong>1318989760</strong></p>
          <p>Reference: <strong><?= e($user['name']) ?> - CID-<?= $id ?></strong></p>
        </div>

        <form method="post" action="enroll?id=<?= $id ?>" enctype="multipart/form-data" style="margin-top:24px;">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="mode" value="<?= e($sel_mode) ?>">
          <input type="hidden" name="cohort_id" value="<?= (int)$cohort_id ?>">
          <div class="form-group">
            <label class="form-label">Amount paid (<?= e(setting('currency')) ?>)</label>
            <input type="number" step="0.01" min="<?= $due ?>" name="amount" class="form-control" required value="<?= $due ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Proof of payment (screenshot or PDF)</label>
            <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" class="form-control" required style="padding-top:8px;">
          </div>
          <div class="form-group">
            <label class="form-label">Message (optional)</label>
            <textarea name="transaction_message" class="form-control" style="min-height:70px;" placeholder="e.g. M-Pesa code RKJ123ABC"></textarea>
          </div>
          <button type="submit" class="btn btn-ghost btn-block" style="border:1px dashed var(--dark-border);">Submit proof of payment <i class="fas fa-upload"></i></button>
          <div style="text-align:center;color:var(--text-dim);font-size:.8rem;margin-top:12px;">Manual verification can take up to 24 hours.</div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
