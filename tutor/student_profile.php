<?php
$pageTitle = 'Student Performance Profile';
require_once '../includes/header.php';

// Auth check
if ($user['role'] !== 'tutor') {
    header('Location: ../login.php');
    exit;
}

$student_id = (int)($_GET['id'] ?? 0);
if (!$student_id) {
    header('Location: students.php');
    exit;
}

try {
    // 1. Fetch Student Basic Info
    $stmt = $pdo->prepare("SELECT name, email, avatar, phone, bio, created_at, last_login, points FROM users WHERE id = ? AND role = 'student'");
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
    $stmt->execute([$student_id, $user['id']]);
    $enrollments = $stmt->fetchAll();

    // 3. Fetch Quiz Performance (Aggregated)
    $stmt = $pdo->prepare("SELECT q.title as quiz_title, qa.score, qa.passed, qa.completed_at, c.title as course_title
                           FROM quiz_attempts qa
                           JOIN quizzes q ON qa.quiz_id = q.id 
                           JOIN courses c ON q.course_id = c.id
                           WHERE qa.student_id = ? AND c.tutor_id = ?
                           ORDER BY qa.completed_at DESC");
    $stmt->execute([$student_id, $user['id']]);
    $quizzes = $stmt->fetchAll();

    // 4. Fetch Assignment Submissions
    $stmt = $pdo->prepare("SELECT asub.*, a.title as assignment_title, c.title as course_title, a.max_score
                           FROM assignment_submissions asub
                           JOIN assignments a ON asub.assignment_id = a.id
                           JOIN courses c ON a.course_id = c.id
                           WHERE asub.student_id = ? AND c.tutor_id = ?
                           ORDER BY asub.submitted_at DESC");
    $stmt->execute([$student_id, $user['id']]);
    $assignments = $stmt->fetchAll();

    // Stats calculations
    $avg_score = count($quizzes) > 0 ? array_sum(array_column($quizzes, 'score')) / count($quizzes) : 0;
    $completion_rate = count($enrollments) > 0 ? (count(array_filter($enrollments, fn($e) => $e['status'] === 'completed')) / count($enrollments)) * 100 : 0;

} catch (Exception $e) {
    error_log($e->getMessage());
    die("An error occurred while fetching the profile.");
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .profile-header-card { background: white; border: 1px solid var(--dark-border); border-radius: 24px; padding: 40px; margin-bottom: 32px; display: flex; gap: 40px; align-items: center; position: relative; overflow: hidden; }
    .profile-header-card::before { content: ''; position: absolute; top: 0; left: 0; width: 6px; height: 100%; background: var(--primary); }
    
    .profile-avatar-large { width: 120px; height: 120px; border-radius: 30px; background: var(--primary-glow); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 800; flex-shrink: 0; }
    .profile-avatar-large img { width: 100%; height: 100%; object-fit: cover; border-radius: 30px; }
    
    .profile-info h1 { font-family: 'Poppins', sans-serif; font-size: 2rem; margin-bottom: 8px; }
    .profile-info p { color: var(--text-dim); font-size: 0.95rem; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    
    .badge-row { display: flex; gap: 12px; }
    .perf-pill { padding: 6px 16px; border-radius: 12px; font-size: 0.82rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
    .pill-blue { background: var(--primary-glow); color: var(--primary); }
    .pill-orange { background: var(--secondary-glow); color: var(--secondary); }
    
    .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 40px; }
    .card-title { font-family: 'Poppins', sans-serif; font-size: 1.1rem; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
    
    .course-mini-card { background: var(--bg-light); border: 1px solid var(--dark-border); border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
    .course-mini-thumb { width: 50px; height: 50px; border-radius: 10px; object-fit: cover; background: #ddd; }
    
    .timeline-item { position: relative; padding-left: 24px; border-left: 2px solid #e2e8f0; margin-bottom: 24px; }
    .timeline-item::before { content: ''; position: absolute; left: -7px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: var(--primary); border: 2px solid white; }
    .timeline-date { font-size: 0.75rem; color: var(--text-dim); font-weight: 700; text-transform: uppercase; margin-bottom: 4px; }
    
    @media (max-width: 1100px) { .profile-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { 
        .profile-header-card { flex-direction: column; text-align: center; padding: 30px; } 
        .profile-header-card::before { width: 100%; height: 6px; top: 0; left: 0; }
        .badge-row { justify-content: center; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div>
            <a href="students.php" style="color: var(--text-dim); font-size: 0.88rem; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px; margin-bottom: 12px;">
                <i class="fas fa-arrow-left"></i> Back to Roster
            </a>
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Performance <span class="text-primary">Profile</span></h1>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="award_student.php?id=<?= $student_id ?>" class="btn btn-secondary btn-sm"><i class="fas fa-trophy"></i> Award Student</a>
            <button class="btn btn-ghost btn-sm" onclick="window.print()"><i class="fas fa-file-pdf"></i> Export Profile</button>
        </div>
    </header>

    <!-- Profile Header -->
    <div class="profile-header-card">
        <div class="profile-avatar-large">
            <?php if ($student['avatar']): ?>
                <img src="../<?= htmlspecialchars($student['avatar']) ?>" alt="">
            <?php else: ?>
                <?= strtoupper(substr($student['name'], 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div class="profile-info" style="flex: 1;">
            <h1><?= htmlspecialchars($student['name']) ?></h1>
            <p><i class="far fa-envelope"></i> <?= htmlspecialchars($student['email']) ?></p>
            <p style="margin-top: -8px;"><i class="fas fa-calendar-alt"></i> Joined <?= date('F Y', strtotime($student['created_at'])) ?> • Last Active <?= $student['last_login'] ? date('M j, g:i a', strtotime($student['last_login'])) : 'Never' ?></p>
            
            <div class="badge-row">
                <div class="perf-pill pill-blue">
                    <i class="fas fa-star"></i> <?= number_format($student['points']) ?> Learning Points
                </div>
                <div class="perf-pill pill-orange">
                    <i class="fas fa-graduation-cap"></i> <?= round($completion_rate) ?>% Graduation Rate
                </div>
                <div class="perf-pill" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                    <i class="fas fa-check-circle"></i> <?= count($quizzes) ?> Assessments Passed
                </div>
            </div>
        </div>
        
        <div style="text-align: right;">
            <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px;">Overall Mastery</div>
            <div style="font-size: 3rem; font-weight: 900; color: var(--primary); line-height: 1;"><?= round($avg_score) ?>%</div>
            <div style="font-size: 0.85rem; font-weight: 700; color: #10B981; margin-top: 4px;"><i class="fas fa-trending-up"></i> Top 10%</div>
        </div>
    </div>

    <div class="profile-grid">
        <!-- Enrolled Courses -->
        <div class="card">
            <h3 class="card-title">Enrolled My Courses <span class="badge badge-secondary"><?= count($enrollments) ?></span></h3>
            <?php if(!empty($enrollments)): ?>
                <?php foreach($enrollments as $e): ?>
                <div class="course-mini-card">
                    <img src="../<?= $e['thumbnail'] ?: 'assets/images/course-placeholder.jpg' ?>" class="course-mini-thumb" alt="">
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 700; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($e['title']) ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-dim);"><?= htmlspecialchars($e['category']) ?> • <?= ucfirst($e['level']) ?></div>
                        
                        <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                            <div style="flex: 1; height: 6px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                                <div style="height: 100%; background: var(--primary); width: <?= $e['progress_percent'] ?>%;"></div>
                            </div>
                            <span style="font-size: 0.82rem; font-weight: 800; color: var(--primary);"><?= round($e['progress_percent']) ?>%</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-dim); padding: 40px 0;">No active enrollments in your courses.</p>
            <?php endif; ?>
        </div>

        <!-- Gradebook / Assessment History -->
        <div class="card">
            <h3 class="card-title">Assessment Mastery <i class="fas fa-award text-secondary"></i></h3>
            <div style="max-height: 480px; overflow-y: auto;">
                <?php if(!empty($quizzes)): ?>
                    <?php foreach($quizzes as $q): ?>
                    <div class="timeline-item">
                        <div class="timeline-date"><?= date('M j, Y', strtotime($q['completed_at'])) ?></div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div style="font-weight: 700; font-size: 0.88rem;"><?= htmlspecialchars($q['quiz_title']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-dim);"><?= htmlspecialchars($q['course_title']) ?></div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 800; color: <?= $q['passed'] ? '#10B981' : '#EF4444' ?>;"><?= round($q['score']) ?>%</div>
                                <div style="font-size: 0.75rem; font-weight: 700; opacity: 0.8;"><?= $q['passed'] ? 'PASSED' : 'RETAKE REQ' ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-dim); padding: 40px 0;">No quiz attempts recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Assignments & Submissions -->
    <div class="card" style="margin-bottom: 40px;">
        <h3 class="card-title">Project Submissions <span class="badge badge-ghost"><?= count($assignments) ?> Submissions</span></h3>
        <div class="table-card" style="border: none; box-shadow: none;">
            <table class="admin-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="padding-left: 0;">Assignment Name</th>
                        <th>Course</th>
                        <th>Submitted On</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th style="padding-right: 0; text-align: right;">Review</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($assignments)): ?>
                        <?php foreach($assignments as $a): ?>
                        <tr>
                            <td style="padding-left: 0; font-weight: 700; color: var(--dark);"><?= htmlspecialchars($a['assignment_title']) ?></td>
                            <td style="font-size: 0.82rem; color: var(--text-dim);"><?= htmlspecialchars($a['course_title']) ?></td>
                            <td><?= date('M j, Y', strtotime($a['submitted_at'])) ?></td>
                            <td>
                                <span class="badge" style="background: <?= $a['status']==='graded' ? 'var(--primary-glow)' : 'var(--bg-light)' ?>; color: <?= $a['status']==='graded' ? 'var(--primary)' : 'var(--text-dim)' ?>;">
                                    <?= ucfirst($a['status']) ?>
                                </span>
                            </td>
                            <td style="font-weight: 800; color: var(--secondary);">
                                <?= $a['score'] !== null ? round($a['score']) . '/' . $a['max_score'] : '-' ?>
                            </td>
                            <td style="padding-right: 0; text-align: right;">
                                <button class="btn btn-ghost btn-sm">View Submission</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 48px; color: var(--text-dim);">No assignments submitted yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

</body>
</html>
