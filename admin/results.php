<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get results
$results = $conn->query("SELECT ea.*, e.title as exam_title, u.full_name as student_name, u.email as student_email 
                         FROM exam_attempts ea 
                         JOIN exams e ON ea.exam_id = e.id 
                         JOIN users u ON ea.student_id = u.id 
                         WHERE ea.status = 'completed' 
                         ORDER BY ea.created_at DESC LIMIT 50");

$page_title = 'Exam Results';
include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 2rem;">Exam Results</h1>
    
    <div class="card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Correct/Wrong</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($result = $results->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($result['student_name']); ?></strong><br>
                            <small><?php echo $result['student_email']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                        <td><?php echo $result['score']; ?> / <?php echo $result['total_questions']; ?></td>
                        <td>
                            <span class="badge bg-<?php echo $result['percentage'] >= 70 ? 'success' : ($result['percentage'] >= 50 ? 'warning' : 'danger'); ?>">
                                <?php echo $result['percentage']; ?>%
                            </span>
                         </td>
                         <td><?php echo $result['correct_answers']; ?> / <?php echo $result['wrong_answers']; ?> <?php echo $result['passed'] ? '✓' : '✗'; ?></td>
                         <td>
                            <span class="badge bg-<?php echo $result['passed'] ? 'success' : 'danger'; ?>">
                                <?php echo $result['passed'] ? 'Passed' : 'Failed'; ?>
                            </span>
                         </td>
                         <td><?php echo formatDate($result['created_at']); ?></td>
                     </tr>
                    <?php endwhile; ?>
                </tbody>
             </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>