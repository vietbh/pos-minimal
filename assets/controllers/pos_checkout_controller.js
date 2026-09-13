import { Controller } from '@hotwired/stimulus';

const DEFAULT_TIMEOUT_MS = 30_000;
const CART_STORAGE_KEY = 'mobile-pos.cart.v1';
const SEARCH_DEBOUNCE_MS = 180;
const MINOR_SCALE = 100n;

export default class extends Controller {
    static targets = [
        'productSearch', 'productResults', 'customerSearch', 'customerResults',
        'selectedCustomer', 'clearCustomer', 'cart', 'cartEmpty', 'cartCount',
        'cartTotal', 'submitButton', 'message', 'success', 'requestId', 'status',
        'retryButton', 'paymentMethods', 'paymentAmount', 'customerTendered',
        'paymentTotal', 'paymentApplied', 'paymentDue', 'paymentChange',
        'paymentState', 'quickCash', 'note', 'resultTotal', 'resultPaid',
        'resultDebt', 'resultTendered', 'resultChange', 'resultOrder',
        'resultTenderedRow', 'resultChangeRow', 'resultDebtRow', 'saleView',
    ];

    static values = {
        csrfToken: String,
        endpoint: String,
        productSearchUrl: String,
        customerSearchUrl: String,
        timeout: { type: Number, default: DEFAULT_TIMEOUT_MS },
    };

    connect() {
        this.state = 'IDLE';
        this.inFlight = false;
        this.idempotencyKey = null;
        this.cartItems = this.loadCart();
        this.customer = null;
        this.products = new Map();
        this.customers = new Map();
        this.productSearchTimer = null;
        this.customerSearchTimer = null;
        this.productSearchSequence = 0;
        this.customerSearchSequence = 0;

        this.renderCart();
        this.paymentMethodChanged();
        this.setStatus('Ready');
    }

    disconnect() {
        globalThis.clearTimeout(this.productSearchTimer);
        globalThis.clearTimeout(this.customerSearchTimer);
    }

    searchProducts() {
        globalThis.clearTimeout(this.productSearchTimer);
        const query = this.productSearchTarget.value.trim();
        if (query === '') {
            this.productResultsTarget.replaceChildren();
            return;
        }

        this.productSearchTimer = globalThis.setTimeout(
            () => this.fetchProducts(query),
            SEARCH_DEBOUNCE_MS,
        );
    }

    productSearchKeydown(event) {
        if (event.key === 'Enter') event.preventDefault();
    }

    async fetchProducts(query) {
        const sequence = ++this.productSearchSequence;
        try {
            const response = await fetch(
                `${this.productSearchUrlValue}?q=${encodeURIComponent(query)}&limit=20`,
                { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
            );
            const body = await response.json().catch(() => ({}));
            if (sequence !== this.productSearchSequence) return;
            if (!response.ok) return new Error(body.message || 'Unable to search products.');

            this.renderProducts(Array.isArray(body.data) ? body.data : []);
        } catch (error) {
            if (sequence === this.productSearchSequence) {
                this.productResultsTarget.textContent = error.message;
            }
        }
    }

    renderProducts(products) {
        this.products.clear();
        this.productResultsTarget.replaceChildren(...products.map((product) => {
            this.products.set(String(product.id), product);

            const row = document.createElement('article');
            row.className = 'pos-product-result';

            const info = document.createElement('div');
            info.className = 'pos-result-main';

            const name = document.createElement('strong');
            name.textContent = product.name;

            const meta = document.createElement('small');
            meta.textContent = [
                product.sku || 'No SKU',
                `${this.formatMajor(product.sellingPrice)} / ${product.unit || 'unit'}`,
                `Stock ${product.stockQuantity}`,
            ].join(' · ');

            info.append(name, meta);

            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'button primary pos-touch-button';
            add.textContent = 'Add';
            add.disabled = Number(product.stockQuantity) <= 0;
            add.dataset.action = 'click->pos-checkout#addToCart';
            add.dataset.productId = String(product.id);

            row.append(info, add);
            return row;
        }));
    }

    addToCart(event) {
        event.preventDefault();
        const product = this.products.get(String(event.currentTarget.dataset.productId || ''));
        if (!product) return;

        const productId = Number(product.id);
        const existing = this.cartItems.find((item) => item.productId === productId);

        if (existing) {
            existing.quantity += 1;
        } else {
            this.cartItems.push({
                productId,
                name: product.name,
                sku: product.sku,
                unitPrice: product.sellingPrice,
                quantity: 1,
            });
        }

        this.persistCart();
        this.renderCart();
        this.setStatus('Item added.');
        this.productSearchTarget.focus();
    }

    changeQuantity(event) {
        event.preventDefault();
        const productId = Number(event.currentTarget.dataset.productId);
        const delta = Number(event.currentTarget.dataset.delta);
        if (!Number.isInteger(productId) || !Number.isInteger(delta)) return;

        const item = this.cartItems.find((entry) => entry.productId === productId);
        if (!item) return;

        item.quantity += delta;
        if (item.quantity <= 0) {
            this.cartItems = this.cartItems.filter((entry) => entry.productId !== productId);
        }

        this.persistCart();
        this.renderCart();
    }

    removeItem(event) {
        event.preventDefault();
        const productId = Number(event.currentTarget.dataset.productId);
        if (!Number.isInteger(productId)) return;
        this.cartItems = this.cartItems.filter((entry) => entry.productId !== productId);
        this.persistCart();
        this.renderCart();
    }

    clearCart() {
        if (this.cartItems.length === 0) return;
        this.cartItems = [];
        this.persistCart();
        this.renderCart();
        this.setStatus('Sale cleared.');
    }

    renderCart() {
        this.cartTarget.replaceChildren(...this.cartItems.map((item) => {
            const row = document.createElement('article');
            row.className = 'pos-cart-item';

            const main = document.createElement('div');
            main.className = 'pos-cart-main';

            const name = document.createElement('strong');
            name.textContent = item.name || `Product #${item.productId}`;

            const unitPrice = this.parseMajorToMinor(item.unitPrice);
            const lineTotal = unitPrice * BigInt(item.quantity);

            const meta = document.createElement('small');
            meta.textContent = `${this.formatMinor(unitPrice)} each`;

            const line = document.createElement('span');
            line.className = 'pos-line-total';
            line.textContent = this.formatMinor(lineTotal);

            main.append(name, meta);

            const controls = document.createElement('div');
            controls.className = 'pos-cart-controls';

            const minus = this.quantityButton('−', 'Decrease', item, -1);
            const count = document.createElement('strong');
            count.textContent = String(item.quantity);
            count.className = 'pos-quantity';
            const plus = this.quantityButton('+', 'Increase', item, 1);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button pos-remove-button';
            remove.textContent = 'Remove';
            remove.dataset.action = 'click->pos-checkout#removeItem';
            remove.dataset.productId = String(item.productId);
            remove.setAttribute('aria-label', `Remove ${name.textContent}`);

            controls.append(minus, count, plus, line, remove);
            row.append(main, controls);
            return row;
        }));

        const itemCount = this.cartItems.reduce((count, item) => count + item.quantity, 0);
        this.cartEmptyTarget.hidden = itemCount > 0;
        this.cartCountTarget.textContent = String(itemCount);

        const total = this.cartTotalMinor();
        this.cartTotalTarget.textContent = this.formatMinor(total);
        this.updatePaymentState(total);
    }

    quantityButton(label, action, item, delta) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'button pos-quantity-button';
        button.textContent = label;
        button.dataset.action = 'click->pos-checkout#changeQuantity';
        button.dataset.productId = String(item.productId);
        button.dataset.delta = String(delta);
        button.setAttribute('aria-label', `${action} ${item.name || `Product #${item.productId}`}`);
        return button;
    }

    cartTotalMinor() {
        return this.cartItems.reduce(
            (sum, item) => sum + this.parseMajorToMinor(item.unitPrice) * BigInt(item.quantity),
            0n,
        );
    }

    searchCustomers() {
        globalThis.clearTimeout(this.customerSearchTimer);
        const query = this.customerSearchTarget.value.trim();
        if (query === '') {
            this.customerResultsTarget.replaceChildren();
            return;
        }

        this.customerSearchTimer = globalThis.setTimeout(
            () => this.fetchCustomers(query),
            SEARCH_DEBOUNCE_MS,
        );
    }

    async fetchCustomers(query) {
        const sequence = ++this.customerSearchSequence;
        try {
            const response = await fetch(
                `${this.customerSearchUrlValue}?q=${encodeURIComponent(query)}&limit=20`,
                { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
            );
            const body = await response.json().catch(() => ({}));
            if (sequence !== this.customerSearchSequence) return;
            if (!response.ok) return new Error(body.message || 'Unable to search customers.');

            const customers = Array.isArray(body.data) ? body.data : [];
            this.customers = new Map(customers.map((customer) => [String(customer.id), customer]));
            this.customerResultsTarget.replaceChildren(...customers.map((customer) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'pos-customer-result';
                button.dataset.action = 'click->pos-checkout#selectCustomer';
                button.dataset.customerId = String(customer.id);

                const name = document.createElement('strong');
                name.textContent = customer.name;
                const phone = document.createElement('small');
                phone.textContent = customer.phone || '';
                button.append(name, phone);
                return button;
            }));
        } catch (error) {
            if (sequence === this.customerSearchSequence) {
                this.customerResultsTarget.textContent = error.message;
            }
        }
    }

    selectCustomer(event) {
        event.preventDefault();
        const customer = this.customers.get(String(event.currentTarget.dataset.customerId || ''));
        if (!customer) return;

        this.customer = customer;
        this.selectedCustomerTarget.hidden = false;
        this.selectedCustomerTarget.textContent = customer.phone
            ? `${customer.name} · ${customer.phone}`
            : customer.name;
        this.clearCustomerTarget.hidden = false;
        this.customerSearchTarget.value = '';
        this.customerResultsTarget.replaceChildren();
    }

    clearCustomer() {
        this.customer = null;
        this.selectedCustomerTarget.hidden = true;
        this.clearCustomerTarget.hidden = true;
    }

    paymentMethodValue() {
        const selected = this.paymentMethodsTargets.find((input) => input.checked);
        return selected?.value || 'CASH';
    }

    paymentMethodChanged() {
        const isCash = this.paymentMethodValue() === 'CASH';
        this.customerTenderedTarget.closest('label').hidden = !isCash;
        this.paymentAmountTarget.closest('label').hidden = isCash;
        this.quickCashTarget.hidden = !isCash;

        this.updatePaymentState(this.cartTotalMinor());
    }

    customerTenderedChanged() {
        this.updatePaymentState(this.cartTotalMinor());
    }

    paymentAmountChanged() {
        this.updatePaymentState(this.cartTotalMinor());
    }

    updatePaymentState(totalMinor) {
        const isCash = this.paymentMethodValue() === 'CASH';
        const enteredMinor = this.safeParseMinor(
            isCash ? this.customerTenderedTarget.value : this.paymentAmountTarget.value,
        );

        if (this.hasPaymentTotalTarget) this.paymentTotalTarget.textContent = this.formatMinor(totalMinor);

        if (isCash) {
            const applied = enteredMinor > totalMinor ? totalMinor : enteredMinor;
            const due = totalMinor > applied ? totalMinor - applied : 0n;
            const change = enteredMinor > totalMinor ? enteredMinor - totalMinor : 0n;

            this.paymentAppliedTarget.textContent = this.formatMinor(applied);
            this.paymentDueTarget.textContent = this.formatMinor(due);
            this.paymentChangeTarget.textContent = this.formatMinor(change);
            this.paymentStateTarget.textContent = totalMinor === 0n
                ? 'Add products'
                : enteredMinor <= 0n
                    ? 'Enter customer amount'
                    : change > 0n
                        ? 'Change due'
                        : due > 0n
                            ? 'Amount remaining'
                            : 'Ready to complete';
            this.renderQuickCash(totalMinor);
            return;
        }

        this.paymentAppliedTarget.textContent = this.formatMinor(enteredMinor);
        this.paymentDueTarget.textContent = '';
        this.paymentChangeTarget.textContent = '';
        this.paymentStateTarget.textContent = totalMinor === 0n
            ? 'Add products'
            : enteredMinor === totalMinor
                ? 'Ready to complete'
                : 'Enter the exact transfer amount';
        this.quickCashTarget.replaceChildren();
    }

    renderQuickCash(totalMinor) {
        this.quickCashTarget.replaceChildren();
        if (totalMinor <= 0n) return;

        for (const value of this.practicalCashSuggestions(totalMinor)) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button';
            button.textContent = this.formatMinor(value);
            button.dataset.action = 'click->pos-checkout#setCustomerTendered';
            button.dataset.amount = this.formatMinor(value);
            this.quickCashTarget.append(button);
        }
    }

    practicalCashSuggestions(totalMinor) {
        const values = new Set();

        // Keep an exact total when it is a practical cash amount.
        if (totalMinor % 1000000n === 0n) {
            values.add(totalMinor);
        }

        // For non-round totals, suggest the next practical 50k amount.
        const next50k = this.roundUp(totalMinor, 5000000n);
        values.add(next50k);

        // Add practical larger tender amounts. The list is intentionally short
        // and never padded with arbitrary values just to reach five buttons.
        const largerAmounts = [20000000n, 50000000n, 100000000n, 200000000n, 500000000n];
        let addedLargerAmount = false;
        for (const amount of largerAmounts) {
            if (amount <= totalMinor) continue;

            values.add(amount);
            if (addedLargerAmount || amount >= 50000000n) break;
            addedLargerAmount = true;
        }

        return [...values]
            .filter((value) => value >= totalMinor)
            .sort((a, b) => (a < b ? -1 : a > b ? 1 : 0));
    }

    roundUp(value, unit) {
        return ((value + unit - 1n) / unit) * unit;
    }

    setCustomerTendered(event) {
        event.preventDefault();
        this.customerTenderedTarget.value = event.currentTarget.dataset.amount || '';
        this.updatePaymentState(this.cartTotalMinor());
        this.customerTenderedTarget.focus();
    }

    normalizePaymentAmount() {
        try {
            this.paymentAmountTarget.value = this.formatMinor(
                this.parseMajorToMinor(this.paymentAmountTarget.value),
            );
        } catch {
            this.paymentAmountTarget.value = '';
        }
        this.updatePaymentState(this.cartTotalMinor());
    }

    async submit(event) {
        event?.preventDefault();
        if (this.inFlight || this.state === 'SUCCESS') return;

        if (this.cartItems.length === 0) {
            this.handleError({ status: 400, errorCode: 'VALIDATION_ERROR', message: 'Cart must contain at least one item.' });
            return;
        }

        const totalMinor = this.cartTotalMinor();
        const isCash = this.paymentMethodValue() === 'CASH';
        const rawAmount = isCash ? this.customerTenderedTarget.value : this.paymentAmountTarget.value;

        let enteredMinor;
        try {
            enteredMinor = this.parseMajorToMinor(rawAmount);
        } catch {
            this.handleError({ status: 400, errorCode: 'VALIDATION_ERROR', message: 'Payment amount must be a valid amount.' });
            return;
        }

        if (enteredMinor <= 0n) {
            this.handleError({
                status: 400,
                errorCode: 'VALIDATION_ERROR',
                message: isCash ? 'Customer tendered amount must be greater than zero.' : 'Transfer amount must be greater than zero.',
            });
            return;
        }

        if (!isCash && enteredMinor !== totalMinor) {
            this.handleError({
                status: 422,
                errorCode: 'INVALID_PAYMENT',
                message: 'Bank transfer amount must equal the order total.',
            });
            return;
        }

        if (this.idempotencyKey === null) this.idempotencyKey = this.newIdempotencyKey();

        const appliedMinor = isCash && enteredMinor > totalMinor ? totalMinor : enteredMinor;
        const payment = {
            method: this.paymentMethodValue(),
            amount: this.formatMinor(appliedMinor),
            tenderedAmount: isCash ? this.formatMinor(enteredMinor) : null,
        };

        const payload = {
            items: this.cartItems.map((item) => ({ productId: item.productId, quantity: item.quantity })),
            customerId: this.customer?.id ?? null,
            payment,
            note: this.noteTarget.value.trim() || null,
        };

        this.inFlight = true;
        this.setSubmitting(true);
        this.clearMessage();

        const controller = new AbortController();
        const timeoutId = globalThis.setTimeout(() => controller.abort(), this.timeoutValue || DEFAULT_TIMEOUT_MS);

        try {
            const response = await fetch(this.endpointValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
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
                this.handleError({ status: 500, errorCode: 'INTERNAL_ERROR', message: 'Checkout succeeded without a valid result.', requestId: body?.requestId || response.headers.get('X-Request-ID') });
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
        if (this.inFlight || this.state === 'SUCCESS' || this.idempotencyKey === null) return;
        this.submit();
    }

    newSale() {
        this.state = 'IDLE';
        this.clearMessage();
        this.successTarget.hidden = true;
        this.saleViewTarget.hidden = false;
        this.cartItems = [];
        this.persistCart();
        this.customer = null;
        this.customerTenderedTarget.value = '';
        this.paymentAmountTarget.value = '';
        this.noteTarget.value = '';
        this.clearCustomer();
        this.idempotencyKey = null;
        this.inFlight = false;
        this.productSearchTarget.value = '';
        this.productResultsTarget.replaceChildren();
        this.renderCart();
        this.setSubmitting(false);
        this.setStatus('Ready');
        this.productSearchTarget.focus();
    }

    setSubmitting(submitting) {
        this.inFlight = submitting;
        if (submitting) this.state = 'SUBMITTING';
        this.submitButtonTarget.disabled = submitting;
        this.submitButtonTarget.textContent = submitting ? 'Processing…' : 'Complete sale';
        this.retryButtonTarget.disabled = submitting;
    }

    handleSuccess(data, requestId) {
        this.state = 'SUCCESS';
        this.saleViewTarget.hidden = true;

        this.resultOrderTarget.textContent = String(data.orderNumber ?? '');
        this.resultTotalTarget.textContent = String(data.total ?? '0.00');
        this.resultPaidTarget.textContent = String(data.paidAmount ?? '0.00');
        this.resultDebtTarget.textContent = String(data.debtAmount ?? '0.00');
        this.resultTenderedTarget.textContent = String(data.tenderedAmount ?? data.paidAmount ?? '0.00');
        this.resultChangeTarget.textContent = String(data.changeAmount ?? '0.00');
        this.requestIdTarget.textContent = String(requestId ?? '');

        const isCash = this.paymentMethodValue() === 'CASH';
        this.resultTenderedRowTarget.hidden = !isCash;
        this.resultChangeRowTarget.hidden = !isCash;
        this.resultDebtRowTarget.hidden = !this.isPositiveMoney(data.debtAmount);

        this.successTarget.hidden = false;
        this.cartItems = [];
        this.persistCart();
        this.renderCart();
        this.idempotencyKey = null;
        this.retryButtonTarget.hidden = true;
        this.messageTarget.hidden = true;
        this.messageTarget.textContent = '';
        this.setStatus('Sale completed.');
    }

    handleError({ status, errorCode, message, requestId }) {
        this.state = 'ERROR';
        if (requestId) this.requestIdTarget.textContent = String(requestId);
        this.showError(errorCode, message);
        this.retryButtonTarget.hidden = !this.canRetry(status, errorCode);
        this.setStatus('Checkout failed. Your sale was preserved.');
    }

    canRetry(status, errorCode) {
        if (errorCode === 'IDEMPOTENCY_IN_PROGRESS' || errorCode === 'IDEMPOTENCY_CONFLICT') return true;
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
        this.messageTarget.hidden = false;
        this.messageTarget.textContent = `${code}: ${message}`;
        this.messageTarget.focus();
    }

    clearMessage() {
        this.messageTarget.hidden = true;
        this.messageTarget.textContent = '';
    }

    setStatus(text) {
        this.statusTarget.textContent = text;
    }

    safeParseMinor(value) {
        try { return this.parseMajorToMinor(value); } catch { return 0n; }
    }

    parseMajorToMinor(value) {
        const raw = String(value ?? '').trim();
        if (!/^\d+(?:\.\d{1,2})?$/.test(raw)) throw new Error('Invalid money amount.');
        const [whole, fraction = ''] = raw.split('.');
        return BigInt(whole) * MINOR_SCALE + BigInt(fraction.padEnd(2, '0') || '0');
    }

    formatMinor(value) {
        const minor = typeof value === 'bigint' ? value : BigInt(value);
        if (minor < 0n) return '0.00';
        const whole = minor / MINOR_SCALE;
        const fraction = (minor % MINOR_SCALE).toString().padStart(2, '0');
        return `${whole}.${fraction}`;
    }

    formatMajor(value) {
        try { return this.formatMinor(this.parseMajorToMinor(value)); } catch { return '0.00'; }
    }

    isPositiveMoney(value) {
        try { return this.parseMajorToMinor(value) > 0n; } catch { return false; }
    }

    loadCart() {
        try {
            const raw = localStorage.getItem(CART_STORAGE_KEY);
            const parsed = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(parsed)) return [];
            return parsed.filter((item) =>
                Number.isInteger(Number(item.productId)) && Number(item.productId) > 0
                && Number.isInteger(Number(item.quantity)) && Number(item.quantity) > 0
                && /^\d+(?:\.\d{1,2})?$/.test(String(item.unitPrice ?? '')),
            );
        } catch {
            return [];
        }
    }

    persistCart() {
        try { localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(this.cartItems)); } catch { /* optional persistence */ }
    }

    newIdempotencyKey() {
        if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
        return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    newRequestId() {
        if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
        return `pos-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }
}
