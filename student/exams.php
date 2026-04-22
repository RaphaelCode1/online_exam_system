<?php
/**
 * Student Available Exams Page
 * Lists all published exams for students to take with unlimited retakes
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

// Get all published exams with attempt status and retake info
$exams = $conn->query("
    SELECT e.*, s.name as subject_name,
           (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND student_id = $user_id AND status = 'completed') as attempts_count,
           (SELECT MAX(percentage) FROM exam_attempts WHERE exam_id = e.id AND student_id = $user_id AND status = 'completed') as best_score,
           (SELECT COUNT(*) FROM exam_questions WHERE exam_id = e.id) as total_available_questions
    FROM exams e 
    JOIN subjects s ON e.subject_id = s.id 
    WHERE e.status = 'published' 
    ORDER BY e.created_at DESC
");

$page_title = 'Available Exams';
include '../includes/header.php';
?>

<div class="exams-container">
    <div class="page-header">
        <h1><i class="fas fa-pen-alt"></i> Available Exams</h1>
        <p>Select an exam to begin. Each attempt has randomized questions for fair evaluation. <strong>Unlimited retakes allowed!</strong></p>
    </div>

    <?php if ($exams->num_rows > 0): ?>
        <div class="exams-grid">
            <?php while ($exam = $exams->fetch_assoc()): 
                $has_questions = $exam['total_available_questions'] > 0;
                $attempts_text = $exam['attempts_count'] > 0 ? "Attempts: {$exam['attempts_count']}" : "Not attempted yet";
                $best_score_text = $exam['best_score'] ? "Best: {$exam['best_score']}%" : "";
            ?>
                <div class="exam-card">
                    <div class="exam-header">
                        <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                        <span class="subject-badge"><?php echo htmlspecialchars($exam['subject_name']); ?></span>
                    </div>
                    
                    <p class="exam-description"><?php echo htmlspecialchars(substr($exam['description'] ?? '', 0, 120)); ?></p>
                    
                    <div class="exam-details">
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $exam['duration_minutes']; ?> minutes</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-question-circle"></i>
                            <span><?php echo $exam['total_questions']; ?> questions</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-database"></i>
                            <span><?php echo $exam['total_available_questions']; ?> available</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-star"></i>
                            <span>Pass: <?php echo $exam['passing_score']; ?>%</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-random"></i>
                            <span>Randomized</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-infinity"></i>
                            <span>Unlimited Retakes</span>
                        </div>
                    </div>
                    
                    <?php if ($exam['attempts_count'] > 0): ?>
                        <div class="exam-stats">
                            <span class="attempts-badge">
                                <i class="fas fa-history"></i> <?php echo $attempts_text; ?>
                            </span>
                            <?php if ($best_score_text): ?>
                                <span class="best-score-badge">
                                    <i class="fas fa-trophy"></i> <?php echo $best_score_text; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($has_questions): ?>
                        <div class="exam-actions">
                            <?php if ($exam['attempts_count'] > 0): ?>
                                <a href="results.php?exam=<?php echo $exam['id']; ?>" class="btn-results">
                                    <i class="fas fa-chart-bar"></i> View Results
                                </a>
                            <?php endif; ?>
                            <a href="take-exam.php?id=<?php echo $exam['id']; ?>" class="btn-start">
                                <i class="fas fa-play"></i> 
                                <?php echo $exam['attempts_count'] > 0 ? 'Retake Exam' : 'Start Exam'; ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="no-questions-warning">
                            <i class="fas fa-exclamation-triangle"></i> No questions available
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <h3>No Exams Available</h3>
            <p>There are no published exams at the moment. Please check back later.</p>
            <a href="dashboard.php" class="btn-primary">Back to Dashboard</a>
        </div>
    <?php endif; ?>
</div>

<style>
.exams-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.page-header {
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header p {
    color: #64748b;
}

.exams-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
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
    font-size: 1.2rem;
    color: #1e293b;
}

.subject-badge {
    background: #ecfdf5;
    color: #10b981;
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}

.exam-description {
    color: #64748b;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.exam-details {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding: 0.75rem 0;
    border-top: 1px solid #eef2f6;
    border-bottom: 1px solid #eef2f6;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.75rem;
    color: #64748b;
}

.detail-item i {
    color: #10b981;
    width: 14px;
}

.exam-stats {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.attempts-badge, .best-score-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 500;
}

.attempts-badge {
    background: #f1f5f9;
    color: #1e293b;
}

.best-score-badge {
    background: #fef3c7;
    color: #f59e0b;
}

.exam-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1rem;
}

.btn-start, .btn-results {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    font-size: 0.85rem;
    flex: 1;
}

.btn-start {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-start:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16,185,129,0.3);
}

.btn-results {
    background: white;
    border: 1px solid #e2e8f0;
    color: #64748b;
}

.btn-results:hover {
    border-color: #10b981;
    color: #10b981;
}

.no-questions-warning {
    background: #fef2f2;
    color: #ef4444;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.empty-state {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .exams-container {
        padding: 1rem;
    }
    
    .exams-grid {
        grid-template-columns: 1fr;
    }
    
    .exam-actions {
        flex-direction: column;
    }
}
</style>

<?php include '../includes/footer.php'; ?>