<?php
/**
 * Bulk Question Import Page
 * Import questions via Excel/CSV
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';
$imported = 0;
$failed = 0;

// Handle CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $subject_id = (int)$_POST['subject_id'];
    $topic_id = (int)$_POST['topic_id'] ?: null;
    $difficulty = $_POST['difficulty'];
    
    if ($subject_id <= 0) {
        $error = "Please select a subject";
    } elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
        $error = "Please select a CSV file";
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        
        // Skip header row
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if (count($data) >= 8) {
                $question_text = $conn->real_escape_string($data[0]);
                $option_a = $conn->real_escape_string($data[1]);
                $option_b = $conn->real_escape_string($data[2]);
                $option_c = $conn->real_escape_string($data[3]);
                $option_d = $conn->real_escape_string($data[4]);
                $correct_option = strtoupper(trim($data[5]));
                $explanation = $conn->real_escape_string($data[6] ?? '');
                $marks = (float)($data[7] ?? 1);
                
                // Validate correct option
                if (!in_array($correct_option, ['A', 'B', 'C', 'D'])) {
                    $failed++;
                    continue;
                }
                
                $stmt = $conn->prepare("INSERT INTO questions (subject_id, topic_id, question_text, option_a, option_b, option_c, option_d, correct_option, difficulty, explanation, marks, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())");
                $stmt->bind_param("iissssssssdi", $subject_id, $topic_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_option, $difficulty, $explanation, $marks, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $failed++;
                }
                $stmt->close();
            } else {
                $failed++;
            }
        }
        fclose($handle);
        
        $message = "Import completed: $imported questions imported, $failed failed.";
        logActivity($_SESSION['user_id'], 'bulk_import', "Imported $imported questions");
    }
}

// Get subjects for dropdown
$subjects = $conn->query("SELECT id, name FROM subjects WHERE status = 1 ORDER BY name");

$page_title = 'Bulk Import Questions';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-upload"></i> Bulk Import Questions</h1>
        <a href="download-template.php?type=questions" class="btn btn-secondary">
            <i class="fas fa-download"></i> Download Template
        </a>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>CSV Format Instructions:</strong><br>
                The CSV file should have the following columns in order:<br>
                <code>Question Text, Option A, Option B, Option C, Option D, Correct Option (A/B/C/D), Explanation (Optional), Marks (Optional)</code>
            </div>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">Select Subject</option>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Topic (Optional)</label>
                    <select name="topic_id" class="form-control">
                        <option value="0">None</option>
                        <option value="0">Select topic after selecting subject</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Default Difficulty</label>
                    <select name="difficulty" class="form-control">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>CSV File *</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                <small>Max size: 10MB. Only .csv files accepted.</small>
            </div>
            
            <div class="sample-preview">
                <h4>Sample CSV Format:</h4>
                <pre>
"What is 2+2?","3","4","5","6","B","Simple addition","1"
"What is the capital of France?","London","Berlin","Paris","Madrid","C","Paris is the capital","1"
                </pre>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Import Questions
            </button>
        </form>
    </div>
</div>

<style>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.page-header h1 {
    font-size: 2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 12px;
}
.btn-secondary {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 10px 20px;
    border-radius: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-secondary:hover {
    border-color: #10b981;
    color: #10b981;
}
.card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid #eef2f6;
}
.info-box {
    background: #e0f2fe;
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 12px;
    color: #0284c7;
}
.form-group {
    margin-bottom: 1.5rem;
}
.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1e293b;
}
.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.95rem;
}
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}
.sample-preview {
    background: #f8fafc;
    padding: 1rem;
    border-radius: 12px;
    margin: 1rem 0;
}
.sample-preview h4 {
    font-size: 0.9rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}
.sample-preview pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 1rem;
    border-radius: 8px;
    overflow-x: auto;
    font-size: 0.8rem;
}
.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(16,185,129,0.4);
}
.alert {
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1rem;
}
.alert-success {
    background: #ecfdf5;
    color: #10b981;
    border-left: 4px solid #10b981;
}
.alert-error {
    background: #fef2f2;
    color: #ef4444;
    border-left: 4px solid #ef4444;
}
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .sample-preview pre {
        font-size: 0.6rem;
    }
}
</style>

<script>
// Load topics based on subject selection
document.querySelector('select[name="subject_id"]').addEventListener('change', function() {
    const subjectId = this.value;
    const topicSelect = document.querySelector('select[name="topic_id"]');
    
    if (subjectId) {
        fetch(`../api/get-topics.php?subject_id=${subjectId}`)
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
});
</script>

<?php include '../includes/footer.php'; ?>