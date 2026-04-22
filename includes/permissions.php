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
        $perms_data = json_decode($row['permissions'], true);
        
        // Handle different permission formats
        if (isset($perms_data['all']) && $perms_data['all'] === true) {
            // Super admin has all permissions
            $permissions = [
                'role_name' => $row['role_name'],
                'is_super_admin' => true,
                'permissions' => []
            ];
        } else {
            $permissions = [
                'role_name' => $row['role_name'],
                'is_super_admin' => false,
                'permissions' => $perms_data
            ];
        }
        return $permissions;
    }
    
    return null;
}

// Check if user has specific permission
function checkPermission($module, $action) {
    $adminPermissions = getUserAdminPermissions();
    
    // Super admin has all permissions
    if ($adminPermissions && ($adminPermissions['is_super_admin'] === true || $adminPermissions['role_name'] === 'super_admin')) {
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

function canViewAdminManagement() { return checkPermission('admin_management', 'view'); }
function canManageAdminManagement() { return checkPermission('admin_management', 'manage'); }

function canViewMaterials() { return checkPermission('materials', 'view'); }
function canCreateMaterials() { return checkPermission('materials', 'create'); }
function canEditMaterials() { return checkPermission('materials', 'edit'); }
function canDeleteMaterials() { return checkPermission('materials', 'delete'); }

function canViewAnnouncements() { return checkPermission('announcements', 'view'); }
function canCreateAnnouncements() { return checkPermission('announcements', 'create'); }
function canEditAnnouncements() { return checkPermission('announcements', 'edit'); }
function canDeleteAnnouncements() { return checkPermission('announcements', 'delete'); }

// Run this automatically when file is included
// This ensures all admin pages require proper permissions
if (!isAdmin()) {
    header('Location: /online_exam_system/admin/login.php');
    exit();
}

// Get current page to check appropriate permissions
$current_file = basename($_SERVER['PHP_SELF']);

// Auto-detect which permission is needed based on the page (NO DUPLICATES)
$permission_map = [
    // User Management
    'users.php' => ['module' => 'users', 'action' => 'view'],
    'profile.php' => ['module' => 'users', 'action' => 'view'],
    'edit-profile.php' => ['module' => 'users', 'action' => 'edit'],
    'add-user.php' => ['module' => 'users', 'action' => 'create'],
    'edit-user.php' => ['module' => 'users', 'action' => 'edit'],
    'delete-user.php' => ['module' => 'users', 'action' => 'delete'],
    'change-password.php' => ['module' => 'users', 'action' => 'edit'],
    'forgot-password.php' => ['module' => 'users', 'action' => 'view'],
    'reset-password.php' => ['module' => 'users', 'action' => 'view'],
    
    // Exam Management
    'exams.php' => ['module' => 'exams', 'action' => 'view'],
    'edit-exam.php' => ['module' => 'exams', 'action' => 'edit'],
    'delete-exam.php' => ['module' => 'exams', 'action' => 'delete'],
    'exam-schedule.php' => ['module' => 'exams', 'action' => 'view'],
    'schedule-exam.php' => ['module' => 'exams', 'action' => 'create'],
    'update-exam.php' => ['module' => 'exams', 'action' => 'edit'],
    
    // Question Management
    'questions.php' => ['module' => 'questions', 'action' => 'view'],
    'edit-question.php' => ['module' => 'questions', 'action' => 'edit'],
    'delete-question.php' => ['module' => 'questions', 'action' => 'delete'],
    'bulk-import.php' => ['module' => 'questions', 'action' => 'create'],
    'ai-generator.php' => ['module' => 'questions', 'action' => 'create'],
    'update-question.php' => ['module' => 'questions', 'action' => 'edit'],
    
    // Results Management
    'results.php' => ['module' => 'results', 'action' => 'view'],
    'export-results.php' => ['module' => 'results', 'action' => 'export'],
    'delete-result.php' => ['module' => 'results', 'action' => 'delete'],
    'view-result.php' => ['module' => 'results', 'action' => 'view'],
    
    // Settings
    'settings.php' => ['module' => 'settings', 'action' => 'view'],
    'chatbot-settings.php' => ['module' => 'settings', 'action' => 'view'],
    'email-settings.php' => ['module' => 'settings', 'action' => 'edit'],
    'system-settings.php' => ['module' => 'settings', 'action' => 'edit'],
    
    // Announcements
    'announcements.php' => ['module' => 'announcements', 'action' => 'view'],
    'edit-announcement.php' => ['module' => 'announcements', 'action' => 'edit'],
    'delete-announcement.php' => ['module' => 'announcements', 'action' => 'delete'],
    
    // Study Materials
    'study-materials.php' => ['module' => 'materials', 'action' => 'view'],
    'edit-study-material.php' => ['module' => 'materials', 'action' => 'edit'],
    'delete-study-material.php' => ['module' => 'materials', 'action' => 'delete'],
    
    // Subjects & Topics
    'subjects.php' => ['module' => 'exams', 'action' => 'view'],
    'edit-subject.php' => ['module' => 'exams', 'action' => 'edit'],
    'add-subject.php' => ['module' => 'exams', 'action' => 'create'],
    'delete-subject.php' => ['module' => 'exams', 'action' => 'delete'],
    'edit-topic.php' => ['module' => 'exams', 'action' => 'edit'],
    'add-topic.php' => ['module' => 'exams', 'action' => 'create'],
    'delete-topic.php' => ['module' => 'exams', 'action' => 'delete'],
    
    // Reports
    'question-analytics.php' => ['module' => 'reports', 'action' => 'view'],
    'progress-report.php' => ['module' => 'reports', 'action' => 'view'],
    'export-report.php' => ['module' => 'reports', 'action' => 'generate'],
    
    // Admin Management
    'admin-management.php' => ['module' => 'admin_management', 'action' => 'view'],
    
    // Sidebar & Common
    'sidebar.php' => ['module' => 'users', 'action' => 'view'],
    'dashboard.php' => ['module' => 'users', 'action' => 'view'],
    'index.php' => ['module' => 'users', 'action' => 'view'],
    'logout.php' => ['module' => 'users', 'action' => 'view'],
    'login.php' => ['module' => 'users', 'action' => 'view'],
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