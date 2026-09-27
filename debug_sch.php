<?php
require_once 'includes/db.php';
try {
    $stmt = $pdo->query("SELECT * FROM scholarships");
    $all = $stmt->fetchAll();
    echo "Total scholarships in DB: " . count($all) . "\n";
    foreach($all as $s) {
        echo "ID: {$s['id']} | Title: {$s['title']} | Expiry: {$s['expiry_date']}\n";
    }
    
    $stmt = $pdo->query("SELECT * FROM scholarships WHERE expiry_date >= CURRENT_DATE OR expiry_date IS NULL ORDER BY created_at DESC LIMIT 1");
    $featured = $stmt->fetch();
    echo "\nFeatured match: " . ($featured ? $featured['title'] : "None") . "\n";
    
    echo "\nCurrent DATE: " . date('Y-m-d') . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
