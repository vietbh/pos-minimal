<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Draft;

use App\Application\Order\Query\GetDraftOrder\GetDraftOrderHandler;
use App\Application\Order\Query\GetDraftOrder\GetDraftOrderInput;
use App\Domain\Customer\Customer;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetDraftOrderTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
    }

    public function testReturnsCompleteDraftForPos(): void
    {
        [$orderId] = $this->createDraft(
            withCustomer: true,
            withItems: true,
        );

        /** @var GetDraftOrderHandler $handler */
        $handler = self::getContainer()->get(
            GetDraftOrderHandler::class,
        );

        $result = $handler(
            new GetDraftOrderInput($orderId),
        );

        self::assertNotNull($result);
        self::assertSame($orderId, $result->id);
        self::assertSame(OrderStatus::DRAFT, $result->status);

        self::assertNotEmpty($result->orderNumber);

        self::assertNotNull($result->customer);
        self::assertSame('Nguyen Van A', $result->customer->name);
        self::assertSame('0900000001', $result->customer->phone);

        self::assertCount(2, $result->items);

        self::assertSame(2_500_000, $result->items[0]->unitPrice);
        self::assertSame(2, $result->items[0]->quantity);
        self::assertSame(5_000_000, $result->items[0]->subtotal);

        self::assertSame(5_000_000, $result->items[1]->unitPrice);
        self::assertSame(1, $result->items[1]->quantity);
        self::assertSame(5_000_000, $result->items[1]->subtotal);

        self::assertSame(10_000_000, $result->subtotal);
        self::assertSame(10_000_000, $result->total);
    }

    public function testReturnsDraftWithoutCustomer(): void
    {
        [$orderId] = $this->createDraft(
            withCustomer: false,
            withItems: true,
        );

        /** @var GetDraftOrderHandler $handler */
        $handler = self::getContainer()->get(
            GetDraftOrderHandler::class,
        );

        $result = $handler(
            new GetDraftOrderInput($orderId),
        );

        self::assertNotNull($result);
        self::assertNull($result->customer);
        self::assertCount(2, $result->items);
    }

    public function testReturnsEmptyDraft(): void
    {
        [$orderId] = $this->createDraft(
            withCustomer: false,
            withItems: false,
        );

        /** @var GetDraftOrderHandler $handler */
        $handler = self::getContainer()->get(
            GetDraftOrderHandler::class,
        );

        $result = $handler(
            new GetDraftOrderInput($orderId),
        );

        self::assertNotNull($result);
        self::assertSame([], $result->items);
        self::assertSame(0, $result->subtotal);
        self::assertSame(0, $result->total);
    }

    public function testReturnsNullForUnknownOrder(): void
    {
        /** @var GetDraftOrderHandler $handler */
        $handler = self::getContainer()->get(
            GetDraftOrderHandler::class,
        );

        self::assertNull(
            $handler(
                new GetDraftOrderInput(999999999),
            ),
        );
    }

    public function testReturnsNullForCompletedOrder(): void
    {
        [$orderId] = $this->createDraft(
            withCustomer: true,
            withItems: true,
        );

        /** @var Order|null $order */
        $order = $this->entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($order);

        $order->complete();

        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var GetDraftOrderHandler $handler */
        $handler = self::getContainer()->get(
            GetDraftOrderHandler::class,
        );

        self::assertNull(
            $handler(
                new GetDraftOrderInput($orderId),
            ),
        );
    }

    public function testPreservesHistoricalOrderItemSnapshot(): void
    {
        [$orderId, $productId] = $this->createDraft(
            withCustomer: false,
            withItems: true,
        );

        /** @var Product|null $product */
        $product = $this->entityManager->find(
            Product::class,
            $productId,
        );

        self::assertNotNull($product);

        $product->changeSellingPrice(
            Money::fromDecimal('99999.00'),
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var GetDraftOrderHandler $handler */
        $handler = self::getContainer()->get(
            GetDraftOrderHandler::class,
        );

        $result = $handler(
            new GetDraftOrderInput($orderId),
        );

        self::assertNotNull($result);
        self::assertNotEmpty($result->items);

        self::assertSame(
            'Draft Product A',
            $result->items[0]->productName,
        );

        self::assertSame(
            2500000,
            $result->items[0]->unitPrice,
        );
    }

    public function testRejectsInvalidOrderId(): void
    {
        /** @var GetDraftOrderHandler $handler */
        $handler = self::getContainer()->get(
            GetDraftOrderHandler::class,
        );

        $this->expectException(\InvalidArgumentException::class);

        $handler(
            new GetDraftOrderInput(0),
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createDraft(
        bool $withCustomer,
        bool $withItems,
    ): array {
        $user = new User(
            'draft-query-'.bin2hex(random_bytes(6)),
        );

        $customer = null;

        if ($withCustomer) {
            $customer = new Customer(
                'Nguyen Van A',
                '0900000001',
            );

            $this->entityManager->persist($customer);
        }

        $productA = new Product(
            'Draft Product A',
            Money::fromDecimal('25000.00'),
        );

        $productB = new Product(
            'Draft Product B',
            Money::fromDecimal('50000.00'),
        );

        $order = new Order(
            orderNumber: new OrderNumber(
                'ORD-QUERY-'.strtoupper(
                    bin2hex(random_bytes(5)),
                ),
            ),
            user: $user,
            customer: $customer,
        );

        if ($withItems) {
            $itemA = new OrderItem(
                product: $productA,
                quantity: 2,
                unitPrice: $productA->getSellingPrice(),
            );

            $itemB = new OrderItem(
                product: $productB,
                quantity: 1,
                unitPrice: $productB->getSellingPrice(),
            );

            $order->addItem($itemA);
            $order->addItem($itemB);
        }

        $this->entityManager->persist($user);
        $this->entityManager->persist($productA);
        $this->entityManager->persist($productB);
        $this->entityManager->persist($order);

        $this->entityManager->flush();

        $orderId = $order->getId();
        $productId = $productA->getId();

        self::assertNotNull($orderId);
        self::assertNotNull($productId);

        return [$orderId, $productId];
    }
}
