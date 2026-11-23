<?php
/**
 * Admin AI Assistant: a chat that knows the academy's live headline numbers (aggregates only – no names,
 * emails or phone numbers are ever sent to the AI provider). Uses Claude or Gemini per Admin > Settings > AI.
 * The conversation lives in the admin's session only.
 */
$pageTitle = 'AI Assistant';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/ai_provider.php';
require_once __DIR__ . '/../includes/security.php';
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: staff-home'); exit; }

const AXAI_KEEP = 24;   // messages kept in the conversation

/** Live headline numbers for the assistant (counts and totals only). */
function axai_snapshot(PDO $pdo): string
{
    $n = function (string $sql) use ($pdo) { try { return $pdo->query($sql)->fetchColumn(); } catch (Throwable $e) { return null; } };
    $rows = function (string $sql) use ($pdo) { try { return $pdo->query($sql)->fetchAll(); } catch (Throwable $e) { return []; } };
    $lines = [
        'Date today: ' . date('l j F Y, H:i') . ' (Africa/Nairobi)',
        'Learners (active accounts): ' . (int)$n("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'"),
        'Learners awaiting activation: ' . (int)$n("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'pending'"),
        'New sign-ups, last 7 days: ' . (int)$n("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= NOW() - INTERVAL 7 DAY"),
        'New sign-ups, last 30 days: ' . (int)$n("SELECT COUNT(*) FROM users WHERE role = 'student' AND created_at >= NOW() - INTERVAL 30 DAY"),
        'Tutors (active): ' . (int)$n("SELECT COUNT(*) FROM users WHERE role = 'tutor' AND status = 'active'"),
        'Courses published: ' . (int)$n("SELECT COUNT(*) FROM courses WHERE status = 'published'") . '; awaiting approval: ' . (int)$n("SELECT COUNT(*) FROM courses WHERE status = 'pending'"),
        'Enrolments total: ' . (int)$n("SELECT COUNT(*) FROM enrollments") . '; last 30 days: ' . (int)$n("SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= NOW() - INTERVAL 30 DAY") . '; completed: ' . (int)$n("SELECT COUNT(*) FROM enrollments WHERE status = 'completed'"),
        'Verified payments, last 30 days: KES ' . number_format((float)$n("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified' AND created_at >= NOW() - INTERVAL 30 DAY")) . ' (' . (int)$n("SELECT COUNT(*) FROM payments WHERE status = 'verified' AND created_at >= NOW() - INTERVAL 30 DAY") . ' payments); all time: KES ' . number_format((float)$n("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'verified'")),
        'Payments waiting for approval: ' . (int)$n("SELECT COUNT(*) FROM payments WHERE status = 'pending'"),
        'Certificates issued: ' . (int)$n("SELECT COUNT(*) FROM certificates") . '; this month: ' . (int)$n("SELECT COUNT(*) FROM certificates WHERE issued_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
        'Open support tickets: ' . (int)$n("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'") . '; unread contact messages: ' . (int)$n("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'"),
        'Scholarship applications pending: ' . (int)$n("SELECT COUNT(*) FROM scholarship_applications WHERE status = 'pending'"),
        'Open job vacancies: ' . (int)$n("SELECT COUNT(*) FROM job_vacancies WHERE status = 'open' AND (deadline IS NULL OR deadline >= CURDATE())") . '; job applications: ' . (int)$n("SELECT COUNT(*) FROM job_applications WHERE archived = 0") . '; assessments to mark: ' . (int)$n("SELECT COUNT(*) FROM job_applications WHERE status = 'submitted' AND archived = 0"),
        'Staff members: ' . (int)$n("SELECT COUNT(*) FROM users WHERE role = 'staff'"),
    ];
    $top = $rows("SELECT c.title, COUNT(e.id) n FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id WHERE c.status = 'published' GROUP BY c.id ORDER BY n DESC LIMIT 6");
    if ($top) $lines[] = 'Most-enrolled published courses: ' . implode('; ', array_map(fn($r) => $r['title'] . ' (' . (int)$r['n'] . ')', $top));
    $jobs = $rows("SELECT title, deadline FROM job_vacancies WHERE status = 'open' ORDER BY sort_order LIMIT 8");
    if ($jobs) $lines[] = 'Open positions: ' . implode('; ', array_map(fn($r) => $r['title'] . ($r['deadline'] ? ' (closes ' . date('j M Y', strtotime($r['deadline'])) . ')' : ''), $jobs));
    return implode("\n", $lines);
}

function axai_system(PDO $pdo): string
{
    $name = setting('academy_name') ?: 'Skope Digital Academy';
    return <<<TXT
You are the AI assistant for the administrators of {$name}, an online academy in Kisumu, Kenya that teaches practical coding and digital skills (website: skopedigital.co.ke). Learners pay in KES, mostly via M-Pesa.

Help the admin team run the academy: draft announcements, emails, WhatsApp and social media posts, replies to learners, policies, course outlines, job adverts and interview questions; explain and analyse the numbers below; suggest practical next steps.

Guidelines:
- Be concise and practical. Use short paragraphs, bullet points and headings when helpful. Use KES for money and Kenyan English.
- For numbers, use only the live snapshot below or what the admin tells you. Never invent statistics, testimonials, partners or achievements; if something isn't in the data, say so.
- Drafts for the public must be honest and avoid exaggerated claims.
- You can't see individual learners' personal details, and you can't change anything in the system; tell the admin which admin page to use instead.

Live snapshot of the academy (aggregates only):
TXT . "\n" . axai_snapshot($pdo);
}

/* ── Chat API ──────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    if (!verifyCsrf()) { http_response_code(400); echo json_encode(['ok' => false, 'error' => 'Your session expired. Reload the page.']); exit; }
    $act = $_POST['action'] ?? '';
    if ($act === 'reset') { unset($_SESSION['axai_chat']); echo json_encode(['ok' => true]); exit; }
    $msg = trim((string)($_POST['message'] ?? ''));
    if ($msg === '' || mb_strlen($msg) > 8000) { echo json_encode(['ok' => false, 'error' => 'Write a message (up to 8,000 characters).']); exit; }
    if (!sda_rate_ok('admin_ai', 60, 60, 'u' . $user['id'])) { http_response_code(429); echo json_encode(['ok' => false, 'error' => 'That\'s 60 messages this hour. Please wait a little.']); exit; }
    $chat = $_SESSION['axai_chat'] ?? [];
    $chat[] = ['role' => 'user', 'content' => $msg];
    $chat = array_slice($chat, -AXAI_KEEP);
    $r = sda_ai_chat($chat, axai_system($pdo), ['max_tokens' => 2048]);
    if (!$r['ok']) {
        error_log('[admin ai] ' . sda_ai_label() . ': ' . $r['error']);
        echo json_encode(['ok' => false, 'error' => sda_ai_label() . ' could not answer: ' . $r['error']]);
        exit;
    }
    $chat[] = ['role' => 'assistant', 'content' => $r['text']];
    $_SESSION['axai_chat'] = array_slice($chat, -AXAI_KEEP);
    echo json_encode(['ok' => true, 'reply' => $r['text']]);
    exit;
}

$chat = $_SESSION['axai_chat'] ?? [];
$ready = sda_anthropic_key() !== '' || (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '');
$first = strtok($user['name'] ?? 'there', ' ');
$prompts = [
    ['chart-line', 'How is the academy doing?', 'Give me a short summary of how the academy is doing based on the live numbers, with 3 things to focus on this week.'],
    ['bullhorn', 'Announcement', 'Draft a short, friendly announcement for learners about '],
    ['briefcase', 'Promote vacancies', 'Write a Facebook post and a WhatsApp message announcing our open staff positions and the closing date, with the link skopedigital.co.ke/careers.'],
    ['envelope', 'Reply to a learner', 'Help me write a polite reply to a learner who says: '],
    ['lightbulb', 'Grow enrolments', 'Suggest 5 practical, low-cost ideas to increase enrolments in the next 30 days, based on our numbers.'],
    ['user-tie', 'Interview questions', 'Write 8 interview questions with what a good answer looks like for the position of '],
];

require_once 'includes/sidebar.php';
?>
<style>
  .ai-wrap { display: grid; grid-template-rows: 1fr auto; height: calc(100vh - var(--header-h, 72px) - 48px); min-height: 520px; max-width: 980px; margin: 0 auto; width: 100%; }
  .ai-log { overflow-y: auto; padding: 8px 4px 20px; scroll-behavior: smooth; }
  .ai-hello { text-align: center; padding: 30px 10px 10px; }
  .ai-orb { width: 66px; height: 66px; border-radius: 20px; margin: 0 auto 14px; display: grid; place-items: center; font-size: 1.6rem; color: #fff; background: linear-gradient(135deg, #00BFFF, #7c3aed); box-shadow: 0 16px 34px -14px rgba(124,58,237,.6); }
  .ai-hello h2 { font: 800 1.5rem 'Poppins', sans-serif; margin: 0 0 6px; color: var(--text-main); }
  .ai-hello p { color: var(--text-dim); margin: 0 auto; max-width: 560px; line-height: 1.6; font-size: .92rem; }
  .ai-chips { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; margin: 24px 0 6px; text-align: left; }
  .ai-chip { display: flex; gap: 12px; align-items: center; padding: 14px; border-radius: 14px; border: 1px solid var(--border); background: var(--surface); color: var(--text-main); cursor: pointer; font: inherit; text-align: left; transition: border-color .15s, transform .15s; }
  .ai-chip:hover { border-color: var(--primary); transform: translateY(-2px); }
  .ai-chip i { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; background: var(--surface-2); color: var(--primary); flex: none; }
  .ai-chip b { font-size: .88rem; }
  .ai-msg { display: flex; gap: 12px; margin: 16px 0; align-items: flex-start; }
  .ai-msg.me { flex-direction: row-reverse; }
  .ai-av { width: 34px; height: 34px; border-radius: 11px; flex: none; display: grid; place-items: center; font-size: .85rem; color: #fff; background: linear-gradient(135deg, #00BFFF, #7c3aed); }
  .ai-msg.me .ai-av { background: var(--surface-2); color: var(--text-main); font-weight: 800; }
  .ai-bub { max-width: min(760px, 85%); padding: 13px 16px; border-radius: 16px; line-height: 1.65; font-size: .93rem; color: var(--text-main); background: var(--surface); border: 1px solid var(--border); overflow-wrap: anywhere; }
  .ai-msg.me .ai-bub { background: var(--primary); border-color: var(--primary); color: #fff; border-top-right-radius: 4px; white-space: pre-wrap; }
  .ai-msg.bot .ai-bub { border-top-left-radius: 4px; }
  .ai-bub p { margin: 0 0 10px; } .ai-bub p:last-child { margin-bottom: 0; }
  .ai-bub h3, .ai-bub h4 { font: 800 1rem 'Poppins', sans-serif; margin: 14px 0 6px; } .ai-bub h3:first-child, .ai-bub h4:first-child { margin-top: 0; }
  .ai-bub ul, .ai-bub ol { margin: 6px 0 10px; padding-left: 22px; } .ai-bub li { margin: 3px 0; }
  .ai-bub code { background: var(--surface-2); padding: 1px 6px; border-radius: 6px; font: .85em ui-monospace, Consolas, monospace; }
  .ai-bub pre { background: var(--surface-2); padding: 12px; border-radius: 10px; overflow-x: auto; white-space: pre-wrap; } .ai-bub pre code { background: none; padding: 0; }
  .ai-bub hr { border: 0; border-top: 1px solid var(--border); margin: 12px 0; }
  .ai-tools { display: flex; gap: 6px; margin-top: 8px; }
  .ai-tools button { background: none; border: 1px solid var(--border); color: var(--text-dim); border-radius: 8px; padding: 4px 10px; font: 600 .74rem Inter, sans-serif; cursor: pointer; }
  .ai-tools button:hover { color: var(--text-main); border-color: var(--text-muted); }
  .ai-typing span { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: var(--text-muted); margin-right: 4px; animation: aiDot 1.2s infinite; }
  .ai-typing span:nth-child(2) { animation-delay: .15s; } .ai-typing span:nth-child(3) { animation-delay: .3s; }
  @keyframes aiDot { 0%, 80%, 100% { opacity: .25; transform: translateY(0); } 40% { opacity: 1; transform: translateY(-3px); } }
  .ai-err { color: var(--danger, #ef4444); }
  .ai-box { border: 1.5px solid var(--border); border-radius: 18px; background: var(--surface); padding: 10px 10px 10px 16px; display: flex; gap: 10px; align-items: flex-end; box-shadow: 0 14px 36px -24px rgba(2,6,23,.5); }
  .ai-box:focus-within { border-color: var(--primary); }
  .ai-box textarea { flex: 1; border: 0; background: transparent; resize: none; color: var(--text-main); font: .95rem/1.55 Inter, sans-serif; max-height: 200px; padding: 8px 0; outline: none; }
  .ai-send { width: 42px; height: 42px; border-radius: 12px; border: 0; cursor: pointer; color: #fff; background: linear-gradient(135deg, #00BFFF, #0080FF); flex: none; }
  .ai-send:disabled { opacity: .45; cursor: not-allowed; }
  .ai-foot { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; font-size: .74rem; color: var(--text-muted); margin-top: 8px; padding: 0 4px; }
  .ai-foot button { background: none; border: 0; color: var(--text-dim); cursor: pointer; font: 600 .76rem Inter, sans-serif; }
  .ai-foot button:hover { color: var(--danger, #ef4444); }
  @media (max-width: 700px) { .ai-bub { max-width: 100%; } .ai-msg .ai-av { display: none; } .ai-wrap { height: calc(100vh - 140px); } }
</style>
<main class="main-content">
    <?php ax_header('AI Assistant', '', 'Powered by ' . sda_ai_label(), '<a class="ax-btn ghost sm" href="settings#ai"><i class="fas fa-gear"></i> AI settings</a>'); ?>
    <div class="ax-body">
        <?php if (!$ready): ?><div class="ax-panel" style="padding:14px 18px;border-color:#f59e0b"><i class="fas fa-key" style="color:#f59e0b"></i> No AI key is saved yet. Add your Anthropic key in <a href="settings#ai">Settings → AI</a>, then come back.</div><?php endif; ?>
        <div class="ai-wrap">
            <div class="ai-log" id="aiLog" aria-live="polite">
                <div class="ai-hello" id="aiHello"<?= $chat ? ' hidden' : '' ?>>
                    <div class="ai-orb"><i class="fas fa-wand-magic-sparkles"></i></div>
                    <h2>Hi <?= e($first) ?>, how can I help?</h2>
                    <p>I can draft announcements, emails and posts, prepare interview questions, and make sense of the academy's numbers. I see live totals (learners, enrolments, payments, tickets), never anyone's personal details.</p>
                    <div class="ai-chips"><?php foreach ($prompts as [$ic, $t, $p]): ?><button type="button" class="ai-chip" data-p="<?= e($p) ?>"><i class="fas fa-<?= e($ic) ?>"></i><b><?= e($t) ?></b></button><?php endforeach; ?></div>
                </div>
            </div>
            <div>
                <form class="ai-box" id="aiForm">
                    <textarea id="aiIn" rows="1" placeholder="Ask anything, or ask for a draft…" aria-label="Message" maxlength="8000"></textarea>
                    <button class="ai-send" id="aiSend" type="submit" aria-label="Send" disabled><i class="fas fa-arrow-up"></i></button>
                </form>
                <div class="ai-foot"><span>Enter to send · Shift+Enter for a new line · AI can make mistakes, so check drafts before sending.</span><button type="button" id="aiReset"><i class="fas fa-rotate-left"></i> New chat</button></div>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/main.js"></script>
<?php ax_footer_js(); ?>
<script>
(function () {
    'use strict';
    var CSRF = <?= json_encode(csrfToken()) ?>, HIST = <?= json_encode(array_values($chat), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    var INITIAL = <?= json_encode(mb_strtoupper(mb_substr($first, 0, 1))) ?>;
    var log = document.getElementById('aiLog'), inp = document.getElementById('aiIn'), send = document.getElementById('aiSend'), busy = false;

    function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function inline(s) {
        return s.replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<i>$2</i>').replace(/(^|\W)_([^_\n]+)_(?=\W|$)/g, '$1<i>$2</i>');
    }
    // Small, safe Markdown: text is escaped first, then simple formatting is added.
    function md(src) {
        var out = [], blocks = esc(src.replace(/\r/g, '')).split(/```/);
        blocks.forEach(function (b, bi) {
            if (bi % 2) { out.push('<pre><code>' + b.replace(/^\w*\n/, '') + '</code></pre>'); return; }
            var lines = b.split('\n'), list = null, para = [];
            function flushP() { if (para.length) { out.push('<p>' + inline(para.join('<br>')) + '</p>'); para = []; } }
            function flushL() { if (list) { out.push('<' + list.t + '>' + list.items.map(function (x) { return '<li>' + inline(x) + '</li>'; }).join('') + '</' + list.t + '>'); list = null; } }
            lines.forEach(function (l) {
                var m;
                if (/^\s*$/.test(l)) { flushP(); flushL(); }
                else if ((m = l.match(/^\s*#{1,6}\s+(.*)/))) { flushP(); flushL(); out.push('<h4>' + inline(m[1]) + '</h4>'); }
                else if (/^\s*(---|\*\*\*)\s*$/.test(l)) { flushP(); flushL(); out.push('<hr>'); }
                else if ((m = l.match(/^\s*[-*•]\s+(.*)/))) { flushP(); if (!list || list.t !== 'ul') { flushL(); list = { t: 'ul', items: [] }; } list.items.push(m[1]); }
                else if ((m = l.match(/^\s*\d+[.)]\s+(.*)/))) { flushP(); if (!list || list.t !== 'ol') { flushL(); list = { t: 'ol', items: [] }; } list.items.push(m[1]); }
                else { flushL(); para.push(l); }
            });
            flushP(); flushL();
        });
        return out.join('');
    }
    function add(role, text, err) {
        document.getElementById('aiHello').hidden = true;
        var d = document.createElement('div');
        d.className = 'ai-msg ' + (role === 'user' ? 'me' : 'bot');
        d.innerHTML = '<div class="ai-av">' + (role === 'user' ? esc(INITIAL) : '<i class="fas fa-wand-magic-sparkles"></i>') + '</div><div class="ai-bub"></div>';
        var b = d.querySelector('.ai-bub');
        if (role === 'user') b.textContent = text;
        else if (err) b.innerHTML = '<span class="ai-err"><i class="fas fa-circle-exclamation"></i> ' + esc(text) + '</span>';
        else {
            b.innerHTML = md(text) + '<div class="ai-tools"><button type="button" data-copy><i class="fas fa-copy"></i> Copy</button></div>';
            b.querySelector('[data-copy]').addEventListener('click', function (e) {
                var btn = e.currentTarget;
                (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { btn.innerHTML = '<i class="fas fa-check"></i> Copied'; setTimeout(function () { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 1500); }, function () {});
            });
        }
        log.appendChild(d);
        log.scrollTop = log.scrollHeight;
        return d;
    }
    HIST.forEach(function (m) { add(m.role, m.content); });

    function size() { inp.style.height = 'auto'; inp.style.height = Math.min(200, inp.scrollHeight) + 'px'; send.disabled = busy || !inp.value.trim(); }
    inp.addEventListener('input', size);
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); if (!send.disabled) document.getElementById('aiForm').requestSubmit(); } });
    document.querySelectorAll('.ai-chip').forEach(function (c) {
        c.addEventListener('click', function () {
            inp.value = c.dataset.p; size(); inp.focus();
            if (!/\s$/.test(c.dataset.p)) document.getElementById('aiForm').requestSubmit();
            else inp.setSelectionRange(inp.value.length, inp.value.length);
        });
    });
    document.getElementById('aiForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var text = inp.value.trim(); if (!text || busy) return;
        busy = true; inp.value = ''; size();
        add('user', text);
        var typing = add('assistant', ''); typing.querySelector('.ai-bub').innerHTML = '<span class="ai-typing"><span></span><span></span><span></span></span>';
        var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('action', 'send'); fd.append('message', text);
        fetch('ai-assistant', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) { typing.remove(); j.ok ? add('assistant', j.reply) : add('assistant', j.error || 'Something went wrong.', true); })
            .catch(function () { typing.remove(); add('assistant', 'Could not reach the server. Check your connection and try again.', true); })
            .then(function () { busy = false; size(); inp.focus(); });
    });
    document.getElementById('aiReset').addEventListener('click', function () {
        var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('action', 'reset');
        fetch('ai-assistant', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function () {
            log.querySelectorAll('.ai-msg').forEach(function (m) { m.remove(); });
            document.getElementById('aiHello').hidden = false; inp.focus();
        });
    });
    size();
})();
</script>
</body></html>
