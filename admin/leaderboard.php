<?php
/** Admin: leaderboards – global XP board and per-course boards; hide/unhide learners from public boards. */
$pageTitle = 'Leaderboards';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    if (($_POST['action'] ?? '') === 'visibility') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $show = !empty($_POST['show']) ? 1 : 0;
        $st = $pdo->prepare("UPDATE users SET show_on_leaderboard = ? WHERE id = ? AND role = 'student'");
        $st->execute([$show, $sid]);
        $n = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $n->execute([$sid]);
        ax_flash('ok', ($n->fetchColumn() ?: 'Learner') . ($show ? ' is shown on public leaderboards again.' : ' is now hidden from public leaderboards.'));
    }
    ax_back();
}

$view = ($_GET['view'] ?? '') === 'course' ? 'course' : 'global';
$period = in_array($_GET['period'] ?? '', ['week', 'month'], true) ? $_GET['period'] : 'all';
$periodLabels = ['all' => 'All time', 'month' => 'Last 30 days', 'week' => 'Last 7 days'];

/** Course board: course_leaderboard() for all-time / week; same ranking over 30 days for "month". */
function lb_course(PDO $pdo, int $cid, string $period): array {
    if ($period !== 'month') return course_leaderboard($pdo, $cid, $period, 200);
    $st = $pdo->prepare("SELECT u.id, u.name, u.avatar, e.progress_percent, e.status, e.enrolled_at,
                                COALESCE((SELECT SUM(le.xp) FROM learning_events le WHERE le.user_id = u.id AND le.course_id = e.course_id AND le.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS course_xp,
                                (SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id WHERE lp.student_id = u.id AND l.course_id = e.course_id AND lp.status = 'completed') AS lessons_done,
                                (SELECT MAX(le.created_at) FROM learning_events le WHERE le.user_id = u.id AND le.course_id = e.course_id) AS last_active
                         FROM enrollments e JOIN users u ON u.id = e.student_id
                         WHERE e.course_id = ? AND e.status IN ('active','completed') AND u.role = 'student' AND u.show_on_leaderboard = 1
                         ORDER BY course_xp DESC, e.progress_percent DESC, last_active ASC LIMIT 200");
    $st->execute([$cid]);
    $rows = $st->fetchAll();
    foreach ($rows as $i => &$r) { $r['rank'] = $i + 1; $r['display_name'] = leaderboard_name($r['name']); }
    unset($r);
    return $rows;
}

// Courses that have learners (for the picker).
$courses = $pdo->query("SELECT c.id, c.title, COUNT(e.id) AS learners FROM courses c JOIN enrollments e ON e.course_id = c.id AND e.status IN ('active','completed') JOIN users u ON u.id = e.student_id AND u.role = 'student'
                        GROUP BY c.id, c.title ORDER BY learners DESC, c.title")->fetchAll();
$courseId = (int)($_GET['course'] ?? 0);
if ($view === 'course' && !$courseId && $courses) $courseId = (int)$courses[0]['id'];

$hiddenCount = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'student' AND show_on_leaderboard = 0");
$learnerCount = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM users WHERE role = 'student'");
$weekXp = (int)ax_scalar($pdo, "SELECT SUM(le.xp) FROM learning_events le JOIN users u ON u.id = le.user_id AND u.role = 'student' WHERE le.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$bestStreak = (int)ax_scalar($pdo, "SELECT MAX(GREATEST(streak_days, best_streak)) FROM users WHERE role = 'student'");

$rows = [];
$hiddenInCourse = [];
if ($view === 'global') {
    if ($period === 'all') {
        $rows = $pdo->query("SELECT u.id, u.name, u.email, u.avatar, u.xp AS score, u.xp, u.streak_days, u.best_streak, u.show_on_leaderboard,
                                    (SELECT COUNT(*) FROM student_badges sb WHERE sb.student_id = u.id) AS badges
                             FROM users u WHERE u.role = 'student' ORDER BY u.xp DESC, u.name LIMIT 300")->fetchAll();
    } else {
        $days = $period === 'week' ? 7 : 30;
        $rows = $pdo->query("SELECT u.id, u.name, u.email, u.avatar, u.xp, u.streak_days, u.best_streak, u.show_on_leaderboard,
                                    COALESCE((SELECT SUM(le.xp) FROM learning_events le WHERE le.user_id = u.id AND le.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)), 0) AS score,
                                    (SELECT COUNT(*) FROM student_badges sb WHERE sb.student_id = u.id) AS badges
                             FROM users u WHERE u.role = 'student' ORDER BY score DESC, u.xp DESC, u.name LIMIT 300")->fetchAll();
    }
    // Public rank counts only learners who are shown.
    $rank = 0;
    foreach ($rows as &$r) { $r['rank'] = $r['show_on_leaderboard'] ? ++$rank : null; }
    unset($r);
} elseif ($courseId) {
    $rows = lb_course($pdo, $courseId, $period);
    foreach ($rows as &$r) { $r['score'] = (int)$r['course_xp']; $r['show_on_leaderboard'] = 1; }
    unset($r);
    $st = $pdo->prepare("SELECT u.id, u.name, u.email, u.avatar, e.progress_percent FROM enrollments e JOIN users u ON u.id = e.student_id
                         WHERE e.course_id = ? AND e.status IN ('active','completed') AND u.role = 'student' AND u.show_on_leaderboard = 0 ORDER BY u.name");
    $st->execute([$courseId]);
    $hiddenInCourse = $st->fetchAll();
}
$podium = array_values(array_filter($rows, fn($r) => $r['show_on_leaderboard'] && (int)$r['score'] > 0));
$podium = array_slice($podium, 0, 3);
$courseTitle = '';
foreach ($courses as $c) if ((int)$c['id'] === $courseId) $courseTitle = $c['title'];

$link = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['view' => $view, 'course' => $view === 'course' ? $courseId : '', 'period' => $period], $o), fn($v) => $v !== '' && $v !== 0 && $v !== 'all' && $v !== 'global'));

/** Hide / show toggle form. */
function lb_toggle(array $r): string {
    $show = (int)$r['show_on_leaderboard'];
    return '<form method="post"' . ($show ? ' data-confirm="Hide ' . e($r['name']) . ' from all public leaderboards? They keep their XP and badges."' : '') . '>' . ax_csrf()
         . '<input type="hidden" name="action" value="visibility"><input type="hidden" name="student_id" value="' . (int)$r['id'] . '">'
         . ($show ? '' : '<input type="hidden" name="show" value="1">')
         . '<button class="ax-btn sm ' . ($show ? 'ghost' : 'ok') . '"><i class="fas fa-' . ($show ? 'eye-slash' : 'eye') . '"></i> ' . ($show ? 'Hide' : 'Show') . '</button></form>';
}

require_once 'includes/sidebar.php';
?>
<style>
.main-content { min-width: 0; } /* keep wide tables scrolling inside their panel */
.lb-podium { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; align-items: end; max-width: 760px; margin: 0 auto; padding: 8px 0 4px; }
.lb-spot { text-align: center; min-width: 0; }
.lb-spot .av { position: relative; display: inline-block; margin-bottom: 10px; }
.lb-spot .ax-avatar { width: 72px; height: 72px; border-radius: 22px; font-size: 1.6rem; border: 3px solid var(--surface); box-shadow: 0 10px 24px -10px rgba(0,0,0,.45); }
.lb-spot.r1 .ax-avatar { width: 92px; height: 92px; border-radius: 28px; font-size: 2rem; }
.lb-spot .medal { position: absolute; right: -8px; bottom: -6px; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 900; font-size: .8rem; border: 3px solid var(--surface); }
.lb-spot.r1 .medal { background: #eab308; } .lb-spot.r2 .medal { background: #94a3b8; } .lb-spot.r3 .medal { background: #c2783a; }
.lb-spot b { display: block; font: 800 .95rem 'Poppins', sans-serif; color: var(--text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.lb-spot small { display: block; color: var(--text-muted); font-size: .74rem; font-weight: 600; }
.lb-spot .step { margin-top: 10px; border-radius: 14px 14px 0 0; display: flex; align-items: center; justify-content: center; font: 800 .95rem 'Poppins', sans-serif; color: var(--text-main); }
.lb-spot.r1 .step { height: 92px; background: linear-gradient(180deg, color-mix(in srgb, #eab308 30%, transparent), color-mix(in srgb, #eab308 12%, transparent)); }
.lb-spot.r2 .step { height: 66px; background: linear-gradient(180deg, color-mix(in srgb, #94a3b8 30%, transparent), color-mix(in srgb, #94a3b8 12%, transparent)); }
.lb-spot.r3 .step { height: 48px; background: linear-gradient(180deg, color-mix(in srgb, #c2783a 30%, transparent), color-mix(in srgb, #c2783a 12%, transparent)); }
.lb-rank { font: 900 1.05rem 'Poppins', sans-serif; color: var(--text-muted); }
.lb-xp { font-weight: 800; color: var(--warning); white-space: nowrap; }
.lb-picker { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.lb-picker select { padding: 9px 12px; border: 1.5px solid var(--border); border-radius: 11px; font: 600 .86rem 'Inter', sans-serif; max-width: 100%; background: var(--surface); color: var(--text-main); }
@media (max-width: 640px) {
  .lb-podium { gap: 8px; }
  .lb-spot .ax-avatar { width: 52px; height: 52px; border-radius: 16px; font-size: 1.1rem; }
  .lb-spot.r1 .ax-avatar { width: 64px; height: 64px; border-radius: 20px; font-size: 1.3rem; }
  .lb-spot .medal { width: 24px; height: 24px; font-size: .7rem; right: -6px; }
  .lb-spot b { font-size: .8rem; } .lb-spot small { font-size: .68rem; }
  .lb-spot.r1 .step { height: 70px; } .lb-spot.r2 .step { height: 50px; } .lb-spot.r3 .step { height: 36px; }
  .lb-picker, .lb-picker select { width: 100%; }
}
@media (max-width: 760px) { .lb-table td[data-label="Learner"] { justify-content: flex-start; } .lb-table td[data-label="Learner"]::before { display: none; } }
</style>
<main class="main-content">
    <?php ax_header('Leaderboards', '', 'XP rankings', '<a class="ax-btn ghost" href="badges"><i class="fas fa-medal"></i> Badges &amp; XP</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <p class="ax-intro">Learners are ranked by the XP they earn. The boards learners see (course pages and their leaderboard) show first name and last initial only; you see everyone here, including
            learners who are hidden (they opted out, or you hid them).</p>
        <div class="ax-stats">
            <?= ax_stat('users', number_format($learnerCount), 'Learners', number_format($learnerCount - $hiddenCount) . ' shown publicly', '') ?>
            <?= ax_stat('eye-slash', number_format($hiddenCount), 'Hidden', 'Not on public boards', 'slate') ?>
            <?= ax_stat('bolt', number_format($weekXp), 'XP this week', 'Last 7 days, all learners', 'amber') ?>
            <?= ax_stat('fire', number_format($bestStreak) . ' d', 'Longest streak', 'Best run of learning days', 'orange') ?>
        </div>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Leaderboard">
                <a href="<?= e($link(['view' => 'global', 'course' => ''])) ?>" class="<?= $view === 'global' ? 'on' : '' ?>"><i class="fas fa-earth-africa"></i> Global XP</a>
                <a href="<?= e($link(['view' => 'course'])) ?>" class="<?= $view === 'course' ? 'on' : '' ?>"><i class="fas fa-book"></i> By course</a>
            </nav>
            <nav class="ax-tabs" aria-label="Period">
                <?php foreach ($periodLabels as $k => $lab): ?><a href="<?= e($link(['period' => $k])) ?>" class="<?= $period === $k ? 'on' : '' ?>"><?= e($lab) ?></a><?php endforeach; ?>
            </nav>
        </div>

        <?php if ($view === 'course'): ?>
            <section class="ax-panel">
                <div class="ax-panel-b">
                    <form class="lb-picker" method="get">
                        <input type="hidden" name="view" value="course"><?php if ($period !== 'all'): ?><input type="hidden" name="period" value="<?= e($period) ?>"><?php endif; ?>
                        <label for="lb-course" style="font-size:.72rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:.8px">Course</label>
                        <select id="lb-course" name="course" onchange="this.form.submit()">
                            <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === $courseId ? ' selected' : '' ?>><?= e($c['title']) ?> (<?= (int)$c['learners'] ?> learner<?= (int)$c['learners'] === 1 ? '' : 's' ?>)</option><?php endforeach; ?>
                        </select>
                        <noscript><button class="ax-btn sm">Show</button></noscript>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($podium): ?>
            <section class="ax-panel">
                <div class="ax-panel-h"><div><h2><i class="fas fa-trophy"></i> Top 3 · <?= e($view === 'course' ? $courseTitle : 'Global') ?></h2><p><?= e($periodLabels[$period]) ?> · as the public sees it.</p></div></div>
                <div class="ax-panel-b">
                    <div class="lb-podium">
                        <?php foreach ([1, 0, 2] as $i): if (!isset($podium[$i])) { echo '<div></div>'; continue; } $p = $podium[$i]; ?>
                            <div class="lb-spot r<?= $i + 1 ?>">
                                <div class="av"><?= ax_avatar($p['name'], $p['avatar']) ?><span class="medal"><?= $i + 1 ?></span></div>
                                <b title="<?= e($p['name']) ?>"><?= e($p['name']) ?></b>
                                <small>Public: <?= e(leaderboard_name($p['name'])) ?></small>
                                <div class="step"><span class="lb-xp"><?= number_format((int)$p['score']) ?> XP</span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="ax-panel">
            <div class="ax-panel-h">
                <div><h2><i class="fas fa-ranking-star"></i> <?= $view === 'course' ? e($courseTitle ?: 'Course leaderboard') : 'Global XP leaderboard' ?></h2>
                    <p><?= $view === 'course' ? 'XP earned in this course' : ($period === 'all' ? 'Total XP' : 'XP earned') ?> · <?= e($periodLabels[$period]) ?></p></div>
                <div class="ax-search" style="flex-basis:240px"><i class="fas fa-search"></i><input type="search" placeholder="Find a learner…" data-filter-table="lbTable" aria-label="Find a learner"></div>
            </div>
            <div class="ax-table-wrap">
                <table class="ax-table cards lb-table" id="lbTable">
                    <?php if ($view === 'global'): ?>
                        <thead><tr><th>Rank</th><th>Learner</th><th><?= $period === 'all' ? 'XP' : 'XP (' . e(strtolower($periodLabels[$period])) . ')' ?></th><th>Level</th><th>Streak</th><th>Badges</th><th>Public</th><th style="text-align:right">Visibility</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): $lv = xp_level((int)$r['xp']); ?>
                            <tr>
                                <td data-label="Rank"><span class="lb-rank"><?= $r['rank'] ? '#' . $r['rank'] : '—' ?></span></td>
                                <td data-label="Learner"><div class="ax-who"><?= ax_avatar($r['name'], $r['avatar']) ?><div><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?></small></div></div></td>
                                <td data-label="XP" class="num"><span class="lb-xp"><?= number_format((int)$r['score']) ?></span><?= $period !== 'all' ? '<div class="muted">' . number_format((int)$r['xp']) . ' total</div>' : '' ?></td>
                                <td data-label="Level"><b style="color:var(--text-main)">Lv <?= $lv['level'] ?></b> <span class="muted"><?= e($lv['title']) ?></span>
                                    <div class="ax-bar" style="margin-top:5px;max-width:120px" title="<?= $lv['pct'] ?>% to level <?= $lv['level'] + 1 ?>"><i style="width:<?= $lv['pct'] ?>%"></i></div></td>
                                <td data-label="Streak" class="num"><i class="fas fa-fire" style="color:var(--secondary)"></i> <?= (int)$r['streak_days'] ?> d <span class="muted">(best <?= max((int)$r['best_streak'], (int)$r['streak_days']) ?>)</span></td>
                                <td data-label="Badges" class="num"><i class="fas fa-medal" style="color:var(--accent)"></i> <?= (int)$r['badges'] ?></td>
                                <td data-label="Public"><?= $r['show_on_leaderboard'] ? '<span class="ax-pill ok">Shown</span>' : '<span class="ax-pill dark"><i class="fas fa-eye-slash"></i> Hidden</span>' ?></td>
                                <td data-label="" class="actions"><?= lb_toggle($r) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    <?php else: ?>
                        <thead><tr><th>Rank</th><th>Learner</th><th>Course XP</th><th>Progress</th><th>Lessons done</th><th>Last active</th><th style="text-align:right">Visibility</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td data-label="Rank"><span class="lb-rank">#<?= (int)$r['rank'] ?></span></td>
                                <td data-label="Learner"><div class="ax-who"><?= ax_avatar($r['name'], $r['avatar']) ?><div><b><?= e($r['name']) ?></b><small>Public: <?= e($r['display_name']) ?><?= $r['status'] === 'completed' ? ' · completed' : '' ?></small></div></div></td>
                                <td data-label="Course XP" class="num"><span class="lb-xp"><?= number_format((int)$r['score']) ?></span></td>
                                <td data-label="Progress"><div style="display:flex;align-items:center;gap:8px;justify-content:inherit"><div class="ax-bar green" style="width:90px"><i style="width:<?= (int)$r['progress_percent'] ?>%"></i></div><span class="num"><?= (int)$r['progress_percent'] ?>%</span></div></td>
                                <td data-label="Lessons done" class="num"><?= (int)$r['lessons_done'] ?></td>
                                <td data-label="Last active" class="num"><?= ax_ago($r['last_active']) ?></td>
                                <td data-label="" class="actions"><?= lb_toggle($r) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($hiddenInCourse as $h): ?>
                            <tr>
                                <td data-label="Rank"><span class="lb-rank">—</span></td>
                                <td data-label="Learner"><div class="ax-who"><?= ax_avatar($h['name'], $h['avatar']) ?><div><b><?= e($h['name']) ?></b><small><?= e($h['email']) ?></small></div></div></td>
                                <td data-label="Course XP" colspan="4"><span class="ax-pill dark"><i class="fas fa-eye-slash"></i> Hidden from leaderboards</span> <span class="muted"><?= (int)$h['progress_percent'] ?>% progress</span></td>
                                <td data-label="" class="actions"><?= lb_toggle($h + ['show_on_leaderboard' => 0]) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    <?php endif; ?>
                </table>
                <?php if (!$rows && !$hiddenInCourse): ?>
                    <div class="ax-empty"><i class="fas fa-ranking-star"></i><b><?= $view === 'course' && !$courses ? 'No course has learners yet' : 'No learners to rank yet' ?></b>Rankings appear once learners start earning XP.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
