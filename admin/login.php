<?php
/**
 * Admin Login Page
 * Online Examination System - MissionTech College
 */

// Initialize admin session
session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/email.php';

// No auto-redirects - allow admin login even if student is logged in elsewhere
// Just check if THIS session is already logged in as admin
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Get developer settings for background image
$admin_bg = 'uploads/background-Image/admin-bg.jpg'; // Default
$custom_css = '';
$custom_js = '';

// Fetch developer settings if table exists
try {
    $db_check = Database::getInstance();
    $conn_check = $db_check->getConnection();
    
    // Check if developer_settings table exists
    $table_check = $conn_check->query("SHOW TABLES LIKE 'developer_settings'");
    if ($table_check->num_rows > 0) {
        // Get admin login background
        $bg_result = $conn_check->query("SELECT setting_value FROM developer_settings WHERE setting_key = 'admin_login_bg'");
        if ($bg_result && $bg_result->num_rows > 0) {
            $bg_row = $bg_result->fetch_assoc();
            if (!empty($bg_row['setting_value'])) {
                $admin_bg = $bg_row['setting_value'];
            }
        }
        
        // Get custom CSS
        $css_result = $conn_check->query("SELECT setting_value FROM developer_settings WHERE setting_key = 'custom_css'");
        if ($css_result && $css_result->num_rows > 0) {
            $css_row = $css_result->fetch_assoc();
            $custom_css = $css_row['setting_value'];
        }
        
        // Get custom JS
        $js_result = $conn_check->query("SELECT setting_value FROM developer_settings WHERE setting_key = 'custom_js'");
        if ($js_result && $js_result->num_rows > 0) {
            $js_row = $js_result->fetch_assoc();
            $custom_js = $js_row['setting_value'];
        }
        
        // Check maintenance mode
        $maintenance_result = $conn_check->query("SELECT setting_value FROM developer_settings WHERE setting_key = 'maintenance_mode'");
        if ($maintenance_result && $maintenance_result->num_rows > 0) {
            $maintenance_row = $maintenance_result->fetch_assoc();
            if ($maintenance_row['setting_value'] == '1') {
                $maintenance_msg_result = $conn_check->query("SELECT setting_value FROM developer_settings WHERE setting_key = 'maintenance_message'");
                $maintenance_msg = "System is under maintenance. Please check back later.";
                if ($maintenance_msg_result && $maintenance_msg_result->num_rows > 0) {
                    $msg_row = $maintenance_msg_result->fetch_assoc();
                    $maintenance_msg = $msg_row['setting_value'];
                }
                die("<div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'><h1>🔧 Maintenance Mode</h1><p>" . htmlspecialchars($maintenance_msg) . "</p></div>");
            }
        }
    }
} catch (Exception $e) {
    // Table doesn't exist yet, use defaults
    error_log("Developer settings table not found: " . $e->getMessage());
}

$error = '';
$email = '';
$success = '';

$site_name = 'MissionTech College';

if (isset($_GET['reset_success']) && $_GET['reset_success'] == 1) {
    $success = "Password reset successful! Please login with your new password.";
}
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Admin account created! Please login.";
}
if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $success = "You have been successfully logged out.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT id, username, email, password, full_name, role, status, avatar FROM users WHERE email = ? AND role = 'admin'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] != 1) {
                $errors[] = "Your account is not active. Please contact the super administrator.";
                logActivity(0, 'admin_login_failed', "Inactive admin account login attempt for: $email");
            } elseif (password_verify($password, $user['password'])) {
                // Clear this session before setting new data
                session_unset();
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                logActivity($user['id'], 'admin_login', "Admin logged in successfully");
                
                // Send login notification email
                sendAdminLoginNotificationEmail(
                    $user['email'], 
                    $user['full_name'],
                    $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    date('F j, Y \a\t g:i A')
                );
                
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (30 * 24 * 60 * 60);
                    setcookie('admin_remember_token', $token, $expires, '/', '', true, true);
                    
                    $token_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $token_stmt->bind_param("si", $token, $user['id']);
                    $token_stmt->execute();
                    $token_stmt->close();
                }
                
                header('Location: dashboard.php');
                exit();
            } else {
                $errors[] = "Invalid email or password";
                logActivity(0, 'admin_login_failed', "Invalid password for admin: $email");
            }
        } else {
            $errors[] = "Invalid email or password";
            logActivity(0, 'admin_login_failed', "Admin not found: $email");
        }
        $stmt->close();
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}

/**
 * Send admin login notification email
 */
function sendAdminLoginNotificationEmail($email, $name, $ip_address, $user_agent, $login_time) {
    $subject = "🔐 Admin Login Alert - MissionTech College";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Admin Login Notification</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 16px 16px 0 0; }
            .header h1 { margin: 0; font-size: 24px; }
            .header p { margin: 10px 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 30px; background: #ffffff; border: 1px solid #eef2f6; border-top: none; border-radius: 0 0 16px 16px; }
            .info-box { background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            .info-item { padding: 10px 0; border-bottom: 1px solid #eef2f6; }
            .info-item:last-child { border-bottom: none; }
            .label { font-weight: 600; color: #1e293b; width: 120px; display: inline-block; }
            .value { color: #475569; }
            .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            .button { display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-radius: 0 0 16px 16px; border-top: 1px solid #eef2f6; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔐 Admin Login Alert</h1>
                <p>MissionTech College Online Examination System</p>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your administrator account was just logged into the system.</p>
                
                <div class="info-box">
                    <div class="info-item">
                        <span class="label">📧 Email:</span>
                        <span class="value">' . htmlspecialchars($email) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="label">🕐 Time:</span>
                        <span class="value">' . $login_time . '</span>
                    </div>
                    <div class="info-item">
                        <span class="label">🌐 IP Address:</span>
                        <span class="value">' . htmlspecialchars($ip_address) . '</span>
                    </div>
                    <div class="info-item">
                        <span class="label">💻 Browser:</span>
                        <span class="value">' . htmlspecialchars(substr($user_agent, 0, 100)) . '</span>
                    </div>
                </div>
                
                <div class="warning">
                    <strong>⚠️ Security Notice:</strong><br>
                    If this was you, you can safely ignore this email.<br>
                    If this wasn\'t you, please change your password immediately and contact the system administrator.
                </div>
                
                <p style="text-align: center;">
                    <a href="http://localhost/online_exam_system/admin/change-password.php" class="button">Change Password</a>
                </p>
                
                <p>Best regards,<br><strong>MissionTech College Security Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' MissionTech College. All rights reserved.</p>
                <p>This is an automated security notification.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($email, $subject, $message, $name);
}

function getAdminGreeting() {
    $hour = (int)date('H');
    if ($hour < 12) return "Good Morning";
    if ($hour < 17) return "Good Afternoon";
    return "Good Evening";
}

$page_title = 'Admin Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            min-height: 100vh;
        }

        .login-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .login-left {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            padding: 2rem;
        }

        .login-card {
            max-width: 450px;
            width: 100%;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo i {
            font-size: 3rem;
            color: #f59e0b;
            margin-bottom: 0.5rem;
        }

        .login-logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
        }

        .login-logo p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .login-greeting {
            margin-bottom: 2rem;
        }

        .login-greeting h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .login-greeting p {
            color: #64748b;
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background: #fef2f2;
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }

        .alert-success {
            background: #ecfdf5;
            color: #10b981;
            border-left: 4px solid #10b981;
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

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 8px;
        }

        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #64748b;
            font-size: 0.9rem;
        }

        .checkbox-label input {
            width: 18px;
            height: 18px;
            accent-color: #f59e0b;
        }

        .forgot-link {
            color: #f59e0b;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            margin-bottom: 1.5rem;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.4);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .footer-links {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .footer-links a {
            color: #64748b;
            text-decoration: none;
            margin: 0 0.5rem;
        }

        .footer-links a:hover {
            color: #f59e0b;
        }

        .login-right {
            flex: 1;
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('../<?php echo $admin_bg; ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .login-right::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 0;
        }

        .admin-content {
            position: relative;
            z-index: 1;
            color: white;
            max-width: 500px;
            text-align: center;
        }

        .admin-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .admin-content h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .admin-content p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .feature-list {
            text-align: left;
            margin-top: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 40px;
            backdrop-filter: blur(5px);
        }

        .feature-item i {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f59e0b;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 968px) {
            .login-right {
                display: none;
            }
            .login-left {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 0 1rem;
            }
            .login-greeting h2 {
                font-size: 1.5rem;
            }
            .login-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
        
        /* Custom CSS from developer settings */
        <?php echo $custom_css; ?>
    </style>
    <?php if (!empty($custom_js)): ?>
    <script>
        <?php echo $custom_js; ?>
    </script>
    <?php endif; ?>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-left">
            <div class="login-card">
                <div class="login-logo">
                    <i class="fas fa-shield-alt"></i>
                    <h1>Admin Portal</h1>
                    <p><?php echo htmlspecialchars($site_name); ?></p>
                </div>
                
                <div class="login-greeting">
                    <h2><?php echo getAdminGreeting(); ?>!</h2>
                    <p>Access the administration dashboard.</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="loginForm">
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="email" class="form-control" placeholder="admin@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="login-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginBtn">
                        Login to Dashboard <i class="fas fa-arrow-right"></i>
                    </button>
                    
                </form>
            </div>
        </div>
        
        <div class="login-right">
            <div class="admin-content">
                <div class="admin-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2>Administrator Access</h2>
                <p>Manage students, exams, questions, and monitor system performance from a centralized dashboard.</p>
                
                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Manage Students & Staff</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-question-circle"></i>
                        <span>Create & Edit Questions</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Schedule and Monitor Exams</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>View Analytics & Reports</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-cog"></i>
                        <span>Configure System Settings</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const icon = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function validateForm() {
            const email = document.querySelector('input[name="email"]').value.trim();
            const password = document.getElementById('password').value;
            
            if (!email) {
                alert('Please enter your email address');
                return false;
            }
            
            if (!password) {
                alert('Please enter your password');
                return false;
            }
            
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.innerHTML = '<span class="spinner"></span> Logging in...';
            loginBtn.disabled = true;
            
            return true;
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (validateForm()) {
                this.submit();
            }
        });

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 5000);
            });
        }, 5000);
    </script>
</body>
</html>