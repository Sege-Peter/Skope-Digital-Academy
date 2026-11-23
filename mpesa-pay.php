<?php
/**
 * M-Pesa checkout API (JSON), used by checkout.php.
 *   POST  {type, id, mode, cohort, coupon, phone}  + X-CSRF-Token  -> sends the PIN prompt, returns {ok, request}
 *   GET   ?status=<request id>                                     -> {state: pending|success|failed|cancelled, message, go}
 * The amount is always worked out here on the server; nothing posted by the browser sets the price.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/payments.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
$out = function (array $d, int $code = 200): never { http_response_code($code); echo json_encode($d); exit; };

if (!isLoggedIn()) $out(['ok' => false, 'message' => 'Please sign in again.'], 401);
$user = currentUser();
$uid = (int)$user['id'];
sda_pay_ensure($pdo);

/* ── poll ── */
if (isset($_GET['status'])) {
    $st = $pdo->prepare("SELECT * FROM mpesa_requests WHERE id = ? AND user_id = ?");
    $st->execute([(int)$_GET['status'], $uid]);
    $r = $st->fetch();
    if (!$r) $out(['state' => 'failed', 'message' => 'Payment request not found.'], 404);

    if ($r['status'] === 'pending') {
        $age = time() - strtotime($r['created_at']);
        $sinceQuery = $r['last_query_at'] ? time() - strtotime($r['last_query_at']) : 999;
        if ($age > 180) {
            sda_pay_mark($pdo, (int)$r['id'], 'failed', 'No confirmation from M-Pesa after 3 minutes.');
        } elseif ($age >= 12 && $sinceQuery >= 8 && $r['checkout_id']) {
            // No callback yet: ask the provider directly
            $pdo->prepare("UPDATE mpesa_requests SET last_query_at = NOW() WHERE id = ?")->execute([$r['id']]);
            try {
                $res = $r['provider'] === 'daraja' ? daraja_query($pdo, $r['checkout_id']) : cyzora_query($r['checkout_id']);
                if ($res['state'] === 'success') {
                    // Daraja's query has no amount/receipt: the prompt was for exactly this amount
                    $paid = isset($res['amount']) && $res['amount'] !== null ? (float)$res['amount'] : (float)ceil((float)$r['amount']);
                    sda_pay_fulfil($pdo, (int)$r['id'], $paid, (string)($res['receipt'] ?? ''));
                } elseif ($res['state'] !== 'pending') {
                    sda_pay_mark($pdo, (int)$r['id'], $res['state'], $res['desc']);
                }
            } catch (Throwable $e) { error_log('[mpesa query] ' . $e->getMessage()); }
        }
        $st->execute([(int)$_GET['status'], $uid]);
        $r = $st->fetch();
    }
    if ($r['status'] === 'success') {
        $go = (string)$r['redirect'];
        if ($r['pay_type'] === 'registration' && str_starts_with($go, 'student/')) $go = sda_after_activation($go);   // back to "Enroll me" etc.
        $out(['state' => 'success', 'message' => 'Payment received' . ($r['receipt'] ? ' (' . $r['receipt'] . ')' : '') . '. Opening…', 'go' => sda_path($go)]);
    }
    if ($r['status'] === 'pending') $out(['state' => 'pending', 'message' => 'Check your phone and enter your M-Pesa PIN.']);
    $out(['state' => $r['status'], 'message' => $r['result_desc'] ?: 'The payment did not go through.']);
}

/* ── start a payment ── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $out(['ok' => false, 'message' => 'Method not allowed.'], 405);
if (!verifyCsrf()) $out(['ok' => false, 'message' => 'Your session expired. Reload the page and try again.'], 403);
$in = json_decode((string)file_get_contents('php://input'), true) ?: $_POST;

$provider = sda_mpesa_provider();
if ($provider === '') $out(['ok' => false, 'message' => 'M-Pesa payments are not set up yet. Please use another method or contact us.'], 503);

$phone = sda_mpesa_phone((string)($in['phone'] ?? ''));
if ($phone === '') $out(['ok' => false, 'message' => 'Enter a valid Safaricom number, e.g. 0712 345 678.'], 422);

require_once 'includes/security.php';
if (!sda_rate_ok('mpesa_push', 6, 10, 'uid:' . $uid)) $out(['ok' => false, 'message' => 'Too many payment prompts. Please wait a few minutes, then try again.'], 429);

$type = (string)($in['type'] ?? 'course');
$id = (int)($in['id'] ?? 0);
$q = sda_checkout_quote($pdo, $user, $type, $id, $in['mode'] ?? null, $in['cohort'] ?? null, (string)($in['coupon'] ?? ''));
if (!$q['ok']) $out(['ok' => false, 'message' => 'Nothing to pay here.', 'go' => $q['redirect'] ? sda_path($q['redirect']) : null], 409);

$token = bin2hex(random_bytes(16));
$pdo->prepare("INSERT INTO mpesa_requests (provider, user_id, pay_type, item_id, learning_mode, cohort_id, coupon, amount, phone, cb_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([$provider, $uid, $type, $id, $q['mode'] ?: null, $q['cohort_id'] ?: null, $q['coupon'] ? $q['coupon']['coupon']['code'] : null, $q['amount'], $phone, $token]);
$reqId = (int)$pdo->lastInsertId();
$req = ['id' => $reqId, 'amount' => $q['amount'], 'phone' => $phone, 'pay_type' => $type, 'item_id' => $id, 'cb_token' => $token];
$accountRef = 'SDA' . $reqId;

try {
    $checkoutId = $provider === 'daraja'
        ? daraja_stk_push($pdo, $req, sda_pay_public_url('mpesa-callback?t=' . $token), $accountRef)
        : cyzora_charge($req, $accountRef);
    $pdo->prepare("UPDATE mpesa_requests SET checkout_id = ?, updated_at = NOW() WHERE id = ?")->execute([$checkoutId, $reqId]);
} catch (Throwable $e) {
    sda_pay_mark($pdo, $reqId, 'failed', $e->getMessage());
    error_log('[mpesa push] ' . $e->getMessage());
    $out(['ok' => false, 'message' => 'We could not send the M-Pesa prompt: ' . $e->getMessage()], 502);
}
$out(['ok' => true, 'request' => $reqId, 'amount' => (int)ceil((float)$q['amount']), 'phone' => '0' . substr($phone, 3),
      'message' => 'Check your phone: enter your M-Pesa PIN to pay KES ' . number_format((int)ceil((float)$q['amount'])) . '.']);
