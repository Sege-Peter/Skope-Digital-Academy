<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=skopedigital', 'root', '');
    
    // 1. Lessons: Week number and Resources
    $pdo->exec("ALTER TABLE lessons ADD COLUMN week_num INT DEFAULT 1");
    $pdo->exec("ALTER TABLE lessons ADD COLUMN pdf_resource VARCHAR(255) NULL");
    
    // 2. Quizzes: Link to Lesson or Week
    $pdo->exec("ALTER TABLE quizzes ADD COLUMN linked_lesson_id INT NULL");
    $pdo->exec("ALTER TABLE quizzes ADD COLUMN linked_week_num INT NULL");
    
    echo "Curriculum structure enhanced successfully!";
} catch (Exception $e) { echo $e->getMessage(); }
