<?php
/**
 * Admin Dashboard
 * Online Examination System - MissionTech College
 */

// Initialize admin session
session_name('ADMIN_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

// Dashboard is visible to all admins, no additional permission check needed
// The permissions.php already checks if user is admin


// Check if user is logged in as admin in THIS session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get user info
$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
$admin_id = $_SESSION['user_id'];

$db = Database::getInstance();
$conn = $db->getConnection();

// Get statistics
$stats = [];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$stats['students'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM exams WHERE status = 'published'");
$stats['exams'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM questions");
$stats['questions'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE status = 'completed'");
$stats['attempts'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT AVG(percentage) as avg FROM exam_attempts WHERE status = 'completed'");
$stats['avg_score'] = round($result->fetch_assoc()['avg'] ?? 0, 1);

// Get new feature statistics
$result = $conn->query("SELECT COUNT(*) as total FROM study_materials");
$stats['materials'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM announcements");
$stats['announcements'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM certificates");
$stats['certificates'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM exam_schedules WHERE status = 'upcoming'");
$stats['upcoming_exams'] = $result->fetch_assoc()['total'];

$page_title = 'Admin Dashboard';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i>
            <?php echo date('l, F j, Y'); ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo number_format($stats['students']); ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-file-alt"></i></div>
            <div class="stat-value"><?php echo number_format($stats['exams']); ?></div>
            <div class="stat-label">Active Exams</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;"><i class="fas fa-question-circle"></i></div>
            <div class="stat-value"><?php echo number_format($stats['questions']); ?></div>
            <div class="stat-label">Total Questions</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0f4ff; color: #8b5cf6;"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?php echo number_format($stats['attempts']); ?></div>
            <div class="stat-label">Exam Attempts</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-star"></i></div>
            <div class="stat-value"><?php echo $stats['avg_score']; ?>%</div>
            <div class="stat-label">Average Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;"><i class="fas fa-book-open"></i></div>
            <div class="stat-value"><?php echo $stats['materials']; ?></div>
            <div class="stat-label">Study Materials</div>
        </div>
    </div>

    <!-- Quick Actions Grid -->
    <div class="quick-actions-section">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        <div class="actions-grid">

            <a href="email-settings.php" class="action-card">
                <i class="fas fa-envelope"></i>
                <h3>Email Settings</h3>
                <p>Toggle email notifications</p>
            </a>

            <a href="exams.php" class="action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Create Exam</h3>
                <p>Add a new exam</p>
            </a>
            <a href="questions.php" class="action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Add Question</h3>
                <p>Create new questions</p>
            </a>

            <a href="subjects.php" class="action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Add Subject</h3>
                <p>Create new subjects</p>
            </a>

            <a href="bulk-import.php" class="action-card">
                <i class="fas fa-upload"></i>
                <h3>Bulk Import</h3>
                <p>Import questions via CSV</p>
            </a>
            <a href="announcements.php" class="action-card">
                <i class="fas fa-bullhorn"></i>
                <h3>Post Announcement</h3>
                <p>Send notification</p>
            </a>
            <a href="study-materials.php" class="action-card">
                <i class="fas fa-book-open"></i>
                <h3>Upload Materials</h3>
                <p>Share study resources</p>
            </a>
            <a href="exam-schedule.php" class="action-card">
                <i class="fas fa-calendar-alt"></i>
                <h3>Schedule Exam</h3>
                <p>Set exam date/time</p>
            </a>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="features-section">
        <h2><i class="fas fa-crown"></i> Enhanced Features</h2>
        <div class="features-grid">
            <a href="export-results.php" class="feature-card">
                <i class="fas fa-download"></i>
                <h3>Export Results</h3>
                <p>Download results as PDF/Excel</p>
                <span class="feature-badge">New</span>
            </a>
            <a href="question-analytics.php" class="feature-card">
                <i class="fas fa-chart-line"></i>
                <h3>Question Analytics</h3>
                <p>Track question performance</p>
                <span class="feature-badge">New</span>
            </a>
            <a href="progress-report.php" class="feature-card">
                <i class="fas fa-chart-bar"></i>
                <h3>Progress Reports</h3>
                <p>Student performance reports</p>
                <span class="feature-badge">New</span>
            </a>
            <a href="users.php" class="feature-card">
                <i class="fas fa-users"></i>
                <h3>Manage Students</h3>
                <p>View and edit students</p>
            </a>
            <a href="results.php" class="feature-card">
                <i class="fas fa-chart-bar"></i>
                <h3>Exam Results</h3>
                <p>View all results</p>
            </a>
            <a href="email-settings.php" class="feature-card">
                <i class="fas fa-envelope"></i>
                <h3>Email Control</h3>
                <p>Enable/disable all emails</p>
                <span class="feature-badge">New</span>
            </a>
            <a href="settings.php" class="feature-card">
                <i class="fas fa-cog"></i>
                <h3>System Settings</h3>
                <p>Configure the system</p>
            </a>
            <a href="chatbot-settings.php" class="feature-card">
                <i class="fas fa-robot"></i>
                <h3>Chatbot Settings</h3>
                <p>Configure AI chatbot</p>
                <span class="feature-badge">New</span>
            </a>
            

        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <a href="activity-log.php">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="activity-list">
            <?php
            $result = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");
            while ($log = $result->fetch_assoc()):
            ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title"><?php echo htmlspecialchars($log['action']); ?></div>
                    <div class="activity-meta">
                        <span><i class="fas fa-clock"></i> <?php echo timeAgo($log['created_at']); ?></span>
                        <span><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($log['details']); ?></span>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-header h1 {
    font-size: 2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.date-badge {
    background: white;
    padding: 0.6rem 1.2rem;
    border-radius: 40px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #10b981;
    font-weight: 600;
    border: 1px solid #eef2f6;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    text-align: center;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
}

.stat-icon i {
    font-size: 1.5rem;
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-label {
    color: #64748b;
    font-size: 0.8rem;
    margin-top: 0.25rem;
}

.quick-actions-section,
.features-section {
    margin-bottom: 2rem;
}

.quick-actions-section h2,
.features-section h2 {
    font-size: 1.3rem;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.action-card {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 16px;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s;
    display: block;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(16,185,129,0.4);
}

.action-card i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.action-card h3 {
    font-size: 1rem;
    margin-bottom: 0.25rem;
}

.action-card p {
    font-size: 0.75rem;
    opacity: 0.9;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.feature-card {
    background: white;
    border: 1px solid #eef2f6;
    border-radius: 16px;
    padding: 1.5rem;
    text-decoration: none;
    transition: all 0.3s;
    position: relative;
    display: block;
}

.feature-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border-color: #10b981;
}

.feature-card i {
    font-size: 2rem;
    color: #10b981;
    margin-bottom: 0.75rem;
}

.feature-card h3 {
    font-size: 1rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.feature-card p {
    font-size: 0.8rem;
    color: #64748b;
}

.feature-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: #10b981;
    color: white;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.6rem;
    font-weight: 600;
}

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
    font-size: 1.1rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-header a {
    color: #10b981;
    text-decoration: none;
    font-size: 0.85rem;
}

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
}

.activity-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: #64748b;
}

.activity-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .actions-grid,
    .features-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../includes/footer.php'; ?>