<?php
/**
 * Clear Email Logs
 * Delete all email logs from the database
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

// Handle clear logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    $stmt = $conn->prepare("TRUNCATE TABLE email_logs");
    if ($stmt->execute()) {
        $message = "All email logs have been cleared successfully!";
        logActivity($_SESSION['user_id'], 'clear_email_logs', "Cleared all email logs");
    } else {
        $error = "Failed to clear email logs: " . $conn->error;
    }
    $stmt->close();
}

// Get count of logs before clearing
$log_count = 0;
$table_check = $conn->query("SHOW TABLES LIKE 'email_logs'");
if ($table_check->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as count FROM email_logs");
    $log_count = $result->fetch_assoc()['count'];
}

$page_title = 'Clear Email Logs';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-trash-alt"></i> Clear Email Logs</h1>
        <p>Delete all email notification logs from the system</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="warning-card">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="warning-content">
                <h3>Warning: This action cannot be undone!</h3>
                <p>You are about to permanently delete all email logs from the system.</p>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stat">
                <div class="stat-value"><?php echo number_format($log_count); ?></div>
                <div class="stat-label">Email Logs Found</div>
            </div>
        </div>
        
        <?php if ($log_count > 0): ?>
            <form method="POST" onsubmit="return confirmClear()">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Clear All Email Logs
                </button>
                <a href="email-settings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Email Settings
                </a>
            </form>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No email logs found to clear.</p>
                <a href="email-settings.php" class="btn btn-primary">Back to Email Settings</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.container {
    max-width: 600px;
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

.card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
}

.warning-card {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 1rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.warning-card i {
    font-size: 2rem;
    color: #f59e0b;
}

.warning-content h3 {
    font-size: 1rem;
    color: #92400e;
    margin-bottom: 0.25rem;
}

.warning-content p {
    font-size: 0.85rem;
    color: #78350f;
}

.stats-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #10b981;
}

.stat-label {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.25rem;
}

.btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    font-size: 0.9rem;
}

.btn-danger {
    background: #ef4444;
    color: white;
    width: 100%;
    justify-content: center;
    margin-bottom: 1rem;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #f1f5f9;
    color: #64748b;
    width: 100%;
    justify-content: center;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 2rem;
}

.empty-state i {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
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
}
</style>

<script>
function confirmClear() {
    return confirm('Are you ABSOLUTELY sure? This will permanently delete ALL email logs. This action cannot be undone!');
}
</script>

<?php include '../includes/footer.php'; ?>