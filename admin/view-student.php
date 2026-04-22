<?php

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canViewUsers()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student = $conn->query("SELECT * FROM users WHERE id = $student_id AND role = 'student'")->fetch_assoc();

if (!$student) {
    header('Location: users.php');
    exit();
}

// Get exam history
$attempts = $conn->query("SELECT ea.*, e.title as exam_title FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.student_id = $student_id ORDER BY ea.created_at DESC");

$page_title = 'Student Details';
include '../includes/header.php';
?>

<div class="container">
    <h1 style="margin-bottom: 2rem;">Student Details</h1>
    
    <div class="card" style="margin-bottom: 2rem;">
        <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
            <div><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></div>
            <div><strong>Username:</strong> <?php echo htmlspecialchars($student['username']); ?></div>
            <div><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></div>
            <div><strong>Joined:</strong> <?php echo formatDate($student['created_at']); ?></div>
            <div><strong>Status:</strong> <?php echo getStatusBadge($student['status']); ?></div>
        </div>
    </div>
    
    <div class="card">
        <h3>Exam History</h3>
        <div class="table-container">
             <table class="table">
                <thead>
                     <tr>
                        <th>Exam</th>
                        <th>Date</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Status</th>
                     </tr>
                </thead>
                <tbody>
                    <?php while ($attempt = $attempts->fetch_assoc()): ?>
                     <tr>
                        <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                        <td><?php echo formatDate($attempt['created_at']); ?></td>
                        <td><?php echo $attempt['score']; ?> / <?php echo $attempt['total_questions']; ?></td>
                        <td><?php echo $attempt['percentage']; ?>%</td>
                        <td>
                            <span class="badge bg-<?php echo $attempt['passed'] ? 'success' : 'danger'; ?>">
                                <?php echo $attempt['passed'] ? 'Passed' : 'Failed'; ?>
                            </span>
                        </td>
                     </tr>
                    <?php endwhile; ?>
                </tbody>
             </table>
        </div>
        <div style="margin-top: 1rem;">
            <a href="users.php" class="btn btn-outline">← Back to Students</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>