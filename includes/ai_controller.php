<?php
/**
 * Skope Digital Academy - Academic AI Controller
 * Handles logic for dynamic mentorship and course generation
 */
require_once 'db.php';
require_once 'auth.php';
require_once 'ai_config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = currentUser();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'mentor_chat') {
        $query = trim($_POST['query'] ?? '');
        $context = trim($_POST['context'] ?? '');

        if (empty($query)) throw new Exception("Query is required.");

        $systemPrompt = "You are the 'SDA Official Academic Mentor' for Skope Digital Academy. 
        Current User: {$user['name']} (Role: {$user['role']}).
        Context: Students use this platform for skill-based certificates in tech, business, and design.
        Academic Context: {$context}
        
        Guidelines:
        1. Keep responses professional, encouraging, and focused on Kenyan market practicalities.
        2. If a student is stuck, offer specific learning strategies.
        3. Do not answer questions unrelated to education, career, or academy courses.
        4. Refer to the current user by name occasionally.
        
        Keep responses concise and well-formatted with markdown.";

        $fullPrompt = "{$systemPrompt}\n\nStudent says: {$query}";
        $response = callGemini($fullPrompt);

        if ($response) {
            echo json_encode(['success' => true, 'response' => $response]);
        } else {
            // Get the last error from PHP error log conceptually, or just return a better generic message
            // In a real dev env, we might check a global error container
            throw new Exception("The AI Engine is reaching its capacity limit or the API key is restricted. Please check your credentials.");
        }

    } elseif ($action === 'generate_syllabus') {
        if ($user['role'] !== 'tutor' && $user['role'] !== 'admin') throw new Exception("Unauthorized role.");

        $courseTitle = trim($_POST['title'] ?? '');
        $courseDesc = trim($_POST['description'] ?? '');

        $prompt = "Act as an expert curriculum designer. Generate a structured 6-week syllabus for a course titled '{$courseTitle}'.
        Description: {$courseDesc}
        Format needed: JSON only. No text before or after.
        JSON Structure: {
          \"title\": \"Course Roadmap\",
          \"sections\": [
            { \"week\": \"Week 1\", \"topic\": \"Title\", \"objectives\": [\"obj1\", \"obj2\"], \"activities\": [\"act1\", \"act2\"], \"assessment\": \"name\" }
          ]
        }
        Create exactly 6 weeks of content.";

        $response = callGemini($prompt);
        // Clean markdown from response if present
        $jsonStr = preg_replace('/```json\n|\n```|```/', '', $response);
        $json = json_decode($jsonStr, true);

        if ($json) {
            echo json_encode(['success' => true, 'plan' => $json]);
        } else {
            throw new Exception("Failed to generate valid JSON syllabus.");
        }

    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
