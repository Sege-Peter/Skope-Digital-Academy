<?php
/**
 * Admin: peer-graded course projects.
 * Tabs: Needs staff review · Flagged · All submissions · Projects (per-project settings).
 * Submission detail (?sub=ID): work, every review (remove abusive ones), staff grade / override, clear flags.
 */
$pageTitle = 'Peer Projects';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/projects.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    if ($act === 'settings') {
        $p = project_by_id($pdo, (int)($_POST['project_id'] ?? 0));
        if (!$p) { ax_flash('err', 'That project no longer exists.'); ax_back(); }
        $pass = max(1, min(100, (int)($_POST['pass_pct'] ?? 70)));
        $req = max(1, min(10, (int)($_POST['reviews_required'] ?? 3)));
        $file = !empty($_POST['allow_file']) ? 1 : 0;
        $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE course_projects SET pass_pct = ?, reviews_required = ?, allow_file = ?, status = ?, settings_updated_by = ?, settings_updated_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$pass, $req, $file, $status, $user['id'], $p['id']]);
        // New rules apply to work that is still waiting for a grade.
        $w = $pdo->prepare("SELECT id FROM project_submissions WHERE project_id = ? AND released_at IS NULL AND status IN ('submitted','staff_review')");
        $w->execute([$p['id']]);
        foreach ($w->fetchAll(PDO::FETCH_COLUMN) as $sid) project_recompute($pdo, (int)$sid);
        ax_log('project.settings', 'course_project', (int)$p['id'], "{$p['title']}: pass $pass%, $req reviews, files " . ($file ? 'on' : 'off') . ", $status");
        ax_flash('ok', 'Settings saved for “' . $p['title'] . '”. They stay even if the course content is re-imported.');
    } elseif ($act === 'grade') {
        $sid = (int)($_POST['submission_id'] ?? 0);
        [$ok, $out] = project_staff_grade($pdo, $sid, (int)$user['id'], $_POST['score'] ?? [], (string)($_POST['feedback'] ?? ''));
        if ($ok) {
            $st = $pdo->prepare("SELECT s.status, u.name, p.title FROM project_submissions s JOIN course_projects p ON p.id = s.project_id LEFT JOIN users u ON u.id = s.student_id WHERE s.id = ?");
            $st->execute([$sid]);
            $g = $st->fetch();
            ax_log('project.grade', 'project_submission', $sid, 'Graded ' . round($out) . '% (' . $g['status'] . ') – ' . ($g['name'] ?? 'deleted learner') . ', ' . $g['title']);
            ax_flash('ok', 'Grade saved: ' . round($out) . '% – ' . ($g['status'] === 'passed' ? 'passed' : 'needs work') . '. The learner has been notified.');
        } else {
            ax_flash('err', $out);
        }
    } elseif ($act === 'remove_review') {
        $r = project_remove_review($pdo, (int)($_POST['review_id'] ?? 0), (int)$user['id']);
        if ($r) {
            ax_log('project.review_remove', 'project_submission', (int)$r['submission_id'], 'Removed review #' . (int)$r['id'] . ' by user #' . (int)$r['reviewer_id'] . ' (grade recomputed, review XP taken back)');
            ax_flash('ok', 'Review removed. The grade was recalculated and the reviewer lost the XP for it.');
        } else {
            ax_flash('err', 'That review was already removed.');
        }
    } elseif ($act === 'clear_flag') {
        $sid = (int)($_POST['submission_id'] ?? 0);
        project_clear_flag($pdo, $sid);
        ax_log('project.flag_clear', 'project_submission', $sid, 'Cleared flags on submission #' . $sid);
        ax_flash('ok', 'Flag cleared. The submission is back in play.');
    }
    ax_back();
}

$moved = project_sweep($pdo);   // hand overdue submissions to staff (same rule the reader applies)

$tabs = ['staff' => 'Needs staff review', 'flagged' => 'Flagged', 'all' => 'All submissions', 'projects' => 'Projects'];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'staff';
$courseF = (int)($_GET['course'] ?? 0);
$statusF = in_array($_GET['status'] ?? '', ['submitted', 'passed', 'needs_work', 'staff_review', 'flagged'], true) ? $_GET['status'] : '';
$q = trim((string)($_GET['q'] ?? ''));

$flagSql = "(s.status = 'flagged' OR EXISTS (SELECT 1 FROM project_reviews fr WHERE fr.submission_id = s.id AND fr.flagged = 1 AND fr.removed_at IS NULL))";
$counts = [
    'staff'    => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM project_submissions WHERE status = 'staff_review'"),
    'flagged'  => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM project_submissions s WHERE $flagSql"),
    'all'      => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM project_submissions"),
    'projects' => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM course_projects"),
];
$stats = $pdo->query("SELECT COUNT(*) n, SUM(status = 'passed') passed, SUM(status = 'needs_work') failed, SUM(status = 'submitted') waiting,
                             COUNT(DISTINCT student_id) learners FROM project_submissions")->fetch();
$reviewsN = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM project_reviews WHERE removed_at IS NULL");
$released = (int)$stats['passed'] + (int)$stats['failed'];
$activeProjects = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM course_projects WHERE status = 'active'");

$select = "SELECT s.*, u.name, u.email, u.avatar, c.title AS course_title, c.slug, p.title AS project_title, p.reviews_required, p.pass_pct,
                  (SELECT COUNT(*) FROM project_reviews r WHERE r.submission_id = s.id AND r.removed_at IS NULL) AS n_reviews,
                  (SELECT COUNT(*) FROM project_reviews r WHERE r.submission_id = s.id AND r.removed_at IS NULL AND r.flagged = 1) AS n_flagged
           FROM project_submissions s JOIN course_projects p ON p.id = s.project_id
           LEFT JOIN users u ON u.id = s.student_id LEFT JOIN courses c ON c.id = s.course_id";
$rows = [];
if ($tab !== 'projects') {
    $where = []; $params = [];
    if ($tab === 'staff') $where[] = "s.status = 'staff_review'";
    if ($tab === 'flagged') $where[] = $flagSql;
    if ($tab === 'all') {
        if ($courseF) { $where[] = 's.course_id = ?'; $params[] = $courseF; }
        if ($statusF) { $where[] = 's.status = ?'; $params[] = $statusF; }
        if ($q !== '') { $where[] = '(u.name LIKE ? OR u.email LIKE ? OR c.title LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
    }
    $st = $pdo->prepare($select . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . ($tab === 'all' ? 's.created_at DESC' : 's.created_at ASC') . ', s.id DESC LIMIT 300');
    $st->execute($params);
    $rows = $st->fetchAll();
}
$projects = $pdo->query("SELECT p.*, c.title AS course_title, c.slug,
                                (SELECT COUNT(*) FROM project_submissions s WHERE s.project_id = p.id) AS subs,
                                (SELECT COUNT(DISTINCT s.student_id) FROM project_submissions s WHERE s.project_id = p.id AND s.status = 'passed') AS passed,
                                (SELECT COUNT(*) FROM project_reviews r WHERE r.project_id = p.id AND r.removed_at IS NULL) AS reviews
                         FROM course_projects p LEFT JOIN courses c ON c.id = p.course_id ORDER BY c.tutorial_order, c.title")->fetchAll();
$courseOpts = array_filter($projects, fn($p) => $p['course_title'] !== null);

// Submission detail
$detail = null; $detailReviews = []; $detailProject = null; $otherAttempts = [];
if (!empty($_GET['sub'])) {
    $st = $pdo->prepare($select . ' WHERE s.id = ?');
    $st->execute([(int)$_GET['sub']]);
    $detail = $st->fetch() ?: null;
    if ($detail) {
        $detailProject = project_by_id($pdo, (int)$detail['project_id']);
        $st = $pdo->prepare("SELECT r.*, u.name, u.email FROM project_reviews r LEFT JOIN users u ON u.id = r.reviewer_id WHERE r.submission_id = ? ORDER BY r.removed_at IS NOT NULL, r.created_at");
        $st->execute([$detail['id']]);
        $detailReviews = $st->fetchAll();
        $st = $pdo->prepare("SELECT id, attempt, status, score_pct, created_at FROM project_submissions WHERE project_id = ? AND student_id = ? AND id <> ? ORDER BY attempt DESC");
        $st->execute([$detail['project_id'], $detail['student_id'], $detail['id']]);
        $otherAttempts = $st->fetchAll();
        $fb = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $fb->execute([(int)$detail['flagged_by']]);
        $detail['flagged_by_name'] = $fb->fetchColumn() ?: null;
    }
}
$keepQs = array_diff_key($_GET, ['sub' => 1]);
$selfUrl = fn(array $o = []) => '?' . http_build_query(array_filter(array_merge($keepQs, $o), fn($v) => $v !== '' && $v !== null && $v !== 0));
$pill = function (string $status): string { [$l, $c] = project_status_label($status); return '<span class="ax-pill ' . e($c) . '">' . e($l) . '</span>'; };

require_once 'includes/sidebar.php';
?>
<style>
.pj-work { white-space: pre-wrap; overflow-wrap: anywhere; font-size: .86rem; line-height: 1.6; color: var(--text-dim); background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; max-height: 320px; overflow: auto; }
.pj-links { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.pj-rv { border: 1px solid var(--border); border-radius: 12px; padding: 12px 14px; margin-bottom: 10px; background: var(--surface); }
.pj-rv.removed { opacity: .55; }
.pj-rv.flagged { border-color: color-mix(in srgb, var(--warning) 55%, var(--border)); background: color-mix(in srgb, var(--warning) 8%, var(--surface)); }
.pj-rv-h { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 6px; }
.pj-rv-h b { color: var(--text-main); font-size: .88rem; }
.pj-rv-h small { color: var(--text-muted); font-size: .74rem; }
.pj-rv-h form { margin-left: auto; }
.pj-rv p { color: var(--text-dim); font-size: .85rem; line-height: 1.55; white-space: pre-line; overflow-wrap: anywhere; margin: 6px 0 0; }
.pj-sc { display: flex; flex-wrap: wrap; gap: 6px; }
.pj-sc span { font-size: .72rem; padding: 2px 9px; border-radius: 99px; background: var(--surface-2); border: 1px solid var(--border); color: var(--text-dim); }
.pj-sc span b { color: var(--text-main); }
.pj-sec { font: 800 .78rem 'Inter', sans-serif; letter-spacing: .06em; text-transform: uppercase; color: var(--text-muted); margin: 18px 0 8px; }
.pj-sec:first-child { margin-top: 0; }
.pj-crit-row { display: grid; grid-template-columns: minmax(0, 1fr) 96px; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px solid var(--border); }
.pj-crit-row b { color: var(--text-main); font-size: .85rem; display: block; }
.pj-crit-row small { color: var(--text-muted); font-size: .76rem; display: block; line-height: 1.4; }
.pj-crit-row input { text-align: center; }
.pj-total { font-weight: 800; color: var(--text-main); }
.pj-crit-row:has(#gradeTotal) { grid-template-columns: auto minmax(0, 1fr); }
#gradeTotal { text-align: right; }
.pj-flagbox { border-radius: 12px; padding: 10px 14px; margin-bottom: 12px; background: color-mix(in srgb, var(--warning) 12%, transparent); border: 1px solid color-mix(in srgb, var(--warning) 40%, transparent); color: var(--text-dim); font-size: .85rem; display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap; }
.pj-flagbox i { color: var(--warning); margin-top: 3px; }
.pj-flagbox form { margin-left: auto; }
.pj-sub-title { font-weight: 700; color: var(--text-main); font-size: .85rem; }
.pj-sub-meta { color: var(--text-muted); font-size: .74rem; }
.pj-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.pj-filters select { padding: 9px 12px; border: 1px solid var(--border); border-radius: 12px; font: 600 .84rem 'Inter', sans-serif; background: var(--surface); color: var(--text-main); max-width: 100%; }
#subDlg { width: min(920px, calc(100vw - 24px)); }
.pj-dlg-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); gap: 20px; }
@media (max-width: 860px) { .pj-dlg-grid { grid-template-columns: 1fr; } }
@media (max-width: 760px) { .pj-filters, .pj-filters select { width: 100%; } #pjTable td[data-label="Learner"]::before { display: none; } #pjTable td[data-label="Learner"] { justify-content: flex-start; } }
</style>
<main class="main-content">
    <?php ax_header('Peer', 'Projects', 'Course projects graded by classmates'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <?php if ($moved): ?><div class="ax-alert info" role="status"><i class="fas fa-circle-info"></i><div><?= $moved ?> submission<?= $moved === 1 ? '' : 's' ?> waited <?= PROJECT_STAFF_DAYS ?>+ days without enough reviews and moved to “Needs staff review”.</div></div><?php endif; ?>
        <p class="ax-intro">Each free course ends with a project. Learners submit their work, review classmates against the rubric (3 by default), and get the average peer score once their own reviews are done.
            Work that waits <?= PROJECT_STAFF_DAYS ?> days without enough reviews comes here for a tutor's grade; learners can also flag unfair reviews or bad submissions.</p>
        <div class="ax-stats">
            <?= ax_stat('inbox', number_format((int)$stats['n']), 'Submissions', number_format((int)$stats['learners']) . ' learner' . ((int)$stats['learners'] === 1 ? '' : 's') . ' · ' . $activeProjects . ' active projects', '') ?>
            <?= ax_stat('hourglass-half', number_format((int)$stats['waiting']), 'Waiting for peers', 'Not graded yet', 'amber') ?>
            <?= ax_stat('chalkboard-user', number_format($counts['staff']), 'Needs staff review', 'Waited ' . PROJECT_STAFF_DAYS . '+ days', 'violet') ?>
            <?= ax_stat('flag', number_format($counts['flagged']), 'Flagged', 'Submissions or reviews', 'red') ?>
            <?= ax_stat('circle-check', $released ? round((int)$stats['passed'] / $released * 100) . '%' : '—', 'Pass rate', number_format((int)$stats['passed']) . ' passed · ' . number_format($reviewsN) . ' reviews', 'green') ?>
        </div>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-<?= ['staff' => 'chalkboard-user', 'flagged' => 'flag', 'all' => 'inbox', 'projects' => 'diagram-project'][$tab] ?>"></i> <?= e($tabs[$tab]) ?></h2>
                <p><?= ['staff' => 'Oldest first. Open one and grade it with the rubric – the grade is released to the learner at once.',
                        'flagged' => 'Submissions a reviewer flagged, and reviews a learner reported as unfair. Clear the flag, remove the review, or grade the work yourself.',
                        'all' => 'Every attempt by every learner (latest 300).',
                        'projects' => 'One project per course, imported from content/courses/&lt;slug&gt;/project.md. Settings you change here are kept on re-import.'][$tab] ?></p></div>
                <?php if ($tab !== 'projects'): ?><div class="ax-search" style="flex-basis:220px"><i class="fas fa-search"></i><input type="search" placeholder="Filter this list…" data-filter-table="pjTable" aria-label="Filter submissions"></div><?php endif; ?>
            </div>
            <div class="ax-panel-b" style="padding-bottom:4px">
                <nav class="ax-tabs" aria-label="Views">
                    <?php foreach ($tabs as $k => $label): ?><a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($label) ?> <span class="n"><?= $counts[$k] ?></span></a><?php endforeach; ?>
                </nav>
                <?php if ($tab === 'all'): ?>
                    <form class="ax-toolbar pj-filters" method="get" role="search" style="margin-top:12px"><input type="hidden" name="tab" value="all">
                        <select name="course" aria-label="Course" onchange="this.form.submit()"><option value="">All courses</option>
                            <?php foreach ($courseOpts as $c): ?><option value="<?= (int)$c['course_id'] ?>"<?= (int)$c['course_id'] === $courseF ? ' selected' : '' ?>><?= e($c['course_title']) ?></option><?php endforeach; ?></select>
                        <select name="status" aria-label="Status" onchange="this.form.submit()"><option value="">Any status</option>
                            <?php foreach (['submitted', 'staff_review', 'flagged', 'passed', 'needs_work'] as $s): ?><option value="<?= $s ?>"<?= $statusF === $s ? ' selected' : '' ?>><?= e(project_status_label($s)[0]) ?></option><?php endforeach; ?></select>
                        <div class="ax-search"><i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Learner or course…" aria-label="Search"></div>
                        <?php if ($courseF || $statusF || $q !== ''): ?><a class="ax-btn sm ghost" href="?tab=all"><i class="fas fa-xmark"></i> Clear</a><?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($tab === 'projects'): ?>
                <div class="ax-table-wrap">
                    <table class="ax-table cards" id="pjProjects">
                        <thead><tr><th>Course &amp; project</th><th>Rubric</th><th>Rules</th><th>Activity</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($projects as $p): $rub = json_decode((string)$p['rubric'], true) ?: []; ?>
                            <tr>
                                <td data-label="Project"><div><div class="pj-sub-title"><?= e($p['title']) ?></div><div class="pj-sub-meta"><?= e($p['course_title'] ?? 'Deleted course') ?></div></div></td>
                                <td data-label="Rubric" class="num"><?= count($rub) ?> parts · <?= array_sum(array_column($rub, 'points')) ?> pts</td>
                                <td data-label="Rules" style="font-size:.8rem">Pass <b style="color:var(--text-main)"><?= (int)$p['pass_pct'] ?>%</b> · <?= (int)$p['reviews_required'] ?> reviews · <?= $p['allow_file'] ? 'files on' : 'no files' ?><?= $p['settings_updated_at'] ? '<div class="muted" style="font-size:.72rem">edited ' . ax_ago($p['settings_updated_at']) . '</div>' : '' ?></td>
                                <td data-label="Activity" class="num"><?= (int)$p['subs'] ?> submitted · <?= (int)$p['passed'] ?> passed · <?= (int)$p['reviews'] ?> reviews</td>
                                <td data-label="Status"><span class="ax-pill <?= $p['status'] === 'active' ? 'ok' : 'dark' ?>"><?= $p['status'] === 'active' ? 'Active' : 'Paused' ?></span></td>
                                <td data-label="" class="actions">
                                    <?php if ($p['slug']): ?><a class="ax-btn sm ghost" href="../learn/<?= e($p['slug']) ?>/project" target="_blank" rel="noopener"><i class="fas fa-eye"></i> View</a><?php endif; ?>
                                    <button type="button" class="ax-btn sm" data-edit='<?= e(json_encode(['id' => (int)$p['id'], 'title' => $p['title'], 'course' => $p['course_title'], 'pass' => (int)$p['pass_pct'], 'req' => (int)$p['reviews_required'], 'file' => (int)$p['allow_file'], 'status' => $p['status']])) ?>'><i class="fas fa-sliders"></i> Settings</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$projects): ?><div class="ax-empty"><i class="fas fa-diagram-project"></i><b>No projects yet</b>Add project.md files to content/courses/&lt;slug&gt;/ and run import_tutorials.php.</div><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="ax-table-wrap">
                    <table class="ax-table cards" id="pjTable">
                        <thead><tr><th>Learner</th><th>Project</th><th>Submitted</th><th>Reviews</th><th>Score</th><th>Status</th><th style="text-align:right"></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td data-label="Learner"><div class="ax-who"><?= ax_avatar($r['name'] ?? '?', $r['avatar']) ?><div><b><?= e($r['name'] ?? 'Deleted learner') ?></b><small><?= e($r['email'] ?? '—') ?></small></div></div></td>
                                <td data-label="Project"><div><div class="pj-sub-title"><?= e($r['course_title'] ?? '—') ?></div><div class="pj-sub-meta">Attempt <?= (int)$r['attempt'] ?><?= $r['file'] ? ' · <i class="fas fa-paperclip"></i> file' : '' ?><?= $r['link'] ? ' · <i class="fas fa-link"></i> link' : '' ?></div></div></td>
                                <td data-label="Submitted" class="num"><?= ax_ago($r['created_at']) ?></td>
                                <td data-label="Reviews" class="num"><?= (int)$r['n_reviews'] ?> / <?= (int)$r['reviews_required'] ?><?= (int)$r['n_flagged'] ? ' <span class="ax-pill warn" title="Flagged reviews"><i class="fas fa-flag"></i> ' . (int)$r['n_flagged'] . '</span>' : '' ?></td>
                                <td data-label="Score" class="num"><span><?= $r['score_pct'] !== null ? '<b style="color:var(--text-main)">' . round((float)$r['score_pct']) . '%</b>' . ($r['graded_by_staff'] ? ' <span class="muted">staff</span>' : '') . (!$r['released_at'] ? ' <span class="muted">· not released</span>' : '') : '<span class="muted">—</span>' ?></span></td>
                                <td data-label="Status"><?= $pill($r['status']) ?></td>
                                <td data-label="" class="actions"><a class="ax-btn sm<?= $tab === 'all' ? ' ghost' : '' ?>" href="<?= e($selfUrl(['sub' => (int)$r['id']])) ?>"><i class="fas fa-<?= $tab === 'staff' ? 'pen-to-square' : 'folder-open' ?>"></i> <?= $tab === 'staff' ? 'Grade' : 'Open' ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$rows): ?><div class="ax-empty"><i class="fas fa-<?= $tab === 'flagged' ? 'flag' : ($tab === 'staff' ? 'mug-hot' : 'inbox') ?>"></i><b><?= ['staff' => 'Nothing waiting for staff', 'flagged' => 'No flags – all clear', 'all' => ($courseF || $statusF || $q !== '') ? 'No submissions match these filters' : 'No submissions yet'][$tab] ?></b><?= $tab === 'all' ? 'Submissions appear here as learners finish course projects.' : '' ?></div><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Project settings -->
<dialog class="ax-modal" id="setDlg" aria-labelledby="setH" style="width:min(500px,calc(100vw - 32px))">
    <div class="ax-panel-h"><div><h2 id="setH"><i class="fas fa-sliders"></i> Project settings</h2><p data-f="course"></p></div>
        <button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>
    <form method="post" class="ax-panel-b ax-form"><?= ax_csrf() ?><input type="hidden" name="action" value="settings"><input type="hidden" name="project_id" data-f="id">
        <div class="ax-row">
            <div class="ax-field"><label for="st-pass">Pass mark (%)</label><input id="st-pass" type="number" name="pass_pct" min="1" max="100" required data-f="pass"><div class="hint">Average peer score needed to pass.</div></div>
            <div class="ax-field"><label for="st-req">Reviews required</label><input id="st-req" type="number" name="reviews_required" min="1" max="10" required data-f="req"><div class="hint">Each learner gives and receives this many.</div></div>
        </div>
        <div class="ax-field"><label for="st-status">Status</label><select id="st-status" name="status" data-f="status"><option value="active">Active – learners can submit and review</option><option value="inactive">Paused – hidden from the course; not required for completion</option></select></div>
        <label class="ax-check" style="margin-bottom:14px"><input type="checkbox" name="allow_file" value="1" data-f="file"> Allow file uploads (PDF, PNG, JPG, Word – 5 MB)</label>
        <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save settings</button></div>
    </form>
</dialog>

<?php if ($detail): $d = $detail; $live = array_values(array_filter($detailReviews, fn($r) => !$r['removed_at'])); ?>
<!-- Submission detail -->
<dialog class="ax-modal" id="subDlg" aria-labelledby="subH">
    <div class="ax-panel-h"><div><h2 id="subH"><i class="fas fa-folder-open"></i> <?= e($d['name'] ?? 'Deleted learner') ?> · attempt <?= (int)$d['attempt'] ?></h2>
        <p><?= e($d['course_title'] ?? '—') ?> · <?= e($d['project_title']) ?> · submitted <?= ax_date($d['created_at'], 'M j, Y H:i') ?></p></div>
        <button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-times"></i></button></div>
    <div class="ax-panel-b">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px">
            <?= $pill($d['status']) ?>
            <span class="ax-pill info"><?= count($live) ?> / <?= (int)$d['reviews_required'] ?> reviews</span>
            <?php if ($d['score_pct'] !== null): ?><span class="ax-pill <?= (float)$d['score_pct'] >= (int)$d['pass_pct'] ? 'ok' : 'bad' ?>"><?= round((float)$d['score_pct']) ?>% <?= $d['graded_by_staff'] ? '(staff)' : '(peers)' ?></span><?php endif; ?>
            <span class="ax-pill dark"><?= $d['released_at'] ? 'Released ' . ax_ago($d['released_at']) : 'Not released yet' ?></span>
            <?php foreach ($otherAttempts as $o): ?><a class="ax-pill" href="<?= e($selfUrl(['sub' => (int)$o['id']])) ?>">Attempt <?= (int)$o['attempt'] ?> · <?= e(project_status_label($o['status'])[0]) ?></a><?php endforeach; ?>
        </div>
        <?php if ($d['status'] === 'flagged' || (int)$d['n_flagged']): ?>
            <div class="pj-flagbox"><i class="fas fa-flag"></i><div>
                <?php if ($d['flag_reason']): ?><b style="color:var(--text-main)">Submission flagged</b> by <?= e($d['flagged_by_name'] ?? 'a learner') ?> <?= ax_ago($d['flagged_at']) ?>: “<?= e($d['flag_reason']) ?>”<br><?php endif; ?>
                <?php if ((int)$d['n_flagged']): ?><b style="color:var(--text-main)"><?= (int)$d['n_flagged'] ?> review<?= (int)$d['n_flagged'] === 1 ? '' : 's' ?> reported</b> by the learner as unfair – see below.<?php endif; ?>
                <?php if ($d['status'] === 'flagged'): ?><div class="muted" style="font-size:.78rem">The grade is on hold until you clear the flag, remove a review or grade it.</div><?php endif; ?></div>
                <form method="post" data-confirm="Clear every flag on this submission and put it back in play?"><?= ax_csrf() ?><input type="hidden" name="action" value="clear_flag"><input type="hidden" name="submission_id" value="<?= (int)$d['id'] ?>">
                    <button class="ax-btn sm ghost"><i class="fas fa-flag-checkered"></i> Clear flag</button></form>
            </div>
        <?php endif; ?>
        <div class="pj-dlg-grid">
            <div>
                <div class="pj-sec">Submitted work</div>
                <?= $d['text'] ? '<div class="pj-work">' . e($d['text']) . '</div>' : '<div class="muted" style="font-size:.85rem">No text – see the link or file.</div>' ?>
                <div class="pj-links">
                    <?php if ($d['link']): ?><a class="ax-btn sm ghost" href="<?= e($d['link']) ?>" target="_blank" rel="noopener noreferrer nofollow"><i class="fas fa-link"></i> <?= e(mb_strimwidth($d['link'], 0, 48, '…')) ?></a><?php endif; ?>
                    <?php if ($d['file']): ?><a class="ax-btn sm ghost" href="../project-file?id=<?= (int)$d['id'] ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> <?= e($d['file_name'] ?: 'File') ?> <span class="muted"><?= $d['file_size'] ? round($d['file_size'] / 1024) . ' KB' : '' ?></span></a><?php endif; ?>
                </div>

                <div class="pj-sec">Peer reviews (<?= count($live) ?><?= count($detailReviews) > count($live) ? ' + ' . (count($detailReviews) - count($live)) . ' removed' : '' ?>)</div>
                <?php if (!$detailReviews): ?><div class="muted" style="font-size:.85rem">No reviews yet.</div><?php endif; ?>
                <?php foreach ($detailReviews as $rv): $sc = json_decode((string)$rv['scores'], true) ?: []; ?>
                    <div class="pj-rv<?= $rv['removed_at'] ? ' removed' : ($rv['flagged'] ? ' flagged' : '') ?>">
                        <div class="pj-rv-h"><b><?= e($rv['name'] ?? 'Deleted learner') ?></b><span class="ax-pill <?= (float)$rv['total_pct'] >= (int)$d['pass_pct'] ? 'ok' : 'bad' ?>"><?= round((float)$rv['total_pct']) ?>%</span><small><?= ax_ago($rv['created_at']) ?></small>
                            <?php if ($rv['removed_at']): ?><span class="ax-pill dark">Removed <?= ax_ago($rv['removed_at']) ?></span>
                            <?php else: ?>
                                <?php if ($rv['flagged']): ?><span class="ax-pill warn"><i class="fas fa-flag"></i> Reported</span><?php endif; ?>
                                <form method="post" data-confirm="Remove this review by <?= e($rv['name'] ?? 'a deleted learner') ?>? It stops counting towards the grade, the grade is recalculated and the reviewer loses the <?= XP_RULES['project_review'] ?> XP for it." data-danger>
                                    <?= ax_csrf() ?><input type="hidden" name="action" value="remove_review"><input type="hidden" name="review_id" value="<?= (int)$rv['id'] ?>">
                                    <button class="ax-btn sm danger"><i class="fas fa-trash"></i> Remove</button></form>
                            <?php endif; ?>
                        </div>
                        <?php if ($rv['flagged'] && $rv['flag_reason']): ?><div style="font-size:.78rem;color:var(--warning);margin-bottom:4px">Learner's report: “<?= e($rv['flag_reason']) ?>”</div><?php endif; ?>
                        <div class="pj-sc"><?php foreach ($sc as $x): ?><span><?= e($x['c']) ?> <b><?= (int)$x['s'] ?>/<?= (int)$x['m'] ?></b></span><?php endforeach; ?></div>
                        <p><?= e($rv['feedback']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div>
                <div class="pj-sec"><?= $d['graded_by_staff'] ? 'Staff grade (edit to override)' : 'Grade as staff' ?></div>
                <?php if ($detailProject && $detailProject['rubric_list']): $prev = json_decode((string)$d['staff_scores'], true) ?: []; ?>
                <form method="post" class="ax-form" id="gradeForm"<?= $d['released_at'] ? ' data-confirm="This learner already has a released grade. Replace it with your grade?"' : '' ?>>
                    <?= ax_csrf() ?><input type="hidden" name="action" value="grade"><input type="hidden" name="submission_id" value="<?= (int)$d['id'] ?>">
                    <?php foreach ($detailProject['rubric_list'] as $i => $c): ?>
                        <div class="pj-crit-row"><div><b><?= e($c['criterion']) ?></b><small><?= e($c['description']) ?></small></div>
                            <input type="number" name="score[<?= $i ?>]" min="0" max="<?= (int)$c['points'] ?>" required value="<?= isset($prev[$i]['s']) ? (int)$prev[$i]['s'] : '' ?>" aria-label="<?= e($c['criterion']) ?> score out of <?= (int)$c['points'] ?>" placeholder="/ <?= (int)$c['points'] ?>"></div>
                    <?php endforeach; ?>
                    <div class="pj-crit-row"><span class="pj-total">Total</span><span class="pj-total" id="gradeTotal" data-max="<?= (int)$detailProject['max_points'] ?>" data-pass="<?= (int)$detailProject['pass_pct'] ?>">— / <?= (int)$detailProject['max_points'] ?></span></div>
                    <div class="ax-field" style="margin-top:10px"><label for="gr-fb">Feedback for the learner</label><textarea id="gr-fb" name="feedback" rows="5" required minlength="10" maxlength="3000" placeholder="What works, and what to improve."><?= e($d['staff_feedback'] ?? '') ?></textarea>
                        <div class="hint">Pass mark <?= (int)$detailProject['pass_pct'] ?>%. The grade replaces the peer average and is released to the learner immediately<?= $d['status'] === 'flagged' ? '; it also clears the flags' : '' ?>.</div></div>
                    <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Close</button><button class="ax-btn ok"><i class="fas fa-check"></i> Save grade</button></div>
                </form>
                <?php else: ?><div class="muted">This project has no rubric.</div><?php endif; ?>
            </div>
        </div>
    </div>
</dialog>
<?php endif; ?>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
  var dlg = document.getElementById('setDlg');
  document.querySelectorAll('[data-edit]').forEach(function (b) {
    b.addEventListener('click', function () {
      var p = JSON.parse(b.dataset.edit);
      dlg.querySelector('[data-f="course"]').textContent = p.title + ' · ' + (p.course || 'Deleted course');
      dlg.querySelector('[data-f="id"]').value = p.id;
      dlg.querySelector('[data-f="pass"]').value = p.pass;
      dlg.querySelector('[data-f="req"]').value = p.req;
      dlg.querySelector('[data-f="status"]').value = p.status;
      dlg.querySelector('[data-f="file"]').checked = !!p.file;
      dlg.showModal();
    });
  });
  var sub = document.getElementById('subDlg');
  if (sub) {
    sub.showModal();
    sub.addEventListener('close', function () {   // drop ?sub= so a refresh doesn't reopen it
      var u = new URL(location.href); u.searchParams.delete('sub'); history.replaceState(null, '', u);
    });
    var t = document.getElementById('gradeTotal'), f = document.getElementById('gradeForm');
    var sum = function () {
      var s = 0, all = true;
      f.querySelectorAll('input[type=number]').forEach(function (i) { if (i.value === '') all = false; s += Number(i.value || 0); });
      var pct = Math.round(s / Number(t.dataset.max) * 100);
      t.textContent = s + ' / ' + t.dataset.max + (all ? ' · ' + pct + '% · ' + (pct >= Number(t.dataset.pass) ? 'pass' : 'needs work') : '');
    };
    if (f && t) { f.addEventListener('input', sum); sum(); }
  }
})();
</script>
</body></html>
