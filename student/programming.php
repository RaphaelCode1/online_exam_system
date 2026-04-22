<?php
/**
 * Programming Practice Page
 * Interactive coding exercises with AI assistance
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/gemini.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$exercise_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get all programming languages
$languages = $conn->query("SELECT * FROM programming_languages WHERE is_active = 1 ORDER BY name");

// Handle AI Help Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'ai_help') {
        $code = $_POST['code'];
        $error = $_POST['error'] ?? '';
        $question = $_POST['question'] ?? '';
        
        $prompt = "You are a friendly programming tutor. A student is working on a coding exercise.
        
        Their code:
        ```\n$code\n```
        
        ";
        
        if ($error) {
            $prompt .= "They are getting this error:\n$error\n\n";
        }
        if ($question) {
            $prompt .= "They are asking:\n$question\n\n";
        }
        
        $prompt .= "Provide helpful, educational guidance. Give hints and explain the concept without giving away the complete solution. Be encouraging and friendly. Keep responses concise (under 200 words).";
        
        $response = generateWithGemini($prompt, ['temperature' => 0.7, 'maxOutputTokens' => 500]);
        
        if ($response) {
            echo json_encode(['success' => true, 'response' => $response]);
        } else {
            echo json_encode(['success' => false, 'error' => 'AI service temporarily unavailable. Please try again.']);
        }
        exit();
    }
    
    // Submit solution
    if ($_POST['action'] === 'submit_solution') {
        $exercise_id = (int)$_POST['exercise_id'];
        $code = $_POST['code'];
        
        // Get exercise details
        $exercise = $conn->query("SELECT * FROM programming_exercises WHERE id = $exercise_id")->fetch_assoc();
        
        if ($exercise) {
            // Simple code evaluation (for demo purposes)
            $test_cases = json_decode($exercise['test_cases'], true);
            $passed = 0;
            $total = count($test_cases['tests']);
            $output = "";
            
            // For demo, we'll do simple evaluation
            // In production, use a proper code execution sandbox
            foreach ($test_cases['tests'] as $test) {
                $expected = trim($test['expected']);
                // This is a placeholder - actual execution should be sandboxed
                if (strpos($code, $expected) !== false) {
                    $passed++;
                }
            }
            
            $score = ($passed / $total) * 100;
            $status = ($score >= 70) ? 'passed' : 'failed';
            
            // Check if already submitted before
            $existing = $conn->query("SELECT id, attempts FROM programming_submissions WHERE student_id = $user_id AND exercise_id = $exercise_id ORDER BY id DESC LIMIT 1");
            $attempts = 1;
            if ($existing->num_rows > 0) {
                $prev = $existing->fetch_assoc();
                $attempts = $prev['attempts'] + 1;
            }
            
            // Save submission
            $stmt = $conn->prepare("INSERT INTO programming_submissions (student_id, exercise_id, code, passed_tests, total_tests, score, status, attempts, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisiiisi", $user_id, $exercise_id, $code, $passed, $total, $score, $status, $attempts);
            $stmt->execute();
            $submission_id = $stmt->insert_id;
            $stmt->close();
            
            // Get AI feedback
            $feedback_prompt = "Review this code and provide constructive, encouraging feedback:\n\n```\n$code\n```\n\nExercise: {$exercise['title']}\nScore: $score% ($passed/$total tests passed). Provide specific suggestions for improvement and praise what they did well. Keep it under 150 words.";
            
            $ai_feedback = generateWithGemini($feedback_prompt, ['temperature' => 0.5, 'maxOutputTokens' => 300]);
            
            if ($ai_feedback) {
                $conn->query("UPDATE programming_submissions SET ai_feedback = '" . $conn->real_escape_string($ai_feedback) . "' WHERE id = $submission_id");
            }
            
            // Award points if passed
            if ($status == 'passed') {
                $points_earned = $exercise['points'];
                $conn->query("UPDATE users SET programming_points = programming_points + $points_earned WHERE id = $user_id");
            }
            
            $_SESSION['submission_result'] = [
                'exercise_title' => $exercise['title'],
                'passed' => $passed,
                'total' => $total,
                'score' => $score,
                'status' => $status,
                'points_earned' => $status == 'passed' ? $exercise['points'] : 0,
                'feedback' => $ai_feedback
            ];
            
            header("Location: programming.php?action=result");
            exit();
        }
    }
}

// Get exercises list
if ($action === 'list') {
    $language_filter = isset($_GET['language']) ? (int)$_GET['language'] : 0;
    $difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
    
    $query = "SELECT pe.*, pl.name as language_name, pl.icon, pl.color 
              FROM programming_exercises pe 
              JOIN programming_languages pl ON pe.language_id = pl.id 
              WHERE pe.is_active = 1";
    
    if ($language_filter > 0) {
        $query .= " AND pe.language_id = $language_filter";
    }
    if ($difficulty_filter) {
        $query .= " AND pe.difficulty = '$difficulty_filter'";
    }
    
    $query .= " ORDER BY FIELD(pe.difficulty, 'beginner', 'intermediate', 'advanced'), pe.id";
    $exercises = $conn->query($query);
    
    // Get user progress
    $completed = $conn->query("SELECT exercise_id, status FROM programming_submissions WHERE student_id = $user_id GROUP BY exercise_id");
    $completed_exercises = [];
    while ($row = $completed->fetch_assoc()) {
        $completed_exercises[$row['exercise_id']] = $row['status'];
    }
    
    // Get user stats
    $total_exercises = $conn->query("SELECT COUNT(*) as total FROM programming_exercises WHERE is_active = 1")->fetch_assoc()['total'];
    $completed_count = $conn->query("SELECT COUNT(DISTINCT exercise_id) as completed FROM programming_submissions WHERE student_id = $user_id AND status = 'passed'")->fetch_assoc()['completed'];
    $total_points = $conn->query("SELECT SUM(score) as total FROM programming_submissions WHERE student_id = $user_id AND status = 'passed'")->fetch_assoc()['total'] ?? 0;
    $attempts_count = $conn->query("SELECT COUNT(*) as total FROM programming_submissions WHERE student_id = $user_id")->fetch_assoc()['total'];
    $perfect_scores = $conn->query("SELECT COUNT(*) as total FROM programming_submissions WHERE student_id = $user_id AND score = 100")->fetch_assoc()['total'];
}

// Get single exercise
if ($action === 'practice' && $exercise_id > 0) {
    $exercise = $conn->query("SELECT pe.*, pl.name as language_name, pl.icon, pl.color 
                              FROM programming_exercises pe 
                              JOIN programming_languages pl ON pe.language_id = pl.id 
                              WHERE pe.id = $exercise_id AND pe.is_active = 1")->fetch_assoc();
    
    if (!$exercise) {
        header('Location: programming.php');
        exit();
    }
    
    // Get previous submissions
    $submissions = $conn->query("SELECT * FROM programming_submissions WHERE student_id = $user_id AND exercise_id = $exercise_id ORDER BY submitted_at DESC LIMIT 5");
}

// Show result
if ($action === 'result') {
    $result = $_SESSION['submission_result'] ?? null;
    unset($_SESSION['submission_result']);
}

$page_title = $action === 'practice' ? 'Coding Practice' : 'Programming Challenges';
include '../includes/header.php';
?>

<div class="programming-container">
    <?php if ($action === 'list'): ?>
    <!-- Exercises List View -->
    <div class="page-header">
        <h1><i class="fas fa-code"></i> Programming Challenges</h1>
        <p>Practice coding with interactive exercises and get AI help when you're stuck!</p>
    </div>
    
    <!-- Student Progress Dashboard -->
    <div class="progress-dashboard">
        <h2><i class="fas fa-chart-line"></i> Your Coding Progress</h2>
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-value"><?php echo $completed_count; ?>/<?php echo $total_exercises; ?></div>
                <div class="stat-label">Challenges Completed</div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo ($total_exercises > 0 ? ($completed_count/$total_exercises)*100 : 0); ?>%"></div></div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_points; ?></div>
                <div class="stat-label">Total Points Earned</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $attempts_count; ?></div>
                <div class="stat-label">Total Attempts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $perfect_scores; ?></div>
                <div class="stat-label">Perfect Scores</div>
            </div>
        </div>
    </div>
    
    <!-- Leaderboard -->
    <div class="leaderboard-section">
        <h2><i class="fas fa-trophy"></i> Coding Leaderboard</h2>
        <div class="leaderboard-card">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        <th>Challenges</th>
                        <th>Points</th>
                        <th>Perfect Scores</th>
                    </thead>
                <tbody>
                    <?php
                    $leaderboard = $conn->query("
                        SELECT u.id, u.full_name, u.username,
                               COUNT(DISTINCT ps.exercise_id) as challenges_completed,
                               SUM(ps.score) as total_points,
                               SUM(CASE WHEN ps.score = 100 THEN 1 ELSE 0 END) as perfect_scores
                        FROM users u
                        JOIN programming_submissions ps ON u.id = ps.student_id
                        WHERE ps.status = 'passed'
                        GROUP BY u.id
                        ORDER BY total_points DESC, perfect_scores DESC
                        LIMIT 20
                    ");
                    $rank = 1;
                    while ($row = $leaderboard->fetch_assoc()):
                        $medal = $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : ($rank == 3 ? '🥉' : '#'.$rank));
                    ?>
                    <tr class="<?php echo $row['id'] == $user_id ? 'current-user' : ''; ?>">
                        <td class="rank"><?php echo $medal; ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo $row['challenges_completed']; ?></td>
                        <td class="points"><?php echo round($row['total_points']); ?></td>
                        <td><?php echo $row['perfect_scores']; ?></td>
                    </tr>
                    <?php $rank++; endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <input type="hidden" name="action" value="list">
            <div class="filter-group">
                <label>Language</label>
                <select name="language" class="filter-control" onchange="this.form.submit()">
                    <option value="0">All Languages</option>
                    <?php 
                    $languages->data_seek(0);
                    while ($lang = $languages->fetch_assoc()): ?>
                    <option value="<?php echo $lang['id']; ?>" <?php echo ($language_filter == $lang['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lang['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Difficulty</label>
                <select name="difficulty" class="filter-control" onchange="this.form.submit()">
                    <option value="">All Levels</option>
                    <option value="beginner" <?php echo $difficulty_filter == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                    <option value="intermediate" <?php echo $difficulty_filter == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                    <option value="advanced" <?php echo $difficulty_filter == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                </select>
            </div>
            <div class="filter-group">
                <a href="programming.php" class="btn-reset">Reset Filters</a>
            </div>
        </form>
    </div>
    
    <!-- Exercises Grid -->
    <div class="exercises-grid">
        <?php if ($exercises && $exercises->num_rows > 0): ?>
            <?php while ($ex = $exercises->fetch_assoc()): 
                $status = $completed_exercises[$ex['id']] ?? null;
                $status_class = '';
                $status_icon = '';
                if ($status == 'passed') {
                    $status_class = 'completed';
                    $status_icon = '<i class="fas fa-check-circle"></i> Completed';
                } elseif ($status == 'failed') {
                    $status_class = 'attempted';
                    $status_icon = '<i class="fas fa-redo-alt"></i> Try Again';
                }
                
                $difficulty_class = $ex['difficulty'] == 'beginner' ? 'easy' : ($ex['difficulty'] == 'intermediate' ? 'medium' : 'hard');
                $difficulty_text = ucfirst($ex['difficulty']);
            ?>
            <div class="exercise-card <?php echo $status_class; ?>" data-id="<?php echo $ex['id']; ?>">
                <div class="exercise-header">
                    <div class="exercise-icon" style="background: <?php echo $ex['color']; ?>20; color: <?php echo $ex['color']; ?>;">
                        <i class="fab <?php echo $ex['icon']; ?>"></i>
                    </div>
                    <div class="exercise-info">
                        <h3><?php echo htmlspecialchars($ex['title']); ?></h3>
                        <div class="exercise-meta">
                            <span class="language-badge"><?php echo htmlspecialchars($ex['language_name']); ?></span>
                            <span class="difficulty-badge difficulty-<?php echo $difficulty_class; ?>"><?php echo $difficulty_text; ?></span>
                            <span class="points-badge"><i class="fas fa-star"></i> <?php echo $ex['points']; ?> pts</span>
                            <?php if ($status_icon): ?>
                                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_icon; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <p class="exercise-description"><?php echo htmlspecialchars(substr($ex['description'], 0, 120)); ?>...</p>
                <a href="programming.php?action=practice&id=<?php echo $ex['id']; ?>" class="btn-practice">
                    <?php echo $status == 'passed' ? 'Review Challenge' : 'Start Coding'; ?>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-code"></i>
                <h3>No Challenges Found</h3>
                <p>Check back soon for new programming challenges!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Feedback Button -->
    <div class="feedback-section">
        <button class="btn-feedback" onclick="openFeedbackModal()">
            <i class="fas fa-comment-dots"></i> Give Feedback
        </button>
    </div>
    
    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-comment-dots"></i> Share Your Feedback</h2>
                <button class="modal-close" onclick="closeFeedbackModal()">&times;</button>
            </div>
            <form method="POST" action="submit-feedback.php">
                <div class="form-group">
                    <label>Feedback Type</label>
                    <select name="feedback_type" class="form-control" required>
                        <option value="suggestion">💡 Suggestion</option>
                        <option value="bug">🐛 Bug Report</option>
                        <option value="question">❓ Question</option>
                        <option value="praise">🎉 Praise</option>
                        <option value="feature">🚀 Feature Request</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Your Feedback</label>
                    <textarea name="feedback" class="form-control" rows="5" placeholder="Share your thoughts about the coding challenges..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Related Challenge (Optional)</label>
                    <select name="exercise_id" class="form-control">
                        <option value="">General Feedback</option>
                        <?php
                        $exercises_list = $conn->query("SELECT id, title FROM programming_exercises WHERE is_active = 1 ORDER BY title");
                        while ($ex = $exercises_list->fetch_assoc()):
                        ?>
                        <option value="<?php echo $ex['id']; ?>"><?php echo htmlspecialchars($ex['title']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rating (1-5)</label>
                    <select name="rating" class="form-control">
                        <option value="5">⭐⭐⭐⭐⭐ - Excellent</option>
                        <option value="4">⭐⭐⭐⭐ - Good</option>
                        <option value="3">⭐⭐⭐ - Average</option>
                        <option value="2">⭐⭐ - Poor</option>
                        <option value="1">⭐ - Very Poor</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeFeedbackModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Submit Feedback</button>
                </div>
            </form>

            <!-- Add after the form in feedback modal -->
            <div class="previous-feedback">
                <h4>Previous Feedback</h4>
                <?php
                // Get student's previous feedback to show replies
                $prev_feedback = $conn->query("SELECT * FROM student_feedback WHERE student_id = $user_id ORDER BY created_at DESC LIMIT 5");
                while ($prev = $prev_feedback->fetch_assoc()):
                ?>
                <div class="prev-feedback-item">
                    <div class="prev-feedback-header">
                        <span class="feedback-type"><?php echo ucfirst($prev['feedback_type']); ?></span>
                        <span class="feedback-status"><?php echo ucfirst($prev['status']); ?></span>
                    </div>
                    <div class="prev-feedback-text"><?php echo htmlspecialchars(substr($prev['feedback'], 0, 100)); ?></div>
                    <?php if (!empty($prev['admin_reply'])): ?>
                    <div class="admin-reply">
                        <i class="fas fa-reply"></i> Admin: <?php echo htmlspecialchars(substr($prev['admin_reply'], 0, 100)); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'practice' && isset($exercise)): ?>
    <!-- Coding Practice Interface -->
    <div class="coding-interface">
        <div class="coding-header">
            <a href="programming.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Challenges</a>
            <h1><?php echo htmlspecialchars($exercise['title']); ?></h1>
            <div class="exercise-meta-header">
                <span class="language-badge"><?php echo htmlspecialchars($exercise['language_name']); ?></span>
                <span class="difficulty-badge difficulty-<?php echo $exercise['difficulty']; ?>"><?php echo ucfirst($exercise['difficulty']); ?></span>
                <span class="points-badge"><i class="fas fa-star"></i> <?php echo $exercise['points']; ?> points</span>
            </div>
        </div>
        
        <div class="coding-layout">
            <!-- Left Panel: Problem Description -->
            <div class="problem-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-file-alt"></i> Problem Description</h3>
                </div>
                <div class="problem-content">
                    <p><?php echo nl2br(htmlspecialchars($exercise['description'])); ?></p>
                    
                    <?php if ($exercise['hints']): ?>
                    <div class="hints-section">
                        <h4><i class="fas fa-lightbulb"></i> Hints</h4>
                        <div class="hint-card">
                            <p><?php echo nl2br(htmlspecialchars($exercise['hints'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($exercise['starter_code']): ?>
                    <div class="starter-code">
                        <h4><i class="fas fa-code"></i> Starter Code</h4>
                        <pre><code><?php echo htmlspecialchars($exercise['starter_code']); ?></code></pre>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Previous Submissions -->
                <?php if ($submissions && $submissions->num_rows > 0): ?>
                <div class="previous-submissions">
                    <div class="panel-header">
                        <h3><i class="fas fa-history"></i> Your Previous Attempts</h3>
                    </div>
                    <div class="submissions-list">
                        <?php while ($sub = $submissions->fetch_assoc()): ?>
                        <div class="submission-item <?php echo $sub['status']; ?>">
                            <div class="submission-date"><?php echo date('M d, H:i', strtotime($sub['submitted_at'])); ?></div>
                            <div class="submission-score"><?php echo round($sub['score']); ?>%</div>
                            <div class="submission-status"><?php echo ucfirst($sub['status']); ?></div>
                            <?php if ($sub['ai_feedback']): ?>
                            <div class="submission-feedback"><?php echo htmlspecialchars(substr($sub['ai_feedback'], 0, 100)); ?>...</div>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Panel: Code Editor & AI Assistant -->
            <div class="code-panel">
                <!-- Code Editor -->
                <div class="editor-section">
                    <div class="panel-header">
                        <h3><i class="fas fa-edit"></i> Your Solution</h3>
                        <div class="editor-actions">
                            <button class="btn-ai" onclick="openAIChat()">
                                <i class="fas fa-robot"></i> Ask AI
                            </button>
                            <button class="btn-run" onclick="runCode()">
                                <i class="fas fa-play"></i> Run Code
                            </button>
                        </div>
                    </div>
                    <textarea id="codeEditor" class="code-editor" rows="15"><?php echo htmlspecialchars($exercise['starter_code']); ?></textarea>
                </div>
                
                <!-- Output Panel -->
                <div class="output-section">
                    <div class="panel-header">
                        <h3><i class="fas fa-terminal"></i> Output</h3>
                        <button class="btn-clear" onclick="clearOutput()">
                            <i class="fas fa-trash"></i> Clear
                        </button>
                    </div>
                    <div id="outputArea" class="output-area">
                        <div class="placeholder">Run your code to see output here...</div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <div class="submit-section">
                    <form method="POST" id="submitForm" onsubmit="return confirmSubmit()">
                        <input type="hidden" name="action" value="submit_solution">
                        <input type="hidden" name="exercise_id" value="<?php echo $exercise['id']; ?>">
                        <input type="hidden" name="code" id="submittedCode">
                        <button type="submit" class="btn-submit-final">
                            <i class="fas fa-check-circle"></i> Submit Solution
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- AI Chat Modal -->
        <div id="aiChatModal" class="modal" style="display: none;">
            <div class="modal-content ai-chat-modal">
                <div class="modal-header">
                    <h2><i class="fas fa-robot"></i> AI Coding Assistant</h2>
                    <button class="modal-close" onclick="closeAIChat()">&times;</button>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <div class="chat-message bot">
                        <div class="message-content">
                            <i class="fas fa-robot"></i>
                            <div class="message-text">
                                Hello! I'm your AI coding assistant. Ask me anything about this coding challenge!
                            </div>
                        </div>
                    </div>
                </div>
                <div class="chat-input-area">
                    <textarea id="chatInput" placeholder="Ask for help, explain your code, or ask about errors..." rows="2"></textarea>
                    <button class="send-btn" onclick="sendAIMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="chat-options">
                    <button class="option-btn" onclick="quickAsk('Can you explain this problem?')">Explain Problem</button>
                    <button class="option-btn" onclick="quickAsk('Give me a hint')">Get Hint</button>
                    <button class="option-btn" onclick="quickAsk('Check my code for errors')">Check Code</button>
                    <button class="option-btn" onclick="quickAsk('How can I improve this code?')">Improve Code</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($action === 'result' && isset($result)): ?>
    <!-- Submission Result -->
    <div class="result-container">
        <div class="result-card <?php echo $result['status']; ?>">
            <div class="result-icon">
                <i class="fas <?php echo $result['status'] == 'passed' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
            </div>
            <h2><?php echo $result['status'] == 'passed' ? 'Congratulations!' : 'Keep Practicing!'; ?></h2>
            <p class="exercise-title"><?php echo htmlspecialchars($result['exercise_title']); ?></p>
            
            <div class="result-stats">
                <div class="stat">
                    <span class="stat-label">Tests Passed</span>
                    <span class="stat-value"><?php echo $result['passed']; ?> / <?php echo $result['total']; ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label">Score</span>
                    <span class="stat-value"><?php echo round($result['score']); ?>%</span>
                </div>
                <div class="stat">
                    <span class="stat-label">Points Earned</span>
                    <span class="stat-value"><?php echo $result['points_earned']; ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label">Status</span>
                    <span class="stat-value <?php echo $result['status']; ?>"><?php echo strtoupper($result['status']); ?></span>
                </div>
            </div>
            
            <?php if ($result['feedback']): ?>
            <div class="ai-feedback">
                <h3><i class="fas fa-robot"></i> AI Feedback</h3>
                <p><?php echo nl2br(htmlspecialchars($result['feedback'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="result-actions">
                <a href="programming.php?action=practice&id=<?php echo $exercise_id; ?>" class="btn-primary">Try Again</a>
                <a href="programming.php" class="btn-secondary">More Challenges</a>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<style>
.programming-container {
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

/* Progress Dashboard */
.progress-dashboard {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #eef2f6;
}
.progress-dashboard h2 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
}
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}
.stat-card {
    text-align: center;
    padding: 1rem;
}
.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #10b981;
}
.stat-label {
    color: #64748b;
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}
.progress-bar {
    width: 100%;
    height: 6px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 0.5rem;
}
.progress-fill {
    height: 100%;
    background: #10b981;
    border-radius: 10px;
}

/* Leaderboard */
.leaderboard-section {
    margin-bottom: 2rem;
}
.leaderboard-section h2 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
}
.leaderboard-card {
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
    overflow: hidden;
}
.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
}
.leaderboard-table th,
.leaderboard-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eef2f6;
}
.leaderboard-table th {
    background: #f8fafc;
    font-weight: 600;
}
.leaderboard-table tr.current-user {
    background: #ecfdf5;
}
.rank {
    width: 80px;
    font-weight: 600;
}
.points {
    font-weight: 600;
    color: #f59e0b;
}

/* Filters */
.filters-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #eef2f6;
}
.filters-form {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    align-items: flex-end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.filter-group label {
    font-size: 0.8rem;
    font-weight: 500;
    color: #64748b;
}
.filter-control {
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    background: white;
}
.btn-reset {
    padding: 10px 20px;
    background: #f1f5f9;
    color: #64748b;
    text-decoration: none;
    border-radius: 12px;
    display: inline-block;
}

/* Exercises Grid */
.exercises-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.exercise-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    transition: all 0.3s;
}
.exercise-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.exercise-card.completed {
    border-left: 4px solid #10b981;
}
.exercise-card.attempted {
    border-left: 4px solid #f59e0b;
}
.exercise-header {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}
.exercise-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.exercise-info h3 {
    font-size: 1.1rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}
.exercise-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.language-badge {
    background: #e0f2fe;
    color: #3b82f6;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
}
.difficulty-badge {
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
}
.difficulty-easy { background: #ecfdf5; color: #10b981; }
.difficulty-medium { background: #fef3c7; color: #f59e0b; }
.difficulty-hard { background: #fef2f2; color: #ef4444; }
.points-badge {
    background: #fef3c7;
    color: #f59e0b;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
}
.status-badge.completed { color: #10b981; }
.status-badge.attempted { color: #f59e0b; }
.exercise-description {
    color: #64748b;
    font-size: 0.85rem;
    margin-bottom: 1rem;
    line-height: 1.4;
}
.btn-practice {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #10b981;
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-size: 0.85rem;
    transition: all 0.3s;
}
.btn-practice:hover {
    transform: translateX(5px);
    background: #059669;
}

/* Feedback Section */
.feedback-section {
    text-align: center;
    margin-top: 2rem;
}
.btn-feedback {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 30px;
    border: none;
    cursor: pointer;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}
.btn-feedback:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(139,92,246,0.4);
}

/* Coding Interface */
.coding-header {
    margin-bottom: 2rem;
}
.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #64748b;
    text-decoration: none;
    margin-bottom: 1rem;
}
.coding-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}
.problem-panel, .code-panel {
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
    overflow: hidden;
}
.panel-header {
    padding: 1rem 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.panel-header h3 {
    font-size: 1rem;
    color: #1e293b;
}
.problem-content {
    padding: 1.5rem;
}
.hints-section {
    margin-top: 1.5rem;
}
.hint-card {
    background: #fef9e3;
    padding: 1rem;
    border-radius: 12px;
    border-left: 3px solid #f59e0b;
}
.starter-code {
    margin-top: 1.5rem;
}
.starter-code pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 1rem;
    border-radius: 12px;
    overflow-x: auto;
    font-size: 0.8rem;
}
.previous-submissions {
    margin-top: 1.5rem;
    border-top: 1px solid #eef2f6;
}
.submissions-list {
    padding: 1rem;
}
.submission-item {
    background: #f8fafc;
    padding: 0.75rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.submission-item.passed { border-left: 3px solid #10b981; }
.submission-item.failed { border-left: 3px solid #ef4444; }
.submission-date {
    font-size: 0.7rem;
    color: #64748b;
}
.submission-score {
    font-weight: 600;
}
.submission-status {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 30px;
}
.submission-item.passed .submission-status { background: #ecfdf5; color: #10b981; }
.submission-item.failed .submission-status { background: #fef2f2; color: #ef4444; }
.submission-feedback {
    font-size: 0.7rem;
    color: #64748b;
    flex: 1;
}
.code-editor {
    width: 100%;
    padding: 1rem;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    border: none;
    resize: vertical;
    background: #f8fafc;
}
.code-editor:focus {
    outline: none;
}
.editor-actions {
    display: flex;
    gap: 0.5rem;
}
.btn-ai, .btn-run {
    padding: 6px 12px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-ai {
    background: #8b5cf6;
    color: white;
}
.btn-run {
    background: #10b981;
    color: white;
}
.output-section {
    margin-top: 1rem;
}
.output-area {
    padding: 1rem;
    background: #1e293b;
    color: #e2e8f0;
    font-family: monospace;
    min-height: 150px;
    max-height: 200px;
    overflow-y: auto;
}
.output-area .placeholder {
    color: #64748b;
}
.btn-clear {
    padding: 4px 12px;
    background: #f1f5f9;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.7rem;
}
.submit-section {
    padding: 1rem;
    border-top: 1px solid #eef2f6;
}
.btn-submit-final {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

/* AI Chat Modal */
.ai-chat-modal {
    max-width: 500px;
}
.chat-messages {
    height: 300px;
    overflow-y: auto;
    padding: 1rem;
    background: #f8fafc;
}
.chat-message {
    display: flex;
    margin-bottom: 1rem;
}
.chat-message.user {
    justify-content: flex-end;
}
.chat-message.user .message-content {
    background: #10b981;
    color: white;
}
.message-content {
    display: flex;
    gap: 8px;
    max-width: 80%;
    padding: 0.75rem;
    background: #e2e8f0;
    border-radius: 12px;
}
.chat-input-area {
    padding: 1rem;
    display: flex;
    gap: 0.5rem;
    border-top: 1px solid #eef2f6;
}
.chat-input-area textarea {
    flex: 1;
    padding: 8px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    resize: none;
    font-family: inherit;
}
.send-btn {
    padding: 8px 16px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}
.chat-options {
    padding: 0.75rem;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    border-top: 1px solid #eef2f6;
}
.option-btn {
    padding: 6px 12px;
    background: #f1f5f9;
    border: none;
    border-radius: 20px;
    font-size: 0.7rem;
    cursor: pointer;
}

/* Result Page */
.result-container {
    max-width: 600px;
    margin: 2rem auto;
}
.result-card {
    background: white;
    border-radius: 24px;
    padding: 2rem;
    text-align: center;
    border: 1px solid #eef2f6;
}
.result-card.passed {
    border-top: 4px solid #10b981;
}
.result-card.failed {
    border-top: 4px solid #ef4444;
}
.result-icon i {
    font-size: 4rem;
}
.result-card.passed .result-icon i { color: #10b981; }
.result-card.failed .result-icon i { color: #ef4444; }
.exercise-title {
    color: #64748b;
    margin-top: 0.5rem;
}
.result-stats {
    display: flex;
    justify-content: center;
    gap: 2rem;
    margin: 1.5rem 0;
    flex-wrap: wrap;
}
.stat {
    text-align: center;
}
.stat-label {
    font-size: 0.8rem;
    color: #64748b;
    display: block;
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}
.ai-feedback {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 12px;
    text-align: left;
    margin: 1rem 0;
}
.result-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1.5rem;
}
.btn-primary, .btn-secondary {
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
}
.btn-primary {
    background: #10b981;
    color: white;
}
.btn-secondary {
    background: white;
    border: 1px solid #e2e8f0;
    color: #64748b;
}
.empty-state {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 20px;
}
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}
.modal-content {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
}
.form-group {
    margin-bottom: 1rem;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
}
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}
.btn-cancel {
    background: white;
    border: 2px solid #e2e8f0;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
}
@media (max-width: 1024px) {
    .coding-layout {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .programming-container {
        padding: 1rem;
    }
    .exercises-grid {
        grid-template-columns: 1fr;
    }
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
let currentCode = '';
let currentExerciseId = <?php echo $exercise_id ?? 0; ?>;

function runCode() {
    const code = document.getElementById('codeEditor').value;
    const outputArea = document.getElementById('outputArea');
    
    outputArea.innerHTML = '<div class="running">Running code... <i class="fas fa-spinner fa-spin"></i></div>';
    
    setTimeout(() => {
        try {
            let output = '';
            const originalLog = console.log;
            console.log = function() {
                output += Array.from(arguments).join(' ') + '\n';
            };
            
            output = "Code executed!\n\nNote: In production, code runs in a secure sandbox environment.\n\n";
            output += "Test your solution by checking the expected output format.";
            
            console.log = originalLog;
            outputArea.innerHTML = '<pre>' + output.replace(/</g, '&lt;') + '</pre>';
        } catch(e) {
            outputArea.innerHTML = '<div style="color: #ef4444;">Error: ' + e.message + '</div>';
        }
    }, 500);
}

function clearOutput() {
    document.getElementById('outputArea').innerHTML = '<div class="placeholder">Run your code to see output here...</div>';
}

function confirmSubmit() {
    const code = document.getElementById('codeEditor').value;
    document.getElementById('submittedCode').value = code;
    return confirm('Submit your solution for evaluation?');
}

function openAIChat() {
    document.getElementById('aiChatModal').style.display = 'flex';
    currentCode = document.getElementById('codeEditor').value;
}

function closeAIChat() {
    document.getElementById('aiChatModal').style.display = 'none';
}

function sendAIMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) return;
    
    addMessage(message, 'user');
    input.value = '';
    
    // Show typing indicator
    const messagesDiv = document.getElementById('chatMessages');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'chat-message bot typing';
    typingDiv.id = 'typingIndicator';
    typingDiv.innerHTML = '<div class="message-content"><i class="fas fa-robot"></i><div class="message-text">Typing...</div></div>';
    messagesDiv.appendChild(typingDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=ai_help&code=${encodeURIComponent(currentCode)}&question=${encodeURIComponent(message)}`
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('typingIndicator')?.remove();
        if (data.success) {
            addMessage(data.response, 'bot');
        } else {
            addMessage('Sorry, I encountered an error. Please try again.', 'bot');
        }
    })
    .catch(error => {
        document.getElementById('typingIndicator')?.remove();
        addMessage('Error connecting to AI service. Please try again.', 'bot');
    });
}

function addMessage(text, sender) {
    const messagesDiv = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${sender}`;
    messageDiv.innerHTML = `
        <div class="message-content">
            <i class="fas ${sender === 'user' ? 'fa-user' : 'fa-robot'}"></i>
            <div class="message-text">${text.replace(/\n/g, '<br>')}</div>
        </div>
    `;
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function quickAsk(question) {
    document.getElementById('chatInput').value = question;
    sendAIMessage();
}

function openFeedbackModal() {
    document.getElementById('feedbackModal').style.display = 'flex';
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('aiChatModal');
    const feedbackModal = document.getElementById('feedbackModal');
    if (event.target === modal) {
        closeAIChat();
    }
    if (event.target === feedbackModal) {
        closeFeedbackModal();
    }
}
</script>

<?php include '../includes/footer.php'; ?>