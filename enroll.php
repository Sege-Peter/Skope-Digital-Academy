<?php
/**
 * Enrollment: step 1 choose a track (self-paced / structured + cohort),
 * step 2 pay whatever is still due (Paystack or manual proof).
 * Prices come from the admin (includes/learning.php); nothing here trusts a posted amount.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';
require_once 'includes/coupons.php';

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
$pay_due = 0.0;         // what is left to pay after a coupon
$coupon = null;         // coupon_check() result when a valid coupon is applied
$coupon_error = '';
$coupon_code = coupon_normalize($_POST['coupon'] ?? $_GET['coupon'] ?? '');
if ($step === 2) {
    [$sel_mode, $cohort_id, $err] = resolve_mode_choice($pdo, $c, $sel_mode, $sel_cohort);
    if ($err) { $error = $err; $step = 1; }
    else {
        $due = $scholarship ? 0.0 : amount_due($c, $sel_mode, $is_active ? $enrollment : null);
        if ($is_active && $enrollment['learning_mode'] === $sel_mode && (int)$enrollment['cohort_id'] === (int)$cohort_id) {
            header('Location: student/classroom?id=' . $id);
            exit;
        }
        $pay_due = $due;
        if ($coupon_code !== '' && $due > 0) {
            $cc = coupon_check($pdo, $coupon_code, (int)$user['id'], 'course', $id, $due);
            if ($cc['ok']) { $coupon = $cc; $pay_due = $cc['final']; }
            else $coupon_error = $cc['error'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    if (!verifyCsrf()) {
        $error = 'Your session expired. Please try again.';
    } elseif (isset($_POST['confirm_coupon'])) {
        // A coupon that covers the whole amount: enrol now, no payment needed.
        if (!$coupon || $pay_due > 0) {
            $error = $coupon_error ?: 'That coupon does not cover the full amount.';
        } elseif ($is_active && !setting('allow_mode_switch')) {
            $error = 'Track changes are currently disabled. Contact support.';
        } else {
            $pdo->beginTransaction();
            enroll_student($pdo, (int)$user['id'], $c, $sel_mode, $cohort_id, 0);
            $enr = get_enrollment($pdo, (int)$user['id'], $id);
            coupon_redeem($pdo, (int)$coupon['coupon']['id'], (int)$user['id'], $coupon['discount'], null, $enr ? (int)$enr['id'] : null);
            $pdo->commit();
            require_once __DIR__ . '/includes/notify.php';
            sda_notify_enrolled($pdo, (int)$user['id'], $c, $sel_mode, $cohort_id);
            header('Location: student/classroom?id=' . $id . '&welcome=1');
            exit;
        }
    } elseif (isset($_POST['confirm_free'])) {
        if ($due > 0) {
            $error = 'This track needs a payment.';
        } elseif ($is_active && !setting('allow_mode_switch')) {
            $error = 'Track changes are currently disabled. Contact support.';
        } else {
            enroll_student($pdo, (int)$user['id'], $c, $sel_mode, $cohort_id, 0);
            require_once __DIR__ . '/includes/notify.php';
            sda_notify_enrolled($pdo, (int)$user['id'], $c, $sel_mode, $cohort_id);
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
        } elseif ($due <= 0 || $pay_due <= 0) {
            $error = 'Nothing to pay for this track.';
        } elseif ($coupon_code !== '' && !$coupon) {
            $error = $coupon_error;
        } elseif ($amount + 0.001 < $pay_due) {
            $error = 'The amount entered (' . money($amount) . ') is less than the amount due (' . money($pay_due) . ').';
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
                $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, proof_file, transaction_message, status, payment_type, learning_mode, cohort_id, coupon_id, discount)
                               VALUES (?, ?, ?, ?, ?, 'pending', 'course', ?, ?, ?, ?)")
                    ->execute([$user['id'], $id, $amount, $filename, trim($_POST['transaction_message'] ?? ''), $sel_mode, $cohort_id,
                               $coupon ? (int)$coupon['coupon']['id'] : null, $coupon ? $coupon['discount'] : 0]);
                if (!$is_active) {
                    // Hold a pending seat; admin verification activates it with this track.
                    enroll_student($pdo, (int)$user['id'], $c, $sel_mode, $cohort_id, 0, 'pending');
                }
                $success = 'Proof of payment uploaded. Our team will verify it within 24 hours and your ' . mode_label($sel_mode) . ' access will open automatically.';
                require_once __DIR__ . '/includes/notify.php';
                sda_notify('payment_received', (int)$user['id'], ['item' => $c['title'] . ' (' . mode_label($sel_mode) . ')', 'amount' => money($amount)], ['inapp' => false]);
                sda_notify_admins('admin_new_payment', ['name' => $user['name'], 'email' => $user['email'], 'amount' => money($amount), 'item' => $c['title']]);
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

// ── View data (display only) ────────────────────────────────
$cat = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
$cat->execute([(int)$c['category_id']]);
$cat_name = (string)($cat->fetchColumn() ?: 'Course');
$lc = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
$lc->execute([$id]);
$lesson_count = (int)$lc->fetchColumn();
$is_free_course = course_from_price($c) <= 0;
$cert_fee = certificate_fee_for($c);
$access_days = (int)$c['access_days'] > 0 ? (int)$c['access_days'] : (int)setting('default_access_days');
$access_txt = $access_days > 0 ? $access_days . ' days of access' : 'Lifetime access';
$thumb = !empty($c['thumbnail']) && is_file(__DIR__ . '/uploads/courses/' . $c['thumbnail']) ? 'uploads/courses/' . $c['thumbnail'] : '';
$cohort_row = ($step === 2 && $cohort_id) ? find_open_cohort($pdo, $id, (int)$cohort_id) : null;
$type_label = $c['source'] === 'course' ? 'Free skills course' : ($c['is_open_tutorial'] ? 'Free tutorial' : 'Course');
// Which step the progress bar shows: 1 choose, 2 pay/confirm, 3 done
$stage = $success || ($pending_payment && !$is_active) ? 3 : $step;
$hero_title = $is_active ? 'Change your learning track' : ($step === 1 ? 'How do you want to learn?' : ($due > 0 ? 'Complete your enrolment' : 'You are almost in'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enrol: <?= e($c['title']) ?> – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Enrol', 'path' => 'enroll', 'noindex' => true]); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="assets/css/enroll.css?v=1">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
</head>
<body class="en-body">
<?php require_once 'includes/nav.php'; ?>

<header class="en-hero">
  <div class="en-wrap">
    <nav class="en-crumbs" aria-label="Breadcrumb">
      <a href="courses">Courses</a><i class="fas fa-chevron-right"></i>
      <a href="course-details?id=<?= $id ?>"><?= e($c['title']) ?></a><i class="fas fa-chevron-right"></i>
      <span>Enrol</span>
    </nav>
    <h1><?= e($hero_title) ?></h1>
    <p><?= e($c['title']) ?><?= $is_active ? ' · you are on the ' . e(mode_label($enrollment['learning_mode'])) : '' ?></p>
    <ol class="en-steps" aria-label="Enrolment steps">
      <?php foreach (['Choose your track', $due > 0 || $step === 1 ? 'Confirm & pay' : 'Confirm', 'Start learning'] as $k => $label): $n = $k + 1; ?>
        <li class="<?= $n < $stage ? 'done' : ($n === $stage ? 'on' : '') ?>"><span class="dot"><?= $n < $stage ? '<i class="fas fa-check"></i>' : $n ?></span><span class="lbl"><?= e($label) ?></span></li>
      <?php endforeach; ?>
    </ol>
  </div>
</header>

<main class="en-wrap en-grid">
  <section class="en-main">

  <?php if ($success): ?>
    <div class="en-card en-state">
      <div class="en-state-ic ok"><i class="fas fa-check"></i></div>
      <h2>Payment submitted</h2>
      <p><?= e($success) ?></p>
      <div class="en-state-actions">
        <a href="student/" class="en-btn primary">Go to my dashboard <i class="fas fa-arrow-right"></i></a>
        <a href="learn?course=<?= $id ?>" class="en-btn ghost">Read the free lessons</a>
      </div>
    </div>

  <?php elseif ($pending_payment && !$is_active): ?>
    <div class="en-card en-state">
      <div class="en-state-ic wait"><i class="fas fa-hourglass-half"></i></div>
      <h2>Payment awaiting verification</h2>
      <p>You sent proof of <b><?= e(money((float)$pending_payment['amount'])) ?></b><?= $pending_payment['learning_mode'] ? ' for the ' . e(mode_label($pending_payment['learning_mode'])) : '' ?>,
         on <?= e(date('j M Y, g:i a', strtotime($pending_payment['created_at']))) ?>. We'll open your access as soon as it's checked – usually within 24 hours.</p>
      <div class="en-state-actions">
        <a href="student/" class="en-btn primary">Go to my dashboard <i class="fas fa-arrow-right"></i></a>
        <a href="learn?course=<?= $id ?>" class="en-btn ghost">Read the free lessons meanwhile</a>
      </div>
    </div>

  <?php elseif ($step === 1): ?>
    <?php if ($error): ?><div class="en-alert err"><i class="fas fa-circle-exclamation"></i><?= e($error) ?></div><?php endif; ?>
    <?php if ($scholarship): ?><div class="en-alert ok"><i class="fas fa-award"></i>Your approved scholarship covers this course.</div><?php endif; ?>
    <?php if ($is_active && $pending_payment): ?><div class="en-alert info"><i class="fas fa-hourglass-half"></i>A track upgrade payment of <?= e(money((float)$pending_payment['amount'])) ?> is awaiting verification.</div><?php endif; ?>

    <form method="get" action="enroll" class="en-card">
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="en-card-h">
        <h2><span class="num">1</span> Pick how you'd like to learn</h2>
        <p>You can change track later<?= $is_active ? '' : ' from your dashboard' ?>.</p>
      </div>
      <div class="en-modes">
        <?php foreach ($modes as $i => $m):
          $mdue = $scholarship ? 0 : amount_due($c, $m, $is_active ? $enrollment : null);
          $current = $is_active && $enrollment['learning_mode'] === $m;
          $checked = $current || (!$is_active && ($sel_mode ? $sel_mode === $m : $i === 0));
        ?>
        <label class="en-mode">
          <input type="radio" name="mode" value="<?= e($m) ?>" <?= $checked ? 'checked' : '' ?> onchange="document.getElementById('cohortBox')?.toggleAttribute('hidden', this.value !== 'structured')">
          <span class="en-mode-top">
            <span class="en-mode-ic <?= $m === 'structured' ? 'st' : 'sp' ?>"><i class="fas <?= $m === 'structured' ? 'fa-calendar-week' : 'fa-person-walking' ?>"></i></span>
            <span class="en-radio" aria-hidden="true"></span>
          </span>
          <span class="en-mode-name"><?= e(mode_label($m)) ?></span>
          <span class="en-mode-price"><?= price_html($c, $m) ?></span>
          <span class="en-mode-blurb"><?= e(mode_blurb($m)) ?></span>
          <ul>
            <?php if ($m === 'structured'): ?>
              <li><i class="fas fa-check"></i>New lessons every <?= (int)setting('structured_week_days') ?> days</li>
              <li><i class="fas fa-check"></i>Due dates and quiz checkpoints (<?= (int)setting('structured_pass_score') ?>% to move on)</li>
              <li><i class="fas fa-check"></i>Learn alongside a cohort</li>
            <?php else: ?>
              <?php if (setting('sequential_lessons', '1') === '1'): ?>
              <li><i class="fas fa-check"></i>Start straight away – the next lesson opens when you finish one</li>
              <?php else: ?>
              <li><i class="fas fa-check"></i>Every lesson open straight away</li>
              <?php endif; ?>
              <li><i class="fas fa-check"></i>No deadlines – pause and resume any time</li>
              <li><i class="fas fa-check"></i>Go as fast as you like</li>
            <?php endif; ?>
            <li><i class="fas fa-check"></i><?= e($access_txt) ?></li>
          </ul>
          <?php if ($current): ?><span class="en-mode-tag cur">Your current track</span>
          <?php elseif ($is_active): ?><span class="en-mode-tag"><?= $mdue > 0 ? 'Upgrade: ' . e(money($mdue)) : 'Switch for free' ?></span><?php endif; ?>
        </label>
        <?php endforeach; ?>
      </div>

      <?php if (in_array('structured', $modes, true)): ?>
      <div class="en-cohort" id="cohortBox" <?= (($is_active && $enrollment['learning_mode'] === 'structured') || (!$is_active && ($sel_mode ?: $modes[0]) === 'structured')) ? '' : 'hidden' ?>>
        <?php if ($cohorts): ?>
          <label for="cohort"><i class="fas fa-users"></i> Choose your cohort</label>
          <select name="cohort" id="cohort">
            <?php foreach ($cohorts as $co): ?>
              <option value="<?= (int)$co['id'] ?>" <?= $is_active && (int)$enrollment['cohort_id'] === (int)$co['id'] ? 'selected' : '' ?>>
                <?= e($co['name']) ?> – starts <?= date('D, j M Y', strtotime($co['start_date'])) ?><?= $co['capacity'] ? ' (' . max(0, $co['capacity'] - $co['seats_taken']) . ' seats left)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <p><i class="fas fa-calendar-day"></i> No cohort is scheduled right now, so your structured track starts the day you enrol, with a new week of lessons every <?= (int)setting('structured_week_days') ?> days.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($is_free_course): ?>
        <div class="en-note"><i class="fas fa-certificate"></i><div>Learning is <b>free</b> on either track.
          <?= $cert_fee > 0 ? 'When you finish, your certificate is created and you can unlock it for <b>' . e(money($cert_fee)) . '</b>.' : 'Your certificate is free too.' ?></div></div>
      <?php endif; ?>

      <div class="en-actions">
        <a href="learn?course=<?= $id ?>" class="en-link"><i class="fas fa-book-open"></i> Read the free lessons first</a>
        <button class="en-btn primary lg">Continue <i class="fas fa-arrow-right"></i></button>
      </div>
    </form>

  <?php elseif ($due <= 0): ?>
    <?php if ($error): ?><div class="en-alert err"><i class="fas fa-circle-exclamation"></i><?= e($error) ?></div><?php endif; ?>
    <div class="en-card">
      <div class="en-card-h">
        <h2><span class="num">2</span> Confirm your enrolment</h2>
        <p><?= $scholarship ? 'Covered by your scholarship.' : ($is_active ? 'Already covered by what you paid.' : 'Nothing to pay – just confirm and start.') ?></p>
      </div>
      <div class="en-confirm">
        <div class="en-confirm-ic"><i class="fas <?= $sel_mode === 'structured' ? 'fa-calendar-week' : 'fa-person-walking' ?>"></i></div>
        <div>
          <div class="en-confirm-t"><?= e(mode_label($sel_mode)) ?><?= $cohort_row ? ' · ' . e($cohort_row['name']) : '' ?></div>
          <div class="en-confirm-s"><?= e(mode_blurb($sel_mode)) ?></div>
        </div>
        <div class="en-free">Free</div>
      </div>
      <ul class="en-gets">
        <li><i class="fas fa-book-open"></i><?= $lesson_count ?> lessons with examples and exercises</li>
        <li><i class="fas fa-bolt"></i>Your progress, XP and badges tracked automatically</li>
        <li><i class="fas fa-ranking-star"></i>A place on the course leaderboard</li>
        <li><i class="fas fa-certificate"></i><?= $cert_fee > 0 ? 'A verifiable certificate when you finish (unlock for ' . e(money($cert_fee)) . ')' : 'A free, verifiable certificate when you finish' ?></li>
      </ul>
      <form method="post" action="enroll?id=<?= $id ?>" class="en-actions">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="mode" value="<?= e($sel_mode) ?>">
        <input type="hidden" name="cohort_id" value="<?= (int)$cohort_id ?>">
        <a href="enroll?id=<?= $id ?>" class="en-link"><i class="fas fa-arrow-left"></i> Change track</a>
        <button class="en-btn primary lg" name="confirm_free" value="1"><?= $is_active ? 'Switch track' : 'Start learning' ?> <i class="fas fa-arrow-right"></i></button>
      </form>
    </div>

  <?php else: ?>
    <?php if ($error): ?><div class="en-alert err"><i class="fas fa-circle-exclamation"></i><?= e($error) ?></div><?php endif; ?>
    <?php $base_q = 'enroll?id=' . $id . '&mode=' . rawurlencode($sel_mode) . '&cohort=' . (int)$cohort_id; ?>

    <div class="en-card">
      <div class="en-card-h">
        <h2><span class="num">2</span> Pay and get access</h2>
        <p><?= $is_active && (float)$enrollment['amount_paid'] > 0 ? "You've already paid " . e(money((float)$enrollment['amount_paid'])) . '; this covers the difference.' : 'Pay online for instant access, or pay by M-Pesa or bank and upload your receipt.' ?></p>
      </div>

      <div class="coupon-box">
        <?php if ($coupon): ?>
          <div class="coupon-ok"><span><i class="fas fa-ticket"></i> Coupon <strong><?= e($coupon['coupon']['code']) ?></strong> applied – <?= e(coupon_label($coupon['coupon'])) ?>, you save <?= e(money($coupon['discount'])) ?></span><a href="<?= e($base_q) ?>">Remove</a></div>
        <?php else: ?>
          <details <?= $coupon_error ? 'open' : '' ?>>
            <summary><i class="fas fa-ticket"></i> Have a coupon code?<i class="fas fa-chevron-down chev"></i></summary>
            <form method="get" action="enroll" class="coupon-row">
              <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="mode" value="<?= e($sel_mode) ?>"><input type="hidden" name="cohort" value="<?= (int)$cohort_id ?>">
              <input type="text" name="coupon" class="en-input" placeholder="e.g. SKOPE20" maxlength="40" value="<?= e($coupon_code) ?>" aria-label="Coupon code" required>
              <button class="en-btn dark">Apply</button>
            </form>
            <?php if ($coupon_error): ?><div class="coupon-err"><i class="fas fa-circle-exclamation"></i> <?= e($coupon_error) ?></div><?php endif; ?>
          </details>
        <?php endif; ?>
      </div>

      <?php if ($coupon && $pay_due <= 0): ?>
        <form method="post" action="enroll?id=<?= $id ?>" class="en-actions">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="mode" value="<?= e($sel_mode) ?>">
          <input type="hidden" name="cohort_id" value="<?= (int)$cohort_id ?>">
          <input type="hidden" name="coupon" value="<?= e($coupon['coupon']['code']) ?>">
          <a href="enroll?id=<?= $id ?>" class="en-link"><i class="fas fa-arrow-left"></i> Change track</a>
          <button class="en-btn primary lg" name="confirm_coupon" value="1"><?= $is_active ? 'Switch track' : 'Start learning' ?> – covered by your coupon <i class="fas fa-arrow-right"></i></button>
        </form>
      <?php else: ?>

      <div class="en-pay" role="tablist" aria-label="Payment method">
        <button type="button" class="en-pay-tab on" data-pay="online" role="tab" aria-selected="true"><i class="fas fa-bolt"></i><span><b>Pay online</b><small>Card · M-Pesa · bank – instant</small></span></button>
        <button type="button" class="en-pay-tab" data-pay="mpesa" role="tab" aria-selected="false"><i class="fas fa-mobile-screen"></i><span><b>M-Pesa</b><small>Send money, upload receipt</small></span></button>
        <button type="button" class="en-pay-tab" data-pay="bank" role="tab" aria-selected="false"><i class="fas fa-building-columns"></i><span><b>Bank transfer</b><small>KCB, upload receipt</small></span></button>
      </div>

      <div class="en-pay-panel" data-panel="online">
        <p class="en-muted">You'll be taken to Paystack's secure page. Your access opens the moment the payment goes through.</p>
        <a class="en-btn primary lg block" href="checkout?type=course&id=<?= $id ?>&mode=<?= e($sel_mode) ?>&cohort=<?= (int)$cohort_id ?><?= $coupon ? '&coupon=' . e(rawurlencode($coupon['coupon']['code'])) : '' ?>"><i class="fas fa-lock"></i> Pay <?= e(money($pay_due)) ?> securely</a>
        <div class="en-trust"><span><i class="fas fa-shield-halved"></i> Secured by Paystack</span><span><i class="fas fa-mobile-screen"></i> M-Pesa</span><span><i class="far fa-credit-card"></i> Visa · Mastercard</span></div>
      </div>

      <div class="en-pay-panel" data-panel="mpesa" hidden>
        <ol class="en-how">
          <li>Open M-Pesa and choose <b>Send Money</b>.</li>
          <li>Send to <b class="copy" data-copy="0742380183">0742380183 <i class="far fa-copy"></i></b> (Peter Sege).</li>
          <li>Amount: <b class="copy" data-copy="<?= e((string)$pay_due) ?>"><?= e(money($pay_due)) ?> <i class="far fa-copy"></i></b></li>
          <li>Upload the confirmation message or screenshot below.</li>
        </ol>
      </div>

      <div class="en-pay-panel" data-panel="bank" hidden>
        <dl class="en-kv">
          <dt>Bank</dt><dd>KCB Bank</dd>
          <dt>Account name</dt><dd>Peter Sege</dd>
          <dt>Account number</dt><dd><b class="copy" data-copy="1318989760">1318989760 <i class="far fa-copy"></i></b></dd>
          <dt>Reference</dt><dd><b class="copy" data-copy="<?= e($user['name'] . ' - CID-' . $id) ?>"><?= e($user['name']) ?> - CID-<?= $id ?> <i class="far fa-copy"></i></b></dd>
          <dt>Amount</dt><dd><b><?= e(money($pay_due)) ?></b></dd>
        </dl>
      </div>

      <form method="post" action="enroll?id=<?= $id ?>" enctype="multipart/form-data" class="en-proof" id="proofForm" hidden>
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="mode" value="<?= e($sel_mode) ?>">
        <input type="hidden" name="cohort_id" value="<?= (int)$cohort_id ?>">
        <?php if ($coupon): ?><input type="hidden" name="coupon" value="<?= e($coupon['coupon']['code']) ?>"><?php endif; ?>
        <h3><i class="fas fa-receipt"></i> Upload your proof of payment</h3>
        <div class="en-row">
          <label class="en-field"><span>Amount paid (<?= e(setting('currency')) ?>)</span>
            <input type="number" step="0.01" min="<?= $pay_due ?>" name="amount" class="en-input" required value="<?= $pay_due ?>"></label>
          <label class="en-field"><span>M-Pesa code or bank reference</span>
            <input type="text" name="transaction_message" class="en-input" placeholder="e.g. RKJ123ABC" maxlength="500"></label>
        </div>
        <label class="en-drop" id="drop">
          <input type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.pdf" required>
          <i class="fas fa-cloud-arrow-up"></i>
          <b data-drop-name>Choose a screenshot or PDF</b>
          <small>JPG, PNG or PDF · up to 5 MB</small>
        </label>
        <button type="submit" class="en-btn primary lg block">Submit proof of payment <i class="fas fa-paper-plane"></i></button>
        <p class="en-muted center">We check manual payments within 24 hours and open your access automatically.</p>
      </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  </section>

  <aside class="en-side">
    <div class="en-card en-summary">
      <div class="en-thumb" <?= $thumb ? 'style="background-image:url(\'' . e(str_replace(' ', '%20', $thumb)) . '\')"' : '' ?>>
        <?php if (!$thumb): ?><i class="fas fa-graduation-cap"></i><?php endif; ?>
        <span class="en-type"><?= e($type_label) ?></span>
      </div>
      <div class="en-sum-b">
        <div class="en-cat"><?= e($cat_name) ?> · <?= e(ucfirst((string)$c['level'])) ?></div>
        <h3><?= e($c['title']) ?></h3>
        <ul class="en-facts">
          <li><i class="fas fa-book-open"></i><?= $lesson_count ?> lessons</li>
          <?php if ((float)$c['duration_hours'] > 0): ?><li><i class="far fa-clock"></i><?= e(rtrim(rtrim(number_format((float)$c['duration_hours'], 1), '0'), '.')) ?> hours</li><?php endif; ?>
          <li><i class="fas fa-infinity"></i><?= e($access_txt) ?></li>
          <li><i class="fas fa-certificate"></i><?= $cert_fee > 0 ? 'Certificate (' . e(money($cert_fee)) . ' to unlock)' : 'Free certificate' ?></li>
        </ul>

        <?php if ($step === 2 && !$success && !($pending_payment && !$is_active)): ?>
        <div class="en-lines">
          <div><span>Track</span><b><?= e(mode_label($sel_mode)) ?></b></div>
          <?php if ($cohort_row): ?><div><span>Cohort</span><b><?= e($cohort_row['name']) ?> · <?= e(date('j M', strtotime($cohort_row['start_date']))) ?></b></div><?php endif; ?>
          <div><span><?= $is_active ? 'Upgrade' : 'Price' ?></span><b><?= $due > 0 ? e(money($due)) : 'Free' ?></b></div>
          <?php if ($coupon): ?><div class="disc"><span>Coupon <?= e($coupon['coupon']['code']) ?></span><b>− <?= e(money($coupon['discount'])) ?></b></div><?php endif; ?>
          <?php if ($scholarship): ?><div class="disc"><span>Scholarship</span><b>Covered</b></div><?php endif; ?>
          <div class="tot"><span>Total today</span><b><?= $pay_due > 0 ? e(money($pay_due)) : 'Free' ?></b></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="en-help">
      <i class="fas fa-headset"></i>
      <div><b>Need help?</b><span>Questions about paying or tracks? <a href="contact">Contact us</a> and we'll reply quickly.</span></div>
    </div>
  </aside>
</main>

<?php require_once 'includes/footer.php'; ?>
<script>
(function () {
  // Payment method tabs: online hides the proof form; M-Pesa and bank show it.
  var tabs = document.querySelectorAll('.en-pay-tab'), proof = document.getElementById('proofForm');
  tabs.forEach(function (t) {
    t.addEventListener('click', function () {
      tabs.forEach(function (x) { x.classList.toggle('on', x === t); x.setAttribute('aria-selected', x === t); });
      document.querySelectorAll('.en-pay-panel').forEach(function (p) { p.hidden = p.dataset.panel !== t.dataset.pay; });
      if (proof) proof.hidden = t.dataset.pay === 'online';
    });
  });
  // Show proof form straight away if the last submit failed (error shown) and it was a manual payment.
  <?php if ($error && isset($_FILES['payment_proof'])): ?>document.querySelector('.en-pay-tab[data-pay="mpesa"]')?.click();<?php endif; ?>
  // Copy buttons
  document.querySelectorAll('.copy').forEach(function (b) {
    b.setAttribute('role', 'button'); b.tabIndex = 0; b.title = 'Copy';
    b.addEventListener('click', function () {
      navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(function () {
        b.classList.add('copied'); setTimeout(function () { b.classList.remove('copied'); }, 1400);
      });
    });
  });
  // File name in the drop zone
  var drop = document.getElementById('drop');
  if (drop) {
    var inp = drop.querySelector('input'), name = drop.querySelector('[data-drop-name]');
    inp.addEventListener('change', function () { if (inp.files[0]) { name.textContent = inp.files[0].name; drop.classList.add('has'); } });
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('over'); }); });
  }
})();
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
