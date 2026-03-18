<?php
$pageTitle = 'Student Command Center';
require_once '../includes/header.php';

// Fetch Professional Student Stats
try {
    // Refresh student context from DB to get the latest referral_code, merit_coins, etc.
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();

    if (!$student) { header('Location: ../login.php'); exit; }

    // 1. Enrolled Courses
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.thumbnail, u.name as tutor_name 
                           FROM enrollments e 
                           JOIN courses c ON e.course_id = c.id 
                           JOIN users u ON c.tutor_id = u.id
                           WHERE e.student_id = ? AND e.status != 'cancelled' 
                           ORDER BY e.enrolled_at DESC");
    $stmt->execute([$student['id']]);
    $my_courses = $stmt->fetchAll();

    // 2. Academic Metrics
    $total_enrolled = count($my_courses);
    $completed_stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'completed'");
    $completed_stmt->execute([$student['id']]);
    $total_completed = $completed_stmt->fetchColumn();

} catch (Exception $e) { $my_courses = []; $total_enrolled = $total_completed = 0; }
?>

<?php require_once '../includes/sidebar.php'; ?>

<style>
    .dash-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; }
    .learning-card { background: white; border: 1px solid var(--dark-border); border-radius: 16px; padding: 20px; transition: 0.3s; margin-bottom: 24px; }
    .learning-card:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: var(--shadow-lg); }
    
    .lc-inner { display: flex; gap: 20px; align-items: flex-start; }
    .lc-thumb { width: 80px; height: 80px; border-radius: 12px; overflow: hidden; flex-shrink: 0; background: var(--bg-light); border: 1px solid var(--dark-border); }
    .lc-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .lc-content { flex: 1; min-width: 0; }
    .lc-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; gap: 10px; }
    .lc-title { font-family: 'Poppins', sans-serif; font-size: 1rem; color: var(--dark); font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .lc-progress-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .lc-progress-bar { flex: 1; height: 6px; background: var(--bg-light); border-radius: 3px; overflow: hidden; }
    .lc-progress-bar div { height: 100%; background: var(--primary); border-radius: 3px; transition: 1s; }
    .lc-pct { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); min-width: 35px; }
    .lc-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .lc-tutor { font-size: 0.75rem; color: var(--text-dim); margin-bottom: 0; }
    
    .status-badge { font-size: 0.6rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
    .status-active { background: var(--primary-glow); color: var(--primary); }
    
    .ai-mentor-box { background: linear-gradient(135deg, white, var(--bg-light)); border: 1px dashed var(--primary); border-radius: 20px; padding: 32px; margin-top: 32px; position: relative; overflow: hidden; }
    .ai-mentor-box::before { content: '\f0e0'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -20px; bottom: -20px; font-size: 6rem; color: var(--primary-glow); transform: rotate(-15deg); pointer-events: none; }

    @media (max-width: 1024px) {
        .dash-grid { grid-template-columns: 1fr; }
        .lc-inner { flex-direction: column; }
        .lc-thumb { width: 100%; height: 140px; }
        .lc-footer { flex-direction: column; align-items: flex-start; }
    }
</style>

<main class="main-content">
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <button class="nav-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div>
                <h1 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem;">Ready for Excellence, <span><?= explode(' ', $student['name'])[0] ?>?</span></h1>
                <p style="color: var(--text-dim); margin-top: 4px;">Track your academic growth and certification progress.</p>
            </div>
        </div>
        <div style="display: flex; gap: 16px;">
            <div style="text-align: right; background: white; padding: 12px 24px; border-radius: 12px; border: 1px solid var(--dark-border);">
                <div style="font-size: 0.72rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; font-weight: 800;">Academic Points</div>
                <div style="font-size: 1.4rem; font-weight: 900; color: var(--secondary);"><i class="fas fa-crown"></i> <?= number_format($student['points'] ?? 0) ?></div>
            </div>
            <div style="text-align: right; background: white; padding: 12px 24px; border-radius: 12px; border: 1px solid var(--primary); box-shadow: 0 4px 12px rgba(0, 174, 239, 0.1);">
                <div style="font-size: 0.72rem; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; font-weight: 800;">Merit Coins</div>
                <div style="font-size: 1.4rem; font-weight: 900; color: var(--primary);"><i class="fas fa-coins"></i> <?= number_format($student['merit_coins'] ?? 0, 2) ?></div>
            </div>
        </div>
    </header>
    
    <!-- Student Utility Hub (Inspired by Maseno MSU pattern) -->
    <div style="display: flex; gap: 32px; flex-wrap: wrap; margin-bottom: 48px; border-bottom: 1px solid var(--dark-border); padding-bottom: 40px; justify-content: space-around;">
        <a href="announcements.php" style="text-decoration: none; text-align: center; width: 100px;">
            <div style="width: 72px; height: 72px; background: rgba(0, 191, 255, 0.1); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 12px; transition: 0.3s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                <i class="fas fa-bullhorn"></i>
            </div>
            <span style="font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">News Forum</span>
        </a>
        <a href="support.php" style="text-decoration: none; text-align: center; width: 100px;">
            <div style="width: 72px; height: 72px; background: rgba(16, 185, 129, 0.1); color: #10B981; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 12px; transition: 0.3s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                <i class="fas fa-headset"></i>
            </div>
            <span style="font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Admin Help</span>
        </a>
        <a href="support.php?action=complain" style="text-decoration: none; text-align: center; width: 100px;">
            <div style="width: 72px; height: 72px; background: rgba(239, 68, 68, 0.1); color: #EF4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 12px; transition: 0.3s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <span style="font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Grievances</span>
        </a>
        <a href="community.php" style="text-decoration: none; text-align: center; width: 100px;">
            <div style="width: 72px; height: 72px; background: rgba(139, 92, 246, 0.1); color: #8B5CF6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 12px; transition: 0.3s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                <i class="fas fa-users"></i>
            </div>
            <span style="font-size: 0.72rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Community</span>
        </a>
    </div>

    <!-- Referral & Growth Section -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); color: white; border-radius: 24px; padding: 32px; margin-bottom: 40px; display: flex; align-items: center; justify-content: space-between; gap: 40px; flex-wrap: wrap; position: relative; overflow: hidden;">
        <div style="position: absolute; right: -20px; top: -20px; width: 180px; height: 180px; background: radial-gradient(circle, rgba(0, 191, 255, 0.15), transparent 70%); border-radius: 50%;"></div>
        <div style="flex: 1; min-width: 300px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <span style="background: #00BFFF; color: white; padding: 4px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Growth Program</span>
                <span style="font-weight: 700; color: #00BFFF;">Earn 4% Recursive Rewards</span>
            </div>
            <h2 style="font-family: 'Poppins', sans-serif; font-size: 1.6rem; font-weight: 800; margin-bottom: 12px; color: #ffffff;">Share Knowledge, Accumulate Wealth</h2>
            <p style="opacity: 0.9; font-size: 0.9rem; line-height: 1.6; max-width: 550px; color: rgba(255,255,255,0.9);">Refer a colleague and earn <strong>4% of their course price</strong> in Merit Coins upon enrollment. Coins can be used as digital credit for your future certification paths.</p>
        </div>
        <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 24px; text-align: center; min-width: 280px;">
            <div style="font-size: 0.72rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 12px;">Your Personal Referral Link</div>
            <div style="background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); padding: 12px; border-radius: 12px; font-family: monospace; font-size: 0.82rem; margin-bottom: 16px; word-break: break-all;" id="refLink">
                http://localhost/Skope Digital Academy/register.php?ref=<?= $student['referral_code'] ?>
            </div>
            <button class="btn btn-primary btn-sm btn-block" onclick="copyRefLink()" style="font-weight: 800; letter-spacing: 0.5px;">
                <i class="fas fa-copy"></i> Copy Link
            </button>
        </div>
    </div>

    <div class="dash-grid">
        <!-- Left: Course Progress -->
        <div class="dash-main-col">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.25rem;">Ongoing Learning Path</h3>
                <a href="../courses.php" class="btn btn-ghost btn-sm">Explore More Path</a>
            </div>

            <?php foreach($my_courses as $c): ?>
            <div class="learning-card">
                <div class="lc-inner">
                    <div class="lc-thumb">
                        <img src="../uploads/courses/<?= $c['thumbnail'] ?: 'course_demo.jpg' ?>" alt="">
                    </div>
                    <div class="lc-content">
                        <div class="lc-header">
                            <h4 class="lc-title"><?= htmlspecialchars($c['title']) ?></h4>
                            <?php if($c['status'] === 'pending'): ?>
                                <span class="badge badge-warning">Waitlist Audit</span>
                            <?php else: ?>
                                <span class="status-badge status-active">In Progress</span>
                            <?php endif; ?>
                        </div>
                        <div class="lc-progress-wrap">
                            <div class="lc-progress-bar">
                                <div style="width: <?= round($c['progress_percent']) ?>%;"></div>
                            </div>
                            <span class="lc-pct"><?= round($c['progress_percent']) ?>%</span>
                        </div>
                        <div class="lc-footer">
                            <p class="lc-tutor"><i class="fas fa-chalkboard-teacher"></i> <?= htmlspecialchars($c['tutor_name']) ?></p>
                            <?php if($c['status'] === 'pending'): ?>
                                <button class="btn btn-ghost btn-sm" disabled style="opacity: 0.5;">Awaiting Review</button>
                            <?php else: ?>
                                <a href="classroom.php?id=<?= $c['course_id'] ?>" class="btn btn-primary btn-sm">Resume Session</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if(empty($my_courses)): ?>
                <div style="padding: 80px; text-align: center; background: white; border: 2px dashed var(--dark-border); border-radius: 24px;">
                    <i class="fas fa-book-reader" style="font-size: 3rem; color: var(--dark-border); margin-bottom: 24px;"></i>
                    <h3 style="color: var(--text-muted);">Your Learning Path is Awaiting</h3>
                    <p style="color: var(--text-dim); margin-bottom: 32px;">Start your professional journey today with a verified certification.</p>
                    <a href="../courses.php" class="btn btn-primary">Enroll in First Course</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: AI Mentorship & Highlights -->
        <aside>
            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px; box-shadow: var(--shadow-sm);">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1rem; margin-bottom: 20px; border-bottom: 2px solid var(--primary-glow); padding-bottom: 12px;">
                    <i class="fas fa-calendar-check text-primary"></i> Academic Schedule
                </h3>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="display: flex; gap: 16px;">
                        <div style="width: 44px; height: 44px; background: var(--bg-light); border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px solid var(--dark-border);">
                            <span style="font-size: 0.6rem; text-transform: uppercase; color: var(--text-dim);">Mar</span>
                            <span style="font-size: 1rem; font-weight: 800; color: var(--primary);">20</span>
                        </div>
                        <div>
                            <div style="font-size: 0.88rem; font-weight: 700;">Logic & Quiz Deadline</div>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Advanced UI/UX Path</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 16px;">
                        <div style="width: 44px; height: 44px; background: var(--bg-light); border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px solid var(--dark-border);">
                            <span style="font-size: 0.6rem; text-transform: uppercase; color: var(--text-dim);">Mar</span>
                            <span style="font-size: 1rem; font-weight: 800; color: var(--secondary);">22</span>
                        </div>
                        <div>
                            <div style="font-size: 0.88rem; font-weight: 700;">Creative Vision Q&A</div>
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Lead Tutor live session</p>
                        </div>
                    </div>
                </div>
            </div>

            <div style="background: white; border: 1px solid var(--dark-border); border-radius: 20px; padding: 32px; box-shadow: var(--shadow-sm); margin-bottom: 24px;">
                <h3 style="font-family: 'Poppins', sans-serif; font-size: 1rem; margin-bottom: 20px; border-bottom: 2px solid var(--primary-glow); padding-bottom: 12px;">
                    <i class="fas fa-history text-primary"></i> Learning Timeline
                </h3>
                <!-- Timeline items (Inspired by Moodle Timeline) -->
                <div style="display: flex; flex-direction: column; gap: 24px; position: relative; padding-left: 20px; border-left: 2px solid var(--bg-light);">
                    <div style="position: relative;">
                        <div style="position: absolute; left: -29px; top: 0; width: 16px; height: 16px; background: var(--primary); border: 4px solid white; border-radius: 50%; box-shadow: 0 0 0 2px var(--primary-glow);"></div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Today, 4:00 PM</div>
                        <div style="font-size: 0.9rem; font-weight: 700; margin-top: 4px;">Live Q&A Session</div>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Advanced UI/UX Trends</p>
                    </div>
                    <div style="position: relative;">
                        <div style="position: absolute; left: -29px; top: 0; width: 16px; height: 16px; background: #94a3b8; border: 4px solid white; border-radius: 50%;"></div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Mar 22</div>
                        <div style="font-size: 0.9rem; font-weight: 700; margin-top: 4px;">Quiz Deadline</div>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Chapter 4 Assessment</p>
                    </div>
                    <div style="position: relative;">
                        <div style="position: absolute; left: -29px; top: 0; width: 16px; height: 16px; background: #94a3b8; border: 4px solid white; border-radius: 50%;"></div>
                        <div style="font-size: 0.75rem; color: var(--text-dim); text-transform: uppercase; font-weight: 800;">Mar 25</div>
                        <div style="font-size: 0.9rem; font-weight: 700; margin-top: 4px;">Project Submission</div>
                        <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Identity Design Phase 1</p>
                    </div>
                </div>
            </div>

            <div class="ai-mentor-box">
                <h4 style="font-family: 'Poppins', sans-serif; font-size: 0.95rem; margin-bottom: 12px;"><i class="fas fa-robot"></i> AI Academic Mentor</h4>
                <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.6; position: relative; z-index: 1;">"I noticed you're progressing quickly in <strong>Design Theory</strong>. Consider starting the <strong>Interactive Prototyping</strong> module next to maximize your retention."</p>
                <a href="mentor.php" class="btn btn-ghost btn-sm btn-block" style="margin-top: 24px; position: relative; z-index: 1; text-decoration: none; text-align: center; display: block;">Talk to Mentor</a>
            </div>
        </aside>
    </div>
</main>

<script src="../assets/js/main.js"></script>
<script>
    function copyRefLink() {
        const linkText = document.getElementById('refLink').innerText;
        navigator.clipboard.writeText(linkText).then(() => {
            SDA.showToast('Referral link copied to clipboard!', 'success');
        });
    }
</script>
</body>
</html>
