<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Draft;

use App\Application\Order\Command\RemoveDraftItem\RemoveDraftItemHandler;
use App\Application\Order\Command\RemoveDraftItem\RemoveDraftItemInput;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\StockMovement;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RemoveDraftItemTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
    }

    public function testRemovesItemAndRecalculatesTotals(): void
    {
        [$orderId, $firstItemId] = $this->createDraftOrderWithItems();

        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $result = $handler(
            new RemoveDraftItemInput(
                orderId: $orderId,
                itemId: $firstItemId,
            ),
        );

        self::assertSame($orderId, $result->orderId);
        self::assertSame(1, $result->remainingItemCount);
        self::assertSame('50000.00', $result->subtotal->toDecimal());
        self::assertSame('50000.00', $result->total->toDecimal());

        $this->entityManager->clear();

        /** @var Order|null $order */
        $order = $this->entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($order);
        self::assertTrue($order->isDraft());
        self::assertCount(1, $order->getItems());
        self::assertSame(
            '50000.00',
            $order->getTotal()->toDecimal(),
        );

        $remainingItem = $order->getItems()->first();

        self::assertInstanceOf(
            OrderItem::class,
            $remainingItem,
        );

        self::assertNotSame(
            $firstItemId,
            $remainingItem->getId(),
        );
    }

    public function testCanRemoveLastItemAndKeepsDraftOrder(): void
    {
        [$orderId, $itemId] = $this->createDraftOrderWithItems(
            onlyOneItem: true,
        );

        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $result = $handler(
            new RemoveDraftItemInput(
                orderId: $orderId,
                itemId: $itemId,
            ),
        );

        self::assertSame(0, $result->remainingItemCount);
        self::assertSame('0.00', $result->subtotal->toDecimal());
        self::assertSame('0.00', $result->total->toDecimal());
        self::assertSame(
            \App\Domain\Order\Enum\OrderStatus::DRAFT,
            $result->status,
        );

        $this->entityManager->clear();

        /** @var Order|null $order */
        $order = $this->entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($order);
        self::assertTrue($order->isDraft());
        self::assertCount(0, $order->getItems());
        self::assertSame(
            '0.00',
            $order->getTotal()->toDecimal(),
        );
    }

    public function testRejectsUnknownOrder(): void
    {
        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Order 999999999 was not found.',
        );

        $handler(
            new RemoveDraftItemInput(
                orderId: 999999999,
                itemId: 1,
            ),
        );
    }

    public function testRejectsUnknownItem(): void
    {
        [$orderId] = $this->createDraftOrderWithItems();

        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order item 999999999 was not found in order %d.',
                $orderId,
            ),
        );

        $handler(
            new RemoveDraftItemInput(
                orderId: $orderId,
                itemId: 999999999,
            ),
        );
    }

    public function testRejectsItemBelongingToAnotherOrder(): void
    {
        [$firstOrderId] = $this->createDraftOrderWithItems();

        [, $secondItemId] = $this->createDraftOrderWithItems(
            onlyOneItem: true,
        );

        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order item %d was not found in order %d.',
                $secondItemId,
                $firstOrderId,
            ),
        );

        $handler(
            new RemoveDraftItemInput(
                orderId: $firstOrderId,
                itemId: $secondItemId,
            ),
        );
    }

    public function testRejectsZeroOrderId(): void
    {
        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Order ID must be greater than zero.',
        );

        $handler(
            new RemoveDraftItemInput(
                orderId: 0,
                itemId: 1,
            ),
        );
    }

    public function testRejectsZeroItemId(): void
    {
        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Order item ID must be greater than zero.',
        );

        $handler(
            new RemoveDraftItemInput(
                orderId: 1,
                itemId: 0,
            ),
        );
    }

    public function testRejectsCompletedOrder(): void
    {
        [$orderId, $itemId] = $this->createDraftOrderWithItems(
            onlyOneItem: true,
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

        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Order items can only be changed while order is draft.',
        );

        $handler(
            new RemoveDraftItemInput(
                orderId: $orderId,
                itemId: $itemId,
            ),
        );
    }

    public function testDoesNotChangeStockOrCreateStockMovement(): void
    {
        [$orderId, $itemId, $productId] =
            $this->createDraftOrderWithItems(
                onlyOneItem: true,
                stock: 10,
            );

        /** @var Product|null $product */
        $product = $this->entityManager->find(
            Product::class,
            $productId,
        );

        self::assertNotNull($product);
        self::assertSame(10, $product->getStockQuantity());

        /** @var RemoveDraftItemHandler $handler */
        $handler = self::getContainer()->get(
            RemoveDraftItemHandler::class,
        );

        $handler(
            new RemoveDraftItemInput(
                orderId: $orderId,
                itemId: $itemId,
            ),
        );

        $this->entityManager->clear();

        /** @var Product|null $product */
        $product = $this->entityManager->find(
            Product::class,
            $productId,
        );

        self::assertNotNull($product);
        self::assertSame(10, $product->getStockQuantity());

        $count = (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(sm.id)')
            ->from(StockMovement::class, 'sm')
            ->where('IDENTITY(sm.product) = :productId')
            ->andWhere('IDENTITY(sm.order) = :orderId')
            ->setParameter('productId', $productId)
            ->setParameter('orderId', $orderId)
            ->getQuery()
            ->getSingleScalarResult();

        self::assertSame(0, $count);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function createDraftOrderWithItems(
        bool $onlyOneItem = false,
        int $stock = 10,
    ): array {
        $user = new User(
            'remove-item-'.bin2hex(random_bytes(6)),
        );

        $firstProduct = new Product(
            'Remove Product A',
            Money::fromDecimal('25000.00'),
        );

        $firstProduct->setStockQuantityForAdjustment($stock);

        $order = new Order(
            orderNumber: new OrderNumber(
                'ORD-REMOVE-'.strtoupper(
                    bin2hex(random_bytes(5)),
                ),
            ),
            user: $user,
        );

        $firstItem = new OrderItem(
            product: $firstProduct,
            quantity: 2,
            unitPrice: $firstProduct->getSellingPrice(),
        );

        $order->addItem($firstItem);

        if (!$onlyOneItem) {
            $secondProduct = new Product(
                'Remove Product B',
                Money::fromDecimal('50000.00'),
            );

            $secondProduct->setStockQuantityForAdjustment($stock);

            $secondItem = new OrderItem(
                product: $secondProduct,
                quantity: 1,
                unitPrice: $secondProduct->getSellingPrice(),
            );

            $order->addItem($secondItem);

            $this->entityManager->persist($secondProduct);
        }

        $this->entityManager->persist($user);
        $this->entityManager->persist($firstProduct);
        $this->entityManager->persist($order);

        $this->entityManager->flush();

        $orderId = $order->getId();
        $itemId = $firstItem->getId();
        $productId = $firstProduct->getId();

        self::assertNotNull($orderId);
        self::assertNotNull($itemId);
        self::assertNotNull($productId);

        return [$orderId, $itemId, $productId];
    }
}
