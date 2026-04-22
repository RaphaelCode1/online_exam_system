<?php
/**
 * Achievements Page
 * Display earned badges and achievements
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

// Update achievements based on student progress
function updateStudentAchievements($conn, $user_id) {
    // Get student stats
    $stats = $conn->query("SELECT 
                            COUNT(*) as exams_taken,
                            AVG(percentage) as avg_accuracy,
                            SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as exams_passed,
                            SUM(correct_answers) as total_correct
                           FROM exam_attempts 
                           WHERE student_id = $user_id AND status = 'completed'");
    $stats_data = $stats->fetch_assoc();
    
    // Get streak
    $streak = $conn->query("SELECT current_streak FROM student_streaks WHERE student_id = $user_id")->fetch_assoc();
    $current_streak = $streak ? $streak['current_streak'] : 0;
    
    // Get all achievements
    $achievements = $conn->query("SELECT * FROM achievements WHERE is_active = 1");
    
    while ($ach = $achievements->fetch_assoc()) {
        $earned = false;
        
        switch ($ach['criteria_type']) {
            case 'exams_taken':
                if ($stats_data['exams_taken'] >= $ach['criteria_value']) $earned = true;
                break;
            case 'exams_passed':
                if ($stats_data['exams_passed'] >= $ach['criteria_value']) $earned = true;
                break;
            case 'accuracy':
                if ($stats_data['avg_accuracy'] >= $ach['criteria_value']) $earned = true;
                break;
            case 'total_questions':
                if ($stats_data['total_correct'] >= $ach['criteria_value']) $earned = true;
                break;
            case 'streak_days':
                if ($current_streak >= $ach['criteria_value']) $earned = true;
                break;
        }
        
        if ($earned) {
            // Check if already earned
            $check = $conn->query("SELECT id FROM student_achievements WHERE student_id = $user_id AND achievement_id = {$ach['id']}");
            if ($check->num_rows == 0) {
                $conn->query("INSERT INTO student_achievements (student_id, achievement_id, earned_at) VALUES ($user_id, {$ach['id']}, NOW())");
                
                // Send notification
                sendNotification($user_id, "New Achievement Unlocked!", "You earned the '{$ach['name']}' badge! +{$ach['points']} points", 'success');
            }
        }
    }
}

// Update achievements
updateStudentAchievements($conn, $user_id);

// Get earned achievements
$earned_achievements = $conn->query("SELECT a.*, sa.earned_at 
                                     FROM achievements a 
                                     JOIN student_achievements sa ON a.id = sa.achievement_id 
                                     WHERE sa.student_id = $user_id 
                                     ORDER BY sa.earned_at DESC");

// Get locked achievements
$locked_achievements = $conn->query("SELECT * FROM achievements 
                                     WHERE id NOT IN (SELECT achievement_id FROM student_achievements WHERE student_id = $user_id)
                                     AND is_active = 1
                                     ORDER BY criteria_value");

// Get student stats for progress
$stats = $conn->query("SELECT 
                        COUNT(*) as exams_taken,
                        AVG(percentage) as avg_accuracy,
                        SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as exams_passed,
                        SUM(correct_answers) as total_correct
                       FROM exam_attempts 
                       WHERE student_id = $user_id AND status = 'completed'")->fetch_assoc();

$streak = $conn->query("SELECT current_streak, longest_streak FROM student_streaks WHERE student_id = $user_id")->fetch_assoc();

$page_title = 'Achievements';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-medal"></i> Achievements & Badges</h1>
        <p>Collect badges and unlock achievements as you progress</p>
    </div>
    
    <!-- Stats Summary -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-value"><?php echo $stats['exams_taken'] ?? 0; ?></div>
            <div class="stat-label">Exams Taken</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['exams_passed'] ?? 0; ?></div>
            <div class="stat-label">Exams Passed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value"><?php echo round($stats['avg_accuracy'] ?? 0, 1); ?>%</div>
            <div class="stat-label">Avg Accuracy</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #ef4444;">
                <i class="fas fa-fire"></i>
            </div>
            <div class="stat-value"><?php echo $streak['current_streak'] ?? 0; ?></div>
            <div class="stat-label">Current Streak</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0f4ff; color: #8b5cf6;">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="stat-value"><?php echo $earned_achievements->num_rows; ?></div>
            <div class="stat-label">Badges Earned</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-brain"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_correct'] ?? 0; ?></div>
            <div class="stat-label">Correct Answers</div>
        </div>
    </div>
    
    <!-- Earned Achievements -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-trophy"></i> Earned Badges</h3>
            <span class="badge-count"><?php echo $earned_achievements->num_rows; ?> / <?php echo $earned_achievements->num_rows + $locked_achievements->num_rows; ?></span>
        </div>
        
        <?php if ($earned_achievements->num_rows > 0): ?>
            <div class="achievements-grid">
                <?php while ($ach = $earned_achievements->fetch_assoc()): ?>
                <div class="achievement-card earned">
                    <div class="achievement-icon" style="background: <?php echo $ach['badge_color']; ?>20;">
                        <i class="fas <?php echo $ach['icon']; ?>" style="color: <?php echo $ach['badge_color']; ?>;"></i>
                    </div>
                    <div class="achievement-info">
                        <h4><?php echo htmlspecialchars($ach['name']); ?></h4>
                        <p><?php echo htmlspecialchars($ach['description']); ?></p>
                        <div class="achievement-meta">
                            <span><i class="fas fa-star"></i> +<?php echo $ach['points']; ?> points</span>
                            <span><i class="fas fa-calendar"></i> Earned <?php echo date('M j, Y', strtotime($ach['earned_at'])); ?></span>
                        </div>
                    </div>
                    <div class="achievement-badge">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-medal"></i>
                <h3>No Badges Yet</h3>
                <p>Complete exams and achieve milestones to earn badges!</p>
                <a href="take-exam.php" class="btn btn-primary">Start Your Journey</a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Locked Achievements -->
    <?php if ($locked_achievements->num_rows > 0): ?>
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-lock"></i> Locked Badges</h3>
            <span>Complete requirements to unlock</span>
        </div>
        
        <div class="achievements-grid locked">
            <?php while ($ach = $locked_achievements->fetch_assoc()): 
                $progress = 0;
                $current = 0;
                $target = $ach['criteria_value'];
                
                switch ($ach['criteria_type']) {
                    case 'exams_taken':
                        $current = $stats['exams_taken'] ?? 0;
                        break;
                    case 'exams_passed':
                        $current = $stats['exams_passed'] ?? 0;
                        break;
                    case 'accuracy':
                        $current = round($stats['avg_accuracy'] ?? 0);
                        break;
                    case 'total_questions':
                        $current = $stats['total_correct'] ?? 0;
                        break;
                    case 'streak_days':
                        $current = $streak['longest_streak'] ?? 0;
                        break;
                }
                
                $progress = min(100, round(($current / $target) * 100));
            ?>
            <div class="achievement-card locked">
                <div class="achievement-icon" style="background: #f1f5f9;">
                    <i class="fas <?php echo $ach['icon']; ?>" style="color: #94a3b8;"></i>
                </div>
                <div class="achievement-info">
                    <h4><?php echo htmlspecialchars($ach['name']); ?></h4>
                    <p><?php echo htmlspecialchars($ach['description']); ?></p>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $progress; ?>%; background: <?php echo $ach['badge_color']; ?>;"></div>
                        </div>
                        <div class="progress-text"><?php echo $current; ?> / <?php echo $target; ?></div>
                    </div>
                    <div class="achievement-meta">
                        <span><i class="fas fa-star"></i> +<?php echo $ach['points']; ?> points</span>
                    </div>
                </div>
                <div class="achievement-badge locked">
                    <i class="fas fa-lock"></i>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
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
    font-size: 1.8rem;
    font-weight: 700;
    color: #1e293b;
}
.stat-label {
    color: #64748b;
    font-size: 0.8rem;
}
.section-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    margin-bottom: 2rem;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.section-header h3 {
    font-size: 1.2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.badge-count {
    background: #10b981;
    color: white;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
}
.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1rem;
}
.achievement-card {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: 16px;
    background: #f8fafc;
    transition: all 0.3s;
    position: relative;
}
.achievement-card.earned {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #bbf7d0;
}
.achievement-card.locked {
    background: #f8fafc;
    border: 1px solid #eef2f6;
    opacity: 0.7;
}
.achievement-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.achievement-icon i {
    font-size: 1.8rem;
}
.achievement-info {
    flex: 1;
}
.achievement-info h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.25rem;
}
.achievement-info p {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 0.5rem;
}
.progress-container {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.5rem 0;
}
.progress-bar {
    flex: 1;
    height: 6px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    border-radius: 10px;
}
.progress-text {
    font-size: 0.7rem;
    color: #64748b;
    min-width: 60px;
}
.achievement-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.7rem;
    color: #94a3b8;
}
.achievement-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}
.achievement-badge {
    width: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.achievement-badge i {
    font-size: 1.5rem;
}
.achievement-badge i.fa-check-circle {
    color: #10b981;
}
.achievement-badge.locked i {
    color: #94a3b8;
}
.empty-state {
    text-align: center;
    padding: 2rem;
}
.empty-state i {
    font-size: 3rem;
    color: #94a3b8;
    margin-bottom: 1rem;
}
.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 12px;
    text-decoration: none;
    display: inline-block;
    margin-top: 1rem;
}
@media (max-width: 768px) {
    .achievements-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php include '../includes/footer.php'; ?>