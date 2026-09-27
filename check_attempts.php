<?php
$pdo = new PDO('mysql:host=localhost;dbname=skopedigital', 'root', '');
$stmt = $pdo->query("DESCRIBE quiz_attempts");
print_r($stmt->fetchAll());
