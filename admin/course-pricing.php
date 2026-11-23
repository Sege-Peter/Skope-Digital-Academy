<?php
/**
 * Admin: pricing & delivery.
 *   course-pricing        → every course with its tracks, prices, certificate fee and visibility
 *   course-pricing?id=ID  → edit one course: tracks, prices, sale, certificate fee, access, tutorial flag,
 *                           publish state, free-preview lessons, structured-track cohorts
 */
$pageTitle = 'Pricing & Tracks';
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);

function money_in($v): ?float {
    $v = trim((string)$v);
    if ($v === '') return null;
    return max(0, round((float)$v, 2));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    if (!$course) { ax_flash('err', 'Course not found.'); ax_back('course-pricing'); }

    try {
        if (isset($_POST['save_pricing'])) {
            $modes = in_array($_POST['learning_modes'] ?? '', ['self_paced', 'structured', 'both'], true) ? $_POST['learning_modes'] : 'both';
            $price = money_in($_POST['price']) ?? 0.0;
            $sprice = money_in($_POST['structured_price'] ?? '');
            $sale = money_in($_POST['sale_price'] ?? '');
            if ($sale !== null && $sale >= $price) $sale = null;           // a "sale" must be below the list price
            $sale_ends = trim($_POST['sale_ends_at'] ?? '');
            $sale_ends = ($sale !== null && $sale_ends !== '') ? date('Y-m-d 23:59:59', strtotime($sale_ends)) : null;
            $status = in_array($_POST['status'] ?? '', ['draft', 'pending', 'published', 'archived'], true) ? $_POST['status'] : $course['status'];
            $certFee = money_in($_POST['certificate_fee'] ?? '');   // blank = platform default
            $pdo->prepare("UPDATE courses SET learning_modes = ?, price = ?, structured_price = ?, sale_price = ?, sale_ends_at = ?,
                                  access_days = ?, is_open_tutorial = ?, is_featured = ?, status = ?, certificate_fee = ?, pricing_updated_by = ?, pricing_updated_at = NOW()
                           WHERE id = ?")
                ->execute([$modes, $price, $sprice, $sale, $sale_ends, max(0, (int)$_POST['access_days']),
                           isset($_POST['is_open_tutorial']) ? 1 : 0, isset($_POST['is_featured']) ? 1 : 0, $status, $certFee, $admin['id'], $id]);
            $pdo->prepare("INSERT INTO audit_log (user_id, action, description, ip_address) VALUES (?, 'course_pricing', ?, ?)")
                ->execute([$admin['id'], "Course #$id: modes=$modes price=$price structured=" . ($sprice ?? 'same') . " sale=" . ($sale ?? 'none') . " cert=" . ($certFee ?? 'default') . " status=$status", $_SERVER['REMOTE_ADDR'] ?? '']);
            ax_flash('ok', 'Pricing and delivery saved.' . (money_in($_POST['sale_price'] ?? '') !== null && $sale === null ? ' The sale price was ignored because it was not below the self-paced price.' : ''));
            ax_back();
        }
        if (isset($_POST['save_previews'])) {
            $ids = array_map('intval', $_POST['preview'] ?? []);
            $pdo->prepare("UPDATE lessons SET is_preview = 0 WHERE course_id = ?")->execute([$id]);
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE lessons SET is_preview = 1 WHERE course_id = ? AND id IN ($in)")->execute(array_merge([$id], $ids));
            }
            ax_flash('ok', count($ids) . ' free preview lesson(s) set.');
            ax_back();
        }
        if (isset($_POST['add_cohort'])) {
            $name = trim($_POST['name'] ?? '');
            $start = $_POST['start_date'] ?? '';
            if ($name === '' || !strtotime($start)) { ax_flash('err', 'A cohort needs a name and a start date.'); ax_back(); }
            $end = trim($_POST['end_date'] ?? '');
            $pdo->prepare("INSERT INTO course_cohorts (course_id, name, start_date, end_date, capacity) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id, mb_substr($name, 0, 120), date('Y-m-d', strtotime($start)), $end !== '' ? date('Y-m-d', strtotime($end)) : null, (int)$_POST['capacity'] ?: null]);
            ax_flash('ok', 'Cohort added.');
            ax_back();
        }
        if (isset($_POST['cohort_status'])) {
            $st = in_array($_POST['cohort_status'], ['open', 'closed', 'archived'], true) ? $_POST['cohort_status'] : 'closed';
            $pdo->prepare("UPDATE course_cohorts SET status = ? WHERE id = ? AND course_id = ?")->execute([$st, (int)$_POST['cohort_id'], $id]);
            ax_flash('ok', 'Cohort ' . ($st === 'open' ? 'reopened' : $st) . '.');
            ax_back();
        }
        if (isset($_POST['delete_cohort'])) {
            $n = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE cohort_id = ?");
            $n->execute([(int)$_POST['cohort_id']]);
            if ($n->fetchColumn() > 0) { ax_flash('err', 'That cohort has learners. Close or archive it instead.'); ax_back(); }
            $pdo->prepare("DELETE FROM course_cohorts WHERE id = ? AND course_id = ?")->execute([(int)$_POST['cohort_id'], $id]);
            ax_flash('ok', 'Cohort deleted.');
            ax_back();
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        ax_flash('err', 'Could not save: ' . $e->getMessage());
    }
    ax_back();
}

$cur = setting('currency');
$defaultCert = (float)setting('certificate_fee');
if ($id) {
    $stmt = $pdo->prepare("SELECT c.*, u.name AS tutor_name FROM courses c LEFT JOIN users u ON u.id = c.tutor_id WHERE c.id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    if (!$course) { header('Location: course-pricing'); exit; }
    $lessons = $pdo->prepare("SELECT id, title, week_num, is_preview, code_example IS NOT NULL AND code_example <> '' AS has_code FROM lessons WHERE course_id = ? ORDER BY week_num, order_num, id");
    $lessons->execute([$id]);
    $lessons = $lessons->fetchAll();
    $cohorts = $pdo->prepare("SELECT co.*, (SELECT COUNT(*) FROM enrollments e WHERE e.cohort_id = co.id AND e.status IN ('active','pending','completed')) AS seats_taken
                              FROM course_cohorts co WHERE co.course_id = ? ORDER BY co.start_date DESC");
    $cohorts->execute([$id]);
    $cohorts = $cohorts->fetchAll();
    $mix = $pdo->prepare("SELECT learning_mode, COUNT(*) n FROM enrollments WHERE course_id = ? AND status IN ('active','completed') GROUP BY learning_mode");
    $mix->execute([$id]);
    $mix = $mix->fetchAll(PDO::FETCH_KEY_PAIR);
    $rev = $pdo->prepare("SELECT payment_type, SUM(amount) FROM payments WHERE course_id = ? AND status = 'verified' GROUP BY payment_type");
    $rev->execute([$id]);
    $rev = $rev->fetchAll(PDO::FETCH_KEY_PAIR);
    $isFree = course_from_price($course) <= 0;
    $certNow = certificate_fee_for($course);
} else {
    $courses = $pdo->query("SELECT c.*, u.name AS tutor_name,
                                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status IN ('active','completed')) AS learners,
                                   (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count
                            FROM courses c LEFT JOIN users u ON u.id = c.tutor_id
                            ORDER BY FIELD(c.status,'pending','published','draft','archived'), c.title")->fetchAll();
    $tabs = ['all' => 'All', 'paid' => 'Paid', 'free' => 'Free', 'pending' => 'Waiting for review', 'sale' => 'On sale'];
    $tab = isset($tabs[$_GET['show'] ?? '']) ? $_GET['show'] : 'all';
    $match = [
        'all' => fn($c) => true,
        'paid' => fn($c) => course_from_price($c) > 0,
        'free' => fn($c) => course_from_price($c) <= 0,
        'pending' => fn($c) => $c['status'] === 'pending',
        'sale' => fn($c) => sale_active($c),
    ];
    $tabCount = array_map(fn($f) => count(array_filter($courses, $f)), $match);
    $list = array_filter($courses, $match[$tab]);
    $freeWithCertFee = count(array_filter($courses, fn($c) => course_from_price($c) <= 0 && certificate_fee_for($c) > 0 && $c['status'] === 'published'));
}

function cp_status_pill(string $s): string {
    return '<span class="ax-pill ' . (['published' => 'ok', 'pending' => 'warn', 'draft' => '', 'archived' => 'bad'][$s] ?? '') . '">' . e(['published' => 'Published', 'pending' => 'Waiting for review', 'draft' => 'Draft', 'archived' => 'Archived'][$s] ?? ucfirst($s)) . '</span>';
}
function cp_price(array $c, string $mode): string {
    if (!in_array($mode, course_modes($c), true)) return '<span class="muted">Not offered</span>';
    $p = course_price($c, $mode);
    if ($p <= 0) return '<span class="ax-pill ok">Free</span>';
    $s = '<span class="strong">' . e(money($p)) . '</span>';
    if ($mode === 'self_paced' && sale_active($c)) $s .= ' <span class="ax-pill warn" title="List price ' . e(money((float)$c['price'])) . '">Sale</span><div class="muted"><s>' . e(money((float)$c['price'])) . '</s>' . ($c['sale_ends_at'] ? ' · ends ' . e(ax_date($c['sale_ends_at'])) : '') . '</div>';
    return $s;
}

require_once 'includes/sidebar.php';
?>
<style>
  .cp-checks { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
  .cp-check { display: flex; gap: 12px; align-items: flex-start; padding: 13px 15px; border: 1.5px solid var(--border); border-radius: 13px; cursor: pointer; background: var(--surface); transition: border-color .15s; }
  .cp-check:hover { border-color: var(--primary); }
  .cp-check:has(input:checked) { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 8%, var(--surface)); }
  .cp-check input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--primary); flex-shrink: 0; }
  .cp-check b { display: block; color: var(--text-main); font-size: .88rem; }
  .cp-check small { display: block; color: var(--text-muted); font-size: .76rem; line-height: 1.45; margin-top: 2px; }
  .cp-sec { font: 800 .72rem 'Inter', sans-serif; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 6px 0 12px; display: flex; align-items: center; gap: 8px; }
  .cp-sec::after { content: ''; flex: 1; height: 1px; background: var(--border-light); }
  .cp-cert { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 14px; background: color-mix(in srgb, var(--accent) 12%, transparent); border: 1px solid color-mix(in srgb, var(--accent) 35%, transparent); color: var(--text-main); font-size: .86rem; line-height: 1.5; margin-bottom: 18px; }
  .cp-cert i { margin-top: 3px; }
  .cp-tags { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px; }
</style>
<main class="main-content">
<?php if (!$id): ?>
    <?php ax_header('Pricing', '& Tracks', 'Tracks, prices and certificate fees',
        '<a class="ax-btn ghost" href="learning-settings"><i class="fas fa-sliders"></i> Learning settings</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('book', number_format(count($courses)), 'Courses', $tabCount['paid'] . ' paid · ' . $tabCount['free'] . ' free') ?>
            <?= ax_stat('hourglass-half', number_format($tabCount['pending']), 'Waiting for review', 'Set a price and publish', 'amber') ?>
            <?= ax_stat('tags', number_format($tabCount['sale']), 'On sale now', 'Self-paced sale prices', 'orange') ?>
            <?= ax_stat('award', $defaultCert > 0 ? ax_money($defaultCert) : 'Free', 'Default certificate fee', $freeWithCertFee . ' free published course' . ($freeWithCertFee == 1 ? '' : 's') . ' charge for the certificate', 'violet') ?>
        </div>
        <p class="ax-intro">Tutors build lessons; prices, tracks and publishing are set here. Free courses can still charge a fee to unlock the certificate — that fee shows in the <b>Certificate</b> column.</p>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Filter courses">
                <?php foreach ($tabs as $k => $l): ?><a href="course-pricing<?= $k === 'all' ? '' : '?show=' . $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($l) ?> <span class="n"><?= $tabCount[$k] ?></span></a><?php endforeach; ?>
            </nav>
            <label class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Search course or tutor…" data-filter-table="cp-table" aria-label="Search courses"></label>
        </div>
        <section class="ax-panel">
            <?php if (!$list): ?>
                <div class="ax-empty"><i class="fas fa-tags"></i><b>No courses here</b>Try another filter.</div>
            <?php else: ?>
            <div class="ax-table-wrap"><table class="ax-table cards" id="cp-table">
                <thead><tr><th>Course</th><th>Status</th><th>Self-paced</th><th>Structured</th><th>Certificate</th><th>Learners</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($list as $c): $cf = certificate_fee_for($c); $free = course_from_price($c) <= 0; ?>
                    <tr>
                        <td data-label=""><div><b class="strong"><?= e($c['title']) ?></b>
                            <div class="muted"><?= e($c['tutor_name'] ?? '—') ?> · <?= (int)$c['lesson_count'] ?> lessons</div>
                            <div class="cp-tags"><?php foreach (course_modes($c) as $m) echo '<span class="ax-pill">' . e(mode_label($m)) . '</span>'; ?>
                                <?= $c['is_open_tutorial'] ? '<span class="ax-pill info">Free tutorial</span>' : '' ?><?= $c['is_featured'] ? '<span class="ax-pill violet">Featured</span>' : '' ?></div></div></td>
                        <td data-label="Status"><?= cp_status_pill((string)$c['status']) ?></td>
                        <td data-label="Self-paced" class="num"><div><?= cp_price($c, 'self_paced') ?></div></td>
                        <td data-label="Structured" class="num"><?= cp_price($c, 'structured') ?></td>
                        <td data-label="Certificate" class="num"><div>
                            <?= $cf > 0 ? '<span class="strong">' . e(money($cf)) . '</span>' : '<span class="ax-pill ok">Free</span>' ?>
                            <div class="muted"><?= $c['certificate_fee'] === null ? 'platform default' : 'set for this course' ?><?= $free && $cf > 0 ? ' · <b style="color:var(--accent)">free course, paid certificate</b>' : '' ?></div></div></td>
                        <td data-label="Learners" class="num"><?= (int)$c['learners'] ?></td>
                        <td class="actions" data-label=""><a class="ax-btn <?= $c['status'] === 'pending' ? '' : 'ghost' ?> sm" href="course-pricing?id=<?= (int)$c['id'] ?>"><i class="fas fa-<?= $c['status'] === 'pending' ? 'eye' : 'pen' ?>"></i> <?= $c['status'] === 'pending' ? 'Review' : 'Edit' ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>
    </div>

<?php else: ?>
    <?php ax_header($course['title'], '', 'Tutor: ' . ($course['tutor_name'] ?? '—') . ' · ' . ucfirst($course['status']),
        '<a class="ax-btn ghost" href="course-pricing"><i class="fas fa-arrow-left"></i> All courses</a>'
        . '<a class="ax-btn ghost" href="../' . e(learn_url($course)) . '" target="_blank" rel="noopener"><i class="fas fa-book-open"></i> Reader view</a>'
        . '<a class="ax-btn ghost" href="../course-details?id=' . $id . '" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> Public page</a>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('users', number_format(array_sum($mix)), 'Learners', $mix ? implode(' · ', array_map(fn($m, $n) => mode_label($m) . ' ' . $n, array_keys($mix), $mix)) : 'No one enrolled yet') ?>
            <?= ax_stat('money-bill-wave', $isFree ? 'Free' : money(course_from_price($course)), 'Price now', $isFree ? 'No course fee' : 'Cheapest enabled track', 'green') ?>
            <?= ax_stat('award', $certNow > 0 ? money($certNow) : 'Free', 'Certificate fee', $course['certificate_fee'] === null ? 'Platform default' : 'Set for this course', 'violet') ?>
            <?= ax_stat('vault', ax_money(($rev['course'] ?? 0) + ($rev['certificate'] ?? 0)), 'Revenue', 'Courses ' . ax_money($rev['course'] ?? 0) . ' · certificates ' . ax_money($rev['certificate'] ?? 0), 'slate') ?>
        </div>

        <div class="ax-grid">
            <section class="ax-panel ax-span-8">
                <div class="ax-panel-h"><div><h2><i class="fas fa-tags"></i> Tracks &amp; pricing</h2><p>Learners pick a track when they enrol. Set prices to 0 for free.</p></div></div>
                <form method="post" class="ax-panel-b ax-form">
                    <?= ax_csrf() ?>
                    <?php if ($isFree): ?>
                        <div class="cp-cert"><i class="fas fa-award"></i><div>This course is <b>free</b>. <?= $certNow > 0 ? 'Learners still pay <b>' . e(money($certNow)) . '</b> to unlock their certificate when they finish' . ($course['certificate_fee'] === null ? ' (platform default — set 0 below to make it free)' : '') . '.' : 'Its certificate is free too.' ?></div></div>
                    <?php endif; ?>
                    <div class="cp-sec">Tracks</div>
                    <div class="ax-row">
                        <div class="ax-field"><label for="lm">Tracks offered</label>
                            <select name="learning_modes" id="lm">
                                <option value="both" <?= $course['learning_modes'] === 'both' ? 'selected' : '' ?>>Self-paced and structured</option>
                                <option value="self_paced" <?= $course['learning_modes'] === 'self_paced' ? 'selected' : '' ?>>Self-paced only</option>
                                <option value="structured" <?= $course['learning_modes'] === 'structured' ? 'selected' : '' ?>>Structured only</option>
                            </select><div class="hint">Self-paced: everything open at once. Structured: weekly releases with a cohort.</div></div>
                        <div class="ax-field"><label for="st">Status</label>
                            <select name="status" id="st">
                                <?php foreach (['draft' => 'Draft (hidden)', 'pending' => 'Waiting for review', 'published' => 'Published', 'archived' => 'Archived'] as $v => $lbl): ?>
                                    <option value="<?= $v ?>" <?= $course['status'] === $v ? 'selected' : '' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select><div class="hint">Only published courses appear in the catalogue.</div></div>
                    </div>
                    <div class="cp-sec">Prices (<?= e($cur) ?>)</div>
                    <div class="ax-row">
                        <div class="ax-field"><label for="price">Self-paced price</label>
                            <input type="number" step="0.01" min="0" name="price" id="price" value="<?= e((string)(float)$course['price']) ?>" required><div class="hint">0 = free.</div></div>
                        <div class="ax-field"><label for="sp">Structured price</label>
                            <input type="number" step="0.01" min="0" name="structured_price" id="sp" value="<?= $course['structured_price'] !== null ? e((string)(float)$course['structured_price']) : '' ?>" placeholder="Same as self-paced"><div class="hint">Blank = same as self-paced.</div></div>
                        <div class="ax-field"><label for="sale">Sale price (self-paced)</label>
                            <input type="number" step="0.01" min="0" name="sale_price" id="sale" value="<?= $course['sale_price'] !== null ? e((string)(float)$course['sale_price']) : '' ?>" placeholder="No sale"><div class="hint">Must be below the self-paced price.<?= $course['sale_price'] !== null && !sale_active($course) ? ' <b>Not active now</b> (ended or not lower).' : '' ?></div></div>
                        <div class="ax-field"><label for="se">Sale ends</label>
                            <input type="date" name="sale_ends_at" id="se" value="<?= $course['sale_ends_at'] ? date('Y-m-d', strtotime($course['sale_ends_at'])) : '' ?>"><div class="hint">Blank = until you remove it.</div></div>
                    </div>
                    <div class="cp-sec">Certificate &amp; access</div>
                    <div class="ax-row">
                        <div class="ax-field"><label for="cf">Certificate fee</label>
                            <input type="number" step="0.01" min="0" name="certificate_fee" id="cf" value="<?= $course['certificate_fee'] !== null ? e((string)(float)$course['certificate_fee']) : '' ?>" placeholder="Default: <?= e(money($defaultCert)) ?>">
                            <div class="hint">Paid to unlock the certificate after completing. Blank = platform default (<?= e($defaultCert > 0 ? money($defaultCert) : 'free') ?>), 0 = free certificate.</div></div>
                        <div class="ax-field"><label for="ad">Access period (days)</label>
                            <input type="number" min="0" name="access_days" id="ad" value="<?= (int)$course['access_days'] ?>"><div class="hint">0 = platform default (<?= (int)setting('default_access_days') ? (int)setting('default_access_days') . ' days' : 'lifetime' ?>).</div></div>
                    </div>
                    <div class="cp-checks">
                        <label class="cp-check"><input type="checkbox" name="is_open_tutorial" <?= $course['is_open_tutorial'] ? 'checked' : '' ?>>
                            <span><b>Free tutorial</b><small>Every lesson is readable free in the public reader. Enrolling still adds tracking, quizzes and certificates.</small></span></label>
                        <label class="cp-check"><input type="checkbox" name="is_featured" <?= $course['is_featured'] ? 'checked' : '' ?>>
                            <span><b>Featured</b><small>Listed first in the catalogue and tutorials bar.</small></span></label>
                    </div>
                    <div class="ax-form-actions" style="margin-top:18px"><button class="ax-btn" name="save_pricing" value="1"><i class="fas fa-floppy-disk"></i> Save pricing &amp; delivery</button></div>
                </form>
            </section>

            <section class="ax-panel ax-span-4">
                <div class="ax-panel-h"><div><h2><i class="fas fa-eye"></i> Free preview lessons</h2><p>Anyone can read these before paying<?= $course['is_open_tutorial'] ? ' — this is a free tutorial, so every lesson is open anyway' : '' ?>.</p></div></div>
                <form method="post" class="ax-panel-b ax-form">
                    <?= ax_csrf() ?>
                    <?php if (!$lessons): ?><div class="ax-empty" style="padding:24px"><b>No lessons yet</b>The tutor hasn't added lessons.</div><?php else: ?>
                    <div class="cp-checks" style="grid-template-columns:1fr;max-height:520px;overflow-y:auto;padding-right:4px">
                        <?php foreach ($lessons as $l): ?>
                            <label class="cp-check"><input type="checkbox" name="preview[]" value="<?= (int)$l['id'] ?>" <?= $l['is_preview'] ? 'checked' : '' ?>>
                                <span><b><?= e($l['title']) ?></b><small>Module <?= (int)$l['week_num'] ?><?= $l['has_code'] ? ' · has Try-it example' : '' ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="ax-form-actions" style="margin-top:14px"><button class="ax-btn" name="save_previews" value="1"><i class="fas fa-floppy-disk"></i> Save previews</button></div>
                    <?php endif; ?>
                </form>
            </section>

            <section class="ax-panel ax-span-12">
                <div class="ax-panel-h"><div><h2><i class="fas fa-users-rectangle"></i> Structured-track cohorts</h2><p>Week 1 opens on the cohort's start date and a new week every <?= (int)setting('structured_week_days') ?> days. With no open cohort, each learner's schedule starts the day they enrol.</p></div></div>
                <?php if ($cohorts): ?>
                <div class="ax-table-wrap"><table class="ax-table cards">
                    <thead><tr><th>Cohort</th><th>Starts</th><th>Enrolment closes</th><th>Seats</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($cohorts as $co): ?>
                        <tr>
                            <td data-label="Cohort" class="strong"><?= e($co['name']) ?></td>
                            <td data-label="Starts" class="num"><?= e(ax_date($co['start_date'])) ?></td>
                            <td data-label="Enrolment closes" class="num"><?= e(ax_date($co['end_date'])) ?></td>
                            <td data-label="Seats" class="num"><?= (int)$co['seats_taken'] ?><?= $co['capacity'] ? ' / ' . (int)$co['capacity'] : ' <span class="muted">(no limit)</span>' ?></td>
                            <td data-label="Status"><span class="ax-pill <?= $co['status'] === 'open' ? 'ok' : '' ?>"><?= e(ucfirst($co['status'])) ?></span></td>
                            <td class="actions" data-label="">
                                <form method="post"><?= ax_csrf() ?><input type="hidden" name="cohort_id" value="<?= (int)$co['id'] ?>">
                                    <button class="ax-btn ghost sm" name="cohort_status" value="<?= $co['status'] === 'open' ? 'closed' : 'open' ?>"><?= $co['status'] === 'open' ? 'Close' : 'Reopen' ?></button>
                                    <?php if (!$co['seats_taken']): ?><button class="ax-btn danger sm" name="delete_cohort" value="1" data-confirm="Delete the cohort &quot;<?= e($co['name']) ?>&quot;?" data-danger>Delete</button><?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>
                <form method="post" class="ax-panel-b ax-form" <?= $cohorts ? 'style="border-top:1px solid var(--border-light)"' : '' ?>>
                    <?= ax_csrf() ?>
                    <div class="cp-sec">Add a cohort</div>
                    <div class="ax-row">
                        <div class="ax-field"><label for="cn">Cohort name</label><input type="text" name="name" id="cn" placeholder="e.g. January 2027 intake" required maxlength="120"></div>
                        <div class="ax-field"><label for="cs">Start date</label><input type="date" name="start_date" id="cs" required></div>
                        <div class="ax-field"><label for="ce">Enrolment closes</label><input type="date" name="end_date" id="ce"><div class="hint">Optional.</div></div>
                        <div class="ax-field"><label for="cc">Capacity</label><input type="number" min="0" name="capacity" id="cc" placeholder="Unlimited"></div>
                    </div>
                    <div class="ax-form-actions"><button class="ax-btn" name="add_cohort" value="1"><i class="fas fa-plus"></i> Add cohort</button></div>
                </form>
            </section>
        </div>
    </div>
<?php endif; ?>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
