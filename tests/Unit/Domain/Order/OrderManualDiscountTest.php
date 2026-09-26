<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Order;

use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class OrderManualDiscountTest extends TestCase
{
    public function testManualDiscountIsRetainedWhenAppliedAfterSnapshotItems(): void
    {
        $order = new Order(
            orderNumber: new OrderNumber('ORD-DISCOUNT-TEST'),
            user: new User('discount-test-user'),
            customer: null,
            note: null,
        );

        $product = new Product('Test product', Money::fromInt(4000));
        $order->addItem(new OrderItem($product, 1, Money::fromInt(4000)));

        $order->setManualDiscount(Money::fromInt(2000));
        $order->recalculateTotals();

        self::assertSame('4000.00', $order->getSubtotal()->toDecimal());
        self::assertSame('2000.00', $order->getManualDiscount()->toDecimal());
        self::assertSame('2000.00', $order->getDiscount()->toDecimal());
        self::assertSame('2000.00', $order->getTotal()->toDecimal());
    }
}
