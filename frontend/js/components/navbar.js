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

        // Initialize user profile dropdown
        this.initUserMenu();
    },

    initUserMenu() {
        const triggers = document.querySelectorAll('.user-menu-trigger');
        if (!triggers.length) return;

        triggers.forEach(trigger => {
            if (trigger.dataset.menuInitialized === 'true') return;
            trigger.dataset.menuInitialized = 'true';

            // Ensure wrapper exists with relative positioning
            let wrapper = trigger.closest('.user-menu-wrapper');
            if (!wrapper) {
                wrapper = document.createElement('div');
                wrapper.className = 'user-menu-wrapper';
                trigger.parentNode.insertBefore(wrapper, trigger);
                wrapper.appendChild(trigger);
            }

            // Set accessibility attributes
            trigger.setAttribute('role', 'button');
            trigger.setAttribute('tabindex', '0');
            trigger.setAttribute('aria-haspopup', 'true');
            trigger.setAttribute('aria-expanded', 'false');

            // Add chevron icon if not present
            if (!trigger.querySelector('.user-menu-chevron')) {
                const chevron = document.createElement('i');
                chevron.className = 'fa-solid fa-chevron-down user-menu-chevron';
                trigger.appendChild(chevron);
            }

            // Sync user data
            const user = (typeof Session !== 'undefined' && Session.getUser && Session.getUser()) || null;
            const displayName = user ? (user.full_name || user.username || 'Passenger') : 'Passenger';
            const displayEmail = user ? (user.email || 'passenger@bonhagan.com') : 'passenger@bonhagan.com';
            const role = (user && user.role) ? user.role : 'Passenger';
            const initial = displayName.charAt(0).toUpperCase();

            const avatarChip = trigger.querySelector('.user-avatar-chip');
            if (avatarChip) avatarChip.textContent = initial;

            const nameEl = trigger.querySelector('#user-display-name');
            if (nameEl) nameEl.textContent = displayName;

            // Create dropdown menu element if not exists
            let dropdown = wrapper.querySelector('.user-dropdown-menu');
            if (!dropdown) {
                dropdown = document.createElement('div');
                dropdown.className = 'user-dropdown-menu';

                const resolveUrl = (page, fallback) => {
                    if (typeof Router !== 'undefined' && typeof Router.toUrl === 'function') {
                        return Router.toUrl(page);
                    }
                    return fallback;
                };

                const dashboardUrl = resolveUrl('dashboard.html', '/dashboard');
                const profileUrl = resolveUrl('profile.html', '/profile');
                const ticketsUrl = resolveUrl('my-tickets.html', '/my-tickets');
                const historyUrl = resolveUrl('booking-history.html', '/booking-history');
                const notifsUrl = resolveUrl('notifications.html', '/notifications');

                const safeName = (typeof DOM !== 'undefined' && DOM.escapeHtml) ? DOM.escapeHtml(displayName) : displayName;
                const safeEmail = (typeof DOM !== 'undefined' && DOM.escapeHtml) ? DOM.escapeHtml(displayEmail) : displayEmail;
                const safeRole = (typeof DOM !== 'undefined' && DOM.escapeHtml) ? DOM.escapeHtml(role) : role;

                dropdown.innerHTML = `
                    <div class="user-dropdown-header">
                        <div class="user-dropdown-name">${safeName}</div>
                        <div class="user-dropdown-email">${safeEmail}</div>
                        <span class="user-dropdown-role"><i class="fa-solid fa-shield"></i> ${safeRole.toUpperCase()}</span>
                    </div>
                    <ul class="user-dropdown-list">
                        <li><a href="${dashboardUrl}" class="user-dropdown-item"><i class="fa-solid fa-gauge"></i> Dashboard</a></li>
                        <li><a href="${profileUrl}" class="user-dropdown-item"><i class="fa-solid fa-user-pen"></i> My Profile</a></li>
                        <li><a href="${ticketsUrl}" class="user-dropdown-item"><i class="fa-solid fa-ticket"></i> My Tickets</a></li>
                        <li><a href="${historyUrl}" class="user-dropdown-item"><i class="fa-solid fa-clock-rotate-left"></i> Booking History</a></li>
                        <li><a href="${notifsUrl}" class="user-dropdown-item"><i class="fa-solid fa-bell"></i> Notifications</a></li>
                    </ul>
                    <div class="user-dropdown-divider"></div>
                    <div style="padding: 2px 0;">
                        <button type="button" class="user-dropdown-item logout" id="user-dropdown-logout-btn">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                        </button>
                    </div>
                `;
                wrapper.appendChild(dropdown);
            }

            // Sync with page script updates to #user-display-name
            if (nameEl && window.MutationObserver) {
                const observer = new MutationObserver(() => {
                    const updatedName = nameEl.textContent.trim();
                    const nameInDropdown = dropdown.querySelector('.user-dropdown-name');
                    if (nameInDropdown && updatedName) {
                        nameInDropdown.textContent = updatedName;
                    }
                });
                observer.observe(nameEl, { childList: true, characterData: true, subtree: true });
            }

            // Click listener on trigger
            const toggleMenu = (e) => {
                e.stopPropagation();
                const isOpen = dropdown.classList.toggle('open');
                trigger.classList.toggle('open', isOpen);
                trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            };

            trigger.addEventListener('click', toggleMenu);
            trigger.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleMenu(e);
                } else if (e.key === 'Escape') {
                    dropdown.classList.remove('open');
                    trigger.classList.remove('open');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                if (!wrapper.contains(e.target)) {
                    dropdown.classList.remove('open');
                    trigger.classList.remove('open');
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });

            // Bind logout button inside dropdown
            const logoutBtn = dropdown.querySelector('#user-dropdown-logout-btn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (typeof Auth !== 'undefined' && typeof Auth.logout === 'function') {
                        Auth.logout();
                    } else if (typeof Session !== 'undefined') {
                        Session.clear();
                        window.location.href = typeof Router !== 'undefined' ? Router.toUrl('login.html') : '/login';
                    } else {
                        window.location.href = '/login';
                    }
                });
            }
        });
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
