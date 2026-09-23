import { Controller } from '@hotwired/stimulus';

/**
 * Presentation-only navigation controller.
 * Authorization and route access remain server-side.
 */
export default class extends Controller {
    static targets = ['menu', 'toggle'];
    static values = { open: Boolean, openLabel: String, closeLabel: String };

    connect() {
        this.handleKeydown = this.handleKeydown.bind(this);
        this.handleDocumentClick = this.handleDocumentClick.bind(this);
        document.addEventListener('keydown', this.handleKeydown);
        document.addEventListener('click', this.handleDocumentClick);
        this.sync();
    }

    disconnect() {
        document.removeEventListener('keydown', this.handleKeydown);
        document.removeEventListener('click', this.handleDocumentClick);
    }

    toggle(event) {
        event.preventDefault();
        this.openValue = !this.openValue;
    }

    close() {
        if (this.openValue) this.openValue = false;
    }

    openValueChanged() {
        this.sync();
    }

    sync() {
        if (!this.hasMenuTarget || !this.hasToggleTarget) return;

        this.menuTarget.hidden = !this.openValue;
        this.toggleTarget.setAttribute('aria-expanded', this.openValue ? 'true' : 'false');
        this.toggleTarget.setAttribute(
            'aria-label',
            this.openValue ? this.closeLabelValue : this.openLabelValue,
        );

        if (this.openValue) {
            const firstFocusable = this.focusableElements()[0];
            if (firstFocusable) firstFocusable.focus();
            return;
        }

        if (this.element.contains(document.activeElement)) {
            this.toggleTarget.focus();
        }
    }

    handleKeydown(event) {
        if (!this.openValue) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = this.focusableElements();
        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    handleDocumentClick(event) {
        if (!this.openValue || this.element.contains(event.target)) return;
        this.close();
    }

    focusableElements() {
        if (!this.hasMenuTarget) return [];

        return Array.from(
            this.menuTarget.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            ),
        ).filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');
    }
}
