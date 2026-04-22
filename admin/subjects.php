<?php
/**
 * Subjects Management Page
 */

session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_subject') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $code = trim($_POST['code']);
        
        if (empty($name)) {
            $error = "Subject name is required";
        } else {
            $stmt = $conn->prepare("INSERT INTO subjects (name, description, code) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $description, $code);
            if ($stmt->execute()) {
                $message = "Subject added successfully!";
                logActivity($_SESSION['user_id'], 'add_subject', "Added subject: $name");
            } else {
                $error = "Failed to add subject: " . $conn->error;
            }
        }
    }
    
    // Add Topic
    elseif ($action === 'add_topic') {
        $subject_id = (int)$_POST['subject_id'];
        $topic_name = trim($_POST['topic_name']);
        $topic_description = trim($_POST['topic_description']);
        
        if (empty($topic_name)) {
            $error = "Topic name is required";
        } else {
            $stmt = $conn->prepare("INSERT INTO topics (subject_id, name, description) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $subject_id, $topic_name, $topic_description);
            if ($stmt->execute()) {
                $message = "Topic added successfully!";
                logActivity($_SESSION['user_id'], 'add_topic', "Added topic: $topic_name");
            } else {
                $error = "Failed to add topic: " . $conn->error;
            }
        }
    }
    
    // Edit Subject
    elseif ($action === 'edit_subject') {
        $subject_id = (int)$_POST['subject_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $code = trim($_POST['code']);
        
        $stmt = $conn->prepare("UPDATE subjects SET name = ?, description = ?, code = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $description, $code, $subject_id);
        if ($stmt->execute()) {
            $message = "Subject updated successfully!";
            logActivity($_SESSION['user_id'], 'edit_subject', "Updated subject ID: $subject_id");
        } else {
            $error = "Failed to update subject";
        }
    }
    
    // Delete Subject
    elseif ($action === 'delete_subject') {
        $subject_id = (int)$_POST['subject_id'];
        
        // Check if subject has exams
        $check = $conn->query("SELECT COUNT(*) as count FROM exams WHERE subject_id = $subject_id");
        $has_exams = $check->fetch_assoc()['count'] > 0;
        
        if ($has_exams) {
            $error = "Cannot delete subject with existing exams. Delete the exams first.";
        } else {
            $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $subject_id);
            if ($stmt->execute()) {
                $message = "Subject deleted successfully!";
                logActivity($_SESSION['user_id'], 'delete_subject', "Deleted subject ID: $subject_id");
            } else {
                $error = "Failed to delete subject";
            }
        }
    }
}

// Get all subjects
$subjects = $conn->query("SELECT * FROM subjects ORDER BY name");

$page_title = 'Manage Subjects';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-book"></i> Manage Subjects & Topics</h1>
        <p>Add, edit, or remove subjects and their topics</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="two-columns">
        <!-- Add Subject Form -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Add New Subject</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_subject">
                
                <div class="form-group">
                    <label>Subject Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Mathematics, English, Science">
                </div>
                
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g., MATH101, ENG201">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the subject"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Subject</button>
            </form>
        </div>
        
        <!-- Add Topic Form -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Add New Topic</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_topic">
                
                <div class="form-group">
                    <label>Select Subject *</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">-- Select Subject --</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Topic Name *</label>
                    <input type="text" name="topic_name" class="form-control" required placeholder="e.g., Algebra, Grammar, Cells">
                </div>
                
                <div class="form-group">
                    <label>Topic Description</label>
                    <textarea name="topic_description" class="form-control" rows="2" placeholder="Brief description of the topic"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Topic</button>
            </form>
        </div>
    </div>
    
    <!-- Subjects List -->
    <div class="card">
        <h2><i class="fas fa-list"></i> Subjects & Topics</h2>
        
        <?php 
        $subjects->data_seek(0);
        while ($subject = $subjects->fetch_assoc()): 
            // Get topics for this subject
            $topics = $conn->query("SELECT * FROM topics WHERE subject_id = {$subject['id']} ORDER BY name");
        ?>
        <div class="subject-item">
            <div class="subject-header">
                <div>
                    <strong><?php echo htmlspecialchars($subject['name']); ?></strong>
                    <?php if ($subject['code']): ?>
                        <span class="subject-code">(<?php echo htmlspecialchars($subject['code']); ?>)</span>
                    <?php endif; ?>
                    <?php if ($subject['description']): ?>
                        <div class="subject-description"><?php echo htmlspecialchars($subject['description']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="subject-actions">
                    <button onclick="editSubject(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['name']); ?>', '<?php echo htmlspecialchars($subject['code']); ?>', '<?php echo htmlspecialchars(addslashes($subject['description'])); ?>')" class="btn-icon">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteSubject(<?php echo $subject['id']; ?>)" class="btn-icon delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            
            <?php if ($topics->num_rows > 0): ?>
                <div class="topics-list">
                    <div class="topics-title">Topics:</div>
                    <div class="topics-grid">
                        <?php while ($topic = $topics->fetch_assoc()): ?>
                            <div class="topic-tag">
                                <?php echo htmlspecialchars($topic['name']); ?>
                                <?php if ($topic['description']): ?>
                                    <span class="topic-desc" title="<?php echo htmlspecialchars($topic['description']); ?>">
                                        <i class="fas fa-info-circle"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-topics">No topics added yet</div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
        
        <?php if ($subjects->num_rows == 0): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>No subjects added yet. Click "Add New Subject" to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Subject Modal -->
<div id="editModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Subject</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_subject">
            <input type="hidden" name="subject_id" id="edit_subject_id">
            
            <div class="form-group">
                <label>Subject Name *</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>Subject Code</label>
                <input type="text" name="code" id="edit_code" class="form-control">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
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

.two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
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

.btn-icon {
    background: none;
    border: none;
    padding: 8px;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s;
}

.btn-icon i {
    font-size: 1rem;
    color: #64748b;
}

.btn-icon:hover {
    background: #f1f5f9;
}

.btn-icon.delete:hover {
    background: #fef2f2;
}

.btn-icon.delete:hover i {
    color: #ef4444;
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

.subject-item {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.subject-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.subject-header strong {
    font-size: 1.1rem;
    color: #1e293b;
}

.subject-code {
    color: #10b981;
    font-size: 0.8rem;
    margin-left: 8px;
}

.subject-description {
    color: #64748b;
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.topics-list {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

.topics-title {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.topics-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.topic-tag {
    background: white;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.8rem;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.topic-desc {
    color: #10b981;
    cursor: help;
}

.no-topics {
    color: #94a3b8;
    font-size: 0.8rem;
    font-style: italic;
    margin-top: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #94a3b8;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

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
    max-width: 500px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 1.2rem;
    color: #1e293b;
    margin: 0;
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

.form-actions {
    padding: 1.5rem;
    border-top: 1px solid #eef2f6;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.btn-cancel {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
}

.btn-cancel:hover {
    border-color: #ef4444;
    color: #ef4444;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .two-columns {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function editSubject(id, name, code, description) {
    document.getElementById('edit_subject_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_description').value = description;
    document.getElementById('editModal').style.display = 'flex';
}

function deleteSubject(id) {
    if (confirm('Are you sure you want to delete this subject? This will also delete all associated topics.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_subject">
            <input type="hidden" name="subject_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php include '../includes/footer.php'; ?>