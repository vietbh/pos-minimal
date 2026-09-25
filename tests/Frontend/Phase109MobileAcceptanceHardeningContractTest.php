<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class Phase109MobileAcceptanceHardeningContractTest extends TestCase
{
    private string $root;
    private string $css;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->css = (string) file_get_contents($this->root . '/assets/styles/phase-uiux-7.css');
    }

    public function testMobileAcceptanceExplicitlyCovers360And390PixelViewports(): void
    {
        self::assertStringContainsString('@media (max-width: 390px)', $this->css);
        self::assertStringContainsString('@media (max-width: 360px)', $this->css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $this->css);
    }

    public function testCriticalMobileContainersCanShrinkWithoutHorizontalOverflow(): void
    {
        foreach ([
            '.app-frame',
            '.app-content-shell',
            '.app-main',
            '.pos-main',
            '.pos-page',
            '.pos-layout',
            '.pos-card',
            '.pos-result-main',
            '.pos-cart-main',
            '.statistics-card',
        ] as $selector) {
            self::assertStringContainsString($selector, $this->css);
        }

        self::assertStringContainsString('min-width: 0', $this->css);
        self::assertStringContainsString('overflow-wrap: anywhere', $this->css);
        self::assertStringContainsString('max-width: 100%', $this->css);
    }

    public function testCriticalTouchTargetsRemainAtLeast48PixelsOnSmallScreens(): void
    {
        foreach ([
            '.pos-method',
            '.pos-quantity-button',
            '.pos-touch-button',
            '.pos-complete-button',
            '.pos-payment-speaker .button',
        ] as $selector) {
            self::assertStringContainsString($selector, $this->css);
        }

        self::assertStringContainsString('min-height: 48px', $this->css);
        self::assertStringContainsString('min-height: 52px', $this->css);
    }

    public function testMobileNavigationAndPosTemplatesKeepAcceptanceEssentials(): void
    {
        $authenticated = (string) file_get_contents($this->root . '/templates/layout/authenticated.html.twig');
        $pos = (string) file_get_contents($this->root . '/templates/layout/pos.html.twig');
        $checkout = (string) file_get_contents($this->root . '/templates/pos/index.html.twig');

        self::assertStringContainsString('mobile-bottom-nav', $authenticated);
        self::assertStringContainsString('aria-current="page"', $authenticated);
        self::assertStringContainsString('aria-controls="app-navigation"', $authenticated);
        self::assertStringContainsString('mobile-bottom-nav', $pos);
        self::assertStringContainsString('pos-mobile-topbar', $pos);

        self::assertStringContainsString('data-pos-checkout-endpoint-value=', $checkout);
        self::assertStringContainsString('data-pos-checkout-messages-value=', $checkout);
        self::assertStringContainsString('X-CSRF-TOKEN', (string) file_get_contents($this->root . '/assets/controllers/pos_checkout_controller.js'));
        self::assertStringContainsString('Idempotency-Key', (string) file_get_contents($this->root . '/assets/controllers/pos_checkout_controller.js'));
    }

    public function testAcceptanceDoesNotMoveBusinessRulesIntoFrontendControllers(): void
    {
        foreach (glob($this->root . '/assets/controllers/*.js') ?: [] as $controllerPath) {
            $source = (string) file_get_contents($controllerPath);
            self::assertStringNotContainsString('EntityManager', $source, basename($controllerPath));
            self::assertStringNotContainsString('stockQuantity -=', $source, basename($controllerPath));
        }
    }


}
