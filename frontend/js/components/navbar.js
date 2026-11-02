/**
 * BONHAGAN TRANSIT — Navbar Component
 * Smooth Mobile Navigation & Active Route Syncer
 */

const Navbar = {
    init() {
        const toggleBtn = document.querySelector('.nav-toggle-btn');
        const mobileMenu = document.querySelector('.nav-mobile-menu');

        if (toggleBtn && mobileMenu) {
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = mobileMenu.classList.toggle('open');
                toggleBtn.classList.toggle('open', isOpen);
                toggleBtn.setAttribute('aria-expanded', isOpen);
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!mobileMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
                    mobileMenu.classList.remove('open');
                    toggleBtn.classList.remove('open');
                    toggleBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Close mobile nav when clicking a link
        document.querySelectorAll('.nav-mobile-link').forEach(link => {
            link.addEventListener('click', () => {
                if (mobileMenu) {
                    mobileMenu.classList.remove('open');
                    if (toggleBtn) {
                        toggleBtn.classList.remove('open');
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        });

        // Highlight active link matching current page
        this.highlightActiveLinks();
    },

    highlightActiveLinks() {
        const currentPath = window.location.pathname.replace(/\/$/, '');
        
        document.querySelectorAll('.nav-link, .portal-link, .sidebar-link, .nav-mobile-link').forEach(link => {
            const href = link.getAttribute('href');
            if (!href) return;

            const cleanHref = href.split('#')[0].replace(/^\.\.\//, '').replace(/^\.\//, '');
            if (cleanHref && currentPath.endsWith(cleanHref)) {
                link.classList.add('active');
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    Navbar.init();
});

window.Navbar = Navbar;
