<?php
/**
 * Announcements Management Page
 * Create and manage system announcements with email notifications
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/email.php';

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    $type = $_POST['type'];
    $priority = $_POST['priority'];
    $target_role = $_POST['target_role'];
    $target_course = isset($_POST['target_course']) ? (int)$_POST['target_course'] : 0;
    $target_year = isset($_POST['target_year']) ? (int)$_POST['target_year'] : 0;
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO announcements (title, content, type, priority, target_role, target_course, target_year, is_pinned, expires_at, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssiiisi", $title, $content, $type, $priority, $target_role, $target_course, $target_year, $is_pinned, $expires_at, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $announcement_id = $stmt->insert_id;
        $message = "Announcement created successfully!";
        logActivity($_SESSION['user_id'], 'create_announcement', "Created announcement: $title");
        
        // Send email notifications if enabled
        if ($send_email) {
            $email_stats = sendAnnouncementEmails($conn, $title, $content, $type, $priority, $target_role, $target_course, $target_year);
            if ($email_stats['sent'] > 0) {
                $message .= " Email notifications sent to {$email_stats['sent']} recipient(s).";
                if ($email_stats['failed'] > 0) {
                    $message .= " ({$email_stats['failed']} failed)";
                }
            } else {
                $message .= " (No email recipients found for selected target audience)";
            }
        }
        
        $action = 'list';
    } else {
        $error = "Error creating announcement: " . $stmt->error;
    }
    $stmt->close();
}

/**
 * Send announcement emails to target users
 */
function sendAnnouncementEmails($conn, $title, $content, $type, $priority, $target_role, $target_course, $target_year) {
    // Build query based on target audience
    $query = "SELECT id, email, full_name FROM users WHERE status = 1";
    
    if ($target_role === 'students') {
        $query .= " AND role = 'student'";
    } elseif ($target_role === 'admins') {
        $query .= " AND role = 'admin'";
    }
    // If 'all', include both students and admins
    
    // For course/year filtering (if you have these fields in users table or a profiles table)
    // You would need to join with a student_profiles table
    // For now, we'll just filter by role
    
    $users = $conn->query($query);
    
    // Priority color and icon
    $priority_colors = [
        'urgent' => '#ef4444',
        'high' => '#f59e0b',
        'normal' => '#10b981',
        'low' => '#64748b'
    ];
    
    $priority_icons = [
        'urgent' => 'fa-exclamation-triangle',
        'high' => 'fa-arrow-up',
        'normal' => 'fa-bell',
        'low' => 'fa-info-circle'
    ];
    
    $priority_color = $priority_colors[$priority] ?? '#10b981';
    $priority_icon = $priority_icons[$priority] ?? 'fa-bell';
    
    $type_labels = [
        'general' => 'General Announcement',
        'exam' => 'Exam Update',
        'result' => 'Results Announcement',
        'maintenance' => 'System Maintenance'
    ];
    $type_label = $type_labels[$type] ?? 'Announcement';
    
    $email_subject = "📢 [" . strtoupper($priority) . "] " . $title;
    
    $email_body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: white; padding: 30px; text-align: center; border-radius: 16px 16px 0 0; }
            .header h1 { margin: 0; font-size: 24px; }
            .header p { margin: 10px 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 30px; background: #ffffff; border: 1px solid #eef2f6; border-top: none; border-radius: 0 0 16px 16px; }
            .priority-badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; margin-bottom: 15px; }
            .announcement-title { font-size: 22px; font-weight: bold; color: #1e293b; margin-bottom: 15px; }
            .announcement-content { color: #475569; line-height: 1.6; margin: 20px 0; white-space: pre-wrap; }
            .info-box { background: #f8fafc; padding: 15px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #10b981; }
            .info-item { padding: 8px 0; border-bottom: 1px solid #eef2f6; }
            .info-item:last-child { border-bottom: none; }
            .label { font-weight: 600; color: #1e293b; width: 120px; display: inline-block; }
            .button { display: inline-block; padding: 12px 28px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; font-weight: 600; }
            .button:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16,185,129,0.3); }
            .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-radius: 0 0 16px 16px; border-top: 1px solid #eef2f6; }
            .social-links a { color: #64748b; text-decoration: none; margin: 0 10px; }
            .warning { color: #ef4444; font-size: 12px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📢 ' . $type_label . '</h1>
                <p>MissionTech College Online Examination System</p>
            </div>
            <div class="content">
                <div class="priority-badge" style="background: ' . $priority_color . '20; color: ' . $priority_color . ';">
                    <i class="fas ' . $priority_icon . '"></i> ' . strtoupper($priority) . ' PRIORITY
                </div>
                
                <div class="announcement-title">' . htmlspecialchars($title) . '</div>
                
                <div class="announcement-content">' . nl2br(htmlspecialchars($content)) . '</div>
                
                <div class="info-box">
                    <div class="info-item">
                        <span class="label">📅 Date:</span>
                        <span>' . date('F j, Y \a\t g:i A') . '</span>
                    </div>
                    <div class="info-item">
                        <span class="label">👥 Target:</span>
                        <span>' . ucfirst($target_role) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="label">📌 Type:</span>
                        <span>' . ucfirst($type) . '</span>
                    </div>
                </div>
                
                <p>For more details, please log in to your student portal.</p>
                
                <p style="text-align: center;">
                    <a href="http://localhost/online_exam_system/student/dashboard.php" class="button">📚 Go to Dashboard</a>
                </p>
                
                <p style="margin-top: 20px; font-size: 13px; color: #64748b;">
                    <strong>Note:</strong> This is an automated notification from the Online Examination System.
                </p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' MissionTech College. All rights reserved.</p>
                <p>MissionTech College | Excellence in Education</p>
                <p>Need help? Contact support: <a href="mailto:missiontech.raph@gmail.com">missiontech.raph@gmail.com</a></p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $sent_count = 0;
    $failed_count = 0;
    $recipients_list = [];
    
    // Check if email_logs table exists, create if not
    $table_check = $conn->query("SHOW TABLES LIKE 'email_logs'");
    if ($table_check->num_rows == 0) {
        $create_table = "CREATE TABLE IF NOT EXISTS `email_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `recipient_email` varchar(255) NOT NULL,
            `recipient_name` varchar(255) DEFAULT NULL,
            `subject` varchar(500) NOT NULL,
            `type` varchar(50) DEFAULT 'announcement',
            `status` enum('sent','failed','pending') DEFAULT 'pending',
            `error_message` text DEFAULT NULL,
            `sent_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `recipient_email` (`recipient_email`),
            KEY `status` (`status`),
            KEY `sent_at` (`sent_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->query($create_table);
    }
    
    while ($user = $users->fetch_assoc()) {
        $recipient_email = $user['email'];
        $recipient_name = $user['full_name'];
        
        if (sendEmail($recipient_email, $email_subject, $email_body, $recipient_name)) {
            $sent_count++;
            $recipients_list[] = $recipient_email;
            
            // Log successful email
            try {
                $log_stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, type, status, sent_at) VALUES (?, ?, ?, 'announcement', 'sent', NOW())");
                $log_stmt->bind_param("sss", $recipient_email, $recipient_name, $email_subject);
                $log_stmt->execute();
                $log_stmt->close();
            } catch (Exception $e) {
                error_log("Failed to log email: " . $e->getMessage());
            }
        } else {
            $failed_count++;
            
            // Log failed email
            try {
                $log_stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, type, status, error_message, sent_at) VALUES (?, ?, ?, 'announcement', 'failed', 'Email sending failed', NOW())");
                $log_stmt->bind_param("sss", $recipient_email, $recipient_name, $email_subject);
                $log_stmt->execute();
                $log_stmt->close();
            } catch (Exception $e) {
                error_log("Failed to log email failure: " . $e->getMessage());
            }
        }
    }
    
    // Log summary to activity log
    if ($sent_count > 0 || $failed_count > 0) {
        logActivity($_SESSION['user_id'], 'announcement_emails_sent', "Sent announcement '$title' to $sent_count recipients ($failed_count failed)");
    }
    
    return ['sent' => $sent_count, 'failed' => $failed_count, 'recipients' => $recipients_list];
}

// Handle Delete Announcement
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['announcement_id'])) {
    $delete_id = (int)$_POST['announcement_id'];
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Announcement deleted successfully!";
        logActivity($_SESSION['user_id'], 'delete_announcement', "Deleted announcement ID: $delete_id");
    } else {
        $error = "Error deleting announcement";
    }
    $stmt->close();
}

// Handle Toggle Pin
if (isset($_POST['action']) && $_POST['action'] === 'toggle_pin' && isset($_POST['announcement_id'])) {
    $pin_id = (int)$_POST['announcement_id'];
    $current_pin = (int)$_POST['current_pin'];
    $new_pin = $current_pin == 1 ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE announcements SET is_pinned = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_pin, $pin_id);
    if ($stmt->execute()) {
        $message = $new_pin ? "Announcement pinned!" : "Announcement unpinned!";
    } else {
        $error = "Error updating pin status";
    }
    $stmt->close();
}

// Get edit announcement data
$edit_announcement = null;
if ($action === 'edit' && $announcement_id > 0) {
    $edit_announcement = $conn->query("SELECT * FROM announcements WHERE id = $announcement_id")->fetch_assoc();
    if (!$edit_announcement) {
        $action = 'list';
        $error = "Announcement not found";
    }
}

// Handle Update Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $update_id = (int)$_POST['announcement_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $content = $conn->real_escape_string($_POST['content']);
    $type = $_POST['type'];
    $priority = $_POST['priority'];
    $target_role = $_POST['target_role'];
    $target_course = isset($_POST['target_course']) ? (int)$_POST['target_course'] : 0;
    $target_year = isset($_POST['target_year']) ? (int)$_POST['target_year'] : 0;
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, type = ?, priority = ?, target_role = ?, target_course = ?, target_year = ?, is_pinned = ?, expires_at = ? WHERE id = ?");
    $stmt->bind_param("sssssiiisi", $title, $content, $type, $priority, $target_role, $target_course, $target_year, $is_pinned, $expires_at, $update_id);
    
    if ($stmt->execute()) {
        $message = "Announcement updated successfully!";
        logActivity($_SESSION['user_id'], 'update_announcement', "Updated announcement ID: $update_id");
        
        // Send email notifications if enabled for update
        if ($send_email) {
            $email_stats = sendAnnouncementEmails($conn, $title, $content, $type, $priority, $target_role, $target_course, $target_year);
            if ($email_stats['sent'] > 0) {
                $message .= " Email notifications sent to {$email_stats['sent']} recipient(s).";
                if ($email_stats['failed'] > 0) {
                    $message .= " ({$email_stats['failed']} failed)";
                }
            } else {
                $message .= " (No email recipients found for selected target audience)";
            }
        }
        
        $action = 'list';
    } else {
        $error = "Error updating announcement: " . $stmt->error;
    }
    $stmt->close();
}

// Get all announcements
$announcements = $conn->query("SELECT a.*, u.full_name as author_name 
                               FROM announcements a 
                               LEFT JOIN users u ON a.created_by = u.id 
                               ORDER BY a.is_pinned DESC, FIELD(a.priority, 'urgent', 'high', 'normal', 'low'), a.created_at DESC");

$page_title = 'Announcements';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> New Announcement
        </button>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <div class="announcements-list">
            <?php if ($announcements->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <h3>No Announcements</h3>
                    <p>Create your first announcement to communicate with students.</p>
                </div>
            <?php else: ?>
                <?php while ($ann = $announcements->fetch_assoc()): 
                    $priority_class = '';
                    $priority_icon = '';
                    switch ($ann['priority']) {
                        case 'urgent':
                            $priority_class = 'priority-urgent';
                            $priority_icon = 'fa-exclamation-triangle';
                            break;
                        case 'high':
                            $priority_class = 'priority-high';
                            $priority_icon = 'fa-arrow-up';
                            break;
                        case 'normal':
                            $priority_class = 'priority-normal';
                            $priority_icon = 'fa-bell';
                            break;
                        case 'low':
                            $priority_class = 'priority-low';
                            $priority_icon = 'fa-info-circle';
                            break;
                    }
                    
                    $type_class = '';
                    switch ($ann['type']) {
                        case 'exam': $type_class = 'type-exam'; break;
                        case 'result': $type_class = 'type-result'; break;
                        case 'maintenance': $type_class = 'type-maintenance'; break;
                        default: $type_class = 'type-general';
                    }
                ?>
                <div class="announcement-card <?php echo $priority_class; ?> <?php echo $ann['is_pinned'] ? 'pinned' : ''; ?>">
                    <?php if ($ann['is_pinned']): ?>
                        <div class="pinned-badge"><i class="fas fa-thumbtack"></i> Pinned</div>
                    <?php endif; ?>
                    <div class="announcement-header">
                        <div class="announcement-icon <?php echo $type_class; ?>">
                            <i class="fas <?php echo $priority_icon; ?>"></i>
                        </div>
                        <div class="announcement-title">
                            <h3><?php echo htmlspecialchars($ann['title']); ?></h3>
                            <div class="announcement-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($ann['author_name']); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo timeAgo($ann['created_at']); ?></span>
                                <span class="priority-badge <?php echo $priority_class; ?>"><?php echo ucfirst($ann['priority']); ?></span>
                            </div>
                        </div>
                        <div class="announcement-actions">
                            <a href="announcements.php?action=edit&id=<?php echo $ann['id']; ?>" class="btn-icon" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_pin">
                                <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                <input type="hidden" name="current_pin" value="<?php echo $ann['is_pinned']; ?>">
                                <button type="submit" class="btn-icon" title="<?php echo $ann['is_pinned'] ? 'Unpin' : 'Pin'; ?>">
                                    <i class="fas fa-thumbtack <?php echo $ann['is_pinned'] ? 'pinned' : ''; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this announcement?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                <button type="submit" class="btn-icon delete" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="announcement-content">
                        <?php echo nl2br(htmlspecialchars($ann['content'])); ?>
                    </div>
                    <?php if ($ann['expires_at']): ?>
                    <div class="announcement-footer">
                        <i class="fas fa-hourglass-half"></i> Expires: <?php echo date('M j, Y', strtotime($ann['expires_at'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        
    <?php elseif ($action === 'edit' && $edit_announcement): ?>
        <div class="card">
            <h2>Edit Announcement</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="announcement_id" value="<?php echo $edit_announcement['id']; ?>">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_announcement['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" class="form-control" rows="6" required><?php echo htmlspecialchars($edit_announcement['content']); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" class="form-control">
                            <option value="general" <?php echo $edit_announcement['type'] == 'general' ? 'selected' : ''; ?>>General</option>
                            <option value="exam" <?php echo $edit_announcement['type'] == 'exam' ? 'selected' : ''; ?>>Exam</option>
                            <option value="result" <?php echo $edit_announcement['type'] == 'result' ? 'selected' : ''; ?>>Result</option>
                            <option value="maintenance" <?php echo $edit_announcement['type'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low" <?php echo $edit_announcement['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="normal" <?php echo $edit_announcement['priority'] == 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="high" <?php echo $edit_announcement['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $edit_announcement['priority'] == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Target Audience</label>
                        <select name="target_role" class="form-control">
                            <option value="all" <?php echo $edit_announcement['target_role'] == 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="students" <?php echo $edit_announcement['target_role'] == 'students' ? 'selected' : ''; ?>>Students Only</option>
                            <option value="admins" <?php echo $edit_announcement['target_role'] == 'admins' ? 'selected' : ''; ?>>Admins Only</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Expires At (Optional)</label>
                        <input type="datetime-local" name="expires_at" class="form-control" value="<?php echo $edit_announcement['expires_at'] ? date('Y-m-d\TH:i', strtotime($edit_announcement['expires_at'])) : ''; ?>">
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_pinned" id="is_pinned" value="1" <?php echo $edit_announcement['is_pinned'] ? 'checked' : ''; ?>>
                    <label for="is_pinned">Pin this announcement (appears at top)</label>
                </div>
                
                <!-- EMAIL NOTIFICATION CHECKBOX FOR EDIT -->
                <div class="checkbox-group">
                    <input type="checkbox" name="send_email" id="send_email_edit" value="1">
                    <label for="send_email_edit">✉️ Send email notification to target audience</label>
                    <small class="help-text">Check this box to send this announcement via email to all selected users</small>
                </div>
                
                <div class="form-actions">
                    <a href="announcements.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Announcement</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Create Announcement Modal -->
<div id="createModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create New Announcement</h2>
            <button class="close-modal" onclick="closeCreateModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="Enter announcement title">
            </div>
            
            <div class="form-group">
                <label>Content *</label>
                <textarea name="content" class="form-control" rows="6" required placeholder="Write your announcement content here..."></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="general">📢 General Announcement</option>
                        <option value="exam">📝 Exam Update</option>
                        <option value="result">📊 Results Announcement</option>
                        <option value="maintenance">🔧 System Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        <option value="low">🔵 Low</option>
                        <option value="normal" selected>🟢 Normal</option>
                        <option value="high">🟠 High</option>
                        <option value="urgent">🔴 Urgent</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_role" class="form-control">
                        <option value="all">👥 All Users</option>
                        <option value="students">🎓 Students Only</option>
                        <option value="admins">👑 Admins Only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expires At (Optional)</label>
                    <input type="datetime-local" name="expires_at" class="form-control">
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="is_pinned" id="modal_is_pinned" value="1">
                <label for="modal_is_pinned">📌 Pin this announcement (appears at top of list)</label>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" name="send_email" id="send_email" value="1" checked>
                <label for="send_email">✉️ Send email notification to target audience</label>
                <small class="help-text">All selected users will receive this announcement via email</small>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create & Send</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Existing styles remain */
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
.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16,185,129,0.3);
}
.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.announcement-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    position: relative;
    transition: all 0.3s;
}
.announcement-card:hover {
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.announcement-card.pinned {
    border-left: 4px solid #f59e0b;
}
.announcement-card.priority-urgent {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
}
.announcement-card.priority-high {
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
}
.pinned-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: #f59e0b;
    color: white;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}
.announcement-header {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}
.announcement-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.announcement-icon.type-general { background: #e0f2fe; color: #0284c7; }
.announcement-icon.type-exam { background: #fef3c7; color: #d97706; }
.announcement-icon.type-result { background: #ecfdf5; color: #10b981; }
.announcement-icon.type-maintenance { background: #fef2f2; color: #ef4444; }
.announcement-icon i { font-size: 1.5rem; }
.announcement-title {
    flex: 1;
}
.announcement-title h3 {
    font-size: 1.1rem;
    color: #1e293b;
    margin-bottom: 0.25rem;
}
.announcement-meta {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.75rem;
    color: #64748b;
}
.announcement-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}
.priority-badge {
    padding: 2px 8px;
    border-radius: 30px;
    font-weight: 600;
}
.priority-badge.priority-urgent { background: #fef2f2; color: #ef4444; }
.priority-badge.priority-high { background: #fef3c7; color: #f59e0b; }
.priority-badge.priority-normal { background: #f1f5f9; color: #64748b; }
.priority-badge.priority-low { background: #f1f5f9; color: #94a3b8; }
.announcement-actions {
    display: flex;
    gap: 0.5rem;
}
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-icon:hover {
    background: #e2e8f0;
}
.btn-icon.delete:hover {
    background: #fef2f2;
    color: #ef4444;
}
.btn-icon i.pinned {
    color: #f59e0b;
}
.announcement-content {
    color: #475569;
    line-height: 1.6;
    margin: 1rem 0;
}
.announcement-footer {
    font-size: 0.75rem;
    color: #94a3b8;
    padding-top: 0.5rem;
    border-top: 1px solid #eef2f6;
}
.card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid #eef2f6;
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
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 1rem 0;
    flex-wrap: wrap;
}
.help-text {
    display: block;
    font-size: 0.7rem;
    color: #94a3b8;
    margin-left: 26px;
}
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: white;
    border-radius: 24px;
    padding: 2rem;
    max-width: 650px;
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
.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
}
.close-modal:hover {
    color: #ef4444;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
}
.btn-secondary {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 10px 20px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-secondary:hover {
    border-color: #10b981;
    color: #10b981;
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
.alert {
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
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
@media (max-width: 768px) {
    .announcement-header {
        flex-direction: column;
    }
    .announcement-actions {
        position: absolute;
        top: 1rem;
        right: 1rem;
    }
    .pinned-badge {
        top: 3rem;
        right: 1rem;
    }
    .modal-content {
        padding: 1.5rem;
    }
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}
</style>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}
function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>