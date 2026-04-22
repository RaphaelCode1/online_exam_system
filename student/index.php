<?php
/**
 * Student Dashboard Landing Page
 * Online Examination System
 */

// Initialize student session
session_name('STUDENT_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student';

// Get student statistics
$stats = [];

// Total exams taken
$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
$stats['exams_taken'] = $result->fetch_assoc()['total'];

// Average score
$result = $conn->query("SELECT AVG(percentage) as avg FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
$stats['avg_score'] = round($result->fetch_assoc()['avg'] ?? 0, 1);

// Exams passed
$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND passed = 1 AND status = 'completed'");
$stats['exams_passed'] = $result->fetch_assoc()['total'];

// Available exams
$result = $conn->query("SELECT COUNT(*) as total FROM exams WHERE status = 'published'");
$stats['available_exams'] = $result->fetch_assoc()['total'];

// Certificates earned
$result = $conn->query("SELECT COUNT(*) as total FROM certificates WHERE student_id = $user_id");
$stats['certificates'] = $result->fetch_assoc()['total'];

// Current streak
$streak = $conn->query("SELECT current_streak FROM student_streaks WHERE student_id = $user_id")->fetch_assoc();
$stats['streak'] = $streak['current_streak'] ?? 0;

// Recent announcements
$announcements = $conn->query("
    SELECT * FROM announcements 
    WHERE (target_role = 'all' OR target_role = 'students') 
    AND is_active = 1 
    AND (expires_at IS NULL OR expires_at > NOW()) 
    ORDER BY is_pinned DESC, created_at DESC 
    LIMIT 3
");

// Upcoming exams
$upcoming_exams = $conn->query("
    SELECT s.*, e.title, e.description, e.duration_minutes 
    FROM exam_schedules s 
    JOIN exams e ON s.exam_id = e.id 
    WHERE s.status = 'upcoming' 
    ORDER BY s.start_time ASC 
    LIMIT 3
");

// Recent exam results
$recent_results = $conn->query("
    SELECT ea.*, e.title as exam_title 
    FROM exam_attempts ea 
    JOIN exams e ON ea.exam_id = e.id 
    WHERE ea.student_id = $user_id AND ea.status = 'completed' 
    ORDER BY ea.created_at DESC 
    LIMIT 5
");

$page_title = 'Student Dashboard';
include '../includes/header.php';
?>

<div class="student-dashboard">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-content">
            <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
            <p>Ready to continue your learning journey? Check out your stats and available exams below.</p>
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
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['available_exams']; ?></div>
                <div class="stat-label">Available Exams</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                <i class="fas fa-pen-alt"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['exams_taken']; ?></div>
                <div class="stat-label">Exams Taken</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['exams_passed']; ?></div>
                <div class="stat-label">Exams Passed</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0f4ff; color: #8b5cf6;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['avg_score']; ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #ef4444;">
                <i class="fas fa-fire"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['streak']; ?> days</div>
                <div class="stat-label">Current Streak</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?php echo $stats['certificates']; ?></div>
                <div class="stat-label">Certificates Earned</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        <div class="actions-grid">
            <a href="take-exam.php" class="action-card">
                <i class="fas fa-pen-alt"></i>
                <h3>Take Exam</h3>
                <p>Start a new examination</p>
            </a>
            <a href="results.php" class="action-card">
                <i class="fas fa-chart-bar"></i>
                <h3>View Results</h3>
                <p>Check your performance</p>
            </a>
            <a href="materials.php" class="action-card">
                <i class="fas fa-book-open"></i>
                <h3>Study Materials</h3>
                <p>Access learning resources</p>
            </a>
            <a href="leaderboard.php" class="action-card">
                <i class="fas fa-trophy"></i>
                <h3>Leaderboard</h3>
                <p>See how you rank</p>
            </a>
        </div>
    </div>

    <!-- Announcements & Upcoming Exams -->
    <div class="two-columns">
        <!-- Announcements -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bullhorn"></i> Announcements</h3>
                <a href="#">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="announcements-list">
                <?php if ($announcements->num_rows > 0): ?>
                    <?php while ($ann = $announcements->fetch_assoc()): ?>
                        <div class="announcement-item <?php echo $ann['priority']; ?>">
                            <div class="announcement-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="announcement-content">
                                <div class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                <div class="announcement-text"><?php echo htmlspecialchars(substr($ann['content'], 0, 100)); ?></div>
                                <div class="announcement-meta">
                                    <i class="fas fa-clock"></i> <?php echo timeAgo($ann['created_at']); ?>
                                    <?php if ($ann['is_pinned']): ?>
                                        <span class="pinned-badge"><i class="fas fa-thumbtack"></i> Pinned</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state small">
                        <i class="fas fa-inbox"></i>
                        <p>No announcements yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Exams -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Upcoming Exams</h3>
                <a href="take-exam.php">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="upcoming-list">
                <?php if ($upcoming_exams->num_rows > 0): ?>
                    <?php while ($exam = $upcoming_exams->fetch_assoc()): ?>
                        <div class="upcoming-item">
                            <div class="exam-date">
                                <span class="date-day"><?php echo date('d', strtotime($exam['start_time'])); ?></span>
                                <span class="date-month"><?php echo date('M', strtotime($exam['start_time'])); ?></span>
                            </div>
                            <div class="exam-info">
                                <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                                <div class="exam-details">
                                    <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($exam['start_time'])); ?></span>
                                    <span><i class="fas fa-hourglass-half"></i> <?php echo $exam['duration_minutes']; ?> min</span>
                                </div>
                                <a href="take-exam.php?id=<?php echo $exam['exam_id']; ?>" class="btn-start">Start Exam <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state small">
                        <i class="fas fa-calendar-check"></i>
                        <p>No upcoming exams scheduled</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Results -->
    <div class="card full-width">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Exam Results</h3>
            <a href="results.php">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="results-table">
            <?php if ($recent_results->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Date</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($result = $recent_results->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($result['exam_title']); ?></strong></td>
                                <td><?php echo date('M d, Y', strtotime($result['created_at'])); ?></td>
                                <td><?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?></td>
                                <td>
                                    <span class="percentage-badge <?php echo $result['passed'] ? 'passed' : 'failed'; ?>">
                                        <?php echo $result['percentage']; ?>%
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $result['passed'] ? 'passed' : 'failed'; ?>">
                                        <i class="fas fa-<?php echo $result['passed'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                        <?php echo $result['passed'] ? 'Passed' : 'Failed'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="results.php?exam=<?php echo $result['exam_id']; ?>" class="btn-icon">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <p>No exam results yet. Take your first exam!</p>
                    <a href="take-exam.php" class="btn-primary btn-sm">Take Exam Now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.student-dashboard {
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
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.stat-label {
    font-size: 0.7rem;
    color: #64748b;
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
    margin-bottom: 1.5rem;
}

/* Cards */
.card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
}

.card.full-width {
    grid-column: span 2;
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

/* Announcements */
.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.announcement-item {
    display: flex;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 12px;
    transition: background 0.3s;
}

.announcement-item.urgent {
    background: #fef2f2;
    border-left: 3px solid #ef4444;
}

.announcement-item.high {
    background: #fffbeb;
    border-left: 3px solid #f59e0b;
}

.announcement-item:hover {
    background: #f8fafc;
}

.announcement-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #10b981;
}

.announcement-content {
    flex: 1;
}

.announcement-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
}

.announcement-text {
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.announcement-meta {
    font-size: 0.65rem;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pinned-badge {
    background: #f59e0b;
    color: white;
    padding: 2px 6px;
    border-radius: 20px;
    font-size: 0.6rem;
}

/* Upcoming Exams */
.upcoming-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.upcoming-item {
    display: flex;
    gap: 1rem;
    padding: 0.75rem;
    border-radius: 12px;
    transition: all 0.3s;
}

.upcoming-item:hover {
    background: #f8fafc;
}

.exam-date {
    width: 60px;
    text-align: center;
    padding: 0.5rem;
    background: #f1f5f9;
    border-radius: 12px;
}

.date-day {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    display: block;
}

.date-month {
    font-size: 0.7rem;
    color: #64748b;
}

.exam-info {
    flex: 1;
}

.exam-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.exam-details {
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.exam-details i {
    width: 12px;
}

.btn-start {
    font-size: 0.7rem;
    color: #10b981;
    text-decoration: none;
    font-weight: 600;
}

.btn-start:hover {
    text-decoration: underline;
}

/* Results Table */
.results-table {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    text-align: left;
    padding: 0.75rem;
    background: #f8fafc;
    color: #1e293b;
    font-size: 0.75rem;
    font-weight: 600;
}

.data-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #eef2f6;
    font-size: 0.8rem;
}

.percentage-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.percentage-badge.passed {
    background: #ecfdf5;
    color: #10b981;
}

.percentage-badge.failed {
    background: #fef2f2;
    color: #ef4444;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.status-badge.passed {
    background: #ecfdf5;
    color: #10b981;
}

.status-badge.failed {
    background: #fef2f2;
    color: #ef4444;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: #f1f5f9;
    color: #1e293b;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.7rem;
    transition: all 0.3s;
}

.btn-icon:hover {
    background: #10b981;
    color: white;
}

.btn-primary.btn-sm {
    display: inline-block;
    padding: 6px 12px;
    font-size: 0.75rem;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    margin-top: 0.5rem;
}

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
    
    .card.full-width {
        grid-column: span 1;
    }
}

@media (max-width: 768px) {
    .student-dashboard {
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
    
    .data-table {
        font-size: 0.7rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.5rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>