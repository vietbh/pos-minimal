import { Controller } from '@hotwired/stimulus';

const DEFAULT_TIMEOUT_MS = 30_000;
const CART_STORAGE_KEY = 'mobile-pos.cart.v1';
const SEARCH_DEBOUNCE_MS = 180;

export default class extends Controller {
    static targets = [
        'productSearch', 'productResults', 'customerSearch', 'customerResults', 'selectedCustomer', 'clearCustomer',
        'cart', 'cartEmpty', 'cartCount', 'submitButton', 'message', 'success', 'requestId', 'status', 'retryButton',
        'paymentMethod', 'paymentAmount', 'note', 'resultTotal', 'resultPaid', 'resultDebt', 'resultOrder',
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
        this.productSearchTimer = null;
        this.customerSearchTimer = null;
        this.productSearchSequence = 0;
        this.customerSearchSequence = 0;
        this.renderCart();
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
        this.productSearchTimer = globalThis.setTimeout(() => this.fetchProducts(query), SEARCH_DEBOUNCE_MS);
    }

    productSearchKeydown(event) {
        if (event.key === 'Enter') event.preventDefault();
    }

    async fetchProducts(query) {
        const sequence = ++this.productSearchSequence;
        try {
            const response = await fetch(`${this.productSearchUrlValue}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' }, credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));
            if (sequence !== this.productSearchSequence) return;
            if (!response.ok) throw new Error(body.message || 'Unable to search products.');
            this.renderProducts(Array.isArray(body.data) ? body.data : []);
        } catch (error) {
            if (sequence === this.productSearchSequence) this.productResultsTarget.textContent = error.message;
        }
    }

    renderProducts(products) {
        this.productResultsTarget.replaceChildren(...products.map((product) => {
            const row = document.createElement('div');
            row.className = 'pos-result';
            const main = document.createElement('div');
            main.className = 'pos-result-main';
            const name = document.createElement('strong');
            name.textContent = product.name;
            const meta = document.createElement('small');
            meta.textContent = [product.sku || 'No SKU', `${this.formatMinor(product.sellingPrice)} / ${product.unit || 'unit'}`, `Stock ${product.stockQuantity}`].join(' · ');
            main.append(name, meta);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button primary pos-result-action';
            button.textContent = 'Add';
            button.disabled = Number(product.stockQuantity) <= 0;
            button.addEventListener('click', () => this.addToCart(product));
            row.append(main, button);
            return row;
        }));
    }

    addToCart(product) {
        const id = Number(product.id);
        const existing = this.cartItems.find((item) => item.productId === id);
        if (existing) existing.quantity += 1;
        else this.cartItems.push({ productId: id, name: product.name, sku: product.sku, unitPrice: product.sellingPrice, quantity: 1 });
        this.persistCart();
        this.renderCart();
        this.productSearchTarget.focus();
    }

    changeQuantity(productId, delta) {
        const item = this.cartItems.find((entry) => entry.productId === productId);
        if (!item) return;
        item.quantity += delta;
        if (item.quantity <= 0) this.cartItems = this.cartItems.filter((entry) => entry.productId !== productId);
        this.persistCart();
        this.renderCart();
    }

    clearCart() {
        this.cartItems = [];
        this.persistCart();
        this.renderCart();
    }

    renderCart() {
        this.cartTarget.replaceChildren(...this.cartItems.map((item) => {
            const row = document.createElement('div');
            row.className = 'pos-cart-item';
            const main = document.createElement('div');
            main.className = 'pos-cart-main';
            const name = document.createElement('strong');
            name.textContent = item.name || `Product #${item.productId}`;
            const meta = document.createElement('small');
            meta.textContent = `${item.sku || 'No SKU'} · ${this.formatMinor(item.unitPrice)} each`;
            main.append(name, meta);
            const qty = document.createElement('div');
            qty.className = 'pos-qty';
            for (const [label, delta] of [['−', -1], ['+', 1]]) {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'button'; button.textContent = label; button.setAttribute('aria-label', `${label === '+' ? 'Increase' : 'Decrease'} ${name.textContent}`);
                button.addEventListener('click', () => this.changeQuantity(item.productId, delta));
                qty.append(button);
            }
            const count = document.createElement('strong'); count.textContent = String(item.quantity); qty.append(count);
            row.append(main, qty);
            return row;
        }));
        this.cartEmptyTarget.hidden = this.cartItems.length > 0;
        this.cartCountTarget.textContent = String(this.cartItems.reduce((count, item) => count + item.quantity, 0));
    }

    searchCustomers() {
        globalThis.clearTimeout(this.customerSearchTimer);
        const query = this.customerSearchTarget.value.trim();
        if (query === '') { this.customerResultsTarget.replaceChildren(); return; }
        this.customerSearchTimer = globalThis.setTimeout(() => this.fetchCustomers(query), SEARCH_DEBOUNCE_MS);
    }

    async fetchCustomers(query) {
        const sequence = ++this.customerSearchSequence;
        try {
            const response = await fetch(`${this.customerSearchUrlValue}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const body = await response.json().catch(() => ({}));
            if (sequence !== this.customerSearchSequence) return;
            if (!response.ok) throw new Error(body.message || 'Unable to search customers.');
            this.customerResultsTarget.replaceChildren(...(Array.isArray(body.data) ? body.data : []).map((customer) => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'pos-result';
                const text = document.createElement('span'); text.textContent = customer.phone ? `${customer.name} · ${customer.phone}` : customer.name;
                button.append(text); button.addEventListener('click', () => this.selectCustomer(customer));
                return button;
            }));
        } catch (error) {
            if (sequence === this.customerSearchSequence) this.customerResultsTarget.textContent = error.message;
        }
    }

    selectCustomer(customer) {
        this.customer = customer;
        this.selectedCustomerTarget.hidden = false;
        this.selectedCustomerTarget.textContent = customer.phone ? `${customer.name} · ${customer.phone}` : customer.name;
        this.clearCustomerTarget.hidden = false;
        this.customerSearchTarget.value = '';
        this.customerResultsTarget.replaceChildren();
    }

    clearCustomer() {
        this.customer = null;
        this.selectedCustomerTarget.hidden = true;
        this.clearCustomerTarget.hidden = true;
    }

    async submit(event) {
        event?.preventDefault();
        if (this.inFlight || this.state === 'SUCCESS') return;
        if (this.cartItems.length === 0) {
            this.handleError({ status: 400, errorCode: 'VALIDATION_ERROR', message: 'Cart must contain at least one item.' });
            return;
        }
        if (this.idempotencyKey === null) this.idempotencyKey = this.newIdempotencyKey();

        const payload = {
            items: this.cartItems.map((item) => ({ productId: item.productId, quantity: item.quantity })),
            customerId: this.customer?.id ?? null,
            payment: { method: this.paymentMethodTarget.value, amount: this.paymentAmountTarget.value.trim() },
            note: this.noteTarget.value.trim() || null,
        };
        this.inFlight = true; this.setSubmitting(true); this.clearMessage();
        const controller = new AbortController();
        const timeoutId = globalThis.setTimeout(() => controller.abort(), this.timeoutValue || DEFAULT_TIMEOUT_MS);
        try {
            const response = await fetch(this.endpointValue, {
                method: 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.csrfTokenValue,'Idempotency-Key':this.idempotencyKey,'X-Request-ID':this.newRequestId()},
                body: JSON.stringify(payload), credentials: 'same-origin', signal: controller.signal,
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) { this.handleError({status:response.status,errorCode:body.errorCode||this.errorCodeForStatus(response.status),message:body.message||'Unable to complete checkout.',requestId:body.requestId||response.headers.get('X-Request-ID')}); return; }
            if (!body?.data) { this.handleError({status:500,errorCode:'INTERNAL_ERROR',message:'Checkout succeeded without a valid result.',requestId:body?.requestId||response.headers.get('X-Request-ID')}); return; }
            this.handleSuccess(body.data, body.requestId || response.headers.get('X-Request-ID'));
        } catch (error) {
            const timedOut = error?.name === 'AbortError';
            this.handleError({status:0,errorCode:timedOut?'CHECKOUT_TIMEOUT':'NETWORK_UNKNOWN',message:timedOut?'Checkout timed out. The result is unknown. Retry with the same key.':'The checkout result is unknown. Retry with the same key.',requestId:null});
        } finally { globalThis.clearTimeout(timeoutId); this.setSubmitting(false); }
    }

    retry(event) { event?.preventDefault(); if (this.inFlight || this.state === 'SUCCESS' || this.idempotencyKey === null) return; this.submit(); }

    newSale() {
        this.state = 'IDLE'; this.clearMessage(); this.successTarget.hidden = true; this.paymentAmountTarget.value = '0.00'; this.noteTarget.value = ''; this.clearCustomer(); this.idempotencyKey = null; this.inFlight = false; this.productSearchTarget.value = ''; this.productSearchTarget.focus(); this.setSubmitting(false); this.setStatus('Ready');
    }

    setSubmitting(submitting) {
        this.inFlight = submitting; this.state = submitting ? 'SUBMITTING' : this.state;
        this.submitButtonTarget.disabled = submitting; this.submitButtonTarget.textContent = submitting ? 'Processing…' : 'Checkout'; this.retryButtonTarget.disabled = submitting; this.setStatus(submitting ? 'Processing checkout…' : '');
    }

    handleSuccess(data, requestId) {
        this.state = 'SUCCESS';
        this.resultOrderTarget.textContent = String(data.orderNumber ?? ''); this.resultTotalTarget.textContent = String(data.total ?? ''); this.resultPaidTarget.textContent = String(data.paidAmount ?? ''); this.resultDebtTarget.textContent = String(data.debtAmount ?? ''); this.requestIdTarget.textContent = String(requestId ?? '');
        this.successTarget.hidden = false;
        // Cart is cleared only after the server confirms success.
        this.cartItems = []; this.persistCart(); this.renderCart(); this.idempotencyKey = null; this.retryButtonTarget.hidden = true; this.messageTarget.hidden = true; this.messageTarget.textContent = ''; this.setStatus('Checkout successful.');
    }

    handleError({ status, errorCode, message, requestId }) {
        this.state = 'ERROR'; if (requestId) this.requestIdTarget.textContent = String(requestId); this.showError(errorCode, message); this.retryButtonTarget.hidden = !this.canRetry(status, errorCode); this.setStatus('Checkout failed. Your cart was preserved.');
    }

    canRetry(status, errorCode) { if (errorCode === 'IDEMPOTENCY_IN_PROGRESS' || errorCode === 'IDEMPOTENCY_CONFLICT') return true; return status === 409 || status === 0 || status >= 500; }
    errorCodeForStatus(status) { switch (status) { case 400:return 'VALIDATION_ERROR'; case 401:return 'AUTHENTICATION_REQUIRED'; case 403:return 'ACCESS_DENIED'; case 404:return 'RESOURCE_NOT_FOUND'; case 409:return 'IDEMPOTENCY_CONFLICT'; case 422:return 'BUSINESS_RULE_VIOLATION'; default:return status>=500?'INTERNAL_ERROR':'CHECKOUT_FAILED'; } }
    showError(code, message) { this.messageTarget.hidden=false; this.messageTarget.textContent=`${code}: ${message}`; this.messageTarget.focus(); }
    clearMessage() { this.messageTarget.hidden=true; this.messageTarget.textContent=''; }
    setStatus(text) { this.statusTarget.textContent = text; }
    formatMinor(value) { return (Number(value) / 100).toFixed(2); }

    loadCart() { try { const raw = localStorage.getItem(CART_STORAGE_KEY); const parsed = raw ? JSON.parse(raw) : []; return Array.isArray(parsed) ? parsed.filter((item) => Number(item.productId) > 0 && Number(item.quantity) > 0) : []; } catch (_) { return []; } }
    persistCart() { try { localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(this.cartItems)); } catch (_) { /* Storage can be unavailable; checkout still uses in-memory cart. */ } }
    newIdempotencyKey() { if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID(); return `${Date.now()}-${Math.random().toString(16).slice(2)}`; }
    newRequestId() { if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID(); return `pos-${Date.now()}-${Math.random().toString(16).slice(2)}`; }
}
