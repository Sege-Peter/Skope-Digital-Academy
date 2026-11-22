<?php
/**
 * Learning platform core – Skope Digital Academy
 *
 * Self-paced vs structured learning, admin-controlled pricing, cohorts,
 * lesson scheduling and platform settings. Every page that enrolls a learner,
 * prices a course or decides whether a lesson is open goes through here.
 */

if (!defined('SDA_BASE')) {
    // URL path of the app root (e.g. "/Skope Digital Academy" locally, "" at a domain root),
    // worked out from where this file lives relative to the web root.
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(__DIR__ . '/..') ?: '';
    $rel = ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) ? substr($appRoot, strlen($docRoot)) : '/Skope Digital Academy';
    define('SDA_BASE', rtrim(str_replace('\\', '/', $rel), '/'));
    unset($docRoot, $appRoot, $rel);
}

/** URL-encoded app base for <base href> and absolute links, always ending in "/". */
function sda_base_href(): string {
    return implode('/', array_map('rawurlencode', explode('/', SDA_BASE))) . '/';
}

/** Pretty tutorial URL: learn/<course-slug>[/<lesson-slug>], falling back to query form. */
function learn_url(array $course, ?array $lesson = null, string $extra = ''): string {
    $q = $extra !== '' ? '?' . ltrim($extra, '?&') : '';
    if (!empty($course['slug']) && preg_match('/^[a-z0-9-]+$/', $course['slug'])) {
        if ($lesson === null) return 'learn/' . $course['slug'] . $q;
        if (!empty($lesson['slug']) && preg_match('/^[a-z0-9-]+$/', $lesson['slug'])) return 'learn/' . $course['slug'] . '/' . $lesson['slug'] . $q;
    }
    $q2 = 'course=' . (int)$course['id'] . ($lesson ? '&lesson=' . (int)$lesson['id'] : '');
    return 'learn?' . $q2 . ($extra !== '' ? '&' . ltrim($extra, '?&') : '');
}

const SDA_MODES = ['self_paced', 'structured'];

/** Absolute app URL path, e.g. sda_url('learn.php?course=3'). */
function sda_url(string $path = ''): string {
    return SDA_BASE . '/' . ltrim($path, '/');
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* ── Platform settings (admin-editable key/value) ─────────────────────── */

const SDA_SETTING_DEFAULTS = [
    'currency'               => 'KES',
    'registration_fee'       => '10',
    'default_access_days'    => '0',     // 0 = lifetime access
    'structured_pass_score'  => '70',    // quiz score that unlocks the next lesson on a structured track
    'structured_week_days'   => '7',     // days between weekly releases on a structured track
    'allow_mode_switch'      => '1',     // learners may switch track when already covered by what they paid
    'tutorials_title'        => 'Free Tutorials',
    'certificate_fee'        => '0',     // default certificate fee (KES); courses can override
    'site_url'               => '',      // public address used in emailed links, e.g. https://academy.example.com
];

function setting(string $key, ?string $default = null): string {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query("SELECT setting_key, setting_value FROM platform_settings")->fetchAll() as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $e) { /* table missing before migration: fall back to defaults */ }
    }
    return $cache[$key] ?? $default ?? SDA_SETTING_DEFAULTS[$key] ?? '';
}

function save_setting(PDO $pdo, string $key, string $value): void {
    $pdo->prepare("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$key, $value]);
}

function money(float $amount): string {
    return setting('currency') . ' ' . number_format($amount, $amount == floor($amount) ? 0 : 2);
}

/* ── Modes & pricing ──────────────────────────────────────────────────── */

function mode_label(string $mode): string {
    return $mode === 'structured' ? 'Structured track' : 'Self-paced';
}

function mode_blurb(string $mode): string {
    return $mode === 'structured'
        ? 'Weekly releases, a schedule with due dates and quiz checkpoints. Learn alongside a cohort.'
        : 'Everything unlocked from day one. Learn in any order, at your own speed.';
}

/** Modes the admin has enabled for a course, in display order. */
function course_modes(array $c): array {
    $m = $c['learning_modes'] ?? 'both';
    if ($m === 'self_paced') return ['self_paced'];
    if ($m === 'structured') return ['structured'];
    return ['self_paced', 'structured'];
}

function sale_active(array $c): bool {
    if (!isset($c['sale_price']) || $c['sale_price'] === null || $c['sale_price'] === '') return false;
    if (!empty($c['sale_ends_at']) && strtotime($c['sale_ends_at']) < time()) return false;
    return (float)$c['sale_price'] < (float)$c['price'];
}

/** List price for a mode before any sale. */
function course_list_price(array $c, string $mode): float {
    if ($mode === 'structured' && isset($c['structured_price']) && $c['structured_price'] !== null && $c['structured_price'] !== '') {
        return (float)$c['structured_price'];
    }
    return (float)($c['price'] ?? 0);
}

/** What a learner pays for a mode right now. The sale price applies to the self-paced price. */
function course_price(array $c, string $mode): float {
    if ($mode === 'self_paced' && sale_active($c)) return (float)$c['sale_price'];
    return course_list_price($c, $mode);
}

/** Fee to unlock this course's certificate: the course's own fee, else the platform default. */
function certificate_fee_for(array $c): float {
    if (isset($c['certificate_fee']) && $c['certificate_fee'] !== null && $c['certificate_fee'] !== '') return max(0.0, (float)$c['certificate_fee']);
    return max(0.0, (float)setting('certificate_fee'));
}

/** Cheapest current price across enabled modes (for catalogue cards). */
function course_from_price(array $c): float {
    return min(array_map(fn($m) => course_price($c, $m), course_modes($c)));
}

/* ── Cohorts ──────────────────────────────────────────────────────────── */

function open_cohorts(PDO $pdo, int $course_id): array {
    $stmt = $pdo->prepare("SELECT co.*, (SELECT COUNT(*) FROM enrollments e WHERE e.cohort_id = co.id AND e.status IN ('active','pending','completed')) AS seats_taken
                           FROM course_cohorts co
                           WHERE co.course_id = ? AND co.status = 'open' AND (co.end_date IS NULL OR co.end_date >= CURDATE())
                           ORDER BY co.start_date ASC");
    $stmt->execute([$course_id]);
    return array_values(array_filter($stmt->fetchAll(), fn($co) => empty($co['capacity']) || $co['seats_taken'] < $co['capacity']));
}

function find_open_cohort(PDO $pdo, int $course_id, int $cohort_id): ?array {
    foreach (open_cohorts($pdo, $course_id) as $co) {
        if ((int)$co['id'] === $cohort_id) return $co;
    }
    return null;
}

/* ── Enrollment ───────────────────────────────────────────────────────── */

function get_enrollment(PDO $pdo, int $student_id, int $course_id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    return $stmt->fetch() ?: null;
}

/**
 * Validate a learner's mode/cohort choice for a course.
 * Returns [mode, cohort_id|null, error|null].
 */
function resolve_mode_choice(PDO $pdo, array $c, ?string $mode, $cohort_id): array {
    $modes = course_modes($c);
    if (!$mode || !in_array($mode, $modes, true)) $mode = $modes[0];
    $cohort_id = (int)$cohort_id ?: null;
    if ($mode !== 'structured') return [$mode, null, null];
    $cohorts = open_cohorts($pdo, (int)$c['id']);
    if ($cohort_id) {
        if (!find_open_cohort($pdo, (int)$c['id'], $cohort_id)) return [$mode, null, 'That cohort is full or no longer open. Please pick another.'];
        return [$mode, $cohort_id, null];
    }
    // No cohort chosen: fine when none are scheduled (personal weekly schedule from today).
    if ($cohorts) return [$mode, null, 'Please choose a cohort start date for the structured track.'];
    return [$mode, null, null];
}

/** Amount still owed to take a course in a mode, crediting what was already paid. */
function amount_due(array $c, string $mode, ?array $enrollment): float {
    $paid = ($enrollment && in_array($enrollment['status'], ['active', 'completed'], true)) ? (float)$enrollment['amount_paid'] : 0.0;
    return max(0.0, round(course_price($c, $mode) - $paid, 2));
}

/**
 * Create or update an enrollment. $paid is added to what the learner already paid.
 * Sets the track start date (cohort start, or today) and access expiry.
 */
function enroll_student(PDO $pdo, int $student_id, array $c, string $mode, ?int $cohort_id, float $paid, string $status = 'active'): void {
    $existing = get_enrollment($pdo, $student_id, (int)$c['id']);
    $starts_on = date('Y-m-d');
    if ($cohort_id) {
        $s = $pdo->prepare("SELECT start_date FROM course_cohorts WHERE id = ? AND course_id = ?");
        $s->execute([$cohort_id, $c['id']]);
        $starts_on = $s->fetchColumn() ?: $starts_on;
    } elseif ($existing && $existing['learning_mode'] === $mode && !empty($existing['starts_on'])) {
        $starts_on = $existing['starts_on'];
    }
    $days = (int)($c['access_days'] ?? 0) ?: (int)setting('default_access_days');
    $expires = $days > 0 ? date('Y-m-d H:i:s', strtotime("+$days days")) : null;

    if ($existing) {
        $new_status = ($existing['status'] === 'completed' && $status === 'active') ? 'completed' : $status;
        $pdo->prepare("UPDATE enrollments SET status = ?, learning_mode = ?, cohort_id = ?, starts_on = ?, expires_at = ?, amount_paid = amount_paid + ? WHERE id = ?")
            ->execute([$new_status, $mode, $cohort_id, $starts_on, $expires, $paid, $existing['id']]);
        if ($existing['status'] !== 'active' && $existing['status'] !== 'completed' && $status === 'active') {
            $pdo->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?")->execute([$c['id']]);
        }
    } else {
        $pdo->prepare("INSERT INTO enrollments (student_id, course_id, status, learning_mode, cohort_id, starts_on, expires_at, amount_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$student_id, $c['id'], $status, $mode, $cohort_id, $starts_on, $expires, $paid]);
        if ($status === 'active') {
            $pdo->prepare("UPDATE courses SET enrolled_count = enrolled_count + 1 WHERE id = ?")->execute([$c['id']]);
        }
    }
}

/* ── Lesson access & schedule ─────────────────────────────────────────── */

/**
 * Annotate ordered lessons with is_locked, lock_reason, opens_at, due_at.
 * Each lesson row needs: week_num, release_date, progress_status, attached_quiz_id, quiz_score.
 */
function apply_lesson_schedule(array $lessons, ?array $enrollment): array {
    $mode = $enrollment['learning_mode'] ?? 'self_paced';
    $expired = $enrollment && !empty($enrollment['expires_at']) && strtotime($enrollment['expires_at']) < time();
    $start = strtotime(($enrollment['starts_on'] ?? null) ?: date('Y-m-d'));
    $week_days = max(1, (int)setting('structured_week_days'));
    $pass = (int)setting('structured_pass_score');
    $weeks = array_map(fn($l) => (int)($l['week_num'] ?: 1), $lessons);
    $first_week = $weeks ? min($weeks) : 1;

    foreach ($lessons as $i => &$l) {
        $l['is_locked'] = false;
        $l['lock_reason'] = '';
        $l['opens_at'] = null;
        $l['due_at'] = null;

        if ($expired) {
            $l['is_locked'] = true;
            $l['lock_reason'] = 'Your access ended on ' . date('M j, Y', strtotime($enrollment['expires_at']));
            continue;
        }
        if (!empty($l['release_date']) && strtotime($l['release_date']) > time()) {
            $l['is_locked'] = true;
            $l['lock_reason'] = 'Released ' . date('M j, Y', strtotime($l['release_date']));
            $l['opens_at'] = strtotime($l['release_date']);
            continue;
        }
        if ($mode !== 'structured') continue;

        $offset = ((int)($l['week_num'] ?: 1) - $first_week) * $week_days;
        $opens = strtotime("+$offset days", $start);
        $l['opens_at'] = $opens;
        $l['due_at'] = strtotime("+$week_days days", $opens);
        if ($opens > time()) {
            $l['is_locked'] = true;
            $l['lock_reason'] = 'Opens ' . date('D, M j', $opens);
            continue;
        }
        if ($i > 0) {
            $prev = $lessons[$i - 1];
            if (($prev['progress_status'] ?? '') !== 'completed') {
                $l['is_locked'] = true;
                $l['lock_reason'] = 'Complete the previous lesson first';
            } elseif (!empty($prev['attached_quiz_id']) && (float)($prev['quiz_score'] ?? 0) < $pass) {
                $l['is_locked'] = true;
                $l['lock_reason'] = "Score $pass% on the previous quiz first";
            }
        }
    }
    unset($l);
    return $lessons;
}

/* ── Public tutorial access ───────────────────────────────────────────── */

/** Can this visitor read a lesson in the public reader? Returns [bool, reason]. */
function reader_can_view(array $course, array $lesson, ?array $enrollment): array {
    if (!empty($course['is_open_tutorial'])) return [true, ''];
    if (!empty($lesson['is_preview'])) return [true, ''];
    if ($enrollment && in_array($enrollment['status'], ['active', 'completed'], true)) {
        return [true, 'enrolled'];
    }
    return [false, 'This lesson is part of the full course. Enroll to unlock it.'];
}

/** Lessons of a course in order, with this learner's progress and best linked-quiz score. */
function load_course_lessons(PDO $pdo, int $course_id, ?int $student_id): array {
    $sid = $student_id ?: 0;
    $stmt = $pdo->prepare("SELECT l.*, q.id AS attached_quiz_id, q.title AS attached_quiz_title,
                                  (SELECT status FROM lesson_progress WHERE student_id = ? AND lesson_id = l.id) AS progress_status,
                                  (SELECT MAX(score) FROM quiz_attempts WHERE student_id = ? AND quiz_id = q.id) AS quiz_score
                           FROM lessons l
                           LEFT JOIN quizzes q ON q.id = (SELECT MIN(q2.id) FROM quizzes q2 WHERE q2.linked_lesson_id = l.id)
                           WHERE l.course_id = ?
                           ORDER BY l.week_num ASC, l.order_num ASC, l.id ASC");
    $stmt->execute([$sid, $sid, $course_id]);
    return $stmt->fetchAll();
}

/** Mark a lesson complete and refresh the enrollment's progress. Returns new percent. */
function complete_lesson(PDO $pdo, array $enrollment, int $lesson_id): float {
    $pdo->prepare("INSERT INTO lesson_progress (student_id, lesson_id, course_id, status, completed_at)
                   VALUES (?, ?, ?, 'completed', NOW())
                   ON DUPLICATE KEY UPDATE status = 'completed', completed_at = COALESCE(completed_at, NOW())")
        ->execute([$enrollment['student_id'], $lesson_id, $enrollment['course_id']]);
    // XP, badges, course completion (with final-quiz rule) and the certificate are handled by the
    // learning engine. Its result is kept in the session so the next page can celebrate it.
    require_once __DIR__ . '/progress.php';
    $res = track_lesson_complete($pdo, (int)$enrollment['student_id'], ['id' => $lesson_id, 'course_id' => (int)$enrollment['course_id']]);
    progress_flash_add($res);
    return (float)($res['progress'] ?? 0);
}

/** YouTube/Vimeo watch link → embeddable URL; anything else returned as-is (or null). */
function video_embed_url(?string $url): ?string {
    if (!$url) return null;
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{6,})~', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    if (preg_match('~vimeo\.com/(\d+)~', $url, $m)) return 'https://player.vimeo.com/video/' . $m[1];
    return preg_match('~^https://~', $url) ? $url : null;
}

/**
 * Render plain-text lesson content as tutorial HTML. Everything is escaped first;
 * supported markup: "# " / "## " headings, "- " lists, "1. " lists, ``` code fences,
 * `inline code`, **bold**. Plain paragraphs keep their line breaks.
 */
function render_lesson_text(?string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
    $out = [];
    $para = [];
    $list = null;   // 'ul' | 'ol'
    $items = [];
    $inCode = false;
    $code = [];
    $inline = function (string $s): string {
        $s = e($s);
        $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
        return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    };
    $flushPara = function () use (&$para, &$out, $inline) {
        if ($para) { $out[] = '<p>' . implode('<br>', array_map($inline, $para)) . '</p>'; $para = []; }
    };
    $flushList = function () use (&$list, &$items, &$out, $inline) {
        if ($list) {
            $out[] = "<$list>" . implode('', array_map(fn($i) => '<li>' . $inline($i) . '</li>', $items)) . "</$list>";
            $list = null; $items = [];
        }
    };
    foreach (explode("\n", $text) as $line) {
        if (preg_match('/^\s*```/', $line)) {
            if ($inCode) { $out[] = '<pre class="lesson-code"><code>' . e(implode("\n", $code)) . '</code></pre>'; $code = []; $inCode = false; }
            else { $flushPara(); $flushList(); $inCode = true; }
            continue;
        }
        if ($inCode) { $code[] = $line; continue; }
        $t = trim($line);
        if ($t === '') { $flushPara(); $flushList(); continue; }
        if (preg_match('/^(#{1,3})\s+(.+)$/', $t, $m)) {
            $flushPara(); $flushList();
            $lvl = strlen($m[1]) + 1;
            $out[] = "<h$lvl>" . $inline($m[2]) . "</h$lvl>";
            continue;
        }
        if (preg_match('/^[-*•]\s+(.+)$/u', $t, $m)) {
            $flushPara();
            if ($list !== 'ul') { $flushList(); $list = 'ul'; }
            $items[] = $m[1];
            continue;
        }
        if (preg_match('/^\d+[.)]\s+(.+)$/', $t, $m) && $list === 'ol') { $items[] = $m[1]; continue; }
        if (preg_match('/^\d+[.)]\s+(.+)$/', $t, $m) && !$para) {
            // A short numbered line on its own reads as a section heading ("1. Introduction").
            if (mb_strlen($m[1]) <= 60 && !preg_match('/[.?!:]$/', $m[1])) { $flushList(); $out[] = '<h3>' . $inline($t) . '</h3>'; continue; }
            $flushList(); $list = 'ol'; $items[] = $m[1];
            continue;
        }
        $flushList();
        $para[] = $t;
    }
    if ($inCode) $out[] = '<pre class="lesson-code"><code>' . e(implode("\n", $code)) . '</code></pre>';
    $flushPara(); $flushList();
    return implode("\n", $out);
}

/** Languages the Try-it editor can run in the browser sandbox. */
function tryit_runnable(?string $lang): bool {
    return in_array($lang, ['html', 'css', 'javascript'], true);
}
