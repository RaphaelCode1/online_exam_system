<?php
/**
 * Retake Exam Handler
 * Allows students to retake an exam by resetting their attempt
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if (!$exam_id) {
    header('Location: take-exam.php');
    exit();
}

// Check if exam exists and is published
$exam = $conn->query("SELECT * FROM exams WHERE id = $exam_id AND status = 'published'")->fetch_assoc();

if (!$exam) {
    $_SESSION['error'] = "Exam not found or not available.";
    header('Location: take-exam.php');
    exit();
}

// Check if student has already taken this exam
$previous_attempts = $conn->query("SELECT COUNT(*) as count FROM exam_attempts WHERE exam_id = $exam_id AND student_id = $user_id")->fetch_assoc()['count'];

// Delete any in-progress attempts for this exam
$conn->query("DELETE FROM exam_attempts WHERE exam_id = $exam_id AND student_id = $user_id AND status = 'in_progress'");

// Clear any existing session data for this exam
if (isset($_SESSION['exam_' . $exam_id])) {
    unset($_SESSION['exam_' . $exam_id]);
}

// Create a new attempt (will be created when starting the exam)
$_SESSION['retake_message'] = "You can now retake the exam. Questions and options will be randomized again.";
$_SESSION['retake_exam_id'] = $exam_id;

// Redirect to take exam page
header("Location: take-exam.php?id=$exam_id");
exit();
?>