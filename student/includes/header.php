<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Access control: Student only
requireRole('student');

$student = currentUser();

// Fetch student extra info (points, etc)
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student['id']]);
    $student_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($student_info) {
        $student = array_merge($student_info, $student);
    }
} catch (Exception $e) { $student_info = []; }
$user = $student; // Shared variable for sidebar compatibility
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'My Learning' ?> – SDAC Academy</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css"> <!-- Consistent dashboard look -->
    
    <link rel="icon" type="image/png" href="../assets/images/Skope Digital  logo.png">
    
    <style>
        /* Neutralize dark-themed admin header for light student dashboard */
        .admin-header {
            background: transparent !important;
            border-bottom: 2px solid var(--dark-border);
            backdrop-filter: none !important;
        }
        .admin-header h1 {
            color: var(--dark) !important;
        }
    </style>
</head>
<body class="dashboard-body">

