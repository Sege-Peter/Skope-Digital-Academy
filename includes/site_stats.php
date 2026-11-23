<?php
/**
 * Privacy-friendly site analytics (no cookies, no IP addresses stored).
 *  - site_visits: one row per page view, sent by assets/js/stats.js to hit.php. A visitor is a daily-rotating
 *    hash of (IP + browser), so the same person can't be followed across days and no IP is kept.
 *  - search_log: what people search for on Courses and Tutorials, with the number of results
 *    (zero-result searches show demand for courses the academy doesn't offer yet).
 * Reported in admin/analytics.php.
 */
require_once __DIR__ . '/learning.php';

function sda_stats_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (setting('stats_schema') === '1') return;
    if ($pdo->inTransaction()) { $done = false; return; }
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        ts DATETIME NOT NULL, day DATE NOT NULL,
        path VARCHAR(160) NOT NULL, item VARCHAR(40) NULL,
        source VARCHAR(30) NOT NULL DEFAULT 'Direct', ref_host VARCHAR(120) NULL,
        utm_source VARCHAR(60) NULL, utm_campaign VARCHAR(80) NULL,
        device VARCHAR(10) NOT NULL DEFAULT 'desktop', browser VARCHAR(20) NULL, os VARCHAR(20) NULL,
        country CHAR(2) NULL, visitor CHAR(16) NOT NULL, role VARCHAR(10) NULL,
        KEY idx_day (day), KEY idx_path (path, day), KEY idx_visitor (day, visitor)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS search_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        ts DATETIME NOT NULL, day DATE NOT NULL,
        q VARCHAR(120) NOT NULL, source VARCHAR(20) NOT NULL, results INT NOT NULL DEFAULT 0,
        visitor CHAR(16) NULL, role VARCHAR(10) NULL,
        KEY idx_day (day), KEY idx_q (q)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (setting('stats_secret') === '') save_setting($pdo, 'stats_secret', bin2hex(random_bytes(32)));
    save_setting($pdo, 'stats_schema', '1');
}

/** Anonymous visitor id for today only. */
function sda_stats_visitor(): string
{
    $ip = function_exists('sda_client_ip') ? sda_client_ip() : (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    $secret = setting('stats_secret') ?: 'sda';
    return substr(hash_hmac('sha256', date('Y-m-d') . '|' . $ip . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''), $secret), 0, 16);
}

function sda_stats_is_bot(string $ua): bool
{
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp\/|headless|lighthouse|curl|wget|python|httpclient|monitor|uptime|scan|fetch/i', $ua);
}

/** [device, browser, os] from the User-Agent. */
function sda_stats_ua(string $ua): array
{
    $device = preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua) ? 'tablet' : (preg_match('/Mobi|iPhone|iPod|Android|Opera Mini|IEMobile/i', $ua) ? 'mobile' : 'desktop');
    $browser = match (true) {
        (bool)preg_match('/Edg\//', $ua) => 'Edge',
        (bool)preg_match('/OPR\/|Opera/', $ua) => 'Opera',
        (bool)preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
        (bool)preg_match('/Firefox\//', $ua) => 'Firefox',
        (bool)preg_match('/Chrome\//', $ua) => 'Chrome',
        (bool)preg_match('/Safari\//', $ua) => 'Safari',
        default => 'Other',
    };
    $os = match (true) {
        (bool)preg_match('/Android/', $ua) => 'Android',
        (bool)preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
        (bool)preg_match('/Windows/', $ua) => 'Windows',
        (bool)preg_match('/Mac OS X|Macintosh/', $ua) => 'macOS',
        (bool)preg_match('/Linux|CrOS/', $ua) => 'Linux',
        default => 'Other',
    };
    return [$device, $browser, $os];
}

/** Friendly traffic source from the referrer host and utm_source. */
function sda_stats_source(string $refHost, string $utm): string
{
    $h = strtolower($refHost . ' ' . $utm);
    $own = strtolower(parse_url(setting('site_url') ?: 'https://skopedigital.co.ke', PHP_URL_HOST) ?: 'skopedigital.co.ke');
    if (trim($h) === '' || str_contains($h, $own)) return 'Direct';
    foreach (['google' => 'Google', 'bing' => 'Bing', 'yahoo' => 'Yahoo', 'duckduckgo' => 'DuckDuckGo', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 'instagram' => 'Instagram',
              'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp', 't.co' => 'X (Twitter)', 'twitter' => 'X (Twitter)', 'x.com' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'lnkd' => 'LinkedIn',
              'tiktok' => 'TikTok', 'youtube' => 'YouTube', 'telegram' => 'Telegram', 'chatgpt' => 'ChatGPT', 'mail' => 'Email', 'email' => 'Email', 'newsletter' => 'Email'] as $k => $v)
        if (str_contains($h, $k)) return $v;
    return 'Other websites';
}

/** Record a search (Courses / Tutorials). Never throws. */
function sda_stats_search(PDO $pdo, string $q, string $source, int $results): void
{
    try {
        $q = mb_strtolower(trim(preg_replace('/\s+/', ' ', $q)));
        if ($q === '' || mb_strlen($q) > 120 || sda_stats_is_bot((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))) return;
        sda_stats_ensure($pdo);
        // One row per visitor + query + day (refreshing the page doesn't count twice)
        $v = sda_stats_visitor();
        $ex = $pdo->prepare("SELECT 1 FROM search_log WHERE day = CURDATE() AND visitor = ? AND q = ? AND source = ? LIMIT 1");
        $ex->execute([$v, $q, $source]);
        if ($ex->fetchColumn()) return;
        $pdo->prepare("INSERT INTO search_log (ts, day, q, source, results, visitor, role) VALUES (NOW(), CURDATE(), ?, ?, ?, ?, ?)")
            ->execute([$q, $source, $results, $v, $_SESSION['role'] ?? null]);
    } catch (Throwable $e) { error_log('[stats search] ' . $e->getMessage()); }
}
