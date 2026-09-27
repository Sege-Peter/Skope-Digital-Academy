<?php
$pageTitle = 'Student Roster';
require_once 'includes/header.php';

$tutor = currentUser();

$search = $_GET['search'] ?? '';
$course_filter = $_GET['course_id'] ?? '';

// Helper to securely send welcome email
function sendStudentWelcomeEmail($to_email, $student_name, $course_title, $temp_password) {
    $subject = "Welcome to Skope Digital Academy - $course_title";
    
    $message = "
    <html>
    <head>
      <title>Welcome to Skope Digital Academy</title>
      <style>
        body { font-family: 'Inter', Arial, sans-serif; background-color: #f8fafc; padding: 30px; }
        .container { background-color: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); max-width: 600px; margin: 0 auto; }
        .header { color: #0f172a; font-size: 24px; font-weight: 800; margin-bottom: 20px; }
        .text { color: #475569; font-size: 16px; line-height: 1.6; }
        .box { background-color: #f0f9ff; border: 1px solid #bae6fd; padding: 20px; border-radius: 8px; margin: 24px 0; text-align: center; }
        .password { font-family: monospace; font-size: 20px; font-weight: bold; color: #0284c7; letter-spacing: 2px; }
        .footer { font-size: 13px; color: #94a3b8; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px; }
      </style>
    </head>
    <body>
      <div class='container'>
        <div class='header'>Your Academic Journey Begins!</div>
        <div class='text'>
          Hello <strong>".htmlspecialchars($student_name)."</strong>,<br><br>
          You have successfully been enrolled in <strong>".htmlspecialchars($course_title)."</strong> at Skope Digital Academy. We are thrilled to have you!
        </div>
        <div class='box'>
          <div style='font-size: 14px; color: #0284c7; font-weight: bold; text-transform: uppercase; margin-bottom: 8px;'>Your Temporary Access Pass is:</div>
          <div class='password'>".htmlspecialchars($temp_password)."</div>
        </div>
        <div class='text'>
          <em>For security protocols, you will be mandated to change this temporary password during your first login.</em>
        </div>
        <div class='footer'>
          If you received this in error, please disregard.<br>
          Skope Digital Academy Admissions
        </div>
      </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Skope Admin <admissions@skopedigital.com>" . "\r\n";

    @mail($to_email, $subject, $message, $headers);
}

$enroll_msg = '';
$enroll_err = '';

// Handle Manual Student Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $s_name = trim($_POST['student_name']);
    $s_email = trim($_POST['student_email']);
    $c_id = (int)$_POST['course_id'];
    
    $temp_pass = 'Pass' . rand(1000, 9999) . '!';
    $hashed_pass = password_hash($temp_pass, PASSWORD_DEFAULT);

    try {
        $chk_c = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND tutor_id = ?");
        $chk_c->execute([$c_id, $tutor['id']]);
        if (!$chk_c->fetch()) {
            throw new Exception("You can only enroll students into your own courses.");
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$s_email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $u_id = $existing['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status, requires_password_change) VALUES (?, ?, ?, 'student', 'active', 1)");
            $stmt->execute([$s_name, $s_email, $hashed_pass]);
            $u_id = $pdo->lastInsertId();
        }

        $chk = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
        $chk->execute([$u_id, $c_id]);
        if ($chk->fetch()) {
            $enroll_err = "Student is already enrolled in this course.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active')");
            $stmt->execute([$u_id, $c_id]);
            $enroll_msg = "Successfully enrolled in course!";
            
            $c_t = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
            $c_t->execute([$c_id]);
            $courseInfo = $c_t->fetch();
            $c_title_mail = $courseInfo ? $courseInfo['title'] : 'Course';

            sendStudentWelcomeEmail($s_email, $s_name, $c_title_mail, $temp_pass);
        }

        header("Location: students.php?msg=" . urlencode($enroll_msg) . "&err=" . urlencode($enroll_err));
        exit;

    } catch (Exception $e) {
        header("Location: students.php?err=" . urlencode($e->getMessage()));
        exit;
    }
}

// Handle CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, "r");
        if ($handle !== FALSE) {
            $row = 0;
            $success_count = 0;
            $error_count = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                if ($row == 1) continue;
                
                if (count($data) >= 3) {
                    $s_name = trim($data[0]);
                    $s_email = trim($data[1]);
                    $c_id = (int)trim($data[2]);
                    
                    if(empty($s_name) || empty($s_email) || !$c_id) { $error_count++; continue; }

                    try {
                        $chk_c = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND tutor_id = ?");
                        $chk_c->execute([$c_id, $tutor['id']]);
                        if (!$chk_c->fetch()) continue;

                        $temp_pass = 'Pass' . rand(1000, 9999) . '!';
                        $hashed_pass = password_hash($temp_pass, PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$s_email]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) { $u_id = $existing['id']; } 
                        else {
                            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, requires_password_change) VALUES (?, ?, ?, 'student', 1)");
                            $stmt->execute([$s_name, $s_email, $hashed_pass]);
                            $u_id = $pdo->lastInsertId();
                        }

                        $chk = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
                        $chk->execute([$u_id, $c_id]);
                        if (!$chk->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'active')");
                            $stmt->execute([$u_id, $c_id]);
                            
                            $c_t = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
                            $c_t->execute([$c_id]);
                            $c_title_mail = ($cInfo = $c_t->fetch()) ? $cInfo['title'] : 'Course';
                            sendStudentWelcomeEmail($s_email, $s_name, $c_title_mail, $temp_pass);
                            $success_count++;
                        }
                    } catch (Exception $e) { $error_count++; }
                }
            }
            fclose($handle);
            $enroll_msg = "CSV Import Complete: $success_count enrolled, $error_count skipped.";
            header("Location: students.php?msg=" . urlencode($enroll_msg));
            exit;
        }
    }
}

try {
    // Fetch tutor's courses for the filter dropdown
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id = ? ORDER BY title ASC");
    $stmt->execute([$tutor['id']]);
    $tutor_courses = $stmt->fetchAll();

    // Fetch students enrolled in this tutor's courses
    $query = "SELECT u.id as student_id, u.name as student_name, u.email as student_email, u.avatar, u.last_login,
                     c.title as course_title, e.progress_percent, e.enrolled_at, e.course_id,
                     (SELECT AVG(score) FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id WHERE qa.student_id = u.id AND q.course_id = c.id) as avg_score
              FROM enrollments e
              JOIN users u ON e.student_id = u.id
              JOIN courses c ON e.course_id = c.id
              WHERE c.tutor_id = ?";
    
    $params = [$tutor['id']];

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


<?php require_once 'includes/sidebar.php'; ?>

<main class="main-content">
<header class="portal-header" style="margin-bottom: 40px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px;">
    <div class="greeting">
        <h1 style="font-size: clamp(1.8rem, 5vw, 2.4rem); font-weight: 950; margin: 0; letter-spacing: -1.5px; color: var(--text-main);">Scholars<span style="color: var(--primary);">.</span></h1>
        <p style="color: var(--text-dim); margin-top: 8px; font-weight: 500; font-size: 1rem;">Directing academic performance and enrollment of <span style="color: var(--primary); font-weight: 700;"><?= count($students) ?> active learners</span>.</p>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <button onclick="document.getElementById('enrollModal').style.display='flex'" class="btn-premium" style="padding: 12px 24px;">
            <i class="fas fa-user-plus"></i> ENROLL SCHOLAR
        </button>
        <button onclick="document.getElementById('importModal').style.display='flex'" class="btn-premium" style="padding: 12px 24px; background: var(--secondary); border: none;">
            <i class="fas fa-file-csv"></i> BULK IMPORT
        </button>
        <a href="export_students.php?course_filter=<?= urlencode($course_filter) ?>" class="btn-premium" style="padding: 12px 24px; background: #10B981; border: none; text-decoration: none; display: flex; align-items: center; justify-content: center;">
            <i class="fas fa-file-export"></i> EXPORT
        </a>
    </div>
</header>

<div class="premium-card" style="padding: 24px 32px; margin-bottom: 40px;">
    <form method="GET" action="students.php" class="grid-3" style="align-items: flex-end;">
        <div>
            <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Scholar Identity Search</label>
            <div style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 18px; top: 16px; color: #94a3b8;"></i>
                <input type="text" name="search" style="width: 100%; padding: 14px 18px 14px 48px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        <div>
            <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Academic Track Filter</label>
            <select name="course_id" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; background: #f8fafc;" onchange="this.form.submit()">
                <option value="">Viewing All Tracks</option>
                <?php foreach($tutor_courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $course_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-premium" style="height: 54px; width: 100%;">SEARCH</button>
    </form>
</div>

<div class="premium-card" style="padding: 0; overflow: hidden;">
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
            <thead>
                <tr style="background: #F8FAFC; border-bottom: 1px solid #f1f5f9;">
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Identity</th>
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Program Track</th>
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Arc Progress</th>
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Academic Health</th>
                    <th style="padding: 24px 32px; text-align: left; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Engagement</th>
                    <th style="padding: 24px 32px; text-align: right; font-size: 0.75rem; font-weight: 950; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($students)): ?>
                    <?php foreach($students as $s): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: 0.2s;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 24px 32px;">
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <div style="width: 44px; height: 44px; border-radius: 14px; background: #E0F7FF; color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 950; font-size: 1.1rem; overflow: hidden; border: 1px solid #E0F7FF;">
                                    <?php if($s['avatar']): ?>
                                        <img src="../uploads/avatars/<?= htmlspecialchars($s['avatar']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                    <?php else: ?>
                                        <?= strtoupper(substr($s['student_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight: 900; color: var(--text-main); font-size: 0.95rem; letter-spacing: -0.3px;"><?= htmlspecialchars($s['student_name']) ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-dim); font-weight: 600;"><?= htmlspecialchars($s['student_email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 24px 32px;">
                            <div style="padding: 6px 14px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 50px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.75rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.2px;">
                                <i class="fas fa-bookmark" style="color: var(--primary); font-size: 0.7rem;"></i>
                                <?= htmlspecialchars($s['course_title']) ?>
                            </div>
                            <div style="font-size: 0.65rem; color: #94A3B8; font-weight: 850; margin-top: 6px; text-transform: uppercase; letter-spacing: 1px;">Enrolled <?= date('M d, Y', strtotime($s['enrolled_at'])) ?></div>
                        </td>
                        <td style="padding: 24px 32px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="flex: 1; height: 8px; background: #F1F5F9; border-radius: 10px; overflow: hidden;">
                                    <div style="height: 100%; background: var(--grad-primary); border-radius: 10px; width: <?= $s['progress_percent'] ?>%;"></div>
                                </div>
                                <span style="font-size: 0.9rem; font-weight: 950; color: var(--primary);"><?= round($s['progress_percent']) ?>%</span>
                            </div>
                        </td>
                        <td style="padding: 24px 32px;">
                            <?php 
                            $score = $s['avg_score'];
                            if($score === null): ?>
                                <span style="font-size: 0.75rem; font-weight: 900; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px;">Pending Audit</span>
                            <?php else: 
                                $isHigh = $score >= 80;
                                $isMid = $score >= 60;
                                $color = $isHigh ? '#10B981' : ($isMid ? '#F59E0B' : '#EF4444');
                                $bg = $isHigh ? '#ECFDF5' : ($isMid ? '#FFFBEB' : '#FEF2F2');
                            ?>
                                <div style="padding: 6px 14px; background: <?= $bg ?>; border: 1px solid <?= $color ?>44; border-radius: 50px; display: inline-flex; align-items: center; gap: 8px; font-size: 0.8rem; font-weight: 950; color: <?= $color ?>;">
                                    <i class="fas <?= $isHigh ? 'fa-star' : ($isMid ? 'fa-bolt' : 'fa-exclamation-triangle') ?>"></i>
                                    <?= round($score) ?>% Avg
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 24px 32px;">
                            <div style="font-size: 0.85rem; font-weight: 800; color: var(--text-main);"><?= $s['last_login'] ? date('M d, g:i a', strtotime($s['last_login'])) : 'Pending Login' ?></div>
                            <div style="font-size: 0.65rem; color: #94A3B8; font-weight: 850; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px;"><?= $s['last_login'] ? 'Engagement Verified' : 'Awaiting Access' ?></div>
                        </td>
                        <td style="padding: 24px 32px; text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <a href="student_profile.php?id=<?= $s['student_id'] ?>" style="width: 40px; height: 40px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--text-dim); text-decoration: none; transition: 0.2s;" onmouseover="this.style.color='var(--primary)'; this.style.borderColor='var(--primary)';" title="View Profile"><i class="fas fa-user-graduate"></i></a>
                                <a href="../impersonate.php?action=start&uid=<?= $s['student_id'] ?>" style="width: 40px; height: 40px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #10B981; text-decoration: none; transition: 0.2s;" onmouseover="this.style.background='#ECFDF5';" title="Secure Proxy Login"><i class="fas fa-fingerprint"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="padding: 100px 40px; text-align: center;">
                            <div style="width: 80px; height: 80px; background: #F8FAFC; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; font-size: 2rem; color: #CBD5E1;"><i class="fas fa-user-slash"></i></div>
                            <h3 style="margin: 0 0 8px; font-weight: 950; font-size: 1.25rem;">No scholars matched.</h3>
                            <p style="color: var(--text-dim); font-weight: 600;">Try clearing your search or filtering by another track.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</main>

<!-- Enroll Student Modal -->
<div id="enrollModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(12px); z-index: 9999; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;">
    <div class="premium-card" style="width: 90%; max-width: 500px; padding: 48px; position: relative; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <button onclick="document.getElementById('enrollModal').style.display='none'" style="position: absolute; right: 24px; top: 24px; background: none; border: none; font-size: 1.5rem; color: #94A3B8; cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='var(--text-main)'">&times;</button>
        
        <h2 style="margin: 0 0 12px; font-size: 1.6rem; font-weight: 950; letter-spacing: -0.8px; color: var(--text-main);">Manual Enrollment</h2>
        <p style="color: var(--text-dim); margin-bottom: 40px; font-weight: 600;">Directly onboard a new scholar into your elite academy.</p>

        <form method="POST">
            <input type="hidden" name="enroll_student" value="1">
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Full Legal Name</label>
                <input type="text" name="student_name" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" required placeholder="e.g. Alexander Hamilton">
            </div>
            <div style="margin-bottom: 24px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Institutional Email</label>
                <input type="email" name="student_email" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none;" required placeholder="alex@university.com">
            </div>
            <div style="margin-bottom: 40px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">Academic Track Allocation</label>
                <select name="course_id" style="width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 14px; font-size: 0.95rem; font-weight: 600; outline: none; background: #f8fafc;" required>
                    <?php foreach($tutor_courses as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-premium" style="width: 100%; height: 58px;">ONBOARD SCHOLAR</button>
        </form>
    </div>
</div>

<!-- Import Modal -->
<div id="importModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(12px); z-index: 9999; align-items: center; justify-content: center; animation: fadeIn 0.3s ease;">
    <div class="premium-card" style="width: 90%; max-width: 500px; padding: 48px; position: relative; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <button onclick="document.getElementById('importModal').style.display='none'" style="position: absolute; right: 24px; top: 24px; background: none; border: none; font-size: 1.5rem; color: #94A3B8; cursor: pointer; transition: 0.2s;" onmouseover="this.style.color='var(--text-main)'">&times;</button>
        
        <h2 style="margin: 0 0 12px; font-size: 1.6rem; font-weight: 950; letter-spacing: -0.8px; color: var(--text-main);">Bulk Arc Ingestion</h2>
        <p style="color: var(--text-dim); margin-bottom: 32px; font-weight: 600;">Import scholar data streams via institutional CSV.</p>

        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 20px; border-radius: 16px; margin-bottom: 32px; font-size: 0.8rem; color: var(--text-dim); line-height: 1.6;">
            <strong style="color: var(--text-main); font-weight: 900; display: block; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;">Data Structure Protocol:</strong>
            1. Name, 2. Email, 3. CourseID <br>
            <span style="font-weight: 900; color: var(--primary);">* Passwords will be auto-generated & sent via mail.</span>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="import_csv" value="1">
            <div style="margin-bottom: 40px;">
                <label style="display: block; font-size: 0.7rem; font-weight: 950; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px;">CSV DATA STREAM</label>
                <div style="position: relative; border: 2px dashed #E2E8F0; border-radius: 16px; padding: 32px; text-align: center; transition: 0.2s;" onmouseover="this.style.borderColor='var(--primary)'; this.style.background='#E0F7FF';" onmouseout="this.style.borderColor='#E2E8F0'; this.style.background='transparent';">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem; color: var(--primary); opacity: 0.4; margin-bottom: 16px;"></i>
                    <p style="margin: 0; font-weight: 700; color: var(--text-main); font-size: 0.9rem;">Drop CSV or Click to Browse</p>
                    <input type="file" name="csv_file" accept=".csv" required style="position: absolute; inset: 0; opacity: 0; cursor: pointer;">
                </div>
            </div>
            <button type="submit" class="btn-premium" style="width: 100%; height: 58px; background: var(--secondary);">EXECUTE BATCH IMPORT</button>
        </form>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        const err = urlParams.get('err');
        
        if (msg) {
            SDAC.showToast(msg, "success");
            window.history.replaceState(null, null, window.location.pathname);
        }
        if (err) {
            SDAC.showToast(err, "error");
            window.history.replaceState(null, null, window.location.pathname);
        }
    });

    // Close modal on backdrop click
    window.addEventListener('click', (e) => {
        const enrollModal = document.getElementById('enrollModal');
        const importModal = document.getElementById('importModal');
        if (e.target === enrollModal) enrollModal.style.display = 'none';
        if (e.target === importModal) importModal.style.display = 'none';
    });
</script>
</body>
</html>
