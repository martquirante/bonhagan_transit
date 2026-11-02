/**
 * BONHAGAN TRANSIT — Input Validation Utility
 */

const ValidationUtil = {
    isEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    },

    isPhilippinePhone(phone) {
        // Must match 09XXXXXXXXX or +639XXXXXXXXX
        const re = /^(09|\+639)\d{9}$/;
        return re.test(String(phone).replace(/\s|-/g, ''));
    },

    isStrongPassword(password) {
        // At least 8 characters
        return typeof password === 'string' && password.length >= 8;
    },

    isNotEmpty(value) {
        return value !== null && value !== undefined && String(value).trim().length > 0;
    },

    isValidDate(dateStr) {
        const d = new Date(dateStr);
        return !isNaN(d.getTime());
    }
};

window.ValidationUtil = ValidationUtil;

