<?php
/**
 * Helper Functions with Brevo Email Integration - COMPLETE
 */

// Include environment loader
require_once __DIR__ . '/../config/loadenv.php';

// Include database configuration only when needed
function getDatabase() {
    require_once __DIR__ . '/../config/database.php';
    return Database::getInstance();
}

/**
 * Check if email notifications are enabled globally
 * @return bool True if emails are enabled, false otherwise
 */
function isEmailEnabled() {
    // First check .env setting
    if (defined('ENABLE_EMAILS') && ENABLE_EMAILS == '0') {
        return false;
    }
    
    // Then check database setting
    try {
        $db = getDatabase();
        $conn = $db->getConnection();
        
        $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'enable_emails'");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return ($row['setting_value'] == '1');
        }
        
        // Default to disabled for security
        return false;
    } catch (Exception $e) {
        error_log("Error checking email status: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if Gemini AI is configured and enabled
 * @return bool True if Gemini is available, false otherwise
 */
function isGeminiAvailable() {
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY) || GEMINI_API_KEY == 'your_gemini_api_key_here') {
        return false;
    }
    return true;
}

/**
 * Get Gemini API response with fallback message
 */
function getGeminiResponse($prompt, $options = []) {
    if (!isGeminiAvailable()) {
        return "⚠️ AI features are currently disabled. To enable, please add your Google Gemini API key to the .env file.\n\nInstructions:\n1. Go to https://makersuite.google.com/app/apikey\n2. Create an API key\n3. Add GEMINI_API_KEY=your_key_here to config/.env";
    }
    
    if (function_exists('generateWithGemini')) {
        return generateWithGemini($prompt, $options);
    }
    
    return "⚠️ Gemini API configuration not found. Please check your installation.";
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $time);
    }
}

function formatDate($date, $format = 'M j, Y H:i') {
    if (empty($date)) return 'Never';
    return date($format, strtotime($date));
}

function getDifficultyBadge($difficulty) {
    $badges = [
        'easy' => '<span class="badge bg-success">Easy</span>',
        'medium' => '<span class="badge bg-warning">Medium</span>',
        'hard' => '<span class="badge bg-danger">Hard</span>'
    ];
    return $badges[$difficulty] ?? '<span class="badge bg-secondary">Unknown</span>';
}

function getStatusBadge($status, $type = 'active') {
    if ($type === 'active') {
        return $status ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
    }
    return $status;
}

function sendNotification($user_id, $title, $message, $type = 'info') {
    try {
        $db = getDatabase();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $message, $type);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

function logActivity($user_id, $action, $details = null) {
    try {
        $db = getDatabase();
        $conn = $db->getConnection();
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $action, $details, $ip);
        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using Brevo (Sendinblue) API - with global toggle
 */
function sendEmail($to, $subject, $message, $name = '') {
    // Check if emails are enabled globally
    if (!isEmailEnabled()) {
        error_log("Email not sent - email notifications are disabled. To: $to, Subject: $subject");
        return true; // Return true to prevent errors in calling code
    }
    
    // Check if API key is configured
    if (!defined('BREVO_API_KEY') || empty(BREVO_API_KEY) || BREVO_API_KEY == 'your_brevo_api_key_here') {
        error_log("Email not sent - Brevo API key not configured. Please add your API key to .env file");
        return false;
    }
    
    // Brevo API Configuration
    $api_key = BREVO_API_KEY;
    $sender_email = defined('BREVO_SENDER_EMAIL') ? BREVO_SENDER_EMAIL : 'noreply@yourapp.com';
    $sender_name = defined('BREVO_SENDER_NAME') ? BREVO_SENDER_NAME : 'Online Exam System';
    
    $email_data = [
        'sender' => [
            'name' => $sender_name,
            'email' => $sender_email
        ],
        'to' => [
            [
                'email' => $to,
                'name' => $name ?: ucfirst(explode('@', $to)[0])
            ]
        ],
        'subject' => $subject,
        'htmlContent' => $message
    ];
    
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.brevo.com/v3/smtp/email',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . $api_key,
            'content-type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($email_data),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    ($ch);
    
    if ($http_code == 201) {
        error_log("Email sent successfully to: $to");
        return true;
    } else {
        error_log("Email failed to: $to - HTTP Code: $http_code - Response: $response - Error: $curl_error");
        return false;
    }
}

/**
 * Send password reset code email
 */
function sendPasswordResetCodeEmail($to, $name, $code) {
    $subject = "Password Reset Code - Online Exam System";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Password Reset Code</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .code-box { background: #e5e7eb; padding: 20px; text-align: center; font-size: 36px; font-weight: bold; letter-spacing: 8px; border-radius: 12px; margin: 20px 0; font-family: monospace; }
            .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔑 Password Reset Code</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>We received a request to reset your password for your account.</p>
                
                <div class="code-box">
                    ' . $code . '
                </div>
                
                <div class="warning">
                    <strong>⚠️ Important:</strong> This code will expire in 30 minutes.
                    If you did not request this password reset, please ignore this email.
                </div>
                
                <p>Enter this code on the password reset page to create a new password.</p>
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send password reset success email
 */
function sendPasswordResetSuccessEmail($to, $name, $username, $new_password) {
    $subject = "Password Reset Successful - Online Exam System";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Password Reset Successful</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .credentials { background: #e5e7eb; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔑 Password Reset Successful</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your password has been reset successfully.</p>
                
                <div class="credentials">
                    <strong>Your New Account Details:</strong><br>
                    <strong>Username:</strong> ' . htmlspecialchars($username) . '<br>
                    <strong>New Password:</strong> ' . htmlspecialchars($new_password) . '<br>
                </div>
                
                <p>Please login and change your password immediately for security reasons.</p>
                <p style="text-align: center;">
                    <a href="' . SITE_URL . '/login.php" class="button">Login Now</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send password change notification email
 */
function sendPasswordChangeNotificationEmail($to, $name, $ip_address, $change_time) {
    $subject = "Password Changed - Online Exam System";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Password Changed</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .info-box { background: #e5e7eb; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔒 Password Changed</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your password was successfully changed on ' . htmlspecialchars($change_time) . '.</p>
                
                <div class="info-box">
                    <strong>Change Details:</strong><br>
                    <strong>IP Address:</strong> ' . htmlspecialchars($ip_address) . '<br>
                    <strong>Time:</strong> ' . htmlspecialchars($change_time) . '<br>
                </div>
                
                <div class="warning">
                    <strong>⚠️ Didn\'t make this change?</strong><br>
                    If you did not change your password, please contact us immediately to secure your account.
                </div>
                
                <p style="text-align: center;">
                    <a href="mailto:support@yourapp.com" class="button">Contact Support</a>
                    <a href="' . SITE_URL . '/forgot-password.php" class="button" style="background: #6b7280;">Reset Password</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send welcome email to new users
 */
function sendWelcomeEmail($to, $name, $username, $password = null) {
    $subject = "Welcome to Online Exam System!";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Welcome!</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .credentials { background: #e5e7eb; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Welcome to Online Exam System!</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Thank you for registering! We\'re excited to have you on board!</p>
                
                <div class="credentials">
                    <strong>Your Account Details:</strong><br>
                    <strong>Username:</strong> ' . htmlspecialchars($username) . '<br>
                    <strong>Email:</strong> ' . htmlspecialchars($to) . '<br>
                    ' . ($password ? "<strong>Temporary Password:</strong> " . htmlspecialchars($password) . "<br>" : "") . '
                </div>
                
                <p>You can now login to your account and start taking exams.</p>
                <p style="text-align: center;">
                    <a href="' . SITE_URL . '/student/login.php" class="button">Login to Your Account</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send login notification email
 */
function sendLoginNotificationEmail($to, $name, $ip_address, $user_agent, $login_time) {
    $subject = "New Login to Your Account";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>New Login Notification</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .info-box { background: #e5e7eb; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            .button { display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔐 New Login Detected</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>We detected a new login to your account on ' . htmlspecialchars($login_time) . '.</p>
                
                <div class="info-box">
                    <strong>Login Details:</strong><br>
                    <strong>IP Address:</strong> ' . htmlspecialchars($ip_address) . '<br>
                    <strong>Browser/Device:</strong> ' . htmlspecialchars($user_agent) . '<br>
                    <strong>Time:</strong> ' . htmlspecialchars($login_time) . '<br>
                </div>
                
                <div class="warning">
                    <strong>⚠️ Wasn\'t you?</strong><br>
                    If you did not authorize this login, please secure your account immediately by changing your password.
                </div>
                
                <p style="text-align: center;">
                    <a href="' . SITE_URL . '/profile.php" class="button">Secure Your Account</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send exam result email
 */
function sendExamResultEmail($to, $name, $exam_title, $score, $percentage, $correct_answers, $total_questions, $passed) {
    $subject = $passed ? "🎉 Congratulations! You Passed the Exam!" : "📊 Your Exam Results - " . $exam_title;
    $status_color = $passed ? '#10b981' : '#ef4444';
    $status_text = $passed ? 'PASSED' : 'FAILED';
    $status_icon = $passed ? '✓' : '✗';
    $wrong_answers = $total_questions - $correct_answers;
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Exam Results</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: ' . $status_color . '; color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .result-card { background: white; border-radius: 12px; padding: 25px; margin: 20px 0; text-align: center; border: 1px solid #e5e7eb; }
            .score { font-size: 48px; font-weight: bold; color: ' . $status_color . '; margin: 10px 0; }
            .status { display: inline-block; padding: 8px 20px; border-radius: 30px; font-weight: bold; background: ' . $status_color . '; color: white; margin-top: 10px; }
            .stats { display: flex; justify-content: space-around; margin: 20px 0; }
            .stat-value { font-size: 28px; font-weight: bold; }
            .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin: 10px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . $status_icon . ' ' . htmlspecialchars($exam_title) . ' - Results</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>You have completed the exam: <strong>' . htmlspecialchars($exam_title) . '</strong></p>
                
                <div class="result-card">
                    <h2>Your Score</h2>
                    <div class="score">' . $percentage . '%</div>
                    <div class="status">' . $status_text . ' ' . $status_icon . '</div>
                    
                    <div class="stats">
                        <div class="stat">
                            <div class="stat-value">' . $correct_answers . '/' . $total_questions . '</div>
                            <div>Correct Answers</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value">' . $wrong_answers . '/' . $total_questions . '</div>
                            <div>Wrong Answers</div>
                        </div>
                    </div>
                </div>
                
                <p style="text-align: center;">
                    <a href="' . SITE_URL . '/student/results.php" class="button">View Detailed Results</a>
                    <a href="' . SITE_URL . '/student/take-exam.php" class="button" style="background: #6b7280;">Retake Exam</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send account status change email
 */
function sendAccountStatusChangeEmail($to, $name, $username, $new_status, $reason) {
    $status_text = $new_status == 1 ? 'activated' : 'deactivated';
    $status_color = $new_status == 1 ? '#10b981' : '#ef4444';
    $subject = "Account " . ucfirst($status_text);
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Account Status Update</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: ' . $status_color . '; color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .reason-box { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📢 Account ' . strtoupper($status_text) . '</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your account has been ' . $status_text . ' by an administrator.</p>
                
                <div class="reason-box">
                    <strong>Reason:</strong><br>
                    ' . nl2br(htmlspecialchars($reason)) . '
                </div>
                
                <p>Account Details:</p>
                <ul>
                    <li><strong>Username:</strong> ' . htmlspecialchars($username) . '</li>
                    <li><strong>Email:</strong> ' . htmlspecialchars($to) . '</li>
                    <li><strong>Status:</strong> ' . ucfirst($status_text) . '</li>
                </ul>
                
                <p>If you have any questions, please contact our support team.</p>
                <p style="text-align: center;">
                    <a href="mailto:support@yourapp.com" class="button">Contact Support</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send account deletion email
 */
function sendAccountDeletionEmail($to, $name, $username, $reason) {
    $subject = "Account Deletion Notice";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Account Deletion Notice</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ef4444; color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .reason-box { background: #fef2f2; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ef4444; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📢 Account Deletion Notice</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>We regret to inform you that your account has been deleted from the system.</p>
                
                <div class="reason-box">
                    <strong>Reason for deletion:</strong><br>
                    ' . nl2br(htmlspecialchars($reason)) . '
                </div>
                
                <p>Your account details:</p>
                <ul>
                    <li><strong>Username:</strong> ' . htmlspecialchars($username) . '</li>
                    <li><strong>Email:</strong> ' . htmlspecialchars($to) . '</li>
                </ul>
                
                <p>If you believe this was a mistake, please contact our support team.</p>
                <p style="text-align: center;">
                    <a href="mailto:support@yourapp.com" class="button">Contact Support</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send profile update email
 */
function sendProfileUpdateEmail($to, $name, $username) {
    $subject = "Profile Updated";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Profile Updated</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📝 Profile Updated</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your profile information has been updated by an administrator.</p>
                
                <p>If you did not request this change or notice any incorrect information, please contact us immediately.</p>
                <p style="text-align: center;">
                    <a href="mailto:support@yourapp.com" class="button">Contact Support</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send admin password reset code email
 */
function sendAdminPasswordResetCodeEmail($to, $name, $code) {
    $subject = "Admin Password Reset Code";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Admin Password Reset Code</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .code-box { background: #e5e7eb; padding: 20px; text-align: center; font-size: 36px; font-weight: bold; letter-spacing: 8px; border-radius: 12px; margin: 20px 0; font-family: monospace; }
            .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔑 Admin Password Reset Code</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>We received a request to reset your administrator password.</p>
                
                <div class="code-box">
                    ' . $code . '
                </div>
                
                <div class="warning">
                    <strong>⚠️ Important:</strong> This code will expire in 30 minutes.
                    If you did not request this password reset, please contact the system administrator immediately.
                </div>
                
                <p>Enter this code on the admin password reset page to create a new password.</p>
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send student password reset code email
 */
function sendStudentPasswordResetCodeEmail($to, $name, $code) {
    $subject = "Student Password Reset Code";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Student Password Reset Code</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .code-box { background: #e5e7eb; padding: 20px; text-align: center; font-size: 36px; font-weight: bold; letter-spacing: 8px; border-radius: 12px; margin: 20px 0; font-family: monospace; }
            .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔑 Student Password Reset Code</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>We received a request to reset your student account password.</p>
                
                <div class="code-box">
                    ' . $code . '
                </div>
                
                <div class="warning">
                    <strong>⚠️ Important:</strong> This code will expire in 30 minutes.
                    If you did not request this password reset, please ignore this email.
                </div>
                
                <p>Enter this code on the student password reset page to create a new password.</p>
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send admin password reset success email
 */
function sendAdminPasswordResetSuccessEmail($to, $name, $username) {
    $subject = "Admin Password Reset Successful";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Admin Password Reset Successful</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .button { display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔑 Admin Password Reset Successful</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your administrator password has been reset successfully.</p>
                
                <p>If you did not perform this action, please contact the system administrator immediately.</p>
                <p style="text-align: center;">
                    <a href="' . SITE_URL . '/admin/login.php" class="button">Login to Admin Panel</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

/**
 * Send student password reset success email
 */
function sendStudentPasswordResetSuccessEmail($to, $name, $username) {
    $subject = "Student Password Reset Successful";
    
    $message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Student Password Reset Successful</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { padding: 30px; background: #f9fafb; }
            .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔑 Student Password Reset Successful</h1>
            </div>
            <div class="content">
                <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Your student account password has been reset successfully.</p>
                
                <p style="text-align: center;">
                    <a href="' . SITE_URL . '/student/login.php" class="button">Login to Student Portal</a>
                </p>
                
                <p>Best regards,<br><strong>Support Team</strong></p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' Online Exam System. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($to, $subject, $message, $name);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

function generateRandomString($length = 10) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
}

function getGravatar($email, $size = 80) {
    $hash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/$hash?s=$size&d=mp";
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>