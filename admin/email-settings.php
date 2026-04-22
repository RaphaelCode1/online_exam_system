<?php
/**
 * Quick Email Settings Page
 * Allows admin to quickly enable/disable all email functionality
 */

session_name('ADMIN_SESSION');
session_start();

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';

// Handle toggle - MUST BE BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enable_emails = isset($_POST['enable_emails']) ? (int)$_POST['enable_emails'] : 0;
    
    // Update system_settings
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                            VALUES ('enable_emails', ?, 'boolean', 'email', NOW()) 
                            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
    $stmt->bind_param("ss", $enable_emails, $enable_emails);
    
    if ($stmt->execute()) {
        // Also update developer_settings for redundancy
        $stmt2 = $conn->prepare("INSERT INTO developer_settings (setting_key, setting_value, setting_type, setting_group, updated_at) 
                                 VALUES ('enable_emails', ?, 'text', 'system', NOW()) 
                                 ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt2->bind_param("ss", $enable_emails, $enable_emails);
        $stmt2->execute();
        $stmt2->close();
        
        $message = "Email settings updated! " . ($enable_emails ? "Emails are now ENABLED." : "Emails are now DISABLED.");
        logActivity($_SESSION['user_id'], 'email_settings_toggle', "Email notifications set to: " . ($enable_emails ? 'ENABLED' : 'DISABLED'));
    } else {
        $error = "Failed to update email settings: " . $stmt->error;
    }
    $stmt->close();
}

$current_status = isEmailEnabled();

$page_title = 'Email Settings';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-envelope"></i> Email Notification Settings</h1>
        <p>Control whether the system sends email notifications</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-toggle-on"></i> Email Status</h2>
        </div>
        
        <div class="status-card <?php echo $current_status ? 'enabled' : 'disabled'; ?>">
            <div class="status-icon">
                <i class="fas <?php echo $current_status ? 'fa-envelope-open-text' : 'fa-envelope'; ?>"></i>
            </div>
            <div class="status-text">
                <h3><?php echo $current_status ? 'Email Notifications: ENABLED' : 'Email Notifications: DISABLED'; ?></h3>
                <p><?php echo $current_status ? 'The system will send emails for registration, results, password reset, and announcements.' : 'The system will NOT send any emails. All email functions are disabled.'; ?></p>
            </div>
        </div>
        
        <form method="POST" action="" class="toggle-form">
            <div class="toggle-options">
                <label class="toggle-option <?php echo $current_status ? 'active' : ''; ?>">
                    <input type="radio" name="enable_emails" value="1" <?php echo $current_status ? 'checked' : ''; ?>>
                    <div class="toggle-card">
                        <i class="fas fa-envelope-open-text"></i>
                        <h4>Enable Emails</h4>
                        <p>Send all email notifications</p>
                    </div>
                </label>
                <label class="toggle-option <?php echo !$current_status ? 'active' : ''; ?>">
                    <input type="radio" name="enable_emails" value="0" <?php echo !$current_status ? 'checked' : ''; ?>>
                    <div class="toggle-card">
                        <i class="fas fa-ban"></i>
                        <h4>Disable Emails</h4>
                        <p>No emails will be sent</p>
                    </div>
                </label>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>What happens when emails are disabled?</strong><br>
                    - No registration welcome emails<br>
                    - No exam result emails<br>
                    - No password reset emails<br>
                    - No announcement emails<br>
                    - System will log that emails were skipped<br>
                    - All other functionality remains unchanged
                </div>
            </div>
            
            <div class="warning-box" id="warningBox" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Warning:</strong> With emails disabled, students will not receive password reset codes or exam results via email. Make sure they have another way to access this information.
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Email Settings
                </button>
            </div>
        </form>
    </div>
    
    <!-- Email Logs -->
    <?php
    $table_check = $conn->query("SHOW TABLES LIKE 'email_logs'");
    if ($table_check->num_rows > 0):
        $recent_emails = $conn->query("SELECT * FROM email_logs ORDER BY sent_at DESC LIMIT 10");
    ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-history"></i> Recent Email Logs</h2>
            <?php if ($recent_emails && $recent_emails->num_rows > 0): ?>
            <a href="clear-email-logs.php" class="btn-secondary">Clear Logs</a>
            <?php endif; ?>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_emails && $recent_emails->num_rows > 0): ?>
                        <?php while ($log = $recent_emails->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['recipient_email']); ?></td>
                            <td><?php echo htmlspecialchars(substr($log['subject'], 0, 50)); ?>...</td>
                            <td><?php echo htmlspecialchars($log['type']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $log['status']; ?>">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, H:i', strtotime($log['sent_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No email logs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.container {
    max-width: 800px;
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

.card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    margin-bottom: 2rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #eef2f6;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.status-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    border-radius: 16px;
    margin-bottom: 2rem;
}

.status-card.enabled {
    background: #ecfdf5;
    border: 1px solid #10b981;
}

.status-card.disabled {
    background: #fef2f2;
    border: 1px solid #ef4444;
}

.status-icon i {
    font-size: 3rem;
}

.status-card.enabled .status-icon i {
    color: #10b981;
}

.status-card.disabled .status-icon i {
    color: #ef4444;
}

.status-text h3 {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
}

.status-text p {
    color: #64748b;
    font-size: 0.85rem;
}

.toggle-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.toggle-option {
    cursor: pointer;
}

.toggle-option input {
    display: none;
}

.toggle-card {
    text-align: center;
    padding: 1.5rem;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    transition: all 0.3s;
    background: white;
}

.toggle-option.active .toggle-card {
    border-color: #10b981;
    background: #ecfdf5;
}

.toggle-card i {
    font-size: 2rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.toggle-option.active .toggle-card i {
    color: #10b981;
}

.toggle-card h4 {
    font-size: 1rem;
    margin-bottom: 0.25rem;
    color: #1e293b;
}

.toggle-card p {
    font-size: 0.7rem;
    color: #64748b;
}

.info-box {
    background: #e0f2fe;
    padding: 1rem;
    border-radius: 12px;
    display: flex;
    gap: 12px;
    color: #0284c7;
    margin-bottom: 1rem;
}

.warning-box {
    background: #fef3c7;
    padding: 1rem;
    border-radius: 12px;
    display: flex;
    gap: 12px;
    color: #d97706;
    margin-bottom: 1rem;
}

.form-actions {
    margin-top: 1.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    width: 100%;
}

.btn-secondary {
    background: #f1f5f9;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #eef2f6;
}

.data-table th {
    background: #f8fafc;
    font-weight: 600;
    font-size: 0.8rem;
}

.status-badge {
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-badge.sent {
    background: #ecfdf5;
    color: #10b981;
}

.status-badge.failed {
    background: #fef2f2;
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

@media (max-width: 640px) {
    .container {
        padding: 1rem;
    }
    .toggle-options {
        grid-template-columns: 1fr;
    }
    .status-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
// Show warning when disabling emails
document.querySelectorAll('input[name="enable_emails"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const warningBox = document.getElementById('warningBox');
        if (this.value === '0' && this.checked) {
            warningBox.style.display = 'flex';
        } else {
            warningBox.style.display = 'none';
        }
    });
});

// Highlight active toggle
document.querySelectorAll('.toggle-option input').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.toggle-option').forEach(opt => {
            opt.classList.remove('active');
        });
        if (this.checked) {
            this.closest('.toggle-option').classList.add('active');
        }
    });
});

// Initial warning check
const initialWarning = document.querySelector('input[name="enable_emails"][value="0"]');
if (initialWarning && initialWarning.checked) {
    document.getElementById('warningBox').style.display = 'flex';
}
</script>

<?php include '../includes/footer.php'; ?>