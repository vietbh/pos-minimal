import { Controller } from '@hotwired/stimulus';

const DEFAULT_TIMEOUT_MS = 30_000;

export default class extends Controller {
    static targets = [
        'items',
        'customerId',
        'paymentMethod',
        'paymentAmount',
        'note',
        'submitButton',
        'message',
        'success',
        'requestId',
        'status',
        'retryButton',
        'resultTotal',
        'resultPaid',
        'resultDebt',
        'resultOrder',
    ];

    static values = {
        csrfToken: String,
        endpoint: String,
        timeout: { type: Number, default: DEFAULT_TIMEOUT_MS },
    };

    connect() {
        this.state = 'IDLE';
        this.inFlight = false;
        this.idempotencyKey = null;
    }

    async submit(event) {
        event?.preventDefault();

        if (this.inFlight || this.state === 'SUCCESS') {
            return;
        }

        let items;
        try {
            items = JSON.parse(this.itemsTarget.value);
        } catch (_) {
            this.handleError({
                status: 400,
                errorCode: 'VALIDATION_ERROR',
                message: 'Cart items must be valid JSON.',
            });
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            this.handleError({
                status: 400,
                errorCode: 'VALIDATION_ERROR',
                message: 'Cart must contain at least one item.',
            });
            return;
        }

        if (this.idempotencyKey === null) {
            this.idempotencyKey = this.newIdempotencyKey();
        }

        const customerId = this.customerIdTarget.value.trim();
        const payload = {
            items,
            customerId: customerId === '' ? null : Number(customerId),
            payment: {
                method: this.paymentMethodTarget.value,
                amount: this.paymentAmountTarget.value.trim(),
            },
            note: this.noteTarget.value.trim() || null,
        };

        this.inFlight = true;
        this.setSubmitting(true);
        this.clearMessage();

        const controller = new AbortController();
        const timeoutId = globalThis.setTimeout(
            () => controller.abort(),
            this.timeoutValue || DEFAULT_TIMEOUT_MS,
        );

        try {
            const response = await fetch(this.endpointValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue,
                    'Idempotency-Key': this.idempotencyKey,
                    'X-Request-ID': this.newRequestId(),
                },
                body: JSON.stringify(payload),
                credentials: 'same-origin',
                signal: controller.signal,
            });

            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                this.handleError({
                    status: response.status,
                    errorCode: body.errorCode || this.errorCodeForStatus(response.status),
                    message: body.message || 'Unable to complete checkout.',
                    requestId: body.requestId || response.headers.get('X-Request-ID'),
                });
                return;
            }

            if (!body?.data) {
                this.handleError({
                    status: 500,
                    errorCode: 'INTERNAL_ERROR',
                    message: 'Checkout succeeded without a valid result.',
                    requestId: body?.requestId || response.headers.get('X-Request-ID'),
                });
                return;
            }

            this.handleSuccess(body.data, body.requestId || response.headers.get('X-Request-ID'));
        } catch (error) {
            const timedOut = error?.name === 'AbortError';

            this.handleError({
                status: 0,
                errorCode: timedOut ? 'CHECKOUT_TIMEOUT' : 'NETWORK_UNKNOWN',
                message: timedOut
                    ? 'Checkout timed out. The result is unknown. Retry with the same key.'
                    : 'The checkout result is unknown. Retry with the same key.',
                requestId: null,
            });
        } finally {
            globalThis.clearTimeout(timeoutId);
            this.setSubmitting(false);
        }
    }

    retry(event) {
        event?.preventDefault();

        if (this.inFlight || this.state === 'SUCCESS' || this.idempotencyKey === null) {
            return;
        }

        this.submit();
    }

    setSubmitting(submitting) {
        this.inFlight = submitting;
        this.state = submitting ? 'SUBMITTING' : this.state;

        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = submitting;
            this.submitButtonTarget.textContent = submitting ? 'Processing…' : 'Checkout';
        }

        if (this.hasRetryButtonTarget) {
            this.retryButtonTarget.disabled = submitting;
        }

        if (this.hasStatusTarget) {
            this.statusTarget.textContent = submitting ? 'Processing checkout…' : '';
        }
    }

    handleSuccess(data, requestId) {
        this.state = 'SUCCESS';

        if (this.hasResultOrderTarget) this.resultOrderTarget.textContent = String(data.orderNumber ?? '');
        if (this.hasResultTotalTarget) this.resultTotalTarget.textContent = String(data.total ?? '');
        if (this.hasResultPaidTarget) this.resultPaidTarget.textContent = String(data.paidAmount ?? '');
        if (this.hasResultDebtTarget) this.resultDebtTarget.textContent = String(data.debtAmount ?? '');
        if (this.hasRequestIdTarget) this.requestIdTarget.textContent = String(requestId ?? '');

        if (this.hasSuccessTarget) {
            this.successTarget.hidden = false;
        }

        if (this.hasStatusTarget) {
            this.statusTarget.textContent = 'Checkout successful.';
        }

        // Cart is cleared only after the server confirms success.
        this.itemsTarget.value = '';
        this.idempotencyKey = null;

        if (this.hasRetryButtonTarget) {
            this.retryButtonTarget.hidden = true;
        }

        if (this.hasMessageTarget) {
            this.messageTarget.hidden = true;
            this.messageTarget.textContent = '';
        }
    }

    handleError({ status, errorCode, message, requestId }) {
        this.state = 'ERROR';

        if (this.hasRequestIdTarget && requestId) {
            this.requestIdTarget.textContent = String(requestId);
        }

        this.showError(errorCode, message);

        if (this.hasRetryButtonTarget) {
            this.retryButtonTarget.hidden = !this.canRetry(status, errorCode);
        }

        if (this.hasStatusTarget) {
            this.statusTarget.textContent = 'Checkout failed. Your cart was preserved.';
        }
    }

    canRetry(status, errorCode) {
        if (errorCode === 'IDEMPOTENCY_IN_PROGRESS' || errorCode === 'IDEMPOTENCY_CONFLICT') {
            return true;
        }

        return status === 409 || status === 0 || status >= 500;
    }

    errorCodeForStatus(status) {
        switch (status) {
            case 400: return 'VALIDATION_ERROR';
            case 401: return 'AUTHENTICATION_REQUIRED';
            case 403: return 'ACCESS_DENIED';
            case 404: return 'RESOURCE_NOT_FOUND';
            case 409: return 'IDEMPOTENCY_CONFLICT';
            case 422: return 'BUSINESS_RULE_VIOLATION';
            default: return status >= 500 ? 'INTERNAL_ERROR' : 'CHECKOUT_FAILED';
        }
    }

    showError(code, message) {
        if (!this.hasMessageTarget) return;

        this.messageTarget.hidden = false;
        this.messageTarget.textContent = `${code}: ${message}`;

        if (typeof this.messageTarget.focus === 'function') {
            this.messageTarget.focus();
        }
    }

    clearMessage() {
        if (!this.hasMessageTarget) return;

        this.messageTarget.hidden = true;
        this.messageTarget.textContent = '';
    }

    newIdempotencyKey() {
        if (globalThis.crypto?.randomUUID) {
            return globalThis.crypto.randomUUID();
        }

        return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    newRequestId() {
        if (globalThis.crypto?.randomUUID) {
            return globalThis.crypto.randomUUID();
        }

        return `pos-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }
}
