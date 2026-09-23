<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class Phase103PosCheckoutIntegrationContractTest extends TestCase
{
    private string $controller;
    private string $template;
    private string $styles;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->controller = (string) file_get_contents($root . '/assets/controllers/pos_checkout_controller.js');
        $this->template = (string) file_get_contents($root . '/templates/pos/index.html.twig');
        $this->styles = (string) file_get_contents($root . '/assets/styles/app.css');
    }

    public function testCheckoutUiUsesServerAuthoritativeSubmitContract(): void
    {
        self::assertStringContainsString("'X-CSRF-TOKEN': this.csrfTokenValue", $this->controller);
        self::assertStringContainsString("'Idempotency-Key': this.idempotencyKey", $this->controller);
        self::assertStringContainsString("'X-Request-ID': this.newRequestId()", $this->controller);
        self::assertStringContainsString('this.endpointValue', $this->controller);
        self::assertStringNotContainsString('fetch(this.endpointValue, {\n                method: \'GET\'', $this->controller);
    }

    public function testCheckoutBusyStateIsExposedToAssistiveTechnology(): void
    {
        self::assertStringContainsString('data-pos-checkout-target="checkoutSection"', $this->template);
        self::assertStringContainsString('setAttribute(\'aria-busy\', submitting ? \'true\' : \'false\')', $this->controller);
        self::assertStringContainsString('aria-describedby="pos-payment-state"', $this->template);
    }

    public function testProductActionsAreTranslatedAndNamed(): void
    {
        self::assertStringContainsString('data-pos-checkout-add-product-label-value', $this->template);
        self::assertStringContainsString('addProductLabel: String', $this->controller);
        self::assertStringContainsString('this.addProductLabelValue', $this->controller);
        self::assertStringContainsString('aria-label', $this->controller);
    }

    public function testCheckoutSuccessMovesFocusToTheResultHeading(): void
    {
        self::assertStringContainsString('data-pos-checkout-target="successTitle" tabindex="-1"', $this->template);
        self::assertStringContainsString('successTitle"]?.focus()', $this->controller);
    }

    public function testMobileCheckoutActionsKeepTouchTargets(): void
    {
        self::assertStringContainsString('min-height: 48px', $this->styles);
        self::assertStringContainsString('.pos-product-result .pos-touch-button', $this->styles);
        self::assertStringContainsString('@media (max-width: 380px)', $this->styles);
    }

    public function testCartQuantityInteractionPreservesFocus(): void
    {
        self::assertStringContainsString('focusButton?.focus()', $this->controller);
        self::assertStringContainsString('data-delta', $this->controller);
    }
}
