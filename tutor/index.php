<?php
$pageTitle = 'Instructor Command Center';
require_once 'includes/header.php'; // Correct portal include

// Fetch Instructor Specific Intelligence
try {
    // 1. Total Revenue Stream (Tutor's Share - 80%)
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments p JOIN courses c ON p.course_id = c.id WHERE c.tutor_id = ? AND p.status = 'verified'");
    $stmt->execute([$tutor['id']]);
    $total_earnings = ($stmt->fetchColumn() ?: 0) * 0.8;

    // 2. Active Scholar Enrollment
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.tutor_id = ?");
    $stmt->execute([$tutor['id']]);
    $student_count = $stmt->fetchColumn() ?: 0;

    // 3. Curriculum Scope (Total Lessons)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons l JOIN courses c ON l.course_id = c.id WHERE c.tutor_id = ?");
    $stmt->execute([$tutor['id']]);
    $total_modules = $stmt->fetchColumn() ?: 0;

    // 4. Academic Tracks (Courses)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE tutor_id = ?");
    $stmt->execute([$tutor['id']]);
    $course_count = $stmt->fetchColumn() ?: 0;

    // 5. Recent Submissions for Audit
    $stmt = $pdo->prepare("SELECT asub.*, u.name as student_name, a.title as assignment_title 
                           FROM assignment_submissions asub 
                           JOIN assignments a ON asub.assignment_id = a.id 
                           JOIN courses c ON a.course_id = c.id 
                           JOIN users u ON asub.student_id = u.id 
                           WHERE c.tutor_id = ? AND asub.status = 'submitted' 
                           ORDER BY asub.submitted_at DESC LIMIT 5");
    $stmt->execute([$tutor['id']]);
    $recent_submissions = $stmt->fetchAll();

} catch (Exception $e) { 
    $total_earnings = $student_count = $total_modules = $course_count = 0; 
    $recent_submissions = []; 
}
?>

<?php require_once 'includes/sidebar.php'; ?>

<style>
    :root {
        --primary: #00BFFF;
        --secondary: #FF8C00;
        --success: #10B981;
        --bg-main: #f8fafc;
        --text-main: #0f172a;
        --text-dim: #64748b;
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
    }

    body { background: var(--bg-main); font-family: 'Inter', sans-serif; }
    .main-content { padding: 40px; min-height: 100vh; }
    
    .premium-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        transition: 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        position: relative;
        overflow: hidden;
    }
    .premium-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.05); }

    .stat-pod-elite { padding: 32px; display: flex; flex-direction: column; }
    .stat-pod-elite .label { font-size: 0.65rem; font-weight: 900; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px; }
    .stat-pod-elite .value { font-size: 1.8rem; font-weight: 950; color: var(--text-main); letter-spacing: -1px; }
    
    .btn-premium {
        background: var(--primary);
        color: white;
        padding: 12px 24px;
        border-radius: 14px;
        font-weight: 800;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 10px 20px rgba(0, 191, 255, 0.2);
    }
    .btn-premium:hover { transform: translateY(-2px); opacity: 0.9; }

    .ai-assist-box {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white;
        padding: 40px;
        border-radius: 32px;
        position: relative;
        overflow: hidden;
    }
    .ai-assist-box::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(0, 191, 255, 0.15) 0%, transparent 70%);
        pointer-events: none;
    }

    @media (max-width: 1024px) {
        .main-content { padding: 24px; }
        .dashboard-grid-4 { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .command-nexus { grid-template-columns: 1fr; }
    }
</style>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Instructor Command Center<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Instructional oversight and performance monitoring for <span style="color: var(--primary); font-weight: 700;"><?= explode(' ', $tutor['name'])[0] ?></span>.</p>
    </div>
    <div style="display: flex; gap: 16px; align-items: center;">
        <a href="courses.php?action=new" class="btn-premium" style="padding: 16px 32px; border-radius: 18px; box-shadow: 0 15px 30px rgba(0, 191, 255, 0.25);">
            <i class="fas fa-plus-circle"></i> LAUNCH NEW TRACK
        </a>
    </div>
</header>

<div class="dashboard-grid-4" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 32px; margin-bottom: 48px; max-width: 1600px;">
    <div class="premium-card stat-pod-elite">
        <span class="label">Total Revenue (80%)</span>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <strong class="value"><span style="font-size: 0.9rem; color: var(--text-dim);">KES</span> <?= number_format($total_earnings) ?></strong>
        </div>
        <div style="color: var(--success); font-size: 0.75rem; font-weight: 800; margin-top: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-arrow-trend-up"></i> +12% Efficiency Boost
        </div>
    </div>

    <div class="premium-card stat-pod-elite">
        <span class="label">Scholar Population</span>
        <strong class="value"><?= number_format($student_count) ?></strong>
        <div style="color: var(--text-dim); font-size: 0.75rem; font-weight: 800; margin-top: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-users"></i> Global Reach
        </div>
    </div>

    <div class="premium-card stat-pod-elite">
        <span class="label">Active Modules</span>
        <strong class="value"><?= number_format($total_modules) ?></strong>
        <div style="color: var(--secondary); font-size: 0.75rem; font-weight: 800; margin-top: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-book-open"></i> Live Curriculum
        </div>
    </div>

    <div class="premium-card stat-pod-elite">
        <span class="label">Academic Tracks</span>
        <strong class="value"><?= number_format($course_count) ?></strong>
        <div style="color: var(--primary); font-size: 0.75rem; font-weight: 800; margin-top: 16px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-layer-group"></i> Program Depth
        </div>
    </div>
</div>

<div class="command-nexus" style="display: grid; grid-template-columns: 1.8fr 1fr; gap: 40px; align-items: start; max-width: 1600px;">
    <!-- AI ASSISTANT -->
    <div class="ai-assist-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px;">
            <div>
                <h2 style="margin: 0; font-size: 1.6rem; font-weight: 950; letter-spacing: -1px;">Nexus AI Assistant<span style="color: var(--primary);">.</span></h2>
                <p style="margin: 8px 0 0; font-size: 0.9rem; color: #94a3b8; font-weight: 600;">Institutional Curriculum Intelligence</p>
            </div>
            <div style="width: 56px; height: 56px; background: rgba(0, 191, 255, 0.1); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--primary);">
                <i class="fas fa-sparkles"></i>
            </div>
        </div>

        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 24px; padding: 32px;">
            <p style="margin: 0 0 24px; font-size: 1rem; line-height: 1.6; color: #cbd5e1;">I can architect complete lesson structures, generate high-fidelity quiz pools, and provide scholarly summaries for your tracks.</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <a href="lessons.php" class="btn-premium" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); box-shadow: none; text-decoration: none; justify-content: center;">
                    <i class="fas fa-wand-magic-sparkles"></i> ARCHITECT LESSON
                </a>
                <a href="quizzes.php" class="btn-premium" style="background: var(--primary); justify-content: center; text-decoration: none;">
                    <i class="fas fa-brain"></i> POOL GENERATOR
                </a>
            </div>
        </div>
    </div>

    <!-- RECENT AUDITS -->
    <div class="premium-card" style="padding: 0; overflow: hidden;">
        <div style="padding: 32px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 950; color: var(--text-main);">Pending Audits</h3>
            <span style="padding: 4px 12px; background: white; border: 1px solid #e2e8f0; border-radius: 50px; font-size: 0.65rem; font-weight: 950; color: var(--primary);"><?= count($recent_submissions) ?> NEW</span>
        </div>
        <div style="padding: 8px;">
            <?php if(empty($recent_submissions)): ?>
                <div style="padding: 60px 40px; text-align: center; color: var(--text-dim);">
                    <i class="fas fa-check-double" style="font-size: 2.5rem; margin-bottom: 16px; opacity: 0.2;"></i>
                    <p style="font-weight: 700; font-size: 0.9rem;">Curriculum status: All clear.</p>
                </div>
            <?php else: ?>
                <?php foreach($recent_submissions as $s): ?>
                    <a href="assignments.php" style="display: flex; align-items: center; gap: 16px; padding: 20px; border-radius: 18px; transition: 0.2s; text-decoration: none; border: 1px solid transparent;" onmouseover="this.style.background='#F8FAFC'; this.style.borderColor='#E2E8F0'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'">
                        <div style="width: 44px; height: 44px; background: #E0F7FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.1rem;"><i class="fas fa-file-invoice"></i></div>
                        <div style="flex: 1;">
                            <div style="font-weight: 800; color: var(--text-main); font-size: 0.9rem; margin-bottom: 2px;"><?= htmlspecialchars($s['student_name']) ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-dim); font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($s['assignment_title']) ?></div>
                        </div>
                        <i class="fas fa-chevron-right" style="color: #cbd5e1; font-size: 0.8rem;"></i>
                    </a>
                <?php endforeach; ?>
                <div style="padding: 16px;">
                    <a href="assignments.php" style="display: flex; align-items: center; justify-content: center; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; color: var(--text-dim); text-decoration: none; font-size: 0.8rem; font-weight: 800; gap: 8px;">VIEW ALL SUBMISSIONS <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</main>
</body>
</html>
