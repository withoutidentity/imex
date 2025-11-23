            </main>
        
        <!-- Footer -->
        <?php
        // Ensure $base_path is defined for asset links
        if (!isset($base_path)) {
            $current_dir = dirname($_SERVER['SCRIPT_NAME']);
            $base_path = '';
            if (basename($current_dir) === 'pages') {
                $base_path = '../';
            }
        }
        ?>
    <footer class="bg-white border-t border-gray-200 px-6 py-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="mb-2 md:mb-0">
                <p class="text-sm text-gray-600">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?> - Version <?php echo APP_VERSION; ?></p>
            </div>
            <div class="flex space-x-4">
                <a href="#" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fab fa-facebook"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fab fa-instagram"></i>
                </a>
            </div>
        </div>
    </footer>
    </div>
    
    <!-- Custom JavaScript -->
    <script src="<?php echo $base_path; ?>assets/js/script.js"></script>
    
    <!-- Sidebar JavaScript -->
    <script>
        // Sidebar elements
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebarClose = document.getElementById('sidebar-close');
        const sidebarCollapse = document.getElementById('sidebar-collapse');
        const sidebarOverlay = document.getElementById('sidebar-overlay');
        const contentArea = document.querySelector('.content-area');
        
        // Check if we're on mobile
        function isMobile() {
            return window.innerWidth < 768;
        }
        
        // Toggle sidebar on mobile
        function toggleSidebar() {
            if (isMobile()) {
                sidebar.classList.toggle('sidebar-closed');
                sidebarOverlay.classList.toggle('hidden');
                document.body.classList.toggle('overflow-hidden');
            } else {
                // Desktop collapse
                sidebar.classList.toggle('sidebar-closed');
                contentArea.classList.toggle('content-expanded');
                contentArea.classList.toggle('ml-64');
                contentArea.classList.toggle('ml-0');
            }
        }
        
        // Close sidebar
        function closeSidebar() {
            sidebar.classList.add('sidebar-closed');
            sidebarOverlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }
        
        // Initialize sidebar based on screen size
        function initSidebar() {
            if (isMobile()) {
                sidebar.classList.add('sidebar-closed');
                contentArea.classList.add('content-expanded');
                contentArea.classList.remove('ml-64');
                sidebarOverlay.classList.add('hidden');
            } else {
                sidebar.classList.remove('sidebar-closed');
                contentArea.classList.remove('content-expanded');
                contentArea.classList.add('ml-64');
                sidebarOverlay.classList.add('hidden');
            }
        }
        
        // Event listeners
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeSidebar);
        }
        
        if (sidebarCollapse) {
            sidebarCollapse.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', initSidebar);
        
        // Re-initialize on window resize
        window.addEventListener('resize', initSidebar);
        
        // Add active state to current page
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const menuLinks = document.querySelectorAll('nav a[href]');
            
            menuLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (currentPath.includes(href) || (href === 'index.php' && currentPath.endsWith('/'))) {
                    link.classList.add('bg-blue-700', 'text-white');
                    link.classList.remove('hover:bg-blue-700');
                }
            });
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html> 