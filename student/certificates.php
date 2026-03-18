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
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .cert-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 32px; padding: 20px 0; }
    .cert-card { background: var(--dark-card); border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--dark-border); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; }
    .cert-card:hover { transform: translateY(-12px); border-color: var(--secondary); box-shadow: 0 20px 40px rgba(247, 148, 29, 0.15); }
    .cert-preview { height: 200px; background: linear-gradient(45deg, #111, #222); position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .cert-preview i { font-size: 5rem; color: var(--secondary); opacity: 0.3; }
    .cert-badge { position: absolute; top: 12px; right: 12px; background: var(--secondary); color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
    .cert-body { padding: 24px; }
    .cert-title { font-size: 1.15rem; font-weight: 700; margin-bottom: 8px; color: var(--text-primary); }
    .cert-meta { font-size: 0.82rem; color: var(--text-dim); margin-bottom: 20px; }
    .shine-effect { position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(to right, transparent, rgba(255,255,255,0.05), transparent); transform: skewX(-25deg); transition: 0.5s; }
    .cert-card:hover .shine-effect { left: 150%; transition: 0.8s; }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800;">Academic <span class="text-secondary">Credentials</span></h1>
                <p style="font-size: 0.88rem; color: var(--text-muted);">Verified proof of your expertise and hard work.</p>
            </div>
        </div>
        <div style="text-align: right;" class="hide-sm">
            <div style="font-size: 1.8rem; font-weight: 900; color: var(--text-primary);"><?= count($certs) ?></div>
            <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Certificates Earned</div>
        </div>
    </header>

    <div class="admin-body">
        <?php if(!empty($certs)): ?>
            <div class="cert-grid">
                <?php foreach($certs as $c): ?>
                <div class="cert-card">
                    <div class="shine-effect"></div>
                    <div class="cert-preview">
                        <div class="cert-badge">Official</div>
                        <i class="fas fa-certificate"></i>
                        <img src="../uploads/courses/<?= $c['thumbnail'] ?>" style="position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:0.4;">
                    </div>
                    <div class="cert-body">
                        <h3 class="cert-title"><?= htmlspecialchars($c['course_title']) ?></h3>
                        <div class="cert-meta">
                            <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['tutor_name']) ?></span><br>
                            <span><i class="fas fa-calendar-check"></i> Issued: <?= date('M d, Y', strtotime($c['issued_at'])) ?></span>
                        </div>
                        <div style="display: flex; gap: 12px;">
                            <a href="view-certificate.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm btn-block">View Full Screen</a>
                            <a href="#" class="btn btn-ghost btn-sm" onclick="alert('PDF Generation System is processing your request. Download will start shortly!')"><i class="fas fa-download"></i></a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 120px 0;">
                <div style="font-size: 5rem; color: var(--dark-border); margin-bottom: 32px;"><i class="fas fa-award"></i></div>
                <h2 style="margin-bottom: 12px;">No certificates yet</h2>
                <p style="color: var(--text-muted); max-width: 450px; margin: 0 auto 32px;">Complete your first course with a passing quiz score to unlock your official Skope Digital Academy diploma.</p>
                <a href="../courses.php" class="btn btn-primary btn-lg">Browse Fast-Track Courses</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
