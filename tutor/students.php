<?php
$pageTitle = 'Student Roster';
require_once 'includes/header.php';

$search = $_GET['search'] ?? '';
$course_filter = $_GET['course_id'] ?? '';

try {
    // Fetch tutor's courses for the filter dropdown
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id = ? ORDER BY title ASC");
    $stmt->execute([$user['id']]);
    $tutor_courses = $stmt->fetchAll();

    // Fetch students enrolled in this tutor's courses
    $query = "SELECT u.id as student_id, u.name as student_name, u.email as student_email, u.avatar, u.last_login,
                     c.title as course_title, e.progress_percent, e.enrolled_at, e.course_id,
                     (SELECT AVG(score) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id WHERE qa.student_id = u.id AND q.course_id = c.id) as avg_score
              FROM enrollments e
              JOIN users u ON e.student_id = u.id
              JOIN courses c ON e.course_id = c.id
              WHERE c.tutor_id = ?";
    
    $params = [$user['id']];

    if ($search) {
        $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($course_filter) {
        $query .= " AND e.course_id = ?";
        $params[] = $course_filter;
    }

    $query .= " ORDER BY e.enrolled_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    $students = [];
    $tutor_courses = [];
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    :root {
        --primary-blue: #00BFFF;
        --secondary-orange: #FF8C00;
        --bg-light: #f8fafc;
        --border-color: #e2e8f0;
    }

    .main-content { background: #fff; padding: 40px; }
    
    /* Header Section */
    .dashboard-header { margin-bottom: 48px; display: flex; justify-content: space-between; align-items: flex-end; }
    .header-title h1 { font-family: 'Poppins', sans-serif; font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
    .header-title h1 span { color: var(--primary-blue); }
    .header-title p { color: #64748b; font-size: 0.95rem; }

    /* Filter Bar */
    .filter-card { background: #fff; border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; margin-bottom: 32px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
    .filter-group { display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 250px; }
    .filter-label { font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
    .filter-input { width: 100%; border: 1px solid var(--border-color); border-radius: 12px; padding: 12px 16px; background: var(--bg-light); font-size: 0.9rem; transition: 0.3s; }
    .filter-input:focus { outline: none; border-color: var(--primary-blue); background: #fff; box-shadow: 0 0 0 4px rgba(0,191,255,0.08); }

    /* Table Design */
    .roster-card { border: 1px solid var(--border-color); border-radius: 24px; overflow: hidden; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.02); }
    .roster-table { width: 100%; border-collapse: collapse; }
    .roster-table th { background: #fafafa; padding: 20px 24px; text-align: left; font-size: 0.73rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--border-color); }
    .roster-table td { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    .roster-table tr:last-child td { border-bottom: none; }
    
    .student-info { display: flex; align-items: center; gap: 16px; }
    .avatar-circle { width: 44px; height: 44px; border-radius: 14px; background: rgba(0,191,255,0.08); display: flex; align-items: center; justify-content: center; color: var(--primary-blue); font-weight: 800; font-size: 1.1rem; overflow: hidden; border: 1px solid rgba(0,191,255,0.1); }
    .avatar-circle img { width: 100%; height: 100%; object-fit: cover; }

    .name-stack .name { font-weight: 700; color: #0f172a; font-size: 0.93rem; margin-bottom: 2px; }
    .name-stack .email { font-size: 0.75rem; color: #64748b; }

    .course-tag { display: inline-block; padding: 4px 10px; border-radius: 8px; background: #f1f5f9; color: #475569; font-size: 0.73rem; font-weight: 700; white-space: nowrap; max-width: 180px; overflow: hidden; text-overflow: ellipsis; }

    /* Progress & Performance */
    .progress-bar { width: 100px; height: 6px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin-top: 6px; position: relative; }
    .progress-fill { height: 100%; background: var(--primary-blue); border-radius: 10px; }
    
    .badge-perf { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 99px; font-size: 0.72rem; font-weight: 800; border: 1px solid transparent; }
    .perf-high { background: rgba(16, 185, 129, 0.08); color: #059669; border-color: rgba(16, 185, 129, 0.1); }
    .perf-mid { background: rgba(245, 158, 11, 0.08); color: #d97706; border-color: rgba(245, 158, 11, 0.1); }
    .perf-low { background: rgba(239, 68, 68, 0.08); color: #dc2626; border-color: rgba(239, 68, 68, 0.1); }

    .btn-action { width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: #64748b; background: #fff; transition: 0.2s; cursor: pointer; text-decoration: none; }
    .btn-action:hover { background: #f1f5f9; color: var(--primary-blue); border-color: var(--primary-blue); }
    .btn-action.award:hover { border-color: var(--secondary-orange); color: var(--secondary-orange); }

    @media (max-width: 900px) {
        .filter-card { flex-direction: column; align-items: stretch; }
        .dashboard-header { flex-direction: column; align-items: flex-start; gap: 20px; }
        .roster-card { border: none; border-radius: 0; box-shadow: none; overflow-x: auto; }
        .main-content { padding: 20px; }
    }
</style>

<main class="main-content">
    <div class="dashboard-header">
        <div class="header-title">
            <h1>Student <span>Roster</span></h1>
            <p>Directing academic performance and enrollment of <strong><?= count($students) ?> learners</strong> across your courses.</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="students.php" class="btn btn-ghost" style="padding: 12px 20px; border-radius: 12px; font-weight: 700; color: #64748b; text-decoration: none; display: flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0;">
                <i class="fas fa-sync"></i> Reset Filters
            </a>
        </div>
    </div>

    <form method="GET" action="students.php" class="filter-card shadow-sm">
        <div class="filter-group">
            <span class="filter-label">Student Search</span>
            <input type="text" name="search" class="filter-input" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-group" style="max-width: 300px;">
            <span class="filter-label">Assigned Course</span>
            <select name="course_id" class="filter-input" onchange="this.form.submit()">
                <option value="">Viewing All Courses</option>
                <?php foreach($tutor_courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $course_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height: 50px; padding: 0 32px; border-radius: 14px; font-weight: 800; background: var(--primary-blue); border: none; align-self: flex-end;">
            <i class="fas fa-search" style="margin-right: 8px;"></i> Search
        </button>
    </form>

    <div class="roster-card">
        <table class="roster-table">
            <thead>
                <tr>
                    <th>Scholarly Identity</th>
                    <th>Course Track</th>
                    <th>Completion</th>
                    <th>Academic Health</th>
                    <th>Last Active</th>
                    <th style="min-width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($students)): ?>
                    <?php foreach($students as $s): ?>
                    <tr>
                        <td>
                            <div class="student-info">
                                <div class="avatar-circle">
                                    <?php if($s['avatar']): ?>
                                        <img src="../uploads/avatars/<?= htmlspecialchars($s['avatar']) ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($s['student_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="name-stack">
                                    <div class="name"><?= htmlspecialchars($s['student_name']) ?></div>
                                    <div class="email"><?= htmlspecialchars($s['student_email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="course-tag" title="<?= htmlspecialchars($s['course_title']) ?>">
                                <i class="fas fa-bookmark" style="font-size: 0.65rem; color: var(--primary-blue); margin-right: 4px;"></i>
                                <?= htmlspecialchars($s['course_title']) ?>
                            </div>
                            <div style="font-size: 0.68rem; color: #94a3b8; font-weight: 700; margin-top: 4px; text-transform: uppercase;">Since <?= date('M Y', strtotime($s['enrolled_at'])) ?></div>
                        </td>
                        <td>
                            <div style="font-size: 0.88rem; font-weight: 800; color: var(--primary-blue);"><?= round($s['progress_percent']) ?>%</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $s['progress_percent'] ?>%;"></div>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $score = $s['avg_score'];
                            if($score === null) {
                                echo '<span style="color:#94a3b8; font-size:0.75rem; font-weight:600;">Pending Quiz</span>';
                            } else {
                                $class = ($score >= 80) ? 'perf-high' : (($score >= 60) ? 'perf-mid' : 'perf-low');
                                $icon = ($score >= 80) ? 'fa-rocket' : (($score >= 60) ? 'fa-chart-line' : 'fa-exclamation-circle');
                                echo '<div class="badge-perf ' . $class . '"><i class="fas ' . $icon . '"></i> ' . round($score) . '% Avg</div>';
                            }
                            ?>
                        </td>
                        <td>
                            <div style="font-size: 0.85rem; font-weight: 600; color: #475569;"><?= $s['last_login'] ? date('M d, g:i a', strtotime($s['last_login'])) : 'Never' ?></div>
                        </td>
                        <td>
                            <div style="display:flex; gap:10px;">
                                <a href="award_student.php?id=<?= $s['student_id'] ?>" class="btn-action award" title="Award Merit"><i class="fas fa-trophy"></i></a>
                                <a href="student_profile.php?id=<?= $s['student_id'] ?>" class="btn-action" title="View Transcript"><i class="fas fa-file-invoice"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 100px 40px;">
                            <i class="fas fa-users-slash" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 24px;"></i>
                            <h3 style="color: #64748b; font-weight: 800; font-size: 1.25rem;">No scholars matched.</h3>
                            <p style="color: #94a3b8;">Try clearing your search or filtering by another course track.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
