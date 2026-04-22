<?php
/**
 * Leaderboard Page
 * Display top performers with medals and rankings
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$period = isset($_GET['period']) ? $_GET['period'] : 'all_time';
$subject_id = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;

// Get subjects for filter
$subjects = $conn->query("SELECT id, name FROM subjects WHERE status = 1 ORDER BY name");

// Build leaderboard query
$query = "SELECT 
            u.id as student_id,
            u.full_name,
            u.username,
            u.avatar,
            COUNT(DISTINCT ea.exam_id) as exams_taken,
            SUM(ea.score) as total_score,
            AVG(ea.percentage) as avg_percentage,
            SUM(CASE WHEN ea.passed = 1 THEN 1 ELSE 0 END) as exams_passed,
            SUM(ea.correct_answers) as total_correct,
            SUM(ea.wrong_answers) as total_wrong,
            (SELECT COUNT(*) FROM student_achievements sa WHERE sa.student_id = u.id) as badges_count,
            (SELECT current_streak FROM student_streaks ss WHERE ss.student_id = u.id) as current_streak
          FROM users u
          LEFT JOIN exam_attempts ea ON u.id = ea.student_id AND ea.status = 'completed'
          WHERE u.role = 'student'";

if ($subject_id > 0) {
    $query .= " AND ea.exam_id IN (SELECT id FROM exams WHERE subject_id = $subject_id)";
}

if ($period === 'monthly') {
    $query .= " AND ea.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
} elseif ($period === 'weekly') {
    $query .= " AND ea.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

$query .= " GROUP BY u.id 
            HAVING exams_taken > 0 
            ORDER BY avg_percentage DESC, total_score DESC 
            LIMIT 100";

$leaderboard = $conn->query($query);

// Get current user's rank
$current_user_rank = 0;
$rank = 1;
$leaderboard_data = [];
while ($row = $leaderboard->fetch_assoc()) {
    $row['rank'] = $rank;
    $leaderboard_data[] = $row;
    if ($row['student_id'] == $user_id) {
        $current_user_rank = $rank;
    }
    $rank++;
}

// Get current user's stats for display
$user_stats = null;
foreach ($leaderboard_data as $data) {
    if ($data['student_id'] == $user_id) {
        $user_stats = $data;
        break;
    }
}

// If user not in leaderboard (no exams taken)
if (!$user_stats) {
    $user_stats = [
        'full_name' => $_SESSION['full_name'],
        'exams_taken' => 0,
        'avg_percentage' => 0,
        'exams_passed' => 0,
        'badges_count' => 0,
        'current_streak' => 0
    ];
}

$page_title = 'Leaderboard';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-trophy"></i> Leaderboard</h1>
        <p>Top performers ranking</p>
    </div>
    
    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-grid">
            <div class="filter-group">
                <label>Period</label>
                <select name="period" class="filter-control" onchange="this.form.submit()">
                    <option value="all_time" <?php echo $period == 'all_time' ? 'selected' : ''; ?>>All Time</option>
                    <option value="monthly" <?php echo $period == 'monthly' ? 'selected' : ''; ?>>This Month</option>
                    <option value="weekly" <?php echo $period == 'weekly' ? 'selected' : ''; ?>>This Week</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Subject</label>
                <select name="subject" class="filter-control" onchange="this.form.submit()">
                    <option value="0">All Subjects</option>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                    <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subject['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group">
                <a href="leaderboard.php" class="btn btn-outline">Reset Filters</a>
            </div>
        </form>
    </div>
    
    <!-- User's Position Card -->
    <?php if ($current_user_rank > 0): ?>
    <div class="user-rank-card">
        <div class="rank-badge">#<?php echo $current_user_rank; ?></div>
        <div class="user-rank-info">
            <div class="user-avatar">
                <?php if (!empty($_SESSION['avatar']) && file_exists('../uploads/avatars/' . $_SESSION['avatar'])): ?>
                    <img src="../uploads/avatars/<?php echo $_SESSION['avatar']; ?>" alt="<?php echo htmlspecialchars($user_stats['full_name']); ?>">
                <?php else: ?>
                    <div class="avatar-initials"><?php echo strtoupper(substr($user_stats['full_name'], 0, 1)); ?></div>
                <?php endif; ?>
            </div>
            <div class="user-rank-stats">
                <div class="user-name"><?php echo htmlspecialchars($user_stats['full_name']); ?></div>
                <div class="user-stats">
                    <span><i class="fas fa-chart-line"></i> Avg: <?php echo round($user_stats['avg_percentage'], 1); ?>%</span>
                    <span><i class="fas fa-check-circle"></i> Passed: <?php echo $user_stats['exams_passed']; ?></span>
                    <span><i class="fas fa-medal"></i> Badges: <?php echo $user_stats['badges_count']; ?></span>
                    <span><i class="fas fa-fire"></i> Streak: <?php echo $user_stats['current_streak']; ?> days</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Leaderboard Table -->
    <div class="leaderboard-card">
        <div class="leaderboard-header">
            <h3><i class="fas fa-ranking-star"></i> Top Performers</h3>
            <span><?php echo count($leaderboard_data); ?> students</span>
        </div>
        
        <?php if (empty($leaderboard_data)): ?>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <h3>No rankings yet</h3>
                <p>Complete exams to appear on the leaderboard!</p>
                <a href="take-exam.php" class="btn btn-primary">Take an Exam</a>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th>Exams</th>
                            <th>Avg Score</th>
                            <th>Passed</th>
                            <th>Badges</th>
                            <th>Streak</th>
                        </thead>
                        <tbody>
                            <?php foreach ($leaderboard_data as $student): 
                                $medal = '';
                                $rank_class = '';
                                if ($student['rank'] == 1) {
                                    $medal = '🥇';
                                    $rank_class = 'rank-1';
                                } elseif ($student['rank'] == 2) {
                                    $medal = '🥈';
                                    $rank_class = 'rank-2';
                                } elseif ($student['rank'] == 3) {
                                    $medal = '🥉';
                                    $rank_class = 'rank-3';
                                }
                            ?>
                            <tr class="<?php echo $student['student_id'] == $user_id ? 'current-user' : ''; ?>">
                                <td class="rank <?php echo $rank_class; ?>">
                                    <?php if ($medal): ?>
                                        <span class="medal"><?php echo $medal; ?></span>
                                    <?php else: ?>
                                        #<?php echo $student['rank']; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="student-info">
                                    <div class="student-avatar-small">
                                        <?php if (!empty($student['avatar']) && file_exists('../uploads/avatars/' . $student['avatar'])): ?>
                                            <img src="../uploads/avatars/<?php echo $student['avatar']; ?>" alt="<?php echo htmlspecialchars($student['full_name']); ?>">
                                        <?php else: ?>
                                            <div class="avatar-initials-small"><?php echo strtoupper(substr($student['full_name'], 0, 1)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="student-details">
                                        <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                        <div class="student-username">@<?php echo htmlspecialchars($student['username']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo $student['exams_taken']; ?></td>
                                <td>
                                    <div class="score-cell">
                                        <div class="progress-bar-small">
                                            <div class="progress-fill" style="width: <?php echo $student['avg_percentage']; ?>%; background: <?php echo $student['avg_percentage'] >= 70 ? '#10b981' : ($student['avg_percentage'] >= 50 ? '#f59e0b' : '#ef4444'); ?>;"></div>
                                        </div>
                                        <span><?php echo round($student['avg_percentage'], 1); ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $student['exams_passed'] > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $student['exams_passed']; ?>/<?php echo $student['exams_taken']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <i class="fas fa-medal"></i> <?php echo $student['badges_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($student['current_streak'] > 0): ?>
                                        <span class="streak-badge">
                                            <i class="fas fa-fire"></i> <?php echo $student['current_streak']; ?> days
                                        </span>
                                    <?php else: ?>
                                        <span class="streak-badge inactive">No streak</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<style>
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
.filters-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #eef2f6;
}
.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.filter-group label {
    font-size: 0.9rem;
    font-weight: 500;
    color: #475569;
}
.filter-control {
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
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
.user-rank-card {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    color: white;
}
.rank-badge {
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
}
.user-rank-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 1rem;
}
.user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    overflow: hidden;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-initials {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: bold;
    background: #10b981;
    color: white;
}
.user-rank-stats {
    flex: 1;
}
.user-name {
    font-size: 1.2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
.user-stats {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 0.85rem;
    opacity: 0.9;
}
.user-stats span {
    display: flex;
    align-items: center;
    gap: 5px;
}
.leaderboard-card {
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
    overflow: hidden;
}
.leaderboard-header {
    padding: 1.5rem;
    border-bottom: 1px solid #eef2f6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.leaderboard-header h3 {
    font-size: 1.1rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.table-container {
    overflow-x: auto;
}
.leaderboard-table {
    width: 100%;
    border-collapse: collapse;
}
.leaderboard-table th,
.leaderboard-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eef2f6;
}
.leaderboard-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #475569;
}
.leaderboard-table tr:hover {
    background: #fafcff;
}
.leaderboard-table tr.current-user {
    background: #ecfdf5;
    border-left: 3px solid #10b981;
}
.rank {
    font-weight: 600;
    width: 80px;
}
.rank-1 .medal { font-size: 1.5rem; }
.rank-2 .medal { font-size: 1.5rem; }
.rank-3 .medal { font-size: 1.5rem; }
.student-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.student-avatar-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    background: #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: center;
}
.student-avatar-small img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.avatar-initials-small {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}
.student-name {
    font-weight: 600;
    color: #1e293b;
}
.student-username {
    font-size: 0.75rem;
    color: #94a3b8;
}
.score-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.progress-bar-small {
    width: 80px;
    height: 6px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    border-radius: 10px;
}
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}
.bg-success {
    background: #ecfdf5;
    color: #10b981;
}
.bg-info {
    background: #e0f2fe;
    color: #0284c7;
}
.bg-secondary {
    background: #f1f5f9;
    color: #64748b;
}
.streak-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
    background: #fef3c7;
    color: #d97706;
}
.streak-badge.inactive {
    background: #f1f5f9;
    color: #94a3b8;
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
.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 12px;
    text-decoration: none;
    display: inline-block;
}
@media (max-width: 768px) {
    .user-rank-card {
        flex-direction: column;
        text-align: center;
    }
    .user-rank-info {
        flex-direction: column;
    }
    .user-stats {
        justify-content: center;
    }
    .leaderboard-table th:nth-child(5),
    .leaderboard-table td:nth-child(5),
    .leaderboard-table th:nth-child(6),
    .leaderboard-table td:nth-child(6) {
        display: none;
    }
}
</style>

<?php include '../includes/footer.php'; ?>