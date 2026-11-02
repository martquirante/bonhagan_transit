/**
 * BONHAGAN TRANSIT — Client Session Manager
 */

const Session = {
    USER_KEY: 'bonhagan_user',
    CSRF_KEY: 'bonhagan_csrf',

    getUser() {
        return StorageUtil.get(this.USER_KEY, null);
    },

    setUser(userData) {
        StorageUtil.set(this.USER_KEY, userData);
    },

    clear() {
        StorageUtil.remove(this.USER_KEY);
        StorageUtil.remove(this.CSRF_KEY);
    },

    getCsrfToken() {
        return StorageUtil.get(this.CSRF_KEY, '');
    },

    setCsrfToken(token) {
        if (token) {
            StorageUtil.set(this.CSRF_KEY, token);
        }
    },

    isAuthenticated() {
        const user = this.getUser();
        return !!(user && user.user_id);
    },

    getRole() {
        const user = this.getUser();
        return user ? user.role : null;
    },

    isAdmin() {
        return this.getRole() === 'admin';
    },

    isStaff() {
        const role = this.getRole();
        return role === 'staff' || role === 'admin';
    },

    isPassenger() {
        return this.getRole() === 'passenger';
    }
};

window.Session = Session;

