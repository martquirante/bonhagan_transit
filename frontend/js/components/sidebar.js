/**
 * BONHAGAN TRANSIT — Sidebar Component for Admin & Staff Portals
 */

const Sidebar = {
    init() {
        const toggleBtn = document.querySelector('.admin-toggle-btn, .sidebar-toggle');
        const sidebar = document.querySelector('.admin-sidebar, .staff-sidebar');

        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('open');
            });

            // Close on click outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 1024 && sidebar.classList.contains('open')) {
                    if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                        sidebar.classList.remove('open');
                    }
                }
            });
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    Sidebar.init();
});

window.Sidebar = Sidebar;

