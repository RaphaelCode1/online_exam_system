<?php
/**
 * Study Materials Management - Enhanced Version
 * With categories, search, filters, and preview functionality
 */

session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/gemini.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    
    $stmt = $conn->prepare("SELECT file_path FROM study_materials WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();
    
    if ($material && file_exists($material['file_path'])) {
        unlink($material['file_path']);
    }
    
    $stmt = $conn->prepare("DELETE FROM study_materials WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $_SESSION['success'] = "Study material deleted successfully!";
    header('Location: study-materials.php');
    exit();
}

// Handle AI Generate Summary
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_summary') {
    $topic = trim($_POST['topic']);
    $title = trim($_POST['title']);
    $subject_id = !empty($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
    
    $summary = generateStudySummary($topic);
    
    if ($summary) {
        echo json_encode(['success' => true, 'summary' => $summary]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to generate summary']);
    }
    exit();
}

// Handle add/edit
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_data = null;

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM study_materials WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_data = $result->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_material') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject_id = isset($_POST['subject_id']) && $_POST['subject_id'] != '' ? (int)$_POST['subject_id'] : null;
    $material_type = $_POST['material_type'] ?? 'pdf';
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $difficulty = $_POST['difficulty'] ?? 'beginner';
    $duration = (int)($_POST['duration'] ?? 0);
    $tags = trim($_POST['tags'] ?? '');
    
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if ($subject_id !== null) {
        $check_subject = $conn->prepare("SELECT id FROM subjects WHERE id = ?");
        $check_subject->bind_param("i", $subject_id);
        $check_subject->execute();
        if ($check_subject->get_result()->num_rows === 0) {
            $errors[] = "Selected subject does not exist.";
            $subject_id = null;
        }
    }
    
    // Handle file upload
    $file_path = $edit_data['file_path'] ?? '';
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/study_materials/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'avi', 'mkv', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowed_ext);
        } else {
            $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['file']['name']);
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
                // Delete old file if exists
                if ($edit_data && $edit_data['file_path'] && file_exists($edit_data['file_path'])) {
                    unlink($edit_data['file_path']);
                }
                $file_path = $target_file;
            } else {
                $errors[] = "Failed to upload file";
            }
        }
    } elseif (empty($file_path) && $edit_id == 0) {
        $errors[] = "File is required";
    }
    
    if (empty($errors)) {
        if ($edit_id > 0) {
            $stmt = $conn->prepare("UPDATE study_materials SET title = ?, description = ?, subject_id = ?, material_type = ?, file_path = ?, is_published = ?, difficulty = ?, duration = ?, tags = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssississsi", $title, $description, $subject_id, $material_type, $file_path, $is_published, $difficulty, $duration, $tags, $edit_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO study_materials (title, description, subject_id, material_type, file_path, is_published, difficulty, duration, tags, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("ssississs", $title, $description, $subject_id, $material_type, $file_path, $is_published, $difficulty, $duration, $tags);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Study material " . ($edit_id ? "updated" : "added") . " successfully!";
            header('Location: study-materials.php');
            exit();
        } else {
            $error_msg = "Database error: " . $conn->error;
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$subject_filter = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "
    SELECT sm.*, s.name as subject_name 
    FROM study_materials sm 
    LEFT JOIN subjects s ON sm.subject_id = s.id 
    WHERE 1=1
";

if ($search) {
    $query .= " AND (sm.title LIKE '%$search%' OR sm.description LIKE '%$search%' OR sm.tags LIKE '%$search%')";
}
if ($subject_filter > 0) {
    $query .= " AND sm.subject_id = $subject_filter";
}
if ($type_filter) {
    $query .= " AND sm.material_type = '$type_filter'";
}
if ($status_filter !== '') {
    $query .= " AND sm.is_published = " . ($status_filter == 'published' ? 1 : 0);
}

$query .= " ORDER BY sm.created_at DESC";

$materials = $conn->query($query);

// Get statistics
$total_materials = $conn->query("SELECT COUNT(*) as total FROM study_materials")->fetch_assoc()['total'];
$published_materials = $conn->query("SELECT COUNT(*) as total FROM study_materials WHERE is_published = 1")->fetch_assoc()['total'];
$draft_materials = $conn->query("SELECT COUNT(*) as total FROM study_materials WHERE is_published = 0")->fetch_assoc()['total'];

// Get all subjects for dropdown
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name");

$page_title = 'Study Materials';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-book-open"></i> Study Materials Library</h1>
            <p>Manage learning resources for students</p>
        </div>
        <div class="header-actions">
            <button class="btn-primary" onclick="openAIModal()">
                <i class="fas fa-robot"></i> AI Generate
            </button>
            <button class="btn-primary" onclick="document.getElementById('addMaterialModal').style.display='flex'">
                <i class="fas fa-plus"></i> Add New
            </button>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-row">
        <div class="stat-mini-card">
            <div class="stat-mini-value"><?php echo $total_materials; ?></div>
            <div class="stat-mini-label">Total Materials</div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-value" style="color: #10b981;"><?php echo $published_materials; ?></div>
            <div class="stat-mini-label">Published</div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-value" style="color: #f59e0b;"><?php echo $draft_materials; ?></div>
            <div class="stat-mini-label">Drafts</div>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_msg)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by title, description, tags..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <i class="fas fa-book"></i>
                <select name="subject">
                    <option value="0">All Subjects</option>
                    <?php 
                    $subjects->data_seek(0);
                    while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subject['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <i class="fas fa-tag"></i>
                <select name="type">
                    <option value="">All Types</option>
                    <option value="pdf" <?php echo $type_filter == 'pdf' ? 'selected' : ''; ?>>PDF</option>
                    <option value="video" <?php echo $type_filter == 'video' ? 'selected' : ''; ?>>Video</option>
                    <option value="document" <?php echo $type_filter == 'document' ? 'selected' : ''; ?>>Document</option>
                    <option value="presentation" <?php echo $type_filter == 'presentation' ? 'selected' : ''; ?>>Presentation</option>
                </select>
            </div>
            <div class="filter-group">
                <i class="fas fa-eye"></i>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="published" <?php echo $status_filter == 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                </select>
            </div>
            <button type="submit" class="btn-filter">Apply Filters</button>
            <?php if ($search || $subject_filter || $type_filter || $status_filter): ?>
                <a href="study-materials.php" class="btn-clear">Clear All</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Materials Grid -->
    <div class="materials-grid">
        <?php if ($materials->num_rows > 0): ?>
            <?php while ($material = $materials->fetch_assoc()): ?>
                <div class="material-card <?php echo !$material['is_published'] ? 'draft-mode' : ''; ?>">
                    <div class="material-icon">
                        <?php
                        $icon = 'fa-file-alt';
                        if ($material['material_type'] == 'pdf') $icon = 'fa-file-pdf';
                        elseif ($material['material_type'] == 'video') $icon = 'fa-video';
                        elseif ($material['material_type'] == 'document') $icon = 'fa-file-word';
                        elseif ($material['material_type'] == 'presentation') $icon = 'fa-file-powerpoint';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="material-content">
                        <div class="material-header">
                            <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                            <?php if ($material['difficulty']): ?>
                                <span class="difficulty-badge difficulty-<?php echo $material['difficulty']; ?>">
                                    <?php echo ucfirst($material['difficulty']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="material-description"><?php echo htmlspecialchars(substr($material['description'] ?? '', 0, 120)); ?></p>
                        
                        <?php if ($material['tags']): ?>
                            <div class="material-tags">
                                <?php foreach (explode(',', $material['tags']) as $tag): ?>
                                    <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="material-meta">
                            <span class="subject-badge">
                                <i class="fas fa-book"></i>
                                <?php echo htmlspecialchars($material['subject_name'] ?? 'General'); ?>
                            </span>
                            <span class="type-badge">
                                <i class="fas fa-tag"></i>
                                <?php echo strtoupper($material['material_type']); ?>
                            </span>
                            <?php if ($material['duration'] > 0): ?>
                                <span class="duration-badge">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $material['duration']; ?> min
                                </span>
                            <?php endif; ?>
                            <span class="date-badge">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('M d, Y', strtotime($material['created_at'])); ?>
                            </span>
                            <span class="downloads-badge">
                                <i class="fas fa-download"></i>
                                <?php echo number_format($material['download_count'] ?? 0); ?> downloads
                            </span>
                            <?php if ($material['is_published']): ?>
                                <span class="status-badge published">
                                    <i class="fas fa-eye"></i> Published
                                </span>
                            <?php else: ?>
                                <span class="status-badge draft">
                                    <i class="fas fa-eye-slash"></i> Draft
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="material-actions">
                            <a href="<?php echo $material['file_path']; ?>" class="btn-sm btn-outline" target="_blank" onclick="incrementDownload(<?php echo $material['id']; ?>)">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <button onclick="previewMaterial(<?php echo $material['id']; ?>)" class="btn-sm btn-outline">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <button onclick="editMaterial(<?php echo $material['id']; ?>)" class="btn-sm btn-outline">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="deleteMaterial(<?php echo $material['id']; ?>)" class="btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No Study Materials Found</h3>
                <p>Click "Add New Material" to upload study materials or adjust your filters.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="addMaterialModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php echo $edit_data ? 'Edit Material' : 'Add New Material'; ?></h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <form method="POST" action="study-materials.php<?php echo $edit_data ? '?edit=' . $edit_data['id'] : ''; ?>" enctype="multipart/form-data" id="materialForm">
            <input type="hidden" name="action" value="save_material">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_data['title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject_id" class="form-control">
                        <option value="">-- General Material --</option>
                        <?php 
                        $subjects->data_seek(0);
                        while ($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo (isset($edit_data['subject_id']) && $edit_data['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($edit_data['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Material Type</label>
                    <select name="material_type" class="form-control">
                        <option value="pdf" <?php echo (isset($edit_data['material_type']) && $edit_data['material_type'] == 'pdf') ? 'selected' : ''; ?>>PDF Document</option>
                        <option value="video" <?php echo (isset($edit_data['material_type']) && $edit_data['material_type'] == 'video') ? 'selected' : ''; ?>>Video</option>
                        <option value="document" <?php echo (isset($edit_data['material_type']) && $edit_data['material_type'] == 'document') ? 'selected' : ''; ?>>Word Document</option>
                        <option value="presentation" <?php echo (isset($edit_data['material_type']) && $edit_data['material_type'] == 'presentation') ? 'selected' : ''; ?>>PowerPoint</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Difficulty Level</label>
                    <select name="difficulty" class="form-control">
                        <option value="beginner" <?php echo (isset($edit_data['difficulty']) && $edit_data['difficulty'] == 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo (isset($edit_data['difficulty']) && $edit_data['difficulty'] == 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo (isset($edit_data['difficulty']) && $edit_data['difficulty'] == 'advanced') ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Estimated Duration (minutes)</label>
                    <input type="number" name="duration" class="form-control" value="<?php echo $edit_data['duration'] ?? 0; ?>" min="0">
                </div>
                <div class="form-group">
                    <label>Tags (comma separated)</label>
                    <input type="text" name="tags" class="form-control" placeholder="e.g., math, algebra, equations" value="<?php echo htmlspecialchars($edit_data['tags'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>File <?php echo $edit_data ? '(Leave empty to keep current file)' : '*'; ?></label>
                <input type="file" name="file" class="form-control" <?php echo !$edit_data ? 'required' : ''; ?> accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.avi,.mkv,.zip,.rar">
                <?php if ($edit_data && $edit_data['file_path']): ?>
                    <small class="form-text">Current file: <?php echo basename($edit_data['file_path']); ?></small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_published" value="1" <?php echo (isset($edit_data['is_published']) && $edit_data['is_published']) ? 'checked' : 'checked'; ?>>
                    <span>Publish immediately</span>
                </label>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary"><?php echo $edit_data ? 'Update' : 'Add'; ?> Material</button>
            </div>
        </form>
    </div>
</div>

<!-- AI Generate Modal -->
<div id="aiModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-robot"></i> AI Generate Study Material</h2>
            <button class="modal-close" onclick="closeAIModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="form-group">
                <label>Topic / Subject *</label>
                <input type="text" id="ai_topic" class="form-control" placeholder="e.g., Photosynthesis, Algebra, World War II">
            </div>
            <div class="form-group">
                <label>Title for the material</label>
                <input type="text" id="ai_title" class="form-control" placeholder="Leave blank to auto-generate">
            </div>
            <div class="form-group">
                <label>Subject (Optional)</label>
                <select id="ai_subject_id" class="form-control">
                    <option value="">-- Select Subject --</option>
                    <?php 
                    $subjects->data_seek(0);
                    while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeAIModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="generateAISummary()">
                    <i class="fas fa-magic"></i> Generate
                </button>
            </div>
        </div>
        
        <div id="aiResult" style="display: none; padding: 1.5rem; border-top: 1px solid #eef2f6;">
            <h3>Generated Content</h3>
            <div id="aiContent" class="ai-content"></div>
            <div class="form-actions" style="margin-top: 1rem;">
                <button type="button" class="btn-secondary" onclick="copyAIContent()">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <button type="button" class="btn-primary" onclick="saveAIContent()">
                    <i class="fas fa-save"></i> Save as Material
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" class="modal" style="display: none;">
    <div class="modal-content preview-content">
        <div class="modal-header">
            <h2><i class="fas fa-eye"></i> Material Preview</h2>
            <button class="modal-close" onclick="closePreviewModal()">&times;</button>
        </div>
        <div class="preview-body">
            <iframe id="previewFrame" src="" style="width: 100%; height: 500px; border: none;"></iframe>
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-header h1 {
    font-size: 2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16,185,129,0.3);
}

.stats-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-mini-card {
    background: white;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    text-align: center;
    flex: 1;
    border: 1px solid #eef2f6;
}

.stat-mini-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-mini-label {
    font-size: 0.7rem;
    color: #64748b;
}

.filters-bar {
    background: white;
    border-radius: 16px;
    padding: 1rem;
    margin-bottom: 2rem;
    border: 1px solid #eef2f6;
}

.filters-form {
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

.filter-group input, .filter-group select {
    border: none;
    background: none;
    padding: 10px 0;
    width: 100%;
    outline: none;
}

.btn-filter {
    padding: 8px 16px;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.btn-clear {
    padding: 8px 16px;
    background: #f1f5f9;
    color: #64748b;
    text-decoration: none;
    border-radius: 8px;
}

.materials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 1.5rem;
}

.material-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    display: flex;
    gap: 1rem;
    transition: all 0.3s;
}

.material-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.material-card.draft-mode {
    opacity: 0.7;
    background: #fef9e3;
}

.material-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.material-content {
    flex: 1;
}

.material-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.material-content h3 {
    font-size: 1.1rem;
    color: #1e293b;
    margin: 0;
}

.difficulty-badge {
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.65rem;
    font-weight: 600;
}

.difficulty-beginner { background: #ecfdf5; color: #10b981; }
.difficulty-intermediate { background: #fef3c7; color: #f59e0b; }
.difficulty-advanced { background: #fef2f2; color: #ef4444; }

.material-description {
    color: #64748b;
    font-size: 0.85rem;
    margin-bottom: 0.75rem;
    line-height: 1.4;
}

.material-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.tag {
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.65rem;
    color: #1e293b;
}

.material-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.subject-badge, .type-badge, .duration-badge, .date-badge, .downloads-badge, .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 500;
}

.subject-badge { background: #e0f2fe; color: #3b82f6; }
.type-badge { background: #fef3c7; color: #f59e0b; }
.duration-badge { background: #f1f5f9; color: #64748b; }
.date-badge { background: #f1f5f9; color: #64748b; }
.downloads-badge { background: #ecfdf5; color: #10b981; }
.status-badge.published { background: #ecfdf5; color: #10b981; }
.status-badge.draft { background: #fef2f2; color: #ef4444; }

.material-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.75rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-outline {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #64748b;
}

.btn-outline:hover {
    border-color: #10b981;
    color: #10b981;
}

.btn-danger {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #ef4444;
}

.btn-danger:hover {
    background: #fee2e2;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    padding: 0 1.5rem;
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
    max-width: 700px;
    max-height: 90vh;
    overflow-y: auto;
}

.preview-content {
    max-width: 900px;
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

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1e293b;
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

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
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

.ai-content {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 8px;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    font-size: 0.85rem;
    line-height: 1.5;
}

.empty-state {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .materials-grid {
        grid-template-columns: 1fr;
    }
    
    .material-card {
        flex-direction: column;
        text-align: center;
    }
    
    .material-icon {
        margin: 0 auto;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .filters-form {
        flex-direction: column;
    }
    
    .filter-group {
        width: 100%;
    }
}
</style>

<script>
function closeModal() {
    document.getElementById('addMaterialModal').style.display = 'none';
}

function editMaterial(id) {
    window.location.href = 'study-materials.php?edit=' + id;
}

function deleteMaterial(id) {
    if (confirm('Are you sure you want to delete this study material? This action cannot be undone.')) {
        window.location.href = 'study-materials.php?delete=' + id;
    }
}

function openAIModal() {
    document.getElementById('aiModal').style.display = 'flex';
    document.getElementById('aiResult').style.display = 'none';
}

function closeAIModal() {
    document.getElementById('aiModal').style.display = 'none';
}

function generateAISummary() {
    const topic = document.getElementById('ai_topic').value;
    const title = document.getElementById('ai_title').value;
    const subjectId = document.getElementById('ai_subject_id').value;
    
    if (!topic) {
        alert('Please enter a topic');
        return;
    }
    
    const generateBtn = event.target;
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=generate_summary&topic=${encodeURIComponent(topic)}&title=${encodeURIComponent(title)}&subject_id=${subjectId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('aiContent').innerHTML = data.summary.replace(/\n/g, '<br>');
            document.getElementById('aiResult').style.display = 'block';
        } else {
            alert('Failed to generate summary: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    })
    .finally(() => {
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="fas fa-magic"></i> Generate';
    });
}

function copyAIContent() {
    const content = document.getElementById('aiContent').innerText;
    navigator.clipboard.writeText(content);
    alert('Content copied to clipboard!');
}

function saveAIContent() {
    const title = document.getElementById('ai_title').value || document.getElementById('ai_topic').value;
    const description = document.getElementById('aiContent').innerHTML;
    const subjectId = document.getElementById('ai_subject_id').value;
    
    document.getElementById('addMaterialModal').style.display = 'flex';
    document.querySelector('input[name="title"]').value = title;
    document.querySelector('textarea[name="description"]').value = description.replace(/<br>/g, '\n');
    if (subjectId) {
        document.querySelector('select[name="subject_id"]').value = subjectId;
    }
    closeAIModal();
}

function previewMaterial(id) {
    // Get file URL from the material
    const materialCard = event.target.closest('.material-card');
    const downloadLink = materialCard.querySelector('.btn-outline[href]');
    if (downloadLink) {
        document.getElementById('previewFrame').src = downloadLink.href;
        document.getElementById('previewModal').style.display = 'flex';
    }
}

function closePreviewModal() {
    document.getElementById('previewModal').style.display = 'none';
    document.getElementById('previewFrame').src = '';
}

function incrementDownload(id) {
    fetch(`update-download.php?id=${id}`, { method: 'POST' });
}

window.onclick = function(event) {
    const modal = document.getElementById('addMaterialModal');
    const aiModal = document.getElementById('aiModal');
    const previewModal = document.getElementById('previewModal');
    if (event.target === modal) closeModal();
    if (event.target === aiModal) closeAIModal();
    if (event.target === previewModal) closePreviewModal();
}

<?php if ($edit_data): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('addMaterialModal').style.display = 'flex';
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>