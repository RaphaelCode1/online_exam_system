<?php
/**
 * Chatbot Admin Settings
 */

session_name('ADMIN_SESSION');
session_start();

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if super admin for full access
$is_super_admin = isSuperAdmin($conn, $_SESSION['user_id']);

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add knowledge
    if ($action === 'add_knowledge') {
        $category = $conn->real_escape_string($_POST['category']);
        $keywords = $conn->real_escape_string($_POST['keywords']);
        $response = $conn->real_escape_string($_POST['response']);
        $priority = (int)$_POST['priority'];
        
        $stmt = $conn->prepare("INSERT INTO chatbot_knowledge (category, keywords, response, priority) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $category, $keywords, $response, $priority);
        if ($stmt->execute()) {
            $message = "Knowledge added successfully!";
        } else {
            $error = "Failed to add knowledge: " . $conn->error;
        }
    }
    
    // Delete knowledge
    elseif ($action === 'delete_knowledge') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM chatbot_knowledge WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "Knowledge deleted successfully!";
        } else {
            $error = "Failed to delete knowledge";
        }
    }
    
    // Clear conversations
    elseif ($action === 'clear_conversations') {
        $conn->query("TRUNCATE TABLE chatbot_conversations");
        $message = "All conversations cleared!";
    }
}

// Get all knowledge entries
$knowledge = $conn->query("SELECT * FROM chatbot_knowledge ORDER BY priority DESC, id DESC");

// Get conversation statistics
$total_conversations = $conn->query("SELECT COUNT(*) as total FROM chatbot_conversations")->fetch_assoc()['total'];
$total_users = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM chatbot_conversations WHERE user_id IS NOT NULL")->fetch_assoc()['total'];
$today_conversations = $conn->query("SELECT COUNT(*) as total FROM chatbot_conversations WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];

$page_title = 'Chatbot Settings';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-robot"></i> Chatbot Settings</h1>
        <p>Manage AI assistant knowledge and view conversation logs</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_conversations; ?></div>
            <div class="stat-label">Total Conversations</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_users; ?></div>
            <div class="stat-label">Unique Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $today_conversations; ?></div>
            <div class="stat-label">Today's Conversations</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $knowledge->num_rows; ?></div>
            <div class="stat-label">Knowledge Entries</div>
        </div>
    </div>
    
    <!-- Add Knowledge Form -->
    <div class="card">
        <h2><i class="fas fa-plus-circle"></i> Add Knowledge Entry</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_knowledge">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" class="form-control" placeholder="e.g., exam, result, help" required>
                </div>
                <div class="form-group">
                    <label>Priority (Higher = More Important)</label>
                    <input type="number" name="priority" class="form-control" value="5" min="0" max="10">
                </div>
            </div>
            
            <div class="form-group">
                <label>Keywords (comma-separated)</label>
                <input type="text" name="keywords" class="form-control" placeholder="exam, test, assessment" required>
                <small>When user mentions these keywords, this response will be triggered</small>
            </div>
            
            <div class="form-group">
                <label>Response</label>
                <textarea name="response" class="form-control" rows="4" required></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Add Knowledge</button>
        </form>
    </div>
    
    <!-- Knowledge Base -->
    <div class="card">
        <h2><i class="fas fa-database"></i> Knowledge Base</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Keywords</th>
                        <th>Response</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = $knowledge->fetch_assoc()): ?>
                    <tr>
                        <td><span class="badge"><?php echo htmlspecialchars($item['category']); ?></span></td>
                        <td><small><?php echo htmlspecialchars($item['keywords']); ?></small></td>
                        <td><small><?php echo htmlspecialchars(substr($item['response'], 0, 100)); ?>...</small></td>
                        <td><?php echo $item['priority']; ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this knowledge entry?');">
                                <input type="hidden" name="action" value="delete_knowledge">
                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="btn-icon delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Recent Conversations -->
    <div class="card">
        <h2><i class="fas fa-history"></i> Recent Conversations</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Message</th>
                        <th>Response</th>
                        <th>Intent</th>
                        <th>Time</th>
                    </thead>
                <tbody>
                    <?php
                    $conversations = $conn->query("
                        SELECT c.*, u.full_name 
                        FROM chatbot_conversations c
                        LEFT JOIN users u ON c.user_id = u.id
                        ORDER BY c.created_at DESC 
                        LIMIT 20
                    ");
                    while ($conv = $conversations->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($conv['full_name'] ?? 'Guest'); ?></td>
                        <td><small><?php echo htmlspecialchars(substr($conv['user_message'], 0, 60)); ?></small></td>
                        <td><small><?php echo htmlspecialchars(substr($conv['bot_response'], 0, 80)); ?></small></td>
                        <td><span class="badge"><?php echo $conv['intent']; ?></span></td>
                        <td><small><?php echo date('M d, H:i', strtotime($conv['created_at'])); ?></small></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <form method="POST" style="margin-top: 1rem;">
            <input type="hidden" name="action" value="clear_conversations">
            <button type="submit" class="btn btn-secondary" onclick="return confirm('Clear all conversation history?');">
                <i class="fas fa-trash"></i> Clear All Conversations
            </button>
        </form>
    </div>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #10b981;
}

.stat-label {
    font-size: 0.8rem;
    color: #64748b;
    margin-top: 0.5rem;
}

.card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid #eef2f6;
}

.card h2 {
    font-size: 1.2rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #eef2f6;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-secondary {
    background: #f1f5f9;
    color: #1e293b;
}

.badge {
    background: #e0f2fe;
    color: #0284c7;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #eef2f6;
}

.data-table th {
    background: #f8fafc;
    font-weight: 600;
    font-size: 0.8rem;
}

.btn-icon {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
}

.btn-icon.delete {
    color: #ef4444;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include '../includes/footer.php'; ?>