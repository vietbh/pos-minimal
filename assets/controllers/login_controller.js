import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['password', 'toggle', 'eyeIcon', 'submit', 'spinner', 'submitLabel'];
    static values = {
        submittingLabel: String,
        submitLabel: String,
    };

    connect() {
        this.isSubmitting = false;
    }

    togglePassword() {
        const visible = this.passwordTarget.type === 'text';
        this.passwordTarget.type = visible ? 'password' : 'text';
        this.toggleTarget.setAttribute('aria-pressed', String(!visible));
        this.toggleTarget.setAttribute(
            'aria-label',
            visible ? this.toggleTarget.dataset.showLabel || 'Show password' : this.toggleTarget.dataset.hideLabel || 'Hide password',
        );

        this.eyeIconTarget.innerHTML = visible
            ? '<path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/>'
            : '<path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.6 6.2A10.8 10.8 0 0 1 12 6c6 0 9.5 6 9.5 6a17.5 17.5 0 0 1-3.1 3.9M6.2 6.3C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.2 0 2.3-.2 3.3-.6"/><path stroke-linecap="round" stroke-linejoin="round" d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>';
    }

    submit(event) {
        if (this.isSubmitting) {
            event.preventDefault();
            return;
        }

        if (!this.element.checkValidity()) {
            event.preventDefault();
            this.element.reportValidity();
            return;
        }

        this.isSubmitting = true;
        this.submitTarget.disabled = true;
        this.submitTarget.classList.add('is-loading');
        this.submitTarget.setAttribute('aria-busy', 'true');
        this.submitLabelTarget.textContent = this.submittingLabelValue || 'Signing in…';
    }
}
