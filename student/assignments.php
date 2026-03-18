<?php
$pageTitle = 'Assignments';
require_once 'includes/header.php';

try {
    // Fetch assignments for enrolled courses
    $stmt = $pdo->prepare("SELECT a.*, c.title as course_title, 
                           (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as sub_status,
                           (SELECT score FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as sub_score
                           FROM assignments a
                           JOIN courses c ON a.course_id = c.id
                           JOIN enrollments e ON e.course_id = c.id
                           WHERE e.student_id = ? AND e.status = 'active'
                           ORDER BY a.due_date ASC");
    $stmt->execute([$student['id'], $student['id'], $student['id']]);
    $assignments = $stmt->fetchAll();
} catch (Exception $e) { $assignments = []; }
?>

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Academic <span class="text-secondary">Assignments</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Track your deadlines and submit your practical projects for evaluation.</p>
            </div>
        </div>
    </header>

    <div class="admin-body">
        <div class="grid-2" style="gap: 32px;">
            <?php foreach($assignments as $a): ?>
            <div class="table-card" style="padding: 32px; border-left: 4px solid <?= $a['sub_status'] ? 'var(--success)' : 'var(--secondary)' ?>;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase;"><?= htmlspecialchars($a['course_title']) ?></div>
                        <h3 style="font-size: 1.25rem; margin-top: 4px;"><?= htmlspecialchars($a['title']) ?></h3>
                    </div>
                    <?php if($a['sub_status']): ?>
                        <span class="badge badge-success">Submitted</span>
                    <?php else: ?>
                        <div style="text-align: right;">
                           <div style="font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase;">Due Date</div>
                           <div style="font-weight: 700; color: var(--danger);"><?= date('M j, Y', strtotime($a['due_date'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <p style="color: var(--text-muted); line-height: 1.6; margin-bottom: 32px;"><?= nl2br(htmlspecialchars($a['description'])) ?></p>
                
                <div style="background: var(--dark-card2); border-radius: 12px; padding: 24px; border: 1px solid var(--dark-border);">
                    <?php if($a['sub_status']): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-dim);">Grade Received</div>
                                <div style="font-size: 1.4rem; font-weight: 800; color: var(--primary);"><?= $a['sub_score'] !== null ? $a['sub_score'] . '/' . $a['max_score'] : 'Pending Grade' ?></div>
                            </div>
                            <button class="btn btn-ghost btn-sm" disabled>Resubmit (Closed)</button>
                        </div>
                    <?php else: ?>
                        <form action="submit-assignment.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.82rem;">Upload Submission (PDF/DOCX/ZIPPED)</label>
                                <input type="file" name="submission_file" class="form-control" style="padding-top: 8px;" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" style="font-size: 0.82rem;">Comments for Tutor</label>
                                <textarea name="notes" class="form-control" style="min-height: 60px;" placeholder="Optional notes..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Turn in Work <i class="fas fa-paper-plane"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if(empty($assignments)): ?>
                <div class="card" style="grid-column: span 2; text-align: center; padding: 100px; border-style: dashed;">
                    <i class="fas fa-tasks" style="font-size: 3rem; color: var(--dark-border); margin-bottom: 24px;"></i>
                    <h2>No pending assignments</h2>
                    <p style="color: var(--text-muted);">You're all caught up! Explore new courses to stay ahead.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
