<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class DebtPaymentAndProfileUiContractTest extends TestCase
{
    public function testDebtHistoryAndPasswordUiContracts(): void
    {
        $root = dirname(__DIR__, 2);

        $debtTemplate = file_get_contents($root . '/templates/debt/show.html.twig');
        self::assertIsString($debtTemplate);
        self::assertStringContainsString('data-debt-payment-target="history"', $debtTemplate);
        self::assertStringContainsString('data-debt-payment-target="paid"', $debtTemplate);
        self::assertStringContainsString('data-debt-payment-target="status"', $debtTemplate);

        $controller = file_get_contents($root . '/assets/controllers/debt_payment_controller.js');
        self::assertIsString($controller);
        self::assertStringContainsString('historyTarget.prepend(row)', $controller);

        $profile = file_get_contents($root . '/templates/application/profile.html.twig');
        self::assertIsString($profile);
        self::assertStringContainsString('name="current_password"', $profile);
        self::assertStringContainsString('name="new_password"', $profile);
        self::assertStringContainsString('name="confirm_password"', $profile);
        self::assertStringContainsString('data-controller="change-password"', $profile);
    }
}
