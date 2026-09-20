import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String, csrf: String, requiredMessage: String, failedMessage: String, recordedTemplate: String };
    static targets = ['form', 'submit', 'remaining', 'error', 'success'];

    connect() {
        this.inFlight = false;
        this.idempotencyKey = null;
    }

    async submit(event) {
        event.preventDefault();

        if (this.inFlight) return;

        const amount = new FormData(this.formTarget)
            .get('amount')
            ?.toString()
            .trim();

        if (!amount) {
            this.showError(this.requiredMessageValue);
            return;
        }

        this.inFlight = true;
        this.submitTarget.disabled = true;
        this.errorTarget.hidden = true;
        this.successTarget.hidden = true;

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
                body: JSON.stringify({ amount }),
            });

            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                const code = body.errorCode ? ` [${body.errorCode}]` : '';
                const message = body.message || this.failedMessageValue;
                throw new Error(`${message}${code}`);
            }

            this.remainingTarget.textContent = `${body.data.remainingAmount} đ`;
            this.successTarget.textContent =
                this.recordedTemplateValue.replace('{amount}', body.data.remainingAmount);
            this.successTarget.hidden = false;
            this.formTarget.reset();

            // A successful logical operation gets a fresh key for the next payment.
            this.idempotencyKey = null;

            if (body.data.status === 'PAID') {
                window.setTimeout(() => window.location.reload(), 400);
            }
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
