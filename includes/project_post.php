<?php
/**
 * POST handler for learn/<slug>/project (included by learn.php; Post/Redirect/Get).
 * Uses: $pdo, $course, $project, $student_id, $projUrl. Messages go to $_SESSION['proj_msg'].
 */
$back = sda_base_href() . $projUrl;
$anchor = '';
if (!$student_id || !verifyCsrf()) {
    // An oversized upload empties $_POST, which also lands here.
    $_SESSION['proj_msg'] = ['bad', $student_id ? 'The form expired or the file was too large (5 MB max). Please try again.' : 'Sign in as a learner to take part.'];
    header('Location: ' . ($student_id ? $back : sda_base_href() . 'login'));
    exit;
}
if (!$project) { header("Location: $back"); exit; }

$act = (string)($_POST['act'] ?? '');
$ok = false; $msg = 'Unknown action.';
try {
    if ($act === 'submit') {
        [$ok, $msg, $res] = project_submit($pdo, $project, $student_id, (string)($_POST['text'] ?? ''), (string)($_POST['link'] ?? ''), $_FILES['file'] ?? null);
        if ($res) progress_flash_add($res);
        if (!$ok) $_SESSION['proj_old'] = ['text' => mb_substr((string)($_POST['text'] ?? ''), 0, 20000), 'link' => mb_substr((string)($_POST['link'] ?? ''), 0, 500)];
        $anchor = $ok ? '#review' : '#submit';
    } elseif ($act === 'review') {
        $sub = (int)($_POST['submission_id'] ?? 0);
        [$ok, $msg, $res] = project_review($pdo, $project, $student_id, $sub, $_POST['score'] ?? [], (string)($_POST['feedback'] ?? ''));
        if ($res) progress_flash_add($res);
        if (!$ok) $_SESSION['proj_old_review'] = ['sub' => $sub, 'score' => array_map('strval', (array)($_POST['score'] ?? [])), 'feedback' => mb_substr((string)($_POST['feedback'] ?? ''), 0, 3000)];
        $anchor = '#review';
    } elseif ($act === 'flag_submission') {
        $reason = trim((string)($_POST['reason'] ?? '') . ' ' . (string)($_POST['details'] ?? ''));
        [$ok, $msg] = project_flag_submission($pdo, $project, $student_id, (int)($_POST['submission_id'] ?? 0), $reason);
        $anchor = '#review';
    } elseif ($act === 'flag_review') {
        $reason = trim((string)($_POST['reason'] ?? '') . ' ' . (string)($_POST['details'] ?? ''));
        [$ok, $msg] = project_flag_review($pdo, $student_id, (int)($_POST['review_id'] ?? 0), $reason);
        $anchor = '#feedback';
    }
} catch (Exception $e) {
    error_log('project: ' . $e->getMessage());
    $ok = false;
    $msg = 'Something went wrong – please try again.';
}
$_SESSION['proj_msg'] = [$ok ? 'ok' : 'bad', $msg];
header("Location: $back$anchor");
exit;
