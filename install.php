<?php
/**
 * Database Installation Script
 * Run this once to set up the database
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'online_exam_system';

// Create connection
$conn = new mysqli($host, $user, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $database";
if ($conn->query($sql) === TRUE) {
    echo "✅ Database created successfully<br>";
} else {
    echo "❌ Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db($database);

// Drop existing tables if they exist (clean install)
$tables_to_drop = ['student_answers', 'exam_attempts', 'exam_questions', 'exams', 'questions', 'topics', 'subjects', 'activity_log', 'notifications', 'users'];
foreach ($tables_to_drop as $table) {
    $conn->query("DROP TABLE IF EXISTS $table");
}
echo "🗑️ Cleaned existing tables<br>";

// Create tables in correct order
$tables = [];

// Users table (first - no foreign keys)
$tables[] = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'student') DEFAULT 'student',
    status TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Subjects table
$tables[] = "CREATE TABLE IF NOT EXISTS subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fa-book',
    color VARCHAR(20) DEFAULT '#10b981',
    status TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Topics table
$tables[] = "CREATE TABLE IF NOT EXISTS topics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status TINYINT DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_subject (subject_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Questions table
$tables[] = "CREATE TABLE IF NOT EXISTS questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT NOT NULL,
    topic_id INT DEFAULT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    explanation TEXT,
    marks DECIMAL(5,2) DEFAULT 1.00,
    status TINYINT DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL,
    INDEX idx_subject (subject_id),
    INDEX idx_topic (topic_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Exams table
$tables[] = "CREATE TABLE IF NOT EXISTS exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    subject_id INT NOT NULL,
    duration_minutes INT DEFAULT 60,
    passing_score INT DEFAULT 70,
    total_questions INT DEFAULT 0,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Exam Questions table
$tables[] = "CREATE TABLE IF NOT EXISTS exam_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    question_id INT NOT NULL,
    question_order INT DEFAULT 0,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_question (exam_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Exam Attempts table
$tables[] = "CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_time DATETIME,
    score DECIMAL(5,2) DEFAULT 0,
    total_questions INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    wrong_answers INT DEFAULT 0,
    percentage DECIMAL(5,2) DEFAULT 0,
    passed TINYINT DEFAULT 0,
    status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_exam (exam_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Student Answers table
$tables[] = "CREATE TABLE IF NOT EXISTS student_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_option ENUM('A', 'B', 'C', 'D'),
    is_correct TINYINT DEFAULT 0,
    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
    INDEX idx_attempt (attempt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Notifications table
$tables[] = "CREATE TABLE IF NOT EXISTS notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Activity Log table
$tables[] = "CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// Execute all table creations
$success = true;
foreach ($tables as $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "✅ Table created successfully<br>";
    } else {
        echo "❌ Error creating table: " . $conn->error . "<br>";
        $success = false;
    }
}

if (!$success) {
    die("<br>❌ Installation failed. Please fix the errors above.");
}

// Insert default admin user
$admin_username = 'admin';
$admin_email = 'admin@examsystem.com';
$admin_password = 'admin123';
$admin_full_name = 'System Administrator';
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

$check_admin = $conn->query("SELECT id FROM users WHERE email = '$admin_email'");
if ($check_admin->num_rows == 0) {
    $admin_sql = "INSERT INTO users (username, email, password, full_name, role, status) 
                  VALUES (?, ?, ?, ?, 'admin', 1)";
    $stmt = $conn->prepare($admin_sql);
    $stmt->bind_param("ssss", $admin_username, $admin_email, $hashed_password, $admin_full_name);
    
    if ($stmt->execute()) {
        echo "✅ Admin user created successfully<br>";
        echo "   Username: admin<br>";
        echo "   Password: admin123<br>";
    } else {
        echo "❌ Error creating admin user: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "ℹ️ Admin user already exists<br>";
}

// Insert default subjects
$subjects = [
    ['Mathematics', 'MATH101', 'Fundamentals of mathematics including algebra, calculus, and geometry', 'fa-calculator', '#10b981'],
    ['Physics', 'PHY101', 'Classical and modern physics concepts', 'fa-atom', '#3b82f6'],
    ['Chemistry', 'CHEM101', 'Organic and inorganic chemistry', 'fa-flask', '#f59e0b'],
    ['Biology', 'BIO101', 'Life sciences and biological systems', 'fa-leaf', '#8b5cf6'],
    ['Computer Science', 'CS101', 'Programming, algorithms, and computer systems', 'fa-laptop-code', '#ef4444']
];

foreach ($subjects as $subject) {
    $check_subject = $conn->query("SELECT id FROM subjects WHERE code = '{$subject[1]}'");
    if ($check_subject->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO subjects (name, code, description, icon, color, status) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssss", $subject[0], $subject[1], $subject[2], $subject[3], $subject[4]);
        if ($stmt->execute()) {
            echo "✅ Subject created: {$subject[0]}<br>";
        }
        $stmt->close();
    }
}

// Insert sample topics for Computer Science
$cs_subject = $conn->query("SELECT id FROM subjects WHERE code = 'CS101'")->fetch_assoc();
if ($cs_subject) {
    $topics = [
        ['Programming Fundamentals', 'Introduction to programming concepts, variables, loops, and functions'],
        ['Data Structures', 'Arrays, lists, stacks, queues, and trees'],
        ['Algorithms', 'Sorting, searching, and algorithm analysis'],
        ['Web Development', 'HTML, CSS, JavaScript, and backend technologies'],
        ['Databases', 'SQL, database design, and management']
    ];
    
    foreach ($topics as $topic) {
        $check_topic = $conn->query("SELECT id FROM topics WHERE subject_id = {$cs_subject['id']} AND name = '{$topic[0]}'");
        if ($check_topic->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO topics (subject_id, name, description, status) VALUES (?, ?, ?, 1)");
            $stmt->bind_param("iss", $cs_subject['id'], $topic[0], $topic[1]);
            if ($stmt->execute()) {
                echo "✅ Topic created: {$topic[0]}<br>";
            }
            $stmt->close();
        }
    }
}

// Insert sample questions
$cs_topic = $conn->query("SELECT id FROM topics WHERE name = 'Programming Fundamentals' LIMIT 1")->fetch_assoc();
if ($cs_topic) {
    $questions = [
        ['What is a variable in programming?', 'A storage location with a name', 'A mathematical function', 'A type of loop', 'A debugging tool', 'A', 'Variables are used to store data in memory.'],
        ['Which of the following is a loop structure?', 'if-else', 'switch-case', 'for loop', 'function', 'C', 'For loops are used for iteration.'],
        ['What does HTML stand for?', 'Hyper Text Markup Language', 'High Tech Modern Language', 'Hyper Transfer Markup Language', 'Home Tool Markup Language', 'A', 'HTML is the standard markup language for web pages.'],
        ['Which language is primarily used for styling web pages?', 'HTML', 'JavaScript', 'Python', 'CSS', 'D', 'CSS is used for styling and layout.'],
        ['What is the purpose of a function?', 'To store data', 'To reuse code blocks', 'To create variables', 'To debug errors', 'B', 'Functions allow code reuse and organization.']
    ];
    
    foreach ($questions as $q) {
        $stmt = $conn->prepare("INSERT INTO questions (subject_id, topic_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, difficulty, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'easy', 1)");
        $stmt->bind_param("iisssssss", $cs_subject['id'], $cs_topic['id'], $q[0], $q[1], $q[2], $q[3], $q[4], $q[5], $q[6]);
        if ($stmt->execute()) {
            echo "✅ Sample question created: " . substr($q[0], 0, 50) . "...<br>";
        }
        $stmt->close();
    }
}

// Insert sample exam
$subject_id = $cs_subject['id'];
$check_exam = $conn->query("SELECT id FROM exams WHERE title = 'Programming Fundamentals Quiz'");
if ($check_exam->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO exams (title, description, subject_id, duration_minutes, passing_score, total_questions, status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'published')");
    $desc = "Test your knowledge of programming fundamentals including variables, loops, and basic concepts.";
    $total_q = 5;
    $stmt->bind_param("ssiiii", $title, $desc, $subject_id, $duration, $passing, $total_q);
    
    $title = "Programming Fundamentals Quiz";
    $desc = "Test your knowledge of programming fundamentals including variables, loops, and basic concepts.";
    $duration = 30;
    $passing = 70;
    $total_q = 5;
    
    if ($stmt->execute()) {
        $exam_id = $stmt->insert_id;
        echo "✅ Sample exam created: Programming Fundamentals Quiz<br>";
        
        // Link questions to exam
        $questions_result = $conn->query("SELECT id FROM questions WHERE subject_id = $subject_id LIMIT 5");
        $order = 1;
        $link_stmt = $conn->prepare("INSERT INTO exam_questions (exam_id, question_id, question_order) VALUES (?, ?, ?)");
        
        while ($q = $questions_result->fetch_assoc()) {
            $link_stmt->bind_param("iii", $exam_id, $q['id'], $order);
            $link_stmt->execute();
            $order++;
        }
        $link_stmt->close();
        echo "✅ Linked 5 questions to the exam<br>";
    }
    $stmt->close();
}

echo "<br><strong>🎉 Installation complete!</strong><br>";
echo "<hr>";
echo "<h3>Login Credentials:</h3>";
echo "<p><strong>Admin:</strong> admin@examsystem.com / admin123</p>";
echo "<p><strong>Student:</strong> Register a new account to test student features</p>";
echo "<hr>";
echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 8px;'>Go to Website →</a>";

$conn->close();
?>