<?php
/** Admin: Activity Log – what admins did (admin_activity, written by ax_log()). Filters, search, CSV export. */
$exporting = ($_GET['export'] ?? '') === 'csv';
if ($exporting) {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/includes/ui.php';
    requireRole('admin');
    $user = currentUser();
} else {
    $pageTitle = 'Activity Log';
    require_once 'includes/header.php';
}

/* ── Filters ────────────────────────────────────────────────────────── */
$fAdmin = (int)($_GET['admin'] ?? 0);
$fType = preg_match('/^[a-z_]{1,40}$/', $_GET['type'] ?? '') ? $_GET['type'] : '';
$fFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$fTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

$w = []; $p = [];
if ($fAdmin) { $w[] = 'a.admin_id = ?'; $p[] = $fAdmin; }
if ($fType !== '') { $w[] = "(a.action = ? OR a.action LIKE ?)"; $p[] = $fType; $p[] = $fType . '.%'; }
if ($fFrom) { $w[] = 'a.created_at >= ?'; $p[] = $fFrom . ' 00:00:00'; }
if ($fTo) { $w[] = 'a.created_at <= ?'; $p[] = $fTo . ' 23:59:59'; }
if ($q !== '') { $w[] = '(a.summary LIKE ? OR a.action LIKE ? OR a.ip LIKE ? OR u.name LIKE ?)'; array_push($p, ...array_fill(0, 4, '%' . $q . '%')); }
$where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
$from = "FROM admin_activity a LEFT JOIN users u ON u.id = a.admin_id $where";

if ($exporting) {
    $s = $pdo->prepare("SELECT a.*, u.name AS admin_name, u.email AS admin_email $from ORDER BY a.created_at DESC, a.id DESC");
    $s->execute($p);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin-activity-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Time', 'Admin', 'Admin email', 'Action', 'Entity', 'Entity ID', 'Summary', 'IP']);
    foreach ($s->fetchAll() as $r) {
        fputcsv($out, [$r['id'], $r['created_at'], $r['admin_name'] ?? ('#' . $r['admin_id']), $r['admin_email'], $r['action'], $r['entity'], $r['entity_id'], $r['summary'], $r['ip']]);
    }
    exit;
}

$per = 50;
$total = (int)ax_scalar($pdo, "SELECT COUNT(*) $from", $p);
$pages = max(1, (int)ceil($total / $per));
$page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
$s = $pdo->prepare("SELECT a.*, u.name AS admin_name, u.avatar $from ORDER BY a.created_at DESC, a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$s->execute($p);
$rows = $s->fetchAll();

$admins = $pdo->query("SELECT DISTINCT u.id, u.name FROM admin_activity a JOIN users u ON u.id = a.admin_id ORDER BY u.name")->fetchAll();
$types = $pdo->query("SELECT SUBSTRING_INDEX(action, '.', 1) AS t, COUNT(*) AS n FROM admin_activity GROUP BY t ORDER BY t")->fetchAll();

$stats = [
    'today' => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM admin_activity WHERE created_at >= CURDATE()"),
    'week'  => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM admin_activity WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
    'all'   => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM admin_activity"),
    'admins'=> (int)ax_scalar($pdo, "SELECT COUNT(DISTINCT admin_id) FROM admin_activity WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
];
$top = $pdo->query("SELECT SUBSTRING_INDEX(action, '.', 1) AS t, COUNT(*) AS n FROM admin_activity WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY t ORDER BY n DESC LIMIT 1")->fetch();

/** Friendly label for an action type prefix. */
function act_type_label(string $t): string {
    $map = ['enrollment' => 'Enrolments', 'coupon' => 'Coupons', 'certificate' => 'Certificates', 'payment' => 'Payments', 'user' => 'Users',
            'course' => 'Courses', 'badge' => 'Badges', 'review' => 'Reviews', 'setting' => 'Settings', 'settings' => 'Settings', 'path' => 'Learning paths',
            'announcement' => 'Announcements', 'scholarship' => 'Scholarships', 'ticket' => 'Support tickets', 'category' => 'Categories', 'payroll' => 'Payroll'];
    return $map[$t] ?? ucfirst(str_replace('_', ' ', $t));
}
/** Colour + icon for an action verb. */
function act_pill(string $action): string {
    $verb = str_contains($action, '.') ? substr(strrchr($action, '.'), 1) : $action;
    $cls = match (true) {
        (bool)preg_match('/delete|remove|revoke|reject|cancel|suspend|reset|deactivate/', $verb) => 'bad',
        (bool)preg_match('/create|add|approve|activate|award|issue|enrol|waive|unlock/', $verb) => 'ok',
        (bool)preg_match('/export|view|login/', $verb) => '',
        default => 'info',
    };
    return '<span class="ax-pill ' . $cls . '">' . e($action) . '</span>';
}
/** Link to the thing an entry is about. */
function act_link(string $entity, ?int $id): ?string {
    if ($entity === '') return null;
    return match ($entity) {
        'user', 'student', 'tutor' => $id ? "user-profile?id=$id" : 'users',
        'course' => $id ? "course_details?id=$id" : 'courses',
        'payment' => $id ? "payments?q=$id" : 'payments',
        'certificate' => 'certificates',
        'coupon' => $id ? "coupons?coupon=$id#history" : 'coupons',
        'enrollment' => 'enrollments',
        'badge' => 'badges', 'review' => 'reviews', 'setting', 'settings' => 'settings',
        'scholarship' => 'scholarships', 'ticket' => 'tickets', 'announcement' => 'announcements', 'category' => 'categories',
        default => null,
    };
}
$qs = fn(array $over = []) => http_build_query(array_filter(array_merge(['admin' => $fAdmin ?: '', 'type' => $fType, 'from' => $fFrom, 'to' => $fTo, 'q' => $q], $over), fn($v) => $v !== '' && $v !== null));
$filtered = $fAdmin || $fType !== '' || $fFrom || $fTo || $q !== '';
require_once 'includes/sidebar.php';
?>
<style>
  .al-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 18px; }
  .al-filters label.f { display: flex; flex-direction: column; gap: 5px; font-size: .66rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--text-muted); min-width: 0; }
  .al-filters select, .al-filters input[type=date] { padding: 9px 12px; border-radius: 12px; border: 1px solid var(--border); background: var(--surface); color: var(--text-main); font: 600 .84rem 'Inter', sans-serif; min-width: 0; color-scheme: light dark; }
  .al-filters .ax-search { flex: 1 1 220px; }
  .al-sum { color: var(--text-main); font-weight: 600; line-height: 1.45; max-width: 460px; overflow-wrap: anywhere; }
  .al-ip { font: 600 .74rem 'JetBrains Mono', ui-monospace, monospace; color: var(--text-muted); }
  .al-pager { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 14px 22px; border-top: 1px solid var(--border); font-size: .82rem; color: var(--text-muted); }
  .al-pager div { display: flex; gap: 6px; }
  main.main-content { min-width: 0; }
  @media (max-width: 760px) {
    .al-filters label.f { flex: 1 1 45%; }
    .al-sum { max-width: none; text-align: right; }
  }
</style>
<main class="main-content">
    <?php ax_header('Activity', 'Log', 'What admins changed, and when',
        '<a class="ax-btn ghost" href="activity?' . e($qs(['export' => 'csv'])) . '"><i class="fas fa-file-csv"></i> Export CSV</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('clock', number_format($stats['today']), 'Actions today', '', '') ?>
            <?= ax_stat('calendar-week', number_format($stats['week']), 'Last 7 days', number_format($stats['all']) . ' recorded in total', 'green') ?>
            <?= ax_stat('user-shield', number_format($stats['admins']), 'Active admins', 'Made changes in 30 days', 'violet') ?>
            <?= ax_stat('chart-simple', $top ? act_type_label($top['t']) : '—', 'Busiest area', $top ? number_format((int)$top['n']) . ' actions in 30 days' : 'No actions yet', 'orange') ?>
        </div>

        <form class="al-filters" method="get" action="activity">
            <label class="f">Admin<select name="admin" onchange="this.form.submit()"><option value="">Everyone</option>
                <?php foreach ($admins as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $fAdmin === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select></label>
            <label class="f">Area<select name="type" onchange="this.form.submit()"><option value="">All areas</option>
                <?php foreach ($types as $t): ?><option value="<?= e($t['t']) ?>" <?= $fType === $t['t'] ? 'selected' : '' ?>><?= e(act_type_label($t['t'])) ?> (<?= (int)$t['n'] ?>)</option><?php endforeach; ?></select></label>
            <label class="f">From<input type="date" name="from" value="<?= e($fFrom) ?>"></label>
            <label class="f">To<input type="date" name="to" value="<?= e($fTo) ?>"></label>
            <label class="ax-search"><i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search summary, action, IP…" aria-label="Search activity"></label>
            <button class="ax-btn ghost"><i class="fas fa-filter"></i> Apply</button>
            <?php if ($filtered): ?><a class="ax-btn ghost" href="activity"><i class="fas fa-xmark"></i> Clear</a><?php endif; ?>
        </form>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-clock-rotate-left"></i> Activity</h2><p><?= number_format($total) ?> entr<?= $total === 1 ? 'y' : 'ies' ?><?= $filtered ? ' match your filters' : '' ?><?= $pages > 1 ? " · page $page of $pages" : '' ?>. Newest first.</p></div></div>
            <?php if (!$rows): ?>
                <div class="ax-empty"><i class="fas fa-clipboard-list"></i><b><?= $filtered ? 'Nothing matches these filters' : 'No admin activity yet' ?></b><?= $filtered ? 'Try a wider date range or clear the filters.' : 'Changes made in the admin (enrolments, coupons, certificates, payments…) are recorded here.' ?></div>
            <?php else: ?>
            <div class="ax-table-wrap">
                <table class="ax-table cards">
                    <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Summary</th><th>About</th><th>IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): $link = act_link((string)$r['entity'], $r['entity_id'] !== null ? (int)$r['entity_id'] : null); ?>
                        <tr>
                            <td data-label="When" class="num"><span title="<?= e(ax_date($r['created_at'], 'M j, Y g:i:s a')) ?>"><?= e(ax_date($r['created_at'], 'M j, g:i a')) ?></span><div class="muted"><?= e(ax_ago($r['created_at'])) ?></div></td>
                            <td data-label="Admin"><div class="ax-who"><?= ax_avatar($r['admin_name'] ?? '?', $r['avatar'] ?? null) ?><div><b><?= e($r['admin_name'] ?? 'Deleted admin #' . $r['admin_id']) ?></b></div></div></td>
                            <td data-label="Action"><?= act_pill((string)$r['action']) ?></td>
                            <td data-label="Summary"><div class="al-sum"><?= e($r['summary'] ?: '—') ?></div></td>
                            <td data-label="About"><?php if ($r['entity'] !== ''): ?><?= $link ? '<a href="' . e($link) . '">' : '' ?><?= e(ucfirst($r['entity'])) ?><?= $r['entity_id'] !== null ? ' #' . (int)$r['entity_id'] : '' ?><?= $link ? '</a>' : '' ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                            <td data-label="IP"><span class="al-ip"><?= e($r['ip'] ?? '—') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1): ?>
            <div class="al-pager"><span>Showing <?= ($page - 1) * $per + 1 ?>–<?= min($total, $page * $per) ?> of <?= number_format($total) ?></span>
                <div><?php if ($page > 1): ?><a class="ax-btn ghost sm" href="activity?<?= e($qs(['page' => $page - 1])) ?>"><i class="fas fa-arrow-left"></i> Newer</a><?php endif; ?>
                <?php if ($page < $pages): ?><a class="ax-btn ghost sm" href="activity?<?= e($qs(['page' => $page + 1])) ?>">Older <i class="fas fa-arrow-right"></i></a><?php endif; ?></div></div>
            <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
