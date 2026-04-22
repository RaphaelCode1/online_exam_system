<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canEditQuestions()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$question_id = (int)$_POST['question_id'];
$subject_id = (int)$_POST['subject_id'];
$topic_id = (int)$_POST['topic_id'] ?: null;
$question_text = $conn->real_escape_string($_POST['question_text']);
$option_a = $conn->real_escape_string($_POST['option_a']);
$option_b = $conn->real_escape_string($_POST['option_b']);
$option_c = $conn->real_escape_string($_POST['option_c']);
$option_d = $conn->real_escape_string($_POST['option_d']);
$correct_option = $_POST['correct_option'];
$difficulty = $_POST['difficulty'];
$explanation = $conn->real_escape_string($_POST['explanation']);
$marks = (float)$_POST['marks'];

$stmt = $conn->prepare("UPDATE questions SET subject_id = ?, topic_id = ?, question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, difficulty = ?, explanation = ?, marks = ? WHERE id = ?");
$stmt->bind_param("iissssssssdi", $subject_id, $topic_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $difficulty, $explanation, $marks, $question_id);

if ($stmt->execute()) {
    $_SESSION['message'] = "Question updated successfully!";
} else {
    $_SESSION['error'] = "Error updating question: " . $stmt->error;
}
$stmt->close();

header('Location: questions.php');
exit();
?>