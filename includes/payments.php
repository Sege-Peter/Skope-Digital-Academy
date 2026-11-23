<?php
/**
 * M-Pesa payments for Skope Digital Academy: Safaricom Daraja (Lipa na M-Pesa STK push) and Cyzora (STK charges).
 * Keys are entered by an admin in Admin > Settings > Payments (never in code).
 *
 * Flow: checkout -> mpesa-pay (POST) creates an mpesa_requests row and sends the PIN prompt to the phone
 *       -> confirmation arrives by callback (mpesa-callback / cyzora-webhook) or by checkout polling the provider
 *       -> sda_pay_fulfil() records the payment and enrols / unlocks / activates, exactly once.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';
require_once __DIR__ . '/coupons.php';

/* ── settings ───────────────────────────────────────────────────────────── */

/** Which M-Pesa provider checkout uses: 'daraja', 'cyzora' or '' (none set up). */
function sda_mpesa_provider(): string
{
    $pref = setting('mpesa_provider') ?: 'auto';
    $daraja = setting('daraja_enabled') !== '0' && setting('daraja_consumer_key') !== '' && setting('daraja_consumer_secret') !== ''
           && setting('daraja_shortcode') !== '' && setting('daraja_passkey') !== '';
    $cyzora = setting('cyzora_enabled') !== '0' && preg_match('/^sk_(live|test)_/', setting('cyzora_secret_key'));
    if ($pref === 'daraja') return $daraja ? 'daraja' : ($cyzora ? 'cyzora' : '');
    if ($pref === 'cyzora') return $cyzora ? 'cyzora' : ($daraja ? 'daraja' : '');
    return $daraja ? 'daraja' : ($cyzora ? 'cyzora' : '');
}

/** Is card / Paystack checkout available? */
function sda_paystack_on(): bool
{
    require_once __DIR__ . '/paystack.php';
    return setting('paystack_enabled') !== '0' && PAYSTACK_PUBLIC_KEY !== '' && PAYSTACK_SECRET_KEY !== '';
}

/** 07xx / 01xx / +2547xx / 2547xx -> 2547XXXXXXXX (or '' if not a Kenyan mobile number). */
function sda_mpesa_phone(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw);
    if (preg_match('/^0([71]\d{8})$/', $d, $m)) return '254' . $m[1];
    if (preg_match('/^254([71]\d{8})$/', $d, $m)) return '254' . $m[1];
    if (preg_match('/^([71]\d{8})$/', $d, $m)) return '254' . $m[1];
    return '';
}

function sda_pay_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (setting('payments_schema') === '1') return;
    if ($pdo->inTransaction()) { $done = false; return; }
    $pdo->exec("CREATE TABLE IF NOT EXISTS mpesa_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(20) NOT NULL, user_id INT NOT NULL,
        pay_type VARCHAR(20) NOT NULL, item_id INT NOT NULL DEFAULT 0,
        learning_mode VARCHAR(20) NULL, cohort_id INT NULL, coupon VARCHAR(40) NULL,
        amount DECIMAL(10,2) NOT NULL, phone VARCHAR(20) NOT NULL,
        checkout_id VARCHAR(100) NULL, cb_token CHAR(32) NOT NULL,
        status ENUM('pending','success','failed','cancelled') NOT NULL DEFAULT 'pending',
        receipt VARCHAR(60) NULL, result_desc VARCHAR(255) NULL, paid_amount DECIMAL(10,2) NULL,
        payment_id INT NULL, redirect VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL, last_query_at DATETIME NULL, fulfilled_at DATETIME NULL,
        UNIQUE KEY uq_checkout (provider, checkout_id), KEY idx_user (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    save_setting($pdo, 'payments_schema', '1');
}

function sda_pay_http(string $method, string $url, array $headers, ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, json_decode((string)$raw, true) ?: [], $err ?: ($raw === false ? 'no response' : '')];
}

/* ── what is being paid for (same rules as checkout.php) ───────────────── */

/**
 * Work out what is due for a checkout. Returns ['ok', 'amount', 'full_amount', 'title', 'desc', 'mode', 'cohort_id',
 * 'coupon' (coupon_check result or null), 'course', 'cert', 'redirect' (when nothing is payable here)].
 */
function sda_checkout_quote(PDO $pdo, array $user, string $type, int $id, $modeIn, $cohortIn, string $couponCode): array
{
    $q = ['ok' => false, 'amount' => 0.0, 'full_amount' => 0.0, 'title' => 'Payment', 'desc' => '', 'mode' => '', 'cohort_id' => 0,
          'coupon' => null, 'course' => null, 'cert' => null, 'redirect' => ''];
    if ($type === 'course') {
        $st = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND status = 'published'");
        $st->execute([$id]);
        $course = $st->fetch();
        if (!$course) return ['redirect' => 'courses'] + $q;
        [$mode, $cohort, $err] = resolve_mode_choice($pdo, $course, $modeIn, $cohortIn);
        if ($err) return ['redirect' => 'enroll?id=' . $id] + $q;
        $q['course'] = $course; $q['mode'] = $mode; $q['cohort_id'] = (int)$cohort;
        $q['amount'] = amount_due($course, $mode, get_enrollment($pdo, (int)$user['id'], $id));
        $q['title'] = 'Payment for ' . $course['title'];
        $q['desc'] = mode_label($mode) . ' – ' . $course['title'];
    } elseif ($type === 'certificate') {
        $st = $pdo->prepare("SELECT c.*, co.title AS course_title FROM certificates c LEFT JOIN courses co ON co.id = c.course_id WHERE c.id = ? AND c.student_id = ?");
        $st->execute([$id, $user['id']]);
        $cert = $st->fetch();
        if (!$cert) return ['redirect' => 'student/achievements'] + $q;
        if ($cert['status'] !== 'pending') return ['redirect' => 'certificate?code=' . rawurlencode($cert['verification_code'])] + $q;
        $q['cert'] = $cert;
        $q['amount'] = (float)$cert['fee'];
        $q['title'] = 'Unlock your certificate';
        $q['desc'] = 'Certificate for ' . $cert['course_title'];
    } elseif ($type === 'registration') {
        $q['amount'] = (float)setting('registration_fee');
        $q['title'] = 'Registration Fee';
        $q['desc'] = 'Account activation fee for ' . $user['name'];
    } else {
        return ['redirect' => './'] + $q;
    }
    $q['full_amount'] = $q['amount'];
    $couponCode = coupon_normalize($couponCode);
    if ($couponCode !== '' && in_array($type, ['course', 'certificate'], true) && $q['amount'] > 0) {
        $cc = coupon_check($pdo, $couponCode, (int)$user['id'], $type,
                           $type === 'course' ? $id : ($q['cert']['course_id'] ? (int)$q['cert']['course_id'] : null), $q['amount']);
        if (!$cc['ok'] || $cc['final'] <= 0) {
            $q['redirect'] = $type === 'course'
                ? "enroll?id=$id&mode=" . urlencode($q['mode']) . "&cohort={$q['cohort_id']}&coupon=" . urlencode($couponCode)
                : 'certificate-pay?code=' . rawurlencode($q['cert']['verification_code']) . '&coupon=' . urlencode($couponCode);
            return $q;
        }
        $q['coupon'] = $cc;
        $q['amount'] = $cc['final'];
    }
    $q['ok'] = $q['amount'] > 0;
    return $q;
}

/* ── after a confirmed payment: record it and deliver, once ─────────────── */

/**
 * Record a confirmed M-Pesa payment for request $req and enrol / unlock / activate. Safe to call many times
 * (callback, webhook and polling can all arrive): only the first call does anything. Returns the page to send
 * the learner to.
 */
function sda_pay_fulfil(PDO $pdo, int $reqId, float $paid, string $receipt): string
{
    require_once __DIR__ . '/notify.php';
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT * FROM mpesa_requests WHERE id = ? FOR UPDATE");
        $st->execute([$reqId]);
        $req = $st->fetch();
        if (!$req) { $pdo->rollBack(); return './'; }
        if ($req['fulfilled_at']) { $pdo->commit(); return (string)$req['redirect']; }

        $uid = (int)$req['user_id'];
        $us = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $us->execute([$uid]);
        $user = $us->fetch();
        $ref = strtoupper($req['provider']) . ':' . ($receipt !== '' ? $receipt : $req['checkout_id']);
        $label = 'M-Pesa' . ($receipt !== '' ? ' receipt ' . $receipt : '') . ' via ' . ucfirst($req['provider']);
        $type = $req['pay_type'];
        $item = (int)$req['item_id'];
        $redirect = './';
        $paymentId = null;

        // A provider reference is only ever credited once
        $dup = $pdo->prepare("SELECT id FROM payments WHERE paystack_reference = ?");
        $dup->execute([$ref]);
        if ($dup->fetchColumn()) {
            $pdo->prepare("UPDATE mpesa_requests SET status = 'success', fulfilled_at = NOW(), updated_at = NOW() WHERE id = ?")->execute([$reqId]);
            $pdo->commit();
            return $type === 'course' ? "student/classroom?id=$item" : 'student/';
        }

        if ($type === 'course') {
            $c = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $c->execute([$item]);
            $course = $c->fetch();
            if (!$course || !$user) throw new RuntimeException("M-Pesa payment for unknown course $item / user $uid");
            [$mode, $cohort] = resolve_mode_choice($pdo, $course, $req['learning_mode'], $req['cohort_id']);
            $due = amount_due($course, $mode, get_enrollment($pdo, $uid, $item));
            $cp = $req['coupon'] ? coupon_check($pdo, (string)$req['coupon'], $uid, 'course', $item, $due) : null;
            $cp = ($cp && $cp['ok']) ? $cp : null;
            if ($cp) $due = $cp['final'];
            $ok = $paid + 0.001 >= floor($due);       // M-Pesa takes whole shillings
            $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at, learning_mode, cohort_id, coupon_id, discount)
                           VALUES (?, ?, ?, ?, ?, ?, 'course', IF(? = 'verified', NOW(), NULL), ?, ?, ?, ?)")
                ->execute([$uid, $item, $paid, $ok ? 'verified' : 'pending', $label . ($ok ? '' : " – paid $paid, due $due; needs review"), $ref,
                           $ok ? 'verified' : 'pending', $mode, $cohort, $cp ? (int)$cp['coupon']['id'] : null, $cp ? $cp['discount'] : 0]);
            $paymentId = (int)$pdo->lastInsertId();
            if ($ok) {
                enroll_student($pdo, $uid, $course, $mode, $cohort, $paid);
                coupon_redeem_payment($pdo, $paymentId);
                $redirect = "student/classroom?id=$item&welcome=1";
            } else {
                $redirect = "enroll?id=$item";
            }
            $after = function () use ($pdo, $uid, $course, $mode, $cohort, $paid, $ref, $ok) { if ($ok) sda_notify_enrolled($pdo, $uid, $course, $mode, $cohort, $paid, $ref); };
        } elseif ($type === 'certificate') {
            require_once __DIR__ . '/progress.php';
            $c = $pdo->prepare("SELECT * FROM certificates WHERE id = ? AND student_id = ?");
            $c->execute([$item, $uid]);
            $cert = $c->fetch();
            if (!$cert) throw new RuntimeException("M-Pesa payment for unknown certificate $item");
            $fee = (float)$cert['fee'];
            $cp = $req['coupon'] && $cert['status'] === 'pending'
                ? coupon_check($pdo, (string)$req['coupon'], $uid, 'certificate', $cert['course_id'] ? (int)$cert['course_id'] : null, $fee) : null;
            $cp = ($cp && $cp['ok']) ? $cp : null;
            $off = $cp ? $cp['discount'] : 0.0;
            $ok = $paid + $off + 0.001 >= floor($fee);
            $pdo->prepare("INSERT INTO payments (student_id, course_id, amount, status, transaction_message, paystack_reference, payment_type, certificate_id, verified_at, coupon_id, discount)
                           VALUES (?, ?, ?, ?, ?, ?, 'certificate', ?, IF(? = 'verified', NOW(), NULL), ?, ?)")
                ->execute([$uid, $cert['course_id'], $paid, $ok ? 'verified' : 'pending', 'Certificate fee – ' . $label . ($ok ? '' : " – paid $paid, fee $fee; needs review"),
                           $ref, $cert['id'], $ok ? 'verified' : 'pending', $cp ? (int)$cp['coupon']['id'] : null, $off]);
            $paymentId = (int)$pdo->lastInsertId();
            if ($ok) { unlock_certificate($pdo, (int)$cert['id'], $paid + $off); coupon_redeem_payment($pdo, $paymentId); }
            $redirect = ($ok ? 'certificate?code=' : 'certificate-pay?code=') . rawurlencode($cert['verification_code']);
            $after = fn() => null;
        } elseif ($type === 'registration') {
            $fee = (float)setting('registration_fee');
            $ok = $paid + 0.001 >= floor($fee);
            $pdo->prepare("INSERT INTO payments (student_id, amount, status, transaction_message, paystack_reference, payment_type, verified_at)
                           VALUES (?, ?, ?, ?, ?, 'registration', IF(? = 'verified', NOW(), NULL))")
                ->execute([$uid, $paid, $ok ? 'verified' : 'pending', 'Registration fee – ' . $label . ($ok ? '' : " – paid $paid, fee $fee; needs review"), $ref, $ok ? 'verified' : 'pending']);
            $paymentId = (int)$pdo->lastInsertId();
            if ($ok) $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$uid]);
            $redirect = $ok ? 'student/?msg=account_activated' : 'checkout?type=registration';
            $after = function () use ($uid, $paid, $ref, $ok) { if ($ok) sda_notify_activated($uid, $paid, $ref); };
        } else {
            throw new RuntimeException("Unknown M-Pesa payment type $type");
        }

        $pdo->prepare("UPDATE mpesa_requests SET status = 'success', receipt = COALESCE(?, receipt), paid_amount = ?, payment_id = ?, redirect = ?, fulfilled_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$receipt !== '' ? $receipt : null, $paid, $paymentId, $redirect, $reqId]);
        $pdo->commit();
        try { $after(); } catch (Throwable $e) { error_log('[mpesa notify] ' . $e->getMessage()); }
        return $redirect;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[mpesa fulfil] ' . $e->getMessage());
        throw $e;
    }
}

function sda_pay_mark(PDO $pdo, int $reqId, string $status, string $desc): void
{
    $pdo->prepare("UPDATE mpesa_requests SET status = ?, result_desc = ?, updated_at = NOW() WHERE id = ? AND fulfilled_at IS NULL AND status = 'pending'")
        ->execute([$status, mb_substr($desc, 0, 255), $reqId]);
}

/* ── Safaricom Daraja (Lipa na M-Pesa Online / STK push) ─────────────────── */

function daraja_base(): string
{
    return setting('daraja_env') === 'live' ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
}

function daraja_token(PDO $pdo): string
{
    $cached = setting('daraja_token_cache');
    if ($cached !== '') {
        [$tok, $exp, $env] = array_pad(explode('|', $cached, 3), 3, '');
        if ((int)$exp > time() + 60 && $env === daraja_base()) return $tok;
    }
    [$code, $j, $err] = sda_pay_http('GET', daraja_base() . '/oauth/v1/generate?grant_type=client_credentials',
        ['Authorization: Basic ' . base64_encode(setting('daraja_consumer_key') . ':' . setting('daraja_consumer_secret'))]);
    if ($code !== 200 || empty($j['access_token'])) throw new RuntimeException('Daraja sign-in failed (' . ($j['errorMessage'] ?? $err ?: "HTTP $code") . '). Check the consumer key and secret.');
    save_setting($pdo, 'daraja_token_cache', $j['access_token'] . '|' . (time() + (int)($j['expires_in'] ?? 3599)) . '|' . daraja_base());
    return $j['access_token'];
}

function daraja_password(string $ts): string
{
    return base64_encode(setting('daraja_shortcode') . setting('daraja_passkey') . $ts);
}

/** Send the PIN prompt. Returns CheckoutRequestID. */
function daraja_stk_push(PDO $pdo, array $req, string $callbackUrl, string $accountRef): string
{
    $ts = (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('YmdHis');
    $till = setting('daraja_type') === 'till';
    $body = [
        'BusinessShortCode' => setting('daraja_shortcode'),
        'Password' => daraja_password($ts),
        'Timestamp' => $ts,
        'TransactionType' => $till ? 'CustomerBuyGoodsOnline' : 'CustomerPayBillOnline',
        'Amount' => (int)ceil((float)$req['amount']),
        'PartyA' => $req['phone'],
        'PartyB' => $till ? (setting('daraja_till') ?: setting('daraja_shortcode')) : setting('daraja_shortcode'),
        'PhoneNumber' => $req['phone'],
        'CallBackURL' => $callbackUrl,
        'AccountReference' => mb_substr($accountRef, 0, 12),
        'TransactionDesc' => 'Skope Academy',
    ];
    [$code, $j, $err] = sda_pay_http('POST', daraja_base() . '/mpesa/stkpush/v1/processrequest',
        ['Authorization: Bearer ' . daraja_token($pdo), 'Content-Type: application/json'], json_encode($body));
    if (($j['ResponseCode'] ?? '') !== '0' || empty($j['CheckoutRequestID'])) {
        throw new RuntimeException($j['errorMessage'] ?? $j['ResponseDescription'] ?? $err ?: "M-Pesa did not accept the request (HTTP $code).");
    }
    return $j['CheckoutRequestID'];
}

/** Ask Daraja how a prompt ended. Returns ['state' => pending|success|failed|cancelled, 'desc' => ...]. */
function daraja_query(PDO $pdo, string $checkoutId): array
{
    $ts = (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('YmdHis');
    [$code, $j] = sda_pay_http('POST', daraja_base() . '/mpesa/stkpushquery/v1/query',
        ['Authorization: Bearer ' . daraja_token($pdo), 'Content-Type: application/json'],
        json_encode(['BusinessShortCode' => setting('daraja_shortcode'), 'Password' => daraja_password($ts), 'Timestamp' => $ts, 'CheckoutRequestID' => $checkoutId]));
    if (!isset($j['ResultCode'])) return ['state' => 'pending', 'desc' => $j['errorMessage'] ?? 'Waiting for M-Pesa'];
    return daraja_result_state((string)$j['ResultCode'], (string)($j['ResultDesc'] ?? ''));
}

function daraja_result_state(string $code, string $desc): array
{
    if ($code === '0') return ['state' => 'success', 'desc' => $desc];
    if ($code === '1032') return ['state' => 'cancelled', 'desc' => 'You cancelled the M-Pesa prompt.'];
    if ($code === '1037') return ['state' => 'failed', 'desc' => 'The M-Pesa prompt timed out before the PIN was entered.'];
    if ($code === '1') return ['state' => 'failed', 'desc' => 'Your M-Pesa balance is too low for this payment.'];
    if ($code === '2001') return ['state' => 'failed', 'desc' => 'The M-Pesa PIN was wrong.'];
    return ['state' => 'failed', 'desc' => $desc ?: 'M-Pesa could not complete the payment.'];
}

/* ── Cyzora (STK charges) ───────────────────────────────────────────────── */

const CYZORA_API = 'https://api.cyzora.co.ke/api/v1';

function cyzora_headers(): array
{
    return ['X-API-Key: ' . setting('cyzora_secret_key'), 'Content-Type: application/json', 'Accept: application/json'];
}

/** Send the PIN prompt. Returns the charge UUID. */
function cyzora_charge(array $req, string $accountRef): string
{
    $body = [
        'amount' => (int)ceil((float)$req['amount']) * 100,     // cents, whole shillings
        'phone' => $req['phone'],
        'network' => 'mpesa',
        'account_ref' => mb_substr($accountRef, 0, 40),
        'metadata' => ['sda_request' => (string)$req['id'], 'pay_type' => (string)$req['pay_type'], 'item_id' => (string)$req['item_id']],
    ];
    [$code, $j, $err] = sda_pay_http('POST', CYZORA_API . '/charges', array_merge(cyzora_headers(), ['Idempotency-Key: sda-' . $req['id'] . '-' . $req['cb_token']]), json_encode($body));
    $charge = isset($j['charge']) && is_array($j['charge']) ? $j['charge'] : $j;     // 202: {"charge": {...}, "warning": ...}
    if ($code >= 400 || empty($charge['id'])) throw new RuntimeException($j['error'] ?? $err ?: "Cyzora did not accept the request (HTTP $code).");
    return (string)$charge['id'];
}

/** Ask Cyzora how a charge ended. */
function cyzora_query(string $chargeId): array
{
    [$code, $j] = sda_pay_http('GET', CYZORA_API . '/charges/' . rawurlencode($chargeId), cyzora_headers());
    $c = isset($j['charge']) && is_array($j['charge']) ? $j['charge'] : $j;
    $s = strtolower((string)($c['status'] ?? ''));
    $out = ['amount' => isset($c['amount']) ? ((int)$c['amount']) / 100 : null, 'receipt' => (string)($c['mpesa_receipt'] ?? '')];
    if ($s === 'succeeded') return ['state' => 'success', 'desc' => 'Paid'] + $out;
    if (in_array($s, ['failed', 'timeout', 'expired'], true)) return ['state' => 'failed', 'desc' => $s === 'timeout' ? 'The M-Pesa prompt timed out before the PIN was entered.' : 'M-Pesa could not complete the payment.'] + $out;
    return ['state' => 'pending', 'desc' => 'Waiting for M-Pesa'] + $out;
}

/** Verify X-Cyzora-Signature: hex HMAC-SHA256 of the raw body with the whsec_ secret. */
function cyzora_signature_ok(string $raw, string $sig): bool
{
    $secret = setting('cyzora_webhook_secret');
    if ($secret === '' || $sig === '') return false;
    return hash_equals(hash_hmac('sha256', $raw, $secret), strtolower(trim($sig)));
}

/** The public address providers call back (must be https and reachable from the internet). */
function sda_pay_public_url(string $path): string
{
    require_once __DIR__ . '/mail_account.php';
    $base = rtrim(sda_site_base(), '/');
    if (preg_match('#^https?://(localhost|127\.0\.0\.1)([:/]|$)#', $base)) $base = 'https://skopedigital.co.ke';
    return $base . '/' . ltrim($path, '/');
}
