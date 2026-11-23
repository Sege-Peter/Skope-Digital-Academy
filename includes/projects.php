<?php
/**
 * Peer-graded course projects – Skope Digital Academy (Coursera-style).
 *
 *   course_projects      one per course (content/courses/<slug>/project.md → import_tutorials.php)
 *   project_submissions  a learner's attempts (text / link / small file)
 *   project_reviews      rubric scores + written feedback from classmates
 *
 * Rules
 *   - Only enrolled learners submit; only learners who submitted in the same project review.
 *   - Nobody reviews themselves or the same submission twice. Submissions are handed out
 *     fewest reviews first, then oldest first (latest attempt of each learner, not yet released).
 *   - A submission's grade = average of its live peer reviews (rubric points → %), once it has
 *     reviews_required reviews. The grade is *released* (passed / needs_work) only when the author
 *     has also reviewed reviews_required classmates – capped by how many they could review, so tiny
 *     classes are never stuck. Released grades stay released.
 *   - Waiting 7+ days without enough reviews → staff_review: an admin grades it (released at once).
 *   - Learners can flag a submission (while reviewing) or an unfair review of their own work →
 *     the submission goes on hold (status flagged) until an admin clears it, removes the review or grades it.
 *   - Failed (needs_work) → resubmit as a new attempt.
 *   - XP: submit 20 (once per project), each review 10, pass 50. Badges: helpful-reviewer, project-passed.
 *     A pass re-runs check_course_completion(), which now also requires the passed project.
 */

require_once __DIR__ . '/progress.php';

const PROJECT_STAFF_DAYS = 7;
const PROJECT_MAX_BYTES = 5 * 1024 * 1024;
const PROJECT_FEEDBACK_MIN = 30;
const PROJECT_FILE_TYPES = [
    'pdf'  => ['application/pdf'],
    'png'  => ['image/png'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
];

function project_upload_dir(): string { return dirname(__DIR__) . '/uploads/projects'; }

/* ── Projects ─────────────────────────────────────────────────────────── */

function project_hydrate(array $p): array {
    $p['rubric_list'] = json_decode((string)$p['rubric'], true) ?: [];
    $p['max_points'] = array_sum(array_map(fn($c) => (int)$c['points'], $p['rubric_list']));
    $p['pass_pct'] = (int)$p['pass_pct'];
    $p['reviews_required'] = max(1, (int)$p['reviews_required']);
    return $p;
}

/** The course's project (active only unless $any). */
function project_for_course(PDO $pdo, int $courseId, bool $any = false): ?array {
    try {
        $st = $pdo->prepare("SELECT * FROM course_projects WHERE course_id = ?" . ($any ? '' : " AND status = 'active'"));
        $st->execute([$courseId]);
        $p = $st->fetch();
    } catch (Exception $e) { return null; }
    return $p ? project_hydrate($p) : null;
}

function project_by_id(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare("SELECT * FROM course_projects WHERE id = ?");
    $st->execute([$id]);
    $p = $st->fetch();
    return $p ? project_hydrate($p) : null;
}

/** Parse a ```rubric block body: "Criterion | what good looks like | points" per line. */
function project_parse_rubric(string $body): array {
    $out = [];
    foreach (preg_split('/\R/', $body) as $line) {
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 3 || $parts[0] === '' || !is_numeric(end($parts))) continue;
        $out[] = ['criterion' => mb_substr($parts[0], 0, 120), 'description' => mb_substr(implode(' | ', array_slice($parts, 1, -1)), 0, 400), 'points' => max(1, (int)end($parts))];
    }
    return $out;
}

function project_status_label(string $s): array {
    return [
        'submitted'    => ['Awaiting reviews', 'info'],
        'passed'       => ['Passed', 'ok'],
        'needs_work'   => ['Needs work', 'bad'],
        'staff_review' => ['With staff', 'violet'],
        'flagged'      => ['On hold', 'warn'],
    ][$s] ?? [ucfirst($s), 'info'];
}

/* ── Who may take part ────────────────────────────────────────────────── */

function project_is_enrolled(PDO $pdo, int $uid, int $courseId): bool {
    $e = get_enrollment($pdo, $uid, $courseId);
    return $e && in_array($e['status'], ['active', 'completed'], true);
}

function project_latest(PDO $pdo, int $projectId, int $uid): ?array {
    $st = $pdo->prepare("SELECT * FROM project_submissions WHERE project_id = ? AND student_id = ? ORDER BY attempt DESC LIMIT 1");
    $st->execute([$projectId, $uid]);
    return $st->fetch() ?: null;
}

function project_live_reviews(PDO $pdo, int $subId): array {
    $st = $pdo->prepare("SELECT * FROM project_reviews WHERE submission_id = ? AND removed_at IS NULL ORDER BY created_at, id");
    $st->execute([$subId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) $r['score_list'] = json_decode((string)$r['scores'], true) ?: [];
    unset($r);
    return $rows;
}

function project_given(PDO $pdo, int $projectId, int $uid): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM project_reviews WHERE project_id = ? AND reviewer_id = ? AND removed_at IS NULL");
    $st->execute([$projectId, $uid]);
    return (int)$st->fetchColumn();
}

/** Submissions this learner could review now, best first (fewest reviews, oldest). */
function project_pool(PDO $pdo, int $projectId, int $uid, ?int $onlySub = null, int $limit = 50): array {
    $st = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM project_reviews r WHERE r.submission_id = s.id AND r.removed_at IS NULL) AS n_reviews
                         FROM project_submissions s
                         WHERE s.project_id = ? AND s.student_id <> ? AND s.status IN ('submitted','staff_review') AND s.released_at IS NULL
                           AND s.attempt = (SELECT MAX(s2.attempt) FROM project_submissions s2 WHERE s2.project_id = s.project_id AND s2.student_id = s.student_id)
                           AND NOT EXISTS (SELECT 1 FROM project_reviews r2 WHERE r2.submission_id = s.id AND r2.reviewer_id = ?)"
                       . ($onlySub ? " AND s.id = " . (int)$onlySub : '') . "
                         ORDER BY n_reviews ASC, s.created_at ASC, s.id ASC LIMIT " . (int)$limit);
    $st->execute([$projectId, $uid, $uid]);
    return $st->fetchAll();
}

/** Reviews this learner still owes: [given, required (capped by what is available), owed]. */
function project_owed(PDO $pdo, array $project, int $uid): array {
    $given = project_given($pdo, (int)$project['id'], $uid);
    $avail = count(project_pool($pdo, (int)$project['id'], $uid, null, 1000));
    $required = min($project['reviews_required'], $given + $avail);
    return ['given' => $given, 'required' => $required, 'owed' => max(0, $required - $given), 'target' => $project['reviews_required']];
}

/* ── Grading ──────────────────────────────────────────────────────────── */

/**
 * Recompute a submission's score/status from its live reviews (or the staff grade) and release it
 * when the rules allow. Returns the progress result for the submission's AUTHOR (XP, badges, certificate).
 */
function project_recompute(PDO $pdo, int $subId): array {
    $r = progress_result();
    $st = $pdo->prepare("SELECT * FROM project_submissions WHERE id = ?");
    $st->execute([$subId]);
    $s = $st->fetch();
    if (!$s || $s['status'] === 'flagged') return $r;
    $project = project_by_id($pdo, (int)$s['project_id']);
    if (!$project) return $r;
    $uid = (int)$s['student_id'];
    $wasPassed = $s['status'] === 'passed';
    $wasReleased = !empty($s['released_at']);

    $score = null; $release = false;
    if (!empty($s['graded_by_staff'])) {
        $score = (float)$s['score_pct'];
        $release = true;
    } else {
        $reviews = project_live_reviews($pdo, $subId);
        if (count($reviews) >= $project['reviews_required']) {
            $score = round(array_sum(array_column($reviews, 'total_pct')) / count($reviews), 2);
            $release = $wasReleased || project_owed($pdo, $project, $uid)['owed'] === 0;
        }
    }

    if ($score === null) {
        // Not enough reviews (again): back in the queue; a staff-review hand-off is kept.
        $status = $s['status'] === 'staff_review' ? 'staff_review' : 'submitted';
        $pdo->prepare("UPDATE project_submissions SET score_pct = NULL, graded_at = NULL, released_at = NULL, status = ? WHERE id = ?")->execute([$status, $subId]);
        return $r;
    }
    $status = $release ? ($score >= $project['pass_pct'] ? 'passed' : 'needs_work') : ($s['status'] === 'staff_review' ? 'staff_review' : 'submitted');
    $pdo->prepare("UPDATE project_submissions SET score_pct = ?, status = ?, graded_at = COALESCE(graded_at, NOW()), released_at = " . ($release ? 'COALESCE(released_at, NOW())' : 'NULL') . " WHERE id = ?")
        ->execute([$score, $status, $subId]);

    if ($release && !$wasReleased) {
        notify($pdo, $uid, $status === 'passed' ? 'Project passed 🎉' : 'Your project grade is ready',
               'Your project "' . $project['title'] . '" scored ' . round($score) . '%' . ($status === 'passed' ? '. Well done!' : ' (pass mark ' . $project['pass_pct'] . '%). Read the feedback and resubmit when ready.'));
    }
    if ($status === 'passed' && !$wasPassed) {
        $r['xp'] += record_event($pdo, $uid, 'project_pass', 'project_pass:' . $project['id'], XP_RULES['project_pass'], (int)$project['course_id'], null, (string)round($score));
        merge_result($r, check_course_completion($pdo, $uid, (int)$project['course_id']));
        merge_result($r, ['badges' => check_badges($pdo, $uid, (int)$project['course_id'])]);
    }
    return $r;
}

/** Hand overdue submissions (7+ days, not enough reviews) to staff. Returns how many moved. */
function project_sweep(PDO $pdo): int {
    try {
        $ids = $pdo->query("SELECT s.id, s.student_id, p.title FROM project_submissions s JOIN course_projects p ON p.id = s.project_id
                            WHERE s.status = 'submitted' AND s.released_at IS NULL AND s.score_pct IS NULL
                              AND s.created_at < DATE_SUB(NOW(), INTERVAL " . PROJECT_STAFF_DAYS . " DAY)
                              AND (SELECT COUNT(*) FROM project_reviews r WHERE r.submission_id = s.id AND r.removed_at IS NULL) < p.reviews_required")->fetchAll();
    } catch (Exception $e) { return 0; }
    $n = 0;
    foreach ($ids as $row) {
        $u = $pdo->prepare("UPDATE project_submissions SET status = 'staff_review' WHERE id = ? AND status = 'submitted'");
        $u->execute([$row['id']]);
        if ($u->rowCount()) {
            $n++;
            notify($pdo, (int)$row['student_id'], 'Your project is with our tutors',
                   'Not enough classmates have reviewed "' . $row['title'] . '" yet, so a tutor will grade it. You will be notified when your grade is ready.');
        }
    }
    return $n;
}

/** Validate rubric scores from a form: [list of {c,s,m}, total_pct] or [null, error]. */
function project_read_scores(array $project, $raw): array {
    $raw = is_array($raw) ? $raw : [];
    $list = []; $got = 0;
    foreach ($project['rubric_list'] as $i => $c) {
        if (!isset($raw[$i]) || $raw[$i] === '' || !is_numeric($raw[$i])) return [null, 'Score every part of the rubric.'];
        $v = (int)$raw[$i];
        if ($v < 0 || $v > (int)$c['points']) return [null, 'A score is outside the allowed range.'];
        $list[] = ['c' => $c['criterion'], 's' => $v, 'm' => (int)$c['points']];
        $got += $v;
    }
    if (!$list) return [null, 'This project has no rubric yet.'];
    return [$list, round($got / max(1, $project['max_points']) * 100, 2)];
}

/* ── Learner actions ──────────────────────────────────────────────────── */

/** Submit (or resubmit after needs_work). $file = $_FILES entry or null. Returns [ok, message, result]. */
function project_submit(PDO $pdo, array $project, int $uid, string $text, string $link, ?array $file): array {
    $courseId = (int)$project['course_id'];
    if ($project['status'] !== 'active') return [false, 'This project is not open for submissions.', null];
    if (!project_is_enrolled($pdo, $uid, $courseId)) return [false, 'Enroll in the course to submit the project.', null];
    $latest = project_latest($pdo, (int)$project['id'], $uid);
    if ($latest && $latest['status'] !== 'needs_work') return [false, $latest['status'] === 'passed' ? 'You already passed this project.' : 'Your submission is still being graded.', null];

    $text = trim(str_replace("\r\n", "\n", $text));
    if (mb_strlen($text) > 20000) return [false, 'Your text is too long (20,000 characters max). Upload a file instead.', null];
    $link = trim($link);
    if ($link !== '') {
        if (mb_strlen($link) > 500 || !filter_var($link, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $link)) return [false, 'The link must be a full web address starting with https://', null];
    }
    $hasFile = $file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($hasFile && empty($project['allow_file'])) return [false, 'This project does not accept file uploads – paste your work or add a link.', null];
    if (mb_strlen($text) < 20 && $link === '' && !$hasFile) return [false, 'Add your work: write at least a few sentences, add a link, or upload a file.', null];

    $stored = null; $origName = null; $size = null;
    if ($hasFile) {
        if ($file['error'] !== UPLOAD_ERR_OK) return [false, $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE ? 'The file is larger than 5 MB.' : 'The upload failed – please try again.', null];
        if ($file['size'] > PROJECT_MAX_BYTES || $file['size'] <= 0) return [false, 'The file must be 5 MB or smaller.', null];
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!isset(PROJECT_FILE_TYPES[$ext])) return [false, 'Upload a PDF, PNG, JPG or Word (.docx) file.', null];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!in_array($mime, PROJECT_FILE_TYPES[$ext], true)) return [false, 'That file does not look like a real .' . $ext . ' file.', null];
        $dir = project_upload_dir();
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $stored = bin2hex(random_bytes(16)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        if (!move_uploaded_file($file['tmp_name'], "$dir/$stored")) return [false, 'Could not save the file – please try again.', null];
        $origName = mb_substr(preg_replace('/[^\w .()-]+/u', '_', basename((string)$file['name'])), 0, 200);
        $size = (int)$file['size'];
    }

    $attempt = $latest ? (int)$latest['attempt'] + 1 : 1;
    $pdo->prepare("INSERT INTO project_submissions (project_id, course_id, student_id, attempt, text, link, file, file_name, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$project['id'], $courseId, $uid, $attempt, $text !== '' ? $text : null, $link !== '' ? $link : null, $stored, $origName, $size]);
    $res = progress_result();
    $res['xp'] += record_event($pdo, $uid, 'project_submit', 'project_submit:' . $project['id'], XP_RULES['project_submit'], $courseId);
    merge_result($res, ['badges' => check_badges($pdo, $uid, $courseId)]);
    return [true, $attempt > 1 ? 'Resubmitted! Your new attempt goes to classmates for review.' : 'Submitted! Now review your classmates to unlock your grade.', $res];
}

/** Save a peer review. Returns [ok, message, result for the reviewer]. */
function project_review(PDO $pdo, array $project, int $uid, int $subId, $scores, string $feedback): array {
    if (!project_is_enrolled($pdo, $uid, (int)$project['course_id']) || !project_latest($pdo, (int)$project['id'], $uid)) return [false, 'Submit your own project before reviewing classmates.', null];
    if (!project_pool($pdo, (int)$project['id'], $uid, $subId)) return [false, 'That submission is no longer available to you – here is another one.', null];
    [$list, $pct] = project_read_scores($project, $scores);
    if ($list === null) return [false, $pct, null];
    $feedback = trim(str_replace("\r\n", "\n", $feedback));
    if (mb_strlen($feedback) < PROJECT_FEEDBACK_MIN) return [false, 'Write at least ' . PROJECT_FEEDBACK_MIN . ' characters of feedback: what works, and one thing to improve.', null];
    if (mb_strlen($feedback) > 3000) return [false, 'Feedback is too long (3,000 characters max).', null];
    try {
        $pdo->prepare("INSERT INTO project_reviews (submission_id, project_id, reviewer_id, scores, total_pct, feedback) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$subId, $project['id'], $uid, json_encode($list, JSON_UNESCAPED_UNICODE), $pct, $feedback]);
    } catch (PDOException $e) {
        return [false, 'You have already reviewed that submission.', null];
    }
    $res = progress_result();
    $res['xp'] += record_event($pdo, $uid, 'project_review', 'project_review:' . $subId, XP_RULES['project_review'], (int)$project['course_id']);
    project_recompute($pdo, $subId);                                   // the classmate's grade (their XP/cert are saved; they see it next visit)
    $own = project_latest($pdo, (int)$project['id'], $uid);
    if ($own) merge_result($res, project_recompute($pdo, (int)$own['id']));   // this review may release the reviewer's own grade
    merge_result($res, ['badges' => check_badges($pdo, $uid, (int)$project['course_id'])]);
    return [true, 'Thanks – review saved.', $res];
}

/** A reviewer flags a submission (plagiarised, abusive, empty…). It leaves the pool until staff decide. */
function project_flag_submission(PDO $pdo, array $project, int $uid, int $subId, string $reason): array {
    if (!project_latest($pdo, (int)$project['id'], $uid) || !project_pool($pdo, (int)$project['id'], $uid, $subId)) return [false, 'You can only flag a submission you were given to review.'];
    $reason = mb_substr(trim($reason), 0, 255);
    if (mb_strlen($reason) < 5) return [false, 'Say briefly why you are flagging it.'];
    $pdo->prepare("UPDATE project_submissions SET status = 'flagged', flag_reason = ?, flagged_by = ?, flagged_at = NOW() WHERE id = ? AND status IN ('submitted','staff_review')")
        ->execute([$reason, $uid, $subId]);
    return [true, 'Thanks – our team will look at it. Here is another submission to review.'];
}

/** The author flags an unfair or abusive review of their own work. */
function project_flag_review(PDO $pdo, int $uid, int $reviewId, string $reason): array {
    $st = $pdo->prepare("SELECT r.*, s.student_id, s.status FROM project_reviews r JOIN project_submissions s ON s.id = r.submission_id WHERE r.id = ? AND r.removed_at IS NULL");
    $st->execute([$reviewId]);
    $r = $st->fetch();
    if (!$r || (int)$r['student_id'] !== $uid) return [false, 'You can only flag reviews of your own work.'];
    $reason = mb_substr(trim($reason), 0, 255);
    if (mb_strlen($reason) < 5) return [false, 'Say briefly what is unfair about this review.'];
    $pdo->prepare("UPDATE project_reviews SET flagged = 1, flag_reason = ?, flagged_at = NOW() WHERE id = ?")->execute([$reason, $reviewId]);
    // Hold the grade while staff look (a pass is never taken away by a learner's own flag).
    if ($r['status'] !== 'passed') $pdo->prepare("UPDATE project_submissions SET status = 'flagged', flagged_at = COALESCE(flagged_at, NOW()) WHERE id = ?")->execute([$r['submission_id']]);
    return [true, 'Thanks – the review is flagged and our team will check it.'];
}

/** Everything the learner UI needs. */
function project_learner_state(PDO $pdo, array $project, int $uid): array {
    $latest = project_latest($pdo, (int)$project['id'], $uid);
    $st = $pdo->prepare("SELECT * FROM project_submissions WHERE project_id = ? AND student_id = ? ORDER BY attempt DESC");
    $st->execute([$project['id'], $uid]);
    $attempts = $st->fetchAll();
    $owed = $latest ? project_owed($pdo, $project, $uid) : ['given' => 0, 'required' => $project['reviews_required'], 'owed' => $project['reviews_required'], 'target' => $project['reviews_required']];
    $next = $latest ? (project_pool($pdo, (int)$project['id'], $uid, null, 1)[0] ?? null) : null;
    $received = $latest ? project_live_reviews($pdo, (int)$latest['id']) : [];
    $released = $latest && !empty($latest['released_at']) && in_array($latest['status'], ['passed', 'needs_work'], true);
    // Per-criterion averages across the reviews (by criterion name).
    $crit = [];
    foreach ($received as $rv) foreach ($rv['score_list'] as $sc) {
        $crit[$sc['c']] ??= ['sum' => 0, 'n' => 0, 'max' => (int)$sc['m']];
        $crit[$sc['c']]['sum'] += (int)$sc['s'];
        $crit[$sc['c']]['n']++;
    }
    if (!$latest) $chip = ['Not started', ''];
    elseif ($latest['status'] === 'submitted' && $owed['owed'] > 0) $chip = ['Review ' . $owed['owed'] . ' more', 'warn'];
    else $chip = project_status_label($latest['status']);
    return compact('latest', 'attempts', 'owed', 'next', 'received', 'released', 'crit', 'chip');
}

/** Can this user download a submission's file? Owner, admin, the course tutor, its reviewers, or a learner it could be handed to. */
function project_can_view_submission(PDO $pdo, array $sub, array $user): bool {
    $uid = (int)$user['id'];
    if ((int)$sub['student_id'] === $uid || $user['role'] === 'admin') return true;
    if ($user['role'] === 'tutor') {
        $t = $pdo->prepare("SELECT tutor_id FROM courses WHERE id = ?");
        $t->execute([$sub['course_id']]);
        return (int)$t->fetchColumn() === $uid;
    }
    $r = $pdo->prepare("SELECT 1 FROM project_reviews WHERE submission_id = ? AND reviewer_id = ?");
    $r->execute([$sub['id'], $uid]);
    if ($r->fetchColumn()) return true;
    return project_is_enrolled($pdo, $uid, (int)$sub['course_id']) && project_latest($pdo, (int)$sub['project_id'], $uid)
        && (bool)project_pool($pdo, (int)$sub['project_id'], $uid, (int)$sub['id']);
}

/* ── Staff actions (admin/projects.php) ───────────────────────────────── */

function project_staff_grade(PDO $pdo, int $subId, int $staffId, $scores, string $feedback): array {
    $st = $pdo->prepare("SELECT * FROM project_submissions WHERE id = ?");
    $st->execute([$subId]);
    $s = $st->fetch();
    if (!$s) return [false, 'Submission not found.'];
    $project = project_by_id($pdo, (int)$s['project_id']);
    [$list, $pct] = project_read_scores($project, $scores);
    if ($list === null) return [false, $pct];
    $feedback = trim($feedback);
    if (mb_strlen($feedback) < 10) return [false, 'Write a little feedback for the learner (10+ characters).'];
    $pdo->prepare("UPDATE project_submissions SET graded_by_staff = ?, staff_scores = ?, staff_feedback = ?, score_pct = ?, status = 'submitted',
                          flag_reason = NULL, flagged_by = NULL, flagged_at = NULL, graded_at = NOW() WHERE id = ?")
        ->execute([$staffId, json_encode($list, JSON_UNESCAPED_UNICODE), mb_substr($feedback, 0, 3000), $pct, $subId]);
    $pdo->prepare("UPDATE project_reviews SET flagged = 0 WHERE submission_id = ?")->execute([$subId]);
    project_recompute($pdo, $subId);
    return [true, $pct];
}

/** Remove an abusive review: it stops counting, the reviewer loses its XP, grades are recomputed. */
function project_remove_review(PDO $pdo, int $reviewId, int $staffId): ?array {
    $st = $pdo->prepare("SELECT r.*, s.status AS sub_status, s.flag_reason AS sub_flag FROM project_reviews r JOIN project_submissions s ON s.id = r.submission_id WHERE r.id = ? AND r.removed_at IS NULL");
    $st->execute([$reviewId]);
    $r = $st->fetch();
    if (!$r) return null;
    $pdo->prepare("UPDATE project_reviews SET removed_at = NOW(), removed_by = ?, flagged = 0 WHERE id = ?")->execute([$staffId, $reviewId]);
    $ev = $pdo->prepare("DELETE FROM learning_events WHERE user_id = ? AND event_key = ?");
    $ev->execute([$r['reviewer_id'], 'project_review:' . $r['submission_id']]);
    if ($ev->rowCount()) $pdo->prepare("UPDATE users SET xp = GREATEST(0, xp - ?) WHERE id = ?")->execute([XP_RULES['project_review'], $r['reviewer_id']]);
    // Lift the hold if nothing else on this submission is flagged.
    if ($r['sub_status'] === 'flagged' && !$r['sub_flag']) {
        $left = $pdo->prepare("SELECT COUNT(*) FROM project_reviews WHERE submission_id = ? AND flagged = 1 AND removed_at IS NULL");
        $left->execute([$r['submission_id']]);
        if (!(int)$left->fetchColumn()) $pdo->prepare("UPDATE project_submissions SET status = 'submitted', flagged_at = NULL WHERE id = ?")->execute([$r['submission_id']]);
    }
    project_recompute($pdo, (int)$r['submission_id']);
    $own = project_latest($pdo, (int)$r['project_id'], (int)$r['reviewer_id']);
    if ($own) project_recompute($pdo, (int)$own['id']);
    return $r;
}

/** Dismiss every flag on a submission (its own and its reviews') and put it back in play. */
function project_clear_flag(PDO $pdo, int $subId): bool {
    $pdo->prepare("UPDATE project_reviews SET flagged = 0 WHERE submission_id = ?")->execute([$subId]);
    $u = $pdo->prepare("UPDATE project_submissions SET flag_reason = NULL, flagged_by = NULL, flagged_at = NULL, status = IF(status = 'flagged', 'submitted', status) WHERE id = ?");
    $u->execute([$subId]);
    project_recompute($pdo, $subId);
    return true;
}
