<?php
/**
 * Database Connection – Skope Digital Academy
 */

// Server-specific settings (live database login, API keys) live in includes/private/config.php,
// which is git-ignored and never uploaded over, so a deploy can't wipe the live login.
if (is_file(__DIR__ . '/private/config.php')) require_once __DIR__ . '/private/config.php';
defined('DB_HOST')    || define('DB_HOST', '127.0.0.1');
defined('DB_NAME')    || define('DB_NAME', 'skopedigital');
defined('DB_USER')    || define('DB_USER', 'root');
defined('DB_PASS')    || define('DB_PASS', '');
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// All dates and times on the site are Kenyan time (PHP's default here is Europe/Berlin).
date_default_timezone_set('Africa/Nairobi');


$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Same clock as PHP (Africa/Nairobi, UTC+3, no daylight saving): NOW() and timestamps match what pages show
    $pdo->exec("SET time_zone = '+03:00'");
} catch (PDOException $e) {
    // Don't expose credentials in production
    error_log('DB Connection failed: ' . $e->getMessage());
    if (PHP_SAPI !== 'cli' && !headers_sent()) http_response_code(503);   // monitors see the outage
    die(json_encode(['error' => 'Database connection failed. Please contact the administrator.']));
}

// AI (Gemini) key: never in this file (the repo is public). Admin > Settings > AI wins so it can be rotated
// without touching the server; otherwise SDA_GEMINI_KEY from includes/private/config.php.
if (!defined('GEMINI_API_KEY')) {
    $sdaGemini = '';
    try { $sdaGemini = trim((string)$pdo->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'gemini_api_key'")->fetchColumn()); } catch (Throwable $e) {}
    define('GEMINI_API_KEY', $sdaGemini !== '' ? $sdaGemini : (defined('SDA_GEMINI_KEY') ? SDA_GEMINI_KEY : ''));
    unset($sdaGemini);
}
if (!defined('GEMINI_MODEL')) {
    // Model: Admin > Settings > AI (gemini_model), else a current one (the 1.5 models are retired)
    $sdaModel = '';
    try { $sdaModel = trim((string)$pdo->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'gemini_model'")->fetchColumn()); } catch (Throwable $e) {}
    define('GEMINI_MODEL', preg_match('/^gemini-[\w.-]+$/', $sdaModel) && !str_starts_with($sdaModel, 'gemini-1.') ? $sdaModel : 'gemini-2.5-flash');
    unset($sdaModel);
}
defined('GEMINI_API_URL') || define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY);
