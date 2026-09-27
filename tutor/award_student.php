<?php
/**
 * Tutor: Award Certificates, Badges, Points & Transcript to a Student
 */
$pageTitle = 'Award Student';
require_once 'includes/header.php';

$tutor = currentUser();
if ($tutor['role'] !== 'tutor') { header('Location: /Skope Digital Academy/login.php'); exit; }

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
            $chk->execute([$course_id, $tutor['id']]);
            if (!$chk->fetch()) throw new Exception("You can only issue certificates for your own courses.");

            $exists = $pdo->prepare("SELECT id FROM certificates WHERE student_id=? AND course_id=?");
            $exists->execute([$student_id, $course_id]);
            if ($exists->fetch()) throw new Exception("Certificate already exists for this student/course.");

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO certificates (student_id, course_id, verification_code, issued_by, issued_by_role, notes, status) VALUES (?,?,?,?,'tutor',?,'approved')")
                ->execute([$student_id, $course_id, $code, $tutor['id'], $notes]);
            $pdo->prepare("UPDATE users SET merit_points=merit_points+100 WHERE id=?")->execute([$student_id]);
            $pdo->prepare("INSERT INTO point_ledger (student_id, merit_points, reason, awarded_by) VALUES (?,100,'Certificate awarded by tutor',?)")->execute([$student_id, $tutor['id']]);
            
            // Mark student as graduated/completed for this specific course
            $pdo->prepare("UPDATE enrollments SET progress_percent = 100, status = 'completed', completed_at = NOW() WHERE student_id = ? AND course_id = ?")->execute([$student_id, $course_id]);
            
            $pdo->commit();
            
            // Auto-award system badges
            require_once '../includes/gamified_logic.php';
            awardBadgeIfEligible($student_id, 'courses_completed', $pdo);
            
            $msg = "Certificate issued and student officially marked as graduated! Verification Code: <strong>$code</strong>";
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
                ->execute([$student_id, $badge_id, $tutor['id'], $notes]);
            $msg = "Badge awarded!";
        } catch (Exception $e) { $msg = $e->getMessage(); $msgType = 'danger'; }
    }

    // Award Points
    if ($action === 'award_points') {
        $points = (int)$_POST['points'];
        $reason = trim($_POST['reason'] ?? 'Tutor award');
        if ($points > 0 && $points <= 500) {
            $pdo->prepare("UPDATE users SET merit_points=merit_points+? WHERE id=?")->execute([$points, $student_id]);
            $pdo->prepare("INSERT INTO point_ledger (student_id, merit_points, reason, awarded_by) VALUES (?,?,?,?)")->execute([$student_id, $points, $reason, $tutor['id']]);
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
            $chk->execute([$course_id, $tutor['id']]);
            if (!$chk->fetch()) throw new Exception("You can only add transcript entries for your own courses.");
            $pdo->prepare("INSERT INTO transcript_entries (student_id, course_id, entry_type, title, score, max_score, grade, credits, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$student_id, $course_id, $type, $title, $score, $max_score, $grade, $credits, $entry_notes, $tutor['id']]);
            $msg = "Transcript entry added!";
        } catch (Exception $e) { $msg = $e->getMessage(); $msgType = 'danger'; }
    }
}

// ── Fetch Data ────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT name, email, avatar, merit_points as points, created_at, last_login FROM users WHERE id=? AND role='student'");
    $stmt->execute([$student_id]);
    $student_data = $stmt->fetch();
    if (!$student_data) { header('Location: students.php'); exit; }

    // Tutor's courses the student is enrolled in
    $my_courses = $pdo->prepare("SELECT c.id, c.title, e.progress_percent FROM courses c 
        JOIN enrollments e ON e.course_id=c.id AND e.student_id=?
        WHERE c.tutor_id=? AND c.status='published'");
    $my_courses->execute([$student_id, $tutor['id']]);
    $enrolled_courses = $my_courses->fetchAll();

    // All tutor courses (for selection)
    $all_my_courses = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id=? AND status='published' ORDER BY title");
    $all_my_courses->execute([$tutor['id']]);
    $tutor_courses = $all_my_courses->fetchAll();

    $badges   = $pdo->query("SELECT id, name, icon FROM badges ORDER BY name")->fetchAll();

    // Existing certs for this student
    $certs_stmt = $pdo->prepare("SELECT ce.*, co.title as course_title FROM certificates ce JOIN courses co ON ce.course_id=co.id WHERE ce.student_id=? AND co.tutor_id=? ORDER BY ce.issued_at DESC");
    $certs_stmt->execute([$student_id, $tutor['id']]);
    $existing_certs = $certs_stmt->fetchAll();

    // Existing badges
    $badges_stmt = $pdo->prepare("SELECT b.name, b.icon, sb.awarded_at FROM student_badges sb JOIN badges b ON sb.badge_id=b.id WHERE sb.student_id=? ORDER BY sb.awarded_at DESC");
    $badges_stmt->execute([$student_id]);
    $existing_badges = $badges_stmt->fetchAll();

    // Transcript entries for this student's courses
    $trans_stmt = $pdo->prepare("SELECT te.*, co.title as course_title, u.name as recorded_by_name FROM transcript_entries te JOIN courses co ON te.course_id=co.id LEFT JOIN users u ON te.recorded_by=u.id WHERE te.student_id=? AND co.tutor_id=? ORDER BY te.recorded_at DESC");
    $trans_stmt->execute([$student_id, $tutor['id']]);
    $transcript = $trans_stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    $student_data = null; $enrolled_courses = $tutor_courses = $badges = $existing_certs = $existing_badges = $transcript = [];
}
?>
<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">

<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <a href="students.php" style="color: var(--text-dim); font-size: 0.85rem; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">
            <i class="fas fa-arrow-left"></i> Back to Scholar Roster
        </a>
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Award Scholastic Merit<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Directing <span style="color: var(--primary); font-weight: 700;">institutional recognition</span> and professional accreditation.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="student_profile.php?id=<?= $student_id ?>" class="btn-premium" style="background: white; color: var(--text-dim); border: 1px solid #E2E8F0; box-shadow: none; font-size: 0.8rem; padding: 12px 24px;">
            <i class="fas fa-user-graduate"></i> VIEW SCHOLAR DOSSIER
        </a>
    </div>
</header>

<?php if ($msg): ?>
    <div style="padding: 20px 32px; background: <?= $msgType==='success'?'#ECFDF5':'#FEF2F2' ?>; color: <?= $msgType==='success'?'#065F46':'#991B1B' ?>; border-radius: 20px; margin-bottom: 40px; display: flex; align-items: center; gap: 16px; border: 1px solid <?= $msgType==='success'?'#D1FAE5':'#FEE2E2' ?>; font-weight: 700;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: <?= $msgType==='success'?'#10B981':'#EF4444' ?>; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.9rem;"><i class="fas fa-<?= $msgType==='success'?'check':'exclamation' ?>"></i></div>
        <?= $msg ?>
    </div>
<?php endif; ?>

<!-- Scholar Identity Summary -->
<div class="premium-card flex-responsive" style="padding: 32px; margin-bottom: 40px; border-left: 8px solid var(--primary);">
    <div style="width: 72px; height: 72px; border-radius: 20px; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 950; flex-shrink: 0; border: 2px solid white; box-shadow: var(--shadow-sm);">
        <?= strtoupper(substr($student_data['name'] ?? 'S', 0, 1)) ?>
    </div>
    <div style="flex: 1;">
        <div style="font-weight: 950; font-size: 1.25rem; color: var(--text-main); letter-spacing: -0.5px;"><?= htmlspecialchars($student_data['name'] ?? '') ?></div>
        <div style="font-size: 0.9rem; color: var(--text-dim); font-weight: 600; margin-top: 4px;"><?= htmlspecialchars($student_data['email'] ?? '') ?></div>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <div style="padding: 8px 16px; background: #E0F7FF; color: var(--primary); border-radius: 12px; font-size: 0.75rem; font-weight: 950; border: 1px solid #E0F7FF; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-star"></i> <?= number_format($student_data['points'] ?? 0) ?> MERITS
        </div>
        <div style="padding: 8px 16px; background: #FFF7ED; color: #EA580C; border-radius: 12px; font-size: 0.75rem; font-weight: 950; border: 1px solid #FFEDD5; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-certificate"></i> <?= count($existing_certs) ?> CERTS
        </div>
        <div style="padding: 8px 16px; background: #ECFDF5; color: #10B981; border-radius: 12px; font-size: 0.75rem; font-weight: 950; border: 1px solid #D1FAE5; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-medal"></i> <?= count($existing_badges) ?> BADGES
        </div>
    </div>
</div>

<div class="award-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; align-items: flex-start;">
    <!-- Form Stack -->
    <div style="display: flex; flex-direction: column; gap: 40px;">
        <!-- Issue Certificate -->
        <div class="premium-card" style="padding: 40px;">
            <h3 style="margin: 0 0 32px; font-size: 1.1rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-certificate" style="color: var(--primary);"></i>
                Issue Professional Certificate
            </h3>
            <?php if (empty($tutor_courses)): ?>
                <div style="padding: 40px; text-align: center; background: #F8FAFC; border-radius: 20px; border: 1.5px dashed #E2E8F0;">
                    <p style="color: var(--text-dim); font-weight: 700; font-style: italic; margin: 0;">No published courses available for accreditation.</p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="issue_cert">
                    <div style="margin-bottom: 24px;">
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Select Academic Track</label>
                        <select name="course_id" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;" required>
                            <option value="">— Select Course —</option>
                            <?php foreach($tutor_courses as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom: 32px;">
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Accreditation Notes (Optional)</label>
                        <input type="text" name="notes" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;" placeholder="e.g. With High Distinction (95%)">
                    </div>
                    <button type="submit" class="btn-premium" style="width: 100%; padding: 16px;">ISSUE OFFICIAL CERTIFICATE</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Award Badge -->
        <div class="premium-card" style="padding: 40px;">
            <h3 style="margin: 0 0 32px; font-size: 1.1rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-medal" style="color: #EA580C;"></i>
                Award Merit Badge
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="award_badge">
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Select Recognition Badge</label>
                    <select name="badge_id" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;" required>
                        <option value="">— Select Badge —</option>
                        <?php foreach($badges as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= $b['icon'] ?> <?= htmlspecialchars($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 32px;">
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Recognition Narrative</label>
                    <input type="text" name="badge_notes" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;" placeholder="e.g. Exceptional community contribution">
                </div>
                <button type="submit" class="btn-premium" style="width: 100%; padding: 16px; background: #EA580C;">AWARD MERIT BADGE</button>
            </form>
        </div>

        <!-- Award Points -->
        <div class="premium-card" style="padding: 40px;">
            <h3 style="margin: 0 0 32px; font-size: 1.1rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-star" style="color: #F59E0B;"></i>
                Distribute Merit Points
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="award_points">
                <div style="display: grid; grid-template-columns: 120px 1fr; gap: 20px; margin-bottom: 32px;">
                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Points</label>
                        <input type="number" name="points" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 950; outline: none; font-size: 1rem; color: var(--primary);" min="1" max="500" value="50" required>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Reasoning Node</label>
                        <input type="text" name="reason" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;" placeholder="e.g. Outstanding laboratory project result">
                    </div>
                </div>
                <button type="submit" class="btn-premium" style="width: 100%; padding: 16px; background: #F59E0B;">DISTRIBUTE POINTS</button>
            </form>
        </div>
    </div>

    <!-- Transcript Stack -->
    <div style="display: flex; flex-direction: column; gap: 40px;">
        <!-- Add Transcript Entry -->
        <div class="premium-card" style="padding: 40px;">
            <h3 style="margin: 0 0 32px; font-size: 1.1rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-scroll" style="color: #00BFFF;"></i>
                Add Transcript Entry
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_transcript">
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Course Track</label>
                    <select name="course_id" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;" required>
                        <option value="">— Select Track —</option>
                        <?php foreach($tutor_courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Entry Title</label>
                        <input type="text" name="entry_title" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;" placeholder="e.g. Final Examination" required>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Classification</label>
                        <select name="entry_type" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;">
                            <option value="manual_entry">Manual Entry</option>
                            <option value="course_completion">Graduation</option>
                            <option value="quiz_pass">Assessment</option>
                            <option value="assignment_grade">Project Review</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Earned Score</label>
                        <input type="number" name="score" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 950; outline: none; font-size: 1rem; color: var(--primary);" step="0.01" placeholder="85.00">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Max Capability</label>
                        <input type="number" name="max_score" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 950; outline: none; font-size: 1rem; color: var(--text-dim);" step="0.01" placeholder="100.00">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 120px; gap: 20px; margin-bottom: 32px;">
                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Grade Classification</label>
                        <input type="text" name="grade" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 700; outline: none; font-size: 0.95rem;" placeholder="A / High Distinction">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px;">Credits</label>
                        <input type="number" name="credits" style="width: 100%; padding: 14px 20px; border-radius: 14px; border: 1.5px solid #F1F5F9; background: #F8FAFC; font-weight: 950; outline: none; font-size: 1rem;" step="0.5" value="1.0">
                    </div>
                </div>
                <button type="submit" class="btn-premium" style="width: 100%; padding: 16px; background: #00BFFF;">ADD TRANSCRIPT ENTRY</button>
            </form>
        </div>

        <!-- Transcript History -->
        <div class="premium-card" style="padding: 40px;">
            <h3 style="margin: 0 0 32px; font-size: 1.1rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
                <i class="fas fa-list-alt" style="color: var(--text-dim);"></i>
                Record History <span style="font-size: 0.8rem; background: #F1F5F9; color: var(--text-dim); padding: 4px 12px; border-radius: 50px; margin-left: auto;"><?= count($transcript) ?> Entries</span>
            </h3>
            <div style="max-height: 500px; overflow-y: auto; padding-right: 12px;">
                <?php if (!empty($transcript)): ?>
                    <?php foreach($transcript as $t): ?>
                    <div style="background: #F8FAFC; border: 1.5px solid #F1F5F9; border-radius: 20px; padding: 24px; margin-bottom: 20px; transition: 0.3s;" onmouseover="this.style.borderColor='var(--primary)'; this.style.background='white'">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div style="font-weight: 950; font-size: 1rem; color: var(--text-main); letter-spacing: -0.3px;"><?= htmlspecialchars($t['title']) ?></div>
                            <?php if ($t['grade']): ?>
                                <span style="padding: 4px 10px; background: var(--primary); color: white; border-radius: 8px; font-size: 0.7rem; font-weight: 950;"><?= htmlspecialchars($t['grade']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); font-weight: 700;"><?= htmlspecialchars($t['course_title']) ?> • <?= date('M d, Y', strtotime($t['recorded_at'])) ?></div>
                        <?php if ($t['score'] !== null): ?>
                            <div style="margin-top: 12px; display: flex; justify-content: space-between; align-items: flex-end;">
                                <div style="font-size: 1.1rem; font-weight: 950; color: var(--primary);"><?= $t['score'] ?><span style="font-size: 0.75rem; color: #94A3B8; font-weight: 700;"> / <?= $t['max_score'] ?></span></div>
                                <div style="font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;"><?= $t['credits'] ?> CREDITS EARNED</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 60px 0; text-align: center; color: var(--text-dim); font-weight: 600; font-style: italic;">No scholastic records archived.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</main>
<script src="../assets/js/main.js"></script>
</body>
</html>
