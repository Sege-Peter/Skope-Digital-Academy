<?php
/**
 * Tutor portal layout – opens the page.
 *
 * Pages set (all optional) before including this file:
 *   $pageTitle   page <h1> and <title>
 *   $pageSub     plain-text line under the title
 *   $pageActions raw HTML buttons shown beside the title
 *   $pageCrumb   ['href' => ..., 'label' => ...] back link above the title
 *   $hideHead    true to skip the standard title block (the dashboard draws its own)
 * Close the page with includes/footer.php.
 */
ob_start(); // pages may redirect after the header has started output
require_once __DIR__ . '/../../includes/tutor_data.php';

requireRole('tutor');

$tutor = currentUser();
$user  = $tutor; // shared variable name used by older includes
$current_page = basename($_SERVER['PHP_SELF']);
$tid = (int)$tutor['id'];

// Fresh name/avatar for the sidebar (the session copy can be stale after a profile edit)
try {
    $tpMe = $pdo->prepare("SELECT name, email, avatar FROM users WHERE id = ?");
    $tpMe->execute([$tid]);
    $tpMe = $tpMe->fetch() ?: [];
    if ($tpMe) { $tutor['name'] = $tpMe['name']; $tutor['avatar'] = $tpMe['avatar']; $tutor['email'] = $tpMe['email']; }
} catch (Throwable $e) { /* keep session values */ }

// Sidebar counters (real numbers only)
$navCounts = ['grading' => 0, 'messages' => 0, 'alerts' => 0];
try {
    $navCounts['grading']  = td_pending_submission_count($pdo, $tid);
    $navCounts['messages'] = td_unread_messages($pdo, $tid, 1)['count'];
    $navCounts['alerts']   = td_unread_notifications($pdo, $tid);
} catch (Throwable $e) { error_log('tutor nav counts: ' . $e->getMessage()); }

$pageTitle = $pageTitle ?? 'Tutor portal';
$tpFlash = td_take_flash();
$cssVer = @filemtime(__DIR__ . '/../../assets/css/tutor-layout.css') ?: 1;
$jsVer  = @filemtime(__DIR__ . '/../../assets/js/tutor.js') ?: 1;
$impersonating = isset($_SESSION['admin_impersonator_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> – Tutor portal – Skope Digital Academy</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/tutor-layout.css?v=<?= (int)$cssVer ?>">
    <link rel="icon" href="../favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="../assets/pwa/favicon-32.png">
    <script src="../assets/js/tutor.js?v=<?= (int)$jsVer ?>" defer></script>
<?php require_once __DIR__ . '/../../includes/pwa.php'; sda_pwa_head(); ?>
</head>
<body class="dashboard-body<?= $impersonating ? ' tp-impersonating' : '' ?>">
<?php if (($_SESSION['portal_home_role'] ?? '') === 'admin'): ?>
<div style="position:sticky;top:0;z-index:2000;background:linear-gradient(90deg,#0b1324,#12305a);color:#fff;padding:9px 16px;display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap;font:600 .85rem Inter,sans-serif">
    <span><i class="fas fa-repeat" style="color:#00BFFF"></i> You're using the tutor portal with your own admin account.</span>
    <a href="../switch-portal?to=admin" style="background:#00BFFF;color:#fff;padding:5px 14px;border-radius:8px;text-decoration:none;font-weight:800;font-size:.78rem">Back to admin</a>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['admin_impersonator_id'])): ?>
    <div style="background:#ef4444;color:#fff;text-align:center;padding:10px 12px;font-weight:800;font-size:.85rem;z-index:10000;position:fixed;top:0;left:0;right:0;display:flex;align-items:center;justify-content:center;gap:16px;flex-wrap:wrap;box-shadow:0 4px 12px rgba(239,68,68,.3)">
        <span><i class="fas fa-user-secret"></i> Viewing the tutor portal as <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></span>
        <a href="../impersonate?action=stop" style="background:#fff;color:#ef4444;padding:4px 14px;border-radius:8px;text-decoration:none;font-size:.75rem;font-weight:700">Back to admin</a>
    </div>
    <div style="height:44px"></div>
<?php endif; ?>
<div id="toast-container" aria-live="polite"></div>

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="tp-main">
    <header class="tp-appbar">
        <button type="button" class="tp-iconbtn" data-tp-menu aria-label="Open menu" aria-controls="tpSide" aria-expanded="false"><i class="fas fa-bars"></i></button>
        <span class="tp-appbar-title"><?= e($pageTitle) ?></span>
        <a class="tp-iconbtn" href="notifications" aria-label="Notifications<?= $navCounts['alerts'] ? ' (' . (int)$navCounts['alerts'] . ' unread)' : '' ?>">
            <i class="fas fa-bell"></i><?php if ($navCounts['alerts']): ?><span class="tp-count tp-dot"><?= (int)min(99, $navCounts['alerts']) ?></span><?php endif; ?>
        </a>
    </header>

    <main class="tp-content" id="main">
<?php if (empty($hideHead)): ?>
        <div class="tp-pagehead">
            <div>
                <?php if (!empty($pageCrumb)): ?>
                    <a class="tp-crumb" href="<?= e($pageCrumb['href']) ?>"><i class="fas fa-arrow-left"></i> <?= e($pageCrumb['label']) ?></a>
                <?php endif; ?>
                <h1><?= e($pageTitle) ?></h1>
                <?php if (!empty($pageSub)): ?><p class="tp-sub"><?= e($pageSub) ?></p><?php endif; ?>
            </div>
            <?php if (!empty($pageActions)): ?><div class="tp-actions"><?= $pageActions ?></div><?php endif; ?>
        </div>
<?php endif; ?>
<?php if ($tpFlash): ?>
        <div class="tp-alert <?= $tpFlash['type'] === 'ok' ? 'ok' : ($tpFlash['type'] === 'info' ? 'info' : 'err') ?>" role="status">
            <i class="fas <?= $tpFlash['type'] === 'ok' ? 'fa-circle-check' : ($tpFlash['type'] === 'info' ? 'fa-circle-info' : 'fa-circle-exclamation') ?>"></i>
            <div><?= e($tpFlash['msg']) ?></div>
        </div>
<?php endif; ?>
