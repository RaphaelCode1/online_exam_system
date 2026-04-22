<?php
/**
 * Student Sidebar Navigation
 */

// Get current page for active class
$current_page = basename($_SERVER["PHP_SELF"]);
$current_uri = $_SERVER['REQUEST_URI'];
$user_name = $_SESSION['full_name'] ?? 'Student';
$user_avatar = $_SESSION['avatar'] ?? null;

// Helper function to check active page
function isStudentActive($pages, $current_page, $current_uri) {
    if (is_array($pages)) {
        foreach ($pages as $page) {
            if (strpos($current_uri, $page) !== false || $current_page == $page) {
                return true;
            }
        }
    } else {
        return (strpos($current_uri, $pages) !== false || $current_page == $pages);
    }
    return false;
}
?>

<!-- Student Sidebar -->
<aside class="student-sidebar" id="studentSidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>Student Portal</span>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <!-- DASHBOARD Section -->
        <div class="menu-section">
            <div class="menu-title">MAIN</div>
            <a href="dashboard.php" class="menu-item <?php echo isStudentActive('dashboard.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- EXAMS Section -->
        <div class="menu-section">
            <div class="menu-title">EXAMS</div>
            <a href="take-exam.php" class="menu-item <?php echo isStudentActive(['take-exam.php', 'exam.php'], $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Take Exam</span>
            </a>
            <a href="results.php" class="menu-item <?php echo isStudentActive('results.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>My Results</span>
            </a>
        </div>
        
        <!-- PRACTICE Section -->
        <div class="menu-section">
            <div class="menu-title">PRACTICE</div>
            <a href="subjects.php" class="menu-item <?php echo isStudentActive(['subjects.php', 'practice.php'], $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>
                <span>Practice by Subject</span>
            </a>
            <a href="random-practice.php" class="menu-item <?php echo isStudentActive('random-practice.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-random"></i>
                <span>Random Practice</span>
            </a>
        </div>
        
        <!-- PROGRESS Section -->
        <div class="menu-section">
            <div class="menu-title">PROGRESS</div>
            <a href="leaderboard.php" class="menu-item <?php echo isStudentActive('leaderboard.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-trophy"></i>
                <span>Leaderboard</span>
            </a>
            <a href="achievements.php" class="menu-item <?php echo isStudentActive('achievements.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-medal"></i>
                <span>Achievements</span>
            </a>
            <a href="certificate.php" class="menu-item <?php echo isStudentActive('certificate.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-certificate"></i>
                <span>Certificates</span>
            </a>
        </div>
        
        <!-- RESOURCES Section -->
        <div class="menu-section">
            <div class="menu-title">RESOURCES</div>
            <a href="materials.php" class="menu-item <?php echo isStudentActive('materials.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i>
                <span>Study Materials</span>
            </a>
            <a href="notes.php" class="menu-item <?php echo isStudentActive('notes.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-sticky-note"></i>
                <span>My Notes</span>
            </a>
            <a href="bookmarks.php" class="menu-item <?php echo isStudentActive('bookmarks.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-bookmark"></i>
                <span>Bookmarks</span>
            </a>
        </div>
        
        <!-- ACCOUNT Section -->
        <div class="menu-section">
            <div class="menu-title">ACCOUNT</div>
            <a href="profile.php" class="menu-item <?php echo isStudentActive(['profile.php', 'edit-profile.php'], $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            <a href="notifications.php" class="menu-item <?php echo isStudentActive('notifications.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
        </div>
        
        <!-- HELP Section -->
        <div class="menu-section">
            <div class="menu-title">HELP</div>
            <a href="help.php" class="menu-item <?php echo isStudentActive('help.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-question-circle"></i>
                <span>Help Center</span>
            </a>
            <a href="contact.php" class="menu-item <?php echo isStudentActive('contact.php', $current_page, $current_uri) ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>
                <span>Contact Support</span>
            </a>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <div class="footer-info">
            <i class="fas fa-graduation-cap"></i>
            MissionTech College<br>
            <span>&copy; <?php echo date('Y'); ?> All Rights Reserved</span>
        </div>
    </div>
</aside>

<style>
.student-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 280px;
    background: linear-gradient(180deg, #1a1f2e 0%, #0f1219 100%);
    color: #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.student-sidebar .sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid #2d3348;
}

.student-sidebar .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
}

.student-sidebar .logo i {
    color: #10b981;
    font-size: 1.75rem;
}

.student-sidebar .sidebar-menu {
    flex: 1;
    padding: 1.5rem 0;
}

.student-sidebar .menu-section {
    margin-bottom: 1.5rem;
}

.student-sidebar .menu-title {
    padding: 0 1.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
}

.student-sidebar .menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0.7rem 1.5rem;
    color: #cbd5e1;
    text-decoration: none;
    transition: all 0.2s;
    font-size: 0.9rem;
    font-weight: 500;
    border-left: 3px solid transparent;
}

.student-sidebar .menu-item i {
    width: 20px;
    font-size: 1rem;
    color: #64748b;
}

.student-sidebar .menu-item:hover {
    background: #2d3348;
    color: white;
}

.student-sidebar .menu-item:hover i {
    color: #10b981;
}

.student-sidebar .menu-item.active {
    background: #2d3348;
    color: white;
    border-left-color: #10b981;
}

.student-sidebar .menu-item.active i {
    color: #10b981;
}

.student-sidebar .sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid #2d3348;
    font-size: 0.7rem;
    text-align: center;
    color: #94a3b8;
}

.student-sidebar .sidebar-footer i {
    color: #10b981;
    margin: 0 2px;
}

.student-sidebar::-webkit-scrollbar {
    width: 6px;
}

.student-sidebar::-webkit-scrollbar-track {
    background: #1a1f2e;
}

.student-sidebar::-webkit-scrollbar-thumb {
    background: #3f455e;
    border-radius: 3px;
}

@media (max-width: 1024px) {
    .student-sidebar {
        transform: translateX(-100%);
    }
    
    .student-sidebar.show {
        transform: translateX(0);
    }
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('studentSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) {
        sidebar.classList.toggle('show');
        if (overlay) {
            overlay.classList.toggle('active');
        }
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('studentSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) {
        sidebar.classList.remove('show');
        if (overlay) {
            overlay.classList.remove('active');
        }
    }
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('studentSidebar');
    const toggleBtn = document.querySelector('.menu-toggle-btn');
    if (window.innerWidth <= 1024 && sidebar && !sidebar.contains(event.target) && !toggleBtn?.contains(event.target)) {
        closeSidebar();
    }
});
</script>