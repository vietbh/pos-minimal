import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['current', 'new', 'confirm', 'currentToggle', 'newToggle', 'confirmToggle', 'submit'];

    connect() { this.submitting = false; }

    submit(event) {
        if (this.submitting) { event.preventDefault(); return; }
        if (this.newTarget.value !== this.confirmTarget.value) {
            event.preventDefault();
            this.confirmTarget.setCustomValidity('Mật khẩu xác nhận không khớp.');
            this.confirmTarget.reportValidity();
            return;
        }
        this.confirmTarget.setCustomValidity('');
        if (!this.element.checkValidity()) {
            event.preventDefault();
            this.element.reportValidity();
            return;
        }
        this.submitting = true;
        this.submitTarget.disabled = true;
        this.submitTarget.setAttribute('aria-busy', 'true');
    }

    toggle(input, button) {
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        button.setAttribute('aria-pressed', String(!visible));
        button.setAttribute('aria-label', visible ? (button.dataset.showLabel || 'Show password') : (button.dataset.hideLabel || 'Hide password'));
    }
    toggleCurrent() { this.toggle(this.currentTarget, this.currentToggleTarget); }
    toggleNew() { this.toggle(this.newTarget, this.newToggleTarget); }
    toggleConfirm() { this.toggle(this.confirmTarget, this.confirmToggleTarget); }
}
