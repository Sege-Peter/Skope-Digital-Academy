<?php
$pageTitle = 'My courses';
require_once 'includes/header.php';

try {
    // All courses the student has joined: in progress first, then finished
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.thumbnail, c.level, c.total_lessons, cat.name as category_name, u.name as tutor_name
                           FROM enrollments e
                           JOIN courses c ON e.course_id = c.id
                           LEFT JOIN users u ON c.tutor_id = u.id
                           LEFT JOIN categories cat ON c.category_id = cat.id
                           WHERE e.student_id = ? AND e.status IN ('active', 'completed') AND c.status = 'published'
                           ORDER BY (e.status = 'completed'), e.enrolled_at DESC");
    $stmt->execute([$student['id']]);
    $enrollments = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('student courses: ' . $e->getMessage());
    $enrollments = [];
}

$count_active = count(array_filter($enrollments, fn($e) => $e['status'] === 'active'));
$count_done = count($enrollments) - $count_active;

require_once 'includes/layout-top.php';
?>

<div class="sp-page-head">
    <div>
        <h1>My courses</h1>
        <p>Every course you've joined, with how far you've got in each one.</p>
    </div>
    <div class="sp-actions">
        <a href="../courses" class="sp-btn"><i class="fas fa-compass"></i> Browse courses</a>
    </div>
</div>

<?php if (empty($enrollments)): ?>
    <div class="sp-empty">
        <div class="sp-ico"><i class="fas fa-graduation-cap"></i></div>
        <h3>You haven't joined a course yet</h3>
        <p>Start free with a tutorial, or choose a course that ends with a certificate.</p>
        <div class="sp-row" style="justify-content: center;">
            <a href="../courses" class="sp-btn">Browse courses</a>
            <a href="../learn" class="sp-btn ghost">Free tutorials</a>
        </div>
    </div>
<?php else: ?>
    <section class="sp-grid cols-3 sp-section">
        <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-book-open-reader"></i></span><div><strong><?= $count_active ?></strong><span>In progress</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-circle-check"></i></span><div><strong><?= $count_done ?></strong><span>Finished</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon violet"><i class="fas fa-layer-group"></i></span><div><strong><?= count($enrollments) ?></strong><span>All courses</span></div></div>
    </section>

    <section class="sp-section">
        <div class="sp-section-head">
            <div class="sp-tabs" role="tablist">
                <button type="button" class="sp-tab active" data-filter="active" onclick="filterCourses(this)">In progress (<?= $count_active ?>)</button>
                <button type="button" class="sp-tab" data-filter="completed" onclick="filterCourses(this)">Finished (<?= $count_done ?>)</button>
                <button type="button" class="sp-tab" data-filter="all" onclick="filterCourses(this)">All</button>
            </div>
        </div>

        <div class="sp-grid cols-3" id="courseGrid">
            <?php foreach ($enrollments as $c):
                $isDone = $c['status'] === 'completed';
                $progress = $isDone ? 100 : (int)round((float)($c['progress_percent'] ?? 0));
            ?>
            <a class="sp-card hover sp-course" data-status="<?= $isDone ? 'completed' : 'active' ?>" href="classroom?id=<?= (int)$c['course_id'] ?>">
                <div class="sp-course-thumb">
                    <img src="../uploads/courses/<?= htmlspecialchars($c['thumbnail'] ?: 'course-default.jpg') ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                    <span class="sp-badge <?= $isDone ? 'green' : '' ?>"><?= $isDone ? '✓ Finished' : (($c['learning_mode'] ?? '') === 'structured' ? 'Structured' : 'Self-paced') ?></span>
                </div>
                <div class="sp-course-body">
                    <?php if (!empty($c['category_name'])): ?><span class="sp-small" style="color: var(--primary-dark); font-weight: 700;"><?= htmlspecialchars($c['category_name']) ?></span><?php endif; ?>
                    <h3><?= htmlspecialchars($c['title']) ?></h3>
                    <div class="sp-course-meta">
                        <span><i class="fas fa-signal"></i> <?= ucfirst(htmlspecialchars($c['level'] ?? 'beginner')) ?></span>
                        <?php if (!empty($c['total_lessons'])): ?><span><i class="fas fa-list"></i> <?= (int)$c['total_lessons'] ?> lessons</span><?php endif; ?>
                        <?php if (!empty($c['tutor_name'])): ?><span><i class="fas fa-chalkboard-user"></i> <?= htmlspecialchars($c['tutor_name']) ?></span><?php endif; ?>
                    </div>
                    <div class="sp-progress <?= $isDone ? 'green' : '' ?>"><span style="width: <?= max(2, $progress) ?>%;"></span></div>
                    <div class="sp-course-foot">
                        <span class="sp-muted"><?= $progress ?>% complete</span>
                        <span style="color: var(--primary-dark);"><?= $isDone ? 'Review' : ($progress > 0 ? 'Continue' : 'Start') ?> <i class="fas fa-arrow-right"></i></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="sp-empty" id="emptyActive" style="display: none;">
            <div class="sp-ico green"><i class="fas fa-flag-checkered"></i></div>
            <h3>Nothing in progress</h3>
            <p>You've finished every course you joined. Pick a new one to keep learning.</p>
            <a href="../courses" class="sp-btn">Browse courses</a>
        </div>
        <div class="sp-empty" id="emptyCompleted" style="display: none;">
            <div class="sp-ico"><i class="fas fa-hourglass-half"></i></div>
            <h3>No finished courses yet</h3>
            <p>When you complete a course it moves here, and you can download your certificate.</p>
        </div>
    </section>
<?php endif; ?>

<script>
function filterCourses(btn) {
    const f = btn.dataset.filter;
    btn.parentElement.querySelectorAll('.sp-tab').forEach(b => b.classList.toggle('active', b === btn));
    let shown = 0;
    document.querySelectorAll('#courseGrid .sp-course').forEach(card => {
        const show = f === 'all' || card.dataset.status === f;
        card.style.display = show ? '' : 'none';
        if (show) shown++;
    });
    document.getElementById('emptyActive').style.display = (f === 'active' && !shown) ? '' : 'none';
    document.getElementById('emptyCompleted').style.display = (f === 'completed' && !shown) ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', () => {
    const first = document.querySelector('.sp-tab[data-filter="active"]');
    if (first) filterCourses(first);
});
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
