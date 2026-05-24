/**
 * Admin Sidebar Toggle Script
 * Handles sidebar toggle functionality for all admin pages
 * with proper mobile support
 */

// Sidebar Toggle Functionality
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const dashboardLayout = document.querySelector('.dashboard-layout');

// Function to check if device is mobile
function isMobile() {
    return window.innerWidth <= 768;
}

// Initialize sidebar state based on device
function initSidebar() {
    // Check if all required elements exist
    if (!dashboardLayout || !sidebarToggle) {
        return; // Exit if elements don't exist
    }
    
    if (isMobile()) {
        // On mobile, start with sidebar collapsed
        dashboardLayout.classList.add('sidebar-collapsed');
        sidebarToggle.classList.add('active');
    } else {
        // On desktop, check localStorage for sidebar state
        const sidebarState = localStorage.getItem('sidebarCollapsed');
        if (sidebarState === 'true') {
            dashboardLayout.classList.add('sidebar-collapsed');
            sidebarToggle.classList.add('active');
        }
    }
}

// Toggle sidebar function
function toggleSidebar() {
    if (!dashboardLayout || !sidebarToggle) {
        return; // Exit if elements don't exist
    }
    
    dashboardLayout.classList.toggle('sidebar-collapsed');
    sidebarToggle.classList.toggle('active');
    const isCollapsed = dashboardLayout.classList.contains('sidebar-collapsed');
    
    // Only save state on desktop
    if (!isMobile()) {
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }
}

// Toggle sidebar on button click
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSidebar();
    });
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function (event) {
    if (!dashboardLayout || !sidebar || !sidebarToggle) {
        return; // Exit if elements don't exist
    }
    
    if (isMobile() && sidebar && sidebarToggle) {
        // Check if click is outside sidebar and toggle button
        if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
            if (!dashboardLayout.classList.contains('sidebar-collapsed')) {
                dashboardLayout.classList.add('sidebar-collapsed');
                sidebarToggle.classList.add('active');
            }
        }
    }
});

// Prevent clicks inside sidebar from closing it
if (sidebar) {
    sidebar.addEventListener('click', (e) => {
        e.stopPropagation();
    });
}

// Handle window resize
let resizeTimer;
window.addEventListener('resize', () => {
    if (!dashboardLayout || !sidebarToggle) {
        return; // Exit if elements don't exist
    }
    
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        // Re-initialize sidebar on resize
        if (isMobile()) {
            dashboardLayout.classList.add('sidebar-collapsed');
            sidebarToggle.classList.add('active');
        } else {
            // Restore desktop state from localStorage
            const sidebarState = localStorage.getItem('sidebarCollapsed');
            if (sidebarState !== 'true') {
                dashboardLayout.classList.remove('sidebar-collapsed');
                sidebarToggle.classList.remove('active');
            }
        }
    }, 250);
});

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebar);
} else {
    initSidebar();
}
