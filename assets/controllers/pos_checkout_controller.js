import { Controller } from '@hotwired/stimulus';

const DEFAULT_TIMEOUT_MS = 30_000;
const CART_STORAGE_KEY = 'mobile-pos.cart.v1';
const SEARCH_DEBOUNCE_MS = 180;
const MINOR_SCALE = 100n;

export default class extends Controller {
    static targets = [
        'productSearch', 'productResults', 'productCategory', 'productCatalogMeta', 'productPagination', 'productPrevious', 'productNext', 'productPageIndicator', 'webhookEnrichment', 'webhookEnrichmentState', 'webhookProvider', 'webhookExternalId', 'webhookOccurredAt', 'webhookAmount', 'webhookDescription', 'customerSearch', 'customerResults',
        'selectedCustomer', 'clearCustomer', 'cart', 'cartEmpty', 'cartCount',
        'cartTotal', 'submitButton', 'message', 'success', 'requestId', 'status',
        'retryButton', 'paymentMethods', 'paymentAmount', 'customerTendered',
        'paymentTotal', 'paymentApplied', 'paymentDue', 'paymentChange',
        'paymentState', 'paymentDueRow', 'clearTendered', 'bankAccount', 'bankDetails', 'bankName', 'bankNumber', 'bankAccountName', 'transferContent', 'bankQr', 'paymentChangeRow', 'quickCash', 'note', 'resultTotal', 'resultPaid',
        'resultDebt', 'resultTendered', 'resultChange', 'resultOrder',
        'resultTenderedRow', 'resultChangeRow', 'resultDebtRow', 'saleView', 'paymentReferenceResult', 'resultPaymentReference', 'resultPaymentReferenceExpiresAt', 'resultPaymentReferenceCountdown', 'regeneratePaymentReferenceButton', 'paymentReferenceHint', 'manualBankConfirmButton', 'resultPaymentReferenceTransferContent', 'resultPaymentReferenceQr', 'bankQrPlaceholder', 'completePaidSaleButton', 'paymentReceivedBanner', 'paymentReceivedBannerAmount', 'paymentReceivedModal', 'paymentReceivedModalAmount', 'paymentReceivedModalReference', 'paymentReceivedCountdown', 'currentTime', 'qrModal', 'qrModalImage', 'qrModalReference', 'qrModalAmount', 'qrModalCountdown', 'qrModalClose',
    ];

    static values = {
        csrfToken: String,
        endpoint: String,
        productSearchUrl: String,
        productCatalogUrl: String,
        customerSearchUrl: String,
        timeout: { type: Number, default: DEFAULT_TIMEOUT_MS },
        paymentReferenceRegenerateBaseUrl: String,
        completeOrderBaseUrl: String,
        manualBankConfirmAvailable: Boolean,
        cartRemoveLabel: String,
        cartIncreaseLabel: String,
        cartDecreaseLabel: String,
        statusItemAdded: String,
        statusSaleCleared: String,
        processingLabel: String,
        completeSaleLabel: String,
        bankPaymentLabel: String,
        messages: Object,
    };

    connect() {
        this.state = 'IDLE';
        this.inFlight = false;
        this.idempotencyKey = null;
        this.cartItems = this.loadCart();
        this.customer = null;
        this.customerTenderedAuto = true;
        this.products = new Map();
        this.customers = new Map();
        this.renderBankAccount();
        this.productSearchTimer = null;
        this.customerSearchTimer = null;
        this.productSearchSequence = 0;
        this.productCatalogSequence = 0;
        this.productCatalogPage = 1;
        this.productCatalogTotalPages = 1;
        this.productCatalogCategory = '';
        this.customerSearchSequence = 0;
        this.paymentReferenceCountdownTimer = null;
        this.paymentReferenceOrderId = null;
        this.paymentSessionId = null;
        this.paymentReferenceExpiresAt = null;
        this.paymentReferenceAmount = null;
        this.paymentStatusTimer = null;
        this.currentTimeTimer = null;
        this.paymentReceivedResetTimer = null;
        this.paymentReceivedCountdownTimer = null;
        this.bankTransferCompletionPolicy = null;
        this.updateCurrentTime();
        this.currentTimeTimer = globalThis.setInterval(() => this.updateCurrentTime(), 1000);

        this.renderCart();
        this.paymentMethodChanged();
        this.loadProductCatalog(1);
        this.setStatus(this.messagesValue.ready);
    }

    disconnect() {
        globalThis.clearTimeout(this.productSearchTimer);
        globalThis.clearTimeout(this.customerSearchTimer);
        this.stopPaymentReferenceCountdown();
        this.stopPaymentStatusPolling();
        this.stopPaymentReceivedCelebration();
        if (this.currentTimeTimer !== null) {
            globalThis.clearInterval(this.currentTimeTimer);
            this.currentTimeTimer = null;
        this.paymentReceivedResetTimer = null;
        this.paymentReceivedCountdownTimer = null;
        }
    }

    searchProducts() {
        globalThis.clearTimeout(this.productSearchTimer);
        this.productSearchTimer = globalThis.setTimeout(
            () => this.loadProductCatalog(1),
            SEARCH_DEBOUNCE_MS,
        );
    }

    productSearchKeydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            globalThis.clearTimeout(this.productSearchTimer);
            this.loadProductCatalog(1);
        }
    }

    productCategoryChanged() {
        this.productCatalogCategory = this.productCategoryTarget.value || '';
        this.loadProductCatalog(1);
    }

    async loadProductCatalog(page = 1) {
        const sequence = ++this.productCatalogSequence;
        const query = this.productSearchTarget.value.trim();
        const category = this.productCategoryTarget.value || '';
        const url = new URL(this.productCatalogUrlValue, window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('page', String(Math.max(1, page)));
        url.searchParams.set('limit', '5');
        if (category) url.searchParams.set('category', category);

        this.productResultsTarget.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));
            if (sequence !== this.productCatalogSequence) return;
            if (!response.ok) throw new Error(body.message || this.messagesValue.unableLoadProducts);

            const products = Array.isArray(body.data) ? body.data : [];
            const pagination = body.pagination || {};
            this.productCatalogPage = Number(pagination.page || 1);
            this.productCatalogTotalPages = Math.max(1, Number(pagination.totalPages || 1));

            this.renderProductCategories(Array.isArray(body.categories) ? body.categories : []);
            this.renderProducts(products);
            this.renderProductPagination(pagination);
        } catch (error) {
            if (sequence === this.productCatalogSequence) {
                this.productResultsTarget.textContent = error?.message || this.messagesValue.unableLoadProducts;
                this.renderProductPagination({ page: 1, totalPages: 1, total: 0 });
            }
        } finally {
            if (sequence === this.productCatalogSequence) {
                this.productResultsTarget.removeAttribute('aria-busy');
            }
        }
    }

    renderProductCategories(categories) {
        const selected = this.productCategoryTarget.value || this.productCatalogCategory || '';
        const options = [new Option(this.messagesValue.allCategories, '')];
        for (const category of categories) {
            if (!category?.id) continue;
            options.push(new Option(String(category.name || `Category #${category.id}`), String(category.id)));
        }
        this.productCategoryTarget.replaceChildren(...options);
        this.productCategoryTarget.value = selected;
        if (this.productCategoryTarget.value !== selected) {
            this.productCategoryTarget.value = '';
            this.productCatalogCategory = '';
        }
    }

    renderProductPagination(pagination) {
        const page = Math.max(1, Number(pagination.page || this.productCatalogPage || 1));
        const totalPages = Math.max(1, Number(pagination.totalPages || this.productCatalogTotalPages || 1));
        const total = Math.max(0, Number(pagination.total || 0));
        this.productCatalogPage = page;
        this.productCatalogTotalPages = totalPages;

        if (this.hasProductCatalogMetaTarget) {
            this.productCatalogMetaTarget.textContent = total === 0
                ? 'Không có sản phẩm phù hợp.'
                : `${total} sản phẩm · Hiển thị 5 sản phẩm mỗi trang`;
        }
        if (!this.hasProductPaginationTarget) return;

        const visible = totalPages > 1;
        this.productPaginationTarget.hidden = !visible;
        this.productPreviousTarget.disabled = page <= 1;
        this.productNextTarget.disabled = page >= totalPages;
        this.productPageIndicatorTarget.textContent = `Trang ${page} / ${totalPages}`;
    }

    previousProductPage() {
        if (this.productCatalogPage > 1) {
            this.loadProductCatalog(this.productCatalogPage - 1);
        }
    }

    nextProductPage() {
        if (this.productCatalogPage < this.productCatalogTotalPages) {
            this.loadProductCatalog(this.productCatalogPage + 1);
        }
    }

    renderProducts(products) {
        this.products.clear();
        if (products.length === 0) {
            this.productResultsTarget.textContent = 'Không tìm thấy sản phẩm.';
            return;
        }

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
                product.categoryName || 'Chưa phân loại',
                product.sku || this.messagesValue.noSku,
                `${this.formatMajor(product.sellingPrice)} / ${product.unit || this.messagesValue.unit}`,
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
        this.setStatus(this.statusItemAddedValue);
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
        this.cartItems = [];
        this.persistCart();

        // Clear all cart-derived payment totals/amounts immediately.
        if (this.hasPaymentTotalTarget) this.paymentTotalTarget.textContent = '0';
        if (this.hasPaymentAmountTarget) this.paymentAmountTarget.value = '';
        if (this.hasCustomerTenderedTarget) {
            this.customerTenderedTarget.value = '';
            this.customerTenderedAuto = true;
        }
        if (this.hasPaymentAppliedTarget) this.paymentAppliedTarget.textContent = '0';
        if (this.hasPaymentDueTarget) this.paymentDueTarget.textContent = '0';
        if (this.hasPaymentChangeTarget) this.paymentChangeTarget.textContent = '0';

        this.renderCart();
        this.setStatus(this.statusSaleClearedValue);
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

            const minus = this.quantityButton('−', this.cartDecreaseLabelValue, item, -1);
            const count = document.createElement('strong');
            count.textContent = String(item.quantity);
            count.className = 'pos-quantity';
            const plus = this.quantityButton('+', this.cartIncreaseLabelValue, item, 1);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'button pos-remove-button';
            remove.textContent = this.cartRemoveLabelValue;
            remove.dataset.action = 'click->pos-checkout#removeItem';
            remove.dataset.productId = String(item.productId);
            remove.setAttribute('aria-label', `${this.cartRemoveLabelValue} ${name.textContent}`);

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
        this.stopPaymentReferenceCountdown();
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
            if (!response.ok) throw new Error(body.message || this.messagesValue.unableSearchCustomers);

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
        this.updatePaymentState(this.cartTotalMinor());
        this.customerResultsTarget.replaceChildren();
    }

    clearCustomer() {
        this.customer = null;
        this.selectedCustomerTarget.hidden = true;
        this.clearCustomerTarget.hidden = true;
        this.updatePaymentState(this.cartTotalMinor());
    }

    paymentMethodValue() {
        const selected = this.paymentMethodsTargets.find((input) => input.checked);
        return selected?.value || 'CASH';
    }

    setElementDisplay(element, visible, display = 'grid') {
        if (!element) return;
        element.style.display = visible ? display : 'none';
        element.hidden = !visible;
    }

    paymentMethodChanged() {
        const isCash = this.paymentMethodValue() === 'CASH';
        this.setElementDisplay(this.customerTenderedTarget.closest('label'), isCash);
        this.setElementDisplay(this.paymentAmountTarget.closest('label'), !isCash);
        this.setElementDisplay(this.quickCashTarget, isCash, 'flex');
        this.setElementDisplay(this.bankAccountTarget.closest('label'), !isCash);

        if (isCash) {
            this.hideBankTransferDetails();
        } else {
            this.renderBankAccount();
        }

        this.updatePaymentState(this.cartTotalMinor());
        this.updateSubmitButtonLabel();
    }

    updateSubmitButtonLabel() {
        if (!this.hasSubmitButtonTarget || this.inFlight) return;
        this.submitButtonTarget.textContent = this.paymentMethodValue() === 'BANK_TRANSFER'
            ? this.messagesValue.startBankPayment
            : this.messagesValue.completeSaleButton;
    }

    bankAccountChanged() {
        if (this.paymentMethodValue() !== 'BANK_TRANSFER') {
            this.hideBankTransferDetails();
            return;
        }
        this.renderBankAccount();
        this.updatePaymentState(this.cartTotalMinor());
    }

    hideBankTransferDetails() {
        if (this.hasBankDetailsTarget) this.setElementDisplay(this.bankDetailsTarget, false);
        if (this.hasBankQrTarget) {
            this.bankQrTarget.style.display = 'none';
            this.bankQrTarget.hidden = true;
            this.bankQrTarget.removeAttribute('src');
        }
        if (this.hasTransferContentTarget) this.transferContentTarget.textContent = '';
        if (this.hasBankNameTarget) this.bankNameTarget.textContent = '';
        if (this.hasBankNumberTarget) this.bankNumberTarget.textContent = '';
        if (this.hasBankAccountNameTarget) this.bankAccountNameTarget.textContent = '';
    }

    renderBankAccount() {
        if (
            this.paymentMethodValue() !== 'BANK_TRANSFER'
            || !this.hasBankAccountTarget
            || this.bankAccountTarget.value === ''
            || this.bankAccountTarget.selectedOptions.length === 0
        ) {
            this.hideBankTransferDetails();
            return;
        }

        const option = this.bankAccountTarget.selectedOptions[0];
        this.setElementDisplay(this.bankDetailsTarget, true);
        this.bankNameTarget.textContent = option.dataset.bankName || '';
        this.bankNumberTarget.textContent = option.dataset.accountNumber || '';
        this.bankAccountNameTarget.textContent = option.dataset.accountName || '';

        this.transferContentTarget.textContent = 'Nội dung chuyển khoản sẽ được tạo khi bắt đầu thanh toán.';
        // QR is authoritative backend data and is rendered after Start payment.
        // Do not manufacture or clear it while the cashier edits the cart.
        if (!this.paymentReference) {
            this.bankQrTarget.style.display = 'none';
            this.bankQrTarget.hidden = true;
            this.bankQrTarget.removeAttribute('src');
        }
    }

    buildBankQr(totalMinor) {
        // The backend creates the payment session/reference/QR. Never derive
        // payment QR data from the client-side cart total.
        if (!this.hasBankQrTarget || this.paymentReference) return;
        this.bankQrTarget.style.display = 'none';
        this.bankQrTarget.hidden = true;
        this.bankQrTarget.removeAttribute('src');
    }

    customerTenderedChanged() {
        const totalMinor = this.cartTotalMinor();
        const enteredMinor = this.safeParseMinor(this.customerTenderedTarget.value);
        this.customerTenderedAuto = this.customerTenderedTarget.value.trim() !== '' && enteredMinor === totalMinor;
        this.updatePaymentState(totalMinor);
    }

    clearCustomerTendered(event) {
        event?.preventDefault();
        this.customerTenderedAuto = false;
        this.customerTenderedTarget.value = '';
        this.updatePaymentState(this.cartTotalMinor());
        this.customerTenderedTarget.focus();
    }

    paymentAmountChanged() {
        this.updatePaymentState(this.cartTotalMinor());
    }

    updatePaymentState(totalMinor) {
        const isCash = this.paymentMethodValue() === 'CASH';
        if (isCash) {
            if (totalMinor > 0n && this.customerTenderedAuto) {
                this.customerTenderedTarget.value = this.formatMinor(totalMinor);
            }
            const currentTendered = this.safeParseMinor(this.customerTenderedTarget.value);
            this.clearTenderedTarget.hidden = totalMinor <= 0n || currentTendered === totalMinor;
        } else {
            // Transfer amount always follows the current cart total.
            // A cart change (add/remove/quantity) must never leave a stale amount.
            this.paymentAmountTarget.value = totalMinor > 0n
                ? this.formatMinor(totalMinor)
                : '';
        }
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

            const canCompleteCash = totalMinor > 0n
                && enteredMinor > 0n
                && (enteredMinor >= totalMinor || this.customer !== null);

            this.paymentDueRowTarget.hidden = due === 0n;
            this.paymentChangeRowTarget.hidden = change === 0n;
            this.paymentStateTarget.textContent = totalMinor === 0n
                ? this.messagesValue.addProducts
                : enteredMinor <= 0n
                    ? this.messagesValue.enterCustomerAmount
                    : change > 0n
                        ? this.messagesValue.changeDue
                        : due > 0n
                            ? this.customer !== null
                                ? this.messagesValue.debtWillBeCreated
                                : this.messagesValue.selectCustomerOrFullPayment
                            : this.messagesValue.paidInFull;
            this.submitButtonTarget.disabled = !canCompleteCash;
            this.renderQuickCash(totalMinor);
            return;
        }

        this.renderBankAccount();
        this.buildBankQr(totalMinor);
        const transferDue = totalMinor > enteredMinor ? totalMinor - enteredMinor : 0n;
        this.paymentAppliedTarget.textContent = this.formatMinor(enteredMinor);
        this.paymentDueTarget.textContent = this.formatMinor(transferDue);
        this.paymentChangeTarget.textContent = this.formatMinor(0n);
        this.paymentDueRowTarget.hidden = transferDue === 0n;
        this.paymentChangeRowTarget.hidden = true;
        const canCompleteTransfer = totalMinor > 0n
            && this.bankAccountTarget.value !== ''
            && enteredMinor === totalMinor;
        this.paymentStateTarget.textContent = totalMinor === 0n
            ? this.messagesValue.addProducts
            : this.bankAccountTarget.value === ''
                ? this.messagesValue.configureReceivingAccount
                : canCompleteTransfer
                    ? this.messagesValue.paidInFull
                    : enteredMinor > totalMinor
                        ? this.messagesValue.transferCannotExceed
                        : this.messagesValue.enterExactTransfer;
        this.submitButtonTarget.disabled = !canCompleteTransfer;
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
        this.customerTenderedAuto = false;
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
            this.handleError({ status: 400, errorCode: 'VALIDATION_ERROR', message: this.messagesValue.cartEmpty });
            return;
        }

        const totalMinor = this.cartTotalMinor();
        const isCash = this.paymentMethodValue() === 'CASH';
        const rawAmount = isCash ? this.customerTenderedTarget.value : this.paymentAmountTarget.value;

        let enteredMinor;
        try {
            enteredMinor = this.parseMajorToMinor(rawAmount);
        } catch {
            this.handleError({ status: 400, errorCode: 'VALIDATION_ERROR', message: this.messagesValue.paymentInvalid });
            return;
        }

        if (enteredMinor <= 0n) {
            this.handleError({
                status: 400,
                errorCode: 'VALIDATION_ERROR',
                message: isCash ? this.messagesValue.customerTenderedZero : this.messagesValue.transferZero,
            });
            return;
        }

        if (!isCash && enteredMinor !== totalMinor) {
            this.handleError({
                status: 422,
                errorCode: 'INVALID_PAYMENT',
                message: this.messagesValue.bankTransferExact,
            });
            return;
        }

        if (this.idempotencyKey === null) this.idempotencyKey = this.newIdempotencyKey();

        const appliedMinor = isCash && enteredMinor > totalMinor ? totalMinor : enteredMinor;

        const payment = {
            method: this.paymentMethodValue(),
            amount: this.formatMinorApi(appliedMinor),
            tenderedAmount: isCash ? this.formatMinorApi(enteredMinor) : null,
            bankAccountId: isCash ? null : Number(this.bankAccountTarget.value),
            // Bank-transfer PaymentReference is generated by the backend
            // together with the persisted payment instruction/QR.
            paymentReference: null,
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
                    message: body.message || this.messagesValue.unableCheckout,
                    requestId: body.requestId || response.headers.get('X-Request-ID'),
                });
                return;
            }

            if (!body?.data) {
                this.handleError({ status: 500, errorCode: 'INTERNAL_ERROR', message: this.messagesValue.invalidResult, requestId: body?.requestId || response.headers.get('X-Request-ID') });
                return;
            }

            this.handleSuccess(body.data, body.requestId || response.headers.get('X-Request-ID'));
        } catch (error) {
            const timedOut = error?.name === 'AbortError';
            this.handleError({
                status: 0,
                errorCode: timedOut ? 'CHECKOUT_TIMEOUT' : 'NETWORK_UNKNOWN',
                message: timedOut
                    ? this.messagesValue.timeoutUnknown
                    : this.messagesValue.unknownResult,
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
        this.stopPaymentReceivedCelebration();
        this.state = 'IDLE';
        this.clearMessage();
        this.successTarget.hidden = true;
        this.saleViewTarget.hidden = false;
        this.cartItems = [];
        this.persistCart();
        this.customer = null;
        this.customerTenderedTarget.value = '';
        this.customerTenderedAuto = true;
        this.paymentAmountTarget.value = '';
        this.noteTarget.value = '';
        this.clearCustomer();
        this.idempotencyKey = null;
        this.paymentReference = null;
        this.paymentReferenceOrderId = null;
        this.paymentSessionId = null;
        this.paymentReferenceExpiresAt = null;
        this.stopPaymentReferenceCountdown();
        this.stopPaymentStatusPolling();
        if (this.hasPaymentReceivedBannerTarget) this.paymentReceivedBannerTarget.hidden = true;
        this.bankTransferCompletionPolicy = null;
        if (this.hasCompletePaidSaleButtonTarget) this.completePaidSaleButtonTarget.hidden = true;
        if (this.hasPaymentReferenceResultTarget) this.paymentReferenceResultTarget.hidden = true;
        this.inFlight = false;
        this.productSearchTarget.value = '';
        this.productResultsTarget.replaceChildren();
        this.renderCart();
        this.setSubmitting(false);
        this.setStatus(this.messagesValue.ready);
        this.productSearchTarget.focus();
    }

    setSubmitting(submitting) {
        this.inFlight = submitting;
        if (submitting) this.state = 'SUBMITTING';
        this.submitButtonTarget.disabled = submitting;
        if (!submitting) this.updatePaymentState(this.cartTotalMinor());
        this.submitButtonTarget.textContent = submitting
            ? this.processingLabelValue
            : (this.paymentMethodValue() === 'BANK_TRANSFER' ? this.bankPaymentLabelValue : this.completeSaleLabelValue);
        this.retryButtonTarget.disabled = submitting;
    }

    handleSuccess(data, requestId) {
        const isBankPending = Boolean(data.paymentReference) && data.status !== 'COMPLETED';
        this.bankTransferCompletionPolicy = data.bankTransferCompletionPolicy || null;

        this.state = isBankPending ? 'PENDING_PAYMENT' : 'SUCCESS';
        this.saleViewTarget.hidden = true;

        this.resultOrderTarget.textContent = String(data.orderNumber ?? '');
        this.renderPaymentReference(data);
        this.resultTotalTarget.textContent = this.formatMajor(data.total ?? '0');
        this.resultPaidTarget.textContent = this.formatMajor(data.paidAmount ?? '0');
        this.resultDebtTarget.textContent = this.formatMajor(data.debtAmount ?? '0');
        this.resultTenderedTarget.textContent = this.formatMajor(data.tenderedAmount ?? data.paidAmount ?? '0');
        this.resultChangeTarget.textContent = this.formatMajor(data.changeAmount ?? '0');
        this.requestIdTarget.textContent = String(requestId ?? '');

        const isCash = this.paymentMethodValue() === 'CASH';
        this.resultTenderedRowTarget.hidden = !isCash;
        this.resultChangeRowTarget.hidden = !isCash;
        this.resultDebtRowTarget.hidden = !this.isPositiveMoney(data.debtAmount);

        this.successTarget.hidden = false;
        this.renderWebhookEnrichment(data.externalTransaction || null, isBankPending);

        if (isBankPending) {
            const isManualCompletion = this.bankTransferCompletionPolicy === 'MANUAL';

            // MANUAL policy has one authoritative cashier action: confirm that
            // the real transfer was independently verified. That endpoint both
            // records the payment and completes the paid order atomically.
            // Do not expose the webhook-recovery completion action for MANUAL
            // policy, because it requires a reconciled webhook and produces the
            // misleading ORDER_COMPLETION_FAILED state shown by the old UI.
            if (isManualCompletion) {
                if (this.hasManualBankConfirmButtonTarget && this.manualBankConfirmAvailableValue) {
                    this.manualBankConfirmButtonTarget.hidden = false;
                    this.manualBankConfirmButtonTarget.disabled = false;
                }
                if (this.hasCompletePaidSaleButtonTarget) {
                    this.completePaidSaleButtonTarget.hidden = true;
                    this.completePaidSaleButtonTarget.disabled = true;
                }
            } else {
                if (this.hasManualBankConfirmButtonTarget) {
                    this.manualBankConfirmButtonTarget.hidden = true;
                    this.manualBankConfirmButtonTarget.disabled = true;
                }
                if (this.hasCompletePaidSaleButtonTarget) {
                    this.completePaidSaleButtonTarget.hidden = false;
                    this.completePaidSaleButtonTarget.disabled = false;
                    this.completePaidSaleButtonTarget.textContent = this.messagesValue.completeSaleManually;
                }
            }
            this.successTarget.querySelector('[data-pos-checkout-target="successEyebrow"]')?.replaceChildren(document.createTextNode(this.messagesValue.bankTransfer));
            this.successTarget.querySelector('[data-pos-checkout-target="successTitle"]')?.replaceChildren(document.createTextNode(this.messagesValue.waitingPayment));
            this.setStatus(this.messagesValue.waitingBankTransfer);
            this.startPaymentStatusPolling();
            this.idempotencyKey = null;
            this.retryButtonTarget.hidden = true;
            this.messageTarget.hidden = true;
            return;
        }

        this.successTarget.querySelector('[data-pos-checkout-target="successEyebrow"]')?.replaceChildren(document.createTextNode(this.messagesValue.completed));
        this.successTarget.querySelector('[data-pos-checkout-target="successTitle"]')?.replaceChildren(document.createTextNode(this.messagesValue.saleCompleted));
        this.cartItems = [];
        this.persistCart();
        this.renderCart();
        this.idempotencyKey = null;
        this.retryButtonTarget.hidden = true;
        this.messageTarget.hidden = true;
        this.messageTarget.textContent = '';
        if (this.hasPaymentReceivedBannerTarget) {
            this.paymentReceivedBannerTarget.hidden = true;
        }
        this.setStatus(this.messagesValue.saleCompleted);
    }

    updateCurrentTime() {
        if (!this.hasCurrentTimeTarget) return;
        const formatted = new Intl.DateTimeFormat('vi-VN', {
            dateStyle: 'short',
            timeStyle: 'medium',
            hour12: false,
        }).format(new Date());
        this.currentTimeTargets.forEach((target) => {
            target.textContent = formatted;
        });
    }

    async manualBankConfirm(event) {
        event?.preventDefault();
        if (this.inFlight || !this.paymentSessionId || !this.hasManualBankConfirmButtonTarget) return;

        const reference = String(this.paymentReference ?? '').trim();
        const amount = this.paymentReferenceAmount ?? '';
        if (!reference || !amount) {
            this.setStatus(this.messagesValue.missingReference);
            return;
        }
        if (!window.confirm('Xác nhận bạn đã kiểm tra giao dịch chuyển khoản thực tế và số tiền đúng với đơn?')) return;

        this.inFlight = true;
        this.manualBankConfirmButtonTarget.disabled = true;
        this.hideAllSuccessActions();
        this.setStatus('Recording manual bank confirmation…');
        try {
            const response = await fetch(`/app/payment-sessions/${this.paymentSessionId}/manual-confirm`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue,
                    'X-Request-ID': this.newRequestId(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ paymentReference: reference, amount: String(amount) }),
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(body.message || this.messagesValue.unableManualConfirm);
            this.paymentReferenceOrderId = Number(body.data?.orderId ?? 0) || null;
            this.renderWebhookEnrichment(body.data?.externalTransaction || null, body.data?.webhookEnrichmentPending !== false);
            this.state = 'SUCCESS';
            this.successTarget.querySelector('[data-pos-checkout-target="successEyebrow"]')?.replaceChildren(document.createTextNode(this.messagesValue.completed));
            this.successTarget.querySelector('[data-pos-checkout-target="successTitle"]')?.replaceChildren(document.createTextNode(this.messagesValue.saleCompleted));
            this.resultOrderTarget.textContent = String(body.data?.orderNumber ?? '');
            this.resultPaidTarget.textContent = this.formatMajor(body.data?.paidAmount ?? amount);
            this.resultDebtTarget.textContent = this.formatMajor(body.data?.debtAmount ?? '0');
            this.hideAllSuccessActions();
            this.cartItems = [];
            this.persistCart();
            this.renderCart();
            this.setStatus('Payment received. Đang chờ webhook bổ sung thông tin giao dịch.');
            this.startPaymentStatusPolling();
            this.showPaymentReceivedCelebration({
                paidAmount: body.data?.paidAmount ?? amount,
                paymentReference: this.paymentReference,
            });
        } catch (error) {
            this.manualBankConfirmButtonTarget.hidden = false;
            this.manualBankConfirmButtonTarget.disabled = false;
            this.setStatus(error?.message || this.messagesValue.manualConfirmFailed);
        } finally {
            this.inFlight = false;
        }
    }

    async completePaidSale(event) {
        event?.preventDefault();
        if (this.inFlight || !this.hasCompletePaidSaleButtonTarget) return;

        this.inFlight = true;
        this.state = 'COMPLETING_SALE';
        // During authoritative completion hide every success-screen action.
        // If completion fails, the catch block restores only the manual recovery action.
        this.hideAllSuccessActions();
        if (this.hasManualBankConfirmButtonTarget) this.manualBankConfirmButtonTarget.hidden = true;
        this.clearMessage();
        this.setStatus('Checking payment confirmation…');

        try {
            // Recovery path: if the browser missed the webhook update, re-read
            // the authoritative payment-session status before attempting sale completion.
            if (!this.paymentReferenceOrderId && this.paymentSessionId) {
                const statusResponse = await fetch(
                    `/app/payment-sessions/${this.paymentSessionId}/status`,
                    { headers: { Accept: 'application/json' }, credentials: 'same-origin' },
                );
                const statusBody = await statusResponse.json().catch(() => ({}));
                if (statusResponse.ok && statusBody?.data) {
                    if (statusBody.data.orderId) {
                        this.paymentReferenceOrderId = Number(statusBody.data.orderId);
                    }
                    if (statusBody.data.orderNumber) {
                        this.resultOrderTarget.textContent = String(statusBody.data.orderNumber);
                    }
                    if (statusBody.data.paidAmount !== undefined) {
                        this.resultPaidTarget.textContent = this.formatMajor(statusBody.data.paidAmount);
                    }
                    if (statusBody.data.debtAmount !== undefined) {
                        this.resultDebtTarget.textContent = this.formatMajor(statusBody.data.debtAmount);
                    }
                    this.renderWebhookEnrichment(statusBody.data.externalTransaction || null, !statusBody.data.externalTransaction);
                    if (statusBody.data.status !== 'PAID' && statusBody.data.paymentReceived !== true) {
                        throw new Error(this.messagesValue.paymentNotConfirmed);
                    }
                }
            }

            if (!this.paymentReferenceOrderId) {
                throw new Error(this.messagesValue.paymentNotConfirmed);
            }

            // Manual recovery is intentionally independent of QR/reference
            // expiry. Expiry only blocks reuse of the old reference for a new
            // transfer; it must never block completion of an already reconciled
            // payment.
            const endpoint = this.completeOrderBaseUrlValue.replace(/\/0\/complete$/, `/${this.paymentReferenceOrderId}/complete`);
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue,
                    'X-Request-ID': this.newRequestId(),
                },
                credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(body.message || this.messagesValue.unableCompleteSale);
            }

            this.stopPaymentStatusPolling();
            this.state = 'SUCCESS';
            this.successTarget.querySelector('[data-pos-checkout-target="successEyebrow"]')?.replaceChildren(document.createTextNode(this.messagesValue.completed));
            this.successTarget.querySelector('[data-pos-checkout-target="successTitle"]')?.replaceChildren(document.createTextNode(this.messagesValue.saleCompleted));
            this.resultPaidTarget.textContent = this.formatMajor(body.data?.paidAmount ?? '0');
            this.resultDebtTarget.textContent = this.formatMajor(body.data?.debtAmount ?? '0');
            this.resultTenderedRowTarget.hidden = true;
            this.resultChangeRowTarget.hidden = true;
            this.hideAllSuccessActions();
            this.cartItems = [];
            this.persistCart();
            this.renderCart();
            this.setStatus(this.messagesValue.paymentReceived);
            this.successTarget.querySelector('[data-pos-checkout-target="successEyebrow"]')?.replaceChildren(document.createTextNode(this.messagesValue.paymentReceived));
            this.successTarget.querySelector('[data-pos-checkout-target="successTitle"]')?.replaceChildren(document.createTextNode(this.messagesValue.paymentReceived));
            this.showPaymentReceivedCelebration({
                paidAmount: body.data?.paidAmount ?? this.resultPaidTarget.textContent,
                paymentReference: this.paymentReference,
            });
        } catch (error) {
            this.state = 'PAYMENT_RECEIVED';
            // MANUAL policy must recover through the explicit cashier
            // confirmation endpoint; AUTO policy may recover through the
            // webhook-reconciliation completion endpoint.
            if (this.bankTransferCompletionPolicy === 'MANUAL') {
                if (this.hasManualBankConfirmButtonTarget && this.manualBankConfirmAvailableValue) {
                    this.manualBankConfirmButtonTarget.hidden = false;
                    this.manualBankConfirmButtonTarget.disabled = false;
                }
                if (this.hasCompletePaidSaleButtonTarget) {
                    this.completePaidSaleButtonTarget.hidden = true;
                    this.completePaidSaleButtonTarget.disabled = true;
                }
            } else if (this.hasCompletePaidSaleButtonTarget) {
                this.completePaidSaleButtonTarget.hidden = false;
                this.completePaidSaleButtonTarget.disabled = false;
                this.completePaidSaleButtonTarget.textContent = this.messagesValue.completeSaleManually;
            }
            if (this.hasNewSaleButtonTarget) {
                this.newSaleButtonTarget.hidden = true;
                this.newSaleButtonTarget.disabled = true;
            }
            this.setStatus(this.messagesValue.paymentReceivedAutoFail);
            this.handleError({
                status: 422,
                errorCode: 'ORDER_COMPLETION_FAILED',
                message: error?.message || this.messagesValue.unableCompleteSaleAuto,
            });
        } finally {
            this.inFlight = false;
        }
    }

    showPaymentReceivedCelebration(data = {}) {
        if (!this.hasPaymentReceivedModalTarget) return;

        this.stopPaymentReceivedCelebration();
        const amount = data.paidAmount ?? '0';
        const reference = String(data.paymentReference ?? this.paymentReference ?? '').trim();
        if (this.hasPaymentReceivedModalAmountTarget) {
            this.paymentReceivedModalAmountTarget.textContent = this.formatMajor(amount);
        }
        if (this.hasPaymentReceivedModalReferenceTarget) {
            this.paymentReceivedModalReferenceTarget.textContent = reference;
        }
        if (this.hasPaymentReceivedCountdownTarget) {
            this.paymentReceivedCountdownTarget.textContent = '5';
        }

        this.paymentReceivedModalTarget.hidden = false;
        this.paymentReceivedModalTarget.setAttribute('aria-hidden', 'false');

        let remaining = 5;
        this.paymentReceivedCountdownTimer = globalThis.setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) return;
            if (this.hasPaymentReceivedCountdownTarget) {
                this.paymentReceivedCountdownTarget.textContent = String(remaining);
            }
        }, 1000);

        this.paymentReceivedResetTimer = globalThis.setTimeout(() => {
            this.newSale();
        }, 5000);

        const newSaleButton = this.paymentReceivedModalTarget.querySelector('[data-action*="pos-checkout#newSale"]');
        newSaleButton?.focus();
    }

    stopPaymentReceivedCelebration() {
        if (this.paymentReceivedResetTimer !== null) {
            globalThis.clearTimeout(this.paymentReceivedResetTimer);
            this.paymentReceivedResetTimer = null;
        }
        if (this.paymentReceivedCountdownTimer !== null) {
            globalThis.clearInterval(this.paymentReceivedCountdownTimer);
            this.paymentReceivedCountdownTimer = null;
        }
        if (this.hasPaymentReceivedModalTarget) {
            this.paymentReceivedModalTarget.hidden = true;
            this.paymentReceivedModalTarget.setAttribute('aria-hidden', 'true');
        }
    }

    hideSuccessActions() {
        if (this.hasCompletePaidSaleButtonTarget) {
            this.completePaidSaleButtonTarget.hidden = true;
            this.completePaidSaleButtonTarget.disabled = true;
        }
        if (this.hasRetryButtonTarget) this.retryButtonTarget.hidden = true;
        if (this.hasRegeneratePaymentReferenceButtonTarget) {
            this.regeneratePaymentReferenceButtonTarget.hidden = true;
            this.regeneratePaymentReferenceButtonTarget.disabled = true;
        }
    }

    hideAllSuccessActions() {
        this.hideSuccessActions();
        if (this.hasNewSaleButtonTarget) {
            this.newSaleButtonTarget.hidden = true;
            this.newSaleButtonTarget.disabled = true;
        }
        if (this.hasResultPaymentReferenceQrButtonTarget) {
            this.resultPaymentReferenceQrButtonTarget.hidden = true;
            this.resultPaymentReferenceQrButtonTarget.disabled = true;
        }
    }

    startPaymentStatusPolling() {
        this.stopPaymentStatusPolling();
        if (!this.paymentSessionId && !this.paymentReferenceOrderId) return;

        this.paymentStatusTimer = globalThis.setInterval(async () => {
            try {
                const endpoint = this.paymentSessionId
                    ? `/app/payment-sessions/${this.paymentSessionId}/status`
                    : `/app/orders/${this.paymentReferenceOrderId}/payment-status`;
                const response = await fetch(endpoint, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const body = await response.json().catch(() => ({}));
                if (!response.ok || !body?.data) return;

                if (body.data.orderId) this.paymentReferenceOrderId = Number(body.data.orderId);
                if (body.data.orderNumber) this.resultOrderTarget.textContent = String(body.data.orderNumber);
                if (body.data.externalTransaction) {
                    this.renderWebhookEnrichment(body.data.externalTransaction, false);
                    if (this.hasPaymentReferenceHintTarget) {
                        this.paymentReferenceHintTarget.textContent = 'Webhook đã về và đã cập nhật thông tin giao dịch vào đơn.';
                    }
                    if (this.state === 'SUCCESS' || this.state === 'PAYMENT_RECEIVED') {
                        this.stopPaymentStatusPolling();
                    }
                }

                if (body.data.status === 'PAID' || body.data.paymentReceived === true) {
                    if (body.data.orderId) this.paymentReferenceOrderId = Number(body.data.orderId);
                    this.state = 'PAYMENT_RECEIVED';
                    this.showPaymentReceivedNotification(body.data);
                    this.successTarget.querySelector('[data-pos-checkout-target="successEyebrow"]')?.replaceChildren(document.createTextNode(this.messagesValue.paymentReceived));
                    this.successTarget.querySelector('[data-pos-checkout-target="successTitle"]')?.replaceChildren(document.createTextNode(this.messagesValue.paymentReceived));
                    this.resultPaidTarget.textContent = this.formatMajor(body.data.paidAmount ?? '0');
                    this.resultDebtTarget.textContent = this.formatMajor(body.data.debtAmount ?? '0');
                    this.paymentReferenceHintTarget.textContent = 'Bank transfer received. Completing sale automatically…';
                    this.paymentReferenceResultTarget.hidden = false;

                    // Once the webhook is confirmed, there must be no competing
                    // cashier actions while the authoritative completion runs.
                    this.hideAllSuccessActions();
                    if (this.hasManualBankConfirmButtonTarget) this.manualBankConfirmButtonTarget.hidden = true;
                    this.setStatus('Payment received. Completing sale…');
                    this.stopPaymentStatusPolling();
                    void this.completePaidSale();
                }
            } catch {
                // Polling is best-effort; backend remains authoritative.
            }
        }, 2000);
    }

    showPaymentReceivedNotification(data = {}) {
        if (!this.hasPaymentReceivedBannerTarget) return;
        const amount = data.paidAmount ?? data.amount ?? '0';
        if (this.hasPaymentReceivedBannerAmountTarget) {
            this.paymentReceivedBannerAmountTarget.textContent = this.formatMajor(amount);
        }
        this.paymentReceivedBannerTarget.hidden = false;
        this.paymentReceivedBannerTarget.setAttribute('role', 'alert');
        this.paymentReceivedBannerTarget.setAttribute('aria-live', 'assertive');
    }

    stopPaymentStatusPolling() {
        if (this.paymentStatusTimer !== null) {
            globalThis.clearInterval(this.paymentStatusTimer);
            this.paymentStatusTimer = null;
        }
    }

    renderWebhookEnrichment(transaction = null, pending = false) {
        if (!this.hasWebhookEnrichmentTarget) return;

        if (!transaction) {
            this.webhookEnrichmentTarget.hidden = !pending;
            if (pending) {
                this.webhookEnrichmentStateTarget.textContent = 'Đang chờ webhook bổ sung thông tin giao dịch.';
                this.webhookProviderTarget.textContent = '—';
                this.webhookExternalIdTarget.textContent = '—';
                this.webhookOccurredAtTarget.textContent = '—';
                this.webhookAmountTarget.textContent = '—';
                this.webhookDescriptionTarget.textContent = 'Đơn đã được xác nhận thủ công. Khi webhook thực tế gửi về, thông tin giao dịch sẽ được gắn vào chính đơn này.';
            }
            return;
        }

        this.webhookEnrichmentTarget.hidden = false;
        this.webhookEnrichmentStateTarget.textContent = 'Đã nhận webhook · đã cập nhật đơn';
        this.webhookProviderTarget.textContent = String(transaction.provider || '—');
        this.webhookExternalIdTarget.textContent = String(transaction.externalTransactionId || '—');
        this.webhookOccurredAtTarget.textContent = transaction.occurredAt
            ? new Date(transaction.occurredAt).toLocaleString('vi-VN')
            : '—';
        this.webhookAmountTarget.textContent = this.formatMajor(transaction.amount ?? '0');
        this.webhookDescriptionTarget.textContent = String(transaction.description || '');
    }

    renderPaymentReference(data) {
        this.stopPaymentReferenceCountdown();
        this.paymentReferenceOrderId = data.orderId ? Number(data.orderId) : null;
        this.paymentSessionId = data.paymentSessionId ? Number(data.paymentSessionId) : (this.paymentSessionId ?? null);
        this.paymentReferenceExpiresAt = data.paymentReferenceExpiresAt ? new Date(data.paymentReferenceExpiresAt) : null;
        this.paymentReferenceAmount = String(data.total ?? data.amount ?? '');

        const reference = String(data.paymentReference ?? '');
        if (!reference || !this.paymentReferenceExpiresAt || Number.isNaN(this.paymentReferenceExpiresAt.getTime())) {
            this.paymentReferenceResultTarget.hidden = true;
            return;
        }

        this.paymentReferenceResultTarget.hidden = false;
        this.paymentReference = reference;
        this.resultPaymentReferenceTarget.textContent = reference;
        this.resultPaymentReferenceExpiresAtTarget.textContent = this.paymentReferenceExpiresAt.toLocaleString('vi-VN');
        this.resultPaymentReferenceTransferContentTarget.textContent = String(data.paymentReferenceTransferContent ?? data.transferContent ?? '');
        if (data.paymentReferenceQrUrl || data.qrUrl) {
            const qrUrl = String(data.paymentReferenceQrUrl ?? data.qrUrl);
            this.resultPaymentReferenceQrTarget.src = qrUrl;
            this.resultPaymentReferenceQrTarget.hidden = false;
            if (this.hasBankQrTarget) {
                this.bankQrTarget.src = qrUrl;
                this.bankQrTarget.hidden = false;
                this.bankQrTarget.style.display = '';
            }
            if (this.hasBankQrPlaceholderTarget) this.bankQrPlaceholderTarget.hidden = true;
        } else {
            this.resultPaymentReferenceQrTarget.hidden = true;
            this.resultPaymentReferenceQrTarget.removeAttribute('src');
            if (this.hasBankQrTarget) {
                this.bankQrTarget.hidden = true;
                this.bankQrTarget.style.display = 'none';
                this.bankQrTarget.removeAttribute('src');
            }
        }
        this.updatePaymentReferenceCountdown();
        this.paymentReferenceCountdownTimer = globalThis.setInterval(() => this.updatePaymentReferenceCountdown(), 1000);
        if (this.hasQrModalImageTarget) {
            this.qrModalImageTarget.src = String(data.paymentReferenceQrUrl ?? data.qrUrl ?? '');
            this.qrModalReferenceTarget.textContent = reference;
            this.qrModalAmountTarget.textContent = this.formatMajor(data.amount ?? '0');
        }
    }

    openQrModal(event) {
        event?.preventDefault();
        if (!this.hasQrModalTarget || !this.hasResultPaymentReferenceQrTarget || this.resultPaymentReferenceQrTarget.hidden) return;
        this.qrModalTarget.hidden = false;
        document.body.classList.add('pos-qr-modal-open');
        this.qrModalCloseTarget?.focus();
    }

    closeQrModal(event) {
        event?.preventDefault();
        if (!this.hasQrModalTarget) return;
        this.qrModalTarget.hidden = true;
        document.body.classList.remove('pos-qr-modal-open');
    }

    handleQrModalKeydown(event) {
        if (event.key === 'Escape') this.closeQrModal(event);
    }

    updatePaymentReferenceCountdown() {
        if (!this.paymentReferenceExpiresAt) return;
        const remaining = Math.max(0, this.paymentReferenceExpiresAt.getTime() - Date.now());
        const totalSeconds = Math.floor(remaining / 1000);
        const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
        const seconds = (totalSeconds % 60).toString().padStart(2, '0');
        this.resultPaymentReferenceCountdownTarget.textContent = `${minutes}:${seconds}`;
        if (this.hasQrModalCountdownTarget) this.qrModalCountdownTarget.textContent = `${minutes}:${seconds}`;

        const expired = remaining <= 0;
        this.regeneratePaymentReferenceButtonTarget.disabled = !expired || this.inFlight;
        if (expired) {
            this.resultPaymentReferenceCountdownTarget.textContent = 'Đã hết hạn';
            if (this.hasQrModalCountdownTarget) this.qrModalCountdownTarget.textContent = 'Đã hết hạn';
            this.paymentReferenceHintTarget.textContent = 'Mã đã hết hạn. Bạn có thể tạo mã mới.';
            this.stopPaymentReferenceCountdown();
        }
    }

    stopPaymentReferenceCountdown() {
        if (this.paymentReferenceCountdownTimer !== null) {
            globalThis.clearInterval(this.paymentReferenceCountdownTimer);
            this.paymentReferenceCountdownTimer = null;
        }
    }

    async regeneratePaymentReference(event) {
        event?.preventDefault();
        if (
            this.inFlight
            || (!this.paymentSessionId && !this.paymentReferenceOrderId)
            || !this.paymentReferenceExpiresAt
            || Date.now() < this.paymentReferenceExpiresAt.getTime()
        ) return;

        this.regeneratePaymentReferenceButtonTarget.disabled = true;
        this.clearMessage();

        const endpoint = this.paymentSessionId
            ? `/app/payment-sessions/${this.paymentSessionId}/payment-reference/regenerate`
            : this.paymentReferenceRegenerateBaseUrlValue.replace(/\/0\/payment-reference\/regenerate$/, `/${this.paymentReferenceOrderId}/payment-reference/regenerate`);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue,
                    'X-Request-ID': this.newRequestId(),
                },
                credentials: 'same-origin',
            });
            const body = await response.json().catch(() => ({}));
            if (!response.ok || !body?.data) {
                throw new Error(body.message || 'Không thể tạo mã thanh toán mới.');
            }

            this.renderPaymentReference({
                orderId: body.data.orderId ?? this.paymentReferenceOrderId,
                paymentSessionId: body.data.sessionId ?? this.paymentSessionId,
                paymentReference: body.data.reference,
                paymentReferenceExpiresAt: body.data.expiresAt,
                paymentReferenceTransferContent: body.data.transferContent,
                paymentReferenceQrUrl: body.data.qrUrl,
                bankName: body.data.bankName,
                accountNumber: body.data.accountNumber,
                accountName: body.data.accountName,
                amount: body.data.amount,
            });
            this.setStatus('Đã tạo mã chuyển khoản mới.');
        } catch (error) {
            this.showError('PAYMENT_REFERENCE_INVALID', error?.message || this.messagesValue.unableCreateReference);
            this.regeneratePaymentReferenceButtonTarget.disabled = true;
        }
    }

    handleError({ status, errorCode, message, requestId }) {
        this.state = 'ERROR';
        if (requestId) this.requestIdTarget.textContent = String(requestId);
        this.showError(errorCode, message);
        this.retryButtonTarget.hidden = !this.canRetry(status, errorCode);
        this.setStatus(this.messagesValue.checkoutFailedPreserved);
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
        const raw = String(value ?? '').trim().replace(/,/g, '');
        if (!/^\d+(?:\.\d{1,2})?$/.test(raw)) throw new Error(this.messagesValue.invalidMoney);
        const [whole, fraction = ''] = raw.split('.');
        return BigInt(whole) * MINOR_SCALE + BigInt(fraction.padEnd(2, '0') || '0');
    }

    formatMinor(value) {
        const minor = typeof value === 'bigint' ? value : BigInt(value);
        if (minor < 0n) return '0';
        const whole = minor / MINOR_SCALE;
        return whole.toLocaleString('en-US');
    }

    formatMajor(value) {
        try { return this.formatMinor(this.parseMajorToMinor(value)); } catch { return '0'; }
    }

    formatMinorApi(value) {
        const minor = typeof value === 'bigint' ? value : BigInt(value);
        if (minor < 0n) return '0.00';
        const whole = minor / MINOR_SCALE;
        const fraction = (minor % MINOR_SCALE).toString().padStart(2, '0');
        return `${whole}.${fraction}`;
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
