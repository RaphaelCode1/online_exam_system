<?php
/**
 * Activity Log Page
 * View all system activity logs with filters
 */

session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';

// Handle Clear Logs Action - MOVED TO TOP BEFORE ANY OUTPUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    // Check if user is super admin
    if (!isSuperAdmin($conn, $_SESSION['user_id'])) {
        $error = "Only Super Administrators can clear logs!";
    } else {
        $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
        
        if ($days > 0) {
            $stmt = $conn->prepare("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->bind_param("i", $days);
            
            if ($stmt->execute()) {
                $deleted = $stmt->affected_rows;
                $message = "Successfully cleared $deleted activity log entries older than $days days.";
                logActivity($_SESSION['user_id'], 'clear_activity_logs', "Cleared logs older than $days days");
            } else {
                $error = "Failed to clear logs: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Invalid number of days.";
        }
    }
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filters
$filter_user = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$filter_action = isset($_GET['action']) ? $conn->real_escape_string($_GET['action']) : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$query = "SELECT al.*, u.full_name as user_name, u.username, u.role 
          FROM activity_log al 
          LEFT JOIN users u ON al.user_id = u.id 
          WHERE 1=1";

if ($filter_user > 0) {
    $query .= " AND al.user_id = $filter_user";
}
if (!empty($filter_action)) {
    $query .= " AND al.action LIKE '%$filter_action%'";
}
if (!empty($filter_date)) {
    $query .= " AND DATE(al.created_at) = '$filter_date'";
}

$query .= " ORDER BY al.created_at DESC LIMIT $offset, $per_page";

$activities = $conn->query($query);

// Count total for pagination
$count_query = "SELECT COUNT(*) as total FROM activity_log al WHERE 1=1";
if ($filter_user > 0) {
    $count_query .= " AND al.user_id = $filter_user";
}
if (!empty($filter_action)) {
    $count_query .= " AND al.action LIKE '%$filter_action%'";
}
if (!empty($filter_date)) {
    $count_query .= " AND DATE(al.created_at) = '$filter_date'";
}
$count_result = $conn->query($count_query);
$total_logs = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $per_page);

// Get users for filter
$users = $conn->query("SELECT id, full_name, username, role FROM users ORDER BY full_name");

// Get unique actions for filter
$actions = $conn->query("SELECT DISTINCT action FROM activity_log ORDER BY action");

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM activity_log");
$stats['total'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(*) as total FROM activity_log WHERE DATE(created_at) = CURDATE()");
$stats['today'] = $result->fetch_assoc()['total'];
$result = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM activity_log WHERE user_id IS NOT NULL AND user_id > 0");
$stats['active_users'] = $result->fetch_assoc()['total'];

// Get most common action
$result = $conn->query("SELECT action, COUNT(*) as count FROM activity_log GROUP BY action ORDER BY count DESC LIMIT 1");
$most_common = $result->fetch_assoc();
$stats['most_common_action'] = $most_common['action'] ?? 'N/A';

$page_title = 'Activity Log';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-history"></i> Activity Log</h1>
        <p>View all system activities and user actions</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total Activities</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['today']); ?></div>
            <div class="stat-label">Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo $stats['active_users']; ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0f4ff; color: #8b5cf6;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value"><?php echo htmlspecialchars($stats['most_common_action']); ?></div>
            <div class="stat-label">Most Common Action</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="filter-group">
                <label>User</label>
                <select name="user" class="form-control" onchange="this.form.submit()">
                    <option value="0">All Users</option>
                    <?php 
                    $users->data_seek(0);
                    while ($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']) . ' (' . $user['role'] . ')'; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Action</label>
                <select name="action" class="form-control" onchange="this.form.submit()">
                    <option value="">All Actions</option>
                    <?php 
                    $actions->data_seek(0);
                    while ($action = $actions->fetch_assoc()): ?>
                        <option value="<?php echo htmlspecialchars($action['action']); ?>" <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action['action']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>" onchange="this.form.submit()">
            </div>
            <div class="filter-actions">
                <a href="activity-log.php" class="btn btn-outline">Reset Filters</a>
            </div>
        </form>
    </div>
    
    <!-- Clear Logs (Super Admin Only) -->
    <?php if (isSuperAdmin($conn, $_SESSION['user_id'])): ?>
    <div class="clear-logs-card">
        <div class="clear-header">
            <h3><i class="fas fa-trash-alt"></i> Clear Old Logs</h3>
            <span class="super-admin-badge">Super Admin Only</span>
        </div>
        <form method="POST" class="clear-form" onsubmit="return confirmClearLogs()">
            <div class="form-row">
                <div class="form-group">
                    <label>Delete logs older than:</label>
                    <select name="days" class="form-control" required>
                        <option value="7">7 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                        <option value="180">180 days</option>
                        <option value="365">1 year</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" name="clear_logs" value="1" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Clear Old Logs
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Activity Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Activity Logs</h3>
            <span><?php echo $total_logs; ?> records</span>
        </div>
        <div class="table-container">
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </thead>
                    <tbody>
                        <?php if ($activities && $activities->num_rows > 0): ?>
                            <?php while ($log = $activities->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                                        <br><small><?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $log['role'] ?? 'system'; ?>">
                                            <?php echo ucfirst($log['role'] ?? 'System'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="action-badge">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td class="details-cell">
                                        <?php echo htmlspecialchars($log['details'] ?? 'No details'); ?>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y H:i:s', strtotime($log['created_at'])); ?>
                                        <br><small><?php echo timeAgo($log['created_at']); ?></small>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No activity logs found</p>
                                         </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page-1; ?>&user=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>&date=<?php echo $filter_date; ?>" class="page-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <a href="?page=<?php echo $i; ?>&user=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>&date=<?php echo $filter_date; ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&user=<?php echo $filter_user; ?>&action=<?php echo urlencode($filter_action); ?>&date=<?php echo $filter_date; ?>" class="page-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* All existing styles plus clear logs fix */
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

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    text-align: center;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-label {
    font-size: 0.75rem;
    color: #64748b;
}

.filters-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #eef2f6;
}

.filters-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-size: 0.8rem;
    font-weight: 500;
    color: #64748b;
}

.form-control {
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.9rem;
    background: white;
}

.btn-outline {
    background: transparent;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 10px 20px;
    border-radius: 12px;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.btn-outline:hover {
    border-color: #10b981;
    color: #10b981;
}

.clear-logs-card {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.clear-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.clear-header h3 {
    font-size: 1rem;
    color: #991b1b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.super-admin-badge {
    background: #f59e0b;
    color: white;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.clear-form .form-row {
    display: flex;
    gap: 1rem;
    align-items: flex-end;
    flex-wrap: wrap;
}

.clear-form .form-group {
    margin-bottom: 0;
}

.btn-danger {
    background: #ef4444;
    color: white;
    padding: 10px 24px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.card {
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
    overflow: hidden;
}

.card-header {
    padding: 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.card-header h3 {
    font-size: 1rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.table-container {
    overflow-x: auto;
}

.activity-table {
    width: 100%;
    border-collapse: collapse;
}

.activity-table th,
.activity-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eef2f6;
}

.activity-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #475569;
    font-size: 0.85rem;
}

.activity-table tr:hover {
    background: #fafcff;
}

.role-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.role-admin {
    background: #e0f2fe;
    color: #3b82f6;
}

.role-student {
    background: #ecfdf5;
    color: #10b981;
}

.role-system {
    background: #f1f5f9;
    color: #64748b;
}

.action-badge {
    background: #fef3c7;
    color: #d97706;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-block;
}

.details-cell {
    max-width: 300px;
    word-wrap: break-word;
    font-size: 0.85rem;
    color: #475569;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #94a3b8;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    padding: 1.5rem;
    border-top: 1px solid #eef2f6;
    flex-wrap: wrap;
}

.page-btn {
    padding: 8px 16px;
    background: #f1f5f9;
    color: #1e293b;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s;
}

.page-btn:hover,
.page-btn.active {
    background: #10b981;
    color: white;
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

@media (max-width: 1024px) {
    .filters-form {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        margin-top: 0.5rem;
    }
    
    .clear-form .form-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .clear-form .btn-danger {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .activity-table th:nth-child(5),
    .activity-table td:nth-child(5) {
        display: none;
    }
    
    .details-cell {
        max-width: 150px;
    }
}
</style>

<script>
function confirmClearLogs() {
    const days = document.querySelector('select[name="days"]').value;
    const daysText = document.querySelector('select[name="days"] option:checked').text;
    return confirm(`⚠️ WARNING: This will permanently delete ALL activity logs older than ${daysText}.\n\nThis action cannot be undone!\n\nAre you absolutely sure?`);
}
</script>

<?php include '../includes/footer.php'; ?>