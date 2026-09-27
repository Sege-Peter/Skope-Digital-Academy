<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$user = isLoggedIn() ? currentUser() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Our Learners – Skope Digital Academy</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --royal-indigo: #003274ff;
            --premium-orange: #FF8C00;
            --sky-blue: #00BFFF;
        }
        body { background: #fdfdfd; font-family: 'Inter', sans-serif; color: #1e293b; }
        .donate-hero {
            background: linear-gradient(135deg, var(--royal-indigo) 0%, #0c1f40 100%);
            padding: 100px 0;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .donate-hero::before {
            content: ''; position: absolute; inset: 0;
            background: radial-gradient(circle at 70% 30%, rgba(0,191,255,0.15) 0%, transparent 60%);
            pointer-events: none;
        }
        .donate-container { max-width: 1000px; margin: -60px auto 100px; padding: 0 20px; position: relative; z-index: 10; }
        .donate-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .premium-card {
            background: white; border-radius: 32px; padding: 48px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;
        }
        .section-tag {
            display: inline-block; background: rgba(0,191,255,0.1); color: var(--sky-blue);
            padding: 8px 16px; border-radius: 50px; font-size: 0.75rem; font-weight: 800;
            text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 24px;
        }
        h1 { font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 3rem; margin-bottom: 16px; letter-spacing: -1px; }
        .hero-sub { font-size: 1.1rem; opacity: 0.85; max-width: 600px; margin: 0 auto; line-height: 1.6; }
        
        .method-card { background: #f8fafc; border-radius: 24px; padding: 24px; margin-bottom: 20px; border: 1px solid #e2e8f0; transition: 0.3s; }
        .method-card:hover { border-color: var(--sky-blue); transform: translateY(-3px); }
        .method-icon { width: 48px; height: 48px; border-radius: 12px; background: white; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 16px; color: var(--sky-blue); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .method-title { font-weight: 800; font-size: 1.1rem; margin-bottom: 8px; color: var(--royal-indigo); }
        .method-detail { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 1.2rem; color: #334155; }
        
        .donation-form label { display: block; font-weight: 800; font-size: 0.85rem; color: #64748b; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 0.5px; }
        .amount-chips { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .amount-chip {
            background: #f1f5f9; border: 2px solid transparent; padding: 16px; border-radius: 16px;
            font-weight: 800; cursor: pointer; transition: 0.3s; text-align: center;
        }
        .amount-chip:hover { background: #e2e8f0; }
        .amount-chip.active { border-color: var(--sky-blue); background: rgba(0,191,255,0.05); color: var(--sky-blue); }
        
        .custom-amount-input {
            width: 100%; padding: 18px 24px; border-radius: 16px; border: 2px solid #e2e8f0;
            background: #f8fafc; font-size: 1.25rem; font-weight: 800; font-family: 'Poppins', sans-serif;
            margin-bottom: 32px; outline: none; transition: 0.3s;
        }
        .custom-amount-input:focus { border-color: var(--sky-blue); background: white; box-shadow: 0 0 0 4px rgba(0,191,255,0.1); }
        
        .btn-donate {
            background: var(--sky-blue); color: white; border: none; padding: 20px; border-radius: 20px;
            font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.2rem; width: 100%;
            cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 12px;
            box-shadow: 0 10px 25px rgba(0,191,255,0.3);
        }
        .btn-donate:hover { background: #0099d6; transform: translateY(-3px); box-shadow: 0 15px 35px rgba(0,191,255,0.4); }
        
        @media (max-width: 900px) { 
            .donate-grid { grid-template-columns: 1fr; gap: 40px; } 
            h1 { font-size: 2.6rem; }
            .donate-hero { padding: 80px 0; }
            .donate-container { margin-top: -40px; }
        }
        
        @media (max-width: 600px) {
            h1 { font-size: 2rem; }
            .premium-card { padding: 32px 20px; border-radius: 24px; }
            .amount-chip { padding: 12px; font-size: 0.95rem; }
            .custom-amount-input { padding: 14px 20px; font-size: 1.1rem; }
            .btn-donate { height: 64px; font-size: 1.1rem; }
        }
    </style>
</head>
<body>

    <?php require_once 'includes/nav.php'; ?>

    <section class="donate-hero">
        <div class="container">
            <div class="section-tag" style="background: rgba(255,255,255,0.1); color: white;">Impact First</div>
            <h1>Support Our <span style="color: var(--sky-blue);">Learners.</span></h1>
            <p class="hero-sub">Your contribution directly funds tuition, world-class certifications, and high-speed infrastructure for talented students across Africa.</p>
        </div>
    </section>

    <div class="donate-container">
        <div class="donate-grid">
            
            <!-- Left: Manual Methods -->
            <div class="premium-card">
                <h3 style="font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 1.5rem; margin-bottom: 32px;">Direct Transfers</h3>
                
                <div class="method-card">
                    <div class="method-icon"><i class="fas fa-mobile-alt"></i></div>
                    <div class="method-title">M-PESA</div>
                    <div class="method-detail">0742380183</div>
                    <div style="font-size: 0.85rem; color: #64748b; margin-top: 4px; font-weight: 600;">Name: Peter Sege</div>
                </div>

                <div class="method-card">
                    <div class="method-icon"><i class="fas fa-university"></i></div>
                    <div class="method-title">KCB BANK</div>
                    <div class="method-detail">1318989760</div>
                    <div style="font-size: 0.85rem; color: #64748b; margin-top: 4px; font-weight: 600;">Account: Peter Sege</div>
                </div>

                <div class="method-card">
                    <div class="method-icon"><i class="fab fa-paypal"></i></div>
                    <div class="method-title">PayPal</div>
                    <div class="method-detail">Secure Global Payment</div>
                    <a href="https://paypal.me/segepeter" target="_blank" style="display: inline-block; margin-top: 10px; color: var(--sky-blue); font-weight: 800; text-decoration: none;">Donate via PayPal <i class="fas fa-external-link-alt"></i></a>
                </div>
            </div>

            <!-- Right: Paystack Online -->
            <div class="premium-card" style="border-color: #eef2f6; background: white; box-shadow: 0 10px 40px rgba(0,0,0,0.04);">
                <h3 style="font-family: 'Poppins', sans-serif; font-weight: 950; font-size: 1.6rem; margin-bottom: 32px; letter-spacing: -0.5px; color: #0f172a;">Donate Now</h3>
                
                <div class="donation-form">
                    <label style="display: block; font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 20px; letter-spacing: 1.5px;">Select Amount (KES)</label>
                    <div class="amount-chips" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
                        <div class="amount-chip" data-amount="500" style="background: #f8fafc; border: 2px solid #f1f5f9; padding: 18px; border-radius: 16px; font-weight: 800; cursor: pointer; text-align: center; transition: 0.3s; font-family: 'Poppins', sans-serif; font-size: 1.1rem; color: #1e293b;">500</div>
                        <div class="amount-chip active" data-amount="1000" style="background: #f8fafc; border: 2px solid #00BFFF; color: #00BFFF; padding: 18px; border-radius: 16px; font-weight: 800; cursor: pointer; text-align: center; transition: 0.3s; font-family: 'Poppins', sans-serif; font-size: 1.1rem;">1,000</div>
                        <div class="amount-chip" data-amount="5000" style="background: #f8fafc; border: 2px solid #f1f5f9; padding: 18px; border-radius: 16px; font-weight: 800; cursor: pointer; text-align: center; transition: 0.3s; font-family: 'Poppins', sans-serif; font-size: 1.1rem; color: #1e293b;">5,000</div>
                    </div>

                    <label style="display: block; font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 1.5px;">Custom Amount</label>
                    <input type="number" id="custom-amount" class="custom-amount-input" style="width: 100%; padding: 20px 24px; border-radius: 16px; border: 2px solid #f1f5f9; font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 1.4rem; outline: none; background: white; transition: 0.3s; color: #1e293b; margin-bottom: 24px;" placeholder="0" value="1000">

                    <?php if(!$user): ?>
                    <label style="display: block; font-weight: 800; font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; margin-bottom: 12px; letter-spacing: 1.5px;">Your Email</label>
                    <input type="email" id="donor-email" class="custom-amount-input" style="width: 100%; padding: 18px 24px; border-radius: 16px; border: 2px solid #f1f5f9; margin-bottom: 24px; font-family: inherit; font-weight: 600; font-size: 1rem; outline: none; background: white; color: #1e293b;" placeholder="email@example.com">
                    <?php endif; ?>

                    <button id="paystack-donate-btn" class="btn-donate" style="width: 100%; height: 78px; background: #00BFFF; color: white; border: none; border-radius: 20px; font-family: 'Poppins', sans-serif; font-weight: 900; font-size: 1.25rem; cursor: pointer; transition: 0.3s; box-shadow: 0 15px 35px rgba(0,191,255,0.3); display: flex; align-items: center; justify-content: center; gap: 14px;">
                        Confirm Donation <i class="fas fa-heart"></i>
                    </button>
                    
                    <div style="margin-top: 28px; font-size: 0.8rem; color: #94a3b8; text-align: center; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px; opacity: 0.8;">
                        <i class="fas fa-lock" style="font-size: 0.7rem;"></i> Secured by Paystack.
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php require_once 'includes/footer.php'; ?>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        const chips = document.querySelectorAll('.amount-chip');
        const customInput = document.getElementById('custom-amount');
        const donateBtn = document.getElementById('paystack-donate-btn');

        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                chips.forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                customInput.value = chip.getAttribute('data-amount');
            });
        });

        customInput.addEventListener('input', () => {
            chips.forEach(c => c.classList.remove('active'));
        });

        donateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const amount = parseFloat(customInput.value);
            const email = "<?= $user ? $user['email'] : '' ?>" || document.getElementById('donor-email')?.value;

            if (!amount || amount < 50) {
                SDA.showToast("Please enter a valid amount (Min KES 50)", "warning");
                return;
            }
            if (!email || !email.includes('@')) {
                SDA.showToast("Please provide a valid email for the receipt.", "warning");
                return;
            }

            donateBtn.disabled = true;
            donateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initializing...';

            let handler = PaystackPop.setup({
                key: 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', // Replace with your Public Key
                email: email,
                amount: amount * 100,
                currency: 'KES',
                ref: 'DON-' + Math.floor((Math.random() * 1000000000) + 1),
                metadata: {
                    custom_fields: [
                        { display_name: "Payment Type", variable_name: "payment_type", value: "donation" },
                        { display_name: "Donor Name", variable_name: "donor_name", value: "<?= $user ? $user['name'] : 'Guest' ?>" }
                    ]
                },
                callback: function(response) {
                    window.location.href = "verify-payment.php?reference=" + response.reference + "&type=donation&email=" + encodeURIComponent(email);
                },
                onClose: function() {
                    donateBtn.disabled = false;
                    donateBtn.innerHTML = 'Confirm Donation <i class="fas fa-heart"></i>';
                    SDA.showToast("Donation cancelled", "info");
                }
            });
            handler.openIframe();
        });
    </script>
</body>
</html>
