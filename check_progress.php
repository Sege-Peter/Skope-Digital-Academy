<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=skopedigital', 'root', '');
    $stmt = $pdo->query('DESCRIBE lesson_progress');
    print_r($stmt->fetchAll());
} catch (Exception $e) { echo $e->getMessage(); }
