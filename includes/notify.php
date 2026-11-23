<?php
/**
 * Notification engine: one call sends the in-app notification AND the email for an event.
 *
 *   sda_notify('payment_approved', $userId, ['course' => 'Python', 'amount' => 'KES 1,500']);
 *   sda_notify_address('contact_received', 'guest@x.com', ['name' => 'Ann']);   // people without an account
 *   sda_notify_admins('tutor_applied', ['name' => ..., 'email' => ...]);
 *
 * - Templates: defaults in SDA_NOTIFY_EVENTS; admins can switch events off and edit subject/body in
 *   Admin > Email Notifications (table notification_templates). {{placeholders}} are filled from $vars.
 * - Learners choose which categories they get by email (table notification_prefs; one-click unsubscribe
 *   link in every non-essential email). Essential account/security emails always go.
 * - Emails are queued (table mail_queue) and sent by a background worker (cron.php), so pages stay fast.
 *   cron.php also sends schedule reminders (lessons opening, assignments due, access expiring...).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';
require_once __DIR__ . '/mailer.php';

/*
 * key => [category, label, who, subject, body HTML, button label, button path (relative to site), essential]
 * Categories: account, payments, applications, schedule, progress, community, admin
 */
const SDA_NOTIFY_EVENTS = [
    // ── account ──
    'welcome' => ['account', 'Welcome / account created', 'learner',
        'Welcome to Skope Digital Academy, {{first_name}}!',
        '<p>Hi {{first_name}},</p><p>Your account is ready. Start with a free tutorial or pick a course: every lesson earns XP, and you can learn self-paced or join a weekly class.</p>',
        'Start learning', 'learn', true],
    'account_activated' => ['account', 'Account activated', 'learner',
        'Your account is active',
        '<p>Hi {{first_name}},</p><p>Your Skope Digital Academy account is now active. You have full access to enrol in courses and earn certificates.</p>',
        'Go to my dashboard', 'student/', true],
    'password_changed' => ['account', 'Password changed', 'everyone',
        'Your password was changed',
        '<p>Hi {{first_name}},</p><p>The password on your account was changed on {{date}}. If this was you, nothing else is needed.</p><p><strong>Not you?</strong> Reset your password right away and contact support.</p>',
        'Reset password', 'forgot-password', true],
    'account_created_by_admin' => ['account', 'Account created by staff', 'everyone',
        'Your Skope Digital Academy account',
        '<p>Hi {{first_name}},</p><p>An account was created for you as <strong>{{role}}</strong>.</p><p>Sign in with this email and the temporary password <strong style="font-family:monospace">{{temp_password}}</strong>. You will be asked to choose your own password.</p>',
        'Sign in', 'login', true],
    'password_reset_by_admin' => ['account', 'Password reset by staff', 'everyone',
        'Your password was reset',
        '<p>Hi {{first_name}},</p><p>A staff member reset your password. Sign in with the temporary password <strong style="font-family:monospace">{{temp_password}}</strong>; you will then choose a new one.</p>',
        'Sign in', 'login', true],
    'account_suspended' => ['account', 'Account suspended', 'everyone',
        'Your account has been suspended',
        '<p>Hi {{first_name}},</p><p>Your Skope Digital Academy account has been suspended. If you think this is a mistake, reply to this email or contact support.</p>',
        '', '', true],
    'account_reactivated' => ['account', 'Account reactivated', 'everyone',
        'Your account is active again',
        '<p>Hi {{first_name}},</p><p>Good news: your account has been reactivated. You can sign in and continue learning.</p>',
        'Sign in', 'login', true],

    // ── payments & subscriptions ──
    'payment_received' => ['payments', 'Payment proof received (awaiting review)', 'learner',
        'We received your payment for {{item}}',
        '<p>Hi {{first_name}},</p><p>Thanks! We received your payment of <strong>{{amount}}</strong> for <strong>{{item}}</strong>. Our team will confirm it shortly and you will get another email when it is approved.</p>',
        'View my payments', 'student/payments', false],
    'payment_approved' => ['payments', 'Payment confirmed / receipt', 'learner',
        'Payment confirmed: {{item}}',
        '<p>Hi {{first_name}},</p><p>Your payment has been confirmed.</p><table style="width:100%;border-collapse:collapse;font-size:14px"><tr><td style="padding:6px 0;color:#64748b">Item</td><td style="text-align:right"><strong>{{item}}</strong></td></tr><tr><td style="padding:6px 0;color:#64748b">Amount</td><td style="text-align:right"><strong>{{amount}}</strong></td></tr><tr><td style="padding:6px 0;color:#64748b">Reference</td><td style="text-align:right">{{reference}}</td></tr><tr><td style="padding:6px 0;color:#64748b">Date</td><td style="text-align:right">{{date}}</td></tr></table>',
        'View my payments', 'student/payments', false],
    'payment_rejected' => ['payments', 'Payment not approved', 'learner',
        'We could not confirm your payment for {{item}}',
        '<p>Hi {{first_name}},</p><p>We could not confirm your payment for <strong>{{item}}</strong>.</p><p><strong>Reason:</strong> {{reason}}</p><p>Please check the details and try again, or reply to this email for help.</p>',
        'Try again', 'student/payments', false],
    'enrollment_confirmed' => ['payments', 'Course subscription / enrolment confirmed', 'learner',
        'You are enrolled in {{course}}',
        '<p>Hi {{first_name}},</p><p>You are now enrolled in <strong>{{course}}</strong> ({{mode}}).</p><p>{{schedule_note}}</p><p>{{access_note}}</p>',
        'Open the course', 'student/classroom?id={{course_id}}', false],
    'access_expiring' => ['payments', 'Course access ending soon', 'learner',
        'Your access to {{course}} ends {{when}}',
        '<p>Hi {{first_name}},</p><p>Your access to <strong>{{course}}</strong> ends on <strong>{{expires}}</strong>. You are {{progress}}% through. Finish your remaining lessons, or renew to keep learning.</p>',
        'Continue the course', 'student/classroom?id={{course_id}}', false],
    'certificate_ready' => ['payments', 'Certificate earned (fee to unlock)', 'learner',
        'You earned a certificate: {{title}}',
        '<p>Congratulations {{first_name}}!</p><p>You completed <strong>{{title}}</strong>. Your certificate is ready; pay the certificate fee of <strong>{{fee}}</strong> to unlock and download it.</p>',
        'Unlock my certificate', 'certificate-pay?id={{certificate_id}}', false],
    'certificate_unlocked' => ['progress', 'Certificate issued / unlocked', 'learner',
        'Your certificate for {{title}} is ready',
        '<p>Congratulations {{first_name}}!</p><p>Your certificate for <strong>{{title}}</strong> is ready to download and share.</p><p>Verification code: <strong>{{code}}</strong></p>',
        'View certificate', 'certificate?code={{code}}', false],

    // ── applications & support ──
    'tutor_application_received' => ['applications', 'Tutor application received', 'everyone',
        'We received your application to teach',
        '<p>Hi {{first_name}},</p><p>Thanks for applying to teach at Skope Digital Academy. Our team reviews every application and you will get an email as soon as a decision is made.</p>',
        '', '', true],
    'tutor_approved' => ['applications', 'Tutor application approved', 'everyone',
        'You are approved to teach at Skope Digital Academy',
        '<p>Hi {{first_name}},</p><p>Welcome aboard! Your tutor account is approved. Sign in to set up your profile and create your first course.</p>',
        'Sign in', 'login', true],
    'scholarship_received' => ['applications', 'Scholarship application received', 'learner',
        'Application received: {{scholarship}}',
        '<p>Hi {{first_name}},</p><p>We received your application for <strong>{{scholarship}}</strong>. You will hear from us once the review is complete.</p>',
        'View scholarships', 'scholarships', false],
    'scholarship_decision' => ['applications', 'Scholarship decision', 'learner',
        'Scholarship update: {{scholarship}}',
        '<p>Hi {{first_name}},</p><p>Your application for <strong>{{scholarship}}</strong> has been <strong>{{status}}</strong>.</p><p>{{note}}</p>',
        'View my applications', 'scholarships', false],
    'contact_received' => ['applications', 'Contact form auto-reply', 'guest',
        'We received your message',
        '<p>Hi {{first_name}},</p><p>Thanks for contacting Skope Digital Academy about "<strong>{{subject}}</strong>". Our team will reply within one working day.</p>',
        '', '', true],
    'ticket_reply' => ['applications', 'Support ticket reply', 'everyone',
        'Re: {{subject}} [Ticket #{{ticket_id}}]',
        '<p>Hi {{first_name}},</p><p>Our support team replied to your ticket:</p><blockquote style="border-left:3px solid #00BFFF;margin:12px 0;padding:6px 14px;color:#334155">{{reply}}</blockquote><p>Status: <strong>{{status}}</strong></p>',
        'View ticket', 'student/support', true],

    // ── class schedule (sent by cron.php) ──
    'cohort_starting' => ['schedule', 'Class (cohort) starting soon', 'learner',
        '{{course}} starts {{when}}',
        '<p>Hi {{first_name}},</p><p>Your class <strong>{{course}}</strong> ({{cohort}}) starts on <strong>{{start}}</strong>. New lessons open every week; the invite in this email adds the dates to your calendar.</p>',
        'Open the course', 'student/classroom?id={{course_id}}', false],
    'lessons_opening' => ['schedule', 'New lessons open (weekly class)', 'learner',
        'New lessons are open in {{course}}',
        '<p>Hi {{first_name}},</p><p>This week\'s lessons in <strong>{{course}}</strong> are now open:</p>{{lessons}}<p>Due by <strong>{{due}}</strong>.</p>',
        'Start this week', 'student/classroom?id={{course_id}}', false],
    'assignment_due' => ['schedule', 'Assignment due reminder', 'learner',
        'Due {{when}}: {{assignment}}',
        '<p>Hi {{first_name}},</p><p>Reminder: <strong>{{assignment}}</strong> in {{course}} is due <strong>{{due}}</strong>.</p>',
        'Submit now', 'student/assignments', false],
    'new_assignment' => ['schedule', 'New assignment posted', 'learner',
        'New assignment in {{course}}: {{assignment}}',
        '<p>Hi {{first_name}},</p><p>A new assignment was posted in <strong>{{course}}</strong>: <strong>{{assignment}}</strong>.</p><p>Due: <strong>{{due}}</strong></p>',
        'View assignment', 'student/assignments', false],
    'weekly_digest' => ['schedule', 'Weekly learning summary', 'learner',
        'Your week at Skope: {{xp}} XP, {{lessons_done}} lessons',
        '<p>Hi {{first_name}},</p><p>Last week you completed <strong>{{lessons_done}}</strong> lessons and earned <strong>{{xp}} XP</strong>. Current streak: <strong>{{streak}}</strong> days.</p><p><strong>Coming up this week:</strong></p>{{upcoming}}',
        'Keep learning', 'student/', false],
    'inactive_nudge' => ['schedule', 'We miss you (7 days inactive)', 'learner',
        'Pick up where you left off in {{course}}',
        '<p>Hi {{first_name}},</p><p>It has been a week since your last lesson in <strong>{{course}}</strong> ({{progress}}% done). Ten minutes today keeps your streak alive.</p>',
        'Continue', 'student/classroom?id={{course_id}}', false],

    // ── progress ──
    'assignment_graded' => ['progress', 'Assignment graded', 'learner',
        'Your assignment was graded: {{score}}',
        '<p>Hi {{first_name}},</p><p>Your submission for <strong>{{assignment}}</strong> was graded: <strong>{{score}}</strong>.</p><p>{{feedback}}</p>',
        'See feedback', 'student/assignments', false],
    'course_completed' => ['progress', 'Course completed', 'learner',
        'You completed {{course}}!',
        '<p>Well done {{first_name}}!</p><p>You completed <strong>{{course}}</strong>. Keep the momentum: the next course in your path is waiting.</p>',
        'Browse courses', 'courses', false],
    'badge_earned' => ['progress', 'Badge earned', 'learner',
        'New badge: {{badge}}',
        '<p>Nice work {{first_name}}! You earned the <strong>{{badge}}</strong> badge.</p>',
        'See my achievements', 'student/achievements', false],

    // ── community ──
    'announcement' => ['community', 'New announcement', 'learner',
        '{{title}}',
        '<p>Hi {{first_name}},</p>{{content}}',
        'Read on the portal', 'student/announcements', false],
    'broadcast' => ['community', 'Message from the academy (bulk email)', 'everyone',
        '{{subject}}',
        '{{content}}',
        '', '', false],
    'direct_message' => ['community', 'New private message', 'everyone',
        'New message from {{sender}}',
        '<p>Hi {{first_name}},</p><p><strong>{{sender}}</strong> sent you a message:</p><blockquote style="border-left:3px solid #00BFFF;margin:12px 0;padding:6px 14px;color:#334155">{{excerpt}}</blockquote>',
        'Reply', 'student/messages', false],

    // ── to admins ──
    'admin_new_payment' => ['admin', 'Admin: payment waiting for approval', 'admin',
        'Payment to approve: {{amount}} from {{name}}',
        '<p>{{name}} ({{email}}) submitted a payment of <strong>{{amount}}</strong> for <strong>{{item}}</strong>.</p>',
        'Review payments', 'admin/verifications', false],
    'admin_new_tutor' => ['admin', 'Admin: new tutor application', 'admin',
        'New tutor application: {{name}}',
        '<p><strong>{{name}}</strong> ({{email}}) applied to teach.</p>',
        'Review users', 'admin/users?status=pending', false],
    'admin_new_scholarship' => ['admin', 'Admin: new scholarship application', 'admin',
        'Scholarship application: {{name}}',
        '<p><strong>{{name}}</strong> applied for <strong>{{scholarship}}</strong>.</p>',
        'Review applications', 'admin/scholarships', false],
    'admin_new_contact' => ['admin', 'Admin: contact form / new ticket', 'admin',
        'New message: {{subject}}',
        '<p><strong>{{name}}</strong> ({{email}}) wrote:</p><blockquote style="border-left:3px solid #00BFFF;margin:12px 0;padding:6px 14px">{{message}}</blockquote>',
        'Open the inbox', 'admin/messages', false],
];

const SDA_NOTIFY_CATEGORIES = [
    'account' => ['Account & security', 'Always sent: sign-in, password and account status.'],
    'payments' => ['Payments & subscriptions', 'Receipts, enrolments, access ending, certificates to unlock.'],
    'applications' => ['Applications & support', 'Scholarships, tutor applications, support replies.'],
    'schedule' => ['Class schedule & reminders', 'Weekly lessons, assignments due, class start dates, weekly summary.'],
    'progress' => ['Progress & achievements', 'Grades, certificates, badges, course completion.'],
    'community' => ['Announcements & messages', 'Academy news, private messages, newsletters.'],
    'admin' => ['Staff alerts', 'Sent to administrators.'],
];

/* ── tables ─────────────────────────────────────────────────────────────── */

function sda_notify_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    // DDL implicitly COMMITs an open transaction (payment approval calls us mid-transaction), so the
    // tables are created once and remembered in a setting; never run CREATE/ALTER on every call.
    if (sda_setting_fresh('notify_schema') === '1') return;
    if ($pdo->inTransaction()) { $done = false; return; }   // try again outside the transaction
    $pdo->exec("CREATE TABLE IF NOT EXISTS mail_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event VARCHAR(60) NOT NULL, user_id INT NULL, recipient VARCHAR(191) NOT NULL,
        subject VARCHAR(255) NOT NULL, html MEDIUMTEXT NOT NULL, ics MEDIUMTEXT NULL, reply_to VARCHAR(191) NULL,
        unsubscribe VARCHAR(500) NULL, dedupe_key VARCHAR(191) NULL,
        status ENUM('queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'queued',
        attempts INT NOT NULL DEFAULT 0, error VARCHAR(500) NULL,
        send_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, sent_at DATETIME NULL,
        UNIQUE KEY uq_dedupe (dedupe_key), KEY idx_status (status, send_after), KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_templates (
        event VARCHAR(60) PRIMARY KEY, enabled TINYINT(1) NOT NULL DEFAULT 1, inapp TINYINT(1) NOT NULL DEFAULT 1,
        subject VARCHAR(255) NULL, body MEDIUMTEXT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_prefs (
        user_id INT NOT NULL, category VARCHAR(30) NOT NULL, email_on TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (user_id, category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    save_setting($pdo, 'notify_schema', '1');
}

/* ── templates ──────────────────────────────────────────────────────────── */

/** Effective template for an event: defaults merged with the admin's edits. */
function sda_notify_template(string $event): ?array
{
    global $pdo;
    static $rows = null;
    if (!isset(SDA_NOTIFY_EVENTS[$event])) return null;
    sda_notify_ensure($pdo);
    if ($rows === null) {
        $rows = [];
        foreach ($pdo->query("SELECT * FROM notification_templates")->fetchAll() as $r) $rows[$r['event']] = $r;
    }
    [$cat, $label, $who, $subject, $body, $btn, $path, $essential] = SDA_NOTIFY_EVENTS[$event];
    $o = $rows[$event] ?? [];
    return [
        'event' => $event, 'category' => $cat, 'label' => $label, 'who' => $who, 'essential' => $essential,
        'enabled' => !isset($o['enabled']) || (bool)$o['enabled'],
        'inapp' => !isset($o['inapp']) || (bool)$o['inapp'],
        'subject' => ($o['subject'] ?? '') !== '' ? $o['subject'] : $subject,
        'body' => ($o['body'] ?? '') !== '' ? $o['body'] : $body,
        'default_subject' => $subject, 'default_body' => $body,
        'button' => $btn, 'path' => $path, 'customised' => ($o['subject'] ?? '') !== '' || ($o['body'] ?? '') !== '',
    ];
}

/** Fill {{placeholders}}. Values are HTML-escaped unless the key ends in _html or is a known HTML block. */
function sda_notify_fill(string $tpl, array $vars, bool $html = true): string
{
    $raw = ['lessons', 'upcoming', 'content', 'feedback_html'];
    return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars, $html, $raw) {
        $k = strtolower($m[1]);
        if (!array_key_exists($k, $vars)) return '';
        $v = (string)$vars[$k];
        if (!$html || in_array($k, $raw, true) || str_ends_with($k, '_html')) return $v;
        return nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'));
    }, $tpl);
}

/** Render subject + inner HTML (with button) for an event. */
function sda_notify_render(string $event, array $vars): ?array
{
    $t = sda_notify_template($event);
    if (!$t) return null;
    $vars += ['date' => date('j M Y, H:i'), 'academy' => setting('academy_name') ?: 'Skope Digital Academy'];
    if (!isset($vars['first_name']) && isset($vars['name'])) $vars['first_name'] = strtok(trim((string)$vars['name']), ' ') ?: (string)$vars['name'];
    $vars += ['first_name' => 'there'];
    $subject = trim(preg_replace('/\s+/', ' ', strip_tags(sda_notify_fill($t['subject'], $vars, false))));
    $html = sda_notify_fill($t['body'], $vars);
    if ($t['button'] !== '' && $t['path'] !== '') {
        $html .= sda_mail_button(rtrim(sda_site_url(), '/') . '/' . ltrim(sda_notify_fill($t['path'], array_map('rawurlencode', array_map('strval', $vars)), false), '/'), $t['button']);
    }
    return ['subject' => $subject, 'html' => $html, 'template' => $t, 'vars' => $vars];
}

/* ── preferences & unsubscribe ──────────────────────────────────────────── */

function sda_pref_token(int $uid): string
{
    return substr(hash_hmac('sha256', 'prefs:' . $uid, sda_app_key()), 0, 24);
}

function sda_prefs_url(int $uid): string
{
    return rtrim(sda_site_url(), '/') . '/email-preferences?u=' . $uid . '&t=' . sda_pref_token($uid);
}

function sda_email_allowed(int $uid, string $category): bool
{
    global $pdo;
    sda_notify_ensure($pdo);
    $s = $pdo->prepare("SELECT email_on FROM notification_prefs WHERE user_id = ? AND category = ?");
    $s->execute([$uid, $category]);
    $v = $s->fetchColumn();
    return $v === false || (int)$v === 1;
}

/* ── sending ────────────────────────────────────────────────────────────── */

/**
 * In-app notification + email to one user. Returns true when an email was queued.
 * $opt: inapp (bool, default per template), title/message (in-app text override), ics, dedupe (string key),
 *       send_after (datetime string), reply_to
 */
function sda_notify(string $event, int $uid, array $vars = [], array $opt = []): bool
{
    global $pdo;
    try {
        $t = sda_notify_template($event);
        if (!$t) return false;
        $u = $pdo->prepare("SELECT id, name, email, role, status FROM users WHERE id = ?");
        $u->execute([$uid]);
        $user = $u->fetch();
        if (!$user) return false;
        $vars += ['name' => $user['name'], 'email' => $user['email'], 'role' => $user['role']];
        $r = sda_notify_render($event, $vars);

        // In-app bell (learners and tutors see notifications targeted at them)
        if (($opt['inapp'] ?? $t['inapp']) && $t['category'] !== 'admin') {
            $title = $opt['title'] ?? $r['subject'];
            $msg = $opt['message'] ?? mb_substr(sda_mail_text($r['html']), 0, 400);
            $pdo->prepare("INSERT INTO notifications (title, message, user_role, target_user_id) VALUES (?, ?, ?, ?)")
                ->execute([mb_substr($title, 0, 255), $msg, in_array($user['role'], ['student', 'tutor', 'admin'], true) ? $user['role'] : 'student', $uid]);
        }

        if (!$t['enabled'] || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) return false;
        if (!$t['essential'] && !sda_email_allowed($uid, $t['category'])) return false;
        return sda_mail_enqueue($event, $user['email'], $r['subject'], $r['html'], [
            'user_id' => $uid, 'ics' => $opt['ics'] ?? '', 'dedupe' => $opt['dedupe'] ?? null,
            'send_after' => $opt['send_after'] ?? null, 'reply_to' => $opt['reply_to'] ?? '',
            'unsubscribe' => $t['essential'] ? '' : sda_prefs_url($uid),
        ]);
    } catch (Throwable $e) {
        error_log('[sda notify] ' . $event . ': ' . $e->getMessage());
        return false;
    }
}

/** Email to an address with no account (guests, contact form). */
function sda_notify_address(string $event, string $email, array $vars = [], array $opt = []): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $t = sda_notify_template($event);
    if (!$t || !$t['enabled']) return false;
    $r = sda_notify_render($event, $vars + ['email' => $email]);
    return sda_mail_enqueue($event, $email, $r['subject'], $r['html'], ['reply_to' => $opt['reply_to'] ?? '', 'dedupe' => $opt['dedupe'] ?? null]);
}

/** Email every active admin (and the support address) about something that needs attention. */
function sda_notify_admins(string $event, array $vars = [], array $opt = []): void
{
    global $pdo;
    try {
        $t = sda_notify_template($event);
        if (!$t || !$t['enabled']) return;
        $to = array_column($pdo->query("SELECT email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(), 'email');
        $support = setting('support_email');
        if ($support) $to[] = $support;
        $r = sda_notify_render($event, $vars + ['first_name' => 'team']);
        foreach (array_unique(array_map('strtolower', array_filter($to, fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL)))) as $addr) {
            // Skip the academy's own mailbox when it is the sender (Gmail would just file it in Sent)
            sda_mail_enqueue($event, $addr, $r['subject'], $r['html'], ['reply_to' => $opt['reply_to'] ?? ($vars['email'] ?? '')]);
        }
    } catch (Throwable $e) { error_log('[sda notify admins] ' . $e->getMessage()); }
}

/** Put an email in the queue (deduplicated by key) and make sure a worker will send it soon. */
function sda_mail_enqueue(string $event, string $to, string $subject, string $html, array $o = []): bool
{
    global $pdo;
    sda_notify_ensure($pdo);
    try {
        $pdo->prepare("INSERT INTO mail_queue (event, user_id, recipient, subject, html, ics, reply_to, unsubscribe, dedupe_key, send_after)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, COALESCE(?, NOW()))")
            ->execute([$event, $o['user_id'] ?? null, $to, mb_substr($subject, 0, 255), $html, ($o['ics'] ?? '') ?: null,
                       ($o['reply_to'] ?? '') ?: null, ($o['unsubscribe'] ?? '') ?: null, $o['dedupe'] ?? null, $o['send_after'] ?? null]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return false;   // already sent for this dedupe key
        throw $e;
    }
    sda_queue_kick();
    return true;
}

/** After the page is done, start a background worker to send queued email (at most once per request). */
function sda_queue_kick(): void
{
    static $kicked = false;
    if ($kicked || PHP_SAPI === 'cli') return;
    $kicked = true;
    register_shutdown_function('sda_spawn_worker');
}

function sda_spawn_worker(): void
{
    $script = realpath(__DIR__ . '/../cron.php');
    $php = PHP_OS_FAMILY === 'Windows' ? (is_file(PHP_BINDIR . '\\php.exe') ? PHP_BINDIR . '\\php.exe' : 'C:\\xampp\\php\\php.exe') : (PHP_BINDIR . '/php');
    $spawned = false;
    if ($script && function_exists('popen')) {
        if (PHP_OS_FAMILY === 'Windows') {
            $h = @popen('start "" /B "' . $php . '" "' . $script . '" queue', 'r');
            if ($h) { pclose($h); $spawned = true; }
        } elseif (function_exists('exec') && is_executable($php)) {
            @exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' queue > /dev/null 2>&1 &', $o, $code);
            $spawned = $code === 0;
        }
    }
    if (!$spawned) {
        // No background processes on this host: send a few now (the visitor already has the page)
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        sda_mail_queue_process(5);
    }
}

/** Send queued emails. Returns [sent, failed]. Safe to run from several workers at once. */
function sda_mail_queue_process(int $max = 25): array
{
    global $pdo;
    sda_notify_ensure($pdo);
    $sent = $failed = 0;
    // Recover rows stuck in 'sending' (worker died)
    $pdo->exec("UPDATE mail_queue SET status = 'queued' WHERE status = 'sending' AND send_after < NOW() - INTERVAL 15 MINUTE");
    for ($i = 0; $i < $max; $i++) {
        $row = $pdo->query("SELECT * FROM mail_queue WHERE status = 'queued' AND send_after <= NOW() ORDER BY id LIMIT 1")->fetch();
        if (!$row) break;
        $claim = $pdo->prepare("UPDATE mail_queue SET status = 'sending', attempts = attempts + 1, send_after = NOW() WHERE id = ? AND status = 'queued'");
        $claim->execute([$row['id']]);
        if ($claim->rowCount() !== 1) continue;   // another worker took it
        $err = '';
        $ok = sda_mail($row['recipient'], $row['subject'], $row['html'], (string)$row['reply_to'], $err, [
            'ics' => (string)$row['ics'], 'event' => $row['event'], 'user_id' => $row['user_id'], 'unsubscribe' => (string)$row['unsubscribe'],
        ]);
        if ($ok) {
            $pdo->prepare("UPDATE mail_queue SET status = 'sent', sent_at = NOW(), error = NULL WHERE id = ?")->execute([$row['id']]);
            $sent++;
        } else {
            $attempts = (int)$row['attempts'] + 1;
            // Back off: 5 min, 30 min, 2 h, then give up (visible in the admin queue for a manual retry)
            $delay = [1 => 5, 2 => 30, 3 => 120][$attempts] ?? null;
            if ($delay && !str_contains($err, 'Invalid recipient')) {
                $pdo->prepare("UPDATE mail_queue SET status = 'queued', error = ?, send_after = NOW() + INTERVAL $delay MINUTE WHERE id = ?")->execute([mb_substr($err, 0, 500), $row['id']]);
            } else {
                $pdo->prepare("UPDATE mail_queue SET status = 'failed', error = ? WHERE id = ?")->execute([mb_substr($err, 0, 500), $row['id']]);
            }
            $failed++;
            if (str_contains($err, 'not set up') || str_contains($err, 'not connected') || str_contains($err, 'sign-in')) break; // mailbox down: stop for now
        }
    }
    return [$sent, $failed];
}

/* ── calendar (.ics) ────────────────────────────────────────────────────── */

function sda_ics_escape(string $s): string
{
    return str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\\;", "\\,", "\\n", "\\n"], $s);
}

/**
 * Build a VCALENDAR. $events: [uid, title, start (ts), end (ts|null), all_day (bool), description, url]
 */
function sda_ics(array $events, string $name = 'Skope Digital Academy'): string
{
    $fold = fn(string $l) => rtrim(chunk_split($l, 73, "\r\n "), "\r\n ");
    $out = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Skope Digital Academy//Schedule//EN", "CALSCALE:GREGORIAN", "METHOD:PUBLISH",
            $fold('X-WR-CALNAME:' . sda_ics_escape($name)), "X-WR-TIMEZONE:Africa/Nairobi"];
    foreach ($events as $e) {
        $out[] = 'BEGIN:VEVENT';
        $out[] = 'UID:' . $e['uid'] . '@skopedigital.co.ke';
        $out[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        if (!empty($e['all_day'])) {
            $out[] = 'DTSTART;VALUE=DATE:' . date('Ymd', $e['start']);
            $out[] = 'DTEND;VALUE=DATE:' . date('Ymd', ($e['end'] ?? $e['start']) + 86400);
        } else {
            $out[] = 'DTSTART:' . gmdate('Ymd\THis\Z', $e['start']);
            $out[] = 'DTEND:' . gmdate('Ymd\THis\Z', $e['end'] ?? ($e['start'] + 3600));
        }
        $out[] = $fold('SUMMARY:' . sda_ics_escape($e['title']));
        if (!empty($e['description'])) $out[] = $fold('DESCRIPTION:' . sda_ics_escape($e['description']));
        if (!empty($e['url'])) $out[] = $fold('URL:' . $e['url']);
        if (!empty($e['alarm'])) { $out[] = 'BEGIN:VALARM'; $out[] = 'ACTION:DISPLAY'; $out[] = 'DESCRIPTION:Reminder'; $out[] = 'TRIGGER:-PT' . (int)$e['alarm'] . 'M'; $out[] = 'END:VALARM'; }
        $out[] = 'END:VEVENT';
    }
    $out[] = 'END:VCALENDAR';
    return implode("\r\n", $out) . "\r\n";
}

function sda_calendar_token(int $uid): string
{
    return substr(hash_hmac('sha256', 'cal:' . $uid, sda_app_key()), 0, 24);
}

/** Private calendar feed URL (subscribe in Google Calendar / Outlook / phone). */
function sda_calendar_url(int $uid, bool $webcal = false): string
{
    $u = rtrim(sda_site_url(), '/') . '/calendar-feed?u=' . $uid . '&t=' . sda_calendar_token($uid);
    return $webcal ? preg_replace('#^https?://#', 'webcal://', $u) : $u;
}

/** "Add to Google Calendar" subscribe link for the feed. */
function sda_google_calendar_link(int $uid): string
{
    return 'https://calendar.google.com/calendar/render?cid=' . rawurlencode(sda_calendar_url($uid, true));
}

/**
 * Everything on a learner's schedule between $from and $to (timestamps), the same data the student
 * portal calendar shows plus lesson releases/dues of structured tracks and course access expiry.
 * Each item: uid, type, title, start, end, all_day, description, url, course_id, course
 */
function sda_student_schedule(PDO $pdo, int $uid, int $from, int $to): array
{
    $site = rtrim(sda_site_url(), '/');
    $items = [];
    $enr = $pdo->prepare("SELECT e.*, c.title AS course_title, c.id AS cid, cc.name AS cohort_name, cc.start_date AS cohort_start, cc.end_date AS cohort_end
                          FROM enrollments e JOIN courses c ON c.id = e.course_id LEFT JOIN course_cohorts cc ON cc.id = e.cohort_id
                          WHERE e.student_id = ? AND e.status IN ('active','completed')");
    $enr->execute([$uid]);
    foreach ($enr->fetchAll() as $e) {
        $cid = (int)$e['cid'];
        $curl = "$site/student/classroom?id=$cid";
        if ($e['learning_mode'] === 'structured' && $e['status'] === 'active') {
            $lessons = apply_lesson_schedule(load_course_lessons($pdo, $cid, $uid), $e);
            $weeks = [];
            foreach ($lessons as $l) {
                if (empty($l['opens_at'])) continue;
                $wk = (int)($l['week_num'] ?? 0);
                $weeks[$wk] ??= ['opens' => $l['opens_at'], 'due' => $l['due_at'] ?? null, 'titles' => []];
                $weeks[$wk]['opens'] = min($weeks[$wk]['opens'], $l['opens_at']);
                $weeks[$wk]['titles'][] = $l['title'];
            }
            foreach ($weeks as $wk => $w) {
                if ($w['opens'] >= $from && $w['opens'] <= $to) {
                    $items[] = ['uid' => "wk-$cid-$wk-$uid", 'type' => 'lessons', 'title' => "{$e['course_title']}: week $wk lessons open",
                        'start' => $w['opens'], 'end' => null, 'all_day' => true, 'description' => implode("\n", $w['titles']), 'url' => $curl,
                        'course_id' => $cid, 'course' => $e['course_title'], 'week' => $wk, 'lessons' => $w['titles'], 'due' => $w['due']];
                }
                if (!empty($w['due']) && $w['due'] >= $from && $w['due'] <= $to) {
                    $items[] = ['uid' => "wkdue-$cid-$wk-$uid", 'type' => 'lessons_due', 'title' => "{$e['course_title']}: week $wk due",
                        'start' => $w['due'], 'end' => null, 'all_day' => true, 'description' => 'Finish this week\'s lessons.', 'url' => $curl,
                        'course_id' => $cid, 'course' => $e['course_title']];
                }
            }
        }
        if (!empty($e['cohort_start'])) {
            $ts = strtotime($e['cohort_start']);
            if ($ts >= $from && $ts <= $to) $items[] = ['uid' => "cohort-{$e['cohort_id']}-$uid", 'type' => 'cohort_start', 'title' => "{$e['course_title']} class starts ({$e['cohort_name']})",
                'start' => $ts, 'end' => null, 'all_day' => true, 'description' => 'Your structured class begins.', 'url' => $curl, 'course_id' => $cid, 'course' => $e['course_title'], 'cohort' => $e['cohort_name']];
        }
        if (!empty($e['expires_at']) && $e['status'] === 'active') {
            $ts = strtotime($e['expires_at']);
            if ($ts >= $from && $ts <= $to) $items[] = ['uid' => "exp-{$e['id']}", 'type' => 'access_ends', 'title' => "Access to {$e['course_title']} ends",
                'start' => $ts, 'end' => null, 'all_day' => true, 'description' => 'Finish or renew before this date.', 'url' => $curl, 'course_id' => $cid, 'course' => $e['course_title']];
        }
    }
    // Assignments in enrolled courses that are not submitted yet
    $a = $pdo->prepare("SELECT a.id, a.title, a.due_date, c.title AS course_title, c.id AS cid FROM assignments a
                        JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = ? AND e.status = 'active'
                        JOIN courses c ON c.id = a.course_id
                        WHERE a.due_date IS NOT NULL AND a.due_date BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)
                          AND NOT EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ?)");
    $a->execute([$uid, $from, $to, $uid]);
    foreach ($a->fetchAll() as $r) {
        $ts = strtotime($r['due_date']);
        $items[] = ['uid' => "asg-{$r['id']}-$uid", 'type' => 'assignment', 'title' => "Due: {$r['title']} ({$r['course_title']})",
            'start' => $ts, 'end' => $ts + 1800, 'all_day' => false, 'description' => 'Assignment deadline.', 'url' => "$site/student/assignments",
            'course_id' => (int)$r['cid'], 'course' => $r['course_title'], 'assignment' => $r['title'], 'alarm' => 1440];
    }
    // Reminders the learner added on the portal calendar, plus academy-wide events
    $c = $pdo->prepare("SELECT * FROM calendar_events WHERE (student_id = ? OR target_role IN ('student','all')) AND event_date BETWEEN FROM_UNIXTIME(?) AND FROM_UNIXTIME(?)");
    $c->execute([$uid, $from, $to]);
    foreach ($c->fetchAll() as $r) {
        $items[] = ['uid' => "cal-{$r['id']}", 'type' => 'reminder', 'title' => $r['title'], 'start' => strtotime($r['event_date']), 'end' => null,
            'all_day' => true, 'description' => ucfirst((string)$r['category']), 'url' => "$site/student/", 'course_id' => 0, 'course' => ''];
    }
    usort($items, fn($x, $y) => $x['start'] <=> $y['start']);
    return $items;
}

/* ── shared event helpers (called from payment/enrolment code paths) ──── */

/** Enrolment confirmed (+ receipt when something was paid). Adds the class schedule as a calendar invite. */
function sda_notify_enrolled(PDO $pdo, int $uid, array $course, string $mode, ?int $cohortId, float $amount = 0, string $reference = '', bool $inapp = true): void
{
    try {
        $cid = (int)$course['id'];
        $structured = $mode === 'structured';
        $cohort = null;
        if ($cohortId) { $s = $pdo->prepare("SELECT * FROM course_cohorts WHERE id = ?"); $s->execute([$cohortId]); $cohort = $s->fetch() ?: null; }
        $e = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
        $e->execute([$uid, $cid]);
        $enr = $e->fetch() ?: [];
        $start = $enr['starts_on'] ?? ($cohort['start_date'] ?? date('Y-m-d'));
        $weekDays = max(1, (int)setting('structured_week_days'));
        $scheduleNote = $structured
            ? 'Your class starts ' . date('l, j M Y', strtotime($start)) . ($cohort ? ' (' . $cohort['name'] . ')' : '') . '. New lessons open every ' . ($weekDays === 7 ? 'week' : "$weekDays days") . ' — the attached invite puts every date in your calendar.'
            : 'Learn at your own pace: all lessons are open now.';
        $accessNote = !empty($enr['expires_at']) ? 'You have access until ' . date('j M Y', strtotime($enr['expires_at'])) . '.' : 'You have lifetime access.';
        $ics = '';
        if ($structured) {
            $items = array_values(array_filter(sda_student_schedule($pdo, $uid, strtotime($start) - 86400, strtotime($start) + 220 * 86400), fn($i) => (int)$i['course_id'] === $cid));
            if ($items) $ics = sda_ics(array_map(fn($i) => $i + ['alarm' => 480], $items), $course['title']);
        }
        sda_notify('enrollment_confirmed', $uid, ['course' => $course['title'], 'course_id' => $cid, 'mode' => mode_label($mode),
            'schedule_note' => $scheduleNote, 'access_note' => $accessNote], ['inapp' => $inapp, 'ics' => $ics, 'dedupe' => "enrolled:$uid:$cid:" . md5($mode . $cohortId . $reference . date('Y-m-d H'))]);
        if ($amount > 0) {
            sda_notify('payment_approved', $uid, ['item' => $course['title'] . ' (' . mode_label($mode) . ')', 'amount' => money($amount), 'reference' => $reference ?: '—'],
                ['inapp' => false, 'dedupe' => $reference ? "receipt:$reference" : null]);
        }
    } catch (Throwable $e) { error_log('[sda notify enrolled] ' . $e->getMessage()); }
}

/** Account activated after the registration fee (or a free activation). */
function sda_notify_activated(int $uid, float $amount = 0, string $reference = ''): void
{
    sda_notify('account_activated', $uid, [], ['dedupe' => "activated:$uid"]);
    if ($amount > 0) sda_notify('payment_approved', $uid, ['item' => 'Account activation fee', 'amount' => money($amount), 'reference' => $reference ?: '—'],
        ['inapp' => false, 'dedupe' => $reference ? "receipt:$reference" : null]);
}
