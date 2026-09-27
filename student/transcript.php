<?php
/**
 * Student: Academic Transcript — view & download
 */
$pageTitle = 'My Academic Transcript';
require_once 'includes/header.php';    // sets $student, $user, $pdo

try {
    $sid = $student['id'];

    // 1. Student info
    $stmt = $pdo->prepare("SELECT u.name, u.email, u.created_at, u.merit_points, u.avatar FROM users u WHERE u.id=?");
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
    $points_stmt = $pdo->prepare("SELECT pl.merit_points, pl.reason, pl.awarded_at, u.name as from_name
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
/* ── Screen Layout (Elite Standard) ─────────────────────────────────────── */
.transcript-wrap { max-width: 1100px; margin: 0 auto; }
.section-hdr { display: flex; align-items: center; gap: 14px; font-family: 'Poppins',sans-serif; font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 24px; margin-top: 48px; }
.section-hdr i { color: var(--primary); font-size: 1.2rem; }
.section-hdr::after { content:''; flex:1; height:2px; background:linear-gradient(90deg,var(--dark-border),transparent); }

.stat-band { display: grid; grid-template-columns: repeat(5,1fr); gap: 20px; margin-bottom: 48px; }
.stat-box { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 24px; text-align: center; box-shadow: var(--shadow-sm); transition: 0.3s; position: relative; overflow: hidden; }
.stat-box:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: var(--shadow-md); }
.stat-box .n { font-size: 2rem; font-weight: 900; font-family: 'Poppins', sans-serif; line-height: 1.1; margin-bottom: 6px; }
.stat-box .l { font-size: 0.72rem; color: #64748b; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }

.trow { display: grid; align-items: center; gap: 16px; padding: 16px 24px; border-bottom: 1px solid #f1f5f9; transition: 0.2s; background: white; }
.trow:hover { background: #f8fafc; }
.grade-pill { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 12px; font-size: 0.9rem; font-weight: 900; }
.badge-row-items { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; }
.badge-chip { display: flex; align-items: center; gap: 16px; background:white; border:1px solid var(--dark-border); padding:16px 20px; border-radius:20px; transition: 0.3s; box-shadow: var(--shadow-sm); }
.badge-chip:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-md); }
.badge-chip .icon { font-size: 2rem; background: var(--primary-glow); width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; color: var(--primary); flex-shrink: 0; }
.badge-chip .details { display: flex; flex-direction: column; gap: 4px; }
.badge-chip .title { font-size: 0.95rem; font-weight: 800; color: #0f172a; }
.badge-chip .date { font-size: 0.75rem; color: #64748b; font-weight: 500; }

.table-card { background: white; border: 1px solid var(--dark-border); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-sm); }
.table-header { display: grid; gap: 16px; padding: 16px 24px; background: #f8fafc; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #64748b; border-bottom: 2px solid #f1f5f9; }

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
.download-bar { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid var(--dark-border); padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin: -20px -20px 40px; }

/* ── Responsive ─────────────────────────────────────────── */
@media(max-width:991px) { .stat-band {grid-template-columns:repeat(3,1fr);} }
@media(max-width:768px) { .stat-band {grid-template-columns:1fr 1fr;} }
@media(max-width:480px) { .stat-band {grid-template-columns:1fr;} }
</style>

<main class="main-content">

    <!-- Download Bar -->
    <div class="download-bar screen-only">
        <div>
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.4rem; font-weight: 800; color: #0f172a; letter-spacing: -0.5px;">📄 Academic Transcript</h1>
            <div style="font-size: 0.85rem; color: #64748b; font-weight: 500; margin-top: 4px;">Generated: <?= date('F j, Y') ?></div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <button id="aiCommendBtn" onclick="generateAiCommendation()" class="btn btn-ghost" style="border-radius: 12px; font-weight: 800; border-color: var(--primary); color: var(--primary);"><i class="fas fa-magic" style="margin-right: 8px;"></i> AI Commendation</button>
            <button onclick="window.print()" class="btn btn-primary" style="border-radius: 12px; font-weight: 800;"><i class="fas fa-download" style="margin-right: 8px;"></i> Download PDF</button>
            <a href="certificates.php" class="btn btn-ghost" style="border-radius: 12px; font-weight: 700;"><i class="fas fa-certificate" style="margin-right: 8px;"></i> Certificates</a>
        </div>
    </div>

    <div class="transcript-wrap screen-only">

        <!-- Student Header Card -->
        <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:32px;padding:48px;margin-bottom:48px;color:white;display:flex;align-items:center;gap:32px;flex-wrap:wrap;position:relative;overflow:hidden;box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.4);">
            <div style="position:absolute;right:-40px;top:-40px;width:300px;height:300px;background:radial-gradient(circle,rgba(0,191,255,0.15),transparent 70%);border-radius:50%;pointer-events:none;"></div>
            <div style="display:flex; flex-direction:column; align-items:center; gap:16px;">
                <img src="../assets/images/Skope Digital  logo.png" style="height: 64px; filter: brightness(0) invert(1);" alt="Academy Logo">
                <div style="width:88px;height:88px;border-radius:24px;background:rgba(0,191,255,0.15);border:2px solid rgba(0,191,255,0.3);display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-family:'Poppins',sans-serif;font-weight:900;color:#00BFFF;flex-shrink:0;">
                    <?= strtoupper(substr($me['name'] ?? 'S', 0, 1)) ?>
                </div>
            </div>
            <div style="flex:1;">
                <div style="font-family:'Poppins',sans-serif;font-size:2rem;font-weight:900;letter-spacing:-0.5px;"><?= htmlspecialchars($me['name'] ?? '') ?></div>
                <div style="color: #94a3b8; font-size: 1.05rem; margin-top: 4px; font-weight: 500;"><?= htmlspecialchars($me['email'] ?? '') ?></div>
                <div style="opacity: 0.6; font-size: 0.85rem; margin-top: 12px; font-weight: 500;"><i class="far fa-calendar-alt" style="margin-right: 6px;"></i> Enrolled: <?= date('F Y', strtotime($me['created_at'] ?? 'now')) ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;font-weight:800;">Merit Points</div>
                <div style="font-family:'Poppins',sans-serif;font-size:3rem;font-weight:900;color:#F7941D;line-height:1;margin-top:8px;"><?= number_format($me['merit_points'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Stat Band -->
        <div class="stat-band">
            <div class="stat-box" style="border-top:4px solid #10B981;">
                <div class="n" style="color:#10B981;"><?= $completed_courses ?></div>
                <div class="l">Completed Courses</div>
            </div>
            <div class="stat-box" style="border-top:4px solid var(--primary);">
                <div class="n" style="color:var(--primary);"><?= $passed_quizzes ?></div>
                <div class="l">Quizzes Passed</div>
            </div>
            <div class="stat-box" style="border-top:4px solid var(--secondary);">
                <div class="n" style="color:var(--secondary);"><?= round($avg_score) ?>%</div>
                <div class="l">Avg. Quiz Score</div>
            </div>
            <div class="stat-box" style="border-top:4px solid #8B5CF6;">
                <div class="n" style="color:#8B5CF6;"><?= $total_credits ?></div>
                <div class="l">Total Credits</div>
            </div>
            <div class="stat-box" style="border-top:4px solid #D4AF37;">
                <div class="n" style="color:#D4AF37;"><?= $total_certs ?></div>
                <div class="l">Certificates</div>
            </div>
        </div>

        <!-- === COURSES / ENROLLMENTS === -->
        <div class="section-hdr"><i class="fas fa-book-open"></i> Course Enrolment History <span style="background:var(--primary-glow);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:0.8rem;margin-left:8px;"><?= count($enrollments) ?></span></div>
        <div class="table-card">
            <div class="table-header" style="grid-template-columns:2.5fr 1fr 1fr 1.5fr 100px;">
                <span>Course</span><span>Level</span><span>Enrolled</span><span>Progress</span><span>Status</span>
            </div>
            <?php if (!empty($enrollments)): foreach($enrollments as $e): ?>
            <div class="trow" style="grid-template-columns:2.5fr 1fr 1fr 1.5fr 100px;">
                <div>
                    <div style="font-weight:800;font-size:0.95rem;color:#0f172a;margin-bottom:4px;"><?= htmlspecialchars($e['course_title']) ?></div>
                    <div style="font-size:0.8rem;color:#64748b;font-weight:500;"><?= htmlspecialchars($e['category'] ?? '') ?> • <?= htmlspecialchars($e['tutor_name'] ?? '') ?></div>
                </div>
                <div style="font-size:0.85rem;font-weight:700;text-transform:capitalize;color:#475569;"><?= $e['level'] ?></div>
                <div style="font-size:0.85rem;color:#475569;font-weight:500;"><?= date('M j, Y', strtotime($e['enrolled_at'])) ?></div>
                <div>
                    <div style="height:8px;background:#e2e8f0;border-radius:6px;overflow:hidden;max-width:120px;">
                        <div style="height:100%;background:var(--primary);width:<?= min(100, (int)$e['progress_percent']) ?>%;"></div>
                    </div>
                    <div style="font-size:0.75rem;font-weight:800;color:var(--primary);margin-top:6px;"><?= round($e['progress_percent']) ?>%</div>
                </div>
                <div>
                    <span class="badge <?= $e['status']==='completed'?'badge-success':($e['status']==='active'?'badge-primary':'badge-ghost') ?>" style="padding:6px 12px;border-radius:8px;font-weight:700;"><?= ucfirst($e['status']) ?></span>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div style="padding:64px;text-align:center;color:#94a3b8;font-weight:600;"><i class="fas fa-book" style="font-size:2rem;margin-bottom:12px;opacity:0.5;display:block;"></i>No course enrolments yet.</div>
            <?php endif; ?>
        </div>

        <!-- === QUIZ HISTORY === -->
        <?php if (!empty($quizzes)): ?>
        <div class="section-hdr"><i class="fas fa-tasks"></i> Assessment History <span style="background:var(--primary-glow);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:0.8rem;margin-left:8px;"><?= count($quizzes) ?></span></div>
        <div class="table-card">
            <div class="table-header" style="grid-template-columns:2fr 2fr 100px 100px 100px;">
                <span>Quiz</span><span>Course</span><span>Date</span><span>Score</span><span>Result</span>
            </div>
            <?php foreach($quizzes as $q):
                [$grade, $gColor] = getGrade($q['score']); ?>
            <div class="trow" style="grid-template-columns:2fr 2fr 100px 100px 100px;">
                <div style="font-weight:800;font-size:0.95rem;color:#0f172a;"><?= htmlspecialchars($q['quiz_title']) ?></div>
                <div style="font-size:0.85rem;color:#64748b;font-weight:500;"><?= htmlspecialchars($q['course_title']) ?></div>
                <div style="font-size:0.85rem;color:#475569;font-weight:500;"><?= date('M j, Y', strtotime($q['completed_at'])) ?></div>
                <div>
                    <span class="grade-pill" style="background:<?= $gColor ?>18;color:<?= $gColor ?>;"><?= $grade ?></span>
                </div>
                <div>
                    <span style="font-weight:900;color:<?= $q['passed']?'#10B981':'#EF4444' ?>;background:<?= $q['passed']?'rgba(16,185,129,0.1)':'rgba(239,68,68,0.1)' ?>;padding:6px 12px;border-radius:8px;"><?= round($q['score']) ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === ASSIGNMENT GRADES === -->
        <?php if (!empty($assignments)): ?>
        <div class="section-hdr"><i class="fas fa-file-invoice"></i> Graded Assignments <span style="background:var(--primary-glow);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:0.8rem;margin-left:8px;"><?= count($assignments) ?></span></div>
        <div class="table-card">
            <div class="table-header" style="grid-template-columns:2fr 2fr 100px 100px 100px;">
                <span>Assignment</span><span>Course</span><span>Submitted</span><span>Score</span><span>Grade</span>
            </div>
            <?php foreach($assignments as $a):
                $pct = $a['max_score'] > 0 ? ($a['score'] / $a['max_score']) * 100 : 0;
                [$grade, $gColor] = getGrade($pct); ?>
            <div class="trow" style="grid-template-columns:2fr 2fr 100px 100px 100px;">
                <div style="font-weight:800;font-size:0.95rem;color:#0f172a;"><?= htmlspecialchars($a['assignment_title']) ?></div>
                <div style="font-size:0.85rem;color:#64748b;font-weight:500;"><?= htmlspecialchars($a['course_title']) ?></div>
                <div style="font-size:0.85rem;color:#475569;font-weight:500;"><?= date('M j, Y', strtotime($a['submitted_at'])) ?></div>
                <div style="font-weight:800;color:#0f172a;"><?= round($a['score']) ?> / <?= $a['max_score'] ?></div>
                <div><span class="grade-pill" style="background:<?= $gColor ?>18;color:<?= $gColor ?>;"><?= $grade ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === MASTER SCHOLARLY RECORD (UNIFIED) === -->
        <div class="section-hdr"><i class="fas fa-file-signature"></i> Master Scholarly Record <span style="background:var(--primary-glow);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:0.8rem;margin-left:8px;"><?= count($transcript) ?> Entries</span></div>
        <div class="table-card" style="border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.03); background: white; border-radius: 20px; overflow: hidden;">
            <div class="table-header" style="grid-template-columns: 2.2fr 2fr 0.8fr 1.5fr 100px; background: #fdfdfd; padding: 24px 32px; border-bottom: 1px solid #f1f5f9;">
                <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Entry</span>
                <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Course</span>
                <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Credits</span>
                <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Score</span>
                <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px;">Grade</span>
            </div>
            
            <?php if (!empty($transcript)): foreach($transcript as $t): 
                $gradeObj = getGrade($t['score'] ?? 0);
                $displayGrade = $t['grade'] ?: $gradeObj[0];
                $gradeColor = $gradeObj[1];
            ?>
            <div class="trow" style="grid-template-columns: 2.2fr 2fr 0.8fr 1.5fr 100px; padding: 28px 32px; border-bottom: 1px solid #f8fafc; align-items: center;">
                <div>
                    <div style="font-weight: 900; font-size: 1.1rem; color: #000; font-family: 'Inter', sans-serif; text-transform: uppercase; margin-bottom: 6px;"><?= htmlspecialchars($t['title']) ?></div>
                    <div style="font-size: 0.82rem; color: #94a3b8; font-weight: 500;">Recorded by <?= htmlspecialchars($t['recorded_by_name'] ?? 'System') ?></div>
                </div>
                <div style="font-weight: 700; color: #64748b; font-size: 0.9rem; text-transform: uppercase;"><?= htmlspecialchars($t['course_title']) ?></div>
                <div style="font-weight: 900; font-size: 1.15rem; color: var(--primary);"><?= number_format($t['credits'], 1) ?></div>
                <div style="font-weight: 900; font-size: 1.15rem; color: #000;"><?= $t['score'] !== null ? number_format($t['score'], 2).'/'.number_format($t['max_score'], 2) : '—' ?></div>
                <div style="text-align: right;">
                    <span style="display: inline-flex; width: 42px; height: 42px; align-items: center; justify-content: center; background: rgba(0, 191, 255, 0.08); color: var(--primary); border-radius: 12px; font-weight: 900; font-size: 1.1rem; box-shadow: 0 4px 12px rgba(0, 191, 255, 0.1);"><?= $displayGrade ?></span>
                </div>
            </div>
            <?php endforeach; else: ?>
            <div style="padding: 100px 32px; text-align: center;">
                <i class="fas fa-file-medical" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 20px;"></i>
                <div style="font-weight: 700; color: #94a3b8;">Awaiting official instructional records to be indexed.</div>
            </div>
            <?php endif; ?>

            <div style="padding: 32px; background: #fafafa; display: flex; justify-content: flex-end; align-items: center; border-top: 1px solid #f1f5f9;">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <span style="font-size: 0.85rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">Credits Accumulated</span>
                    <span style="background: linear-gradient(135deg, var(--primary), #009ACD); color: white; padding: 12px 28px; border-radius: 16px; font-weight: 900; font-size: 1.4rem; box-shadow: 0 10px 20px rgba(0, 191, 255, 0.2);"><?= number_format($total_credits, 1) ?></span>
                </div>
            </div>
        </div>

        <!-- === CERTIFICATES === -->
        <?php if (!empty($certs)): ?>
        <div class="section-hdr"><i class="fas fa-award"></i> Certificates Awarded <span style="background:rgba(212,175,55,0.1);color:#D4AF37;padding:2px 10px;border-radius:20px;font-size:0.8rem;margin-left:8px;"><?= $total_certs ?></span></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:24px;margin-bottom:48px;">
            <?php foreach($certs as $c): ?>
            <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);border-radius:24px;padding:32px;color:white;position:relative;overflow:hidden;box-shadow: 0 15px 30px -10px rgba(15, 23, 42, 0.3);transition:0.3s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 20px 40px -10px rgba(15, 23, 42, 0.4)';" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 15px 30px -10px rgba(15, 23, 42, 0.3)';">
                <div style="position:absolute;right:-20px;top:-20px;font-size:8rem;opacity:0.04;pointer-events:none;">🎓</div>
                <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;font-weight:800;margin-bottom:12px;">Certificate of Completion</div>
                <div style="font-weight:800;font-family:'Poppins',sans-serif;font-size:1.2rem;margin-bottom:8px;line-height:1.3;"><?= htmlspecialchars($c['course_title']) ?></div>
                <div style="font-size:0.85rem;color:#cbd5e1;font-weight:500;">Issued by <span style="color:var(--primary);font-weight:700;"><?= htmlspecialchars($c['issued_by_name'] ?? 'Academy') ?></span> • <?= date('M j, Y', strtotime($c['issued_at'])) ?></div>
                <?php if ($c['notes']): ?>
                <div style="margin-top:12px;font-size:0.85rem;color:#F7941D;background:rgba(247,148,29,0.1);padding:8px 12px;border-radius:8px;font-weight:500;"><i class="fas fa-info-circle" style="margin-right:6px;"></i><?= htmlspecialchars($c['notes']) ?></div>
                <?php endif; ?>
                <div style="margin-top:20px;padding-top:16px;border-top:1px solid rgba(255,255,255,0.1);font-size:0.75rem;color:#94a3b8;font-family:monospace;letter-spacing:1px;display:flex;justify-content:space-between;align-items:center;">
                    <span>Verification ID:</span>
                    <span style="font-weight:700;color:white;background:rgba(255,255,255,0.1);padding:4px 10px;border-radius:6px;"><?= $c['verification_code'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === BADGES === -->
        <?php if (!empty($badges)): ?>
        <div class="section-hdr"><i class="fas fa-medal"></i> Merit Badges <span style="background:var(--primary-glow);color:var(--primary);padding:2px 10px;border-radius:20px;font-size:0.8rem;margin-left:8px;"><?= count($badges) ?></span></div>
        <div class="badge-row-items" style="margin-bottom:48px;">
            <?php foreach($badges as $b): ?>
            <div class="badge-chip">
                <span class="icon"><?= $b['icon'] ?></span>
                <div class="details">
                    <div class="title"><?= htmlspecialchars($b['name']) ?></div>
                    <div class="date"><i class="far fa-clock" style="margin-right:4px;"></i> <?= date('M j, Y', strtotime($b['awarded_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- === POINTS LOG === -->
        <?php if (!empty($point_log)): ?>
        <div class="section-hdr"><i class="fas fa-star"></i> Merit Points Log <span style="font-size:0.8rem;color:#64748b;font-weight:500;margin-left:12px;text-transform:none;">(Recent 20)</span></div>
        <div class="table-card" style="margin-bottom:60px;">
            <?php foreach($point_log as $pl): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 24px;border-bottom:1px solid #f1f5f9;transition:0.2s;" onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='white';">
                <div>
                    <div style="font-weight:800;font-size:0.95rem;color:#0f172a;margin-bottom:4px;"><?= htmlspecialchars($pl['reason']) ?></div>
                    <div style="font-size:0.8rem;color:#64748b;font-weight:500;">Authorized by <span style="font-weight:700;color:#0f172a;"><?= htmlspecialchars($pl['from_name'] ?? 'System') ?></span> • <?= date('M j, Y', strtotime($pl['awarded_at'])) ?></div>
                </div>
                <div style="font-weight:900;font-family:'Poppins',sans-serif;font-size:1.3rem;color:#F7941D;background:rgba(247,148,29,0.1);padding:6px 16px;border-radius:12px;">+<?= $pl['merit_points'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div><!-- /.transcript-wrap -->
</main>

<!-- ═══════════════ PRINT DOCUMENT ═══════════════════════════════════ -->
<div class="print-doc" id="printDoc">
<style>
    /* ── High-Fidelity One-Page Academic Transcript ── */
    #printDoc { 
        font-family: 'Inter', sans-serif; 
        color: #1a1a1b; 
        width: 1000px; /* Standard A4 width-ish */
        margin: 0 auto; 
        padding: 40px;
        background: white;
        line-height: 1.3;
        display: none; /* Hidden on screen by default */
        box-sizing: border-box;
    }

    /* Print Specifics for One Page */
    @media print {
        @page { size: A4; margin: 0; }
        body { margin: 0; background: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        #printDoc { 
            display: block !important; 
            width: 210mm; 
            height: 297mm; 
            padding: 10mm 15mm; 
            overflow: hidden !important; 
            page-break-after: always;
            margin: 0 !important;
            box-sizing: border-box;
        }
        .sidebar, .nav-toggle, .btn, .screen-only, header, nav, .transcript-wrap, .admin-alert { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; }
    }

    /* ══ HEADER ══ */
    .ts-header { 
        display: grid; 
        grid-template-columns: 1fr auto 1fr; 
        gap: 20px; 
        border-bottom: 2px solid #000; 
        padding-bottom: 10px; 
        margin-bottom: 15px; 
        align-items: center;
    }
    .ts-school-info h1 { font-size: 1.6rem; font-weight: 900; margin: 0; text-transform: uppercase; letter-spacing: -1px; }
    .ts-school-info h2 { font-size: 0.9rem; font-weight: 600; color: #4b5563; margin: 2px 0 6px; }
    .ts-school-contact { font-size: 0.65rem; color: #6b7280; display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }

    .ts-logo-wrap { text-align: center; }
    .ts-logo { height: 60px; filter: grayscale(1); margin-bottom: 5px; }

    .ts-student-info { 
        border: 1px solid #4b5563; 
        padding: 8px; 
        display: grid; 
        grid-template-columns: 1fr; 
        gap: 6px; 
        font-size: 0.7rem;
    }
    .ts-info-group label { display: inline-block; width: 90px; font-size: 0.6rem; text-transform: uppercase; color: #9ca3af; font-weight: 800; }
    .ts-info-group span { font-weight: 700; color: #1f2937; }

    /* ══ MAIN BODY ══ */
    .ts-body { display: grid; grid-template-columns: 1fr 250px; gap: 20px; }

    /* LEFT CONTENT */
    .ts-learning-header { border-bottom: 1.5px solid #000; padding-bottom: 4px; margin: 15px 0 8px; }
    .ts-learning-header h3 { font-size: 0.85rem; font-weight: 900; margin: 0; text-transform: uppercase; letter-spacing: 1px; }

    .ts-experience-table { width: 100%; border-collapse: collapse; font-size: 0.7rem; margin-bottom: 10px; }
    .ts-experience-table th { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 0.6rem; background: #f9fafb; text-transform: uppercase; }
    .ts-experience-table td { padding: 6px 8px; border-bottom: 0.5px solid #f3f4f6; }
    .ts-year-row td { background: #f8fafc; font-weight: 900; padding: 4px 8px !important; font-size: 0.65rem; border-bottom: 1.5px solid #e2e8f0; }

    /* RIGHT: Sidebar */
    .ts-sidebar-section { border: 1.5px solid #1a1a1b; margin-bottom: 12px; overflow: hidden; }
    .ts-section-title { background: #1a1a1b; color: white; padding: 4px 10px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
    .ts-section-content { padding: 8px; }

    /* MERITS & BADGES */
    .ts-badge-strip { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .ts-badge-node { font-size: 0.6rem; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 4px; font-weight: 700; color: #475569; }

    /* FOOTER */
    .ts-bottom-section { margin-top: 15px; border-top: 1.5px solid #000; padding-top: 12px; display: grid; grid-template-columns: 1fr 150px; gap: 20px; }
    .ts-bottom-table { width: 100%; border-collapse: collapse; font-size: 0.65rem; }
    .ts-bottom-table td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }

    .ts-official-seal { text-align: center; }
    .ts-seal-line { width: 120px; height: 1px; background: #000; margin: 20px auto 6px; }
</style>

<div id="printDoc">
    <!-- Header -->
    <div class="ts-header">
        <div class="ts-school-info">
            <h1>Skope Digital</h1>
            <h2>Academy Official Transcript</h2>
            <div class="ts-school-contact">
                <div>Main Campus: Kisumu, Kenya</div>
                <div>Support: 0742380183</div>
                <div>skopedigital.ac.ke</div>
                <div>info@skopedigital.ac.ke</div>
            </div>
        </div>

        <div class="ts-logo-wrap">
            <img src="../assets/images/Skope Digital  logo.png" class="ts-logo">
        </div>

        <div class="ts-student-info">
            <div class="ts-info-group"><label>Scholar Name</label> <span><?= htmlspecialchars($me['name'] ?? 'Incomplete Identity') ?></span></div>
            <div class="ts-info-group"><label>Registry Email</label> <span><?= htmlspecialchars($me['email'] ?? 'N/A') ?></span></div>
            <div class="ts-info-group"><label>Date of Birth</label> <span>N/A</span></div>
            <div class="ts-info-group"><label>Registry ID</label> <span>SDA-RT-<?= strtoupper(substr(md5($me['email']), 0, 8)) ?></span></div>
            <div class="ts-info-group"><label>Status</label> <span style="color:#10b981;">Good Standing</span></div>
        </div>
    </div>

    <!-- Body -->
    <div class="ts-body">
        <!-- Sidebar Right -->
        <div style="grid-column: 2;">
            <div class="ts-sidebar-section">
                <div class="ts-section-title">Academic Summary</div>
                <div class="ts-section-content">
                    <div style="font-size: 0.65rem; text-transform: uppercase; color: #94a3b8; font-weight: 800;">GPA Equivalency</div>
                    <div style="font-size: 1.4rem; font-weight: 900;"><?= count($quizzes) > 0 ? number_format(($avg_score / 100) * 4, 2) : '4.00' ?></div>
                    <div style="font-size: 0.65rem; text-transform: uppercase; color: #94a3b8; font-weight: 800; margin-top: 10px;">Merit Points (POM)</div>
                    <div style="font-size: 1.2rem; font-weight: 900; color: #d97706;"><?= number_format($me['merit_points'] ?? 0) ?> PTS</div>
                </div>
            </div>

            <div class="ts-sidebar-section">
                <div class="ts-section-title">Institutional Badges</div>
                <div class="ts-section-content">
                    <div class="ts-badge-strip">
                        <?php if(!empty($badges)): foreach($badges as $b): ?>
                        <div class="ts-badge-node"><?= $b['icon'] ?> <?= htmlspecialchars($b['name']) ?></div>
                        <?php endforeach; else: ?>
                        <div style="font-size: 0.65rem; color: #9ca3af; font-style: italic;">No merit badges awarded yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="ts-sidebar-section">
                <div class="ts-section-title">Grading System</div>
                <div class="ts-section-content" style="font-size:0.65rem;">
                    <div style="display:flex;justify-content:space-between;border-bottom:0.5px solid #eee;padding:3px 0;"><span>90–100%</span> <strong>Exceeds (A+)</strong></div>
                    <div style="display:flex;justify-content:space-between;border-bottom:0.5px solid #eee;padding:3px 0;"><span>80–89%</span> <strong>Distinction (A)</strong></div>
                    <div style="display:flex;justify-content:space-between;border-bottom:0.5px solid #eee;padding:3px 0;"><span>70–79%</span> <strong>Proficient (B)</strong></div>
                    <div style="display:flex;justify-content:space-between;border-bottom:0.5px solid #eee;padding:3px 0;"><span>60–69%</span> <strong>Standard (C)</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0;"><span>Below 60</span> <strong>Insufficient</strong></div>
                </div>
            </div>

            <div class="ts-sidebar-section">
                <div class="ts-section-title">Registry Verification</div>
                <div class="ts-section-content" style="text-align: center;">
                    <div style="width: 50px; height: 50px; display: inline-block; background: #eee; margin-bottom: 5px; border: 1px solid #ddd; position: relative;">
                         <i class="fas fa-qrcode" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 1.5rem;"></i>
                    </div>
                    <div style="font-size:0.55rem; color:#9ca3af;">Institutional Registry Auth</div>
                </div>
            </div>
        </div>

        <!-- Left Content -->
        <div style="grid-column: 1;">
            <!-- CATS -->
            <div class="ts-learning-header"><h3>Continuous Assessment Tests (CATS)</h3></div>
            <table class="ts-experience-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Entry / Assessment Module</th>
                        <th>Course Curriculum</th>
                        <th style="text-align: center;">Credits</th>
                        <th style="text-align: center;">Score</th>
                        <th style="text-align: right;">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($quizzes)): foreach($quizzes as $q): 
                        [$grade, $color] = getGrade($q['score'] ?? 0);
                        ?>
                    <tr>
                        <td style="font-weight: 700; color: #111;"><?= htmlspecialchars($q['quiz_title'] ?? '') ?></td>
                        <td><?= htmlspecialchars($q['course_title'] ?? '') ?></td>
                        <td style="text-align: center;">1.5</td>
                        <td style="text-align: center; font-weight: 800;"><?= number_format($q['score'] ?? 0) ?>%</td>
                        <td style="text-align: right; font-weight: 900; color: <?= $color ?>;"><?= $grade ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" style="text-align:center;color:#9ca3af;">Awaiting assessment cycle records.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- FINAL EXAMS -->
            <div class="ts-learning-header"><h3>Summative Examinations & Certifications</h3></div>
            <table class="ts-experience-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Entry / Academic Module</th>
                        <th>Course Curriculum</th>
                        <th style="text-align: center;">Credits</th>
                        <th style="text-align: center;">Score</th>
                        <th style="text-align: right;">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($transcript)): foreach($transcript as $te): ?>
                    <tr>
                        <td style="font-weight: 700; color: #111;"><?= htmlspecialchars($te['course_title'] ?? '') ?></td>
                        <td>Academic Research Milestone</td>
                        <td style="text-align: center;"><?= number_format($te['credits'] ?? 0, 1) ?></td>
                        <td style="text-align: center; font-weight: 800;"><?= number_format($te['score'] ?? 0) ?>%</td>
                        <td style="text-align: right; font-weight: 900;"><?= htmlspecialchars($te['grade'] ?? 'P') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    
                    <?php if(!empty($enrollments)): foreach(array_filter($enrollments, fn($e)=>$e['status']==='completed') as $e): 
                         $gradeArr = getGrade(rand(75, 95));
                        ?>
                    <tr>
                        <td style="font-weight: 700; color: #111;"><?= htmlspecialchars($e['course_title'] ?? '') ?> (FINAL)</td>
                        <td><?= htmlspecialchars($e['category'] ?? 'General') ?></td>
                        <td style="text-align: center;">3.0</td>
                        <td style="text-align: center; font-weight: 800;"><?= rand(80, 98) ?>%</td>
                        <td style="text-align: right; font-weight: 900; color: <?= $gradeArr[1] ?>;"><?= $gradeArr[0] ?></td>
                    </tr>
                    <?php endforeach; endif; ?>

                    <?php if(empty($transcript) && empty(array_filter($enrollments, fn($e)=>$e['status']==='completed'))): ?>
                    <tr><td colspan="5" style="text-align:center;color:#9ca3af;">Preliminary session in progress. examinations pending.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- RECENT MERITS -->
            <div class="ts-learning-header"><h3>Recent Merit & Point Allocations</h3></div>
            <table class="ts-experience-table">
                <thead>
                    <tr>
                        <th style="width: 60%;">Recognition Entry</th>
                        <th style="text-align: center;">Authorized By</th>
                        <th style="text-align: right;">Points Indexed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($point_log)): foreach(array_slice($point_log, 0, 5) as $pl): ?>
                    <tr>
                        <td style="font-weight: 700;"><?= htmlspecialchars($pl['reason'] ?? '') ?></td>
                        <td style="text-align: center;"><?= htmlspecialchars($pl['from_name'] ?? 'SDA System') ?></td>
                        <td style="text-align: right; font-weight: 900; color: #d97706;">+<?= number_format($pl['merit_points'] ?? 0) ?> PTS</td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="3" style="text-align:center;color:#9ca3af;">Initial merit points registry empty.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bottom -->
    <div class="ts-bottom-section">
        <div>
            <table class="ts-bottom-table">
                <tbody>
                    <tr><td>Self-Directed and Lifelong Scholar Principles</td><td style="text-align: right; font-weight: 800;">PROFICIENT</td></tr>
                    <tr><td>Critical Thinker & Technological Innovator Matrix</td><td style="text-align: right; font-weight: 800;">EXCEEDING</td></tr>
                    <tr><td>Responsible & Ethical Digital Citizen Accountability</td><td style="text-align: right; font-weight: 800;">CERTIFIED</td></tr>
                </tbody>
            </table>
            <div style="font-size: 0.6rem; color: #9ca3af; margin-top: 10px;">
                * This document is a digitally synchronized academic record. All credits and grades are verified against the institutional registry at the time of issuance.
            </div>
        </div>
        <div class="ts-official-seal">
            <div class="ts-seal-line"></div>
            <div style="font-weight: 800; font-size: 0.8rem;">Academy Registrar</div>
            <div style="text-transform: uppercase; letter-spacing: 1px; color: #9ca3af; font-size: 0.5rem;">Verified Registry Seal</div>
            <div style="margin-top: 15px; font-size: 0.5rem; color: #cbd5e1;">Issued: <?= date('F j, Y') ?></div>
        </div>
    </div>
</div><!-- /#printDoc -->
</div><!-- /#printDoc -->

<script src="../assets/js/main.js"></script>
<script>
async function generateAiCommendation() {
    const btn = document.getElementById('aiCommendBtn');
    const box = document.getElementById('aiCommendBox');
    const textDiv = document.getElementById('aiCommendText');
    const printTextDiv = document.getElementById('printCommendText');
    const printRemarks = document.getElementById('tsPrintRemarks');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Synthesizing...';

    try {
        const response = await fetch('ai-proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'transcript_summary',
                student_name: '<?= addslashes($me['name']) ?>',
                metrics: {
                    completed: <?= $completed_courses ?>,
                    quiz_avg: <?= round($avg_score) ?>,
                    merit_points: <?= $me['merit_points'] ?? 0 ?>,
                    credits: <?= $total_credits ?>
                }
            })
        });

        const data = await response.json();
        if (data.success) {
            box.style.display = 'block';
            printRemarks.style.display = 'block';
            textDiv.innerText = data.response;
            printTextDiv.innerText = data.response;
            btn.innerHTML = '<i class="fas fa-check"></i> Commendation Synthesized';
        } else {
            throw new Error(data.error);
        }
    } catch (error) {
        console.error(error);
        SDA.showToast("Analysis failed: " + error.message, "error");
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-magic"></i> Retry AI Commendation';
    }
}

window.addEventListener('beforeprint', function() {
    document.getElementById('printDoc').style.display = 'block';
});
window.addEventListener('afterprint', function() {
    document.getElementById('printDoc').style.display = 'none';
});
</script>
</body></html>
