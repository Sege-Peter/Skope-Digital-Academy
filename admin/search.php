<?php
/** Admin: global search (sidebar box) – people, courses, payments, certificates, lessons and coupons. */
$pageTitle = 'Search';
require_once 'includes/header.php';

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$only = (string)($_GET['in'] ?? '');
$LIMIT = 20;

/** Escape $text and wrap every case-insensitive match of $q in <mark>. */
function sr_hl(?string $text, string $q): string {
    $text = (string)$text;
    if ($q === '' || $text === '') return e($text);
    $parts = preg_split('/(' . preg_quote($q, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return e($text);
    $out = '';
    foreach ($parts as $i => $p) $out .= $i % 2 ? '<mark>' . e($p) . '</mark>' : e($p);
    return $out;
}
/** A window of $text around the first match (for long M-Pesa messages). */
function sr_snip(?string $text, string $q, int $len = 90): string {
    $text = trim(preg_replace('/\s+/', ' ', (string)$text));
    if (mb_strlen($text) <= $len) return $text;
    $pos = mb_stripos($text, $q);
    $start = $pos === false ? 0 : max(0, $pos - 30);
    return ($start ? '…' : '') . mb_substr($text, $start, $len) . ($start + $len < mb_strlen($text) ? '…' : '');
}

$groups = [];
$tooShort = $q !== '' && mb_strlen($q) < 2;
if ($q !== '' && !$tooShort) {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $run = function (string $countSql, string $sql, int $n) use ($pdo, $like, $LIMIT): array {
        try {
            $c = $pdo->prepare($countSql); $c->execute(array_fill(0, $n, $like));
            $s = $pdo->prepare($sql . " LIMIT $LIMIT"); $s->execute(array_fill(0, $n, $like));
            return [(int)$c->fetchColumn(), $s->fetchAll()];
        } catch (Throwable $e) { error_log('admin search: ' . $e->getMessage()); return [0, []]; }
    };

    $w = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR admission_number LIKE ?)";
    [$n, $rows] = $run("SELECT COUNT(*) FROM users WHERE $w", "SELECT id, name, email, phone, admission_number, role, status, avatar, created_at FROM users WHERE $w ORDER BY name", 4);
    $groups['users'] = ['People', 'users', $n, $rows, 'users'];

    $w = "(c.title LIKE ? OR c.slug LIKE ?)";
    [$n, $rows] = $run("SELECT COUNT(*) FROM courses c WHERE $w", "SELECT c.id, c.title, c.slug, c.status, c.source, c.enrolled_count, u.name AS tutor FROM courses c LEFT JOIN users u ON u.id = c.tutor_id WHERE $w ORDER BY c.title", 2);
    $groups['courses'] = ['Courses', 'book', $n, $rows, 'courses'];

    $w = "(p.paystack_reference LIKE ? OR p.transaction_message LIKE ?)";
    [$n, $rows] = $run("SELECT COUNT(*) FROM payments p WHERE $w", "SELECT p.id, p.amount, p.status, p.payment_type, p.paystack_reference, p.transaction_message, p.created_at, u.name AS learner, c.title AS course_title FROM payments p LEFT JOIN users u ON u.id = p.student_id LEFT JOIN courses c ON c.id = p.course_id WHERE $w ORDER BY p.created_at DESC", 2);
    $groups['payments'] = ['Payments', 'receipt', $n, $rows, 'payments'];

    $w = "ce.verification_code LIKE ?";
    [$n, $rows] = $run("SELECT COUNT(*) FROM certificates ce WHERE $w", "SELECT ce.id, ce.verification_code, ce.status, ce.issued_at, ce.certificate_type, u.name AS learner, COALESCE(c.title, lp.title) AS title FROM certificates ce LEFT JOIN users u ON u.id = ce.student_id LEFT JOIN courses c ON c.id = ce.course_id LEFT JOIN learning_paths lp ON lp.id = ce.path_id WHERE $w ORDER BY ce.issued_at DESC", 1);
    $groups['certificates'] = ['Certificates', 'award', $n, $rows, 'certificates'];

    $w = "l.title LIKE ?";
    [$n, $rows] = $run("SELECT COUNT(*) FROM lessons l WHERE $w", "SELECT l.id, l.title, l.course_id, l.week_num, l.module_title, c.title AS course_title FROM lessons l LEFT JOIN courses c ON c.id = l.course_id WHERE $w ORDER BY c.title, l.order_num", 1);
    $groups['lessons'] = ['Lessons', 'file-lines', $n, $rows, 'courses'];

    $w = "(code LIKE ? OR description LIKE ?)";
    [$n, $rows] = $run("SELECT COUNT(*) FROM coupons WHERE $w", "SELECT id, code, description, discount_type, value, active, expires_at FROM coupons WHERE $w ORDER BY created_at DESC", 2);
    $groups['coupons'] = ['Coupons', 'ticket', $n, $rows, 'coupons'];
}
$total = array_sum(array_map(fn($g) => $g[2], $groups));
if ($only !== '' && !isset($groups[$only])) $only = '';

require_once 'includes/sidebar.php';
?>
<style>
  .sr-box { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
  .sr-box .ax-search { flex: 1 1 320px; max-width: none; }
  .sr-box .ax-search input { padding-top: 13px; padding-bottom: 13px; font-size: .95rem; }
  .sr-res mark { background: color-mix(in srgb, var(--warning, #f59e0b) 35%, transparent); color: inherit; border-radius: 4px; padding: 0 2px; }
  .sr-list { list-style: none; }
  .sr-list li { border-bottom: 1px solid var(--border); }
  .sr-list li:last-child { border-bottom: 0; }
  .sr-list a { display: flex; align-items: center; gap: 14px; padding: 13px 22px; text-decoration: none; color: var(--text-dim); }
  .sr-list a:hover { background: var(--surface-2); }
  .sr-list .grow { flex: 1; min-width: 0; }
  .sr-list b { display: block; color: var(--text-main); font-size: .9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .sr-list small { display: block; color: var(--text-muted); font-size: .76rem; margin-top: 2px; overflow-wrap: anywhere; }
  .sr-list .end { display: flex; gap: 6px; align-items: center; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }
  .sr-ic { width: 38px; height: 38px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: var(--primary-glow); color: var(--primary); flex-shrink: 0; }
  .sr-more { padding: 12px 22px; border-top: 1px solid var(--border); font-size: .8rem; color: var(--text-muted); }
  .sr-tips { max-width: 560px; margin: 0 auto; text-align: left; color: var(--text-dim); font-size: .88rem; line-height: 1.7; }
  .sr-tips li { margin-left: 18px; }
  .sr-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; align-items: start; }
  .sr-grid .ax-panel { margin-bottom: 0; }
  main.main-content { min-width: 0; }
  .ax-toolbar .ax-tabs { min-width: 0; max-width: 100%; }
  @media (max-width: 1200px) { .sr-grid { grid-template-columns: 1fr; } }
  @media (max-width: 640px) { .sr-list a { padding: 12px 16px; align-items: flex-start; } .sr-list .end { max-width: 40%; } }
</style>
<main class="main-content">
    <?php ax_header('Search', '', $q !== '' && !$tooShort ? number_format($total) . ' result' . ($total === 1 ? '' : 's') . ' for “' . $q . '”' : 'Find anything in the academy'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <form class="sr-box" method="get" action="search" role="search">
            <label class="ax-search"><i class="fas fa-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone, admission no., course, M-Pesa code, certificate code, coupon…" aria-label="Search" minlength="2" autofocus></label>
            <button class="ax-btn"><i class="fas fa-search"></i> Search</button>
        </form>

        <?php if ($q === '' || $tooShort): ?>
            <section class="ax-panel"><div class="ax-empty">
                <i class="fas fa-magnifying-glass"></i><b><?= $tooShort ? 'Type at least 2 characters' : 'Search the whole academy' ?></b>
                <ul class="sr-tips">
                    <li><b>People</b> – name, email, phone or admission number.</li>
                    <li><b>Courses &amp; lessons</b> – part of a title or the course slug (e.g. <span class="ax-code">python</span>).</li>
                    <li><b>Payments</b> – a Paystack reference or an M-Pesa code from the learner's message (e.g. <span class="ax-code">RKJ123ABC</span>).</li>
                    <li><b>Certificates</b> – the verification code (e.g. <span class="ax-code">SDA-26-</span>).</li>
                    <li><b>Coupons</b> – the code or its note.</li>
                </ul></div></section>
        <?php elseif (!$total): ?>
            <section class="ax-panel"><div class="ax-empty"><i class="fas fa-circle-question"></i><b>Nothing matches “<?= e($q) ?>”</b>Check the spelling, try a shorter part of the name or code, or search an email instead of a name.</div></section>
        <?php else: ?>
            <div class="ax-toolbar">
                <nav class="ax-tabs" aria-label="Result type">
                    <a href="search?q=<?= e(rawurlencode($q)) ?>" class="<?= $only === '' ? 'on' : '' ?>">All <span class="n"><?= $total ?></span></a>
                    <?php foreach ($groups as $k => [$label, $icon, $n]): if (!$n) continue; ?>
                        <a href="search?q=<?= e(rawurlencode($q)) ?>&amp;in=<?= $k ?>" class="<?= $only === $k ? 'on' : '' ?>"><i class="fas fa-<?= $icon ?>"></i> <?= e($label) ?> <span class="n"><?= $n ?></span></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="sr-res <?= $only === '' ? 'sr-grid' : '' ?>">
            <?php foreach ($groups as $k => [$label, $icon, $n, $rows, $page]):
                if (!$n || ($only !== '' && $only !== $k)) continue; ?>
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-<?= $icon ?>"></i> <?= e($label) ?> <span class="ax-pill"><?= $n ?></span></h2></div>
                        <a class="ax-btn ghost sm" href="<?= e($page . ($k === 'lessons' ? '' : '?q=' . rawurlencode($q))) ?>">Open <?= e(strtolower($label)) ?> <i class="fas fa-arrow-right"></i></a></div>
                    <ul class="sr-list">
                    <?php foreach ($rows as $r): ?>
                        <li>
                        <?php if ($k === 'users'): ?>
                            <a href="user-profile?id=<?= (int)$r['id'] ?>"><?= ax_avatar($r['name'], $r['avatar']) ?><div class="grow"><b><?= sr_hl($r['name'], $q) ?></b>
                                <small><?= sr_hl($r['email'], $q) ?><?= $r['phone'] ? ' · ' . sr_hl($r['phone'], $q) : '' ?><?= $r['admission_number'] ? ' · ' . sr_hl($r['admission_number'], $q) : '' ?></small></div>
                                <span class="end"><span class="ax-pill <?= $r['role'] === 'admin' ? 'violet' : ($r['role'] === 'tutor' ? 'info' : '') ?>"><?= e(ucfirst($r['role'])) ?></span><?php if ($r['status'] !== 'active'): ?><span class="ax-pill <?= $r['status'] === 'suspended' ? 'bad' : 'warn' ?>"><?= e(ucfirst($r['status'])) ?></span><?php endif; ?></span></a>
                        <?php elseif ($k === 'courses'): ?>
                            <a href="course_details?id=<?= (int)$r['id'] ?>"><span class="sr-ic"><i class="fas fa-book"></i></span><div class="grow"><b><?= sr_hl($r['title'], $q) ?></b>
                                <small><?= sr_hl($r['slug'], $q) ?> · <?= e($r['tutor'] ?? '—') ?> · <?= number_format((int)$r['enrolled_count']) ?> enrolled</small></div>
                                <span class="end"><span class="ax-pill <?= $r['status'] === 'published' ? 'ok' : ($r['status'] === 'pending' ? 'warn' : '') ?>"><?= e(ucfirst($r['status'])) ?></span></span></a>
                        <?php elseif ($k === 'payments'): ?>
                            <a href="payments?q=<?= e(rawurlencode($r['paystack_reference'] ?: $q)) ?>"><span class="sr-ic"><i class="fas fa-receipt"></i></span><div class="grow"><b><?= e(ax_money($r['amount'])) ?> · <?= e($r['learner'] ?? 'Deleted account') ?></b>
                                <small><?= $r['paystack_reference'] ? sr_hl($r['paystack_reference'], $q) . ' · ' : '' ?><?= sr_hl(sr_snip($r['transaction_message'], $q), $q) ?></small>
                                <small><?= e($r['payment_type'] === 'certificate' ? 'Certificate fee' : ucfirst((string)$r['payment_type'])) ?><?= $r['course_title'] ? ' · ' . e($r['course_title']) : '' ?> · #<?= (int)$r['id'] ?> · <?= e(ax_date($r['created_at'])) ?></small></div>
                                <span class="end"><span class="ax-pill <?= $r['status'] === 'verified' ? 'ok' : ($r['status'] === 'pending' ? 'warn' : 'bad') ?>"><?= e($r['status'] === 'verified' ? 'Approved' : ($r['status'] === 'failed' ? 'Rejected' : 'Pending')) ?></span></span></a>
                        <?php elseif ($k === 'certificates'): ?>
                            <a href="certificates?q=<?= e(rawurlencode($r['verification_code'])) ?>"><span class="sr-ic"><i class="fas fa-award"></i></span><div class="grow"><b><?= sr_hl($r['verification_code'], $q) ?></b>
                                <small><?= e($r['learner'] ?? 'Deleted account') ?> · <?= e($r['title'] ?? '—') ?> · <?= e(ax_date($r['issued_at'])) ?></small></div>
                                <span class="end"><span class="ax-pill <?= $r['status'] === 'approved' ? 'ok' : ($r['status'] === 'pending' ? 'warn' : 'bad') ?>"><?= e($r['status'] === 'pending' ? 'Locked' : ucfirst($r['status'])) ?></span></span></a>
                        <?php elseif ($k === 'lessons'): ?>
                            <a href="course_details?id=<?= (int)$r['course_id'] ?>"><span class="sr-ic"><i class="fas fa-file-lines"></i></span><div class="grow"><b><?= sr_hl($r['title'], $q) ?></b>
                                <small><?= e($r['course_title'] ?? 'Course removed') ?><?= $r['module_title'] ? ' · ' . e($r['module_title']) : '' ?> · Week <?= (int)$r['week_num'] ?></small></div></a>
                        <?php elseif ($k === 'coupons'): ?>
                            <a href="coupons?coupon=<?= (int)$r['id'] ?>#history"><span class="sr-ic"><i class="fas fa-ticket"></i></span><div class="grow"><b><?= sr_hl($r['code'], $q) ?></b>
                                <small><?= $r['discount_type'] === 'percent' ? (float)$r['value'] . '% off' : e(ax_money($r['value'])) . ' off' ?><?= $r['description'] ? ' · ' . sr_hl($r['description'], $q) : '' ?></small></div>
                                <span class="end"><span class="ax-pill <?= (int)$r['active'] && (!$r['expires_at'] || strtotime($r['expires_at']) > time()) ? 'ok' : '' ?>"><?= (int)$r['active'] ? ($r['expires_at'] && strtotime($r['expires_at']) < time() ? 'Ended' : 'Active') : 'Off' ?></span></span></a>
                        <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                    <?php if ($n > count($rows)): ?><div class="sr-more">Showing the first <?= count($rows) ?> of <?= $n ?>. Refine your search to narrow it down.</div><?php endif; ?>
                </section>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
</body></html>
