<?php
$pageTitle = 'Help & support';
require_once 'includes/header.php';

// Handle Ticket Submission
$message = '';
$message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim((string)($_POST['subject'] ?? ''));
    $content = trim((string)($_POST['message'] ?? ''));

    if (!verifyCsrf()) {
        $message = 'Your session expired. Please send your message again.';
        $message_type = 'error';
    } elseif ($subject === '' || $content === '') {
        $message = 'Choose a topic and describe the problem before sending.';
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, subject, message, status, created_at) VALUES (?, ?, ?, 'open', NOW())");
            $stmt->execute([$student['id'], mb_substr($subject, 0, 255), $content]);
            $message = "Thanks, we've got your message. Your ticket number is #SK-" . $pdo->lastInsertId() . '.';
        } catch (Exception $e) {
            error_log('support ticket: ' . $e->getMessage());
            $message = 'Sorry, your message could not be sent. Please try again or email us.';
            $message_type = 'error';
        }
    }
}

try {
    // Fetch student's open/recent tickets
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$student['id']]);
    $tickets = $stmt->fetchAll();
} catch (Exception $e) { $tickets = []; }

$ticket_status = ['open' => ['green', 'Open'], 'in_progress' => ['yellow', 'In progress'], 'closed' => ['gray', 'Closed']];
$topics = ['Course Access Issue' => 'I can\'t open a course', 'Billing & Payment Verification' => 'Payments and billing', 'Technical Bug / Platform Error' => 'Something isn\'t working', 'Certificate Verification' => 'Certificates', 'General Inquiry' => 'Something else'];

require_once 'includes/layout-top.php';
?>

<style>
.pg-support details { border-bottom: 1px solid var(--border); }
.pg-support details:last-child { border-bottom: 0; }
.pg-support summary { list-style: none; cursor: pointer; padding: 14px 2px; font-weight: 700; font-size: .92rem; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.pg-support summary::-webkit-details-marker { display: none; }
.pg-support summary i { color: var(--text-muted); transition: transform .2s; }
.pg-support details[open] summary i { transform: rotate(180deg); }
.pg-support details p { margin: 0 0 14px; color: var(--text-dim); font-size: .88rem; line-height: 1.6; }
</style>

<div class="pg-support">
    <div class="sp-page-head">
        <div><h1>Help & support</h1><p>Send us a message and we'll get back to you, or check the common questions below.</p></div>
    </div>

    <?php if ($message): ?>
        <div class="sp-alert <?= $message_type ?>" role="<?= $message_type === 'error' ? 'alert' : 'status' ?>">
            <i class="fas <?= $message_type === 'error' ? 'fa-circle-exclamation' : 'fa-paper-plane' ?>"></i> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="sp-split">
        <div>
            <!-- New ticket -->
            <div class="sp-card sp-section">
                <div class="sp-card-head"><h3>Contact support</h3></div>
                <form method="POST" class="sp-form">
                    <input type="hidden" name="submit_ticket" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <div class="sp-field">
                        <label for="tk-subject">What do you need help with?</label>
                        <select id="tk-subject" name="subject" required>
                            <?php foreach ($topics as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sp-field">
                        <label for="tk-message">Your message</label>
                        <textarea id="tk-message" name="message" rows="6" placeholder="Tell us what happened and which course or page it was on. The more detail, the faster we can help." required></textarea>
                    </div>
                    <button type="submit" class="sp-btn"><i class="fas fa-paper-plane"></i> Send message</button>
                </form>
            </div>

            <!-- FAQ -->
            <div class="sp-section-head"><div><h2>Common questions</h2></div></div>
            <div class="sp-card" style="padding: 4px 18px;">
                <details>
                    <summary>When will my payment be confirmed? <i class="fas fa-chevron-down"></i></summary>
                    <p>We usually check manual payments within 2 to 6 working hours. It can take a little longer at busy times. You can see the status on your <a href="payments" class="sp-link" style="font-size: inherit;">Payments</a> page.</p>
                </details>
                <details>
                    <summary>Can I change the course I joined? <i class="fas fa-chevron-down"></i></summary>
                    <p>For certificate courses you can switch within 24 hours of joining, as long as you have done no more than 10% of the lessons. Send us a message above and we'll help.</p>
                </details>
                <details>
                    <summary>How do I get my certificate? <i class="fas fa-chevron-down"></i></summary>
                    <p>Your certificate is created automatically when you finish every lesson and pass the required quizzes. Find it on your <a href="certificates" class="sp-link" style="font-size: inherit;">Certificates</a> page.</p>
                </details>
                <details>
                    <summary>How do I change my password or email? <i class="fas fa-chevron-down"></i></summary>
                    <p>Go to <a href="profile" class="sp-link" style="font-size: inherit;">Profile & settings</a>. You can update your details and change your password there.</p>
                </details>
            </div>
        </div>

        <aside>
            <div class="sp-card">
                <div class="sp-card-head"><h4>Your recent tickets</h4></div>
                <?php if (!empty($tickets)): ?>
                    <div class="sp-list">
                        <?php foreach ($tickets as $t):
                            [$bc, $bl] = $ticket_status[$t['status']] ?? ['gray', ucfirst((string)$t['status'])];
                        ?>
                        <div class="sp-list-item" style="padding: 10px 0;">
                            <div class="sp-grow">
                                <h5 class="sp-truncate"><?= htmlspecialchars($t['subject']) ?></h5>
                                <p>#SK-<?= (int)$t['id'] ?> · <?= date('j M Y', strtotime($t['created_at'])) ?></p>
                            </div>
                            <span class="sp-badge <?= $bc ?>"><?= htmlspecialchars($bl) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="sp-muted sp-small" style="margin: 0;">You haven't sent any messages yet.</p>
                <?php endif; ?>
            </div>

            <div class="sp-card">
                <div class="sp-list">
                    <div class="sp-list-item">
                        <span class="sp-ico"><i class="fas fa-envelope"></i></span>
                        <div class="sp-grow"><h4>Email us</h4><p style="overflow-wrap: anywhere;"><a href="mailto:support@skopedigital.ac.ke" class="sp-link" style="font-size: inherit;">support@skopedigital.ac.ke</a></p></div>
                    </div>
                    <div class="sp-list-item">
                        <span class="sp-ico orange"><i class="fas fa-clock"></i></span>
                        <div class="sp-grow"><h4>Opening hours</h4><p>Mon to Fri: 8:00 am to 6:00 pm<br>Sat: 9:00 am to 1:00 pm (EAT)</p></div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php require_once 'includes/layout-bottom.php'; ?>
