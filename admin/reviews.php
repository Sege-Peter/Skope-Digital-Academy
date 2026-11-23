<?php
/** Admin: course reviews – browse, filter, see rating spread and per-course averages, delete reviews. */
$pageTitle = 'Reviews';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    if (($_POST['action'] ?? '') === 'delete') {
        $st = $pdo->prepare("SELECT r.course_id, c.title FROM course_ratings r LEFT JOIN courses c ON c.id = r.course_id WHERE r.id = ?");
        $st->execute([(int)($_POST['review_id'] ?? 0)]);
        $r = $st->fetch();
        if (!$r) { ax_flash('err', 'That review no longer exists.'); ax_back(); }
        $cid = (int)$r['course_id'];
        $pdo->prepare("DELETE FROM course_ratings WHERE id = ?")->execute([(int)$_POST['review_id']]);
        // Same calculation as api/review.php (no reviews left → back to the 0.00 default).
        $pdo->prepare("UPDATE courses SET avg_rating = COALESCE((SELECT ROUND(AVG(rating), 2) FROM course_ratings WHERE course_id = ?), 0) WHERE id = ?")->execute([$cid, $cid]);
        ax_flash('ok', 'Review deleted. The average rating for “' . ($r['title'] ?? 'the course') . '” was recalculated.');
    }
    ax_back();
}

$courseF = (int)($_GET['course'] ?? 0);
$starsF = (int)($_GET['stars'] ?? 0);
if ($starsF < 1 || $starsF > 5) $starsF = 0;
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($courseF) { $where[] = 'r.course_id = ?'; $params[] = $courseF; }
if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR r.review LIKE ? OR c.title LIKE ?)'; array_push($params, ...array_fill(0, 4, '%' . $q . '%')); }
$baseWhere = $where;
$baseParams = $params;
if ($starsF) { $where[] = 'r.rating = ?'; $params[] = $starsF; }
$from = "FROM course_ratings r LEFT JOIN users u ON u.id = r.student_id LEFT JOIN courses c ON c.id = r.course_id";

$st = $pdo->prepare("SELECT r.*, u.name AS student_name, u.email, u.avatar, c.title AS course_title $from " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . " ORDER BY COALESCE(r.updated_at, r.created_at) DESC, r.id DESC LIMIT 300");
$st->execute($params);
$reviews = $st->fetchAll();

// Distribution for the current course/search filter (ignores the star filter so every bar stays visible).
$dist = array_fill_keys([5, 4, 3, 2, 1], 0);
$st = $pdo->prepare("SELECT r.rating, COUNT(*) n $from " . ($baseWhere ? 'WHERE ' . implode(' AND ', $baseWhere) : '') . " GROUP BY r.rating");
$st->execute($baseParams);
foreach ($st->fetchAll() as $row) if (isset($dist[(int)$row['rating']])) $dist[(int)$row['rating']] = (int)$row['n'];
$distTotal = array_sum($dist);
$distAvg = $distTotal ? array_sum(array_map(fn($s, $n) => $s * $n, array_keys($dist), $dist)) / $distTotal : 0;

$all = $pdo->query("SELECT COUNT(*) n, AVG(rating) a, SUM(rating >= 4) good, SUM(COALESCE(review, '') <> '') written,
                           SUM(created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) recent, COUNT(DISTINCT course_id) courses FROM course_ratings")->fetch();
$perCourse = $pdo->query("SELECT c.id, c.title, c.avg_rating, COUNT(r.id) n, AVG(r.rating) a, MAX(COALESCE(r.updated_at, r.created_at)) last_at
                          FROM course_ratings r JOIN courses c ON c.id = r.course_id GROUP BY c.id, c.title, c.avg_rating ORDER BY n DESC, a DESC")->fetchAll();
$courseOpts = $pdo->query("SELECT DISTINCT c.id, c.title FROM courses c JOIN course_ratings r ON r.course_id = c.id ORDER BY c.title")->fetchAll();

function rv_stars(float $n, string $size = ''): string {
    $out = '<span class="rv-stars ' . $size . '" aria-label="' . round($n, 1) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) $out .= '<i class="' . ($n >= $i ? 'fas fa-star' : ($n >= $i - .5 ? 'fas fa-star-half-stroke' : 'far fa-star')) . '"></i>';
    return $out . '</span>';
}
$link = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['course' => $courseF ?: '', 'stars' => $starsF ?: '', 'q' => $q], $o), fn($v) => $v !== '' && $v !== 0));

require_once 'includes/sidebar.php';
?>
<style>
.main-content { min-width: 0; } /* keep wide tables scrolling inside their panel */
.rv-stars { color: var(--warning); white-space: nowrap; font-size: .8rem; letter-spacing: 1px; }
.rv-stars.lg { font-size: 1.15rem; }
.rv-dist a { display: grid; grid-template-columns: 44px 1fr 64px; gap: 10px; align-items: center; padding: 6px 8px; margin: 0 -8px; border-radius: 10px; text-decoration: none; color: var(--text-dim); font-size: .82rem; font-weight: 700; }
.rv-dist a:hover, .rv-dist a.on { background: color-mix(in srgb, var(--warning) 12%, transparent); }
.rv-dist a.on { outline: 1.5px solid color-mix(in srgb, var(--warning) 60%, transparent); }
.rv-dist .ax-bar { height: 10px; }
.rv-dist .ax-bar > i { background: var(--warning); }
.rv-dist span:last-child { text-align: right; font-variant-numeric: tabular-nums; }
.rv-big { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.rv-big .v { font: 800 2.6rem 'Poppins', sans-serif; color: var(--text-main); line-height: 1; }
.rv-text { color: var(--text-main); font-size: .86rem; line-height: 1.55; max-width: 520px; white-space: pre-line; overflow-wrap: anywhere; }
.rv-text.none { color: var(--text-muted); font-style: italic; }
.rv-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.rv-filters select { padding: 9px 12px; border: 1px solid var(--border); border-radius: 12px; font: 600 .84rem 'Inter', sans-serif; background: var(--surface); color: var(--text-main); max-width: 100%; }
@media (max-width: 760px) {
  #rvTable td[data-label="Review"] { flex-direction: column; align-items: flex-start; text-align: left; }
  #rvTable td[data-label="Learner"] { justify-content: flex-start; } #rvTable td[data-label="Learner"]::before { display: none; }
  .rv-filters, .rv-filters select { width: 100%; }
}
</style>
<main class="main-content">
    <?php ax_header('Course', 'Reviews', 'Ratings & feedback'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <p class="ax-intro">Learners can rate a course once they have finished about a third of it. Deleting a review removes it from the course page and
            recalculates that course's average rating.</p>
        <div class="ax-stats">
            <?= ax_stat('star', $all['n'] ? number_format((float)$all['a'], 2) : '—', 'Average rating', number_format((int)$all['n']) . ' rating' . ((int)$all['n'] === 1 ? '' : 's') . ' overall', 'amber') ?>
            <?= ax_stat('comment-dots', number_format((int)$all['written']), 'Written reviews', 'Ratings with a comment', '') ?>
            <?= ax_stat('thumbs-up', $all['n'] ? round($all['good'] / $all['n'] * 100) . '%' : '—', 'Positive', '4 or 5 stars', 'green') ?>
            <?= ax_stat('calendar-plus', number_format((int)$all['recent']), 'Last 30 days', 'New ratings', 'violet') ?>
            <?= ax_stat('book', number_format((int)$all['courses']), 'Courses rated', 'With at least one rating', 'slate') ?>
        </div>

        <div class="ax-grid">
            <section class="ax-panel ax-span-8">
                <div class="ax-panel-h"><div><h2><i class="fas fa-comments"></i> Reviews</h2><p><?= count($reviews) ?> shown<?= $starsF ? ' · ' . $starsF . ' star' . ($starsF > 1 ? 's' : '') : '' ?><?= $q !== '' ? ' · matching “' . e($q) . '”' : '' ?></p></div>
                    <?php if ($courseF || $starsF || $q !== ''): ?><a class="ax-btn sm ghost" href="reviews"><i class="fas fa-xmark"></i> Clear filters</a><?php endif; ?></div>
                <div class="ax-panel-b" style="padding-bottom:0">
                    <form class="ax-toolbar rv-filters" method="get" role="search">
                        <select name="course" aria-label="Course" onchange="this.form.submit()">
                            <option value="">All courses</option>
                            <?php foreach ($courseOpts as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $courseF ? ' selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
                        </select>
                        <select name="stars" aria-label="Rating" onchange="this.form.submit()">
                            <option value="">Any rating</option>
                            <?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"<?= $starsF === $s ? ' selected' : '' ?>><?= str_repeat('★', $s) ?> <?= $s ?> star<?= $s > 1 ? 's' : '' ?></option><?php endfor; ?>
                        </select>
                        <div class="ax-search"><i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Learner, course or words…" aria-label="Search reviews"></div>
                    </form>
                </div>
                <div class="ax-table-wrap">
                    <table class="ax-table cards" id="rvTable">
                        <thead><tr><th>Learner</th><th>Course</th><th>Rating</th><th>Review</th><th>Date</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($reviews as $r): ?>
                            <tr>
                                <td data-label="Learner"><div class="ax-who"><?= ax_avatar($r['student_name'] ?? '?', $r['avatar']) ?><div><b><?= e($r['student_name'] ?? 'Deleted learner') ?></b><small><?= e($r['email'] ?? '—') ?></small></div></div></td>
                                <td data-label="Course"><a href="<?= e($link(['course' => (int)$r['course_id']])) ?>" style="font-weight:700;color:var(--text-main);text-decoration:none"><?= e($r['course_title'] ?? 'Deleted course') ?></a></td>
                                <td data-label="Rating"><?= rv_stars((float)$r['rating']) ?></td>
                                <td data-label="Review"><?= trim((string)$r['review']) !== '' ? '<div class="rv-text">' . e($r['review']) . '</div>' : '<div class="rv-text none">Rating only, no comment</div>' ?></td>
                                <td data-label="Date" class="num"><?= ax_date($r['created_at']) ?><?= $r['updated_at'] ? '<div class="muted">edited ' . ax_ago($r['updated_at']) . '</div>' : '' ?></td>
                                <td data-label="" class="actions">
                                    <form method="post" data-confirm="Delete this <?= (int)$r['rating'] ?>-star review by <?= e($r['student_name'] ?? 'a deleted learner') ?>? The course average is recalculated. This cannot be undone." data-danger>
                                        <?= ax_csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                                        <button class="ax-btn sm danger"><i class="fas fa-trash"></i> Delete</button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$reviews): ?><div class="ax-empty"><i class="fas fa-star-half-stroke"></i><b><?= $courseF || $starsF || $q !== '' ? 'No reviews match these filters' : 'No reviews yet' ?></b>Ratings appear here as learners review the courses they take.</div><?php endif; ?>
                </div>
            </section>

            <aside class="ax-span-4 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-chart-simple"></i> Rating spread</h2><p><?= $courseF || $q !== '' ? 'For the current filter' : 'All courses' ?> · click a row to filter.</p></div></div>
                    <div class="ax-panel-b">
                        <div class="rv-big"><div class="v"><?= $distTotal ? number_format($distAvg, 1) : '—' ?></div><div><?= rv_stars($distAvg, 'lg') ?><div style="font-size:.78rem;color:var(--text-muted);margin-top:4px"><?= number_format($distTotal) ?> rating<?= $distTotal === 1 ? '' : 's' ?></div></div></div>
                        <div class="rv-dist">
                            <?php foreach ($dist as $s => $n): $pct = $distTotal ? round($n / $distTotal * 100) : 0; ?>
                                <a href="<?= e($link(['stars' => $starsF === $s ? '' : $s])) ?>" class="<?= $starsF === $s ? 'on' : '' ?>"><span><?= $s ?> <i class="fas fa-star" style="color:var(--warning);font-size:.72rem"></i></span><div class="ax-bar"><i style="width:<?= $pct ?>%"></i></div><span><?= $n ?> · <?= $pct ?>%</span></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-book"></i> Averages by course</h2><p>The stored average is what learners see on the course page.</p></div></div>
                    <div class="ax-panel-b" style="padding-top:6px">
                        <?php if ($perCourse): ?><ul class="ax-list">
                            <?php foreach ($perCourse as $c): $mismatch = abs((float)$c['avg_rating'] - round((float)$c['a'], 2)) > 0.01; ?>
                                <li>
                                    <div class="grow"><a href="<?= e($link(['course' => (int)$c['id'], 'stars' => ''])) ?>" style="display:block;font-weight:700;color:var(--text-main);font-size:.86rem;text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($c['title']) ?></a>
                                        <small style="color:var(--text-muted);font-size:.74rem"><?= (int)$c['n'] ?> rating<?= (int)$c['n'] === 1 ? '' : 's' ?> · last <?= ax_ago($c['last_at']) ?><?= $mismatch ? ' · <span style="color:var(--warning)">stored ' . number_format((float)$c['avg_rating'], 2) . '</span>' : '' ?></small></div>
                                    <div style="text-align:right"><b style="color:var(--text-main)"><?= number_format((float)$c['a'], 2) ?></b><div><?= rv_stars((float)$c['a']) ?></div></div>
                                </li>
                            <?php endforeach; ?></ul>
                        <?php else: ?><div class="ax-empty" style="padding:24px"><b>No course has ratings yet</b></div><?php endif; ?>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
