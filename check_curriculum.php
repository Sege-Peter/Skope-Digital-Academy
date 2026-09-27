<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=skopedigital', 'root', '');
    $stmt = $pdo->query('DESCRIBE lessons');
    print_r($stmt->fetchAll());
    $stmt = $pdo->query('DESCRIBE quizzes');
    print_r($stmt->fetchAll());
} catch (Exception $e) { echo $e->getMessage(); }
