<?php
/**
 * Email Helper Class
 * Handles all email notifications using Brevo API
 */

// Load environment variables
require_once __DIR__ . '/../config/load_env.php';

class EmailHelper {
    private $api_key;
    private $from_email;
    private $from_name;
    private $site_url;
    private $enabled;
    
    public function __construct() {
        // Load from environment variables instead of hardcoding
        $this->api_key = BREVO_API_KEY;
        $this->from_email = BREVO_SENDER_EMAIL;
        $this->from_name = BREVO_SENDER_NAME;
        $this->site_url = SITE_URL;
        $this->enabled = (ENABLE_EMAILS == 1);
        
        // Log warning if API key is missing
        if (empty($this->api_key) && $this->enabled) {
            error_log("WARNING: BREVO_API_KEY is not set in .env file. Emails will not be sent.");
        }
        
        // Log warning if emails are disabled
        if (!$this->enabled) {
            error_log("INFO: Email notifications are disabled (ENABLE_EMAILS=0). Set to 1 in .env to enable.");
        }
    }
    
    /**
     * Send email using Brevo API
     */
    public function send($to_email, $to_name, $subject, $html_content) {
        // Check if emails are enabled
        if (!$this->enabled) {
            error_log("Email not sent - Email notifications are disabled. To: $to_email, Subject: $subject");
            return true; // Return true to not break functionality
        }
        
        // Check if API key is set
        if (empty($this->api_key)) {
            error_log("Email not sent - BREVO_API_KEY is not configured. To: $to_email, Subject: $subject");
            return false;
        }
        
        $email_data = [
            'sender' => [
                'name' => $this->from_name,
                'email' => $this->from_email
            ],
            'to' => [
                [
                    'email' => $to_email,
                    'name' => $to_name
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $html_content
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.brevo.com/v3/smtp/email',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'api-key: ' . $this->api_key,
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
            error_log("Email sent successfully to: $to_email - Subject: $subject");
            return true;
        } else {
            error_log("Email failed to: $to_email - HTTP: $http_code - Response: $response - Error: $curl_error");
            return false;
        }
    }
    
    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail($user) {
        $subject = "Welcome to " . SITE_NAME . "!";
        
        $html = $this->getEmailTemplate('welcome', [
            'name' => $user['full_name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => ucfirst($user['role']),
            'site_url' => $this->site_url,
            'site_name' => SITE_NAME,
            'login_url' => $this->site_url . '/login.php'
        ]);
        
        return $this->send($user['email'], $user['full_name'], $subject, $html);
    }
    
    /**
     * Send login notification email
     */
    public function sendLoginNotification($user, $ip_address, $user_agent, $location = 'Unknown') {
        $subject = "New Login to Your Account - " . SITE_NAME;
        
        $html = $this->getEmailTemplate('login_notification', [
            'name' => $user['full_name'],
            'email' => $user['email'],
            'role' => ucfirst($user['role']),
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'location' => $location,
            'login_time' => date('F j, Y \a\t H:i:s'),
            'site_url' => $this->site_url,
            'site_name' => SITE_NAME,
            'security_url' => $this->site_url . '/profile.php?section=security'
        ]);
        
        return $this->send($user['email'], $user['full_name'], $subject, $html);
    }
    
    /**
     * Send exam result email
     */
    public function sendExamResultEmail($user, $exam_title, $score, $percentage, $correct_answers, $total_questions, $passed) {
        $subject = $passed ? "🎉 Congratulations! You Passed the Exam!" : "📊 Your Exam Results - " . $exam_title;
        
        $html = $this->getEmailTemplate('exam_result', [
            'name' => $user['full_name'],
            'exam_title' => $exam_title,
            'score' => $score,
            'total_questions' => $total_questions,
            'percentage' => $percentage,
            'correct_answers' => $correct_answers,
            'wrong_answers' => $total_questions - $correct_answers,
            'passed' => $passed,
            'passing_score' => 70,
            'site_url' => $this->site_url,
            'site_name' => SITE_NAME,
            'results_url' => $this->site_url . '/student/results.php',
            'retake_url' => $this->site_url . '/student/take-exam.php'
        ]);
        
        return $this->send($user['email'], $user['full_name'], $subject, $html);
    }
    
    /**
     * Send password change notification
     */
    public function sendPasswordChangeNotification($user, $ip_address) {
        $subject = "Password Changed - " . SITE_NAME;
        
        $html = $this->getEmailTemplate('password_change', [
            'name' => $user['full_name'],
            'email' => $user['email'],
            'ip_address' => $ip_address,
            'change_time' => date('F j, Y \a\t H:i:s'),
            'site_url' => $this->site_url,
            'site_name' => SITE_NAME,
            'support_url' => $this->site_url . '/contact.php',
            'reset_url' => $this->site_url . '/forgot-password.php'
        ]);
        
        return $this->send($user['email'], $user['full_name'], $subject, $html);
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($user, $reset_token) {
        $subject = "Password Reset Request - " . SITE_NAME;
        $reset_link = $this->site_url . "/reset-password.php?token=" . $reset_token;
        
        $html = $this->getEmailTemplate('password_reset', [
            'name' => $user['full_name'],
            'email' => $user['email'],
            'reset_link' => $reset_link,
            'expires_minutes' => 60,
            'site_url' => $this->site_url,
            'site_name' => SITE_NAME,
            'support_url' => $this->site_url . '/contact.php'
        ]);
        
        return $this->send($user['email'], $user['full_name'], $subject, $html);
    }
    
    /**
     * Send account status change notification
     */
    public function sendAccountStatusChangeEmail($user, $new_status, $reason = '') {
        $status_text = $new_status == 1 ? 'activated' : 'deactivated';
        $subject = "Account " . ucfirst($status_text) . " - " . SITE_NAME;
        
        $html = $this->getEmailTemplate('account_status', [
            'name' => $user['full_name'],
            'email' => $user['email'],
            'status' => $status_text,
            'reason' => $reason,
            'site_url' => $this->site_url,
            'site_name' => SITE_NAME,
            'contact_url' => $this->site_url . '/contact.php'
        ]);
        
        return $this->send($user['email'], $user['full_name'], $subject, $html);
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($template, $data) {
        $templates = [
            'welcome' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to {{site_name}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
        .content { padding: 30px; background: #f9fafb; }
        .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        .credentials { background: #e5e7eb; padding: 15px; border-radius: 8px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{site_name}}! 🎉</h1>
        </div>
        <div class="content">
            <p>Dear <strong>{{name}}</strong>,</p>
            <p>Thank you for registering with {{site_name}}. We\'re excited to have you on board!</p>
            
            <div class="credentials">
                <strong>Your Account Details:</strong><br>
                <strong>Username:</strong> {{username}}<br>
                <strong>Email:</strong> {{email}}<br>
                <strong>Role:</strong> {{role}}<br>
            </div>
            
            <p>You can now login to your account and start taking exams. Here are some things you can do:</p>
            <ul>
                <li>📝 Take practice exams</li>
                <li>📊 Track your progress</li>
                <li>🏆 View your results and rankings</li>
                <li>📚 Access study materials</li>
            </ul>
            
            <p style="text-align: center;">
                <a href="{{login_url}}" class="button">Login to Your Account</a>
            </p>
            
            <p>If you have any questions or need assistance, please don\'t hesitate to contact us.</p>
            <p>Best regards,<br><strong>{{site_name}} Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; 2024 {{site_name}}. All rights reserved.</p>
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>',
            
            'login_notification' => '
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
        .button { display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 New Login Detected</h1>
        </div>
        <div class="content">
            <p>Dear <strong>{{name}}</strong>,</p>
            <p>We detected a new login to your account on {{login_time}}.</p>
            
            <div class="info-box">
                <strong>Login Details:</strong><br>
                <strong>IP Address:</strong> {{ip_address}}<br>
                <strong>Location:</strong> {{location}}<br>
                <strong>Browser/Device:</strong> {{user_agent}}<br>
                <strong>Time:</strong> {{login_time}}<br>
            </div>
            
            <div class="warning">
                <strong>⚠️ Wasn\'t you?</strong><br>
                If you did not authorize this login, please secure your account immediately by changing your password.
            </div>
            
            <p style="text-align: center;">
                <a href="{{security_url}}" class="button">Secure Your Account</a>
            </p>
            
            <p>If this was you, you can safely ignore this email.</p>
            <p>Best regards,<br><strong>{{site_name}} Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; 2024 {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            
            'exam_result' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exam Results</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, {{header_color}} 0%, {{header_dark}} 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
        .content { padding: 30px; background: #f9fafb; }
        .result-card { background: white; border-radius: 12px; padding: 20px; margin: 20px 0; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .score { font-size: 48px; font-weight: bold; color: {{score_color}}; margin: 10px 0; }
        .status { display: inline-block; padding: 8px 20px; border-radius: 30px; background: {{status_bg}}; color: {{status_color}}; font-weight: bold; margin: 15px 0; }
        .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
        .stats { display: flex; justify-content: space-around; margin: 20px 0; }
        .stat { text-align: center; }
        .stat-value { font-size: 24px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header" style="background: linear-gradient(135deg, {{header_color}} 0%, {{header_dark}} 100%);">
            <h1>{{status_icon}} {{exam_title}} - Results</h1>
        </div>
        <div class="content">
            <p>Dear <strong>{{name}}</strong>,</p>
            <p>You have completed the exam: <strong>{{exam_title}}</strong></p>
            
            <div class="result-card">
                <h2>Your Score</h2>
                <div class="score">{{percentage}}%</div>
                <div class="status">{{status_text}}</div>
                
                <div class="stats">
                    <div class="stat">
                        <div class="stat-value">{{correct_answers}}/{{total_questions}}</div>
                        <div>Correct Answers</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">{{wrong_answers}}/{{total_questions}}</div>
                        <div>Wrong Answers</div>
                    </div>
                </div>
                
                <p>You scored {{score}} out of {{total_questions}} points.</p>
                <p>Passing Score Required: {{passing_score}}%</p>
            </div>
            
            <p style="text-align: center;">
                <a href="{{results_url}}" class="button">View Detailed Results</a>
                <a href="{{retake_url}}" class="button" style="background: #6b7280;">Retake Exam</a>
            </p>
            
            <p>Keep practicing to improve your scores!</p>
            <p>Best regards,<br><strong>{{site_name}} Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; 2024 {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            
            'password_change' => '
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
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
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
            <p>Dear <strong>{{name}}</strong>,</p>
            <p>Your password was successfully changed on {{change_time}}.</p>
            
            <div class="warning">
                <strong>⚠️ Didn\'t make this change?</strong><br>
                If you did not change your password, please contact us immediately to secure your account.
            </div>
            
            <p style="text-align: center;">
                <a href="{{support_url}}" class="button">Contact Support</a>
                <a href="{{reset_url}}" class="button" style="background: #6b7280;">Reset Password</a>
            </p>
            
            <p>If this was you, you can safely ignore this email.</p>
            <p>Best regards,<br><strong>{{site_name}} Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; 2024 {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            
            'password_reset' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset Request</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 12px 12px 0 0; }
        .content { padding: 30px; background: #f9fafb; }
        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
        .button { display: inline-block; padding: 12px 24px; background: #f59e0b; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 12px 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Password Reset Request</h1>
        </div>
        <div class="content">
            <p>Dear <strong>{{name}}</strong>,</p>
            <p>We received a request to reset your password for your {{site_name}} account.</p>
            
            <div class="warning">
                <strong>⚠️ Important:</strong> If you did not request this password reset, please ignore this email. Your password will not be changed.
            </div>
            
            <p>Click the button below to reset your password:</p>
            <p style="text-align: center;">
                <a href="{{reset_link}}" class="button">Reset Password</a>
            </p>
            <p>Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; font-size: 12px;">{{reset_link}}</p>
            <p>This link will expire in {{expires_minutes}} minutes.</p>
            
            <p>Best regards,<br><strong>{{site_name}} Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; 2024 {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>',
            
            'account_status' => '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Account Status Update</title>
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
            <h1>📢 Account Status Update</h1>
        </div>
        <div class="content">
            <p>Dear <strong>{{name}}</strong>,</p>
            <p>Your account has been {{status}}.</p>
            ' . (!empty($data['reason']) ? '<p><strong>Reason:</strong> ' . htmlspecialchars($data['reason']) . '</p>' : '') . '
            <p>If you have any questions about this change, please contact our support team.</p>
            
            <p style="text-align: center;">
                <a href="{{contact_url}}" class="button">Contact Support</a>
            </p>
            
            <p>Best regards,<br><strong>{{site_name}} Team</strong></p>
        </div>
        <div class="footer">
            <p>&copy; 2024 {{site_name}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>'
        ];
        
        $template_content = $templates[$template] ?? '';
        
        // Replace placeholders
        foreach ($data as $key => $value) {
            $template_content = str_replace('{{' . $key . '}}', $value, $template_content);
        }
        
        // Special replacements for exam result colors
        if ($template === 'exam_result') {
            $passed = $data['passed'] ?? false;
            $header_color = $passed ? '#10b981' : '#ef4444';
            $header_dark = $passed ? '#059669' : '#dc2626';
            $score_color = $passed ? '#10b981' : '#ef4444';
            $status_bg = $passed ? '#ecfdf5' : '#fef2f2';
            $status_color = $passed ? '#10b981' : '#ef4444';
            $status_text = $passed ? '🎉 PASSED!' : '❌ FAILED';
            $status_icon = $passed ? '🎉' : '📊';
            
            $template_content = str_replace('{{header_color}}', $header_color, $template_content);
            $template_content = str_replace('{{header_dark}}', $header_dark, $template_content);
            $template_content = str_replace('{{score_color}}', $score_color, $template_content);
            $template_content = str_replace('{{status_bg}}', $status_bg, $template_content);
            $template_content = str_replace('{{status_color}}', $status_color, $template_content);
            $template_content = str_replace('{{status_text}}', $status_text, $template_content);
            $template_content = str_replace('{{status_icon}}', $status_icon, $template_content);
        }
        
        return $template_content;
    }
}
?>