<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

try {
    // Calendar reminders table (created on first use)
    $pdo->exec("CREATE TABLE IF NOT EXISTS calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        event_date DATE NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(50) DEFAULT 'activity',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Add a reminder from the calendar
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_event') {
        $ev_date = (string)($_POST['event_date'] ?? '');
        $ev_title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 255);
        $ev_cat = in_array($_POST['category'] ?? '', ['activity', 'cat', 'tutor'], true) ? $_POST['category'] : 'activity';

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ev_date) && $ev_title !== '') {
            $ins = $pdo->prepare("INSERT INTO calendar_events (student_id, event_date, title, category) VALUES (?, ?, ?, ?)");
            $ins->execute([$student['id'], $ev_date, $ev_title, $ev_cat]);
            try {
                $nIns = $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id, read_status, created_at) VALUES ('Reminder set', ?, 'student', ?, 0, NOW())");
                $nIns->execute(["$ev_title on " . date('j M Y', strtotime($ev_date)), $student['id']]);
            } catch (Exception $ex) {}
        }
        header("Location: ./");
        exit;
    }

    // My courses: in progress first, then finished ones
    // (kept in $dash_courses: includes/portal-data.php reuses the name $my_courses)
    $stmt = $pdo->prepare("SELECT e.*, c.title, c.thumbnail, c.level, c.total_lessons, u.name as tutor_name
                           FROM enrollments e
                           JOIN courses c ON e.course_id = c.id
                           LEFT JOIN users u ON c.tutor_id = u.id
                           WHERE e.student_id = ? AND e.status IN ('active', 'completed')
                           ORDER BY (e.status = 'completed'), e.enrolled_at DESC");
    $stmt->execute([$student['id']]);
    $dash_courses = $stmt->fetchAll();
    $active_courses = array_values(array_filter($dash_courses, fn($c) => $c['status'] === 'active'));
    $done_courses = count($dash_courses) - count($active_courses);

    // Quizzes not yet attempted
    $stmt = $pdo->prepare("SELECT q.*, c.title as course_name
                           FROM quizzes q
                           JOIN enrollments e ON q.course_id = e.course_id
                           JOIN courses c ON q.course_id = c.id
                           WHERE e.student_id = ? AND e.status = 'active'
                             AND q.id NOT IN (SELECT quiz_id FROM quiz_attempts WHERE student_id = ?)
                           LIMIT 5");
    $stmt->execute([$student['id'], $student['id']]);
    $dynamic_tasks = $stmt->fetchAll();

    // Recommended: published courses the student has not joined
    $stmt = $pdo->prepare("SELECT c.id, c.title, c.slug, c.thumbnail, c.level, c.price, c.total_lessons, c.is_open_tutorial
                           FROM courses c
                           WHERE c.status = 'published' AND c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)
                           ORDER BY c.is_featured DESC, c.enrolled_count DESC, c.created_at DESC LIMIT 3");
    $stmt->execute([$student['id']]);
    $recommended = $stmt->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
    $dash_courses = $active_courses = $dynamic_tasks = $recommended = [];
    $done_courses = 0;
}

// Real progress from the XP engine (level, XP, streak, lessons, certificates)
$lvl = ['level' => 1, 'title' => 'Newcomer', 'xp' => 0, 'from' => 0, 'to' => 100, 'pct' => 0];
$ustats = ['lessons' => 0, 'streak' => (int)($student['streak_days'] ?? 0), 'xp' => 0, 'seconds' => 0];
$cert_count = 0;
try {
    require_once __DIR__ . '/../includes/progress.php';
    $ustats = user_stats($pdo, (int)$student['id']);
    $lvl = xp_level((int)$ustats['xp']);
    $cs = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = ? AND status <> 'revoked'");
    $cs->execute([$student['id']]);
    $cert_count = (int)$cs->fetchColumn();
} catch (Throwable $e) { error_log('dashboard stats: ' . $e->getMessage()); }
$panel_stats = ['streak' => (int)($student['streak_days'] ?? 0), 'xp' => (int)$lvl['xp'], 'level' => (int)$lvl['level']];
$first_name = htmlspecialchars(explode(' ', trim((string)$student['name']))[0] ?: 'there');
$hour = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$next_course = $active_courses[0] ?? null;
$hours_learned = round(((int)($ustats['seconds'] ?? 0)) / 3600, 1);

require_once 'includes/layout-top.php';
?>

<!-- Welcome -->
<section class="sp-hero">
    <div class="sp-hero-grid">
        <div>
            <div class="sp-row">
                <span class="sp-badge dark"><i class="far fa-calendar"></i> <?= date('l, j F') ?></span>
                <?php if ((int)($student['streak_days'] ?? 0) > 0): ?><span class="sp-badge dark">🔥 <?= (int)$student['streak_days'] ?>-day streak</span><?php endif; ?>
            </div>
            <h1><?= $greeting ?>, <?= $first_name ?> 👋</h1>
            <p>
                <?php if ($next_course): ?>
                    Pick up where you left off in <strong style="color: #fff;"><?= htmlspecialchars($next_course['title']) ?></strong>.
                <?php elseif ($done_courses): ?>
                    You've finished <?= $done_courses ?> course<?= $done_courses == 1 ? '' : 's' ?>. Ready for the next one?
                <?php else: ?>
                    Start with a free tutorial or choose a course. Every lesson earns XP.
                <?php endif; ?>
            </p>
            <div class="sp-row" style="margin-top: 20px;">
                <?php if ($next_course): ?>
                    <a href="classroom?id=<?= (int)$next_course['course_id'] ?>" class="sp-btn lg"><i class="fas fa-play"></i> Continue learning</a>
                <?php else: ?>
                    <a href="../courses" class="sp-btn lg"><i class="fas fa-compass"></i> Browse courses</a>
                <?php endif; ?>
                <a href="../learn" class="sp-btn lg ghost" style="background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.2); color: #fff !important;"><i class="fas fa-book-open"></i> Free tutorials</a>
            </div>
        </div>
        <a href="achievements" class="sp-level-card" style="text-decoration: none; color: #fff;">
            <div class="sp-between">
                <span style="font-size: .75rem; font-weight: 700; opacity: .7; text-transform: uppercase; letter-spacing: .06em;">Your level</span>
                <i class="fas fa-medal" style="color: #FFC53D;"></i>
            </div>
            <div class="sp-row" style="align-items: baseline; margin: 8px 0 2px;">
                <span class="sp-level-num"><?= (int)$lvl['level'] ?></span>
                <span style="font-weight: 700;"><?= htmlspecialchars($lvl['title']) ?></span>
            </div>
            <div class="sp-progress dark" style="margin: 12px 0 8px;"><span style="width: <?= max(3, (int)$lvl['pct']) ?>%;"></span></div>
            <div class="sp-between" style="font-size: .75rem; opacity: .8;">
                <span><?= number_format((int)$lvl['xp']) ?> XP</span>
                <span><?= number_format(max(0, (int)$lvl['to'] - (int)$lvl['xp'])) ?> XP to level <?= (int)$lvl['level'] + 1 ?></span>
            </div>
        </a>
    </div>
</section>

<!-- Quick stats -->
<section class="sp-grid cols-4 sp-section">
    <div class="sp-stat"><span class="sp-stat-icon"><i class="fas fa-book-open-reader"></i></span><div><strong><?= count($active_courses) ?></strong><span>Courses in progress</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon green"><i class="fas fa-circle-check"></i></span><div><strong><?= (int)$ustats['lessons'] ?></strong><span>Lessons completed</span></div></div>
    <div class="sp-stat"><span class="sp-stat-icon orange"><i class="fas fa-clock"></i></span><div><strong><?= $hours_learned ?>h</strong><span>Time learning</span></div></div>
    <a href="certificates" class="sp-stat" style="text-decoration: none;"><span class="sp-stat-icon violet"><i class="fas fa-certificate"></i></span><div><strong><?= $cert_count ?></strong><span>Certificates</span></div></a>
</section>

<!-- My courses -->
<section class="sp-section">
    <div class="sp-section-head">
        <div>
            <h2>My courses</h2>
            <p><?= count($active_courses) ?> in progress<?= $done_courses ? ' · ' . $done_courses . ' finished' : '' ?></p>
        </div>
        <a href="courses" class="sp-link">See all <i class="fas fa-arrow-right"></i></a>
    </div>

    <?php if (empty($dash_courses)): ?>
        <div class="sp-empty">
            <div class="sp-ico"><i class="fas fa-graduation-cap"></i></div>
            <h3>You haven't joined a course yet</h3>
            <p>Start free with a tutorial (HTML, Python, SQL and more), or choose a course that ends with a certificate.</p>
            <div class="sp-row" style="justify-content: center;">
                <a href="../courses" class="sp-btn">Browse courses</a>
                <a href="../learn" class="sp-btn ghost">Free tutorials</a>
            </div>
        </div>
    <?php else: ?>
        <div class="sp-grid cols-3">
            <?php foreach (array_slice($dash_courses, 0, 6) as $c):
                $isDone = $c['status'] === 'completed';
                $progress = $isDone ? 100 : (int)round($c['progress_percent'] ?? 0);
            ?>
            <a class="sp-card hover sp-course" href="classroom?id=<?= (int)$c['course_id'] ?>">
                <div class="sp-course-thumb">
                    <img src="../uploads/courses/<?= htmlspecialchars($c['thumbnail'] ?: 'course-default.jpg') ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                    <span class="sp-badge <?= $isDone ? 'green' : '' ?>"><?= $isDone ? '✓ Finished' : (($c['learning_mode'] ?? '') === 'structured' ? 'Structured' : 'Self-paced') ?></span>
                </div>
                <div class="sp-course-body">
                    <h3><?= htmlspecialchars($c['title']) ?></h3>
                    <div class="sp-course-meta">
                        <span><i class="fas fa-signal"></i> <?= ucfirst(htmlspecialchars($c['level'] ?? 'beginner')) ?></span>
                        <?php if (!empty($c['total_lessons'])): ?><span><i class="fas fa-list"></i> <?= (int)$c['total_lessons'] ?> lessons</span><?php endif; ?>
                    </div>
                    <div>
                        <div class="sp-progress <?= $isDone ? 'green' : '' ?>"><span style="width: <?= max(2, $progress) ?>%;"></span></div>
                    </div>
                    <div class="sp-course-foot">
                        <span class="sp-muted"><?= $progress ?>% complete</span>
                        <span style="color: var(--primary-dark);"><?= $isDone ? 'Review' : ($progress > 0 ? 'Continue' : 'Start') ?> <i class="fas fa-arrow-right"></i></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- To do -->
<section class="sp-section">
    <div class="sp-section-head">
        <div><h2>To do</h2><p>Quizzes and assignments waiting for you</p></div>
        <div class="sp-tabs" role="tablist">
            <button class="sp-tab active" onclick="switchTab(this, 'tab-quizzes')">Quizzes<?= $dynamic_tasks ? ' (' . count($dynamic_tasks) . ')' : '' ?></button>
            <button class="sp-tab" onclick="switchTab(this, 'tab-assignments')">Assignments<?= $new_assignments ? ' (' . count($new_assignments) . ')' : '' ?></button>
        </div>
    </div>

    <div class="sp-card" style="padding: 6px 18px;">
        <div id="tab-quizzes" class="rt-tab sp-list">
            <?php if (empty($dynamic_tasks)): ?>
                <div class="sp-list-item"><span class="sp-ico green"><i class="fas fa-check"></i></span><div class="sp-grow"><h4>No quizzes waiting</h4><p>New quizzes from your courses will show up here.</p></div></div>
            <?php else: foreach ($dynamic_tasks as $t): ?>
                <div class="sp-list-item">
                    <span class="sp-ico"><i class="fas fa-circle-question"></i></span>
                    <div class="sp-grow"><h4><?= htmlspecialchars($t['title']) ?></h4><p><?= htmlspecialchars($t['course_name']) ?></p></div>
                    <a href="take-quiz?id=<?= (int)$t['id'] ?>" class="sp-btn sm">Start quiz</a>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div id="tab-assignments" class="rt-tab sp-list" style="display: none;">
            <?php if (empty($new_assignments)): ?>
                <div class="sp-list-item"><span class="sp-ico green"><i class="fas fa-check"></i></span><div class="sp-grow"><h4>All caught up</h4><p>No assignments are waiting for you right now.</p></div></div>
            <?php else: foreach ($new_assignments as $a):
                $overdue = !empty($a['due_date']) && strtotime($a['due_date']) < time();
            ?>
                <div class="sp-list-item">
                    <span class="sp-ico <?= $overdue ? 'red' : 'orange' ?>"><i class="fas fa-file-pen"></i></span>
                    <div class="sp-grow"><h4><?= htmlspecialchars($a['title']) ?></h4><p><?= htmlspecialchars($a['course_name']) ?></p></div>
                    <?php if (!empty($a['due_date'])): ?><span class="sp-badge <?= $overdue ? 'red' : 'yellow' ?> sp-hide-sm"><?= $overdue ? 'Overdue' : 'Due ' . date('j M', strtotime($a['due_date'])) ?></span><?php endif; ?>
                    <a href="assignments" class="sp-btn sm <?= $overdue ? 'danger' : 'ghost' ?>">Submit</a>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>

<!-- Recommended + recent activity -->
<section class="sp-split sp-section">
    <div>
        <div class="sp-section-head"><div><h2>Recommended for you</h2><p>Popular courses you haven't joined yet</p></div><a href="../courses" class="sp-link">Browse all <i class="fas fa-arrow-right"></i></a></div>
        <?php if (empty($recommended)): ?>
            <div class="sp-empty" style="padding: 28px;"><p style="margin: 0;">You've joined every course we have. Nice work!</p></div>
        <?php else: ?>
            <div class="sp-card" style="padding: 6px 18px;">
                <div class="sp-list">
                    <?php foreach ($recommended as $r):
                        $free = (float)$r['price'] <= 0;
                        $href = !empty($r['is_open_tutorial']) && !empty($r['slug']) ? '../learn/' . rawurlencode($r['slug']) : '../course-details?id=' . (int)$r['id'];
                    ?>
                    <a href="<?= htmlspecialchars($href) ?>" class="sp-list-item" style="text-decoration: none;">
                        <span class="sp-ico gray" style="overflow: hidden; width: 56px; height: 40px;">
                            <img src="../uploads/courses/<?= htmlspecialchars($r['thumbnail'] ?: 'course-default.jpg') ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.replaceWith(Object.assign(document.createElement('i'), {className: 'fas fa-laptop-code'}))">
                        </span>
                        <div class="sp-grow"><h4 class="sp-truncate"><?= htmlspecialchars($r['title']) ?></h4><p><?= ucfirst(htmlspecialchars($r['level'] ?? 'beginner')) ?><?= !empty($r['total_lessons']) ? ' · ' . (int)$r['total_lessons'] . ' lessons' : '' ?></p></div>
                        <span class="sp-badge <?= $free ? 'green' : 'gray' ?>"><?= $free ? 'Free' : 'KES ' . number_format((float)$r['price']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="sp-section-head"><div><h2>Recent activity</h2><p>Badges, certificates and updates</p></div><a href="notifications" class="sp-link">All</a></div>
        <div class="sp-card" style="padding: 6px 18px;">
            <?php if (empty($live_notifications)): ?>
                <p class="sp-muted sp-small" style="padding: 14px 0; margin: 0;">Nothing yet. Finish a lesson to earn your first XP.</p>
            <?php else: ?>
                <div class="sp-list">
                    <?php foreach (array_slice($live_notifications, 0, 4) as $n):
                        $t = strtolower(($n['title'] ?? '') . ' ' . ($n['message'] ?? ''));
                        [$ic, $col] = str_contains($t, 'badge') ? ['fa-medal', 'violet'] : (str_contains($t, 'certificate') ? ['fa-certificate', 'green'] : (str_contains($t, 'reminder') ? ['fa-calendar-day', 'orange'] : ['fa-bell', '']));
                    ?>
                    <div class="sp-list-item">
                        <span class="sp-ico <?= $col ?>" style="width: 34px; height: 34px;"><i class="fas <?= $ic ?>"></i></span>
                        <div class="sp-grow"><h5 class="sp-truncate"><?= htmlspecialchars($n['title'] ?? 'Update') ?></h5><p class="sp-truncate"><?= htmlspecialchars($n['message'] ?? '') ?></p></div>
                        <span class="sp-muted" style="font-size: .7rem; white-space: nowrap;"><?= !empty($n['created_at']) ? date('j M', strtotime($n['created_at'])) : '' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Add-reminder dialog (opened from the calendar) -->
<div class="sp-modal" id="eventModal" onclick="if (event.target === this) closeEventModal()">
    <div class="sp-modal-box" role="dialog" aria-labelledby="evTitle">
        <div class="sp-between" style="margin-bottom: 14px;">
            <h3 id="evTitle" style="margin: 0; font-size: 1.05rem;">Add a reminder</h3>
            <button type="button" class="sp-icon-btn" onclick="closeEventModal()" aria-label="Close"><i class="fas fa-xmark"></i></button>
        </div>
        <form method="POST" class="sp-form">
            <input type="hidden" name="action" value="add_event">
            <input type="hidden" name="event_date" id="modalEventDate">
            <p class="sp-muted sp-small" id="modalEventLabel" style="margin: 0 0 14px;"></p>
            <div class="sp-field">
                <label for="evName">What do you want to remember?</label>
                <input type="text" id="evName" name="title" required maxlength="255" placeholder="e.g. Revise for the Python quiz">
            </div>
            <div class="sp-field">
                <label for="evCat">Type</label>
                <select id="evCat" name="category">
                    <option value="activity">Study session</option>
                    <option value="cat">Quiz or test</option>
                    <option value="tutor">Meeting with a tutor</option>
                </select>
            </div>
            <button type="submit" class="sp-btn block">Save reminder</button>
        </form>
    </div>
</div>

<script>
function openEventModal(dateStr) {
    document.getElementById('modalEventDate').value = dateStr;
    const d = new Date(dateStr + 'T00:00:00');
    document.getElementById('modalEventLabel').textContent = d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    document.getElementById('eventModal').classList.add('open');
    setTimeout(() => document.getElementById('evName').focus(), 50);
}
function closeEventModal() { document.getElementById('eventModal').classList.remove('open'); }
function switchTab(btn, id) {
    btn.parentElement.querySelectorAll('.sp-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.rt-tab').forEach(t => t.style.display = 'none');
    const el = document.getElementById(id);
    if (el) el.style.display = '';
}
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
