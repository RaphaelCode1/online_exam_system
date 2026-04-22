<?php
/**
 * Chat Widget - Floating AI Assistant
 */

// Initialize chatbot if not already
if (!isset($chatbot) && function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/chatbot.php';
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $chatbot = new ExamChatbot($conn, $user_id);
}
?>

<!-- Chat Widget HTML -->
<div id="chatWidget" class="chat-widget" style="display: none;">
    <div class="chat-widget-header" onclick="toggleChat()">
        <div class="chat-widget-header-content">
            <i class="fas fa-robot"></i>
            <div>
                <strong>AI Exam Assistant</strong>
                <small>Powered by MissionTech</small>
            </div>
        </div>
        <div class="chat-widget-actions">
            <button class="chat-minimize" onclick="toggleChat(event)">
                <i class="fas fa-chevron-down"></i>
            </button>
            <button class="chat-close" onclick="closeChat(event)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    
    <div class="chat-widget-messages" id="chatMessages">
        <div class="chat-message bot">
            <div class="message-content">
                <i class="fas fa-robot"></i>
                <div class="message-text">
                    Hello! 👋 I'm your AI Exam Assistant.<br>
                    Ask me anything about:
                    <ul>
                        <li>📝 Exam information</li>
                        <li>📊 Results and scores</li>
                        <li>📜 Certificates</li>
                        <li>❓ How to use the system</li>
                        <li>🔧 Technical support</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="chat-widget-input">
        <textarea id="chatInput" placeholder="Type your question here..." rows="2"></textarea>
        <button id="chatSendBtn" onclick="sendMessage()">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<!-- Chat Button -->
<div id="chatButton" class="chat-button" onclick="openChat()">
    <i class="fas fa-robot"></i>
    <span class="chat-badge">AI</span>
</div>

<style>
/* Chat Widget Styles */
.chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 380px;
    height: 500px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
    z-index: 10000;
    animation: slideUp 0.3s ease;
    font-family: 'Inter', sans-serif;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chat-widget-header {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 1rem;
    border-radius: 16px 16px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.chat-widget-header-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.chat-widget-header-content i {
    font-size: 1.5rem;
}

.chat-widget-header-content strong {
    font-size: 1rem;
    display: block;
}

.chat-widget-header-content small {
    font-size: 0.7rem;
    opacity: 0.9;
}

.chat-widget-actions {
    display: flex;
    gap: 8px;
}

.chat-widget-actions button {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.chat-widget-actions button:hover {
    background: rgba(255,255,255,0.3);
}

.chat-widget-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.chat-message {
    display: flex;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chat-message.user {
    justify-content: flex-end;
}

.chat-message.user .message-content {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.chat-message.bot .message-content {
    background: white;
    border: 1px solid #e2e8f0;
}

.message-content {
    max-width: 80%;
    padding: 0.75rem 1rem;
    border-radius: 12px;
    display: flex;
    gap: 8px;
}

.message-content i {
    margin-top: 2px;
}

.message-text {
    font-size: 0.85rem;
    line-height: 1.5;
}

.message-text ul {
    margin: 8px 0 0 20px;
    font-size: 0.8rem;
}

.chat-widget-input {
    padding: 1rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 8px;
    background: white;
    border-radius: 0 0 16px 16px;
}

.chat-widget-input textarea {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    resize: none;
    font-family: inherit;
    font-size: 0.85rem;
    outline: none;
    transition: all 0.3s;
}

.chat-widget-input textarea:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 2px rgba(16,185,129,0.1);
}

.chat-widget-input button {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}

.chat-widget-input button:hover {
    transform: scale(1.05);
}

.chat-button {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(16,185,129,0.4);
    transition: all 0.3s;
    z-index: 9999;
}

.chat-button:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(16,185,129,0.5);
}

.chat-button i {
    font-size: 1.5rem;
    color: white;
}

.chat-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    font-size: 0.6rem;
    padding: 2px 6px;
    border-radius: 20px;
    font-weight: bold;
}

@media (max-width: 480px) {
    .chat-widget {
        width: calc(100% - 40px);
        height: 450px;
        bottom: 10px;
        right: 10px;
    }
}
</style>

<script>
let isTyping = false;

function openChat() {
    document.getElementById('chatWidget').style.display = 'flex';
    document.getElementById('chatButton').style.display = 'none';
    document.getElementById('chatInput').focus();
}

function closeChat(event) {
    if (event) event.stopPropagation();
    document.getElementById('chatWidget').style.display = 'none';
    document.getElementById('chatButton').style.display = 'flex';
}

function toggleChat(event) {
    if (event) event.stopPropagation();
    const widget = document.getElementById('chatWidget');
    const messages = document.getElementById('chatMessages');
    const input = document.getElementById('chatWidgetInput');
    
    if (widget.style.height === 'auto') {
        widget.style.height = '500px';
        messages.style.display = 'flex';
        input.style.display = 'flex';
    } else {
        widget.style.height = 'auto';
        messages.style.display = 'none';
        input.style.display = 'none';
    }
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message to chat
    addMessage(message, 'user');
    input.value = '';
    
    // Show typing indicator
    showTypingIndicator();
    
    // Send to server
    fetch('/online_exam_system/api/chatbot.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ message: message })
    })
    .then(response => response.json())
    .then(data => {
        hideTypingIndicator();
        addMessage(data.response, 'bot');
        
        // Auto-scroll to bottom
        const messagesDiv = document.getElementById('chatMessages');
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    })
    .catch(error => {
        hideTypingIndicator();
        addMessage('Sorry, I encountered an error. Please try again.', 'bot');
    });
}

function addMessage(text, sender) {
    const messagesDiv = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${sender}`;
    messageDiv.innerHTML = `
        <div class="message-content">
            <i class="fas ${sender === 'user' ? 'fa-user' : 'fa-robot'}"></i>
            <div class="message-text">${text}</div>
        </div>
    `;
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function showTypingIndicator() {
    if (isTyping) return;
    isTyping = true;
    
    const messagesDiv = document.getElementById('chatMessages');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'chat-message bot typing-indicator';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = `
        <div class="message-content">
            <i class="fas fa-robot"></i>
            <div class="message-text">
                <span class="typing-dot">.</span><span class="typing-dot">.</span><span class="typing-dot">.</span>
            </div>
        </div>
    `;
    messagesDiv.appendChild(typingDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function hideTypingIndicator() {
    const indicator = document.getElementById('typingIndicator');
    if (indicator) {
        indicator.remove();
    }
    isTyping = false;
}

// Enter key to send
document.getElementById('chatInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});
</script>