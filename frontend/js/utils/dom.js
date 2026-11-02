/**
 * BONHAGAN TRANSIT — DOM Helper Utility
 */

const DOM = {
    $(selector, parent = document) {
        return parent.querySelector(selector);
    },

    $$(selector, parent = document) {
        return Array.from(parent.querySelectorAll(selector));
    },

    on(element, event, handler) {
        if (!element) return;
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.addEventListener(event, handler);
        }
    },

    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    },

    setLoading(btnElement, isLoading, originalHtml = '') {
        if (!btnElement) return;
        if (isLoading) {
            btnElement.dataset.originalContent = btnElement.innerHTML;
            btnElement.disabled = true;
            btnElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="display:inline-block;width:1rem;height:1rem;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin 0.75s linear infinite;margin-right:0.5rem;vertical-align:middle;"></span> Loading...`;
        } else {
            btnElement.disabled = false;
            btnElement.innerHTML = originalHtml || btnElement.dataset.originalContent || 'Submit';
        }
    }
};

window.DOM = DOM;

