<?php
/**
 * Cyzora webhooks (add https://<site>/cyzora-webhook on the Webhooks page at app.cyzora.co.ke and paste the
 * whsec_ secret into Admin > Settings > Payments). Signature: X-Cyzora-Signature = hex HMAC-SHA256 of the raw body.
 */
require_once 'includes/db.php';
require_once 'includes/payments.php';

header('Content-Type: application/json');
$raw = (string)file_get_contents('php://input');
if (!cyzora_signature_ok($raw, (string)($_SERVER['HTTP_X_CYZORA_SIGNATURE'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['error' => 'bad signature']);
    exit;
}
$ev = json_decode($raw, true) ?: [];
$event = (string)($ev['event'] ?? ($_SERVER['HTTP_X_CYZORA_EVENT'] ?? ''));
$chargeId = (string)($ev['charge_id'] ?? $ev['id'] ?? '');
$reqId = (int)($ev['metadata']['sda_request'] ?? 0);

if ($event === 'webhook.test' || $chargeId === '') { echo json_encode(['ok' => true]); exit; }

sda_pay_ensure($pdo);
$st = $pdo->prepare("SELECT * FROM mpesa_requests WHERE provider = 'cyzora' AND checkout_id = ?" . ($reqId ? " AND id = $reqId" : ''));
$st->execute([$chargeId]);
$r = $st->fetch();
if (!$r) { echo json_encode(['ok' => true, 'note' => 'unknown charge']); exit; }

try {
    if ($event === 'charge.succeeded' || ($ev['status'] ?? '') === 'succeeded') {
        sda_pay_fulfil($pdo, (int)$r['id'], isset($ev['amount']) ? ((int)$ev['amount']) / 100 : (float)ceil((float)$r['amount']), (string)($ev['mpesa_receipt'] ?? ''));
    } elseif (in_array($event, ['charge.failed', 'charge.expired'], true)) {
        sda_pay_mark($pdo, (int)$r['id'], 'failed', ($ev['status'] ?? '') === 'timeout' ? 'The M-Pesa prompt timed out before the PIN was entered.' : 'M-Pesa could not complete the payment.');
    }
} catch (Throwable $e) {
    error_log('[cyzora webhook] ' . $e->getMessage());
    http_response_code(500);           // Cyzora retries later
    echo json_encode(['error' => 'retry']);
    exit;
}
echo json_encode(['ok' => true]);
