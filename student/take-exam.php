<?php
/**
 * Student Take Exam Page
 * Allows unlimited retakes with completely random questions each time
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get exam details
$exam = $conn->query("SELECT e.*, s.name as subject_name FROM exams e JOIN subjects s ON e.subject_id = s.id WHERE e.id = $exam_id AND e.status = 'published'")->fetch_assoc();

if (!$exam) {
    header('Location: exams.php');
    exit();
}

// Check if there's an in-progress attempt (resume functionality)
$in_progress = $conn->query("SELECT * FROM exam_attempts WHERE exam_id = $exam_id AND student_id = $user_id AND status = 'in_progress' ORDER BY id DESC LIMIT 1")->fetch_assoc();

if (!$in_progress) {
    // Create new attempt (always allowed for retakes)
    $stmt = $conn->prepare("INSERT INTO exam_attempts (exam_id, student_id, total_questions, start_time) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iii", $exam_id, $user_id, $exam['total_questions']);
    $stmt->execute();
    $attempt_id = $stmt->insert_id;
    $stmt->close();
} else {
    $attempt_id = $in_progress['id'];
}

// Get available questions for this exam
$available_questions = $conn->query("
    SELECT q.* FROM exam_questions eq 
    JOIN questions q ON eq.question_id = q.id 
    WHERE eq.exam_id = $exam_id
");

$all_questions = [];
while ($q = $available_questions->fetch_assoc()) {
    $all_questions[] = $q;
}

$available_count = count($all_questions);
$total_needed = $exam['total_questions'];
$question_list = [];

if ($available_count >= $total_needed) {
    // Enough questions: select random distinct questions
    $random_keys = array_rand($all_questions, $total_needed);
    if (!is_array($random_keys)) {
        $random_keys = [$random_keys];
    }
    foreach ($random_keys as $key) {
        $question_list[] = $all_questions[$key];
    }
} else {
    // Not enough questions: repeat questions randomly
    for ($i = 0; $i < $total_needed; $i++) {
        $random_index = array_rand($all_questions);
        $question_list[] = $all_questions[$random_index];
    }
}

// Shuffle the final question list for randomness
shuffle($question_list);

// For each question, randomly shuffle the options
$question_count = 0;
$question_order_data = [];

foreach ($question_list as $index => $q) {
    $options = [
        'A' => $q['option_a'],
        'B' => $q['option_b'],
        'C' => $q['option_c'],
        'D' => $q['option_d']
    ];
    
    // Shuffle options for this user
    $original_correct = $q['correct_option'];
    $correct_text = $options[$original_correct];
    
    // Shuffle the options array
    $option_keys = array_keys($options);
    shuffle($option_keys);
    
    // Rebuild shuffled options
    $shuffled = [];
    $new_correct_option = null;
    
    foreach ($option_keys as $idx => $key) {
        $letter = chr(65 + $idx);
        $shuffled[$letter] = $options[$key];
        
        if ($options[$key] === $correct_text) {
            $new_correct_option = $letter;
        }
    }
    
    // Store the shuffled options
    $question_list[$index]['shuffled_options'] = $shuffled;
    $question_list[$index]['shuffled_correct'] = $new_correct_option;
    $question_list[$index]['original_correct'] = $original_correct;
    $question_count++;
    
    // Store question order for this attempt
    $question_order_data[] = [
        'question_id' => $q['id'],
        'position' => $index + 1,
        'shuffled_options' => json_encode($shuffled),
        'shuffled_correct' => $new_correct_option
    ];
}

// Save the question order for this attempt
$order_json = json_encode($question_order_data);
$stmt = $conn->prepare("UPDATE exam_attempts SET question_order = ? WHERE id = ?");
$stmt->bind_param("si", $order_json, $attempt_id);
$stmt->execute();
$stmt->close();

// Store the shuffled questions in session for this attempt
$_SESSION['exam_' . $attempt_id] = $question_list;

$page_title = $exam['title'];
include '../includes/header.php';
?>

<!-- Your existing exam HTML and JavaScript here -->
<!-- (Keep your existing take-exam.php HTML/CSS/JS structure) -->

<div class="exam-container">
    <div class="exam-header">
        <div class="exam-header-content">
            <h1 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h1>
            <div class="exam-meta-grid">
                <div class="exam-meta-item">
                    <i class="fas fa-book-open"></i>
                    <span><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                </div>
                <div class="exam-meta-item timer">
                    <i class="fas fa-hourglass-half"></i>
                    <span id="timerDisplay"><?php echo $exam['duration_minutes']; ?>:00</span>
                </div>
                <div class="exam-meta-item">
                    <i class="fas fa-question-circle"></i>
                    <span><?php echo $question_count; ?> questions</span>
                </div>
                <div class="exam-meta-item">
                    <i class="fas fa-star"></i>
                    <span>Pass: <?php echo $exam['passing_score']; ?>%</span>
                </div>
                <div class="exam-meta-item">
                    <i class="fas fa-random"></i>
                    <span>Randomized</span>
                </div>
                <div class="exam-meta-item">
                    <i class="fas fa-infinity"></i>
                    <span>Attempt #<?php echo $conn->query("SELECT COUNT(*) FROM exam_attempts WHERE exam_id = $exam_id AND student_id = $user_id AND status = 'completed'")->fetch_assoc()['count'] + 1; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Progress Card -->
        <div class="progress-card">
            <div class="progress-circle">
                <svg viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" class="progress-bg"/>
                    <circle cx="50" cy="50" r="45" class="progress-fill" id="progressCircle"/>
                </svg>
                <div class="progress-text">
                    <span id="answeredPercent">0</span>%
                </div>
            </div>
            <div class="progress-details">
                <div class="answered-count">
                    <i class="fas fa-check-circle"></i>
                    <span id="answeredCount">0</span> of <?php echo $question_count; ?> answered
                </div>
                <div class="review-count" id="reviewCount">
                    <i class="fas fa-flag"></i>
                    <span>0</span> marked for review
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="submit-exam.php" id="examForm">
        <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
        <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
        
        <!-- Question Navigator -->
        <div class="question-navigator">
            <div class="nav-header">
                <h3>Question Navigator</h3>
                <button type="button" class="toggle-nav" onclick="toggleNavigator()">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="nav-grid" id="navGrid">
                <?php for ($i = 0; $i < $question_count; $i++): ?>
                <button type="button" class="nav-item" data-question-index="<?php echo $i; ?>" onclick="scrollToQuestion(<?php echo $i; ?>)">
                    <?php echo $i + 1; ?>
                </button>
                <?php endfor; ?>
            </div>
        </div>
        
        <div id="questions-container">
            <?php foreach ($question_list as $index => $question): ?>
            <div class="question-card" data-question-id="<?php echo $question['id']; ?>" data-question-index="<?php echo $index; ?>" id="question-<?php echo $index; ?>">
                <div class="question-header">
                    <div class="question-number">
                        <span class="number-badge"><?php echo $index + 1; ?></span>
                        <span class="difficulty-badge difficulty-<?php echo $question['difficulty']; ?>">
                            <?php echo ucfirst($question['difficulty']); ?>
                        </span>
                    </div>
                    <div class="question-actions">
                        <button type="button" class="review-btn" onclick="toggleReview(this, <?php echo $question['id']; ?>, <?php echo $index; ?>)">
                            <i class="fas fa-flag"></i>
                            <span>Review Later</span>
                        </button>
                    </div>
                </div>
                
                <div class="question-text">
                    <?php echo nl2br(htmlspecialchars($question['question_text'])); ?>
                </div>
                
                <div class="options-grid">
                    <?php 
                    $options = $question['shuffled_options'];
                    foreach ($options as $letter => $text): 
                    ?>
                    <label class="option-card">
                        <input type="radio" name="answers[<?php echo $question['id']; ?>]" value="<?php echo $letter; ?>" required onchange="markAnswered(this, <?php echo $question['id']; ?>, <?php echo $index; ?>)">
                        <div class="option-content">
                            <span class="option-letter"><?php echo $letter; ?></span>
                            <span class="option-text"><?php echo htmlspecialchars($text); ?></span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="exam-actions">
            <button type="button" class="btn-cancel" onclick="window.location.href='exams.php'">
                <i class="fas fa-times"></i> Exit Exam
            </button>
            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-check-circle"></i> Submit Exam
            </button>
        </div>
    </form>
</div>

<!-- Add your existing CSS and JavaScript here -->

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #f8fafc;
}

.exam-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 2rem;
}

/* Exam Header */
.exam-header {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.exam-title {
    font-size: 1.5rem;
    color: #1e293b;
    margin-bottom: 1rem;
}

.exam-meta-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
}

.exam-meta-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.9rem;
}

.exam-meta-item i {
    color: #10b981;
    width: 20px;
}

.exam-meta-item.timer {
    font-weight: 600;
    color: #f59e0b;
}

/* Progress Card */
.progress-card {
    text-align: center;
}

.progress-circle {
    position: relative;
    width: 100px;
    height: 100px;
    margin-bottom: 0.5rem;
}

.progress-circle svg {
    transform: rotate(-90deg);
    width: 100%;
    height: 100%;
}

.progress-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 8;
}

.progress-fill {
    fill: none;
    stroke: #10b981;
    stroke-width: 8;
    stroke-dasharray: 283;
    stroke-dashoffset: 283;
    transition: stroke-dashoffset 0.5s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.2rem;
    font-weight: 700;
    color: #10b981;
}

.progress-details {
    font-size: 0.85rem;
}

.answered-count, .review-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
    margin: 0.25rem 0;
    color: #64748b;
}

/* Question Navigator */
.question-navigator {
    background: white;
    border-radius: 16px;
    padding: 1rem;
    margin-bottom: 1.5rem;
    border: 1px solid #eef2f6;
}

.nav-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    user-select: none;
}

.nav-header h3 {
    font-size: 0.9rem;
    color: #1e293b;
    margin: 0;
}

.toggle-nav {
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    transition: transform 0.3s;
}

.toggle-nav.collapsed i {
    transform: rotate(180deg);
}

.nav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
    gap: 0.5rem;
    margin-top: 1rem;
    max-height: 200px;
    overflow-y: auto;
    transition: all 0.3s;
}

.nav-grid.collapsed {
    display: none;
}

.nav-item {
    aspect-ratio: 1;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    color: #1e293b;
    transition: all 0.3s;
    font-size: 0.8rem;
}

.nav-item:hover {
    background: #e2e8f0;
    transform: scale(1.05);
}

.nav-item.answered {
    background: #10b981;
    color: white;
    border-color: #10b981;
}

.nav-item.review {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
}

.nav-item.current {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
    transform: scale(1.05);
}

/* Question Card */
.question-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #eef2f6;
    transition: all 0.3s;
    scroll-margin-top: 100px;
}

.question-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.question-number {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.number-badge {
    background: #f1f5f9;
    color: #1e293b;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-weight: 700;
}

.difficulty-badge {
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.difficulty-easy {
    background: #ecfdf5;
    color: #10b981;
}

.difficulty-medium {
    background: #fef3c7;
    color: #f59e0b;
}

.difficulty-hard {
    background: #fef2f2;
    color: #ef4444;
}

.review-btn {
    background: none;
    border: 1px solid #e2e8f0;
    padding: 6px 12px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.75rem;
    color: #64748b;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.review-btn.active {
    background: #fef3c7;
    border-color: #f59e0b;
    color: #f59e0b;
}

.review-btn:hover {
    background: #f1f5f9;
}

.question-text {
    margin-bottom: 1.5rem;
    font-size: 1rem;
    line-height: 1.6;
    color: #1e293b;
}

.options-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.option-card {
    display: block;
    cursor: pointer;
    transition: all 0.3s;
}

.option-card input {
    display: none;
}

.option-content {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border: 2px solid #eef2f6;
    border-radius: 12px;
    transition: all 0.3s;
}

.option-card:hover .option-content {
    background: #f1f5f9;
    transform: translateX(5px);
}

.option-card input:checked + .option-content {
    background: #ecfdf5;
    border-color: #10b981;
}

.option-letter {
    font-weight: 700;
    color: #10b981;
    background: white;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 0.9rem;
}

.option-text {
    flex: 1;
    color: #1e293b;
    line-height: 1.5;
}

/* Exam Actions */
.exam-actions {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin-top: 2rem;
    flex-wrap: wrap;
}

.btn-cancel, .btn-submit {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    font-size: 0.9rem;
}

.btn-cancel {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
}

.btn-cancel:hover {
    border-color: #ef4444;
    color: #ef4444;
    transform: translateY(-2px);
}

.btn-submit {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16,185,129,0.3);
}

.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Responsive */
@media (max-width: 768px) {
    .exam-container {
        padding: 1rem;
    }
    
    .exam-header {
        flex-direction: column;
        text-align: center;
    }
    
    .exam-meta-grid {
        justify-content: center;
    }
    
    .question-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .options-grid {
        gap: 0.5rem;
    }
    
    .exam-actions {
        flex-direction: column;
    }
    
    .btn-cancel, .btn-submit {
        width: 100%;
        justify-content: center;
    }
}

/* Animation */
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.nav-item.current {
    animation: pulse 0.5s ease;
}
</style>

<script>
let totalQuestions = <?php echo $question_count; ?>;
let answeredQuestions = new Set();
let reviewQuestions = new Set();
let timeLeft = <?php echo $exam['duration_minutes'] * 60; ?>;
let currentQuestionIndex = null;

// Timer functionality
const timerDisplay = document.getElementById('timerDisplay');

function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${minutes}:${secs.toString().padStart(2, '0')}`;
}

function updateTimer() {
    if (timeLeft <= 0) {
        document.getElementById('examForm').submit();
    }
    timerDisplay.textContent = formatTime(timeLeft);
    timeLeft--;
}

setInterval(updateTimer, 1000);

// Update progress circle
function updateProgressCircle() {
    const percentage = (answeredQuestions.size / totalQuestions) * 100;
    const circle = document.getElementById('progressCircle');
    const circumference = 283;
    const offset = circumference - (percentage / 100) * circumference;
    circle.style.strokeDashoffset = offset;
    document.getElementById('answeredPercent').textContent = Math.round(percentage);
}

// Update answered count
function updateAnsweredCount() {
    const count = answeredQuestions.size;
    document.getElementById('answeredCount').textContent = count;
    updateProgressCircle();
    
    if (count === totalQuestions) {
        document.getElementById('submitBtn').style.opacity = '1';
    }
}

// Mark answered
function markAnswered(radioElement, questionId, questionIndex) {
    answeredQuestions.add(questionId);
    updateAnsweredCount();
    
    // Update navigator
    const navItem = document.querySelector(`.nav-item[data-question-id="${questionId}"]`);
    if (navItem) {
        navItem.classList.add('answered');
        navItem.classList.remove('review');
    }
    
    // Auto-save progress
    saveProgress();
}

// Toggle review
function toggleReview(button, questionId, questionIndex) {
    if (reviewQuestions.has(questionId)) {
        reviewQuestions.delete(questionId);
        button.classList.remove('active');
        button.querySelector('span').textContent = 'Review Later';
        
        // Update navigator
        const navItem = document.querySelector(`.nav-item[data-question-id="${questionId}"]`);
        if (navItem && !answeredQuestions.has(questionId)) {
            navItem.classList.remove('review');
        }
    } else {
        reviewQuestions.add(questionId);
        button.classList.add('active');
        button.querySelector('span').textContent = 'Reviewed';
        
        // Update navigator
        const navItem = document.querySelector(`.nav-item[data-question-id="${questionId}"]`);
        if (navItem && !answeredQuestions.has(questionId)) {
            navItem.classList.add('review');
        }
    }
    
    // Update review count display
    document.getElementById('reviewCount').querySelector('span').textContent = reviewQuestions.size;
    saveProgress();
}

// Scroll to question
function scrollToQuestion(questionIndex) {
    const questionElement = document.getElementById(`question-${questionIndex}`);
    if (questionElement) {
        questionElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Highlight current question in navigator
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('current');
        });
        const currentNav = document.querySelector(`.nav-item[data-question-index="${questionIndex}"]`);
        if (currentNav) {
            currentNav.classList.add('current');
        }
        
        // Update current question index
        currentQuestionIndex = questionIndex;
    }
}

// Toggle navigator
function toggleNavigator() {
    const navGrid = document.getElementById('navGrid');
    const toggleBtn = document.querySelector('.toggle-nav');
    navGrid.classList.toggle('collapsed');
    toggleBtn.classList.toggle('collapsed');
}

// Save progress to localStorage
function saveProgress() {
    const answers = {};
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        const name = radio.name;
        answers[name] = radio.value;
    });
    
    const progress = {
        answers: answers,
        answered: Array.from(answeredQuestions),
        review: Array.from(reviewQuestions),
        timestamp: new Date().getTime()
    };
    
    localStorage.setItem(`exam_progress_${<?php echo $attempt_id; ?>}`, JSON.stringify(progress));
}

// Load saved progress
function loadProgress() {
    const saved = localStorage.getItem(`exam_progress_${<?php echo $attempt_id; ?>}`);
    if (saved) {
        const progress = JSON.parse(saved);
        
        // Restore answers
        for (const [name, value] of Object.entries(progress.answers)) {
            const radio = document.querySelector(`input[name="${name}"][value="${value}"]`);
            if (radio) {
                radio.checked = true;
                const questionId = parseInt(name.match(/\d+/)[0]);
                answeredQuestions.add(questionId);
            }
        }
        
        // Restore review marks
        progress.review.forEach(questionId => {
            reviewQuestions.add(questionId);
            const reviewBtn = document.querySelector(`.review-btn[onclick*="${questionId}"]`);
            if (reviewBtn) {
                reviewBtn.classList.add('active');
                reviewBtn.querySelector('span').textContent = 'Reviewed';
            }
        });
        
        updateAnsweredCount();
        document.getElementById('reviewCount').querySelector('span').textContent = reviewQuestions.size;
        
        // Update navigator
        document.querySelectorAll('.nav-item').forEach(item => {
            const qid = parseInt(item.dataset.questionId);
            if (answeredQuestions.has(qid)) {
                item.classList.add('answered');
            } else if (reviewQuestions.has(qid)) {
                item.classList.add('review');
            }
        });
    }
}

// Clear progress on submit
document.getElementById('examForm').addEventListener('submit', function() {
    localStorage.removeItem(`exam_progress_${<?php echo $attempt_id; ?>}`);
});

// Confirm before leaving
let formSubmitted = false;
document.getElementById('examForm').addEventListener('submit', function() {
    formSubmitted = true;
});

window.addEventListener('beforeunload', function(e) {
    if (!formSubmitted && answeredQuestions.size < totalQuestions) {
        e.preventDefault();
        e.returnValue = 'You have not answered all questions. Are you sure you want to leave?';
        return 'You have not answered all questions. Are you sure you want to leave?';
    }
});

// Load saved progress on page load
window.addEventListener('load', function() {
    loadProgress();
    
    // Add scroll spy to highlight current question
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const questionIndex = parseInt(entry.target.dataset.questionIndex);
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.classList.remove('current');
                });
                const currentNav = document.querySelector(`.nav-item[data-question-index="${questionIndex}"]`);
                if (currentNav) {
                    currentNav.classList.add('current');
                }
            }
        });
    }, { threshold: 0.5 });
    
    document.querySelectorAll('.question-card').forEach(question => {
        observer.observe(question);
    });
});

// Auto-save every 15 seconds
setInterval(saveProgress, 15000);
</script>

<?php include '../includes/footer.php'; ?>