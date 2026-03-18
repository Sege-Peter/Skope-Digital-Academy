<?php
/**
 * AI Course Assistant — AJAX Handler
 * Generates quiz questions & lesson plans from course data.
 * No external API needed: intelligently constructs from course metadata.
 require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/ai_config.php';

header('Content-Type: application/json');

// Auth check
if (!isLoggedIn() || currentUser()['role'] !== 'tutor') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$tutor  = currentUser();
$action = $_POST['action'] ?? '';

// ── Handle Requests ─────────────────────────────────────────────────────────
try {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $prompt    = trim($_POST['prompt'] ?? '');

    // Fetch tutor's course for context
    if ($course_id) {
        $stmt = $pdo->prepare("SELECT c.*, cat.name AS category_name FROM courses c LEFT JOIN categories cat ON c.category_id = cat.id WHERE c.id = ? AND c.tutor_id = ?");
        $stmt->execute([$course_id, $tutor['id']]);
        $course = $stmt->fetch();
        if (!$course) throw new Exception("Course not found or access denied.");
        $courseTitle = $course['title'];
        $courseDesc  = $course['description'] ?? '';
    } else {
        throw new Exception("Please select a target course first.");
    }

    if ($action === 'generate_quiz') {
        $num_q = 5;
        $qTitle = "AI Quiz: " . (empty($prompt) ? $courseTitle : $prompt);

        $aiPrompt = "Create a high-quality educational quiz with {$num_q} multiple choice questions for a course titled '{$courseTitle}'.
        Topic Focus: " . (empty($prompt) ? $courseDesc : $prompt) . "
        
        Format: JSON array of objects only. No conversational text.
        Structure: [ { \"question\": \"text\", \"correct_answer\": \"text\", \"options\": [\"opt1\", \"opt2\", \"opt3\", \"opt4\"], \"points\": 10 } ]";

        $response = callGemini($aiPrompt);
        $jsonStr  = preg_replace('/```json\n|\n```|```/', '', $response);
        $questions = json_decode($jsonStr, true);

        if (!$questions) throw new Exception("Failed to generate a valid quiz structure.");

        // Save quiz to DB
        $stmt = $pdo->prepare("INSERT INTO quizzes (course_id, title, time_limit_mins, pass_score) VALUES (?, ?, ?, ?)");
        $stmt->execute([$course_id, $qTitle, 20, 70]);
        $quiz_id = $pdo->lastInsertId();

        // Save questions
        $qStmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, correct_answer, options_json, points, order_num) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($questions as $i => $q) {
            $qStmt->execute([$quiz_id, $q['question'], $q['correct_answer'], json_encode($q['options']), 10, $i + 1]);
        }

        echo json_encode([
            'success'     => true,
            'message'     => "✅ Quiz created with dynamic AI questions!",
            'quiz_id'     => $quiz_id,
            'quiz_title'  => $qTitle,
            'course_id'   => $course_id,
            'questions'   => $questions,
            'quiz_url'    => "quizzes.php?course_id={$course_id}&quiz_id={$quiz_id}",
        ]);

    } elseif ($action === 'generate_lesson_plan') {
        $aiPrompt = "Generate a structured 6-week lesson roadmap for a course titled '{$courseTitle}'.
        Theme/Topic: " . (empty($prompt) ? $courseDesc : $prompt) . "
        
        Format: JSON object only. No conversational text.
        Structure: {
          \"title\": \"Roadmap Title\", \"level\": \"Intermediate\", \"duration\": \"6 Weeks\",
          \"sections\": [
            { \"week\": \"Week 1\", \"topic\": \"Title\", \"objectives\": [\"obj1\"], \"activities\": [\"act1\"], \"assessment\": \"title\" }
          ]
        }";

        $response = callGemini($aiPrompt);
        $jsonStr  = preg_replace('/```json\n|\n```|```/', '', $response);
        $plan = json_decode($jsonStr, true);

        if (!$plan) throw new Exception("Failed to generate a valid curriculum roadmap.");

        echo json_encode([
            'success'  => true,
            'plan'     => $plan,
            'course'   => $courseTitle,
            'course_id' => $course_id,
            'message'  => "✅ Dynamic 6-week syllabus drafted!",
        ]);
    } elseif ($action === 'save_lesson_plan') {
        $course_id = (int)$_POST['course_id'];
        $planData  = json_decode($_POST['plan_json'] ?? '[]', true);
        
        if (empty($planData) || !$course_id) {
            throw new Exception("Incomplete data for saving lesson plan.");
        }

        $stmt = $pdo->prepare("INSERT INTO lessons (course_id, title, content, lesson_type, order_num) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($planData['sections'] as $i => $sec) {
            $content = "### Objectives\n" . implode("\n- ", $sec['objectives']) . 
                       "\n\n### Activities\n" . implode("\n- ", $sec['activities']) . 
                       "\n\n### Assessment\n" . $sec['assessment'];
            
            $stmt->execute([
                $course_id,
                "{$sec['week']}: {$sec['topic']}",
                $content,
                'text',
                $i + 1
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => "✅ 6-week syllabus successfully published to your course!",
            'course_url' => "courses.php?edit_id={$course_id}"
        ]);

    } else {
        // Return list of tutor's courses for the course selector
        $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE tutor_id = ? ORDER BY created_at DESC");
        $stmt->execute([$tutor['id']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'courses' => $courses]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
