<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class PosCheckoutUiContractTest extends TestCase
{
    private string $controllerSource;
    private string $templateSource;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);

        $this->controllerSource = file_get_contents(
            $root . '/assets/controllers/pos_checkout_controller.js'
        );

        $this->templateSource = file_get_contents(
            $root . '/templates/pos/index.html.twig'
        );

        self::assertNotFalse($this->controllerSource);
        self::assertNotFalse($this->templateSource);
    }

    public function testIdempotencyKeyIsRetainedAcrossRetry(): void
    {
        self::assertStringContainsString('this.idempotencyKey = null;', $this->controllerSource);
        self::assertStringContainsString('if (this.idempotencyKey === null)', $this->controllerSource);
        self::assertStringContainsString("'Idempotency-Key': this.idempotencyKey", $this->controllerSource);
        self::assertStringContainsString('this.submit();', $this->controllerSource);
        self::assertStringContainsString('this.idempotencyKey = null;', $this->controllerSource);
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
    }

    public function testCashAndBankTransferHaveDistinctUiSemantics(): void
    {
        self::assertStringContainsString("value=\"CASH\"", $this->templateSource);
        self::assertStringContainsString("value=\"BANK_TRANSFER\"", $this->templateSource);
        self::assertStringContainsString('Customer gives', $this->templateSource);
        self::assertStringContainsString('Transfer amount', $this->templateSource);
        self::assertStringContainsString("tenderedAmount: isCash ?", $this->controllerSource);
        self::assertStringContainsString("Bank transfer amount must equal the order total.", $this->controllerSource);
        self::assertStringContainsString("this.paymentMethodsTargets", $this->controllerSource);
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
}
