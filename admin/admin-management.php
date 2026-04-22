<?php
/**
 * Admin Management Page
 * Manage administrators with different roles and permissions
 */

session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// Only super admin can access this page
if (!canManageAdminManagement()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


// Check if user is logged in as admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

$current_user_id = $_SESSION['user_id'];

// Check if user is super admin using the function from auth.php
$is_super_admin = isSuperAdmin($conn, $current_user_id);

// If not super admin, redirect
if (!$is_super_admin) {
    header('Location: dashboard.php?error=unauthorized');
    exit();
}

$message = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'admins';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Create new admin
    if ($action === 'create_admin') {
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = (int)$_POST['role_id'] ?? 0;
        
        // Validate
        if (empty($email)) {
            $error = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } elseif (empty($full_name)) {
            $error = "Full name is required";
        } elseif (empty($password)) {
            $error = "Password is required";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } else {
            // Check if user exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user_id = $user['id'];
                
                // Check if already an admin
                $check_admin = $conn->prepare("SELECT id FROM admin_users WHERE user_id = ?");
                $check_admin->bind_param("i", $user_id);
                $check_admin->execute();
                if ($check_admin->get_result()->num_rows > 0) {
                    $error = "This user is already an administrator!";
                } else {
                    // Add as admin
                    $stmt = $conn->prepare("INSERT INTO admin_users (user_id, role_id, created_by) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $user_id, $role_id, $current_user_id);
                    if ($stmt->execute()) {
                        $message = "Administrator added successfully!";
                        logActivity($current_user_id, 'create_admin', "Added admin: $email with role ID: $role_id");
                    } else {
                        $error = "Failed to add administrator: " . $conn->error;
                    }
                }
            } else {
                // Create new user first
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $username = explode('@', $email)[0];
                
                // Ensure unique username
                $username_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $username_check->bind_param("s", $username);
                $username_check->execute();
                if ($username_check->get_result()->num_rows > 0) {
                    $username = $username . '_' . rand(100, 999);
                }
                
                $stmt = $conn->prepare("INSERT INTO users (username, email, full_name, password, role, status) VALUES (?, ?, ?, ?, 'admin', 1)");
                $stmt->bind_param("ssss", $username, $email, $full_name, $hashed_password);
                
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    
                    // Add as admin
                    $stmt2 = $conn->prepare("INSERT INTO admin_users (user_id, role_id, created_by) VALUES (?, ?, ?)");
                    $stmt2->bind_param("iii", $user_id, $role_id, $current_user_id);
                    if ($stmt2->execute()) {
                        $message = "Administrator created successfully!";
                        logActivity($current_user_id, 'create_admin', "Created admin: $email");
                    } else {
                        $error = "User created but failed to assign admin role: " . $conn->error;
                    }
                } else {
                    $error = "Failed to create user: " . $conn->error;
                }
            }
        }
    }
    
    // Update admin role
    elseif ($action === 'update_role') {
        $admin_id = (int)$_POST['admin_id'];
        $role_id = (int)$_POST['role_id'];
        
        // Prevent changing own role
        $check_self = $conn->prepare("SELECT user_id FROM admin_users WHERE id = ?");
        $check_self->bind_param("i", $admin_id);
        $check_self->execute();
        $result = $check_self->get_result();
        $admin = $result->fetch_assoc();
        
        if ($admin['user_id'] == $current_user_id) {
            $error = "You cannot change your own role!";
        } else {
            $stmt = $conn->prepare("UPDATE admin_users SET role_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $role_id, $admin_id);
            if ($stmt->execute()) {
                $message = "Admin role updated successfully!";
                logActivity($current_user_id, 'update_admin_role', "Updated admin ID: $admin_id to role ID: $role_id");
            } else {
                $error = "Failed to update role: " . $conn->error;
            }
        }
    }
    
    // Create new role
    elseif ($action === 'create_role') {
        $role_name = trim($_POST['role_name'] ?? '');
        $role_description = trim($_POST['role_description'] ?? '');
        
        if (empty($role_name)) {
            $error = "Role name is required";
        } else {
            $role_name = strtolower(str_replace(' ', '_', $role_name));
            $permissions = json_encode([
                'users' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'exams' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'questions' => ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'results' => ['view' => false, 'export' => false, 'delete' => false],
                'settings' => ['view' => false, 'edit' => false],
                'reports' => ['view' => false, 'generate' => false]
            ]);
            
            $stmt = $conn->prepare("INSERT INTO admin_roles (role_name, description, permissions) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $role_name, $role_description, $permissions);
            if ($stmt->execute()) {
                $message = "New role created successfully!";
                logActivity($current_user_id, 'create_role', "Created role: $role_name");
            } else {
                $error = "Failed to create role: " . $conn->error;
            }
        }
    }
    
    // Update role permissions
    elseif ($action === 'update_permissions') {
        $role_id = (int)$_POST['role_id'];
        $permissions = $_POST['permissions'] ?? [];
        
        // Build permissions array
        $permissions_data = [
            'users' => [
                'view' => isset($permissions['users_view']),
                'create' => isset($permissions['users_create']),
                'edit' => isset($permissions['users_edit']),
                'delete' => isset($permissions['users_delete'])
            ],
            'exams' => [
                'view' => isset($permissions['exams_view']),
                'create' => isset($permissions['exams_create']),
                'edit' => isset($permissions['exams_edit']),
                'delete' => isset($permissions['exams_delete'])
            ],
            'questions' => [
                'view' => isset($permissions['questions_view']),
                'create' => isset($permissions['questions_create']),
                'edit' => isset($permissions['questions_edit']),
                'delete' => isset($permissions['questions_delete'])
            ],
            'results' => [
                'view' => isset($permissions['results_view']),
                'export' => isset($permissions['results_export']),
                'delete' => isset($permissions['results_delete'])
            ],
            'settings' => [
                'view' => isset($permissions['settings_view']),
                'edit' => isset($permissions['settings_edit'])
            ],
            'reports' => [
                'view' => isset($permissions['reports_view']),
                'generate' => isset($permissions['reports_generate'])
            ]
        ];
        
        $permissions_json = json_encode($permissions_data);
        
        $stmt = $conn->prepare("UPDATE admin_roles SET permissions = ? WHERE id = ?");
        $stmt->bind_param("si", $permissions_json, $role_id);
        if ($stmt->execute()) {
            $message = "Permissions updated successfully!";
            logActivity($current_user_id, 'update_permissions', "Updated permissions for role ID: $role_id");
        } else {
            $error = "Failed to update permissions: " . $conn->error;
        }
    }
    
    // Remove admin
    elseif ($action === 'remove_admin') {
        $admin_id = (int)$_POST['admin_id'];
        
        // Check if trying to remove self
        $check_self = $conn->prepare("SELECT user_id FROM admin_users WHERE id = ?");
        $check_self->bind_param("i", $admin_id);
        $check_self->execute();
        $result = $check_self->get_result();
        $admin = $result->fetch_assoc();
        
        if ($admin['user_id'] == $current_user_id) {
            $error = "You cannot remove yourself!";
        } else {
            $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            if ($stmt->execute()) {
                $message = "Administrator removed successfully!";
                logActivity($current_user_id, 'remove_admin', "Removed admin ID: $admin_id");
            } else {
                $error = "Failed to remove administrator: " . $conn->error;
            }
        }
    }
    
    // Delete role
    elseif ($action === 'delete_role') {
        $role_id = (int)$_POST['role_id'];
        
        // Check if role has admins assigned
        $check = $conn->prepare("SELECT COUNT(*) as count FROM admin_users WHERE role_id = ?");
        $check->bind_param("i", $role_id);
        $check->execute();
        $result = $check->get_result();
        $count = $result->fetch_assoc()['count'];
        
        if ($count > 0) {
            $error = "Cannot delete role that has administrators assigned!";
        } else {
            $stmt = $conn->prepare("DELETE FROM admin_roles WHERE id = ?");
            $stmt->bind_param("i", $role_id);
            if ($stmt->execute()) {
                $message = "Role deleted successfully!";
                logActivity($current_user_id, 'delete_role', "Deleted role ID: $role_id");
            } else {
                $error = "Failed to delete role: " . $conn->error;
            }
        }
    }
}

// Get all admin roles
$roles = $conn->query("SELECT * FROM admin_roles ORDER BY 
    CASE role_name 
        WHEN 'super_admin' THEN 1 
        WHEN 'admin' THEN 2 
        WHEN 'moderator' THEN 3 
        ELSE 4 
    END");

// Get all admins with their details
$admins = $conn->query("
    SELECT au.*, u.username, u.email, u.full_name, u.status, ar.role_name, ar.permissions,
           u.created_at as user_created_at,
           creator.full_name as created_by_name
    FROM admin_users au
    JOIN users u ON au.user_id = u.id
    JOIN admin_roles ar ON au.role_id = ar.id
    LEFT JOIN users creator ON au.created_by = creator.id
    ORDER BY 
        CASE ar.role_name 
            WHEN 'super_admin' THEN 1 
            WHEN 'admin' THEN 2 
            WHEN 'moderator' THEN 3 
            ELSE 4 
        END,
        u.full_name
");

$page_title = 'Admin Management';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-user-shield"></i> Administrator Management</h1>
        <p>Manage system administrators, roles, and permissions</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Admin Management Tabs -->
    <div class="admin-tabs">
        <a href="?tab=admins" class="admin-tab <?php echo $active_tab == 'admins' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Administrators
        </a>
        <a href="?tab=roles" class="admin-tab <?php echo $active_tab == 'roles' ? 'active' : ''; ?>">
            <i class="fas fa-tag"></i> Roles & Permissions
        </a>
    </div>
    
    <?php if ($active_tab == 'admins'): ?>
    <!-- Administrators Tab -->
    <div class="two-columns">
        <!-- Add New Admin Form -->
        <div class="card">
            <h2><i class="fas fa-user-plus"></i> Add New Administrator</h2>
            <form method="POST" id="createAdminForm">
                <input type="hidden" name="action" value="create_admin">
                
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Password *</label>
                    <div class="password-field">
                        <input type="password" name="password" id="new_password" class="form-control" required minlength="6">
                        <button type="button" class="toggle-password" onclick="togglePassword('new_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small>Minimum 6 characters</small>
                </div>
                
                <div class="form-group">
                    <label>Admin Role *</label>
                    <select name="role_id" class="form-control" required>
                        <?php 
                        $roles->data_seek(0);
                        while ($role = $roles->fetch_assoc()): 
                            if ($role['role_name'] !== 'super_admin'):
                        ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $role['role_name'])); ?>
                                - <?php echo htmlspecialchars($role['description']); ?>
                            </option>
                        <?php 
                            endif;
                        endwhile; 
                        ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Administrator</button>
            </form>
        </div>
        
        <!-- Admin List -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Administrators List</h2>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php 
                        $roles->data_seek(0);
                        while ($admin = $admins->fetch_assoc()): 
                            $is_current = ($admin['user_id'] == $current_user_id);
                        ?>
                          <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($admin['full_name']); ?></strong>
                                <?php if ($is_current): ?>
                                    <span class="badge current-user">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $admin['role_name']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $admin['role_name'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $admin['status'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $admin['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($admin['created_by_name'] ?? 'System'); ?></td>
                            <td class="actions">
                                <?php if (!$is_current && $admin['role_name'] !== 'super_admin'): ?>
                                    <button onclick="editAdmin(<?php echo $admin['id']; ?>, <?php echo $admin['role_id']; ?>)" class="btn-icon" title="Edit Role">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="removeAdmin(<?php echo $admin['id']; ?>)" class="btn-icon delete" title="Remove Admin">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php elseif ($admin['role_name'] === 'super_admin' && !$is_current): ?>
                                    <span class="text-muted">Protected</span>
                                <?php elseif ($is_current): ?>
                                    <span class="text-muted">Current</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Roles & Permissions Tab -->
    <div class="roles-container">
        <!-- Create New Role Form -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Create New Role</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_role">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Role Name *</label>
                        <input type="text" name="role_name" class="form-control" placeholder="e.g., Editor, Viewer, etc." required>
                        <small>Use lowercase letters and underscores (e.g., content_editor)</small>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="role_description" class="form-control" placeholder="Brief description of this role">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Create Role</button>
            </form>
        </div>
        
        <!-- Roles List with Permission Management -->
        <?php 
        $roles->data_seek(0);
        while ($role = $roles->fetch_assoc()): 
            if ($role['role_name'] === 'super_admin') continue; // Super admin permissions cannot be edited
            $permissions = json_decode($role['permissions'], true);
        ?>
        <div class="card role-permissions-card">
            <div class="role-header">
                <h3>
                    <i class="fas fa-tag"></i> 
                    <?php echo ucfirst(str_replace('_', ' ', $role['role_name'])); ?>
                    <span class="role-description"><?php echo htmlspecialchars($role['description']); ?></span>
                </h3>
                <?php if ($role['role_name'] !== 'super_admin' && $role['role_name'] !== 'admin' && $role['role_name'] !== 'moderator'): ?>
                <form method="POST" onsubmit="return confirm('Delete this role? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_role">
                    <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                    <button type="submit" class="btn-icon delete" title="Delete Role">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_permissions">
                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                
                <div class="permissions-grid">
                    <!-- Users Permissions -->
                    <div class="permission-group">
                        <h4><i class="fas fa-users"></i> Users Management</h4>
                        <div class="permission-checkboxes">
                            <label><input type="checkbox" name="permissions[users_view]" <?php echo isset($permissions['users']['view']) && $permissions['users']['view'] ? 'checked' : ''; ?>> View Users</label>
                            <label><input type="checkbox" name="permissions[users_create]" <?php echo isset($permissions['users']['create']) && $permissions['users']['create'] ? 'checked' : ''; ?>> Create Users</label>
                            <label><input type="checkbox" name="permissions[users_edit]" <?php echo isset($permissions['users']['edit']) && $permissions['users']['edit'] ? 'checked' : ''; ?>> Edit Users</label>
                            <label><input type="checkbox" name="permissions[users_delete]" <?php echo isset($permissions['users']['delete']) && $permissions['users']['delete'] ? 'checked' : ''; ?>> Delete Users</label>
                        </div>
                    </div>
                    
                    <!-- Exams Permissions -->
                    <div class="permission-group">
                        <h4><i class="fas fa-file-alt"></i> Exams Management</h4>
                        <div class="permission-checkboxes">
                            <label><input type="checkbox" name="permissions[exams_view]" <?php echo isset($permissions['exams']['view']) && $permissions['exams']['view'] ? 'checked' : ''; ?>> View Exams</label>
                            <label><input type="checkbox" name="permissions[exams_create]" <?php echo isset($permissions['exams']['create']) && $permissions['exams']['create'] ? 'checked' : ''; ?>> Create Exams</label>
                            <label><input type="checkbox" name="permissions[exams_edit]" <?php echo isset($permissions['exams']['edit']) && $permissions['exams']['edit'] ? 'checked' : ''; ?>> Edit Exams</label>
                            <label><input type="checkbox" name="permissions[exams_delete]" <?php echo isset($permissions['exams']['delete']) && $permissions['exams']['delete'] ? 'checked' : ''; ?>> Delete Exams</label>
                        </div>
                    </div>
                    
                    <!-- Questions Permissions -->
                    <div class="permission-group">
                        <h4><i class="fas fa-question-circle"></i> Questions Management</h4>
                        <div class="permission-checkboxes">
                            <label><input type="checkbox" name="permissions[questions_view]" <?php echo isset($permissions['questions']['view']) && $permissions['questions']['view'] ? 'checked' : ''; ?>> View Questions</label>
                            <label><input type="checkbox" name="permissions[questions_create]" <?php echo isset($permissions['questions']['create']) && $permissions['questions']['create'] ? 'checked' : ''; ?>> Create Questions</label>
                            <label><input type="checkbox" name="permissions[questions_edit]" <?php echo isset($permissions['questions']['edit']) && $permissions['questions']['edit'] ? 'checked' : ''; ?>> Edit Questions</label>
                            <label><input type="checkbox" name="permissions[questions_delete]" <?php echo isset($permissions['questions']['delete']) && $permissions['questions']['delete'] ? 'checked' : ''; ?>> Delete Questions</label>
                        </div>
                    </div>
                    
                    <!-- Results Permissions -->
                    <div class="permission-group">
                        <h4><i class="fas fa-chart-bar"></i> Results Management</h4>
                        <div class="permission-checkboxes">
                            <label><input type="checkbox" name="permissions[results_view]" <?php echo isset($permissions['results']['view']) && $permissions['results']['view'] ? 'checked' : ''; ?>> View Results</label>
                            <label><input type="checkbox" name="permissions[results_export]" <?php echo isset($permissions['results']['export']) && $permissions['results']['export'] ? 'checked' : ''; ?>> Export Results</label>
                            <label><input type="checkbox" name="permissions[results_delete]" <?php echo isset($permissions['results']['delete']) && $permissions['results']['delete'] ? 'checked' : ''; ?>> Delete Results</label>
                        </div>
                    </div>
                    
                    <!-- Settings Permissions -->
                    <div class="permission-group">
                        <h4><i class="fas fa-cog"></i> System Settings</h4>
                        <div class="permission-checkboxes">
                            <label><input type="checkbox" name="permissions[settings_view]" <?php echo isset($permissions['settings']['view']) && $permissions['settings']['view'] ? 'checked' : ''; ?>> View Settings</label>
                            <label><input type="checkbox" name="permissions[settings_edit]" <?php echo isset($permissions['settings']['edit']) && $permissions['settings']['edit'] ? 'checked' : ''; ?>> Edit Settings</label>
                        </div>
                    </div>
                    
                    <!-- Reports Permissions -->
                    <div class="permission-group">
                        <h4><i class="fas fa-chart-line"></i> Reports</h4>
                        <div class="permission-checkboxes">
                            <label><input type="checkbox" name="permissions[reports_view]" <?php echo isset($permissions['reports']['view']) && $permissions['reports']['view'] ? 'checked' : ''; ?>> View Reports</label>
                            <label><input type="checkbox" name="permissions[reports_generate]" <?php echo isset($permissions['reports']['generate']) && $permissions['reports']['generate'] ? 'checked' : ''; ?>> Generate Reports</label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Permissions</button>
            </form>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
    
    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Update Admin Role</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                
                <div class="form-group">
                    <label>Select Role</label>
                    <select name="role_id" id="edit_role_id" class="form-control" required>
                        <?php 
                        $roles->data_seek(0);
                        while ($role = $roles->fetch_assoc()): 
                            if ($role['role_name'] !== 'super_admin'):
                        ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $role['role_name'])); ?>
                                - <?php echo htmlspecialchars($role['description']); ?>
                            </option>
                        <?php 
                            endif;
                        endwhile; 
                        ?>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Remove Admin Modal -->
    <div id="removeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Remove Administrator</h2>
                <button class="modal-close" onclick="closeRemoveModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this administrator?</p>
                <p class="text-warning">This action cannot be undone. The user will no longer have admin access.</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="remove_admin">
                <input type="hidden" name="admin_id" id="remove_admin_id">
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeRemoveModal()">Cancel</button>
                    <button type="submit" class="btn-danger">Remove Administrator</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* All existing styles plus new ones */
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.page-header {
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header p {
    color: #64748b;
    margin-top: 0.5rem;
}

/* Admin Tabs */
.admin-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0.5rem;
}

.admin-tab {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    color: #64748b;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.admin-tab:hover {
    background: #f1f5f9;
    color: #10b981;
}

.admin-tab.active {
    background: #10b981;
    color: white;
}

.two-columns {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    margin-bottom: 2rem;
}

.card h2 {
    font-size: 1.3rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #1e293b;
    border-bottom: 2px solid #eef2f6;
    padding-bottom: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1e293b;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
}

.password-field {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #94a3b8;
    padding: 8px;
}

.toggle-password:hover {
    color: #10b981;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(16,185,129,0.4);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-cancel {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 10px 20px;
}

.btn-cancel:hover {
    border-color: #ef4444;
    color: #ef4444;
}

.btn-icon {
    background: none;
    border: none;
    padding: 8px;
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s;
    margin: 0 4px;
}

.btn-icon i {
    font-size: 1rem;
    color: #64748b;
}

.btn-icon:hover {
    background: #f1f5f9;
}

.btn-icon.delete:hover {
    background: #fef2f2;
}

.btn-icon.delete:hover i {
    color: #ef4444;
}

.alert {
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-success {
    background: #ecfdf5;
    color: #10b981;
    border-left: 4px solid #10b981;
}

.alert-error {
    background: #fef2f2;
    color: #ef4444;
    border-left: 4px solid #ef4444;
}

.table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
}

.admin-table th {
    text-align: left;
    padding: 1rem;
    background: #f8fafc;
    color: #1e293b;
    font-weight: 600;
    font-size: 0.85rem;
}

.admin-table td {
    padding: 1rem;
    border-bottom: 1px solid #eef2f6;
    vertical-align: middle;
}

.role-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.role-super_admin {
    background: #fef3c7;
    color: #f59e0b;
}

.role-admin {
    background: #e0f2fe;
    color: #3b82f6;
}

.role-moderator {
    background: #ecfdf5;
    color: #10b981;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-badge.active {
    background: #ecfdf5;
    color: #10b981;
}

.status-badge.inactive {
    background: #fef2f2;
    color: #ef4444;
}

.badge.current-user {
    background: #10b981;
    color: white;
    font-size: 0.65rem;
    padding: 2px 8px;
    border-radius: 30px;
    margin-left: 8px;
    display: inline-block;
}

.text-muted {
    color: #94a3b8;
    font-size: 0.8rem;
}

.text-warning {
    color: #f59e0b;
}

/* Roles & Permissions Styles */
.roles-container {
    margin-top: 1rem;
}

.role-permissions-card {
    margin-bottom: 2rem;
}

.role-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #eef2f6;
}

.role-header h3 {
    font-size: 1.2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.role-description {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: normal;
}

.permissions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.permission-group {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 12px;
}

.permission-group h4 {
    font-size: 0.9rem;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.permission-checkboxes {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.permission-checkboxes label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.85rem;
    color: #475569;
    cursor: pointer;
}

.permission-checkboxes input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
    accent-color: #10b981;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.modal-content {
    background: white;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 1.2rem;
    color: #1e293b;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #94a3b8;
}

.modal-close:hover {
    color: #ef4444;
}

.modal-body {
    padding: 1.5rem;
}

.form-actions {
    padding: 1.5rem;
    border-top: 1px solid #eef2f6;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

@media (max-width: 1024px) {
    .two-columns {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .permissions-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .admin-table {
        font-size: 0.8rem;
    }
    
    .admin-table th,
    .admin-table td {
        padding: 0.75rem;
    }
}
</style>

<script>
// Toggle password visibility
function togglePassword(inputId, button) {
    const passwordInput = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function editAdmin(adminId, roleId) {
    document.getElementById('edit_admin_id').value = adminId;
    document.getElementById('edit_role_id').value = roleId;
    document.getElementById('editModal').style.display = 'flex';
}

function removeAdmin(adminId) {
    document.getElementById('remove_admin_id').value = adminId;
    document.getElementById('removeModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closeRemoveModal() {
    document.getElementById('removeModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const editModal = document.getElementById('editModal');
    const removeModal = document.getElementById('removeModal');
    if (event.target === editModal) {
        closeModal();
    }
    if (event.target === removeModal) {
        closeRemoveModal();
    }
}

// Form validation
document.getElementById('createAdminForm')?.addEventListener('submit', function(e) {
    const password = document.getElementById('new_password').value;
    if (password.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long');
    }
});
</script>

<?php include '../includes/footer.php'; ?>