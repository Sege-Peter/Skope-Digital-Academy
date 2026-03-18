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

    foreach ($questions as $idx => $q) {
        $total_possible += $q['points'];
        $ans = $student_answers[$idx] ?? '';
        $q_points = 0;

        if ($q['type'] == 'mcq' || $q['type'] == 'tf') {
            // Strict match for MCQ / True False
            if (trim(strtolower($ans)) === trim(strtolower($q['correct_answer']))) {
                $q_points = $q['points'];
            }
        } 
        elseif ($q['type'] == 'text') {
            // Keyword Matching Logic (Partial Credit)
            $keywords = array_map('trim', explode(',', strtolower($q['correct_answer'])));
            $clean_answer = strtolower($ans);
            $matches = 0;
            
            if (!empty($keywords)) {
                foreach ($keywords as $kw) {
                    if (str_contains($clean_answer, $kw)) {
                        $matches++;
                    }
                }
                // Calculate percentage based on keyword count
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

    // Update global student points if passed
    if ($passed) {
        $pdo->prepare("UPDATE users SET merit_points = merit_points + ? WHERE id = ?")->execute([round($student_total), $student_id]);
    }

    echo json_encode([
        'score' => round($score_percent),
        'points' => round($student_total),
        'passed' => $passed
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
