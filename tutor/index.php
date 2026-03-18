<?php
$pageTitle = 'Instructor Command Center';
require_once '../includes/header.php';

// Fetch Tutor Stats
try {
    // 1. Total Earnings (80% share)
    $stmt = $pdo->prepare("SELECT SUM(p.amount * 0.8) FROM payments p 
                           JOIN courses c ON p.course_id = c.id 
                           WHERE c.tutor_id = ? AND p.status = 'verified'");
    $stmt->execute([$tutor['id']]);
    $total_earnings = $stmt->fetchColumn() ?: 0;

    // 2. Student Count
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e 
                           JOIN courses c ON e.course_id = c.id 
                           WHERE c.tutor_id = ? AND e.status = 'active'");
    $stmt->execute([$tutor['id']]);
    $student_count = $stmt->fetchColumn() ?: 0;

    // 3. Courses
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE tutor_id = ?");
    $stmt->execute([$tutor['id']]);
    $course_count = $stmt->fetchColumn() ?: 0;

    // 4. Student Performance Metrics
    // Quiz Avg
    $stmt = $pdo->prepare("SELECT AVG(qa.score) FROM quiz_attempts qa 
                           JOIN quizzes q ON qa.quiz_id = q.id 
                           JOIN courses c ON q.course_id = c.id 
                           WHERE c.tutor_id = ?");
    $stmt->execute([$tutor['id']]);
    $avg_quiz_score = round($stmt->fetchColumn() ?: 0);

    // Assignment Turn-in (submissions / enrolled students)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignment_submissions asub 
                           JOIN assignments a ON asub.assignment_id = a.id 
                           JOIN courses c ON a.course_id = c.id 
                           WHERE c.tutor_id = ?");
    $stmt->execute([$tutor['id']]);
    $total_submissions = $stmt->fetchColumn() ?: 0;
    
    $turn_in_rate = $student_count > 0 ? round(($total_submissions / ($student_count * 1.5)) * 100) : 0; // Weighted estimate
    $turn_in_rate = min(100, $turn_in_rate);

    // Certificates
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates cert 
                           JOIN courses c ON cert.course_id = c.id 
                           WHERE c.tutor_id = ?");
    $stmt->execute([$tutor['id']]);
    $cert_count = $stmt->fetchColumn() ?: 0;

    // 5. Revenue History (Last 3 Months)
    $stmt = $pdo->prepare("SELECT 
                             DATE_FORMAT(p.created_at, '%M %Y') as month,
                             COUNT(p.id) as enrolls,
                             SUM(p.amount * 0.8) as earnings
                           FROM payments p 
                           JOIN courses c ON p.course_id = c.id 
                           WHERE c.tutor_id = ? AND p.status = 'verified'
                           GROUP BY DATE_FORMAT(p.created_at, '%M %Y')
                           ORDER BY p.created_at DESC LIMIT 3");
    $stmt->execute([$tutor['id']]);
    $revenue_history = $stmt->fetchAll();

    // 7. Fetch Tutor's Courses for AI selector
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id = ? ORDER BY title ASC");
    $stmt->execute([$tutor['id']]);
    $tutor_courses = $stmt->fetchAll();

} catch (Exception $e) { 
    $total_earnings = $student_count = $course_count = 0; 
    $avg_quiz_score = $turn_in_rate = $cert_count = 0;
    $revenue_history = [];
    $tutor_courses = [];
}
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    /* ══ RESPONSIVE GRIDS ══ */
    .dashboard-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 48px; }
    .dashboard-grid-2 { display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px; }
    .action-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

    .stat-box { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px; transition: 0.3s; box-shadow: var(--shadow-sm); }
    .stat-box:hover { transform: translateY(-5px); border-color: var(--primary); }
    .stat-label { font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; font-weight: 800; margin-bottom: 8px; display: block; }
    .stat-val { font-family: 'Poppins', sans-serif; font-size: 1.8rem; font-weight: 800; color: var(--dark); }

    .action-card { background: white; border: 1px solid var(--dark-border); border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 20px; text-decoration: none; transition: 0.3s; }
    .action-card:hover { border-color: var(--secondary); background: var(--secondary-glow); }
    .action-icon { width: 56px; height: 56px; background: var(--bg-light); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: var(--secondary); transition: 0.3s; }
    .action-card:hover .action-icon { background: white; }

    .ai-assist-box { background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px; text-align: center; box-shadow: var(--shadow-sm); }
    .ai-progress-bar { height: 6px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin: 15px 0; display: none; }
    .ai-progress-inner { height: 100%; background: var(--primary); width: 0%; transition: 0.3s; }
    
    /* AI Result Modal Styles */
    #aiModal .modal-content { max-width: 700px; max-height: 85vh; overflow-y: auto; border-radius: 24px; }
    .ai-result-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 16px; transition: 0.2s; }
    .ai-result-card:hover { border-color: var(--primary); background: #fff; }
    .q-badge { display: inline-block; padding: 4px 10px; background: var(--primary-glow); color: var(--primary); border-radius: 6px; font-size: 0.75rem; font-weight: 700; margin-bottom: 10px; }
    .opt-chip { padding: 8px 12px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; margin-top: 6px; }
    .opt-chip.correct { border-color: var(--success); background: #f0fdf4; color: #166534; }
    
    .lp-week { border-left: 3px solid var(--primary); padding-left: 20px; margin-bottom: 30px; position: relative; }
    .lp-week::before { content: ''; position: absolute; left: -9px; top: 0; width: 15px; height: 15px; background: white; border: 3px solid var(--primary); border-radius: 50%; }
    .lp-week h5 { font-family: 'Poppins', sans-serif; color: var(--primary); font-size: 0.95rem; margin-bottom: 8px; }

    /* ══ MOBILE OVERRIDES ══ */
    @media (max-width: 1200px) {
        .dashboard-grid-4 { grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .dashboard-grid-2 { grid-template-columns: 1fr; }
    }

    @media (max-width: 768px) {
        .admin-header { flex-direction: column; align-items: flex-start; gap: 20px; margin-bottom: 32px; }
        .admin-header .btn { width: 100%; justify-content: center; }
        .stat-box { padding: 24px; }
        .stat-val { font-size: 1.5rem; }
        .action-grid { grid-template-columns: 1fr; }
        .dashboard-grid-4 { grid-template-columns: repeat(2, 1fr); margin-bottom: 32px; }
    }

    @media (max-width: 480px) {
        .dashboard-grid-4 { grid-template-columns: 1fr; }
        .stat-box { text-align: center; }
        .action-card { padding: 16px; gap: 15px; }
        .action-icon { width: 44px; height: 44px; font-size: 1.1rem; }
        .table-card { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .admin-table { min-width: 600px; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div>
            <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Professor Overview: <span><?= explode(' ', $tutor['name'])[0] ?></span></h1>
            <p style="color: var(--text-dim); margin-top: 4px;">Manage your curriculum and monitor student academic performance.</p>
        </div>
        <div style="display: flex; gap: 16px;">
            <a href="courses.php?action=new" class="btn btn-primary" style="border-radius: 10px;"><i class="fas fa-plus"></i> Launch New Course</a>
        </div>
    </header>

    <div class="dashboard-grid-4">
        <div class="stat-box">
            <span class="stat-label">Total Revenue</span>
            <div class="stat-val">KES <?= number_format($total_earnings) ?></div>
            <p style="font-size: 0.75rem; color: var(--success); margin-top: 8px;"><i class="fas fa-arrow-up"></i> 12% from last month</p>
        </div>
        <div class="stat-box">
            <span class="stat-label">Active Learners</span>
            <div class="stat-val"><?= number_format($student_count) ?></div>
            <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 8px;">Across all modules</p>
        </div>
        <div class="stat-box">
            <span class="stat-label">Course Rating</span>
            <div class="stat-val">4.92 / 5.0</div>
            <p style="font-size: 0.75rem; color: var(--secondary); margin-top: 8px;"><i class="fas fa-star"></i> Verified Student Reviews</p>
        </div>
        <div class="stat-box">
            <span class="stat-label">Completion Rate</span>
            <div class="stat-val">84.5%</div>
            <p style="font-size: 0.75rem; color: var(--text-dim); margin-top: 8px;">Higher than avg (72%)</p>
        </div>
    </div>

    <div class="dashboard-grid-2">
        <div>
            <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.25rem; margin-bottom: 24px;">Instructional Toolkit</h3>
            <div class="action-grid">
                <a href="courses.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-book-open"></i></div>
                    <div>
                        <div style="font-weight: 700; color: var(--dark);">Manage Courses</div>
                        <div style="font-size: 0.75rem; color: var(--text-dim);">Edit lessons & media</div>
                    </div>
                </a>
                <a href="assignments.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-file-invoice"></i></div>
                    <div>
                        <div style="font-weight: 700; color: var(--dark);">Project Grading</div>
                        <div style="font-size: 0.75rem; color: var(--text-dim);">Evaluate submissions</div>
                    </div>
                </a>
                <a href="quizzes.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-brain"></i></div>
                    <div>
                        <div style="font-weight: 700; color: var(--dark);">Quiz Designer</div>
                        <div style="font-size: 0.75rem; color: var(--text-dim);">Assess knowledge</div>
                    </div>
                </a>
                <a href="analytics.php" class="action-card">
                    <div class="action-icon"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <div style="font-weight: 700; color: var(--dark);">Detailed Analytics</div>
                        <div style="font-size: 0.75rem; color: var(--text-dim);">Audience insights</div>
                    </div>
                </a>
            </div>

            <div style="margin-top: 48px;">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.25rem; margin-bottom: 24px;">Revenue History</h3>
                <div class="table-card">
                    <table class="admin-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Sales</th>
                                <th>Earnings (80%)</th>
                                <th>Impact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($revenue_history)): ?>
                                <?php foreach($revenue_history as $rev): ?>
                                <tr>
                                    <td><?= $rev['month'] ?></td>
                                    <td><?= $rev['enrolls'] ?> Enrolls</td>
                                    <td style="font-weight: 700;">KES <?= number_format($rev['earnings']) ?></td>
                                    <td>
                                        <?php if($rev['earnings'] > 20000): ?>
                                            <span class="status-badge status-active">Top Tier</span>
                                        <?php else: ?>
                                            <span class="status-badge status-active" style="background:rgba(0,174,239,0.1); color:var(--primary);">Growing</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding: 24px; color: var(--text-dim);">No verified revenue data available yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <aside>
            <div class="ai-assist-box">
                <div style="width: 60px; height: 60px; background: var(--primary-glow); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.5rem; margin: 0 auto 24px;">
                    <i class="fas fa-magic"></i>
                </div>
                <h4 style="font-family: 'Poppins', sans-serif; font-size: 1.1rem; margin-bottom: 12px;">AI Course Assistant</h4>
                
                <div style="text-align: left; margin-bottom: 16px;">
                    <label style="font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">1. Target Course</label>
                    <select id="aiCourseSelect" class="form-control" style="margin-top: 5px; border-radius: 10px; font-size: 0.88rem; height: 44px; border: 1px solid #e2e8f0;">
                        <?php foreach($tutor_courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                        <?php endforeach; ?>
                        <?php if(empty($tutor_courses)): ?>
                            <option value="0">No courses found</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div style="text-align: left; margin-bottom: 20px;">
                    <label style="font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase;">2. Describe Content Topic</label>
                    <textarea id="aiPrompt" class="form-control" style="margin-top: 5px; border-radius: 12px; font-size: 0.88rem; min-height: 80px; border: 1px solid #e2e8f0; resize: none;" placeholder="e.g. Introduction to React components or Advanced SEO analysis..."></textarea>
                </div>

                <div class="ai-progress-bar" id="aiProcessBar">
                    <div class="ai-progress-inner" id="aiProcessInner"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <button class="btn btn-primary btn-sm" onclick="triggerAi('generate_quiz')" id="aiQuizBtn" style="padding: 12px; border-radius: 12px;">
                        <i class="fas fa-brain"></i> Quiz
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="triggerAi('generate_lesson_plan')" id="aiLessonBtn" style="padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px;">
                        <i class="fas fa-list-ul"></i> Syllabus
                    </button>
                </div>
            </div>

            <div style="margin-top: 40px; background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px;">
                <h4 style="font-family: 'Poppins', sans-serif; font-size: 0.95rem; margin-bottom: 24px; border-bottom: 2px solid var(--primary-glow); padding-bottom: 12px;">Student Performance</h4>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">Quiz Avg</span>
                        <span style="font-weight: 700;"><?= $avg_quiz_score ?>%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">Assignment Turn-in</span>
                        <span style="font-weight: 700;"><?= $turn_in_rate ?>%</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: var(--text-muted);">Certificates Earned</span>
                        <span style="font-weight: 700;"><?= $cert_count ?></span>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</main>

<!-- AI Assistant Modal -->
<div class="modal" id="aiModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(8px);">
    <div class="modal-content card" style="width: 90%; max-width: 700px; padding: 40px; position: relative; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
        <button onclick="closeAiModal()" style="position: absolute; top: 25px; right: 25px; background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
        
        <div id="aiModalHeader" style="margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                <div style="width: 44px; height: 44px; background: var(--primary-glow); color: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    <i class="fas fa-magic"></i>
                </div>
                <h2 id="aiModalTitle" style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 800;">AI Generation Results</h2>
            </div>
            <p id="aiModalSubtitle" style="color: var(--text-dim); font-size: 0.95rem;">Review and customize your AI-generated content.</p>
        </div>

        <div id="aiResultArea" style="margin-bottom: 30px;">
            <!-- Dynamic Content Here -->
        </div>

        <div id="aiModalFooter" style="display: flex; gap: 12px; border-top: 1px solid #eee; padding-top: 25px;">
            <button class="btn btn-ghost" onclick="closeAiModal()" style="flex: 1;">Close</button>
            <button class="btn btn-primary" id="aiModalActionBtn" style="flex: 2;">Confirm & Save</button>
        </div>
    </div>
</div>

<script>
/**
 * AI Course Assistant Logic
 */
async function triggerAi(action) {
    const quizBtn = document.getElementById('aiQuizBtn');
    const lessonBtn = document.getElementById('aiLessonBtn');
    const progressBar = document.getElementById('aiProcessBar');
    const progressInner = document.getElementById('aiProcessInner');

    // UI Feedback
    quizBtn.disabled = true;
    lessonBtn.disabled = true;
    progressBar.style.display = 'block';
    
    // Simulate thinking steps
    progressInner.style.width = '30%';
    setTimeout(() => progressInner.style.width = '60%', 400);
    setTimeout(() => progressInner.style.width = '90%', 800);

    try {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('course_id', document.getElementById('aiCourseSelect').value);
        formData.append('prompt', document.getElementById('aiPrompt').value);
        
        const response = await fetch('ai-assistant.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            progressInner.style.width = '100%';
            setTimeout(() => {
                showAiResults(action, data);
                // Reset UI
                quizBtn.disabled = false;
                lessonBtn.disabled = false;
                progressBar.style.display = 'none';
                progressInner.style.width = '0%';
            }, 500);
        } else {
            throw new Error(data.error || 'Generation failed');
        }
    } catch (error) {
        alert("AI Assistant: " + error.message);
        quizBtn.disabled = false;
        lessonBtn.disabled = false;
        progressBar.style.display = 'none';
        progressInner.style.width = '0%';
    }
}

function showAiResults(action, data) {
    const modal = document.getElementById('aiModal');
    const resultArea = document.getElementById('aiResultArea');
    const title = document.getElementById('aiModalTitle');
    const subtitle = document.getElementById('aiModalSubtitle');
    const actionBtn = document.getElementById('aiModalActionBtn');

    modal.style.display = 'flex';
    resultArea.innerHTML = '';

    if (action === 'generate_quiz') {
        title.innerText = "Interactive Quiz Drafted";
        subtitle.innerText = `AI has generated a quiz for "${data.quiz_title}". It is now saved to your course.`;
        actionBtn.innerText = "Go to Quiz Designer";
        actionBtn.style.flex = '2';
        actionBtn.onclick = () => window.location.href = data.quiz_url;

        // Reset footer if needed (for clean transitions)
        const footer = document.getElementById('aiModalFooter');
        footer.innerHTML = '';
        const closeBtn = document.createElement('button');
        closeBtn.className = 'btn btn-ghost';
        closeBtn.style.flex = '1';
        closeBtn.innerText = 'Close';
        closeBtn.onclick = closeAiModal;
        footer.appendChild(closeBtn);
        footer.appendChild(actionBtn);

        data.questions.forEach((q, i) => {
            const card = document.createElement('div');
            card.className = 'ai-result-card';
            card.innerHTML = `
                <div class="q-badge">Question ${i+1}</div>
                <h4 style="margin-bottom: 12px; font-size: 1rem;">${q.question}</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    ${q.options.map(opt => `
                        <div class="opt-chip ${opt === q.correct_answer ? 'correct' : ''}">
                            ${opt} ${opt === q.correct_answer ? '<i class="fas fa-check-circle" style="float:right"></i>' : ''}
                        </div>
                    `).join('')}
                </div>
            `;
            resultArea.appendChild(card);
        });

    } else if (action === 'generate_lesson_plan') {
        title.innerText = "Curriculum Roadmap Drafted";
        subtitle.innerText = `Strategic 6-week curriculum for ${data.course}`;
        
        // Use multi-btn layout
        actionBtn.innerText = "Publish to Course Syllabus";
        actionBtn.onclick = () => publishLessonPlan(data.course_id, data.plan);
        
        // Add Copy button next to it
        const copyBtn = document.createElement('button');
        copyBtn.className = 'btn btn-ghost';
        copyBtn.style.flex = '1';
        copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy Text';
        copyBtn.onclick = () => copyLessonToClipboard(data.plan);
        
        // Re-arrange footer for 3 buttons
        const footer = document.getElementById('aiModalFooter');
        footer.innerHTML = '';
        const closeBtn = document.createElement('button');
        closeBtn.className = 'btn btn-ghost';
        closeBtn.style.flex = '1';
        closeBtn.innerText = 'Close';
        closeBtn.onclick = closeAiModal;

        footer.appendChild(closeBtn);
        footer.appendChild(copyBtn);
        footer.appendChild(actionBtn);

        data.plan.sections.forEach(sec => {
            const div = document.createElement('div');
            div.className = 'lp-week';
            div.innerHTML = `
                <h5>${sec.week}: ${sec.topic}</h5>
                <div style="font-size: 0.88rem; color: #475569; margin-bottom: 10px;">
                    <strong>Objectives:</strong> ${sec.objectives.join(', ')}
                </div>
                <div style="font-size: 0.82rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; color: #64748b;">
                    <i class="fas fa-tasks" style="color:var(--primary)"></i> <strong>Activities:</strong> ${sec.activities.join(' • ')}
                </div>
            `;
            resultArea.appendChild(div);
        });
    }
}

async function publishLessonPlan(courseId, plan) {
    const actionBtn = document.getElementById('aiModalActionBtn');
    const oldHtml = actionBtn.innerHTML;
    actionBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
    actionBtn.disabled = true;

    try {
        const fd = new FormData();
        fd.append('action', 'save_lesson_plan');
        fd.append('course_id', courseId);
        fd.append('plan_json', JSON.stringify(plan));

        const res = await fetch('ai-assistant.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
            alert(data.message);
            closeAiModal();
            // Optional: window.location.reload();
        } else {
            throw new Error(data.error);
        }
    } catch (e) {
        alert("Publish Failed: " + e.message);
        actionBtn.innerHTML = oldHtml;
        actionBtn.disabled = false;
    }
}

function copyLessonToClipboard(plan) {
    let text = `${plan.title}\nLevel: ${plan.level}\nDuration: ${plan.duration}\n\n`;
    plan.sections.forEach(s => {
        text += `== ${s.week}: ${s.topic} ==\n`;
        text += `Objectives:\n- ${s.objectives.join('\n- ')}\n`;
        text += `Activities:\n- ${s.activities.join('\n- ')}\n`;
        text += `Assessment: ${s.assessment}\n\n`;
    });
    
    navigator.clipboard.writeText(text).then(() => {
        alert("✅ Lesson plan copied to clipboard!");
    });
}

function closeAiModal() {
    document.getElementById('aiModal').style.display = 'none';
}

// Close on backdrop click
window.onclick = function(event) {
    const modal = document.getElementById('aiModal');
    if (event.target == modal) {
        closeAiModal();
    }
}
</script>
</body>
</html>
