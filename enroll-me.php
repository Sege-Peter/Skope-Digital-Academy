<?php
/**
 * One-tap "Enroll me" from a course card.
 *   POST (with CSRF): free course -> enrolled on the self-paced track and sent straight into the classroom.
 *                     paid course  -> the payment step of enroll.php for that track (coupons, scholarships, proof).
 *   GET: signed out -> sign in first, then back here; signed in -> a one-button confirmation page.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if (!$id) { header('Location: courses'); exit; }

// Only registered learners enroll. Visitors sign up first (or sign in from there) and come back to this course.
$back = 'enroll-me?id=' . $id;
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $back;
    $_SESSION['after_activation'] = $back;
    header('Location: register?msg=signup_to_enroll');
    exit;
}
$user = currentUser();
if ($user['role'] !== 'student') { header('Location: course-details?id=' . $id); exit; }

// Registration must be complete (account activated) before enrolling; checkout brings them back here.
$st = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$st->execute([$user['id']]);
$status = (string)$st->fetchColumn();
if ($status === 'suspended') { header('Location: course-details?id=' . $id); exit; }
if ($status !== 'active') {
    $_SESSION['after_activation'] = $back;
    header('Location: checkout?type=registration');
    exit;
}
$_SESSION['status'] = 'active';

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND status = 'published'");
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) { header('Location: courses'); exit; }

$enrollment = get_enrollment($pdo, (int)$user['id'], $id);
if ($enrollment && in_array($enrollment['status'], ['active', 'completed'], true)) {
    header('Location: student/classroom?id=' . $id);
    exit;
}

$modes = course_modes($c);
$freeSelf = in_array('self_paced', $modes, true) && course_price($c, 'self_paced') <= 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { header('Location: enroll-me?id=' . $id); exit; }
    if ($freeSelf) {
        enroll_student($pdo, (int)$user['id'], $c, 'self_paced', null, 0);
        require_once __DIR__ . '/includes/notify.php';
        sda_notify_enrolled($pdo, (int)$user['id'], $c, 'self_paced', null);
        header('Location: student/classroom?id=' . $id . '&welcome=1');
        exit;
    }
    // Paid (or class-only) course: go to the payment / track step for the cheapest track.
    if (count($modes) === 1 && $modes[0] === 'structured') { header('Location: enroll?id=' . $id); exit; }
    header('Location: enroll?id=' . $id . '&mode=self_paced');
    exit;
}

$pageTitle = 'Enroll – ' . $c['title'];
$price = course_from_price($c);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> – Skope Digital Academy</title>
    <meta name="robots" content="noindex">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Poppins:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php require_once __DIR__ . '/includes/pwa.php'; sda_pwa_head(); ?>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; background: #eef2f7; font-family: Inter, system-ui, sans-serif; color: #334155; }
        .em-card { width: 100%; max-width: 460px; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 24px 50px -24px rgba(15,23,42,.35); }
        .em-top { background: linear-gradient(135deg, #0b1220, #0f2847); color: #fff; padding: 22px 24px; display: flex; gap: 14px; align-items: center; }
        .em-top img { width: 46px; height: 46px; border-radius: 12px; background: #fff; }
        .em-top b { font: 800 1.05rem Poppins, sans-serif; } .em-top b span { color: #FF8C00; }
        .em-bar { height: 4px; background: linear-gradient(90deg, #00BFFF, #0080FF 55%, #FF8C00); }
        .em-body { padding: 26px 24px 28px; }
        .em-body small { text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; color: #94a3b8; font-size: .72rem; }
        .em-body h1 { font: 800 1.35rem/1.3 Poppins, sans-serif; color: #0f172a; margin: 6px 0 10px; }
        .em-body p { margin: 0 0 18px; line-height: 1.6; }
        .em-price { display: inline-block; padding: 5px 12px; border-radius: 99px; font-weight: 700; font-size: .85rem; margin-bottom: 18px; background: #ecfdf3; color: #15803d; }
        .em-price.paid { background: #fff7ed; color: #c2410c; }
        .em-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 52px; border: 0; border-radius: 14px; cursor: pointer;
                  background: linear-gradient(135deg, #FF8C00, #FF6A00); color: #fff; font: 800 1rem Inter, sans-serif; box-shadow: 0 12px 24px -12px rgba(255,120,0,.8); }
        .em-link { display: block; text-align: center; margin-top: 14px; color: #64748b; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <main class="em-card">
        <div class="em-top"><img src="assets/pwa/icon-192.png" alt=""><b>SKOPE <span>DIGITAL</span> ACADEMY</b></div>
        <div class="em-bar"></div>
        <div class="em-body">
            <small>Enroll in</small>
            <h1><?= e($c['title']) ?></h1>
            <span class="em-price<?= $freeSelf ? '' : ' paid' ?>"><?= $freeSelf ? 'Free · start right away' : 'From ' . e(money($price)) ?></span>
            <p><?= $freeSelf ? 'Tap below and the course opens straight away. Learn at your own pace; your progress is saved as you go.' : 'Tap below to choose how to pay. Your course opens as soon as the payment is confirmed.' ?></p>
            <form method="post" action="enroll-me">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="em-btn" type="submit"><i class="fas fa-<?= $freeSelf ? 'play' : 'lock-open' ?>"></i> <?= $freeSelf ? 'Enroll me & start learning' : 'Enroll me' ?></button>
            </form>
            <a class="em-link" href="course-details?id=<?= $id ?>">See the course details first</a>
        </div>
    </main>
</body>
</html>
