import { Controller } from '@hotwired/stimulus';

/**
 * UI/UX 3 — Mobile interaction hardening.
 *
 * Presentation/interaction only. This controller deliberately contains no
 * business, checkout, payment, persistence, or authorization logic.
 */
export default class extends Controller {
    connect() {
        this.updateViewportMetrics = this.updateViewportMetrics.bind(this);
        this.handleFocusIn = this.handleFocusIn.bind(this);
        this.handleVisibilityChange = this.handleVisibilityChange.bind(this);

        this.visualViewport = window.visualViewport || null;

        window.addEventListener('resize', this.updateViewportMetrics, { passive: true });
        window.addEventListener('orientationchange', this.updateViewportMetrics, { passive: true });
        document.addEventListener('focusin', this.handleFocusIn);
        document.addEventListener('visibilitychange', this.handleVisibilityChange);

        if (this.visualViewport) {
            this.visualViewport.addEventListener('resize', this.updateViewportMetrics, { passive: true });
            this.visualViewport.addEventListener('scroll', this.updateViewportMetrics, { passive: true });
        }

        this.updateViewportMetrics();
    }

    disconnect() {
        window.removeEventListener('resize', this.updateViewportMetrics);
        window.removeEventListener('orientationchange', this.updateViewportMetrics);
        document.removeEventListener('focusin', this.handleFocusIn);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);

        if (this.visualViewport) {
            this.visualViewport.removeEventListener('resize', this.updateViewportMetrics);
            this.visualViewport.removeEventListener('scroll', this.updateViewportMetrics);
        }
    }

    viewportMetrics() {
        const viewport = this.visualViewport;

        return {
            height: Math.round(viewport?.height || window.innerHeight),
            width: Math.round(viewport?.width || window.innerWidth),
            offsetTop: Math.round(viewport?.offsetTop || 0),
            offsetLeft: Math.round(viewport?.offsetLeft || 0),
        };
    }

    updateViewportMetrics() {
        const { height, width, offsetTop, offsetLeft } = this.viewportMetrics();

        if (!height || !width) {
            return;
        }

        const root = document.documentElement;
        root.style.setProperty('--ui-viewport-height', `${height}px`);
        root.style.setProperty('--ui-viewport-width', `${width}px`);
        root.style.setProperty('--ui-viewport-offset-top', `${offsetTop}px`);
        root.style.setProperty('--ui-viewport-offset-left', `${offsetLeft}px`);

        // A reduction of the visual viewport is a useful keyboard signal on
        // mobile browsers. Keep this as a presentation hint only.
        const keyboardOpen = window.innerHeight - height > 120;
        root.toggleAttribute('data-keyboard-open', keyboardOpen);

        const orientation = width > height ? 'landscape' : 'portrait';
        root.setAttribute('data-ui-orientation', orientation);
    }

    handleFocusIn(event) {
        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (!target.matches('input, textarea, select, [contenteditable="true"]')) {
            return;
        }

        window.setTimeout(() => {
            if (!target.isConnected) {
                return;
            }

            const rect = target.getBoundingClientRect();
            const viewportHeight = this.viewportMetrics().height;
            const margin = 18;

            if (rect.bottom > viewportHeight - margin || rect.top < margin) {
                target.scrollIntoView({
                    block: 'center',
                    inline: 'nearest',
                    behavior: document.documentElement.hasAttribute('data-reduce-motion')
                        ? 'auto'
                        : 'smooth',
                });
            }
        }, 120);
    }

    handleVisibilityChange() {
        if (!document.hidden) {
            window.setTimeout(() => this.updateViewportMetrics(), 0);
        }
    }
}
