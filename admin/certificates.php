<?php
/**
 * Admin: Certificate Management & Approval Center
 */
$pageTitle = 'Certificate Management';
require_once '../includes/header.php';

if ($user['role'] !== 'admin') { header('Location: /Skope Digital Academy/login.php'); exit; }

$msg = ''; $msgType = 'success';

// ── Handle POST Actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Issue / Award Certificate
    if ($action === 'issue_cert') {
        $student_id = (int)$_POST['student_id'];
        $course_id  = (int)$_POST['course_id'];
        $notes      = trim($_POST['notes'] ?? '');
        $code       = strtoupper(bin2hex(random_bytes(6)));
        try {
            $pdo->beginTransaction();
            // Check not already issued
            $exists = $pdo->prepare("SELECT id FROM certificates WHERE student_id=? AND course_id=?");
            $exists->execute([$student_id, $course_id]);
            if ($exists->fetch()) throw new Exception("Certificate already issued for this student/course.");

            $stmt = $pdo->prepare("INSERT INTO certificates (student_id, course_id, verification_code, issued_by, issued_by_role, notes, status) VALUES (?,?,?,?,'admin',?,'approved')");
            $stmt->execute([$student_id, $course_id, $code, $user['id'], $notes]);
            // Award 100 points for cert
            $pdo->prepare("UPDATE users SET points = points + 100 WHERE id=?")->execute([$student_id]);
            $pdo->prepare("INSERT INTO point_ledger (student_id, points, reason, awarded_by) VALUES (?,100,'Certificate awarded',?)")->execute([$student_id, $user['id']]);
            $pdo->commit();
            $msg = "Certificate issued successfully! Code: $code";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = $e->getMessage(); $msgType = 'danger';
        }
    }

    // Revoke Certificate
    if ($action === 'revoke_cert') {
        $cert_id = (int)$_POST['cert_id'];
        $pdo->prepare("UPDATE certificates SET status='revoked' WHERE id=?")->execute([$cert_id]);
        $msg = "Certificate #$cert_id revoked."; $msgType = 'danger';
    }

    // Restore Certificate
    if ($action === 'restore_cert') {
        $cert_id = (int)$_POST['cert_id'];
        $pdo->prepare("UPDATE certificates SET status='approved' WHERE id=?")->execute([$cert_id]);
        $msg = "Certificate #$cert_id restored.";
    }

    // Award Badge
    if ($action === 'award_badge') {
        $student_id = (int)$_POST['student_id'];
        $badge_id   = (int)$_POST['badge_id'];
        $notes      = trim($_POST['badge_notes'] ?? '');
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO student_badges (student_id, badge_id, awarded_by, awarded_by_role, notes) VALUES (?,?,?,'admin',?)");
            $stmt->execute([$student_id, $badge_id, $user['id'], $notes]);
            $msg = "Badge awarded successfully!";
        } catch (Exception $e) { $msg = $e->getMessage(); $msgType = 'danger'; }
    }

    // Award Points
    if ($action === 'award_points') {
        $student_id = (int)$_POST['student_id'];
        $points     = (int)$_POST['points'];
        $reason     = trim($_POST['reason'] ?? 'Admin award');
        if ($points > 0 && $points <= 10000) {
            $pdo->prepare("UPDATE users SET points=points+? WHERE id=?")->execute([$points, $student_id]);
            $pdo->prepare("INSERT INTO point_ledger (student_id, points, reason, awarded_by) VALUES (?,?,?,?)")->execute([$student_id, $points, $reason, $user['id']]);
            $msg = "$points merit points awarded!";
        } else { $msg = 'Invalid points value (1-10000).'; $msgType = 'danger'; }
    }

    // Add Transcript Entry
    if ($action === 'add_transcript') {
        $student_id  = (int)$_POST['student_id'];
        $course_id   = (int)$_POST['course_id'];
        $title       = trim($_POST['entry_title']);
        $score       = $_POST['score'] !== '' ? (float)$_POST['score'] : null;
        $max_score   = $_POST['max_score'] !== '' ? (float)$_POST['max_score'] : null;
        $grade       = trim($_POST['grade'] ?? '');
        $credits     = (float)($_POST['credits'] ?? 1.0);
        $entry_notes = trim($_POST['entry_notes'] ?? '');
        $type        = $_POST['entry_type'] ?? 'manual_entry';
        try {
            $pdo->prepare("INSERT INTO transcript_entries (student_id, course_id, entry_type, title, score, max_score, grade, credits, notes, recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                ->execute([$student_id, $course_id, $type, $title, $score, $max_score, $grade, $credits, $entry_notes, $user['id']]);
            $msg = "Transcript entry added!";
        } catch (Exception $e) { $msg = $e->getMessage(); $msgType = 'danger'; }
    }
}

// ── Fetch Data ────────────────────────────────────────────────
try {
    $certs = $pdo->query("SELECT ce.*, u.name as student_name, u.email as student_email, 
        co.title as course_title, iss.name as issued_by_name
        FROM certificates ce 
        JOIN users u ON ce.student_id = u.id 
        JOIN courses co ON ce.course_id = co.id
        LEFT JOIN users iss ON ce.issued_by = iss.id
        ORDER BY ce.issued_at DESC LIMIT 60")->fetchAll();

    $students = $pdo->query("SELECT id, name, email, points FROM users WHERE role='student' AND status='active' ORDER BY name")->fetchAll();
    $courses  = $pdo->query("SELECT id, title FROM courses WHERE status='published' ORDER BY title")->fetchAll();
    $badges   = $pdo->query("SELECT id, name, icon FROM badges ORDER BY name")->fetchAll();

    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM certificates WHERE status='approved'")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM certificates WHERE status='pending'")->fetchColumn(),
        'revoked' => $pdo->query("SELECT COUNT(*) FROM certificates WHERE status='revoked'")->fetchColumn(),
    ];
} catch (Exception $e) { $certs = $students = $courses = $badges = []; $stats = []; }
?>
<?php require_once '../includes/sidebar.php'; ?>

<style>
.award-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 40px; }
.award-card { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 28px; }
.award-card h3 { font-family: 'Poppins',sans-serif; font-size: 1rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.award-card h3 i { color: var(--primary); }
.stat-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 20px; margin-bottom: 36px; }
.stat-box { background: white; border: 1px solid var(--dark-border); border-radius: 16px; padding: 22px; text-align: center; }
.stat-box .num { font-size: 2rem; font-weight: 900; }
.stat-box .lbl { font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
.cert-status-approved { background: rgba(16,185,129,0.1); color: #059669; }
.cert-status-revoked  { background: rgba(239,68,68,0.1);  color: #DC2626; }
.cert-status-pending  { background: rgba(245,158,11,0.1); color: #D97706; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 0.82rem; font-weight: 700; color: var(--text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 1px; }
.form-control { width:100%; padding: 10px 14px; border: 1.5px solid var(--dark-border); border-radius: 10px; font-size: 0.92rem; outline: none; font-family: var(--font); transition: 0.2s; background: white; }
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
@media(max-width:1024px){ .award-grid,.form-row{grid-template-columns:1fr;} }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display:flex;align-items:center;gap:20px;">
            <button class="nav-toggle" onclick="document.getElementById('dashSidebar').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family:'Poppins',sans-serif;font-size:1.8rem;">Certificate <span class="text-primary">Management</span></h1>
                <p style="color:var(--text-dim);margin-top:4px;">Issue, revoke, and manage student certificates, badges & transcripts.</p>
            </div>
        </div>
    </header>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom:28px;">
        <i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Stat Row -->
    <div class="stat-row">
        <div class="stat-box" style="border-top:4px solid #10B981;">
            <div class="num" style="color:#10B981;"><?= $stats['total'] ?? 0 ?></div>
            <div class="lbl">Active Certificates</div>
        </div>
        <div class="stat-box" style="border-top:4px solid var(--warning);">
            <div class="num" style="color:var(--warning);"><?= $stats['pending'] ?? 0 ?></div>
            <div class="lbl">Pending Approval</div>
        </div>
        <div class="stat-box" style="border-top:4px solid var(--danger);">
            <div class="num" style="color:var(--danger);"><?= $stats['revoked'] ?? 0 ?></div>
            <div class="lbl">Revoked</div>
        </div>
    </div>

    <!-- Award Action Cards -->
    <div class="award-grid">
        <!-- Issue Certificate -->
        <div class="award-card">
            <h3><i class="fas fa-certificate"></i> Issue Certificate</h3>
            <form method="POST">
                <input type="hidden" name="action" value="issue_cert">
                <div class="form-group">
                    <label class="form-label">Select Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">— Choose Student —</option>
                        <?php foreach($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Course / Programme</label>
                    <select name="course_id" class="form-control" required>
                        <option value="">— Choose Course —</option>
                        <?php foreach($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="e.g. With Distinction..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-certificate"></i> Issue Certificate</button>
            </form>
        </div>

        <!-- Award Badge + Points -->
        <div>
            <div class="award-card" style="margin-bottom:24px;">
                <h3><i class="fas fa-medal"></i> Award Badge</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="award_badge">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Student</label>
                            <select name="student_id" class="form-control" required>
                                <option value="">— Student —</option>
                                <?php foreach($students as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Badge</label>
                            <select name="badge_id" class="form-control" required>
                                <option value="">— Badge —</option>
                                <?php foreach($badges as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= $b['icon'] ?> <?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason / Notes</label>
                        <input type="text" name="badge_notes" class="form-control" placeholder="Why this badge?">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-block btn-sm"><i class="fas fa-medal"></i> Award Badge</button>
                </form>
            </div>
            <div class="award-card">
                <h3><i class="fas fa-star"></i> Award Merit Points</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="award_points">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Student</label>
                            <select name="student_id" class="form-control" required>
                                <option value="">— Student —</option>
                                <?php foreach($students as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= $s['points'] ?>pts)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Points</label>
                            <input type="number" name="points" class="form-control" min="1" max="10000" placeholder="e.g. 50" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Excellent project submission">
                    </div>
                    <button type="submit" class="btn btn-outline btn-block btn-sm"><i class="fas fa-star"></i> Award Points</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Transcript Entry -->
    <div class="award-card" style="margin-bottom:40px;">
        <h3><i class="fas fa-scroll"></i> Add Transcript Entry</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_transcript">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">— Student —</option>
                        <?php foreach($students as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Course</label>
                    <select name="course_id" class="form-control" required>
                        <option value="">— Course —</option>
                        <?php foreach($courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Entry Title</label>
                    <input type="text" name="entry_title" class="form-control" placeholder="e.g. Final Project Assessment" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Entry Type</label>
                    <select name="entry_type" class="form-control">
                        <option value="manual_entry">Manual Entry</option>
                        <option value="course_completion">Course Completion</option>
                        <option value="quiz_pass">Quiz / Exam</option>
                        <option value="assignment_grade">Assignment Grade</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Score</label>
                    <input type="number" name="score" class="form-control" step="0.01" placeholder="e.g. 87">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Score</label>
                    <input type="number" name="max_score" class="form-control" step="0.01" placeholder="e.g. 100">
                </div>
                <div class="form-group">
                    <label class="form-label">Grade</label>
                    <input type="text" name="grade" class="form-control" placeholder="A / B+ / Dist.">
                </div>
                <div class="form-group">
                    <label class="form-label">Credits</label>
                    <input type="number" name="credits" class="form-control" step="0.5" min="0.5" max="10" value="1.0">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <input type="text" name="entry_notes" class="form-control" placeholder="Additional remarks...">
            </div>
            <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-plus"></i> Add to Transcript</button>
        </form>
    </div>

    <!-- Certificate List -->
    <div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <h3 style="font-family:'Poppins',sans-serif;font-size:1.1rem;">All Certificates</h3>
        <input type="text" id="certSearch" class="form-control" placeholder="Search student, course..." style="max-width:280px;">
    </div>
    <div class="table-card">
        <table class="admin-table" style="width:100%;" id="certsTable">
            <thead>
                <tr>
                    <th>#</th><th>Student</th><th>Course</th><th>Issued By</th>
                    <th>Date</th><th>Code</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($certs)): foreach($certs as $c): ?>
            <tr>
                <td style="color:var(--text-dim);font-weight:800;">#<?= $c['id'] ?></td>
                <td>
                    <div style="font-weight:700;"><?= htmlspecialchars($c['student_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-dim);"><?= htmlspecialchars($c['student_email']) ?></div>
                </td>
                <td style="font-size:0.88rem;"><?= htmlspecialchars($c['course_title']) ?></td>
                <td style="font-size:0.82rem;color:var(--text-dim);"><?= htmlspecialchars($c['issued_by_name'] ?? 'System') ?></td>
                <td style="font-size:0.82rem;"><?= date('M j, Y', strtotime($c['issued_at'])) ?></td>
                <td><code style="font-size:0.72rem;background:var(--bg-light);padding:2px 8px;border-radius:4px;"><?= $c['verification_code'] ?></code></td>
                <td><span class="badge cert-status-<?= $c['status'] ?? 'approved' ?>"><?= ucfirst($c['status'] ?? 'approved') ?></span></td>
                <td>
                    <?php $s = $c['status'] ?? 'approved'; ?>
                    <?php if ($s !== 'revoked'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="revoke_cert">
                        <input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" onclick="return confirm('Revoke this certificate?')"><i class="fas fa-ban"></i></button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="restore_cert">
                        <input type="hidden" name="cert_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" style="color:#10B981;"><i class="fas fa-undo"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align:center;padding:60px;color:var(--text-dim);">No certificates issued yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
document.getElementById('certSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#certsTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>
</body></html>
