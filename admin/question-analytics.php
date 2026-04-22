<?php
/**
 * Question Analytics Page
 * Track which questions students get wrong and performance metrics
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canViewReports()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$subject_id = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';

// Get subjects for filter
$subjects = $conn->query("SELECT id, name FROM subjects WHERE status = 1 ORDER BY name");

// Build query
$query = "SELECT q.id, q.question_text, q.difficulty, q.subject_id, q.topic_id,
                 s.name as subject_name,
                 COUNT(DISTINCT qa.attempt_id) as total_attempts,
                 SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                 SUM(CASE WHEN qa.is_correct = 0 THEN 1 ELSE 0 END) as wrong_count,
                 ROUND(AVG(CASE WHEN qa.is_correct = 1 THEN 100 ELSE 0 END), 1) as success_rate,
                 AVG(qa.time_taken) as avg_time
          FROM questions q
          LEFT JOIN question_analytics qa ON q.id = qa.question_id
          LEFT JOIN subjects s ON q.subject_id = s.id
          WHERE 1=1";

if ($subject_id > 0) {
    $query .= " AND q.subject_id = $subject_id";
}
if (!empty($difficulty)) {
    $query .= " AND q.difficulty = '$difficulty'";
}

$query .= " GROUP BY q.id 
            ORDER BY success_rate ASC, wrong_count DESC 
            LIMIT 50";

$questions = $conn->query($query);

// Get overall statistics
$stats = $conn->query("SELECT 
                        COUNT(DISTINCT qa.question_id) as questions_analyzed,
                        SUM(CASE WHEN qa.is_correct = 1 THEN 1 ELSE 0 END) as total_correct,
                        SUM(CASE WHEN qa.is_correct = 0 THEN 1 ELSE 0 END) as total_wrong,
                        AVG(qa.time_taken) as avg_time,
                        COUNT(DISTINCT qa.attempt_id) as total_attempts
                       FROM question_analytics qa")->fetch_assoc();

$page_title = 'Question Analytics';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Question Analytics</h1>
        <p>Track question performance and identify weak areas</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f2fe; color: #3b82f6;">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['questions_analyzed'] ?? 0; ?></div>
            <div class="stat-label">Questions Analyzed</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ecfdf5; color: #10b981;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_correct'] ?? 0; ?></div>
            <div class="stat-label">Correct Answers</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef2f2; color: #ef4444;">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_wrong'] ?? 0; ?></div>
            <div class="stat-label">Wrong Answers</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?php echo round($stats['avg_time'] ?? 0, 1); ?> sec</div>
            <div class="stat-label">Avg Time per Question</div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-grid">
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
                <label>Difficulty</label>
                <select name="difficulty" class="filter-control" onchange="this.form.submit()">
                    <option value="">All Difficulties</option>
                    <option value="easy" <?php echo $difficulty == 'easy' ? 'selected' : ''; ?>>Easy</option>
                    <option value="medium" <?php echo $difficulty == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="hard" <?php echo $difficulty == 'hard' ? 'selected' : ''; ?>>Hard</option>
                </select>
            </div>
            <div class="filter-group">
                <a href="question-analytics.php" class="btn btn-outline">Reset Filters</a>
            </div>
        </form>
    </div>
    
    <!-- Questions Table -->
    <div class="card">
        <div class="table-container">
            <table class="analytics-table">
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Subject</th>
                        <th>Difficulty</th>
                        <th>Attempts</th>
                        <th>Correct</th>
                        <th>Wrong</th>
                        <th>Success Rate</th>
                        <th>Avg Time</th>
                        <th>Status</th>
                    </thead>
                    <tbody>
                        <?php if ($questions->num_rows == 0): ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <p>No data available. Students need to take exams first.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($q = $questions->fetch_assoc()): 
                                $success_rate = $q['success_rate'] ?? 0;
                                $status_class = $success_rate >= 70 ? 'status-good' : ($success_rate >= 50 ? 'status-warning' : 'status-poor');
                                $status_text = $success_rate >= 70 ? 'Good' : ($success_rate >= 50 ? 'Needs Review' : 'Poor Performance');
                            ?>
                            <tr>
                                <td class="question-text">
                                    <?php echo htmlspecialchars(substr($q['question_text'], 0, 80)); ?>
                                    <?php if (strlen($q['question_text']) > 80): ?>...<?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($q['subject_name']); ?></td>
                                <td>
                                    <span class="difficulty-badge <?php echo $q['difficulty']; ?>">
                                        <?php echo ucfirst($q['difficulty']); ?>
                                    </span>
                                </td>
                                <td><?php echo $q['total_attempts']; ?></td>
                                <td class="correct"><?php echo $q['correct_count']; ?></td>
                                <td class="wrong"><?php echo $q['wrong_count']; ?></td>
                                <td>
                                    <div class="success-rate">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $success_rate; ?>%; background: <?php echo $success_rate >= 70 ? '#10b981' : ($success_rate >= 50 ? '#f59e0b' : '#ef4444'); ?>;"></div>
                                        </div>
                                        <span><?php echo $success_rate; ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo round($q['avg_time'], 1); ?> sec</td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
.card {
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
    overflow: hidden;
}
.table-container {
    overflow-x: auto;
}
.analytics-table {
    width: 100%;
    border-collapse: collapse;
}
.analytics-table th,
.analytics-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eef2f6;
}
.analytics-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #475569;
    font-size: 0.85rem;
}
.analytics-table tr:hover {
    background: #fafcff;
}
.question-text {
    max-width: 300px;
    font-size: 0.9rem;
    color: #1e293b;
}
.difficulty-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}
.difficulty-badge.easy {
    background: #ecfdf5;
    color: #10b981;
}
.difficulty-badge.medium {
    background: #fef3c7;
    color: #d97706;
}
.difficulty-badge.hard {
    background: #fef2f2;
    color: #ef4444;
}
.correct {
    color: #10b981;
    font-weight: 600;
}
.wrong {
    color: #ef4444;
    font-weight: 600;
}
.success-rate {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 100px;
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
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}
.status-badge.status-good {
    background: #ecfdf5;
    color: #10b981;
}
.status-badge.status-warning {
    background: #fef3c7;
    color: #d97706;
}
.status-badge.status-poor {
    background: #fef2f2;
    color: #ef4444;
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
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .analytics-table th:nth-child(5),
    .analytics-table td:nth-child(5),
    .analytics-table th:nth-child(6),
    .analytics-table td:nth-child(6) {
        display: none;
    }
}
</style>

<?php include '../includes/footer.php'; ?>