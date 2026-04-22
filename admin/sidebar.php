<?php
/**
 * Admin Sidebar Navigation
 */

// Get current page for active class
$current_page = basename($_SERVER["PHP_SELF"]);
$user_name = $_SESSION['full_name'] ?? 'Administrator';
$user_avatar = $_SESSION['avatar'] ?? null;
$user_role = $_SESSION['role'] ?? '';

// Helper function to check active page
function isAdminActive($pages) {
    global $current_page;
    if (is_array($pages)) {
        return in_array($current_page, $pages) ? 'active' : '';
    }
    return $current_page == $pages ? 'active' : '';
}
?>

<!-- Admin Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>MissionTech Admin</span>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <!-- DASHBOARD Section -->
        <div class="menu-section">
            <div class="menu-title">MAIN</div>
            <a href="dashboard.php" class="menu-item <?php echo isAdminActive(['dashboard.php']); ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- USER MANAGEMENT Section -->
        <div class="menu-section">
            <div class="menu-title">USER MANAGEMENT</div>
            <a href="users.php" class="menu-item <?php echo isAdminActive(['users.php', 'view-student.php', 'edit-student.php']); ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <?php if ($user_role == 'super_admin'): ?>
            <a href="admins.php" class="menu-item <?php echo isAdminActive(['admins.php', 'edit-admin.php']); ?>">
                <i class="fas fa-user-shield"></i>
                <span>Administrators</span>
            </a>
            <?php endif; ?>
        </div>
        
        <!-- QUESTION BANK Section -->
        <div class="menu-section">
            <div class="menu-title">QUESTION BANK</div>
            <a href="questions.php" class="menu-item <?php echo isAdminActive(['questions.php', 'add-question.php', 'edit-question.php']); ?>">
                <i class="fas fa-question-circle"></i>
                <span>All Questions</span>
            </a>
            <a href="subjects.php" class="menu-item <?php echo isAdminActive(['subjects.php', 'add-subject.php']); ?>">
                <i class="fas fa-book"></i>
                <span>Subjects</span>
            </a>
            <a href="topics.php" class="menu-item <?php echo isAdminActive(['topics.php', 'add-topic.php']); ?>">
                <i class="fas fa-tags"></i>
                <span>Topics</span>
            </a>
            <a href="bulk-import.php" class="menu-item <?php echo isAdminActive(['bulk-import.php']); ?>">
                <i class="fas fa-upload"></i>
                <span>Bulk Import</span>
            </a>
        </div>
        
        <!-- EXAM MANAGEMENT Section -->
        <div class="menu-section">
            <div class="menu-title">EXAM MANAGEMENT</div>
            <a href="exams.php" class="menu-item <?php echo isAdminActive(['exams.php', 'add-exam.php', 'edit-exam.php']); ?>">
                <i class="fas fa-file-alt"></i>
                <span>Exams</span>
            </a>
            <a href="assign-questions.php" class="menu-item <?php echo isAdminActive(['assign-questions.php']); ?>">
                <i class="fas fa-link"></i>
                <span>Assign Questions</span>
            </a>
            <a href="exam-schedule.php" class="menu-item <?php echo isAdminActive(['exam-schedule.php']); ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Exam Schedule</span>
            </a>
            <a href="results.php" class="menu-item <?php echo isAdminActive(['results.php']); ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Exam Results</span>
            </a>
        </div>
        
        <!-- CONTENT MANAGEMENT Section -->
        <div class="menu-section">
            <div class="menu-title">CONTENT MANAGEMENT</div>
            <a href="study-materials.php" class="menu-item <?php echo isAdminActive(['study-materials.php']); ?>">
                <i class="fas fa-book-open"></i>
                <span>Study Materials</span>
            </a>
            <a href="announcements.php" class="menu-item <?php echo isAdminActive(['announcements.php']); ?>">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
        </div>
        
        <!-- REPORTS & ANALYTICS Section -->
        <div class="menu-section">
            <div class="menu-title">REPORTS & ANALYTICS</div>
            <a href="export-results.php" class="menu-item <?php echo isAdminActive(['export-results.php']); ?>">
                <i class="fas fa-download"></i>
                <span>Export Results</span>
            </a>
            <a href="question-analytics.php" class="menu-item <?php echo isAdminActive(['question-analytics.php']); ?>">
                <i class="fas fa-chart-line"></i>
                <span>Question Analytics</span>
            </a>
            <a href="progress-report.php" class="menu-item <?php echo isAdminActive(['progress-report.php']); ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Student Progress</span>
            </a>
            <a href="reports.php" class="menu-item <?php echo isAdminActive(['reports.php']); ?>">
                <i class="fas fa-file-pdf"></i>
                <span>Reports</span>
            </a>
        </div>
        
        <!-- SYSTEM Section -->
        <div class="menu-section">
            <div class="menu-title">SYSTEM</div>
            <a href="activity-log.php" class="menu-item <?php echo isAdminActive(['activity-log.php']); ?>">
                <i class="fas fa-history"></i>
                <span>Activity Log</span>
            </a>
            <?php if ($user_role == 'super_admin'): ?>
            <a href="backup.php" class="menu-item <?php echo isAdminActive(['backup.php']); ?>">
                <i class="fas fa-database"></i>
                <span>Backup</span>
            </a>
            <a href="settings.php" class="menu-item <?php echo isAdminActive(['settings.php']); ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <?php endif; ?>
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
.admin-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 280px;
    background: #1a1f2e;
    color: #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.admin-sidebar .sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid #2d3348;
}

.admin-sidebar .logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.25rem;
    font-weight: 700;
    color: white;
}

.admin-sidebar .logo i {
    color: #10b981;
    font-size: 1.75rem;
}

.admin-sidebar .sidebar-menu {
    flex: 1;
    padding: 1.5rem 0;
}

.admin-sidebar .menu-section {
    margin-bottom: 1.5rem;
}

.admin-sidebar .menu-title {
    padding: 0 1.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
}

.admin-sidebar .menu-item {
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

.admin-sidebar .menu-item i {
    width: 20px;
    font-size: 1rem;
    color: #64748b;
}

.admin-sidebar .menu-item:hover {
    background: #2d3348;
    color: white;
}

.admin-sidebar .menu-item:hover i {
    color: #10b981;
}

.admin-sidebar .menu-item.active {
    background: #2d3348;
    color: white;
    border-left-color: #10b981;
}

.admin-sidebar .menu-item.active i {
    color: #10b981;
}

.admin-sidebar .sidebar-footer {
    padding: 1.5rem;
    border-top: 1px solid #2d3348;
    font-size: 0.7rem;
    text-align: center;
    color: #94a3b8;
}

.admin-sidebar .sidebar-footer i {
    color: #10b981;
    margin: 0 2px;
}

.admin-sidebar::-webkit-scrollbar {
    width: 6px;
}

.admin-sidebar::-webkit-scrollbar-track {
    background: #1a1f2e;
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: #3f455e;
    border-radius: 3px;
}

@media (max-width: 1024px) {
    .admin-sidebar {
        transform: translateX(-100%);
    }
    
    .admin-sidebar.show {
        transform: translateX(0);
    }
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) {
        sidebar.classList.toggle('show');
        if (overlay) {
            overlay.classList.toggle('active');
        }
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('adminSidebar');
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
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.querySelector('.menu-toggle-btn');
    if (window.innerWidth <= 1024 && sidebar && !sidebar.contains(event.target) && !toggleBtn?.contains(event.target)) {
        closeSidebar();
    }
});
</script>