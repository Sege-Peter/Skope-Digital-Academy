<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=skopedigital', 'root', '');
    $stmt = $pdo->query('DESCRIBE notifications');
    $cols = $stmt->fetchAll();
    print_r($cols);
} catch (Exception $e) { echo $e->getMessage(); }
