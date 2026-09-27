<?php
$pageTitle = 'Search Results';
require_once 'includes/header.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$courses = [];
$lessons = [];

if (!empty($q)) {
    // 1. Search Published Courses
    $stmt = $pdo->prepare("SELECT c.*, u.name as tutor_name, cat.name as category_name 
                          FROM courses c 
                          JOIN users u ON c.tutor_id = u.id 
                          LEFT JOIN categories cat ON c.category_id = cat.id 
                          WHERE (c.title LIKE ? OR c.description LIKE ?) AND c.status = 'published'
                          LIMIT 12");
    $stmt->execute(['%'.$q.'%', '%'.$q.'%']);
    $courses = $stmt->fetchAll();

    // 2. Search Lessons in Enrolled Courses (or all published)
    $stmt = $pdo->prepare("SELECT l.*, c.title as course_title 
                          FROM lessons l 
                          JOIN courses c ON l.course_id = c.id 
                          WHERE l.title LIKE ? AND c.status = 'published'
                          LIMIT 10");
    $stmt->execute(['%'.$q.'%']);
    $lessons = $stmt->fetchAll();
}

require_once 'includes/layout-top.php';
?>

<div style="margin-bottom: 40px;">
    <h2 style="font-weight: 900; margin: 0;">Search Results for "<span style="color: var(--primary);"><?= htmlspecialchars($q) ?></span>"</h2>
    <p style="color: var(--text-dim); margin-top: 4px;">Found <?= count($courses) ?> courses and <?= count($lessons) ?> lesson modules.</p>
</div>

<?php if (empty($courses) && empty($lessons)): ?>
    <div style="text-align: center; padding: 100px 20px; background: #fff; border-radius: 24px; border: 1px solid var(--border);">
        <div style="width: 80px; height: 80px; background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 2rem; color: var(--text-dim);">
            <i class="fas fa-search"></i>
        </div>
        <h3 style="font-weight: 800; margin: 0 0 8px;">No results found</h3>
        <p style="color: var(--text-dim); margin-bottom: 24px;">We couldn't find anything matching your query. Try different keywords.</p>
        <a href="index.php" class="btn-premium" style="display: inline-block;">Return to Dashboard</a>
    </div>
<?php else: ?>

    <!-- Courses Section -->
    <?php if(!empty($courses)): ?>
    <section style="margin-bottom: 48px;">
        <h4 style="font-weight: 900; text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem; color: var(--text-dim); margin-bottom: 24px;"><i class="fas fa-graduation-cap"></i> Academic Courses</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
            <?php foreach($courses as $c): ?>
            <div class="card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column; cursor: pointer; transition: 0.3s;" onclick="window.location.href='classroom.php?id=<?= $c['id'] ?>'">
                <div style="height: 160px; position: relative;">
                    <img src="../<?= $c['thumbnail'] ? 'uploads/courses/'.$c['thumbnail'] : 'assets/images/course-placeholder.jpg' ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <span style="position: absolute; bottom: 12px; left: 12px; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); color: white; padding: 4px 10px; border-radius: 8px; font-size: 0.65rem; font-weight: 800;"><?= htmlspecialchars($c['category_name'] ?: 'Academy') ?></span>
                </div>
                <div style="padding: 20px;">
                    <h5 style="margin: 0 0 8px; font-weight: 800; line-height: 1.4;"><?= htmlspecialchars($c['title']) ?></h5>
                    <p style="font-size: 0.78rem; color: var(--text-dim); margin-bottom: 16px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars(strip_tags($c['description'])) ?></p>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto;">
                        <span style="font-size: 0.7rem; font-weight: 700; color: var(--primary);"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['tutor_name']) ?></span>
                        <span style="font-size: 0.72rem; font-weight: 800; color: var(--text-main);">View Case →</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Lessons Section -->
    <?php if(!empty($lessons)): ?>
    <section>
        <h4 style="font-weight: 900; text-transform: uppercase; letter-spacing: 1px; font-size: 0.75rem; color: var(--text-dim); margin-bottom: 24px;"><i class="fas fa-play-circle"></i> Lesson Modules</h4>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach($lessons as $l): ?>
            <a href="classroom.php?id=<?= $l['course_id'] ?>&lesson=<?= $l['id'] ?>" style="display: flex; align-items: center; gap: 20px; padding: 16px 24px; background: white; border: 1px solid var(--border); border-radius: 16px; text-decoration: none; color: inherit; transition: 0.2s;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: #E0F7FF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                    <i class="fas <?= $l['lesson_type'] == 'video' ? 'fa-play' : 'fa-file-alt' ?>"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h6 style="margin: 0; font-weight: 800; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($l['title']) ?></h6>
                    <p style="margin: 2px 0 0; font-size: 0.72rem; color: var(--text-dim);">Part of <strong><?= htmlspecialchars($l['course_title']) ?></strong></p>
                </div>
                <div style="font-size: 0.75rem; font-weight: 800; color: var(--primary); white-space: nowrap;">Jump to Module <i class="fas fa-external-link-alt" style="margin-left: 6px;"></i></div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/layout-bottom.php'; ?>
