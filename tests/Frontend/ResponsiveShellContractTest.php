<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class ResponsiveShellContractTest extends TestCase
{
    private string $css;
    private string $authenticatedLayout;
    private string $posLayout;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);

        $this->css = (string) file_get_contents($root . '/assets/styles/app.css');
        $this->authenticatedLayout = (string) file_get_contents($root . '/templates/layout/authenticated.html.twig');
        $this->posLayout = (string) file_get_contents($root . '/templates/layout/pos.html.twig');
    }

    public function testActiveStylesheetContainsResponsiveApplicationShellRules(): void
    {
        self::assertStringContainsString('PHASE UI/UX 4.1', $this->css);
        self::assertStringContainsString('--shell-sidebar-width', $this->css);
        self::assertStringContainsString('.app-content-shell', $this->css);
        self::assertStringContainsString('@media (max-width: 900px)', $this->css);
        self::assertStringContainsString('env(safe-area-inset-bottom)', $this->css);
        self::assertStringContainsString('overflow-x: hidden', $this->css);
    }

    public function testMobileNavigationHasRealActiveStylesheetSupport(): void
    {
        self::assertStringContainsString('.mobile-navigation', $this->css);
        self::assertStringContainsString('.mobile-nav-toggle', $this->css);
        self::assertStringContainsString('.mobile-navigation[hidden]', $this->css);
        self::assertStringContainsString('aria-expanded', $this->authenticatedLayout);
        self::assertStringContainsString('aria-controls="app-navigation"', $this->authenticatedLayout);
    }

    public function testShellKeepsPosAsSeparateResponsiveWorkspace(): void
    {
        self::assertStringContainsString('pos-frame', $this->posLayout);
        self::assertStringContainsString('pos-mobile-topbar', $this->posLayout);
        self::assertStringContainsString('.pos-frame .pos-page', $this->css);
        self::assertStringContainsString('.pos-frame .mobile-bottom-nav', $this->css);
    }

    public function testResponsiveShellSupportsKeyboardAndReducedMotion(): void
    {
        self::assertStringContainsString(':focus-visible', $this->css);
        self::assertStringContainsString('prefers-reduced-motion: reduce', $this->css);
        self::assertStringContainsString('orientation: landscape', $this->css);
    }
}
