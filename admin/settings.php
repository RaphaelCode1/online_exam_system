<?php
/**
 * Admin Settings Page
 * Manage system settings, email configuration, security, and appearance
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canViewSettings()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Create settings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'number', 'boolean', 'color', 'select') DEFAULT 'text',
    setting_group VARCHAR(50) DEFAULT 'general',
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create developer settings table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS developer_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'textarea', 'image', 'file', 'json') DEFAULT 'text',
    setting_group VARCHAR(50) DEFAULT 'developer',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Insert default developer settings if not exists
$conn->query("INSERT IGNORE INTO developer_settings (setting_key, setting_value, setting_type, setting_group, description) VALUES
('student_login_bg', 'uploads/background-Image/student-bg.jpg', 'image', 'backgrounds', 'Background image for student login page'),
('admin_login_bg', 'uploads/background-Image/admin-bg.jpg', 'image', 'backgrounds', 'Background image for admin login page'),
('custom_css', '', 'textarea', 'customization', 'Custom CSS to override default styles'),
('custom_js', '', 'textarea', 'customization', 'Custom JavaScript to add functionality'),
('debug_mode', '0', 'text', 'system', 'Enable debug mode (0=off, 1=on)'),
('maintenance_mode', '0', 'text', 'system', 'Enable maintenance mode (0=off, 1=on)'),
('maintenance_message', 'System is under maintenance. Please check back later.', 'textarea', 'system', 'Message to show during maintenance'),
('cache_enabled', '1', 'text', 'performance', 'Enable caching (0=off, 1=on)'),
('cache_lifetime', '3600', 'text', 'performance', 'Cache lifetime in seconds'),
('footer_text', '© 2024 MissionTech College. All rights reserved.', 'text', 'footer', 'Footer copyright text'),
('footer_about', 'Online Examination System for MissionTech College', 'textarea', 'footer', 'Footer about text')");

// Get all settings
$settings = [];
$result = $conn->query("SELECT * FROM system_settings ORDER BY setting_group, id");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
}

/**
 * Send test email function
 */
function sendTestEmail($to, $name) {
    $subject = "Test Email from MissionTech College";
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Test Email</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
            .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📧 Test Email</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>This is a test email from your Online Exam System.</p>
                <p>If you received this email, your email configuration is working correctly!</p>
                <p><strong>Time sent:</strong> ' . date('F j, Y \a\t H:i:s') . '</p>
                <p><strong>Server timezone:</strong> ' . date_default_timezone_get() . '</p>
                <p style="text-align: center;">
                    <a href="http://localhost/online_exam_system/admin/settings.php" class="button">Go to Settings</a>
                </p>
                <p>Best regards,<br><strong>MissionTech College Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' MissionTech College. All rights reserved.</p>
                <p>This is an automated test email.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Update General Settings
        if ($_POST['action'] === 'update_general') {
            $site_name = $conn->real_escape_string($_POST['site_name']);
            $site_description = $conn->real_escape_string($_POST['site_description']);
            $site_email = $conn->real_escape_string($_POST['site_email']);
            $timezone = $conn->real_escape_string($_POST['timezone']);
            $date_format = $conn->real_escape_string($_POST['date_format']);
            $time_format = $conn->real_escape_string($_POST['time_format']);
            
            // Update or insert settings
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                    VALUES (?, ?, 'text', 'general', NOW()) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            
            $stmt->bind_param("sss", $key, $value, $value);
            
            $key = 'site_name'; $value = $site_name; $stmt->execute();
            $key = 'site_description'; $value = $site_description; $stmt->execute();
            $key = 'site_email'; $value = $site_email; $stmt->execute();
            $key = 'timezone'; $value = $timezone; $stmt->execute();
            $key = 'date_format'; $value = $date_format; $stmt->execute();
            $key = 'time_format'; $value = $time_format; $stmt->execute();
            
            $stmt->close();
            
            // Update PHP timezone
            date_default_timezone_set($timezone);
            
            $message = "General settings updated successfully!";
            logActivity($_SESSION['user_id'], 'update_settings', 'Updated general settings');
        }
        
        // Update Email Settings
        elseif ($_POST['action'] === 'update_email') {
            $smtp_host = $conn->real_escape_string($_POST['smtp_host']);
            $smtp_port = (int)$_POST['smtp_port'];
            $smtp_user = $conn->real_escape_string($_POST['smtp_user']);
            $smtp_pass = $conn->real_escape_string($_POST['smtp_pass']);
            $smtp_encryption = $conn->real_escape_string($_POST['smtp_encryption']);
            $from_email = $conn->real_escape_string($_POST['from_email']);
            $from_name = $conn->real_escape_string($_POST['from_name']);
            
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                    VALUES (?, ?, 'text', 'email', NOW()) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            
            $stmt->bind_param("sss", $key, $value, $value);
            
            $key = 'smtp_host'; $value = $smtp_host; $stmt->execute();
            $key = 'smtp_port'; $value = $smtp_port; $stmt->execute();
            $key = 'smtp_user'; $value = $smtp_user; $stmt->execute();
            $key = 'smtp_pass'; $value = $smtp_pass; $stmt->execute();
            $key = 'smtp_encryption'; $value = $smtp_encryption; $stmt->execute();
            $key = 'from_email'; $value = $from_email; $stmt->execute();
            $key = 'from_name'; $value = $from_name; $stmt->execute();
            
            $stmt->close();

            $enable_emails = isset($_POST['enable_emails']) ? 1 : 0;
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                    VALUES ('enable_emails', ?, 'boolean', 'email', NOW()) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param("ss", $enable_emails, $enable_emails);
            $stmt->execute();
            $stmt->close();
            
            $message = "Email settings updated successfully!";
            logActivity($_SESSION['user_id'], 'update_settings', 'Updated email settings');
        }
        
        // Update Security Settings
        elseif ($_POST['action'] === 'update_security') {
            $max_login_attempts = (int)$_POST['max_login_attempts'];
            $lockout_time = (int)$_POST['lockout_time'];
            $password_min_length = (int)$_POST['password_min_length'];
            $session_timeout = (int)$_POST['session_timeout'];
            $two_factor_auth = isset($_POST['two_factor_auth']) ? 1 : 0;
            $force_password_change = isset($_POST['force_password_change']) ? 1 : 0;
            
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                    VALUES (?, ?, 'number', 'security', NOW()) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param("sss", $key, $value, $value);
            
            $key = 'max_login_attempts'; $value = $max_login_attempts; $stmt->execute();
            $key = 'lockout_time'; $value = $lockout_time; $stmt->execute();
            $key = 'password_min_length'; $value = $password_min_length; $stmt->execute();
            $key = 'session_timeout'; $value = $session_timeout; $stmt->execute();
            
            // Boolean settings
            $stmt_bool = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                         VALUES (?, ?, 'boolean', 'security', NOW()) 
                                         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt_bool->bind_param("sss", $key, $value, $value);
            
            $key = 'two_factor_auth'; $value = $two_factor_auth; $stmt_bool->execute();
            $key = 'force_password_change'; $value = $force_password_change; $stmt_bool->execute();
            
            $stmt->close();
            $stmt_bool->close();
            
            $message = "Security settings updated successfully!";
            logActivity($_SESSION['user_id'], 'update_settings', 'Updated security settings');
        }
        
        // Update Appearance Settings
        elseif ($_POST['action'] === 'update_appearance') {
            $primary_color = $conn->real_escape_string($_POST['primary_color']);
            $secondary_color = $conn->real_escape_string($_POST['secondary_color']);
            $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
            $sidebar_collapsed = isset($_POST['sidebar_collapsed']) ? 1 : 0;
            $font_size = $conn->real_escape_string($_POST['font_size']);
            
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                    VALUES (?, ?, 'color', 'appearance', NOW()) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param("sss", $key, $value, $value);
            
            $key = 'primary_color'; $value = $primary_color; $stmt->execute();
            $key = 'secondary_color'; $value = $secondary_color; $stmt->execute();
            
            $stmt_bool = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                         VALUES (?, ?, 'boolean', 'appearance', NOW()) 
                                         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt_bool->bind_param("sss", $key, $value, $value);
            
            $key = 'dark_mode'; $value = $dark_mode; $stmt_bool->execute();
            $key = 'sidebar_collapsed'; $value = $sidebar_collapsed; $stmt_bool->execute();
            
            $key = 'font_size'; $value = $font_size; 
            $stmt_font = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                         VALUES (?, ?, 'text', 'appearance', NOW()) 
                                         ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt_font->bind_param("sss", $key, $value, $value);
            $stmt_font->execute();
            
            $stmt->close();
            $stmt_bool->close();
            $stmt_font->close();
            
            $message = "Appearance settings updated successfully!";
            logActivity($_SESSION['user_id'], 'update_settings', 'Updated appearance settings');
        }
        
        // Update Feature Settings
        elseif ($_POST['action'] === 'update_features') {
            $enable_registration = isset($_POST['enable_registration']) ? 1 : 0;
            $enable_public_notes = isset($_POST['enable_public_notes']) ? 1 : 0;
            $enable_forum = isset($_POST['enable_forum']) ? 1 : 0;
            $enable_certificates = isset($_POST['enable_certificates']) ? 1 : 0;
            $questions_per_page = (int)$_POST['questions_per_page'];
            $max_attempts_per_exam = (int)$_POST['max_attempts_per_exam'];
            $default_pass_score = (int)$_POST['default_pass_score'];
            
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                    VALUES (?, ?, 'boolean', 'features', NOW()) 
                                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->bind_param("sss", $key, $value, $value);
            
            $key = 'enable_registration'; $value = $enable_registration; $stmt->execute();
            $key = 'enable_public_notes'; $value = $enable_public_notes; $stmt->execute();
            $key = 'enable_forum'; $value = $enable_forum; $stmt->execute();
            $key = 'enable_certificates'; $value = $enable_certificates; $stmt->execute();
            
            $stmt_num = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                        VALUES (?, ?, 'number', 'features', NOW()) 
                                        ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt_num->bind_param("sss", $key, $value, $value);
            
            $key = 'questions_per_page'; $value = $questions_per_page; $stmt_num->execute();
            $key = 'max_attempts_per_exam'; $value = $max_attempts_per_exam; $stmt_num->execute();
            $key = 'default_pass_score'; $value = $default_pass_score; $stmt_num->execute();
            
            $stmt->close();
            $stmt_num->close();
            
            $message = "Feature settings updated successfully!";
            logActivity($_SESSION['user_id'], 'update_settings', 'Updated feature settings');
        }
        
        // Backup Settings
        elseif ($_POST['action'] === 'backup') {
            $backup_file = isset($_POST['backup_file']) ? $conn->real_escape_string($_POST['backup_file']) : '';
            if ($backup_file) {
                // Restore from backup
                $backup_path = '../backups/' . $backup_file;
                if (file_exists($backup_path)) {
                    $sql = file_get_contents($backup_path);
                    if ($conn->multi_query($sql)) {
                        while ($conn->next_result()) {;}
                        $message = "Database restored successfully from backup: " . $backup_file;
                        logActivity($_SESSION['user_id'], 'restore_backup', "Restored from backup: $backup_file");
                    } else {
                        $error = "Failed to restore backup: " . $conn->error;
                    }
                } else {
                    $error = "Backup file not found";
                }
            } else {
                // Create backup
                $backup_dir = '../backups/';
                if (!file_exists($backup_dir)) {
                    mkdir($backup_dir, 0777, true);
                }
                
                $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = $backup_dir . $filename;
                
                // Get all tables
                $tables = [];
                $result = $conn->query("SHOW TABLES");
                while ($row = $result->fetch_row()) {
                    $tables[] = $row[0];
                }
                
                $backup_content = "-- Online Exam System Backup\n";
                $backup_content .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
                $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
                
                foreach ($tables as $table) {
                    $result = $conn->query("SELECT * FROM $table");
                    $num_fields = $result->field_count;
                    
                    $backup_content .= "DROP TABLE IF EXISTS $table;\n";
                    $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
                    $backup_content .= $row2[1] . ";\n\n";
                    
                    while ($row = $result->fetch_row()) {
                        $backup_content .= "INSERT INTO $table VALUES(";
                        for ($j = 0; $j < $num_fields; $j++) {
                            $row[$j] = addslashes($row[$j]);
                            $backup_content .= (isset($row[$j])) ? "'" . $row[$j] . "'" : "NULL";
                            if ($j < ($num_fields - 1)) {
                                $backup_content .= ',';
                            }
                        }
                        $backup_content .= ");\n";
                    }
                    $backup_content .= "\n";
                }
                
                $backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
                
                file_put_contents($filepath, $backup_content);
                $message = "Database backup created successfully: " . $filename;
                logActivity($_SESSION['user_id'], 'create_backup', "Created backup: $filename");
            }
        }
        
        // Send Test Email
        elseif ($_POST['action'] === 'test_email') {
            $test_email = $conn->real_escape_string($_POST['test_email']);
            $result = sendTestEmail($test_email, $_SESSION['full_name']);
            if ($result) {
                $message = "Test email sent successfully to " . $test_email;
            } else {
                $error = "Failed to send test email. Check your email settings.";
            }
        }
        
        // Clear Cache
        elseif ($_POST['action'] === 'clear_cache') {
            $cache_dirs = ['../cache/', '../uploads/temp/'];
            $cleared = 0;
            foreach ($cache_dirs as $dir) {
                if (file_exists($dir)) {
                    $files = glob($dir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                            $cleared++;
                        }
                    }
                }
            }
            $message = "Cache cleared successfully! Removed $cleared files.";
            logActivity($_SESSION['user_id'], 'clear_cache', 'Cleared system cache');
        }
        
        // Update Developer Settings
        elseif ($_POST['action'] === 'update_developer') {
            // Create upload directory if not exists
            $upload_dir = '../uploads/background-Image/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Handle student login background upload
            if (isset($_FILES['student_login_bg']) && $_FILES['student_login_bg']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['student_login_bg'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($ext, $allowed)) {
                    // Delete old file if exists
                    $old_bg = getDevSetting($conn, 'student_login_bg', '');
                    if ($old_bg && file_exists('../' . $old_bg) && $old_bg != 'uploads/background-Image/student-bg.jpg') {
                        unlink('../' . $old_bg);
                    }
                    
                    $filename = 'student-bg-' . time() . '.' . $ext;
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $value = 'uploads/background-Image/' . $filename;
                        $stmt = $conn->prepare("INSERT INTO developer_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                                VALUES ('student_login_bg', ?, 'image', 'backgrounds', NOW()) 
                                                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                        $stmt->bind_param("ss", $value, $value);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Update the variable for immediate display
                        $student_bg = $value;
                    }
                }
            }
            
            // Handle admin login background upload
            if (isset($_FILES['admin_login_bg']) && $_FILES['admin_login_bg']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['admin_login_bg'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (in_array($ext, $allowed)) {
                    // Delete old file if exists
                    $old_bg = getDevSetting($conn, 'admin_login_bg', '');
                    if ($old_bg && file_exists('../' . $old_bg) && $old_bg != 'uploads/background-Image/admin-bg.jpg') {
                        unlink('../' . $old_bg);
                    }
                    
                    $filename = 'admin-bg-' . time() . '.' . $ext;
                    $target = $upload_dir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $value = 'uploads/background-Image/' . $filename;
                        $stmt = $conn->prepare("INSERT INTO developer_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                                VALUES ('admin_login_bg', ?, 'image', 'backgrounds', NOW()) 
                                                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                        $stmt->bind_param("ss", $value, $value);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Update the variable for immediate display
                        $admin_bg = $value;
                    }
                }
            }
            
            // Update text settings
            $text_settings = ['custom_css', 'custom_js', 'debug_mode', 'maintenance_mode', 'maintenance_message', 
                               'cache_enabled', 'cache_lifetime', 'footer_text', 'footer_about'];
            
            foreach ($text_settings as $key) {
                if (isset($_POST[$key])) {
                    $value = $conn->real_escape_string($_POST[$key]);
                    $stmt = $conn->prepare("INSERT INTO developer_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                            VALUES (?, ?, 'text', 'developer', NOW()) 
                                            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
                    $stmt->bind_param("sss", $key, $value, $value);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $message = "Developer settings updated successfully!";
            logActivity($_SESSION['user_id'], 'update_settings', 'Updated developer settings');
        }
    }
}

// Get current settings with defaults
function getSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

// Get developer setting
function getDevSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM developer_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

// Get backup files
$backup_files = [];
$backup_dir = '../backups/';
if (file_exists($backup_dir)) {
    $backup_files = array_diff(scandir($backup_dir), ['.', '..']);
    rsort($backup_files);
}

// Fetch current background images after potential update
$student_bg_display = getDevSetting($conn, 'student_login_bg', 'uploads/background-Image/student-bg.jpg');
$admin_bg_display = getDevSetting($conn, 'admin_login_bg', 'uploads/background-Image/admin-bg.jpg');

$page_title = 'System Settings';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> System Settings</h1>
        <p>Configure your examination system settings</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Settings Tabs -->
    <div class="settings-tabs">
        <a href="?tab=general" class="settings-tab <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
            <i class="fas fa-globe"></i> General
        </a>
        <a href="?tab=email" class="settings-tab <?php echo $active_tab == 'email' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i> Email
        </a>
        <a href="?tab=security" class="settings-tab <?php echo $active_tab == 'security' ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i> Security
        </a>
        <a href="?tab=appearance" class="settings-tab <?php echo $active_tab == 'appearance' ? 'active' : ''; ?>">
            <i class="fas fa-palette"></i> Appearance
        </a>
        <a href="?tab=features" class="settings-tab <?php echo $active_tab == 'features' ? 'active' : ''; ?>">
            <i class="fas fa-rocket"></i> Features
        </a>
        <a href="?tab=backup" class="settings-tab <?php echo $active_tab == 'backup' ? 'active' : ''; ?>">
            <i class="fas fa-database"></i> Backup
        </a>
        <a href="?tab=developer" class="settings-tab <?php echo $active_tab == 'developer' ? 'active' : ''; ?>">
            <i class="fas fa-code"></i> Developer
        </a>
    </div>
    
    <!-- General Settings -->
    <div class="settings-panel <?php echo $active_tab == 'general' ? 'active' : ''; ?>" id="general-panel">
        <div class="card">
            <h2><i class="fas fa-globe"></i> General Settings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_general">
                
                <div class="form-group">
                    <label>Site Name</label>
                    <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars(getSetting($conn, 'site_name', 'MissionTech College')); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Site Description</label>
                    <textarea name="site_description" class="form-control" rows="3"><?php echo htmlspecialchars(getSetting($conn, 'site_description', 'Online Examination System for MissionTech College')); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Admin Email</label>
                        <input type="email" name="site_email" class="form-control" value="<?php echo htmlspecialchars(getSetting($conn, 'site_email', 'admin@examsystem.com')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Timezone</label>
                        <select name="timezone" class="form-control">
                            <?php
                            $timezones = [
                                'Africa/Nairobi' => 'Nairobi (EAT)',
                                'America/New_York' => 'New York (EST)',
                                'America/Los_Angeles' => 'Los Angeles (PST)',
                                'Europe/London' => 'London (GMT)',
                                'Asia/Dubai' => 'Dubai (GST)',
                                'Asia/Kolkata' => 'Mumbai (IST)',
                                'Asia/Tokyo' => 'Tokyo (JST)',
                                'Australia/Sydney' => 'Sydney (AEDT)'
                            ];
                            $current_tz = getSetting($conn, 'timezone', 'Africa/Nairobi');
                            foreach ($timezones as $tz => $name): ?>
                                <option value="<?php echo $tz; ?>" <?php echo $current_tz == $tz ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date Format</label>
                        <select name="date_format" class="form-control">
                            <option value="F j, Y" <?php echo getSetting($conn, 'date_format', 'F j, Y') == 'F j, Y' ? 'selected' : ''; ?>>January 1, 2024</option>
                            <option value="Y-m-d" <?php echo getSetting($conn, 'date_format') == 'Y-m-d' ? 'selected' : ''; ?>>2024-01-01</option>
                            <option value="d/m/Y" <?php echo getSetting($conn, 'date_format') == 'd/m/Y' ? 'selected' : ''; ?>>01/01/2024</option>
                            <option value="m/d/Y" <?php echo getSetting($conn, 'date_format') == 'm/d/Y' ? 'selected' : ''; ?>>01/01/2024</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Time Format</label>
                        <select name="time_format" class="form-control">
                            <option value="H:i" <?php echo getSetting($conn, 'time_format', 'H:i') == 'H:i' ? 'selected' : ''; ?>>24-hour (14:30)</option>
                            <option value="h:i A" <?php echo getSetting($conn, 'time_format') == 'h:i A' ? 'selected' : ''; ?>>12-hour (02:30 PM)</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Save General Settings</button>
            </form>
        </div>
    </div>
    
    <!-- Email Settings -->
    <div class="settings-panel <?php echo $active_tab == 'email' ? 'active' : ''; ?>" id="email-panel">
        <div class="card">
            <h2><i class="fas fa-envelope"></i> Email Settings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_email">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars(getSetting($conn, 'smtp_host', 'smtp-relay.brevo.com')); ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?php echo getSetting($conn, 'smtp_port', 587); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_user" class="form-control" value="<?php echo htmlspecialchars(getSetting($conn, 'smtp_user', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_pass" class="form-control" value="<?php echo htmlspecialchars(getSetting($conn, 'smtp_pass', '')); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="smtp_encryption" class="form-control">
                            <option value="tls" <?php echo getSetting($conn, 'smtp_encryption', 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo getSetting($conn, 'smtp_encryption') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo getSetting($conn, 'smtp_encryption') == 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="from_email" class="form-control" value="<?php echo htmlspecialchars(getSetting($conn, 'from_email', 'noreply@missiontech.edu')); ?>">
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="from_name" class="form-control" value="<?php echo htmlspecialchars(getSetting($conn, 'from_name', 'MissionTech College')); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Test Email</label>
                    <div class="form-row">
                        <input type="email" name="test_email" class="form-control" placeholder="Enter email to test" value="<?php echo $_SESSION['email']; ?>" style="flex: 1;">
                        <button type="submit" name="action" value="test_email" class="btn btn-secondary">Send Test Email</button>
                    </div>
                </div>
                <!-- Add this inside the email settings form, before the save button -->
                <div class="checkbox-group" style="margin-top: 1rem;">
                    <?php
                    $enable_emails = getSetting($conn, 'enable_emails', '1');
                    ?>
                    <input type="checkbox" name="enable_emails" id="enable_emails" value="1" <?php echo $enable_emails == '1' ? 'checked' : ''; ?>>
                    <label for="enable_emails">
                        <i class="fas fa-envelope"></i> Enable Email Notifications
                    </label>
                    <small class="help-text">When disabled, no emails will be sent from the system (registration, results, password reset, etc.)</small>
                </div>
                
                <button type="submit" name="action" value="update_email" class="btn btn-primary">Save Email Settings</button>
            </form>
        </div>
    </div>
    
    <!-- Security Settings -->
    <div class="settings-panel <?php echo $active_tab == 'security' ? 'active' : ''; ?>" id="security-panel">
        <div class="card">
            <h2><i class="fas fa-shield-alt"></i> Security Settings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_security">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Max Login Attempts</label>
                        <input type="number" name="max_login_attempts" class="form-control" value="<?php echo getSetting($conn, 'max_login_attempts', 5); ?>" min="1" max="20">
                        <small>Number of failed attempts before lockout</small>
                    </div>
                    <div class="form-group">
                        <label>Lockout Time (minutes)</label>
                        <input type="number" name="lockout_time" class="form-control" value="<?php echo getSetting($conn, 'lockout_time', 15); ?>" min="5" max="120">
                        <small>Minutes to lock account after max attempts</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Password Length</label>
                        <input type="number" name="password_min_length" class="form-control" value="<?php echo getSetting($conn, 'password_min_length', 6); ?>" min="6" max="20">
                    </div>
                    <div class="form-group">
                        <label>Session Timeout (minutes)</label>
                        <input type="number" name="session_timeout" class="form-control" value="<?php echo getSetting($conn, 'session_timeout', 30); ?>" min="5" max="480">
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="two_factor_auth" id="two_factor_auth" value="1" <?php echo getSetting($conn, 'two_factor_auth', 0) ? 'checked' : ''; ?>>
                    <label for="two_factor_auth">Enable Two-Factor Authentication</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="force_password_change" id="force_password_change" value="1" <?php echo getSetting($conn, 'force_password_change', 0) ? 'checked' : ''; ?>>
                    <label for="force_password_change">Force password change every 90 days</label>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Security Settings</button>
            </form>
        </div>
    </div>
    
    <!-- Appearance Settings -->
    <div class="settings-panel <?php echo $active_tab == 'appearance' ? 'active' : ''; ?>" id="appearance-panel">
        <div class="card">
            <h2><i class="fas fa-palette"></i> Appearance Settings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_appearance">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Primary Color</label>
                        <div class="color-picker">
                            <input type="color" name="primary_color" class="form-control" value="<?php echo getSetting($conn, 'primary_color', '#10b981'); ?>" style="height: 50px; padding: 5px;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Secondary Color</label>
                        <div class="color-picker">
                            <input type="color" name="secondary_color" class="form-control" value="<?php echo getSetting($conn, 'secondary_color', '#059669'); ?>" style="height: 50px; padding: 5px;">
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Font Size</label>
                        <select name="font_size" class="form-control">
                            <option value="small" <?php echo getSetting($conn, 'font_size', 'medium') == 'small' ? 'selected' : ''; ?>>Small</option>
                            <option value="medium" <?php echo getSetting($conn, 'font_size') == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="large" <?php echo getSetting($conn, 'font_size') == 'large' ? 'selected' : ''; ?>>Large</option>
                        </select>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="dark_mode" id="dark_mode" value="1" <?php echo getSetting($conn, 'dark_mode', 0) ? 'checked' : ''; ?>>
                    <label for="dark_mode">Enable Dark Mode</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="sidebar_collapsed" id="sidebar_collapsed" value="1" <?php echo getSetting($conn, 'sidebar_collapsed', 0) ? 'checked' : ''; ?>>
                    <label for="sidebar_collapsed">Collapse Sidebar by Default</label>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Appearance Settings</button>
            </form>
        </div>
    </div>
    
    <!-- Features Settings -->
    <div class="settings-panel <?php echo $active_tab == 'features' ? 'active' : ''; ?>" id="features-panel">
        <div class="card">
            <h2><i class="fas fa-rocket"></i> Features Settings</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_features">
                
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_registration" id="enable_registration" value="1" <?php echo getSetting($conn, 'enable_registration', 1) ? 'checked' : ''; ?>>
                    <label for="enable_registration">Enable Student Registration</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_public_notes" id="enable_public_notes" value="1" <?php echo getSetting($conn, 'enable_public_notes', 1) ? 'checked' : ''; ?>>
                    <label for="enable_public_notes">Enable Public Notes</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_forum" id="enable_forum" value="1" <?php echo getSetting($conn, 'enable_forum', 0) ? 'checked' : ''; ?>>
                    <label for="enable_forum">Enable Discussion Forum</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_certificates" id="enable_certificates" value="1" <?php echo getSetting($conn, 'enable_certificates', 1) ? 'checked' : ''; ?>>
                    <label for="enable_certificates">Enable Certificates on Completion</label>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Questions Per Page</label>
                        <select name="questions_per_page" class="form-control">
                            <option value="10" <?php echo getSetting($conn, 'questions_per_page', 20) == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo getSetting($conn, 'questions_per_page') == 20 ? 'selected' : ''; ?>>20</option>
                            <option value="30" <?php echo getSetting($conn, 'questions_per_page') == 30 ? 'selected' : ''; ?>>30</option>
                            <option value="50" <?php echo getSetting($conn, 'questions_per_page') == 50 ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Max Attempts Per Exam</label>
                        <select name="max_attempts_per_exam" class="form-control">
                            <option value="1" <?php echo getSetting($conn, 'max_attempts_per_exam', 3) == 1 ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo getSetting($conn, 'max_attempts_per_exam') == 2 ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo getSetting($conn, 'max_attempts_per_exam') == 3 ? 'selected' : ''; ?>>3</option>
                            <option value="5" <?php echo getSetting($conn, 'max_attempts_per_exam') == 5 ? 'selected' : ''; ?>>5</option>
                            <option value="0" <?php echo getSetting($conn, 'max_attempts_per_exam') == 0 ? 'selected' : ''; ?>>Unlimited</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Default Passing Score (%)</label>
                        <input type="number" name="default_pass_score" class="form-control" value="<?php echo getSetting($conn, 'default_pass_score', 70); ?>" min="0" max="100">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Feature Settings</button>
            </form>
        </div>
    </div>
    
    <!-- Backup Settings -->
    <div class="settings-panel <?php echo $active_tab == 'backup' ? 'active' : ''; ?>" id="backup-panel">
        <div class="card">
            <h2><i class="fas fa-database"></i> Backup & Restore</h2>
            
            <div class="backup-actions">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Create New Backup
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-trash"></i> Clear Cache
                    </button>
                </form>
            </div>
            
            <div class="backup-list">
                <h3>Available Backups</h3>
                <?php if (empty($backup_files)): ?>
                    <div class="empty-state">
                        <i class="fas fa-database"></i>
                        <p>No backups found. Click "Create New Backup" to create one.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                               <tr>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Date</th>
                                <th>Actions</th>
                               </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backup_files as $file): 
                                $filepath = '../backups/' . $file;
                                if (file_exists($filepath)) {
                                    $size = filesize($filepath);
                                    $size_str = $size < 1024 ? $size . ' B' : ($size < 1048576 ? round($size / 1024, 2) . ' KB' : round($size / 1048576, 2) . ' MB');
                                } else {
                                    $size_str = 'N/A';
                                }
                            ?>
                               <tr>
                                   <td><?php echo htmlspecialchars($file); ?></td>
                                   <td><?php echo $size_str; ?></td>
                                   <td><?php echo file_exists($filepath) ? date('M j, Y H:i:s', filemtime($filepath)) : 'N/A'; ?></td>
                                   <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Restore from this backup? This will overwrite current data.');">
                                        <input type="hidden" name="action" value="backup">
                                        <input type="hidden" name="backup_file" value="<?php echo $file; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">Restore</button>
                                    </form>
                                    <a href="../backups/<?php echo $file; ?>" download class="btn btn-sm btn-secondary">Download</a>
                                   </td>
                               </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Backup Information:</strong><br>
                    - Backups include all database tables and structure<br>
                    - Store backups in a secure location<br>
                    - Regular backups are recommended before major changes
                </div>
            </div>
        </div>
    </div>
    
    <!-- Developer Settings -->
    <div class="settings-panel <?php echo $active_tab == 'developer' ? 'active' : ''; ?>" id="developer-panel">
        <div class="card">
            <h2><i class="fas fa-code"></i> Developer Settings</h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_developer">
                
                <!-- Background Images Section -->
                <h3 style="margin-top: 1rem; margin-bottom: 1rem; color: #10b981;">
                    <i class="fas fa-image"></i> Background Images
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Student Login Background</label>
                        <?php if ($student_bg_display && file_exists('../' . $student_bg_display)): ?>
                            <div class="image-preview">
                                <img src="../<?php echo $student_bg_display; ?>" alt="Current Background" style="max-width: 200px; border-radius: 8px; margin-bottom: 10px; border: 2px solid #10b981;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="student_login_bg" class="form-control" accept="image/*">
                        <small>Current: <?php echo basename($student_bg_display); ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Login Background</label>
                        <?php if ($admin_bg_display && file_exists('../' . $admin_bg_display)): ?>
                            <div class="image-preview">
                                <img src="../<?php echo $admin_bg_display; ?>" alt="Current Background" style="max-width: 200px; border-radius: 8px; margin-bottom: 10px; border: 2px solid #f59e0b;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="admin_login_bg" class="form-control" accept="image/*">
                        <small>Current: <?php echo basename($admin_bg_display); ?></small>
                    </div>
                </div>
                
                <!-- Custom Code Section -->
                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #10b981;">
                    <i class="fas fa-code"></i> Custom Code
                </h3>
                <div class="form-group">
                    <label>Custom CSS</label>
                    <textarea name="custom_css" class="form-control" rows="5" placeholder="/* Add your custom CSS here */"><?php echo htmlspecialchars(getDevSetting($conn, 'custom_css', '')); ?></textarea>
                    <small>Add custom CSS to override default styles</small>
                </div>
                
                <div class="form-group">
                    <label>Custom JavaScript</label>
                    <textarea name="custom_js" class="form-control" rows="5" placeholder="// Add your custom JavaScript here"><?php echo htmlspecialchars(getDevSetting($conn, 'custom_js', '')); ?></textarea>
                    <small>Add custom JavaScript for additional functionality</small>
                </div>
                
                <!-- System Configuration -->
                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #10b981;">
                    <i class="fas fa-cog"></i> System Configuration
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Debug Mode</label>
                        <select name="debug_mode" class="form-control">
                            <option value="0" <?php echo getDevSetting($conn, 'debug_mode', '0') == '0' ? 'selected' : ''; ?>>Disabled</option>
                            <option value="1" <?php echo getDevSetting($conn, 'debug_mode') == '1' ? 'selected' : ''; ?>>Enabled</option>
                        </select>
                        <small>Enable to show detailed error messages</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Maintenance Mode</label>
                        <select name="maintenance_mode" class="form-control">
                            <option value="0" <?php echo getDevSetting($conn, 'maintenance_mode', '0') == '0' ? 'selected' : ''; ?>>Disabled</option>
                            <option value="1" <?php echo getDevSetting($conn, 'maintenance_mode') == '1' ? 'selected' : ''; ?>>Enabled</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Maintenance Message</label>
                    <textarea name="maintenance_message" class="form-control" rows="3"><?php echo htmlspecialchars(getDevSetting($conn, 'maintenance_message', 'System is under maintenance. Please check back later.')); ?></textarea>
                </div>
                
                <!-- Performance Settings -->
                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #10b981;">
                    <i class="fas fa-tachometer-alt"></i> Performance
                </h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cache Enabled</label>
                        <select name="cache_enabled" class="form-control">
                            <option value="0" <?php echo getDevSetting($conn, 'cache_enabled', '1') == '0' ? 'selected' : ''; ?>>Disabled</option>
                            <option value="1" <?php echo getDevSetting($conn, 'cache_enabled') == '1' ? 'selected' : ''; ?>>Enabled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Cache Lifetime (seconds)</label>
                        <input type="number" name="cache_lifetime" class="form-control" value="<?php echo getDevSetting($conn, 'cache_lifetime', '3600'); ?>" min="60" max="86400">
                        <small>3600 = 1 hour, 86400 = 24 hours</small>
                    </div>
                </div>
                
                <!-- Footer Settings -->
                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color: #10b981;">
                    <i class="fas fa-copyright"></i> Footer Settings
                </h3>
                <div class="form-group">
                    <label>Footer Text</label>
                    <input type="text" name="footer_text" class="form-control" value="<?php echo htmlspecialchars(getDevSetting($conn, 'footer_text', '© 2024 MissionTech College. All rights reserved.')); ?>">
                </div>
                
                <div class="form-group">
                    <label>Footer About Text</label>
                    <textarea name="footer_about" class="form-control" rows="3"><?php echo htmlspecialchars(getDevSetting($conn, 'footer_about', 'Online Examination System for MissionTech College')); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Developer Settings</button>
            </form>
            
            <!-- Cache Management Info -->
            <div class="info-box" style="margin-top: 2rem;">
                <i class="fas fa-database"></i>
                <div>
                    <strong>Cache Management:</strong><br>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_cache">
                        <button type="submit" class="btn btn-sm btn-secondary">Clear All Cache</button>
                    </form>
                    <span style="margin-left: 10px;">Clear cached files to apply new changes</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* All existing styles remain the same... */
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
.page-header p {
    color: #64748b;
    margin-top: 0.5rem;
}

.settings-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
}

.settings-tab {
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

.settings-tab:hover {
    background: #f1f5f9;
    color: #10b981;
}

.settings-tab.active {
    background: #10b981;
    color: white;
}

.settings-panel {
    display: none;
}

.settings-panel.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
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

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1rem;
    padding: 0.5rem 0;
}

.checkbox-group input {
    width: 18px;
    height: 18px;
    accent-color: #10b981;
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
    font-size: 0.95rem;
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
    background: #f8fafc;
    border-color: #10b981;
    color: #10b981;
}

.btn-warning {
    background: #f59e0b;
    color: white;
}

.btn-warning:hover {
    background: #d97706;
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

.backup-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.backup-list {
    margin-top: 2rem;
}

.backup-list h3 {
    margin-bottom: 1rem;
    color: #1e293b;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eef2f6;
}

.table th {
    background: #f8fafc;
    font-weight: 600;
    color: #475569;
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

.info-box {
    background: #e0f2fe;
    padding: 1rem;
    border-radius: 12px;
    margin-top: 2rem;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    color: #0284c7;
}

.image-preview {
    margin-bottom: 10px;
}

.image-preview img {
    max-width: 200px;
    border-radius: 8px;
    border: 2px solid #e2e8f0;
    padding: 4px;
}

.color-picker input {
    cursor: pointer;
}

@media (max-width: 768px) {
    .settings-tabs {
        flex-direction: column;
    }
    
    .settings-tab {
        text-align: center;
        justify-content: center;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .backup-actions {
        flex-direction: column;
    }
    
    .table {
        font-size: 0.8rem;
    }
    
    .table th, .table td {
        padding: 0.5rem;
    }
}
</style>

<script>
// Tab switching
document.querySelectorAll('.settings-tab').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        const tabId = this.getAttribute('href').split('=')[1];
        
        // Update active tab
        document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        // Update active panel
        document.querySelectorAll('.settings-panel').forEach(panel => panel.classList.remove('active'));
        document.getElementById(tabId + '-panel').classList.add('active');
        
        // Update URL without reload
        history.pushState(null, '', '?tab=' + tabId);
    });
});

// Live color preview
const primaryColor = document.querySelector('input[name="primary_color"]');
const secondaryColor = document.querySelector('input[name="secondary_color"]');

if (primaryColor) {
    primaryColor.addEventListener('input', function() {
        document.documentElement.style.setProperty('--primary', this.value);
    });
}

if (secondaryColor) {
    secondaryColor.addEventListener('input', function() {
        document.documentElement.style.setProperty('--primary-dark', this.value);
    });
}

// Auto-hide alerts
setTimeout(() => {
    document.querySelectorAll('.alert').forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);
</script>

<?php include '../includes/footer.php'; ?>