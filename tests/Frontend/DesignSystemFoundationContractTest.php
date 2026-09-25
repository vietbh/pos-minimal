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
            '--ds-color-background: #F8FAFC',
            '--ds-color-surface: #FFFFFF',
            '--ds-color-text: #172033',
            '--ds-color-text-secondary: #475569',
            '--ds-color-primary: #2563EB',
            '--ds-color-primary-hover: #1D4ED8',
            '--ds-color-success: #15803D',
            '--ds-color-warning: #B45309',
            '--ds-color-danger: #B91C1C',
            '--ds-color-border: #CBD5E1',
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
