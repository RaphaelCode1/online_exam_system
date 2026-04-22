<?php
/**
 * Assign Questions to Exam
 * Modern interface with search, filter, and bulk operations
 */

session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canEditExams()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Get exam details
$exam = $conn->query("SELECT * FROM exams WHERE id = $exam_id")->fetch_assoc();

if (!$exam) {
    header('Location: exams.php');
    exit();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'assign') {
        $question_ids = isset($_POST['question_ids']) ? $_POST['question_ids'] : [];
        
        foreach ($question_ids as $question_id) {
            // Check if already assigned
            $check = $conn->query("SELECT id FROM exam_questions WHERE exam_id = $exam_id AND question_id = $question_id")->num_rows;
            if ($check == 0) {
                $stmt = $conn->prepare("INSERT INTO exam_questions (exam_id, question_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $exam_id, $question_id);
                $stmt->execute();
            }
        }
        
        $_SESSION['success'] = count($question_ids) . " question(s) assigned successfully!";
        header("Location: assign-questions.php?exam_id=$exam_id");
        exit();
        
    } elseif ($action === 'remove') {
        $question_id = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
        
        $stmt = $conn->prepare("DELETE FROM exam_questions WHERE exam_id = ? AND question_id = ?");
        $stmt->bind_param("ii", $exam_id, $question_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Question removed from exam!";
        header("Location: assign-questions.php?exam_id=$exam_id");
        exit();
        
    } elseif ($action === 'bulk_remove') {
        $question_ids = isset($_POST['question_ids']) ? $_POST['question_ids'] : [];
        
        foreach ($question_ids as $question_id) {
            $stmt = $conn->prepare("DELETE FROM exam_questions WHERE exam_id = ? AND question_id = ?");
            $stmt->bind_param("ii", $exam_id, $question_id);
            $stmt->execute();
        }
        
        $_SESSION['success'] = count($question_ids) . " question(s) removed from exam!";
        header("Location: assign-questions.php?exam_id=$exam_id");
        exit();
    }
}

// Get assigned questions
$assigned_questions = $conn->query("
    SELECT q.*, eq.id as assigned_id 
    FROM exam_questions eq 
    JOIN questions q ON eq.question_id = q.id 
    WHERE eq.exam_id = $exam_id 
    ORDER BY eq.id
");

// Get available questions (not assigned)
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$subject_filter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$difficulty_filter = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

$available_query = "SELECT q.*, s.name as subject_name 
                    FROM questions q 
                    LEFT JOIN subjects s ON q.subject_id = s.id 
                    WHERE q.id NOT IN (SELECT question_id FROM exam_questions WHERE exam_id = $exam_id)";

if ($search) {
    $available_query .= " AND (q.question_text LIKE '%$search%')";
}
if ($subject_filter > 0) {
    $available_query .= " AND q.subject_id = $subject_filter";
}
if ($difficulty_filter) {
    $available_query .= " AND q.difficulty = '$difficulty_filter'";
}

$available_query .= " ORDER BY q.created_at DESC";

$available_questions = $conn->query($available_query);

// Get subjects for filter
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name");

$page_title = "Assign Questions - " . htmlspecialchars($exam['title']);
include '../includes/header.php';
?>

<div class="assign-container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-link"></i> Assign Questions to Exam</h1>
            <p class="exam-info">Exam: <strong><?php echo htmlspecialchars($exam['title']); ?></strong> | Total Questions: <?php echo $exam['total_questions']; ?></p>
        </div>
        <a href="exams.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Exams
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="two-column-layout">
        <!-- Assigned Questions Column -->
        <div class="column assigned-column">
            <div class="column-header">
                <h2><i class="fas fa-check-circle"></i> Assigned Questions</h2>
                <span class="badge"><?php echo $assigned_questions->num_rows; ?> questions</span>
            </div>
            
            <?php if ($assigned_questions->num_rows > 0): ?>
                <form method="POST" id="removeForm">
                    <input type="hidden" name="action" value="bulk_remove">
                    <div class="questions-list">
                        <?php $q_num = 1; while ($q = $assigned_questions->fetch_assoc()): ?>
                            <div class="question-item assigned">
                                <div class="question-checkbox">
                                    <input type="checkbox" name="question_ids[]" value="<?php echo $q['id']; ?>" class="question-checkbox">
                                </div>
                                <div class="question-content">
                                    <div class="question-header">
                                        <span class="question-number">#<?php echo $q_num; ?></span>
                                        <span class="difficulty-badge difficulty-<?php echo $q['difficulty']; ?>">
                                            <?php echo ucfirst($q['difficulty']); ?>
                                        </span>
                                        <span class="subject-badge"><?php echo htmlspecialchars($q['subject_name'] ?? 'No Subject'); ?></span>
                                        <span class="marks-badge"><?php echo $q['marks']; ?> marks</span>
                                    </div>
                                    <div class="question-text">
                                        <?php echo htmlspecialchars(substr($q['question_text'], 0, 150)); ?>
                                        <?php if (strlen($q['question_text']) > 150): ?>...<?php endif; ?>
                                    </div>
                                    <div class="question-actions">
                                        <button type="button" class="btn-icon edit-question" data-id="<?php echo $q['id']; ?>" data-text="<?php echo htmlspecialchars($q['question_text']); ?>" data-opt-a="<?php echo htmlspecialchars($q['option_a']); ?>" data-opt-b="<?php echo htmlspecialchars($q['option_b']); ?>" data-opt-c="<?php echo htmlspecialchars($q['option_c']); ?>" data-opt-d="<?php echo htmlspecialchars($q['option_d']); ?>" data-correct="<?php echo $q['correct_option']; ?>" data-difficulty="<?php echo $q['difficulty']; ?>" data-marks="<?php echo $q['marks']; ?>" data-subject="<?php echo $q['subject_id']; ?>">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn-icon remove-single" data-id="<?php echo $q['id']; ?>">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php $q_num++; endwhile; ?>
                    </div>
                    
                    <div class="bulk-actions">
                        <button type="button" class="btn-checkbox-all" onclick="toggleAllCheckboxes()">
                            <i class="fas fa-check-double"></i> Select All
                        </button>
                        <button type="submit" class="btn-danger" onclick="return confirm('Remove selected questions from this exam?')">
                            <i class="fas fa-trash-alt"></i> Remove Selected
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state small">
                    <i class="fas fa-question-circle"></i>
                    <p>No questions assigned yet</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Available Questions Column -->
        <div class="column available-column">
            <div class="column-header">
                <h2><i class="fas fa-database"></i> Available Questions</h2>
                <span class="badge"><?php echo $available_questions->num_rows; ?> available</span>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="filter-form">
                    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                    <div class="filter-group">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search questions..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="filter-group">
                        <i class="fas fa-book"></i>
                        <select name="subject">
                            <option value="0">All Subjects</option>
                            <?php while ($subject = $subjects->fetch_assoc()): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <i class="fas fa-chart-line"></i>
                        <select name="difficulty">
                            <option value="">All Difficulties</option>
                            <option value="easy" <?php echo $difficulty_filter == 'easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo $difficulty_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo $difficulty_filter == 'hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <?php if ($search || $subject_filter || $difficulty_filter): ?>
                        <a href="assign-questions.php?exam_id=<?php echo $exam_id; ?>" class="btn-clear">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if ($available_questions->num_rows > 0): ?>
                <form method="POST" id="assignForm">
                    <input type="hidden" name="action" value="assign">
                    <div class="questions-list">
                        <?php while ($q = $available_questions->fetch_assoc()): ?>
                            <div class="question-item available">
                                <div class="question-checkbox">
                                    <input type="checkbox" name="question_ids[]" value="<?php echo $q['id']; ?>" class="question-checkbox">
                                </div>
                                <div class="question-content">
                                    <div class="question-header">
                                        <span class="difficulty-badge difficulty-<?php echo $q['difficulty']; ?>">
                                            <?php echo ucfirst($q['difficulty']); ?>
                                        </span>
                                        <span class="subject-badge"><?php echo htmlspecialchars($q['subject_name'] ?? 'No Subject'); ?></span>
                                        <span class="marks-badge"><?php echo $q['marks']; ?> marks</span>
                                    </div>
                                    <div class="question-text">
                                        <?php echo htmlspecialchars(substr($q['question_text'], 0, 150)); ?>
                                        <?php if (strlen($q['question_text']) > 150): ?>...<?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="bulk-actions">
                        <button type="button" class="btn-checkbox-all" onclick="toggleAvailableCheckboxes()">
                            <i class="fas fa-check-double"></i> Select All
                        </button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus-circle"></i> Assign Selected
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-state small">
                    <i class="fas fa-check-circle"></i>
                    <p>All questions are assigned or no questions available</p>
                    <a href="questions.php" class="btn-sm btn-primary">Create New Question</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Question Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Question</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <form id="editForm" method="POST" action="update-question.php">
            <input type="hidden" name="question_id" id="edit_question_id">
            <input type="hidden" name="redirect" value="assign-questions.php?exam_id=<?php echo $exam_id; ?>">
            
            <div class="form-group">
                <label>Question Text *</label>
                <textarea name="question_text" id="edit_question_text" class="form-control" rows="4" required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Option A *</label>
                    <input type="text" name="option_a" id="edit_option_a" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Option B *</label>
                    <input type="text" name="option_b" id="edit_option_b" class="form-control" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Option C *</label>
                    <input type="text" name="option_c" id="edit_option_c" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Option D *</label>
                    <input type="text" name="option_d" id="edit_option_d" class="form-control" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Correct Option *</label>
                    <select name="correct_option" id="edit_correct_option" class="form-control" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Difficulty *</label>
                    <select name="difficulty" id="edit_difficulty" class="form-control" required>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Marks *</label>
                    <input type="number" name="marks" id="edit_marks" class="form-control" required min="1">
                </div>
            </div>
            
            <div class="form-group">
                <label>Subject (Optional)</label>
                <select name="subject_id" id="edit_subject_id" class="form-control">
                    <option value="">No Subject</option>
                    <?php 
                    $subjects->data_seek(0);
                    while ($subject = $subjects->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Explanation (Optional)</label>
                <textarea name="explanation" id="edit_explanation" class="form-control" rows="3" placeholder="Explain why this is the correct answer..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Update Question</button>
            </div>
        </form>
    </div>
</div>

<style>
.assign-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-header h1 {
    font-size: 1.8rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.exam-info {
    color: #64748b;
    margin-top: 0.5rem;
}

.exam-info strong {
    color: #10b981;
}

.two-column-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.column {
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
    overflow: hidden;
}

.column-header {
    padding: 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.column-header h2 {
    font-size: 1.2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.badge {
    background: #e2e8f0;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #1e293b;
}

/* Filters */
.filters {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #eef2f6;
    background: white;
}

.filter-form {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0 12px;
    flex: 1;
    min-width: 150px;
}

.filter-group i {
    color: #94a3b8;
}

.filter-group input,
.filter-group select {
    border: none;
    background: none;
    padding: 10px 0;
    width: 100%;
    outline: none;
    font-size: 0.85rem;
}

.btn-filter {
    padding: 8px 16px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-filter:hover {
    background: #059669;
}

.btn-clear {
    padding: 8px 16px;
    background: #f1f5f9;
    color: #64748b;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-clear:hover {
    background: #e2e8f0;
}

/* Questions List */
.questions-list {
    max-height: 500px;
    overflow-y: auto;
    padding: 1rem;
}

.question-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: #f8fafc;
    border-radius: 12px;
    transition: all 0.3s;
}

.question-item:hover {
    background: #f1f5f9;
}

.question-item.assigned {
    border-left: 3px solid #10b981;
}

.question-checkbox {
    padding-top: 0.25rem;
}

.question-checkbox input {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.question-content {
    flex: 1;
}

.question-header {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.question-number {
    font-weight: 700;
    color: #1e293b;
    background: white;
    padding: 2px 8px;
    border-radius: 6px;
    font-size: 0.7rem;
}

.difficulty-badge {
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.65rem;
    font-weight: 600;
}

.difficulty-easy { background: #ecfdf5; color: #10b981; }
.difficulty-medium { background: #fef3c7; color: #f59e0b; }
.difficulty-hard { background: #fef2f2; color: #ef4444; }

.subject-badge {
    background: #e0f2fe;
    color: #3b82f6;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.65rem;
    font-weight: 600;
}

.marks-badge {
    background: #f1f5f9;
    color: #64748b;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.65rem;
    font-weight: 600;
}

.question-text {
    color: #1e293b;
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 0.5rem;
}

.question-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    background: none;
    border: none;
    padding: 4px 8px;
    font-size: 0.7rem;
    cursor: pointer;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
    transition: all 0.3s;
}

.btn-icon:hover {
    background: #e2e8f0;
}

.edit-question {
    color: #3b82f6;
}

.remove-single {
    color: #ef4444;
}

/* Bulk Actions */
.bulk-actions {
    padding: 1rem 1.5rem;
    border-top: 1px solid #eef2f6;
    background: #f8fafc;
    display: flex;
    gap: 1rem;
}

.btn-checkbox-all {
    padding: 8px 16px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-checkbox-all:hover {
    background: #f1f5f9;
}

.btn-danger {
    padding: 8px 16px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #ef4444;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-danger:hover {
    background: #fee2e2;
}

.btn-primary {
    padding: 8px 16px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(16,185,129,0.3);
}

/* Alerts */
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

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-state.small {
    padding: 2rem;
}

.empty-state i {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-state p {
    color: #64748b;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.modal-content {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 1.3rem;
    color: #1e293b;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
}

.modal-close:hover {
    color: #ef4444;
}

.form-group {
    margin-bottom: 1rem;
    padding: 0 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    padding: 0 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1e293b;
    font-size: 0.85rem;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #10b981;
}

textarea.form-control {
    resize: vertical;
}

.form-actions {
    padding: 1.5rem;
    border-top: 1px solid #eef2f6;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.btn-cancel {
    padding: 10px 20px;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-cancel:hover {
    border-color: #ef4444;
    color: #ef4444;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.75rem;
}

@media (max-width: 1024px) {
    .two-column-layout {
        grid-template-columns: 1fr;
    }
    
    .assign-container {
        padding: 1rem;
    }
    
    .filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        width: 100%;
    }
}
</style>

<script>
// Toggle all checkboxes in assigned column
function toggleAllCheckboxes() {
    const checkboxes = document.querySelectorAll('.assigned-column .question-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}

// Toggle all checkboxes in available column
function toggleAvailableCheckboxes() {
    const checkboxes = document.querySelectorAll('.available-column .question-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}

// Remove single question
document.querySelectorAll('.remove-single').forEach(btn => {
    btn.addEventListener('click', function() {
        const questionId = this.dataset.id;
        if (confirm('Remove this question from the exam?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="question_id" value="${questionId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
});

// Edit question modal
const modal = document.getElementById('editModal');

function editQuestion(questionId, questionText, optA, optB, optC, optD, correct, difficulty, marks, subjectId, explanation) {
    document.getElementById('edit_question_id').value = questionId;
    document.getElementById('edit_question_text').value = questionText;
    document.getElementById('edit_option_a').value = optA;
    document.getElementById('edit_option_b').value = optB;
    document.getElementById('edit_option_c').value = optC;
    document.getElementById('edit_option_d').value = optD;
    document.getElementById('edit_correct_option').value = correct;
    document.getElementById('edit_difficulty').value = difficulty;
    document.getElementById('edit_marks').value = marks;
    document.getElementById('edit_subject_id').value = subjectId;
    if (document.getElementById('edit_explanation')) {
        document.getElementById('edit_explanation').value = explanation || '';
    }
    modal.style.display = 'flex';
}

function closeModal() {
    modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target === modal) {
        closeModal();
    }
}

// Initialize edit buttons
document.querySelectorAll('.edit-question').forEach(btn => {
    btn.addEventListener('click', function() {
        editQuestion(
            this.dataset.id,
            this.dataset.text,
            this.dataset.optA,
            this.dataset.optB,
            this.dataset.optC,
            this.dataset.optD,
            this.dataset.correct,
            this.dataset.difficulty,
            this.dataset.marks,
            this.dataset.subject,
            this.dataset.explanation || ''
        );
    });
});
</script>

<?php include '../includes/footer.php'; ?>