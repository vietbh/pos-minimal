import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { url: String, csrf: String, requiredMessage: String, failedMessage: String, recordedTemplate: String };
    static targets = ['form', 'submit', 'remaining', 'paid', 'status', 'error', 'success', 'history', 'historyEmpty'];

    connect() {
        this.inFlight = false;
        this.idempotencyKey = null;
        // Bind the submit handler programmatically as a defensive fallback.
        // This keeps payment working even when a stale Twig cache omits
        // data-action="submit->debt-payment#submit" from the form.
        this.boundSubmit = (event) => this.submit(event);
        if (this.hasFormTarget) {
            this.formTarget.addEventListener('submit', this.boundSubmit);
        }
    }

    disconnect() {
        if (this.hasFormTarget && this.boundSubmit) {
            this.formTarget.removeEventListener('submit', this.boundSubmit);
        }
    }

    async submit(event) {
        event.preventDefault();

        if (this.inFlight) return;

        const amount = new FormData(this.formTarget)
            .get('amount')
            ?.toString()
            .replace(/,/g, '')
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
            if (this.hasPaidTarget && body.data.paidAmount !== undefined) {
                this.paidTarget.textContent = `${body.data.paidAmount} đ`;
            }
            if (this.hasStatusTarget && body.data.status) {
                this.statusTarget.textContent = String(body.data.status).replaceAll('_', ' ');
            }
            if (body.data.status === 'PAID') {
                this.submitTarget.disabled = true;
            }
            if (this.hasHistoryTarget) {
                const empty = this.hasHistoryEmptyTarget ? this.historyEmptyTarget : null;
                if (empty) empty.remove();
                const row = document.createElement('div');
                row.className = 'list-row';
                const label = document.createElement('span');
                const createdAt = body.data.createdAt ? new Date(body.data.createdAt) : new Date();
                label.textContent = `${createdAt.toLocaleDateString('vi-VN')} ${createdAt.toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'})} · ${body.data.username || ''}`;
                const amountNode = document.createElement('strong');
                amountNode.textContent = `${body.data.amount} đ`;
                row.append(label, amountNode);
                this.historyTarget.prepend(row);
            }
            this.successTarget.textContent =
                this.recordedTemplateValue.replace('{amount}', body.data.remainingAmount);
            this.successTarget.hidden = false;
            this.formTarget.reset();

            // A successful logical operation gets a fresh key for the next payment.
            this.idempotencyKey = null;

            // Do not reload here. The payment request is already authoritative;
            // keep the current POS/admin context and let the UI update in place.
        } catch (error) {
            this.showError(error instanceof Error ? error.message : this.failedMessageValue);
        } finally {
            this.inFlight = false;
            this.submitTarget.disabled = false;
        }
    }

    amountChanged(event) {
        const input = event.currentTarget;
        const value = String(input.value || '');
        const caret = Number.isInteger(input.selectionStart) ? input.selectionStart : value.length;
        const digitsBeforeCaret = value.slice(0, caret).replace(/[^0-9]/g, '').length;
        const raw = value.replace(/[^0-9]/g, '');
        if (raw === '') return;
        const normalized = raw.replace(/^0+(?=\d)/, '');
        const formatted = Number(normalized).toLocaleString('en-US');
        input.value = formatted;
        let digits = 0;
        let nextCaret = formatted.length;
        for (let i = 0; i < formatted.length; i += 1) {
            if (/\d/.test(formatted[i])) digits += 1;
            if (digits >= digitsBeforeCaret) { nextCaret = i + 1; break; }
        }
        if (input === document.activeElement && typeof input.setSelectionRange === 'function') {
            input.setSelectionRange(nextCaret, nextCaret);
        }
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }
}
