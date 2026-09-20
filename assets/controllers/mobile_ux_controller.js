import { Controller } from '@hotwired/stimulus';

/**
 * Mobile UX hardening only.
 *
 * This controller deliberately contains no business/payment logic. It only
 * reacts to viewport/keyboard changes and keeps focused controls visible.
 */
export default class extends Controller {
    connect() {
        this.updateViewportHeight = this.updateViewportHeight.bind(this);
        this.handleFocusIn = this.handleFocusIn.bind(this);
        this.handleVisibilityChange = this.handleVisibilityChange.bind(this);

        this.visualViewport = window.visualViewport || null;
        this.lastViewportHeight = this.currentViewportHeight();

        window.addEventListener('resize', this.updateViewportHeight, { passive: true });
        window.addEventListener('orientationchange', this.updateViewportHeight, { passive: true });
        document.addEventListener('focusin', this.handleFocusIn);
        document.addEventListener('visibilitychange', this.handleVisibilityChange);

        if (this.visualViewport) {
            this.visualViewport.addEventListener('resize', this.updateViewportHeight, { passive: true });
            this.visualViewport.addEventListener('scroll', this.updateViewportHeight, { passive: true });
        }

        this.updateViewportHeight();
    }

    disconnect() {
        window.removeEventListener('resize', this.updateViewportHeight);
        window.removeEventListener('orientationchange', this.updateViewportHeight);
        document.removeEventListener('focusin', this.handleFocusIn);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);

        if (this.visualViewport) {
            this.visualViewport.removeEventListener('resize', this.updateViewportHeight);
            this.visualViewport.removeEventListener('scroll', this.updateViewportHeight);
        }
    }

    currentViewportHeight() {
        return Math.round(this.visualViewport?.height || window.innerHeight);
    }

    updateViewportHeight() {
        const height = this.currentViewportHeight();
        if (!height) return;

        document.documentElement.style.setProperty('--ui-viewport-height', `${height}px`);

        // A large reduction from the layout viewport is a practical keyboard
        // signal on mobile browsers. Do not hide application controls based on
        // this alone; the CSS only uses it for safe visual adjustments.
        const keyboardOpen = window.innerHeight - height > 120;
        document.documentElement.toggleAttribute('data-keyboard-open', keyboardOpen);

        this.lastViewportHeight = height;
    }

    handleFocusIn(event) {
        const target = event.target;
        if (!(target instanceof HTMLElement)) return;
        if (!target.matches('input, textarea, select, [contenteditable="true"]')) return;

        window.setTimeout(() => {
            if (!target.isConnected) return;

            const rect = target.getBoundingClientRect();
            const viewportHeight = this.currentViewportHeight();
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
            window.setTimeout(() => this.updateViewportHeight(), 0);
        }
    }
}
