<?php
/**
 * Admin Dashboard Landing Page
 * Online Examination System
 */

// Initialize admin session
session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


// Check if admin is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';

// Get dashboard statistics
$stats = [];

// Total students
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$stats['students'] = $result->fetch_assoc()['total'];

// Total exams
$result = $conn->query("SELECT COUNT(*) as total FROM exams");
$stats['exams'] = $result->fetch_assoc()['total'];

// Total questions
$result = $conn->query("SELECT COUNT(*) as total FROM questions");
$stats['questions'] = $result->fetch_assoc()['total'];

// Total exam attempts
$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE status = 'completed'");
$stats['attempts'] = $result->fetch_assoc()['total'];

// Pending approvals (if any)
$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE status = 'pending'");
$stats['pending'] = $result->fetch_assoc()['total'];

// Recent activities
$recent_activities = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 5");

// Recent exam attempts
$recent_attempts = $conn->query("
    SELECT ea.*, u.full_name as student_name, e.title as exam_title 
    FROM exam_attempts ea 
    JOIN users u ON ea.student_id = u.id 
    JOIN exams e ON ea.exam_id = e.id 
    ORDER BY ea.created_at DESC 
    LIMIT 5
");

$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>

<div class="admin-dashboard">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-content">
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
            <p>Here's what's happening with your exam system today.</p>
        </div>
        <div class="date-time">
            <i class="fas fa-calendar-alt"></i>
            <?php echo date('l, F j, Y'); ?>
            <span class="time"><?php echo date('h:i A'); ?></span>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($stats['students']); ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($stats['exams']); ?></div>
                <div class="stat-label">Total Exams</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($stats['questions']); ?></div>
                <div class="stat-label">Total Questions</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0f4ff; color: #8b5cf6;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo number_format($stats['attempts']); ?></div>
                <div class="stat-label">Exam Attempts</div>
            </div>
        </div>
        <?php if ($stats['pending'] > 0): ?>
        <div class="stat-card warning">
            <div class="stat-icon" style="background: #fef2f2; color: #ef4444;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        <div class="actions-grid">
            <a href="exams.php" class="action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Create Exam</h3>
                <p>Add a new examination</p>
            </a>
            <a href="questions.php" class="action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Add Question</h3>
                <p>Create new questions</p>
            </a>
            <a href="users.php" class="action-card">
                <i class="fas fa-user-plus"></i>
                <h3>Add Student</h3>
                <p>Register new student</p>
            </a>
            <a href="announcements.php" class="action-card">
                <i class="fas fa-bullhorn"></i>
                <h3>Post Announcement</h3>
                <p>Send notification to students</p>
            </a>
            <a href="bulk-import.php" class="action-card">
                <i class="fas fa-upload"></i>
                <h3>Bulk Import</h3>
                <p>Import questions via CSV</p>
            </a>
            <a href="export-results.php" class="action-card">
                <i class="fas fa-download"></i>
                <h3>Export Results</h3>
                <p>Download results report</p>
            </a>
        </div>
    </div>

    <!-- Recent Activity & Attempts -->
    <div class="two-columns">
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <a href="activity-log.php">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="activity-list">
                <?php if ($recent_activities->num_rows > 0): ?>
                    <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['action']); ?></div>
                                <div class="activity-meta">
                                    <span><i class="fas fa-clock"></i> <?php echo timeAgo($activity['created_at']); ?></span>
                                    <span><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($activity['details']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state small">
                        <i class="fas fa-inbox"></i>
                        <p>No recent activity</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Exam Attempts -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> Recent Exam Attempts</h3>
                <a href="results.php">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="attempts-list">
                <?php if ($recent_attempts->num_rows > 0): ?>
                    <?php while ($attempt = $recent_attempts->fetch_assoc()): ?>
                        <div class="attempt-item">
                            <div class="attempt-info">
                                <div class="attempt-title"><?php echo htmlspecialchars($attempt['exam_title']); ?></div>
                                <div class="attempt-student"><?php echo htmlspecialchars($attempt['student_name']); ?></div>
                            </div>
                            <div class="attempt-score">
                                <span class="score-badge <?php echo $attempt['passed'] ? 'passed' : 'failed'; ?>">
                                    <?php echo $attempt['percentage']; ?>%
                                </span>
                                <a href="results.php?exam=<?php echo $attempt['exam_id']; ?>&student=<?php echo $attempt['student_id']; ?>" class="btn-icon">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state small">
                        <i class="fas fa-chart-line"></i>
                        <p>No exam attempts yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.admin-dashboard {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

/* Welcome Section */
.welcome-section {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    color: white;
}

.welcome-content h1 {
    font-size: 1.8rem;
    margin-bottom: 0.5rem;
}

.welcome-content p {
    opacity: 0.9;
}

.date-time {
    text-align: right;
    font-size: 0.9rem;
}

.date-time .time {
    display: block;
    font-size: 1.2rem;
    font-weight: 600;
    margin-top: 0.25rem;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-label {
    font-size: 0.8rem;
    color: #64748b;
}

.stat-card.warning {
    border-left: 4px solid #ef4444;
}

/* Quick Actions */
.quick-actions {
    margin-bottom: 2rem;
}

.quick-actions h2 {
    font-size: 1.3rem;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.action-card {
    background: white;
    border: 1px solid #eef2f6;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s;
    display: block;
}

.action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border-color: #10b981;
}

.action-card i {
    font-size: 2rem;
    color: #10b981;
    margin-bottom: 0.75rem;
}

.action-card h3 {
    font-size: 0.9rem;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.action-card p {
    font-size: 0.7rem;
    color: #64748b;
}

/* Two Columns */
.two-columns {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

/* Cards */
.card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #eef2f6;
}

.card-header h3 {
    font-size: 1rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-header a {
    color: #10b981;
    text-decoration: none;
    font-size: 0.8rem;
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 12px;
    transition: background 0.3s;
}

.activity-item:hover {
    background: #f8fafc;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
}

.activity-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: #64748b;
}

.activity-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Attempts List */
.attempts-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.attempt-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-radius: 12px;
    transition: background 0.3s;
}

.attempt-item:hover {
    background: #f8fafc;
}

.attempt-title {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.85rem;
}

.attempt-student {
    font-size: 0.7rem;
    color: #64748b;
    margin-top: 0.25rem;
}

.attempt-score {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.score-badge {
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.score-badge.passed {
    background: #ecfdf5;
    color: #10b981;
}

.score-badge.failed {
    background: #fef2f2;
    color: #ef4444;
}

.btn-icon {
    padding: 6px;
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.3s;
}

.btn-icon:hover {
    background: #f1f5f9;
    color: #10b981;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem;
}

.empty-state.small {
    padding: 1.5rem;
}

.empty-state i {
    font-size: 2rem;
    color: #cbd5e1;
    margin-bottom: 0.5rem;
}

.empty-state p {
    font-size: 0.8rem;
    color: #64748b;
}

/* Responsive */
@media (max-width: 1024px) {
    .two-columns {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .admin-dashboard {
        padding: 1rem;
    }
    
    .welcome-section {
        flex-direction: column;
        text-align: center;
    }
    
    .date-time {
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../includes/footer.php'; ?>