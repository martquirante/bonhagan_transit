/**
 * BONHAGAN TRANSIT — Global Loading Overlay Controller
 */

const Loading = {
    overlay: null,

    init() {
        if (!this.overlay) {
            let el = document.getElementById('global-loading-overlay');
            if (!el) {
                el = document.createElement('div');
                el.id = 'global-loading-overlay';
                el.style.cssText = `
                    position: fixed;
                    top: 0; left: 0; right: 0; bottom: 0;
                    background: rgba(15, 23, 42, 0.6);
                    backdrop-filter: blur(4px);
                    z-index: 9999;
                    display: none;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    color: #FFFFFF;
                `;
                el.innerHTML = `
                    <div style="width: 50px; height: 50px; border: 4px solid rgba(255,255,255,0.2); border-top-color: #F59E0B; border-radius: 50%; animation: spin 0.8s linear infinite;"></div>
                    <p id="global-loading-text" style="margin-top: 1rem; font-weight: 600; font-size: 0.95rem; letter-spacing: 0.02em;">Processing request...</p>
                    <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
                `;
                document.body.appendChild(el);
            }
            this.overlay = el;
        }
    },

    show(message = 'Processing request...') {
        this.init();
        const textEl = document.getElementById('global-loading-text');
        if (textEl) textEl.textContent = message;
        this.overlay.style.display = 'flex';
    },

    hide() {
        if (this.overlay) {
            this.overlay.style.display = 'none';
        }
    }
};

window.Loading = Loading;

