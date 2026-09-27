<?php
$pageTitle = 'Student Command Center';
require_once 'includes/header.php'; 

// Fetch Learning Path Data
try {
    // Silent Init: Ensure calendar events architecture exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        event_date DATE NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'activity',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Process Add Event Form
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
        $ev_date = $_POST['event_date'];
        $ev_title = trim($_POST['title']);
        $ev_cat = $_POST['category'];

        if(!empty($ev_date) && !empty($ev_title)){
            $ins = $pdo->prepare("INSERT INTO calendar_events (student_id, event_date, title, category) VALUES (?, ?, ?, ?)");
            $ins->execute([$student['id'], $ev_date, $ev_title, $ev_cat]);
            
            // Generate a Reminder Notification natively
            try {
                $nIns = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'reminder')");
                $nIns->execute([$student['id'], "Reminder Scheduled: $ev_title on $ev_date"]);
            } catch(Exception $ex){}
        }
        
        // Prevent re-submission
        header("Location: index.php");
        exit;
    }

    // 1. My Courses (Active Learning Paths)
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.thumbnail, c.level, u.name as tutor_name 
                           FROM enrollments e 
                           JOIN courses c ON e.course_id = c.id 
                           JOIN users u ON c.tutor_id = u.id 
                           WHERE e.student_id = ? AND e.status = 'active'
                           ORDER BY e.enrolled_at DESC");
    $stmt->execute([$student['id']]);
    $my_courses = $stmt->fetchAll();

    // 2. Dynamic Tasks (Quizzes)
    $stmt = $pdo->prepare("SELECT q.*, c.title as course_name 
                           FROM quizzes q 
                           JOIN enrollments e ON q.course_id = e.course_id 
                           JOIN courses c ON q.course_id = c.id 
                           WHERE e.student_id = ? AND e.status = 'active'
                           LIMIT 5");
    $stmt->execute([$student['id']]);
    $dynamic_tasks = $stmt->fetchAll();

    // 3. New Assignments
    $stmt = $pdo->prepare("SELECT a.*, c.title as course_name 
                           FROM assignments a 
                           JOIN enrollments e ON a.course_id = e.course_id 
                           JOIN courses c ON a.course_id = c.id 
                           WHERE e.student_id = ? AND e.status = 'active'
                           ORDER BY a.due_date ASC LIMIT 5");
    $stmt->execute([$student['id']]);
    $new_assignments = $stmt->fetchAll();

    // 4. Notifications (Live Pulse)
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$student['id']]);
    $live_notifications = $stmt->fetchAll();

    // 5. CAT Quizzes (Diagnostic)
    $cat_quizzes = array_filter($dynamic_tasks, fn($t) => stripos($t['title'], 'CAT') !== false);

    // Dynamic Referral URL Base
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $baseUrl = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace('index.php', '', $_SERVER['PHP_SELF']);
    $referralLink = str_replace('student/', '', $baseUrl) . "register.php?ref=" . $student['referral_code'];

} catch (Exception $e) { 
    error_log($e->getMessage());
    $my_courses = $dynamic_tasks = $new_assignments = $live_notifications = $cat_quizzes = [];
}

require_once 'includes/layout-top.php';
?>

<!-- Section: Welcome Hero -->
<div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 48px; border-radius: 32px; margin-bottom: 48px; position: relative; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 20px 40px rgba(15, 23, 42, 0.1);">
    <div style="position: relative; z-index: 1;">
        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
            <span style="background: rgba(0, 174, 239, 0.2); color: var(--primary); padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Scholar Level: Gold</span>
            <span style="opacity: 0.5; color: white;">•</span>
            <span style="opacity: 0.8; font-size: 0.8rem; font-weight: 600; color: white;"><?= date('l, M jS') ?></span>
        </div>
        <h1 style="font-family: 'Poppins', sans-serif; font-size: clamp(2rem, 5vw, 2.8rem); font-weight: 950; margin: 0 0 12px; letter-spacing: -1.5px; color: white !important;">Welcome Back, <span style="color: var(--primary);"><?= explode(' ', $student['name'])[0] ?></span>.</h1>
        <p style="opacity: 0.7; font-size: 1.1rem; max-width: 600px; line-height: 1.6; font-weight: 500; color: white;">Your learning journey is at <span style="font-weight: 800; color: white; opacity: 1;"><?= count($my_courses) ?> active paths</span>. Ready to master your next module today?</p>
        
        <div style="margin-top: 40px; display: flex; gap: 24px; flex-wrap: wrap;">
            <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 12px 20px; border-radius: 16px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; background: rgba(0, 174, 239, 0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--primary);"><i class="fas fa-coins"></i></div>
                <div>
                    <div style="font-size: 0.6rem; opacity: 0.5; text-transform: uppercase; letter-spacing: 1px; font-weight: 800;">Merit Coins</div>
                    <div style="font-size: 1.1rem; font-weight: 900; color: white;"><?= number_format($student['merit_coins'] ?? 0) ?></div>
                </div>
            </div>
            <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); padding: 12px 20px; border-radius: 16px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; background: rgba(16, 185, 129, 0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #10b981;"><i class="fas fa-trophy"></i></div>
                <div>
                    <div style="font-size: 0.6rem; opacity: 0.5; text-transform: uppercase; letter-spacing: 1px; font-weight: 800;">Completed</div>
                    <div style="font-size: 1.1rem; font-weight: 900; color: white;"><?= count(array_filter($my_courses, fn($c) => ($c['progress_percent'] ?? 0) >= 100)) ?> <span style="font-size: 0.7rem; opacity: 0.5;">Paths</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section: My Courses — Balanced Grid -->
<div style="margin-bottom: 64px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 32px;">
        <div>
            <h3 style="margin: 0; font-weight: 950; font-size: 1.8rem; letter-spacing: -1px; color: var(--text-main);">My Active Paths<span style="color: var(--primary);">.</span></h3>
            <p style="margin: 8px 0 0; color: var(--text-dim); font-size: 0.9rem; font-weight: 500;">Your high-performance curriculum dashboard.</p>
        </div>
        <a href="courses.php" class="btn-action" style="color: var(--primary); background: rgba(0, 174, 239, 0.08); font-weight: 800; border-radius: 50px; padding: 12px 24px; text-decoration: none;">View Roadmap</a>
    </div>

    <?php if(empty($my_courses)): ?>
    <div class="premium-card" style="padding: 80px 40px; text-align: center; border: 2px dashed #e2e8f0; background: #fff; display: flex; flex-direction: column; align-items: center;">
        <div style="width: 100px; height: 100px; background: #f8fafc; border-radius: 30px; display: flex; align-items: center; justify-content: center; margin-bottom: 32px; font-size: 2.8rem; color: var(--primary); box-shadow: inset 0 4px 10px rgba(0,0,0,0.03);"><i class="fas fa-graduation-cap"></i></div>
        <h4 style="margin: 0 0 12px; font-weight: 950; font-size: 1.5rem; letter-spacing: -0.5px;">No Active Enrollment</h4>
        <p style="color: var(--text-dim); font-size: 1rem; margin: 0 0 32px; max-width: 440px; line-height: 1.6;">Your journey to digital excellence starts here. Explore our elite courses and pick your path today.</p>
        <a href="../courses.php" class="btn-premium" style="min-width: 240px;">EXPLORE CATALOGUE</a>
    </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 32px;">
            <?php foreach($my_courses as $ci => $c): 
                $progress = isset($c['progress_percent']) ? round($c['progress_percent']) : 0;
                $isFallback = isset($c['is_fallback']);
            ?>
            <div class="course-card-premium" onclick="window.location.href='classroom.php?id=<?= $c['course_id'] ?>'" style="cursor: pointer;">
                <div style="height: 200px; position: relative; overflow: hidden;">
                    <img src="../uploads/courses/<?= htmlspecialchars($c['thumbnail'] ?: 'course-default.jpg') ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <div style="position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 40%, rgba(15, 23, 42, 0.9) 100%);"></div>
                    <div style="position: absolute; top: 16px; right: 16px;">
                        <span style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(8px); padding: 6px 14px; border-radius: 50px; color: white; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid rgba(255, 255, 255, 0.2);">
                            <?= $isFallback ? 'Live' : 'On-Demand' ?>
                        </span>
                    </div>
                    <div style="position: absolute; bottom: 16px; left: 16px; right: 16px;">
                        <h5 style="margin: 0; font-size: 1.1rem; font-weight: 900; line-height: 1.3; color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3);"><?= htmlspecialchars($c['title']) ?></h5>
                    </div>
                </div>
                <div style="padding: 24px; flex: 1; display: flex; flex-direction: column;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 24px;">
                        <div style="width: 28px; height: 28px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border);">
                            <img src="../assets/images/user-placeholder.png" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-dim);"><?= isset($c['tutor_name']) ? htmlspecialchars($c['tutor_name']) : 'Skope Lead Instructor' ?></span>
                    </div>
                    
                    <div style="margin-top: auto;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px;">Path Progress</span>
                            <span style="font-size: 0.8rem; font-weight: 900; color: var(--primary);"><?= $progress ?>%</span>
                        </div>
                        <div style="background: #f1f5f9; height: 10px; border-radius: 10px; overflow: hidden; border: 1px solid var(--border);">
                            <div style="height: 100%; width: <?= $progress ?>%; background: linear-gradient(to right, var(--primary), #0072FF); border-radius: 10px; transition: 1s cubic-bezier(0.175, 0.885, 0.32, 1.275);"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>


<!-- Section: Today Tasks — Real-Time Tabs -->
<div style="margin-bottom: 48px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 28px;">
        <div>
            <h3 style="margin: 0; font-weight: 950; font-size: 1.6rem; letter-spacing: -0.5px;">Focus Zone <span style="color: var(--secondary);">.</span></h3>
            <p style="margin: 4px 0 0; color: var(--text-dim); font-size: 0.85rem; font-weight: 500;"><?= date('l, F j, Y') ?></p>
        </div>
        <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 14px; gap: 4px;">
            <button class="tab-btn active" onclick="switchTab(this, 'tab-quizzes')" style="padding: 8px 16px; font-size: 0.75rem; border-radius: 10px; margin: 0;">Quizzes</button>
            <button class="tab-btn" onclick="switchTab(this, 'tab-assignments')" style="padding: 8px 16px; font-size: 0.75rem; border-radius: 10px; margin: 0;">Assignments</button>
            <button class="tab-btn" onclick="switchTab(this, 'tab-classes')" style="padding: 8px 16px; font-size: 0.75rem; border-radius: 10px; margin: 0;">Schedule</button>
        </div>
    </div>

    <style>
        .course-card-premium {
            background: white;
            border-radius: 28px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }
        .course-card-premium:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 114, 255, 0.12);
            border-color: var(--primary);
        }
        .course-card-premium img {
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .course-card-premium:hover img {
            transform: scale(1.08);
        }
        .pulse-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 20px;
            transition: 0.3s;
        }
        .pulse-card:hover {
            border-color: var(--primary);
            background: #fcfdfe;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-dim);
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .tab-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
    </style>

    <!-- Tab: Quizzes -->
    <div id="tab-quizzes" class="tasks-list rt-tab" style="display: flex; flex-direction: column; gap: 12px;">
        <?php
        if(empty($dynamic_tasks)): ?>
            <div style="padding: 40px; text-align: center; background: #f8fafc; border-radius: 20px; border: 1px solid var(--border);">
                <div style="width: 60px; height: 60px; background: #ECFDF5; color: #10B981; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 1.5rem;"><i class="fas fa-check-double"></i></div>
                <p style="margin: 0; font-size: 0.9rem; font-weight: 600; color: var(--text-main);">No Pending Quizzes</p>
                <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-dim);">You've cleared all your knowledge checks!</p>
            </div>
        <?php else: foreach($dynamic_tasks as $idx => $t): ?>
            <div class="task-card">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(0, 174, 239, 0.08); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; color: var(--primary);">
                    <i class="fas fa-brain"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h5 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($t['title']) ?></h5>
                    <p style="margin: 2px 0 0; font-size: 0.75rem; color: var(--text-dim); font-weight: 500;"><?= htmlspecialchars($t['course_name']) ?></p>
                </div>
                <a href="take-quiz.php?id=<?= $t['id'] ?>" class="btn-premium" style="padding: 10px 20px; font-size: 0.75rem; border-radius: 12px; box-shadow: none;">Attempt</a>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Tab: Assignments -->
    <div id="tab-assignments" class="tasks-list rt-tab" style="display: none; flex-direction: column; gap: 12px;">
        <?php if(empty($new_assignments)): ?>
            <div style="padding: 40px; text-align: center; background: #f8fafc; border-radius: 20px; border: 1px solid var(--border);">
                <div style="width: 60px; height: 60px; background: #FFFBEB; color: #F59E0B; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 1.5rem;"><i class="fas fa-clipboard-check"></i></div>
                <p style="margin: 0; font-size: 0.9rem; font-weight: 600; color: var(--text-main);">All Caught Up!</p>
                <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-dim);">No assignments pending your attention right now.</p>
            </div>
        <?php else: foreach($new_assignments as $a):
            $overdue = strtotime($a['due_date']) < time();
        ?>
            <div class="task-card" style="<?= $overdue ? 'border-color: rgba(239, 68, 68, 0.2); background: #FFF5F5;' : '' ?>">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: <?= $overdue ? '#FEE2E2' : '#FEF3C7' ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: <?= $overdue ? '#EF4444' : '#D97706' ?>; font-size: 1.2rem;">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h5 style="margin: 0; font-size: 0.95rem; font-weight: 800;"><?= htmlspecialchars($a['title']) ?></h5>
                    <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                        <span style="font-size: 0.72rem; color: var(--text-dim); font-weight: 500;"><?= htmlspecialchars($a['course_name']) ?></span>
                        <span style="font-size: 0.7rem; font-weight: 700; color: <?= $overdue ? '#EF4444' : '#D97706' ?>; display: flex; align-items: center; gap: 4px;">
                            <i class="far fa-clock"></i> <?= $overdue ? 'Overdue' : 'Due: ' . date('M j', strtotime($a['due_date'])) ?>
                        </span>
                    </div>
                </div>
                <a href="assignments.php" class="btn-action" style="color: <?= $overdue ? '#EF4444' : 'var(--primary)' ?>; background: white; border: 1px solid <?= $overdue ? '#FEE2E2' : 'var(--border)' ?>; font-weight: 800;">Submit</a>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Tab: Classes -->
    <div id="tab-classes" class="tasks-list rt-tab" style="display: none; flex-direction: column; gap: 12px;">
        <?php if(empty($my_courses)): ?>
            <div style="padding: 40px; text-align: center; background: #f8fafc; border-radius: 20px; border: 1px solid var(--border);">
                <div style="width: 60px; height: 60px; background: #F0F9FF; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; font-size: 1.5rem;"><i class="fas fa-calendar-alt"></i></div>
                <p style="margin: 0; font-size: 0.9rem; font-weight: 600; color: var(--text-main);">Classroom Empty</p>
                <p style="margin: 4px 0 0; font-size: 0.8rem; color: var(--text-dim);">Enroll in courses to see your learning schedule.</p>
            </div>
        <?php else: foreach($my_courses as $ci => $course): ?>
            <div class="task-card">
                <div style="width: 48px; height: 48px; border-radius: 14px; background: #F8FAFC; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--primary); font-size: 1.2rem;">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h5 style="margin: 0; font-size: 0.95rem; font-weight: 800;"><?= htmlspecialchars($course['title']) ?></h5>
                    <p style="margin: 2px 0 0; font-size: 0.72rem; color: var(--text-dim); font-weight: 500;">
                        <?= isset($course['tutor_name']) ? htmlspecialchars($course['tutor_name']) : 'SDAC Academy' ?>
                        <span style="margin-left: 8px; color: var(--primary); font-weight: 700;"><?= round($course['progress_percent'] ?? 0) ?>% Path Complete</span>
                    </p>
                </div>
                <a href="classroom.php?id=<?= $course['course_id'] ?>" class="btn-action" style="background: var(--primary); color: white;">Open Path</a>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>


<!-- ===== LIVE ACTIVITY FEED ===== -->
<div style="margin-top: 48px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h3 style="margin: 0; font-weight: 950; font-size: 1.5rem; letter-spacing: -0.5px;">Live Pulse <span style="color: var(--secondary);">.</span></h3>
        <a href="notifications.php" style="font-size: 0.8rem; font-weight: 800; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 6px;">View Full History <i class="fas fa-arrow-right"></i></a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">

        <?php // --- Notifications
        foreach(array_slice($live_notifications, 0, 4) as $notif): ?>
        <div class="premium-card glass-effect" style="display: flex; flex-direction: column; padding: 20px; gap: 12px; border-radius: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: <?= empty($notif['read_status']) ? 'var(--grad-primary)' : '#f1f5f9' ?>; display: flex; align-items: center; justify-content: center; color: <?= empty($notif['read_status']) ? 'white' : 'var(--text-dim)' ?>;">
                    <i class="fas fa-bell" style="font-size: 0.9rem;"></i>
                </div>
                <span style="font-size: 0.65rem; font-weight: 800; color: var(--text-dim);"><?= date('M j, g:i A', strtotime($notif['created_at'])) ?></span>
            </div>
            <div>
                <strong style="display: block; font-size: 0.9rem; font-weight: 900; color: var(--text-main); margin-bottom: 4px;"><?= htmlspecialchars($notif['title'] ?? 'System Update') ?></strong>
                <p style="margin: 0; font-size: 0.8rem; color: var(--text-dim); line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?= htmlspecialchars($notif['message']) ?></p>
            </div>
            <?php if(empty($notif['read_status'])): ?>
                <div style="margin-top: auto; display: flex; align-items: center; gap: 6px;">
                    <span style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></span>
                    <span style="font-size: 0.65rem; font-weight: 900; color: var(--primary); text-transform: uppercase;">Unread Alert</span>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if(empty($live_notifications) && empty($new_assignments) && empty($cat_quizzes)): ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 60px; background: #f8fafc; border-radius: 24px; border: 1px dashed var(--border);">
            <div style="font-size: 3rem; color: var(--border); margin-bottom: 16px;"><i class="fas fa-broadcast-tower"></i></div>
            <p style="margin: 0; font-size: 1rem; font-weight: 700; color: var(--text-dim);">The airwaves are quiet. No new updates for now.</p>
        </div>
        <?php endif; ?>

    </div>
</div>
<!-- ===== END ACTIVITY FEED ===== -->

<!-- Event Scheduling Modal -->
<div id="eventModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; width: 90%; max-width: 400px; border-radius: 20px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0; font-weight: 800;">Schedule Activity</h4>
            <button onclick="document.getElementById('eventModal').style.display = 'none'" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-dim); transition: 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='var(--text-dim)'"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_event">
            <input type="hidden" name="event_date" id="modalEventDate">
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 8px;">Activity Title</label>
                <input type="text" name="title" required placeholder="e.g. Revision for Science CAT" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; box-sizing: border-box; outline: none; transition: 0.2s;" onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--border)'">
            </div>
            
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 8px;">Category</label>
                <select name="category" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 10px; box-sizing: border-box; outline: none; cursor: pointer;">
                    <option value="activity">📚 Standard Activity</option>
                    <option value="cat">📝 CAT Date Setup</option>
                    <option value="tutor">👨‍🏫 Tutor Consultation</option>
                </select>
            </div>
            
            <button type="submit" style="width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 10px; font-weight: 800; cursor: pointer; font-size: 0.95rem; transition: 0.2s; box-shadow: 0 4px 15px rgba(0, 174, 239, 0.3);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">Save & Bind Reminder</button>
        </form>
    </div>
</div>

<script>
    function openEventModal(dateStr) {
        document.getElementById('modalEventDate').value = dateStr;
        document.getElementById('eventModal').style.display = 'flex';
    }
    
    function switchTab(btn, sectionId) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.rt-tab').forEach(tab => {
            tab.style.display = 'none';
        });
        const target = document.getElementById(sectionId);
        if (target) {
            target.style.display = 'flex';
            target.style.flexDirection = 'column';
        }
    }
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
