<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Draft;

use App\Application\Order\Command\ChangeDraftItemQuantity\ChangeDraftItemQuantityHandler;
use App\Application\Order\Command\ChangeDraftItemQuantity\ChangeDraftItemQuantityInput;
use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\StockMovement;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChangeDraftItemQuantityTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class,
        );
    }

    public function testChangesQuantityAndRecalculatesTotals(): void
    {
        [$orderId, $itemId, $productId] = $this->createDraftOrder(
            price: '25000.00',
            initialQuantity: 2,
        );

        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
        );

        $result = $handler(
            new ChangeDraftItemQuantityInput(
                orderId: $orderId,
                itemId: $itemId,
                quantity: 5,
            ),
        );

        self::assertSame($orderId, $result->orderId);
        self::assertSame(5, $result->item['quantity']);
        self::assertSame('25000.00', $result->item['unitPrice']);
        self::assertSame('125000.00', $result->item['subtotal']);
        self::assertSame('125000.00', $result->subtotal->toDecimal());
        self::assertSame('125000.00', $result->total->toDecimal());

        $this->entityManager->clear();

        /** @var Order|null $order */
        $order = $this->entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($order);
        self::assertCount(1, $order->getItems());
        self::assertSame(
            '125000.00',
            $order->getTotal()->toDecimal(),
        );

        $item = $order->getItems()->first();

        self::assertInstanceOf(OrderItem::class, $item);
        self::assertSame($itemId, $item->getId());
        self::assertSame(5, $item->getQuantity());
        self::assertSame(
            '125000.00',
            $item->getSubtotal()->toDecimal(),
        );
        self::assertSame(
            $productId,
            $item->getProduct()->getId(),
        );
    }

    public function testUsesHistoricalUnitPriceWhenProductPriceChanges(): void
    {
        [$orderId, $itemId, $productId] = $this->createDraftOrder(
            price: '30000.00',
            initialQuantity: 2,
        );

        /** @var Product|null $product */
        $product = $this->entityManager->find(
            Product::class,
            $productId,
        );

        self::assertNotNull($product);

        $product->changeSellingPrice(
            Money::fromDecimal('50000.00'),
        );

        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
        );

        $result = $handler(
            new ChangeDraftItemQuantityInput(
                orderId: $orderId,
                itemId: $itemId,
                quantity: 3,
            ),
        );

        self::assertSame('30000.00', $result->item['unitPrice']);
        self::assertSame('90000.00', $result->item['subtotal']);
        self::assertSame('90000.00', $result->total->toDecimal());
    }

    public function testRejectsZeroQuantity(): void
    {
        [$orderId, $itemId] = $this->createDraftOrder();

        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Order item quantity must be greater than zero.',
        );

        $handler(
            new ChangeDraftItemQuantityInput(
                orderId: $orderId,
                itemId: $itemId,
                quantity: 0,
            ),
        );
    }

    public function testRejectsNegativeQuantity(): void
    {
        [$orderId, $itemId] = $this->createDraftOrder();

        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
        );

        $this->expectException(\InvalidArgumentException::class);

        $handler(
            new ChangeDraftItemQuantityInput(
                orderId: $orderId,
                itemId: $itemId,
                quantity: -1,
            ),
        );
    }

    public function testRejectsUnknownOrder(): void
    {
        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Order 999999999 was not found.',
        );

        $handler(
            new ChangeDraftItemQuantityInput(
                orderId: 999999999,
                itemId: 1,
                quantity: 2,
            ),
        );
    }

    public function testRejectsUnknownItem(): void
    {
        [$orderId] = $this->createDraftOrder();

        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Order item 999999999 was not found in order %d.',
                $orderId,
            ),
        );

        $handler(
            new ChangeDraftItemQuantityInput(
                orderId: $orderId,
                itemId: 999999999,
                quantity: 2,
            ),
        );
    }

    public function testRejectsItemBelongingToAnotherOrder(): void
    {
        [$firstOrderId] = $this->createDraftOrder(
            price: '10000.00',
            initialQuantity: 1,
        );

        [, $secondItemId] = $this->createDraftOrder(
            price: '20000.00',
            initialQuantity: 1,
        );

        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
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
            new ChangeDraftItemQuantityInput(
                orderId: $firstOrderId,
                itemId: $secondItemId,
                quantity: 2,
            ),
        );
    }

    public function testRejectsCompletedOrder(): void
    {
        [$orderId, $itemId] = $this->createDraftOrder(
            price: '10000.00',
            initialQuantity: 1,
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

        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Order items can only be changed while order is draft.',
        );

        $handler(
            new ChangeDraftItemQuantityInput(
                orderId: $orderId,
                itemId: $itemId,
                quantity: 2,
            ),
        );
    }

    public function testDoesNotChangeStockOrCreateStockMovement(): void
    {
        [$orderId, $itemId, $productId] = $this->createDraftOrder(
            price: '75000.00',
            initialQuantity: 2,
            stock: 10,
        );

        /** @var ChangeDraftItemQuantityHandler $handler */
        $handler = self::getContainer()->get(
            ChangeDraftItemQuantityHandler::class,
        );

        $handler(
            new ChangeDraftItemQuantityInput(
                orderId: $orderId,
                itemId: $itemId,
                quantity: 5,
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
    private function createDraftOrder(
        string $price = '25000.00',
        int $initialQuantity = 2,
        int $stock = 10,
    ): array {
        $user = new User(
            'change-qty-'.bin2hex(random_bytes(6)),
        );

        $product = new Product(
            'Change Quantity Product',
            Money::fromDecimal($price),
        );

        $product->setStockQuantityForAdjustment($stock);

        $order = new Order(
            orderNumber: new OrderNumber(
                'ORD-CHANGE-'.strtoupper(bin2hex(random_bytes(5))),
            ),
            user: $user,
        );

        $item = new OrderItem(
            product: $product,
            quantity: $initialQuantity,
            unitPrice: $product->getSellingPrice(),
        );

        $order->addItem($item);

        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $this->entityManager->persist($order);

        $this->entityManager->flush();

        $orderId = $order->getId();
        $itemId = $item->getId();
        $productId = $product->getId();

        self::assertNotNull($orderId);
        self::assertNotNull($itemId);
        self::assertNotNull($productId);

        return [$orderId, $itemId, $productId];
    }
}
