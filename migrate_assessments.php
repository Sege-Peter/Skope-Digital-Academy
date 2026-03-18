<?php
require_once 'includes/db.php';

try {
    // 1. Convert is_cat to assessment_type
    // First add the new column
    $pdo->exec("ALTER TABLE quizzes ADD COLUMN type ENUM('quiz', 'cat', 'final') DEFAULT 'quiz' AFTER pass_score");
    
    // Migrate existing is_cat values
    $pdo->exec("UPDATE quizzes SET type = 'cat' WHERE is_cat = 1");
    $pdo->exec("UPDATE quizzes SET type = 'quiz' WHERE is_cat = 0");

    // Remove old column
    $pdo->exec("ALTER TABLE quizzes DROP COLUMN is_cat");

    echo "Assessment types synchronized to [Quiz, CAT, Final].\n";

} catch (Exception $e) {
    echo "Sync Failed: " . $e->getMessage() . "\n";
}
