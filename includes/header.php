<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $page_title ?? 'Online Exam System'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/online_exam_system/assets/css/style.css">
    <style>
        /* Additional styles for tabs */
        .nav-tabs {
            display: flex;
            gap: 0.5rem;
            margin-left: 2rem;
        }
        
        .nav-tab {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-tab:hover {
            background: #f1f5f9;
            color: #10b981;
        }
        
        .nav-tab.active {
            background: #10b981;
            color: white;
        }
        
        .nav-tab i {
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .nav-tabs {
                flex-direction: column;
                margin-left: 0;
                margin-top: 1rem;
                width: 100%;
            }
            
            .nav-tab {
                text-align: center;
                padding: 0.75rem;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <a href="/online_exam_system/index.php" class="logo">
                    <i class="fas fa-graduation-cap"></i>
                    <span>MissionTech College</span>
                </a>
                
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <!-- Admin Tabs -->
                        <div class="nav-tabs">
                            <a href="/online_exam_system/admin/dashboard.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="/online_exam_system/admin/users.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                                <i class="fas fa-users"></i> Users
                            </a>
                            <a href="/online_exam_system/admin/exams.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'exams.php' ? 'active' : ''; ?>">
                                <i class="fas fa-file-alt"></i> Exams
                            </a>
                            <a href="/online_exam_system/admin/questions.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'questions.php' ? 'active' : ''; ?>">
                                <i class="fas fa-question-circle"></i> Questions
                            </a>
                            <a href="/online_exam_system/admin/results.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-bar"></i> Results
                            </a>
                            <a href="/online_exam_system/admin/study-materials.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'study-materials.php' ? 'active' : ''; ?>">
                                <i class="fas fa-book-open"></i> Materials
                            </a>
                            <a href="/online_exam_system/admin/announcements.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>">
                                <i class="fas fa-bullhorn"></i> Announcements
                            </a>
                            <a href="/online_exam_system/admin/exam-schedule.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'exam-schedule.php' ? 'active' : ''; ?>">
                                <i class="fas fa-calendar-alt"></i> Schedule
                            </a>
                            <a href="/online_exam_system/admin/admin-management.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'admin-management.php' ? 'active' : ''; ?>">
                                <i class="fas fa-user-shield"></i> Admin Management
                            </a>
                            <a href="/online_exam_system/admin/ai-generator.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'ai-generator.php' ? 'active' : ''; ?>">
    <i class="fas fa-robot"></i> AI Generator
</a>
                        </div>
                    <?php else: ?>
                        <!-- Student Tabs -->
                        <div class="nav-tabs">
                            <a href="/online_exam_system/student/dashboard.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                            <a href="/online_exam_system/student/exams.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'exams.php' ? 'active' : ''; ?>">
                                <i class="fas fa-pen-alt"></i> Take Exam
                            </a>
                            <a href="/online_exam_system/student/results.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'active' : ''; ?>">
                                <i class="fas fa-chart-bar"></i> Results
                            </a>
                            <a href="/online_exam_system/student/leaderboard.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'leaderboard.php' ? 'active' : ''; ?>">
                                <i class="fas fa-trophy"></i> Leaderboard
                            </a>
                            <a href="/online_exam_system/student/achievements.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'achievements.php' ? 'active' : ''; ?>">
                                <i class="fas fa-medal"></i> Achievements
                            </a>
                            <a href="/online_exam_system/student/materials.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'materials.php' ? 'active' : ''; ?>">
                                <i class="fas fa-book-open"></i> Materials
                            </a>
                            <a href="/online_exam_system/student/certificate.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'certificate.php' ? 'active' : ''; ?>">
                                <i class="fas fa-certificate"></i> Certificates
                            </a>
                            <a href="/online_exam_system/student/ai-tutor.php" class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'ai-tutor.php' ? 'active' : ''; ?>">
                                <i class="fas fa-robot"></i> AI Tutor
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-links" id="navLinks">
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <div class="dropdown">
                            <button class="dropdown-btn">
                                <i class="fas fa-user-shield"></i>
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="dropdown-content">
                                <a href="/online_exam_system/admin/profile.php">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
                                <a href="/online_exam_system/admin/settings.php">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="/online_exam_system/admin/logout.php" class="logout">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="dropdown">
                            <button class="dropdown-btn">
                                <i class="fas fa-user-circle"></i>
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Student'); ?>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="dropdown-content">
                                <a href="/online_exam_system/student/profile.php">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
                                <a href="/online_exam_system/student/change-password.php">
                                    <i class="fas fa-key"></i> Change Password
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="/online_exam_system/student/logout.php" class="logout">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/online_exam_system/student/login.php" class="btn btn-outline">
                        <i class="fas fa-graduation-cap"></i> Student Login
                    </a>
                    <a href="/online_exam_system/admin/login.php" class="btn btn-primary">
                        <i class="fas fa-shield-alt"></i> Admin Login
                    </a>
                    <a href="/online_exam_system/student/register.php" class="btn btn-outline">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <main class="main-content">
        <script>
            // Dropdown functionality
            document.addEventListener('DOMContentLoaded', function() {
                // Get all dropdowns
                const dropdowns = document.querySelectorAll('.dropdown');
                
                dropdowns.forEach(dropdown => {
                    const btn = dropdown.querySelector('.dropdown-btn');
                    
                    if (btn) {
                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            // Close other dropdowns
                            dropdowns.forEach(d => {
                                if (d !== dropdown && d.classList.contains('active')) {
                                    d.classList.remove('active');
                                }
                            });
                            dropdown.classList.toggle('active');
                        });
                    }
                });
                
                // Close dropdowns when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.dropdown')) {
                        dropdowns.forEach(dropdown => {
                            dropdown.classList.remove('active');
                        });
                    }
                });
                
                // Mobile menu functionality
                const mobileMenuBtn = document.getElementById('mobileMenuBtn');
                const navLinks = document.getElementById('navLinks');
                
                if (mobileMenuBtn) {
                    mobileMenuBtn.addEventListener('click', function() {
                        navLinks.classList.toggle('active');
                        document.body.style.overflow = navLinks.classList.contains('active') ? 'hidden' : '';
                    });
                }
                
                // Close mobile menu on window resize if open
                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768 && navLinks.classList.contains('active')) {
                        navLinks.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
                
                // Add active class to current page link
                const currentPage = window.location.pathname.split('/').pop();
                document.querySelectorAll('.nav-tab').forEach(link => {
                    const href = link.getAttribute('href');
                    if (href && href.includes(currentPage)) {
                        link.classList.add('active');
                    }
                });
            });
        </script>