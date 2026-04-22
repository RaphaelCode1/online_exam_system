<?php
/**
 * Permission Check Helper
 * Include this file in all admin pages to enforce permission restrictions
 * Usage: require_once '../includes/permissions.php';
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Get current user's admin role and permissions
function getUserAdminPermissions() {
    if (!isAdmin()) {
        return null;
    }
    
    static $permissions = null;
    if ($permissions !== null) {
        return $permissions;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT ar.role_name, ar.permissions 
        FROM admin_users au 
        JOIN admin_roles ar ON au.role_id = ar.id 
        WHERE au.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $permissions = [
            'role_name' => $row['role_name'],
            'permissions' => json_decode($row['permissions'], true)
        ];
        return $permissions;
    }
    
    return null;
}

// Check if user has specific permission
function checkPermission($module, $action) {
    $adminPermissions = getUserAdminPermissions();
    
    // Super admin has all permissions
    if ($adminPermissions && $adminPermissions['role_name'] === 'super_admin') {
        return true;
    }
    
    // Check specific permission
    if ($adminPermissions && isset($adminPermissions['permissions'][$module])) {
        $perms = $adminPermissions['permissions'][$module];
        if (is_array($perms)) {
            return isset($perms[$action]) && $perms[$action] === true;
        }
        return $perms === true;
    }
    
    return false;
}

// Redirect if no permission
function requirePermission($module, $action, $redirectUrl = null) {
    if (!checkPermission($module, $action)) {
        $redirectUrl = $redirectUrl ?: '/online_exam_system/admin/dashboard.php';
        header("Location: $redirectUrl?error=permission_denied");
        exit();
    }
}

// Shortcut functions for common permissions
function canViewUsers() { return checkPermission('users', 'view'); }
function canCreateUsers() { return checkPermission('users', 'create'); }
function canEditUsers() { return checkPermission('users', 'edit'); }
function canDeleteUsers() { return checkPermission('users', 'delete'); }

function canViewExams() { return checkPermission('exams', 'view'); }
function canCreateExams() { return checkPermission('exams', 'create'); }
function canEditExams() { return checkPermission('exams', 'edit'); }
function canDeleteExams() { return checkPermission('exams', 'delete'); }

function canViewQuestions() { return checkPermission('questions', 'view'); }
function canCreateQuestions() { return checkPermission('questions', 'create'); }
function canEditQuestions() { return checkPermission('questions', 'edit'); }
function canDeleteQuestions() { return checkPermission('questions', 'delete'); }

function canViewResults() { return checkPermission('results', 'view'); }
function canExportResults() { return checkPermission('results', 'export'); }
function canDeleteResults() { return checkPermission('results', 'delete'); }

function canViewSettings() { return checkPermission('settings', 'view'); }
function canEditSettings() { return checkPermission('settings', 'edit'); }

function canViewReports() { return checkPermission('reports', 'view'); }
function canGenerateReports() { return checkPermission('reports', 'generate'); }

// Run this automatically when file is included
// This ensures all admin pages require proper permissions
if (!isAdmin()) {
    header('Location: /online_exam_system/admin/login.php');
    exit();
}

// Get current page to check appropriate permissions
$current_file = basename($_SERVER['PHP_SELF']);
$current_path = $_SERVER['PHP_SELF'];

// Auto-detect which permission is needed based on the page
$permission_map = [
    'users.php' => ['module' => 'users', 'action' => 'view'],
    'profile.php' => ['module' => 'users', 'action' => 'view'],
    'exams.php' => ['module' => 'exams', 'action' => 'view'],
    'edit-exam.php' => ['module' => 'exams', 'action' => 'edit'],
    'questions.php' => ['module' => 'questions', 'action' => 'view'],
    'edit-question.php' => ['module' => 'questions', 'action' => 'edit'],
    'bulk-import.php' => ['module' => 'questions', 'action' => 'create'],
    'results.php' => ['module' => 'results', 'action' => 'view'],
    'export-results.php' => ['module' => 'results', 'action' => 'export'],
    'settings.php' => ['module' => 'settings', 'action' => 'view'],
    'announcements.php' => ['module' => 'announcements', 'action' => 'view'],
    'study-materials.php' => ['module' => 'materials', 'action' => 'view'],
    'exam-schedule.php' => ['module' => 'exams', 'action' => 'view'],
    'question-analytics.php' => ['module' => 'reports', 'action' => 'view'],
    'progress-report.php' => ['module' => 'reports', 'action' => 'view'],
    'export-report.php' => ['module' => 'reports', 'action' => 'generate'],
    'admin-management.php' => ['module' => 'admin_management', 'action' => 'view'],
];

// Check permission for current page if it exists in the map
if (isset($permission_map[$current_file])) {
    $required = $permission_map[$current_file];
    if (!checkPermission($required['module'], $required['action'])) {
        header('Location: dashboard.php?error=permission_denied');
        exit();
    }
}
?>