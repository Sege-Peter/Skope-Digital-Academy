<?php
$pageTitle = 'My Learning Path';
require_once 'includes/header.php';

try {
    // 1. Fetch active enrollments
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.thumbnail, c.level, cat.name as category_name, u.name as tutor_name
                           FROM enrollments e
                           JOIN courses c ON e.course_id = c.id
                           JOIN users u ON c.tutor_id = u.id
                           LEFT JOIN categories cat ON c.category_id = cat.id
                           WHERE e.student_id = ? AND e.status = 'active' AND c.status = 'published'
                           ORDER BY e.enrolled_at DESC");
    $stmt->execute([$student['id']]);
    $enrollments = $stmt->fetchAll();

} catch (Exception $e) {
    $enrollments = [];
}

require_once 'includes/layout-top.php';
?>

<header class="portal-header" style="margin-bottom: 40px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">My Learning Path<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Track your growth across all certifications and high-performance modules.</p>
    </div>
    <div class="header-tools">
        <a href="../courses.php" class="btn-premium" style="padding: 14px 28px; border-radius: 50px;"><i class="fas fa-compass"></i> Explore More</a>
    </div>
</header>

<div style="margin-bottom: 40px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px;">
    <div class="premium-card glass-effect" style="display: flex; align-items: center; gap: 20px; padding: 24px;">
        <div style="width: 60px; height: 60px; background: rgba(0, 174, 239, 0.08); color: var(--primary); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <div>
            <span style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Active Enrollments</span>
            <strong style="font-size: 1.8rem; font-weight: 950; color: var(--text-main); line-height: 1;"><?= count($enrollments) ?></strong>
        </div>
    </div>
    <!-- You can add more metric cards here if needed -->
</div>

<div class="courses-stack">
    <?php if(!empty($enrollments)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 32px;">
        <?php foreach($enrollments as $e): 
            $prog = round($e['progress_percent']);
        ?>
        <div class="premium-card clickable-card" onclick="window.location.href='classroom.php?id=<?= $e['course_id'] ?>'" style="padding: 0; cursor: pointer; border-radius: 28px;">
            <div style="position: relative; height: 200px; overflow: hidden;">
                <img src="../<?= $e['thumbnail'] ? 'uploads/courses/'.$e['thumbnail'] : 'assets/images/course-placeholder.jpg' ?>" style="width: 100%; height: 100%; object-fit: cover; transition: 0.6s;">
                <div style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 40%, rgba(15, 23, 42, 0.85) 100%);"></div>
                <span style="position: absolute; top: 16px; left: 16px; background: var(--primary); color: white; padding: 6px 14px; border-radius: 50px; font-size: 0.65rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?= $e['level'] ?>
                </span>
            </div>
            <div style="padding: 28px; flex: 1; display: flex; flex-direction: column;">
                <span style="font-size: 0.72rem; font-weight: 900; color: var(--primary); text-transform: uppercase; letter-spacing: 0.8px;"><?= htmlspecialchars($e['category_name'] ?: 'Academy Path') ?></span>
                <h3 style="margin: 12px 0; font-size: 1.25rem; font-weight: 900; color: var(--text-main); line-height: 1.3; height: 2.6em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"><?= htmlspecialchars($e['title']) ?></h3>
                
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 28px;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #f1f5f9; overflow: hidden;">
                        <img src="../assets/images/user-placeholder.png" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <span style="font-size: 0.85rem; color: var(--text-dim); font-weight: 600;"><?= htmlspecialchars($e['tutor_name']) ?></span>
                </div>

                <div style="margin-top: auto;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 800; margin-bottom: 10px;">
                        <span style="color: var(--text-dim); text-transform: uppercase;">Module Progress</span>
                        <span style="color: var(--primary);"><?= $prog ?>%</span>
                    </div>
                    <div style="height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden; margin-bottom: 24px;">
                        <div style="height: 100%; background: var(--grad-primary); width: <?= $prog ?>%; border-radius: 10px; transition: 1s;"></div>
                    </div>
                    <div class="btn-premium" style="width: 100%; border-radius: 16px;">
                        Continue Path <i class="fas fa-arrow-right" style="margin-left: 8px; font-size: 0.8rem;"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="premium-card" style="text-align: center; padding: 100px 40px; border-style: dashed; border-width: 2px;">
            <div style="width: 80px; height: 80px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: var(--text-dim); font-size: 2rem;">
                <i class="fas fa-book-open"></i>
            </div>
            <h3 style="font-weight: 900; font-size: 1.5rem; margin-bottom: 12px;">No Active Paths</h3>
            <p style="color: var(--text-dim); max-width: 400px; margin: 0 auto 32px; line-height: 1.6;">You haven't enrolled in any courses yet. Start your journey by exploring our academy catalog.</p>
            <a href="../courses.php" class="btn-premium" style="min-width: 200px;">Browse All Courses</a>
        </div>
    <?php endif; ?>
</div>


<?php require_once 'includes/layout-bottom.php'; ?>
