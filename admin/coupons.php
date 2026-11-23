<?php
/** Admin: coupons – discount codes for course enrolments and certificate fees, with usage and redemption history. */
$pageTitle = 'Coupons';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/coupons.php';

/** Read and validate the coupon form. Returns [data, error]. */
function cp_form(PDO $pdo, int $id): array {
    $code = coupon_normalize($_POST['code'] ?? '');
    $type = ($_POST['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
    $value = round((float)($_POST['value'] ?? 0), 2);
    $applies = in_array($_POST['applies_to'] ?? '', ['all', 'course', 'certificate'], true) ? $_POST['applies_to'] : 'all';
    $course = (int)($_POST['course_id'] ?? 0) ?: null;
    $max = trim((string)($_POST['max_uses'] ?? '')) === '' ? null : max(1, (int)$_POST['max_uses']);
    $per = max(1, (int)($_POST['per_user_limit'] ?? 1));
    $starts = trim((string)($_POST['starts_at'] ?? ''));
    $expires = trim((string)($_POST['expires_at'] ?? ''));
    $d = [
        'code' => $code, 'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 255),
        'discount_type' => $type, 'value' => $value, 'applies_to' => $applies, 'course_id' => $course,
        'max_uses' => $max, 'per_user_limit' => $per,
        'starts_at' => $starts !== '' && strtotime($starts) ? date('Y-m-d 00:00:00', strtotime($starts)) : null,
        'expires_at' => $expires !== '' && strtotime($expires) ? date('Y-m-d 23:59:59', strtotime($expires)) : null,
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    if (strlen($code) < 3 || strlen($code) > 40) return [$d, 'Codes need 3 to 40 letters, numbers, dashes or underscores.'];
    if ($value <= 0) return [$d, 'Enter a discount greater than zero.'];
    if ($type === 'percent' && $value > 100) return [$d, 'A percentage discount cannot be more than 100%.'];
    if ($d['starts_at'] && $d['expires_at'] && $d['expires_at'] < $d['starts_at']) return [$d, 'The end date is before the start date.'];
    if ($course) {
        $s = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE id = ?");
        $s->execute([$course]);
        if (!$s->fetchColumn()) return [$d, 'That course no longer exists.'];
    }
    $s = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND id <> ?");
    $s->execute([$code, $id]);
    if ($s->fetchColumn()) return [$d, "The code $code is already used by another coupon."];
    return [$d, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'save') {
        [$d, $err] = cp_form($pdo, $id);
        if ($err) { ax_flash('err', $err); ax_back(); }
        if ($id) {
            $pdo->prepare("UPDATE coupons SET code=?, description=?, discount_type=?, value=?, applies_to=?, course_id=?, max_uses=?, per_user_limit=?, starts_at=?, expires_at=?, active=? WHERE id=?")
                ->execute([...array_values($d), $id]);
            ax_log('coupon.update', 'coupon', $id, "Edited coupon {$d['code']} (" . coupon_label($d) . ')');
            ax_flash('ok', "Coupon {$d['code']} saved.");
        } else {
            $pdo->prepare("INSERT INTO coupons (code, description, discount_type, value, applies_to, course_id, max_uses, per_user_limit, starts_at, expires_at, active, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([...array_values($d), (int)$user['id']]);
            $id = (int)$pdo->lastInsertId();
            ax_log('coupon.create', 'coupon', $id, "Created coupon {$d['code']} (" . coupon_label($d) . ')');
            ax_flash('ok', "Coupon {$d['code']} created. Share the code with learners.");
        }
        ax_back();
    }
    if ($act === 'toggle' && $id) {
        $s = $pdo->prepare("SELECT code, active FROM coupons WHERE id = ?");
        $s->execute([$id]);
        if ($c = $s->fetch()) {
            $on = (int)$c['active'] ? 0 : 1;
            $pdo->prepare("UPDATE coupons SET active = ? WHERE id = ?")->execute([$on, $id]);
            ax_log($on ? 'coupon.activate' : 'coupon.deactivate', 'coupon', $id, ($on ? 'Reactivated' : 'Deactivated') . " coupon {$c['code']}");
            ax_flash('ok', "Coupon {$c['code']} " . ($on ? 'is active again.' : 'is deactivated. Learners can no longer use it.'));
        }
        ax_back();
    }
    if ($act === 'delete' && $id) {
        $s = $pdo->prepare("SELECT code FROM coupons WHERE id = ?");
        $s->execute([$id]);
        $code = $s->fetchColumn();
        if ($code && coupon_uses($pdo, $id) === 0) {
            $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
            ax_log('coupon.delete', 'coupon', $id, "Deleted unused coupon $code");
            ax_flash('ok', "Coupon $code deleted.");
        } else {
            ax_flash('err', 'A coupon that has been used cannot be deleted (its history is kept). Deactivate it instead.');
        }
        ax_back();
    }
}

/* ── Data ─────────────────────────────────────────────────────────────── */
$tab = in_array($_GET['tab'] ?? '', ['active', 'scheduled', 'expired', 'off'], true) ? $_GET['tab'] : 'all';
$filterCoupon = (int)($_GET['coupon'] ?? 0);

$coupons = $pdo->query("SELECT cp.*, co.title AS course_title, u.name AS creator,
        (SELECT COUNT(*) FROM coupon_redemptions r WHERE r.coupon_id = cp.id) AS used,
        (SELECT COALESCE(SUM(r.amount_off),0) FROM coupon_redemptions r WHERE r.coupon_id = cp.id) AS given,
        (SELECT COUNT(*) FROM payments p WHERE p.coupon_id = cp.id AND p.status = 'pending') AS pending_n
    FROM coupons cp LEFT JOIN courses co ON co.id = cp.course_id LEFT JOIN users u ON u.id = cp.created_by
    ORDER BY cp.created_at DESC, cp.id DESC")->fetchAll();

function cp_state(array $c): string {
    if (!(int)$c['active']) return 'off';
    if ($c['expires_at'] && strtotime($c['expires_at']) < time()) return 'expired';
    if ($c['starts_at'] && strtotime($c['starts_at']) > time()) return 'scheduled';
    if ($c['max_uses'] !== null && (int)$c['used'] + (int)$c['pending_n'] >= (int)$c['max_uses']) return 'expired';
    return 'active';
}
$counts = ['all' => count($coupons), 'active' => 0, 'scheduled' => 0, 'expired' => 0, 'off' => 0];
foreach ($coupons as &$c) { $c['_state'] = cp_state($c); $counts[$c['_state']]++; }
unset($c);
$shown = $tab === 'all' ? $coupons : array_values(array_filter($coupons, fn($c) => $c['_state'] === $tab));

$stats = [
    'uses'   => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM coupon_redemptions"),
    'given'  => (float)ax_scalar($pdo, "SELECT COALESCE(SUM(amount_off),0) FROM coupon_redemptions"),
    'month'  => (int)ax_scalar($pdo, "SELECT COUNT(*) FROM coupon_redemptions WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'people' => (int)ax_scalar($pdo, "SELECT COUNT(DISTINCT user_id) FROM coupon_redemptions"),
];

$rsql = "SELECT r.*, cp.code, u.name AS learner, u.email, u.avatar, p.amount AS paid, p.payment_type, co.title AS course_title,
                ce.verification_code, ec.title AS enr_course
         FROM coupon_redemptions r
         JOIN coupons cp ON cp.id = r.coupon_id
         LEFT JOIN users u ON u.id = r.user_id
         LEFT JOIN payments p ON p.id = r.payment_id
         LEFT JOIN certificates ce ON ce.id = r.certificate_id
         LEFT JOIN courses co ON co.id = COALESCE(p.course_id, ce.course_id)
         LEFT JOIN enrollments en ON en.id = r.enrollment_id
         LEFT JOIN courses ec ON ec.id = en.course_id";
if ($filterCoupon) {
    $st = $pdo->prepare("$rsql WHERE r.coupon_id = ? ORDER BY r.created_at DESC LIMIT 200");
    $st->execute([$filterCoupon]);
} else {
    $st = $pdo->query("$rsql ORDER BY r.created_at DESC LIMIT 100");
}
$redemptions = $st->fetchAll();
$filterCode = '';
foreach ($coupons as $c) if ((int)$c['id'] === $filterCoupon) $filterCode = $c['code'];

$courses = $pdo->query("SELECT id, title, status FROM courses WHERE status <> 'archived' ORDER BY title")->fetchAll();

$stateMeta = ['active' => ['ok', 'Active'], 'scheduled' => ['info', 'Scheduled'], 'expired' => ['warn', 'Ended'], 'off' => ['', 'Deactivated']];
require_once 'includes/sidebar.php';
?>
<style>
  .cp-code { font: 800 .9rem 'JetBrains Mono', ui-monospace, monospace; letter-spacing: .5px; color: var(--text-main); background: var(--surface-2); border: 1px dashed var(--border); padding: 4px 10px; border-radius: 8px; white-space: nowrap; }
  .cp-off { font: 800 1rem 'Poppins', sans-serif; color: var(--text-main); white-space: nowrap; }
  .cp-use { min-width: 110px; }
  .cp-use .ax-bar { margin-top: 6px; }
  .cp-desc { font-size: .78rem; color: var(--text-muted); margin-top: 4px; max-width: 260px; }
  .cp-gen { display: flex; gap: 8px; }
  .cp-gen input { flex: 1; min-width: 0; text-transform: uppercase; font-family: 'JetBrains Mono', ui-monospace, monospace; }
  .cp-preview { padding: 12px 14px; border-radius: 12px; background: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--text-main); font-size: .85rem; font-weight: 600; margin-bottom: 14px; }
  #cp-dlg { width: min(680px, calc(100vw - 32px)); }
  main.main-content { min-width: 0; }
  .ax-toolbar .ax-tabs { min-width: 0; max-width: 100%; }
  @media (max-width: 760px) { .cp-desc { max-width: none; text-align: right; } .cp-use .ax-bar { margin-left: auto; width: 120px; } }
</style>
<main class="main-content">
    <?php ax_header('Coupons', '', 'Discount codes for courses & certificates',
        '<button type="button" class="ax-btn" data-new><i class="fas fa-plus"></i> New coupon</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <div class="ax-stats">
            <?= ax_stat('ticket', number_format($counts['active']), 'Active coupons', number_format($counts['all']) . ' in total', '') ?>
            <?= ax_stat('receipt', number_format($stats['uses']), 'Times used', number_format($stats['month']) . ' this month', 'green') ?>
            <?= ax_stat('tags', ax_money($stats['given']), 'Total discount given', 'Verified payments & free unlocks', 'orange') ?>
            <?= ax_stat('users', number_format($stats['people']), 'Learners who saved', 'Distinct learners', 'violet') ?>
        </div>

        <div class="ax-toolbar">
            <nav class="ax-tabs" aria-label="Filter coupons">
                <?php foreach (['all' => 'All', 'active' => 'Active', 'scheduled' => 'Scheduled', 'expired' => 'Ended', 'off' => 'Deactivated'] as $k => $l): ?>
                    <a href="coupons<?= $k === 'all' ? '' : '?tab=' . $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($l) ?> <span class="n"><?= $counts[$k] ?></span></a>
                <?php endforeach; ?>
            </nav>
            <label class="ax-search"><i class="fas fa-search"></i><input type="search" placeholder="Search code, course, note…" data-filter-table="cp-table" aria-label="Search coupons"></label>
        </div>

        <section class="ax-panel">
            <div class="ax-panel-h"><div><h2><i class="fas fa-ticket"></i> Coupons</h2><p>Learners enter a code on the enrolment payment step or the certificate unlock page. Uses count once a payment is verified (or a 100% coupon is used); payments still waiting for approval hold a use.</p></div></div>
            <?php if (!$shown): ?>
                <div class="ax-empty"><i class="fas fa-ticket"></i><b><?= $coupons ? 'No coupons here' : 'No coupons yet' ?></b><?= $coupons ? 'Try another tab.' : 'Create a code to give learners a discount on a course or a certificate fee.' ?></div>
            <?php else: ?>
            <div class="ax-table-wrap">
                <table class="ax-table cards" id="cp-table">
                    <thead><tr><th>Code</th><th>Discount</th><th>Works on</th><th>Valid</th><th>Uses</th><th>Discount given</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($shown as $c):
                        [$pc, $pl] = $stateMeta[$c['_state']];
                        $usedAll = (int)$c['used'] + (int)$c['pending_n'];
                        $json = ['id' => (int)$c['id'], 'code' => $c['code'], 'description' => $c['description'], 'discount_type' => $c['discount_type'],
                                 'value' => (float)$c['value'], 'applies_to' => $c['applies_to'], 'course_id' => (int)$c['course_id'],
                                 'max_uses' => $c['max_uses'], 'per_user_limit' => (int)$c['per_user_limit'],
                                 'starts_at' => $c['starts_at'] ? substr($c['starts_at'], 0, 10) : '', 'expires_at' => $c['expires_at'] ? substr($c['expires_at'], 0, 10) : '',
                                 'active' => (int)$c['active']]; ?>
                        <tr>
                            <td data-label="Code"><div><span class="cp-code"><?= e($c['code']) ?></span><?php if ($c['description']): ?><div class="cp-desc"><?= e($c['description']) ?></div><?php endif; ?></div></td>
                            <td data-label="Discount"><span class="cp-off"><?= e(coupon_label($c)) ?></span></td>
                            <td data-label="Works on"><div>
                                <?= $c['applies_to'] === 'certificate' ? '<span class="ax-pill violet"><i class="fas fa-award"></i> Certificates</span>' : ($c['applies_to'] === 'course' ? '<span class="ax-pill info"><i class="fas fa-book"></i> Courses</span>' : '<span class="ax-pill">Courses & certificates</span>') ?>
                                <div class="muted" style="margin-top:4px"><?= $c['course_id'] ? e($c['course_title'] ?? 'Course removed') : 'Any course' ?></div></div></td>
                            <td data-label="Valid" class="num"><div><?= $c['starts_at'] ? 'From ' . e(ax_date($c['starts_at'])) : 'Now' ?><div class="muted"><?= $c['expires_at'] ? 'Until ' . e(ax_date($c['expires_at'])) : 'No end date' ?></div></div></td>
                            <td data-label="Uses"><div class="cp-use"><b class="num" style="color:var(--text-main)"><?= (int)$c['used'] ?></b><span class="muted"> / <?= $c['max_uses'] !== null ? (int)$c['max_uses'] : '∞' ?></span>
                                <?php if ($c['max_uses'] !== null): ?><div class="ax-bar"><i style="width:<?= min(100, round($usedAll / max(1, (int)$c['max_uses']) * 100)) ?>%"></i></div><?php endif; ?>
                                <div class="muted"><?= (int)$c['pending_n'] ? (int)$c['pending_n'] . ' awaiting approval · ' : '' ?><?= (int)$c['per_user_limit'] ?> per learner</div></div></td>
                            <td data-label="Given" class="num strong"><?= e(ax_money($c['given'])) ?></td>
                            <td data-label="Status"><span class="ax-pill <?= $pc ?>"><?= e($pl) ?></span></td>
                            <td class="actions" data-label="">
                                <a class="ax-btn ghost sm" href="coupons?coupon=<?= (int)$c['id'] ?>#history" title="Redemption history"><i class="fas fa-clock-rotate-left"></i></a>
                                <button type="button" class="ax-btn ghost sm" data-edit="<?= e(json_encode($json)) ?>"><i class="fas fa-pen"></i> Edit</button>
                                <form method="post"<?= (int)$c['active'] ? ' data-confirm="Deactivate ' . e($c['code']) . '? Learners will no longer be able to use it."' : '' ?>><?= ax_csrf() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="ax-btn <?= (int)$c['active'] ? 'danger' : 'ok' ?> sm"><i class="fas fa-<?= (int)$c['active'] ? 'ban' : 'rotate-left' ?>"></i> <?= (int)$c['active'] ? 'Deactivate' : 'Activate' ?></button></form>
                                <?php if ($usedAll === 0): ?>
                                <form method="post" data-confirm="Delete coupon <?= e($c['code']) ?>? It has never been used." data-danger><?= ax_csrf() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                                    <button class="ax-btn danger sm icon" aria-label="Delete"><i class="fas fa-trash"></i></button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>

        <section class="ax-panel" id="history" style="margin-top:24px">
            <div class="ax-panel-h"><div><h2><i class="fas fa-clock-rotate-left"></i> Redemption history<?= $filterCode ? ' · ' . e($filterCode) : '' ?></h2>
                <p><?= $filterCode ? 'Every use of this coupon.' : 'Latest 100 uses across all coupons.' ?></p></div>
                <?php if ($filterCoupon): ?><a class="ax-btn ghost sm" href="coupons#history"><i class="fas fa-xmark"></i> Show all</a><?php endif; ?></div>
            <?php if (!$redemptions): ?>
                <div class="ax-empty"><i class="fas fa-receipt"></i><b>No uses yet</b>When a learner pays with a coupon (and the payment is verified) it is listed here.</div>
            <?php else: ?>
            <div class="ax-table-wrap">
                <table class="ax-table cards">
                    <thead><tr><th>Learner</th><th>Coupon</th><th>Used on</th><th>Discount</th><th>Paid</th><th>When</th></tr></thead>
                    <tbody>
                    <?php foreach ($redemptions as $r): ?>
                        <tr>
                            <td data-label="Learner"><a class="ax-who" href="user-profile?id=<?= (int)$r['user_id'] ?>" style="text-decoration:none"><?= ax_avatar($r['learner'] ?? '?', $r['avatar'] ?? null) ?><div><b><?= e($r['learner'] ?? 'Deleted account') ?></b><small><?= e($r['email'] ?? '#' . $r['user_id']) ?></small></div></a></td>
                            <td data-label="Coupon"><span class="cp-code"><?= e($r['code']) ?></span></td>
                            <td data-label="Used on"><div>
                                <?php if ($r['certificate_id']): ?><span class="ax-pill violet">Certificate</span> <span class="muted"><?= e($r['course_title'] ?? '') ?></span>
                                <?php else: ?><span class="ax-pill info">Course</span> <span class="muted"><?= e($r['course_title'] ?? $r['enr_course'] ?? '') ?></span><?php endif; ?></div></td>
                            <td data-label="Discount" class="num strong"><?= e(ax_money($r['amount_off'])) ?></td>
                            <td data-label="Paid" class="num"><?= $r['payment_id'] ? '<span><a href="payments?q=' . (int)$r['payment_id'] . '">' . e(ax_money($r['paid'])) . '</a> <span class="muted">#' . (int)$r['payment_id'] . '</span></span>' : '<span class="ax-pill ok">Free with coupon</span>' ?></td>
                            <td data-label="When" class="num"><?= e(ax_date($r['created_at'], 'M j, Y g:i a')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<dialog class="ax-modal" id="cp-dlg" aria-labelledby="cp-title">
    <form method="post" class="ax-form">
        <div class="ax-panel-h"><div><h3 id="cp-title"><i class="fas fa-ticket"></i> <span data-title>New coupon</span></h3><p>Codes are not case-sensitive for learners.</p></div><button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="ax-panel-b">
            <?= ax_csrf() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0">
            <div class="ax-row">
                <div class="ax-field"><label for="cp-code">Code</label>
                    <div class="cp-gen"><input id="cp-code" type="text" name="code" required maxlength="40" pattern="[A-Za-z0-9_\-]{3,40}" placeholder="e.g. WELCOME20"><button type="button" class="ax-btn ghost sm" data-gen title="Generate a random code"><i class="fas fa-shuffle"></i> Generate</button></div>
                    <div class="hint">Letters, numbers, dashes. 3–40 characters.</div></div>
                <div class="ax-field"><label for="cp-desc">Internal note</label><input id="cp-desc" type="text" name="description" maxlength="255" placeholder="e.g. DevFest Kisumu attendees"></div>
            </div>
            <div class="ax-row">
                <div class="ax-field"><label for="cp-type">Discount type</label><select id="cp-type" name="discount_type"><option value="percent">Percentage (%)</option><option value="fixed">Fixed amount (<?= e(setting('currency')) ?>)</option></select></div>
                <div class="ax-field"><label for="cp-value">Discount</label><input id="cp-value" type="number" name="value" min="0.01" step="0.01" required placeholder="20"><div class="hint">100% makes it free – no payment needed.</div></div>
            </div>
            <div class="ax-row">
                <div class="ax-field"><label for="cp-applies">Works on</label><select id="cp-applies" name="applies_to"><option value="all">Courses and certificate fees</option><option value="course">Course enrolments only</option><option value="certificate">Certificate fees only</option></select></div>
                <div class="ax-field"><label for="cp-course">Course</label><select id="cp-course" name="course_id"><option value="">Any course</option>
                    <?php foreach ($courses as $co): ?><option value="<?= (int)$co['id'] ?>"><?= e($co['title']) ?><?= $co['status'] !== 'published' ? ' (' . e($co['status']) . ')' : '' ?></option><?php endforeach; ?></select>
                    <div class="hint">For a certificate coupon, the course the certificate is for.</div></div>
            </div>
            <div class="ax-row">
                <div class="ax-field"><label for="cp-max">Total uses</label><input id="cp-max" type="number" name="max_uses" min="1" placeholder="Unlimited"></div>
                <div class="ax-field"><label for="cp-per">Uses per learner</label><input id="cp-per" type="number" name="per_user_limit" min="1" value="1" required></div>
            </div>
            <div class="ax-row">
                <div class="ax-field"><label for="cp-start">Starts</label><input id="cp-start" type="date" name="starts_at"><div class="hint">Empty = right away.</div></div>
                <div class="ax-field"><label for="cp-end">Ends (last day)</label><input id="cp-end" type="date" name="expires_at"><div class="hint">Empty = no end date.</div></div>
            </div>
            <div class="cp-preview" data-preview></div>
            <label class="ax-check" style="margin-bottom:14px"><input type="checkbox" name="active" value="1" checked> Active – learners can use it</label>
            <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-floppy-disk"></i> Save coupon</button></div>
        </div>
    </form>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function(){
  var dlg = document.getElementById('cp-dlg'), f = dlg.querySelector('form'), cur = <?= json_encode(setting('currency')) ?>;
  function preview(){
    var v = parseFloat(f.value.value) || 0, pct = f.discount_type.value === 'percent';
    var what = {all:'a course or certificate fee', course:'a course enrolment', certificate:'a certificate fee'}[f.applies_to.value];
    var course = f.course_id.value ? ' for ' + f.course_id.options[f.course_id.selectedIndex].text : '';
    var off = pct ? (v >= 100 ? 'makes ' + what + ' free' : 'takes ' + v + '% off ' + what) : 'takes ' + cur + ' ' + v.toLocaleString() + ' off ' + what;
    dlg.querySelector('[data-preview]').textContent = (f.code.value ? f.code.value.toUpperCase() : 'This coupon') + ' ' + off + course + '.';
  }
  function gen(){
    var a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', s = 'SDA-';
    var r = new Uint32Array(8); crypto.getRandomValues(r);
    for (var i = 0; i < 8; i++) s += a[r[i] % a.length];
    f.code.value = s; preview();
  }
  function open(d){
    f.reset();
    f.id.value = d ? d.id : 0;
    dlg.querySelector('[data-title]').textContent = d ? 'Edit ' + d.code : 'New coupon';
    if (d) {
      ['code','description','discount_type','value','applies_to','per_user_limit','starts_at','expires_at'].forEach(function(k){ f[k].value = d[k] == null ? '' : d[k]; });
      f.course_id.value = d.course_id || ''; f.max_uses.value = d.max_uses == null ? '' : d.max_uses; f.active.checked = !!d.active;
    } else gen();
    preview(); dlg.showModal();
  }
  document.querySelector('[data-new]').addEventListener('click', function(){ open(null); });
  document.querySelectorAll('[data-edit]').forEach(function(b){ b.addEventListener('click', function(){ open(JSON.parse(b.dataset.edit)); }); });
  dlg.querySelector('[data-gen]').addEventListener('click', gen);
  f.addEventListener('input', preview); f.addEventListener('change', preview);
})();
</script>
</body></html>
