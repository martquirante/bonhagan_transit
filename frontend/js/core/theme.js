/**
 * BONHAGAN TRANSIT — Theme Controller (Light / Dark)
 */

const Theme = {
    STORAGE_KEY: 'bonhagan_theme',

    init() {
        const savedTheme = localStorage.getItem(this.STORAGE_KEY);
        if (savedTheme) {
            this.setTheme(savedTheme, false);
        } else {
            // Detect system preference
            const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.setTheme(prefersDark ? 'dark' : 'light', false);
        }

        // Ensure icons are synchronized on DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.updateToggleIcons(this.getCurrentTheme());
            });
        } else {
            this.updateToggleIcons(this.getCurrentTheme());
        }

        // Attach event listener to any theme toggler elements
        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('.theme-toggle-btn, [data-theme-toggle]');
            if (toggle) {
                this.toggle();
            }
        });
    },

    getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    },

    setTheme(theme, save = true) {
        document.documentElement.setAttribute('data-theme', theme);
        if (save) {
            localStorage.setItem(this.STORAGE_KEY, theme);
        }
        this.updateToggleIcons(theme);
    },

    toggle() {
        const nextTheme = this.getCurrentTheme() === 'dark' ? 'light' : 'dark';
        this.setTheme(nextTheme, true);
    },

    updateToggleIcons(theme) {
        document.querySelectorAll('.theme-toggle-btn, [data-theme-toggle]').forEach(btn => {
            if (theme === 'dark') {
                btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"/></svg>`;
                btn.setAttribute('title', 'Switch to Light Mode');
            } else {
                btn.innerHTML = `<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/></svg>`;
                btn.setAttribute('title', 'Switch to Dark Mode');
            }
        });
    }
};

// Immediate execution to prevent flash of wrong theme
Theme.init();

window.Theme = Theme;

