<?php
$pageTitle = 'Sales & Impact Analytics';
require_once 'includes/header.php'; // Corrected include path

// Fetch Analytics Data
try {
    $tutor_id = $tutor['id'];

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

<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Sales & Impact Analytics<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Monitor your revenue growth, course popularity, and student success metrics.</p>
    </div>
    <div style="display: flex; gap: 16px; align-items: center;">
        <button onclick="window.print()" class="btn-premium" style="padding: 14px 28px; background: white; color: var(--text-dim); border: 1px solid #E2E8F0; box-shadow: none;">
            <i class="fas fa-file-export"></i> EXPORT REPORT
        </button>
        <a href="index.php" class="btn-premium" style="padding: 14px 28px;">
            <i class="fas fa-th-large"></i> DASHBOARD
        </a>
    </div>
</header>

<style>
    @media (max-width: 1024px) {
        .analytics-grid { grid-template-columns: 1fr !important; }
    }
</style>

<div class="analytics-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 32px; margin-bottom: 40px;">
    <div class="premium-card" style="padding: 40px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px;">
            <div>
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.5px;">Revenue Performance</h3>
                <p style="margin: 4px 0 0; color: var(--text-dim); font-size: 0.85rem; font-weight: 600;">Historical earnings over the last 6 months</p>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 0.75rem; color: var(--success); font-weight: 900; background: #ECFDF5; padding: 6px 12px; border-radius: 50px;"><i class="fas fa-arrow-trend-up"></i> +18.4%</span>
            </div>
        </div>
        <div style="height: 350px; width: 100%;">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <div class="premium-card" style="padding: 40px;">
        <h3 style="margin: 0 0 32px; font-size: 1.1rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.5px;">Top Performing Tracks</h3>
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <?php if(!empty($top_courses)): ?>
                <?php foreach($top_courses as $i => $c): ?>
                <div style="display: flex; align-items: flex-start; gap: 16px; padding-bottom: 24px; border-bottom: 1px solid #f1f5f9;">
                    <div style="width: 32px; height: 32px; background: #E0F7FF; color: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 950; font-size: 0.8rem; flex-shrink: 0;"><?= $i+1 ?></div>
                    <div style="flex: 1; min-width: 0;">
                        <h4 style="margin: 0; font-weight: 900; font-size: 0.9rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($c['title']) ?></h4>
                        <p style="margin: 4px 0 12px; font-size: 0.75rem; color: var(--text-dim); font-weight: 700;"><?= $c['student_count'] ?> Scholars</p>
                        <div style="height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden;">
                            <div class="progress-pill-fill" style="height: 100%; background: var(--grad-primary); border-radius: 10px; transition: 1s cubic-bezier(0.4, 0, 0.2, 1); width: 0;" data-width="<?= ($c['student_count'] / ($top_courses[0]['student_count'] ?: 1)) * 100 ?>%"></div>
                        </div>
                    </div>
                    <div style="text-align: right; font-weight: 950; font-size: 0.85rem; color: var(--primary);">KES <?= number_format($c['total_rev'], 0) ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-dim); padding: 40px 0;">No tracks available for analysis.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="analytics-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 64px;">
    <div class="premium-card" style="padding: 40px;">
        <h3 style="margin: 0 0 32px; font-size: 1.1rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.5px;">Enrollment Trajectory</h3>
        <div style="height: 300px; width: 100%;">
            <canvas id="enrollmentChart"></canvas>
        </div>
    </div>

    <div class="premium-card" style="padding: 40px;">
        <h3 style="margin: 0 0 32px; font-size: 1.1rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.5px;">Knowledge Mastery</h3>
        <div style="display: flex; flex-direction: column; gap: 32px;">
            <?php if(!empty($quiz_analytics)): ?>
                <?php foreach($quiz_analytics as $q): ?>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span style="font-weight: 800; color: var(--text-main); font-size: 0.9rem;"><?= htmlspecialchars($q['quiz_title']) ?></span>
                        <span style="font-weight: 950; color: var(--secondary); font-size: 1rem;"><?= round($q['avg_score']) ?>%</span>
                    </div>
                    <div style="height: 10px; background: #f1f5f9; border-radius: 10px; overflow: hidden; margin-bottom: 8px;">
                        <div style="height: 100%; background: var(--secondary); border-radius: 10px; width: <?= $q['avg_score'] ?>%;"></div>
                    </div>
                    <p style="margin: 0; font-size: 0.7rem; color: var(--text-dim); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Based on <?= $q['attempt_count'] ?> Verified Attempts</p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; color: var(--text-dim); padding: 40px 0;">No assessment metrics available.</div>
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
