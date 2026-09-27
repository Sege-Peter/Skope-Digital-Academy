<?php
error_reporting(0); // Prevent PHP warnings from breaking JSON
require_once '../includes/ai-handler.php'; // This already includes db.php

// Auth check
session_start();
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Authentication required']));
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? 'ask';
$query = $data['query'] ?? '';
$lesson = $data['lesson'] ?? 'General Academy Context';
$course_desc = $data['course_desc'] ?? 'Skope Digital Academy Curriculum';

try {
    if ($type === 'study_buddy') {
        $response = SDAC_AI::studyBuddy($lesson, $course_desc, $query);
        echo json_encode(['reply' => $response]);
    } else {
        $response = SDAC_AI::ask($query);
        echo json_encode(['reply' => $response]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
