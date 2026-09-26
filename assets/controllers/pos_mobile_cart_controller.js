import { Controller } from '@hotwired/stimulus';

/**
 * Mobile POS cart presentation only.
 *
 * This controller does not own cart state, pricing, checkout, payment or
 * persistence. It only opens/closes the existing cart as a bottom sheet and
 * provides a thumb-friendly path to the existing payment section.
 */
export default class extends Controller {
    static targets = ['cart', 'toggle', 'backdrop', 'payButton', 'paymentCard', 'paymentBackdrop', 'paymentClose'];

    connect() {
        this.debugEnabled = new URLSearchParams(window.location.search).get('posDebug') === '1'
            || window.localStorage.getItem('pos-debug') === '1';
        this.debug('connect', { element: this.element?.className });
        this.opened = false;
        this.paymentOpened = false;
        this.handleKeydown = this.handleKeydown.bind(this);
        this.handleHeaderKeydown = this.handleHeaderKeydown.bind(this);
        this.handleCheckoutComplete = this.handleCheckoutComplete.bind(this);
        document.addEventListener('keydown', this.handleKeydown);
        const header = this.element.querySelector('.pos-cart-header');
        if (header) header.addEventListener('keydown', this.handleHeaderKeydown);
        this.element.addEventListener('pos:checkout-complete', this.handleCheckoutComplete);
        this.setOpenState(false);
    }

    disconnect() {
        document.removeEventListener('keydown', this.handleKeydown);
        const header = this.element.querySelector('.pos-cart-header');
        if (header) header.removeEventListener('keydown', this.handleHeaderKeydown);
        this.element.removeEventListener('pos:checkout-complete', this.handleCheckoutComplete);
        document.documentElement.classList.remove('pos-cart-sheet-open', 'pos-payment-sheet-open');
        this.paymentOpened = false;
    }

    headerToggle(event) {
        // The collapsed mobile cart is intentionally tappable across its whole
        // surface. Keep native controls inside the header working normally.
        if (event?.target?.closest?.('button, a, input, select, textarea')) return;
        this.toggle();
    }

    handleHeaderKeydown(event) {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        this.headerToggle(event);
    }

    toggle() {
        this.debug('cart:toggle', { from: this.opened, to: !this.opened });
        this.setOpenState(!this.opened);
    }

    open() {
        this.setOpenState(true);
    }

    close() {
        this.setOpenState(false);
    }

    setOpenState(open) {
        this.opened = Boolean(open);

        // The controller lives on .pos-page; the actual bottom sheet is the
        // cart Stimulus target. The open class must be applied to that target.
        const cart = this.hasCartTarget ? this.cartTarget : null;
        if (cart) {
            cart.classList.toggle('pos-cart-is-open', this.opened);

            // A bottom sheet is a fresh surface each time it opens. Do not
            // restore a stale scroll position from the previous interaction.
            // This keeps the cart header and first line item aligned instead
            // of reopening halfway through the list.
            if (this.opened) {
                cart.scrollTop = 0;
                window.requestAnimationFrame(() => {
                    if (this.opened && cart.isConnected) cart.scrollTop = 0;
                });
            }
        }

        this.debug('cart:state', {
            open: this.opened,
            controllerClassName: this.element.className,
            cartClassName: cart?.className || null,
            cartHeight: cart?.getBoundingClientRect().height || 0,
        });
        document.documentElement.classList.toggle('pos-cart-sheet-open', this.opened);

        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', this.opened ? 'true' : 'false');
            this.toggleTarget.textContent = this.opened
                ? this.toggleTarget.dataset.closeLabel || 'Đóng'
                : this.toggleTarget.dataset.openLabel || 'Xem giỏ';
        }

        if (this.hasBackdropTarget) {
            this.backdropTarget.setAttribute('aria-hidden', this.opened ? 'false' : 'true');
            this.backdropTarget.tabIndex = this.opened ? 0 : -1;
        }
    }

    focusPayment() {
        this.debug('payment:open-request', { hasPaymentCardTarget: this.hasPaymentCardTarget, cartOpen: this.opened });
        if (!this.hasPaymentCardTarget) return;
        this.close();
        this.openPayment();
    }

    openPayment() {
        this.debug('payment:open', { target: this.paymentCardTarget?.className });
        if (!this.hasPaymentCardTarget) return;
        this.paymentOpened = true;
        if (this.hasCartTarget) {
            this.cartTarget.classList.remove('pos-cart-is-open');
        }
        this.paymentCardTarget.setAttribute('aria-hidden', 'false');
        this.element.classList.add('pos-payment-sheet-open');
        document.documentElement.classList.add('pos-payment-sheet-open');
        this.debug('payment:state', {
            open: true,
            pageClassName: this.element.className,
            paymentClassName: this.paymentCardTarget.className,
            paymentHeight: this.paymentCardTarget.getBoundingClientRect().height,
        });
        if (this.hasPaymentBackdropTarget) {
            this.paymentBackdropTarget.setAttribute('aria-hidden', 'false');
            this.paymentBackdropTarget.tabIndex = 0;
        }
        window.setTimeout(() => {
            if (this.hasPaymentCloseTarget) this.paymentCloseTarget.focus();
        }, 80);
    }

    handleCheckoutComplete() {
        // Checkout success/new-sale resets cart state in pos-checkout.
        // Presentation must also leave the cart/payment sheets closed so the
        // next sale starts from the catalog instead of an empty open sheet.
        this.debug('checkout:complete-close-sheets');
        this.opened = false;
        this.paymentOpened = false;
        if (this.hasCartTarget) this.cartTarget.classList.remove('pos-cart-is-open');
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', 'false');
            this.toggleTarget.textContent = this.toggleTarget.dataset.openLabel || 'Xem giỏ';
        }
        if (this.hasBackdropTarget) {
            this.backdropTarget.setAttribute('aria-hidden', 'true');
            this.backdropTarget.tabIndex = -1;
        }
        this.closePayment();
        document.documentElement.classList.remove('pos-cart-sheet-open', 'pos-payment-sheet-open');
    }

    closePayment() {
        this.paymentOpened = false;
        this.element.classList.remove('pos-payment-sheet-open');
        document.documentElement.classList.remove('pos-payment-sheet-open');
        if (this.hasPaymentCardTarget) this.paymentCardTarget.setAttribute('aria-hidden', 'true');
        if (this.hasPaymentBackdropTarget) {
            this.paymentBackdropTarget.setAttribute('aria-hidden', 'true');
            this.paymentBackdropTarget.tabIndex = -1;
        }
    }

    debug(event, payload = {}) {
        if (!this.debugEnabled) return;
        console.groupCollapsed(`[POS][mobile-cart][${event}]`);
        console.log(payload);
        console.groupEnd();
    }

    handleKeydown(event) {
        if (event.key !== 'Escape') return;
        if (this.paymentOpened) {
            this.closePayment();
            return;
        }
        if (this.opened) this.close();
    }
}
