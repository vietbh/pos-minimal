<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class MobileUxContractTest extends TestCase
{
    private string $controllerSource;
    private string $authenticatedLayout;
    private string $posTemplate;
    private string $appCss;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);

        $this->controllerSource = (string) file_get_contents($root . '/assets/controllers/mobile_ux_controller.js');
        $this->authenticatedLayout = (string) file_get_contents($root . '/templates/layout/authenticated.html.twig');
        $this->posTemplate = (string) file_get_contents($root . '/templates/pos/index.html.twig');
        $this->appCss = (string) file_get_contents($root . '/assets/app.css');
    }

    public function testMobileControllerContainsNoPaymentOrBusinessEndpoint(): void
    {
        self::assertStringContainsString('visualViewport', $this->controllerSource);
        self::assertStringContainsString('data-keyboard-open', $this->controllerSource);
        self::assertStringContainsString('--ui-viewport-width', $this->controllerSource);
        self::assertStringContainsString('data-ui-orientation', $this->controllerSource);
        self::assertStringContainsString('scrollIntoView', $this->controllerSource);
        self::assertStringNotContainsString('/checkout', $this->controllerSource);
        self::assertStringNotContainsString('payment', strtolower($this->controllerSource));
        self::assertStringNotContainsString('order', strtolower($this->controllerSource));
    }

    public function testAuthenticatedShellUsesMobileUxController(): void
    {
        self::assertStringContainsString('data-controller="mobile-ux"', $this->authenticatedLayout);
        self::assertStringContainsString('mobile-bottom-nav', $this->authenticatedLayout);
    }

    public function testPosUsesMobileUxAlongsideCheckoutController(): void
    {
        self::assertStringContainsString('data-controller="pos-checkout mobile-ux"', $this->posTemplate);
        self::assertStringContainsString('enterkeyhint="search"', $this->posTemplate);
        self::assertStringContainsString('data-pos-checkout-endpoint-value=', $this->posTemplate);
        self::assertStringContainsString('enterkeyhint="done"', $this->posTemplate);
        self::assertStringContainsString("{{ 'pos.page_title'|trans }}", $this->posTemplate);
    }

    public function testMobileCssHardensKeyboardAndSmallViewportBehavior(): void
    {
        self::assertStringContainsString('--ui-viewport-height', $this->appCss);
        self::assertStringContainsString('[data-keyboard-open="true"] .mobile-bottom-nav', $this->appCss);
        self::assertStringContainsString('@media (max-width: 360px)', $this->appCss);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $this->appCss);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $this->appCss);
        self::assertStringContainsString('orientation: landscape', $this->appCss);
        self::assertStringContainsString('@media (pointer: coarse)', $this->appCss);
    }
}
