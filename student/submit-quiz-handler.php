<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn()) { echo json_encode(['error' => 'Unauthorized']); exit; }

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['quiz_id'])) { echo json_encode(['error' => 'Integrity Check Failed']); exit; }

$qid = (int)$data['quiz_id'];
$student_id = $_SESSION['user_id'];
$student_answers = $data['answers'] ?? [];

try {
    // 1. Fetch Quiz Info
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->execute([$qid]);
    $quiz = $stmt->fetch();
    if (!$quiz) { throw new Exception("Assessment Not Found"); }

    // 2. Fetch Questions
    $stmt = $pdo->prepare("SELECT id, type, correct_answer, points FROM quiz_questions WHERE quiz_id = ?");
    $stmt->execute([$qid]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_possible = 0;
    $student_total = 0;

    foreach ($questions as $q) {
        $total_possible += $q['points'];
        $ans = $student_answers[$q['id']] ?? '';
        $q_points = 0;

        if ($q['type'] == 'mcq' || $q['type'] == 'tf') {
            if (trim(strtolower($ans)) === trim(strtolower($q['correct_answer']))) {
                $q_points = $q['points'];
            }
        } 
        elseif ($q['type'] == 'text') {
            $keywords = array_map('trim', explode(',', strtolower($q['correct_answer'])));
            $clean_answer = strtolower($ans);
            $matches = 0;
            
            if (!empty($keywords)) {
                foreach ($keywords as $kw) {
                    if (str_contains($clean_answer, $kw)) {
                        $matches++;
                    }
                }
                $ratio = $matches / count($keywords);
                $q_points = $q['points'] * $ratio;
            }
        }

        $student_total += $q_points;
    }

    // Final calculations
    $score_percent = ($total_possible > 0) ? ($student_total / $total_possible) * 100 : 0;
    $passed = ($score_percent >= $quiz['pass_score']) ? 1 : 0;

    // Save Attempt
    $stmt = $pdo->prepare("INSERT INTO quiz_attempts (quiz_id, student_id, score, passed, completed_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$qid, $student_id, $score_percent, $passed]);

    // Reward Merit Points if passed
    if ($passed) {
        require_once __DIR__ . '/../includes/gamified_logic.php';
        rewardStudentPoints($student_id, $student_total, $pdo);

        // --- AUTOMATED TRANSCRIPT RECORDING (FULL FUNCTION) ---
        // 1. Determine Grade
        $grade = 'F';
        if ($score_percent >= 90) $grade = 'A+';
        elseif ($score_percent >= 80) $grade = 'A';
        elseif ($score_percent >= 70) $grade = 'B';
        elseif ($score_percent >= 60) $grade = 'C';
        elseif ($score_percent >= 50) $grade = 'D';

        // 2. Determine Entry Title based on type
        $entry_title = ($quiz['type'] == 'cat') ? "CAT: " . $quiz['title'] : (($quiz['type'] == 'final') ? "FINAL: " . $quiz['title'] : "QUIZ: " . $quiz['title']);
        
        // 3. Find course tutor (recorded_by)
        $tstmt = $pdo->prepare("SELECT tutor_id FROM courses WHERE id = ?");
        $tstmt->execute([$quiz['course_id']]);
        $tutor_id = $tstmt->fetchColumn();

        // 4. Update or Insert Transcript Entry
        // We only keep the HIGHEST score for this specific quiz in transcript
        $check_stmt = $pdo->prepare("SELECT id, score FROM transcript_entries WHERE student_id = ? AND title = ? AND course_id = ?");
        $check_stmt->execute([$student_id, $entry_title, $quiz['course_id']]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            if ($score_percent > $existing['score']) {
                $upd = $pdo->prepare("UPDATE transcript_entries SET score = ?, grade = ?, recorded_at = NOW() WHERE id = ?");
                $upd->execute([$score_percent, $grade, $existing['id']]);
            }
        } else {
            $ins = $pdo->prepare("INSERT INTO transcript_entries (student_id, course_id, entry_type, title, score, max_score, grade, credits, recorded_by) VALUES (?, ?, 'quiz_pass', ?, ?, 100, ?, 1.0, ?)");
            $ins->execute([$student_id, $quiz['course_id'], $entry_title, $score_percent, $grade, $tutor_id]);
        }
    }

    echo json_encode([
        'score' => round($score_percent),
        'points' => round($student_total),
        'passed' => $passed
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
