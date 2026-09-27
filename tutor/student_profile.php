<?php
$pageTitle = 'Student Performance Profile';
require_once 'includes/header.php';

$tutor = currentUser();

$student_id = (int)($_GET['id'] ?? 0);
if (!$student_id) {
    header('Location: students.php');
    exit;
}

try {
    // 1. Fetch Student Basic Info
    $stmt = $pdo->prepare("SELECT name, email, avatar, phone, bio, created_at, last_login, merit_points FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die("Student profile not found.");
    }

    // 2. Fetch Enrollments in THIS Tutor's courses
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.thumbnail, c.level, cat.name as category 
                           FROM enrollments e 
                           JOIN courses c ON e.course_id = c.id 
                           LEFT JOIN categories cat ON c.category_id = cat.id
                           WHERE e.student_id = ? AND c.tutor_id = ? 
                           ORDER BY e.enrolled_at DESC");
    $stmt->execute([$student_id, $tutor['id']]);
    $enrollments = $stmt->fetchAll();

    // 3. Fetch Quiz Performance (Aggregated)
    $stmt = $pdo->prepare("SELECT q.title as quiz_title, qa.score, qa.passed, qa.completed_at, c.title as course_title
                           FROM quiz_attempts qa
                           JOIN quizzes q ON qa.quiz_id = q.id 
                           JOIN courses c ON q.course_id = c.id
                           WHERE qa.student_id = ? AND c.tutor_id = ?
                           ORDER BY qa.completed_at DESC");
    $stmt->execute([$student_id, $tutor['id']]);
    $quizzes = $stmt->fetchAll();

    // 4. Fetch Assignment Submissions
    $stmt = $pdo->prepare("SELECT asub.*, a.title as assignment_title, c.title as course_title, a.max_score
                           FROM assignment_submissions asub
                           JOIN assignments a ON asub.assignment_id = a.id
                           JOIN courses c ON a.course_id = c.id
                           WHERE asub.student_id = ? AND c.tutor_id = ?
                           ORDER BY asub.submitted_at DESC");
    $stmt->execute([$student_id, $tutor['id']]);
    $assignments = $stmt->fetchAll();

    // Stats calculations
    $avg_score = count($quizzes) > 0 ? array_sum(array_column($quizzes, 'score')) / count($quizzes) : 0;
    $completion_rate = count($enrollments) > 0 ? (count(array_filter($enrollments, fn($e) => $e['status'] === 'completed')) / count($enrollments)) * 100 : 0;

} catch (Exception $e) {
    error_log($e->getMessage());
    die("An error occurred while fetching the profile.");
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    @media (max-width: 1024px) {
        .profile-grid { grid-template-columns: 1fr !important; gap: 32px; }
        .scholar-identity-card { flex-direction: column; text-align: center; gap: 24px; padding: 32px !important; }
        .mastery-score-box { text-align: center !important; padding-left: 0 !important; border-left: none !important; border-top: 1.5px solid #F1F5F9; padding-top: 24px; margin-top: 24px; }
    }
</style>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <a href="students.php" style="color: var(--text-dim); font-size: 0.85rem; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">
            <i class="fas fa-arrow-left"></i> Back to Scholar Roster
        </a>
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Performance Profile<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Directing <span style="color: var(--primary); font-weight: 700;">academic mastery</span> and scholarly progression.</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <button class="btn-premium" onclick="window.print()" style="background: white; color: var(--text-dim); border: 1px solid #E2E8F0; box-shadow: none; font-size: 0.8rem; padding: 12px 24px;">
            <i class="fas fa-file-pdf"></i> EXPORT DOSSIER
        </button>
        <a href="award_student.php?id=<?= $student_id ?>" class="btn-premium" style="padding: 12px 32px;">
            <i class="fas fa-trophy"></i> AWARD MERIT
        </a>
    </div>
</header>

<div class="scholar-identity-card premium-card flex-responsive" style="padding: 48px; margin-bottom: 40px; border-left: 8px solid var(--primary);">
    <div style="width: 160px; height: 160px; border-radius: 50px; background: #F8FAFC; border: 6px solid white; box-shadow: var(--shadow-premium); overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 4rem; font-weight: 950; color: var(--primary); flex-shrink: 0;">
        <?php if ($student['avatar']): ?>
            <img src="../uploads/avatars/<?= htmlspecialchars($student['avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='../assets/images/user-placeholder.png'">
        <?php else: ?>
            <?= strtoupper(substr($student['name'], 0, 1)) ?>
        <?php endif; ?>
    </div>
    <div style="flex: 1;">
        <h2 style="margin: 0; font-size: 2rem; font-weight: 950; color: var(--text-main); letter-spacing: -1px;"><?= htmlspecialchars($student['name']) ?></h2>
        <div style="display: flex; align-items: center; gap: 20px; margin: 12px 0 24px; flex-wrap: wrap;">
            <div style="font-size: 0.9rem; color: var(--text-dim); font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="far fa-envelope"></i> <?= htmlspecialchars($student['email']) ?></div>
            <div style="font-size: 0.9rem; color: var(--text-dim); font-weight: 600; display: flex; align-items: center; gap: 8px;"><i class="fas fa-calendar-alt"></i> Joined <?= date('F Y', strtotime($student['created_at'])) ?></div>
        </div>
        
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <div style="padding: 10px 20px; background: #E0F7FF; color: var(--primary); border-radius: 14px; font-size: 0.8rem; font-weight: 900; display: flex; align-items: center; gap: 10px; border: 1px solid #E0F7FF;">
                <i class="fas fa-star"></i> <?= number_format($student['merit_points']) ?> MERITS
            </div>
            <div style="padding: 10px 20px; background: #FFF7ED; color: #EA580C; border-radius: 14px; font-size: 0.8rem; font-weight: 900; display: flex; align-items: center; gap: 10px; border: 1px solid #FFEDD5;">
                <i class="fas fa-graduation-cap"></i> <?= round($completion_rate) ?>% PROGRESS
            </div>
            <div style="padding: 10px 20px; background: #ECFDF5; color: #10B981; border-radius: 14px; font-size: 0.8rem; font-weight: 900; display: flex; align-items: center; gap: 10px; border: 1px solid #D1FAE5;">
                <i class="fas fa-check-circle"></i> <?= count($quizzes) ?> ASSESSMENTS
            </div>
        </div>
    </div>
    
    <div class="mastery-score-box" style="text-align: right; padding-left: 48px; border-left: 1.5px solid #F1F5F9; margin-left: auto;">
        <div style="font-size: 0.75rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px;">Institutional Mastery</div>
        <div style="font-size: 3.5rem; font-weight: 950; color: var(--primary); line-height: 1; letter-spacing: -2px;"><?= round($avg_score) ?>%</div>
        <div style="font-size: 0.85rem; font-weight: 850; color: #10B981; margin-top: 8px; display: flex; align-items: center; justify-content: flex-end; gap: 6px;">
            <i class="fas fa-arrow-trend-up"></i> SCHOLASTIC ELITE
        </div>
    </div>
</div>

<div class="profile-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px;">
    <!-- Enrolled Courses -->
    <div class="premium-card" style="padding: 40px;">
        <h3 style="margin: 0 0 32px; font-size: 1.25rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
            <span style="width: 8px; height: 32px; background: var(--primary); border-radius: 4px;"></span>
            Track Enrollment <span style="font-size: 0.8rem; background: #F1F5F9; color: var(--text-dim); padding: 4px 12px; border-radius: 50px; margin-left: auto;"><?= count($enrollments) ?> Total</span>
        </h3>
        <?php if(!empty($enrollments)): ?>
            <?php foreach($enrollments as $e): ?>
            <div style="background: #F8FAFC; border: 1.5px solid #F1F5F9; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; margin-bottom: 16px; transition: 0.3s;" onmouseover="this.style.borderColor='var(--primary)'; this.style.background='white'">
                <img src="../<?= $e['thumbnail'] ?: 'assets/images/course-placeholder.jpg' ?>" style="width: 60px; height: 60px; border-radius: 14px; object-fit: cover; background: #E2E8F0; border: 1.5px solid white; box-shadow: var(--shadow-sm);">
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 950; font-size: 1rem; color: var(--text-main); letter-spacing: -0.3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($e['title']) ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-dim); font-weight: 700; margin-top: 4px;"><?= htmlspecialchars($e['category']) ?> • <?= ucfirst($e['level']) ?></div>
                    
                    <div style="display: flex; align-items: center; gap: 16px; margin-top: 12px;">
                        <div style="flex: 1; height: 8px; background: #E2E8F0; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; background: var(--grad-primary); width: <?= $e['progress_percent'] ?>%; border-radius: 10px; transition: 1s cubic-bezier(0.16, 1, 0.3, 1);"></div>
                        </div>
                        <span style="font-size: 0.85rem; font-weight: 950; color: var(--primary);"><?= round($e['progress_percent']) ?>%</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding: 60px 0; text-align: center; color: var(--text-dim); font-weight: 600; font-style: italic;">No active enrollments detected.</div>
        <?php endif; ?>
    </div>

    <!-- Assessment History -->
    <div class="premium-card" style="padding: 40px;">
        <h3 style="margin: 0 0 32px; font-size: 1.25rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
            <span style="width: 8px; height: 32px; background: #10B981; border-radius: 4px;"></span>
            Assessment Mastery <i class="fas fa-award" style="color: #F59E0B; margin-left: auto;"></i>
        </h3>
        <div style="max-height: 520px; overflow-y: auto; padding-right: 12px;">
            <?php if(!empty($quizzes)): ?>
                <?php foreach($quizzes as $q): ?>
                <div style="position: relative; padding-left: 32px; border-left: 2.5px solid #F1F5F9; margin-bottom: 32px;">
                    <div style="position: absolute; left: -8.5px; top: 0; width: 14px; height: 14px; border-radius: 50%; background: white; border: 3px solid <?= $q['passed'] ? '#10B981' : '#EF4444' ?>;"></div>
                    <div style="font-size: 0.7rem; color: var(--text-dim); font-weight: 850; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;"><?= date('M d, Y', strtotime($q['completed_at'])) ?></div>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; background: #F8FAFC; padding: 20px; border-radius: 16px;">
                        <div>
                            <div style="font-weight: 950; font-size: 0.95rem; color: var(--text-main); letter-spacing: -0.3px;"><?= htmlspecialchars($q['quiz_title']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-dim); font-weight: 700; margin-top: 4px;"><?= htmlspecialchars($q['course_title']) ?></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 950; font-size: 1.1rem; color: <?= $q['passed'] ? '#10B981' : '#EF4444' ?>;"><?= round($q['score']) ?>%</div>
                            <div style="font-size: 0.65rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px;"><?= $q['passed'] ? 'SUCCESS' : 'FAILURE' ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 60px 0; text-align: center; color: var(--text-dim); font-weight: 600; font-style: italic;">No assessment records available.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Project Submissions -->
<div class="premium-card" style="padding: 0; overflow: hidden; margin-bottom: 60px;">
    <div style="padding: 32px 40px; border-bottom: 1.5px solid #F1F5F9; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 1.25rem; font-weight: 950; color: var(--text-main); display: flex; align-items: center; gap: 12px;">
            <span style="width: 8px; height: 32px; background: #00BFFF; border-radius: 4px;"></span>
            Project Submissions
        </h3>
        <span style="font-size: 0.75rem; background: #F1F5F9; color: var(--text-dim); padding: 6px 14px; border-radius: 50px; font-weight: 950; text-transform: uppercase; letter-spacing: 1px;"><?= count($assignments) ?> Submissions</span>
    </div>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 900px;">
            <thead>
                <tr style="background: #F8FAFC;">
                    <th style="padding: 24px 40px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Institutional Project</th>
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Curriculum Path</th>
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Submitted On</th>
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Status</th>
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Score</th>
                    <th style="padding: 24px 40px; text-align: right; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($assignments)): ?>
                    <?php foreach($assignments as $a): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: 0.2s;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 24px 40px; font-weight: 950; color: var(--text-main); font-size: 1rem; letter-spacing: -0.3px;"><?= htmlspecialchars($a['assignment_title']) ?></td>
                        <td style="padding: 24px 32px; font-size: 0.85rem; color: var(--text-dim); font-weight: 700;"><?= htmlspecialchars($a['course_title']) ?></td>
                        <td style="padding: 24px 32px; font-size: 0.85rem; color: var(--text-dim); font-weight: 700;"><?= date('M d, Y', strtotime($a['submitted_at'])) ?></td>
                        <td style="padding: 24px 32px;">
                            <span style="padding: 6px 14px; background: <?= $a['status']==='graded' ? '#ECFDF5' : '#F1F5F9' ?>; color: <?= $a['status']==='graded' ? '#10B981' : '#94A3B8' ?>; border-radius: 50px; font-size: 0.65rem; font-weight: 950; text-transform: uppercase; letter-spacing: 1px; border: 1px solid <?= $a['status']==='graded' ? '#D1FAE5' : '#E2E8F0' ?>;">
                                <?= $a['status'] ?>
                            </span>
                        </td>
                        <td style="padding: 24px 32px; font-weight: 950; color: var(--primary); font-size: 1rem;">
                            <?= $a['score'] !== null ? round($a['score']) . ' <span style="color:#94A3B8; font-size:0.8rem; font-weight:700;">/ ' . $a['max_score'] . '</span>' : '<span style="color:#CBD5E1;">PENDING</span>' ?>
                        </td>
                        <td style="padding: 24px 40px; text-align: right;">
                            <a href="assignments.php?id=<?= $a['assignment_id'] ?>" class="btn-premium" style="padding: 8px 16px; font-size: 0.7rem; background: white; color: var(--primary); border: 1px solid var(--primary); box-shadow: none;">VIEW PROJECT</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 80px; color: var(--text-dim); font-weight: 700; font-style: italic;">No scholarly projects submitted to your tracks yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>
</body>
</html>

</body>
</html>
