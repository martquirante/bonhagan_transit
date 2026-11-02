/**
 * BONHAGAN TRANSIT — Authentication Controller
 */

const Auth = {
    async login(identifier, password) {
        const payload = {
            login: identifier,
            email: identifier,
            password: password
        };

        const res = await Api.post(API_CONFIG.ENDPOINTS.AUTH.LOGIN, payload);
        if (res.success && res.data && res.data.user) {
            Session.setUser(res.data.user);
            if (res.data.csrf_token) {
                Session.setCsrfToken(res.data.csrf_token);
            }
        }
        return res;
    },

    async register(data) {
        const res = await Api.post(API_CONFIG.ENDPOINTS.AUTH.REGISTER, data);
        if (res.success && res.data && res.data.user) {
            Session.setUser(res.data.user);
            if (res.data.csrf_token) {
                Session.setCsrfToken(res.data.csrf_token);
            }
        }
        return res;
    },

    _getLoginUrl() {
        if (typeof Router !== 'undefined' && typeof Router.toUrl === 'function') {
            return Router.toUrl('pages/public/login.html');
        }
        const path = window.location.pathname;
        const idx = path.indexOf('/frontend/');
        if (idx !== -1) {
            return path.substring(0, idx + 9) + '/pages/public/login.html';
        }
        return '/pages/public/login.html';
    },

    async logout() {
        try {
            await Api.post(API_CONFIG.ENDPOINTS.AUTH.LOGOUT, {});
        } catch (e) {
            console.warn('Logout API failed, continuing local clear', e);
        } finally {
            Session.clear();
            window.location.href = this._getLoginUrl();
        }
    },

    async checkSession() {
        const res = await Api.get(API_CONFIG.ENDPOINTS.AUTH.CURRENT_USER);
        if (res.success && res.data && res.data.user) {
            Session.setUser(res.data.user);
            if (res.data.csrf_token) {
                Session.setCsrfToken(res.data.csrf_token);
            }
            return res.data.user;
        } else {
            Session.clear();
            return null;
        }
    },

    /**
     * Enforce page authorization.
     * @param {string|string[]} allowedRoles e.g. 'passenger' or ['admin', 'staff']
     * @param {string} redirectUrl Optional override for redirect destination
     */
    async protectPage(allowedRoles = null, redirectUrl = null) {
        let user = Session.getUser();
        if (!user) {
            user = await this.checkSession();
        }

        if (!user) {
            const targetLogin = redirectUrl || this._getLoginUrl();
            const currentPath = window.location.pathname + window.location.search;
            window.location.href = `${targetLogin}?redirect=${encodeURIComponent(currentPath)}`;
            return false;
        }

        if (allowedRoles) {
            const roles = Array.isArray(allowedRoles) ? allowedRoles : [allowedRoles];
            if (!roles.includes(user.role)) {
                // Forbidden role, redirect to appropriate role home
                if (typeof Router !== 'undefined' && Router.redirectRoleHome) {
                    Router.redirectRoleHome(user.role);
                }
                return false;
            }
        }

        return user;
    }
};

window.Auth = Auth;

