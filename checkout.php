<?php
/**
 * Checkout: M-Pesa (STK push via Safaricom Daraja or Cyzora) and card (Paystack).
 * The price comes from sda_checkout_quote() (same rules as enrolment and the M-Pesa endpoint).
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';
require_once 'includes/coupons.php';
require_once 'includes/payments.php';
require_once 'includes/paystack.php';

if (!isLoggedIn()) { header('Location: login'); exit; }

$user = currentUser();
$type = (string)($_GET['type'] ?? 'course');
$id = (int)($_GET['id'] ?? 0);
if (!in_array($type, ['course', 'certificate', 'registration'], true)) { header('Location: ./'); exit; }

$q = sda_checkout_quote($pdo, $user, $type, $id, $_GET['mode'] ?? null, $_GET['cohort'] ?? null, (string)($_GET['coupon'] ?? ''));
if ($q['redirect'] !== '') { header('Location: ' . $q['redirect']); exit; }
$amount = (float)$q['amount'];
$mode = $q['mode'];
$cohort_id = (int)$q['cohort_id'];
$coupon = $q['coupon'];

// Nothing to pay: finish on the page that owns the free path
if ($amount <= 0) {
    if ($type === 'certificate') {
        require_once 'includes/progress.php';
        unlock_certificate($pdo, (int)$q['cert']['id'], 0);
        header('Location: certificate?code=' . rawurlencode($q['cert']['verification_code']));
    } elseif ($type === 'course') {
        header("Location: enroll?id=$id&mode=" . urlencode($mode) . "&cohort=$cohort_id");   // confirmed there (POST + CSRF)
    } else {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$user['id']]);
        header('Location: ' . sda_after_activation('student/'));
    }
    exit;
}

$mpesa = sda_mpesa_provider();
$card = sda_paystack_on();
$kes = (int)ceil($amount);   // M-Pesa takes whole shillings
$st = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
$st->execute([$user['id']]);
$phone = (string)$st->fetchColumn();
$back = $type === 'course' ? 'enroll?id=' . $id : ($type === 'certificate' ? 'certificate-pay?code=' . rawurlencode($q['cert']['verification_code']) : 'student/');
$icon = $type === 'course' ? 'fa-graduation-cap' : ($type === 'certificate' ? 'fa-award' : 'fa-user-check');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($q['title']) ?> – Skope Digital Academy</title>
    <?php require_once __DIR__ . '/includes/seo.php'; sda_seo(['title' => 'Checkout', 'path' => 'checkout', 'noindex' => true]); ?>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --ink: #0f172a; --muted: #64748b; --line: #e2e8f0; --cyan: #00BFFF; --blue: #0080FF; --orange: #FF8C00; --mpesa: #16a34a; --mpesa-d: #15803d; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 16px;
               font-family: Inter, system-ui, sans-serif; color: #334155;
               background: radial-gradient(900px 500px at 10% -10%, rgba(0,191,255,.16), transparent 60%), radial-gradient(800px 500px at 110% 110%, rgba(255,140,0,.12), transparent 60%), #f4f7fb; }
        .co-brand { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; margin-bottom: 16px; font: 800 1rem Poppins, sans-serif; color: var(--ink); }
        .co-brand img { width: 38px; height: 38px; border-radius: 10px; background: #fff; }
        .co-brand em { font-style: normal; color: var(--orange); }
        .co-card { width: 100%; max-width: 460px; background: #fff; border-radius: 24px; overflow: hidden; box-shadow: 0 30px 60px -30px rgba(15,23,42,.35), 0 0 0 1px rgba(15,23,42,.04); }
        .co-bar { height: 4px; background: linear-gradient(90deg, var(--cyan), var(--blue) 55%, var(--orange)); }
        .co-body { padding: 26px 26px 24px; }
        .co-head { display: flex; gap: 14px; align-items: center; margin-bottom: 18px; }
        .co-head .ic { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; background: #e0f2fe; color: var(--blue); font-size: 1.3rem; flex: none; }
        .co-head h1 { margin: 0; font: 800 1.15rem/1.3 Poppins, sans-serif; color: var(--ink); }
        .co-head p { margin: 3px 0 0; font-size: .88rem; color: var(--muted); }
        .co-total { display: flex; justify-content: space-between; align-items: baseline; padding: 14px 16px; border-radius: 14px; background: #f8fafc; border: 1px solid var(--line); margin-bottom: 18px; }
        .co-total span { font-size: .85rem; color: var(--muted); font-weight: 600; }
        .co-total b { font: 900 1.6rem Poppins, sans-serif; color: var(--ink); }
        .co-total b small { font-size: .8rem; color: var(--muted); font-weight: 700; margin-right: 4px; }
        .co-coupon { margin: -8px 0 16px; font-size: .85rem; color: #065f46; font-weight: 600; }
        .co-tabs { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: 8px; margin-bottom: 16px; }
        .co-tab { display: flex; align-items: center; justify-content: center; gap: 8px; height: 46px; border-radius: 12px; border: 1.5px solid var(--line); background: #fff; font: 700 .92rem Inter, sans-serif; color: var(--ink); cursor: pointer; }
        .co-tab[aria-selected="true"] { border-color: var(--blue); background: #f0f9ff; box-shadow: 0 0 0 3px rgba(0,128,255,.12); }
        .co-tab .mp { color: var(--mpesa); font-weight: 900; }
        .co-pane[hidden] { display: none; }
        .co-label { display: block; font-weight: 700; font-size: .85rem; color: var(--ink); margin-bottom: 6px; }
        .co-phone { display: flex; align-items: center; border: 1.5px solid #cbd5e1; border-radius: 12px; overflow: hidden; background: #fff; }
        .co-phone:focus-within { border-color: var(--mpesa); box-shadow: 0 0 0 4px rgba(22,163,74,.14); }
        .co-phone span { padding: 0 12px; color: var(--muted); font-weight: 700; border-right: 1px solid var(--line); height: 48px; display: grid; place-items: center; background: #f8fafc; }
        .co-phone input { flex: 1; min-width: 0; border: 0; outline: none; height: 48px; padding: 0 14px; font: 600 16px Inter, sans-serif; color: var(--ink); letter-spacing: .5px; }
        .co-hint { font-size: .8rem; color: var(--muted); margin: 6px 0 16px; }
        .co-btn { display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 52px; border: 0; border-radius: 14px; cursor: pointer; font: 800 1rem Inter, sans-serif; color: #fff; text-decoration: none; transition: transform .15s, opacity .15s; }
        .co-btn:hover { transform: translateY(-1px); }
        .co-btn:disabled { opacity: .6; cursor: default; transform: none; }
        .co-btn.mpesa { background: linear-gradient(135deg, #22c55e, var(--mpesa-d)); box-shadow: 0 14px 26px -14px rgba(22,163,74,.8); }
        .co-btn.card { background: linear-gradient(135deg, var(--cyan), var(--blue)); box-shadow: 0 14px 26px -14px rgba(0,128,255,.8); }
        .co-status { display: flex; gap: 12px; align-items: flex-start; margin-top: 14px; padding: 14px; border-radius: 14px; font-size: .9rem; line-height: 1.5; }
        .co-status[hidden] { display: none; }
        .co-status.wait { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .co-status.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .co-status.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .co-status i { margin-top: 3px; }
        .co-steps { margin: 6px 0 0; padding-left: 18px; font-size: .82rem; color: #166534; }
        .co-off { padding: 16px; border-radius: 14px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: .9rem; line-height: 1.55; }
        .co-foot { display: flex; justify-content: space-between; gap: 10px; margin-top: 18px; padding-top: 14px; border-top: 1px solid #f1f5f9; font-size: .82rem; color: #94a3b8; }
        .co-foot a { color: var(--muted); text-decoration: none; font-weight: 600; }
        @media (max-width: 400px) { .co-body { padding: 22px 18px 20px; } .co-total b { font-size: 1.35rem; } }
    </style>
</head>
<body>
    <a class="co-brand" href="./"><img src="assets/pwa/icon-192.png" alt=""> SKOPE <em>DIGITAL</em> ACADEMY</a>
    <main class="co-card">
        <div class="co-bar"></div>
        <div class="co-body">
            <div class="co-head"><span class="ic"><i class="fas <?= $icon ?>"></i></span><div><h1><?= e($q['title']) ?></h1><p><?= e($q['desc']) ?></p></div></div>

            <div class="co-total"><span>Total to pay</span><b><small><?= e(setting('currency') ?: 'KES') ?></small><?= number_format($mpesa && !$card ? $kes : $amount, $mpesa && !$card ? 0 : 2) ?></b></div>
            <?php if ($coupon): ?><p class="co-coupon"><i class="fas fa-ticket"></i> Coupon <?= e($coupon['coupon']['code']) ?>: <s style="color:#94a3b8"><?= e(money((float)$q['full_amount'])) ?></s> → <?= e(money($amount)) ?></p><?php endif; ?>

            <?php if (!$mpesa && !$card): ?>
                <div class="co-off"><b>Online payment isn't available right now.</b> Please contact us on WhatsApp or email and we'll help you pay and get started.</div>
            <?php else: ?>
                <?php if ($mpesa && $card): ?>
                <div class="co-tabs" role="tablist">
                    <button type="button" class="co-tab" role="tab" aria-selected="true" data-pane="paneM"><span class="mp">M-PESA</span></button>
                    <button type="button" class="co-tab" role="tab" aria-selected="false" data-pane="paneC"><i class="far fa-credit-card"></i> Card</button>
                </div>
                <?php endif; ?>

                <?php if ($mpesa): ?>
                <section class="co-pane" id="paneM">
                    <label class="co-label" for="phone">M-Pesa phone number</label>
                    <div class="co-phone"><span>🇰🇪</span><input id="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0712 345 678" value="<?= e(sda_mpesa_phone($phone) ? '0' . substr(sda_mpesa_phone($phone), 3) : '') ?>"></div>
                    <p class="co-hint">You'll get a prompt on this phone to enter your M-Pesa PIN for <b>KES <?= number_format($kes) ?></b>.</p>
                    <button type="button" class="co-btn mpesa" id="mpBtn"><i class="fas fa-mobile-screen-button"></i> Pay KES <?= number_format($kes) ?> with M-Pesa</button>
                    <div class="co-status" id="mpStatus" hidden></div>
                </section>
                <?php endif; ?>

                <?php if ($card): ?>
                <section class="co-pane" id="paneC"<?= $mpesa ? ' hidden' : '' ?>>
                    <button type="button" class="co-btn card" id="pay-button"><i class="far fa-credit-card"></i> Pay <?= e(money($amount)) ?> by card</button>
                    <p class="co-hint" style="text-align:center;margin-top:10px"><i class="fas fa-lock"></i> Secure checkout by Paystack (card, and M-Pesa inside Paystack).</p>
                </section>
                <?php endif; ?>
            <?php endif; ?>

            <div class="co-foot"><a href="<?= e($back) ?>"><i class="fas fa-arrow-left"></i> Back</a><span><i class="fas fa-shield-halved"></i> Your payment is confirmed automatically</span></div>
        </div>
    </main>

    <script>
    (function () {
        var tabs = document.querySelectorAll('.co-tab');
        tabs.forEach(function (t) { t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.setAttribute('aria-selected', String(x === t)); document.getElementById(x.dataset.pane).hidden = x !== t; });
        }); });
    })();
    </script>

    <?php if ($mpesa): ?>
    <script>
    (function () {
        var btn = document.getElementById('mpBtn'), box = document.getElementById('mpStatus'), phone = document.getElementById('phone'), timer = null;
        var payload = <?= json_encode(['type' => $type, 'id' => $id, 'mode' => $mode, 'cohort' => $cohort_id, 'coupon' => $coupon ? $coupon['coupon']['code'] : '']) ?>;
        var csrf = <?= json_encode(csrfToken()) ?>, label = btn.innerHTML;
        function show(kind, html) { box.className = 'co-status ' + kind; box.innerHTML = html; box.hidden = false; }
        function reset() { btn.disabled = false; btn.innerHTML = label; }
        function poll(id, n) {
            fetch('mpesa-pay?status=' + id, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
                if (d.state === 'success') { show('ok', '<i class="fas fa-circle-check"></i><div><b>' + d.message + '</b></div>'); setTimeout(function () { location.href = d.go; }, 1200); return; }
                if (d.state === 'pending' && n < 75) { timer = setTimeout(function () { poll(id, n + 1); }, 3000); return; }
                show('err', '<i class="fas fa-circle-exclamation"></i><div><b>Payment not completed.</b> ' + (d.message || '') + ' You can try again.</div>'); reset();
            }).catch(function () { timer = setTimeout(function () { poll(id, n + 1); }, 4000); });
        }
        btn.addEventListener('click', function () {
            var p = phone.value.trim();
            if (!/^(\+?254|0)?[71]\d{8}$/.test(p.replace(/[\s-]/g, ''))) { show('err', '<i class="fas fa-circle-exclamation"></i><div>Enter your Safaricom number, e.g. 0712 345 678.</div>'); phone.focus(); return; }
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sending the prompt…';
            fetch('mpesa-pay', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, Accept: 'application/json' },
                                 body: JSON.stringify(Object.assign({ phone: p }, payload)) })
                .then(function (r) { return r.json(); }).then(function (d) {
                    if (!d.ok) { show('err', '<i class="fas fa-circle-exclamation"></i><div>' + (d.message || 'Something went wrong.') + '</div>'); reset(); if (d.go) location.href = d.go; return; }
                    btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Waiting for your PIN…';
                    show('wait', '<i class="fas fa-mobile-screen-button"></i><div><b>Check your phone (' + d.phone + ').</b><ol class="co-steps"><li>Open the M-Pesa prompt</li><li>Enter your M-Pesa PIN to pay KES ' + Number(d.amount).toLocaleString() + '</li><li>Stay on this page: it continues by itself</li></ol></div>');
                    poll(d.request, 0);
                }).catch(function () { show('err', '<i class="fas fa-circle-exclamation"></i><div>Network problem. Check your connection and try again.</div>'); reset(); });
        });
    })();
    </script>
    <?php endif; ?>

    <?php if ($card): ?>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        const pay = <?= json_encode([
            'key' => PAYSTACK_PUBLIC_KEY, 'email' => $user['email'], 'amount' => (int)round($amount * 100), 'currency' => setting('currency'),
            'type' => $type, 'id' => $id, 'mode' => $mode, 'cohort' => $cohort_id, 'coupon' => $coupon ? $coupon['coupon']['code'] : '',
        ]) ?>;
        document.getElementById('pay-button').addEventListener('click', function (e) {
            e.preventDefault();
            const handler = PaystackPop.setup({
                key: pay.key, email: pay.email, amount: pay.amount, currency: pay.currency,
                ref: 'SDA-' + Date.now() + '-' + Math.floor(Math.random() * 1e6),
                metadata: { payment_type: pay.type, item_id: pay.id, learning_mode: pay.mode, cohort_id: pay.cohort, coupon: pay.coupon,
                    custom_fields: [
                        { display_name: "Payment Type", variable_name: "payment_type", value: pay.type },
                        { display_name: "Item ID", variable_name: "item_id", value: String(pay.id) },
                        { display_name: "Learning track", variable_name: "learning_mode", value: pay.mode }
                    ] },
                callback: function (response) {
                    const q = new URLSearchParams({ reference: response.reference, type: pay.type, id: pay.id, mode: pay.mode, cohort: pay.cohort });
                    if (pay.coupon) q.set('coupon', pay.coupon);
                    window.location.href = "verify-payment?" + q.toString();
                }
            });
            handler.openIframe();
        });
    </script>
    <?php endif; ?>
</body>
</html>
