/**
 * BONHAGAN TRANSIT — Client-side Routing & Navigation Helper
 * 
 * Unifies navigation between Clean URL Mode (e.g. PHP built-in server with router.php,
 * Apache mod_rewrite, Nginx) and Static File Mode (e.g. VS Code Live Server).
 */

const Router = {
    _directory: {
        // Public Pages
        'index.html': { clean: '/', live: 'index.html' },
        'routes.html': { clean: '/routes', live: 'pages/public/routes.html' },
        'schedules.html': { clean: '/schedules', live: 'pages/public/schedules.html' },
        'services.html': { clean: '/services', live: 'pages/public/services.html' },
        'about.html': { clean: '/about', live: 'pages/public/about.html' },
        'contact.html': { clean: '/contact', live: 'pages/public/contact.html' },
        'login.html': { clean: '/login', live: 'pages/public/login.html' },
        'register.html': { clean: '/register', live: 'pages/public/register.html' },

        // Passenger Pages
        'book-trip.html': { clean: '/book-trip', live: 'pages/passenger/book-trip.html' },
        'seat-selection.html': { clean: '/passenger/seat-selection', live: 'pages/passenger/seat-selection.html' },
        'passenger-details.html': { clean: '/passenger/passenger-details', live: 'pages/passenger/passenger-details.html' },
        'checkout.html': { clean: '/passenger/checkout', live: 'pages/passenger/checkout.html' },
        'booking-confirmation.html': { clean: '/passenger/booking-confirmation', live: 'pages/passenger/booking-confirmation.html' },
        'my-tickets.html': { clean: '/passenger/my-tickets', live: 'pages/passenger/my-tickets.html' },
        'ticket-details.html': { clean: '/passenger/ticket-details', live: 'pages/passenger/ticket-details.html' },
        'booking-history.html': { clean: '/passenger/booking-history', live: 'pages/passenger/booking-history.html' },
        'profile.html': { clean: '/passenger/profile', live: 'pages/passenger/profile.html' },
        'notifications.html': { clean: '/passenger/notifications', live: 'pages/passenger/notifications.html' },
        'trip-details.html': { clean: '/passenger/trip-details', live: 'pages/passenger/trip-details.html' },

        // Admin Pages
        'buses.html': { clean: '/admin/buses', live: 'pages/admin/buses.html' },
        'stops.html': { clean: '/admin/stops', live: 'pages/admin/stops.html' },
        'bookings.html': { clean: '/admin/bookings', live: 'pages/admin/bookings.html' },
        'passengers.html': { clean: '/admin/passengers', live: 'pages/admin/passengers.html' },
        'discounts.html': { clean: '/admin/discounts', live: 'pages/admin/discounts.html' },
        'payments.html': { clean: '/admin/payments', live: 'pages/admin/payments.html' },
        'staff.html': { clean: '/admin/staff', live: 'pages/admin/staff.html' },
        'reports.html': { clean: '/admin/reports', live: 'pages/admin/reports.html' },
        'settings.html': { clean: '/admin/settings', live: 'pages/admin/settings.html' },

        // Staff Pages
        'assigned-trips.html': { clean: '/staff/assigned-trips', live: 'pages/staff/assigned-trips.html' },
        'passenger-manifest.html': { clean: '/staff/passenger-manifest', live: 'pages/staff/passenger-manifest.html' },
        'qr-scanner.html': { clean: '/staff/qr-scanner', live: 'pages/staff/qr-scanner.html' },
        'incidents.html': { clean: '/staff/incidents', live: 'pages/staff/incidents.html' },
    },

    /**
     * Dynamically resolves the base path (e.g. '/frontend' on Live Server or subdirectories).
     */
    getBasePath() {
        const host = window.location.hostname;
        if (host.endsWith('vercel.app') || host.endsWith('onrender.com')) {
            return '';
        }
        const path = window.location.pathname;
        const frontendIdx = path.indexOf('/frontend/');
        if (frontendIdx !== -1) {
            return path.substring(0, frontendIdx + 9); // Returns e.g. "/frontend"
        }
        return '';
    },

    /**
     * Checks if current environment is using clean URLs (e.g. router.php, Vercel, Render, Apache mod_rewrite).
     */
    isCleanUrlMode() {
        const host = window.location.hostname;
        if (host.endsWith('vercel.app') || host.endsWith('onrender.com')) {
            return true;
        }
        return !window.location.pathname.includes('.html') && !window.location.pathname.includes('/frontend/');
    },

    /**
     * Converts any relative or legacy route into an environment-aware URL.
     */
    toUrl(relativePath) {
        if (!relativePath) return '/';

        // Direct clean URLs (e.g. '/routes', '/book-trip')
        if (relativePath.startsWith('/') && !relativePath.includes('.html')) {
            if (this.isCleanUrlMode()) return relativePath;
            // Map clean URL to static live server path
            for (const [, item] of Object.entries(this._directory)) {
                if (item.clean === relativePath) {
                    const base = this.getBasePath();
                    return base ? `${base}/${item.live}` : `/${item.live}`;
                }
            }
            return relativePath;
        }

        // Extract filename from input (e.g. '../passenger/book-trip.html' -> 'book-trip.html')
        const rawClean = relativePath.split('?')[0].split('#')[0];
        const filename = rawClean.split('/').pop();

        // Special handling for dashboard.html based on context
        if (filename === 'dashboard.html') {
            if (rawClean.includes('admin') || window.location.pathname.includes('admin')) {
                return this.isCleanUrlMode() ? '/admin/dashboard' : `${this.getBasePath()}/pages/admin/dashboard.html`;
            }
            if (rawClean.includes('staff') || window.location.pathname.includes('staff')) {
                return this.isCleanUrlMode() ? '/staff/dashboard' : `${this.getBasePath()}/pages/staff/dashboard.html`;
            }
            return this.isCleanUrlMode() ? '/passenger/dashboard' : `${this.getBasePath()}/pages/passenger/dashboard.html`;
        }

        if (this._directory[filename]) {
            const entry = this._directory[filename];
            if (this.isCleanUrlMode()) {
                return entry.clean;
            } else {
                const base = this.getBasePath();
                return base ? `${base}/${entry.live}` : `/${entry.live}`;
            }
        }

        // Fallback standard resolution
        const clean = relativePath.startsWith('/') ? relativePath.substring(1) : relativePath;
        const base = this.getBasePath();
        return base ? `${base}/${clean}` : `/${clean}`;
    },

    /**
     * Sanitizes a redirect query string parameter to prevent 404s.
     */
    sanitizeRedirect(redirect) {
        if (!redirect || redirect.includes('login') || redirect.includes('register')) {
            return null;
        }
        if (this.isCleanUrlMode()) {
            if (redirect.includes('dashboard')) return '/passenger/dashboard';
            if (redirect.includes('book-trip')) return '/book-trip';
            return redirect;
        }
        const base = this.getBasePath();
        if (base && redirect.startsWith('/pages/') && !redirect.startsWith(base)) {
            return `${base}${redirect}`;
        }
        return redirect;
    },

    getParam(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    },

    getParams() {
        const urlParams = new URLSearchParams(window.location.search);
        const params = {};
        for (const [key, value] of urlParams.entries()) {
            params[key] = value;
        }
        return params;
    },

    setParam(name, value) {
        const url = new URL(window.location);
        url.searchParams.set(name, value);
        window.history.pushState({}, '', url);
    },

    navigate(path, params = {}) {
        const target = this.toUrl(path);
        let queryString = '';
        const keys = Object.keys(params);
        if (keys.length > 0) {
            queryString = '?' + keys.map(k => `${encodeURIComponent(k)}=${encodeURIComponent(params[k])}`).join('&');
        }
        window.location.href = `${target}${queryString}`;
    },

    redirectRoleHome(role) {
        switch (role) {
            case 'admin':
                window.location.href = this.toUrl('pages/admin/dashboard.html');
                break;
            case 'staff':
                window.location.href = this.toUrl('pages/staff/dashboard.html');
                break;
            case 'passenger':
            default:
                window.location.href = this.toUrl('pages/passenger/dashboard.html');
                break;
        }
    }
};

window.Router = Router;
