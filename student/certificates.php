<?php
$pageTitle = 'My Certificates';
require_once 'includes/header.php';

try {
    // Fetch completed courses with certificates
    $stmt = $pdo->prepare("SELECT c.*, co.title as course_title, co.thumbnail, co.level, u.name as tutor_name
                           FROM certificates c
                           JOIN courses co ON c.course_id = co.id
                           JOIN users u ON co.tutor_id = u.id
                           WHERE c.student_id = ?
                           ORDER BY c.issued_at DESC");
    $stmt->execute([$student['id']]);
    $certs = $stmt->fetchAll();
} catch (Exception $e) { $certs = []; }

require_once 'includes/layout-top.php';
?>

<header class="portal-header" style="margin-bottom: 24px;">
    <div class="greeting">
        <h1 style="font-size: 1.85rem; font-weight: 800; margin: 0;">Academic Credentials</h1>
        <p style="color: var(--text-dim); margin-top: 4px;">Verified proof of your expertise and hard work.</p>
    </div>
</header>

<div class="grid-3" style="margin-bottom: 32px;">
    <div class="card" style="display: flex; align-items: center; gap: 20px;">
        <div style="width: 50px; height: 50px; background: #FFF9E7; color: #D97706; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
            <i class="fas fa-certificate"></i>
        </div>
        <div>
            <span style="display: block; font-size: 0.7rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">Earned Diplomas</span>
            <strong style="font-size: 1.5rem; font-weight: 800;"><?= count($certs) ?></strong>
        </div>
    </div>
</div>

<div class="certificates-grid">
    <?php if(!empty($certs)): ?>
        <div class="grid-3">
        <?php foreach($certs as $c): ?>
        <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 20px;">
            <div style="height: 160px; background: #1e293b; position: relative; display: flex; align-items: center; justify-content: center;">
                <img src="../uploads/courses/<?= $c['thumbnail'] ?>" style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.3;">
                <i class="fas fa-certificate" style="font-size: 4rem; color: #FFD700; opacity: 0.8; position: relative;"></i>
                <span style="position: absolute; top: 12px; right: 12px; background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase;">Official</span>
            </div>
            <div style="padding: 24px; flex: 1; display: flex; flex-direction: column;">
                <h3 style="margin: 0 0 8px; font-size: 1.1rem; font-weight: 800;"><?= htmlspecialchars($c['course_title']) ?></h3>
                <p style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 24px;">
                    <i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['tutor_name']) ?><br>
                    <i class="far fa-calendar-alt"></i> Issued: <?= date('M d, Y', strtotime($c['issued_at'])) ?>
                </p>
                <div style="margin-top: auto; display: flex; gap: 12px;">
                    <a href="view-certificate.php?id=<?= $c['id'] ?>" class="btn-premium" style="flex: 1; justify-content: center; font-size: 0.75rem;">View Online</a>
                    <a href="#" class="btn-premium" style="background: #F8FAFC; color: var(--text-dim); border: 1px solid var(--border); box-shadow: none; width: 44px; display: flex; justify-content: center;" onclick="alert('Download started...')"><i class="fas fa-download"></i></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 80px 40px; border-style: dashed;">
            <i class="fas fa-award" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 20px; display: block;"></i>
            <h3 style="font-weight: 800;">No Certificates Earned Yet</h3>
            <p style="color: var(--text-dim); margin-bottom: 20px;">Complete your curriculum milestones to unlock your official diploma.</p>
            <a href="courses.php" class="btn-premium">Continue Learning</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
