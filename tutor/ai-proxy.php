<?php
error_reporting(0); // Prevent PHP warnings from breaking JSON
require_once '../includes/ai-handler.php'; // This already includes db.php

// Auth check
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'tutor') {
    die(json_encode(['error' => 'Faculty Authorization Required']));
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$topic = $data['topic'] ?? '';

if (empty($topic)) {
    die(json_encode(['error' => 'Topic is required for quiz generation']));
}

try {
    // Generate 5 questions via AI
    $questions = SDAC_AI::generateQuiz($topic, 5);
    echo json_encode(['questions' => $questions]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
