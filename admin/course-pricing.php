<?php
/**
 * Admin: pricing & delivery.
 *   course-pricing.php        → every course with its tracks, prices and visibility
 *   course-pricing.php?id=ID  → edit one course: tracks, prices, sale, access, tutorial flag,
 *                               publish state, free-preview lessons, structured-track cohorts
 */
$pageTitle = 'Pricing & Tracks';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/learning.php';

$id = (int)($_GET['id'] ?? 0);
$flash = getFlash();

function back_to(int $id, string $type, string $msg): void {
    setFlash($type, $msg);
    header('Location: course-pricing' . ($id ? '?id=' . $id : ''));
    exit;
}
function money_in($v): ?float {
    $v = trim((string)$v);
    if ($v === '') return null;
    return max(0, round((float)$v, 2));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) back_to($id, 'danger', 'Your session expired. Please try again.');
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    if (!$course) back_to(0, 'danger', 'Course not found.');

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
                ->execute([$admin['id'], "Course #$id: modes=$modes price=$price structured=" . ($sprice ?? 'same') . " sale=" . ($sale ?? 'none') . " status=$status", $_SERVER['REMOTE_ADDR'] ?? '']);
            back_to($id, 'success', 'Pricing and delivery saved.');
        }
        if (isset($_POST['save_previews'])) {
            $ids = array_map('intval', $_POST['preview'] ?? []);
            $pdo->prepare("UPDATE lessons SET is_preview = 0 WHERE course_id = ?")->execute([$id]);
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE lessons SET is_preview = 1 WHERE course_id = ? AND id IN ($in)")->execute(array_merge([$id], $ids));
            }
            back_to($id, 'success', count($ids) . ' free preview lesson(s) set.');
        }
        if (isset($_POST['add_cohort'])) {
            $name = trim($_POST['name'] ?? '');
            $start = $_POST['start_date'] ?? '';
            if ($name === '' || !strtotime($start)) back_to($id, 'danger', 'A cohort needs a name and a start date.');
            $end = trim($_POST['end_date'] ?? '');
            $pdo->prepare("INSERT INTO course_cohorts (course_id, name, start_date, end_date, capacity) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id, mb_substr($name, 0, 120), date('Y-m-d', strtotime($start)), $end !== '' ? date('Y-m-d', strtotime($end)) : null, (int)$_POST['capacity'] ?: null]);
            back_to($id, 'success', 'Cohort added.');
        }
        if (isset($_POST['cohort_status'])) {
            $st = in_array($_POST['cohort_status'], ['open', 'closed', 'archived'], true) ? $_POST['cohort_status'] : 'closed';
            $pdo->prepare("UPDATE course_cohorts SET status = ? WHERE id = ? AND course_id = ?")->execute([$st, (int)$_POST['cohort_id'], $id]);
            back_to($id, 'success', 'Cohort ' . $st . '.');
        }
        if (isset($_POST['delete_cohort'])) {
            $n = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE cohort_id = ?");
            $n->execute([(int)$_POST['cohort_id']]);
            if ($n->fetchColumn() > 0) back_to($id, 'danger', 'That cohort has learners. Close or archive it instead.');
            $pdo->prepare("DELETE FROM course_cohorts WHERE id = ? AND course_id = ?")->execute([(int)$_POST['cohort_id'], $id]);
            back_to($id, 'success', 'Cohort deleted.');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        back_to($id, 'danger', 'Could not save: ' . $e->getMessage());
    }
}

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
} else {
    $courses = $pdo->query("SELECT c.*, u.name AS tutor_name,
                                   (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status IN ('active','completed')) AS learners,
                                   (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lesson_count
                            FROM courses c LEFT JOIN users u ON u.id = c.tutor_id
                            ORDER BY FIELD(c.status,'pending','published','draft','archived'), c.title")->fetchAll();
}
$cur = setting('currency');
?>
<?php require_once 'includes/sidebar.php'; ?>
<style>
  .pp-wrap { display: grid; gap: 22px; }
  .pp-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 24px; }
  .pp-card h2 { font-size: 1.05rem; font-weight: 800; margin: 0 0 4px; }
  .pp-card .pp-sub { color: #64748b; font-size: .85rem; margin: 0 0 18px; }
  .pp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px; }
  .pp-field label { display: block; font-size: .75rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #475569; margin-bottom: 6px; }
  .pp-field input, .pp-field select { width: 100%; padding: 11px 13px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; background: #fff; }
  .pp-field small { display: block; color: #64748b; font-size: .78rem; margin-top: 5px; }
  .pp-check { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 12px; cursor: pointer; }
  .pp-check input { margin-top: 4px; }
  .pp-check span small { display: block; color: #64748b; }
  .pp-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
  .pp-table th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 10px; border-bottom: 1px solid #e2e8f0; }
  .pp-table td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
  .pp-tag { display: inline-block; padding: 2px 9px; border-radius: 99px; font-size: .72rem; font-weight: 800; background: #f1f5f9; color: #334155; margin: 1px 2px 1px 0; }
  .pp-tag.pub { background: #dcfce7; color: #166534; } .pp-tag.pen { background: #fef3c7; color: #92400e; } .pp-tag.dra { background: #e2e8f0; } .pp-tag.arc { background: #fee2e2; color: #991b1b; }
  .pp-tag.open { background: #dbeafe; color: #1e40af; }
  .pp-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; flex-wrap: wrap; }
  .pp-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: 1px solid transparent; font-weight: 800; font-size: .85rem; cursor: pointer; text-decoration: none; background: #0080ff; color: #fff; }
  .pp-btn.ghost { background: #fff; color: #334155; border-color: #cbd5e1; }
  .pp-btn.sm { padding: 6px 12px; font-size: .78rem; }
  .pp-btn.danger { background: #fff; color: #b91c1c; border-color: #fecaca; }
  .pp-flash { padding: 12px 16px; border-radius: 12px; font-weight: 700; }
  .pp-flash.success { background: #dcfce7; color: #166534; } .pp-flash.danger { background: #fee2e2; color: #991b1b; }
  .pp-scroll { overflow-x: auto; }
</style>
<main class="main-content">
  <header class="admin-header">
    <div style="display:flex;align-items:center;gap:20px;">
      <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
      <div>
        <h1 style="font-size:1.5rem;font-weight:900;letter-spacing:-.5px;"><?= $id ? e($course['title']) : 'Pricing &amp; <span>Tracks</span>' ?></h1>
        <p style="font-size:.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:1px;">
          <?= $id ? 'Tutor: ' . e($course['tutor_name'] ?? '—') . ' · ' . e(ucfirst($course['status'])) : 'Admin decides tracks, prices and what is free' ?></p>
      </div>
    </div>
    <div style="display:flex;gap:10px;">
      <?php if ($id): ?>
        <a class="pp-btn ghost" href="course-pricing"><i class="fas fa-arrow-left"></i> All courses</a>
        <a class="pp-btn ghost" href="../learn?course=<?= $id ?>" target="_blank"><i class="fas fa-book-open"></i> Reader view</a>
      <?php else: ?>
        <a class="pp-btn ghost" href="learning-settings"><i class="fas fa-sliders"></i> Learning settings</a>
      <?php endif; ?>
    </div>
  </header>

  <div class="admin-body pp-wrap">
    <?php if ($flash): ?><div class="pp-flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>

    <?php if (!$id): ?>
      <div class="pp-card">
        <h2>All courses</h2>
        <p class="pp-sub">Tutors build lessons; prices, tracks and publishing are set here. Pending courses are waiting for your review.</p>
        <div class="pp-scroll">
        <table class="pp-table">
          <thead><tr><th>Course</th><th>Status</th><th>Tracks</th><th>Self-paced</th><th>Structured</th><th>Certificate</th><th>Learners</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($courses as $c):
            $st = ['published' => 'pub', 'pending' => 'pen', 'draft' => 'dra', 'archived' => 'arc'][$c['status']] ?? 'dra'; ?>
            <tr>
              <td><strong><?= e($c['title']) ?></strong><br><small style="color:#64748b"><?= e($c['tutor_name'] ?? '') ?> · <?= (int)$c['lesson_count'] ?> lessons</small>
                <?= $c['is_open_tutorial'] ? '<br><span class="pp-tag open">Free tutorial</span>' : '' ?><?= $c['is_featured'] ? '<span class="pp-tag">Featured</span>' : '' ?></td>
              <td><span class="pp-tag <?= $st ?>"><?= e(ucfirst($c['status'])) ?></span></td>
              <td><?php foreach (course_modes($c) as $m) echo '<span class="pp-tag">' . e(mode_label($m)) . '</span>'; ?></td>
              <td><?= in_array('self_paced', course_modes($c), true) ? (course_price($c, 'self_paced') > 0 ? e(money(course_price($c, 'self_paced'))) . (sale_active($c) ? ' <small style="color:#b45309">sale</small>' : '') : 'Free') : '—' ?></td>
              <td><?= in_array('structured', course_modes($c), true) ? (course_price($c, 'structured') > 0 ? e(money(course_price($c, 'structured'))) : 'Free') : '—' ?></td>
              <td><?= certificate_fee_for($c) > 0 ? e(money(certificate_fee_for($c))) . ($c['certificate_fee'] === null ? ' <small style="color:#94a3b8">default</small>' : '') : 'Free' ?></td>
              <td><?= (int)$c['learners'] ?></td>
              <td style="text-align:right"><a class="pp-btn sm" href="course-pricing?id=<?= (int)$c['id'] ?>"><?= $c['status'] === 'pending' ? 'Review' : 'Edit' ?></a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$courses): ?><tr><td colspan="8">No courses yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>

    <?php else: ?>
      <form method="post" class="pp-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <h2>Tracks &amp; pricing</h2>
        <p class="pp-sub">Learners pick a track at enrollment. Leave the structured price blank to charge the same as self-paced. Set prices to 0 for free.
          <?php if ($mix): ?>Current learners: <?php foreach ($mix as $m => $n) echo e(mode_label($m)) . ' ' . (int)$n . '  '; ?><?php endif; ?></p>
        <div class="pp-grid">
          <div class="pp-field"><label for="lm">Tracks offered</label>
            <select name="learning_modes" id="lm">
              <option value="both" <?= $course['learning_modes'] === 'both' ? 'selected' : '' ?>>Self-paced and structured</option>
              <option value="self_paced" <?= $course['learning_modes'] === 'self_paced' ? 'selected' : '' ?>>Self-paced only</option>
              <option value="structured" <?= $course['learning_modes'] === 'structured' ? 'selected' : '' ?>>Structured only</option>
            </select></div>
          <div class="pp-field"><label for="price">Self-paced price (<?= e($cur) ?>)</label>
            <input type="number" step="0.01" min="0" name="price" id="price" value="<?= e((string)(float)$course['price']) ?>" required></div>
          <div class="pp-field"><label for="sp">Structured price (<?= e($cur) ?>)</label>
            <input type="number" step="0.01" min="0" name="structured_price" id="sp" value="<?= $course['structured_price'] !== null ? e((string)(float)$course['structured_price']) : '' ?>" placeholder="Same as self-paced"></div>
          <div class="pp-field"><label for="sale">Sale price, self-paced (<?= e($cur) ?>)</label>
            <input type="number" step="0.01" min="0" name="sale_price" id="sale" value="<?= $course['sale_price'] !== null ? e((string)(float)$course['sale_price']) : '' ?>" placeholder="No sale">
            <small>Must be below the self-paced price.</small></div>
          <div class="pp-field"><label for="se">Sale ends</label>
            <input type="date" name="sale_ends_at" id="se" value="<?= $course['sale_ends_at'] ? date('Y-m-d', strtotime($course['sale_ends_at'])) : '' ?>"><small>Blank = until you remove it.</small></div>
          <div class="pp-field"><label for="cf">Certificate fee (<?= e($cur) ?>)</label>
            <input type="number" step="0.01" min="0" name="certificate_fee" id="cf" value="<?= $course['certificate_fee'] !== null ? e((string)(float)$course['certificate_fee']) : '' ?>" placeholder="Default: <?= e(money((float)setting('certificate_fee'))) ?>">
            <small>Paid to unlock the certificate after completing. Blank = platform default, 0 = free certificate.</small></div>
          <div class="pp-field"><label for="ad">Access period (days)</label>
            <input type="number" min="0" name="access_days" id="ad" value="<?= (int)$course['access_days'] ?>"><small>0 = platform default (<?= (int)setting('default_access_days') ?: 'lifetime' ?><?= (int)setting('default_access_days') ? ' days' : '' ?>).</small></div>
          <div class="pp-field"><label for="st">Status</label>
            <select name="status" id="st">
              <?php foreach (['draft' => 'Draft (hidden)', 'pending' => 'Pending review', 'published' => 'Published', 'archived' => 'Archived'] as $v => $lbl): ?>
                <option value="<?= $v ?>" <?= $course['status'] === $v ? 'selected' : '' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <div class="pp-grid" style="margin-top:16px">
          <label class="pp-check"><input type="checkbox" name="is_open_tutorial" <?= $course['is_open_tutorial'] ? 'checked' : '' ?>>
            <span><strong>Free tutorial</strong><small>Every lesson is readable free in the public reader, like W3Schools. Enrollment still adds tracking, quizzes and certificates.</small></span></label>
          <label class="pp-check"><input type="checkbox" name="is_featured" <?= $course['is_featured'] ? 'checked' : '' ?>>
            <span><strong>Featured</strong><small>Listed first in the catalogue and tutorials bar.</small></span></label>
        </div>
        <div class="pp-actions"><button class="pp-btn" name="save_pricing" value="1"><i class="fas fa-floppy-disk"></i> Save pricing &amp; delivery</button></div>
      </form>

      <form method="post" class="pp-card">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <h2>Free preview lessons</h2>
        <p class="pp-sub">Anyone can read these in the public reader before paying<?= $course['is_open_tutorial'] ? ' (this course is a free tutorial, so every lesson is already open)' : '' ?>.</p>
        <?php if (!$lessons): ?><p>No lessons yet.</p><?php else: ?>
        <div class="pp-grid">
          <?php foreach ($lessons as $l): ?>
            <label class="pp-check"><input type="checkbox" name="preview[]" value="<?= (int)$l['id'] ?>" <?= $l['is_preview'] ? 'checked' : '' ?>>
              <span><?= e($l['title']) ?><small>Module <?= (int)$l['week_num'] ?><?= $l['has_code'] ? ' · has Try-it example' : '' ?></small></span></label>
          <?php endforeach; ?>
        </div>
        <div class="pp-actions"><button class="pp-btn" name="save_previews" value="1">Save previews</button></div>
        <?php endif; ?>
      </form>

      <div class="pp-card">
        <h2>Structured-track cohorts</h2>
        <p class="pp-sub">Learners on the structured track join a cohort; week 1 opens on its start date and a new week every <?= (int)setting('structured_week_days') ?> days. With no open cohort, each learner's schedule starts the day they enroll.</p>
        <?php if ($cohorts): ?>
        <div class="pp-scroll">
        <table class="pp-table">
          <thead><tr><th>Cohort</th><th>Starts</th><th>Ends</th><th>Seats</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($cohorts as $co): ?>
            <tr>
              <td><strong><?= e($co['name']) ?></strong></td>
              <td><?= date('M j, Y', strtotime($co['start_date'])) ?></td>
              <td><?= $co['end_date'] ? date('M j, Y', strtotime($co['end_date'])) : '—' ?></td>
              <td><?= (int)$co['seats_taken'] ?><?= $co['capacity'] ? ' / ' . (int)$co['capacity'] : '' ?></td>
              <td><span class="pp-tag <?= $co['status'] === 'open' ? 'pub' : 'dra' ?>"><?= e(ucfirst($co['status'])) ?></span></td>
              <td style="text-align:right;white-space:nowrap">
                <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>"><input type="hidden" name="cohort_id" value="<?= (int)$co['id'] ?>">
                  <button class="pp-btn ghost sm" name="cohort_status" value="<?= $co['status'] === 'open' ? 'closed' : 'open' ?>"><?= $co['status'] === 'open' ? 'Close' : 'Reopen' ?></button>
                  <?php if (!$co['seats_taken']): ?><button class="pp-btn danger sm" name="delete_cohort" value="1">Delete</button><?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
        <?php endif; ?>
        <form method="post" style="margin-top:18px">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
          <div class="pp-grid">
            <div class="pp-field"><label for="cn">Cohort name</label><input name="name" id="cn" placeholder="e.g. January 2027 intake" required maxlength="120"></div>
            <div class="pp-field"><label for="cs">Start date</label><input type="date" name="start_date" id="cs" required></div>
            <div class="pp-field"><label for="ce">Enrollment closes</label><input type="date" name="end_date" id="ce"><small>Optional.</small></div>
            <div class="pp-field"><label for="cc">Capacity</label><input type="number" min="0" name="capacity" id="cc" placeholder="Unlimited"></div>
          </div>
          <div class="pp-actions"><button class="pp-btn" name="add_cohort" value="1"><i class="fas fa-plus"></i> Add cohort</button></div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</main>
<script src="../assets/js/main.js"></script>
</body>
</html>
