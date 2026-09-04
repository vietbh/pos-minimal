<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Draft;

use App\Application\Order\Command\AddProductToDraft\AddProductToDraftHandler;
use App\Application\Order\Command\AddProductToDraft\AddProductToDraftInput;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Order;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class AddProductToDraftTest extends IntegrationTestCase
{
    public function testAddsActiveProductToDraft(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        $user = new User(
            'draft-add-user-'.bin2hex(random_bytes(4)),
        );

        $product = new Product(
            'Draft Product',
            Money::fromDecimal('125000.00'),
        );

        $entityManager->persist($user);
        $entityManager->persist($product);

        $order = new Order(
            new OrderNumber(
                'ORD-TEST-'.strtoupper(bin2hex(random_bytes(6))),
            ),
            $user,
        );

        $entityManager->persist($order);
        $entityManager->flush();

        $orderId = $order->getId();
        $productId = $product->getId();

        self::assertNotNull($orderId);
        self::assertNotNull($productId);

        /** @var AddProductToDraftHandler $handler */
        $handler = $container->get(
            AddProductToDraftHandler::class,
        );

        $result = $handler(
            new AddProductToDraftInput(
                orderId: $orderId,
                productId: $productId,
                quantity: 2,
            ),
        );

        self::assertSame($orderId, $result->orderId);
        self::assertSame(
            OrderStatus::DRAFT->value,
            $result->status,
        );

        self::assertSame(
            '125000.00',
            $result->item['unitPrice'],
        );

        self::assertSame(
            2,
            $result->item['quantity'],
        );

        self::assertSame(
            '250000.00',
            $result->item['subtotal'],
        );

        self::assertSame(
            '250000.00',
            $result->subtotal,
        );

        self::assertSame(
            '250000.00',
            $result->total,
        );

        $entityManager->clear();

        /** @var Order|null $persistedOrder */
        $persistedOrder = $entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($persistedOrder);
        self::assertSame(
            OrderStatus::DRAFT,
            $persistedOrder->getStatus(),
        );

        self::assertCount(
            1,
            $persistedOrder->getItems(),
        );

        $item = $persistedOrder->getItems()->first();

        self::assertNotFalse($item);
        self::assertSame(2, $item->getQuantity());
        self::assertSame(
            '125000.00',
            $item->getUnitPrice()->toDecimal(),
        );
        self::assertSame(
            '250000.00',
            $item->getSubtotal()->toDecimal(),
        );
        self::assertSame(
            'Draft Product',
            $item->getProductName(),
        );
    }

    public function testAddingSameProductMergesIntoExistingLine(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        $user = new User(
            'draft-merge-user-'.bin2hex(random_bytes(4)),
        );

        $product = new Product(
            'Merge Product',
            Money::fromDecimal('50000.00'),
        );

        $entityManager->persist($user);
        $entityManager->persist($product);

        $order = new Order(
            new OrderNumber(
                'ORD-TEST-'.strtoupper(bin2hex(random_bytes(6))),
            ),
            $user,
        );

        $entityManager->persist($order);
        $entityManager->flush();

        $orderId = $order->getId();
        $productId = $product->getId();

        self::assertNotNull($orderId);
        self::assertNotNull($productId);

        /** @var AddProductToDraftHandler $handler */
        $handler = $container->get(
            AddProductToDraftHandler::class,
        );

        $handler(
            new AddProductToDraftInput(
                orderId: $orderId,
                productId: $productId,
                quantity: 2,
            ),
        );

        $result = $handler(
            new AddProductToDraftInput(
                orderId: $orderId,
                productId: $productId,
                quantity: 3,
            ),
        );

        self::assertSame(5, $result->item['quantity']);
        self::assertSame(
            '250000.00',
            $result->item['subtotal'],
        );
        self::assertSame(
            '250000.00',
            $result->total,
        );

        self::assertCount(
            1,
            $order->getItems(),
        );
    }

    public function testUsesSellingPriceAsHistoricalSnapshot(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        $user = new User(
            'draft-snapshot-user-'.bin2hex(random_bytes(4)),
        );

        $product = new Product(
            'Snapshot Product',
            Money::fromDecimal('100000.00'),
        );

        $entityManager->persist($user);
        $entityManager->persist($product);

        $order = new Order(
            new OrderNumber(
                'ORD-TEST-'.strtoupper(bin2hex(random_bytes(6))),
            ),
            $user,
        );

        $entityManager->persist($order);
        $entityManager->flush();

        $orderId = $order->getId();
        $productId = $product->getId();

        self::assertNotNull($orderId);
        self::assertNotNull($productId);

        /** @var AddProductToDraftHandler $handler */
        $handler = $container->get(
            AddProductToDraftHandler::class,
        );

        $handler(
            new AddProductToDraftInput(
                orderId: $orderId,
                productId: $productId,
                quantity: 1,
            ),
        );

        $product->changeSellingPrice(
            Money::fromDecimal('150000.00'),
        );

        $entityManager->flush();
        $entityManager->clear();

        /** @var Order|null $persistedOrder */
        $persistedOrder = $entityManager->find(
            Order::class,
            $orderId,
        );

        self::assertNotNull($persistedOrder);

        $item = $persistedOrder->getItems()->first();

        self::assertNotFalse($item);

        self::assertSame(
            '100000.00',
            $item->getUnitPrice()->toDecimal(),
        );

        self::assertSame(
            '100000.00',
            $item->getSubtotal()->toDecimal(),
        );
    }

    public function testRejectsInactiveProduct(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        $user = new User(
            'draft-inactive-user-'.bin2hex(random_bytes(4)),
        );

        $product = new Product(
            'Inactive Product',
            Money::fromDecimal('10000.00'),
        );

        $product->deactivate();

        $order = new Order(
            new OrderNumber(
                'ORD-TEST-'.strtoupper(bin2hex(random_bytes(6))),
            ),
            $user,
        );

        $entityManager->persist($user);
        $entityManager->persist($product);
        $entityManager->persist($order);
        $entityManager->flush();

        /** @var AddProductToDraftHandler $handler */
        $handler = $container->get(
            AddProductToDraftHandler::class,
        );

        self::expectException(\DomainException::class);
        self::expectExceptionMessage(
            'Inactive products cannot be added to an order.',
        );

        $handler(
            new AddProductToDraftInput(
                orderId: $order->getId() ?? throw new \LogicException(),
                productId: $product->getId() ?? throw new \LogicException(),
                quantity: 1,
            ),
        );
    }

    public function testRejectsUnknownProduct(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        $user = new User(
            'draft-unknown-product-user-'.bin2hex(random_bytes(4)),
        );

        $order = new Order(
            new OrderNumber(
                'ORD-TEST-'.strtoupper(bin2hex(random_bytes(6))),
            ),
            $user,
        );

        $entityManager->persist($user);
        $entityManager->persist($order);
        $entityManager->flush();

        /** @var AddProductToDraftHandler $handler */
        $handler = $container->get(
            AddProductToDraftHandler::class,
        );

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Product not found.');

        $handler(
            new AddProductToDraftInput(
                orderId: $order->getId() ?? throw new \LogicException(),
                productId: 999999999,
                quantity: 1,
            ),
        );
    }

    public function testRejectsUnknownOrder(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        $product = new Product(
            'Unknown Order Product',
            Money::fromDecimal('10000.00'),
        );

        $entityManager->persist($product);
        $entityManager->flush();

        /** @var AddProductToDraftHandler $handler */
        $handler = $container->get(
            AddProductToDraftHandler::class,
        );

        self::expectException(\DomainException::class);
        self::expectExceptionMessage('Order not found.');

        $handler(
            new AddProductToDraftInput(
                orderId: 999999999,
                productId: $product->getId()
                    ?? throw new \LogicException(),
                quantity: 1,
            ),
        );
    }
}
