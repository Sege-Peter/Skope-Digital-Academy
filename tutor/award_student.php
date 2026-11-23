<?php
/**
 * Tutor: certificates, badges, merit points and transcript entries for a learner in the tutor's own courses.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

$student_id = (int)($_GET['id'] ?? 0);
if (!$student_id || !td_teaches_student($pdo, $tid, $student_id)) {
    td_flash('err', 'That learner is not enrolled in any of your courses.');
    td_redirect('students');
}
$back = 'award_student?id=' . $student_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if (!verifyCsrf()) throw new RuntimeException('Your session expired. Please try again.');

        if ($action === 'issue_cert') {
            $course_id = (int)($_POST['course_id'] ?? 0);
            $notes = trim((string)($_POST['notes'] ?? ''));
            if (!td_owns_course($pdo, $tid, $course_id)) throw new RuntimeException('You can only issue certificates for your own courses.');
            $ex = $pdo->prepare("SELECT id FROM certificates WHERE student_id = ? AND course_id = ?");
            $ex->execute([$student_id, $course_id]);
            if ($ex->fetch()) throw new RuntimeException('This learner already has a certificate for that course.');
            // Same fee rule as automatic certificates: with a certificate fee set, it stays locked until the learner pays it
            require_once __DIR__ . '/../includes/progress.php';
            $cr = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $cr->execute([$course_id]);
            $fee = certificate_fee_for($cr->fetch() ?: []);
            $code = new_cert_code($pdo);
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO certificates (student_id, course_id, certificate_type, verification_code, issued_at, issued_by, issued_by_role, notes, status, fee)
                           VALUES (?, ?, 'course', ?, NOW(), ?, 'tutor', ?, ?, ?)")
                ->execute([$student_id, $course_id, $code, $tid, $notes, $fee > 0 ? 'pending' : 'approved', $fee]);
            $pdo->prepare("UPDATE users SET merit_points = merit_points + 100 WHERE id = ?")->execute([$student_id]);
            $pdo->prepare("INSERT INTO point_ledger (student_id, points, reason, awarded_by) VALUES (?,100,'Certificate awarded by tutor',?)")->execute([$student_id, $tid]);
            $pdo->prepare("UPDATE enrollments SET progress_percent = 100, status = 'completed', completed_at = NOW() WHERE student_id = ? AND course_id = ?")->execute([$student_id, $course_id]);
            $pdo->commit();
            require_once __DIR__ . '/../includes/gamified_logic.php';
            awardBadgeIfEligible($student_id, 'courses_completed', $pdo);
            td_flash('ok', $fee > 0
                ? "Certificate created and the course marked as completed. The learner unlocks it by paying the certificate fee of " . money($fee) . ". Verification code: $code"
                : "Certificate issued and the course marked as completed. Verification code: $code");
        } elseif ($action === 'award_badge') {
            $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id, awarded_by, awarded_by_role, notes) VALUES (?,?,?,'tutor',?)")
                ->execute([$student_id, (int)($_POST['badge_id'] ?? 0), $tid, trim((string)($_POST['badge_notes'] ?? ''))]);
            td_flash('ok', 'Badge awarded.');
        } elseif ($action === 'award_points') {
            $points = (int)($_POST['points'] ?? 0);
            if ($points < 1 || $points > 500) throw new RuntimeException('You can award between 1 and 500 points at a time.');
            $reason = trim((string)($_POST['reason'] ?? '')) ?: 'Tutor award';
            $pdo->prepare("UPDATE users SET merit_points = merit_points + ? WHERE id = ?")->execute([$points, $student_id]);
            $pdo->prepare("INSERT INTO point_ledger (student_id, points, reason, awarded_by) VALUES (?,?,?,?)")->execute([$student_id, $points, $reason, $tid]);
            td_flash('ok', "$points points awarded.");
        } elseif ($action === 'add_transcript') {
            $course_id = (int)($_POST['course_id'] ?? 0);
            if (!td_owns_course($pdo, $tid, $course_id)) throw new RuntimeException('You can only add transcript entries for your own courses.');
            $title = trim((string)($_POST['entry_title'] ?? ''));
            if ($title === '') throw new RuntimeException('Give the transcript entry a title.');
            $type = in_array($_POST['entry_type'] ?? '', ['manual_entry', 'course_completion', 'quiz_pass', 'assignment_grade'], true) ? $_POST['entry_type'] : 'manual_entry';
            $pdo->prepare("INSERT INTO transcript_entries (student_id, course_id, entry_type, title, score, max_score, grade, credits, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$student_id, $course_id, $type, $title,
                    ($_POST['score'] ?? '') !== '' ? (float)$_POST['score'] : null, ($_POST['max_score'] ?? '') !== '' ? (float)$_POST['max_score'] : null,
                    trim((string)($_POST['grade'] ?? '')), (float)($_POST['credits'] ?? 1), trim((string)($_POST['entry_notes'] ?? '')), $tid]);
            td_flash('ok', 'Transcript entry added.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        td_flash('err', $e instanceof RuntimeException ? $e->getMessage() : 'That could not be saved. Please try again.');
        if (!$e instanceof RuntimeException) error_log('tutor award: ' . $e->getMessage());
    }
    td_redirect($back);
}

$st = $pdo->prepare("SELECT name, email, avatar, merit_points FROM users WHERE id = ? AND role = 'student'");
$st->execute([$student_id]);
$student = $st->fetch();
if (!$student) td_redirect('students');

$pageTitle = 'Certificates & awards';
$pageSub = 'For ' . $student['name'] . '. Certificates mark the course as completed and add 100 points.';
$pageCrumb = ['href' => 'student_profile?id=' . $student_id, 'label' => $student['name']];
require_once 'includes/header.php';

$enrolled = td_enrolments($pdo, $tid, ['student_id' => $student_id, 'statuses' => ['active', 'completed', 'pending']]);
$badges = $pdo->query("SELECT id, name FROM badges ORDER BY name")->fetchAll();
$st = $pdo->prepare("SELECT ce.*, co.title AS course_title FROM certificates ce JOIN courses co ON ce.course_id = co.id WHERE ce.student_id = ? AND co.tutor_id = ? ORDER BY ce.issued_at DESC");
$st->execute([$student_id, $tid]);
$certs = $st->fetchAll();
$st = $pdo->prepare("SELECT te.*, co.title AS course_title FROM transcript_entries te JOIN courses co ON te.course_id = co.id WHERE te.student_id = ? AND co.tutor_id = ? ORDER BY te.recorded_at DESC");
$st->execute([$student_id, $tid]);
$transcript = $st->fetchAll();
$csrf = csrfToken();
$courseOpts = function () use ($enrolled) {
    $o = '';
    foreach ($enrolled as $en) $o .= '<option value="' . (int)$en['course_id'] . '">' . e($en['course_title']) . '</option>';
    return $o;
};
?>
<div class="tp-grid two">
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-certificate"></i> Issue a certificate</h2><p>Marks the course as completed for this learner.</p></div></div>
        <div class="tp-card-body">
            <form method="post" action="<?= e($back) ?>" class="tp-form" data-confirm="Issue the certificate and mark the course as completed?">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="issue_cert">
                <div class="tp-field"><label for="ce-c">Course</label><select id="ce-c" name="course_id" required><?= $courseOpts() ?></select></div>
                <div class="tp-field"><label for="ce-n">Note <span class="tp-hint">(optional)</span></label><input id="ce-n" type="text" name="notes"></div>
                <button class="tp-btn primary" type="submit"><i class="fas fa-certificate"></i> Issue certificate</button>
            </form>
            <?php if ($certs): ?>
                <ul class="tp-list" style="margin-top:14px;border-top:1px solid var(--tp-border)">
                    <?php foreach ($certs as $c): ?><li><div class="tp-row no-indent" style="padding-left:0;padding-right:0"><span class="tp-row-main"><span class="tp-row-title"><?= e($c['course_title']) ?></span><span class="tp-row-meta">Code <?= e($c['verification_code']) ?> · <?= e(date('j M Y', strtotime($c['issued_at']))) ?></span></span><span class="tp-row-side"><span class="tp-pill <?= $c['status'] === 'approved' ? 'ok' : ($c['status'] === 'revoked' ? 'bad' : 'warn') ?>"><?= e(ucfirst($c['status'])) ?></span></span></div></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <div class="tp-stack">
        <section class="tp-card">
            <div class="tp-card-head"><div><h2><i class="fas fa-medal"></i> Award a badge</h2></div></div>
            <div class="tp-card-body">
                <form method="post" action="<?= e($back) ?>" class="tp-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="award_badge">
                    <div class="tp-form-row">
                        <div class="tp-field"><label for="bd-b">Badge</label><select id="bd-b" name="badge_id" required><?php foreach ($badges as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="tp-field"><label for="bd-n">Reason <span class="tp-hint">(optional)</span></label><input id="bd-n" type="text" name="badge_notes"></div>
                    </div>
                    <button class="tp-btn" type="submit"><i class="fas fa-medal"></i> Award badge</button>
                </form>
            </div>
        </section>
        <section class="tp-card">
            <div class="tp-card-head"><div><h2><i class="fas fa-star"></i> Award points</h2><p>Current balance: <?= number_format((int)$student['merit_points']) ?> points.</p></div></div>
            <div class="tp-card-body">
                <form method="post" action="<?= e($back) ?>" class="tp-form">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="award_points">
                    <div class="tp-form-row">
                        <div class="tp-field"><label for="pt-p">Points (1–500)</label><input id="pt-p" type="number" name="points" min="1" max="500" required></div>
                        <div class="tp-field"><label for="pt-r">Reason</label><input id="pt-r" type="text" name="reason" placeholder="e.g. Helped other learners"></div>
                    </div>
                    <button class="tp-btn" type="submit"><i class="fas fa-plus"></i> Award points</button>
                </form>
            </div>
        </section>
    </div>
</div>

<div class="tp-grid two" style="margin-top:18px">
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-file-lines"></i> Add a transcript entry</h2></div></div>
        <div class="tp-card-body">
            <form method="post" action="<?= e($back) ?>" class="tp-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_transcript">
                <div class="tp-form-row">
                    <div class="tp-field"><label for="tr-c">Course</label><select id="tr-c" name="course_id" required><?= $courseOpts() ?></select></div>
                    <div class="tp-field"><label for="tr-ty">Kind</label><select id="tr-ty" name="entry_type"><option value="manual_entry">Other</option><option value="course_completion">Course completed</option><option value="quiz_pass">Quiz or test</option><option value="assignment_grade">Assignment</option></select></div>
                </div>
                <div class="tp-field"><label for="tr-t">Title</label><input id="tr-t" type="text" name="entry_title" required placeholder="e.g. Final project"></div>
                <div class="tp-form-row three">
                    <div class="tp-field"><label for="tr-s">Score</label><input id="tr-s" type="number" step="0.01" name="score"></div>
                    <div class="tp-field"><label for="tr-m">Out of</label><input id="tr-m" type="number" step="0.01" name="max_score"></div>
                    <div class="tp-field"><label for="tr-g">Grade</label><input id="tr-g" type="text" name="grade" maxlength="10" placeholder="e.g. A"></div>
                </div>
                <div class="tp-form-row">
                    <div class="tp-field"><label for="tr-cr">Credits</label><input id="tr-cr" type="number" step="0.5" name="credits" value="1"></div>
                    <div class="tp-field"><label for="tr-n">Note</label><input id="tr-n" type="text" name="entry_notes"></div>
                </div>
                <button class="tp-btn" type="submit"><i class="fas fa-plus"></i> Add entry</button>
            </form>
        </div>
    </section>
    <section class="tp-card">
        <div class="tp-card-head"><div><h2><i class="fas fa-list"></i> Transcript</h2><p><?= count($transcript) ?> entr<?= count($transcript) === 1 ? 'y' : 'ies' ?> in your courses</p></div></div>
        <?php if ($transcript): ?>
            <ul class="tp-list">
                <?php foreach ($transcript as $t): ?>
                    <li><div class="tp-row no-indent"><span class="tp-row-main"><span class="tp-row-title"><?= e($t['title']) ?></span><span class="tp-row-meta"><?= e($t['course_title']) ?> · <?= e(date('j M Y', strtotime($t['recorded_at']))) ?><?= $t['credits'] ? ' · ' . e((string)(float)$t['credits']) . ' credits' : '' ?></span></span>
                        <span class="tp-row-side"><?= $t['score'] !== null ? e((string)(float)$t['score']) . ($t['max_score'] !== null ? ' / ' . e((string)(float)$t['max_score']) : '') : '' ?> <?= $t['grade'] ? '<span class="tp-pill info">' . e($t['grade']) . '</span>' : '' ?></span></div></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?><div class="tp-empty"><i class="fas fa-file-lines"></i><h3>No transcript entries yet</h3></div><?php endif; ?>
    </section>
</div>
<?php require 'includes/footer.php'; ?>
