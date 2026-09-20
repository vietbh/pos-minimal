<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class OrderCustomerDebtUiContractTest extends TestCase
{
    public function testPhase6UsesMobileFriendlySearchAndTranslationContracts(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([
            'templates/order/index.html.twig' => ['orders.search_placeholder', 'orders.pagination', '|money_vnd'],
            'templates/customer/index.html.twig' => ['customers.search_placeholder', 'customers.empty_title'],
            'templates/debt/index.html.twig' => ['debt.search_placeholder', 'debt.pagination', '|money_vnd'],
        ] as $file => $needles) {
            $content = file_get_contents($root.'/'.$file);
            self::assertNotFalse($content);
            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $content);
            }
        }
    }

    public function testPhase6PreservesServerAuthoritativeOrderAndDebtSemantics(): void
    {
        $root = dirname(__DIR__, 2);
        $order = file_get_contents($root.'/templates/order/show.html.twig');
        $debt = file_get_contents($root.'/templates/debt/show.html.twig');
        $lifecycle = file_get_contents($root.'/assets/controllers/order_lifecycle_controller.js');
        $payment = file_get_contents($root.'/assets/controllers/debt_payment_controller.js');

        foreach ([$order, $debt, $lifecycle, $payment] as $content) {
            self::assertNotFalse($content);
        }

        self::assertStringContainsString("is_granted('ORDER_CANCEL')", $order);
        self::assertStringContainsString("is_granted('ORDER_REFUND')", $order);
        self::assertStringContainsString('money_vnd', $order);
        self::assertStringContainsString('Idempotency-Key', $lifecycle);
        self::assertStringContainsString('X-Request-ID', $lifecycle);
        self::assertStringContainsString('Idempotency-Key', $payment);
        self::assertStringContainsString('X-Request-ID', $payment);

        foreach (['calculateDebt', 'debtRemaining =', 'remainingAmount =', 'debtOriginal -'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $order);
            self::assertStringNotContainsString($forbidden, $debt);
            self::assertStringNotContainsString($forbidden, $payment);
        }
    }

    public function testPhase6InteractionMessagesComeFromTranslationValues(): void
    {
        $root = dirname(__DIR__, 2);
        $order = file_get_contents($root.'/templates/order/show.html.twig');
        $debt = file_get_contents($root.'/templates/debt/show.html.twig');
        $lifecycle = file_get_contents($root.'/assets/controllers/order_lifecycle_controller.js');
        $payment = file_get_contents($root.'/assets/controllers/debt_payment_controller.js');

        self::assertStringContainsString('data-order-lifecycle-reason-required-label-value', $order);
        self::assertStringContainsString('data-order-lifecycle-generic-error-label-value', $order);
        self::assertStringContainsString('reasonRequiredLabelValue', $lifecycle);
        self::assertStringContainsString('genericErrorLabelValue', $lifecycle);
        self::assertStringContainsString('data-debt-payment-required-label-value', $debt);
        self::assertStringContainsString('data-debt-payment-recorded-label-value', $debt);
        self::assertStringContainsString('requiredLabelValue', $payment);
        self::assertStringContainsString('recordedLabelValue', $payment);
    }

    public function testPhase6TranslationsContainEnglishAndVietnameseWorkflowKeys(): void
    {
        $root = dirname(__DIR__, 2);
        $en = file_get_contents($root.'/translations/messages.en.yaml');
        $vi = file_get_contents($root.'/translations/messages.vi.yaml');

        self::assertNotFalse($en);
        self::assertNotFalse($vi);

        foreach (['orders:', 'customers:', 'debt:', 'orders.empty_title:', 'customers.empty_title:', 'debt.payment_error:'] as $key) {
            self::assertStringContainsString($key, $en);
            self::assertStringContainsString($key, $vi);
        }
    }
}
