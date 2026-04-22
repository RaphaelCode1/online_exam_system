<?php
/**
 * Submit Exam Page
 * Processes exam submissions, awards badges, generates certificates, and sends emails
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/email.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$attempt_id = isset($_POST['attempt_id']) ? (int)$_POST['attempt_id'] : 0;
$exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;
$answers = $_POST['answers'] ?? [];

if (!$attempt_id || !$exam_id) {
    header('Location: dashboard.php');
    exit();
}

// Get student details
$student_id = $_SESSION['user_id'];
$student = $conn->query("SELECT * FROM users WHERE id = $student_id")->fetch_assoc();

if (!$student) {
    header('Location: dashboard.php');
    exit();
}

// Get exam details
$exam = $conn->query("SELECT * FROM exams WHERE id = $exam_id")->fetch_assoc();

if (!$exam) {
    header('Location: dashboard.php');
    exit();
}

// Get attempt details to calculate time taken
$attempt = $conn->query("SELECT start_time FROM exam_attempts WHERE id = $attempt_id AND student_id = $student_id")->fetch_assoc();

if (!$attempt) {
    header('Location: dashboard.php');
    exit();
}

// Calculate time taken in seconds
$start_time = strtotime($attempt['start_time']);
$end_time = time();
$time_taken = $end_time - $start_time;

// Get the shuffled questions from session
$question_list = $_SESSION['exam_' . $attempt_id] ?? [];

if (empty($question_list)) {
    $questions = $conn->query("SELECT q.* FROM exam_questions eq 
                               JOIN questions q ON eq.question_id = q.id 
                               WHERE eq.exam_id = $exam_id");
    while ($q = $questions->fetch_assoc()) {
        $question_list[] = $q;
    }
}

$correct = 0;
$wrong = 0;
$total = count($question_list);
$score = 0;
$question_results = [];

// Process each answer
foreach ($question_list as $question) {
    $selected_letter = $answers[$question['id']] ?? null;
    $correct_letter = $question['shuffled_correct'] ?? $question['correct_option'];
    $is_correct = ($selected_letter === $correct_letter) ? 1 : 0;
    
    if ($is_correct) {
        $correct++;
        $score += $question['marks'];
    } else {
        $wrong++;
    }
    
    $question_results[] = [
        'id' => $question['id'],
        'selected' => $selected_letter,
        'correct' => $correct_letter,
        'is_correct' => $is_correct,
        'shuffled_options' => $question['shuffled_options']
    ];
    
    // Save answer to database
    $stmt = $conn->prepare("INSERT INTO student_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $attempt_id, $question['id'], $selected_letter, $is_correct);
    $stmt->execute();
    $stmt->close();
}

// Calculate percentage
$percentage = ($total > 0) ? round(($correct / $total) * 100, 2) : 0;
$passed = ($percentage >= $exam['passing_score']) ? 1 : 0;

// Update exam attempt
$stmt = $conn->prepare("UPDATE exam_attempts SET 
                        end_time = NOW(), 
                        score = ?, 
                        correct_answers = ?, 
                        wrong_answers = ?, 
                        percentage = ?, 
                        passed = ?, 
                        status = 'completed',
                        time_taken = ? 
                        WHERE id = ?");
$stmt->bind_param("iiiiiii", $score, $correct, $wrong, $percentage, $passed, $time_taken, $attempt_id);
$stmt->execute();
$stmt->close();

// ==================== CERTIFICATE GENERATION ====================
if ($passed) {
    // Check if certificate already exists
    $existing_cert = $conn->query("SELECT id FROM certificates WHERE student_id = $student_id AND exam_id = $exam_id");
    
    if ($existing_cert->num_rows == 0) {
        // Generate certificate
        $certificate_no = 'MTC-' . strtoupper(uniqid()) . '-' . date('Ymd');
        $grade = getGrade($percentage);
        $verification_code = md5($certificate_no . $student_id . $exam_id);
        
        $stmt = $conn->prepare("INSERT INTO certificates (certificate_no, student_id, exam_id, attempt_id, score, percentage, grade, issue_date, verification_code, status) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 'active')");
        $stmt->bind_param("siiidiss", $certificate_no, $student_id, $exam_id, $attempt_id, $score, $percentage, $grade, $verification_code);
        $stmt->execute();
        $cert_id = $stmt->insert_id;
        $stmt->close();
        
        // Add notification for certificate
        addNotification($conn, $student_id, "New Certificate Earned!", "Congratulations! You've earned a certificate for {$exam['title']} with {$percentage}%.", "certificate");
    }
}

// ==================== BADGE AWARDING ====================
// Get all student achievements
$achievements = $conn->query("SELECT * FROM student_achievements WHERE student_id = $student_id");
$earned_badges = [];
while ($ach = $achievements->fetch_assoc()) {
    $earned_badges[$ach['badge_name']] = true;
}

// Get exam statistics for badge calculations
$exam_stats = $conn->query("
    SELECT COUNT(*) as total_exams, 
           AVG(percentage) as avg_percentage,
           SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_exams,
           MAX(percentage) as best_score
    FROM exam_attempts 
    WHERE student_id = $student_id AND status = 'completed'
")->fetch_assoc();

// Get total correct answers
$total_correct = $conn->query("SELECT COUNT(*) as total FROM student_answers sa 
                               JOIN exam_attempts ea ON sa.attempt_id = ea.id 
                               WHERE ea.student_id = $student_id AND sa.is_correct = 1")->fetch_assoc()['total'];

// Get subjects passed with 80%+
$high_score_subjects = $conn->query("
    SELECT DISTINCT e.subject_id, MAX(ea.percentage) as best_score
    FROM exam_attempts ea
    JOIN exams e ON ea.exam_id = e.id
    WHERE ea.student_id = $student_id AND ea.passed = 1 AND ea.percentage >= 80
    GROUP BY e.subject_id
    HAVING best_score >= 80
")->num_rows;

// Award "First Step" badge (first exam completed)
if (!isset($earned_badges['First Step']) && $exam_stats['total_exams'] >= 1) {
    awardBadge($conn, $student_id, 'First Step', 'Complete your first exam', 50, 'fa-flag-checkered', '#10b981');
}

// Award "Speed Demon" badge (completed exam in half the time)
$exam_duration = $exam['duration_minutes'] * 60;
if (!isset($earned_badges['Speed Demon']) && $time_taken <= ($exam_duration / 2)) {
    awardBadge($conn, $student_id, 'Speed Demon', 'Complete an exam in half the time', 75, 'fa-bolt', '#f59e0b');
}

// Award "Scholar" badge (80%+ in 5 different subjects)
if (!isset($earned_badges['Scholar']) && $high_score_subjects >= 5) {
    awardBadge($conn, $student_id, 'Scholar', 'Score above 80% in 5 subjects', 300, 'fa-graduation-cap', '#8b5cf6');
}

// Award "Dedicated Learner" badge (complete 10 exams)
if (!isset($earned_badges['Dedicated Learner']) && $exam_stats['total_exams'] >= 10) {
    awardBadge($conn, $student_id, 'Dedicated Learner', 'Complete 10 exams', 200, 'fa-book-reader', '#3b82f6');
}

// Award "Accuracy Master" badge (90%+ accuracy in 5 exams)
if (!isset($earned_badges['Accuracy Master'])) {
    $high_accuracy = $conn->query("
        SELECT COUNT(*) as count FROM exam_attempts 
        WHERE student_id = $student_id AND percentage >= 90 AND status = 'completed'
    ")->fetch_assoc()['count'];
    if ($high_accuracy >= 5) {
        awardBadge($conn, $student_id, 'Accuracy Master', 'Achieve 90%+ accuracy in 5 exams', 150, 'fa-bullseye', '#ef4444');
    }
}

// Award "Perfect Score" badge (100% in any exam)
if (!isset($earned_badges['Perfect Score']) && $percentage == 100) {
    awardBadge($conn, $student_id, 'Perfect Score', 'Get 100% in any exam', 100, 'fa-star', '#f59e0b');
}

// Award "Question Master" badge (500 correct answers)
if (!isset($earned_badges['Question Master']) && $total_correct >= 500) {
    awardBadge($conn, $student_id, 'Question Master', 'Answer 500 questions correctly', 250, 'fa-brain', '#ec4899');
}

// Award "7-Day Streak" badge (check streak)
$streak = getCurrentStreak($conn, $student_id);
if (!isset($earned_badges['7-Day Streak']) && $streak >= 7) {
    awardBadge($conn, $student_id, '7-Day Streak', 'Practice for 7 consecutive days', 100, 'fa-calendar-check', '#10b981');
}

// Update streak
updateStudentStreak($conn, $student_id);

// ==================== EMAIL NOTIFICATION ====================
// Send email notification with results
$subject = "Exam Results: {$exam['title']}";
$status_text = $passed ? "PASSED" : "FAILED";
$status_color = $passed ? "#10b981" : "#ef4444";

$email_body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Exam Results</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; background: #f9fafb; }
        .score-box { background: white; padding: 20px; border-radius: 12px; margin: 20px 0; text-align: center; }
        .score { font-size: 48px; font-weight: bold; color: #10b981; }
        .status { display: inline-block; padding: 5px 15px; border-radius: 30px; font-weight: bold; background: $status_color; color: white; }
        .button { display: inline-block; padding: 12px 24px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; }
        .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Exam Results</h1>
        </div>
        <div class='content'>
            <p>Dear <strong>" . htmlspecialchars($student['full_name']) . "</strong>,</p>
            <p>You have completed the exam: <strong>{$exam['title']}</strong></p>
            
            <div class='score-box'>
                <div class='score'>$percentage%</div>
                <div>Your Score: $score / $total</div>
                <div style='margin-top: 10px;'><span class='status'>$status_text</span></div>
            </div>
            
            <div style='text-align: center; margin-top: 20px;'>
                <a href='http://localhost/online_exam_system/student/results.php?exam=$exam_id' class='button'>View Detailed Results</a>
            </div>
        </div>
        <div class='footer'>
            <p>&copy; " . date('Y') . " MissionTech College. All rights reserved.</p>
            <p>This is an automated message. Please do not reply.</p>
        </div>
    </div>
</body>
</html>
";

sendEmail($student['email'], $subject, $email_body, $student['full_name']);

// Log email
logEmail($conn, $student['email'], $student['full_name'], $subject, 'exam_result');

// Clear session data
unset($_SESSION['exam_' . $attempt_id]);

// Store results in session for display
$_SESSION['last_exam_result'] = [
    'exam_title' => $exam['title'],
    'score' => $score,
    'total_questions' => $total,
    'correct_answers' => $correct,
    'wrong_answers' => $wrong,
    'percentage' => $percentage,
    'passed' => $passed,
    'passing_score' => $exam['passing_score'],
    'time_taken' => $time_taken,
    'question_results' => $question_results,
    'email_sent' => true
];

// Redirect to results page
header("Location: results.php?exam=$exam_id&submitted=1");
exit();

// Helper Functions
function getGrade($percentage) {
    if ($percentage >= 80) return 'A (Excellent)';
    if ($percentage >= 70) return 'B (Very Good)';
    if ($percentage >= 60) return 'C (Good)';
    if ($percentage >= 50) return 'D (Satisfactory)';
    return 'F (Needs Improvement)';
}

function awardBadge($conn, $student_id, $badge_name, $description, $points, $icon, $color) {
    // Check if already awarded
    $check = $conn->query("SELECT id FROM student_achievements WHERE student_id = $student_id AND badge_name = '$badge_name'");
    if ($check->num_rows > 0) return;
    
    $stmt = $conn->prepare("INSERT INTO student_achievements (student_id, badge_name, description, points, badge_icon, badge_color, earned_date) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ississ", $student_id, $badge_name, $description, $points, $icon, $color);
    $stmt->execute();
    $stmt->close();
    
    // Add notification
    addNotification($conn, $student_id, "New Badge Earned!", "You've earned the '$badge_name' badge! +$points points", "badge");
}

function updateStudentStreak($conn, $student_id) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Check if already recorded today
    $check = $conn->query("SELECT id, current_streak, last_activity_date FROM student_streaks WHERE student_id = $student_id");
    
    if ($check->num_rows > 0) {
        $streak = $check->fetch_assoc();
        if ($streak['last_activity_date'] == $yesterday) {
            // Continue streak
            $new_streak = $streak['current_streak'] + 1;
            $conn->query("UPDATE student_streaks SET current_streak = $new_streak, last_activity_date = '$today' WHERE student_id = $student_id");
        } elseif ($streak['last_activity_date'] != $today) {
            // Reset streak
            $conn->query("UPDATE student_streaks SET current_streak = 1, last_activity_date = '$today' WHERE student_id = $student_id");
        }
    } else {
        // New streak
        $conn->query("INSERT INTO student_streaks (student_id, current_streak, last_activity_date) VALUES ($student_id, 1, '$today')");
    }
}

function getCurrentStreak($conn, $student_id) {
    $result = $conn->query("SELECT current_streak FROM student_streaks WHERE student_id = $student_id");
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['current_streak'];
    }
    return 0;
}

function addNotification($conn, $user_id, $title, $message, $type) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}

function logEmail($conn, $email, $name, $subject, $type) {
    $stmt = $conn->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, type, status, sent_at) VALUES (?, ?, ?, ?, 'sent', NOW())");
    $stmt->bind_param("ssss", $email, $name, $subject, $type);
    $stmt->execute();
    $stmt->close();
}
?>