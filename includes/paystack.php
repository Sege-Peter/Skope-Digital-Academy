<?php
/**
 * Paystack Payment Gateway Integration
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';   // setting()
// Keys: Admin > Settings > Payments wins; the values below are the old test keys kept as a fallback.
if (!defined('PAYSTACK_PUBLIC_KEY')) define('PAYSTACK_PUBLIC_KEY', setting('paystack_public_key') ?: 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
if (!defined('PAYSTACK_SECRET_KEY')) define('PAYSTACK_SECRET_KEY', setting('paystack_secret_key') ?: 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

/**
 * Initialize Paystack Transaction
 */
function paystack_init_transaction($email, $amount, $callback_url, $metadata = []) {
    $url = "https://api.paystack.co/transaction/initialize";
    $fields = [
        'email' => $email,
        'amount' => $amount * 100, // Amount in kobo/cents
        'callback_url' => $callback_url,
        'metadata' => json_encode($metadata)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}

/**
 * Verify Paystack Transaction
 */
function paystack_verify_transaction($reference) {
    $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache"
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result, true);
}
?>
