<?php
/**
 * Student Results Page
 * Displays exam results with the shuffled options the user saw
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
$submitted = isset($_GET['submitted']);

// Get last exam result from session if available
$last_result = $_SESSION['last_exam_result'] ?? null;

// Clear session result after retrieving
if ($submitted && $last_result) {
    unset($_SESSION['last_exam_result']);
}

// Get student details
$student = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Get overall statistics
$total_attempts = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'")->fetch_assoc()['total'];
$avg_score = $conn->query("SELECT AVG(percentage) as avg FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'")->fetch_assoc()['avg'];
$total_passed = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND status = 'completed' AND passed = 1")->fetch_assoc()['total'];
$best_score = $conn->query("SELECT MAX(percentage) as best FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'")->fetch_assoc()['best'];

// Get all attempts for this student
$attempts = $conn->query("SELECT ea.*, e.title as exam_title, e.passing_score, e.duration_minutes, e.description 
                          FROM exam_attempts ea 
                          JOIN exams e ON ea.exam_id = e.id 
                          WHERE ea.student_id = $user_id AND ea.status = 'completed' 
                          ORDER BY ea.created_at DESC");

// If specific exam requested, get detailed results
$detailed_result = null;
$question_details = [];

if ($exam_id > 0) {
    // Get the specific attempt
    $attempt = $conn->query("SELECT ea.*, e.title as exam_title, e.passing_score, e.description, e.total_questions
                             FROM exam_attempts ea 
                             JOIN exams e ON ea.exam_id = e.id 
                             WHERE ea.exam_id = $exam_id AND ea.student_id = $user_id AND ea.status = 'completed' 
                             ORDER BY ea.created_at DESC LIMIT 1")->fetch_assoc();
    
    if ($attempt) {
        // Format time taken
        $time_taken_seconds = isset($attempt['time_taken']) ? $attempt['time_taken'] : 0;
        if ($time_taken_seconds > 0) {
            $minutes = floor($time_taken_seconds / 60);
            $seconds = $time_taken_seconds % 60;
            $time_taken_formatted = sprintf("%d:%02d", $minutes, $seconds);
        } else {
            $time_taken_formatted = "0:00";
        }
        $attempt['time_taken_formatted'] = $time_taken_formatted;
        $detailed_result = $attempt;
        
        // Get question details for this attempt
        $questions = $conn->query("SELECT sa.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, 
                                          q.correct_option as original_correct, q.explanation, q.difficulty, q.marks
                                   FROM student_answers sa 
                                   JOIN questions q ON sa.question_id = q.id 
                                   WHERE sa.attempt_id = {$attempt['id']} 
                                   ORDER BY sa.id");
        
        while ($q = $questions->fetch_assoc()) {
            // Get the shuffled options that the user saw
            $shuffled_options = null;
            if ($last_result && isset($last_result['question_results'])) {
                foreach ($last_result['question_results'] as $rq) {
                    if ($rq['id'] == $q['question_id']) {
                        $shuffled_options = $rq['shuffled_options'];
                        break;
                    }
                }
            }
            
            // If no shuffled options from session, create them for display
            if (!$shuffled_options) {
                $options = [
                    'A' => $q['option_a'],
                    'B' => $q['option_b'],
                    'C' => $q['option_c'],
                    'D' => $q['option_d']
                ];
                $shuffled_options = $options;
            }
            
            $q['shuffled_options'] = $shuffled_options;
            $question_details[] = $q;
        }
    }
}

$page_title = $exam_id ? 'Exam Results' : 'My Results';
include '../includes/header.php';
?>

<div class="results-container">
    <?php if ($submitted && $last_result): ?>
        <div class="alert alert-success slide-in">
            <i class="fas fa-check-circle"></i>
            <div class="alert-content">
                <strong>Exam Submitted Successfully!</strong>
                <p>Your results are displayed below.</p>
                <?php if ($last_result['email_sent']): ?>
                    <small><i class="fas fa-envelope"></i> A detailed result has been sent to your email.</small>
                <?php endif; ?>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endif; ?>
    
    <?php if ($exam_id > 0 && $detailed_result): ?>
        <!-- Detailed Exam Results -->
        <div class="result-header">
            <div class="result-header-content">
                <div class="exam-badge">Exam Results</div>
                <h1><?php echo htmlspecialchars($detailed_result['exam_title']); ?></h1>
                <p class="exam-description"><?php echo htmlspecialchars($detailed_result['description'] ?? 'No description available'); ?></p>
            </div>
            <div class="result-date">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('F j, Y', strtotime($detailed_result['created_at'])); ?>
                <span class="time">at <?php echo date('h:i A', strtotime($detailed_result['created_at'])); ?></span>
            </div>
        </div>
        
        <!-- Score Overview Cards -->
        <div class="score-overview">
            <div class="score-card main-score">
                <div class="score-circle">
                    <svg viewBox="0 0 100 100">
                        <circle cx="50" cy="50" r="45" class="score-bg"/>
                        <circle cx="50" cy="50" r="45" class="score-fill" style="stroke-dashoffset: <?php echo 283 - (283 * $detailed_result['percentage'] / 100); ?>"/>
                    </svg>
                    <div class="score-percentage">
                        <span class="percentage"><?php echo $detailed_result['percentage']; ?></span>
                        <span class="percent-sign">%</span>
                    </div>
                </div>
                <div class="score-status <?php echo $detailed_result['passed'] ? 'passed' : 'failed'; ?>">
                    <i class="fas fa-<?php echo $detailed_result['passed'] ? 'check-circle' : 'times-circle'; ?>"></i>
                    <?php echo $detailed_result['passed'] ? 'PASSED' : 'FAILED'; ?>
                </div>
                <div class="passing-score">
                    Passing Score: <?php echo $detailed_result['passing_score']; ?>%
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon correct">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-value"><?php echo $detailed_result['correct_answers']; ?></div>
                    <div class="stat-label">Correct Answers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon wrong">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="stat-value"><?php echo $detailed_result['wrong_answers']; ?></div>
                    <div class="stat-label">Wrong Answers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon score">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value"><?php echo $detailed_result['score']; ?>/<?php echo $detailed_result['total_questions']; ?></div>
                    <div class="stat-label">Total Score</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon time">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-value"><?php echo $detailed_result['time_taken_formatted']; ?></div>
                    <div class="stat-label">Time Taken</div>
                </div>
            </div>
        </div>
        
        <!-- Performance Insights -->
        <div class="performance-insights">
            <h3><i class="fas fa-chart-line"></i> Performance Insights</h3>
            <div class="insights-grid">
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="insight-content">
                        <div class="insight-label">Your Score</div>
                        <div class="insight-value"><?php echo $detailed_result['percentage']; ?>%</div>
                        <div class="insight-bar">
                            <div class="bar-fill" style="width: <?php echo $detailed_result['percentage']; ?>%"></div>
                        </div>
                    </div>
                </div>
                <div class="insight-card">
                    <div class="insight-icon">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <div class="insight-content">
                        <div class="insight-label">Passing Score</div>
                        <div class="insight-value"><?php echo $detailed_result['passing_score']; ?>%</div>
                        <div class="insight-bar">
                            <div class="bar-fill" style="width: <?php echo $detailed_result['passing_score']; ?>%; background: #f59e0b;"></div>
                        </div>
                    </div>
                </div>
                <?php if ($detailed_result['percentage'] >= 90): ?>
                <div class="insight-card highlight">
                    <div class="insight-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="insight-content">
                        <div class="insight-label">Outstanding Performance!</div>
                        <div class="insight-value">Top Score Achieved</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Question Review Section -->
        <div class="review-section">
            <div class="review-header">
                <h3><i class="fas fa-question-circle"></i> Question Review</h3>
                <div class="review-filters">
                    <button class="filter-btn active" data-filter="all">All Questions</button>
                    <button class="filter-btn" data-filter="correct">Correct</button>
                    <button class="filter-btn" data-filter="incorrect">Incorrect</button>
                </div>
            </div>
            
            <div class="questions-list">
                <?php 
                $q_num = 1;
                foreach ($question_details as $q): 
                    $is_correct = $q['is_correct'];
                    $user_answer = $q['selected_option'];
                    $correct_answer = $q['original_correct'];
                    $shuffled_options = $q['shuffled_options'];
                ?>
                <div class="question-review-card <?php echo $is_correct ? 'correct' : 'incorrect'; ?>" data-status="<?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                    <div class="question-number">
                        <span class="number"><?php echo $q_num; ?></span>
                        <span class="difficulty <?php echo $q['difficulty']; ?>">
                            <?php echo ucfirst($q['difficulty']); ?>
                        </span>
                        <span class="marks"><?php echo $q['marks']; ?> mark</span>
                    </div>
                    
                    <div class="question-text">
                        <?php echo nl2br(htmlspecialchars($q['question_text'])); ?>
                    </div>
                    
                    <div class="options-grid">
                        <?php foreach ($shuffled_options as $letter => $text): ?>
                        <div class="option-item <?php 
                            if ($letter == $correct_answer) echo 'correct-answer';
                            if ($user_answer == $letter && $user_answer != $correct_answer) echo 'user-wrong';
                            if ($user_answer == $letter && $user_answer == $correct_answer) echo 'user-correct';
                        ?>">
                            <div class="option-letter"><?php echo $letter; ?></div>
                            <div class="option-text"><?php echo htmlspecialchars($text); ?></div>
                            <?php if ($letter == $correct_answer): ?>
                                <div class="option-badge correct-badge">
                                    <i class="fas fa-check"></i> Correct Answer
                                </div>
                            <?php endif; ?>
                            <?php if ($user_answer == $letter && $user_answer != $correct_answer): ?>
                                <div class="option-badge wrong-badge">
                                    <i class="fas fa-times"></i> Your Answer
                                </div>
                            <?php endif; ?>
                            <?php if ($user_answer == $letter && $user_answer == $correct_answer): ?>
                                <div class="option-badge correct-badge">
                                    <i class="fas fa-check-circle"></i> Your Correct Answer
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($q['explanation'] && !$is_correct): ?>
                        <div class="explanation-box">
                            <i class="fas fa-lightbulb"></i>
                            <div class="explanation-content">
                                <strong>Explanation:</strong>
                                <p><?php echo htmlspecialchars($q['explanation']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php $q_num++; endforeach; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
            </a>
            <a href="retake-exam.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary">
                <i class="fas fa-redo-alt"></i> Retake Exam
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                <i class="fas fa-print"></i> Print Results
            </button>
        </div>
        
    <?php elseif ($exam_id > 0 && !$detailed_result): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <h3>No Results Found</h3>
            <p>You haven't taken this exam yet or the results are not available.</p>
            <a href="take-exam.php?id=<?php echo $exam_id; ?>" class="btn btn-primary">
                <i class="fas fa-play"></i> Take Exam Now
            </a>
        </div>
        
    <?php else: ?>
        <!-- Results Dashboard -->
        <div class="results-dashboard">
            <div class="dashboard-header">
                <h1><i class="fas fa-chart-line"></i> My Performance</h1>
                <p>Track your exam history and performance metrics</p>
            </div>
            
            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="overview-card">
                    <div class="overview-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="overview-value"><?php echo $total_attempts; ?></div>
                    <div class="overview-label">Total Exams Taken</div>
                </div>
                <div class="overview-card">
                    <div class="overview-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="overview-value"><?php echo round($avg_score ?? 0, 1); ?>%</div>
                    <div class="overview-label">Average Score</div>
                </div>
                <div class="overview-card">
                    <div class="overview-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="overview-value"><?php echo $total_passed; ?></div>
                    <div class="overview-label">Exams Passed</div>
                </div>
                <div class="overview-card">
                    <div class="overview-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="overview-value"><?php echo round($best_score ?? 0, 1); ?>%</div>
                    <div class="overview-label">Best Score</div>
                </div>
            </div>
            
            <!-- Exam History Table -->
            <div class="history-section">
                <h2><i class="fas fa-history"></i> Exam History</h2>
                <div class="table-responsive">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Exam Title</th>
                                <th>Date</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Correct/Wrong</th>
                                <th>Time Taken</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($attempts->num_rows > 0): ?>
                                <?php while ($attempt = $attempts->fetch_assoc()): 
                                    // Format time taken
                                    $time_sec = isset($attempt['time_taken']) ? $attempt['time_taken'] : 0;
                                    if ($time_sec > 0) {
                                        $mins = floor($time_sec / 60);
                                        $secs = $time_sec % 60;
                                        $time_formatted = sprintf("%d:%02d", $mins, $secs);
                                    } else {
                                        $time_formatted = "N/A";
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($attempt['exam_title']); ?></strong>
                                        <small><?php echo htmlspecialchars(substr($attempt['description'] ?? '', 0, 50)); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($attempt['created_at'])); ?>
                                        <small><?php echo date('h:i A', strtotime($attempt['created_at'])); ?></small>
                                    </td>
                                    <td class="score-cell"><?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?></td>
                                    <td>
                                        <div class="percentage-badge <?php echo $attempt['percentage'] >= 70 ? 'high' : ($attempt['percentage'] >= 50 ? 'medium' : 'low'); ?>">
                                            <?php echo $attempt['percentage']; ?>%
                                        </div>
                                    </td>
                                    <td>
                                        <span class="correct-count"><?php echo $attempt['correct_answers']; ?></span>
                                        <span class="separator">/</span>
                                        <span class="wrong-count"><?php echo $attempt['wrong_answers']; ?></span>
                                    </td>
                                    <td>
                                        <span class="time-badge">
                                            <i class="fas fa-clock"></i> <?php echo $time_formatted; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $attempt['passed'] ? 'passed' : 'failed'; ?>">
                                            <i class="fas fa-<?php echo $attempt['passed'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                            <?php echo $attempt['passed'] ? 'Passed' : 'Failed'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="results.php?exam=<?php echo $attempt['exam_id']; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-row">
                                        <i class="fas fa-folder-open"></i>
                                        <p>No exam attempts yet</p>
                                        <a href="take-exam.php" class="btn btn-primary btn-sm">Take Your First Exam</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Add time badge styles */
.time-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: #f1f5f9;
    border-radius: 30px;
    font-size: 0.7rem;
    color: #1e293b;
}

.time-badge i {
    color: #64748b;
}

.results-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

/* Alert Styles */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    position: relative;
    animation: slideIn 0.5s ease;
}

.alert-success {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border-left: 4px solid #10b981;
    color: #065f46;
}

.alert-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    color: inherit;
    opacity: 0.5;
    transition: opacity 0.3s;
    margin-left: auto;
}

.alert-close:hover {
    opacity: 1;
}

/* Result Header */
.result-header {
    background: white;
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.exam-badge {
    display: inline-block;
    padding: 4px 12px;
    background: #ecfdf5;
    color: #10b981;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 1rem;
}

.result-header h1 {
    font-size: 1.8rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.exam-description {
    color: #64748b;
    line-height: 1.5;
}

.result-date {
    text-align: right;
    color: #64748b;
    font-size: 0.85rem;
}

.result-date .time {
    display: block;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

/* Score Overview */
.score-overview {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .score-overview {
        grid-template-columns: 1fr;
    }
}

.score-card.main-score {
    text-align: center;
    background: white;
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.score-circle {
    position: relative;
    width: 180px;
    height: 180px;
    margin: 0 auto 1rem;
}

.score-circle svg {
    transform: rotate(-90deg);
    width: 100%;
    height: 100%;
}

.score-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 8;
}

.score-fill {
    fill: none;
    stroke: #10b981;
    stroke-width: 8;
    stroke-dasharray: 283;
    stroke-dashoffset: 283;
    transition: stroke-dashoffset 1s ease;
}

.score-percentage {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.score-percentage .percentage {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
}

.score-percentage .percent-sign {
    font-size: 1rem;
    color: #64748b;
}

.score-status {
    font-size: 1rem;
    font-weight: 700;
    margin-top: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 30px;
    display: inline-block;
}

.score-status.passed {
    background: #ecfdf5;
    color: #10b981;
}

.score-status.failed {
    background: #fef2f2;
    color: #ef4444;
}

.passing-score {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
}

.stat-icon.correct { background: #ecfdf5; color: #10b981; }
.stat-icon.wrong { background: #fef2f2; color: #ef4444; }
.stat-icon.score { background: #fef3c7; color: #f59e0b; }
.stat-icon.time { background: #e0f2fe; color: #3b82f6; }

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-label {
    font-size: 0.75rem;
    color: #64748b;
}

/* Performance Insights */
.performance-insights {
    background: white;
    border-radius: 24px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.performance-insights h3 {
    margin-bottom: 1.5rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.insight-card {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 16px;
    transition: all 0.3s;
}

.insight-card.highlight {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
}

.insight-icon {
    width: 48px;
    height: 48px;
    background: white;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981;
    font-size: 1.25rem;
}

.insight-content {
    flex: 1;
}

.insight-label {
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 0.25rem;
}

.insight-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.insight-bar {
    height: 6px;
    background: #e2e8f0;
    border-radius: 3px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    background: #10b981;
    border-radius: 3px;
    transition: width 1s ease;
}

/* Review Section */
.review-section {
    background: white;
    border-radius: 24px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.review-header h3 {
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.review-filters {
    display: flex;
    gap: 0.5rem;
}

.filter-btn {
    padding: 6px 12px;
    background: #f1f5f9;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s;
}

.filter-btn.active {
    background: #10b981;
    color: white;
}

/* Question Review Card */
.question-review-card {
    border: 1px solid #eef2f6;
    border-radius: 20px;
    margin-bottom: 1rem;
    overflow: hidden;
    transition: all 0.3s;
}

.question-review-card.correct {
    border-left: 4px solid #10b981;
}

.question-review-card.incorrect {
    border-left: 4px solid #ef4444;
}

.question-number {
    background: #f8fafc;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid #eef2f6;
}

.number {
    font-weight: 700;
    color: #1e293b;
    background: white;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.8rem;
}

.difficulty {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.difficulty.easy { background: #ecfdf5; color: #10b981; }
.difficulty.medium { background: #fef3c7; color: #f59e0b; }
.difficulty.hard { background: #fef2f2; color: #ef4444; }

.marks {
    font-size: 0.7rem;
    color: #64748b;
}

.question-text {
    padding: 1.5rem;
    font-size: 1rem;
    line-height: 1.5;
    color: #1e293b;
}

.options-grid {
    padding: 0 1.5rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.option-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 12px;
    position: relative;
}

.option-item.correct-answer {
    background: #ecfdf5;
    border: 1px solid #10b981;
}

.option-item.user-wrong {
    background: #fef2f2;
    border: 1px solid #ef4444;
}

.option-item.user-correct {
    background: #ecfdf5;
    border: 1px solid #10b981;
}

.option-letter {
    width: 32px;
    height: 32px;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #10b981;
}

.option-text {
    flex: 1;
    color: #1e293b;
}

.option-badge {
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 30px;
    margin-left: 0.5rem;
}

.correct-badge {
    background: #10b981;
    color: white;
}

.wrong-badge {
    background: #ef4444;
    color: white;
}

.explanation-box {
    margin: 0 1.5rem 1.5rem;
    padding: 1rem;
    background: #fef9e3;
    border-radius: 12px;
    display: flex;
    gap: 1rem;
    border-left: 3px solid #f59e0b;
}

.explanation-box i {
    color: #f59e0b;
    font-size: 1.2rem;
}

.explanation-content strong {
    color: #92400e;
}

.explanation-content p {
    margin-top: 0.25rem;
    color: #78350f;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16,185,129,0.3);
}

.btn-secondary {
    background: white;
    border: 2px solid #e2e8f0;
    color: #1e293b;
}

.btn-secondary:hover {
    border-color: #10b981;
    color: #10b981;
}

.btn-outline {
    background: transparent;
    border: 2px solid #e2e8f0;
    color: #64748b;
}

.btn-outline:hover {
    border-color: #10b981;
    color: #10b981;
}

/* Results Dashboard */
.results-dashboard .dashboard-header {
    margin-bottom: 2rem;
}

.results-dashboard .dashboard-header h1 {
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.overview-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.overview-card:hover {
    transform: translateY(-3px);
}

.overview-icon {
    width: 50px;
    height: 50px;
    background: #ecfdf5;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    color: #10b981;
    font-size: 1.5rem;
}

.overview-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
}

.overview-label {
    font-size: 0.8rem;
    color: #64748b;
}

.history-section {
    background: white;
    border-radius: 24px;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.history-section h2 {
    margin-bottom: 1.5rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.table-responsive {
    overflow-x: auto;
}

.history-table {
    width: 100%;
    border-collapse: collapse;
}

.history-table th {
    text-align: left;
    padding: 1rem;
    background: #f8fafc;
    color: #1e293b;
    font-weight: 600;
    font-size: 0.85rem;
}

.history-table td {
    padding: 1rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
}

.history-table td small {
    display: block;
    font-size: 0.7rem;
    color: #94a3b8;
    margin-top: 0.25rem;
}

.score-cell {
    font-weight: 600;
}

.percentage-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}

.percentage-badge.high {
    background: #ecfdf5;
    color: #10b981;
}

.percentage-badge.medium {
    background: #fef3c7;
    color: #f59e0b;
}

.percentage-badge.low {
    background: #fef2f2;
    color: #ef4444;
}

.correct-count {
    color: #10b981;
    font-weight: 600;
}

.wrong-count {
    color: #ef4444;
    font-weight: 600;
}

.separator {
    color: #94a3b8;
    margin: 0 0.25rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-badge.passed {
    background: #ecfdf5;
    color: #10b981;
}

.status-badge.failed {
    background: #fef2f2;
    color: #ef4444;
}

.btn-view {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #f1f5f9;
    color: #1e293b;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.75rem;
    transition: all 0.3s;
}

.btn-view:hover {
    background: #10b981;
    color: white;
}

.empty-row {
    text-align: center;
    padding: 3rem !important;
}

.empty-row i {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
    display: block;
}

.empty-row p {
    color: #64748b;
    margin-bottom: 1rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.empty-icon {
    font-size: 4rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.2rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #64748b;
    margin-bottom: 1.5rem;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.slide-in {
    animation: slideIn 0.5s ease;
}

/* Responsive */
@media (max-width: 768px) {
    .results-container {
        padding: 1rem;
    }
    
    .result-header {
        flex-direction: column;
        text-align: center;
    }
    
    .result-date {
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .insights-grid {
        grid-template-columns: 1fr;
    }
    
    .review-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
        justify-content: center;
    }
    
    .history-table th,
    .history-table td {
        padding: 0.75rem;
    }
}
</style>

<script>
// Filter questions
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const filter = this.dataset.filter;
        
        // Update active button
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        // Filter questions
        document.querySelectorAll('.question-review-card').forEach(card => {
            if (filter === 'all') {
                card.style.display = 'block';
            } else {
                card.style.display = card.classList.contains(filter) ? 'block' : 'none';
            }
        });
    });
});

// Animate score circles
document.querySelectorAll('.score-fill').forEach(circle => {
    const percentage = circle.parentElement.parentElement.querySelector('.percentage').textContent;
    const circumference = 283;
    const offset = circumference - (percentage / 100) * circumference;
    circle.style.strokeDashoffset = offset;
});

// Animate progress bars
document.querySelectorAll('.bar-fill').forEach(bar => {
    const width = bar.style.width;
    bar.style.width = '0';
    setTimeout(() => {
        bar.style.width = width;
    }, 100);
});
</script>

<?php include '../includes/footer.php'; ?>