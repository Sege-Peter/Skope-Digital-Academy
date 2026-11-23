<?php
/** Admin: certificates – unlock (fee paid / waived), revoke, issue, plus merit points & transcript tools. */
$pageTitle = 'Certificates';
require_once 'includes/header.php';

$uid = (int)$user['id'];

/** Load one certificate with its title. */
function cert_row(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT c.*, COALESCE(co.title, p.title) AS title FROM certificates c
                         LEFT JOIN courses co ON co.id = c.course_id LEFT JOIN learning_paths p ON p.id = c.path_id WHERE c.id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ax_post_guard();
    $act = $_POST['action'] ?? '';
    $certId = (int)($_POST['cert_id'] ?? 0);

    if ($act === 'issue') {
        $sid = (int)($_POST['student_id'] ?? 0);
        [$kind, $tid] = array_pad(explode(':', (string)($_POST['target'] ?? '')), 2, 0);
        $tid = (int)$tid;
        $notes = trim(mb_substr((string)($_POST['notes'] ?? ''), 0, 500));
        $st = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
        $st->execute([$sid]);
        if (!$st->fetch() || !$tid || !in_array($kind, ['course', 'path'], true)) { ax_flash('err', 'Choose a learner and a course or learning path.'); ax_back(); }
        $res = $kind === 'course' ? issue_certificate($pdo, $sid, $tid) : issue_certificate($pdo, $sid, null, $tid);
        if (!$res) { ax_flash('err', 'The certificate could not be created.'); ax_back(); }
        if (!$res['new']) { ax_flash('err', 'This learner already has a certificate for that ' . $kind . ' (code ' . $res['code'] . ').'); ax_back(); }
        $note = 'Issued by ' . $user['name'] . ($notes !== '' ? ' – ' . $notes : '');
        $pdo->prepare("UPDATE certificates SET issued_by = ?, issued_by_role = 'admin', notes = ? WHERE id = ?")->execute([$uid, $note, $res['id']]);
        $msg = 'Certificate issued: ' . $res['code'] . '.';
        if ($res['locked'] && !empty($_POST['waive'])) {
            $pdo->prepare("UPDATE certificates SET fee = 0, notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?")
                ->execute(["\nFee of " . money($res['fee']) . ' waived by ' . $user['name'] . ' on ' . date('M j, Y'), $res['id']]);
            unlock_certificate($pdo, $res['id'], 0);
            $msg .= ' The fee was waived, so it is unlocked.';
        } elseif ($res['locked']) {
            $msg .= ' It stays locked until the learner pays the ' . money($res['fee']) . ' certificate fee.';
        }
        // Admin-issued certificates also earn merit points (as before).
        $pdo->prepare("UPDATE users SET merit_points = merit_points + 100 WHERE id = ?")->execute([$sid]);
        $pdo->prepare("INSERT INTO point_ledger (student_id, points, reason, awarded_by) VALUES (?, 100, 'Certificate awarded', ?)")->execute([$sid, $uid]);
        ax_flash('ok', $msg); ax_back();
    }

    if ($act === 'mark_paid' || $act === 'waive') {
        $c = cert_row($pdo, $certId);
        if (!$c || $c['status'] !== 'pending') { ax_flash('err', 'Only certificates awaiting payment can be unlocked.'); ax_back(); }
        if ($act === 'mark_paid') {
            $fee = (float)$c['fee'];
            // Use the learner's own pending payment for this certificate if there is one; otherwise record a manual one.
            $p = $pdo->prepare("SELECT id FROM payments WHERE payment_type = 'certificate' AND certificate_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
            $p->execute([$certId]);
            if ($pid = $p->fetchColumn()) {
                $pdo->prepare("UPDATE payments SET status = 'verified', verified_at = NOW(), verified_by = ?, amount = GREATEST(amount, ?) WHERE id = ?")->execute([$uid, $fee, $pid]);
            } else {
                $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, payment_type, certificate_id, verified_by, verified_at)
                               VALUES (?, ?, ?, 'verified', ?, 'certificate', ?, ?, NOW())")
                    ->execute([$c['student_id'], (int)$c['course_id'], $fee, 'Certificate fee marked as paid by ' . $user['name'], $certId, $uid]);
            }
            unlock_certificate($pdo, $certId, $fee);
            ax_flash('ok', 'Marked as paid (' . money($fee) . '). The certificate is unlocked and the learner was notified.');
        } else {
            $reason = trim(mb_substr((string)($_POST['reason'] ?? ''), 0, 200));
            $pdo->prepare("UPDATE certificates SET fee = 0, notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?")
                ->execute(["\nFee of " . money((float)$c['fee']) . ' waived by ' . $user['name'] . ' on ' . date('M j, Y') . ($reason !== '' ? ' – ' . $reason : ''), $certId]);
            unlock_certificate($pdo, $certId, 0);
            ax_flash('ok', 'Fee waived. The certificate is unlocked and the learner was notified.');
        }
        ax_back();
    }

    if ($act === 'revoke') {
        $pdo->prepare("UPDATE certificates SET status = 'revoked' WHERE id = ?")->execute([$certId]);
        ax_flash('ok', 'Certificate revoked. Its code no longer verifies.'); ax_back();
    }

    if ($act === 'restore') {
        $c = cert_row($pdo, $certId);
        if (!$c || $c['status'] !== 'revoked') { ax_flash('err', 'That certificate is not revoked.'); ax_back(); }
        $dup = $pdo->prepare("SELECT verification_code FROM certificates WHERE student_id = ? AND id <> ? AND status <> 'revoked' AND certificate_type = ?
                              AND " . ($c['certificate_type'] === 'path' ? 'path_id' : 'course_id') . " = ?");
        $dup->execute([$c['student_id'], $certId, $c['certificate_type'], $c['certificate_type'] === 'path' ? $c['path_id'] : $c['course_id']]);
        if ($code = $dup->fetchColumn()) { ax_flash('err', "The learner already has another active certificate for this ($code). Revoke that one first."); ax_back(); }
        // Unpaid certificates go back to "awaiting payment", not straight to unlocked.
        $status = ((float)$c['fee'] > 0 && !$c['paid_at']) ? 'pending' : 'approved';
        $pdo->prepare("UPDATE certificates SET status = ? WHERE id = ?")->execute([$status, $certId]);
        ax_flash('ok', $status === 'pending' ? 'Certificate restored. It is awaiting payment again.' : 'Certificate restored.'); ax_back();
    }

    if ($act === 'award_points') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $points = (int)($_POST['points'] ?? 0);
        $reason = trim(mb_substr((string)($_POST['reason'] ?? ''), 0, 250)) ?: 'Admin award';
        if ($points < 1 || $points > 10000 || !$sid) { ax_flash('err', 'Choose a learner and between 1 and 10,000 points.'); ax_back(); }
        $pdo->prepare("UPDATE users SET merit_points = merit_points + ? WHERE id = ? AND role = 'student'")->execute([$points, $sid]);
        $pdo->prepare("INSERT INTO point_ledger (student_id, points, reason, awarded_by) VALUES (?, ?, ?, ?)")->execute([$sid, $points, $reason, $uid]);
        ax_flash('ok', number_format($points) . ' merit points awarded.'); ax_back();
    }

    if ($act === 'add_transcript') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $cid = (int)($_POST['course_id'] ?? 0);
        $title = trim(mb_substr((string)($_POST['entry_title'] ?? ''), 0, 255));
        $type = in_array($_POST['entry_type'] ?? '', ['manual_entry', 'course_completion', 'quiz_pass', 'assignment_grade'], true) ? $_POST['entry_type'] : 'manual_entry';
        $score = ($_POST['score'] ?? '') !== '' ? (float)$_POST['score'] : null;
        $max = ($_POST['max_score'] ?? '') !== '' ? (float)$_POST['max_score'] : null;
        $credits = max(0, min(99, (float)($_POST['credits'] ?? 1)));
        if (!$sid || !$cid || $title === '') { ax_flash('err', 'Choose a learner and course and give the entry a title.'); ax_back(); }
        $pdo->prepare("INSERT INTO transcript_entries (student_id, course_id, entry_type, title, score, max_score, grade, credits, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$sid, $cid, $type, $title, $score, $max, trim(mb_substr((string)($_POST['grade'] ?? ''), 0, 10)) ?: null, $credits,
                       trim((string)($_POST['entry_notes'] ?? '')) ?: null, $uid]);
        ax_flash('ok', 'Transcript entry added.'); ax_back();
    }
    ax_back();
}

/* ── Data ── */
$tab = $_GET['tab'] ?? 'all';
$tabs = ['all' => 'All', 'pending' => 'Awaiting payment', 'approved' => 'Unlocked', 'revoked' => 'Revoked', 'path' => 'Path certificates'];
if (!isset($tabs[$tab])) $tab = 'all';
$q = trim((string)($_GET['q'] ?? ''));

$counts = ['all' => 0, 'pending' => 0, 'approved' => 0, 'revoked' => 0, 'path' => 0];
foreach ($pdo->query("SELECT status, certificate_type, COUNT(*) n FROM certificates GROUP BY status, certificate_type")->fetchAll() as $r) {
    $counts['all'] += $r['n'];
    if (isset($counts[$r['status']])) $counts[$r['status']] += $r['n'];
    if ($r['certificate_type'] === 'path') $counts['path'] += $r['n'];
}
$owed = (float)ax_scalar($pdo, "SELECT SUM(fee) FROM certificates WHERE status = 'pending'");
$revenue = (float)ax_scalar($pdo, "SELECT SUM(amount) FROM payments WHERE payment_type = 'certificate' AND status = 'verified'");
$revenueN = (int)ax_scalar($pdo, "SELECT COUNT(*) FROM payments WHERE payment_type = 'certificate' AND status = 'verified'");
$defaultFee = (float)setting('certificate_fee');

$where = [];
$params = [];
if (in_array($tab, ['pending', 'approved', 'revoked'], true)) { $where[] = 'c.status = ?'; $params[] = $tab; }
if ($tab === 'path') $where[] = "c.certificate_type = 'path'";
if ($q !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR c.verification_code LIKE ? OR co.title LIKE ? OR p.title LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
}
$st = $pdo->prepare("SELECT c.*, u.name AS student_name, u.email AS student_email, u.avatar,
                            co.title AS course_title, p.title AS path_title, iss.name AS issuer_name,
                            (SELECT SUM(pm.amount) FROM payments pm WHERE pm.payment_type = 'certificate' AND pm.certificate_id = c.id AND pm.status = 'verified') AS paid_amount,
                            (SELECT COUNT(*) FROM payments pm WHERE pm.payment_type = 'certificate' AND pm.certificate_id = c.id AND pm.status = 'pending') AS pending_payments
                     FROM certificates c
                     LEFT JOIN users u ON u.id = c.student_id
                     LEFT JOIN courses co ON co.id = c.course_id
                     LEFT JOIN learning_paths p ON p.id = c.path_id
                     LEFT JOIN users iss ON iss.id = c.issued_by
                     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                     ORDER BY c.issued_at DESC, c.id DESC LIMIT 300");
$st->execute($params);
$certs = $st->fetchAll();

$students = $pdo->query("SELECT id, name, email, merit_points FROM users WHERE role = 'student' AND status = 'active' ORDER BY name")->fetchAll();
$courses = $pdo->query("SELECT id, title, certificate_fee FROM courses WHERE status = 'published' ORDER BY title")->fetchAll();
$paths = $pdo->query("SELECT id, title FROM learning_paths ORDER BY sort_order, id")->fetchAll();
$recentPoints = $pdo->query("SELECT pl.points, pl.reason, pl.awarded_at, u.name FROM point_ledger pl LEFT JOIN users u ON u.id = pl.student_id ORDER BY pl.id DESC LIMIT 5")->fetchAll();

$statusPill = ['pending' => ['warn', 'Awaiting payment', 'lock'], 'approved' => ['ok', 'Unlocked', 'lock-open'], 'revoked' => ['bad', 'Revoked', 'ban']];
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['tab' => $tab, 'q' => $q], $o), fn($v) => $v !== '' && $v !== 'all'));

require_once 'includes/sidebar.php';
?>
<style>
.main-content { min-width: 0; } /* keep wide tables scrolling inside their panel */
@media (min-width: 641px) { .cx-stats { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); } }
.ax-toolbar > .ax-tabs { contain: inline-size; flex: 1 1 320px; min-width: 0; }
.cx-title { font-weight: 700; color: var(--text-main); }
@media (min-width: 761px) { #certTable th, #certTable td { padding-left: 9px; padding-right: 9px; } #certTable td[data-label="Course / path"] { min-width: 150px; } }
.cx-type { font-size: .7rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .6px; }
.cx-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; width: 196px; margin-left: auto; }
.cx-actions form { display: inline-flex; }
@media (max-width: 760px) { .cx-actions { justify-content: flex-end; width: auto; } #certTable td[data-label="Learner"] { justify-content: flex-start; } #certTable td[data-label="Learner"]::before { display: none; } }
</style>
<main class="main-content">
    <?php ax_header('Certificates', '', 'Unlock, verify & issue', '<a class="ax-btn ghost" href="badges"><i class="fas fa-medal"></i> Badges &amp; XP</a><button class="ax-btn" type="button" data-open="issueDlg"><i class="fas fa-plus"></i> Issue certificate</button>'); ?>
    <div class="ax-body">
        <?php ax_alerts(); ?>
        <p class="ax-intro">Learners get a certificate automatically when they finish a course or learning path. If a certificate fee applies
            (default <?= e(money($defaultFee)) ?>, or the course's own fee) it stays <b>locked</b> until the learner pays it. You can mark it as paid,
            waive the fee, revoke it, or issue one yourself.</p>

        <div class="ax-stats cx-stats">
            <?= ax_stat('lock-open', number_format($counts['approved']), 'Unlocked', 'Verifiable with their code', 'green') ?>
            <?= ax_stat('lock', number_format($counts['pending']), 'Awaiting payment', ax_money($owed) . ' in unpaid fees', 'amber') ?>
            <?= ax_stat('ban', number_format($counts['revoked']), 'Revoked', 'Codes no longer verify', 'red') ?>
            <?= ax_stat('sack-dollar', ax_money($revenue), 'Certificate revenue', number_format($revenueN) . ' verified fee payment' . ($revenueN === 1 ? '' : 's'), 'violet') ?>
            <?= ax_stat('route', number_format($counts['path']), 'Path certificates', 'Free reward for a full path', '') ?>
        </div>

        <section class="ax-panel">
                <div class="ax-panel-h">
                    <div><h2><i class="fas fa-certificate"></i> All certificates</h2><p>Newest first<?= $q !== '' ? ' · matching “' . e($q) . '”' : '' ?>.</p></div>
                </div>
                <div class="ax-panel-b" style="padding-bottom:0">
                    <div class="ax-toolbar">
                        <nav class="ax-tabs" aria-label="Filter certificates">
                            <?php foreach ($tabs as $k => $label): ?>
                                <a href="<?= e($qs(['tab' => $k])) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($label) ?> <span class="n"><?= $counts[$k] ?></span></a>
                            <?php endforeach; ?>
                        </nav>
                        <form class="ax-search" method="get" role="search">
                            <i class="fas fa-search"></i>
                            <?php if ($tab !== 'all'): ?><input type="hidden" name="tab" value="<?= e($tab) ?>"><?php endif; ?>
                            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Learner, course or code…" aria-label="Search certificates">
                        </form>
                    </div>
                </div>
                <div class="ax-table-wrap">
                    <table class="ax-table cards" id="certTable">
                        <thead><tr><th>Learner</th><th>Course / path</th><th>Code</th><th>Issued · score</th><th>Fee</th><th>Paid</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($certs as $c):
                            [$pc, $pl, $pi] = $statusPill[$c['status']] ?? ['', ucfirst((string)$c['status']), 'circle'];
                            $isPath = $c['certificate_type'] === 'path';
                            $title = $isPath ? $c['path_title'] : $c['course_title'];
                            $waived = (float)$c['fee'] == 0 && stripos((string)$c['notes'], 'waived') !== false; ?>
                            <tr>
                                <td data-label="Learner">
                                    <div class="ax-who"><?= ax_avatar($c['student_name'] ?? '?', $c['avatar'] ?? null) ?>
                                        <div><b><?= e($c['student_name'] ?? 'Deleted learner') ?></b><small><?= e($c['student_email'] ?? '—') ?></small></div></div>
                                </td>
                                <td data-label="Course / path"><div><div class="cx-title"><?= e($title ?: '—') ?></div><div class="cx-type"><?= $isPath ? 'Learning path' : 'Course' ?> · <?= e($c['issued_by_role'] === 'admin' ? 'by ' . ($c['issuer_name'] ?? 'admin') : ($c['issued_by_role'] === 'tutor' ? 'by tutor' : 'automatic')) ?></div></div></td>
                                <td data-label="Code"><a class="ax-code" href="../certificate?code=<?= urlencode((string)$c['verification_code']) ?>" target="_blank" rel="noopener"><?= e($c['verification_code'] ?: '—') ?></a></td>
                                <td data-label="Issued · score" class="num"><div><?= ax_date($c['issued_at']) ?><div class="muted">Score <?= $c['score'] !== null ? rtrim(rtrim(number_format((float)$c['score'], 1), '0'), '.') . '%' : '—' ?></div></div></td>
                                <td data-label="Fee" class="num"><?= (float)$c['fee'] > 0 ? e(money((float)$c['fee'])) : ($waived ? '<span class="ax-pill info">Waived</span>' : 'Free') ?></td>
                                <td data-label="Paid" class="num"><?= $c['paid_at'] ? ax_date($c['paid_at']) . ($c['paid_amount'] ? '<div class="muted">' . e(money((float)$c['paid_amount'])) . '</div>' : '') : ($c['pending_payments'] ? '<span class="ax-pill warn">Payment to check</span>' : '—') ?></td>
                                <td data-label="Status"><span class="ax-pill <?= $pc ?>"><i class="fas fa-<?= $pi ?>"></i> <?= e($pl) ?></span></td>
                                <td data-label="" class="actions">
                                    <div class="cx-actions">
                                        <?php if ($c['status'] === 'pending'): ?>
                                            <form method="post" data-confirm="Mark the <?= e(money((float)$c['fee'])) ?> fee as paid and unlock this certificate? A verified payment will be recorded.">
                                                <?= ax_csrf() ?><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="cert_id" value="<?= (int)$c['id'] ?>">
                                                <button class="ax-btn sm ok" title="Mark as paid"><i class="fas fa-money-bill-wave"></i> Mark paid</button>
                                            </form>
                                            <button type="button" class="ax-btn sm ghost" data-waive="<?= (int)$c['id'] ?>" data-who="<?= e(($c['student_name'] ?? '') . ' – ' . $title) ?>" data-fee="<?= e(money((float)$c['fee'])) ?>"><i class="fas fa-hand-holding-heart"></i> Waive</button>
                                        <?php endif; ?>
                                        <?php if ($c['status'] !== 'revoked'): ?>
                                            <form method="post" data-confirm="Revoke this certificate? Its code will stop verifying. You can restore it later." data-danger>
                                                <?= ax_csrf() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="cert_id" value="<?= (int)$c['id'] ?>">
                                                <button class="ax-btn sm danger icon" title="Revoke" aria-label="Revoke"><i class="fas fa-ban"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post"><?= ax_csrf() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="cert_id" value="<?= (int)$c['id'] ?>">
                                                <button class="ax-btn sm ghost" title="Restore"><i class="fas fa-rotate-left"></i> Restore</button></form>
                                        <?php endif; ?>
                                        <a class="ax-btn sm ghost icon" href="../certificate?code=<?= urlencode((string)$c['verification_code']) ?>" target="_blank" rel="noopener" title="View certificate" aria-label="View certificate"><i class="fas fa-eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!$certs): ?>
                        <div class="ax-empty"><i class="fas fa-certificate"></i><b><?= $q !== '' || $tab !== 'all' ? 'Nothing matches this filter' : 'No certificates yet' ?></b>Certificates appear here when learners finish a course or path, or when you issue one.</div>
                    <?php endif; ?>
                </div>
            </section>

        <div class="ax-grid">
            <div class="ax-span-4 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-star"></i> Award merit points</h2><p>Adds to the learner's merit balance.</p></div></div>
                        <form class="ax-panel-b ax-form" method="post">
                            <?= ax_csrf() ?><input type="hidden" name="action" value="award_points">
                            <div class="ax-field"><label for="mp-s">Learner</label>
                                <select id="mp-s" name="student_id" required><option value="">Choose a learner…</option>
                                    <?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= number_format((int)$s['merit_points']) ?> pts)</option><?php endforeach; ?>
                                </select></div>
                            <div class="ax-field"><label for="mp-p">Points</label><input id="mp-p" type="number" name="points" min="1" max="10000" placeholder="e.g. 50" required></div>
                            <div class="ax-field"><label for="mp-r">Reason</label><input id="mp-r" type="text" name="reason" maxlength="250" placeholder="e.g. Excellent project submission"></div>
                            <div class="ax-form-actions"><button class="ax-btn block"><i class="fas fa-star"></i> Award points</button></div>
                        </form>
                </section>
                <section class="ax-panel">
                    <div class="ax-panel-b" style="display:flex;gap:14px;align-items:center">
                        <span class="ax-icon-badge" style="background:color-mix(in srgb,var(--accent) 16%,transparent);color:var(--accent)"><i class="fas fa-medal"></i></span>
                        <div style="flex:1;min-width:0"><b style="color:var(--text-main);font-size:.9rem">Looking for badges?</b><div style="font-size:.8rem;color:var(--text-dim)">Award, create and manage badges on the Badges &amp; XP page.</div></div>
                        <a class="ax-btn sm ghost" href="badges">Open</a>
                    </div>
                </section>
            </div>
            <div class="ax-span-8 ax-stack">
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-scroll"></i> Add transcript entry</h2><p>Grades, assessments and completions on the learner's transcript.</p></div></div>
                        <form class="ax-panel-b ax-form" method="post">
                            <?= ax_csrf() ?><input type="hidden" name="action" value="add_transcript">
                            <div class="ax-row"><div class="ax-field"><label for="tr-s">Learner</label>
                                <select id="tr-s" name="student_id" required><option value="">Choose a learner…</option>
                                    <?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
                                </select></div>
                            <div class="ax-field"><label for="tr-c">Course</label>
                                <select id="tr-c" name="course_id" required><option value="">Choose a course…</option>
                                    <?php foreach ($courses as $co): ?><option value="<?= (int)$co['id'] ?>"><?= e($co['title']) ?></option><?php endforeach; ?>
                                </select></div></div>
                            <div class="ax-row"><div class="ax-field"><label for="tr-t">Entry title</label><input id="tr-t" type="text" name="entry_title" maxlength="255" placeholder="e.g. Final project assessment" required></div>
                            <div class="ax-field"><label for="tr-y">Type</label>
                                <select id="tr-y" name="entry_type"><option value="manual_entry">Manual entry</option><option value="course_completion">Course completion</option><option value="quiz_pass">Quiz / exam</option><option value="assignment_grade">Assignment grade</option></select></div></div>
                            <div class="ax-row" style="grid-template-columns:repeat(auto-fit,minmax(110px,1fr))">
                                <div class="ax-field"><label for="tr-sc">Score</label><input id="tr-sc" type="number" name="score" step="0.01" placeholder="87"></div>
                                <div class="ax-field"><label for="tr-mx">Out of</label><input id="tr-mx" type="number" name="max_score" step="0.01" placeholder="100"></div>
                                <div class="ax-field"><label for="tr-g">Grade</label><input id="tr-g" type="text" name="grade" maxlength="10" placeholder="A / B+"></div>
                                <div class="ax-field"><label for="tr-cr">Credits</label><input id="tr-cr" type="number" name="credits" step="0.5" min="0" max="10" value="1.0"></div>
                            </div>
                            <div class="ax-field"><label for="tr-n">Notes</label><input id="tr-n" type="text" name="entry_notes" placeholder="Optional remarks"></div>
                            <div class="ax-form-actions"><button class="ax-btn"><i class="fas fa-plus"></i> Add to transcript</button></div>
                        </form>
                </section>
                <section class="ax-panel">
                    <div class="ax-panel-h"><div><h2><i class="fas fa-clock-rotate-left"></i> Recent merit awards</h2></div></div>
                    <div class="ax-panel-b">
                        <?php if ($recentPoints): ?><ul class="ax-list">
                            <?php foreach ($recentPoints as $r): ?>
                                <li><span class="ax-icon-badge" style="background:color-mix(in srgb,var(--warning) 16%,transparent);color:var(--warning);width:34px;height:34px;font-size:.85rem"><i class="fas fa-star"></i></span>
                                    <div class="grow"><b style="display:block;font-size:.86rem;color:var(--text-main)"><?= e($r['name'] ?? 'Deleted learner') ?> · +<?= number_format((int)$r['points']) ?></b>
                                    <small style="color:var(--text-muted);font-size:.75rem"><?= e($r['reason']) ?> · <?= ax_ago($r['awarded_at']) ?></small></div></li>
                            <?php endforeach; ?></ul>
                        <?php else: ?><div class="ax-empty" style="padding:24px"><b>No merit points awarded yet</b></div><?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<dialog class="ax-modal" id="issueDlg" aria-labelledby="issueH">
    <div class="ax-panel-h"><div><h2 id="issueH"><i class="fas fa-certificate"></i> Issue a certificate</h2><p>Uses the normal certificate rules: a unique code, and the course fee if one applies.</p></div>
        <button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
    <form class="ax-panel-b ax-form" method="post">
        <?= ax_csrf() ?><input type="hidden" name="action" value="issue">
        <div class="ax-field"><label for="is-s">Learner</label>
            <select id="is-s" name="student_id" required><option value="">Choose a learner…</option>
                <?php foreach ($students as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['email']) ?>)</option><?php endforeach; ?>
            </select></div>
        <div class="ax-field"><label for="is-t">Course or learning path</label>
            <select id="is-t" name="target" required><option value="">Choose…</option>
                <optgroup label="Courses (fee applies unless waived)">
                    <?php foreach ($courses as $co): ?><option value="course:<?= (int)$co['id'] ?>"><?= e($co['title']) ?> – <?= e(money(certificate_fee_for($co))) ?></option><?php endforeach; ?>
                </optgroup>
                <optgroup label="Learning paths (always free)">
                    <?php foreach ($paths as $p): ?><option value="path:<?= (int)$p['id'] ?>"><?= e($p['title']) ?></option><?php endforeach; ?>
                </optgroup>
            </select></div>
        <div class="ax-field"><label for="is-n">Note (optional)</label><input id="is-n" type="text" name="notes" maxlength="500" placeholder="e.g. With distinction"></div>
        <div class="ax-field"><label class="ax-check"><input type="checkbox" name="waive" value="1"> Waive the certificate fee (unlock it now)</label>
            <div class="hint">The learner is notified and gets 100 merit points. Without a waiver, a course certificate with a fee stays locked until paid.</div></div>
        <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn"><i class="fas fa-certificate"></i> Issue certificate</button></div>
    </form>
</dialog>

<dialog class="ax-modal" id="waiveDlg" aria-labelledby="waiveH" style="width:min(460px,calc(100vw - 32px))">
    <div class="ax-panel-h"><div><h2 id="waiveH"><i class="fas fa-hand-holding-heart"></i> Waive certificate fee</h2><p data-who></p></div>
        <button type="button" class="ax-btn ghost icon" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
    <form class="ax-panel-b ax-form" method="post">
        <?= ax_csrf() ?><input type="hidden" name="action" value="waive"><input type="hidden" name="cert_id" value="">
        <p style="font-size:.88rem;color:var(--text-dim);margin-bottom:14px">The <b data-fee></b> fee is set to zero and the certificate unlocks now. A note is saved on the certificate.</p>
        <div class="ax-field"><label for="wv-r">Reason (saved in the note)</label><input id="wv-r" type="text" name="reason" maxlength="200" placeholder="e.g. Scholarship learner"></div>
        <div class="ax-form-actions"><button type="button" class="ax-btn ghost" data-close>Cancel</button><button class="ax-btn ok"><i class="fas fa-lock-open"></i> Waive &amp; unlock</button></div>
    </form>
</dialog>

<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
document.querySelectorAll('[data-waive]').forEach(function (b) {
  b.addEventListener('click', function () {
    var d = document.getElementById('waiveDlg');
    d.querySelector('[name=cert_id]').value = b.dataset.waive;
    d.querySelector('[data-who]').textContent = b.dataset.who;
    d.querySelector('[data-fee]').textContent = b.dataset.fee;
    d.showModal();
  });
});
</script>
</body></html>
