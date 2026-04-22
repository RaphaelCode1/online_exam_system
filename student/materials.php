<?php
/**
 * Study Materials Page for Students - Enhanced Version
 * View, search, filter, and download study materials
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireStudent();

$db = Database::getInstance();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];
$subject_id = isset($_GET['subject']) ? (int)$_GET['subject'] : 0;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Get subjects for filter
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name");

// Build query with filters
$query = "SELECT m.*, s.name as subject_name,
          (SELECT COUNT(*) FROM material_favorites WHERE material_id = m.id AND user_id = $user_id) as is_favorited
          FROM study_materials m 
          LEFT JOIN subjects s ON m.subject_id = s.id 
          WHERE m.is_published = 1";

if ($subject_id > 0) {
    $query .= " AND m.subject_id = $subject_id";
}

if ($search) {
    $query .= " AND (m.title LIKE '%$search%' OR m.description LIKE '%$search%' OR m.tags LIKE '%$search%')";
}

if ($difficulty) {
    $query .= " AND m.difficulty = '$difficulty'";
}

// Sorting
switch ($sort) {
    case 'popular':
        $query .= " ORDER BY m.download_count DESC";
        break;
    case 'rating':
        $query .= " ORDER BY m.rating DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY m.created_at ASC";
        break;
    default:
        $query .= " ORDER BY m.created_at DESC";
}

$materials = $conn->query($query);

// Get statistics
$total_materials = $conn->query("SELECT COUNT(*) as total FROM study_materials WHERE is_published = 1")->fetch_assoc()['total'];
$total_downloads = $conn->query("SELECT SUM(download_count) as total FROM study_materials WHERE is_published = 1")->fetch_assoc()['total'];

// Handle view/download
if (isset($_GET['view']) && isset($_GET['id'])) {
    $view_id = (int)$_GET['id'];
    
    // Update download count
    $conn->query("UPDATE study_materials SET download_count = download_count + 1 WHERE id = $view_id");
    
    // Get material details
    $material = $conn->query("SELECT * FROM study_materials WHERE id = $view_id")->fetch_assoc();
    
    if ($material) {
        // Add to user activity
        $stmt = $conn->prepare("INSERT INTO user_activity (user_id, activity_type, material_id, created_at) VALUES (?, 'download', ?, NOW())");
        $stmt->bind_param("ii", $user_id, $view_id);
        $stmt->execute();
        
        if ($material['file_path']) {
            header("Location: ../" . $material['file_path']);
            exit();
        }
    }
}

// Handle favorite toggle via AJAX
if (isset($_POST['toggle_favorite'])) {
    $material_id = (int)$_POST['material_id'];
    $check = $conn->query("SELECT id FROM material_favorites WHERE user_id = $user_id AND material_id = $material_id");
    
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM material_favorites WHERE user_id = $user_id AND material_id = $material_id");
        echo json_encode(['status' => 'removed']);
    } else {
        $conn->query("INSERT INTO material_favorites (user_id, material_id, created_at) VALUES ($user_id, $material_id, NOW())");
        echo json_encode(['status' => 'added']);
    }
    exit();
}

// Handle rating via AJAX
if (isset($_POST['rate_material'])) {
    $material_id = (int)$_POST['material_id'];
    $rating = (int)$_POST['rating'];
    
    // Check if user already rated
    $check = $conn->query("SELECT id FROM material_ratings WHERE user_id = $user_id AND material_id = $material_id");
    
    if ($check->num_rows > 0) {
        $conn->query("UPDATE material_ratings SET rating = $rating WHERE user_id = $user_id AND material_id = $material_id");
    } else {
        $conn->query("INSERT INTO material_ratings (user_id, material_id, rating, created_at) VALUES ($user_id, $material_id, $rating, NOW())");
    }
    
    // Update average rating
    $avg = $conn->query("SELECT AVG(rating) as avg FROM material_ratings WHERE material_id = $material_id")->fetch_assoc();
    $new_avg = round($avg['avg'], 1);
    $conn->query("UPDATE study_materials SET rating = $new_avg WHERE id = $material_id");
    
    echo json_encode(['status' => 'success', 'rating' => $new_avg]);
    exit();
}

$page_title = 'Study Materials';
include '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-book-open"></i> Study Materials</h1>
            <p>Access learning resources for your courses</p>
        </div>
        <div class="stats-badge">
            <span><i class="fas fa-database"></i> <?php echo $total_materials; ?> Resources</span>
            <span><i class="fas fa-download"></i> <?php echo number_format($total_downloads); ?> Downloads</span>
        </div>
    </div>
    
    <!-- Enhanced Search and Filters -->
    <div class="filters-card">
        <form method="GET" action="" class="filters-form" id="filterForm">
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by title, description, or tags..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-search">Search</button>
            </div>
            
            <div class="filters-row">
                <div class="filter-group">
                    <label><i class="fas fa-book"></i> Subject</label>
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
                    <label><i class="fas fa-chart-line"></i> Difficulty</label>
                    <select name="difficulty" class="filter-control" onchange="this.form.submit()">
                        <option value="">All Levels</option>
                        <option value="beginner" <?php echo $difficulty == 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                        <option value="intermediate" <?php echo $difficulty == 'intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                        <option value="advanced" <?php echo $difficulty == 'advanced' ? 'selected' : ''; ?>>Advanced</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label><i class="fas fa-sort"></i> Sort By</label>
                    <select name="sort" class="filter-control" onchange="this.form.submit()">
                        <option value="latest" <?php echo $sort == 'latest' ? 'selected' : ''; ?>>Latest First</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Top Rated</option>
                        <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <a href="materials.php" class="btn-reset">
                        <i class="fas fa-times"></i> Reset Filters
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Results Count -->
    <div class="results-count">
        Found <strong><?php echo $materials->num_rows; ?></strong> study materials
        <?php if ($search): ?>
            for "<strong><?php echo htmlspecialchars($search); ?></strong>"
        <?php endif; ?>
    </div>
    
    <!-- Materials Grid -->
    <div class="materials-grid">
        <?php if ($materials->num_rows == 0): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No Study Materials Found</h3>
                <p>Try adjusting your search or filter criteria.</p>
                <a href="materials.php" class="btn-primary">Clear Filters</a>
            </div>
        <?php else: ?>
            <?php while ($material = $materials->fetch_assoc()): 
                $type_icon = [
                    'pdf' => 'fa-file-pdf',
                    'video' => 'fa-video',
                    'document' => 'fa-file-word',
                    'presentation' => 'fa-file-powerpoint',
                    'link' => 'fa-link',
                    'image' => 'fa-image'
                ];
                $type_color = [
                    'pdf' => '#ef4444',
                    'video' => '#3b82f6',
                    'document' => '#10b981',
                    'presentation' => '#f59e0b',
                    'link' => '#8b5cf6',
                    'image' => '#ec4899'
                ];
                $icon = $type_icon[$material['material_type']] ?? 'fa-file';
                $color = $type_color[$material['material_type']] ?? '#64748b';
                
                // Calculate rating stars
                $rating = round($material['rating'] ?? 0);
                $full_stars = floor($rating);
                $half_star = ($rating - $full_stars) >= 0.5;
                $empty_stars = 5 - ceil($rating);
            ?>
            <div class="material-card" data-material-id="<?php echo $material['id']; ?>">
                <div class="material-icon" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="material-info">
                    <div class="material-header">
                        <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                        <button class="favorite-btn <?php echo $material['is_favorited'] ? 'active' : ''; ?>" onclick="toggleFavorite(<?php echo $material['id']; ?>, this)">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                    <p><?php echo htmlspecialchars(substr($material['description'], 0, 120)); ?></p>
                    
                    <?php if ($material['tags']): ?>
                        <div class="material-tags">
                            <?php foreach (explode(',', $material['tags']) as $tag): ?>
                                <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="material-meta">
                        <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($material['subject_name'] ?? 'General'); ?></span>
                        <?php if ($material['difficulty']): ?>
                            <span class="difficulty-badge difficulty-<?php echo $material['difficulty']; ?>">
                                <i class="fas fa-chart-line"></i> <?php echo ucfirst($material['difficulty']); ?>
                            </span>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($material['created_at'])); ?></span>
                        <span><i class="fas fa-download"></i> <?php echo number_format($material['download_count'] ?? 0); ?> downloads</span>
                        <span class="rating-stars">
                            <?php for ($i = 0; $i < $full_stars; $i++): ?>
                                <i class="fas fa-star star-filled"></i>
                            <?php endfor; ?>
                            <?php if ($half_star): ?>
                                <i class="fas fa-star-half-alt star-filled"></i>
                            <?php endif; ?>
                            <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                                <i class="far fa-star star-empty"></i>
                            <?php endfor; ?>
                            <span class="rating-value">(<?php echo number_format($material['rating'] ?? 0, 1); ?>)</span>
                        </span>
                    </div>
                    
                    <div class="rating-section">
                        <span class="rate-label">Rate this resource:</span>
                        <div class="star-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="far fa-star rate-star" data-rating="<?php echo $i; ?>" onclick="rateMaterial(<?php echo $material['id']; ?>, <?php echo $i; ?>)"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <div class="material-actions">
                    <a href="materials.php?view=1&id=<?php echo $material['id']; ?>" class="btn-download" title="Download">
                        <i class="fas fa-download"></i> Download
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination (if needed) -->
    <?php if ($materials->num_rows > 12): ?>
    <div class="pagination">
        <button class="page-btn">« Previous</button>
        <button class="page-btn active">1</button>
        <button class="page-btn">2</button>
        <button class="page-btn">3</button>
        <button class="page-btn">Next »</button>
    </div>
    <?php endif; ?>
</div>

<style>
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

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

.stats-badge {
    display: flex;
    gap: 1rem;
    background: white;
    padding: 0.75rem 1.5rem;
    border-radius: 40px;
    border: 1px solid #eef2f6;
}

.stats-badge span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #64748b;
    font-size: 0.85rem;
}

/* Filters Card */
.filters-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #eef2f6;
}

.search-bar {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 0.5rem;
}

.search-bar i {
    margin-left: 0.75rem;
    color: #94a3b8;
}

.search-bar input {
    flex: 1;
    border: none;
    background: none;
    padding: 0.75rem;
    outline: none;
    font-size: 0.95rem;
}

.btn-search {
    padding: 0.5rem 1.5rem;
    background: #10b981;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

.filters-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-size: 0.8rem;
    font-weight: 500;
    color: #64748b;
}

.filter-control {
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 0.9rem;
    background: white;
    cursor: pointer;
}

.btn-reset {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 16px;
    background: #f1f5f9;
    color: #64748b;
    text-decoration: none;
    border-radius: 12px;
    font-size: 0.85rem;
    transition: all 0.3s;
}

.btn-reset:hover {
    background: #e2e8f0;
}

/* Results Count */
.results-count {
    margin-bottom: 1.5rem;
    font-size: 0.85rem;
    color: #64748b;
}

/* Materials Grid */
.materials-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
}

.material-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid #eef2f6;
    display: flex;
    gap: 1rem;
    transition: all 0.3s;
    position: relative;
}

.material-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.material-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.material-icon i {
    font-size: 1.8rem;
}

.material-info {
    flex: 1;
}

.material-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.material-info h3 {
    font-size: 1.05rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.favorite-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: #cbd5e1;
    font-size: 1.1rem;
    transition: all 0.3s;
}

.favorite-btn.active {
    color: #ef4444;
}

.favorite-btn:hover {
    transform: scale(1.1);
}

.material-info p {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 0.75rem;
    line-height: 1.4;
}

.material-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.tag {
    background: #f1f5f9;
    padding: 2px 8px;
    border-radius: 30px;
    font-size: 0.65rem;
    color: #1e293b;
}

.material-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.7rem;
    color: #94a3b8;
    margin-bottom: 0.75rem;
}

.material-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.difficulty-badge {
    padding: 2px 8px;
    border-radius: 30px;
    font-weight: 500;
}

.difficulty-beginner { background: #ecfdf5; color: #10b981; }
.difficulty-intermediate { background: #fef3c7; color: #f59e0b; }
.difficulty-advanced { background: #fef2f2; color: #ef4444; }

.rating-stars {
    display: inline-flex;
    align-items: center;
    gap: 2px;
}

.star-filled {
    color: #f59e0b;
}

.star-empty {
    color: #cbd5e1;
}

.rating-value {
    margin-left: 4px;
    font-size: 0.65rem;
}

.rating-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.rate-label {
    font-size: 0.7rem;
    color: #64748b;
}

.star-rating {
    display: inline-flex;
    gap: 4px;
}

.rate-star {
    font-size: 0.8rem;
    color: #cbd5e1;
    cursor: pointer;
    transition: all 0.2s;
}

.rate-star:hover,
.rate-star.hover {
    color: #f59e0b;
}

.material-actions {
    display: flex;
    align-items: center;
}

.btn-download {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s;
}

.btn-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16,185,129,0.3);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.page-btn {
    padding: 8px 14px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.page-btn:hover,
.page-btn.active {
    background: #10b981;
    color: white;
    border-color: #10b981;
}

.empty-state {
    text-align: center;
    padding: 4rem;
    background: white;
    border-radius: 20px;
    border: 1px solid #eef2f6;
    grid-column: 1 / -1;
}

.empty-state i {
    font-size: 4rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 1.2rem;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #64748b;
    margin-bottom: 1.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 768px) {
    .container {
        padding: 1rem;
    }
    
    .materials-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-row {
        grid-template-columns: 1fr;
    }
    
    .material-card {
        flex-direction: column;
    }
    
    .material-actions {
        justify-content: flex-end;
        margin-top: 0.5rem;
    }
    
    .stats-badge {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Toggle favorite
function toggleFavorite(materialId, button) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `toggle_favorite=1&material_id=${materialId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'added') {
            button.classList.add('active');
        } else {
            button.classList.remove('active');
        }
    });
}

// Rate material
function rateMaterial(materialId, rating) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `rate_material=1&material_id=${materialId}&rating=${rating}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Update the rating display for this card
            const card = document.querySelector(`.material-card[data-material-id="${materialId}"]`);
            const ratingStars = card.querySelector('.rating-stars');
            const ratingValue = ratingStars.querySelector('.rating-value');
            
            // Reload page to show updated rating
            location.reload();
        }
    });
}

// Star rating hover effect
document.querySelectorAll('.rate-star').forEach(star => {
    star.addEventListener('mouseenter', function() {
        const rating = parseInt(this.dataset.rating);
        const stars = this.parentElement.querySelectorAll('.rate-star');
        stars.forEach((s, index) => {
            if (index < rating) {
                s.classList.add('hover');
            } else {
                s.classList.remove('hover');
            }
        });
    });
    
    star.addEventListener('mouseleave', function() {
        const stars = this.parentElement.querySelectorAll('.rate-star');
        stars.forEach(s => s.classList.remove('hover'));
    });
});
</script>

<?php include '../includes/footer.php'; ?>