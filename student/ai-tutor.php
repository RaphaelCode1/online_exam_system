<?php
/**
 * AI Tutor - Personalized Learning Assistant
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/gemini.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$student = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

$message = '';
$response = '';
$query = '';

// Handle AI Query
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = trim($_POST['query']);
    
    if (!empty($query)) {
        // Get user's exam history for context
        $exam_history = $conn->query("
            SELECT e.title, ea.percentage, ea.passed 
            FROM exam_attempts ea 
            JOIN exams e ON ea.exam_id = e.id 
            WHERE ea.student_id = $user_id AND ea.status = 'completed' 
            ORDER BY ea.created_at DESC LIMIT 5
        ");
        
        $history_text = "";
        while ($history = $exam_history->fetch_assoc()) {
            $status = $history['passed'] ? 'passed' : 'attempted';
            $history_text .= "- {$history['title']}: {$history['percentage']}% ({$status})\n";
        }
        
        $prompt = "You are an AI tutor for a student named {$student['full_name']}. 
        The student's recent exam history:
        {$history_text}
        
        The student asks: {$query}
        
        Provide a helpful, encouraging, and educational response. Focus on exam preparation, study tips, and subject understanding.";
        
        $response = generateWithGemini($prompt, ['temperature' => 0.7, 'max_output_tokens' => 500]);
        
        if (!$response) {
            $response = "I'm having trouble connecting to the AI service. Please try again in a moment.";
        }
        
        // Log the conversation
        $stmt = $conn->prepare("INSERT INTO chatbot_conversations (user_id, user_message, bot_response, intent, created_at) VALUES (?, ?, ?, 'ai_tutor', NOW())");
        $stmt->bind_param("iss", $user_id, $query, $response);
        $stmt->execute();
    }
}

$page_title = 'AI Tutor';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-robot"></i> AI Learning Assistant</h1>
        <p>Your personal AI tutor - ask any question about your studies, exam preparation, or subject topics</p>
    </div>
    
    <div class="tutor-layout">
        <!-- Chat Area -->
        <div class="chat-area">
            <div class="chat-messages" id="chatMessages">
                <?php if ($response): ?>
                    <div class="message user-message">
                        <div class="message-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="message-content">
                            <div class="message-text"><?php echo nl2br(htmlspecialchars($query)); ?></div>
                            <div class="message-time"><?php echo date('h:i A'); ?></div>
                        </div>
                    </div>
                    <div class="message bot-message">
                        <div class="message-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="message-content">
                            <div class="message-text"><?php echo nl2br(htmlspecialchars($response)); ?></div>
                            <div class="message-time"><?php echo date('h:i A'); ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="welcome-message">
                        <div class="welcome-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3>Welcome to AI Tutor, <?php echo htmlspecialchars($student['full_name']); ?>! 👋</h3>
                        <p>I'm your personal AI learning assistant. I can help you with:</p>
                        <ul>
                            <li>📚 Explaining difficult concepts</li>
                            <li>📝 Exam preparation tips</li>
                            <li>🔍 Answering subject-specific questions</li>
                            <li>💡 Study strategies and techniques</li>
                            <li>📊 Understanding your exam results</li>
                        </ul>
                        <p>What would you like to learn today?</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" class="chat-input-form">
                <textarea name="query" class="chat-input" placeholder="Ask me anything about your studies..." rows="3" required><?php echo htmlspecialchars($query); ?></textarea>
                <button type="submit" class="send-btn">
                    <i class="fas fa-paper-plane"></i>
                    Send
                </button>
            </form>
        </div>
        
        <!-- Suggestions Sidebar -->
        <div class="suggestions-sidebar">
            <h3><i class="fas fa-lightbulb"></i> Suggested Topics</h3>
            <div class="suggestion-list">
                <button class="suggestion-btn" data-query="How can I prepare effectively for exams?">
                    <i class="fas fa-chart-line"></i> Exam Preparation Tips
                </button>
                <button class="suggestion-btn" data-query="What are the best study techniques?">
                    <i class="fas fa-brain"></i> Study Techniques
                </button>
                <button class="suggestion-btn" data-query="How to manage exam stress?">
                    <i class="fas fa-heart"></i> Managing Exam Stress
                </button>
                <button class="suggestion-btn" data-query="Explain time management for studying">
                    <i class="fas fa-clock"></i> Time Management
                </button>
                <button class="suggestion-btn" data-query="How to improve memory retention?">
                    <i class="fas fa-database"></i> Memory Retention
                </button>
            </div>
            
            <h3 style="margin-top: 1.5rem;"><i class="fas fa-chart-simple"></i> Your Stats</h3>
            <div class="stats-card">
                <?php
                $total_exams = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'")->fetch_assoc()['total'];
                $avg_score = $conn->query("SELECT AVG(percentage) as avg FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'")->fetch_assoc()['avg'];
                $passed = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND status = 'completed' AND passed = 1")->fetch_assoc()['total'];
                ?>
                <div class="stat-item">
                    <span class="stat-label">Exams Taken</span>
                    <span class="stat-value"><?php echo $total_exams; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Average Score</span>
                    <span class="stat-value"><?php echo round($avg_score ?? 0, 1); ?>%</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Exams Passed</span>
                    <span class="stat-value"><?php echo $passed; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.page-header {
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.tutor-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 2rem;
}

.chat-area {
    background: white;
    border-radius: 24px;
    border: 1px solid #eef2f6;
    display: flex;
    flex-direction: column;
    height: 70vh;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.message {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    animation: fadeIn 0.3s ease;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.user-message .message-avatar {
    color: #10b981;
}

.bot-message .message-avatar {
    color: #3b82f6;
}

.message-content {
    flex: 1;
    background: #f8fafc;
    padding: 1rem;
    border-radius: 16px;
    max-width: 80%;
}

.user-message {
    justify-content: flex-end;
}

.user-message .message-content {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.bot-message .message-content {
    background: #f1f5f9;
}

.message-text {
    line-height: 1.5;
    white-space: pre-wrap;
}

.message-time {
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 0.5rem;
    text-align: right;
}

.welcome-message {
    text-align: center;
    padding: 2rem;
}

.welcome-icon {
    font-size: 3rem;
    color: #10b981;
    margin-bottom: 1rem;
}

.welcome-message h3 {
    font-size: 1.3rem;
    color: #1e293b;
    margin-bottom: 1rem;
}

.welcome-message p {
    color: #64748b;
    margin-bottom: 1rem;
}

.welcome-message ul {
    text-align: left;
    display: inline-block;
    margin: 1rem 0;
    color: #475569;
}

.welcome-message li {
    margin: 0.5rem 0;
}

.chat-input-form {
    padding: 1rem;
    border-top: 1px solid #eef2f6;
    display: flex;
    gap: 1rem;
}

.chat-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-family: inherit;
    resize: none;
    transition: all 0.3s;
}

.chat-input:focus {
    outline: none;
    border-color: #10b981;
}

.send-btn {
    padding: 12px 24px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.send-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16,185,129,0.3);
}

.suggestions-sidebar {
    background: white;
    border-radius: 24px;
    border: 1px solid #eef2f6;
    padding: 1.5rem;
}

.suggestions-sidebar h3 {
    font-size: 1rem;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.suggestion-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.suggestion-btn {
    padding: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    text-align: left;
    font-size: 0.85rem;
    color: #1e293b;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.suggestion-btn:hover {
    background: #ecfdf5;
    border-color: #10b981;
    color: #10b981;
    transform: translateX(5px);
}

.stats-card {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.stat-item:last-child {
    border-bottom: none;
}

.stat-label {
    color: #64748b;
    font-size: 0.85rem;
}

.stat-value {
    font-weight: 700;
    color: #10b981;
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

@media (max-width: 1024px) {
    .tutor-layout {
        grid-template-columns: 1fr;
    }
    
    .suggestions-sidebar {
        order: -1;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .message-content {
        max-width: 90%;
    }
    
    .chat-input-form {
        flex-direction: column;
    }
    
    .send-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Loading Animation */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading {
    animation: pulse 1s ease-in-out infinite;
}
</style>

<script>
// Auto-scroll to bottom
const chatMessages = document.getElementById('chatMessages');
if (chatMessages) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Suggestion buttons
document.querySelectorAll('.suggestion-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const query = this.dataset.query;
        const textarea = document.querySelector('.chat-input');
        if (textarea) {
            textarea.value = query;
            textarea.focus();
        }
    });
});

// Auto-resize textarea
document.querySelector('.chat-input')?.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 150) + 'px';
});
</script>

<?php include '../includes/footer.php'; ?>