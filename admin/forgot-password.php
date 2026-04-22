<?php
/**
 * Admin Forgot Password Page - Code Based Verification
 * Online Examination System
 */

session_start();

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// No permission check needed - this is a public page
// But ensure user is not already logged in
if (isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$email = '';

$db = Database::getInstance();
$conn = $db->getConnection();

// Create password_resets table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Step 1: Request reset code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_code') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, username FROM users WHERE email = ? AND role = 'admin'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            // Generate 6-digit code
            $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            // Delete any existing unused codes for this email
            $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ? AND used = 0");
            $delete_stmt->bind_param("s", $email);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Store the code
            $insert_stmt = $conn->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
            $insert_stmt->bind_param("sss", $email, $code, $expires);
            $insert_stmt->execute();
            $insert_stmt->close();
            
            // Send email with code
            $email_sent = sendAdminPasswordResetCodeEmail($email, $user['full_name'], $code);
            
            if ($email_sent) {
                $_SESSION['admin_reset_email'] = $email;
                $step = 2;
                $success = "A 6-digit verification code has been sent to your email address.";
            } else {
                $error = "Failed to send email. Please try again or contact support.";
            }
        } else {
            $error = "No admin account found with this email address";
        }
    }
}

// Step 2: Verify code and reset password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $code = trim($_POST['code'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['admin_reset_email'] ?? '';
    
    if (empty($code)) {
        $error = "Please enter the verification code";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Verify the code
        $verify_stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND code = ? AND used = 0 AND expires_at > NOW()");
        $verify_stmt->bind_param("ss", $email, $code);
        $verify_stmt->execute();
        $result = $verify_stmt->get_result();
        $reset = $result->fetch_assoc();
        
        if ($reset) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $update_stmt->bind_param("ss", $hashed_password, $email);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Mark code as used
            $mark_stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $mark_stmt->bind_param("i", $reset['id']);
            $mark_stmt->execute();
            $mark_stmt->close();
            
            // Send confirmation email
            sendAdminPasswordResetSuccessEmail($email, $reset['email'], 'Admin');
            
            // Clear session
            unset($_SESSION['admin_reset_email']);
            
            $step = 3;
            $success = "Password reset successful! You can now login with your new password.";
        } else {
            $error = "Invalid or expired verification code. Please request a new code.";
        }
        $verify_stmt->close();
    }
}

$page_title = 'Admin Forgot Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Forgot Password</title>
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
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            max-width: 500px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo i {
            font-size: 3rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
        }

        .logo p {
            color: #64748b;
            font-size: 0.9rem;
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

        .form-control {
            width: 100%;
            padding: 12px 16px;
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

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #64748b;
        }

        .btn-outline:hover {
            border-color: #f59e0b;
            color: #f59e0b;
        }

        .code-input {
            text-align: center;
            font-size: 1.2rem;
            letter-spacing: 4px;
            font-family: monospace;
        }

        .info-box {
            background: #e0f2fe;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            color: #0284c7;
        }

        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link a {
            color: #f59e0b;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
                <h1>Admin Password Reset</h1>
                <p><?php echo $step == 1 ? 'Enter your email to receive a verification code' : 'Enter the code sent to your email'; ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Step 1: Request Code -->
            <?php if ($step == 1): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="request_code">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@example.com" required value="<?php echo htmlspecialchars($email); ?>">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Step 2: Enter Code and New Password -->
            <?php if ($step == 2): ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                
                <?php if (isset($_SESSION['admin_reset_email'])): ?>
                <div class="info-box">
                    <i class="fas fa-envelope"></i> Code sent to: <strong><?php echo htmlspecialchars($_SESSION['admin_reset_email']); ?></strong>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Verification Code</label>
                    <input type="text" name="code" class="form-control code-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" required autocomplete="off">
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </form>
            
            <div class="back-link">
                <a href="forgot-password.php">Request New Code</a>
            </div>
            <?php endif; ?>
            
            <!-- Step 3: Success -->
            <?php if ($step == 3): ?>
            <div style="text-align: center;">
                <i class="fas fa-check-circle" style="font-size: 4rem; color: #10b981; margin-bottom: 1rem;"></i>
                <p style="margin: 1rem 0;"><?php echo $success; ?></p>
                <a href="login.php" class="btn btn-primary" style="display: inline-block; text-align: center; text-decoration: none;">
                    <i class="fas fa-arrow-right"></i> Go to Login
                </a>
            </div>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>