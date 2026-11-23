<?php
/** Uptime check for monitors: 200 "ok" when PHP and the database answer, 503 otherwise. No details leak. */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
try {
    require_once __DIR__ . '/includes/db.php';
    $pdo->query('SELECT 1')->fetchColumn();
    echo 'ok';
} catch (Throwable $e) {
    http_response_code(503);
    echo 'unavailable';
}
