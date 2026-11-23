<?php
/**
 * Safaricom Daraja STK push result (CallBackURL). Daraja does not sign callbacks, so each prompt gets its own
 * secret token in the URL (?t=...) and the CheckoutRequestID must match that prompt. Always answers 200 so
 * Safaricom does not retry.
 */
require_once 'includes/db.php';
require_once 'includes/payments.php';

header('Content-Type: application/json');
$ack = function (): never { echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']); exit; };

$raw = (string)file_get_contents('php://input');
$token = (string)($_GET['t'] ?? '');
$cb = json_decode($raw, true)['Body']['stkCallback'] ?? null;
if (!preg_match('/^[a-f0-9]{32}$/', $token) || !is_array($cb)) $ack();

sda_pay_ensure($pdo);
$st = $pdo->prepare("SELECT * FROM mpesa_requests WHERE cb_token = ? AND provider = 'daraja'");
$st->execute([$token]);
$r = $st->fetch();
if (!$r || !hash_equals((string)$r['checkout_id'], (string)($cb['CheckoutRequestID'] ?? ''))) {
    error_log('[mpesa callback] unmatched callback for token ' . substr($token, 0, 6));
    $ack();
}

$res = daraja_result_state((string)($cb['ResultCode'] ?? ''), (string)($cb['ResultDesc'] ?? ''));
if ($res['state'] === 'success') {
    $meta = [];
    foreach (($cb['CallbackMetadata']['Item'] ?? []) as $it) if (isset($it['Name'])) $meta[$it['Name']] = $it['Value'] ?? null;
    try {
        sda_pay_fulfil($pdo, (int)$r['id'], (float)($meta['Amount'] ?? ceil((float)$r['amount'])), (string)($meta['MpesaReceiptNumber'] ?? ''));
    } catch (Throwable $e) { error_log('[mpesa callback] ' . $e->getMessage()); }
} else {
    sda_pay_mark($pdo, (int)$r['id'], $res['state'], $res['desc']);
}
$ack();
