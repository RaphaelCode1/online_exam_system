<?php
/**
 * Exam Scheduling Page
 * Schedule exams with start/end dates and times
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

if (!canViewExams()) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}


requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

$message = '';
$error = '';

// Handle Create Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $exam_id = (int)$_POST['exam_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $duration_minutes = (int)$_POST['duration_minutes'];
    $attempts_allowed = (int)$_POST['attempts_allowed'];
    $instructions = $conn->real_escape_string($_POST['instructions']);
    
    // Validate times
    if (strtotime($start_time) >= strtotime($end_time)) {
        $error = "End time must be after start time";
    } elseif ($duration_minutes <= 0) {
        $error = "Duration must be positive";
    } else {
        $status = strtotime($start_time) > time() ? 'upcoming' : 
                  (strtotime($end_time) < time() ? 'completed' : 'ongoing');
        
        $stmt = $conn->prepare("INSERT INTO exam_schedules (exam_id, start_time, end_time, duration_minutes, attempts_allowed, instructions, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issiissi", $exam_id, $start_time, $end_time, $duration_minutes, $attempts_allowed, $instructions, $status, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            $message = "Exam schedule created successfully!";
            logActivity($_SESSION['user_id'], 'create_schedule', "Created schedule for exam ID: $exam_id");
        } else {
            $error = "Error creating schedule: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle Delete Schedule
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['schedule_id'])) {
    $schedule_id = (int)$_POST['schedule_id'];
    $stmt = $conn->prepare("DELETE FROM exam_schedules WHERE id = ?");
    $stmt->bind_param("i", $schedule_id);
    if ($stmt->execute()) {
        $message = "Schedule deleted successfully!";
        logActivity($_SESSION['user_id'], 'delete_schedule', "Deleted schedule ID: $schedule_id");
    } else {
        $error = "Error deleting schedule";
    }
    $stmt->close();
}

// Get all exams for dropdown
$exams = $conn->query("SELECT id, title FROM exams WHERE status = 'published' ORDER BY title");

// Get all schedules
$schedules = $conn->query("SELECT s.*, e.title as exam_title, e.duration_minutes as exam_duration 
                           FROM exam_schedules s 
                           JOIN exams e ON s.exam_id = e.id 
                           ORDER BY s.start_time DESC");

$page_title = 'Exam Scheduling';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-calendar-alt"></i> Exam Scheduling</h1>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Schedule Exam
        </button>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Upcoming Schedules -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-clock"></i> Upcoming & Ongoing Exams</h3>
        </div>
        
        <?php 
        $has_upcoming = false;
        $schedules->data_seek(0);
        while ($schedule = $schedules->fetch_assoc()):
            if ($schedule['status'] == 'upcoming' || $schedule['status'] == 'ongoing'):
                $has_upcoming = true;
                $status_class = $schedule['status'] == 'upcoming' ? 'status-upcoming' : 'status-ongoing';
                $status_text = $schedule['status'] == 'upcoming' ? 'Upcoming' : 'Ongoing';
        ?>
        <div class="schedule-card <?php echo $status_class; ?>">
            <div class="schedule-header">
                <h4><?php echo htmlspecialchars($schedule['exam_title']); ?></h4>
                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
            <div class="schedule-details">
                <div class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo date('F j, Y', strtotime($schedule['start_time'])); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span><?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('h:i A', strtotime($schedule['end_time'])); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-hourglass-half"></i>
                    <span>Duration: <?php echo $schedule['duration_minutes']; ?> minutes</span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-repeat"></i>
                    <span>Attempts: <?php echo $schedule['attempts_allowed']; ?></span>
                </div>
            </div>
            <div class="schedule-actions">
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this schedule?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                    <button type="submit" class="btn-icon delete" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php 
            endif;
        endwhile;
        if (!$has_upcoming): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-alt"></i>
            <p>No upcoming or ongoing exams scheduled.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Past Schedules -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-history"></i> Past Exams</h3>
        </div>
        
        <?php 
        $has_past = false;
        $schedules->data_seek(0);
        while ($schedule = $schedules->fetch_assoc()):
            if ($schedule['status'] == 'completed'):
                $has_past = true;
        ?>
        <div class="schedule-card status-completed">
            <div class="schedule-header">
                <h4><?php echo htmlspecialchars($schedule['exam_title']); ?></h4>
                <span class="status-badge status-completed">Completed</span>
            </div>
            <div class="schedule-details">
                <div class="detail-item">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo date('F j, Y', strtotime($schedule['start_time'])); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clock"></i>
                    <span><?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('h:i A', strtotime($schedule['end_time'])); ?></span>
                </div>
                <div class="detail-item">
                    <i class="fas fa-hourglass-half"></i>
                    <span>Duration: <?php echo $schedule['duration_minutes']; ?> minutes</span>
                </div>
            </div>
            <div class="schedule-actions">
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this schedule?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                    <button type="submit" class="btn-icon delete" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php 
            endif;
        endwhile;
        if (!$has_past): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <p>No past exams.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Schedule Modal -->
<div id="createModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Schedule Exam</h2>
            <button class="close-modal" onclick="closeCreateModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label>Select Exam</label>
                <select name="exam_id" class="form-control" required>
                    <option value="">Choose Exam</option>
                    <?php 
                    $exams->data_seek(0);
                    while ($exam = $exams->fetch_assoc()): ?>
                    <option value="<?php echo $exam['id']; ?>"><?php echo htmlspecialchars($exam['title']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date & Time</label>
                    <input type="datetime-local" name="start_time" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date & Time</label>
                    <input type="datetime-local" name="end_time" class="form-control" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration_minutes" class="form-control" value="60" required min="1">
                </div>
                <div class="form-group">
                    <label>Attempts Allowed</label>
                    <select name="attempts_allowed" class="form-control">
                        <option value="1">1 attempt</option>
                        <option value="2">2 attempts</option>
                        <option value="3">3 attempts</option>
                        <option value="0">Unlimited</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Instructions (Optional)</label>
                <textarea name="instructions" class="form-control" rows="4" placeholder="Enter exam instructions for students..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Schedule Exam</button>
            </div>
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
.section-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    margin-bottom: 2rem;
}
.section-header {
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #eef2f6;
}
.section-header h3 {
    font-size: 1.1rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.schedule-card {
    background: #f8fafc;
    border-radius: 16px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
    border-left: 4px solid;
}
.schedule-card.status-upcoming {
    border-left-color: #f59e0b;
}
.schedule-card.status-ongoing {
    border-left-color: #10b981;
}
.schedule-card.status-completed {
    border-left-color: #94a3b8;
    opacity: 0.7;
}
.schedule-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.schedule-header h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
}
.status-badge {
    padding: 4px 12px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: 600;
}
.status-badge.status-upcoming {
    background: #fef3c7;
    color: #d97706;
}
.status-badge.status-ongoing {
    background: #ecfdf5;
    color: #10b981;
}
.status-badge.status-completed {
    background: #f1f5f9;
    color: #64748b;
}
.schedule-details {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}
.detail-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: #64748b;
}
.detail-item i {
    width: 16px;
    color: #10b981;
}
.schedule-actions {
    display: flex;
    gap: 0.5rem;
}
.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    background: #f1f5f9;
    color: #64748b;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-icon.delete:hover {
    background: #fef2f2;
    color: #ef4444;
}
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-content {
    background: white;
    border-radius: 24px;
    padding: 2rem;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 1.5rem;
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
.btn-secondary {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
    padding: 10px 20px;
    border-radius: 12px;
    cursor: pointer;
}
.empty-state {
    text-align: center;
    padding: 2rem;
    color: #94a3b8;
}
.empty-state i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
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
    .schedule-card {
        flex-direction: column;
        align-items: flex-start;
    }
    .schedule-actions {
        align-self: flex-end;
    }
}
</style>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}
function closeCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>