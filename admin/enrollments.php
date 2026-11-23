<?php
/**
 * Admin: Enrolments – every learner on every course.
 *   Filter by status / course / track, search, CSV export.
 *   Actions: enrol a learner manually, change track or cohort, extend/remove access expiry,
 *   cancel / reactivate, reset progress (lesson progress only – XP history is kept).
 */
$exporting = ($_GET['export'] ?? '') === 'csv' && $_SERVER['REQUEST_METHOD'] === 'GET';
if ($exporting) {
    // No HTML for a download: load what header.php would, without its output.
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/includes/ui.php';
    requireRole('admin');
    $user = currentUser();
} else {
    $pageTitle = 'Enrolments';
    require_once 'includes/header.php';
}

const EN_TABS = ['all' => 'All', 'active' => 'Active', 'pending' => 'Pending', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'expired' => 'Access ended'];

/** The enrolment + learner + course for an id, or null. */
function en_get(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare("SELECT e.*, u.name AS learner, c.title AS course_title FROM enrollments e
                        LEFT JOIN users u ON u.id = e.student_id LEFT JOIN courses c ON c.id = e.course_id WHERE e.id = ?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function en_course(PDO $pdo, int $id): ?array {
    $s = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
/** A cohort of this course that is not archived (admins may place learners in full/closed cohorts). */
function en_cohort(PDO $pdo, int $courseId, int $cohortId): ?array {
    if (!$cohortId) return null;
    $s = $pdo->prepare("SELECT * FROM course_cohorts WHERE id = ? AND course_id = ? AND status <> 'archived'");
    $s->execute([$cohortId, $courseId]);
    return $s->fetch() ?: null;
}

/* ── Actions ────────────────────────────────────────────────────────── */
if (!$exporting && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($act === 'enrol') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $s = $pdo->prepare("SELECT id, name, role FROM users WHERE id = ?");
        $s->execute([$sid]);
        $learner = $s->fetch();
        $c = en_course($pdo, (int)($_POST['course_id'] ?? 0));
        $mode = (string)($_POST['mode'] ?? '');
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 200);
        if (!$learner || $learner['role'] !== 'student') { ax_flash('err', 'Pick a learner account.'); ax_back(); }
        if (!$c) { ax_flash('err', 'Pick a course.'); ax_back(); }
        if (!in_array($mode, course_modes($c), true)) $mode = course_modes($c)[0];
        $cohort = $mode === 'structured' ? en_cohort($pdo, (int)$c['id'], (int)($_POST['cohort_id'] ?? 0)) : null;
        if ($mode === 'structured' && (int)($_POST['cohort_id'] ?? 0) && !$cohort) { ax_flash('err', 'That cohort does not belong to this course.'); ax_back(); }
        $ex = get_enrollment($pdo, $sid, (int)$c['id']);
        if ($ex && in_array($ex['status'], ['active', 'completed'], true)) {
            ax_flash('err', "{$learner['name']} is already enrolled in \"{$c['title']}\". Use Manage on their row to change the track.");
            ax_back();
        }
        enroll_student($pdo, $sid, $c, $mode, $cohort ? (int)$cohort['id'] : null, 0);
        $en = get_enrollment($pdo, $sid, (int)$c['id']);
        notify($pdo, $sid, 'You have been enrolled 🎉', "You now have access to \"{$c['title']}\" (" . mode_label($mode) . ($cohort ? ', ' . $cohort['name'] : '') . ').'
            . ($note !== '' ? ' Note from the academy: ' . $note : ''));
        require_once __DIR__ . '/../includes/notify.php';
        sda_notify_enrolled($pdo, $sid, $c, $mode, $cohort ? (int)$cohort['id'] : null, 0, '', false);
        ax_log('enrollment.create', 'enrollment', $en ? (int)$en['id'] : null,
            "Enrolled {$learner['name']} in {$c['title']} (" . mode_label($mode) . ', no payment)' . ($note !== '' ? " – $note" : ''));
        ax_flash('ok', "{$learner['name']} is enrolled in \"{$c['title']}\" and has been notified.");
        ax_back();
    }

    $en = $id ? en_get($pdo, $id) : null;
    if (!$en) { ax_flash('err', 'That enrolment no longer exists.'); ax_back(); }
    $who = ($en['learner'] ?? 'Learner #' . $en['student_id']) . ' · ' . ($en['course_title'] ?? 'course #' . $en['course_id']);

    if ($act === 'track') {
        $c = en_course($pdo, (int)$en['course_id']);
        $mode = in_array($_POST['mode'] ?? '', SDA_MODES, true) ? $_POST['mode'] : $en['learning_mode'];
        $cohort = $mode === 'structured' ? en_cohort($pdo, (int)$en['course_id'], (int)($_POST['cohort_id'] ?? 0)) : null;
        $starts = $cohort ? $cohort['start_date'] : ($mode === $en['learning_mode'] && !$en['cohort_id'] && $en['starts_on'] ? $en['starts_on'] : date('Y-m-d'));
        $pdo->prepare("UPDATE enrollments SET learning_mode = ?, cohort_id = ?, starts_on = ? WHERE id = ?")
            ->execute([$mode, $cohort ? (int)$cohort['id'] : null, $starts, $id]);
        notify($pdo, (int)$en['student_id'], 'Your learning track changed', 'You are now on the ' . mode_label($mode) . ($cohort ? ' (' . $cohort['name'] . ')' : '') . ' for "' . ($c['title'] ?? '') . '".');
        ax_log('enrollment.track', 'enrollment', $id, "$who → " . mode_label($mode) . ($cohort ? ", {$cohort['name']}" : ''));
        ax_flash('ok', 'Track updated to ' . mode_label($mode) . ($cohort ? " ({$cohort['name']})" : '') . '.');
        ax_back();
    }

    if ($act === 'expiry') {
        $op = $_POST['op'] ?? '';
        if ($op === 'remove') {
            $new = null;
        } elseif ($op === 'extend') {
            $days = max(1, min(3650, (int)($_POST['days'] ?? 30)));
            $base = $en['expires_at'] && strtotime($en['expires_at']) > time() ? strtotime($en['expires_at']) : time();
            $new = date('Y-m-d H:i:s', strtotime("+$days days", $base));
        } else {
            $d = (string)($_POST['date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) { ax_flash('err', 'Pick a valid date.'); ax_back(); }
            $new = $d . ' 23:59:59';
        }
        $pdo->prepare("UPDATE enrollments SET expires_at = ? WHERE id = ?")->execute([$new, $id]);
        notify($pdo, (int)$en['student_id'], 'Course access updated', 'Your access to "' . ($en['course_title'] ?? '') . '" ' . ($new ? 'now runs until ' . date('M j, Y', strtotime($new)) : 'no longer expires') . '.');
        ax_log('enrollment.expiry', 'enrollment', $id, "$who – access " . ($new ? 'until ' . date('M j, Y', strtotime($new)) : 'never expires'));
        ax_flash('ok', $new ? 'Access now ends on ' . date('M j, Y', strtotime($new)) . '.' : 'Access no longer expires.');
        ax_back();
    }

    if ($act === 'status') {
        $to = $_POST['to'] ?? '';
        $wasCounted = in_array($en['status'], ['active', 'completed'], true);
        if ($to === 'cancel' && $en['status'] !== 'cancelled') {
            $pdo->prepare("UPDATE enrollments SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            if ($wasCounted) $pdo->prepare("UPDATE courses SET enrolled_count = GREATEST(enrolled_count - 1, 0) WHERE id = ?")->execute([$en['course_id']]);
            notify($pdo, (int)$en['student_id'], 'Enrolment cancelled', 'Your enrolment in "' . ($en['course_title'] ?? '') . '" was cancelled. Contact support if this is unexpected.');
            ax_log('enrollment.cancel', 'enrollment', $id, "Cancelled $who");
            ax_flash('ok', 'Enrolment cancelled. The learner has been told.');
        } elseif ($to === 'reactivate' && !$wasCounted) {
            $new = $en['completed_at'] && (float)$en['progress_percent'] >= 100 ? 'completed' : 'active';
            $pdo->prepare("UPDATE enrollments SET status = ? WHERE id = ?")->execute([$new, $id]);
            $pdo->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?")->execute([$en['course_id']]);
            notify($pdo, (int)$en['student_id'], 'Course access open', 'Your access to "' . ($en['course_title'] ?? '') . '" is open. Happy learning!');
            ax_log($en['status'] === 'pending' ? 'enrollment.activate' : 'enrollment.reactivate', 'enrollment', $id, ($en['status'] === 'pending' ? 'Activated' : 'Reactivated') . " $who");
            ax_flash('ok', 'Enrolment is ' . $new . ' again. The learner has been told.');
        }
        ax_back();
    }

    if ($act === 'reset') {
        if (trim((string)($_POST['confirm_text'] ?? '')) !== 'RESET') { ax_flash('err', 'Type RESET to confirm a progress reset.'); ax_back(); }
        $pdo->beginTransaction();
        $d = $pdo->prepare("DELETE lp FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id WHERE lp.student_id = ? AND l.course_id = ?");
        $d->execute([$en['student_id'], $en['course_id']]);
        $n = $d->rowCount();
        $d2 = $pdo->prepare("DELETE FROM lesson_progress WHERE student_id = ? AND course_id = ?");
        $d2->execute([$en['student_id'], $en['course_id']]);
        $n += $d2->rowCount();
        $pdo->prepare("UPDATE enrollments SET progress_percent = 0, completed_at = NULL, status = IF(status = 'completed', 'active', status) WHERE id = ?")->execute([$id]);
        $pdo->commit();
        notify($pdo, (int)$en['student_id'], 'Course progress reset', 'Your progress in "' . ($en['course_title'] ?? '') . '" was reset so you can start fresh. Your XP and badges are kept.');
        ax_log('enrollment.reset', 'enrollment', $id, "Reset progress for $who ($n lesson records removed; XP kept)");
        ax_flash('ok', "Progress reset ($n lesson record" . ($n === 1 ? '' : 's') . ' removed). XP history was kept.');
        ax_back();
    }
    ax_back();
}

/* ── Filters & data ─────────────────────────────────────────────────── */
$tab = isset(EN_TABS[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'all';
$fCourse = (int)($_GET['course'] ?? 0);
$fMode = in_array($_GET['mode'] ?? '', SDA_MODES, true) ? $_GET['mode'] : '';
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

$w = []; $p = [];
if ($fCourse) { $w[] = 'e.course_id = ?'; $p[] = $fCourse; }
if ($fMode) { $w[] = 'e.learning_mode = ?'; $p[] = $fMode; }
if ($q !== '') {
    $w[] = '(u.name LIKE ? OR u.email LIKE ? OR u.admission_number LIKE ? OR c.title LIKE ? OR co.name LIKE ?)';
    array_push($p, ...array_fill(0, 5, '%' . $q . '%'));
}
$tabSql = [
    'all' => '1', 'active' => "e.status = 'active'", 'pending' => "e.status = 'pending'", 'completed' => "e.status = 'completed'",
    'cancelled' => "e.status = 'cancelled'", 'expired' => "e.expires_at IS NOT NULL AND e.expires_at < NOW() AND e.status <> 'cancelled'",
];
$from = "FROM enrollments e
         LEFT JOIN users u ON u.id = e.student_id
         LEFT JOIN courses c ON c.id = e.course_id
         LEFT JOIN course_cohorts co ON co.id = e.cohort_id";
$baseWhere = $w ? implode(' AND ', $w) : '1';

$counts = [];
foreach ($tabSql as $k => $sql) {
    $s = $pdo->prepare("SELECT COUNT(*) $from WHERE $baseWhere AND $sql");
    $s->execute($p);
    $counts[$k] = (int)$s->fetchColumn();
}

$select = "SELECT e.*, u.name AS learner, u.email, u.avatar, u.admission_number, c.title AS course_title, c.source, c.slug, c.price, c.learning_modes,
                  co.name AS cohort_name, co.start_date AS cohort_start,
                  (SELECT MAX(le.created_at) FROM learning_events le WHERE le.user_id = e.student_id AND le.course_id = e.course_id) AS last_active
           $from WHERE $baseWhere AND {$tabSql[$tab]}";

if ($exporting) {
    $s = $pdo->prepare("$select ORDER BY e.enrolled_at DESC");
    $s->execute($p);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="enrolments-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Enrolment ID', 'Learner', 'Email', 'Admission no.', 'Course', 'Course type', 'Track', 'Cohort', 'Status', 'Progress %', 'Enrolled', 'Access ends', 'Amount paid', 'Last activity']);
    foreach ($s->fetchAll() as $r) {
        fputcsv($out, [$r['id'], $r['learner'], $r['email'], $r['admission_number'], $r['course_title'],
            $r['source'] === 'tutorial' ? 'Tutorial' : ($r['source'] === 'course' ? 'Free course' : 'Paid course'),
            mode_label($r['learning_mode']), $r['cohort_name'], $r['status'], (float)$r['progress_percent'], $r['enrolled_at'],
            $r['expires_at'] ?: 'Never', (float)$r['amount_paid'], $r['last_active']]);
    }
    ax_log('enrollment.export', 'enrollment', null, 'Exported enrolments CSV (' . EN_TABS[$tab] . ')');
    exit;
}

$per = 50;
$total = $counts[$tab];
$pages = max(1, (int)ceil($total / $per));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$s = $pdo->prepare("$select ORDER BY e.enrolled_at DESC, e.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$s->execute($p);
$rows = $s->fetchAll();

$stats = [
    'total'     => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM enrollments"),
    'active'    => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM enrollments WHERE status = 'active'"),
    'completed' => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM enrollments WHERE status = 'completed'"),
    'pending'   => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM enrollments WHERE status = 'pending'"),
    'paid'      => (float)ax_scalar($pdo, "SELECT COALESCE(SUM(amount_paid),0) FROM enrollments WHERE status IN ('active','completed')"),
    'expiring'  => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM enrollments WHERE status IN ('active','completed') AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 14 DAY)"),
    'week'      => (int)ax_scalar($pdo, "SELECT COUNT(DISTINCT CONCAT(le.user_id,'-',le.course_id)) FROM learning_events le JOIN enrollments e ON e.student_id = le.user_id AND e.course_id = le.course_id WHERE le.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
    'new30'     => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
];
$rate = ($stats['active'] + $stats['completed']) ? round($stats['completed'] / ($stats['active'] + $stats['completed']) * 100) : 0;

$courses = $pdo->query("SELECT id, title, source, learning_modes, status FROM courses WHERE status <> 'archived' ORDER BY title")->fetchAll();
$learners = $pdo->query("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name")->fetchAll();
$cohortMap = [];
foreach ($pdo->query("SELECT id, course_id, name, start_date, status, capacity FROM course_cohorts WHERE status <> 'archived' ORDER BY start_date")->fetchAll() as $co) {
    $cohortMap[$co['course_id']][] = ['id' => (int)$co['id'], 'label' => $co['name'] . ' – starts ' . date('M j, Y', strtotime($co['start_date'])) . ($co['status'] !== 'open' ? ' (' . $co['status'] . ')' : '')];
}
$modeMap = [];
foreach ($courses as $c) $modeMap[$c['id']] = course_modes($c);

function en_type_pill(?string $src): string {
    if ($src === 'tutorial') return '<span class="ax-pill violet">Tutorial</span>';
    if ($src === 'course') return '<span class="ax-pill ok">Free course</span>';
    return '<span class="ax-pill info">Paid course</span>';
}
function en_status_pill(array $r): string {
    $expired = $r['expires_at'] && strtotime($r['expires_at']) < time() && $r['status'] !== 'cancelled';
    $m = ['active' => ['ok', 'Active'], 'pending' => ['warn', 'Pending'], 'completed' => ['violet', 'Completed'], 'cancelled' => ['bad', 'Cancelled']][$r['status']] ?? ['', $r['status']];
    return '<span class="ax-pill ' . $m[0] . '">' . e($m[1]) . '</span>' . ($expired ? ' <span class="ax-pill bad" title="Access ended">Ended</span>' : '');
}
$qs = fn(array $over = []) => http_build_query(array_filter(array_merge(['tab' => $tab === 'all' ? '' : $tab, 'course' => $fCourse ?: '', 'mode' => $fMode, 'q' => $q], $over), fn($v) => $v !== '' && $v !== null));
require_once 'includes/sidebar.php';
?>
<style>
  .en-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
  .en-filters select { padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text-main); font: 600 .84rem 'Inter', sans-serif; max-width: 260px; min-width: 0; }
  .en-filters .ax-search { flex: 1 1 240px; }
  .en-course { font-weight: 700; color: var(--text-main); display: block; text-decoration: none; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .en-course:hover { color: var(--primary); }
  .en-prog { display: flex; align-items: center; gap: 8px; min-width: 120px; }
  .en-prog .ax-bar { flex: 1; }
  .en-prog b { font-size: .75rem; color: var(--text-main); min-width: 34px; text-align: right; }
  .en-pager { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 22px; border-top: 1px solid var(--border); font-size: .82rem; color: var(--text-muted); }
  .en-pager div { display: flex; gap: 6px; }
  .en-sec { border: 1px solid var(--border); border-radius: 14px; padding: 16px; margin-bottom: 14px; background: var(--surface); }
  .en-sec h4 { font: 800 .9rem 'Inter', sans-serif; color: var(--text-main); margin-bottom: 4px; display: flex; gap: 8px; align-items: center; }
  .en-sec p { font-size: .8rem; color: var(--text-muted); margin-bottom: 12px; }
  .en-sec.danger { border-color: color-mix(in srgb, var(--danger) 45%, transparent); background: color-mix(in srgb, var(--danger) 6%, transparent); }
  .en-quick { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
  .en-inline { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
  .en-inline .ax-field { margin-bottom: 0; flex: 1; min-width: 160px; }
  #en-manage, #en-new { width: min(640px, calc(100vw - 32px)); }
  main.main-content { min-width: 0; }
  @media (min-width: 761px) { .en-mlabel { display: none; } }
  @media (min-width: 761px) { #en-table td, #en-table th { padding-left: 12px; padding-right: 12px; } #en-table .en-prog { min-width: 100px; } }
  .ax-toolbar .ax-tabs { min-width: 0; max-width: 100%; }
  .ax-stats { grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); }
  .en-course { max-width: 210px; }
  @media (max-width: 760px) {
    .en-filters select { flex: 1 1 45%; max-width: none; }
    .en-course { max-width: 190px; }
    .en-prog { min-width: 150px; }
  }
</style>
<main class="main-content">
    <?php ax_header('Enrolments', '', 'Every learner on every course',
        '<a class="ax-btn ghost" href="enrollments?' . e($qs(['export' => 'csv', 'page' => ''])) . '"><i class="fas fa-file-csv"></i> Export CSV</a>'
        . '<button type="button" class="ax-btn" data-open="en-new"><i class="fas fa-user-plus"></i> Enrol a learner</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('user-check', number_format($stats['active']), 'Active enrolments', number_format($stats['total']) . ' in total · ' . number_format($stats['new30']) . ' new in 30 days', '') ?>
            <?= ax_stat('graduation-cap', number_format($stats['completed']), 'Completed', $rate . '% completion rate', 'green') ?>
            <?= ax_stat('hourglass-half', number_format($stats['pending']), 'Pending', 'Waiting for payment approval', 'amber') ?>
            <?= ax_stat('wallet', ax_money($stats['paid']), 'Paid by enrolled learners', 'Sum of amount paid', 'violet') ?>
            <?= ax_stat('bolt', number_format($stats['week']), 'Learning this week', 'Enrolments with activity in 7 days', 'orange') ?>
            <?= ax_stat('calendar-xmark', number_format($stats['expiring']), 'Access ending soon', 'Within the next 14 days', 'red') ?>
        </div>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Status">
                <?php foreach (EN_TABS as $k => $l): ?>
                    <a href="enrollments?<?= e($qs(['tab' => $k === 'all' ? '' : $k])) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($l) ?> <span class="n"><?= $counts[$k] ?></span></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <form class="en-filters" method="get" action="enrollments">
            <?php if ($tab !== 'all'): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
            <select name="course" aria-label="Course" onchange="this.form.submit()">
                <option value="">All courses</option>
                <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $fCourse === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
            </select>
            <select name="mode" aria-label="Track" onchange="this.form.submit()">
                <option value="">Both tracks</option>
                <?php foreach (SDA_MODES as $m): ?><option value="<?= $m ?>" <?= $fMode === $m ? 'selected' : '' ?>><?= e(mode_label($m)) ?></option><?php endforeach; ?>
            </select>
            <label class="ax-search"><i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Learner, email, admission no., course, cohort…" aria-label="Search enrolments"></label>
            <button class="ax-btn ghost"><i class="fas fa-filter"></i> Apply</button>
            <?php if ($fCourse || $fMode || $q !== ''): ?><a class="ax-btn ghost" href="enrollments<?= $tab !== 'all' ? '?tab=' . e($tab) : '' ?>"><i class="fas fa-xmark"></i> Clear</a><?php endif; ?>
        </form>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-user-check"></i> <?= e(EN_TABS[$tab]) ?> enrolments</h2><p><?= number_format($total) ?> found<?= $pages > 1 ? " · page $page of $pages" : '' ?>. Newest first.</p></div></div>
            <?php if (!$rows): ?>
                <div class="ax-empty"><i class="fas fa-user-graduate"></i><b>No enrolments here</b><?= ($fCourse || $fMode || $q !== '') ? 'Try clearing the filters.' : 'Enrolments appear when learners join a course, or when you enrol someone.' ?></div>
            <?php else: ?>
            <div class="ax-table-wrap">
                <table class="ax-table cards" id="en-table">
                    <thead><tr><th>Learner</th><th>Course</th><th>Track &amp; status</th><th>Progress</th><th>Enrolled / access ends</th><th>Paid / last activity</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $pct = (float)$r['progress_percent'];
                        $json = ['id' => (int)$r['id'], 'learner' => $r['learner'] ?? 'Deleted account', 'course' => $r['course_title'] ?? 'Course removed', 'course_id' => (int)$r['course_id'],
                                 'mode' => $r['learning_mode'], 'cohort_id' => (int)$r['cohort_id'], 'status' => $r['status'],
                                 'expires' => $r['expires_at'] ? substr($r['expires_at'], 0, 10) : '', 'progress' => $pct]; ?>
                        <tr>
                            <td data-label="Learner"><a class="ax-who" href="user-profile?id=<?= (int)$r['student_id'] ?>" style="text-decoration:none"><?= ax_avatar($r['learner'] ?? '?', $r['avatar'] ?? null) ?><div><b><?= e($r['learner'] ?? 'Deleted account') ?></b><small><?= e($r['email'] ?? '#' . $r['student_id']) ?></small></div></a></td>
                            <td data-label="Course"><div><a class="en-course" href="course_details?id=<?= (int)$r['course_id'] ?>" title="<?= e($r['course_title'] ?? '') ?>"><?= e($r['course_title'] ?? 'Course removed') ?></a><?= en_type_pill($r['source']) ?></div></td>
                            <td data-label="Track / status"><div><span class="ax-pill <?= $r['learning_mode'] === 'structured' ? 'info' : '' ?>"><i class="fas fa-<?= $r['learning_mode'] === 'structured' ? 'calendar-week' : 'person-walking' ?>"></i> <?= $r['learning_mode'] === 'structured' ? 'Structured' : 'Self-paced' ?></span>
                                <?php if ($r['cohort_name']): ?><div class="muted" style="margin-top:4px"><?= e($r['cohort_name']) ?></div><?php endif; ?><div style="margin-top:6px"><?= en_status_pill($r) ?></div></div></td>
                            <td data-label="Progress"><div class="en-prog"><div class="ax-bar <?= $pct >= 100 ? 'green' : '' ?>"><i style="width:<?= min(100, $pct) ?>%"></i></div><b><?= round($pct) ?>%</b></div></td>
                            <td data-label="Enrolled / ends" class="num"><div><?= e(ax_date($r['enrolled_at'])) ?><div class="muted"><?= $r['expires_at'] ? 'Ends ' . e(ax_date($r['expires_at'])) : 'Never ends' ?></div></div></td>
                            <td data-label="Paid / active" class="num"><div><span class="strong"><?= (float)$r['amount_paid'] > 0 ? e(ax_money($r['amount_paid'])) : '—' ?></span><div class="muted"><?= $r['last_active'] ? 'Active ' . e(ax_ago($r['last_active'])) : 'No activity yet' ?></div></div></td>
                            <td class="actions" data-label=""><button type="button" class="ax-btn ghost sm" data-manage="<?= e(json_encode($json)) ?>" title="Manage this enrolment" aria-label="Manage"><i class="fas fa-sliders"></i><span class="en-mlabel"> Manage</span></button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
            <div class="en-pager"><span>Showing <?= ($page - 1) * $per + 1 ?>–<?= min($total, $page * $per) ?> of <?= number_format($total) ?></span>
                <div><?php if ($page > 1): ?><a class="ax-btn ghost sm" href="enrollments?<?= e($qs(['page' => $page - 1])) ?>"><i class="fas fa-arrow-left"></i> Previous</a><?php endif; ?>
                <?php if ($page < $pages): ?><a class="ax-btn ghost sm" href="enrollments?<?= e($qs(['page' => $page + 1])) ?>">Next <i class="fas fa-arrow-right"></i></a><?php endif; ?></div></div>
            <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Enrol a learner -->
<dialog class="ax-modal" id="en-new" aria-labelledby="en-new-t">
    <form method="post" class="ax-form" data-confirm="Enrol this learner without a payment? They will be notified.">
        <div class="ax-panel-h"><div><h3 id="en-new-t"><i class="fas fa-user-plus"></i> Enrol a learner</h3><p>Gives access without a payment (scholarship, sponsor, staff…).</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="enrol">
            <div class="ax-field"><label for="en-learner">Learner</label>
                <input type="text" class="ax-input" id="en-lfilter" placeholder="Type to filter learners…" style="margin-bottom:8px" aria-label="Filter learners">
                <select id="en-learner" name="student_id" required size="5" style="height:auto">
                    <?php foreach ($learners as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['name'] . ' — ' . $l['email']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="ax-row">
                <div class="ax-field"><label for="en-course">Course</label><select id="en-course" name="course_id" required><option value="">Choose a course…</option>
                    <?php foreach ($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?><?= $c['status'] !== 'published' ? ' (' . e($c['status']) . ')' : '' ?></option><?php endforeach; ?></select></div>
                <div class="ax-field"><label for="en-mode">Track</label><select id="en-mode" name="mode"><?php foreach (SDA_MODES as $m): ?><option value="<?= $m ?>"><?= e(mode_label($m)) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="ax-field" data-cohort-wrap hidden><label for="en-cohort">Cohort</label><select id="en-cohort" name="cohort_id"></select><div class="hint">No cohort = a personal weekly schedule starting today.</div></div>
            <div class="ax-field"><label for="en-note">Note to the learner (optional)</label><input type="text" id="en-note" name="note" maxlength="200" class="ax-input" placeholder="e.g. Sponsored by the county youth fund"></div>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-user-plus"></i> Enrol and notify</button></div>
        </div>
    </form>
</dialog>

<!-- Manage one enrolment -->
<dialog class="ax-modal" id="en-manage" aria-labelledby="en-m-t">
    <div class="ax-panel-h"><div><h3 id="en-m-t"><i class="fas fa-sliders"></i> Manage enrolment</h3><p data-m-who></p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
    <div class="ax-panel-b">
        <form method="post" class="ax-form en-sec">
            <h4><i class="fas fa-route"></i> Track &amp; cohort</h4><p>Moves the learner to another track or cohort. Their progress stays.</p>
            <?= ax_csrf() ?><input type="hidden" name="action" value="track"><input type="hidden" name="id" data-m-id>
            <div class="en-inline">
                <div class="ax-field"><label for="m-mode">Track</label><select id="m-mode" name="mode"><?php foreach (SDA_MODES as $m): ?><option value="<?= $m ?>"><?= e(mode_label($m)) ?></option><?php endforeach; ?></select></div>
                <div class="ax-field" data-m-cohort-wrap><label for="m-cohort">Cohort</label><select id="m-cohort" name="cohort_id"></select></div>
                <button class="ax-btn sm"><i class="fas fa-floppy-disk"></i> Save track</button>
            </div>
        </form>

        <div class="en-sec">
            <h4><i class="fas fa-calendar-days"></i> Access</h4><p data-m-exp></p>
            <form method="post" class="en-quick"><?= ax_csrf() ?><input type="hidden" name="action" value="expiry"><input type="hidden" name="op" value="extend"><input type="hidden" name="id" data-m-id>
                <button class="ax-btn ghost sm" name="days" value="30">+30 days</button><button class="ax-btn ghost sm" name="days" value="90">+90 days</button><button class="ax-btn ghost sm" name="days" value="365">+1 year</button></form>
            <form method="post" class="ax-form en-inline"><?= ax_csrf() ?><input type="hidden" name="action" value="expiry"><input type="hidden" name="op" value="set"><input type="hidden" name="id" data-m-id>
                <div class="ax-field"><label for="m-date">Access ends on</label><input type="date" id="m-date" name="date" required></div>
                <button class="ax-btn ghost sm"><i class="fas fa-check"></i> Set date</button></form>
            <form method="post" style="margin-top:10px" data-m-remove data-confirm="Remove the expiry date? The learner keeps access for life."><?= ax_csrf() ?><input type="hidden" name="action" value="expiry"><input type="hidden" name="op" value="remove"><input type="hidden" name="id" data-m-id>
                <button class="ax-btn ghost sm"><i class="fas fa-infinity"></i> Remove expiry (lifetime access)</button></form>
        </div>

        <div class="en-sec">
            <h4><i class="fas fa-toggle-on"></i> Status</h4><p data-m-status></p>
            <form method="post" data-m-cancel data-confirm="Cancel this enrolment? The learner loses access and is notified." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="to" value="cancel"><input type="hidden" name="id" data-m-id>
                <button class="ax-btn danger sm"><i class="fas fa-ban"></i> Cancel enrolment</button></form>
            <form method="post" data-m-react data-confirm="Open access to this course for the learner again?"><?= ax_csrf() ?><input type="hidden" name="action" value="status"><input type="hidden" name="to" value="reactivate"><input type="hidden" name="id" data-m-id>
                <button class="ax-btn ok sm"><i class="fas fa-rotate-left"></i> <span data-m-react-l>Reactivate</span></button></form>
        </div>

        <form method="post" class="ax-form en-sec danger" data-confirm="Reset all lesson progress for this course? This cannot be undone. XP and badges are kept." data-danger>
            <h4><i class="fas fa-triangle-exclamation" style="color:var(--danger)"></i> Reset progress</h4><p>Deletes this learner's lesson progress for the course and sets progress to 0%. XP history and badges stay. Type <b>RESET</b> to confirm.</p>
            <?= ax_csrf() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" data-m-id>
            <div class="en-inline"><div class="ax-field"><label for="m-reset">Confirmation</label><input type="text" id="m-reset" name="confirm_text" required pattern="RESET" placeholder="RESET" autocomplete="off"></div>
                <button class="ax-btn warn sm"><i class="fas fa-eraser"></i> Reset progress</button></div>
        </form>
    </div>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function(){
  var COHORTS = <?= json_encode($cohortMap, JSON_HEX_TAG) ?>, MODES = <?= json_encode($modeMap) ?>;
  var LABEL = {self_paced: 'Self-paced', structured: 'Structured track'};
  function fillCohorts(sel, courseId, current){
    sel.innerHTML = '<option value="0">No cohort (personal schedule)</option>';
    (COHORTS[courseId] || []).forEach(function(c){ var o = new Option(c.label, c.id); if (c.id === current) o.selected = true; sel.add(o); });
  }
  function fillModes(sel, courseId, current){
    var ms = MODES[courseId] || ['self_paced','structured'];
    sel.innerHTML = ''; ms.forEach(function(m){ var o = new Option(LABEL[m], m); if (m === current) o.selected = true; sel.add(o); });
  }
  // New enrolment
  var nd = document.getElementById('en-new'), nc = nd.querySelector('#en-course'), nm = nd.querySelector('#en-mode'), nco = nd.querySelector('#en-cohort'), nw = nd.querySelector('[data-cohort-wrap]');
  function syncNew(){ if (nc.value) fillModes(nm, nc.value, nm.value); fillCohorts(nco, nc.value, 0); nw.hidden = nm.value !== 'structured'; }
  nc.addEventListener('change', syncNew); nm.addEventListener('change', function(){ nw.hidden = nm.value !== 'structured'; });
  var lf = nd.querySelector('#en-lfilter'), ls = nd.querySelector('#en-learner');
  lf.addEventListener('input', function(){ var q = lf.value.toLowerCase(); Array.prototype.forEach.call(ls.options, function(o){ o.hidden = q && o.text.toLowerCase().indexOf(q) < 0; }); });
  // Manage
  var md = document.getElementById('en-manage'), mm = md.querySelector('#m-mode'), mc = md.querySelector('#m-cohort'), mw = md.querySelector('[data-m-cohort-wrap]');
  mm.addEventListener('change', function(){ mw.hidden = mm.value !== 'structured'; });
  document.querySelectorAll('[data-manage]').forEach(function(b){
    b.addEventListener('click', function(){
      var d = JSON.parse(b.dataset.manage);
      md.querySelectorAll('[data-m-id]').forEach(function(i){ i.value = d.id; });
      md.querySelector('[data-m-who]').textContent = d.learner + ' · ' + d.course + ' · ' + Math.round(d.progress) + '% done';
      fillModes(mm, d.course_id, d.mode); fillCohorts(mc, d.course_id, d.cohort_id); mw.hidden = mm.value !== 'structured';
      md.querySelector('[data-m-exp]').textContent = d.expires ? 'Access ends on ' + new Date(d.expires + 'T12:00:00').toLocaleDateString(undefined, {month:'short', day:'numeric', year:'numeric'}) + '. Extending adds to that date (or to today if it has passed).' : 'Lifetime access – no expiry date. Set a date to limit access.';
      md.querySelector('#m-date').value = d.expires || '';
      md.querySelector('[data-m-remove]').hidden = !d.expires;
      var on = d.status === 'active' || d.status === 'completed';
      md.querySelector('[data-m-status]').textContent = 'Currently ' + d.status + '.' + (d.status === 'pending' ? ' Pending usually means a payment is waiting for approval.' : '');
      md.querySelector('[data-m-cancel]').hidden = d.status === 'cancelled';
      md.querySelector('[data-m-react]').hidden = on;
      md.querySelector('[data-m-react-l]').textContent = d.status === 'pending' ? 'Activate without payment' : 'Reactivate';
      md.querySelector('#m-reset').value = '';
      md.showModal();
    });
  });
})();
</script>
</body></html>
