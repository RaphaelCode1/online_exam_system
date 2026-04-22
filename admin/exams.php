<?php
/**
 * Admin Exams Management Page
 * Manage all exams (view, add, edit, delete, duplicate, manage questions)
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canViewExams()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Filters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_subject = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$filter_search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Handle Delete Action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['exam_id'])) {
    $delete_id = (int)$_POST['exam_id'];
    
    // Check if exam has attempts
    $check = $conn->query("SELECT COUNT(*) as count FROM exam_attempts WHERE exam_id = $delete_id");
    $attempt_count = $check->fetch_assoc()['count'];
    
    if ($attempt_count > 0) {
        $error = "Cannot delete exam that has been attempted by students. Consider archiving it instead.";
    } else {
        $conn->begin_transaction();
        try {
            // Delete exam questions
            $conn->query("DELETE FROM exam_questions WHERE exam_id = $delete_id");
            // Delete exam attempts
            $conn->query("DELETE FROM exam_attempts WHERE exam_id = $delete_id");
            // Delete exam
            $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $message = "Exam deleted successfully!";
            logActivity($_SESSION['user_id'], 'delete_exam', "Deleted exam ID: $delete_id");
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error deleting exam: " . $e->getMessage();
        }
    }
}

// Handle Status Toggle
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status' && isset($_POST['exam_id'])) {
    $toggle_id = (int)$_POST['exam_id'];
    $current_status = $_POST['current_status'];
    $new_status = ($current_status == 'published') ? 'draft' : 'published';
    
    $stmt = $conn->prepare("UPDATE exams SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $toggle_id);
    
    if ($stmt->execute()) {
        $message = "Exam status updated successfully!";
        logActivity($_SESSION['user_id'], 'toggle_exam_status', "Toggled exam ID: $toggle_id to $new_status");
    } else {
        $error = "Error updating status";
    }
    $stmt->close();
}

// Handle Duplicate Exam
if (isset($_POST['action']) && $_POST['action'] === 'duplicate' && isset($_POST['exam_id'])) {
    $duplicate_id = (int)$_POST['exam_id'];
    
    // Get original exam
    $original = $conn->query("SELECT * FROM exams WHERE id = $duplicate_id")->fetch_assoc();
    
    if ($original) {
        $new_title = $original['title'] . " (Copy)";
        $new_description = $original['description'] . " (Duplicated on " . date('Y-m-d H:i:s') . ")";
        
        $conn->begin_transaction();
        try {
            // Insert new exam
            $stmt = $conn->prepare("INSERT INTO exams (title, description, subject_id, duration_minutes, passing_score, total_questions, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, NOW())");
            $stmt->bind_param("ssiiiii", $new_title, $new_description, $original['subject_id'], $original['duration_minutes'], $original['passing_score'], $original['total_questions'], $_SESSION['user_id']);
            $stmt->execute();
            $new_exam_id = $stmt->insert_id;
            $stmt->close();
            
            // Copy exam questions
            $questions = $conn->query("SELECT question_id, question_order FROM exam_questions WHERE exam_id = $duplicate_id");
            $q_stmt = $conn->prepare("INSERT INTO exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)");
            while ($q = $questions->fetch_assoc()) {
                $q_stmt->bind_param("iii", $new_exam_id, $q['question_id'], $q['question_order']);
                $q_stmt->execute();
            }
            $q_stmt->close();
            
            $conn->commit();
            $message = "Exam duplicated successfully! New exam is in draft mode.";
            logActivity($_SESSION['user_id'], 'duplicate_exam', "Duplicated exam ID: $duplicate_id to new exam ID: $new_exam_id");
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error duplicating exam: " . $e->getMessage();
        }
    }
}

// Handle Create Action
if (isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $subject_id = (int)$_POST['subject_id'];
    $duration = (int)$_POST['duration'];
    $passing_score = (int)$_POST['passing_score'];
    $status = $_POST['status'];
    $total_questions = 0;
    
    $stmt = $conn->prepare("INSERT INTO exams (title, description, subject_id, duration_minutes, passing_score, total_questions, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssiiiiis", $title, $description, $subject_id, $duration, $passing_score, $total_questions, $status, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $new_exam_id = $stmt->insert_id;
        $message = "Exam created successfully!";
        logActivity($_SESSION['user_id'], 'create_exam', "Created exam ID: $new_exam_id");
        
        // Redirect to add questions
        header("Location: assign-questions.php?exam_id=$new_exam_id");
        exit();
    } else {
        $error = "Error creating exam: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Update Action
if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['exam_id'])) {
    $update_id = (int)$_POST['exam_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $subject_id = (int)$_POST['subject_id'];
    $duration = (int)$_POST['duration'];
    $passing_score = (int)$_POST['passing_score'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE exams SET title = ?, description = ?, subject_id = ?, duration_minutes = ?, passing_score = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssiiisi", $title, $description, $subject_id, $duration, $passing_score, $status, $update_id);
    
    if ($stmt->execute()) {
        $message = "Exam updated successfully!";
        logActivity($_SESSION['user_id'], 'update_exam', "Updated exam ID: $update_id");
        $action = 'list';
    } else {
        $error = "Error updating exam: " . $stmt->error;
    }
    $stmt->close();
}

// Get exam data for editing
$edit_exam = null;
if ($action === 'edit' && $exam_id > 0) {
    $edit_exam = $conn->query("SELECT * FROM exams WHERE id = $exam_id")->fetch_assoc();
    if (!$edit_exam) {
        $action = 'list';
        $error = "Exam not found";
    }
}

// Get subjects for dropdown
$subjects = $conn->query("SELECT * FROM subjects WHERE status = 1 ORDER BY name");

// Build query for exams list
$query = "SELECT e.*, s.name as subject_name, 
          (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as question_count,
          (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND status = 'completed') as attempt_count,
          (SELECT AVG(percentage) FROM exam_attempts WHERE exam_id = e.id AND status = 'completed') as avg_score
          FROM exams e 
          LEFT JOIN subjects s ON e.subject_id = s.id 
          WHERE 1=1";

if (!empty($filter_status)) {
    $query .= " AND e.status = '$filter_status'";
}
if ($filter_subject > 0) {
    $query .= " AND e.subject_id = $filter_subject";
}
if (!empty($filter_search)) {
    $query .= " AND (e.title LIKE '%$filter_search%' OR e.description LIKE '%$filter_search%')";
}

$query .= " ORDER BY e.created_at DESC LIMIT $offset, $per_page";

$exams = $conn->query($query);

// Count total for pagination
$count_query = "SELECT COUNT(*) as total FROM exams e WHERE 1=1";
if (!empty($filter_status)) {
    $count_query .= " AND e.status = '$filter_status'";
}
if ($filter_subject > 0) {
    $count_query .= " AND e.subject_id = $filter_subject";
}
if (!empty($filter_search)) {
    $count_query .= " AND (e.title LIKE '%$filter_search%' OR e.description LIKE '%$filter_search%')";
}
$count_result = $conn->query($count_query);
$total_exams = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_exams / $per_page);

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM exams");
$stats['total'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) as total FROM exams WHERE status = 'published'");
$stats['published'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) as total FROM exams WHERE status = 'draft'");
$stats['draft'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) as total FROM exams WHERE status = 'archived'");
$stats['archived'] = $result->fetch_assoc()['total'];

$page_title = 'Manage Exams';
include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h1>Manage Exams</h1>
        <div style="display: flex; gap: 1rem;">
            <a href="exams.php?action=list" class="btn btn-primary">
                <i class="fas fa-list"></i> All Exams
            </a>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Create Exam
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
                <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;"><i class="fas fa-file-alt"></i></div>
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Exams</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-value"><?php echo $stats['published']; ?></div>
                <div class="stat-label">Published</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;"><i class="fas fa-edit"></i></div>
                <div class="stat-value"><?php echo $stats['draft']; ?></div>
                <div class="stat-label">Draft</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-archive"></i></div>
                <div class="stat-value"><?php echo $stats['archived']; ?></div>
                <div class="stat-label">Archived</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card" style="margin-bottom: 2rem;">
            <form method="GET" action="exams.php" class="form-row" style="align-items: flex-end; gap: 1rem; flex-wrap: wrap;">
                <input type="hidden" name="action" value="list">
                <div class="form-group" style="flex: 2;">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by title or description..." value="<?php echo htmlspecialchars($filter_search); ?>">
                </div>
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
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="published" <?php echo $filter_status == 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $filter_status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="archived" <?php echo $filter_status == 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="exams.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Exams Table -->
        <div class="card">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th>Exam Title</th>
                            <th>Subject</th>
                            <th>Duration</th>
                            <th>Passing</th>
                            <th>Questions</th>
                            <th>Attempts</th>
                            <th>Avg Score</th>
                            <th>Status</th>
                            <th style="width: 180px;">Actions</th>
                        </thead>
                    <tbody>
                        <?php if ($exams && $exams->num_rows > 0): ?>
                            <?php while ($exam = $exams->fetch_assoc()): ?>
                            <tr class="exam-row" data-id="<?php echo $exam['id']; ?>">
                                <td><?php echo $exam['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($exam['title']); ?></strong>
                                    <br><small style="color: var(--gray);"><?php echo htmlspecialchars(substr($exam['description'] ?? '', 0, 50)); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($exam['subject_name'] ?? '-'); ?></td>
                                <td><?php echo $exam['duration_minutes']; ?> min</td>
                                <td><?php echo $exam['passing_score']; ?>%</td>
                                <td>
                                    <a href="assign-questions.php?exam_id=<?php echo $exam['id']; ?>" class="badge bg-info" style="text-decoration: none;">
                                        <?php echo $exam['question_count']; ?> questions
                                    </a>
                                </td>
                                <td><?php echo $exam['attempt_count']; ?></td>
                                <td>
                                    <?php 
                                    $avg = round($exam['avg_score'] ?? 0, 1);
                                    $badge_class = $avg >= 70 ? 'bg-success' : ($avg >= 50 ? 'bg-warning' : 'bg-danger');
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $avg; ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $exam['status'] == 'published' ? 'bg-success' : ($exam['status'] == 'draft' ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo ucfirst($exam['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                        <a href="exams.php?action=edit&id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-outline" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="assign-questions.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-outline" title="Manage Questions">
                                            <i class="fas fa-question-circle"></i>
                                        </a>
                                        <button onclick="toggleStatus(<?php echo $exam['id']; ?>, '<?php echo $exam['status']; ?>')" class="btn btn-sm btn-outline" title="<?php echo $exam['status'] == 'published' ? 'Unpublish' : 'Publish'; ?>">
                                            <i class="fas fa-<?php echo $exam['status'] == 'published' ? 'eye-slash' : 'eye'; ?>"></i>
                                        </button>
                                        <button onclick="duplicateExam(<?php echo $exam['id']; ?>, '<?php echo addslashes($exam['title']); ?>')" class="btn btn-sm btn-outline" title="Duplicate">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button onclick="showDeleteModal(<?php echo $exam['id']; ?>, '<?php echo addslashes($exam['title']); ?>', <?php echo $exam['attempt_count']; ?>)" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-file-alt" style="font-size: 2rem; color: var(--gray); margin-bottom: 0.5rem; display: block;"></i>
                                    No exams found.
                                    <br><a href="javascript:void(0)" onclick="openCreateModal()" class="btn btn-primary btn-sm" style="margin-top: 1rem;">Create Your First Exam</a>
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
                <a href="?page=<?php echo $page-1; ?>&status=<?php echo $filter_status; ?>&subject=<?php echo $filter_subject; ?>&search=<?php echo urlencode($filter_search); ?>" class="page-btn">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&subject=<?php echo $filter_subject; ?>&search=<?php echo urlencode($filter_search); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&status=<?php echo $filter_status; ?>&subject=<?php echo $filter_subject; ?>&search=<?php echo urlencode($filter_search); ?>" class="page-btn">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'edit' && $edit_exam): ?>
        <!-- Edit Exam Form -->
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h2 style="margin-bottom: 1.5rem;">Edit Exam</h2>
            
            <form method="POST" action="exams.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="exam_id" value="<?php echo $edit_exam['id']; ?>">
                
                <div class="form-group">
                    <label>Exam Title *</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_exam['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($edit_exam['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">Select Subject</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject['id'] == $edit_exam['subject_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Duration (minutes) *</label>
                        <input type="number" name="duration" class="form-control" value="<?php echo $edit_exam['duration_minutes']; ?>" required min="1">
                    </div>
                    <div class="form-group">
                        <label>Passing Score (%) *</label>
                        <input type="number" name="passing_score" class="form-control" value="<?php echo $edit_exam['passing_score']; ?>" required min="0" max="100">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="draft" <?php echo $edit_exam['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $edit_exam['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo $edit_exam['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="info-box" style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                    <i class="fas fa-info-circle"></i>
                    <strong>Exam Statistics:</strong><br>
                    Questions: <?php echo $conn->query("SELECT COUNT(*) as total FROM exam_questions WHERE exam_id = {$edit_exam['id']}")->fetch_assoc()['total']; ?><br>
                    Attempts: <?php echo $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE exam_id = {$edit_exam['id']}")->fetch_assoc()['total']; ?>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <a href="exams.php" class="btn btn-outline">Cancel</a>
                    <a href="assign-questions.php?exam_id=<?php echo $edit_exam['id']; ?>" class="btn btn-secondary">Manage Questions</a>
                    <button type="submit" class="btn btn-primary">Update Exam</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Create Exam Modal -->
<div id="createModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Create New Exam</h2>
            <button class="close-modal" onclick="closeCreateModal()">&times;</button>
        </div>
        <form method="POST" action="exams.php">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Exam Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g., JavaScript Fundamentals">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Describe what this exam covers..."></textarea>
            </div>
            
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
            
            <div class="form-row">
                <div class="form-group">
                    <label>Duration (minutes) *</label>
                    <input type="number" name="duration" class="form-control" value="60" required min="1">
                </div>
                <div class="form-group">
                    <label>Passing Score (%) *</label>
                    <input type="number" name="passing_score" class="form-control" value="70" required min="0" max="100">
                </div>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="draft">Draft (Not visible to students)</option>
                    <option value="published">Published (Visible to students)</option>
                </select>
            </div>
            
            <div class="info-box" style="background: #e0f2fe; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                <i class="fas fa-info-circle"></i>
                <strong>Next Step:</strong> After creating the exam, you'll be redirected to add questions.
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create & Continue</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Delete Exam</h2>
            <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form method="POST" action="exams.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="exam_id" id="delete_exam_id">
            <div class="modal-body">
                <p>Are you sure you want to delete the exam: <strong id="delete_exam_title"></strong>?</p>
                <p id="delete_attempt_warning" style="color: #ef4444; margin-top: 0.5rem;"></p>
                <p style="color: #ef4444; margin-top: 1rem; padding: 0.75rem; background: #fef2f2; border-radius: 8px;">
                    <i class="fas fa-exclamation-triangle"></i> This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Exam</button>
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
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
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
.btn-secondary {
    background: #64748b;
    color: white;
}
.btn-secondary:hover {
    background: #475569;
}
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}
.bg-success {
    background: #ecfdf5;
    color: #10b981;
}
.bg-warning {
    background: #fef3c7;
    color: #d97706;
}
.bg-danger {
    background: #fef2f2;
    color: #ef4444;
}
.bg-info {
    background: #e0f2fe;
    color: #0284c7;
}
.info-box {
    background: #e0f2fe;
    padding: 1rem;
    border-radius: 8px;
    font-size: 0.9rem;
}
</style>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}

function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}

function showDeleteModal(id, title, attemptCount) {
    document.getElementById('delete_exam_id').value = id;
    document.getElementById('delete_exam_title').innerHTML = title;
    const warning = document.getElementById('delete_attempt_warning');
    if (attemptCount > 0) {
        warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> This exam has been attempted by ${attemptCount} student(s). It cannot be deleted.`;
        document.querySelector('#deleteModal .btn-danger').disabled = true;
        document.querySelector('#deleteModal .btn-danger').style.opacity = '0.5';
        document.querySelector('#deleteModal .btn-danger').style.cursor = 'not-allowed';
    } else {
        warning.innerHTML = '';
        document.querySelector('#deleteModal .btn-danger').disabled = false;
        document.querySelector('#deleteModal .btn-danger').style.opacity = '1';
        document.querySelector('#deleteModal .btn-danger').style.cursor = 'pointer';
    }
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

function toggleStatus(id, currentStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="toggle_status">' +
                    '<input type="hidden" name="exam_id" value="' + id + '">' +
                    '<input type="hidden" name="current_status" value="' + currentStatus + '">';
    document.body.appendChild(form);
    form.submit();
}

function duplicateExam(id, title) {
    if (confirm(`Duplicate exam "${title}"? This will create a copy in draft mode.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="duplicate">' +
                        '<input type="hidden" name="exam_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
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