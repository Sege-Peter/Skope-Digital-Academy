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


// A PHP crash (fatal error) shows the friendly 500 page instead of a blank screen. Details stay in the log.
if (PHP_SAPI !== 'cli') {
    register_shutdown_function(function () {
        $e = error_get_last();
        if (!$e || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) || headers_sent()) return;
        while (ob_get_level() > 0) @ob_end_clean();
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            http_response_code(500); header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'server_error']); return;
        }
        $sda_error_code = 500;
        include __DIR__ . '/../error.php';
    });
}

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
    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    if (PHP_SAPI !== 'cli' && str_contains($accept, 'text/html')) { $sda_error_code = 503; include __DIR__ . '/../error.php'; exit; }
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

// ── Maintenance mode (Admin > Settings > General) ─────────────────────────────────────────
// Visitors see the "Back in a moment" page. Admins (signed in) keep using everything, and the
// sign-in pages, payment confirmations (Paystack, Daraja, Cyzora), cron and health checks keep working.
if (PHP_SAPI !== 'cli' && !defined('SDA_MAINTENANCE_CHECKED')) {
    define('SDA_MAINTENANCE_CHECKED', true);
    $sdaM = [];
    try { $sdaM = $pdo->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key IN ('maintenance_mode','maintenance_message','maintenance_until')")->fetchAll(PDO::FETCH_KEY_PAIR); } catch (Throwable $e) {}
    if (($sdaM['maintenance_mode'] ?? '0') === '1') {
        $sdaApp = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: '');
        $sdaScript = str_replace('\\', '/', realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '');
        $sdaRel = ltrim(substr($sdaScript, strlen($sdaApp)), '/');
        $sdaOpen = str_starts_with($sdaRel, 'admin/') || in_array($sdaRel, [
            'login.php', 'logout.php', 'two-factor.php', 'google-auth.php', 'forgot-password.php', 'reset-password.php', 'force_password_change.php',
            'impersonate.php', 'paystack-webhook.php', 'verify-payment.php', 'mpesa-callback.php', 'cyzora-webhook.php', 'healthz.php', 'cron.php', 'error.php',
        ], true);
        if (!$sdaOpen) {
            $sdaAdmin = false;
            if (!empty($_COOKIE[session_name()])) {
                require_once __DIR__ . '/auth.php';          // starts the session the normal way
                $sdaAdmin = ($_SESSION['role'] ?? '') === 'admin' || !empty($_SESSION['admin_impersonator_id']) || ($_SESSION['portal_home_role'] ?? '') === 'admin';
            }
            if (!$sdaAdmin) {
                $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
                if (!headers_sent()) { http_response_code(503); header('Retry-After: 600'); }
                if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'maintenance', 'message' => 'The academy is being updated. Please try again shortly.']);
                    exit;
                }
                $sda_error_code = 503;
                $sda_maint = ['message' => trim((string)($sdaM['maintenance_message'] ?? '')), 'until' => trim((string)($sdaM['maintenance_until'] ?? ''))];
                include __DIR__ . '/../error.php';
                exit;
            }
        }
    }
    unset($sdaM);
}
