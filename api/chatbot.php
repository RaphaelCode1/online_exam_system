<?php
/**
 * Chatbot API Endpoint
 */

session_start();

require_once '../config/database.php';
require_once '../config/chatbot.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    echo json_encode(['response' => 'Please enter a message.']);
    exit();
}

// Initialize database
$db = Database::getInstance();
$conn = $db->getConnection();

// Get user ID if logged in
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

// Process message
$chatbot = new ExamChatbot($conn, $user_id);
$response = $chatbot->processMessage($message);

echo json_encode(['response' => $response]);
?>