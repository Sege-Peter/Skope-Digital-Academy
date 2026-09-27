<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole('tutor');
$tutor = currentUser();

$success_msg = '';
$error = '';

// 1. Handle Review & Grade (AJAX or POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $submission_id = (int)$_POST['submission_id'];
    $score = (float)$_POST['score'];
    $notes = trim($_POST['feedback']);

    try {
        // Security: Ensure this submission belongs to a course owned by the logged-in tutor
        $stmt = $pdo->prepare("
            SELECT asub.student_id 
            FROM assignment_submissions asub 
            JOIN assignments a ON asub.assignment_id = a.id 
            JOIN courses c ON a.course_id = c.id 
            WHERE asub.id = ? AND c.tutor_id = ?
        ");
        $stmt->execute([$submission_id, $tutor['id']]);
        $student_id = $stmt->fetchColumn();

        if ($student_id) {
            $stmt = $pdo->prepare("UPDATE assignment_submissions SET score=?, notes=?, status='graded' WHERE id=?");
            $stmt->execute([$score, $notes, $submission_id]);

            // Gamification: Reward points based on score
            require_once __DIR__ . '/../includes/gamified_logic.php';
            rewardStudentPoints($student_id, $score, $pdo);

            // Notification: Alert the student
            $notifMsg = "Your project submission has been audited. Score: {$score}";
            $pdo->prepare("INSERT INTO notifications (title, message, target_user_id) VALUES ('Assignment Graded', ?, ?)")
                ->execute([$notifMsg, $student_id]);

            header("Location: assignments.php?msg=" . urlencode("Scholarly evaluation synchronized!"));
            exit;
        } else {
            $error = "Unauthorized attempt to grade this submission.";
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// 2. Handle New Assignment Launch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignment'])) {
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $max_score = (int)$_POST['max_score'];
    $deadline = $_POST['due_date'];

    try {
        // Security: Ensure this tutor owns the course
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND tutor_id = ?");
        $stmt->execute([$course_id, $tutor['id']]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO assignments (course_id, title, description, max_score, deadline) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $title, $desc, $max_score, $deadline]);
            header("Location: assignments.php?msg=" . urlencode("New academic challenge deployed!"));
            exit;
        } else {
            $error = "Unauthorized attempt to launch assignment.";
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$pageTitle = 'Project Audits';
require_once 'includes/header.php';
?>

<?php
// 3. Fetch Data
try {
    // Fetch all submissions for courses owned by this tutor
    $stmt = $pdo->prepare("SELECT asub.*, u.name as student_name, a.title as assignment_title, a.max_score, c.title as course_title 
                           FROM assignment_submissions asub 
                           JOIN assignments a ON asub.assignment_id = a.id 
                           JOIN courses c ON a.course_id = c.id 
                           JOIN users u ON asub.student_id = u.id 
                           WHERE c.tutor_id = ? 
                           ORDER BY asub.submitted_at DESC");
    $stmt->execute([$tutor['id']]);
    $submissions = $stmt->fetchAll();

    // Fetch tutor courses for the "Launch" modal
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id = ? ORDER BY title ASC");
    $stmt->execute([$tutor['id']]);
    $my_courses = $stmt->fetchAll();

} catch (Exception $e) { 
    $submissions = []; 
    $my_courses = []; 
}

if (isset($_GET['msg'])) $success_msg = $_GET['msg'];
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Project Audits<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Managing curriculum tasks and evaluating <span style="color: var(--primary); font-weight: 700;">scholarly submissions</span>.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <button onclick="document.getElementById('newAssModal').style.display='flex'" class="btn-premium" style="padding: 12px 24px;">
            <i class="fas fa-plus"></i> LAUNCH ASSIGNMENT
        </button>
    </div>
</header>

<?php if($success_msg): ?> 
    <div style="padding: 20px 24px; background: #ECFDF5; border: 1px solid #10B981; border-radius: 16px; color: #065F46; font-size: 0.85rem; font-weight: 800; margin-bottom: 40px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.4s ease;">
        <i class="fas fa-check-circle" style="font-size: 1.2rem;"></i> <?= htmlspecialchars($success_msg) ?>
    </div>
<?php endif; ?>

<?php if($error): ?> 
    <div style="padding: 20px 24px; background: #FEF2F2; border: 1px solid #EF4444; border-radius: 16px; color: #991B1B; font-size: 0.85rem; font-weight: 800; margin-bottom: 40px; display: flex; align-items: center; gap: 12px; animation: slideIn 0.4s ease;">
        <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>


    <div class="premium-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 32px; background: #f8fafc; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 950; color: var(--text-main);">Latest Submissions</h3>
            <span style="padding: 6px 14px; background: white; border: 1px solid #e2e8f0; border-radius: 50px; font-size: 0.7rem; font-weight: 950; color: var(--primary); text-transform: uppercase; letter-spacing: 1px;"><?= count($submissions) ?> RECEIVED</span>
        </div>

        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
                <thead>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Scholar</th>
                        <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Assignment</th>
                        <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Submission Date</th>
                        <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Deliverable</th>
                        <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Status & Score</th>
                        <th style="padding: 24px 32px; text-align: right; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($submissions)): ?>
                        <?php foreach($submissions as $s): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: 0.2s;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 24px 32px;">
                                <div style="font-weight: 900; color: var(--text-main); font-size: 0.95rem; letter-spacing: -0.3px;"><?= htmlspecialchars($s['student_name']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-dim); font-weight: 700; text-transform: uppercase; margin-top: 4px;"><?= htmlspecialchars($s['course_title']) ?></div>
                            </td>
                            <td style="padding: 24px 32px;">
                                <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem;"><?= htmlspecialchars($s['assignment_title']) ?></div>
                            </td>
                            <td style="padding: 24px 32px;">
                                <div style="font-weight: 700; color: var(--text-dim); font-size: 0.85rem;"><?= date('M j, Y', strtotime($s['submitted_at'])) ?></div>
                            </td>
                            <td style="padding: 24px 32px;">
                                <a href="../uploads/assignments/<?= $s['file_url'] ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 8px; color: var(--primary); text-decoration: none; font-weight: 800; font-size: 0.8rem; padding: 6px 12px; background: #E0F7FF; border-radius: 50px;">
                                    <i class="fas fa-file-download"></i> DOWNLOAD WORK
                                </a>
                            </td>
                            <td style="padding: 24px 32px;">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span style="font-size: 1rem; font-weight: 950; color: var(--text-main);"><?= $s['score'] !== null ? $s['score'] . '/' . $s['max_score'] : '--' ?></span>
                                    <span style="padding: 4px 10px; background: <?= $s['status'] == 'graded' ? '#ECFDF5' : '#FFFBEB' ?>; color: <?= $s['status'] == 'graded' ? '#10B981' : '#F59E0B' ?>; border-radius: 50px; font-size: 0.65rem; font-weight: 950; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid <?= $s['status'] == 'graded' ? '#10B981' : '#F59E0B' ?>44;"><?= $s['status'] ?></span>
                                </div>
                            </td>
                            <td style="padding: 24px 32px; text-align: right;">
                                <button onclick="openGradeModal('<?= $s['id'] ?>', '<?= addslashes($s['student_name']) ?>', '<?= $s['max_score'] ?>', '<?= addslashes($s['notes']) ?>')" class="btn-premium" style="padding: 8px 16px; font-size: 0.75rem; background: #F8FAFC; color: var(--text-main); border: 1px solid #E2E8F0; box-shadow: none;">REVIEW & GRADE</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="padding: 100px 40px; text-align: center;">
                                <div style="width: 80px; height: 80px; background: #F8FAFC; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 2rem; color: #CBD5E1;"><i class="fas fa-inbox"></i></div>
                                <h3 style="margin: 0 0 8px; font-weight: 950; font-size: 1.25rem;">No submissions yet.</h3>
                                <p style="color: var(--text-dim); font-weight: 600;">Launch your first task to start receiving scholarly work.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Create Assignment Modal -->
<div id="newAssModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(12px); z-index: 9999; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;">
    <div class="premium-card" style="width: 90%; max-width: 550px; padding: 48px; position: relative; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <button onclick="document.getElementById('newAssModal').style.display='none'" style="position: absolute; right: 24px; top: 24px; background: none; border: none; font-size: 1.5rem; color: #94A3B8; cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='var(--text-main)'">&times;</button>
        
        <h2 style="margin: 0 0 12px; font-size: 1.6rem; font-weight: 950; letter-spacing: -0.8px; color: var(--text-main);">Launch New Assignment</h2>
        <p style="color: var(--text-dim); margin-bottom: 40px; font-weight: 600;">Synchronize a new task across your academic program.</p>

        <form method="POST" action="assignments.php">
            <input type="hidden" name="save_assignment" value="1">
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Academic Track</label>
                <select name="course_id" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; background: #f8fafc;" required>
                    <?php foreach($my_courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Assignment Title</label>
                <input type="text" name="title" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" placeholder="e.g. Phase 1: Institutional Core" required>
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Task Description</label>
                <textarea name="description" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; min-height: 120px; resize: none;" placeholder="Detailed instructions for scholars..."></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 40px;">
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Deadline</label>
                    <input type="date" name="due_date" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" required>
                </div>
                <div>
                    <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Max Score</label>
                    <input type="number" name="max_score" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" value="100">
                </div>
            </div>
            <button type="submit" class="btn-premium" style="width: 100%; height: 58px;">DEPLOY ASSIGNMENT</button>
        </form>
    </div>
</div>

<!-- Grading Modal -->
<div id="gradeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(12px); z-index: 9999; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;">
    <div class="premium-card" style="width: 90%; max-width: 550px; padding: 48px; position: relative; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <button onclick="document.getElementById('gradeModal').style.display='none'" style="position: absolute; right: 24px; top: 24px; background: none; border: none; font-size: 1.5rem; color: #94A3B8; cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='var(--text-main)'">&times;</button>
        
        <h2 style="margin: 0 0 12px; font-size: 1.6rem; font-weight: 950; letter-spacing: -0.8px; color: var(--text-main);">Audit Submission</h2>
        <p style="color: var(--text-dim); margin-bottom: 32px; font-weight: 600;" id="subMeta">Evaluating work for: Alexander Hamilton</p>

        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 20px; border-radius: 16px; margin-bottom: 32px;">
            <label style="display: block; font-size: 0.65rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Scholar Notes</label>
            <p id="subNotes" style="margin: 0; font-size: 0.9rem; color: var(--text-main); font-weight: 600; font-style: italic;">"Attached is my project core documentation."</p>
        </div>

        <form method="POST" action="assignments.php">
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="submission_id" id="modalSubId">
            
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Scholarly Score (Max: <span id="maxPoints">100</span>)</label>
                <input type="number" name="score" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" step="0.5" required>
            </div>
            
            <div style="margin-bottom: 40px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Constructive Feedback</label>
                <textarea name="feedback" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; min-height: 120px; resize: none;" placeholder="Provide insights on architectural integrity..."></textarea>
            </div>
            
            <button type="submit" class="btn-premium" style="width: 100%; height: 58px;">CONFIRM AUDIT & NOTIFY</button>
        </form>
    </div>
</div>

<script>
function openGradeModal(id, name, max, notes) {
    document.getElementById('modalSubId').value = id;
    document.getElementById('subMeta').textContent = "Evaluating work for: " + name;
    document.getElementById('maxPoints').textContent = max;
    document.getElementById('subNotes').textContent = notes || "No additional context provided.";
    document.getElementById('gradeModal').style.display = 'flex';
}

// Close modal on backdrop click
window.addEventListener('click', (e) => {
    const newAssModal = document.getElementById('newAssModal');
    const gradeModal = document.getElementById('gradeModal');
    if (e.target === newAssModal) newAssModal.style.display = 'none';
    if (e.target === gradeModal) gradeModal.style.display = 'none';
});
</script>
</body>
</html>
