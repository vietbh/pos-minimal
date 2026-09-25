<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Order\Query;

use App\Application\Order\Query\CopyOrderToPos\CopyOrderToPosHandler;
use App\Application\Order\Query\CopyOrderToPos\CopyOrderToPosInput;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Customer\Customer;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Payment;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class CopyOrderToPosHandlerTest extends TestCase
{
    public function testCopiesProductsCustomerAndAuditedCashTenderedAmount(): void
    {
        $user = $this->createMock(User::class);
        $customer = new Customer('Nguyễn Văn A', '0900000000');
        $product = new Product('Cà phê', Money::fromDecimal('45000.00'));
        $order = new Order(new OrderNumber('ORD-COPY-001'), $user, $customer);
        $order->addItem(new OrderItem($product, 2));

        $payment = new Payment(Money::fromDecimal('90000.00'), PaymentMethod::CASH, $user);
        $order->addPayment($payment);

        $id = new \ReflectionProperty(Order::class, 'id');
        $id->setValue($order, 1);
        $customerId = new \ReflectionProperty(Customer::class, 'id');
        $customerId->setValue($customer, 7);
        $productId = new \ReflectionProperty(Product::class, 'id');
        $productId->setValue($product, 11);

        $orders = $this->createMock(OrderRepositoryInterface::class);
        $orders->method('findById')->with(1)->willReturn($order);

        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $auditLog = new AuditLog(
            action: 'ORDER_COMPLETED',
            user: $user,
            entityType: 'Order',
            entityId: '1',
            newValues: ['tenderedAmount' => '100000.00'],
        );
        $audit->method('findByEntity')->with('Order', '1')->willReturn([$auditLog]);

        $handler = new CopyOrderToPosHandler($orders, $audit);
        $result = $handler(new CopyOrderToPosInput(1));

        self::assertNotNull($result);
        self::assertSame('ORD-COPY-001', $result->sourceOrderNumber);
        self::assertSame(7, $result->customerId);
        self::assertSame('100000.00', $result->cashTenderedAmount);
        self::assertCount(1, $result->items);
        self::assertSame(11, $result->items[0]->productId);
        self::assertSame(2, $result->items[0]->quantity);
        self::assertSame('45000.00', $result->items[0]->unitPrice);
    }
}
