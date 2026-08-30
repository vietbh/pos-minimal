import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['form', 'input', 'submit', 'status', 'token'];

    async submit(event) {
        event.preventDefault();

        const file = this.inputTarget.files[0];
        if (!file) {
            this.statusTarget.textContent = 'Please choose an image.';
            return;
        }

        this.submitTarget.disabled = true;
        this.statusTarget.textContent = 'Uploading…';

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
                throw new Error(payload.message || 'Upload failed.');
            }

            this.statusTarget.textContent = 'Image uploaded. Processing will finish in the background.';
            this.formTarget.reset();
        } catch (error) {
            this.statusTarget.textContent = error.message;
        } finally {
            this.submitTarget.disabled = false;
        }
    }
}
