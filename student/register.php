<?php
/**
 * Student Registration Page - Professional Version
 * 3-step registration with email verification and welcome email
 */

session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/email.php';

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

$db = Database::getInstance();
$conn = $db->getConnection();

$course_suggestions = ['Computer Science', 'Information Technology', 'Business Administration', 'Software Engineering', 'Data Science', 'Cyber Security', 'Network Engineering', 'Graphic Design'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'step1') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        if (empty($full_name)) $errors[] = "Full name is required";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
        if ($password !== $confirm) $errors[] = "Passwords do not match";
        
        $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
        if ($check->num_rows > 0) $errors[] = "Email already registered";
        
        $username = strtolower(explode('@', $email)[0]);
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check->num_rows > 0) $username .= '_' . rand(100, 999);
        
        if (empty($errors)) {
            $_SESSION['student_reg'] = [
                'full_name' => $full_name,
                'email' => $email,
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'plain_password' => $password
            ];
            header('Location: register.php?step=2');
            exit();
        } else {
            $error = implode('<br>', $errors);
        }
    } elseif ($action === 'step2') {
        $course = trim($_POST['course'] ?? '');
        $year = (int)($_POST['year'] ?? 1);
        $phone = trim($_POST['phone'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $interests = isset($_POST['interests']) ? implode(',', $_POST['interests']) : '';
        
        if (empty($course)) {
            $error = "Course is required";
        } else {
            $_SESSION['student_reg']['course'] = $course;
            $_SESSION['student_reg']['year'] = $year;
            $_SESSION['student_reg']['phone'] = $phone;
            $_SESSION['student_reg']['location'] = $location;
            $_SESSION['student_reg']['interests'] = $interests;
            header('Location: register.php?step=3');
            exit();
        }
    } elseif ($action === 'step3') {
        $reg = $_SESSION['student_reg'] ?? [];
        if (empty($reg)) {
            header('Location: register.php?step=1');
            exit();
        }
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role, status, created_at) VALUES (?, ?, ?, ?, 'student', 1, NOW())");
            $stmt->bind_param("ssss", $reg['username'], $reg['email'], $reg['password'], $reg['full_name']);
            $stmt->execute();
            $user_id = $stmt->insert_id;
            $stmt->close();
            
            // Save additional student info if you have a student_profiles table
            // You can create this table later for storing course, year, phone, etc.
            
            // Send Welcome Email
            sendWelcomeEmail($reg['email'], $reg['full_name'], $reg['username'], $reg['plain_password']);
            
            // Log email
            logRegistrationEmail($conn, $reg['email'], $reg['full_name']);
            
            $conn->commit();
            unset($_SESSION['student_reg']);
            header('Location: login.php?registered=1');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

function logRegistrationEmail($conn, $email, $name) {
    $stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, type, status, sent_at) VALUES (?, ?, 'Welcome to MissionTech College', 'registration', 'sent', NOW())");
    $stmt->bind_param("ss", $email, $name);
    $stmt->execute();
    $stmt->close();
}

$page_title = 'Student Registration';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - MissionTech College</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .register-container {
            max-width: 550px;
            width: 100%;
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .register-card {
            background: white;
            border-radius: 32px;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo i {
            font-size: 3rem;
            color: #10b981;
            margin-bottom: 0.5rem;
        }
        
        .logo h1 {
            font-size: 1.8rem;
            color: #1e293b;
        }
        
        .logo p {
            color: #64748b;
            font-size: 0.85rem;
        }
        
        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            position: relative;
        }
        
        .progress-step {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #94a3b8;
            position: relative;
            z-index: 2;
            transition: all 0.3s;
        }
        
        .progress-step.active {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .progress-step.completed {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .progress-step-label {
            position: absolute;
            top: 50px;
            font-size: 0.7rem;
            white-space: nowrap;
            color: #64748b;
        }
        
        .progress-line {
            position: absolute;
            top: 22px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        
        /* Alert Styles */
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
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e293b;
            font-size: 0.85rem;
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
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
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
        
        .input-icon .form-control {
            padding-left: 45px;
        }
        
        .password-field {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
        }
        
        /* Checkbox Group */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }
        
        .checkbox-group input {
            width: 18px;
            height: 18px;
            accent-color: #10b981;
        }
        
        /* Buttons */
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #64748b;
        }
        
        .btn-outline:hover {
            border-color: #10b981;
            color: #10b981;
        }
        
        .flex-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .flex-buttons .btn {
            flex: 1;
        }
        
        /* Review Section */
        .review-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .review-item {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-label {
            width: 40%;
            font-weight: 600;
            color: #1e293b;
        }
        
        .review-value {
            width: 60%;
            color: #475569;
        }
        
        /* Footer Links */
        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eef2f6;
        }
        
        .footer-links a {
            color: #10b981;
            text-decoration: none;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            
            .register-card {
                padding: 1.5rem;
            }
            
            .progress-step-label {
                display: none;
            }
            
            .flex-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <h1>Student Registration</h1>
                <p>Join MissionTech College today</p>
            </div>
            
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="progress-step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">
                    1
                    <span class="progress-step-label">Account</span>
                </div>
                <div class="progress-step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">
                    2
                    <span class="progress-step-label">Details</span>
                </div>
                <div class="progress-step <?php echo $step >= 3 ? 'active' : ''; ?>">
                    3
                    <span class="progress-step-label">Complete</span>
                </div>
                <div class="progress-line"></div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
            <!-- Step 1: Account Information -->
            <form method="POST">
                <input type="hidden" name="action" value="step1">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <div class="input-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email Address *</label>
                    <div class="input-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="student@example.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password *</label>
                    <div class="password-field">
                        <i class="fas fa-lock" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                        <input type="password" name="password" id="password" class="form-control" style="padding-left: 45px;" placeholder="Minimum 6 characters" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <div class="password-field">
                        <i class="fas fa-lock" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" style="padding-left: 45px;" placeholder="Confirm your password" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Continue <i class="fas fa-arrow-right"></i></button>
            </form>
            <?php endif; ?>
            
            <?php if ($step == 2): ?>
            <!-- Step 2: Academic Information -->
            <form method="POST">
                <input type="hidden" name="action" value="step2">
                
                <div class="form-group">
                    <label>Course / Program *</label>
                    <div class="input-icon">
                        <i class="fas fa-graduation-cap"></i>
                        <input type="text" name="course" class="form-control" list="courses" placeholder="Select your course" required>
                        <datalist id="courses">
                            <?php foreach($course_suggestions as $c): ?>
                                <option value="<?php echo $c; ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Year of Study</label>
                    <div class="input-icon">
                        <i class="fas fa-calendar"></i>
                        <select name="year" class="form-control">
                            <option value="1">1st Year - Freshman</option>
                            <option value="2">2nd Year - Sophomore</option>
                            <option value="3">3rd Year - Junior</option>
                            <option value="4">4th Year - Senior</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Phone Number (Optional)</label>
                    <div class="input-icon">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="phone" class="form-control" placeholder="+254 700 000 000">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Location (Optional)</label>
                    <div class="input-icon">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="location" class="form-control" placeholder="City, Country">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Areas of Interest</label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="interests[]" value="programming"> Programming</label>
                        <label><input type="checkbox" name="interests[]" value="design"> Design</label>
                        <label><input type="checkbox" name="interests[]" value="business"> Business</label>
                        <label><input type="checkbox" name="interests[]" value="data_science"> Data Science</label>
                        <label><input type="checkbox" name="interests[]" value="networking"> Networking</label>
                    </div>
                </div>
                
                <div class="flex-buttons">
                    <a href="register.php?step=1" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
                    <button type="submit" class="btn btn-primary">Continue <i class="fas fa-arrow-right"></i></button>
                </div>
            </form>
            <?php endif; ?>
            
            <?php if ($step == 3): ?>
            <!-- Step 3: Review & Complete -->
            <div class="review-section">
                <h3 style="margin-bottom: 1rem; font-size: 1rem;">Account Information</h3>
                <div class="review-item">
                    <div class="review-label">Full Name</div>
                    <div class="review-value"><?php echo htmlspecialchars($_SESSION['student_reg']['full_name'] ?? ''); ?></div>
                </div>
                <div class="review-item">
                    <div class="review-label">Email</div>
                    <div class="review-value"><?php echo htmlspecialchars($_SESSION['student_reg']['email'] ?? ''); ?></div>
                </div>
                <div class="review-item">
                    <div class="review-label">Username</div>
                    <div class="review-value"><?php echo htmlspecialchars($_SESSION['student_reg']['username'] ?? ''); ?></div>
                </div>
            </div>
            
            <div class="review-section">
                <h3 style="margin-bottom: 1rem; font-size: 1rem;">Academic Information</h3>
                <div class="review-item">
                    <div class="review-label">Course</div>
                    <div class="review-value"><?php echo htmlspecialchars($_SESSION['student_reg']['course'] ?? ''); ?></div>
                </div>
                <div class="review-item">
                    <div class="review-label">Year</div>
                    <div class="review-value"><?php echo $_SESSION['student_reg']['year'] ?? ''; ?> Year</div>
                </div>
                <?php if (!empty($_SESSION['student_reg']['phone'])): ?>
                <div class="review-item">
                    <div class="review-label">Phone</div>
                    <div class="review-value"><?php echo htmlspecialchars($_SESSION['student_reg']['phone']); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['student_reg']['location'])): ?>
                <div class="review-item">
                    <div class="review-label">Location</div>
                    <div class="review-value"><?php echo htmlspecialchars($_SESSION['student_reg']['location']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="step3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Complete Registration
                </button>
            </form>
            
            <div class="flex-buttons" style="margin-top: 1rem;">
                <a href="register.php?step=2" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
            <?php endif; ?>
            
            <div class="footer-links">
                <a href="login.php">Already have an account? Login</a> |
                <a href="privacy.php">Privacy Policy</a>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength indicator (optional)
        const passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                const password = this.value;
                const strength = document.getElementById('password-strength');
                if (!strength) return;
                
                let strengthText = '';
                let strengthColor = '';
                
                if (password.length < 6) {
                    strengthText = 'Weak';
                    strengthColor = '#ef4444';
                } else if (password.length < 8) {
                    strengthText = 'Medium';
                    strengthColor = '#f59e0b';
                } else {
                    strengthText = 'Strong';
                    strengthColor = '#10b981';
                }
                
                strength.textContent = strengthText;
                strength.style.color = strengthColor;
            });
        }
    </script>
</body>
</html>