<?php
/**
 * Student Dashboard
 * Online Examination System - MissionTech College
 */

// Initialize student session
session_name('STUDENT_SESSION');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in as student in THIS session
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

$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
$stats['attempts'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT AVG(percentage) as avg FROM exam_attempts WHERE student_id = $user_id AND status = 'completed'");
$stats['avg_score'] = round($result->fetch_assoc()['avg'] ?? 0, 1);

$result = $conn->query("SELECT COUNT(*) as total FROM exam_attempts WHERE student_id = $user_id AND passed = 1");
$stats['passed'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM exams WHERE status = 'published'");
$stats['available'] = $result->fetch_assoc()['total'];

// Get achievements count
$result = $conn->query("SELECT COUNT(*) as total FROM student_achievements WHERE student_id = $user_id");
$stats['achievements'] = $result->fetch_assoc()['total'];

// Get certificates count
$result = $conn->query("SELECT COUNT(*) as total FROM certificates WHERE student_id = $user_id");
$stats['certificates'] = $result->fetch_assoc()['total'];

// Get streak
$streak = $conn->query("SELECT current_streak FROM student_streaks WHERE student_id = $user_id")->fetch_assoc();
$stats['streak'] = $streak['current_streak'] ?? 0;

// Get recent announcements
$announcements = $conn->query("SELECT * FROM announcements WHERE (target_role = 'all' OR target_role = 'students') AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY is_pinned DESC, created_at DESC LIMIT 3");

// Get upcoming scheduled exams
$upcoming_exams = $conn->query("SELECT s.*, e.title, e.description 
                                FROM exam_schedules s 
                                JOIN exams e ON s.exam_id = e.id 
                                WHERE s.status = 'upcoming' 
                                ORDER BY s.start_time ASC 
                                LIMIT 3");

$page_title = 'Student Dashboard';
include '../includes/header.php';
?>

<div class="container">
    <div class="welcome-section">
        <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>! 👋</h1>
        <p>Ready to continue your learning journey? Check out your stats and available exams below.</p>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;"><i class="fas fa-file-alt"></i></div>
            <div class="stat-value"><?php echo $stats['available']; ?></div>
            <div class="stat-label">Available Exams</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;"><i class="fas fa-pen-alt"></i></div>
            <div class="stat-value"><?php echo $stats['attempts']; ?></div>
            <div class="stat-label">Exams Taken</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-trophy"></i></div>
            <div class="stat-value"><?php echo $stats['passed']; ?></div>
            <div class="stat-label">Exams Passed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0f4ff; color: #8b5cf6;"><i class="fas fa-chart-line"></i></div>
            <div class="stat-value"><?php echo $stats['avg_score']; ?>%</div>
            <div class="stat-label">Average Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-fire"></i></div>
            <div class="stat-value"><?php echo $stats['streak']; ?> days</div>
            <div class="stat-label">Current Streak</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-medal"></i></div>
            <div class="stat-value"><?php echo $stats['achievements']; ?></div>
            <div class="stat-label">Badges Earned</div>
        </div>
    </div>

    <!-- Announcements -->
    <?php if ($announcements->num_rows > 0): ?>
    <div class="announcements-section">
        <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
        <div class="announcements-list">
            <?php while ($ann = $announcements->fetch_assoc()): 
                $priority_class = $ann['priority'] == 'urgent' ? 'priority-urgent' : ($ann['priority'] == 'high' ? 'priority-high' : '');
            ?>
            <div class="announcement-card <?php echo $priority_class; ?> <?php echo $ann['is_pinned'] ? 'pinned' : ''; ?>">
                <div class="announcement-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="announcement-content">
                    <h4><?php echo htmlspecialchars($ann['title']); ?></h4>
                    <p><?php echo htmlspecialchars(substr($ann['content'], 0, 150)); ?></p>
                    <div class="announcement-meta">
                        <span><i class="fas fa-clock"></i> <?php echo timeAgo($ann['created_at']); ?></span>
                        <?php if ($ann['is_pinned']): ?>
                        <span class="pinned-badge"><i class="fas fa-thumbtack"></i> Pinned</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Exams -->
    <?php if ($upcoming_exams->num_rows > 0): ?>
    <div class="upcoming-section">
        <h2><i class="fas fa-calendar-alt"></i> Upcoming Scheduled Exams</h2>
        <div class="upcoming-grid">
            <?php while ($exam = $upcoming_exams->fetch_assoc()): ?>
            <div class="upcoming-card">
                <div class="exam-date">
                    <span class="month"><?php echo date('M', strtotime($exam['start_time'])); ?></span>
                    <span class="day"><?php echo date('d', strtotime($exam['start_time'])); ?></span>
                </div>
                <div class="exam-info">
                    <h4><?php echo htmlspecialchars($exam['title']); ?></h4>
                    <p><?php echo htmlspecialchars(substr($exam['description'] ?? '', 0, 80)); ?></p>
                    <div class="exam-meta">
                        <span><i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($exam['start_time'])); ?></span>
                        <span><i class="fas fa-hourglass-half"></i> <?php echo $exam['duration_minutes']; ?> min</span>
                    </div>
                    <a href="take-exam.php?id=<?php echo $exam['exam_id']; ?>" class="btn-sm btn-primary">Start Exam</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Available Exams -->
    <div class="available-exams">
        <div class="section-header">
            <h2><i class="fas fa-list"></i> Available Exams</h2>
            <a href="take-exam.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="exams-grid">
            <?php
            $exams = $conn->query("SELECT e.*, s.name as subject_name 
                                   FROM exams e 
                                   JOIN subjects s ON e.subject_id = s.id 
                                   WHERE e.status = 'published' 
                                   ORDER BY e.created_at DESC 
                                   LIMIT 3");
            while ($exam = $exams->fetch_assoc()):
                $attempted = $conn->query("SELECT id FROM exam_attempts WHERE exam_id = {$exam['id']} AND student_id = $user_id AND status = 'completed'")->num_rows > 0;
            ?>
            <div class="exam-card">
                <div class="exam-header">
                    <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                    <span class="subject-badge"><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                </div>
                <p class="exam-description"><?php echo htmlspecialchars(substr($exam['description'] ?? '', 0, 100)); ?></p>
                <div class="exam-meta">
                    <span><i class="fas fa-clock"></i> <?php echo $exam['duration_minutes']; ?> min</span>
                    <span><i class="fas fa-question-circle"></i> <?php echo $exam['total_questions']; ?> questions</span>
                    <span><i class="fas fa-star"></i> Pass: <?php echo $exam['passing_score']; ?>%</span>
                </div>
                <?php if ($attempted): ?>
                    <div class="exam-status completed">
                        <i class="fas fa-check-circle"></i> Completed
                        <a href="results.php?exam=<?php echo $exam['id']; ?>" class="btn-sm btn-outline">View Results</a>
                    </div>
                <?php else: ?>
                    <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn-primary">Start Exam</a>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="features-section">
        <h2><i class="fas fa-crown"></i> Learning Tools</h2>
        <div class="features-grid">
            <a href="leaderboard.php" class="feature-card">
                <i class="fas fa-trophy"></i>
                <h3>Leaderboard</h3>
                <p>See how you rank against peers</p>
                <span class="feature-badge">New</span>
            </a>
            <a href="achievements.php" class="feature-card">
                <i class="fas fa-medal"></i>
                <h3>Achievements</h3>
                <p>Earn badges and rewards</p>
                <span class="feature-badge">New</span>
            </a>
            <a href="materials.php" class="feature-card">
                <i class="fas fa-book-open"></i>
                <h3>Study Materials</h3>
                <p>Access learning resources</p>
                <span class="feature-badge">New</span>
            </a>
            <a href="results.php" class="feature-card">
                <i class="fas fa-chart-bar"></i>
                <h3>My Results</h3>
                <p>View your exam history</p>
            </a>
            <a href="profile.php" class="feature-card">
                <i class="fas fa-user-circle"></i>
                <h3>My Profile</h3>
                <p>Manage your account</p>
            </a>
            <a href="certificate.php" class="feature-card">
                <i class="fas fa-certificate"></i>
                <h3>Certificates</h3>
                <p>Download your certificates</p>
            </a>
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.welcome-section {
    margin-bottom: 2rem;
}

.welcome-section h1 {
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.welcome-section p {
    color: #64748b;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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
    color: #64748b;
    font-size: 0.75rem;
}

.announcements-section,
.upcoming-section,
.available-exams,
.features-section {
    margin-bottom: 2rem;
}

.announcements-section h2,
.upcoming-section h2,
.available-exams .section-header h2,
.features-section h2 {
    font-size: 1.2rem;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.view-all {
    color: #10b981;
    text-decoration: none;
    font-size: 0.85rem;
}

.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.announcement-card {
    background: white;
    border-radius: 16px;
    padding: 1rem;
    border: 1px solid #eef2f6;
    display: flex;
    gap: 1rem;
    transition: all 0.3s;
}

.announcement-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.announcement-card.priority-urgent {
    border-left: 4px solid #ef4444;
}

.announcement-card.priority-high {
    border-left: 4px solid #f59e0b;
}

.announcement-card.pinned {
    background: #fef3c7;
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

.announcement-content h4 {
    font-size: 0.95rem;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.announcement-content p {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.announcement-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: #94a3b8;
}

.pinned-badge {
    background: #f59e0b;
    color: white;
    padding: 2px 8px;
    border-radius: 30px;
}

.upcoming-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1rem;
}

.upcoming-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #eef2f6;
    display: flex;
    overflow: hidden;
    transition: all 0.3s;
}

.upcoming-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.exam-date {
    width: 80px;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-right: 1px solid #eef2f6;
}

.exam-date .month {
    font-size: 0.7rem;
    color: #10b981;
    font-weight: 600;
}

.exam-date .day {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.exam-info {
    flex: 1;
    padding: 1rem;
}

.exam-info h4 {
    font-size: 0.9rem;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.exam-info p {
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}

.exam-info .exam-meta {
    display: flex;
    gap: 0.75rem;
    font-size: 0.7rem;
    color: #94a3b8;
    margin-bottom: 0.75rem;
}

.exams-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
}

.exam-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    transition: all 0.3s;
}

.exam-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.exam-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.exam-header h3 {
    font-size: 1.1rem;
    color: #1e293b;
}

.subject-badge {
    background: #ecfdf5;
    color: #10b981;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}

.exam-description {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.exam-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.75rem;
    color: #94a3b8;
    margin-bottom: 1rem;
}

.exam-status {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 0.5rem;
}

.exam-status.completed {
    color: #10b981;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.feature-card {
    background: white;
    border: 1px solid #eef2f6;
    border-radius: 16px;
    padding: 1.5rem;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s;
    position: relative;
}

.feature-card:hover {
    transform: translateY(-5px);
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
    font-size: 0.75rem;
    color: #64748b;
}

.feature-badge {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    background: #10b981;
    color: white;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.6rem;
    font-weight: 600;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-block;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16,185,129,0.3);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.75rem;
}

.btn-outline {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.75rem;
}

.btn-outline:hover {
    border-color: #10b981;
    color: #10b981;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .upcoming-grid,
    .exams-grid,
    .features-grid {
        grid-template-columns: 1fr;
    }
    .upcoming-card {
        flex-direction: column;
    }
    .exam-date {
        width: 100%;
        flex-direction: row;
        gap: 0.5rem;
        padding: 0.5rem;
        border-right: none;
        border-bottom: 1px solid #eef2f6;
    }
}
</style>

<?php include '../includes/footer.php'; ?>