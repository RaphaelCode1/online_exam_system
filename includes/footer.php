        </div> <!-- Close .content-area -->
    </div> <!-- Close .main-content -->
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Online Exam System. All rights reserved.</p>
        </div>
    </footer>
    
    <script>
        // Profile Dropdown Toggle
        const profileDropdown = document.querySelector('.profile-dropdown');
        if (profileDropdown) {
            profileDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                this.classList.toggle('active');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            if (profileDropdown) {
                profileDropdown.classList.remove('active');
            }
        });

        // Mobile menu toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
            
            // Close sidebar when clicking on main content on mobile
            if (mainContent) {
                mainContent.addEventListener('click', function() {
                    if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                    }
                });
            }
        }

        // Notification click
        const notificationIcon = document.querySelector('.notification-icon');
        if (notificationIcon) {
            notificationIcon.addEventListener('click', function() {
                alert('Notifications feature coming soon!');
            });
        }

        // Add active class to current menu item based on URL
        const currentUrl = window.location.pathname;
        const menuItems = document.querySelectorAll('.menu-item a');
        menuItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href && currentUrl.includes(href)) {
                item.closest('.menu-item').classList.add('active');
            }
        });
    </script>
    
    <?php if (isset($custom_js) && !empty($custom_js)): ?>
        <?php foreach ($custom_js as $js): ?>
            <script src="<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($page_scripts) && !empty($page_scripts)): ?>
        <script>
            <?php echo $page_scripts; ?>
        </script>
    <?php endif; ?>

    <!-- Add Chat Widget -->
<?php if (file_exists(__DIR__ . '/chat-widget.php')): ?>
    <?php include __DIR__ . '/chat-widget.php'; ?>
<?php endif; ?>

</body>
</html>