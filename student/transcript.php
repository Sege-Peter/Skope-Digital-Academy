<?php
/**
 * Student: Academic Transcript — view & download
 */
$pageTitle = 'My Academic Transcript';
require_once 'includes/header.php';    // sets $student, $user, $pdo

try {
    $sid = $student['id'];

    // 1. Student info
    $stmt = $pdo->prepare("SELECT u.name, u.email, u.created_at, u.points, u.avatar FROM users u WHERE u.id=?");
    $stmt->execute([$sid]);
    $me = $stmt->fetch();

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

    // 3. Transcript entries (manually added)
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
    $points_stmt = $pdo->prepare("SELECT pl.points, pl.reason, pl.awarded_at, u.name as from_name
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

    // GPA-style letter grade helper
    function getGrade($score) {
        if ($score >= 90) return ['A+', '#10B981'];
        if ($score >= 80) return ['A',  '#10B981'];
        if ($score >= 75) return ['B+', '#3B82F6'];
        if ($score >= 70) return ['B',  '#3B82F6'];
        if ($score >= 65) return ['C+', '#F59E0B'];
        if ($score >= 60) return ['C',  '#F59E0B'];
        return ['D', '#EF4444'];
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    $me = $enrollments = $transcript = $quizzes = $assignments = $certs = $badges = $point_log = [];
    $completed_courses = $passed_quizzes = $avg_score = $total_credits = $total_certs = 0;
}
?>
<?php require_once 'includes/sidebar.php'; ?>

<style>
/* ── Screen Layout ─────────────────────────────────────── */
.transcript-wrap { max-width: 1100px; }
.section-hdr { display: flex; align-items: center; gap: 14px; font-family: 'Poppins',sans-serif; font-size: 1.05rem; font-weight: 800; color: var(--dark); margin-bottom: 22px; margin-top: 40px; }
.section-hdr i { color: var(--primary); }
.section-hdr::after { content:''; flex:1; height:2px; background:linear-gradient(90deg,var(--dark-border),transparent); }
.stat-band { display: grid; grid-template-columns: repeat(5,1fr); gap: 16px; margin-bottom: 40px; }
.stat-band .box { background: white; border: 1px solid var(--dark-border); border-radius: 16px; padding: 20px; text-align: center; }
.stat-band .box .n { font-size: 1.8rem; font-weight: 900; }
.stat-band .box .l { font-size: 0.68rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
.trow { display: grid; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--bg-light); transition: 0.15s; }
.trow:hover { background: var(--bg-light); }
.grade-pill { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; font-size: 0.8rem; font-weight: 900; }
.badge-row-items { display: flex; flex-wrap: wrap; gap: 12px; }
.badge-chip { display: flex; align-items: center; gap: 8px; background:white; border:1px solid var(--dark-border); padding:10px 16px; border-radius:16px; font-size:0.85rem; font-weight:700; }

/* ── Print / Download Styles ───────────────────────────── */
@media print {
    body { background: white !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    .sidebar-wrapper, #dashSidebar, #sidebarOverlay, .admin-header, .download-bar, nav { display: none !important; }
    .print-doc { display: block !important; padding: 40px 60px; font-family: 'Georgia', serif; }
    .screen-only { display: none !important; }
}
.print-doc { display: none; }

/* ── Download Bar ──────────────────────────────────────── */
.download-bar { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid var(--dark-border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin: -20px -20px 32px; }

/* ── Responsive ─────────────────────────────────────────── */
@media(max-width:768px) { .stat-band {grid-template-columns:1fr 1fr;} }
@media(max-width:480px) { .stat-band {grid-template-columns:1fr;} }
</style>

<main class="main-content">

    <!-- Download Bar -->
    <div class="download-bar screen-only">
        <div>
            <h1 style="font-size:1.2rem;font-weight:800;">📄 Academic Transcript</h1>
            <div style="font-size:0.82rem;color:var(--text-dim);">Generated: <?= date('F j, Y') ?></div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Download / Print PDF</button>
            <a href="certificates.php" class="btn btn-ghost btn-sm"><i class="fas fa-certificate"></i> View Certificates</a>
        </div>
    </div>

    <div class="transcript-wrap screen-only">

        <!-- Student Header Card -->
        <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:24px;padding:36px;margin-bottom:32px;color:white;display:flex;align-items:center;gap:32px;flex-wrap:wrap;position:relative;overflow:hidden;">
            <div style="position:absolute;right:-40px;top:-40px;width:240px;height:240px;background:radial-gradient(circle,rgba(0,191,255,0.12),transparent 70%);border-radius:50%;pointer-events:none;"></div>
            <div style="display:flex; flex-direction:column; align-items:center; gap:12px;">
                <img src="../assets/images/Skope Digital  logo.png" style="height: 60px; filter: brightness(0) invert(1);" alt="Academy Logo">
                <div style="width:80px;height:80px;border-radius:22px;background:rgba(0,191,255,0.15);border:2px solid rgba(0,191,255,0.3);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:900;color:#00BFFF;flex-shrink:0;">
                    <?= strtoupper(substr($me['name'] ?? 'S', 0, 1)) ?>
                </div>
            </div>
            <div style="flex:1;">
                <div style="font-family:'Poppins',sans-serif;font-size:1.6rem;font-weight:900;"><?= htmlspecialchars($me['name'] ?? '') ?></div>
                <div style="opacity:0.7;margin-top:4px;"><?= htmlspecialchars($me['email'] ?? '') ?></div>
                <div style="opacity:0.5;font-size:0.8rem;margin-top:4px;">Enrolled: <?= date('F Y', strtotime($me['created_at'] ?? 'now')) ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.7rem;opacity:0.5;text-transform:uppercase;letter-spacing:2px;">Merit Points</div>
                <div style="font-size:2.5rem;font-weight:900;color:#F7941D;"><?= number_format($me['points'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Stat Band -->
        <div class="stat-band">
            <div class="box" style="border-top:3px solid #10B981;">
                <div class="n" style="color:#10B981;"><?= $completed_courses ?></div>
                <div class="l">Completed Courses</div>
            </div>
            <div class="box" style="border-top:3px solid var(--primary);">
                <div class="n" style="color:var(--primary);"><?= $passed_quizzes ?></div>
                <div class="l">Quizzes Passed</div>
            </div>
            <div class="box" style="border-top:3px solid var(--secondary);">
                <div class="n" style="color:var(--secondary);"><?= round($avg_score) ?>%</div>
                <div class="l">Avg. Quiz Score</div>
            </div>
            <div class="box" style="border-top:3px solid #8B5CF6;">
                <div class="n" style="color:#8B5CF6;"><?= $total_credits ?></div>
                <div class="l">Total Credits</div>
            </div>
            <div class="box" style="border-top:3px solid #D4AF37;">
                <div class="n" style="color:#D4AF37;"><?= $total_certs ?></div>
                <div class="l">Certificates</div>
            </div>
        </div>

        <!-- === COURSES / ENROLLMENTS === -->
        <div class="section-hdr"><i class="fas fa-book-open"></i> Course Enrolment History (<?= count($enrollments) ?>)</div>
        <div class="card" style="padding:0;overflow:hidden;">
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 80px;gap:12px;padding:12px 20px;background:var(--bg-light);font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);">
                <span>Course</span><span>Level</span><span>Enrolled</span><span>Progress</span><span>Status</span>
            </div>
            <?php if (!empty($enrollments)): foreach($enrollments as $e): ?>
            <div class="trow" style="grid-template-columns:2fr 1fr 1fr 1fr 80px;">
                <div>
                    <div style="font-weight:700;font-size:0.92rem;"><?= htmlspecialchars($e['course_title']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-dim);"><?= htmlspecialchars($e['category'] ?? '') ?> • <?= htmlspecialchars($e['tutor_name'] ?? '') ?></div>
                </div>
                <div style="font-size:0.82rem;font-weight:600;text-transform:capitalize;"><?= $e['level'] ?></div>
                <div style="font-size:0.82rem;"><?= date('M j, Y', strtotime($e['enrolled_at'])) ?></div>
                <div>
                    <div style="height:6px;background:#e2e8f0;border-radius:4px;overflow:hidden;max-width:80px;">
                        <div style="height:100%;background:var(--primary);width:<?= min(100, (int)$e['progress_percent']) ?>%;"></div>
                    </div>
                    <div style="font-size:0.72rem;font-weight:700;color:var(--primary);margin-top:2px;"><?= round($e['progress_percent']) ?>%</div>
                </div>
                <div>
                    <span class="badge <?= $e['status']==='completed'?'badge-success':($e['status']==='active'?'badge-primary':'badge-ghost') ?>"><?= ucfirst($e['status']) ?></span>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div style="padding:48px;text-align:center;color:var(--text-dim);">No course enrolments yet.</div>
            <?php endif; ?>
        </div>

        <!-- === QUIZ HISTORY === -->
        <?php if (!empty($quizzes)): ?>
        <div class="section-hdr"><i class="fas fa-tasks"></i> Assessment History (<?= count($quizzes) ?>)</div>
        <div class="card" style="padding:0;overflow:hidden;">
            <div style="display:grid;grid-template-columns:2fr 1.5fr 80px 80px 80px;gap:12px;padding:12px 20px;background:var(--bg-light);font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);">
                <span>Quiz</span><span>Course</span><span>Date</span><span>Score</span><span>Result</span>
            </div>
            <?php foreach($quizzes as $q):
                [$grade, $gColor] = getGrade($q['score']); ?>
            <div class="trow" style="grid-template-columns:2fr 1.5fr 80px 80px 80px;">
                <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($q['quiz_title']) ?></div>
                <div style="font-size:0.82rem;color:var(--text-dim);"><?= htmlspecialchars($q['course_title']) ?></div>
                <div style="font-size:0.78rem;"><?= date('M j, Y', strtotime($q['completed_at'])) ?></div>
                <div>
                    <span class="grade-pill" style="background:<?= $gColor ?>18;color:<?= $gColor ?>;"><?= $grade ?></span>
                </div>
                <div>
                    <span style="font-weight:800;color:<?= $q['passed']?'#10B981':'#EF4444' ?>;"><?= round($q['score']) ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === ASSIGNMENT GRADES === -->
        <?php if (!empty($assignments)): ?>
        <div class="section-hdr"><i class="fas fa-file-alt"></i> Graded Assignments (<?= count($assignments) ?>)</div>
        <div class="card" style="padding:0;overflow:hidden;">
            <div style="display:grid;grid-template-columns:2fr 1.5fr 80px 100px 80px;gap:12px;padding:12px 20px;background:var(--bg-light);font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);">
                <span>Assignment</span><span>Course</span><span>Submitted</span><span>Score</span><span>Grade</span>
            </div>
            <?php foreach($assignments as $a):
                $pct = $a['max_score'] > 0 ? ($a['score'] / $a['max_score']) * 100 : 0;
                [$grade, $gColor] = getGrade($pct); ?>
            <div class="trow" style="grid-template-columns:2fr 1.5fr 80px 100px 80px;">
                <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($a['assignment_title']) ?></div>
                <div style="font-size:0.82rem;color:var(--text-dim);"><?= htmlspecialchars($a['course_title']) ?></div>
                <div style="font-size:0.78rem;"><?= date('M j, Y', strtotime($a['submitted_at'])) ?></div>
                <div style="font-weight:700;"><?= round($a['score']) ?> / <?= $a['max_score'] ?></div>
                <div><span class="grade-pill" style="background:<?= $gColor ?>18;color:<?= $gColor ?>;"><?= $grade ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === TRANSCRIPT ENTRIES (Manual) === -->
        <?php if (!empty($transcript)): ?>
        <div class="section-hdr"><i class="fas fa-scroll"></i> Official Transcript Entries (<?= count($transcript) ?>)</div>
        <div class="card" style="padding:0;overflow:hidden;">
            <div style="display:grid;grid-template-columns:2fr 1.5fr 60px 100px 80px 80px;gap:12px;padding:12px 20px;background:var(--bg-light);font-size:0.72rem;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);">
                <span>Entry</span><span>Course</span><span>Credits</span><span>Score</span><span>Grade</span><span>Date</span>
            </div>
            <?php foreach($transcript as $t): ?>
            <div class="trow" style="grid-template-columns:2fr 1.5fr 60px 100px 80px 80px;">
                <div>
                    <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($t['title']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-dim);">By <?= htmlspecialchars($t['recorded_by_name'] ?? 'System') ?></div>
                </div>
                <div style="font-size:0.82rem;color:var(--text-dim);"><?= htmlspecialchars($t['course_title']) ?></div>
                <div style="font-weight:700;font-size:0.88rem;"><?= $t['credits'] ?></div>
                <div style="font-weight:700;"><?= $t['score'] !== null ? $t['score'].'/'.$t['max_score'] : '—' ?></div>
                <div>
                    <?php if ($t['grade']): ?>
                    <span class="badge badge-primary"><?= htmlspecialchars($t['grade']) ?></span>
                    <?php else: echo '—'; endif; ?>
                </div>
                <div style="font-size:0.78rem;"><?= date('M j, Y', strtotime($t['recorded_at'])) ?></div>
            </div>
            <?php endforeach; ?>
            <div style="padding:12px 20px;background:var(--bg-light);display:flex;justify-content:flex-end;font-size:0.82rem;font-weight:800;border-top:1px solid var(--dark-border);">
                Total Credits Accumulated: <span style="color:var(--primary);margin-left:8px;"><?= $total_credits ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- === CERTIFICATES === -->
        <?php if (!empty($certs)): ?>
        <div class="section-hdr"><i class="fas fa-certificate"></i> Certificates Awarded (<?= $total_certs ?>)</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-bottom:16px;">
            <?php foreach($certs as $c): ?>
            <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:20px;padding:24px;color:white;position:relative;overflow:hidden;">
                <div style="position:absolute;right:-20px;top:-20px;font-size:6rem;opacity:0.06;pointer-events:none;">🎓</div>
                <div style="font-size:0.7rem;opacity:0.5;text-transform:uppercase;letter-spacing:2px;">Certificate of Completion</div>
                <div style="font-weight:800;font-size:1.05rem;margin-top:8px;margin-bottom:4px;"><?= htmlspecialchars($c['course_title']) ?></div>
                <div style="font-size:0.78rem;opacity:0.6;">Issued by <?= htmlspecialchars($c['issued_by_name'] ?? 'Academy') ?> • <?= date('M j, Y', strtotime($c['issued_at'])) ?></div>
                <?php if ($c['notes']): ?>
                <div style="margin-top:8px;font-size:0.78rem;color:#F7941D;"><?= htmlspecialchars($c['notes']) ?></div>
                <?php endif; ?>
                <div style="margin-top:12px;font-size:0.68rem;opacity:0.4;font-family:monospace;"><?= $c['verification_code'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === BADGES === -->
        <?php if (!empty($badges)): ?>
        <div class="section-hdr"><i class="fas fa-medal"></i> Merit Badges (<?= count($badges) ?>)</div>
        <div class="badge-row-items" style="margin-bottom:40px;">
            <?php foreach($badges as $b): ?>
            <div class="badge-chip">
                <span style="font-size:1.3rem;"><?= $b['icon'] ?></span>
                <div>
                    <div style="font-size:0.82rem;font-weight:800;"><?= htmlspecialchars($b['name']) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-dim);"><?= date('M j, Y', strtotime($b['awarded_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === POINTS LOG === -->
        <?php if (!empty($point_log)): ?>
        <div class="section-hdr"><i class="fas fa-star"></i> Merit Points Log (Recent 20)</div>
        <div class="card" style="padding:0;overflow:hidden;margin-bottom:60px;">
            <?php foreach($point_log as $pl): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid var(--bg-light);">
                <div>
                    <div style="font-weight:600;font-size:0.88rem;"><?= htmlspecialchars($pl['reason']) ?></div>
                    <div style="font-size:0.72rem;color:var(--text-dim);">By <?= htmlspecialchars($pl['from_name'] ?? 'System') ?> • <?= date('M j, Y', strtotime($pl['awarded_at'])) ?></div>
                </div>
                <div style="font-weight:900;font-size:1.1rem;color:var(--secondary);">+<?= $pl['points'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.transcript-wrap -->
</main>

<!-- ═══════════════ PRINT DOCUMENT ═══════════════════════════════════ -->
<div class="print-doc" id="printDoc">
<style>
#printDoc { font-family:'Georgia',serif; color:#111; max-width:900px; margin:0 auto; }
.pt-header { text-align:center; border-bottom:3px double #0f172a; margin-bottom:32px; padding-bottom:24px; }
.pt-logo { font-size:1.4rem; font-weight:900; letter-spacing:2px; text-transform:uppercase; color:#0f172a; }
.pt-title { font-size:2rem; font-weight:700; margin:8px 0; color:#00AEEF; }
.pt-subtitle { font-size:0.85rem; letter-spacing:3px; text-transform:uppercase; color:#666; }
.pt-student { margin-bottom:32px; background:#f8fafc; padding:20px 28px; border-left:4px solid #00AEEF; }
.pt-student h2 { font-size:1.4rem; margin:0 0 6px; }
.pt-section { margin-bottom:28px; }
.pt-section h3 { font-size:0.9rem; text-transform:uppercase; letter-spacing:2px; border-bottom:1px solid #ddd; padding-bottom:8px; margin-bottom:12px; color:#555; }
.pt-table { width:100%; border-collapse:collapse; font-size:0.88rem; }
.pt-table th { text-align:left; padding:8px 12px; background:#f1f5f9; font-size:0.75rem; text-transform:uppercase; letter-spacing:1px; }
.pt-table td { padding:8px 12px; border-bottom:1px solid #f1f5f9; }
.pt-footer { text-align:center; font-size:0.7rem; color:#aaa; border-top:1px solid #ddd; margin-top:40px; padding-top:16px; }
.pg { text-align:center; margin-bottom:20px; font-size:0.75rem; color:#aaa; }
</style>

<div class="pt-header">
    <div class="pt-logo">Skope Digital Academy</div>
    <div class="pt-title">Official Academic Transcript</div>
    <div class="pt-subtitle">Confidential Educational Record</div>
</div>

<div class="pt-student">
    <h2><?= htmlspecialchars($me['name'] ?? '') ?></h2>
    <div><?= htmlspecialchars($me['email'] ?? '') ?></div>
    <div style="font-size:0.82rem;color:#555;margin-top:4px;">
        Enrolled: <?= date('F j, Y', strtotime($me['created_at'] ?? 'now')) ?> | 
        Total Merit Points: <?= number_format($me['points'] ?? 0) ?> |
        Credits: <?= $total_credits ?> |
        Certificates: <?= $total_certs ?>
    </div>
    <div style="font-size:0.78rem;color:#888;margin-top:4px;">Issued: <?= date('F j, Y \a\t g:i a') ?></div>
</div>

<!-- Courses -->
<div class="pt-section">
    <h3>Course Enrolment</h3>
    <table class="pt-table">
        <thead><tr><th>Course</th><th>Level</th><th>Tutor</th><th>Enrolled</th><th>Progress</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($enrollments as $e): ?>
        <tr>
            <td><?= htmlspecialchars($e['course_title']) ?></td>
            <td><?= ucfirst($e['level']) ?></td>
            <td><?= htmlspecialchars($e['tutor_name'] ?? '') ?></td>
            <td><?= date('M j, Y', strtotime($e['enrolled_at'])) ?></td>
            <td><?= round($e['progress_percent']) ?>%</td>
            <td><?= ucfirst($e['status']) ?></td>
        </tr>
        <?php endforeach; if (empty($enrollments)): ?>
        <tr><td colspan="6" style="text-align:center;color:#aaa;">No enrolments on record.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Transcript Entries -->
<?php if (!empty($transcript)): ?>
<div class="pt-section">
    <h3>Official Transcript Entries</h3>
    <table class="pt-table">
        <thead><tr><th>Entry</th><th>Course</th><th>Score</th><th>Grade</th><th>Credits</th><th>Recorded By</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach($transcript as $t): ?>
        <tr>
            <td><?= htmlspecialchars($t['title']) ?></td>
            <td><?= htmlspecialchars($t['course_title']) ?></td>
            <td><?= $t['score'] !== null ? $t['score'].'/'.$t['max_score'] : '—' ?></td>
            <td><?= htmlspecialchars($t['grade'] ?: '—') ?></td>
            <td><?= $t['credits'] ?></td>
            <td><?= htmlspecialchars($t['recorded_by_name'] ?? 'System') ?></td>
            <td><?= date('M j, Y', strtotime($t['recorded_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:#f8fafc;font-weight:700;"><td colspan="4" style="text-align:right;">Total Credits:</td><td><?= $total_credits ?></td><td colspan="2"></td></tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Quiz History -->
<?php if (!empty($quizzes)): ?>
<div class="pt-section">
    <h3>Assessment Results</h3>
    <table class="pt-table">
        <thead><tr><th>Quiz</th><th>Course</th><th>Score</th><th>Grade</th><th>Result</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach($quizzes as $q): [$grade,$gColor] = getGrade($q['score']); ?>
        <tr>
            <td><?= htmlspecialchars($q['quiz_title']) ?></td>
            <td><?= htmlspecialchars($q['course_title']) ?></td>
            <td><?= round($q['score']) ?>%</td>
            <td><?= $grade ?></td>
            <td><?= $q['passed'] ? 'PASS' : 'FAIL' ?></td>
            <td><?= date('M j, Y', strtotime($q['completed_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Certificates -->
<?php if (!empty($certs)): ?>
<div class="pt-section">
    <h3>Certificates Awarded</h3>
    <table class="pt-table">
        <thead><tr><th>Course</th><th>Issued By</th><th>Notes</th><th>Code</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach($certs as $c): ?>
        <tr>
            <td><?= htmlspecialchars($c['course_title']) ?></td>
            <td><?= htmlspecialchars($c['issued_by_name'] ?? 'Academy') ?></td>
            <td><?= htmlspecialchars($c['notes'] ?? '—') ?></td>
            <td style="font-family:monospace;font-size:0.75rem;"><?= $c['verification_code'] ?></td>
            <td><?= date('M j, Y', strtotime($c['issued_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Badges -->
<?php if (!empty($badges)): ?>
<div class="pt-section">
    <h3>Merit Badges</h3>
    <table class="pt-table">
        <thead><tr><th>Badge</th><th>Awarded On</th></tr></thead>
        <tbody>
        <?php foreach($badges as $b): ?>
        <tr>
            <td><?= $b['icon'] ?> <?= htmlspecialchars($b['name']) ?></td>
            <td><?= date('M j, Y', strtotime($b['awarded_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="pt-footer">
    This is an official academic transcript of Skope Digital Academy. <br>
    Verified records for <?= htmlspecialchars($me['name'] ?? '') ?> — Generated <?= date('F j, Y \a\t g:i a') ?><br>
    For verification, contact admin@skopedigital.ac.ke
</div>
</div><!-- /#printDoc -->

<script src="../assets/js/main.js"></script>
<script>
window.addEventListener('beforeprint', function() {
    document.getElementById('printDoc').style.display = 'block';
});
window.addEventListener('afterprint', function() {
    document.getElementById('printDoc').style.display = 'none';
});
</script>
</body></html>
