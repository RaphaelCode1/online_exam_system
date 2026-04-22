<?php
/**
 * Student Login Page
 * Online Examination System - MissionTech College
 */

// Initialize student session
session_name('STUDENT_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/email.php';

// No auto-redirects - allow student login even if admin is logged in elsewhere
// Just check if THIS session is already logged in as student
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header('Location: dashboard.php');
    exit();
}

// Get developer settings for background image
$student_bg = 'uploads/background-Image/student-bg.jpg'; // Default
$custom_css = '';
$custom_js = '';

// Fetch developer settings if table exists
try {
    $db_check = Database::getInstance();
    $conn_check = $db_check->getConnection();
    
    // Check if developer_settings table exists
    $table_check = $conn_check->query("SHOW TABLES LIKE 'developer_settings'");
    if ($table_check->num_rows > 0) {
        // Get student login background
        $bg_result = $conn_check->query("SELECT setting_value FROM developer_settings WHERE setting_key = 'student_login_bg'");
        if ($bg_result && $bg_result->num_rows > 0) {
            $bg_row = $bg_result->fetch_assoc();
            if (!empty($bg_row['setting_value'])) {
                $student_bg = $bg_row['setting_value'];
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
$enable_registration = true;

if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = "Registration successful! Please login with your credentials.";
}
if (isset($_GET['loggedout']) && $_GET['loggedout'] == 1) {
    $success = "You have been successfully logged out.";
}
if (isset($_GET['reset_success']) && $_GET['reset_success'] == 1) {
    $success = "Password reset successful! Please login with your new password.";
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
        
        $stmt = $conn->prepare("SELECT id, username, email, password, full_name, role, status, avatar FROM users WHERE email = ? AND role = 'student'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] != 1) {
                $errors[] = "Your account is not active. Please contact the administrator.";
                logActivity(0, 'student_login_failed', "Inactive student account login attempt for: $email");
            } elseif (password_verify($password, $user['password'])) {
                // Clear this session before setting new data
                session_unset();
                
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                logActivity($user['id'], 'student_login', "Student logged in successfully");
                
                sendLoginNotificationEmail(
    $user['email'], 
    $user['full_name'],
    $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    date('F j, Y \a\t g:i A')
);
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = time() + (30 * 24 * 60 * 60);
                    setcookie('student_remember_token', $token, $expires, '/', '', true, true);
                    
                    $token_stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $token_stmt->bind_param("si", $token, $user['id']);
                    $token_stmt->execute();
                    $token_stmt->close();
                }
                
                header('Location: dashboard.php');
                exit();
            } else {
                $errors[] = "Invalid email or password";
                logActivity(0, 'student_login_failed', "Invalid password for student: $email");
            }
        } else {
            $errors[] = "Invalid email or password";
            logActivity(0, 'student_login_failed', "Student not found: $email");
        }
        $stmt->close();
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}


function getStudentGreeting() {
    $hour = (int)date('H');
    if ($hour < 12) return "Good Morning";
    if ($hour < 17) return "Good Afternoon";
    return "Good Evening";
}

$page_title = 'Student Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - <?php echo htmlspecialchars($site_name); ?></title>
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
            background: #f8fafc;
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
            color: #10b981;
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
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
            accent-color: #10b981;
        }

        .forgot-link {
            color: #10b981;
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .register-link {
            text-align: center;
            color: #64748b;
        }

        .register-link a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
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
            color: #10b981;
        }

        .login-right {
            flex: 1;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('../<?php echo $student_bg; ?>');
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

        .hero-content {
            position: relative;
            z-index: 1;
            color: white;
            max-width: 500px;
            text-align: center;
        }

        .hero-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .hero-content h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .hero-content p {
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
            background: white;
            color: #10b981;
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
                    <i class="fas fa-graduation-cap"></i>
                    <h1>Student Portal</h1>
                    <p><?php echo htmlspecialchars($site_name); ?></p>
                </div>
                
                <div class="login-greeting">
                    <h2><?php echo getStudentGreeting(); ?>!</h2>
                    <p>Welcome back! Login to continue your learning journey.</p>
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
                            <input type="email" name="email" class="form-control" placeholder="student@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
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
                    
                    <?php if ($enable_registration): ?>
                    <div class="register-link">
                        Don't have an account? <a href="register.php">Register here</a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="footer-links">
                        <a href="../student/index.php">Home</a> |
                        <a href="../student/privacy.php">Privacy Policy</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="login-right">
            <div class="hero-content">
                <div class="hero-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2>Welcome to <?php echo htmlspecialchars($site_name); ?></h2>
                <p>Your gateway to excellence in education. Take tests, track progress, and achieve your academic goals.</p>
                
                <div class="feature-list">
                    <div class="feature-item">
                        <i class="fas fa-file-alt"></i>
                        <span>Access hundreds of practice tests</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Track your performance over time</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-trophy"></i>
                        <span>Compete with peers on leaderboard</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bookmark"></i>
                        <span>Bookmark questions for later review</span>
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