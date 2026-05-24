</main> <!-- End main-content -->
</div> <!-- End dashboard-layout -->

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar Toggle Functionality
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const dashboardLayout = document.querySelector('.dashboard-layout');

    // Check localStorage for sidebar state
    const sidebarState = localStorage.getItem('linumSidebarCollapsed');
    if (sidebarState === 'true') {
        dashboardLayout.classList.add('sidebar-collapsed');
        sidebarToggle.classList.add('active');
    }

    // Toggle sidebar on button click
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            dashboardLayout.classList.toggle('sidebar-collapsed');
            sidebarToggle.classList.toggle('active');

            // Save state to localStorage
            const isCollapsed = dashboardLayout.classList.contains('sidebar-collapsed');
            localStorage.setItem('linumSidebarCollapsed', isCollapsed);
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (event) {
        if (window.innerWidth <= 768) {
            if (sidebar && !sidebar.contains(event.target) && sidebarToggle && !sidebarToggle.contains(event.target)) {
                if (!dashboardLayout.classList.contains('sidebar-collapsed')) {
                    dashboardLayout.classList.add('sidebar-collapsed');
                    if (sidebarToggle) sidebarToggle.classList.remove('active'); // Wait, header logic adds active to expand? No, active is usually cross shape.
                    // Actually logic in header styles: collapsed = transform -100%. 
                    // If we are NOT collapsed on mobile, sidebar is visible. We want to collapse it.
                    // sidebarToggle.classList.add('active') makes it a X.
                }
            }
        }
    });
</script>
</body>

</html>