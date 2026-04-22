<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    // Get user
    $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
    
    if (!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match';
    } elseif (strlen($new) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        $hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        
        if ($stmt->execute()) {
            $success = 'Password changed successfully!';
            logActivity($user_id, 'password_change', 'User changed password');
        } else {
            $error = 'Error changing password';
        }
        $stmt->close();
    }
}

// Redirect back to profile
if ($success) {
    $_SESSION['success'] = $success;
} elseif ($error) {
    $_SESSION['error'] = $error;
}

header('Location: profile.php');
exit();
?>