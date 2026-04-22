<?php
/**
 * Authentication Functions - Separate Admin/Student Sessions
 */

// Initialize session with role-specific name
function initSession($role = null) {
    // Close any existing session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // Set different session names for different roles
    if ($role === 'admin') {
        session_name('ADMIN_SESSION');
    } elseif ($role === 'student') {
        session_name('STUDENT_SESSION');
    } else {
        session_name('EXAM_SYSTEM_SESSION');
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is Super Admin
 */
function isSuperAdmin($conn = null, $user_id = null) {
    if (!$user_id) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    if (!$user_id) {
        return false;
    }
    
    if (!$conn) {
        require_once __DIR__ . '/../config/database.php';
        $db = Database::getInstance();
        $conn = $db->getConnection();
    }
    
    $stmt = $conn->prepare("
        SELECT ar.role_name 
        FROM admin_users au 
        JOIN admin_roles ar ON au.role_id = ar.id 
        WHERE au.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['role_name'] === 'super_admin';
    }
    return false;
}

// Modified isLoggedIn to check current session
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /online_exam_system/index.php');
        exit();
    }
}

function requireAdmin() {
    // Initialize admin session if not already
    if (session_name() !== 'ADMIN_SESSION') {
        session_write_close();
        session_name('ADMIN_SESSION');
        session_start();
    }
    
    if (!isLoggedIn() || !isAdmin()) {
        header('Location: /online_exam_system/index.php');
        exit();
    }
}

function requireStudent() {
    // Initialize student session if not already
    if (session_name() !== 'STUDENT_SESSION') {
        session_write_close();
        session_name('STUDENT_SESSION');
        session_start();
    }
    
    if (!isLoggedIn() || !isStudent()) {
        header('Location: /online_exam_system/index.php');
        exit();
    }
}

function login($user) {
    // Initialize session based on role
    initSession($user['role']);
    
    // Clear any existing session data for this role
    session_unset();
    
    // Set new session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

function logout() {
    // Log activity before destroying session
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        require_once __DIR__ . '/../includes/functions.php';
        $role = $_SESSION['role'];
        logActivity($_SESSION['user_id'], $role . '_logout', ucfirst($role) . ' logged out');
    }
    
    // Destroy current session data
    $_SESSION = array();
    
    // Delete session cookie for current session
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy current session
    session_destroy();
}

function logoutAll() {
    // Logout from all sessions
    if (isset($_SESSION['user_id'])) {
        require_once __DIR__ . '/../includes/functions.php';
        logActivity($_SESSION['user_id'], 'logout_all', 'Logged out from all sessions');
    }
    
    // Destroy all session cookies
    if (isset($_COOKIE['ADMIN_SESSION'])) {
        setcookie('ADMIN_SESSION', '', time() - 3600, '/');
    }
    if (isset($_COOKIE['STUDENT_SESSION'])) {
        setcookie('STUDENT_SESSION', '', time() - 3600, '/');
    }
    if (isset($_COOKIE['EXAM_SYSTEM_SESSION'])) {
        setcookie('EXAM_SYSTEM_SESSION', '', time() - 3600, '/');
    }
    
    // Destroy all sessions
    session_name('ADMIN_SESSION');
    session_start();
    session_destroy();
    
    session_name('STUDENT_SESSION');
    session_start();
    session_destroy();
    
    session_name('EXAM_SYSTEM_SESSION');
    session_start();
    session_destroy();
}

function getCurrentUser() {
    if (isLoggedIn()) {
        try {
            require_once __DIR__ . '/../config/database.php';
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
    return null;
}

function redirectToDashboard() {
    if (isAdmin()) {
        header('Location: /online_exam_system/admin/dashboard.php');
    } elseif (isStudent()) {
        header('Location: /online_exam_system/student/dashboard.php');
    } else {
        header('Location: /online_exam_system/index.php');
    }
    exit();
}
?>