<?php
$pageTitle = 'Strategic Assignments';
require_once 'includes/header.php';

try {
    // Fetch assignments for enrolled courses
    $stmt = $pdo->prepare("SELECT a.*, c.title as course_title, 
                           (SELECT status FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid) as sub_status,
                           (SELECT score FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid) as sub_score,
                           (SELECT notes FROM assignment_submissions WHERE assignment_id = a.id AND student_id = :sid) as sub_notes
                           FROM assignments a
                           JOIN courses c ON a.course_id = c.id
                           JOIN enrollments e ON e.course_id = c.id
                           WHERE e.student_id = :sid AND e.status = 'active'
                           ORDER BY a.due_date ASC");
    $stmt->execute(['sid' => $student['id']]);
    $assignments = $stmt->fetchAll();
} catch (Exception $e) { $assignments = []; }

require_once 'includes/layout-top.php';

$msg = $_GET['msg'] ?? '';
?>

<header class="portal-header" style="margin-bottom: 40px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Project Milestones<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Translate your theoretical knowledge into high-performance practical results.</p>
    </div>
</header>

<?php if($msg === 'success'): ?>
    <div style="margin-bottom: 32px; padding: 20px 24px; background: #ECFDF5; border: 1px solid #10b981; border-radius: 20px; color: #065f46; display: flex; align-items: center; gap: 16px; font-weight: 700; animation: slideIn 0.4s ease;">
        <i class="fas fa-check-circle" style="font-size: 1.4rem;"></i>
        <span>Mission Accomplished! Your project has been synchronized for evaluation.</span>
    </div>
<?php elseif($msg): ?>
    <div style="margin-bottom: 32px; padding: 20px 24px; background: #FEF2F2; border: 1px solid #ef4444; border-radius: 20px; color: #991b1b; display: flex; align-items: center; gap: 16px; font-weight: 700; animation: slideIn 0.4s ease;">
        <i class="fas fa-exclamation-circle" style="font-size: 1.4rem;"></i>
        <span>System Alert: <?= htmlspecialchars(str_replace('_', ' ', $msg)) ?>. Please verify and retry.</span>
    </div>
<?php endif; ?>

<div class="assignments-stack" style="display: grid; grid-template-columns: 1fr; gap: 32px; margin-bottom: 60px;">
    <?php foreach($assignments as $a): 
        $is_submitted = ($a['sub_status'] !== null);
        $is_overdue = (strtotime($a['due_date']) < time() && !$is_submitted);
    ?>
    <div class="premium-card" style="padding: 0; overflow: hidden; border-radius: 30px; border-left: 8px solid <?= $is_submitted ? '#10b981' : ($is_overdue ? '#ef4444' : 'var(--primary)') ?>;">
        <div style="padding: 40px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 24px; margin-bottom: 32px;">
                <div style="flex: 1; min-width: 300px;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <span style="font-size: 0.72rem; font-weight: 900; color: var(--primary); text-transform: uppercase; letter-spacing: 1px;"><?= htmlspecialchars($a['course_title']) ?></span>
                        <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                        <span style="font-size: 0.72rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Project ID: #<?= $a['id'] ?></span>
                    </div>
                    <h3 style="margin: 0 0 16px; font-size: 1.6rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.5px;"><?= htmlspecialchars($a['title']) ?></h3>
                    <div style="background: #f8fafc; padding: 24px; border-radius: 20px; border: 1px solid #f1f5f9;">
                        <p style="color: var(--text-dim); font-size: 0.95rem; line-height: 1.7; margin: 0; font-weight: 500;"><?= nl2br(htmlspecialchars($a['description'])) ?></p>
                    </div>
                </div>
                <div style="text-align: right; min-width: 180px;">
                    <?php if($is_submitted): ?>
                        <div style="background: #ECFDF5; color: #059669; padding: 10px 20px; border-radius: 50px; font-size: 0.8rem; font-weight: 900; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 5px 15px rgba(16, 185, 129, 0.15);">
                            <i class="fas fa-check-double"></i> SUBMITTED
                        </div>
                    <?php else: ?>
                        <div style="background: <?= $is_overdue ? '#FEF2F2' : '#FFFBEB' ?>; color: <?= $is_overdue ? '#ef4444' : '#D97706' ?>; padding: 10px 20px; border-radius: 50px; font-size: 0.8rem; font-weight: 900; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                            <i class="<?= $is_overdue ? 'fas fa-calendar-times' : 'far fa-calendar-alt' ?>"></i> <?= $is_overdue ? 'OVERDUE' : 'DUE ' . date('M j, Y', strtotime($a['due_date'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($is_submitted): ?>
                <div style="background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%); border: 1px solid #e2e8f0; border-radius: 24px; padding: 32px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 32px;">
                    <div>
                        <span style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Academic Grade</span>
                        <div style="display: flex; align-items: baseline; gap: 8px;">
                            <strong style="font-size: 2.2rem; font-weight: 950; color: var(--text-main);"><?= $a['sub_score'] !== null ? $a['sub_score'] : '--' ?></strong>
                            <span style="font-size: 1.1rem; color: #cbd5e1; font-weight: 800;">/ <?= $a['max_score'] ?: 100 ?></span>
                        </div>
                    </div>
                    <div style="border-left: 1px solid #f1f5f9; padding-left: 32px;">
                        <span style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;">Status Report</span>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 10px; height: 10px; border-radius: 50%; background: <?= $a['sub_score'] !== null ? '#10b981' : '#f59e0b' ?>;"></div>
                            <span style="font-size: 0.9rem; font-weight: 800; color: var(--text-main);"><?= $a['sub_score'] !== null ? "Competency Verified" : "Evaluation in Progress" ?></span>
                        </div>
                    </div>
                    <?php if($a['sub_notes']): ?>
                    <div style="grid-column: 1 / -1; border-top: 1px solid #f1f5f9; padding-top: 24px;">
                        <span style="display: block; font-size: 0.7rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px;">Your Submission Notes</span>
                        <p style="margin: 0; font-size: 0.85rem; font-weight: 600; color: #64748b; background: white; padding: 16px; border-radius: 12px; border: 1px dashed #e2e8f0;"><?= htmlspecialchars($a['sub_notes']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form action="submit-assignment.php" method="POST" enctype="multipart/form-data" class="assignment-form">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                    <div style="background: #f1f5f9; border-radius: 24px; padding: 32px; border: 2px dashed #cbd5e1;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; align-items: flex-end;">
                            <div class="form-group">
                                <label style="display: block; font-size: 0.75rem; font-weight: 900; color: var(--text-main); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">Deploy Source Files</label>
                                <div style="position: relative;">
                                    <input type="file" name="submission_file" required style="opacity: 0; position: absolute; inset: 0; width: 100%; height: 100%; cursor: pointer; z-index: 2;">
                                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 16px 20px; display: flex; align-items: center; gap: 12px; color: #64748b; font-size: 0.85rem; font-weight: 700; transition: 0.3s;">
                                        <i class="fas fa-cloud-upload-alt" style="font-size: 1.2rem; color: var(--primary);"></i>
                                        <span>Select PDF, ZIP, or DOCX...</span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label style="display: block; font-size: 0.75rem; font-weight: 900; color: var(--text-main); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">Strategic Notes (Optional)</label>
                                <input type="text" name="notes" placeholder="e.g. Focus on Chapter 3 implementation..." style="width: 100%; font-size: 0.85rem; padding: 16px 20px; border: 1px solid #e2e8f0; border-radius: 16px; outline: none; background: white; font-weight: 600; transition: 0.3s;">
                            </div>
                            <button type="submit" class="btn-premium" style="height: 56px; border-radius: 16px; justify-content: center; width: 100%; border: none;">SUBMIT FOR REVIEW <i class="fas fa-paper-plane" style="margin-left: 10px;"></i></button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if(empty($assignments)): ?>
        <div class="premium-card" style="text-align: center; padding: 100px 40px; border-style: dashed; border-width: 2px;">
            <div style="width: 80px; height: 80px; background: #f8fafc; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; color: var(--text-dim); font-size: 2rem;">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <h3 style="font-weight: 950; font-size: 1.6rem; color: var(--text-main); margin-bottom: 12px; letter-spacing: -0.5px;">All Objectives Secured</h3>
            <p style="color: var(--text-dim); max-width: 400px; margin: 0 auto 32px; line-height: 1.6; font-weight: 500;">Your academic workspace is clean. No pending assignments require your immediate attention.</p>
            <a href="index.php" class="btn-premium" style="min-width: 220px;">Return to Command Center</a>
        </div>
    <?php endif; ?>
</div>

<style>
@keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.assignment-form input:focus { border-color: var(--primary) !important; box-shadow: 0 0 0 4px rgba(0, 174, 239, 0.1); }
.assignment-form .form-group div:has(input:focus) { border-color: var(--primary) !important; }
</style>

<?php require_once 'includes/layout-bottom.php'; ?>

