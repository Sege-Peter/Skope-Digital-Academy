<?php
/**
 * Skope Digital Academy - High-Availability Academic AI Bridge
 */
ob_start();
error_reporting(0);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ai-handler.php';

if (!isLoggedIn()) {
    ob_clean();
    header('Content-Type: application/json');
    die(json_encode(['success' => false, 'error' => 'Institutional Access Denied']));
}

$user = currentUser();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'mentor_chat' || $action === 'study_buddy') {
        $query = trim($_POST['query'] ?? '');
        $context = trim($_POST['context'] ?? 'Skope Digital Academy Scholar Interaction');
        
        if (empty($query)) throw new Exception("Query registry is empty.");

        // Centralized SDAC AI Engine Call
        $response = SDAC_AI::ask($query, "You are the Dean of Scholarly Success at SDAC. Context: $context. Provide elite-grade, detailed, and encouraging guidance.");

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'response' => $response, 'reply' => $response]);
        exit;

    } elseif ($action === 'generate_syllabus') {
        $courseTitle = trim($_POST['title'] ?? 'Selected Track');
        
        $plan = [
            'success' => true,
            'plan' => [
                'title' => "High-Impact Roadmap: " . $courseTitle,
                'sections' => [
                    ['week' => 'Week 1', 'topic' => 'Foundational Principles', 'objectives' => ['Domain Overview', 'Core Logic'], 'activities' => ['Setup'], 'assessment' => 'Logic Check'],
                    ['week' => 'Week 2', 'topic' => 'Structural Integration', 'objectives' => ['Development Cycle'], 'activities' => ['Sprint 1'], 'assessment' => 'Mid-Term'],
                    ['week' => 'Final', 'topic' => 'Market Readiness', 'objectives' => ['Deployment'], 'activities' => ['Live Launch'], 'assessment' => 'Institutional Certificate'],
                ]
            ]
        ];
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($plan);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
ob_end_flush();
