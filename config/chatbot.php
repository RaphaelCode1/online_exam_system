<?php
/**
 * Chatbot Configuration
 * Free AI-powered chatbot for the examination system
 */

require_once __DIR__ . '/database.php';

// Database connection for chatbot memory
$db = Database::getInstance();
$conn = $db->getConnection();

// Create chatbot conversation table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    session_id VARCHAR(100) NOT NULL,
    user_message TEXT,
    bot_response TEXT,
    intent VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create chatbot knowledge base table
$conn->query("CREATE TABLE IF NOT EXISTS chatbot_knowledge (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category VARCHAR(50) NOT NULL,
    keywords TEXT,
    response TEXT,
    priority INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Insert default knowledge base
$conn->query("INSERT IGNORE INTO chatbot_knowledge (category, keywords, response, priority) VALUES
('greeting', 'hi,hello,hey,good morning,good afternoon,good evening', 'Hello! Welcome to MissionTech College Online Examination System. How can I help you today?', 10),
('exam_info', 'exam,test,assessment,online exam', 'Our online examination system allows you to take exams, view results, and track your progress. You can find available exams in the \"Take Exam\" section.', 9),
('results', 'result,score,grade,performance', 'You can view your exam results in the \"Results\" section. Your scores are automatically calculated and displayed with detailed feedback.', 8),
('certificate', 'certificate,completion,achievement', 'Certificates are automatically generated when you pass an exam with the required passing score. You can download them from the \"Certificates\" section.', 8),
('registration', 'register,sign up,create account', 'To register as a student, click on the \"Register\" button on the homepage. Fill in your details and create a password to get started.', 8),
('login', 'login,sign in,access', 'Use your email and password to login. If you forgot your password, click on \"Forgot Password\" to reset it.', 7),
('forgot_password', 'forgot password,reset password,cant login', 'Click on \"Forgot Password\" on the login page, enter your email, and follow the instructions sent to your email to reset your password.', 7),
('subjects', 'subject,course,topics', 'Our system covers various subjects including Mathematics, English, Science, and more. Check the exams page to see available subjects.', 6),
('time_limit', 'time limit,duration,timer', 'Each exam has a specific time limit displayed before you start. The timer counts down while you take the exam.', 6),
('questions', 'question,questions,type', 'Questions are multiple choice with four options. They are randomly ordered for each attempt to ensure fairness.', 6),
('passing_score', 'passing score,pass mark,minimum score', 'Each exam has a passing score requirement (usually 50-70%). You need to achieve at least this percentage to pass.', 6),
('retake', 'retake,retry,attempt again', 'You can retake exams if allowed by the administrator. Check the exam settings for retake availability.', 5),
('help', 'help,support,assistance', 'For technical support, please contact missiontech.raph@gmail.com. For admin inquiries, email missiontech.admin@gmail.com', 9),
('contact', 'contact,email,support', 'Student Support: missiontech.raph@gmail.com | Admin Support: missiontech.admin@gmail.com', 9),
('privacy', 'privacy policy,data,security', 'Your data is protected and only used for academic purposes. Read our Privacy Policy for more details.', 5),
('feature', 'feature,tools,functionality', 'Our system offers: Timed exams, instant results, certificates, study materials, leaderboard, and achievements!', 7),
('thanks', 'thank you,thanks,appreciate', 'You\'re welcome! Feel free to ask if you need any more help. Good luck with your exams!', 8),
('bye', 'bye,goodbye,see you', 'Goodbye! Have a great day and happy learning!', 8)");

/**
 * Chatbot Handler Class
 */
class ExamChatbot {
    private $conn;
    private $user_id;
    private $session_id;
    
    public function __construct($conn, $user_id = null) {
        $this->conn = $conn;
        $this->user_id = $user_id;
        $this->session_id = session_id();
        
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Process user message and return response
     */
    public function processMessage($message) {
        $message = strtolower(trim($message));
        
        // Check for exam-specific queries
        if ($this->isExamRelated($message)) {
            return $this->handleExamQuery($message);
        }
        
        // Check for result-related queries
        if ($this->isResultRelated($message)) {
            return $this->handleResultQuery($message);
        }
        
        // Check knowledge base first
        $response = $this->searchKnowledgeBase($message);
        if ($response) {
            $this->saveConversation($message, $response, 'knowledge_base');
            return $response;
        }
        
        // Check if user is logged in for personalized responses
        if ($this->user_id) {
            $response = $this->getPersonalizedResponse($message);
            if ($response) {
                $this->saveConversation($message, $response, 'personalized');
                return $response;
            }
        }
        
        // Default response
        $response = $this->getDefaultResponse($message);
        $this->saveConversation($message, $response, 'default');
        return $response;
    }
    
    /**
     * Search knowledge base for matching keywords
     */
    private function searchKnowledgeBase($message) {
        $stmt = $this->conn->prepare("
            SELECT response, keywords, priority 
            FROM chatbot_knowledge 
            WHERE is_active = 1 
            ORDER BY priority DESC, id ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $best_match = null;
        $highest_score = 0;
        
        while ($row = $result->fetch_assoc()) {
            $keywords = explode(',', strtolower($row['keywords']));
            $score = 0;
            
            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if (strpos($message, $keyword) !== false) {
                    $score += 10;
                }
                // Check for similar words
                if ($this->similarWords($message, $keyword)) {
                    $score += 5;
                }
            }
            
            if ($score > $highest_score) {
                $highest_score = $score;
                $best_match = $row['response'];
            }
        }
        
        return $best_match;
    }
    
    /**
     * Check if words are similar (basic)
     */
    private function similarWords($message, $keyword) {
        $message_words = explode(' ', $message);
        foreach ($message_words as $word) {
            similar_text($word, $keyword, $percent);
            if ($percent > 70) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if message is exam-related
     */
    private function isExamRelated($message) {
        $exam_keywords = ['exam', 'test', 'quiz', 'assessment', 'available', 'schedule'];
        foreach ($exam_keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Handle exam-related queries
     */
    private function handleExamQuery($message) {
        if (strpos($message, 'available') !== false || strpos($message, 'how many') !== false) {
            $result = $this->conn->query("SELECT COUNT(*) as total FROM exams WHERE status = 'published'");
            $count = $result->fetch_assoc()['total'];
            return "Currently there are <strong>{$count}</strong> exams available. You can view them in the 'Take Exam' section.";
        }
        
        if (strpos($message, 'subject') !== false || strpos($message, 'topic') !== false) {
            $result = $this->conn->query("SELECT COUNT(*) as total FROM subjects");
            $count = $result->fetch_assoc()['total'];
            return "We have <strong>{$count}</strong> subjects available. You can see all exams categorized by subject.";
        }
        
        return "You can find all available exams in the 'Take Exam' section. Each exam has a time limit and passing score requirement.";
    }
    
    /**
     * Check if message is result-related
     */
    private function isResultRelated($message) {
        $result_keywords = ['result', 'score', 'grade', 'passed', 'failed'];
        foreach ($result_keywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Handle result-related queries
     */
    private function handleResultQuery($message) {
        if (!$this->user_id) {
            return "Please log in to view your exam results. You can then see all your scores in the 'Results' section.";
        }
        
        if (strpos($message, 'latest') !== false || strpos($message, 'recent') !== false) {
            $stmt = $this->conn->prepare("
                SELECT e.title, ea.percentage, ea.passed 
                FROM exam_attempts ea 
                JOIN exams e ON ea.exam_id = e.id 
                WHERE ea.student_id = ? AND ea.status = 'completed' 
                ORDER BY ea.created_at DESC LIMIT 1
            ");
            $stmt->bind_param("i", $this->user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $status = $row['passed'] ? '✅ Passed' : '❌ Failed';
                return "Your latest exam: <strong>{$row['title']}</strong><br>Score: <strong>{$row['percentage']}%</strong><br>Status: {$status}";
            }
            return "You haven't taken any exams yet. Go to 'Take Exam' to start your first exam!";
        }
        
        return "You can view all your exam results in the 'Results' section. Each result shows your score, percentage, and detailed feedback per question.";
    }
    
    /**
     * Get personalized response based on user data
     */
    private function getPersonalizedResponse($message) {
        // Check total exams taken
        if (strpos($message, 'how many exam') !== false || strpos($message, 'exams taken') !== false) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as total FROM exam_attempts 
                WHERE student_id = ? AND status = 'completed'
            ");
            $stmt->bind_param("i", $this->user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['total'];
            
            if ($count > 0) {
                return "You have taken <strong>{$count}</strong> exam(s) so far. Keep up the great work! 🎯";
            }
            return "You haven't taken any exams yet. Ready to start your first exam? Go to the 'Take Exam' section!";
        }
        
        // Check average score
        if (strpos($message, 'average') !== false || strpos($message, 'overall') !== false) {
            $stmt = $this->conn->prepare("
                SELECT AVG(percentage) as avg FROM exam_attempts 
                WHERE student_id = ? AND status = 'completed'
            ");
            $stmt->bind_param("i", $this->user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $avg = round($result->fetch_assoc()['avg'] ?? 0, 1);
            
            if ($avg > 0) {
                $encouragement = $avg >= 70 ? "Excellent work! 🌟" : ($avg >= 50 ? "Good job! Keep practicing! 📚" : "Keep studying, you'll improve! 💪");
                return "Your average score is <strong>{$avg}%</strong>. {$encouragement}";
            }
            return "Take some exams to see your average score!";
        }
        
        return null;
    }
    
    /**
     * Get default response
     */
    private function getDefaultResponse($message) {
        $responses = [
            "I understand you're asking about <strong>" . htmlspecialchars($message) . "</strong>. Could you please provide more details? I'm here to help with exam-related questions!",
            "Great question! For specific exam-related inquiries, you can check our FAQ section or contact support at missiontech.raph@gmail.com",
            "I'm here to help with the online examination system. You can ask me about exams, results, certificates, or how to use the platform.",
            "I can help you with: 📝 Exam information • 📊 Results & scores • 📜 Certificates • ❓ How to use the system • 🔧 Technical support"
        ];
        return $responses[array_rand($responses)];
    }
    
    /**
     * Save conversation to database
     */
    private function saveConversation($user_message, $bot_response, $intent) {
        $stmt = $this->conn->prepare("
            INSERT INTO chatbot_conversations (user_id, session_id, user_message, bot_response, intent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("issss", $this->user_id, $this->session_id, $user_message, $bot_response, $intent);
        $stmt->execute();
    }
    
    /**
     * Get conversation history
     */
    public function getConversationHistory($limit = 10) {
        if ($this->user_id) {
            $stmt = $this->conn->prepare("
                SELECT user_message, bot_response, created_at 
                FROM chatbot_conversations 
                WHERE user_id = ? 
                ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->bind_param("ii", $this->user_id, $limit);
        } else {
            $stmt = $this->conn->prepare("
                SELECT user_message, bot_response, created_at 
                FROM chatbot_conversations 
                WHERE session_id = ? 
                ORDER BY created_at DESC LIMIT ?
            ");
            $stmt->bind_param("si", $this->session_id, $limit);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Clear conversation history
     */
    public function clearHistory() {
        if ($this->user_id) {
            $stmt = $this->conn->prepare("DELETE FROM chatbot_conversations WHERE user_id = ?");
            $stmt->bind_param("i", $this->user_id);
        } else {
            $stmt = $this->conn->prepare("DELETE FROM chatbot_conversations WHERE session_id = ?");
            $stmt->bind_param("s", $this->session_id);
        }
        return $stmt->execute();
    }
}
?>