<?php
/**
 * Admin Profile Page
 * Allows administrators to view and update their profile information with avatar upload
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$success = '';

$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

if (!$user) {
    header('Location: dashboard.php');
    exit();
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['avatar']['type'];
        $file_size = $_FILES['avatar']['size'];
        $max_size = 5 * 1024 * 1024;
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Only JPG, PNG, GIF, and WEBP images are allowed";
        } elseif ($file_size > $max_size) {
            $error = "File size must be less than 5MB";
        } else {
            $upload_dir = '../uploads/avatars/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (!empty($user['avatar']) && file_exists($upload_dir . $user['avatar'])) {
                unlink($upload_dir . $user['avatar']);
            }
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $file_path)) {
                $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->bind_param("si", $filename, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['avatar'] = $filename;
                    $success = "Profile picture updated successfully!";
                    $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
                    logActivity($user_id, 'avatar_upload', 'Admin uploaded new profile picture');
                } else {
                    $error = "Failed to update profile picture";
                }
                $stmt->close();
            } else {
                $error = "Failed to upload image";
            }
        }
    } else {
        $error = "Please select an image to upload";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string(trim($_POST['full_name']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $user_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Email already in use by another account';
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $full_name, $email, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $success = 'Profile updated successfully!';
                $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
                logActivity($user_id, 'profile_update', 'Admin updated profile');
            } else {
                $error = 'Error updating profile: ' . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success = 'Password changed successfully!';
            logActivity($user_id, 'password_change', 'Admin changed password');
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            sendPasswordChangeNotificationEmail($user['email'], $user['full_name'], $ip_address, date('F j, Y \a\t H:i:s'));
        } else {
            $error = 'Error changing password: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle avatar removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_avatar'])) {
    if (!empty($user['avatar'])) {
        $upload_dir = '../uploads/avatars/';
        if (file_exists($upload_dir . $user['avatar'])) {
            unlink($upload_dir . $user['avatar']);
        }
        
        $stmt = $conn->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['avatar'] = null;
            $success = "Profile picture removed successfully!";
            $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
            logActivity($user_id, 'avatar_removed', 'Admin removed profile picture');
        } else {
            $error = "Failed to remove profile picture";
        }
        $stmt->close();
    }
}

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM exams WHERE created_by = $user_id");
$stats['exams_created'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) as total FROM questions WHERE created_by = $user_id");
$stats['questions_created'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT created_at FROM activity_log WHERE user_id = $user_id AND action = 'login' ORDER BY created_at DESC LIMIT 1");
$stats['last_login'] = $result->fetch_assoc()['created_at'] ?? 'Never';
$result = $conn->query("SELECT COUNT(*) as total FROM activity_log WHERE user_id = $user_id");
$stats['activity_count'] = $result->fetch_assoc()['total'];

$page_title = 'Admin Profile';
include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 2rem;">Admin Profile</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="dashboard-grid" style="margin-bottom: 2rem;">
        <div class="stat-card"><div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;"><i class="fas fa-file-alt"></i></div><div class="stat-value"><?php echo $stats['exams_created']; ?></div><div class="stat-label">Exams Created</div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #fef3c7; color: #f59e0b;"><i class="fas fa-question-circle"></i></div><div class="stat-value"><?php echo $stats['questions_created']; ?></div><div class="stat-label">Questions Created</div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-history"></i></div><div class="stat-value"><?php echo $stats['activity_count']; ?></div><div class="stat-label">Total Activities</div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #f0f4ff; color: #8b5cf6;"><i class="fas fa-clock"></i></div><div class="stat-value"><?php echo $stats['last_login'] != 'Never' ? formatDate($stats['last_login'], 'M j, Y') : 'Never'; ?></div><div class="stat-label">Last Login</div></div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem;">
        <div class="card"><h3 style="margin-bottom: 1.5rem;"><i class="fas fa-camera"></i> Profile Picture</h3>
            <div style="display: flex; flex-direction: column; align-items: center; gap: 1.5rem;">
                <div><?php if (!empty($user['avatar']) && file_exists('../uploads/avatars/' . $user['avatar'])): ?><img src="../uploads/avatars/<?php echo $user['avatar']; ?>?t=<?php echo time(); ?>" alt="Profile Picture" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--primary);"><?php else: ?><div style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: white;"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div><?php endif; ?></div>
                <form method="POST" enctype="multipart/form-data" style="width: 100%;"><div class="form-group"><label>Upload New Picture</label><input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" class="form-control"><small>Max size: 5MB. Allowed: JPG, PNG, GIF, WEBP</small></div>
                <div style="display: flex; gap: 1rem;"><button type="submit" name="upload_avatar" class="btn btn-primary"><i class="fas fa-upload"></i> Upload</button><?php if (!empty($user['avatar'])): ?><button type="submit" name="remove_avatar" class="btn btn-danger" onclick="return confirm('Remove your profile picture?')"><i class="fas fa-trash"></i> Remove</button><?php endif; ?></div></form>
            </div>
        </div>
        
        <div class="card"><h3 style="margin-bottom: 1.5rem;"><i class="fas fa-user-circle"></i> Profile Information</h3>
            <form method="POST"><div class="form-group"><label>Username</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled><small>Username cannot be changed</small></div>
            <div class="form-group"><label>Full Name</label><input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
            <div class="form-group"><label>Email Address</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
            <div class="form-group"><label>Role</label><input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled></div>
            <div class="form-group"><label>Account Created</label><input type="text" class="form-control" value="<?php echo formatDate($user['created_at'], 'F j, Y \a\t H:i'); ?>" disabled></div>
            <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button></form>
        </div>
        
        <div class="card"><h3 style="margin-bottom: 1.5rem;"><i class="fas fa-lock"></i> Change Password</h3>
            <form method="POST"><div class="form-group"><label>Current Password</label><div style="position: relative;"><input type="password" name="current_password" id="current_password" class="form-control" style="padding-right: 48px;" required><button type="button" class="toggle-password" onclick="togglePassword('current_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;"><i class="fas fa-eye"></i></button></div></div>
            <div class="form-group"><label>New Password</label><div style="position: relative;"><input type="password" name="new_password" id="new_password" class="form-control" style="padding-right: 48px;" required minlength="6"><button type="button" class="toggle-password" onclick="togglePassword('new_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;"><i class="fas fa-eye"></i></button></div><small>Minimum 6 characters</small></div>
            <div class="form-group"><label>Confirm New Password</label><div style="position: relative;"><input type="password" name="confirm_password" id="confirm_password" class="form-control" style="padding-right: 48px;" required><button type="button" class="toggle-password" onclick="togglePassword('confirm_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;"><i class="fas fa-eye"></i></button></div><div id="passwordMatchMessage" style="font-size: 0.85rem; margin-top: 0.5rem;"></div></div>
            <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button></form>
        </div>
    </div>
    
    <div class="card" style="margin-top: 2rem;"><h3 style="margin-bottom: 1.5rem;"><i class="fas fa-history"></i> Recent Activity</h3>
        <div class="table-container"><table class="table"><thead><tr><th>Action</th><th>Details</th><th>IP Address</th><th>Date & Time</th></tr></thead>
        <tbody><?php $activities = $conn->query("SELECT * FROM activity_log WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 10");
        if ($activities->num_rows > 0): while ($activity = $activities->fetch_assoc()): ?>
        <tr><td><span class="badge bg-info"><?php echo htmlspecialchars($activity['action']); ?></span></td><td><?php echo htmlspecialchars($activity['details'] ?? 'No details'); ?></td><td><?php echo $activity['ip_address'] ?? 'N/A'; ?></td><td><?php echo formatDate($activity['created_at']); ?></td></tr>
        <?php endwhile; else: ?><tr><td colspan="4" style="text-align: center;">No activity recorded yet</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
</div>

<style>
.alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
.alert-success { background: #ecfdf5; color: #10b981; border-left: 4px solid #10b981; }
.alert-error { background: #fef2f2; color: #ef4444; border-left: 4px solid #ef4444; }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }
</style>

<script>
function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    const icon = input.parentElement.querySelector('.toggle-password i');
    if (input.type === 'password') { input.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
    else { input.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
}
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const matchMessage = document.getElementById('passwordMatchMessage');
if (newPassword && confirmPassword) {
    function checkPasswordMatch() {
        if (!confirmPassword.value) { matchMessage.innerHTML = ''; return; }
        if (newPassword.value === confirmPassword.value) { matchMessage.innerHTML = '<span style="color: var(--success);"><i class="fas fa-check-circle"></i> Passwords match</span>'; }
        else { matchMessage.innerHTML = '<span style="color: var(--danger);"><i class="fas fa-exclamation-circle"></i> Passwords do not match</span>'; }
    }
    newPassword.addEventListener('input', checkPasswordMatch);
    confirmPassword.addEventListener('input', checkPasswordMatch);
}
setTimeout(() => { document.querySelectorAll('.alert').forEach(alert => { alert.style.opacity = '0'; alert.style.transition = 'opacity 0.5s'; setTimeout(() => alert.remove(), 500); }); }, 5000);
</script>

<?php include '../includes/footer.php'; ?>