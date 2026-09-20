import { Controller } from '@hotwired/stimulus';

/**
 * Application navigation interaction only.
 * Authorization remains server-side; this controller only manages presentation.
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
            const firstLink = this.menuTarget.querySelector('a, button');
            if (firstLink) firstLink.focus();
        }
    }

    handleKeydown(event) {
        if (event.key === 'Escape') this.close();
    }

    handleDocumentClick(event) {
        if (!this.openValue) return;
        if (this.element.contains(event.target)) return;
        this.close();
    }
}
