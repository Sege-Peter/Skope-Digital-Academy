<?php
/**
 * Careers + recruitment assessments.
 *  - job_vacancies: positions the admin posts (admin/careers.php), listed on careers.php.
 *  - job_questions: each position's assessment (admin/career-assessment.php); MCQs auto-marked, written tasks graded by staff.
 *  - job_applications / job_answers: applications (careers-apply.php) and the timed, proctored assessment
 *    (careers-assessment.php), reviewed in admin/career-applications.php and admin/career-application.php.
 * Integrity: the assessment link is emailed (proves the address), is single-use per device (first browser to start
 * owns it), runs on a server-side clock, accepts answers only while open, shuffles questions/options per applicant,
 * never sends correct answers to the browser, and logs every tab switch / fullscreen exit / reload. Three strikes
 * auto-submit. Settings: careers_apply_email, careers_apply_link, careers_schema.
 */

require_once __DIR__ . '/learning.php';

const SDA_JOB_TYPES = ['Full-time', 'Part-time', 'Contract', 'Internship', 'Volunteer'];
const SDA_AX_SECTIONS = ['knowledge' => 'Knowledge assessment', 'practical' => 'Practical problem-solving', 'situational' => 'Situational judgement', 'professional' => 'Professional competency'];
const SDA_AX_MAX_STRIKES = 3;
const SDA_AX_GRACE_SECS = 45;
const SDA_AX_INVITE_DAYS = 7;
const SDA_APP_STATUSES = [
    'received'    => ['Received', ''],
    'invited'     => ['Assessment sent', 'info'],
    'in_progress' => ['Taking assessment', 'info'],
    'submitted'   => ['Awaiting evaluation', 'warn'],
    'evaluated'   => ['Evaluated', 'info'],
    'shortlisted' => ['Shortlisted', 'ok'],
    'interview'   => ['Interview invited', 'ok'],
    'offered'     => ['Offer made', 'ok'],
    'rejected'    => ['Not successful', 'bad'],
];

function sda_careers_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (setting('careers_schema') === '2') return;
    if ($pdo->inTransaction()) { $done = false; return; }

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_vacancies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL, department VARCHAR(80) NULL,
        summary TEXT NOT NULL, responsibilities TEXT NULL, requirements TEXT NULL,
        job_type VARCHAR(30) NOT NULL DEFAULT 'Full-time', location VARCHAR(120) NULL, deadline DATE NULL,
        status ENUM('open','closed') NOT NULL DEFAULT 'open', sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status (status, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $cols = $pdo->query("SHOW COLUMNS FROM job_vacancies")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('assess_minutes', $cols, true)) $pdo->exec("ALTER TABLE job_vacancies ADD assess_minutes INT NOT NULL DEFAULT 90, ADD assess_on TINYINT(1) NOT NULL DEFAULT 1");

    $pdo->exec("CREATE TABLE IF NOT EXISTS job_questions (
        id INT AUTO_INCREMENT PRIMARY KEY, job_id INT NOT NULL,
        section VARCHAR(20) NOT NULL, qtype ENUM('mcq','written') NOT NULL DEFAULT 'mcq',
        prompt TEXT NOT NULL, options TEXT NULL, correct TINYINT NULL, marks DECIMAL(6,2) NOT NULL DEFAULT 4,
        guide TEXT NULL, sort_order INT NOT NULL DEFAULT 0,
        KEY idx_job (job_id, section, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_applications (
        id INT AUTO_INCREMENT PRIMARY KEY, job_id INT NOT NULL,
        name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL, phone VARCHAR(30) NOT NULL, town VARCHAR(80) NULL,
        cover_letter TEXT NOT NULL, cv_file VARCHAR(120) NULL, cv_name VARCHAR(160) NULL, portfolio_url VARCHAR(300) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'received',
        token_hash CHAR(64) NULL, invite_sent_at DATETIME NULL, invite_expires_at DATETIME NULL,
        device_hash CHAR(64) NULL, opens INT NOT NULL DEFAULT 0, qorder TEXT NULL,
        started_at DATETIME NULL, deadline_at DATETIME NULL, submitted_at DATETIME NULL, end_reason VARCHAR(20) NULL,
        violations INT NOT NULL DEFAULT 0, integrity_log TEXT NULL,
        auto_score DECIMAL(7,2) NULL, manual_score DECIMAL(7,2) NULL, max_score DECIMAL(7,2) NULL, final_score DECIMAL(5,2) NULL,
        graded_at DATETIME NULL, graded_by INT NULL, staff_notes TEXT NULL, history TEXT NULL,
        ip VARCHAR(45) NULL, user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_job_email (job_id, email), UNIQUE KEY uq_token (token_hash), KEY idx_job_status (job_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS job_answers (
        application_id INT NOT NULL, question_id INT NOT NULL,
        answer MEDIUMTEXT NULL, marks_awarded DECIMAL(6,2) NULL, feedback VARCHAR(500) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (application_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // First run: post the five roles from the academy's vacancy announcement.
    if ((int)$pdo->query("SELECT COUNT(*) FROM job_vacancies")->fetchColumn() === 0) {
        $seed = [
            ['Academic & Training Coordinator', 'Academics', 'Develops training programmes, coordinates instructors, manages curricula, monitors assessments and ensures quality learning delivery.',
             "Develop and coordinate training programmes.\nManage curriculum development and implementation.\nCoordinate instructors and training schedules.\nMonitor learner assessments and academic progress.\nMaintain quality standards in training delivery."],
            ['Technology & Innovation Officer', 'Technology', 'Manages the LMS, digital infrastructure, technical support, software solutions and technology innovation.',
             "Manage the Academy's Learning Management System (LMS).\nMaintain digital infrastructure and learning platforms.\nProvide technical support to learners and instructors.\nSupport software development and technology integration.\nIdentify and implement innovative digital solutions."],
            ['Student Affairs & Welfare Officer', 'Student Affairs', 'Handles admissions, student support, learner engagement, grievances, academic progress and alumni coordination.',
             "Coordinate student admissions and registration.\nProvide learner support and guidance.\nManage student engagement and welfare matters.\nMonitor student progress and address grievances.\nCoordinate alumni engagement and graduate support."],
            ['Marketing & Communications Officer', 'Marketing', 'Manages branding, digital marketing, public relations, content creation, social media and institutional visibility.',
             "Develop and implement marketing strategies.\nManage institutional branding and communications.\nCreate engaging digital and promotional content.\nManage social media and online visibility.\nCoordinate public relations and promotional campaigns."],
            ['Partnerships & Resource Mobilisation Officer', 'Partnerships', 'Builds relationships with institutions, companies and sponsors; coordinates partnerships, fundraising, grants and scholarship opportunities.',
             "Identify and establish strategic partnerships.\nBuild relationships with organisations, companies and institutions.\nCoordinate fundraising and resource mobilisation initiatives.\nIdentify grant and sponsorship opportunities.\nSupport scholarship programmes and institutional collaborations."],
        ];
        $ins = $pdo->prepare("INSERT INTO job_vacancies (title, department, summary, responsibilities, job_type, status, sort_order) VALUES (?, ?, ?, ?, 'Full-time', 'open', ?)");
        foreach ($seed as $i => $r) $ins->execute([$r[0], $r[1], $r[2], $r[3], ($i + 1) * 10]);
    }

    // Default assessments for the seeded roles (only where a role has no questions yet).
    $bank = require __DIR__ . '/careers_seed.php';
    $has = $pdo->prepare("SELECT COUNT(*) FROM job_questions WHERE job_id = ?");
    $ins = $pdo->prepare("INSERT INTO job_questions (job_id, section, qtype, prompt, options, correct, marks, guide, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($pdo->query("SELECT id, title FROM job_vacancies")->fetchAll() as $job) {
        $set = $bank[$job['title']] ?? null;
        if (!$set) continue;
        $has->execute([$job['id']]);
        if ((int)$has->fetchColumn()) continue;
        $n = 0;
        foreach (['knowledge', 'situational'] as $sec) foreach ($set[$sec] as $q)
            $ins->execute([$job['id'], $sec, 'mcq', $q[0], json_encode($q[1], JSON_UNESCAPED_UNICODE), $q[2], 4, null, $n += 10]);
        foreach (['practical', 'professional'] as $sec)
            $ins->execute([$job['id'], $sec, 'written', $set[$sec][0], null, null, $set[$sec][1], $set[$sec][2], $n += 10]);
    }
    save_setting($pdo, 'careers_schema', '2');
}

/* ── Small helpers ─────────────────────────────────────────────────────── */

function sda_lines(?string $s): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$s)), 'strlen'));
}

function sda_careers_email(): string
{
    $e = trim(setting('careers_apply_email', 'info@skopedigital.co.ke'));
    return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : 'info@skopedigital.co.ke';
}

function sda_careers_link(): string
{
    $u = trim(setting('careers_apply_link', ''));
    return preg_match('#^https://#i', $u) && filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
}

function sda_open_vacancies(PDO $pdo): array
{
    return $pdo->query("SELECT * FROM job_vacancies WHERE status = 'open' AND (deadline IS NULL OR deadline >= CURDATE())
                        ORDER BY sort_order, id")->fetchAll();
}

function sda_job_open(array $job): bool
{
    return $job['status'] === 'open' && (!$job['deadline'] || $job['deadline'] >= date('Y-m-d'));
}

function sda_careers_dir(): string
{
    $d = dirname(__DIR__) . '/uploads/careers';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    if (!is_file("$d/.htaccess")) @file_put_contents("$d/.htaccess", "# CVs are private: served only through admin/career-cv.php\nRequire all denied\n");
    return $d;
}

function sda_app_status_label(string $s): array { return SDA_APP_STATUSES[$s] ?? [ucfirst($s), '']; }

/** Recommendation band for a percentage score. */
function sda_ax_recommend(?float $pct): array
{
    if ($pct === null) return ['Not scored yet', ''];
    if ($pct >= 80) return ['Strongly recommended', 'ok'];
    if ($pct >= 70) return ['Recommended', 'ok'];
    if ($pct >= 60) return ['Further review', 'warn'];
    return ['Not shortlisted automatically', 'bad'];
}

function sda_ax_questions(PDO $pdo, int $jobId): array
{
    $st = $pdo->prepare("SELECT * FROM job_questions WHERE job_id = ? ORDER BY FIELD(section, 'knowledge', 'practical', 'situational', 'professional'), sort_order, id");
    $st->execute([$jobId]);
    return $st->fetchAll();
}

function sda_app_log(PDO $pdo, int $appId, string $line, bool $strike = false): int
{
    $entry = date('Y-m-d H:i:s') . ($strike ? ' [STRIKE] ' : ' ') . mb_substr(preg_replace('/\s+/', ' ', $line), 0, 300) . "\n";
    $pdo->prepare("UPDATE job_applications SET integrity_log = CONCAT(COALESCE(integrity_log, ''), ?)" . ($strike ? ', violations = violations + 1' : '') . " WHERE id = ?")
        ->execute([$entry, $appId]);
    $st = $pdo->prepare("SELECT violations FROM job_applications WHERE id = ?");
    $st->execute([$appId]);
    return (int)$st->fetchColumn();
}

function sda_app_history(PDO $pdo, int $appId, string $line): void
{
    $pdo->prepare("UPDATE job_applications SET history = CONCAT(COALESCE(history, ''), ?) WHERE id = ?")
        ->execute([date('Y-m-d H:i') . ' · ' . mb_substr(preg_replace('/\s+/', ' ', $line), 0, 400) . "\n", $appId]);
}

/* ── Links ─────────────────────────────────────────────────────────────── */

function sda_app_by_token(PDO $pdo, string $raw): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $raw)) return null;
    $st = $pdo->prepare("SELECT a.*, j.title AS job_title, j.assess_minutes, j.assess_on, j.status AS job_status, j.deadline AS job_deadline
                         FROM job_applications a JOIN job_vacancies j ON j.id = a.job_id WHERE a.token_hash = ?");
    $st->execute([hash('sha256', $raw)]);
    return $st->fetch() ?: null;
}

/** New private assessment link (invalidates any earlier one). */
function sda_app_issue_token(PDO $pdo, int $appId): string
{
    $raw = bin2hex(random_bytes(24));
    $pdo->prepare("UPDATE job_applications SET token_hash = ?, invite_sent_at = NOW(), invite_expires_at = NOW() + INTERVAL ? DAY WHERE id = ?")
        ->execute([hash('sha256', $raw), SDA_AX_INVITE_DAYS, $appId]);
    return $raw;
}

function sda_app_link(string $raw): string
{
    require_once __DIR__ . '/mailer.php';
    return rtrim(sda_mail_public_base(), '/') . '/careers-assessment?t=' . $raw;
}

/** Email the applicant their private assessment link. */
function sda_app_send_invite(PDO $pdo, array $app, string $jobTitle, int $minutes, ?string &$err = null): bool
{
    require_once __DIR__ . '/mailer.php';
    $raw = sda_app_issue_token($pdo, (int)$app['id']);
    $link = sda_app_link($raw);
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $html = '<p>Hi ' . $e(strtok($app['name'], ' ')) . ',</p>'
        . '<p>Thank you for applying for the <b>' . $e($jobTitle) . '</b> position at Skope Digital Academy. The next step is a timed online assessment.</p>'
        . '<ul style="padding-left:18px;line-height:1.7">'
        . '<li>It takes <b>' . $minutes . ' minutes</b> and must be finished in one sitting.</li>'
        . '<li>Use a laptop or desktop with a stable connection, in a quiet place.</li>'
        . '<li>Once you start, stay on the test: leaving the tab or window, exiting full screen or reloading the page is recorded. After ' . SDA_AX_MAX_STRIKES . ' warnings the test submits itself.</li>'
        . '<li>The link works on one device only and expires in ' . SDA_AX_INVITE_DAYS . ' days. Please don\'t share it.</li></ul>'
        . '<p style="margin:22px 0"><a href="' . $e($link) . '" style="background:#00BFFF;color:#fff;padding:12px 22px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block">Open my assessment</a></p>'
        . '<p style="font-size:13px;color:#64748b">Or paste this link into your browser:<br>' . $e($link) . '</p>'
        . '<p>Good luck!<br>Skope Digital Academy Recruitment</p>';
    $ok = sda_mail($app['email'], 'Your assessment for ' . $jobTitle . ' – Skope Digital Academy', $html, sda_careers_email(), $err, ['event' => 'career_invite']);
    $pdo->prepare("UPDATE job_applications SET status = IF(status = 'received', 'invited', status) WHERE id = ?")->execute([$app['id']]);
    sda_app_history($pdo, (int)$app['id'], $ok ? 'Assessment link emailed' : 'Assessment link email failed: ' . $err);
    return $ok;
}

/* ── Assessment lifecycle ──────────────────────────────────────────────── */

/** Start: fix the question/option order for this applicant and bind the attempt to this browser. Returns the device key. */
function sda_ax_start(PDO $pdo, array $app): string
{
    $qs = sda_ax_questions($pdo, (int)$app['job_id']);
    $order = [];
    $bySec = [];
    foreach ($qs as $q) $bySec[$q['section']][] = $q;
    foreach (array_keys(SDA_AX_SECTIONS) as $sec) {
        $list = $bySec[$sec] ?? [];
        shuffle($list);
        foreach ($list as $q) {
            $perm = null;
            if ($q['qtype'] === 'mcq') { $perm = array_keys(json_decode((string)$q['options'], true) ?: []); shuffle($perm); }
            $order[] = ['q' => (int)$q['id'], 'p' => $perm];
        }
    }
    $key = bin2hex(random_bytes(24));
    $st = $pdo->prepare("UPDATE job_applications SET status = 'in_progress', started_at = NOW(), deadline_at = NOW() + INTERVAL ? MINUTE,
                         device_hash = ?, opens = 1, qorder = ? WHERE id = ? AND started_at IS NULL");
    $st->execute([max(5, (int)$app['assess_minutes']), hash('sha256', $key), json_encode($order), $app['id']]);
    if ($st->rowCount() !== 1) return '';
    sda_app_log($pdo, (int)$app['id'], 'Started the assessment (IP ' . sda_ax_ip() . ', ' . mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120) . ')');
    sda_app_history($pdo, (int)$app['id'], 'Started the assessment');
    return $key;
}

function sda_ax_ip(): string
{
    if (function_exists('sda_client_ip')) return sda_client_ip();
    return (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
}

function sda_ax_device_cookie(int $appId): string { return 'sda_ax_' . $appId; }

function sda_ax_device_ok(array $app): bool
{
    $c = (string)($_COOKIE[sda_ax_device_cookie((int)$app['id'])] ?? '');
    return $c !== '' && $app['device_hash'] && hash_equals((string)$app['device_hash'], hash('sha256', $c));
}

function sda_ax_set_device_cookie(int $appId, string $key): void
{
    setcookie(sda_ax_device_cookie($appId), $key, ['expires' => time() + 86400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https', 'httponly' => true, 'samesite' => 'Strict']);
}

/** Recalculate scores from the stored answers. */
function sda_ax_score(PDO $pdo, int $appId): void
{
    $st = $pdo->prepare("SELECT a.job_id FROM job_applications a WHERE a.id = ?");
    $st->execute([$appId]);
    $jobId = (int)$st->fetchColumn();
    $ans = $pdo->prepare("SELECT question_id, answer, marks_awarded FROM job_answers WHERE application_id = ?");
    $ans->execute([$appId]);
    $byQ = [];
    foreach ($ans->fetchAll() as $r) $byQ[(int)$r['question_id']] = $r;
    $auto = 0.0; $manual = 0.0; $max = 0.0; $ungraded = 0;
    foreach (sda_ax_questions($pdo, $jobId) as $q) {
        $max += (float)$q['marks'];
        $a = $byQ[(int)$q['id']] ?? null;
        if ($q['qtype'] === 'mcq') {
            if ($a && $a['answer'] !== null && $a['answer'] !== '' && (int)$a['answer'] === (int)$q['correct']) $auto += (float)$q['marks'];
        } else {
            $blank = !$a || trim((string)$a['answer']) === '';
            if ($a && $a['marks_awarded'] !== null) $manual += min((float)$q['marks'], (float)$a['marks_awarded']);
            elseif (!$blank) $ungraded++;          // a blank written answer simply scores 0
        }
    }
    $final = $ungraded === 0 && $max > 0 ? round(($auto + $manual) / $max * 100, 2) : null;
    $pdo->prepare("UPDATE job_applications SET auto_score = ?, manual_score = ?, max_score = ?, final_score = ? WHERE id = ?")
        ->execute([$auto, $ungraded ? null : $manual, $max, $final, $appId]);
}

/** Close an attempt (submitted, time up, too many warnings…). Idempotent. */
function sda_ax_finalize(PDO $pdo, int $appId, string $reason): bool
{
    $st = $pdo->prepare("UPDATE job_applications SET status = 'submitted', submitted_at = NOW(), end_reason = ? WHERE id = ? AND status = 'in_progress'");
    $st->execute([$reason, $appId]);
    if ($st->rowCount() !== 1) return false;
    sda_ax_score($pdo, $appId);
    $labels = ['submitted' => 'Submitted by the applicant', 'time_up' => 'Time ran out', 'violations' => 'Auto-submitted after ' . SDA_AX_MAX_STRIKES . ' integrity warnings', 'abandoned' => 'Closed: the applicant left and did not return before time ran out'];
    sda_app_log($pdo, $appId, 'Assessment closed: ' . ($labels[$reason] ?? $reason));
    sda_app_history($pdo, $appId, 'Assessment closed – ' . ($labels[$reason] ?? $reason));
    // Score is final straight away when there is nothing to grade by hand
    $f = $pdo->prepare("SELECT final_score FROM job_applications WHERE id = ?");
    $f->execute([$appId]);
    if ($f->fetchColumn() !== null) $pdo->prepare("UPDATE job_applications SET status = 'evaluated' WHERE id = ? AND status = 'submitted'")->execute([$appId]);
    return true;
}

/** Close every attempt whose time is up (called from the test page and the admin pages). */
function sda_ax_close_overdue(PDO $pdo): void
{
    $ids = $pdo->query("SELECT id FROM job_applications WHERE status = 'in_progress' AND deadline_at < NOW() - INTERVAL " . SDA_AX_GRACE_SECS . " SECOND")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) sda_ax_finalize($pdo, (int)$id, 'time_up');
}

/** Email the applicant a decision / update written by staff. */
function sda_app_respond(PDO $pdo, array $app, string $subject, string $message, ?string &$err = null): bool
{
    require_once __DIR__ . '/mailer.php';
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $html = '<p>Dear ' . $e($app['name']) . ',</p>' . nl2br($e($message), false)
        . '<p style="margin-top:22px">Kind regards,<br>Recruitment Team<br>Skope Digital Academy</p>';
    return sda_mail($app['email'], $subject, $html, sda_careers_email(), $err, ['event' => 'career_response']);
}
