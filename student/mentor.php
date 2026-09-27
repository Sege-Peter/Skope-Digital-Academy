<?php
$pageTitle = 'AI Academic Mentor';
require_once 'includes/header.php';

// Fetch Student Progress for Intelligence
try {
    // Get last studied course
    $stmt = $pdo->prepare("SELECT c.title, c.id, cat.name as category 
                          FROM enrollments e 
                          JOIN courses c ON e.course_id = c.id 
                          LEFT JOIN categories cat ON c.category_id = cat.id
                          WHERE e.student_id = ? 
                          ORDER BY e.enrolled_at DESC LIMIT 1");
    $stmt->execute([$student['id']]);
    $lastCourse = $stmt->fetch();

    // Get average quiz score
    $avgScore = $pdo->prepare("SELECT AVG(score) FROM quiz_attempts WHERE student_id = ?");
    $avgScore->execute([$student['id']]);
    $performance = $avgScore->fetchColumn() ?: 0;

    // Count quizzes taken
    $quizCount = $pdo->prepare("SELECT COUNT(*) FROM quiz_attempts WHERE student_id = ?");
    $quizCount->execute([$student['id']]);
    $totalQuizzes = $quizCount->fetchColumn() ?: 0;

    // Count enrolled courses
    $enrollCount = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ?");
    $enrollCount->execute([$student['id']]);
    $totalEnrolled = $enrollCount->fetchColumn() ?: 0;

} catch (Exception $e) { $lastCourse = null; $performance = 0; $totalQuizzes = 0; $totalEnrolled = 0; }

$isSaturday = (date('l') === 'Saturday');

require_once 'includes/layout-top.php';
?>

<style>
/* ── Mentor Layout ────────────────────────────────────────── */
.mentor-page { display: grid; grid-template-columns: 1fr 340px; gap: 28px; min-height: 80vh; }
.chat-container { display: flex; flex-direction: column; background: #fff; border: 1px solid var(--border); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-sm); position: relative; }

/* ── Chat Header ───────────────────────────────────────────── */
.chat-header { padding: 20px 28px; background: #fff; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 16px; }
.mentor-avatar { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, var(--primary), #0062a3); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #fff; position: relative; }
.mentor-avatar::after { content: ''; position: absolute; bottom: -2px; right: -2px; width: 14px; height: 14px; border-radius: 50%; border: 2.5px solid #fff; background: #10B981; }
.mentor-avatar.offline::after { background: #EF4444; }

/* ── Chat Messages ─────────────────────────────────────────── */
.chat-messages { flex: 1; padding: 28px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; background: #f8fafc; }
.msg-row { display: flex; gap: 12px; align-items: flex-end; animation: fadeInUp 0.3s ease; }
.msg-row.student-row { flex-direction: row-reverse; }
.msg-bubble { max-width: 80%; padding: 14px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.6; position: relative; }
.mentor-msg { background: #fff; border: 1px solid var(--border); border-bottom-left-radius: 4px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.student-msg { background: var(--primary); color: #fff; border-bottom-right-radius: 4px; box-shadow: 0 10px 15px -3px rgba(0,174,239,0.2); }

/* ── Chat Input ────────────────────────────────────────────── */
.chat-input-area { padding: 18px 24px; border-top: 1px solid var(--border); background: #fff; }
.chat-input-wrap { display: flex; align-items: center; gap: 12px; background: #f1f5f9; border: 1px solid var(--border); border-radius: 16px; padding: 10px 10px 10px 20px; transition: 0.2s; }
.chat-input-wrap:focus-within { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px var(--primary-light); }
#userInput { flex: 1; border: none; background: transparent; outline: none; font-size: 0.95rem; font-family: inherit; resize: none; max-height: 120px; }

@media (max-width: 1100px) {
    .mentor-page { grid-template-columns: 1fr; }
    .mentor-sidebar { display: none; }
}
</style>

<div class="mentor-page">
    <div class="chat-container">
        <div class="chat-header">
            <div class="mentor-avatar <?= $isSaturday ? 'offline' : '' ?>"><i class="fas fa-robot"></i></div>
            <div style="flex: 1;">
                <h5 style="margin: 0; font-weight: 800;">SDA Global Mentor</h5>
                <p style="margin: 2px 0 0; font-size: 0.75rem; color: var(--text-dim);">
                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: <?= $isSaturday ? '#ef4444' : '#10b981' ?>; margin-right: 4px;"></span>
                    <?= $isSaturday ? 'Maintenance Rest' : 'Online & Ready to Guide' ?>
                </p>
            </div>
            <button onclick="clearChat()" style="width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--border); background: none; color: var(--text-dim); transition: 0.2s;" onmouseover="this.style.color='#ef4444'"><i class="fas fa-trash-alt"></i></button>
        </div>

        <div class="chat-messages" id="chatBox">
            <div class="msg-row">
                <div style="width: 34px; height: 34px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; flex-shrink: 0;"><i class="fas fa-robot"></i></div>
                <div>
                    <div class="msg-bubble mentor-msg">
                        Hello <strong><?= htmlspecialchars($student['name']) ?></strong>! 👋 I'm your AI Academic Mentor.<br><br>
                        <?php if ($lastCourse): ?>
                            I see you're currently studying <strong><?= htmlspecialchars($lastCourse['title']) ?></strong>. 
                            How can I help you master your curriculum today?
                        <?php else: ?>
                            Would you like me to recommend a learning path based on current industry trends in Kenya? 🇰🇪
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="chat-input-area">
            <div class="chat-input-wrap">
                <textarea id="userInput" rows="1" placeholder="Type your academic or career question here..."></textarea>
                <button class="btn-premium" id="sendBtn" onclick="sendMessage()" style="padding: 10px 16px; border-radius: 10px; border: none;"><i class="fas fa-paper-plane"></i></button>
            </div>
            <div style="display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap;">
                <button class="suggestion-tag" onclick="usePrompt('Explain my latest lesson')">📚 Explain Lesson</button>
                <button class="suggestion-tag" onclick="usePrompt('Career advice for tech in Kenya')">💼 Career Advice</button>
                <button class="suggestion-tag" onclick="usePrompt('Give me a 4-week study plan')">📅 Study Planner</button>
            </div>
        </div>
    </div>

    <aside class="mentor-sidebar">
        <div class="card" style="padding: 24px; border-radius: 20px; margin-bottom: 24px;">
            <h6 style="font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-dim); margin-bottom: 16px; font-size: 0.7rem;"><i class="fas fa-chart-line"></i> Performance IQ</h6>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div style="background: #f8fafc; padding: 12px; border-radius: 12px; text-align: center;">
                    <strong style="display: block; font-size: 1.2rem; color: var(--primary);"><?= $totalEnrolled ?></strong>
                    <span style="font-size: 0.65rem; color: var(--text-dim);">Courses</span>
                </div>
                <div style="background: #f8fafc; padding: 12px; border-radius: 12px; text-align: center;">
                    <strong style="display: block; font-size: 1.2rem; color: var(--primary);"><?= round($performance) ?>%</strong>
                    <span style="font-size: 0.65rem; color: var(--text-dim);">Avg Score</span>
                </div>
            </div>
        </div>

        <div class="card" style="padding: 24px; border-radius: 20px; background: linear-gradient(135deg, var(--primary), #0062a3); color: white; border: none;">
            <h6 style="margin: 0; font-weight: 800;"><i class="fas fa-microchip"></i> SDA Gemini Core</h6>
            <p style="margin-top: 10px; font-size: 0.8rem; opacity: 0.9; line-height: 1.6;">I am trained on your unique academic progress to provide hyper-personalized feedback and career coaching available 24/7.</p>
        </div>
    </aside>
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
    
    // Show typing indicator
    const typingIndicator = document.createElement('div');
    typingIndicator.className = 'msg-row';
    typingIndicator.id = 'typing-indicator';
    typingIndicator.innerHTML = '<div style="width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; background: white;"><i class="fas fa-robot"></i></div><div><div class="msg-bubble mentor-msg"><i class="fas fa-ellipsis-h fa-beat"></i> Analyzing...</div></div>';
    chatBox.appendChild(typingIndicator);
    chatBox.scrollTop = chatBox.scrollHeight;

    const formData = new FormData();
    formData.append('action', 'mentor_chat');
    formData.append('query', text);
    formData.append('context', "Course: <?= addslashes($lastCourse['title'] ?? 'None') ?>. Performance: <?= round($performance) ?>%. User: <?= addslashes($student['name']) ?>");

    fetch('../includes/ai_controller.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            document.getElementById('typing-indicator').remove();
            if(data.success) {
                appendMessage(data.response, 'mentor');
            } else {
                appendMessage("I encountered a technical glitch. Error: " + (data.error || 'System Timeout'), 'mentor');
            }
        })
        .catch(() => {
            document.getElementById('typing-indicator').remove();
            appendMessage("Connection lost. Please check your internet.", 'mentor');
        });
}

function appendMessage(text, role) {
    const row = document.createElement('div');
    row.className = `msg-row ${role === 'student' ? 'student-row' : ''}`;
    
    const avatar = role === 'mentor' 
        ? '<div style="width: 34px; height: 34px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 0.8rem; flex-shrink: 0;"><i class="fas fa-robot"></i></div>'
        : '<div style="width: 34px; height: 34px; border-radius: 50%; background: #e2e8f0; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--text-dim); font-size: 0.8rem; flex-shrink: 0; overflow: hidden;"><?php if($student['avatar']): ?><img src="../uploads/avatars/<?= $student['avatar'] ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: ?><i class="fas fa-user-astronaut"></i><?php endif; ?></div>';
    
    row.innerHTML = `${avatar}<div><div class="msg-bubble ${role === 'mentor' ? 'mentor-msg' : 'student-msg'}">${text.replace(/\n/g, '<br>')}</div></div>`;
    chatBox.appendChild(row);
    chatBox.scrollTop = chatBox.scrollHeight;
}
</script>

<?php require_once 'includes/layout-bottom.php'; ?>
