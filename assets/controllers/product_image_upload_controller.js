import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'input', 'submit', 'status', 'token', 'filename'];
    static values = { chooseRequiredMessage: String, uploadingMessage: String, failedMessage: String, uploadedMessage: String };

    fileChanged() {
        const file = this.inputTarget.files[0];
        if (file && this.hasFilenameTarget) this.filenameTarget.textContent = file.name;
    }

    async submit(event) {
        event.preventDefault();

        const file = this.inputTarget.files[0];
        if (!file) {
            this.statusTarget.textContent = this.chooseRequiredMessageValue;
            return;
        }

        this.submitTarget.disabled = true;
        this.statusTarget.textContent = this.uploadingMessageValue;

        try {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('_token', this.tokenTarget.value);

            const response = await fetch(this.formTarget.action, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || this.failedMessageValue);
            }

            this.statusTarget.textContent = this.uploadedMessageValue;
            this.formTarget.reset();
            if (this.hasFilenameTarget) this.filenameTarget.textContent = this.chooseRequiredMessageValue;
        } catch (error) {
            this.statusTarget.textContent = error.message;
        } finally {
            this.submitTarget.disabled = false;
        }
    }
}
