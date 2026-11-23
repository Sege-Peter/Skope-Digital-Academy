<?php
$pageTitle = 'AI mentor';
require_once 'includes/header.php';

// Student progress, used as context for the mentor
try {
    // Last course joined
    $stmt = $pdo->prepare("SELECT c.title, c.id, cat.name as category
                          FROM enrollments e
                          JOIN courses c ON e.course_id = c.id
                          LEFT JOIN categories cat ON c.category_id = cat.id
                          WHERE e.student_id = ?
                          ORDER BY e.enrolled_at DESC LIMIT 1");
    $stmt->execute([$student['id']]);
    $lastCourse = $stmt->fetch();

    // Average quiz score
    $avgScore = $pdo->prepare("SELECT AVG(score) FROM quiz_attempts WHERE student_id = ?");
    $avgScore->execute([$student['id']]);
    $performance = $avgScore->fetchColumn() ?: 0;

    // Quizzes taken
    $quizCount = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ?");
    $quizCount->execute([$student['id']]);
    $totalQuizzes = $quizCount->fetchColumn() ?: 0;

    // Courses joined
    $enrollCount = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ?");
    $enrollCount->execute([$student['id']]);
    $totalEnrolled = $enrollCount->fetchColumn() ?: 0;

} catch (Exception $e) { $lastCourse = null; $performance = 0; $totalQuizzes = 0; $totalEnrolled = 0; }

$isSaturday = (date('l') === 'Saturday');
$first_name = explode(' ', trim((string)$student['name']))[0] ?: 'there';

// Context sent with each question (same text as before, safely encoded for JavaScript)
$mentor_context = "Course: " . ($lastCourse['title'] ?? 'None') . ". Performance: " . round($performance) . "%. User: " . $student['name'];
$student_avatar_html = !empty($student['avatar'])
    ? '<img src="../uploads/avatars/' . htmlspecialchars($student['avatar']) . '" alt="">'
    : '<i class="fas fa-user"></i>';

require_once 'includes/layout-top.php';
?>

<style>
.pg-mentor .chat { display: flex; flex-direction: column; height: min(760px, calc(100dvh - var(--topbar-h) - 150px)); min-height: 440px; padding: 0; overflow: hidden; }
.pg-mentor .chat-head { display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-bottom: 1px solid var(--border); }
.pg-mentor .bot-ava { position: relative; width: 42px; height: 42px; border-radius: 12px; background: var(--grad-primary); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.pg-mentor .bot-ava::after { content: ''; position: absolute; right: -3px; bottom: -3px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; background: var(--success); }
.pg-mentor .bot-ava.offline::after { background: var(--warning); }
.pg-mentor .chat-messages { flex: 1; overflow-y: auto; padding: 20px 18px; display: flex; flex-direction: column; gap: 14px; background: var(--surface-2); overscroll-behavior: contain; }
.pg-mentor .msg-row { display: flex; gap: 10px; align-items: flex-end; max-width: 100%; animation: pgFade .25s ease; }
.pg-mentor .msg-row.student-row { flex-direction: row-reverse; }
.pg-mentor .msg-row > div:last-child { min-width: 0; max-width: min(80%, 640px); }
.pg-mentor .msg-ava { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: .78rem; background: var(--primary); color: #fff; }
.pg-mentor .msg-ava.me { background: #E2E8F0; color: var(--text-dim); }
.pg-mentor .msg-ava img { width: 100%; height: 100%; object-fit: cover; }
.pg-mentor .msg-bubble { padding: 11px 15px; border-radius: 16px; font-size: .92rem; line-height: 1.6; overflow-wrap: anywhere; }
.pg-mentor .mentor-msg { background: var(--surface); border: 1px solid var(--border); border-bottom-left-radius: 4px; }
.pg-mentor .student-msg { background: var(--primary); color: #fff; border-bottom-right-radius: 4px; }
.pg-mentor .chat-input { padding: 12px 14px; border-top: 1px solid var(--border); background: var(--surface); }
.pg-mentor .chat-input-wrap { display: flex; align-items: flex-end; gap: 8px; background: var(--surface-2); border: 1px solid var(--border-strong); border-radius: 14px; padding: 6px 6px 6px 14px; }
.pg-mentor .chat-input-wrap:focus-within { border-color: var(--primary); background: var(--surface); box-shadow: 0 0 0 3px var(--primary-glow); }
.pg-mentor #userInput { flex: 1; min-width: 0; border: 0; background: transparent; outline: none; font: inherit; font-size: .95rem; resize: none; max-height: 120px; padding: 9px 0; color: var(--text-main); }
.pg-mentor #sendBtn { width: 42px; height: 42px; padding: 0; flex-shrink: 0; }
.pg-mentor .suggestions { display: flex; gap: 8px; margin-top: 10px; overflow-x: auto; scrollbar-width: none; }
.pg-mentor .suggestions::-webkit-scrollbar { display: none; }
.pg-mentor .suggestion-tag { flex-shrink: 0; border: 1px solid var(--border-strong); background: var(--surface); color: var(--text-main); border-radius: 999px; padding: 6px 12px; font: inherit; font-size: .8rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
.pg-mentor .suggestion-tag:hover { border-color: var(--primary); color: var(--primary-dark); }
@keyframes pgFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
@media (max-width: 640px) {
    .pg-mentor .chat { height: calc(100dvh - var(--topbar-h) - 130px); min-height: 380px; border-radius: var(--radius-md); }
    .pg-mentor .chat-messages { padding: 14px 12px; }
    .pg-mentor .msg-row > div:last-child { max-width: 86%; }
    .pg-mentor .sp-page-head { margin-bottom: 14px; }
    .pg-mentor .sp-page-head p { display: none; }
}
</style>

<div class="pg-mentor">
    <div class="sp-page-head">
        <div><h1>AI mentor</h1><p>Ask questions about your lessons, study plans or tech careers. Answers come from AI, so double-check important facts.</p></div>
    </div>

    <div class="sp-split">
        <div class="sp-card chat">
            <div class="chat-head">
                <div class="bot-ava <?= $isSaturday ? 'offline' : '' ?>"><i class="fas fa-robot"></i></div>
                <div class="sp-grow" style="flex: 1; min-width: 0;">
                    <strong style="display: block; font-size: .95rem;">Skope mentor</strong>
                    <span class="sp-muted" style="font-size: .76rem;"><?= $isSaturday ? 'Replies may be slower on Saturdays' : 'Online and ready to help' ?></span>
                </div>
                <button type="button" class="sp-icon-btn" onclick="clearChat()" aria-label="Clear conversation" title="Clear conversation"><i class="fas fa-trash-can"></i></button>
            </div>

            <div class="chat-messages" id="chatBox">
                <div class="msg-row">
                    <div class="msg-ava"><i class="fas fa-robot"></i></div>
                    <div>
                        <div class="msg-bubble mentor-msg">
                            Hi <strong><?= htmlspecialchars($first_name) ?></strong>! 👋 I'm your AI mentor.<br><br>
                            <?php if ($lastCourse): ?>
                                I see you're studying <strong><?= htmlspecialchars($lastCourse['title']) ?></strong>. What would you like help with today?
                            <?php else: ?>
                                Would you like me to suggest a learning path based on tech skills in demand in Kenya? 🇰🇪
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chat-input">
                <div class="chat-input-wrap">
                    <textarea id="userInput" rows="1" placeholder="Ask a question…" aria-label="Your question"></textarea>
                    <button type="button" class="sp-btn" id="sendBtn" onclick="sendMessage()" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div class="suggestions">
                    <button type="button" class="suggestion-tag" onclick="usePrompt('Explain my latest lesson')">📚 Explain my lesson</button>
                    <button type="button" class="suggestion-tag" onclick="usePrompt('Career advice for tech in Kenya')">💼 Career advice</button>
                    <button type="button" class="suggestion-tag" onclick="usePrompt('Give me a 4-week study plan')">📅 Study plan</button>
                </div>
            </div>
        </div>

        <aside class="sp-hide-sm">
            <div class="sp-card">
                <div class="sp-card-head"><h4>Your progress</h4></div>
                <div class="sp-grid" style="grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="sp-stat" style="padding: 12px;"><div><strong><?= (int)$totalEnrolled ?></strong><span>Courses</span></div></div>
                    <div class="sp-stat" style="padding: 12px;"><div><strong><?= round($performance) ?>%</strong><span>Avg quiz score</span></div></div>
                </div>
                <p class="sp-small sp-muted" style="margin: 12px 0 0;"><?= (int)$totalQuizzes ?> quiz<?= (int)$totalQuizzes === 1 ? '' : 'zes' ?> taken</p>
            </div>
            <div class="sp-card">
                <div class="sp-card-head"><h4><i class="fas fa-lightbulb" style="color: var(--warning);"></i> Tips</h4></div>
                <p class="sp-small sp-muted" style="margin: 0;">The mentor knows which course you're on and your average quiz score. Ask it to explain a topic, quiz you, or plan your week. Your chat is cleared when you leave the page.</p>
            </div>
        </aside>
    </div>
</div>

<script>
const chatBox = document.getElementById('chatBox');
const userInput = document.getElementById('userInput');
const sendBtn = document.getElementById('sendBtn');

function clearChat() {
    if(confirm('Are you sure you want to clear this conversation?')) {
        chatBox.innerHTML = '';
        location.reload();
    }
}

function usePrompt(text) {
    userInput.value = text;
    sendMessage();
}

function sendMessage() {
    const text = userInput.value.trim();
    if(!text) return;

    appendMessage(text, 'student');
    userInput.value = '';
    userInput.style.height = '';

    // Show typing indicator
    const typingIndicator = document.createElement('div');
    typingIndicator.className = 'msg-row';
    typingIndicator.id = 'typing-indicator';
    typingIndicator.innerHTML = '<div class="msg-ava"><i class="fas fa-robot"></i></div><div><div class="msg-bubble mentor-msg"><i class="fas fa-ellipsis-h fa-beat"></i> Thinking…</div></div>';
    chatBox.appendChild(typingIndicator);
    chatBox.scrollTop = chatBox.scrollHeight;

    const formData = new FormData();
    formData.append('action', 'mentor_chat');
    formData.append('query', text);
    formData.append('context', <?= json_encode($mentor_context, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>);

    fetch('ai-mentor', { method: 'POST', body: formData, credentials: 'same-origin', headers: { 'X-CSRF-Token': <?= json_encode(csrfToken()) ?> } })
        .then(res => res.json())
        .then(data => {
            document.getElementById('typing-indicator').remove();
            if(data.success) {
                appendMessage(data.response, 'mentor');
            } else {
                appendMessage(data.error || 'Sorry, the mentor could not answer. Please try again.', 'mentor');
            }
        })
        .catch(() => {
            document.getElementById('typing-indicator').remove();
            appendMessage("Connection lost. Please check your internet.", 'mentor');
        });
}

// Escape HTML, then allow **bold** and line breaks
function formatText(text) {
    const esc = String(text ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    return esc.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
}

function appendMessage(text, role) {
    const row = document.createElement('div');
    row.className = `msg-row ${role === 'student' ? 'student-row' : ''}`;

    const avatar = role === 'mentor'
        ? '<div class="msg-ava"><i class="fas fa-robot"></i></div>'
        : <?= json_encode('<div class="msg-ava me">' . $student_avatar_html . '</div>', JSON_HEX_TAG | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES) ?>;

    row.innerHTML = `${avatar}<div><div class="msg-bubble ${role === 'mentor' ? 'mentor-msg' : 'student-msg'}">${formatText(text)}</div></div>`;
    chatBox.appendChild(row);
    chatBox.scrollTop = chatBox.scrollHeight;
}

// Enter sends, Shift+Enter adds a new line; the box grows as you type
userInput.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});
userInput.addEventListener('input', () => {
    userInput.style.height = 'auto';
    userInput.style.height = Math.min(userInput.scrollHeight, 120) + 'px';
});
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
