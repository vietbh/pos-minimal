<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class Phase106PaymentBankTransferUiContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testPosPaymentUiIsAccessibleAndKeepsPaymentStateServerAuthoritative(): void
    {
        $template = (string) file_get_contents($this->root . '/templates/pos/index.html.twig');
        $controller = (string) file_get_contents($this->root . '/assets/controllers/pos_checkout_controller.js');

        self::assertStringContainsString('aria-describedby="pos-payment-method-help"', $template);
        self::assertStringContainsString('id="pos-payment-method-help"', $template);
        self::assertStringContainsString('aria-describedby="pos-bank-payment-help"', $template);
        self::assertStringContainsString('id="pos-bank-payment-help"', $template);
        self::assertStringContainsString('aria-live="polite"', $template);
        self::assertStringContainsString('pos.payment_reference.title', $template);
        self::assertStringContainsString('pos.payment_reference.remaining', $template);

        self::assertStringContainsString("this.messagesValue.transferContentGenerated", $controller);
        self::assertStringContainsString("this.messagesValue.paymentReferenceExpired", $controller);
        self::assertStringContainsString("this.messagesValue.paymentReferenceCreated", $controller);
        self::assertStringNotContainsString('Nội dung chuyển khoản sẽ được tạo khi bắt đầu thanh toán.', $controller);
        self::assertStringNotContainsString('Mã đã hết hạn. Bạn có thể tạo mã mới.', $controller);
    }

    public function testBankTransferUiDoesNotMoveBusinessRulesIntoJavascript(): void
    {
        $controller = (string) file_get_contents($this->root . '/assets/controllers/pos_checkout_controller.js');

        self::assertStringContainsString("method: this.paymentMethodValue()", $controller);
        self::assertStringContainsString("paymentReference: null", $controller);
        self::assertStringContainsString("'Idempotency-Key'", $controller);
        self::assertStringContainsString("'X-CSRF-TOKEN'", $controller);
        self::assertStringNotContainsString('fetch(\'/api/', $controller);
        self::assertStringNotContainsString('stockQuantity', $controller);
        self::assertStringNotContainsString('order->', $controller);
    }

    public function testReceivingAccountAdminUiUsesTranslationsAndLabeledControls(): void
    {
        $template = (string) file_get_contents($this->root . '/templates/admin/payment/accounts.html.twig');

        self::assertStringContainsString("'admin.transfer_content'|trans", $template);
        self::assertStringContainsString("'admin.casso_subaccount'|trans", $template);
        self::assertStringContainsString("'admin.add_account'|trans", $template);
        self::assertStringContainsString('for="payment-account-number"', $template);
        self::assertStringContainsString('id="payment-account-number"', $template);
        self::assertStringContainsString('for="payment-account-name"', $template);
        self::assertStringContainsString('id="payment-account-name"', $template);
        self::assertStringContainsString('id="payment-transfer-template"', $template);
        self::assertStringContainsString('id="payment-qr-template"', $template);
    }

    public function testPaymentMutationRoutesRemainProtectedByExistingBackendContracts(): void
    {
        $controller = (string) file_get_contents($this->root . '/src/Controller/Order/CheckoutController.php');
        $adminController = (string) file_get_contents($this->root . '/src/Controller/Admin/PaymentBankAccountController.php');

        self::assertStringContainsString('Idempotency-Key', $controller);
        self::assertStringContainsString('CsrfToken', $controller);
        self::assertStringContainsString('manualBankPaymentConfirm', $controller);
        self::assertStringContainsString('regeneratePaymentSessionReference', $controller);
        self::assertStringContainsString('CsrfTokenManagerInterface', $adminController);
        self::assertStringContainsString('admin_payment_account_toggle', $adminController);
    }
}
