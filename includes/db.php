<?php
/**
 * Database Connection – Skope Digital Academy
 */

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'skopedigital');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 🤖 SDAC AI Engine configuration (Gemini 1.5 Flash - High Speed & Availability)
define('GEMINI_API_KEY', 'AIzaSyCFTZMZYhdV564f2ybDAjG4_nBI2R01XQo');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . GEMINI_API_KEY);

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Don't expose credentials in production
    error_log('DB Connection failed: ' . $e->getMessage());
    die(json_encode(['error' => 'Database connection failed. Please contact the administrator.']));
}
