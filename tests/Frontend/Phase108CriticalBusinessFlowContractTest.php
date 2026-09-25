<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class Phase108CriticalBusinessFlowContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testCheckoutFlowPreservesServerAuthoritativePaymentAndRetryContracts(): void
    {
        $controller = (string) file_get_contents($this->root . '/assets/controllers/pos_checkout_controller.js');
        $checkout = (string) file_get_contents($this->root . '/src/Controller/Order/CheckoutController.php');
        $template = (string) file_get_contents($this->root . '/templates/pos/index.html.twig');

        self::assertStringContainsString("'Idempotency-Key'", $controller);
        self::assertStringContainsString("'X-CSRF-TOKEN'", $controller);
        self::assertStringContainsString("this.idempotencyKey = this.newIdempotencyKey()", $controller);
        self::assertStringContainsString("this.idempotencyKey = null", $controller);
        self::assertStringContainsString('startPaymentStatusPolling()', $controller);
        self::assertStringContainsString('manualBankConfirm', $controller);
        self::assertStringContainsString('completePaidSale', $controller);
        self::assertStringContainsString('/app/payment-sessions/', $controller);
        self::assertStringContainsString('paymentSessionStatus', $checkout);
        self::assertStringContainsString('manualBankPaymentConfirm', $checkout);
        self::assertStringContainsString('completeOrder', $checkout);
        self::assertStringContainsString('CsrfTokenManagerInterface', $checkout);
        self::assertStringContainsString('data-pos-checkout-messages-value=', $template);
    }

    public function testPosNeverShowsTechnicalErrorCodeOrBackendExceptionTextToCashier(): void
    {
        $controller = (string) file_get_contents($this->root . '/assets/controllers/pos_checkout_controller.js');

        self::assertStringContainsString('userFacingErrorMessage', $controller);
        self::assertStringContainsString('messages.errorMessages?.[code]', $controller);
        self::assertStringContainsString('Never expose backend exception text', $controller);
        self::assertStringNotContainsString('`${code}: ${message}`', $controller);
    }

    public function testOrderLifecycleKeepsIdempotencyCsrfAndUserFacingErrorContract(): void
    {
        $controller = (string) file_get_contents($this->root . '/assets/controllers/order_lifecycle_controller.js');
        $template = (string) file_get_contents($this->root . '/templates/order/show.html.twig');
        $backend = (string) file_get_contents($this->root . '/src/Controller/Order/OrderLifecycleController.php');

        self::assertStringContainsString("'Idempotency-Key'", $controller);
        self::assertStringContainsString("'X-CSRF-TOKEN'", $controller);
        self::assertStringContainsString('errorMessagesValue', $controller);
        self::assertStringNotContainsString('`${message}${code}`', $controller);
        self::assertStringContainsString('data-order-lifecycle-error-messages-value=', $template);
        self::assertStringContainsString('Idempotency-Key', $backend);
        self::assertStringContainsString('CsrfToken', $backend);
        self::assertStringContainsString('Permission::ORDER_CANCEL', $backend);
        self::assertStringContainsString('Permission::ORDER_REFUND', $backend);
    }

    public function testCriticalFlowDoesNotAddBusinessRulesToStimulus(): void
    {
        $checkout = (string) file_get_contents($this->root . '/assets/controllers/pos_checkout_controller.js');
        $lifecycle = (string) file_get_contents($this->root . '/assets/controllers/order_lifecycle_controller.js');

        foreach ([$checkout, $lifecycle] as $controller) {
            self::assertStringNotContainsString('EntityManager', $controller);
            self::assertStringNotContainsString('stockQuantity -=', $controller);
            self::assertStringNotContainsString('order.status =', $controller);
            self::assertStringNotContainsString('payment.status =', $controller);
        }
    }
}
