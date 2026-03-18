<?php
/**
 * Tutor: Award Certificates, Badges, Points & Transcript to a Student
 */
$pageTitle = 'Award Student';
require_once '../includes/header.php';

if ($user['role'] !== 'tutor') { header('Location: /Skope Digital Academy/login.php'); exit; }

$student_id = (int)($_GET['id'] ?? 0);
if (!$student_id) { header('Location: students.php'); exit; }

$msg = ''; $msgType = 'success';

// ── Handle POST Actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Issue Certificate (only for tutor's own courses)
    if ($action === 'issue_cert') {
        $course_id = (int)$_POST['course_id'];
        $notes     = trim($_POST['notes'] ?? '');
        $code      = strtoupper(bin2hex(random_bytes(6)));
        try {
            // Verify course belongs to this tutor
            $chk = $pdo->prepare("SELECT id FROM courses WHERE id=? AND tutor_id=?");
            $chk->execute([$course_id, $user['id']]);
            if (!$chk->fetch()) throw new Exception("You can only issue certificates for your own courses.");

            $exists = $pdo->prepare("SELECT id FROM certificates WHERE student_id=? AND course_id=?");
            $exists->execute([$student_id, $course_id]);
            if ($exists->fetch()) throw new Exception("Certificate already exists for this student/course.");

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO certificates (student_id, course_id, verification_code, issued_by, issued_by_role, notes, status) VALUES (?,?,?,?,'tutor',?,'approved')")
                ->execute([$student_id, $course_id, $code, $user['id'], $notes]);
            $pdo->prepare("UPDATE users SET points=points+100 WHERE id=?")->execute([$student_id]);
            $pdo->prepare("INSERT INTO point_ledger (student_id, points, reason, awarded_by) VALUES (?,100,'Certificate awarded by tutor',?)")->execute([$student_id, $user['id']]);
            $pdo->commit();
            $msg = "Certificate issued! Verification Code: <strong>$code</strong>";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = $e->getMessage(); $msgType = 'danger';
        }
    }

    // Award Badge
    if ($action === 'award_badge') {
        $badge_id = (int)$_POST['badge_id'];
        $notes    = trim($_POST['badge_notes'] ?? '');
        try {
            $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id, awarded_by, awarded_by_role, notes) VALUES (?,?,?,'tutor',?)")
                ->execute([$student_id, $badge_id, $user['id'], $notes]);
            $msg = "Badge awarded!";
        } catch (Exception $e) { $msg = $e->getMessage(); $msgType = 'danger'; }
    }

    // Award Points
    if ($action === 'award_points') {
        $points = (int)$_POST['points'];
        $reason = trim($_POST['reason'] ?? 'Tutor award');
        if ($points > 0 && $points <= 500) {
            $pdo->prepare("UPDATE users SET points=points+? WHERE id=?")->execute([$points, $student_id]);
            $pdo->prepare("INSERT INTO point_ledger (student_id, points, reason, awarded_by) VALUES (?,?,?,?)")->execute([$student_id, $points, $reason, $user['id']]);
            $msg = "$points merit points awarded!";
        } else { $msg = 'Tutors can award 1-500 points at a time.'; $msgType = 'danger'; }
    }

    // Add Transcript Entry
    if ($action === 'add_transcript') {
        $course_id   = (int)$_POST['course_id'];
        $title       = trim($_POST['entry_title']);
        $score       = $_POST['score'] !== '' ? (float)$_POST['score'] : null;
        $max_score   = $_POST['max_score'] !== '' ? (float)$_POST['max_score'] : null;
        $grade       = trim($_POST['grade'] ?? '');
        $credits     = (float)($_POST['credits'] ?? 1.0);
        $entry_notes = trim($_POST['entry_notes'] ?? '');
        $type        = $_POST['entry_type'] ?? 'manual_entry';
        try {
            // Verify course belongs to tutor
            $chk = $pdo->prepare("SELECT id FROM courses WHERE id=? AND tutor_id=?");
            $chk->execute([$course_id, $user['id']]);
            if (!$chk->fetch()) throw new Exception("You can only add transcript entries for your own courses.");
            $pdo->prepare("INSERT INTO transcript_entries (student_id, course_id, entry_type, title, score, max_score, grade, credits, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$student_id, $course_id, $type, $title, $score, $max_score, $grade, $credits, $entry_notes, $user['id']]);
            $msg = "Transcript entry added!";
        } catch (Exception $e) { $msg = $e->getMessage(); $msgType = 'danger'; }
    }
}

// ── Fetch Data ────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT name, email, avatar, points, created_at, last_login FROM users WHERE id=? AND role='student'");
    $stmt->execute([$student_id]);
    $student_data = $stmt->fetch();
    if (!$student_data) { header('Location: students.php'); exit; }

    // Tutor's courses the student is enrolled in
    $my_courses = $pdo->prepare("SELECT c.id, c.title, e.progress_percent FROM courses c 
        JOIN enrollments e ON e.course_id=c.id AND e.student_id=?
        WHERE c.tutor_id=? AND c.status='published'");
    $my_courses->execute([$student_id, $user['id']]);
    $enrolled_courses = $my_courses->fetchAll();

    // All tutor courses (for selection)
    $all_my_courses = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id=? AND status='published' ORDER BY title");
    $all_my_courses->execute([$user['id']]);
    $tutor_courses = $all_my_courses->fetchAll();

    $badges   = $pdo->query("SELECT id, name, icon FROM badges ORDER BY name")->fetchAll();

    // Existing certs for this student
    $certs_stmt = $pdo->prepare("SELECT ce.*, co.title as course_title FROM certificates ce JOIN courses co ON ce.course_id=co.id WHERE ce.student_id=? AND co.tutor_id=? ORDER BY ce.issued_at DESC");
    $certs_stmt->execute([$student_id, $user['id']]);
    $existing_certs = $certs_stmt->fetchAll();

    // Existing badges
    $badges_stmt = $pdo->prepare("SELECT b.name, b.icon, sb.awarded_at FROM student_badges sb JOIN badges b ON sb.badge_id=b.id WHERE sb.student_id=? ORDER BY sb.awarded_at DESC");
    $badges_stmt->execute([$student_id]);
    $existing_badges = $badges_stmt->fetchAll();

    // Transcript entries for this student's courses
    $trans_stmt = $pdo->prepare("SELECT te.*, co.title as course_title, u.name as recorded_by_name FROM transcript_entries te JOIN courses co ON te.course_id=co.id LEFT JOIN users u ON te.recorded_by=u.id WHERE te.student_id=? AND co.tutor_id=? ORDER BY te.recorded_at DESC");
    $trans_stmt->execute([$student_id, $user['id']]);
    $transcript = $trans_stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    $student_data = null; $enrolled_courses = $tutor_courses = $badges = $existing_certs = $existing_badges = $transcript = [];
}
?>
<?php require_once '../includes/sidebar.php'; ?>

<style>
.award-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; }
.award-card { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 28px; margin-bottom: 28px; }
.award-card h3 { font-family: 'Poppins',sans-serif; font-size: 0.95rem; font-weight: 700; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; color: var(--dark); }
.award-card h3 i { color: var(--primary); }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 5px; text-transform: uppercase; letter-spacing: 1px; }
.form-control { width:100%; padding: 10px 14px; border: 1.5px solid var(--dark-border); border-radius: 10px; font-size: 0.9rem; outline: none; font-family: var(--font); transition: 0.2s; background: white; }
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
.form-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mini-tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; background: var(--primary-glow); color: var(--primary); }
@media(max-width:900px){ .award-grid{grid-template-columns:1fr;} }
</style>

<main class="main-content">
    <header class="admin-header">
        <div>
            <a href="students.php" style="color:var(--text-dim);font-size:0.88rem;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:6px;margin-bottom:12px;">
                <i class="fas fa-arrow-left"></i> Back to Roster
            </a>
            <h1 style="font-family:'Poppins',sans-serif;font-size:1.8rem;">Award <span class="text-primary"><?= htmlspecialchars($student_data['name'] ?? 'Student') ?></span></h1>
        </div>
        <a href="student_profile.php?id=<?= $student_id ?>" class="btn btn-ghost btn-sm"><i class="fas fa-user"></i> View Full Profile</a>
    </header>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:24px;">
        <i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= $msg ?>
    </div>
    <?php endif; ?>

    <!-- Student Summary Strip -->
    <div style="background:white;border:1px solid var(--dark-border);border-radius:16px;padding:20px 28px;margin-bottom:32px;display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
        <div style="width:56px;height:56px;border-radius:14px;background:var(--primary-glow);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;flex-shrink:0;"><?= strtoupper(substr($student_data['name'] ?? 'S', 0, 1)) ?></div>
        <div style="flex:1;">
            <div style="font-weight:800;font-size:1.05rem;"><?= htmlspecialchars($student_data['name'] ?? '') ?></div>
            <div style="font-size:0.82rem;color:var(--text-dim);"><?= htmlspecialchars($student_data['email'] ?? '') ?></div>
        </div>
        <div class="mini-tag"><i class="fas fa-star"></i> <?= number_format($student_data['points'] ?? 0) ?> Points</div>
        <div class="mini-tag" style="background:var(--secondary-glow);color:var(--secondary);"><i class="fas fa-certificate"></i> <?= count($existing_certs) ?> Certs</div>
        <div class="mini-tag" style="background:rgba(16,185,129,0.1);color:#10B981;"><i class="fas fa-medal"></i> <?= count($existing_badges) ?> Badges</div>
    </div>

    <div class="award-grid">
        <!-- Left Column -->
        <div>
            <!-- Issue Certificate -->
            <div class="award-card">
                <h3><i class="fas fa-certificate"></i> Issue Certificate</h3>
                <?php if (empty($tutor_courses)): ?>
                <p style="color:var(--text-dim);font-size:0.88rem;">You have no published courses yet.</p>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="issue_cert">
                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-control" required>
                            <option value="">— Select Your Course —</option>
                            <?php foreach($tutor_courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes (grade/distinction)</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. With Distinction, 94%">
                    </div>
                    <button type="submit" class="btn btn-primary btn-block btn-sm"><i class="fas fa-certificate"></i> Issue Certificate</button>
                </form>
                <?php endif; ?>

                <?php if (!empty($existing_certs)): ?>
                <div style="margin-top:20px;border-top:1px solid var(--dark-border);padding-top:16px;">
                    <div style="font-size:0.72rem;font-weight:800;text-transform:uppercase;color:var(--text-dim);margin-bottom:10px;">Existing Certs</div>
                    <?php foreach($existing_certs as $ec): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--bg-light);">
                        <div style="font-size:0.82rem;font-weight:600;"><?= htmlspecialchars($ec['course_title']) ?></div>
                        <span class="badge badge-<?= ($ec['status']??'approved')==='approved'?'success':'danger' ?>"><?= ucfirst($ec['status']??'approved') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Award Badge -->
            <div class="award-card">
                <h3><i class="fas fa-medal"></i> Award Badge</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="award_badge">
                    <div class="form-group">
                        <label class="form-label">Badge</label>
                        <select name="badge_id" class="form-control" required>
                            <option value="">— Select Badge —</option>
                            <?php foreach($badges as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= $b['icon'] ?> <?= htmlspecialchars($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <input type="text" name="badge_notes" class="form-control" placeholder="Why this badge?">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-block btn-sm"><i class="fas fa-medal"></i> Award Badge</button>
                </form>
                <?php if (!empty($existing_badges)): ?>
                <div style="margin-top:16px;border-top:1px solid var(--dark-border);padding-top:12px;display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach($existing_badges as $eb): ?>
                    <span style="font-size:0.78rem;background:var(--bg-light);border:1px solid var(--dark-border);padding:3px 10px;border-radius:10px;"><?= $eb['icon'] ?> <?= htmlspecialchars($eb['name']) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Award Points -->
            <div class="award-card">
                <h3><i class="fas fa-star"></i> Award Merit Points</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="award_points">
                    <div class="form-row2">
                        <div class="form-group">
                            <label class="form-label">Points (max 500)</label>
                            <input type="number" name="points" class="form-control" min="1" max="500" placeholder="e.g. 50" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reason</label>
                            <input type="text" name="reason" class="form-control" placeholder="e.g. Great quiz score">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline btn-block btn-sm"><i class="fas fa-star"></i> Award Points</button>
                </form>
            </div>
        </div>

        <!-- Right Column: Transcript -->
        <div>
            <div class="award-card">
                <h3><i class="fas fa-scroll"></i> Add Transcript Entry</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_transcript">
                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <select name="course_id" class="form-control" required>
                            <option value="">— Your Course —</option>
                            <?php foreach($tutor_courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Entry Title</label>
                        <input type="text" name="entry_title" class="form-control" placeholder="e.g. Final Exam, Project Review" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="entry_type" class="form-control">
                            <option value="manual_entry">Manual Entry</option>
                            <option value="course_completion">Course Completion</option>
                            <option value="quiz_pass">Quiz / Exam</option>
                            <option value="assignment_grade">Assignment Grade</option>
                        </select>
                    </div>
                    <div class="form-row2">
                        <div class="form-group">
                            <label class="form-label">Score</label>
                            <input type="number" name="score" class="form-control" step="0.01" placeholder="e.g. 87">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Score</label>
                            <input type="number" name="max_score" class="form-control" step="0.01" placeholder="e.g. 100">
                        </div>
                    </div>
                    <div class="form-row2">
                        <div class="form-group">
                            <label class="form-label">Grade</label>
                            <input type="text" name="grade" class="form-control" placeholder="A / Distinction">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Credits</label>
                            <input type="number" name="credits" class="form-control" step="0.5" min="0.5" max="10" value="1.0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Remarks</label>
                        <input type="text" name="entry_notes" class="form-control" placeholder="Optional remarks">
                    </div>
                    <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-plus"></i> Add Entry</button>
                </form>
            </div>

            <!-- Transcript History -->
            <div class="award-card">
                <h3><i class="fas fa-list-alt"></i> Transcript History (<?= count($transcript) ?>)</h3>
                <?php if (!empty($transcript)): ?>
                <div style="max-height:400px;overflow-y:auto;">
                    <?php foreach($transcript as $t): ?>
                    <div style="border:1px solid var(--dark-border);border-radius:12px;padding:14px 16px;margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">
                            <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($t['title']) ?></div>
                            <?php if ($t['grade']): ?>
                            <span class="badge badge-primary"><?= htmlspecialchars($t['grade']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.75rem;color:var(--text-dim);"><?= htmlspecialchars($t['course_title']) ?> • <?= date('M j, Y', strtotime($t['recorded_at'])) ?></div>
                        <?php if ($t['score'] !== null): ?>
                        <div style="font-size:0.82rem;margin-top:6px;color:var(--primary);font-weight:700;"><?= $t['score'] ?>/<?= $t['max_score'] ?> (<?= $t['credits'] ?> credits)</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="color:var(--text-dim);font-size:0.88rem;text-align:center;padding:20px 0;">No transcript entries yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
</body></html>
