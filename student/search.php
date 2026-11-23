<?php
$pageTitle = 'Search';
require_once 'includes/header.php';

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
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

$total = count($courses) + count($lessons);
$search_q = $q; // includes/sidebar.php reuses $q for its own query

require_once 'includes/layout-top.php';
$q = $search_q;
?>

<div class="sp-page-head">
    <div>
        <h1>Search</h1>
        <?php if ($q === ''): ?>
            <p>Type a word in the search box to find courses and lessons.</p>
        <?php else: ?>
            <p><?= $total ?> result<?= $total == 1 ? '' : 's' ?> for “<?= htmlspecialchars($q) ?>”</p>
        <?php endif; ?>
    </div>
</div>

<form action="search" method="GET" class="sp-form sp-mb" role="search">
    <div class="sp-row" style="flex-wrap: nowrap;">
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search courses and lessons" aria-label="Search" style="flex: 1; min-width: 0; width: auto; height: 44px; padding: 0 14px; border-radius: 12px; border: 1px solid var(--border-strong); font: inherit;">
        <button type="submit" class="sp-btn"><i class="fas fa-magnifying-glass"></i><span class="sp-hide-sm">Search</span></button>
    </div>
</form>

<?php if ($q === '' || $total === 0): ?>
    <div class="sp-empty">
        <div class="sp-ico"><i class="fas fa-magnifying-glass"></i></div>
        <?php if ($q === ''): ?>
            <h3>What would you like to find?</h3>
            <p>Search by course name, topic or lesson title, for example “Python” or “HTML”.</p>
        <?php else: ?>
            <h3>No results for “<?= htmlspecialchars($q) ?>”</h3>
            <p>Check the spelling or try a shorter, more general word.</p>
        <?php endif; ?>
        <div class="sp-row" style="justify-content: center;">
            <a href="../courses" class="sp-btn">Browse courses</a>
            <a href="./" class="sp-btn ghost">Back to dashboard</a>
        </div>
    </div>
<?php else: ?>

    <!-- Courses -->
    <?php if (!empty($courses)): ?>
    <section class="sp-section">
        <div class="sp-section-head"><div><h2>Courses</h2><p><?= count($courses) ?> found</p></div></div>
        <div class="sp-grid cols-3">
            <?php foreach ($courses as $c): ?>
            <a class="sp-card hover sp-course" href="classroom?id=<?= (int)$c['id'] ?>">
                <div class="sp-course-thumb">
                    <img src="../<?= htmlspecialchars($c['thumbnail'] ? 'uploads/courses/' . $c['thumbnail'] : 'assets/images/course-placeholder.jpg') ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                    <span class="sp-badge"><?= htmlspecialchars($c['category_name'] ?: 'Course') ?></span>
                </div>
                <div class="sp-course-body">
                    <h3><?= htmlspecialchars($c['title']) ?></h3>
                    <p class="sp-muted sp-small" style="margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars(strip_tags((string)$c['description'])) ?></p>
                    <div class="sp-course-foot">
                        <span class="sp-muted sp-truncate"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($c['tutor_name']) ?></span>
                        <span style="color: var(--primary-dark); white-space: nowrap;">Open <i class="fas fa-arrow-right"></i></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Lessons -->
    <?php if (!empty($lessons)): ?>
    <section class="sp-section">
        <div class="sp-section-head"><div><h2>Lessons</h2><p><?= count($lessons) ?> found</p></div></div>
        <div class="sp-card" style="padding: 6px 18px;">
            <div class="sp-list">
                <?php foreach ($lessons as $l): ?>
                <a href="classroom?id=<?= (int)$l['course_id'] ?>&lesson=<?= (int)$l['id'] ?>" class="sp-list-item" style="text-decoration: none;">
                    <span class="sp-ico <?= ($l['lesson_type'] ?? '') == 'video' ? 'orange' : '' ?>"><i class="fas <?= ($l['lesson_type'] ?? '') == 'video' ? 'fa-play' : 'fa-file-lines' ?>"></i></span>
                    <div class="sp-grow">
                        <h4 class="sp-truncate"><?= htmlspecialchars($l['title']) ?></h4>
                        <p class="sp-truncate">In <?= htmlspecialchars($l['course_title']) ?></p>
                    </div>
                    <span class="sp-link sp-hide-sm">Open lesson <i class="fas fa-arrow-right"></i></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/layout-bottom.php'; ?>
