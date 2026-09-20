<?php

declare(strict_types=1);

namespace App\Tests\Application\Order\Command\CompleteOrder;

use App\Application\Order\Command\Checkout\ProductLockingInterface;
use App\Application\Order\Command\CompleteOrder\CompleteOrderService;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Payment;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Repository\StockMovementRepositoryInterface;
use App\Domain\User\User;
use App\Domain\Order\ValueObject\OrderNumber;
use PHPUnit\Framework\TestCase;

final class CompleteOrderServiceTest extends TestCase
{
    public function testCompletesFullyPaidOrderAndMutatesStockOnce(): void
    {
        $user = new User('complete-order-'.bin2hex(random_bytes(3)));
        $order = new Order(new OrderNumber('ORD-COMPLETE123456789'), $user);
        $product = new Product('Complete product', Money::fromInt(100000));
        $product->setStockQuantityForAdjustment(5);
        $order->addItem(new OrderItem($product, 2, Money::fromInt(100000)));
        $order->recalculateTotals();
        $payment = new Payment(Money::fromInt(200000), PaymentMethod::BANK_TRANSFER, $user, 'ABC12345');
        $order->addPayment($payment);

        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->expects(self::once())->method('findByIdForUpdate')->with(1)->willReturn($order);
        $orders->expects(self::once())->method('save')->with($order);

        $locking = $this->createMock(ProductLockingInterface::class);
        $locking->expects(self::once())->method('lock')->with($product->getId())->willReturn($product);

        $stock = $this->createMock(StockMovementRepositoryInterface::class);
        $stock->expects(self::once())->method('save');

        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $audit->expects(self::once())->method('save');

        $service = new CompleteOrderService($orders, $locking, $stock, $audit);

        $reflection = new \ReflectionClass($order);
        $reflection->getProperty('id')->setValue($order, 1);

        $result = $service->completePaidOrder(1, $user, 'req-1', 'POS');

        self::assertSame($order, $result);
        self::assertTrue($order->isCompleted());
        self::assertSame(3, $product->getStockQuantity());
    }

    public function testRejectsUnpaidOrder(): void
    {
        $user = new User('complete-order-unpaid-'.bin2hex(random_bytes(3)));
        $order = new Order(new OrderNumber('ORD-UNPAID123456789'), $user);
        $product = new Product('Unpaid product', Money::fromInt(100000));
        $product->setStockQuantityForAdjustment(5);
        $order->addItem(new OrderItem($product, 1, Money::fromInt(100000)));
        $order->recalculateTotals();

        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findByIdForUpdate')->willReturn($order);

        $service = new CompleteOrderService(
            $orders,
            $this->createMock(ProductLockingInterface::class),
            $this->createMock(StockMovementRepositoryInterface::class),
            $this->createMock(AuditLogRepositoryInterface::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('fully paid');
        $service->completePaidOrder(1, $user);
    }
}
