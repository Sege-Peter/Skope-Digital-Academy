<?php
require_once 'includes/db.php';
$dbname = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "Active Database: $dbname\n";
$stmt = $pdo->query("SHOW COLUMNS FROM users");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo "Field: {$col['Field']}, Type: {$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']}, Default: {$col['Default']}, Extra: {$col['Extra']}\n";
}
