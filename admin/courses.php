<?php
/** Admin: every course on the platform – paid tutor courses, free skills courses and open tutorials. */
$pageTitle = 'Courses';
require_once 'includes/header.php';

const AX_TYPES = ['all' => 'All', 'paid' => 'Paid courses', 'free' => 'Free skills courses', 'tutorial' => 'Tutorials'];
const AX_STATUSES = ['all' => 'All', 'published' => 'Live', 'pending' => 'Pending review', 'draft' => 'Draft', 'archived' => 'Archived'];

function axc_type_sql(string $type): string {
    return match ($type) {
        'paid' => " AND c.source IS NULL",
        'free' => " AND c.source = 'course'",
        'tutorial' => " AND c.source = 'tutorial'",
        default => '',
    };
}

function axc_notify_owner(PDO $pdo, int $courseId, string $title, string $msg): void {
    $st = $pdo->prepare("SELECT u.id FROM courses c JOIN users u ON u.id = c.tutor_id WHERE c.id = ? AND u.role = 'tutor'");
    $st->execute([$courseId]);
    if ($uid = $st->fetchColumn()) {
        try {
            $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, 'tutor', ?)")->execute([$title, $msg, $uid]);
        } catch (Throwable $e) { error_log($e->getMessage()); }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $row = null;
    if ($id) {
        $st = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch() ?: null;
        if (!$row) { ax_flash('err', 'That course no longer exists.'); ax_back(); }
    }

    if ($act === 'approve' && $row) {
        $pdo->prepare("UPDATE courses SET status = 'published' WHERE id = ?")->execute([$id]);
        axc_notify_owner($pdo, $id, 'Course approved', "Your course \"{$row['title']}\" has been approved and is now live.");
        ax_flash('ok', "\"{$row['title']}\" is approved and now live."); ax_back();
    }
    if ($act === 'reject' && $row) {
        $reason = trim($_POST['reason'] ?? '');
        $pdo->prepare("UPDATE courses SET status = 'draft' WHERE id = ?")->execute([$id]);
        axc_notify_owner($pdo, $id, 'Course sent back for changes', "Your course \"{$row['title']}\" was not approved yet and is back in your drafts." . ($reason !== '' ? " Reason: $reason" : ''));
        ax_flash('ok', "\"{$row['title']}\" was sent back to the owner as a draft."); ax_back();
    }
    if ($act === 'archive' && $row) {
        $pdo->prepare("UPDATE courses SET status = 'archived' WHERE id = ?")->execute([$id]);
        ax_flash('ok', "\"{$row['title']}\" is archived and hidden from the catalogue. Enrolled learners keep their records."); ax_back();
    }
    if ($act === 'restore' && $row) {
        $pdo->prepare("UPDATE courses SET status = 'published' WHERE id = ? AND status = 'archived'")->execute([$id]);
        ax_flash('ok', "\"{$row['title']}\" is restored and live again."); ax_back();
    }
    if ($act === 'save') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') { ax_flash('err', 'Give the course a title.'); ax_back(); }
        $level  = in_array($_POST['level'] ?? '', ['beginner', 'intermediate', 'advanced'], true) ? $_POST['level'] : 'beginner';
        $status = in_array($_POST['status'] ?? '', ['draft', 'pending', 'published', 'archived'], true) ? $_POST['status'] : 'draft';
        $modes  = in_array($_POST['learning_modes'] ?? '', ['self_paced', 'structured', 'both'], true) ? $_POST['learning_modes'] : 'both';
        $catId  = (int)($_POST['category_id'] ?? 0) ?: null;
        $tutor  = (int)($_POST['tutor_id'] ?? 0);
        $price  = max(0, (float)($_POST['price'] ?? 0));
        $feeRaw = trim((string)($_POST['certificate_fee'] ?? ''));
        $fee    = $feeRaw === '' ? null : max(0, (float)$feeRaw);
        $lines  = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($_POST['outcomes'] ?? ''))), 'strlen'));
        $outcomes = $lines ? json_encode($lines, JSON_UNESCAPED_UNICODE) : null;
        $featured = !empty($_POST['is_featured']) ? 1 : 0;
        $desc   = trim($_POST['description'] ?? '');
        if (!$tutor) { ax_flash('err', 'Choose who owns the course.'); ax_back(); }

        $thumb = null;
        if (!empty($_FILES['thumbnail']['name'])) {
            $f = $_FILES['thumbnail'];
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $ok = $f['error'] === UPLOAD_ERR_OK && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)
                  && $f['size'] <= 3 * 1024 * 1024 && ($info = @getimagesize($f['tmp_name']))
                  && in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true);
            if (!$ok) { ax_flash('err', 'Cover image must be a JPG, PNG or WebP image under 3 MB.'); ax_back(); }
            $dir = __DIR__ . '/../uploads/courses/';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $thumb = 'course_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], $dir . $thumb)) { ax_flash('err', 'The cover image could not be saved.'); ax_back(); }
        }

        $fields = ['title' => $title, 'description' => $desc, 'price' => $price, 'level' => $level, 'category_id' => $catId,
                   'tutor_id' => $tutor, 'status' => $status, 'learning_modes' => $modes, 'is_featured' => $featured,
                   'certificate_fee' => $fee, 'outcomes' => $outcomes];
        if ($thumb) $fields['thumbnail'] = $thumb;
        if ($row) {
            // The slug stays as it is: library courses are matched to their content folder by slug,
            // and learning paths and reader links point at it.
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            $pdo->prepare("UPDATE courses SET $set WHERE id = ?")->execute([...array_values($fields), $id]);
            ax_flash('ok', "\"$title\" saved.");
        } else {
            $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') ?: 'course';
            $slug = $base; $n = 2;
            $chk = $pdo->prepare("SELECT 1 FROM courses WHERE slug = ?");
            while (true) { $chk->execute([$slug]); if (!$chk->fetchColumn()) break; $slug = $base . '-' . $n++; }
            $fields['slug'] = $slug;
            $cols = implode(', ', array_keys($fields));
            $pdo->prepare("INSERT INTO courses ($cols) VALUES (" . implode(', ', array_fill(0, count($fields), '?')) . ")")->execute(array_values($fields));
            ax_flash('ok', "\"$title\" created. Add its lessons from the tutor studio, then set pricing.");
        }
        ax_back();
    }
    ax_flash('err', 'Unknown action.'); ax_back();
}

/* ── Filters ── */
$type   = array_key_exists($_GET['type'] ?? '', AX_TYPES) ? $_GET['type'] : 'all';
$status = array_key_exists($_GET['status'] ?? '', AX_STATUSES) ? $_GET['status'] : 'all';
$q      = trim($_GET['q'] ?? ($_GET['search'] ?? ''));
$cat    = (int)($_GET['cat'] ?? 0);

$where = " WHERE 1=1"; $p = [];
if ($q !== '') { $where .= " AND (c.title LIKE ? OR u.name LIKE ? OR c.slug LIKE ?)"; array_push($p, "%$q%", "%$q%", "%$q%"); }
if ($cat) { $where .= " AND c.category_id = ?"; $p[] = $cat; }

// Tab counts: type counts ignore the type filter; status counts respect it.
$typeCounts = array_fill_keys(array_keys(AX_TYPES), 0);
$statusCounts = array_fill_keys(array_keys(AX_STATUSES), 0);
$st = $pdo->prepare("SELECT c.source, c.status FROM courses c LEFT JOIN users u ON u.id = c.tutor_id $where");
$st->execute($p);
foreach ($st->fetchAll() as $r) {
    $t = $r['source'] === 'course' ? 'free' : ($r['source'] === 'tutorial' ? 'tutorial' : 'paid');
    if ($status === 'all' || $r['status'] === $status) { $typeCounts['all']++; $typeCounts[$t]++; }
    if ($type === 'all' || $type === $t) { $statusCounts['all']++; if (isset($statusCounts[$r['status']])) $statusCounts[$r['status']]++; }
}

$sql = "SELECT c.*, u.name AS owner_name, u.role AS owner_role, cat.name AS category_name, cat.icon AS cat_icon, cat.color AS cat_color
        FROM courses c LEFT JOIN users u ON u.id = c.tutor_id LEFT JOIN categories cat ON cat.id = c.category_id
        $where" . axc_type_sql($type) . ($status !== 'all' ? " AND c.status = ?" : '') . "
        ORDER BY FIELD(c.status, 'pending', 'published', 'draft', 'archived'), (c.source IS NULL) DESC, c.source, c.tutorial_order, c.created_at DESC";
if ($status !== 'all') $p[] = $status;
$st = $pdo->prepare($sql);
$st->execute($p);
$courses = $st->fetchAll();

/* ── Per-course numbers (grouped once, not per row) ── */
$map = fn(string $sql) => array_column($pdo->query($sql)->fetchAll(), 'v', 'k');
$enrolled = $map("SELECT course_id k, COUNT(*) v FROM enrollments GROUP BY course_id");
$lessonsN = $map("SELECT course_id k, COUNT(*) v FROM lessons GROUP BY course_id");
$revenue  = $map("SELECT course_id k, SUM(amount) v FROM payments WHERE status = 'verified' AND (payment_type IS NULL OR payment_type <> 'certificate') GROUP BY course_id");
$ratings  = [];
foreach ($pdo->query("SELECT course_id, AVG(rating) a, COUNT(*) n FROM course_ratings GROUP BY course_id")->fetchAll() as $r) $ratings[$r['course_id']] = $r;
$completed = [];
foreach ($pdo->query("SELECT course_id, student_id FROM enrollments WHERE progress_percent >= 100 OR status = 'completed'
                      UNION SELECT course_id, student_id FROM certificates WHERE certificate_type = 'course' AND course_id IS NOT NULL AND status <> 'revoked'")->fetchAll() as $r) {
    $completed[$r['course_id']] = ($completed[$r['course_id']] ?? 0) + 1;
}

/* ── Header stats ── */
$byType = $pdo->query("SELECT SUM(source IS NULL) paid, SUM(source = 'course') free, SUM(source = 'tutorial') tut, COUNT(*) total,
                              SUM(status = 'pending') pending, SUM(status = 'published') live FROM courses")->fetch();
$totEnrol = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM enrollments");
$totDone  = array_sum($completed);
$rateAll  = $pdo->query("SELECT AVG(rating) a, COUNT(*) n FROM course_ratings")->fetch();
$salesRev = (float)ax_scalar($pdo, "SELECT SUM(amount) FROM payments WHERE status = 'verified' AND (payment_type IS NULL OR payment_type <> 'certificate')");

// New enrolments, last 8 weeks (Monday-based)
$weekStart = strtotime('monday this week', time()) - 7 * 7 * 86400;
$weeks = [];
for ($i = 0; $i < 8; $i++) $weeks[date('Y-m-d', $weekStart + $i * 7 * 86400)] = 0;
$st = $pdo->prepare("SELECT enrolled_at FROM enrollments WHERE enrolled_at >= ?");
$st->execute([date('Y-m-d', $weekStart)]);
foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $d) {
    $k = date('Y-m-d', $weekStart + intdiv(strtotime($d) - $weekStart, 7 * 86400) * 7 * 86400);
    if (isset($weeks[$k])) $weeks[$k]++;
}
$weekMax = max(1, max($weeks));
$weekTotal = array_sum($weeks);

$pending = $pdo->query("SELECT c.id, c.title, c.created_at, u.name owner_name FROM courses c LEFT JOIN users u ON u.id = c.tutor_id
                        WHERE c.status = 'pending' ORDER BY c.created_at LIMIT 6")->fetchAll();
$owners = $pdo->query("SELECT id, name, role FROM users WHERE role IN ('tutor', 'admin') ORDER BY role DESC, name")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$defaultFee = (float)setting('certificate_fee');

function axc_icon(?string $i): string {
    $i = trim((string)$i);
    if (!str_contains($i, 'fa-')) return 'fas fa-book-open';
    return str_contains($i, ' ') ? $i : 'fas ' . $i;
}
function axc_type_of(array $c): string { return $c['source'] === 'course' ? 'free' : ($c['source'] === 'tutorial' ? 'tutorial' : 'paid'); }
function axc_qs(array $over): string {
    global $type, $status, $q, $cat;
    $a = array_merge(['type' => $type, 'status' => $status, 'q' => $q, 'cat' => $cat ?: ''], $over);
    $a = array_filter($a, fn($v) => $v !== '' && $v !== 'all' && $v !== 0);
    return 'courses' . ($a ? '?' . http_build_query($a) : '');
}

require_once 'includes/sidebar.php';
?>
<style>
.main-content { min-width: 0; }
.ax-toolbar > .ax-tabs { min-width: 0; max-width: 100%; }
.axc-stats { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
.axc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 330px), 1fr)); gap: 20px; }
.axc-card { background: var(--surface); border: 1px solid var(--border); border-radius: 18px; overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--shadow-sm); min-width: 0; }
.axc-card.is-pending { border-color: var(--warning); box-shadow: 0 0 0 3px color-mix(in srgb, var(--warning) 22%, transparent); }
.axc-thumb { position: relative; height: 128px; background: var(--surface-2); display: flex; align-items: center; justify-content: center; }
.axc-thumb img { width: 100%; height: 100%; object-fit: cover; }
.axc-thumb .fallback { font-size: 2.4rem; color: rgba(255,255,255,.9); }
.axc-thumb .tags { position: absolute; top: 12px; left: 12px; right: 12px; display: flex; justify-content: space-between; gap: 6px; flex-wrap: wrap; }
.axc-thumb .tags .ax-pill { background: rgba(15,23,42,.8); color: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.2); backdrop-filter: blur(4px); }
.axc-thumb .tags .ax-pill::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--dot, #94a3b8); }
.axc-thumb .tags .ax-pill.ok { --dot: #22c55e; } .axc-thumb .tags .ax-pill.warn { --dot: #f59e0b; } .axc-thumb .tags .ax-pill.info { --dot: #38bdf8; } .axc-thumb .tags .ax-pill.violet { --dot: #a78bfa; } .axc-thumb .tags .ax-pill.dark { --dot: #64748b; }
.axc-thumb .feat { position: absolute; bottom: 10px; right: 12px; background: rgba(15,23,42,.75); color: #fde68a; font-size: .66rem; font-weight: 800; padding: 4px 9px; border-radius: 20px; }
.axc-b { padding: 16px 18px; flex: 1; display: flex; flex-direction: column; gap: 10px; }
.axc-b h3 { font: 800 1rem 'Poppins', sans-serif; color: var(--text-main); line-height: 1.35; margin: 0; }
.axc-meta { font-size: .76rem; color: var(--text-muted); display: flex; flex-wrap: wrap; gap: 4px 12px; }
.axc-meta i { width: 12px; text-align: center; margin-right: 3px; }
.axc-nums { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; border-top: 1px solid var(--border-light); border-bottom: 1px solid var(--border-light); padding: 10px 0; margin-top: auto; }
.axc-nums div { text-align: center; min-width: 0; }
.axc-nums b { display: block; font: 800 .95rem 'Poppins', sans-serif; color: var(--text-main); }
.axc-nums b .fa-star { color: #f59e0b; font-size: .7rem; }
.axc-nums small { font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .6px; color: var(--text-muted); }
.axc-money { display: flex; justify-content: space-between; gap: 10px; font-size: .8rem; flex-wrap: wrap; }
.axc-money span { color: var(--text-muted); } .axc-money b { color: var(--text-main); }
.axc-f { padding: 12px 18px; background: var(--surface-2); border-top: 1px solid var(--border-light); display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.axc-f form { display: inline; }
.axc-f .sp { flex: 1; }
.axc-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.axc-filters select { padding: 10px 12px; border-radius: 12px; border: 1px solid var(--border); font: 600 .84rem 'Inter', sans-serif; background: var(--surface); color: var(--text-main); max-width: 100%; }
.axc-pend li small { display: block; color: var(--text-muted); font-size: .74rem; }
@media (max-width: 640px) { .axc-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } .axc-filters { width: 100%; } .axc-filters select { flex: 1; } }
</style>
<main class="main-content">
    <?php ax_header('Courses', '', 'Catalogue, approvals & performance',
        '<button class="ax-btn" type="button" data-course-new><i class="fas fa-plus"></i> New course</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats axc-stats">
            <?= ax_stat('book', number_format((int)$byType['total']), 'Courses', (int)$byType['paid'] . ' paid · ' . (int)$byType['free'] . ' free · ' . (int)$byType['tut'] . ' tutorials') ?>
            <?= ax_stat('hourglass-half', number_format((int)$byType['pending']), 'Pending review', (int)$byType['live'] . ' live now', 'amber') ?>
            <?= ax_stat('user-graduate', number_format($totEnrol), 'Enrolments', number_format($totDone) . ' completed', 'green') ?>
            <?= ax_stat('star', $rateAll['n'] ? number_format((float)$rateAll['a'], 1) : '—', 'Average rating', number_format((int)$rateAll['n']) . ' review' . ((int)$rateAll['n'] === 1 ? '' : 's'), 'orange') ?>
            <?= ax_stat('sack-dollar', ax_money($salesRev), 'Course sales', 'Verified, excl. certificate fees', 'violet') ?>
        </div>

        <div class="ax-grid" style="margin-bottom:24px">
            <section class="ax-panel ax-span-8">
                <div class="ax-panel-h"><div><h2><i class="fas fa-chart-column"></i> New enrolments</h2><p>Per week, last 8 weeks · <?= number_format($weekTotal) ?> in total</p></div></div>
                <div class="ax-panel-b">
                    <div class="ax-chart">
                        <?php foreach ($weeks as $wk => $n): ?>
                            <div class="col"><em><?= $n ?></em><i style="height:<?= round($n / $weekMax * 100) ?>%"></i><span><?= date('M j', strtotime($wk)) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <section class="ax-panel ax-span-4">
                <div class="ax-panel-h"><div><h2><i class="fas fa-clipboard-check"></i> Waiting for review</h2><p>Tutor courses submitted for approval</p></div>
                    <?php if ($pending): ?><a class="ax-btn ghost sm" href="<?= e(axc_qs(['status' => 'pending', 'type' => 'all'])) ?>">View all</a><?php endif; ?></div>
                <div class="ax-panel-b">
                    <?php if (!$pending): ?>
                        <div class="ax-empty" style="padding:24px 10px"><i class="fas fa-circle-check"></i><b>All caught up</b>No courses are waiting for approval.</div>
                    <?php else: ?>
                        <ul class="ax-list axc-pend">
                            <?php foreach ($pending as $pc): ?>
                                <li><span class="ax-icon-badge" style="background:color-mix(in srgb, var(--warning) 16%, transparent);color:var(--warning)"><i class="fas fa-hourglass-half"></i></span>
                                    <div class="grow"><a class="strong" style="color:var(--text-main);font-weight:700;text-decoration:none" href="course_details?id=<?= (int)$pc['id'] ?>"><?= e($pc['title']) ?></a>
                                    <small><?= e($pc['owner_name'] ?? '—') ?> · <?= ax_ago($pc['created_at']) ?></small></div></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Course type">
                <?php foreach (AX_TYPES as $k => $label): ?>
                    <a href="<?= e(axc_qs(['type' => $k])) ?>" class="<?= $type === $k ? 'on' : '' ?>"><?= e($label) ?> <span class="n"><?= $typeCounts[$k] ?></span></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Status">
                <?php foreach (AX_STATUSES as $k => $label): ?>
                    <a href="<?= e(axc_qs(['status' => $k])) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($label) ?> <span class="n"><?= $statusCounts[$k] ?></span></a>
                <?php endforeach; ?>
            </nav>
            <form class="axc-filters" method="get" action="courses">
                <input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="status" value="<?= e($status) ?>">
                <select name="cat" onchange="this.form.submit()" aria-label="Category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
                <div class="ax-search"><i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title, owner or slug…" aria-label="Search courses"></div>
            </form>
        </div>

        <?php if (!$courses): ?>
            <div class="ax-panel"><div class="ax-empty"><i class="fas fa-book"></i><b>No courses match</b>Try another tab, category or search.</div></div>
        <?php else: ?>
        <div class="axc-grid">
            <?php foreach ($courses as $c):
                $cid = (int)$c['id'];
                $t = axc_type_of($c);
                $rt = $ratings[$cid] ?? null;
                $fee = certificate_fee_for($c);
                if ((float)$c['price'] <= 0 && ($c['structured_price'] === null || (float)$c['structured_price'] <= 0)) {
                    $priceTxt = 'Free';
                } else {
                    $priceTxt = ax_money(course_from_price($c)) . (count(course_modes($c)) > 1 && course_price($c, 'structured') != course_price($c, 'self_paced') ? ' – ' . ax_money(max(course_price($c, 'self_paced'), course_price($c, 'structured'))) : '');
                    if (sale_active($c)) $priceTxt .= ' (sale)';
                }
                $statusPill = ['published' => ['ok', 'Live'], 'pending' => ['warn', 'Pending'], 'draft' => ['', 'Draft'], 'archived' => ['dark', 'Archived']][$c['status']] ?? ['', $c['status']];
                $typePill = ['paid' => ['info', 'Paid course'], 'free' => ['violet', 'Free skills'], 'tutorial' => ['ok', 'Tutorial']][$t];
                $edit = ['id' => $cid, 'title' => $c['title'], 'description' => $c['description'], 'price' => (float)$c['price'], 'level' => $c['level'],
                         'category_id' => $c['category_id'], 'tutor_id' => $c['tutor_id'], 'owner_missing' => $c['owner_name'] === null, 'status' => $c['status'],
                         'learning_modes' => $c['learning_modes'] ?: 'both', 'is_featured' => (int)$c['is_featured'],
                         'certificate_fee' => $c['certificate_fee'] === null ? '' : (float)$c['certificate_fee'],
                         'outcomes' => implode("\n", (array)(json_decode((string)$c['outcomes'], true) ?: [])), 'library' => $t !== 'paid'];
            ?>
            <article class="axc-card<?= $c['status'] === 'pending' ? ' is-pending' : '' ?>">
                <div class="axc-thumb" style="<?= $c['thumbnail'] ? '' : 'background:linear-gradient(135deg,' . e($c['cat_color'] ?: '#0080ff') . ',#0f172a)' ?>">
                    <?php if ($c['thumbnail']): ?><img src="../uploads/courses/<?= e($c['thumbnail']) ?>" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'fas fa-image fallback'}))">
                    <?php else: ?><i class="<?= e(axc_icon($c['cat_icon'])) ?> fallback"></i><?php endif; ?>
                    <div class="tags"><span class="ax-pill <?= $typePill[0] ?>"><?= $typePill[1] ?></span><span class="ax-pill <?= $statusPill[0] ?>"><?= e($statusPill[1]) ?></span></div>
                    <?php if ($c['is_featured']): ?><span class="feat"><i class="fas fa-star"></i> Featured</span><?php endif; ?>
                </div>
                <div class="axc-b">
                    <h3><?= e($c['title']) ?></h3>
                    <div class="axc-meta">
                        <span><i class="fas fa-user"></i><?= e($c['owner_name'] ?? '—') ?></span>
                        <span><i class="fas fa-tag"></i><?= e($c['category_name'] ?? 'Uncategorised') ?></span>
                        <span><i class="fas fa-signal"></i><?= e(ucfirst((string)$c['level'])) ?></span>
                    </div>
                    <div class="axc-nums">
                        <div><b><?= number_format((int)($enrolled[$cid] ?? 0)) ?></b><small>Learners</small></div>
                        <div><b><?= number_format((int)($completed[$cid] ?? 0)) ?></b><small>Completed</small></div>
                        <div><b><?= number_format((int)($lessonsN[$cid] ?? 0)) ?></b><small>Lessons</small></div>
                        <div><b><?= $rt ? number_format((float)$rt['a'], 1) . ' <i class="fas fa-star"></i>' : '—' ?></b><small><?= $rt ? (int)$rt['n'] . ' review' . ((int)$rt['n'] === 1 ? '' : 's') : 'No reviews' ?></small></div>
                    </div>
                    <div class="axc-money"><span>Price <b><?= e($priceTxt) ?></b></span><span>Certificate <b><?= $fee > 0 ? ax_money($fee) : 'Free' ?></b></span></div>
                    <div class="axc-money"><span>Revenue <b><?= ax_money($revenue[$cid] ?? 0) ?></b></span><span>Added <b><?= ax_date($c['created_at']) ?></b></span></div>
                </div>
                <div class="axc-f">
                    <?php if ($c['status'] === 'pending'): ?>
                        <form method="post" data-confirm="Approve &quot;<?= e($c['title']) ?>&quot; and make it live?"><?= ax_csrf() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $cid ?>"><button class="ax-btn ok sm"><i class="fas fa-check"></i> Approve</button></form>
                        <button type="button" class="ax-btn danger sm" data-reject="<?= $cid ?>" data-title="<?= e($c['title']) ?>"><i class="fas fa-xmark"></i> Reject</button>
                    <?php endif; ?>
                    <button type="button" class="ax-btn ghost sm" data-course-edit="<?= e(json_encode($edit, JSON_UNESCAPED_UNICODE)) ?>"><i class="fas fa-pen"></i> Edit</button>
                    <?php if ($c['status'] === 'archived'): ?>
                        <form method="post" data-confirm="Restore &quot;<?= e($c['title']) ?>&quot;? It goes live again."><?= ax_csrf() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= $cid ?>"><button class="ax-btn ghost sm" title="Restore"><i class="fas fa-rotate-left"></i> Restore</button></form>
                    <?php else: ?>
                        <form method="post" data-confirm="Archive &quot;<?= e($c['title']) ?>&quot;? It disappears from the catalogue; enrolled learners keep their records." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= $cid ?>"><button class="ax-btn ghost sm icon" title="Archive" aria-label="Archive"><i class="fas fa-box-archive"></i></button></form>
                    <?php endif; ?>
                    <span class="sp"></span>
                    <a class="ax-btn ghost sm icon" href="course-pricing?id=<?= $cid ?>" title="Pricing, tracks &amp; cohorts" aria-label="Pricing"><i class="fas fa-money-bill-wave"></i></a>
                    <a class="ax-btn ghost sm icon" href="course_details?id=<?= $cid ?>" title="Course details & learners" aria-label="Details"><i class="fas fa-chart-simple"></i></a>
                    <a class="ax-btn ghost sm icon" href="../course-details?id=<?= $cid ?>" target="_blank" rel="noopener" title="Public page" aria-label="Public page"><i class="fas fa-up-right-from-square"></i></a>
                    <a class="ax-btn ghost sm icon" href="../<?= e(learn_url($c)) ?>" target="_blank" rel="noopener" title="Open in the reader" aria-label="Reader"><i class="fas fa-book-open-reader"></i></a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<dialog class="ax-modal" id="courseModal" style="width:min(760px,calc(100vw - 32px))">
    <form method="post" enctype="multipart/form-data" class="ax-form" id="courseForm">
        <div class="ax-panel-h"><div><h2 id="cmTitle"><i class="fas fa-book"></i> New course</h2><p id="cmSub">Fill in the basics. Prices per track and cohorts are set on the pricing page.</p></div>
            <button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="cm-id">
            <div id="cm-lib" class="ax-alert info" hidden><i class="fas fa-circle-info"></i><div>This course comes from the content library. Its title, description, level, category and outcomes are overwritten by the next import – change those in its <span class="ax-code">course.json</span> / <span class="ax-code">tutorial.json</span> instead.</div></div>
            <div class="ax-field"><label for="cm-title">Title</label><input type="text" id="cm-title" name="title" required maxlength="255"></div>
            <div class="ax-row">
                <div class="ax-field"><label for="cm-owner">Owner</label><select id="cm-owner" name="tutor_id" required>
                    <option value="" id="cm-owner-missing" hidden>— (owner account deleted)</option>
                    <?php foreach ($owners as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?><?= $o['role'] === 'admin' ? ' (admin)' : '' ?></option><?php endforeach; ?>
                </select></div>
                <div class="ax-field"><label for="cm-cat">Category</label><select id="cm-cat" name="category_id">
                    <option value="">Uncategorised</option>
                    <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                </select></div>
            </div>
            <div class="ax-row">
                <div class="ax-field"><label for="cm-level">Level</label><select id="cm-level" name="level"><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select></div>
                <div class="ax-field"><label for="cm-status">Status</label><select id="cm-status" name="status"><option value="published">Live</option><option value="pending">Pending review</option><option value="draft">Draft</option><option value="archived">Archived</option></select></div>
                <div class="ax-field"><label for="cm-modes">Learning tracks</label><select id="cm-modes" name="learning_modes"><option value="both">Self-paced and structured</option><option value="self_paced">Self-paced only</option><option value="structured">Structured only</option></select></div>
            </div>
            <div class="ax-row">
                <div class="ax-field"><label for="cm-price">Price (KES)</label><input type="number" id="cm-price" name="price" min="0" step="1" required><div class="hint">Self-paced price. 0 = free. Structured and sale prices: pricing page.</div></div>
                <div class="ax-field"><label for="cm-fee">Certificate fee (KES)</label><input type="number" id="cm-fee" name="certificate_fee" min="0" step="1" placeholder="Default: <?= e(number_format($defaultFee)) ?>"><div class="hint">Leave empty to use the platform default (<?= ax_money($defaultFee) ?>).</div></div>
                <div class="ax-field"><label for="cm-thumb">Cover image</label><input type="file" id="cm-thumb" name="thumbnail" accept="image/png,image/jpeg,image/webp" class="ax-input"><div class="hint">JPG, PNG or WebP, up to 3 MB.</div></div>
            </div>
            <div class="ax-field"><label for="cm-desc">Description</label><textarea id="cm-desc" name="description" rows="4"></textarea></div>
            <div class="ax-field"><label for="cm-out">What learners will learn</label><textarea id="cm-out" name="outcomes" rows="4" placeholder="One outcome per line"></textarea><div class="hint">One per line. Shown as “What you'll learn” on the course page.</div></div>
            <label class="ax-check"><input type="checkbox" name="is_featured" value="1" id="cm-feat"> Feature this course on the home page and catalogue</label>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn" id="cm-submit"><i class="fas fa-floppy-disk"></i> Create course</button></div>
        </div>
    </form>
</dialog>

<dialog class="ax-modal" id="rejectModal" style="width:min(480px,calc(100vw - 32px))">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h2><i class="fas fa-rotate-left"></i> Send back to the owner</h2><p id="rj-sub"></p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" id="rj-id">
            <div class="ax-field"><label for="rj-reason">What needs to change? (optional)</label><textarea id="rj-reason" name="reason" maxlength="1000" placeholder="e.g. Add at least 5 lessons and a cover image."></textarea><div class="hint">The course goes back to Draft and the owner gets a notification with this note.</div></div>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn warn"><i class="fas fa-xmark"></i> Reject course</button></div>
        </div>
    </form>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
    var dlg = document.getElementById('courseModal'), f = document.getElementById('courseForm');
    function $(id) { return document.getElementById(id); }
    function open(c) {
        f.reset();
        var miss = $('cm-owner-missing');
        miss.hidden = true;
        $('cm-id').value = c ? c.id : '';
        $('cmTitle').innerHTML = c ? '<i class="fas fa-pen"></i> Edit course' : '<i class="fas fa-book"></i> New course';
        $('cm-submit').innerHTML = c ? '<i class="fas fa-floppy-disk"></i> Save changes' : '<i class="fas fa-plus"></i> Create course';
        $('cm-lib').hidden = !(c && c.library);
        if (c) {
            $('cm-title').value = c.title || '';
            if (c.owner_missing) { miss.hidden = false; miss.value = c.tutor_id || ''; $('cm-owner').value = miss.value; }
            else $('cm-owner').value = c.tutor_id;
            $('cm-cat').value = c.category_id || '';
            $('cm-level').value = c.level || 'beginner';
            $('cm-status').value = c.status;
            $('cm-modes').value = c.learning_modes || 'both';
            $('cm-price').value = c.price;
            $('cm-fee').value = c.certificate_fee;
            $('cm-desc').value = c.description || '';
            $('cm-out').value = c.outcomes || '';
            $('cm-feat').checked = !!c.is_featured;
        } else {
            $('cm-price').value = 1500; $('cm-status').value = 'pending'; $('cm-modes').value = 'both';
        }
        dlg.showModal();
    }
    document.querySelectorAll('[data-course-edit]').forEach(function (b) { b.addEventListener('click', function () { open(JSON.parse(b.dataset.courseEdit)); }); });
    document.querySelectorAll('[data-course-new]').forEach(function (b) { b.addEventListener('click', function () { open(null); }); });
    document.querySelectorAll('[data-reject]').forEach(function (b) { b.addEventListener('click', function () {
        $('rj-id').value = b.dataset.reject; $('rj-sub').textContent = b.dataset.title; $('rj-reason').value = ''; $('rejectModal').showModal();
    }); });
})();
</script>
</body></html>
