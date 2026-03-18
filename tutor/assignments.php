<?php
$pageTitle = 'Manage Assignments';
require_once 'includes/header.php';

$success_msg = '';
$error_msg = '';

// 1. Handle Create Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assignment'])) {
    $course_id = (int)$_POST['course_id'];
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $max_score = (int)$_POST['max_score'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO assignments (course_id, title, description, due_date, max_score) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $title, $desc, $due_date, $max_score]);
        $success_msg = "Assignment published.";
    } catch (Exception $e) { $error_msg = "Error: " . $e->getMessage(); }
}

// 2. Handle Grading Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $sid = (int)$_POST['submission_id'];
    $score = (float)$_POST['score'];
    $feedback = trim($_POST['feedback']);
    
    try {
        $stmt = $pdo->prepare("UPDATE assignment_submissions SET score = ?, feedback = ?, status = 'graded', graded_at = NOW() WHERE id = ?");
        $stmt->execute([$score, $feedback, $sid]);
        
        // Notify student
        $stmt = $pdo->prepare("SELECT student_id, assignment_id FROM assignment_submissions WHERE id = ?");
        $stmt->execute([$sid]);
        $sub = $stmt->fetch();
        if ($sub) {
            $stmt = $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, 'student', ?)");
            $stmt->execute(["Assignment Graded", "Your assignment submission has been graded. Check your dashboard for feedback.", $sub['student_id']]);
        }
        
        $success_msg = "Submission graded successfully.";
    } catch (Exception $e) { $error_msg = "Error grading: " . $e->getMessage(); }
}

// 3. Fetch Tutor's Courses
$stmt = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id = ?");
$stmt->execute([$tutor['id']]);
$my_courses = $stmt->fetchAll();

// 4. Fetch Submissions for Tutor's courses
try {
    $stmt = $pdo->prepare("SELECT s.*, u.name as student_name, a.title as assignment_title, a.max_score, c.title as course_title 
                           FROM assignment_submissions s 
                           JOIN assignments a ON s.assignment_id = a.id 
                           JOIN courses c ON a.course_id = c.id 
                           JOIN users u ON s.student_id = u.id
                           WHERE c.tutor_id = ? 
                           ORDER BY s.submitted_at DESC");
    $stmt->execute([$tutor['id']]);
    $submissions = $stmt->fetchAll();
} catch (Exception $e) { $submissions = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <h1 style="font-size: 1.25rem; font-weight: 700;">Assignments & Grading</h1>
        <button class="btn btn-primary btn-sm" onclick="document.getElementById('newAssModal').style.display='flex'">
            <i class="fas fa-plus"></i> Create Assignment
        </button>
    </header>

    <div class="admin-body">
        <?php if($success_msg): ?> <div class="alert alert-success"><?= $success_msg ?></div> <?php endif; ?>
        <?php if($error_msg): ?> <div class="alert alert-danger"><?= $error_msg ?></div> <?php endif; ?>

        <!-- Pending Submissions -->
        <section style="margin-bottom: 40px;">
            <h3 style="margin-bottom: 24px; font-size: 1.1rem;">Latest Submissions</h3>
            <div class="table-card">
               <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Assignment</th>
                                <th>Submitted On</th>
                                <th>File Name</th>
                                <th>Grade</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($submissions as $s): ?>
                            <tr class="user-row">
                                <td>
                                    <strong><?= htmlspecialchars($s['student_name']) ?></strong><br>
                                    <small style="color:var(--text-dim)"><?= htmlspecialchars($s['course_title']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($s['assignment_title']) ?></td>
                                <td><?= date('M j, Y', strtotime($s['submitted_at'])) ?></td>
                                <td>
                                    <a href="../uploads/assignments/<?= $s['file_url'] ?>" target="_blank" style="color:var(--primary); font-size:0.88rem; text-decoration:none;">
                                        <i class="fas fa-file-download"></i> Download Work
                                    </a>
                                </td>
                                <td>
                                    <strong><?= $s['score'] !== null ? $s['score'] . '/' . $s['max_score'] : '--' ?></strong>
                                    <span class="badge <?= $s['status'] == 'graded' ? 'badge-success' : 'badge-warning' ?>"><?= $s['status'] ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-ghost btn-sm" onclick="openGradeModal('<?= $s['id'] ?>', '<?= addslashes($s['student_name']) ?>', '<?= $s['max_score'] ?>', '<?= addslashes($s['notes']) ?>')">Review & Grade</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
               </div>
            </div>
            <?php if(empty($submissions)): ?>
                <div class="card" style="text-align: center; padding: 60px; border-style: dashed;">
                    <i class="fas fa-inbox" style="font-size: 2.5rem; color: var(--dark-border); margin-bottom: 20px;"></i>
                    <p style="color: var(--text-muted);">No student submissions yet. Start assigning tasks to see them here!</p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Create Assignment Modal -->
<div class="modal" id="newAssModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 500px; padding: 32px;">
         <h3 style="margin-bottom: 24px;">Post New Assignment</h3>
         <form method="POST" action="assignments.php">
             <div class="form-group">
                 <label class="form-label">Course</label>
                 <select name="course_id" class="form-control" required>
                     <?php foreach($my_courses as $c): ?>
                         <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                     <?php endforeach; ?>
                 </select>
             </div>
             <div class="form-group">
                 <label class="form-label">Title</label>
                 <input type="text" name="title" class="form-control" placeholder="e.g. Unit 1 Practical Project" required>
             </div>
             <div class="form-group">
                 <label class="form-label">Task Description</label>
                 <textarea name="description" class="form-control" style="min-height: 100px;" placeholder="Instructions for students..."></textarea>
             </div>
             <div class="grid-2" style="gap: 16px;">
                 <div class="form-group">
                     <label class="form-label">Due Date</label>
                     <input type="date" name="due_date" class="form-control" required>
                 </div>
                 <div class="form-group">
                     <label class="form-label">Max Score</label>
                     <input type="number" name="max_score" class="form-control" value="100">
                 </div>
             </div>
             <div style="display: flex; gap: 12px; margin-top: 24px;">
                 <button type="submit" name="save_assignment" class="btn btn-primary btn-block">Launch Assignment</button>
                 <button type="button" class="btn btn-ghost" onclick="document.getElementById('newAssModal').style.display='none'">Cancel</button>
             </div>
         </form>
    </div>
</div>

<!-- Grading Modal -->
<div class="modal" id="gradeModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 2000; align-items: center; justify-content: center;">
    <div class="card" style="width: 500px; padding: 40px; position: relative;">
        <button onclick="document.getElementById('gradeModal').style.display='none'" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: var(--text-dim); cursor: pointer; font-size: 1.2rem;"><i class="fas fa-times"></i></button>
        <h2 style="margin-bottom: 8px;">Grade Submission</h2>
        <p style="color: var(--text-muted); margin-bottom: 24px;" id="subMeta">Student: </p>
        
        <div style="background: var(--dark-card2); padding: 16px; border-radius: 8px; margin-bottom: 32px; border: 1px solid var(--dark-border);">
           <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;">Student Notes</div>
           <div id="subNotes" style="font-size: 0.9rem; margin-top: 8px; font-style: italic;">...</div>
        </div>

        <form method="POST" action="assignments.php">
            <input type="hidden" name="action" value="grade">
            <input type="hidden" name="submission_id" id="modalSubId">
            
            <div class="form-group">
                <label class="form-label">Score (Max: <span id="maxPoints">100</span>)</label>
                <input type="number" name="score" class="form-control" step="0.5" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Constructive Feedback</label>
                <textarea name="feedback" class="form-control" style="min-height: 100px;" placeholder="Great work on the UI, but check your database normalization..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Confirm Grade & Notify Student</button>
        </form>
    </div>
</div>

<script>
function openGradeModal(id, name, max, notes) {
    document.getElementById('modalSubId').value = id;
    document.getElementById('subMeta').textContent = "Student: " + name;
    document.getElementById('maxPoints').textContent = max;
    document.getElementById('subNotes').textContent = notes || "No notes provided.";
    document.getElementById('gradeModal').style.display = 'flex';
}
</script>
</body>
</html>
