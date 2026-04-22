<?php
/**
 * AI Question & Exam Generator
 * Uses Google Gemini AI to generate exam questions and content
 */

session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/gemini.php';

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'questions';

// Get subjects for dropdown
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name");

// Handle AI Question Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Generate Questions
    if ($action === 'generate_questions') {
        $subject_id = (int)$_POST['subject_id'];
        $topic = trim($_POST['topic']);
        $num_questions = (int)$_POST['num_questions'];
        $difficulty = $_POST['difficulty'];
        
        // Get subject name
        $subject_result = $conn->query("SELECT name FROM subjects WHERE id = $subject_id");
        $subject = $subject_result->fetch_assoc();
        $subject_name = $subject['name'];
        
        $full_topic = $topic ? "$subject_name - $topic" : $subject_name;
        
        // Generate questions with Gemini
        $generated_questions = generateExamQuestions($full_topic, $num_questions, $difficulty);
        
        if ($generated_questions && count($generated_questions) > 0) {
            $_SESSION['generated_questions'] = $generated_questions;
            $_SESSION['generated_topic'] = $full_topic;
            $message = count($generated_questions) . " questions generated successfully!";
        } else {
            $error = "Failed to generate questions. Please try again.";
        }
    }
    
    // Save Generated Questions
    elseif ($action === 'save_questions') {
        $questions = $_POST['questions'] ?? [];
        $subject_id = (int)$_POST['subject_id'];
        $topic = $_POST['topic'];
        
        $saved = 0;
        $failed = 0;
        
        foreach ($questions as $q) {
            if (empty($q['question_text']) || empty($q['correct_option'])) {
                $failed++;
                continue;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, 
                                      correct_option, explanation, difficulty, marks, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())
            ");
            
            $difficulty = $q['difficulty'] ?? 'medium';
            $explanation = $q['explanation'] ?? '';
            $user_id = $_SESSION['user_id'];
            
            $stmt->bind_param("issssssssi", 
                $subject_id, 
                $q['question_text'], 
                $q['option_a'], 
                $q['option_b'], 
                $q['option_c'], 
                $q['option_d'], 
                $q['correct_option'], 
                $explanation, 
                $difficulty,
                $user_id
            );
            
            if ($stmt->execute()) {
                $saved++;
            } else {
                $failed++;
            }
        }
        
        if ($saved > 0) {
            $message = "$saved questions saved successfully!";
            if ($failed > 0) $message .= " $failed failed.";
            unset($_SESSION['generated_questions']);
        } else {
            $error = "Failed to save questions.";
        }
    }
    
    // Generate Exam Description
    elseif ($action === 'generate_description') {
        $title = $_POST['title'];
        $subject_id = (int)$_POST['subject_id'];
        $difficulty = $_POST['difficulty'];
        
        $subject_result = $conn->query("SELECT name FROM subjects WHERE id = $subject_id");
        $subject = $subject_result->fetch_assoc();
        
        $description = generateExamDescription($title, $subject['name'], $difficulty);
        
        if ($description) {
            echo json_encode(['success' => true, 'description' => $description]);
            exit();
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to generate description']);
            exit();
        }
    }
    
    // Analyze Answer
    elseif ($action === 'analyze_answer') {
        $question = $_POST['question'];
        $student_answer = $_POST['student_answer'];
        $correct_answer = $_POST['correct_answer'];
        
        $feedback = analyzeStudentAnswer($question, $student_answer, $correct_answer);
        
        if ($feedback) {
            echo json_encode(['success' => true, 'feedback' => $feedback]);
            exit();
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to analyze answer']);
            exit();
        }
    }
    
    // Generate Study Summary
    elseif ($action === 'generate_summary') {
        $topic = $_POST['topic'];
        
        $summary = generateStudySummary($topic);
        
        if ($summary) {
            echo json_encode(['success' => true, 'summary' => $summary]);
            exit();
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to generate summary']);
            exit();
        }
    }
    
    // Test API
    elseif ($action === 'test_api') {
        $test_result = testGeminiAPI();
        if ($test_result) {
            $message = "✅ Gemini API is working correctly!";
        } else {
            $error = "❌ Gemini API test failed. Please check your API key.";
        }
    }
}

$generated_questions = $_SESSION['generated_questions'] ?? [];
$generated_topic = $_SESSION['generated_topic'] ?? '';

$page_title = 'AI Generator';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-robot"></i> AI Content Generator</h1>
        <p>Powered by Google Gemini AI - Generate exam questions, descriptions, and study materials</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- AI Tools Tabs -->
    <div class="ai-tabs">
        <a href="?tab=questions" class="ai-tab <?php echo $active_tab == 'questions' ? 'active' : ''; ?>">
            <i class="fas fa-question-circle"></i> Generate Questions
        </a>
        <a href="?tab=exam" class="ai-tab <?php echo $active_tab == 'exam' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i> Generate Exam
        </a>
        <a href="?tab=summary" class="ai-tab <?php echo $active_tab == 'summary' ? 'active' : ''; ?>">
            <i class="fas fa-book-open"></i> Study Summary
        </a>
        <a href="?tab=analyze" class="ai-tab <?php echo $active_tab == 'analyze' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Answer Analysis
        </a>
    </div>
    
    <!-- Test API Button -->
    <div style="text-align: right; margin-bottom: 1rem;">
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="test_api">
            <button type="submit" class="btn-sm btn-secondary">
                <i class="fas fa-vial"></i> Test API Connection
            </button>
        </form>
    </div>
    
    <?php if ($active_tab == 'questions'): ?>
    <!-- Generate Questions Tab -->
    <div class="card">
        <h2><i class="fas fa-magic"></i> Generate Questions with AI</h2>
        <form method="POST" id="generateForm">
            <input type="hidden" name="action" value="generate_questions">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">Select Subject</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Topic (Optional)</label>
                    <input type="text" name="topic" class="form-control" placeholder="e.g., Cell Division, Algebra, Grammar">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Number of Questions</label>
                    <select name="num_questions" class="form-control">
                        <option value="3">3 Questions</option>
                        <option value="5" selected>5 Questions</option>
                        <option value="10">10 Questions</option>
                        <option value="15">15 Questions</option>
                        <option value="20">20 Questions</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Difficulty Level</label>
                    <select name="difficulty" class="form-control">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" id="generateBtn">
                <i class="fas fa-magic"></i> Generate Questions
            </button>
        </form>
        
        <?php if (!empty($generated_questions)): ?>
        <div class="generated-questions">
            <h3>Generated Questions - <?php echo htmlspecialchars($generated_topic); ?></h3>
            <form method="POST" id="saveQuestionsForm">
                <input type="hidden" name="action" value="save_questions">
                <input type="hidden" name="subject_id" id="save_subject_id" value="">
                <input type="hidden" name="topic" value="<?php echo htmlspecialchars($generated_topic); ?>">
                
                <?php foreach ($generated_questions as $index => $q): ?>
                <div class="question-preview">
                    <div class="question-header">
                        <strong>Question <?php echo $index + 1; ?></strong>
                        <select name="questions[<?php echo $index; ?>][difficulty]" class="difficulty-select">
                            <option value="easy" <?php echo ($q['difficulty'] ?? 'medium') == 'easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo ($q['difficulty'] ?? 'medium') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo ($q['difficulty'] ?? 'medium') == 'hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="questions[<?php echo $index; ?>][question_text]" value="<?php echo htmlspecialchars($q['question_text']); ?>">
                    <input type="hidden" name="questions[<?php echo $index; ?>][option_a]" value="<?php echo htmlspecialchars($q['option_a']); ?>">
                    <input type="hidden" name="questions[<?php echo $index; ?>][option_b]" value="<?php echo htmlspecialchars($q['option_b']); ?>">
                    <input type="hidden" name="questions[<?php echo $index; ?>][option_c]" value="<?php echo htmlspecialchars($q['option_c']); ?>">
                    <input type="hidden" name="questions[<?php echo $index; ?>][option_d]" value="<?php echo htmlspecialchars($q['option_d']); ?>">
                    <input type="hidden" name="questions[<?php echo $index; ?>][correct_option]" value="<?php echo $q['correct_option']; ?>">
                    <input type="hidden" name="questions[<?php echo $index; ?>][explanation]" value="<?php echo htmlspecialchars($q['explanation'] ?? ''); ?>">
                    
                    <div class="question-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>
                    <div class="options">
                        <div class="option correct">A) <?php echo htmlspecialchars($q['option_a']); ?></div>
                        <div class="option">B) <?php echo htmlspecialchars($q['option_b']); ?></div>
                        <div class="option">C) <?php echo htmlspecialchars($q['option_c']); ?></div>
                        <div class="option">D) <?php echo htmlspecialchars($q['option_d']); ?></div>
                    </div>
                    <div class="answer-info">
                        <span class="correct-answer">✓ Correct: <?php echo $q['correct_option']; ?></span>
                        <?php if (!empty($q['explanation'])): ?>
                            <span class="explanation">📖 Explanation: <?php echo htmlspecialchars(substr($q['explanation'], 0, 100)); ?>...</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save All Questions
                    </button>
                    <a href="?tab=questions" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($active_tab == 'exam'): ?>
    <!-- Generate Exam Tab -->
    <div class="card">
        <h2><i class="fas fa-file-alt"></i> Generate Complete Exam with AI</h2>
        <form id="examForm">
            <div class="form-row">
                <div class="form-group">
                    <label>Exam Title *</label>
                    <input type="text" id="exam_title" class="form-control" placeholder="e.g., Mid-Term Examination 2024" required>
                </div>
                
                <div class="form-group">
                    <label>Subject *</label>
                    <select id="exam_subject_id" class="form-control" required>
                        <option value="">Select Subject</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>" data-name="<?php echo htmlspecialchars($subject['name']); ?>">
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Difficulty Level</label>
                    <select id="exam_difficulty" class="form-control">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Number of Questions</label>
                    <select id="exam_questions_count" class="form-control">
                        <option value="5">5 Questions</option>
                        <option value="10" selected>10 Questions</option>
                        <option value="15">15 Questions</option>
                        <option value="20">20 Questions</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description (AI Generated)</label>
                <textarea id="exam_description" class="form-control" rows="3" placeholder="Click 'Generate Description' to create one"></textarea>
                <button type="button" id="generateDescBtn" class="btn-sm btn-secondary" style="margin-top: 0.5rem;">
                    <i class="fas fa-magic"></i> Generate Description
                </button>
            </div>
            
            <div class="form-group">
                <label>Duration (minutes)</label>
                <input type="number" id="exam_duration" class="form-control" value="60" min="15" max="180">
            </div>
            
            <div class="form-group">
                <label>Passing Score (%)</label>
                <input type="number" id="exam_passing_score" class="form-control" value="70" min="0" max="100">
            </div>
            
            <button type="button" id="generateExamBtn" class="btn btn-primary">
                <i class="fas fa-magic"></i> Generate Complete Exam
            </button>
        </form>
        
        <div id="examResult" style="display: none; margin-top: 2rem;">
            <h3>Generated Exam Preview</h3>
            <div id="examQuestionsPreview"></div>
            <div class="form-actions">
                <button id="saveExamBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Exam
                </button>
                <button id="cancelExamBtn" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($active_tab == 'summary'): ?>
    <!-- Study Summary Tab -->
    <div class="card">
        <h2><i class="fas fa-book-open"></i> Generate Study Summary</h2>
        <div class="form-group">
            <label>Topic / Subject *</label>
            <input type="text" id="summary_topic" class="form-control" placeholder="e.g., Photosynthesis, Quadratic Equations, World War II">
        </div>
        <button type="button" id="generateSummaryBtn" class="btn btn-primary">
            <i class="fas fa-magic"></i> Generate Summary
        </button>
        
        <div id="summaryResult" style="display: none; margin-top: 2rem;">
            <h3>AI Generated Summary</h3>
            <div id="summaryContent" class="summary-content"></div>
            <div class="form-actions">
                <button id="copySummaryBtn" class="btn btn-secondary">
                    <i class="fas fa-copy"></i> Copy to Clipboard
                </button>
                <button id="saveMaterialBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save as Study Material
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($active_tab == 'analyze'): ?>
    <!-- Answer Analysis Tab -->
    <div class="card">
        <h2><i class="fas fa-chart-line"></i> AI Answer Analysis</h2>
        <p>Paste a student's answer to get AI-powered feedback and analysis.</p>
        
        <div class="form-group">
            <label>Question</label>
            <textarea id="analyze_question" class="form-control" rows="2" placeholder="What is the capital of France?"></textarea>
        </div>
        
        <div class="form-group">
            <label>Student's Answer</label>
            <textarea id="analyze_student_answer" class="form-control" rows="3" placeholder="Paris"></textarea>
        </div>
        
        <div class="form-group">
            <label>Correct Answer</label>
            <input type="text" id="analyze_correct_answer" class="form-control" placeholder="Paris">
        </div>
        
        <button type="button" id="analyzeBtn" class="btn btn-primary">
            <i class="fas fa-brain"></i> Analyze Answer
        </button>
        
        <div id="analysisResult" style="display: none; margin-top: 2rem;">
            <h3>AI Feedback</h3>
            <div id="analysisContent" class="analysis-content"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.container {
    max-width: 1200px;
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

.ai-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
    flex-wrap: wrap;
}

.ai-tab {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.ai-tab:hover {
    background: #f1f5f9;
    color: #10b981;
}

.ai-tab.active {
    background: #10b981;
    color: white;
}

.card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    margin-bottom: 2rem;
}

.card h2 {
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e293b;
    border-bottom: 2px solid #eef2f6;
    padding-bottom: 1rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1e293b;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
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
    box-shadow: 0 10px 25px rgba(16,185,129,0.4);
}

.btn-secondary {
    background: white;
    color: #64748b;
    border: 2px solid #e2e8f0;
}

.btn-secondary:hover {
    border-color: #10b981;
    color: #10b981;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.85rem;
}

.alert {
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: #ecfdf5;
    color: #10b981;
    border-left: 4px solid #10b981;
}

.alert-error {
    background: #fef2f2;
    color: #ef4444;
    border-left: 4px solid #ef4444;
}

/* Question Preview Styles */
.generated-questions {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 2px solid #eef2f6;
}

.question-preview {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.difficulty-select {
    padding: 4px 8px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: white;
}

.question-text {
    font-weight: 600;
    margin-bottom: 1rem;
    color: #1e293b;
}

.options {
    margin-bottom: 1rem;
}

.option {
    padding: 0.5rem;
    margin-bottom: 0.25rem;
    border-radius: 8px;
}

.option.correct {
    background: #ecfdf5;
    border-left: 3px solid #10b981;
}

.answer-info {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    padding-top: 0.5rem;
    border-top: 1px solid #e2e8f0;
}

.correct-answer {
    color: #10b981;
}

.explanation {
    color: #64748b;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}

.summary-content, .analysis-content {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .ai-tabs {
        flex-direction: column;
    }
    
    .ai-tab {
        justify-content: center;
    }
}
</style>

<script>
// Generate Description
document.getElementById('generateDescBtn')?.addEventListener('click', async function() {
    const title = document.getElementById('exam_title').value;
    const subjectSelect = document.getElementById('exam_subject_id');
    const subjectId = subjectSelect.value;
    const subjectName = subjectSelect.options[subjectSelect.selectedIndex]?.dataset.name;
    const difficulty = document.getElementById('exam_difficulty').value;
    
    if (!title || !subjectId) {
        alert('Please enter exam title and select subject');
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    
    try {
        const formData = new FormData();
        formData.append('action', 'generate_description');
        formData.append('title', title);
        formData.append('subject_id', subjectId);
        formData.append('difficulty', difficulty);
        
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('exam_description').value = data.description;
        } else {
            alert('Failed to generate description: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-magic"></i> Generate Description';
    }
});

// Generate Exam
document.getElementById('generateExamBtn')?.addEventListener('click', async function() {
    const title = document.getElementById('exam_title').value;
    const subjectId = document.getElementById('exam_subject_id').value;
    const subjectName = document.getElementById('exam_subject_id').options[document.getElementById('exam_subject_id').selectedIndex]?.dataset.name;
    const difficulty = document.getElementById('exam_difficulty').value;
    const numQuestions = document.getElementById('exam_questions_count').value;
    
    if (!title || !subjectId) {
        alert('Please enter exam title and select subject');
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Exam...';
    
    const formData = new FormData();
    formData.append('action', 'generate_questions');
    formData.append('subject_id', subjectId);
    formData.append('topic', subjectName);
    formData.append('num_questions', numQuestions);
    formData.append('difficulty', difficulty);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const html = await response.text();
        
        // Reload page to show generated questions
        window.location.href = '?tab=questions';
        
    } catch (error) {
        alert('Error: ' + error.message);
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-magic"></i> Generate Complete Exam';
    }
});

// Generate Summary
document.getElementById('generateSummaryBtn')?.addEventListener('click', async function() {
    const topic = document.getElementById('summary_topic').value;
    
    if (!topic) {
        alert('Please enter a topic');
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating Summary...';
    
    const formData = new FormData();
    formData.append('action', 'generate_summary');
    formData.append('topic', topic);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('summaryContent').innerHTML = data.summary.replace(/\n/g, '<br>');
            document.getElementById('summaryResult').style.display = 'block';
        } else {
            alert('Failed to generate summary: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-magic"></i> Generate Summary';
    }
});

// Analyze Answer
document.getElementById('analyzeBtn')?.addEventListener('click', async function() {
    const question = document.getElementById('analyze_question').value;
    const studentAnswer = document.getElementById('analyze_student_answer').value;
    const correctAnswer = document.getElementById('analyze_correct_answer').value;
    
    if (!question || !studentAnswer || !correctAnswer) {
        alert('Please fill in all fields');
        return;
    }
    
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
    
    const formData = new FormData();
    formData.append('action', 'analyze_answer');
    formData.append('question', question);
    formData.append('student_answer', studentAnswer);
    formData.append('correct_answer', correctAnswer);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('analysisContent').innerHTML = data.feedback.replace(/\n/g, '<br>');
            document.getElementById('analysisResult').style.display = 'block';
        } else {
            alert('Failed to analyze answer: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        this.disabled = false;
        this.innerHTML = '<i class="fas fa-brain"></i> Analyze Answer';
    }
});

// Copy Summary
document.getElementById('copySummaryBtn')?.addEventListener('click', function() {
    const content = document.getElementById('summaryContent').innerText;
    navigator.clipboard.writeText(content).then(() => {
        alert('Summary copied to clipboard!');
    });
});

// Save as Study Material
document.getElementById('saveMaterialBtn')?.addEventListener('click', async function() {
    const topic = document.getElementById('summary_topic').value;
    const content = document.getElementById('summaryContent').innerHTML;
    
    // Save to database via AJAX
    const formData = new FormData();
    formData.append('action', 'save_material');
    formData.append('title', topic);
    formData.append('content', content);
    
    // You'll need to implement save_material action
    alert('Study material saved! (Feature coming soon)');
});

// Set subject ID for saving questions
document.getElementById('saveQuestionsForm')?.addEventListener('submit', function(e) {
    const subjectId = document.querySelector('select[name="subject_id"]').value;
    document.getElementById('save_subject_id').value = subjectId;
});
</script>

<?php include '../includes/footer.php'; ?>