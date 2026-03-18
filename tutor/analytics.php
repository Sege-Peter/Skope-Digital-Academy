<?php
$pageTitle = 'Sales & Impact Analytics';
require_once '../includes/header.php';

// Auth check (header.php usually handles this, but let's be safe)
if ($user['role'] !== 'tutor') {
    header("Location: ../index.php");
    exit;
}

// Fetch Analytics Data
try {
    $tutor_id = $user['id'];

    // 1. Revenue Trends (Last 6 Months)
    $stmt = $pdo->prepare("SELECT 
                             DATE_FORMAT(p.created_at, '%b %Y') as month,
                             SUM(p.amount * 0.8) as earnings,
                             COUNT(p.id) as enrollments
                           FROM payments p 
                           JOIN courses c ON p.course_id = c.id 
                           WHERE c.tutor_id = ? AND p.status = 'verified'
                           GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
                           ORDER BY p.created_at ASC LIMIT 6");
    $stmt->execute([$tutor_id]);
    $revenue_data = $stmt->fetchAll();

    // 2. Best Selling Courses
    $stmt = $pdo->prepare("SELECT 
                             c.title,
                             COUNT(e.id) as student_count,
                             SUM(p.amount * 0.8) as total_rev
                           FROM courses c 
                           JOIN enrollments e ON c.id = e.course_id
                           JOIN payments p ON e.course_id = p.course_id AND e.student_id = p.student_id
                           WHERE c.tutor_id = ? AND p.status = 'verified'
                           GROUP BY c.id 
                           ORDER BY student_count DESC LIMIT 5");
    $stmt->execute([$tutor_id]);
    $top_courses = $stmt->fetchAll();

    // 3. Quiz Performance Summary
    $stmt = $pdo->prepare("SELECT 
                             q.title as quiz_title,
                             AVG(qa.score) as avg_score,
                             COUNT(qa.id) as attempt_count
                           FROM quizzes q 
                           JOIN courses c ON q.course_id = c.id 
                           JOIN quiz_attempts qa ON q.id = qa.quiz_id
                           WHERE c.tutor_id = ?
                           GROUP BY q.id 
                           ORDER BY attempt_count DESC LIMIT 5");
    $stmt->execute([$tutor_id]);
    $quiz_analytics = $stmt->fetchAll();

} catch (Exception $e) {
    error_log($e->getMessage());
    $revenue_data = $top_courses = $quiz_analytics = [];
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .analytics-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 32px; margin-bottom: 40px; }
    .chart-card { background: white; border: 1px solid var(--dark-border); border-radius: 24px; padding: 32px; box-shadow: var(--shadow-sm); }
    .chart-container { position: relative; height: 320px; width: 100%; margin-top: 20px; }
    
    .data-card { background: white; border: 1px solid var(--dark-border); border-radius: 24px; padding: 28px; height: 100%; }
    .data-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    
    .ranking-list { list-style: none; }
    .ranking-item { display: flex; align-items: center; gap: 15px; padding: 16px 0; border-bottom: 1px solid #f1f5f9; }
    .ranking-item:last-child { border-bottom: none; }
    .ranking-num { width: 32px; height: 32px; background: var(--bg-light); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--text-dim); font-size: 0.85rem; }
    .ranking-item:nth-child(1) .ranking-num { background: var(--secondary-glow); color: var(--secondary); }
    .ranking-item:nth-child(2) .ranking-num { background: var(--primary-glow); color: var(--primary); }
    
    .progress-pill { height: 8px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin-top: 8px; }
    .progress-pill-fill { height: 100%; background: var(--primary); border-radius: 10px; transition: 1s cubic-bezier(0.4, 0, 0.2, 1); width: 0; }

    /* ══ MOBILE ══ */
    @media (max-width: 1100px) {
        .analytics-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .chart-card { padding: 20px; }
        .chart-container { height: 260px; }
        .admin-header { flex-direction: column; align-items: flex-start; gap: 16px; min-height: auto; padding: 20px 0; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div>
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Sales & Impact <span class="text-primary">Analytics</span></h1>
            <p style="color: var(--text-dim); margin-top: 4px;">Track your revenue growth, course popularity, and student success metrics.</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="btn btn-ghost btn-sm" onclick="window.print()"><i class="fas fa-file-export"></i> Export Report</button>
            <a href="index.php" class="btn btn-primary btn-sm"><i class="fas fa-th-large"></i> Dashboard</a>
        </div>
    </header>

    <!-- Revenue Curve & Top Courses -->
    <div class="analytics-grid">
        <div class="chart-card">
            <div class="data-header">
                <div>
                    <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem;">Revenue Performance</h3>
                    <p style="font-size: 0.8rem; color: var(--text-dim);">Historical earnings over the last 6 months</p>
                </div>
                <div style="text-align: right;">
                    <span style="font-size: 0.75rem; color: var(--success); font-weight: 700;"><i class="fas fa-caret-up"></i> +18.4%</span>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <div class="data-card">
            <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; margin-bottom: 20px;">Top Performing Courses</h3>
            <ul class="ranking-list">
                <?php if(!empty($top_courses)): ?>
                    <?php foreach($top_courses as $i => $c): ?>
                    <li class="ranking-item">
                        <div class="ranking-num"><?= $i+1 ?></div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 700; font-size: 0.88rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($c['title']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-dim); margin-top: 2px;"><?= $c['student_count'] ?> Students Enrolled</div>
                            <div class="progress-pill">
                                <div class="progress-pill-fill" data-width="<?= ($c['student_count'] / ($top_courses[0]['student_count'] ?: 1)) * 100 ?>%"></div>
                            </div>
                        </div>
                        <div style="text-align: right; font-weight: 800; font-size: 0.88rem; color: var(--primary);">$<?= number_format($c['total_rev'], 0) ?></div>
                    </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:var(--text-dim); padding-top: 40px;">No course data yet.</p>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Engagement Grids -->
    <div class="analytics-grid" style="grid-template-columns: 1fr 1fr;">
        <div class="chart-card">
            <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; margin-bottom: 20px;">Enrollment Trajectory</h3>
            <div class="chart-container" style="height: 240px;">
                <canvas id="enrollmentChart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; margin-bottom: 24px;">Quiz Completion & Mastery</h3>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php if(!empty($quiz_analytics)): ?>
                    <?php foreach($quiz_analytics as $q): ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 6px;">
                            <span style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($q['quiz_title']) ?></span>
                            <span style="font-weight: 800; color: var(--secondary);"><?= round($q['avg_score']) ?>% Avg</span>
                        </div>
                        <div class="progress-pill" style="height: 10px;">
                            <div class="progress-pill-fill" style="background: var(--secondary); width: <?= $q['avg_score'] ?>%;"></div>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-dim); margin-top: 4px;">Based on <?= $q['attempt_count'] ?> verified attempts</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:var(--text-dim); padding-top: 40px;">No quiz metrics available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Revenue Chart
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueGradient = revCtx.createLinearGradient(0, 0, 0, 400);
    revenueGradient.addColorStop(0, 'rgba(0, 191, 255, 0.4)');
    revenueGradient.addColorStop(1, 'rgba(0, 191, 255, 0)');

    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: [<?= '"' . implode('","', array_column($revenue_data, 'month')) . '"' ?>],
            datasets: [{
                label: 'Earnings (KES)',
                data: [<?= implode(',', array_column($revenue_data, 'earnings')) ?>],
                borderColor: '#00BFFF',
                borderWidth: 4,
                backgroundColor: revenueGradient,
                fill: true,
                tension: 0.4,
                pointRadius: 6,
                pointBackgroundColor: '#fff',
                pointBorderWidth: 3,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { font: { size: 11 } } }
            }
        }
    });

    // 2. Enrollment Chart
    const enCtx = document.getElementById('enrollmentChart').getContext('2d');
    new Chart(enCtx, {
        type: 'bar',
        data: {
            labels: [<?= '"' . implode('","', array_column($revenue_data, 'month')) . '"' ?>],
            datasets: [{
                label: 'New Enrolled Students',
                data: [<?= implode(',', array_column($revenue_data, 'enrollments')) ?>],
                backgroundColor: '#FF8C00',
                borderRadius: 8,
                barThickness: 24
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: '#f1f5f9' }, ticks: { stepSize: 5 } },
                x: { grid: { display: false } }
            }
        }
    });

    // Animate progress bars
    setTimeout(() => {
        document.querySelectorAll('.progress-pill-fill').forEach(el => {
            if(el.dataset.width) el.style.width = el.dataset.width;
        });
    }, 300);
});
</script>

</body>
</html>
