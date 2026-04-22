<?php
/**
 * Privacy Policy Page
 * Student Privacy Policy - Simple Version
 */

session_start();

$page_title = 'Privacy Policy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - MissionTech College</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .privacy-container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .privacy-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 2rem;
            text-align: center;
            color: white;
        }

        .privacy-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .privacy-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .privacy-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .privacy-content {
            padding: 2rem;
        }

        .section {
            margin-bottom: 1.5rem;
        }

        .section h2 {
            font-size: 1.1rem;
            color: #1e293b;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section h2 i {
            color: #10b981;
            font-size: 1rem;
        }

        .section p {
            color: #475569;
            line-height: 1.6;
            font-size: 0.9rem;
        }

        .contact-info {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .contact-item:last-child {
            border-bottom: none;
        }

        .contact-item i {
            width: 30px;
            color: #10b981;
            font-size: 1.1rem;
        }

        .contact-item strong {
            color: #1e293b;
            width: 80px;
        }

        .contact-item span {
            color: #475569;
        }

        .contact-item a {
            color: #10b981;
            text-decoration: none;
        }

        .contact-item a:hover {
            text-decoration: underline;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 1rem;
        }

        .back-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .footer {
            text-align: center;
            padding: 1.5rem;
            background: #f8fafc;
            font-size: 0.75rem;
            color: #64748b;
            border-top: 1px solid #eef2f6;
        }

        @media (max-width: 640px) {
            .privacy-container {
                margin: 1rem;
            }
            
            .privacy-content {
                padding: 1.5rem;
            }
            
            .contact-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .contact-item strong {
                width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="privacy-container">
        <div class="privacy-header">
            <i class="fas fa-shield-alt"></i>
            <h1>Privacy Policy</h1>
            <p>MissionTech College - Online Examination System</p>
        </div>
        
        <div class="privacy-content">
            <div class="section">
                <h2><i class="fas fa-info-circle"></i> Information We Collect</h2>
                <p>We collect personal information that you provide to us when registering for the online examination system, including:</p>
                <ul>
                    <li>Full name and contact information</li>
                    <li>Email address</li>
                    <li>Student ID and academic information</li>
                    <li>Exam results and performance data</li>
                    <li>Login credentials (password stored securely)</li>
                </ul>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-lock"></i> Data Protection</h2>
                <p>Your data is securely stored and only used for educational purposes. We do not share your personal information with third parties without your consent.</p>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-file-alt"></i> Exam Data</h2>
                <p>Your exam results and performance data are stored to track your progress and generate certificates. You can view your results anytime from your dashboard.</p>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-cookie-bite"></i> Cookies</h2>
                <p>We use cookies to maintain your session and remember your preferences. You can disable cookies in your browser settings, but this may affect functionality.</p>
            </div>
            
            <div class="contact-info">
                <h2><i class="fas fa-envelope"></i> Contact Us</h2>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <strong>Email:</strong>
                    <a href="mailto:missiontech.admin@gmail.com">missiontech.admin@gmail.com</a>
                </div>
                <div class="contact-item">
                    <i class="fas fa-headset"></i>
                    <strong>Support:</strong>
                    <a href="mailto:missiontech.raph@gmail.com">missiontech.raph@gmail.com</a>
                </div>
            </div>
            
            <div class="section">
                <h2><i class="fas fa-calendar-alt"></i> Policy Updates</h2>
                <p>This policy may be updated from time to time. We will notify you of any significant changes through email or website announcement.</p>
            </div>
            
            <div style="text-align: center;">
                <a href="login.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> MissionTech College. All rights reserved.</p>
            <p style="margin-top: 0.5rem;">Last updated: <?php echo date('F j, Y'); ?></p>
        </div>
    </div>
</body>
</html>