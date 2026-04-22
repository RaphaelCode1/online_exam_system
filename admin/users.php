<?php
/**
 * Admin Users Management Page
 * Manage students (view, edit, delete, toggle status) with email notifications
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle Delete Action
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['user_id'])) {
    $delete_id = (int)$_POST['user_id'];
    $reason = isset($_POST['delete_reason']) ? trim($_POST['delete_reason']) : 'No reason provided';
    
    // Check if user exists and is a student
    $check = $conn->query("SELECT id, email, full_name, username FROM users WHERE id = $delete_id AND role = 'student'");
    $user = $check->fetch_assoc();
    
    if ($user) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Send deletion notification email BEFORE deleting
            sendAccountDeletionEmail($user['email'], $user['full_name'], $user['username'], $reason);
            
            // Get all exam attempts for this student
            $attempts = $conn->query("SELECT id FROM exam_attempts WHERE student_id = $delete_id");
            while ($attempt = $attempts->fetch_assoc()) {
                // Delete student answers
                $conn->query("DELETE FROM student_answers WHERE attempt_id = {$attempt['id']}");
            }
            // Delete exam attempts
            $conn->query("DELETE FROM exam_attempts WHERE student_id = $delete_id");
            
            // Delete notifications
            $conn->query("DELETE FROM notifications WHERE user_id = $delete_id");
            
            // Delete activity logs
            $conn->query("DELETE FROM activity_log WHERE user_id = $delete_id");
            
            // Finally delete the user
            $conn->query("DELETE FROM users WHERE id = $delete_id AND role = 'student'");
            
            $conn->commit();
            $message = "Student deleted successfully! Email notification sent to the student.";
            logActivity($_SESSION['user_id'], 'delete_student', "Deleted student ID: $delete_id - Reason: $reason");
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error deleting student: " . $e->getMessage();
        }
    } else {
        $error = "Student not found or invalid user type";
    }
}

// Handle Status Toggle
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status' && isset($_POST['user_id'])) {
    $toggle_id = (int)$_POST['user_id'];
    $current_status = isset($_POST['current_status']) ? (int)$_POST['current_status'] : 1;
    $reason = isset($_POST['status_reason']) ? trim($_POST['status_reason']) : 'No reason provided';
    $new_status = $current_status == 1 ? 0 : 1;
    
    // Get user details before updating
    $user = $conn->query("SELECT id, email, full_name, username FROM users WHERE id = $toggle_id AND role = 'student'")->fetch_assoc();
    
    if ($user) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'student'");
        $stmt->bind_param("ii", $new_status, $toggle_id);
        
        if ($stmt->execute()) {
            $status_text = $new_status == 1 ? 'activated' : 'deactivated';
            $message = "Student $status_text successfully!";
            
            // Send status change notification email
            sendAccountStatusChangeEmail($user['email'], $user['full_name'], $user['username'], $new_status, $reason);
            
            logActivity($_SESSION['user_id'], 'toggle_student_status', "Toggled student ID: $toggle_id to status: $new_status - Reason: $reason");
        } else {
            $error = "Error updating status";
        }
        $stmt->close();
    } else {
        $error = "Student not found";
    }
}

// Handle Reset Password
if (isset($_POST['action']) && $_POST['action'] === 'reset_password' && isset($_POST['user_id'])) {
    $reset_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        $user = $conn->query("SELECT id, email, full_name, username FROM users WHERE id = $reset_id AND role = 'student'")->fetch_assoc();
        
        if ($user) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $reset_id);
            
            if ($stmt->execute()) {
                $message = "Password reset successfully for " . htmlspecialchars($user['full_name']);
                
                // Send password reset notification email
                sendPasswordResetSuccessEmail($user['email'], $user['full_name'], $user['username'], $new_password);
                
                logActivity($_SESSION['user_id'], 'reset_student_password', "Reset password for student ID: $reset_id");
            } else {
                $error = "Error resetting password";
            }
            $stmt->close();
        } else {
            $error = "Student not found";
        }
    }
}

// Handle Edit/Update
if (isset($_POST['action']) && $_POST['action'] === 'update' && isset($_POST['user_id'])) {
    $update_id = (int)$_POST['user_id'];
    $full_name = $conn->real_escape_string(trim($_POST['full_name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $username = $conn->real_escape_string(trim($_POST['username']));
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address";
    } else {
        // Check if email exists for other users
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $update_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Email already in use by another account";
        } else {
            // Check if username exists for other users
            $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check->bind_param("si", $username, $update_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Username already taken";
            } else {
                $old_user = $conn->query("SELECT email, full_name FROM users WHERE id = $update_id")->fetch_assoc();
                
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, username = ? WHERE id = ? AND role = 'student'");
                $stmt->bind_param("sssi", $full_name, $email, $username, $update_id);
                
                if ($stmt->execute()) {
                    $message = "Student updated successfully!";
                    
                    // Send profile update notification if email changed
                    if ($old_user['email'] != $email) {
                        sendProfileUpdateEmail($email, $full_name, $username);
                    }
                    
                    logActivity($_SESSION['user_id'], 'update_student', "Updated student ID: $update_id");
                    $action = 'list'; // Go back to list view
                } else {
                    $error = "Error updating student: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        $check->close();
    }
}

// Get student data for editing
$edit_student = null;
if ($action === 'edit' && $user_id > 0) {
    $edit_student = $conn->query("SELECT * FROM users WHERE id = $user_id AND role = 'student'")->fetch_assoc();
    if (!$edit_student) {
        $action = 'list';
        $error = "Student not found";
    }
}

// Get view student data
$view_student = null;
if ($action === 'view' && $user_id > 0) {
    $view_student = $conn->query("SELECT * FROM users WHERE id = $user_id AND role = 'student'")->fetch_assoc();
    if (!$view_student) {
        $action = 'list';
        $error = "Student not found";
    } else {
        // Get student statistics
        $stats = [];
        
        // Total exams taken
        $result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
        $stats['exams_taken'] = $result->fetch_assoc()['total'];
        
        // Average score
        $result = $conn->query("SELECT AVG(percentage) as avg FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
        $stats['avg_score'] = round($result->fetch_assoc()['avg'] ?? 0, 1);
        
        // Passed exams
        $result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND passed = 1");
        $stats['exams_passed'] = $result->fetch_assoc()['total'];
        
        // Total score
        $result = $conn->query("SELECT SUM(score) as total_score FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
        $stats['total_score'] = $result->fetch_assoc()['total_score'] ?? 0;
        
        // Last activity
        $result = $conn->query("SELECT created_at FROM activity_log WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 1");
        $stats['last_activity'] = $result->fetch_assoc()['created_at'] ?? 'Never';
        
        // Get recent exam attempts
        $recent_attempts = $conn->query("SELECT ea.*, e.title as exam_title FROM exam_attempts ea 
                                         JOIN exams e ON ea.exam_id = e.id 
                                         WHERE ea.student_id = $user_id AND ea.status = 'completed' 
                                         ORDER BY ea.created_at DESC LIMIT 5");
    }
}

// Get all students with filters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1;

$query = "SELECT * FROM users WHERE role = 'student'";
if ($search) {
    $query .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%' OR username LIKE '%$search%')";
}
if ($status_filter >= 0) {
    $query .= " AND status = $status_filter";
}
$query .= " ORDER BY created_at DESC";

$students = $conn->query($query);
$total_students = $students->num_rows;

// Get statistics
$active_students = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status = 1")->fetch_assoc()['total'];
$inactive_students = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student' AND status = 0")->fetch_assoc()['total'];

$page_title = 'Manage Students';
include '../includes/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h1>Manage Students</h1>
        <div>
            <a href="users.php?action=list" class="btn btn-primary">
                <i class="fas fa-list"></i> Student List
            </a>
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
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?php echo $active_students; ?></div>
                <div class="stat-label">Active Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: #fef2f2; color: #ef4444;">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-value"><?php echo $inactive_students; ?></div>
                <div class="stat-label">Inactive Students</div>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="card" style="margin-bottom: 2rem;">
            <form method="GET" action="users.php" class="form-row" style="align-items: flex-end;">
                <input type="hidden" name="action" value="list">
                <div class="form-group" style="flex: 2;">
                    <label>Search Students</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email or username..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="-1">All Status</option>
                        <option value="1" <?php echo $status_filter == 1 ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $status_filter == 0 ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="users.php?action=list" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Students Table -->
        <div class="card">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php if ($students->num_rows > 0): ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $student['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($student['username']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <?php if ($student['status'] == 1): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($student['created_at'], 'M j, Y'); ?></td>
                                <td style="white-space: nowrap;">
                                    <a href="users.php?action=view&id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="users.php?action=edit&id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button onclick="showStatusModal(<?php echo $student['id']; ?>, <?php echo $student['status']; ?>, '<?php echo addslashes($student['full_name']); ?>')" class="btn btn-sm btn-outline" title="<?php echo $student['status'] == 1 ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $student['status'] == 1 ? 'ban' : 'check-circle'; ?>"></i>
                                    </button>
                                    <button onclick="showResetPasswordModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['full_name']); ?>')" class="btn btn-sm btn-outline" title="Reset Password">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button onclick="showDeleteModal(<?php echo $student['id']; ?>, '<?php echo addslashes($student['full_name']); ?>')" class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">
                                    <i class="fas fa-users" style="font-size: 2rem; color: var(--gray); margin-bottom: 0.5rem; display: block;"></i>
                                    No students found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($action === 'view' && $view_student): ?>
        <!-- View Student Details -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Student Details</h2>
                <div>
                    <a href="users.php?action=edit&id=<?php echo $view_student['id']; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="users.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <div>
                    <h3>Profile Information</h3>
                    <div style="margin-top: 1rem;">
                        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($view_student['full_name']); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($view_student['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($view_student['email']); ?></p>
                        <p><strong>Status:</strong> <?php echo $view_student['status'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>'; ?></p>
                        <p><strong>Registered:</strong> <?php echo formatDate($view_student['created_at']); ?></p>
                    </div>
                </div>
                
                <div>
                    <h3>Performance Statistics</h3>
                    <div style="margin-top: 1rem;">
                        <p><strong>Exams Taken:</strong> <?php echo $stats['exams_taken']; ?></p>
                        <p><strong>Exams Passed:</strong> <?php echo $stats['exams_passed']; ?></p>
                        <p><strong>Average Score:</strong> <?php echo $stats['avg_score']; ?>%</p>
                        <p><strong>Total Score:</strong> <?php echo $stats['total_score']; ?></p>
                        <p><strong>Last Activity:</strong> <?php echo $stats['last_activity'] != 'Never' ? formatDate($stats['last_activity']) : 'Never'; ?></p>
                    </div>
                </div>
            </div>
            
            <h3 style="margin-top: 2rem;">Recent Exam Attempts</h3>
            <div class="table-container" style="margin-top: 1rem;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>Date</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Result</th>
                        </thead>
                    <tbody>
                        <?php if ($recent_attempts && $recent_attempts->num_rows > 0): ?>
                            <?php while ($attempt = $recent_attempts->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                                <td><?php echo formatDate($attempt['created_at']); ?></td>
                                <td><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions']; ?></td>
                                <td><?php echo $attempt['percentage']; ?>%</td>
                                <td>
                                    <span class="badge <?php echo $attempt['passed'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $attempt['passed'] ? 'Passed' : 'Failed'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">No exam attempts yet</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    <?php elseif ($action === 'edit' && $edit_student): ?>
        <!-- Edit Student Form -->
        <div class="card" style="max-width: 600px; margin: 0 auto;">
            <h2 style="margin-bottom: 1.5rem;">Edit Student</h2>
            
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?php echo $edit_student['id']; ?>">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($edit_student['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($edit_student['username']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($edit_student['email']); ?>" required>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">Update Student</button>
                    <a href="users.php" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        </div>
        
    <?php endif; ?>
</div>

<!-- Status Change Modal -->
<div id="statusModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2 id="statusModalTitle">Change Student Status</h2>
            <button class="close-modal" onclick="closeStatusModal()">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="user_id" id="status_user_id">
            <input type="hidden" name="current_status" id="status_current_status">
            <div class="form-group">
                <label>Reason for this action</label>
                <textarea name="status_reason" class="form-control" rows="3" placeholder="Enter reason for activating/deactivating this account..."></textarea>
                <small>This reason will be sent to the student via email.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="statusSubmitBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Reset Student Password</h2>
            <button class="close-modal" onclick="closeResetPasswordModal()">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset_user_id">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" class="form-control" required minlength="6">
                <small>Minimum 6 characters</small>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeResetPasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h2>Delete Student Account</h2>
            <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="delete_user_id">
            <div class="form-group">
                <label>Reason for deletion</label>
                <textarea name="delete_reason" class="form-control" rows="3" placeholder="Enter reason for deleting this account..." required></textarea>
                <small>This reason will be sent to the student via email.</small>
            </div>
            <div class="warning-box" style="background: #fef2f2; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                <strong>Warning:</strong> This action cannot be undone. All student data including exam attempts, results, and activity logs will be permanently deleted.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete Permanently</button>
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
.btn-danger {
    background: #ef4444;
    color: white;
}
.btn-danger:hover {
    background: #dc2626;
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
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.2rem;
    border: 1px solid var(--border);
}
.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
}
.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-label {
    font-size: 0.75rem;
    color: var(--gray);
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
.bg-danger {
    background: #fef2f2;
    color: #ef4444;
}
</style>

<script>
function showStatusModal(userId, currentStatus, studentName) {
    document.getElementById('status_user_id').value = userId;
    document.getElementById('status_current_status').value = currentStatus;
    const title = currentStatus == 1 ? 'Deactivate Student Account' : 'Activate Student Account';
    const btnText = currentStatus == 1 ? 'Deactivate Account' : 'Activate Account';
    document.getElementById('statusModalTitle').innerHTML = title;
    document.getElementById('statusSubmitBtn').innerHTML = btnText;
    document.getElementById('statusModal').style.display = 'flex';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

function showResetPasswordModal(userId, studentName) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function closeResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}

function showDeleteModal(userId, studentName) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>