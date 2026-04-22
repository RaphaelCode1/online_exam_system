<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$exam = $conn->query("SELECT * FROM exams WHERE id = $exam_id")->fetch_assoc();

if (!$exam) {
    header('Location: exams.php');
    exit();
}

$subjects = $conn->query("SELECT * FROM subjects WHERE status = 1 ORDER BY name");

$page_title = 'Edit Exam';
include '../includes/header.php';
?>

<div class="container" style="max-width: 600px;">
    <h1 style="margin-bottom: 2rem;">Edit Exam</h1>
    
    <div class="card">
        <form method="POST" action="update-exam.php">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            <div class="form-group">
                <label>Exam Title</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($exam['title']); ?>" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($exam['description']); ?></textarea>
            </div>
            <div class="form-group">
                <label>Subject</label>
                <select name="subject_id" class="form-control" required>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                    <option value="<?php echo $subject['id']; ?>" <?php echo $subject['id'] == $exam['subject_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subject['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Duration (minutes)</label>
                <input type="number" name="duration" class="form-control" value="<?php echo $exam['duration_minutes']; ?>" required>
            </div>
            <div class="form-group">
                <label>Passing Score (%)</label>
                <input type="number" name="passing_score" class="form-control" value="<?php echo $exam['passing_score']; ?>" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="draft" <?php echo $exam['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo $exam['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="archived" <?php echo $exam['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="exams.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Exam</button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>