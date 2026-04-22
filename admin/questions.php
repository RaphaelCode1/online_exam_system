<?php
/**
 * Admin Questions Management Page
 * Manage all questions (view, add, edit, delete, search, filter)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canViewQuestions()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$filter_subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$filter_difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$filter_status = isset($_GET['status']) ? (int)$_GET['status'] : -1;

// Handle Delete Action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['question_id'])) {
    $delete_id = (int)$_POST['question_id'];
    
    // Check if question is used in any exam
    $check = $conn->query("SELECT COUNT(*) as count FROM exam_questions WHERE question_id = $delete_id");
    $used_count = $check->fetch_assoc()['count'];
    
    if ($used_count > 0) {
        $error = "Cannot delete question that is used in exams. Remove from exams first.";
    } else {
        $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            $message = "Question deleted successfully!";
            logActivity($_SESSION['user_id'], 'delete_question', "Deleted question ID: $delete_id");
        } else {
            $error = "Error deleting question: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Update Action
if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['question_id'])) {
    $update_id = (int)$_POST['question_id'];
    $subject_id = (int)$_POST['subject_id'];
    $topic_id = (int)$_POST['topic_id'] ?: null;
    $question_text = $conn->real_escape_string($_POST['question_text']);
    $option_a = $conn->real_escape_string($_POST['option_a']);
    $option_b = $conn->real_escape_string($_POST['option_b']);
    $option_c = $conn->real_escape_string($_POST['option_c']);
    $option_d = $conn->real_escape_string($_POST['option_d']);
    $correct_option = $_POST['correct_option'];
    $difficulty = $_POST['difficulty'];
    $explanation = $conn->real_escape_string($_POST['explanation']);
    $marks = (float)$_POST['marks'];
    $status = isset($_POST['status']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE questions SET 
                            subject_id = ?, topic_id = ?, question_text = ?, 
                            option_a = ?, option_b = ?, option_c = ?, option_d = ?, 
                            correct_option = ?, difficulty = ?, explanation = ?, 
                            marks = ?, status = ? WHERE id = ?");
    $stmt->bind_param("iissssssssdii", $subject_id, $topic_id, $question_text, 
                      $option_a, $option_b, $option_c, $option_d, 
                      $correct_option, $difficulty, $explanation, 
                      $marks, $status, $update_id);
    
    if ($stmt->execute()) {
        $message = "Question updated successfully!";
        logActivity($_SESSION['user_id'], 'update_question', "Updated question ID: $update_id");
        $action = 'list';
    } else {
        $error = "Error updating question: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Create Action
if (isset($_POST['action']) && $_POST['action'] === 'create') {
    $subject_id = (int)$_POST['subject_id'];
    $topic_id = (int)$_POST['topic_id'] ?: null;
    $question_text = $conn->real_escape_string($_POST['question_text']);
    $option_a = $conn->real_escape_string($_POST['option_a']);
    $option_b = $conn->real_escape_string($_POST['option_b']);
    $option_c = $conn->real_escape_string($_POST['option_c']);
    $option_d = $conn->real_escape_string($_POST['option_d']);
    $correct_option = $_POST['correct_option'];
    $difficulty = $_POST['difficulty'];
    $explanation = $conn->real_escape_string($_POST['explanation']);
    $marks = (float)$_POST['marks'];
    $status = isset($_POST['status']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO questions (subject_id, topic_id, question_text, option_a, option_b, option_c, option_d, correct_option, difficulty, explanation, marks, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iissssssssddi", $subject_id, $topic_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $difficulty, $explanation, $marks, $status, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $message = "Question added successfully!";
        logActivity($_SESSION['user_id'], 'create_question', "Created question ID: " . $stmt->insert_id);
    } else {
        $error = "Error adding question: " . $stmt->error;
    }
    $stmt->close();
}

// Get question data for editing
$edit_question = null;
if ($action === 'edit' && $question_id > 0) {
    $edit_question = $conn->query("SELECT * FROM questions WHERE id = $question_id")->fetch_assoc();
    if (!$edit_question) {
        $action = 'list';
        $error = "Question not found";
    }
}

// Get subjects for dropdown
$subjects = $conn->query("SELECT * FROM subjects WHERE status = 1 ORDER BY name");

// Get topics for dropdown (when editing)
$topics = [];
if ($edit_question && $edit_question['subject_id']) {
    $topics_result = $conn->query("SELECT * FROM topics WHERE subject_id = {$edit_question['subject_id']} AND status = 1 ORDER BY name");
    while ($topic = $topics_result->fetch_assoc()) {
        $topics[] = $topic;
    }
}

// Build query for questions list with filters
$query = "SELECT q.*, s.name as subject_name, t.name as topic_name 
          FROM questions q 
          LEFT JOIN subjects s ON q.subject_id = s.id 
          LEFT JOIN topics t ON q.topic_id = t.id 
          WHERE 1=1";

if ($filter_subject > 0) {
    $query .= " AND q.subject_id = $filter_subject";
}
if (!empty($filter_difficulty)) {
    $query .= " AND q.difficulty = '$filter_difficulty'";
}
if ($filter_status >= 0) {
    $query .= " AND q.status = $filter_status";
}

$query .= " ORDER BY q.created_at DESC LIMIT $offset, $per_page";

$questions = $conn->query($query);

// Count total for pagination
$count_query = "SELECT COUNT(*) as total FROM questions WHERE 1=1";
if ($filter_subject > 0) {
    $count_query .= " AND subject_id = $filter_subject";
}
if (!empty($filter_difficulty)) {
    $count_query .= " AND difficulty = '$filter_difficulty'";
}
if ($filter_status >= 0) {
    $count_query .= " AND status = $filter_status";
}
$count_result = $conn->query($count_query);
$total_questions = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_questions / $per_page);

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM questions");
$stats['total'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) as total FROM questions WHERE status = 1");
$stats['active'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) as total FROM questions WHERE status = 0");
$stats['inactive'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT difficulty, COUNT(*) as count FROM questions GROUP BY difficulty");
$stats['by_difficulty'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['by_difficulty'][$row['difficulty']] = $row['count'];
}

$page_title = 'Manage Questions';
include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h1>Manage Questions</h1>
        <div style="display: flex; gap: 1rem;">
            <a href="questions.php?action=list" class="btn btn-primary">
                <i class="fas fa-list"></i> All Questions
            </a>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Add Question
            </button>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <!-- Statistics Cards -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;"><i class="fas fa-question-circle"></i></div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Active Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-ban"></i></div>
                <div class="stat-value"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Inactive Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;"><i class="fas fa-chart-line"></i></div>
                <div class="stat-value">
                    <?php echo $stats['by_difficulty']['easy'] ?? 0; ?> / 
                    <?php echo $stats['by_difficulty']['medium'] ?? 0; ?> / 
                    <?php echo $stats['by_difficulty']['hard'] ?? 0; ?>
                </div>
                <div class="stat-label">Easy / Medium / Hard</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card" style="margin-bottom: 2rem;">
            <form method="GET" action="questions.php" class="form-row" style="align-items: flex-end; gap: 1rem;">
                <input type="hidden" name="action" value="list">
                <div class="form-group" style="flex: 1;">
                    <label>Subject</label>
                    <select name="subject" class="form-control">
                        <option value="0">All Subjects</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $filter_subject == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Difficulty</label>
                    <select name="difficulty" class="form-control">
                        <option value="">All Difficulties</option>
                        <option value="easy" <?php echo $filter_difficulty == 'easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?php echo $filter_difficulty == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="hard" <?php echo $filter_difficulty == 'hard' ? 'selected' : ''; ?>>Hard</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="-1">All Status</option>
                        <option value="1" <?php echo $filter_status == 1 ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $filter_status == 0 ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="questions.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Questions Table -->
        <div class="card">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Question</th>
                            <th>Subject</th>
                            <th>Topic</th>
                            <th>Difficulty</th>
                            <th>Marks</th>
                            <th>Status</th>
                            <th style="width: 120px;">Actions</th>
                        </thead>
                    <tbody>
                        <?php if ($questions && $questions->num_rows > 0): ?>
                            <?php while ($q = $questions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $q['id']; ?></td>
                                <td style="max-width: 400px;">
                                    <strong><?php echo htmlspecialchars(substr($q['question_text'], 0, 80)); ?></strong>
                                    <?php if (strlen($q['question_text']) > 80): ?>...<?php endif; ?>
                                    <br><small style="color: var(--gray);">Options: <?php echo substr($q['option_a'], 0, 20); ?> | <?php echo substr($q['option_b'], 0, 20); ?> | ...</small>
                                </td>
                                <td><?php echo htmlspecialchars($q['subject_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($q['topic_name'] ?? '-'); ?></td>
                                <td><?php echo getDifficultyBadge($q['difficulty']); ?></td>
                                <td><?php echo $q['marks']; ?></td>
                                <td>
                                    <span class="badge <?php echo $q['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $q['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="questions.php?action=edit&id=<?php echo $q['id']; ?>" class="btn btn-sm btn-outline" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="showDeleteModal(<?php echo $q['id']; ?>, '<?php echo addslashes(substr($q['question_text'], 0, 50)); ?>')" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-question-circle" style="font-size: 2rem; color: var(--gray); margin-bottom: 0.5rem; display: block;"></i>
                                    No questions found.
                                    <br><a href="javascript:void(0)" onclick="openCreateModal()" class="btn btn-primary btn-sm" style="margin-top: 1rem;">Add Your First Question</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination" style="margin-top: 1.5rem;">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&subject=<?php echo $filter_subject; ?>&difficulty=<?php echo $filter_difficulty; ?>&status=<?php echo $filter_status; ?>" class="page-btn">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&subject=<?php echo $filter_subject; ?>&difficulty=<?php echo $filter_difficulty; ?>&status=<?php echo $filter_status; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&subject=<?php echo $filter_subject; ?>&difficulty=<?php echo $filter_difficulty; ?>&status=<?php echo $filter_status; ?>" class="page-btn">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'edit' && $edit_question): ?>
        <!-- Edit Question Form -->
        <div class="card" style="max-width: 800px; margin: 0 auto;">
            <h2 style="margin-bottom: 1.5rem;">Edit Question</h2>
            
            <form method="POST" action="questions.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="question_id" value="<?php echo $edit_question['id']; ?>">
                
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" id="edit_subject_id" class="form-control" required onchange="loadEditTopics()">
                        <option value="">Select Subject</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject['id'] == $edit_question['subject_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Topic (Optional)</label>
                    <select name="topic_id" id="edit_topic_id" class="form-control">
                        <option value="0">None</option>
                        <?php foreach ($topics as $topic): ?>
                        <option value="<?php echo $topic['id']; ?>" <?php echo $topic['id'] == $edit_question['topic_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($topic['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Question Text *</label>
                    <textarea name="question_text" class="form-control" rows="4" required><?php echo htmlspecialchars($edit_question['question_text']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Option A *</label>
                        <input type="text" name="option_a" class="form-control" value="<?php echo htmlspecialchars($edit_question['option_a']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Option B *</label>
                        <input type="text" name="option_b" class="form-control" value="<?php echo htmlspecialchars($edit_question['option_b']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Option C *</label>
                        <input type="text" name="option_c" class="form-control" value="<?php echo htmlspecialchars($edit_question['option_c']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Option D *</label>
                        <input type="text" name="option_d" class="form-control" value="<?php echo htmlspecialchars($edit_question['option_d']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Correct Option *</label>
                        <select name="correct_option" class="form-control" required>
                            <option value="">Select</option>
                            <option value="A" <?php echo $edit_question['correct_option'] == 'A' ? 'selected' : ''; ?>>A</option>
                            <option value="B" <?php echo $edit_question['correct_option'] == 'B' ? 'selected' : ''; ?>>B</option>
                            <option value="C" <?php echo $edit_question['correct_option'] == 'C' ? 'selected' : ''; ?>>C</option>
                            <option value="D" <?php echo $edit_question['correct_option'] == 'D' ? 'selected' : ''; ?>>D</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Difficulty</label>
                        <select name="difficulty" class="form-control">
                            <option value="easy" <?php echo $edit_question['difficulty'] == 'easy' ? 'selected' : ''; ?>>Easy</option>
                            <option value="medium" <?php echo $edit_question['difficulty'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="hard" <?php echo $edit_question['difficulty'] == 'hard' ? 'selected' : ''; ?>>Hard</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Marks</label>
                        <input type="number" name="marks" class="form-control" value="<?php echo $edit_question['marks']; ?>" step="0.5">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Explanation (Optional)</label>
                    <textarea name="explanation" class="form-control" rows="3"><?php echo htmlspecialchars($edit_question['explanation']); ?></textarea>
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="status" id="status" value="1" <?php echo $edit_question['status'] ? 'checked' : ''; ?>>
                    <label for="status">Active (visible to students)</label>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <a href="questions.php" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Question</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Create Question Modal -->
<div id="createModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2>Add New Question</h2>
            <button class="close-modal" onclick="closeCreateModal()">&times;</button>
        </div>
        <form method="POST" action="questions.php">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Subject *</label>
                <select name="subject_id" id="create_subject_id" class="form-control" required onchange="loadCreateTopics()">
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
                <select name="topic_id" id="create_topic_id" class="form-control">
                    <option value="0">None</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Question Text *</label>
                <textarea name="question_text" class="form-control" rows="4" required placeholder="Enter your question here..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Option A *</label>
                    <input type="text" name="option_a" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Option B *</label>
                    <input type="text" name="option_b" class="form-control" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Option C *</label>
                    <input type="text" name="option_c" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Option D *</label>
                    <input type="text" name="option_d" class="form-control" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Correct Option *</label>
                    <select name="correct_option" class="form-control" required>
                        <option value="">Select</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Difficulty</label>
                    <select name="difficulty" class="form-control">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Marks</label>
                    <input type="number" name="marks" class="form-control" value="1" step="0.5">
                </div>
            </div>
            
            <div class="form-group">
                <label>Explanation (Optional)</label>
                <textarea name="explanation" class="form-control" rows="3" placeholder="Explain why the correct answer is right..."></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="status" id="create_status" value="1" checked>
                <label for="create_status">Active (visible to students)</label>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Question</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Delete Question</h2>
            <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form method="POST" action="questions.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="question_id" id="delete_question_id">
            <div class="modal-body">
                <p>Are you sure you want to delete this question?</p>
                <p id="delete_question_text" style="background: #f8fafc; padding: 0.75rem; border-radius: 8px; margin-top: 0.5rem; font-style: italic;"></p>
                <p style="color: #ef4444; margin-top: 1rem; padding: 0.75rem; background: #fef2f2; border-radius: 8px;">
                    <i class="fas fa-exclamation-triangle"></i> This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Question</button>
            </div>
        </form>
    </div>
</div>

<style>
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
    z-index: 1000;
}
.modal-content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    max-width: 700px;
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
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}
.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
}
.close-modal:hover {
    color: #1e293b;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 1rem 0;
}
.checkbox-group input {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}
.btn-danger {
    background: #ef4444;
    color: white;
}
.btn-danger:hover {
    background: #dc2626;
}
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
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
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.2rem;
    border: 1px solid var(--border);
    text-align: center;
}
.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-label {
    font-size: 0.75rem;
    color: var(--gray);
}
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}
.page-btn {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: white;
    color: var(--gray);
    text-decoration: none;
    transition: all 0.3s;
}
.page-btn:hover, .page-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
.btn-outline {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--gray);
}
.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}
</style>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
    document.getElementById('create_subject_id').value = '';
    document.getElementById('create_topic_id').innerHTML = '<option value="0">None</option>';
    document.getElementById('create_status').checked = true;
}

function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}

function showDeleteModal(id, text) {
    document.getElementById('delete_question_id').value = id;
    document.getElementById('delete_question_text').innerHTML = text;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function loadCreateTopics() {
    const subjectId = document.getElementById('create_subject_id').value;
    const topicSelect = document.getElementById('create_topic_id');
    
    if (subjectId) {
        fetch(`/online_exam_system/api/get-topics.php?subject_id=${subjectId}`)
            .then(response => response.json())
            .then(data => {
                topicSelect.innerHTML = '<option value="0">None</option>';
                data.forEach(topic => {
                    topicSelect.innerHTML += `<option value="${topic.id}">${topic.name}</option>`;
                });
            })
            .catch(error => console.error('Error loading topics:', error));
    } else {
        topicSelect.innerHTML = '<option value="0">None</option>';
    }
}

function loadEditTopics() {
    const subjectId = document.getElementById('edit_subject_id').value;
    const topicSelect = document.getElementById('edit_topic_id');
    const currentTopicId = '<?php echo $edit_question['topic_id'] ?? 0; ?>';
    
    if (subjectId) {
        fetch(`/online_exam_system/api/get-topics.php?subject_id=${subjectId}`)
            .then(response => response.json())
            .then(data => {
                topicSelect.innerHTML = '<option value="0">None</option>';
                data.forEach(topic => {
                    const selected = (topic.id == currentTopicId) ? 'selected' : '';
                    topicSelect.innerHTML += `<option value="${topic.id}" ${selected}>${topic.name}</option>`;
                });
            })
            .catch(error => console.error('Error loading topics:', error));
    } else {
        topicSelect.innerHTML = '<option value="0">None</option>';
    }
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.opacity = '0';
        alert.style.transition = 'opacity 0.5s';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);
</script>

<?php include '../includes/footer.php'; ?>