<?php
/**
 * AI mentor for learners (POST: action=mentor_chat|study_buddy, query, context; X-CSRF-Token).
 * A tutor, not an answer machine: it explains, gives hints and checks the learner's own work, but does not
 * hand out answers to graded quizzes, tests or exams. It is paused while the learner has a test open.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/exam.php';
require_once __DIR__ . '/../includes/ai-handler.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
$out = function (array $d, int $code = 200): never { http_response_code($code); echo json_encode($d); exit; };

if (!isLoggedIn()) $out(['success' => false, 'error' => 'Please sign in again.'], 401);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verifyCsrf()) $out(['success' => false, 'error' => 'Your session expired. Reload the page.'], 403);

$uid = (int)$_SESSION['user_id'];
if (($_SESSION['role'] ?? '') === 'student' && sda_exam_active($pdo, $uid)) {
    $out(['success' => false, 'error' => 'The AI mentor is paused while you have a quiz, test or exam open. Finish and submit it first, then come back to review what you learned.'], 423);
}
if (setting('ai_enabled') === '0') $out(['success' => false, 'error' => 'The AI mentor is switched off right now.'], 503);

require_once __DIR__ . '/../includes/security.php';
if (!sda_rate_ok('ai_mentor', 40, 60, 'uid:' . $uid)) $out(['success' => false, 'error' => 'You have asked a lot of questions this hour. Take a short break, then continue.'], 429);

$query = mb_substr(trim((string)($_POST['query'] ?? '')), 0, 4000);
$context = mb_substr(trim((string)($_POST['context'] ?? '')), 0, 1500);
if ($query === '') $out(['success' => false, 'error' => 'Type a question first.'], 422);

$system = <<<TXT
You are the AI mentor at Skope Digital Academy, an online academy in Kisumu, Kenya, that teaches coding and digital skills.
Your job is to help the learner LEARN and understand, like a patient, encouraging tutor.

How you help:
- Explain concepts in plain English with short, concrete examples (Kenyan context where it helps).
- Break problems into steps and ask guiding questions so the learner works it out.
- Give hints, point out mistakes in the learner's own work, and explain why something works.
- Suggest practice exercises, study plans and which lesson to revisit.
- Keep answers clear and reasonably short; use **bold** for key terms and numbered steps where useful.

Academic integrity (always):
- Do not give direct answers to graded quizzes, tests, CATs, final exams or assignments. If the learner pastes what looks like
  a graded question (multiple choice options, "which of the following", marks, exam wording) or asks you to "just give the answer",
  do not state the answer or the correct option. Instead explain the underlying concept, give a hint, or work through a
  similar example with different values, and encourage them to try.
- For programming tasks: explain the approach and help debug the learner's own code, but do not write the full solution to an
  assessment for them.
- Never claim to be a human, and say so if you are unsure rather than guessing.
TXT;
if ($context !== '') $system .= "\n\nWhat the learner is studying: " . $context;

try {
    $reply = SDAC_AI::ask($query, $system);
    if (!is_string($reply) || trim($reply) === '') throw new RuntimeException('No reply');
    $out(['success' => true, 'response' => $reply, 'reply' => $reply]);
} catch (Throwable $e) {
    error_log('[ai mentor] ' . $e->getMessage());
    $out(['success' => false, 'error' => 'The AI mentor could not answer right now. Please try again in a moment.'], 502);
}
