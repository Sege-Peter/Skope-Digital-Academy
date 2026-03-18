<?php
require_once 'includes/db.php';

try {
    // 1. Update quizzes table
    $pdo->exec("ALTER TABLE quizzes ADD COLUMN is_cat TINYINT(1) DEFAULT 0 AFTER pass_score");
    echo "Added is_cat to quizzes table.\n";

    // 2. Update quiz_questions table
    $pdo->exec("ALTER TABLE quiz_questions ADD COLUMN type ENUM('mcq', 'tf', 'text') DEFAULT 'mcq' AFTER question");
    echo "Added type to quiz_questions table.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
