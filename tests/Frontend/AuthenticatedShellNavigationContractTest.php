<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class AuthenticatedShellNavigationContractTest extends TestCase
{
    private string $css;
    private string $authenticatedLayout;
    private string $posLayout;
    private string $navigationController;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->css = (string) file_get_contents($root . '/assets/styles/app.css');
        $this->authenticatedLayout = (string) file_get_contents($root . '/templates/layout/authenticated.html.twig');
        $this->posLayout = (string) file_get_contents($root . '/templates/layout/pos.html.twig');
        $this->navigationController = (string) file_get_contents($root . '/assets/controllers/navigation_controller.js');
    }

    public function testShellProvidesSkipLinkAndFocusableMainLandmark(): void
    {
        self::assertStringContainsString('class="skip-link"', $this->authenticatedLayout);
        self::assertStringContainsString('href="#main-content"', $this->authenticatedLayout);
        self::assertStringContainsString('id="main-content" tabindex="-1"', $this->authenticatedLayout);
        self::assertStringContainsString('class="skip-link"', $this->posLayout);
    }

    public function testNavigationKeepsAuthorizationServerSide(): void
    {
        self::assertStringContainsString('is_granted(', $this->authenticatedLayout);
        self::assertStringNotContainsString('fetch(', $this->navigationController);
        self::assertStringNotContainsString('/api/', $this->navigationController);
        self::assertStringNotContainsString('/checkout', $this->navigationController);
    }

    public function testMobileNavigationRestoresFocusAndSupportsTabLoop(): void
    {
        self::assertStringContainsString("event.key === 'Escape'", $this->navigationController);
        self::assertStringContainsString("event.key !== 'Tab'", $this->navigationController);
        self::assertStringContainsString('toggleTarget.focus()', $this->navigationController);
        self::assertStringContainsString('focusableElements()', $this->navigationController);
    }

    public function testShellUsesAccessibleTouchTargetsAndSmallViewportRules(): void
    {
        self::assertStringContainsString('min-height: 48px', $this->css);
        self::assertStringContainsString('@media (max-width: 380px)', $this->css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $this->css);
        self::assertStringContainsString('skip-link', $this->css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $this->css);
    }
}
