<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;

final class OrderHistoryUiContractTest extends TestCase
{
    public function testOrderHistoryUsesServerSideFiltersAndMoneyFormatter(): void
    {
        $root = dirname(__DIR__, 2);
        $index = file_get_contents($root.'/templates/order/index.html.twig');

        self::assertNotFalse($index);
        self::assertStringContainsString('name="q"', $index);
        self::assertStringContainsString('name="status"', $index);
        self::assertStringContainsString('name="from"', $index);
        self::assertStringContainsString('name="to"', $index);
        self::assertStringContainsString('|money_vnd', $index);
        self::assertStringContainsString("path('orders_show'", $index);
    }

    public function testOrderHistoryDoesNotCalculateFinancialValuesInTwig(): void
    {
        $root = dirname(__DIR__, 2);
        $index = file_get_contents($root.'/templates/order/index.html.twig');
        $show = file_get_contents($root.'/templates/order/show.html.twig');

        self::assertNotFalse($index);
        self::assertNotFalse($show);

        foreach (['paidAmount -', 'total -', 'calculateDebt', 'debtRemaining =', 'remainingAmount ='] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $index);
            self::assertStringNotContainsString($forbidden, $show);
        }
    }

    public function testOrderDetailPreservesListContextAndPermissionChecksActions(): void
    {
        $root = dirname(__DIR__, 2);
        $show = file_get_contents($root.'/templates/order/show.html.twig');

        self::assertNotFalse($show);
        self::assertStringContainsString("path('orders_index', backQuery)", $show);
        self::assertStringContainsString("is_granted('ORDER_CANCEL')", $show);
        self::assertStringContainsString("is_granted('ORDER_REFUND')", $show);
    }
}
