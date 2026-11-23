<?php
/**
 * Staff accounts (role 'staff'): each staff member belongs to a department and may use only the admin tools
 * ("modules") granted to them. Admin manages them in admin/staff.php; staff land on admin/staff-home.php.
 * Never grantable: users, settings, payments, mailbox, revenue, payroll – anything that changes roles or money.
 */
require_once __DIR__ . '/learning.php';

/** Grantable modules: key => [label, icon, admin pages, short description]. */
const SDA_STAFF_MODULES = [
    'courses'       => ['Courses', 'book', ['courses.php', 'course_details.php'], 'Review, approve and manage courses'],
    'content'       => ['Content library', 'book-open-reader', ['content.php'], 'Lessons and learning content'],
    'paths'         => ['Learning paths', 'route', ['paths.php'], 'Career paths and course bundles'],
    'categories'    => ['Categories', 'tags', ['categories.php'], 'Course categories'],
    'learning'      => ['Learning settings', 'sliders', ['learning-settings.php'], 'Cohorts, schedules and learning rules'],
    'projects'      => ['Project reviews', 'diagram-project', ['projects.php'], 'Review flagged learner projects'],
    'certificates'  => ['Certificates', 'certificate', ['certificates.php'], 'Issue and check certificates'],
    'badges'        => ['Badges', 'medal', ['badges.php'], 'Learner badges and rewards'],
    'reviews'       => ['Course reviews', 'star', ['reviews.php'], 'Learner ratings and reviews'],
    'leaderboard'   => ['Leaderboard', 'trophy', ['leaderboard.php'], 'Learner rankings'],
    'enrollments'   => ['Enrolments', 'user-check', ['enrollments.php'], 'Who is enrolled in what'],
    'scholarships'  => ['Scholarships', 'graduation-cap', ['scholarships.php'], 'Schemes and learner applications'],
    'tickets'       => ['Support tickets', 'life-ring', ['tickets.php'], 'Learner support requests'],
    'messages'      => ['Contact messages', 'envelope', ['messages.php'], 'Messages from the website contact form'],
    'announcements' => ['Announcements', 'bullhorn', ['announcements.php'], 'News shown to learners'],
    'notifications' => ['Notifications', 'bell', ['notifications.php'], 'Send notifications to users'],
    'coupons'       => ['Coupons', 'ticket', ['coupons.php'], 'Discount codes for campaigns'],
    'email'         => ['Email templates', 'envelope-open-text', ['email-notifications.php'], 'Automatic email wording'],
    'activity'      => ['Activity log', 'clock-rotate-left', ['activity.php'], 'Audit trail of platform actions'],
    'careers'       => ['Careers & recruitment', 'briefcase', ['careers.php', 'career-applications.php', 'career-application.php', 'career-assessment.php', 'career-cv.php'], 'Vacancies, applicants, assessments and interviews'],
];

/** Departments: label, icon, colour, focus line, default modules. */
const SDA_STAFF_DEPTS = [
    'academics'       => ['Academics & Training', 'chalkboard-user', '#0080FF', 'Training programmes, curriculum, instructors and learner assessment.',
                          ['courses', 'content', 'paths', 'categories', 'learning', 'projects', 'certificates', 'badges', 'reviews', 'enrollments', 'announcements']],
    'technology'      => ['Technology & Innovation', 'microchip', '#7c3aed', 'The LMS, digital infrastructure and technical support.',
                          ['tickets', 'activity', 'email', 'content', 'courses', 'notifications', 'announcements']],
    'student_affairs' => ['Student Affairs & Welfare', 'hands-holding-child', '#059669', 'Admissions, learner support, welfare, progress and alumni.',
                          ['enrollments', 'scholarships', 'tickets', 'messages', 'certificates', 'leaderboard', 'announcements', 'notifications']],
    'marketing'       => ['Marketing & Communications', 'bullhorn', '#FF8C00', 'Brand, campaigns, content, social media and public relations.',
                          ['announcements', 'notifications', 'messages', 'reviews', 'coupons', 'email', 'leaderboard']],
    'partnerships'    => ['Partnerships & Resources', 'handshake', '#0891b2', 'Partners, sponsors, fundraising, grants and scholarships.',
                          ['scholarships', 'messages', 'announcements', 'coupons']],
    'general'         => ['General', 'user-tie', '#64748b', 'General staff access.', []],
];

/** Pages every staff member may open. */
const SDA_STAFF_ALWAYS = ['staff-home.php', 'profile.php'];

function sda_staff_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (setting('staff_schema') === '1') return;
    if ($pdo->inTransaction()) { $done = false; return; }
    $type = (string)$pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch()['Type'];
    if (!str_contains($type, "'staff'")) $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','tutor','student','staff') NOT NULL DEFAULT 'student'");
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_members (
        user_id INT PRIMARY KEY,
        department VARCHAR(30) NOT NULL DEFAULT 'general',
        job_title VARCHAR(120) NULL,
        modules TEXT NULL,
        added_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    save_setting($pdo, 'staff_schema', '1');
}

function sda_staff_record(PDO $pdo, int $uid): ?array
{
    sda_staff_ensure($pdo);
    $st = $pdo->prepare("SELECT s.*, u.name, u.email, u.status, u.role, u.last_login, u.avatar FROM staff_members s JOIN users u ON u.id = s.user_id WHERE s.user_id = ?");
    $st->execute([$uid]);
    $r = $st->fetch();
    if (!$r) return null;
    $r['mods'] = array_values(array_intersect(json_decode((string)$r['modules'], true) ?: [], array_keys(SDA_STAFF_MODULES)));
    return $r;
}

/** The modules this signed-in user may use (admins: all). */
function sda_staff_modules(PDO $pdo, int $uid, string $role): array
{
    if ($role === 'admin') return array_keys(SDA_STAFF_MODULES);
    if ($role !== 'staff') return [];
    return sda_staff_record($pdo, $uid)['mods'] ?? [];
}

/** May this user open this admin page? */
function sda_staff_can_page(PDO $pdo, int $uid, string $role, string $page): bool
{
    if ($role === 'admin') return true;
    if ($role !== 'staff') return false;
    if (in_array($page, SDA_STAFF_ALWAYS, true)) return true;
    foreach (sda_staff_modules($pdo, $uid, $role) as $m) if (in_array($page, SDA_STAFF_MODULES[$m][2], true)) return true;
    return false;
}

function sda_staff_clean_modules(array $in): array
{
    return array_values(array_intersect(array_unique(array_map('strval', $in)), array_keys(SDA_STAFF_MODULES)));
}

/** Make a user staff (or update their staff record). */
function sda_staff_save(PDO $pdo, int $uid, string $dept, string $title, array $mods, int $by): void
{
    sda_staff_ensure($pdo);
    $dept = array_key_exists($dept, SDA_STAFF_DEPTS) ? $dept : 'general';
    $pdo->prepare("INSERT INTO staff_members (user_id, department, job_title, modules, added_by) VALUES (?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE department = VALUES(department), job_title = VALUES(job_title), modules = VALUES(modules)")
        ->execute([$uid, $dept, mb_substr(trim($title), 0, 120) ?: null, json_encode(sda_staff_clean_modules($mods)), $by]);
    $pdo->prepare("UPDATE users SET role = 'staff' WHERE id = ? AND role <> 'admin'")->execute([$uid]);
}

/** Email a "set your password" link (valid 72 hours) using the normal password-reset table. */
function sda_staff_invite_email(PDO $pdo, array $u, string $deptLabel, bool $isNew, ?string &$err = null): bool
{
    require_once __DIR__ . '/mailer.php';
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $base = rtrim(sda_mail_public_base(), '/');
    $link = $base . '/login';
    $btn = 'Sign in to the staff portal';
    if ($isNew) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$u['email']]);
        $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")->execute([$u['email'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 72 * 3600)]);
        $link = $base . '/reset-password?token=' . $token;
        $btn = 'Set my password';
    }
    $html = '<p>Hi ' . $e(strtok($u['name'], ' ')) . ',</p>'
        . '<p>' . ($isNew ? 'You\'ve been invited to the <b>Skope Digital Academy staff portal</b>' : 'Your Skope Digital Academy account now has <b>staff access</b>')
        . ' for the <b>' . $e($deptLabel) . '</b> team.</p>'
        . ($isNew ? '<p>First, choose your password. This link works for 72 hours.</p>' : '<p>Sign in with your usual email and password. You\'ll land on your team dashboard.</p>')
        . '<p style="margin:22px 0"><a href="' . $e($link) . '" style="background:#00BFFF;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block">' . $btn . '</a></p>'
        . '<p style="font-size:13px;color:#64748b">For security, each sign-in is confirmed with a code sent to this email address. Never share your password or codes with anyone.</p>';
    return sda_mail($u['email'], $isNew ? 'Your staff account at Skope Digital Academy' : 'You now have staff access – Skope Digital Academy', $html, '', $err, ['event' => 'staff_invite']);
}
