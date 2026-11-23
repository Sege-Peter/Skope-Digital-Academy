<?php
/**
 * Exam rules for quizzes, weekly tests (CATs) and final exams.
 *  - An attempt is recorded when the test starts (status in_progress) and answers are saved as the learner goes.
 *  - Leaving the tab / window counts as a violation; at SDA_EXAM_MAX_VIOLATIONS the test is submitted automatically.
 *  - A test that was closed or reloaded before submitting is finalised with the saved answers the next time it opens.
 *  - The deadline is kept on the server (time limit + a short grace), so the clock can't be reset in the browser.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';

const SDA_EXAM_MAX_VIOLATIONS = 3;
const SDA_EXAM_GRACE_SECS = 60;

function sda_exam_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (setting('exam_schema') === '1') return;
    if ($pdo->inTransaction()) { $done = false; return; }
    foreach ([
        "ALTER TABLE quiz_attempts ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'completed'",
        "ALTER TABLE quiz_attempts ADD COLUMN IF NOT EXISTS started_at DATETIME NULL",
        "ALTER TABLE quiz_attempts ADD COLUMN IF NOT EXISTS deadline_at DATETIME NULL",
        "ALTER TABLE quiz_attempts ADD COLUMN IF NOT EXISTS violations INT NOT NULL DEFAULT 0",
        "ALTER TABLE quiz_attempts ADD COLUMN IF NOT EXISTS proctor_log TEXT NULL",
        "ALTER TABLE quiz_attempts ADD COLUMN IF NOT EXISTS end_reason VARCHAR(30) NULL",
        "ALTER TABLE quiz_questions MODIFY type ENUM('mcq','tf','text','code') DEFAULT 'mcq'",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) { error_log('[exam schema] ' . $e->getMessage()); }
    }
    save_setting($pdo, 'exam_schema', '1');
}

/** Grade answers ({question_id: answer}) for a quiz. Returns [percent, points earned, points possible]. */
function sda_exam_grade(PDO $pdo, int $quizId, array $answers): array
{
    $st = $pdo->prepare("SELECT id, type, correct_answer, points FROM quiz_questions WHERE quiz_id = ?");
    $st->execute([$quizId]);
    $total = 0.0; $earned = 0.0;
    foreach ($st->fetchAll() as $q) {
        $pts = (float)$q['points'];
        $total += $pts;
        $ans = (string)($answers[$q['id']] ?? $answers[(string)$q['id']] ?? '');
        if ($q['type'] === 'mcq' || $q['type'] === 'tf') {
            if ($ans !== '' && mb_strtolower(trim($ans)) === mb_strtolower(trim((string)$q['correct_answer']))) $earned += $pts;
        } else {
            // Short answer and code: share of the required keywords present (code is matched case-sensitively)
            $kws = array_values(array_filter(array_map('trim', explode(',', (string)$q['correct_answer'])), 'strlen'));
            if ($kws && trim($ans) !== '') {
                $hay = $q['type'] === 'code' ? $ans : mb_strtolower($ans);
                $hit = 0;
                foreach ($kws as $kw) if (str_contains($hay, $q['type'] === 'code' ? $kw : mb_strtolower($kw))) $hit++;
                $earned += $pts * $hit / count($kws);
            }
        }
    }
    return [$total > 0 ? round($earned / $total * 100, 2) : 0.0, $earned, $total];
}

/** Finish an in-progress attempt with its saved answers. Returns ['score', 'passed', 'earned'] or null if not open. */
function sda_exam_finalize(PDO $pdo, int $attemptId, int $studentId, ?array $answers, string $reason): ?array
{
    $st = $pdo->prepare("SELECT a.*, q.pass_score FROM quiz_attempts a JOIN quizzes q ON q.id = a.quiz_id WHERE a.id = ? AND a.student_id = ? AND a.status = 'in_progress'");
    $st->execute([$attemptId, $studentId]);
    $a = $st->fetch();
    if (!$a) return null;
    if ($answers === null) $answers = json_decode((string)$a['answers_json'], true) ?: [];
    [$pct, $earned] = sda_exam_grade($pdo, (int)$a['quiz_id'], $answers);
    $passed = $pct >= (float)$a['pass_score'] ? 1 : 0;
    $pdo->prepare("UPDATE quiz_attempts SET status = 'completed', score = ?, passed = ?, answers_json = ?, completed_at = NOW(), end_reason = ? WHERE id = ? AND status = 'in_progress'")
        ->execute([$pct, $passed, json_encode($answers), $reason, $attemptId]);
    return ['score' => $pct, 'passed' => $passed, 'earned' => $earned, 'quiz_id' => (int)$a['quiz_id']];
}

/** The learner's open attempt for a quiz (if any). */
function sda_exam_open_attempt(PDO $pdo, int $quizId, int $studentId): ?array
{
    $st = $pdo->prepare("SELECT * FROM quiz_attempts WHERE quiz_id = ? AND student_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1");
    $st->execute([$quizId, $studentId]);
    return $st->fetch() ?: null;
}

/** Is this learner in the middle of a test right now? (Used to pause the AI mentor.) */
function sda_exam_active(PDO $pdo, int $studentId): bool
{
    sda_exam_ensure($pdo);
    $st = $pdo->prepare("SELECT 1 FROM quiz_attempts WHERE student_id = ? AND status = 'in_progress' AND deadline_at > NOW() - INTERVAL 1 MINUTE LIMIT 1");
    $st->execute([$studentId]);
    return (bool)$st->fetchColumn();
}

function sda_exam_log(PDO $pdo, int $attemptId, string $event): void
{
    $pdo->prepare("UPDATE quiz_attempts SET proctor_log = CONCAT(COALESCE(proctor_log, ''), ?) WHERE id = ?")
        ->execute([date('H:i:s') . ' ' . mb_substr($event, 0, 80) . "\n", $attemptId]);
}
