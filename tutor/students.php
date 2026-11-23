<?php
/**
 * Tutor: learners in my courses. Counts come from td_learner_summary() (same as dashboard/analytics/leaderboard).
 * Also: add a learner by email, CSV import, CSV export.
 */
require_once __DIR__ . '/../includes/tutor_data.php';
requireRole('tutor');
$tutor = currentUser();
$tid = (int)$tutor['id'];

function tp_welcome_email(string $to, string $name, string $course, ?string $tempPass): void {
    require_once __DIR__ . '/../includes/mailer.php';
    $body = '<h2 style="margin:0 0 12px;color:#0f172a">You have been enrolled</h2>'
        . '<p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>'
        . '<p>You have been enrolled in <strong>' . htmlspecialchars($course) . '</strong> at Skope Digital Academy.</p>';
    if ($tempPass !== null) {
        $body .= '<div style="background:#f0f9ff;border:1px solid #bae6fd;padding:18px;border-radius:8px;margin:22px 0;text-align:center">'
            . '<div style="font-size:13px;color:#0284c7;font-weight:700;text-transform:uppercase;margin-bottom:6px">Your temporary password</div>'
            . '<div style="font-family:monospace;font-size:20px;font-weight:700;color:#0284c7;letter-spacing:2px">' . htmlspecialchars($tempPass) . '</div></div>'
            . '<p>Sign in with this email address and the password above. You will be asked to choose your own password the first time.</p>';
    } else {
        $body .= '<p>Sign in with your usual email and password to start.</p>';
    }
    sda_mail($to, 'Welcome to ' . $course . ' – Skope Digital Academy', $body . sda_mail_button(sda_site_url() . '/login', 'Sign in'));
}

/** Enrol one person by email into one of the tutor's courses. Returns [ok(bool), message]. */
function tp_enrol(PDO $pdo, int $tid, string $name, string $email, int $courseId): array {
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Enter a name and a valid email address.'];
    $c = $pdo->prepare("SELECT id, title FROM courses WHERE id = ? AND tutor_id = ?");
    $c->execute([$courseId, $tid]);
    $course = $c->fetch();
    if (!$course) return [false, 'You can only enrol learners into your own courses.'];
    $u = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $u->execute([$email]);
    $existing = $u->fetch();
    $temp = null;
    if ($existing) {
        if ($existing['role'] !== 'student') return [false, "$email belongs to a staff account and cannot be enrolled."];
        $uid = (int)$existing['id'];
    } else {
        $temp = 'Sda-' . random_int(100000, 999999) . '!';
        $pdo->prepare("INSERT INTO users (name, email, password, role, status, requires_password_change) VALUES (?, ?, ?, 'student', 'active', 1)")
            ->execute([$name, $email, password_hash($temp, PASSWORD_DEFAULT)]);
        $uid = (int)$pdo->lastInsertId();
    }
    $chk = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $chk->execute([$uid, $courseId]);
    if ($chk->fetch()) return [false, "$email is already enrolled in {$course['title']}."];
    $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, starts_on) VALUES (?, ?, 'active', CURDATE())")->execute([$uid, $courseId]);
    try { tp_welcome_email($email, $name, $course['title'], $temp); } catch (Throwable $e) { error_log('tutor enrol email: ' . $e->getMessage()); }
    return [true, "$name was enrolled in {$course['title']}" . ($temp ? ' and emailed a temporary password.' : '.')];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        td_flash('err', 'Your session expired. Please try again.');
    } elseif (isset($_POST['enroll_student'])) {
        try {
            [$ok, $msg] = tp_enrol($pdo, $tid, trim((string)$_POST['student_name']), trim((string)$_POST['student_email']), (int)$_POST['course_id']);
            td_flash($ok ? 'ok' : 'err', $msg);
        } catch (Throwable $e) { error_log('tutor enrol: ' . $e->getMessage()); td_flash('err', 'The learner could not be enrolled.'); }
    } elseif (isset($_POST['import_csv'])) {
        $done = $skipped = 0;
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK && ($h = fopen($_FILES['csv_file']['tmp_name'], 'r'))) {
            $row = 0;
            while (($data = fgetcsv($h, 1000, ',')) !== false) {
                if (++$row === 1 || count($data) < 3) continue;
                try {
                    [$ok] = tp_enrol($pdo, $tid, trim((string)$data[0]), trim((string)$data[1]), (int)trim((string)$data[2]));
                    $ok ? $done++ : $skipped++;
                } catch (Throwable $e) { $skipped++; }
            }
            fclose($h);
            td_flash('ok', "Import finished: $done enrolled, $skipped skipped (already enrolled, invalid row or not your course).");
        } else {
            td_flash('err', 'Choose a CSV file to import.');
        }
    }
    td_redirect('students');
}

$search = trim((string)($_GET['search'] ?? ''));
$course_filter = (int)($_GET['course_id'] ?? 0);
$show = ($_GET['show'] ?? '') === 'pending' ? 'pending' : 'learners';

$sum = td_learner_summary($pdo, $tid);
$pageTitle = 'Learners';
$pageSub = $sum['learners'] . ' learner' . ($sum['learners'] === 1 ? '' : 's') . ' in ' . $sum['enrolments'] . ' enrolment' . ($sum['enrolments'] === 1 ? '' : 's')
         . ' (' . $sum['active'] . ' active, ' . $sum['completed'] . ' completed)' . ($sum['pending'] ? ' · ' . $sum['pending'] . ' pending' : '') . '.';
$pageActions = '<button type="button" class="tp-btn primary" data-open="enrollModal"><i class="fas fa-user-plus"></i> Add learner</button>'
             . '<button type="button" class="tp-btn" data-open="importModal"><i class="fas fa-file-csv"></i> Import CSV</button>'
             . '<a class="tp-btn" href="export_students' . ($course_filter ? '?course_filter=' . $course_filter : '') . '"><i class="fas fa-file-arrow-down"></i> Export</a>';
require_once 'includes/header.php';

$tutor_courses = td_course_options($pdo, $tid);
$rows = td_enrolments($pdo, $tid, ['statuses' => $show === 'pending' ? ['pending'] : TD_LEARNER_STATUSES, 'course_id' => $course_filter, 'search' => $search]);
$csrf = csrfToken();
?>
<?php if ($note = td_orphan_note($sum)): ?><div class="tp-alert info"><i class="fas fa-circle-info"></i><div><?= e($note) ?></div></div><?php endif; ?>

<section class="tp-card" style="margin-bottom:18px">
    <div class="tp-card-body">
        <form method="get" action="students" class="tp-filter">
            <input type="hidden" name="show" value="<?= e($show) ?>">
            <div class="tp-field"><label for="s-q">Search</label><div class="tp-search"><i class="fas fa-magnifying-glass"></i><input id="s-q" type="search" name="search" placeholder="Name or email" value="<?= e($search) ?>"></div></div>
            <div class="tp-field"><label for="s-c">Course</label>
                <select id="s-c" name="course_id" onchange="this.form.submit()"><option value="">All courses</option>
                    <?php foreach ($tutor_courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $course_filter === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
                </select></div>
            <button type="submit" class="tp-btn"><i class="fas fa-filter"></i> Apply</button>
            <?php if ($search !== '' || $course_filter): ?><a class="tp-btn ghost" href="students?show=<?= e($show) ?>">Clear</a><?php endif; ?>
        </form>
    </div>
</section>

<nav class="tp-tabs" style="margin-bottom:14px" aria-label="Show">
    <a href="students?<?= e(http_build_query(array_filter(['search' => $search, 'course_id' => $course_filter ?: null]))) ?>" class="<?= $show === 'learners' ? 'active' : '' ?>">Learners (<?= $sum['enrolments'] ?>)</a>
    <a href="students?<?= e(http_build_query(array_filter(['show' => 'pending', 'search' => $search, 'course_id' => $course_filter ?: null]))) ?>" class="<?= $show === 'pending' ? 'active' : '' ?>">Pending (<?= $sum['pending'] ?>)</a>
</nav>

<section class="tp-card">
    <?php if ($rows): ?>
        <div class="tp-table-wrap">
            <table class="tp-table stack">
                <thead><tr><th>Learner</th><th>Course</th><th>Progress</th><th class="num">Quiz average</th><th>Last active</th><th class="actions"><span class="tp-sr">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $s): [$sl, $sc] = td_enrol_status($s['status']); ?>
                    <tr>
                        <td data-label=""><span class="tp-who"><?= td_avatar($s['avatar'], $s['student_name']) ?><span><b><?= e($s['student_name']) ?></b><small><?= e($s['student_email']) ?></small></span></span></td>
                        <td data-label="Course"><?= e($s['course_title']) ?> <span class="tp-pill <?= $sc ?>"><?= $sl ?></span>
                            <div class="tp-note"><?= e(mode_label($s['learning_mode'] ?: 'self_paced')) ?><?= $s['cohort_name'] ? ' · ' . e($s['cohort_name']) : '' ?> · enrolled <?= e(date('j M Y', strtotime($s['enrolled_at']))) ?></div></td>
                        <td data-label="Progress"><div class="tp-progress"><div class="tp-progress-bar"><span style="width:<?= max(0, min(100, (float)$s['progress_percent'])) ?>%"></span></div><b><?= round((float)$s['progress_percent']) ?>%</b></div></td>
                        <td data-label="Quiz average" class="num"><?php if ($s['avg_quiz'] === null): ?><span class="tp-note">No quizzes taken</span><?php else: $q = (float)$s['avg_quiz']; ?><span class="tp-pill <?= $q >= 80 ? 'ok' : ($q >= 60 ? 'warn' : 'bad') ?>"><?= round($q) ?>%</span><?php endif; ?></td>
                        <td data-label="Last active"><?= $s['last_active'] ? e(td_ago($s['last_active'])) : ($s['last_login'] ? 'Signed in ' . e(td_ago($s['last_login'])) : '<span class="tp-note">Not yet</span>') ?></td>
                        <td class="actions">
                            <a class="tp-btn sm" href="student_profile?id=<?= (int)$s['student_id'] ?>"><i class="fas fa-user"></i> Profile</a>
                            <a class="tp-btn sm" href="messages?to=<?= (int)$s['student_id'] ?>"><i class="fas fa-comment"></i> Message</a>
                            <form method="post" action="../impersonate?action=start"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="uid" value="<?= (int)$s['student_id'] ?>"><button type="submit" class="tp-btn sm" title="Open the learner portal as this learner"><i class="fas fa-eye"></i> View as learner</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="tp-empty" style="padding:44px 22px">
            <i class="fas fa-user-graduate"></i>
            <?php if ($search !== '' || $course_filter): ?>
                <h3>No learners match</h3><p>Try another name or email, or show all courses.</p>
                <div class="tp-actions"><a class="tp-btn sm" href="students?show=<?= e($show) ?>">Clear filters</a></div>
            <?php elseif ($show === 'pending'): ?>
                <h3>No pending enrolments</h3><p>Learners who have started enrolling but not finished paying or been approved show here.</p>
            <?php else: ?>
                <h3>No learners yet</h3><p>Learners appear here once they enrol in one of your courses. You can also add someone yourself by email.</p>
                <div class="tp-actions"><button type="button" class="tp-btn sm primary" data-open="enrollModal"><i class="fas fa-user-plus"></i> Add learner</button></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<div class="tp-modal" id="enrollModal" role="dialog" aria-modal="true" aria-labelledby="enT"<?= isset($_GET['enrol']) ? ' data-autoopen' : '' ?>>
    <div class="tp-modal-box">
        <div class="tp-modal-head"><div><h2 id="enT">Add a learner</h2><p>If the email is new, an account is created and a temporary password is emailed.</p></div><button type="button" class="tp-iconbtn" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="tp-modal-body">
            <?php if ($tutor_courses): ?>
            <form method="post" action="students" class="tp-form">
                <input type="hidden" name="enroll_student" value="1"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="tp-field"><label for="en-n">Full name</label><input id="en-n" type="text" name="student_name" required autocomplete="off"></div>
                <div class="tp-field"><label for="en-e">Email</label><input id="en-e" type="email" name="student_email" required autocomplete="off"></div>
                <div class="tp-field"><label for="en-c">Course</label><select id="en-c" name="course_id" required><?php foreach ($tutor_courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
                <div class="tp-modal-foot"><button type="button" class="tp-btn" data-close>Cancel</button><button type="submit" class="tp-btn primary"><i class="fas fa-user-plus"></i> Enrol</button></div>
            </form>
            <?php else: ?><div class="tp-empty"><i class="fas fa-book"></i><h3>Create a course first</h3><div class="tp-actions"><a class="tp-btn sm primary" href="courses?action=new">New course</a></div></div><?php endif; ?>
        </div>
    </div>
</div>

<div class="tp-modal" id="importModal" role="dialog" aria-modal="true" aria-labelledby="imT">
    <div class="tp-modal-box">
        <div class="tp-modal-head"><div><h2 id="imT">Import learners from CSV</h2><p>One learner per row. The first row is treated as a heading.</p></div><button type="button" class="tp-iconbtn" data-close aria-label="Close"><i class="fas fa-xmark"></i></button></div>
        <div class="tp-modal-body">
            <div class="tp-alert info"><i class="fas fa-table"></i><div>Columns: <b>name, email, course ID</b>.<?php if ($tutor_courses): ?> Your course IDs: <?= implode(', ', array_map(fn($c) => e($c['title']) . ' = <b>' . (int)$c['id'] . '</b>', $tutor_courses)) ?>.<?php endif; ?></div></div>
            <form method="post" action="students" enctype="multipart/form-data" class="tp-form">
                <input type="hidden" name="import_csv" value="1"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div class="tp-field"><label for="im-f">CSV file</label><input id="im-f" type="file" name="csv_file" accept=".csv,text/csv" required></div>
                <div class="tp-modal-foot"><button type="button" class="tp-btn" data-close>Cancel</button><button type="submit" class="tp-btn primary"><i class="fas fa-upload"></i> Import</button></div>
            </form>
        </div>
    </div>
</div>
<?php require 'includes/footer.php'; ?>
