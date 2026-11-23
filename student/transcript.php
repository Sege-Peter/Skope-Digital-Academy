<?php
/**
 * Student: transcript (academic record) - view, print / save as PDF
 */
$pageTitle = 'Transcript';
require_once 'includes/header.php';    // sets $student, $user, $pdo

// Letter grade for a percentage: [letter, print colour, badge colour]
function getGrade($score) {
    $score = (float)$score;
    if ($score >= 90) return ['A+', '#0B8A4F', 'green'];
    if ($score >= 80) return ['A',  '#0B8A4F', 'green'];
    if ($score >= 75) return ['B+', '#0077C2', ''];
    if ($score >= 70) return ['B',  '#0077C2', ''];
    if ($score >= 65) return ['C+', '#A16207', 'yellow'];
    if ($score >= 60) return ['C',  '#A16207', 'yellow'];
    return ['D', '#C53030', 'red'];
}

try {
    $sid = $student['id'];

    // 1. Student info
    $stmt = $pdo->prepare("SELECT u.name, u.email, u.created_at, u.merit_points, u.avatar FROM users u WHERE u.id=?");
    $stmt->execute([$sid]);
    $me = $stmt->fetch() ?: [];

    // 2. All enrollments + progress
    $enr_stmt = $pdo->prepare("SELECT e.*, c.title as course_title, c.level, c.duration_hours, c.total_lessons,
        cat.name as category, u.name as tutor_name, e.enrolled_at, e.completed_at
        FROM enrollments e
        JOIN courses c ON e.course_id=c.id
        LEFT JOIN categories cat ON c.category_id=cat.id
        LEFT JOIN users u ON c.tutor_id=u.id
        WHERE e.student_id=? ORDER BY e.enrolled_at DESC");
    $enr_stmt->execute([$sid]);
    $enrollments = $enr_stmt->fetchAll();

    // 3. Transcript entries (recorded results)
    $trans_stmt = $pdo->prepare("SELECT te.*, c.title as course_title, c.level, u.name as recorded_by_name
        FROM transcript_entries te
        JOIN courses c ON te.course_id=c.id
        LEFT JOIN users u ON te.recorded_by=u.id
        WHERE te.student_id=? ORDER BY te.recorded_at DESC");
    $trans_stmt->execute([$sid]);
    $transcript = $trans_stmt->fetchAll();

    // 4. Quiz attempts
    $quiz_stmt = $pdo->prepare("SELECT qa.score, qa.passed, qa.completed_at, q.title as quiz_title, q.pass_score, c.title as course_title
        FROM quiz_attempts qa
        JOIN quizzes q ON qa.quiz_id=q.id
        JOIN courses c ON q.course_id=c.id
        WHERE qa.student_id=? ORDER BY qa.completed_at DESC");
    $quiz_stmt->execute([$sid]);
    $quizzes = $quiz_stmt->fetchAll();

    // 5. Assignment submissions
    $asn_stmt = $pdo->prepare("SELECT asub.score, asub.status, asub.submitted_at, asub.graded_at,
        a.title as assignment_title, a.max_score, c.title as course_title
        FROM assignment_submissions asub
        JOIN assignments a ON asub.assignment_id=a.id
        JOIN courses c ON a.course_id=c.id
        WHERE asub.student_id=? AND asub.status='graded' ORDER BY asub.graded_at DESC");
    $asn_stmt->execute([$sid]);
    $assignments = $asn_stmt->fetchAll();

    // 6. Certificates
    $cert_stmt = $pdo->prepare("SELECT ce.issued_at, ce.verification_code, ce.notes, ce.status,
        c.title as course_title, tu.name as tutor_name, iss.name as issued_by_name, ce.issued_by_role
        FROM certificates ce
        JOIN courses c ON ce.course_id=c.id
        LEFT JOIN users tu ON c.tutor_id=tu.id
        LEFT JOIN users iss ON ce.issued_by=iss.id
        WHERE ce.student_id=? AND ce.status='approved' ORDER BY ce.issued_at DESC");
    $cert_stmt->execute([$sid]);
    $certs = $cert_stmt->fetchAll();

    // 7. Badges
    $badge_stmt = $pdo->prepare("SELECT b.name, b.icon, b.description, sb.awarded_at
        FROM student_badges sb JOIN badges b ON sb.badge_id=b.id
        WHERE sb.student_id=? ORDER BY sb.awarded_at DESC");
    $badge_stmt->execute([$sid]);
    $badges = $badge_stmt->fetchAll();

    // 8. Point ledger
    $points_stmt = $pdo->prepare("SELECT pl.points AS merit_points, pl.reason, pl.awarded_at, u.name as from_name
        FROM point_ledger pl LEFT JOIN users u ON pl.awarded_by=u.id
        WHERE pl.student_id=? ORDER BY pl.awarded_at DESC LIMIT 20");
    $points_stmt->execute([$sid]);
    $point_log = $points_stmt->fetchAll();

    // Stats
    $completed_courses = count(array_filter($enrollments, fn($e) => $e['status'] === 'completed'));
    $passed_quizzes    = count(array_filter($quizzes, fn($q) => $q['passed']));
    $avg_score         = count($quizzes) > 0 ? array_sum(array_column($quizzes, 'score')) / count($quizzes) : 0;
    $total_credits     = array_sum(array_column($transcript, 'credits'));
    $total_certs       = count($certs);

} catch (Exception $e) {
    error_log($e->getMessage());
    $me = [];
    $enrollments = $transcript = $quizzes = $assignments = $certs = $badges = $point_log = [];
    $completed_courses = $passed_quizzes = $avg_score = $total_credits = $total_certs = 0;
}

// Helpers for display
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
$fmtDate = fn($d, $f = 'j M Y') => !empty($d) ? date($f, strtotime($d)) : '—';
$badgeIcon = function ($icon) use ($h) {
    $icon = trim((string)$icon);
    if ($icon !== '' && preg_match('/^fa-[a-z0-9-]+$/', $icon)) return '<i class="fas ' . $icon . '"></i>';
    return $icon !== '' ? $h($icon) : '<i class="fas fa-medal"></i>';
};
// Result for a transcript entry: [score text, grade letter, badge colour, print colour]
$entryResult = function ($t) {
    $score = $t['score'];
    $max = (float)($t['max_score'] ?? 0);
    if ($score === null) {
        return ['—', $t['grade'] ?: '—', 'gray', '#475569'];
    }
    $pct = $max > 0 ? ((float)$score / $max) * 100 : (float)$score;
    [$letter, $hex, $cls] = getGrade($pct);
    $scoreText = $max > 0 ? rtrim(rtrim(number_format((float)$score, 2), '0'), '.') . ' / ' . rtrim(rtrim(number_format($max, 2), '0'), '.') : round((float)$score) . '%';
    return [$scoreText, $t['grade'] ?: $letter, $cls, $hex];
};
$me_name   = $me['name'] ?? ($student['name'] ?? '');
$me_email  = $me['email'] ?? ($student['email'] ?? '');
$me_points = (int)($me['merit_points'] ?? 0);
$initials  = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($me_name)) ?: [], 0, 2)))) ?: 'S';
$record_id = 'SDA-RT-' . strtoupper(substr(md5((string)$me_email), 0, 8));
$course_ids_with_entry = array_column($transcript, 'course_id');
$completed_without_entry = array_filter($enrollments, fn($e) => $e['status'] === 'completed' && !in_array($e['course_id'], $course_ids_with_entry));

require_once 'includes/layout-top.php';
?>

<style>
.pg-transcript .tr-profile { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
.pg-transcript .tr-profile .sp-avatar { width: 60px; height: 60px; font-size: 1.2rem; }
.pg-transcript .tr-profile h2 { margin: 0; font-size: 1.15rem; font-weight: 800; }
.pg-transcript .tr-profile p { margin: 2px 0 0; color: var(--text-dim); font-size: .86rem; }
.pg-transcript .tr-points { margin-left: auto; text-align: right; }
.pg-transcript .tr-points strong { display: block; font-size: 1.6rem; font-weight: 800; color: var(--secondary); line-height: 1.1; }
.pg-transcript .tr-points span { font-size: .78rem; color: var(--text-dim); font-weight: 600; }
.pg-transcript .tr-grade { min-width: 38px; justify-content: center; font-size: .8rem; }
.pg-transcript .sp-table td.num, .pg-transcript .sp-table th.num { text-align: right; white-space: nowrap; }
.pg-transcript .sp-table tfoot td { background: var(--surface-2); font-weight: 800; border-top: 1px solid var(--border); border-bottom: 0; }
.pg-transcript .tr-course small { display: block; color: var(--text-dim); font-size: .76rem; margin-top: 2px; }
.pg-transcript .tr-prog { display: flex; align-items: center; gap: 10px; min-width: 130px; }
.pg-transcript .tr-prog .sp-progress { flex: 1; }
.pg-transcript .tr-prog span.pct { font-size: .78rem; font-weight: 700; color: var(--text-dim); width: 36px; text-align: right; }
.pg-transcript .tr-badge { display: flex; align-items: center; gap: 12px; }
.pg-transcript .tr-badge .sp-ico { font-size: 1.2rem; }
.pg-transcript .tr-badge h4 { margin: 0; font-size: .9rem; font-weight: 700; }
.pg-transcript .tr-badge p { margin: 2px 0 0; font-size: .76rem; color: var(--text-dim); }
.pg-transcript code.tr-code { font-size: .76rem; background: var(--surface-2); border: 1px solid var(--border); padding: 2px 8px; border-radius: 6px; }
@media (max-width: 640px) { .pg-transcript .tr-points { margin-left: 0; text-align: left; } }

/* Printable document: hidden on screen */
.print-doc { display: none; }

@media print {
    @page { size: A4; margin: 12mm; }
    body, body.student-portal { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sidebar, .sp-topbar, .sidebar-overlay, .screen-only { display: none !important; }
    .main-content { margin-left: 0 !important; padding: 0 !important; min-height: 0 !important; background: #fff !important; }
    .student-portal .dashboard-wrapper { max-width: none !important; }
    .student-portal .dashboard-wrapper > main { padding: 0 !important; }
    .print-doc { display: block !important; }
}

/* Printable transcript layout */
#printDoc { font-family: 'Inter', Arial, sans-serif; color: #1a1a1b; line-height: 1.35; font-size: 11px; }
#printDoc h1, #printDoc h2, #printDoc h3 { font-family: 'Inter', Arial, sans-serif; color: #1a1a1b; letter-spacing: 0; }
#printDoc .ts-header { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 14px; }
#printDoc .ts-school h1 { font-size: 20px; font-weight: 900; margin: 0; text-transform: uppercase; }
#printDoc .ts-school h2 { font-size: 12px; font-weight: 600; color: #4b5563; margin: 2px 0 4px; }
#printDoc .ts-school div { font-size: 9px; color: #6b7280; }
#printDoc .ts-logo { height: 54px; filter: grayscale(1); }
#printDoc .ts-student { border: 1px solid #4b5563; padding: 8px; font-size: 10px; }
#printDoc .ts-student div { padding: 1px 0; }
#printDoc .ts-student label { display: inline-block; width: 80px; font-size: 8.5px; color: #6b7280; font-weight: 700; }
#printDoc .ts-body { display: grid; grid-template-columns: 1fr 210px; gap: 18px; }
#printDoc .ts-h { border-bottom: 1.5px solid #000; padding-bottom: 3px; margin: 12px 0 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
#printDoc .ts-h:first-child { margin-top: 0; }
#printDoc table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
#printDoc th { text-align: left; padding: 4px 6px; background: #f3f4f6; font-size: 8.5px; text-transform: uppercase; border-bottom: 1px solid #d1d5db; }
#printDoc td { padding: 4px 6px; border-bottom: 0.5px solid #e5e7eb; }
#printDoc .r { text-align: right; }
#printDoc .c { text-align: center; }
#printDoc .muted { color: #6b7280; }
#printDoc .ts-box { border: 1.5px solid #1a1a1b; margin-bottom: 10px; break-inside: avoid; }
#printDoc .ts-box-title { background: #1a1a1b; color: #fff; padding: 3px 8px; font-size: 9px; font-weight: 800; text-transform: uppercase; }
#printDoc .ts-box-body { padding: 7px 8px; }
#printDoc .ts-kv { display: flex; justify-content: space-between; padding: 2px 0; border-bottom: 0.5px solid #eee; }
#printDoc .ts-kv:last-child { border-bottom: 0; }
#printDoc .ts-chip { display: inline-block; font-size: 9px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 4px; padding: 2px 6px; margin: 0 4px 4px 0; }
#printDoc .ts-foot { margin-top: 16px; border-top: 1.5px solid #000; padding-top: 10px; display: grid; grid-template-columns: 1fr 170px; gap: 18px; align-items: end; break-inside: avoid; }
#printDoc .ts-sign { text-align: center; }
#printDoc .ts-sign-line { height: 1px; background: #000; margin: 26px 0 5px; }
#printDoc .ts-remarks { border: 1px solid #d1d5db; padding: 8px; margin-top: 10px; font-style: italic; white-space: pre-line; }
</style>

<div class="pg-transcript">

<div class="sp-page-head screen-only">
    <div>
        <h1>Transcript</h1>
        <p>Your courses, results, certificates and badges in one record. Print it or save it as a PDF.</p>
    </div>
    <div class="sp-actions">
        <button type="button" id="aiCommendBtn" onclick="generateAiCommendation()" class="sp-btn ghost"><i class="fas fa-wand-magic-sparkles"></i> Write a summary</button>
        <a href="certificates" class="sp-btn ghost"><i class="fas fa-certificate"></i> Certificates</a>
        <button type="button" onclick="window.print()" class="sp-btn"><i class="fas fa-print"></i> Print / save PDF</button>
    </div>
</div>

<div class="screen-only">

    <!-- Student -->
    <div class="sp-card sp-section tr-profile">
        <span class="sp-avatar"><?= $h($initials) ?></span>
        <div>
            <h2><?= $h($me_name) ?></h2>
            <p><?= $h($me_email) ?></p>
            <p class="sp-small"><i class="far fa-calendar"></i> Member since <?= $fmtDate($me['created_at'] ?? null, 'F Y') ?> &middot; Record ID <?= $h($record_id) ?></p>
        </div>
        <div class="tr-points">
            <strong><?= number_format($me_points) ?></strong>
            <span>Merit points</span>
        </div>
    </div>

    <!-- AI summary (filled by "Write a summary") -->
    <div id="aiCommendBox" class="sp-alert" style="display: none;">
        <i class="fas fa-wand-magic-sparkles" style="margin-top: 3px;"></i>
        <div><strong>Summary</strong><div id="aiCommendText" style="white-space: pre-line; margin-top: 4px;"></div></div>
    </div>

    <!-- Summary stats -->
    <section class="sp-grid cols-4 sp-section">
        <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-circle-check"></i></span><div><strong><?= (int)$completed_courses ?></strong><span>Courses completed</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-circle-question"></i></span><div><strong><?= (int)$passed_quizzes ?></strong><span>Quizzes passed</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon orange"><i class="fas fa-percent"></i></span><div><strong><?= count($quizzes) ? round($avg_score) . '%' : '—' ?></strong><span>Average quiz score</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon violet"><i class="fas fa-layer-group"></i></span><div><strong><?= number_format((float)$total_credits, 1) ?></strong><span>Credits earned</span></div></div>
        <div class="sp-stat"><span class="sp-stat-icon yellow"><i class="fas fa-award"></i></span><div><strong><?= (int)$total_certs ?></strong><span>Certificates</span></div></div>
    </section>

    <!-- Recorded results -->
    <section class="sp-section">
        <div class="sp-section-head">
            <div><h2>Results</h2><p>Final results recorded for your courses</p></div>
            <span class="sp-badge gray"><?= count($transcript) ?> <?= count($transcript) === 1 ? 'entry' : 'entries' ?></span>
        </div>
        <?php if (empty($transcript)): ?>
            <div class="sp-empty">
                <div class="sp-ico"><i class="fas fa-file-lines"></i></div>
                <h3>No results yet</h3>
                <p>When you finish a course, your final result and credits will be recorded here.</p>
                <a href="courses" class="sp-btn">Go to my courses</a>
            </div>
        <?php else: ?>
            <div class="sp-table-wrap">
                <table class="sp-table">
                    <thead><tr><th>Course</th><th>Entry</th><th class="num">Credits</th><th class="num">Score</th><th class="num">Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($transcript as $t): [$scoreText, $grade, $gCls] = $entryResult($t); ?>
                        <tr>
                            <td class="tr-course"><strong><?= $h($t['course_title']) ?></strong><small>Recorded <?= $fmtDate($t['recorded_at']) ?><?= !empty($t['recorded_by_name']) ? ' by ' . $h($t['recorded_by_name']) : '' ?></small></td>
                            <td><?= $h($t['title']) ?></td>
                            <td class="num"><?= number_format((float)$t['credits'], 1) ?></td>
                            <td class="num"><?= $h($scoreText) ?></td>
                            <td class="num"><span class="sp-badge tr-grade <?= $gCls ?>"><?= $h($grade) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><td colspan="2">Total credits</td><td class="num"><?= number_format((float)$total_credits, 1) ?></td><td colspan="2"></td></tr></tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Courses -->
    <section class="sp-section">
        <div class="sp-section-head">
            <div><h2>Courses</h2><p>Every course you have joined and how far you got</p></div>
            <span class="sp-badge gray"><?= count($enrollments) ?></span>
        </div>
        <?php if (empty($enrollments)): ?>
            <div class="sp-empty">
                <div class="sp-ico"><i class="fas fa-book-open"></i></div>
                <h3>No courses yet</h3>
                <p>Join a course and it will show up on your transcript.</p>
                <a href="../courses" class="sp-btn">Browse courses</a>
            </div>
        <?php else: ?>
            <div class="sp-table-wrap">
                <table class="sp-table">
                    <thead><tr><th>Course</th><th>Level</th><th>Joined</th><th>Progress</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($enrollments as $e):
                        $pct = $e['status'] === 'completed' ? 100 : min(100, max(0, (int)round((float)($e['progress_percent'] ?? 0))));
                        $stCls = $e['status'] === 'completed' ? 'green' : ($e['status'] === 'active' ? '' : 'gray');
                        $meta = array_filter([$e['category'] ?? '', $e['tutor_name'] ?? '']);
                    ?>
                        <tr>
                            <td class="tr-course"><strong><?= $h($e['course_title']) ?></strong><?php if ($meta): ?><small><?= $h(implode(' · ', $meta)) ?></small><?php endif; ?></td>
                            <td><?= $h(ucfirst((string)($e['level'] ?? ''))) ?></td>
                            <td style="white-space: nowrap;"><?= $fmtDate($e['enrolled_at']) ?></td>
                            <td><div class="tr-prog"><div class="sp-progress <?= $pct >= 100 ? 'green' : '' ?>"><span style="width: <?= max(2, $pct) ?>%;"></span></div><span class="pct"><?= $pct ?>%</span></div></td>
                            <td><span class="sp-badge <?= $stCls ?>"><?= $h(ucfirst((string)$e['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- Quizzes -->
    <?php if (!empty($quizzes)): ?>
    <section class="sp-section">
        <div class="sp-section-head">
            <div><h2>Quizzes</h2><p>Every quiz you have taken</p></div>
            <span class="sp-badge gray"><?= count($quizzes) ?></span>
        </div>
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead><tr><th>Quiz</th><th>Course</th><th>Date</th><th class="num">Score</th><th class="num">Grade</th><th>Result</th></tr></thead>
                <tbody>
                <?php foreach ($quizzes as $q): [$grade, , $gCls] = getGrade($q['score']); ?>
                    <tr>
                        <td><strong><?= $h($q['quiz_title']) ?></strong></td>
                        <td class="sp-muted"><?= $h($q['course_title']) ?></td>
                        <td style="white-space: nowrap;"><?= $fmtDate($q['completed_at']) ?></td>
                        <td class="num"><?= round((float)$q['score']) ?>%</td>
                        <td class="num"><span class="sp-badge tr-grade <?= $gCls ?>"><?= $grade ?></span></td>
                        <td><span class="sp-badge <?= $q['passed'] ? 'green' : 'red' ?>"><?= $q['passed'] ? 'Passed' : 'Not passed' ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <!-- Assignments -->
    <?php if (!empty($assignments)): ?>
    <section class="sp-section">
        <div class="sp-section-head">
            <div><h2>Assignments</h2><p>Marked assignments and their grades</p></div>
            <span class="sp-badge gray"><?= count($assignments) ?></span>
        </div>
        <div class="sp-table-wrap">
            <table class="sp-table">
                <thead><tr><th>Assignment</th><th>Course</th><th>Submitted</th><th class="num">Score</th><th class="num">Grade</th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $a):
                    $apct = $a['max_score'] > 0 ? ($a['score'] / $a['max_score']) * 100 : 0;
                    [$grade, , $gCls] = getGrade($apct); ?>
                    <tr>
                        <td><strong><?= $h($a['assignment_title']) ?></strong></td>
                        <td class="sp-muted"><?= $h($a['course_title']) ?></td>
                        <td style="white-space: nowrap;"><?= $fmtDate($a['submitted_at']) ?></td>
                        <td class="num"><?= round((float)$a['score']) ?> / <?= (int)$a['max_score'] ?></td>
                        <td class="num"><span class="sp-badge tr-grade <?= $gCls ?>"><?= $grade ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <!-- Certificates -->
    <?php if (!empty($certs)): ?>
    <section class="sp-section">
        <div class="sp-section-head">
            <div><h2>Certificates</h2><p>Course certificates you have unlocked</p></div>
            <a href="certificates" class="sp-link">All certificates <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="sp-card" style="padding: 6px 18px;">
            <div class="sp-list">
            <?php foreach ($certs as $c): ?>
                <div class="sp-list-item">
                    <span class="sp-ico green"><i class="fas fa-award"></i></span>
                    <div class="sp-grow">
                        <h4><?= $h($c['course_title']) ?></h4>
                        <p>Issued <?= $fmtDate($c['issued_at']) ?><?= !empty($c['issued_by_name']) ? ' by ' . $h($c['issued_by_name']) : '' ?><?= !empty($c['notes']) ? ' · ' . $h($c['notes']) : '' ?></p>
                    </div>
                    <code class="tr-code sp-hide-sm"><?= $h($c['verification_code']) ?></code>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Badges -->
    <?php if (!empty($badges)): ?>
    <section class="sp-section">
        <div class="sp-section-head">
            <div><h2>Badges</h2><p>Badges you have earned</p></div>
            <a href="achievements" class="sp-link">Achievements <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="sp-grid cols-3">
            <?php foreach ($badges as $b): ?>
            <div class="sp-card tr-badge">
                <span class="sp-ico violet"><?= $badgeIcon($b['icon']) ?></span>
                <div class="sp-grow"><h4><?= $h($b['name']) ?></h4><p>Earned <?= $fmtDate($b['awarded_at']) ?></p></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Merit points -->
    <?php if (!empty($point_log)): ?>
    <section class="sp-section">
        <div class="sp-section-head"><div><h2>Merit points</h2><p>Your 20 most recent awards</p></div></div>
        <div class="sp-card" style="padding: 6px 18px;">
            <div class="sp-list">
            <?php foreach ($point_log as $pl): ?>
                <div class="sp-list-item">
                    <span class="sp-ico orange"><i class="fas fa-star"></i></span>
                    <div class="sp-grow"><h4><?= $h($pl['reason']) ?></h4><p>From <?= $h($pl['from_name'] ?? 'Skope Digital Academy') ?> · <?= $fmtDate($pl['awarded_at']) ?></p></div>
                    <span class="sp-badge orange">+<?= (int)$pl['merit_points'] ?></span>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

</div><!-- /.screen-only -->

<!-- ═══════════ PRINTABLE TRANSCRIPT (shown only when printing) ═══════════ -->
<div class="print-doc" id="printDoc">
    <div class="ts-header">
        <div class="ts-school">
            <h1>Skope Digital Academy</h1>
            <h2>Academic transcript</h2>
            <div>Kisumu, Kenya &middot; Support: 0742380183</div>
        </div>
        <div><img src="../assets/images/Skope Digital  logo.png" class="ts-logo" alt=""></div>
        <div class="ts-student">
            <div><label>Name</label> <strong><?= $h($me_name) ?></strong></div>
            <div><label>Email</label> <?= $h($me_email) ?></div>
            <div><label>Record ID</label> <?= $h($record_id) ?></div>
            <div><label>Member since</label> <?= $fmtDate($me['created_at'] ?? null, 'F Y') ?></div>
            <div><label>Printed</label> <?= date('j F Y') ?></div>
        </div>
    </div>

    <div class="ts-body">
        <div>
            <div class="ts-h">Course results</div>
            <table>
                <thead><tr><th>Course</th><th>Entry</th><th class="c">Credits</th><th class="c">Score</th><th class="r">Grade</th></tr></thead>
                <tbody>
                <?php foreach ($transcript as $t): [$scoreText, $grade, , $hex] = $entryResult($t); ?>
                    <tr>
                        <td><strong><?= $h($t['course_title']) ?></strong></td>
                        <td><?= $h($t['title']) ?></td>
                        <td class="c"><?= number_format((float)$t['credits'], 1) ?></td>
                        <td class="c"><?= $h($scoreText) ?></td>
                        <td class="r" style="font-weight: 800; color: <?= $hex ?>;"><?= $h($grade) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($completed_without_entry as $e): ?>
                    <tr>
                        <td><strong><?= $h($e['course_title']) ?></strong></td>
                        <td>Course completed<?= !empty($e['completed_at']) ? ' ' . $fmtDate($e['completed_at']) : '' ?></td>
                        <td class="c">—</td>
                        <td class="c">—</td>
                        <td class="r" style="font-weight: 800;">Pass</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($transcript) && empty($completed_without_entry)): ?>
                    <tr><td colspan="5" class="c muted">No course results recorded yet.</td></tr>
                <?php else: ?>
                    <tr><td colspan="2" style="font-weight: 800;">Total credits</td><td class="c" style="font-weight: 800;"><?= number_format((float)$total_credits, 1) ?></td><td colspan="2"></td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <div class="ts-h">Courses joined</div>
            <table>
                <thead><tr><th>Course</th><th>Level</th><th>Joined</th><th class="c">Progress</th><th class="r">Status</th></tr></thead>
                <tbody>
                <?php if (!empty($enrollments)): foreach ($enrollments as $e):
                    $pct = $e['status'] === 'completed' ? 100 : min(100, max(0, (int)round((float)($e['progress_percent'] ?? 0)))); ?>
                    <tr>
                        <td><?= $h($e['course_title']) ?></td>
                        <td><?= $h(ucfirst((string)($e['level'] ?? ''))) ?></td>
                        <td><?= $fmtDate($e['enrolled_at']) ?></td>
                        <td class="c"><?= $pct ?>%</td>
                        <td class="r"><?= $h(ucfirst((string)$e['status'])) ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5" class="c muted">No courses joined yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($quizzes)): ?>
            <div class="ts-h">Quizzes</div>
            <table>
                <thead><tr><th>Quiz</th><th>Course</th><th>Date</th><th class="c">Score</th><th class="r">Grade</th></tr></thead>
                <tbody>
                <?php foreach ($quizzes as $q): [$grade, $hex] = getGrade($q['score']); ?>
                    <tr>
                        <td><?= $h($q['quiz_title']) ?></td>
                        <td><?= $h($q['course_title']) ?></td>
                        <td><?= $fmtDate($q['completed_at']) ?></td>
                        <td class="c"><?= round((float)$q['score']) ?>%</td>
                        <td class="r" style="font-weight: 800; color: <?= $hex ?>;"><?= $grade ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (!empty($assignments)): ?>
            <div class="ts-h">Assignments</div>
            <table>
                <thead><tr><th>Assignment</th><th>Course</th><th>Submitted</th><th class="c">Score</th><th class="r">Grade</th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $a):
                    $apct = $a['max_score'] > 0 ? ($a['score'] / $a['max_score']) * 100 : 0;
                    [$grade, $hex] = getGrade($apct); ?>
                    <tr>
                        <td><?= $h($a['assignment_title']) ?></td>
                        <td><?= $h($a['course_title']) ?></td>
                        <td><?= $fmtDate($a['submitted_at']) ?></td>
                        <td class="c"><?= round((float)$a['score']) ?> / <?= (int)$a['max_score'] ?></td>
                        <td class="r" style="font-weight: 800; color: <?= $hex ?>;"><?= $grade ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div id="tsPrintRemarks" style="display: none;">
                <div class="ts-h">Summary</div>
                <div class="ts-remarks" id="printCommendText"></div>
            </div>
        </div>

        <div>
            <div class="ts-box">
                <div class="ts-box-title">Summary</div>
                <div class="ts-box-body">
                    <div class="ts-kv"><span>Courses completed</span><strong><?= (int)$completed_courses ?></strong></div>
                    <div class="ts-kv"><span>Credits earned</span><strong><?= number_format((float)$total_credits, 1) ?></strong></div>
                    <div class="ts-kv"><span>Quizzes passed</span><strong><?= (int)$passed_quizzes ?> of <?= count($quizzes) ?></strong></div>
                    <div class="ts-kv"><span>Average quiz score</span><strong><?= count($quizzes) ? round($avg_score) . '%' : '—' ?></strong></div>
                    <div class="ts-kv"><span>Certificates</span><strong><?= (int)$total_certs ?></strong></div>
                    <div class="ts-kv"><span>Merit points</span><strong><?= number_format($me_points) ?></strong></div>
                </div>
            </div>

            <?php if (!empty($certs)): ?>
            <div class="ts-box">
                <div class="ts-box-title">Certificates</div>
                <div class="ts-box-body">
                    <?php foreach ($certs as $c): ?>
                    <div style="padding: 3px 0; border-bottom: 0.5px solid #eee;">
                        <strong><?= $h($c['course_title']) ?></strong><br>
                        <span class="muted"><?= $fmtDate($c['issued_at']) ?> &middot; <?= $h($c['verification_code']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="ts-box">
                <div class="ts-box-title">Badges</div>
                <div class="ts-box-body">
                    <?php if (!empty($badges)): foreach ($badges as $b): ?>
                        <span class="ts-chip"><?= $h($b['name']) ?></span>
                    <?php endforeach; else: ?>
                        <span class="muted">No badges yet.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ts-box">
                <div class="ts-box-title">Grading scale</div>
                <div class="ts-box-body">
                    <div class="ts-kv"><span>90–100%</span><strong>A+</strong></div>
                    <div class="ts-kv"><span>80–89%</span><strong>A</strong></div>
                    <div class="ts-kv"><span>75–79%</span><strong>B+</strong></div>
                    <div class="ts-kv"><span>70–74%</span><strong>B</strong></div>
                    <div class="ts-kv"><span>65–69%</span><strong>C+</strong></div>
                    <div class="ts-kv"><span>60–64%</span><strong>C</strong></div>
                    <div class="ts-kv"><span>Below 60%</span><strong>D</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="ts-foot">
        <div class="muted" style="font-size: 9px;">
            This transcript was generated from Skope Digital Academy records on <?= date('j F Y') ?>.
            Certificates can be checked online with their verification code.
        </div>
        <div class="ts-sign">
            <div class="ts-sign-line"></div>
            <strong>Academy Registrar</strong>
        </div>
    </div>
</div><!-- /#printDoc -->

</div><!-- /.pg-transcript -->

<script>
async function generateAiCommendation() {
    const btn = document.getElementById('aiCommendBtn');
    const box = document.getElementById('aiCommendBox');
    const textDiv = document.getElementById('aiCommendText');
    const printTextDiv = document.getElementById('printCommendText');
    const printRemarks = document.getElementById('tsPrintRemarks');
    const studentName = <?= json_encode($me_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const metrics = {
        completed: <?= (int)$completed_courses ?>,
        quiz_avg: <?= (int)round($avg_score) ?>,
        merit_points: <?= $me_points ?>,
        credits: <?= json_encode((float)$total_credits) ?>
    };

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Writing…';

    try {
        const response = await fetch('ai-proxy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'transcript_summary',
                type: 'ask',
                query: 'Write a short, warm, professional summary (3 to 4 sentences, plain text, no headings) of this student\'s progress for their academic transcript. '
                    + 'Student: ' + studentName + '. Courses completed: ' + metrics.completed + '. Average quiz score: ' + metrics.quiz_avg + '%. '
                    + 'Merit points: ' + metrics.merit_points + '. Credits earned: ' + metrics.credits + '.',
                student_name: studentName,
                metrics: metrics
            })
        });

        const data = await response.json();
        const text = data.response || data.reply;
        if (text && !data.error) {
            box.style.display = '';
            printRemarks.style.display = 'block';
            textDiv.innerText = text;
            printTextDiv.innerText = text;
            btn.innerHTML = '<i class="fas fa-check"></i> Summary added';
        } else {
            throw new Error(data.error || 'No summary came back.');
        }
    } catch (error) {
        console.error(error);
        if (window.SDA && SDA.showToast) SDA.showToast('Could not write a summary: ' + error.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i> Try again';
    }
}
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
