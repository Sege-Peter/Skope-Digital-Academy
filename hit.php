<?php
/** Page-view beacon from assets/js/stats.js. Stores no IP and sets no cookie (see includes/site_stats.php). */
require_once 'includes/db.php';
if (!empty($_COOKIE[session_name()])) require_once 'includes/auth.php';   // read the visitor's role only if already signed in
require_once 'includes/site_stats.php';
header('Cache-Control: no-store');

$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || sda_stats_is_bot($ua) || ($_SERVER['HTTP_DNT'] ?? '') === '1') { http_response_code(204); exit; }
$d = json_decode((string)file_get_contents('php://input', false, null, 0, 4096), true);
if (!is_array($d)) { http_response_code(204); exit; }

// Path only (never the query string, which can hold private links); course pages keep the course id.
$path = trim(rawurldecode(strtok((string)($d['p'] ?? ''), '?#')), '/');
$base = trim(str_replace('\\', '/', rawurldecode(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/hit.php')))), '/');   // the site's folder (empty on the live site)
if ($base !== '' && str_starts_with($path, $base)) $path = trim(substr($path, strlen($base)), '/');
$path = trim(preg_replace('~[^a-z0-9/_.-]~i', '', $path), '/');
$path = preg_replace('~\.php$~', '', $path) ?: 'home';
$path = preg_replace('~/index$~', '', $path);
if ($path === 'index' || $path === '') $path = 'home';
if (preg_match('~^(admin|api|includes|uploads)(/|$)~', $path) || mb_strlen($path) > 160) { http_response_code(204); exit; }
$item = null;
if ($path === 'course-details' && preg_match('/^\d{1,9}$/', (string)($d['id'] ?? ''))) $item = (string)$d['id'];

$refHost = mb_substr(strtolower((string)parse_url((string)($d['r'] ?? ''), PHP_URL_HOST)), 0, 120);
$utm = mb_substr(preg_replace('/[^\w.-]/', '', (string)($d['us'] ?? '')), 0, 60);
$camp = mb_substr(preg_replace('/[^\w .-]/', '', (string)($d['uc'] ?? '')), 0, 80);
[$device, $browser, $os] = sda_stats_ua($ua);
$country = strtoupper(substr(preg_replace('/[^A-Z]/i', '', (string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '')), 0, 2)) ?: null;
if ($country === 'XX' || $country === 'T1') $country = null;

try {
    sda_stats_ensure($pdo);
    $pdo->prepare("INSERT INTO site_visits (ts, day, path, item, source, ref_host, utm_source, utm_campaign, device, browser, os, country, visitor, role)
                   VALUES (NOW(), CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$path, $item, sda_stats_source($refHost, $utm), $refHost ?: null, $utm ?: null, $camp ?: null, $device, $browser, $os, $country, sda_stats_visitor(), $_SESSION['role'] ?? null]);
    if (random_int(1, 2000) === 1) $pdo->exec("DELETE FROM site_visits WHERE day < CURDATE() - INTERVAL 400 DAY");
} catch (Throwable $e) { error_log('[hit] ' . $e->getMessage()); }
http_response_code(204);
