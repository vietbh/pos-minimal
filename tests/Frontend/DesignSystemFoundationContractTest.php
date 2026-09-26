<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class DesignSystemFoundationContractTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->css = (string) file_get_contents($root . '/assets/styles/app.css');
    }

    public function testPhase101TokensAreDefined(): void
    {
        foreach ([
            '--ds-color-background: #F4F8F7',
            '--ds-color-surface: #FFFFFF',
            '--ds-color-text: #172522',
            '--ds-color-text-secondary: #64736F',
            '--ds-color-primary: #0F9D8A',
            '--ds-color-primary-hover: #087C6D',
            '--ds-color-success: #16803C',
            '--ds-color-warning: #A15C00',
            '--ds-color-danger: #C62828',
            '--ds-color-border: #D4E0DD',
            '--ds-touch-min: 48px',
            '--ds-primary-action-min: 52px',
            '--ds-font-size-body: 1rem',
            '--ds-font-size-input: 1.0625rem',
        ] as $token) {
            self::assertStringContainsString($token, $this->css);
        }
    }

    public function testFoundationCoversAccessibleInteractiveControlsAndFocus(): void
    {
        self::assertStringContainsString('.button:focus-visible', $this->css);
        self::assertStringContainsString('input:focus-visible', $this->css);
        self::assertStringContainsString('min-height: var(--ds-touch-min)', $this->css);
        self::assertStringContainsString('min-height: var(--ds-primary-action-min)', $this->css);
        self::assertStringContainsString('@media (pointer: coarse)', $this->css);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $this->css);
    }

    public function testFoundationProvidesNonColorOnlyStatusAndFeedbackPrimitives(): void
    {
        self::assertStringContainsString('.ds-status::before', $this->css);
        self::assertStringContainsString('.ds-alert', $this->css);
        self::assertStringContainsString('.ds-empty', $this->css);
        self::assertStringContainsString('.ds-error-state', $this->css);
        self::assertStringContainsString('.ds-success-state', $this->css);
        self::assertStringContainsString('.ds-spinner', $this->css);
    }

    public function testFoundationProvidesResponsiveDialogAndMobileBaseline(): void
    {
        self::assertStringContainsString('.ds-dialog', $this->css);
        self::assertStringContainsString('.ds-dialog::backdrop', $this->css);
        self::assertStringContainsString('@media (max-width: 600px)', $this->css);
        self::assertStringContainsString('@media (max-width: 360px)', $this->css);
    }

    public function testExistingLegacyTokensAliasTheFoundation(): void
    {
        self::assertStringContainsString('--color-primary: var(--ds-color-primary)', $this->css);
        self::assertStringContainsString('--color-danger: var(--ds-color-danger)', $this->css);
        self::assertStringContainsString('--color-border: var(--ds-color-border)', $this->css);
    }
}
