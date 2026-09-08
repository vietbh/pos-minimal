<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order;

use App\Application\Order\Query\GetOrder\GetOrderHandler;
use App\Application\Order\Query\GetOrder\GetOrderInput;
use App\Application\Order\Query\ListOrders\ListOrdersHandler;
use App\Application\Order\Query\ListOrders\ListOrdersInput;
use App\Domain\Customer\Customer;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Payment;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;

final class OrderQueryTest extends IntegrationTestCase
{
    public function testListOrdersSearchesByOrderNumberAndCustomer(): void
    {
        $user = new User('query-user-'.bin2hex(random_bytes(4)));
        $customer = new Customer('Nguyen Order Customer', '0901234567');
        $otherCustomer = new Customer('Other Customer', '0911111111');
        $this->entityManager->persist($user);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($otherCustomer);

        $this->persistCompletedOrder($user, $customer, 'QUERY-1001', '100.00');
        $this->persistCompletedOrder($user, $otherCustomer, 'QUERY-1002', '200.00');
        $this->entityManager->flush();

        $handler = self::getContainer()->get(ListOrdersHandler::class);
        $result = $handler(new ListOrdersInput(search: 'Nguyen'));

        self::assertCount(1, $result->items);
        self::assertSame('QUERY-1001', $result->items[0]->orderNumber);
        self::assertSame('100.00', $result->items[0]->total);
        self::assertSame('100.00', $result->items[0]->paidAmount);
        self::assertSame('0.00', $result->items[0]->debtAmount);
    }

    public function testListOrdersFiltersStatusAndPaginates(): void
    {
        $user = new User('pagination-user-'.bin2hex(random_bytes(4)));
        $this->entityManager->persist($user);
        $this->persistCompletedOrder($user, null, 'QUERY-2001', '100.00');
        $this->persistCompletedOrder($user, null, 'QUERY-2002', '100.00');
        $this->persistCompletedOrder($user, null, 'QUERY-2003', '100.00');
        $this->entityManager->flush();

        $handler = self::getContainer()->get(ListOrdersHandler::class);
        $result = $handler(new ListOrdersInput(status: OrderStatus::COMPLETED, page: 2, perPage: 2));

        self::assertSame(3, $result->totalItems);
        self::assertSame(2, $result->page);
        self::assertCount(1, $result->items);
        self::assertSame('QUERY-2001', $result->items[0]->orderNumber);
    }

    public function testDetailReturnsHistoricalItemsPaymentsAndDebt(): void
    {
        $user = new User('detail-user-'.bin2hex(random_bytes(4)));
        $customer = new Customer('Detail Customer');
        $product = new Product('Historical Product', Money::fromDecimal('25.00'));
        $product->setStockQuantityForAdjustment(10);
        $this->entityManager->persist($user);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $order = new Order(new OrderNumber('QUERY-3001'), $user, $customer, 'detail');
        $item = new OrderItem($product, 2, Money::fromDecimal('25.00'));
        $order->addItem($item);
        $payment = new Payment(Money::fromDecimal('30.00'), PaymentMethod::CASH, $user);
        $order->addPayment($payment);
        $order->complete(new \DateTimeImmutable('2026-09-01 10:00:00'));
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $handler = self::getContainer()->get(GetOrderHandler::class);
        $result = $handler(new GetOrderInput($order->getId()));

        self::assertNotNull($result);
        self::assertSame('QUERY-3001', $result->orderNumber);
        self::assertSame('50.00', $result->total);
        self::assertSame('30.00', $result->paidAmount);
        self::assertSame('20.00', $result->debtAmount);
        self::assertCount(1, $result->items);
        self::assertSame('25.00', $result->items[0]->unitPrice);
        self::assertSame('Historical Product', $result->items[0]->productName);
        self::assertCount(1, $result->payments);
    }

    private function persistCompletedOrder(User $user, ?Customer $customer, string $number, string $total): void
    {
        $product = new Product('Product '.$number, Money::fromDecimal($total));
        $product->setStockQuantityForAdjustment(10);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $order = new Order(new OrderNumber($number), $user, $customer);
        $order->addItem(new OrderItem($product, 1, Money::fromDecimal($total)));
        $order->addPayment(new Payment(Money::fromDecimal($total), PaymentMethod::CASH, $user));
        $order->complete();
        $this->entityManager->persist($order);
    }
}
