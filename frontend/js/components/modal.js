/**
 * BONHAGAN TRANSIT — Modal Dialog Controller
 */

const Modal = {
    open(modalId) {
        const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
        if (!modal) return;

        modal.classList.add('active');
        document.body.classList.add('modal-open');

        // Focus trap or first input
        const firstInput = modal.querySelector('input, button:not(.modal-close), select, textarea');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 50);
        }

        // Close on backdrop click if setup
        const backdrop = modal.querySelector('.modal-backdrop');
        if (backdrop && !backdrop.dataset.bound) {
            backdrop.dataset.bound = 'true';
            backdrop.addEventListener('click', () => Modal.close(modal));
        }

        const closeBtns = modal.querySelectorAll('[data-close-modal], .modal-close');
        closeBtns.forEach(btn => {
            if (!btn.dataset.bound) {
                btn.dataset.bound = 'true';
                btn.addEventListener('click', () => Modal.close(modal));
            }
        });
    },

    close(modalId) {
        const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
        if (!modal) return;

        modal.classList.remove('active');
        // Only remove class if no other modals are open
        if (!document.querySelector('.modal.active')) {
            document.body.classList.remove('modal-open');
        }
    },

    closeAll() {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
        document.body.classList.remove('modal-open');
    }
};

window.Modal = Modal;

// Global ESC listener to close modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        Modal.closeAll();
    }
});

