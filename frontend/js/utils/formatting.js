/**
 * BONHAGAN TRANSIT — Formatting Utility
 */

const FormatUtil = {
    /**
     * Format a number as Philippine Peso currency
     * @param {number|string} amount 
     * @returns {string} ₱XX.XX
     */
    currency(amount) {
        const num = parseFloat(amount) || 0;
        return '₱' + num.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    /**
     * Format ISO or SQL datetime into readable Philippine date
     * @param {string} dateStr 
     * @param {boolean} includeTime 
     * @returns {string}
     */
    date(dateStr, includeTime = false) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;

        const options = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };

        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
            options.hour12 = true;
        }

        return d.toLocaleDateString('en-PH', options);
    },

    /**
     * Format time only (12-hour format with AM/PM)
     * @param {string} timeStr (HH:MM:SS or full datetime)
     */
    time(timeStr) {
        if (!timeStr) return '—';
        // If passed as HH:MM or HH:MM:SS
        if (typeof timeStr === 'string' && timeStr.includes(':') && !timeStr.includes('T') && !timeStr.includes('-')) {
            const parts = timeStr.split(':');
            let h = parseInt(parts[0], 10);
            const m = parts[1];
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            h = h ? h : 12; // 0 is 12
            return `${h}:${m} ${ampm}`;
        }

        const d = new Date(timeStr);
        if (isNaN(d.getTime())) return timeStr;
        return d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', hour12: true });
    },

    /**
     * Clean and format Philippine Mobile Number
     * 09171234567 or +639171234567
     */
    phone(phoneStr) {
        if (!phoneStr) return '—';
        const cleaned = phoneStr.replace(/\D/g, '');
        if (cleaned.length === 11 && cleaned.startsWith('09')) {
            return `09${cleaned.slice(2, 5)}-${cleaned.slice(5, 8)}-${cleaned.slice(8)}`;
        }
        return phoneStr;
    },

    /**
     * Format uppercase status text into human readable badge label
     */
    statusLabel(status) {
        if (!status) return 'Unknown';
        return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase().replace(/_/g, ' ');
    }
};

window.FormatUtil = FormatUtil;

