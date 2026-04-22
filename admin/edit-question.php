<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$question = $conn->query("SELECT * FROM questions WHERE id = $question_id")->fetch_assoc();

if (!$question) {
    header('Location: questions.php');
    exit();
}

$subjects = $conn->query("SELECT * FROM subjects WHERE status = 1 ORDER BY name");
$topics = $conn->query("SELECT * FROM topics WHERE subject_id = {$question['subject_id']} ORDER BY name");

$page_title = 'Edit Question';
include '../includes/header.php';
?>

<div class="container" style="max-width: 700px;">
    <h1 style="margin-bottom: 2rem;">Edit Question</h1>
    
    <div class="card">
        <form method="POST" action="update-question.php">
            <input type="hidden" name="question_id" value="<?php echo $question_id; ?>">
            <div class="form-group">
                <label>Subject</label>
                <select name="subject_id" id="subject_id" class="form-control" required onchange="loadTopics()">
                    <?php 
                    $subjects->data_seek(0);
                    while ($subject = $subjects->fetch_assoc()): ?>
                    <option value="<?php echo $subject['id']; ?>" <?php echo $subject['id'] == $question['subject_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($subject['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Topic (Optional)</label>
                <select name="topic_id" id="topic_id" class="form-control">
                    <option value="0">None</option>
                    <?php while ($topic = $topics->fetch_assoc()): ?>
                    <option value="<?php echo $topic['id']; ?>" <?php echo $topic['id'] == $question['topic_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($topic['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Question Text</label>
                <textarea name="question_text" class="form-control" rows="3" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Option A</label>
                    <input type="text" name="option_a" class="form-control" value="<?php echo htmlspecialchars($question['option_a']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Option B</label>
                    <input type="text" name="option_b" class="form-control" value="<?php echo htmlspecialchars($question['option_b']); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Option C</label>
                    <input type="text" name="option_c" class="form-control" value="<?php echo htmlspecialchars($question['option_c']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Option D</label>
                    <input type="text" name="option_d" class="form-control" value="<?php echo htmlspecialchars($question['option_d']); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Correct Option</label>
                    <select name="correct_option" class="form-control" required>
                        <option value="A" <?php echo $question['correct_option'] == 'A' ? 'selected' : ''; ?>>A</option>
                        <option value="B" <?php echo $question['correct_option'] == 'B' ? 'selected' : ''; ?>>B</option>
                        <option value="C" <?php echo $question['correct_option'] == 'C' ? 'selected' : ''; ?>>C</option>
                        <option value="D" <?php echo $question['correct_option'] == 'D' ? 'selected' : ''; ?>>D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Difficulty</label>
                    <select name="difficulty" class="form-control">
                        <option value="easy" <?php echo $question['difficulty'] == 'easy' ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?php echo $question['difficulty'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="hard" <?php echo $question['difficulty'] == 'hard' ? 'selected' : ''; ?>>Hard</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Marks</label>
                    <input type="number" name="marks" class="form-control" value="<?php echo $question['marks']; ?>" step="0.5">
                </div>
            </div>
            <div class="form-group">
                <label>Explanation (Optional)</label>
                <textarea name="explanation" class="form-control" rows="2"><?php echo htmlspecialchars($question['explanation']); ?></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="questions.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Update Question</button>
            </div>
        </form>
    </div>
</div>

<script>
function loadTopics() {
    const subjectId = document.getElementById('subject_id').value;
    const topicSelect = document.getElementById('topic_id');
    
    if (subjectId) {
        fetch(`/online_exam_system/api/get-topics.php?subject_id=${subjectId}`)
            .then(response => response.json())
            .then(data => {
                topicSelect.innerHTML = '<option value="0">None</option>';
                data.forEach(topic => {
                    topicSelect.innerHTML += `<option value="${topic.id}">${topic.name}</option>`;
                });
            });
    } else {
        topicSelect.innerHTML = '<option value="0">None</option>';
    }
}
</script>

<?php include '../includes/footer.php'; ?>