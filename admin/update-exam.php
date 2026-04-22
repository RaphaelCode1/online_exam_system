<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canEditExams()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$exam_id = (int)$_POST['exam_id'];
$title = $conn->real_escape_string($_POST['title']);
$description = $conn->real_escape_string($_POST['description']);
$subject_id = (int)$_POST['subject_id'];
$duration = (int)$_POST['duration'];
$passing_score = (int)$_POST['passing_score'];
$status = $_POST['status'];

$stmt = $conn->prepare("UPDATE exams SET title = ?, description = ?, subject_id = ?, duration_minutes = ?, passing_score = ?, status = ? WHERE id = ?");
$stmt->bind_param("ssiiisi", $title, $description, $subject_id, $duration, $passing_score, $status, $exam_id);

if ($stmt->execute()) {
    $_SESSION['message'] = "Exam updated successfully!";
} else {
    $_SESSION['error'] = "Error updating exam: " . $stmt->error;
}
$stmt->close();

header('Location: exams.php');
exit();
?>