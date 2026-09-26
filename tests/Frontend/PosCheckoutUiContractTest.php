<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class PosCheckoutUiContractTest extends TestCase
{
    private string $controllerSource;
    private string $templateSource;
    private string $compiledControllerSource;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);

        $this->controllerSource = file_get_contents(
            $root . '/assets/controllers/pos_checkout_controller.js'
        );

        $this->templateSource = file_get_contents(
            $root . '/templates/pos/index.html.twig'
        );

        $manifest = json_decode(
            (string) file_get_contents($root . '/public/assets/manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $compiledPath = $manifest['controllers/pos_checkout_controller.js'] ?? null;
        self::assertIsString($compiledPath);
        $this->compiledControllerSource = file_get_contents($root . '/public' . $compiledPath);

        self::assertNotFalse($this->controllerSource);
        self::assertNotFalse($this->templateSource);
        self::assertNotFalse($this->compiledControllerSource);
    }


    public function testCustomerDiscountIsVisibleAndAppliedToCurrentCart(): void
    {
        foreach ([
            'defaultDiscountPercent: Number(data.customer.defaultDiscountPercent || 0)',
            'Giảm ${discount}%',
            'Giảm ${customerDiscount}%',
            'this.renderCart();',
            'cartCustomerDiscount',
        ] as $required) {
            self::assertStringContainsString($required, $this->controllerSource);
        }

        foreach ([
            'data-pos-checkout-target="cartCustomerDiscount"',
            'data-pos-checkout-target="selectedCustomer"',
        ] as $required) {
            self::assertStringContainsString($required, $this->templateSource);
        }
    }

    public function testCartStorageIsIsolatedPerSalesPoint(): void
    {
        self::assertStringContainsString("mobile-pos.cart.v2.sales-point.", $this->controllerSource);
        self::assertStringContainsString('this.salesPointId = this.readCurrentSalesPointId();', $this->controllerSource);
        self::assertStringContainsString('this.cartStorageKey = this.buildCartStorageKey(this.salesPointId);', $this->controllerSource);
        self::assertStringContainsString('localStorage.getItem(this.cartStorageKey)', $this->controllerSource);
        self::assertStringContainsString('localStorage.setItem(this.cartStorageKey', $this->controllerSource);
    }

    public function testIdempotencyKeyLifecycleSeparatesServerFailuresFromInFlightRetry(): void
    {
        self::assertStringContainsString("if (errorCode !== 'IDEMPOTENCY_IN_PROGRESS')", $this->controllerSource);
        self::assertStringContainsString('this.idempotencyKey = null;', $this->controllerSource);
        self::assertStringContainsString("'Idempotency-Key': this.idempotencyKey", $this->controllerSource);
        self::assertStringContainsString("if (this.inFlight || this.state === 'SUCCESS') return;", $this->controllerSource);
        self::assertStringContainsString('// submit() creates a fresh key when the previous server-side attempt', $this->controllerSource);
    }

    public function testPhase5InteractionLabelsAreProvidedByTranslationValues(): void
    {
        foreach ([
            'cartRemoveLabel: String',
            'cartIncreaseLabel: String',
            'cartDecreaseLabel: String',
            'statusItemAdded: String',
            'statusSaleCleared: String',
            'processingLabel: String',
            'completeSaleLabel: String',
            'bankPaymentLabel: String',
            'this.cartRemoveLabelValue',
            'this.cartIncreaseLabelValue',
            'this.cartDecreaseLabelValue',
            'this.processingLabelValue',
            'this.completeSaleLabelValue',
            'this.bankPaymentLabelValue',
        ] as $required) {
            self::assertStringContainsString($required, $this->controllerSource);
        }

        foreach ([
            'data-pos-checkout-cart-remove-label-value',
            'data-pos-checkout-cart-increase-label-value',
            'data-pos-checkout-cart-decrease-label-value',
            'data-pos-checkout-processing-label-value',
            'data-pos-checkout-complete-sale-label-value',
            'data-pos-checkout-bank-payment-label-value',
        ] as $required) {
            self::assertStringContainsString($required, $this->templateSource);
        }
    }

    public function testNewSaleResetsAndReloadsDefaultFiveProductCatalog(): void
    {
        self::assertStringContainsString("this.productSearchTarget.value = '';", $this->controllerSource);
        self::assertStringContainsString("this.productCatalogCategory = '';", $this->controllerSource);
        self::assertStringContainsString("this.productCategoryTarget.value = '';", $this->controllerSource);
        self::assertStringContainsString('void this.loadProductCatalog(1);', $this->controllerSource);
        self::assertStringContainsString("url.searchParams.set('limit', '5');", $this->controllerSource);
        self::assertStringContainsString('this.productResultsTarget.replaceChildren();', $this->controllerSource);
    }

    public function testProductInformationWrapsInsteadOfBeingEllipsizedOnNarrowScreens(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/assets/styles/phase-uiux-7.css');
        self::assertNotFalse($css);
        self::assertStringContainsString('white-space: normal;', $css);
        self::assertStringContainsString('overflow-wrap: anywhere;', $css);
        self::assertStringContainsString('grid-template-columns: 1fr;', $css);
    }

    public function testDoubleSubmitIsBlockedWhileRequestIsInFlight(): void
    {
        self::assertStringContainsString('if (this.inFlight || this.state === \'SUCCESS\')', $this->controllerSource);
        self::assertStringContainsString('this.inFlight = true;', $this->controllerSource);
        self::assertStringContainsString('this.inFlight = submitting;', $this->controllerSource);
        self::assertStringContainsString('this.submitButtonTarget.disabled = submitting;', $this->controllerSource);
    }

    public function testNetworkAndTimeoutPreserveCartAndOfferRetry(): void
    {
        self::assertStringContainsString("errorCode: timedOut ? 'CHECKOUT_TIMEOUT' : 'NETWORK_UNKNOWN'", $this->controllerSource);
        self::assertStringContainsString("status === 0 || status >= 500", $this->controllerSource);
        self::assertStringContainsString('this.cartItems = [];', $this->controllerSource);
        self::assertStringContainsString('this.persistCart();', $this->controllerSource);
    }

    public function testStableErrorCodeAndRequestIdAreHandled(): void
    {
        self::assertStringContainsString('body.errorCode || this.errorCodeForStatus(response.status)', $this->controllerSource);
        self::assertStringContainsString('body.requestId || response.headers.get(\'X-Request-ID\')', $this->controllerSource);
        self::assertStringContainsString('this.requestIdTarget.textContent', $this->controllerSource);
    }

    public function testCheckoutUsesRequiredHeaders(): void
    {
        foreach ([
            "'Content-Type': 'application/json'",
            "'Accept': 'application/json'",
            "'X-CSRF-TOKEN': this.csrfTokenValue",
            "'Idempotency-Key': this.idempotencyKey",
            "'X-Request-ID': this.newRequestId()",
        ] as $header) {
            self::assertStringContainsString($header, $this->controllerSource);
        }
    }

    public function testAccessibleErrorAndResultTargetsExist(): void
    {
        self::assertStringContainsString('role="alert"', $this->templateSource);
        self::assertStringContainsString('aria-live="assertive"', $this->templateSource);
        self::assertStringContainsString('tabindex="-1"', $this->templateSource);
        self::assertStringContainsString('data-pos-checkout-target="retryButton"', $this->templateSource);
        self::assertStringContainsString('data-pos-checkout-target="requestId"', $this->templateSource);
    }

    public function testCashTenderedAndChangeAreRepresentedInTheUiContract(): void
    {
        self::assertStringContainsString('customerTendered', $this->controllerSource);
        self::assertStringContainsString('tenderedAmount', $this->controllerSource);
        self::assertStringContainsString('changeAmount', $this->controllerSource);
        self::assertStringContainsString('data-pos-checkout-target="customerTendered"', $this->templateSource);
        self::assertStringContainsString('data-pos-checkout-target="paymentChange"', $this->templateSource);
    }

    public function testQuickQuantityInputAndDebtCustomerGateAreRepresentedInTheUiContract(): void
    {
        self::assertStringContainsString('quantityChanged', $this->controllerSource);
        self::assertStringContainsString("data-action = 'input->pos-checkout#quantityChanged change->pos-checkout#quantityChanged'", $this->controllerSource);
        self::assertStringContainsString('const canCompleteCash = totalMinor > 0n', $this->controllerSource);
        self::assertStringContainsString('this.customer !== null', $this->controllerSource);
        self::assertStringContainsString('this.messagesValue.selectCustomerOrFullPayment', $this->controllerSource);
        self::assertStringContainsString('this.messagesValue.debtWillBeCreated', $this->controllerSource);
        self::assertStringContainsString('quantity_exceeds_stock', $this->templateSource);
    }

    public function testMoneyUsesMinorUnitsWithoutFloatingPointArithmetic(): void
    {
        self::assertStringContainsString('parseMajorToMinor', $this->controllerSource);
        self::assertStringContainsString('BigInt', $this->controllerSource);
        self::assertStringNotContainsString('Number(item.unitPrice) * item.quantity', $this->controllerSource);
    }

    public function testCashTenderSuggestionsUsePracticalAmountsAtOrAboveTotal(): void
    {
        self::assertStringContainsString('practicalCashSuggestions', $this->controllerSource);
        self::assertStringContainsString('totalMinor % 1000000n === 0n', $this->controllerSource);
        self::assertStringContainsString('this.roundUp(totalMinor, 5000000n)', $this->controllerSource);
        self::assertStringContainsString('20000000n, 50000000n, 100000000n', $this->controllerSource);
        self::assertStringContainsString('value >= totalMinor', $this->controllerSource);
        self::assertStringContainsString('sort((a, b) => (a < b ? -1 : a > b ? 1 : 0))', $this->controllerSource);
        self::assertStringContainsString('data-pos-checkout-target="quickCash"', $this->templateSource);
        self::assertStringContainsString('data-pos-checkout-target="paymentDueRow"', $this->templateSource);
        self::assertStringContainsString('data-pos-checkout-target="paymentChangeRow"', $this->templateSource);
    }

    public function testCashAndBankTransferHaveDistinctUiSemantics(): void
    {
        self::assertStringContainsString("value=\"CASH\"", $this->templateSource);
        self::assertStringContainsString("value=\"BANK_TRANSFER\"", $this->templateSource);
        self::assertStringContainsString('Customer gives', $this->templateSource);
        self::assertStringContainsString('Transfer amount', $this->templateSource);
        self::assertStringContainsString("tenderedAmount: isCash ?", $this->controllerSource);
        self::assertStringContainsString('data-cash-only', $this->templateSource);
        self::assertStringContainsString('data-transfer-only', $this->templateSource);
        self::assertStringContainsString('Transfer content: <strong data-pos-checkout-target="transferContent"></strong>', $this->templateSource);
        self::assertStringNotContainsString('<label hidden data-transfer-only>Transfer content', $this->templateSource);
        self::assertStringContainsString("Bank transfer amount must equal the order total.", $this->controllerSource);
        self::assertStringContainsString("this.paymentMethodsTargets", $this->controllerSource);
    }

    public function testPaymentStateControlsCompletionButton(): void
    {
        self::assertStringContainsString('const canCompleteCash = totalMinor > 0n', $this->controllerSource);
        self::assertStringContainsString('(enteredMinor >= totalMinor || this.customer !== null)', $this->controllerSource);
        self::assertStringContainsString('this.submitButtonTarget.disabled = !canCompleteCash;', $this->controllerSource);
        self::assertStringContainsString('const canCompleteTransfer = totalMinor > 0n && enteredMinor === totalMinor;', $this->controllerSource);
        self::assertStringContainsString('this.submitButtonTarget.disabled = !canCompleteTransfer;', $this->controllerSource);
    }


    public function testBankTransferCompletionPolicySeparatesPaymentReceiptFromSaleCompletion(): void
    {
        self::assertStringContainsString('bankTransferCompletionPolicy', $this->controllerSource);
        self::assertStringContainsString("this.bankTransferCompletionPolicy === 'MANUAL'", $this->controllerSource);
        self::assertStringContainsString('completePaidSale', $this->controllerSource);
        self::assertStringContainsString('data-pos-checkout-target="completePaidSaleButton"', $this->templateSource);
        self::assertStringContainsString("body.data.paymentReceived === true", $this->controllerSource);
        self::assertStringContainsString('paymentSessionId', $this->controllerSource);
        self::assertStringContainsString('/app/payment-sessions/', $this->controllerSource);
    }


    public function testManualCompletionPolicyUsesCashierConfirmationInsteadOfWebhookCompletion(): void
    {
        self::assertStringContainsString("const isManualCompletion = this.bankTransferCompletionPolicy === 'MANUAL';", $this->controllerSource);
        self::assertStringContainsString('this.manualBankConfirmButtonTarget.hidden = false;', $this->controllerSource);
        self::assertStringContainsString('this.completePaidSaleButtonTarget.hidden = true;', $this->controllerSource);
        self::assertStringContainsString("/manual-confirm", $this->controllerSource);
        self::assertStringContainsString("this.state = 'SUCCESS';", $this->controllerSource);
        self::assertStringContainsString('this.cartItems = [];', $this->controllerSource);
    }


    public function testManualBankConfirmationIsExplicitAndPermissionGated(): void
    {
        self::assertStringContainsString('manualBankConfirm', $this->controllerSource);
        self::assertStringContainsString('/manual-confirm', $this->controllerSource);
        self::assertStringContainsString('X-CSRF-TOKEN', $this->controllerSource);
        self::assertStringContainsString('window.confirm(', $this->controllerSource);
        self::assertStringContainsString('PAYMENT_BANK_MANUAL_CONFIRM', file_get_contents(__DIR__ . '/../../src/Application/Security/Permission.php'));
        self::assertStringContainsString('data-pos-checkout-target="manualBankConfirmButton"', $this->templateSource);
    }


    public function testBankWebhookReceiptAutoCompletesAndHidesActions(): void
    {
        self::assertStringContainsString('this.hideAllSuccessActions();', $this->controllerSource);
        self::assertStringContainsString('void this.completePaidSale();', $this->controllerSource);
        self::assertStringContainsString("this.setStatus('Payment received. Completing sale…');", $this->controllerSource);
        self::assertStringContainsString("this.completePaidSaleButtonTarget.textContent = 'Complete sale manually';", $this->controllerSource);
        self::assertStringContainsString('data-pos-checkout-target="newSaleButton"', $this->templateSource);
        self::assertStringContainsString('data-pos-checkout-target="resultPaymentReferenceQrButton"', $this->templateSource);
        self::assertStringContainsString('this.newSaleButtonTarget.hidden = true;', $this->controllerSource);
    }


    public function testQrModalLivesInsideStimulusController(): void
    {
        $controllerStart = strpos($this->templateSource, '<div class="pos-page"');
        $modalStart = strpos($this->templateSource, 'data-pos-checkout-target="qrModal"');
        $controllerEnd = strrpos($this->templateSource, '</div>');

        self::assertNotFalse($controllerStart);
        self::assertNotFalse($modalStart);
        self::assertGreaterThan($controllerStart, $modalStart);
        self::assertSame(1, substr_count($this->templateSource, 'data-pos-checkout-target="qrModal"'));
        self::assertStringContainsString('this.qrModalTarget.hidden = false;', $this->controllerSource);
        self::assertStringContainsString('click->pos-checkout#openQrModal', $this->templateSource);
    }

    public function testCompiledPosControllerMatchesPhase227Contract(): void
    {
        foreach ([
            'completePaidSale',
            'bankTransferCompletionPolicy',
            'currentTime',
            'paymentSessionId',
            'qrModal',
            'openQrModal',
            'closeQrModal',
            "body.data.paymentReceived === true",
        ] as $required) {
            self::assertStringContainsString($required, $this->compiledControllerSource);
        }
    }

    public function testPosShowsLiveCurrentTime(): void
    {
        self::assertStringContainsString('currentTime', $this->controllerSource);
        self::assertStringContainsString('setInterval(() => this.updateCurrentTime(), 1000)', $this->controllerSource);
        self::assertStringContainsString('data-pos-checkout-target="currentTime"', $this->templateSource);
    }

    public function testFrontendDoesNotBecomeBusinessAuthority(): void
    {
        foreach ([
            'calculateServerTotal',
            'validateStock',
            'calculateDebt',
            'applyDiscountRule',
            'decidePaymentValidity',
        ] as $forbiddenFunction) {
            self::assertStringNotContainsString($forbiddenFunction, $this->controllerSource);
        }
    }

    public function testSuccessRendersServerResultBeforeClearingCart(): void
    {
        $successPosition = strpos($this->controllerSource, 'handleSuccess(data, requestId)');
        $clearPosition = strpos($this->controllerSource, 'this.cartItems = [];', $successPosition);

        self::assertNotFalse($successPosition);
        self::assertNotFalse($clearPosition);
        self::assertStringContainsString('data.orderNumber', $this->controllerSource);
        self::assertStringContainsString('data.total', $this->controllerSource);
        self::assertStringContainsString('data.paidAmount', $this->controllerSource);
        self::assertStringContainsString('data.debtAmount', $this->controllerSource);
        self::assertStringContainsString('data.tenderedAmount', $this->controllerSource);
        self::assertStringContainsString('data.changeAmount', $this->controllerSource);
    }
    public function testExpiredSessionReferenceRegenerationUsesSessionEndpointAndRestartsCountdown(): void
    {
        self::assertStringContainsString(
            "`/app/payment-sessions/${this.paymentSessionId}/payment-reference/regenerate`",
            $this->controllerSource
        );
        self::assertStringContainsString(
            '(!this.paymentSessionId && !this.paymentReferenceOrderId)',
            $this->controllerSource
        );
        self::assertStringContainsString(
            'paymentReferenceExpiresAt: body.data.expiresAt',
            $this->controllerSource
        );
        self::assertStringContainsString(
            'this.paymentReferenceCountdownTimer = globalThis.setInterval(() => this.updatePaymentReferenceCountdown(), 1000);',
            $this->controllerSource
        );
        self::assertStringContainsString(
            'this.renderPaymentReference({',
            $this->controllerSource
        );
        self::assertStringContainsString(
            'paymentReferenceQrUrl: body.data.qrUrl',
            $this->controllerSource
        );
        self::assertStringContainsString(
            'paymentSessionId: body.data.sessionId ?? this.paymentSessionId',
            $this->controllerSource
        );
    }


}
