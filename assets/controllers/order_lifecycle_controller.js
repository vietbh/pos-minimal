import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String, csrf: String, reasonRequiredMessage: String, failedMessage: String };
    static targets = ['reason', 'submit', 'error', 'modal'];

    connect() {
        this.inFlight = false;
        this.idempotencyKey = null;
    }

    open(event) {
        event.preventDefault();
        this.urlValue = event.currentTarget.dataset.url || '';
        this.idempotencyKey = null;
        this.modalTarget.hidden = false;
        this.reasonTarget.focus();
    }

    close(event) {
        if (event) event.preventDefault();
        if (this.inFlight) return;
        this.modalTarget.hidden = true;
        this.errorTarget.hidden = true;
        this.idempotencyKey = null;
    }

    async submit(event) {
        event.preventDefault();

        if (this.inFlight) return;

        const reason = this.reasonTarget.value.trim();
        if (!reason) {
            this.showError(this.reasonRequiredMessageValue);
            return;
        }

        this.inFlight = true;
        this.submitTarget.disabled = true;
        this.errorTarget.hidden = true;

        // Keep the same key across retries of this logical operation.
        if (!this.idempotencyKey) {
            this.idempotencyKey = crypto.randomUUID();
        }

        const requestId = crypto.randomUUID();

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfValue,
                    'Idempotency-Key': this.idempotencyKey,
                    'X-Request-ID': requestId,
                },
                body: JSON.stringify({ reason }),
            });

            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = body.message || this.failedMessageValue;
                throw new Error(message);
            }

            this.idempotencyKey = null;
            window.location.reload();
        } catch (error) {
            this.showError(error instanceof Error ? error.message : this.failedMessageValue);
        } finally {
            this.inFlight = false;
            this.submitTarget.disabled = false;
        }
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }
}
