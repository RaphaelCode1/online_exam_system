<?php
/**
 * Student Profile Page
 * View and update profile information with avatar upload and email notifications
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

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
            $filename = 'student_' . $user_id . '_' . time() . '.' . $file_extension;
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
                    logActivity($user_id, 'avatar_upload', 'Student uploaded new profile picture');
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
                logActivity($user_id, 'profile_update', 'Student updated profile');
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
            logActivity($user_id, 'password_change', 'Student changed password');
            
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
            logActivity($user_id, 'avatar_removed', 'Student removed profile picture');
        } else {
            $error = "Failed to remove profile picture";
        }
        $stmt->close();
    }
}

// Get student statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
$stats['exams_taken'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT AVG(percentage) as avg FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
$stats['avg_score'] = round($result->fetch_assoc()['avg'] ?? 0, 1);
$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND passed = 1");
$stats['exams_passed'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND passed = 0 AND status = 'completed'");
$stats['exams_failed'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT SUM(score) as total_score FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
$stats['total_score'] = $result->fetch_assoc()['total_score'] ?? 0;
$result = $conn->query("SELECT MAX(percentage) as best FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
$stats['best_score'] = round($result->fetch_assoc()['best'] ?? 0, 1);
$result = $conn->query("SELECT created_at FROM activity_log WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 1");
$stats['last_activity'] = $result->fetch_assoc()['created_at'] ?? 'Never';
$stats['member_since'] = formatDate($user['created_at'], 'F j, Y');

$page_title = 'My Profile';
include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 2rem;">My Profile</h1>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="stat-card"><div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;"><i class="fas fa-file-alt"></i></div><div class="stat-value"><?php echo $stats['exams_taken']; ?></div><div class="stat-label">Exams Taken</div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-trophy"></i></div><div class="stat-value"><?php echo $stats['exams_passed']; ?></div><div class="stat-label">Exams Passed</div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #fef3c7; color: #f59e0b;"><i class="fas fa-chart-line"></i></div><div class="stat-value"><?php echo $stats['avg_score']; ?>%</div><div class="stat-label">Average Score</div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #f0f4ff; color: #8b5cf6;"><i class="fas fa-star"></i></div><div class="stat-value"><?php echo $stats['best_score']; ?>%</div><div class="stat-label">Best Score</div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-times-circle"></i></div><div class="stat-value"><?php echo $stats['exams_failed']; ?></div><div class="stat-label">Exams Failed</div></div>
        <div class="stat-card"><div class="stat-icon" style="background: #f1f5f9; color: #475569;"><i class="fas fa-clock"></i></div><div class="stat-value"><?php echo $stats['last_activity'] != 'Never' ? timeAgo($stats['last_activity']) : 'Never'; ?></div><div class="stat-label">Last Activity</div></div>
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
            <div class="form-group"><label>Member Since</label><input type="text" class="form-control" value="<?php echo $stats['member_since']; ?>" disabled></div>
            <div class="form-group"><label>Total Score</label><input type="text" class="form-control" value="<?php echo $stats['total_score']; ?> points" disabled></div>
            <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button></form>
        </div>
        
        <div class="card"><h3 style="margin-bottom: 1.5rem;"><i class="fas fa-lock"></i> Change Password</h3>
            <form method="POST"><div class="form-group"><label>Current Password</label><div style="position: relative;"><input type="password" name="current_password" id="current_password" class="form-control" style="padding-right: 48px;" required><button type="button" class="toggle-password" onclick="togglePassword('current_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;"><i class="fas fa-eye"></i></button></div></div>
            <div class="form-group"><label>New Password</label><div style="position: relative;"><input type="password" name="new_password" id="new_password" class="form-control" style="padding-right: 48px;" required minlength="6"><button type="button" class="toggle-password" onclick="togglePassword('new_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;"><i class="fas fa-eye"></i></button></div><small>Minimum 6 characters</small></div>
            <div class="form-group"><label>Confirm New Password</label><div style="position: relative;"><input type="password" name="confirm_password" id="confirm_password" class="form-control" style="padding-right: 48px;" required><button type="button" class="toggle-password" onclick="togglePassword('confirm_password')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;"><i class="fas fa-eye"></i></button></div><div id="passwordMatchMessage" style="font-size: 0.85rem; margin-top: 0.5rem;"></div></div>
            <button type="submit" name="change_password" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button></form>
        </div>
    </div>
    
    <div class="card" style="margin-top: 2rem;"><h3 style="margin-bottom: 1.5rem;"><i class="fas fa-history"></i> Recent Exam Attempts</h3>
        <div class="table-container"><table class="table"><thead><tr><th>Exam Title</th><th>Date</th><th>Score</th><th>Percentage</th><th>Correct/Wrong</th><th>Result</th><th>Action</th></tr></thead>
        <tbody><?php $recent_attempts = $conn->query("SELECT ea.*, e.title as exam_title FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.student_id = $user_id AND ea.status = 'completed' ORDER BY ea.created_at DESC LIMIT 5");
        if ($recent_attempts && $recent_attempts->num_rows > 0): while ($attempt = $recent_attempts->fetch_assoc()): ?>
        <tr><td><strong><?php echo htmlspecialchars($attempt['exam_title']); ?></strong></td><td><?php echo formatDate($attempt['created_at']); ?></td><td><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions']; ?></td><td><span class="badge <?php echo $attempt['percentage'] >= 70 ? 'bg-success' : ($attempt['percentage'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>"><?php echo $attempt['percentage']; ?>%</span></td><td><span style="color: #10b981;"><?php echo $attempt['correct_answers']; ?></span> / <span style="color: #ef4444;"><?php echo $attempt['wrong_answers']; ?></span></td><td><span class="badge <?php echo $attempt['passed'] ? 'bg-success' : 'bg-danger'; ?>"><?php echo $attempt['passed'] ? 'Passed' : 'Failed'; ?></span></td><td><a href="results.php?exam=<?php echo $attempt['exam_id']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> View Details</a></td></tr>
        <?php endwhile; else: ?><tr><td colspan="7" style="text-align: center; padding: 2rem;"><i class="fas fa-file-alt" style="font-size: 2rem; color: var(--gray); margin-bottom: 0.5rem; display: block;"></i>No exam attempts yet.<br><a href="take-exam.php" class="btn btn-primary btn-sm" style="margin-top: 1rem;">Take an Exam</a></td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($recent_attempts && $recent_attempts->num_rows > 0): ?><div style="margin-top: 1rem; text-align: center;"><a href="results.php" class="btn btn-outline"><i class="fas fa-list"></i> View All Results</a></div><?php endif; ?>
    </div>
</div>

<style>
.alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
.alert-success { background: #ecfdf5; color: #10b981; border-left: 4px solid #10b981; }
.alert-error { background: #fef2f2; color: #ef4444; border-left: 4px solid #ef4444; }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; }
.stat-card { background: white; border-radius: 12px; padding: 1.2rem; border: 1px solid var(--border); transition: transform 0.2s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
.stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.75rem; }
.stat-value { font-size: 1.5rem; font-weight: 700; }
.stat-label { font-size: 0.75rem; color: var(--gray); }
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }
.bg-success { background: #ecfdf5; color: #10b981; }
.bg-danger { background: #fef2f2; color: #ef4444; }
.bg-warning { background: #fef3c7; color: #d97706; }
.btn-sm { padding: 0.25rem 0.75rem; font-size: 0.75rem; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--gray); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.form-control[disabled] { background: var(--light); cursor: not-allowed; }
.toggle-password:hover { color: var(--primary); }
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