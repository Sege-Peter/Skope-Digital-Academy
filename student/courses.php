<?php
$pageTitle = 'My Learning Path';
require_once 'includes/header.php';

try {
    // Fetch all active enrollments for this student
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.thumbnail, c.level, cat.name as category_name, u.name as tutor_name
                           FROM enrollments e
                           JOIN courses c ON e.course_id = c.id
                           JOIN users u ON c.tutor_id = u.id
                           LEFT JOIN categories cat ON c.category_id = cat.id
                           WHERE e.student_id = ? AND e.status = 'active'
                           ORDER BY e.enrolled_at DESC");
    $stmt->execute([$student['id']]);
    $enrollments = $stmt->fetchAll();

    // Fetch categorized stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'active'");
    $stmt->execute([$student['id']]);
    $active_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'completed'");
    $stmt->execute([$student['id']]);
    $completed_count = $stmt->fetchColumn();

} catch (Exception $e) {
    error_log($e->getMessage());
    $enrollments = [];
    $active_count = $completed_count = 0;
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    .learning-hero {
        background: var(--dark-card);
        border: 1px solid var(--dark-border);
        border-radius: 32px;
        padding: 40px;
        margin-bottom: 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
        overflow: hidden;
    }
    .learning-hero::after {
        content: '';
        position: absolute;
        right: -50px;
        top: -50px;
        width: 200px;
        height: 200px;
        background: var(--primary);
        filter: blur(100px);
        opacity: 0.1;
        pointer-events: none;
    }

    .courses-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 32px;
    }

    .course-card-premium {
        background: white;
        border: 1px solid var(--dark-border);
        border-radius: 28px;
        overflow: hidden;
        transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex;
        flex-direction: column;
    }
    .course-card-premium:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary);
    }

    .card-thumb-wrap {
        position: relative;
        height: 180px;
        overflow: hidden;
    }
    .card-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: 0.5s;
    }
    .course-card-premium:hover .card-thumb { transform: scale(1.1); }

    .card-level-badge {
        position: absolute;
        top: 16px;
        left: 16px;
        padding: 6px 14px;
        background: rgba(15, 23, 42, 0.8);
        backdrop-filter: blur(8px);
        color: white;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .card-content { padding: 24px; flex: 1; display: flex; flex-direction: column; }
    .card-category { font-size: 0.75rem; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 8px; }
    .card-title { font-family: 'Poppins', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--dark); margin-bottom: 12px; line-height: 1.4; }
    
    .card-tutor { display: flex; align-items: center; gap: 8px; color: var(--text-dim); font-size: 0.82rem; margin-bottom: 24px; }
    .tutor-icon { width: 24px; height: 24px; border-radius: 50%; background: var(--bg-light); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; }

    .progress-section { margin-top: auto; border-top: 1px solid #f1f5f9; padding-top: 20px; }
    .progress-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.82rem; font-weight: 700; }
    
    .btn-resume {
        width: 100%;
        padding: 14px;
        border-radius: 16px;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }

    @media (max-width: 768px) {
        .learning-hero { flex-direction: column; text-align: center; gap: 24px; }
        .courses-grid { grid-template-columns: 1fr; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Learning <span class="text-primary">Repository</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Track your academic growth across all enrolled certifications.</p>
            </div>
        </div>
        <div style="display: flex; gap: 12px;">
            <a href="../courses.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Browse More</a>
        </div>
    </header>

    <div class="learning-hero">
        <div>
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; margin-bottom: 8px;">Hello, <?= explode(' ', $student['name'])[0] ?>! 👋</h2>
            <p style="color: var(--text-dim); font-size: 1rem;">You have <span style="font-weight: 800; color: var(--primary);"><?= $active_count ?> active courses</span> in your curriculum right now.</p>
        </div>
        <div style="display: flex; gap: 40px;">
            <div style="text-align: center;">
                <div style="font-size: 1.8rem; font-weight: 900; color: var(--dark);"><?= $active_count ?></div>
                <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Learning</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 1.8rem; font-weight: 900; color: #10B981;"><?= $completed_count ?></div>
                <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Graduated</div>
            </div>
        </div>
    </div>

    <div class="courses-grid">
        <?php if(!empty($enrollments)): ?>
            <?php foreach($enrollments as $e): ?>
            <div class="course-card-premium">
                <div class="card-thumb-wrap">
                    <img src="../<?= $e['thumbnail'] ? 'uploads/courses/'.$e['thumbnail'] : 'assets/images/course-placeholder.jpg' ?>" class="card-thumb" alt="">
                    <span class="card-level-badge"><?= $e['level'] ?></span>
                </div>
                
                <div class="card-content">
                    <div class="card-category"><?= htmlspecialchars($e['category_name'] ?: 'Curriculum') ?></div>
                    <h3 class="card-title"><?= htmlspecialchars($e['title']) ?></h3>
                    
                    <div class="card-tutor">
                        <div class="tutor-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                        <span>Instructor: <strong><?= htmlspecialchars($e['tutor_name']) ?></strong></span>
                    </div>

                    <div class="progress-section">
                        <div class="progress-header">
                            <span>Course Progress</span>
                            <span class="text-primary"><?= round($e['progress_percent']) ?>%</span>
                        </div>
                        <div style="height: 8px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; background: var(--primary); width: <?= $e['progress_percent'] ?>%; transition: 1s;"></div>
                        </div>

                        <a href="classroom.php?id=<?= $e['course_id'] ?>" class="btn btn-primary btn-resume">
                            Resume Learning <i class="fas fa-play-circle"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 100px 40px; background: white; border: 1px dashed var(--dark-border); border-radius: 40px;">
                <div style="font-size: 4rem; color: var(--primary-glow); margin-bottom: 24px;"><i class="fas fa-book-reader"></i></div>
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.4rem;">Your path is currently empty</h3>
                <p style="color: var(--text-dim); max-width: 400px; margin: 8px auto 32px;">You haven't enrolled in any courses yet. Start your journey by browsing our world-class repository.</p>
                <a href="../courses.php" class="btn btn-primary">Browse Catalog</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
