<?php
/**
 * Admin Logout Page
 * Online Examination System
 */

// Initialize admin session with correct name
session_name('ADMIN_SESSION');
session_start();

// Log activity before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    require_once '../config/database.php';
    require_once '../includes/functions.php';
    logActivity($_SESSION['user_id'], 'admin_logout', 'Admin logged out');
}

// Clear all session variables
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Delete remember me cookie
if (isset($_COOKIE['admin_remember_token'])) {
    setcookie('admin_remember_token', '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to admin login page
header('Location: login.php?logout=1');
exit();
?>