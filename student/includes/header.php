<?php
ob_start(); // pages may still redirect after including this header
date_default_timezone_set('Africa/Nairobi'); // portal dates follow Kenyan time
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access control: Student only
requireRole('student');

$student = currentUser();

// Fetch student extra info (points, etc)
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student['id']]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student_info) {
        $student = array_merge($student_info, $student);
        
        // Final fallback: Ensure referral_code exists
        if (empty($student['referral_code'])) {
            $new_code = 'SDA' . strtoupper(substr(md5($student['id'] . time()), 0, 8));
            $upd = $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?");
            $upd->execute([$new_code, $student['id']]);
            $student['referral_code'] = $new_code;
        }
    }
} catch (Exception $e) { $student_info = []; }
$user = $student; // Shared variable for sidebar compatibility
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'My Learning') ?> – Skope Digital Academy</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Styles (student-ui.css last: the portal design system) -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/admin.css') ?>">
    <link rel="stylesheet" href="../assets/css/student-portal.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/student-portal.css') ?>">
    <link rel="stylesheet" href="../assets/css/student-ui.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/student-ui.css') ?>">
    <script>try { if (localStorage.getItem('sp_mini') === '1' && innerWidth > 1024) document.documentElement.classList.add('sp-mini-pending'); } catch (e) {}</script>
    
    <link rel="icon" href="../favicon.ico" sizes="any"><link rel="icon" type="image/png" sizes="32x32" href="../assets/pwa/favicon-32.png">

<?php require_once __DIR__ . '/../../includes/pwa.php'; sda_pwa_head(); ?>
</head>
<body class="dashboard-body student-portal">
<script>if (document.documentElement.classList.contains('sp-mini-pending')) document.body.classList.add('sp-mini');</script>
<?php if(isset($_SESSION['admin_impersonator_id'])): ?>
    <div style="background: #ef4444; color: white; text-align: center; padding: 12px; font-weight: 800; font-size: 0.85rem; z-index: 10000; position: fixed; top: 0; left: 0; right: 0; display: flex; align-items: center; justify-content: center; gap: 20px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); backdrop-filter: blur(8px);">
        <span><i class="fas fa-user-secret"></i> DIAGNOSTIC MODE: VIEWING AS <?= htmlspecialchars($student['name']) ?></span>
        <a href="../impersonate?action=stop" style="background: white; color: #ef4444; padding: 4px 14px; border-radius: 8px; text-decoration: none; font-size: 0.75rem; letter-spacing: 0.5px; font-weight: 700;">STOP IMPERSONATION</a>
    </div>
    <div style="height: 48px;"></div>
<?php endif; ?>

<script>
// Phones: slide the menu in and out
function toggleSidebar() {
    const sidebar = document.getElementById('dashSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('active', sidebar.classList.contains('open'));
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}
// Desktop: shrink the menu to icons (remembered)
function toggleDesktopFocus() {
    const mini = document.body.classList.toggle('sp-mini');
    try { localStorage.setItem('sp_mini', mini ? '1' : '0'); } catch (e) {}
}
// Top bar pop-ups (notifications, profile)
function spPop(id, ev) {
    if (ev) ev.stopPropagation();
    document.querySelectorAll('.sp-pop.open').forEach(p => { if (p.id !== id) p.classList.remove('open'); });
    const el = document.getElementById(id);
    if (el) el.classList.toggle('open');
}
document.addEventListener('click', e => {
    if (!e.target.closest('.sp-pop-panel')) document.querySelectorAll('.sp-pop.open').forEach(p => p.classList.remove('open'));
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.sp-pop.open').forEach(p => p.classList.remove('open'));
        const s = document.getElementById('dashSidebar');
        if (s && s.classList.contains('open')) toggleSidebar();
    }
});
</script>
