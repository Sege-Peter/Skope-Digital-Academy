<?php
$pageTitle = 'Course Intelligence Detail';
require_once 'includes/header.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: courses.php');
    exit;
}

try {
    // 1. Fetch Course Core Info
    $stmt = $pdo->prepare("SELECT c.*, u.name as tutor_name, u.avatar as tutor_avatar, cat.name as category_name
                           FROM courses c
                           JOIN users u ON c.tutor_id = u.id
                           LEFT JOIN categories cat ON c.category_id = cat.id
                           WHERE c.id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: courses.php');
        exit;
    }

    // 2. Fetch Aggregated Stats
    $stmt = $pdo->prepare("SELECT 
                            (SELECT COUNT(*) FROM enrollments WHERE course_id = ?) as total_students,
                            (SELECT SUM(amount) FROM payments WHERE course_id = ? AND status = 'verified') as total_revenue,
                            (SELECT COUNT(*) FROM lessons WHERE course_id = ?) as lesson_count");
    $stmt->execute([$id, $id, $id]);
    $stats = $stmt->fetch();

    // 3. Fetch Recent Enrolled Students
    $stmt = $pdo->prepare("SELECT e.*, u.name, u.email, u.last_login 
                           FROM enrollments e 
                           JOIN users u ON e.student_id = u.id 
                           WHERE e.course_id = ? 
                           ORDER BY e.enrolled_at DESC LIMIT 10");
    $stmt->execute([$id]);
    $students = $stmt->fetchAll();

    // 4. Fetch Lessons
    $stmt = $pdo->prepare("SELECT * FROM lessons WHERE course_id = ? ORDER BY order_num ASC");
    $stmt->execute([$id]);
    $lessons = $stmt->fetchAll();

    // 5. Handle Status Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $new_status = $_POST['update_status'];
        $stmt = $pdo->prepare("UPDATE courses SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        $course['status'] = $new_status; // Update local state for display
        $success_action = "Course status updated to " . ucfirst($new_status);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    header('Location: courses.php');
    exit;
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .detail-hero { background: white; border: 1px solid var(--dark-border); border-radius: 24px; padding: 40px; margin-bottom: 32px; display: grid; grid-template-columns: 200px 1fr auto; gap: 40px; align-items: center; }
    .course-poster { width: 200px; height: 130px; border-radius: 16px; object-fit: cover; border: 1px solid var(--dark-border); }
    
    .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 48px; }
    .stat-pill { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 24px; }
    
    .content-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 32px; }
    
    .action-btn-group { display: flex; flex-direction: column; gap: 12px; }
    
    .user-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
    .user-row:last-child { border: none; }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Course <span class="text-primary">Intelligence</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Deep-dive into curriculum performance and student engagement metrics.</p>
            </div>
        </div>
        <div>
            <a href="courses.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back to Catalog</a>
        </div>
    </header>

    <div class="admin-body">
        <div class="detail-hero">
            <img src="../uploads/courses/<?= $course['thumbnail'] ?: 'default-course.jpg' ?>" class="course-poster">
            <div>
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <span class="badge badge-<?= $course['status'] ?>"><?= $course['status'] ?></span>
                    <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-dim);"><?= htmlspecialchars($course['category_name']) ?></span>
                </div>
                <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem; margin-bottom: 8px;"><?= htmlspecialchars($course['title']) ?></h2>
                <div style="display: flex; align-items: center; gap: 12px; color: var(--text-muted); font-size: 0.9rem;">
                    <img src="../uploads/avatars/<?= $course['tutor_avatar'] ?: 'default-avatar.png' ?>" style="width: 24px; height: 24px; border-radius: 50%;">
                    <span>Authored by <strong><?= htmlspecialchars($course['tutor_name']) ?></strong></span>
                </div>
            </div>
            <div class="action-btn-group">
                <form method="POST">
                    <?php if($course['status'] !== 'published'): ?>
                        <button type="submit" name="update_status" value="published" class="btn btn-primary btn-sm" style="width: 160px; margin-bottom: 8px;">
                            <i class="fas fa-check"></i> Approve Course
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-ghost btn-sm" style="width: 160px; margin-bottom: 8px; color: var(--success); cursor: default; border: 1px solid var(--success);">
                            <i class="fas fa-check-circle"></i> Live on Site
                        </button>
                    <?php endif; ?>
                    
                    <?php if($course['status'] !== 'archived'): ?>
                        <button type="submit" name="update_status" value="archived" class="btn btn-ghost btn-sm" style="width: 160px; color: var(--danger);">
                            <i class="fas fa-ban"></i> Archive Course
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-pill">
                <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px;">Life-time Revenue</div>
                <div style="font-size: 1.5rem; font-weight: 900; color: var(--dark);">KES <?= number_format($stats['total_revenue'] ?: 0) ?></div>
            </div>
            <div class="stat-pill">
                <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px;">Enrolled Students</div>
                <div style="font-size: 1.5rem; font-weight: 900; color: var(--dark);"><?= number_format($stats['total_students']) ?></div>
            </div>
            <div class="stat-pill">
                <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px;">Curriculum Depth</div>
                <div style="font-size: 1.5rem; font-weight: 900; color: var(--dark);"><?= $stats['lesson_count'] ?> Modules</div>
            </div>
            <div class="stat-pill">
                <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 8px;">Market Valuation</div>
                <div style="font-size: 1.5rem; font-weight: 900; color: var(--primary);">KES <?= number_format($course['price']) ?></div>
            </div>
        </div>

        <div class="content-grid">
            <div class="table-card">
                <div class="table-header">
                    <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem;">Academic Structure</h3>
                    <span style="font-size: 0.75rem; color: var(--text-dim);"><?= count($lessons) ?> Total Lessons</span>
                </div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Order</th>
                                <th>Lesson Title</th>
                                <th>Type</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($lessons as $l): ?>
                            <tr>
                                <td style="font-weight: 800; color: var(--text-dim);"><?= $l['order_num'] ?></td>
                                <td style="font-weight: 700; color: var(--dark);"><?= htmlspecialchars($l['title']) ?></td>
                                <td>
                                    <?php if($l['lesson_type'] === 'video'): ?>
                                        <i class="fas fa-play-circle text-primary"></i> Video
                                    <?php else: ?>
                                        <i class="fas fa-file-pdf text-secondary"></i> Resource
                                    <?php endif; ?>
                                </td>
                                <td><?= $l['duration_mins'] ?>m</td>
                                <td><span class="status-badge status-active">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($lessons)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 40px; color: var(--text-dim);">No lessons added yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside>
                <div class="table-card" style="margin-bottom: 32px;">
                    <div class="table-header">
                        <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem;">Recent Enrolments</h3>
                    </div>
                    <div style="padding: 24px;">
                        <?php foreach($students as $s): ?>
                        <div class="user-row">
                            <div style="width: 40px; height: 40px; background: var(--bg-light); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--primary);">
                                <?= strtoupper(substr($s['name'], 0, 1)) ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 700; font-size: 0.9rem; color: var(--dark);"><?= htmlspecialchars($s['name']) ?></div>
                                <div style="font-size: 0.72rem; color: var(--text-dim);"><?= $s['email'] ?></div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.8rem; font-weight: 800; color: var(--dark);"><?= round($s['progress_percent']) ?>%</div>
                                <div style="font-size: 0.65rem; color: var(--text-dim); text-transform: uppercase;">Progress</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if(empty($students)): ?>
                            <p style="text-align: center; color: var(--text-dim); font-style: italic;">No students enrolled yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="table-card" style="padding: 32px; background: var(--dark); color: white;">
                    <h3 style="font-family: 'Poppins', sans-serif; font-size: 1rem; margin-bottom: 20px;">Platform Health Notice</h3>
                    <p style="font-size: 0.82rem; line-height: 1.6; opacity: 0.7; margin-bottom: 24px;">
                        This course is currently contributing 12.5% of the total academy revenue. 
                        Engagement levels are high. Ensure the mentor responds to support tickets within 12 hours.
                    </p>
                    <button class="btn btn-primary btn-block btn-sm">Message Instructor</button>
                </div>
            </aside>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        <?php if(isset($success_action)): ?>
            SDA.showToast('<?= $success_action ?>', 'success');
        <?php endif; ?>
    });
</script>
</body>
</html>
