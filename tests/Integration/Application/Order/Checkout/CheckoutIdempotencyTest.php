<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Checkout;

use App\Application\Common\Idempotency\IdempotencyConflict;
use App\Application\Order\Command\Checkout\CheckoutHandlerEntryPoint;
use App\Application\Order\Command\Checkout\CheckoutInput;
use App\Application\Order\Command\Checkout\CheckoutItemInput;
use App\Application\Order\Command\Checkout\CheckoutPaymentInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Audit\AuditLog;
use App\Domain\Idempotency\Enum\IdempotencyStatus;
use App\Domain\Idempotency\IdempotencyRecord;
use App\Domain\Order\Order;
use App\Domain\Order\Payment;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\StockMovement;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class CheckoutIdempotencyTest extends IntegrationTestCase
{
    public function testSameKeyAndSameRequestReplaysCompletedCheckout(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        /** @var RuntimeActorContextProvider $actorContextProvider */
        $actorContextProvider = $container->get(
            RuntimeActorContextProvider::class,
        );

        /** @var CheckoutHandlerEntryPoint $entryPoint */
        $entryPoint = $container->get(
            CheckoutHandlerEntryPoint::class,
        );

        $user = new User(
            'checkout-replay-user-'.bin2hex(random_bytes(4)),
        );

        $product = new Product(
            'Checkout Replay Product',
            Money::fromDecimal('100000.00'),
        );

        $product->setStockQuantityForAdjustment(10);

        $entityManager->persist($user);
        $entityManager->persist($product);
        $entityManager->flush();

        self::assertNotNull($user->getId());
        self::assertNotNull($product->getId());

        $actorContextProvider->set(
            new ActorContext(
                userId: $user->getId(),
                sessionId: null,
                requestId: 'checkout-idempotency-replay',
            ),
        );

        $idempotencyKey = 'checkout-replay-'.bin2hex(
                random_bytes(16),
            );

        $input = $this->createInput(
            productId: $product->getId(),
            quantity: 2,
            paymentAmount: '200000.00',
            idempotencyKey: $idempotencyKey,
        );

        try {
            $firstResult = $entryPoint->handle($input);

            $entityManager->clear();

            $firstProduct = $entityManager->find(
                Product::class,
                $product->getId(),
            );

            self::assertNotNull($firstProduct);

            self::assertSame(
                8,
                $firstProduct->getStockQuantity(),
            );

            self::assertSame(
                1,
                $this->countOrdersForUser(
                    $entityManager,
                    $user,
                ),
            );

            self::assertSame(
                1,
                $this->countPaymentsForUser(
                    $entityManager,
                    $user,
                ),
            );
            self::assertSame(
                1,
                $this->countSaleMovements($entityManager),
            );

            self::assertSame(
                1,
                $this->countCompletedCheckoutAudits($entityManager),
            );

            self::assertSame(
                1,
                $this->countIdempotencyRecords(
                    $entityManager,
                    $idempotencyKey,
                ),
            );

            $entityManager->clear();

            $secondResult = $entryPoint->handle($input);

            self::assertSame(
                $firstResult->orderId,
                $secondResult->orderId,
            );

            self::assertSame(
                $firstResult->orderNumber,
                $secondResult->orderNumber,
            );

            self::assertSame(
                $firstResult->total->toDecimal(),
                $secondResult->total->toDecimal(),
            );

            self::assertSame(
                $firstResult->paidAmount->toDecimal(),
                $secondResult->paidAmount->toDecimal(),
            );

            self::assertSame(
                $firstResult->debtAmount->toDecimal(),
                $secondResult->debtAmount->toDecimal(),
            );

            $entityManager->clear();

            $productAfterReplay = $entityManager->find(
                Product::class,
                $product->getId(),
            );

            self::assertNotNull($productAfterReplay);

            self::assertSame(
                8,
                $productAfterReplay->getStockQuantity(),
                'Replay must not mutate stock again.',
            );

            self::assertSame(
                1,
                $this->countOrders($entityManager),
                'Replay must not create another order.',
            );

            self::assertSame(
                1,
                $this->countPayments($entityManager),
                'Replay must not create another payment.',
            );

            self::assertSame(
                1,
                $this->countSaleMovements($entityManager),
                'Replay must not create another stock movement.',
            );

            self::assertSame(
                1,
                $this->countCompletedCheckoutAudits($entityManager),
                'Replay must not create another checkout audit.',
            );

            $records = $this->findIdempotencyRecords(
                $entityManager,
                $idempotencyKey,
            );

            self::assertCount(1, $records);

            self::assertSame(
                IdempotencyStatus::COMPLETED,
                $records[0]->getStatus(),
            );

            self::assertSame(
                200,
                $records[0]->getResponseStatus(),
            );

            self::assertNotNull(
                $records[0]->getResponseBody(),
            );
        } finally {
            $actorContextProvider->clear();
        }
    }

    public function testSameKeyWithDifferentRequestIsRejected(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        /** @var RuntimeActorContextProvider $actorContextProvider */
        $actorContextProvider = $container->get(
            RuntimeActorContextProvider::class,
        );

        /** @var CheckoutHandlerEntryPoint $entryPoint */
        $entryPoint = $container->get(
            CheckoutHandlerEntryPoint::class,
        );

        $user = new User(
            'checkout-conflict-user-'.bin2hex(random_bytes(4)),
        );

        $product = new Product(
            'Checkout Conflict Product',
            Money::fromDecimal('100000.00'),
        );

        $product->setStockQuantityForAdjustment(10);

        $entityManager->persist($user);
        $entityManager->persist($product);
        $entityManager->flush();

        self::assertNotNull($user->getId());
        self::assertNotNull($product->getId());

        $actorContextProvider->set(
            new ActorContext(
                userId: $user->getId(),
                sessionId: null,
                requestId: 'checkout-idempotency-conflict',
            ),
        );

        $idempotencyKey = 'checkout-conflict-'.bin2hex(
                random_bytes(16),
            );

        $firstInput = $this->createInput(
            productId: $product->getId(),
            quantity: 1,
            paymentAmount: '100000.00',
            idempotencyKey: $idempotencyKey,
        );

        try {
            $firstResult = $entryPoint->handle($firstInput);

            $entityManager->clear();

            $conflictingInput = $this->createInput(
                productId: $product->getId(),
                quantity: 2,
                paymentAmount: '200000.00',
                idempotencyKey: $idempotencyKey,
            );

            try {
                $entryPoint->handle($conflictingInput);

                self::fail(
                    'A reused idempotency key with a different request '
                    .'must be rejected.',
                );
            } catch (IdempotencyConflict) {
                // Expected.
            }

            $entityManager->clear();

            self::assertSame(
                1,
                $this->countOrders($entityManager),
            );

            self::assertSame(
                1,
                $this->countPayments($entityManager),
            );

            self::assertSame(
                1,
                $this->countSaleMovements($entityManager),
            );

            self::assertSame(
                1,
                $this->countCompletedCheckoutAudits($entityManager),
            );

            $persistedProduct = $entityManager->find(
                Product::class,
                $product->getId(),
            );

            self::assertNotNull($persistedProduct);

            self::assertSame(
                9,
                $persistedProduct->getStockQuantity(),
            );

            $records = $this->findIdempotencyRecords(
                $entityManager,
                $idempotencyKey,
            );

            self::assertCount(1, $records);

            self::assertSame(
                IdempotencyStatus::COMPLETED,
                $records[0]->getStatus(),
            );

            self::assertSame(
                $firstResult->orderId,
                $this->findSingleOrder($entityManager)->getId(),
            );
        } finally {
            $actorContextProvider->clear();
        }
    }

    public function testFailedIdempotencyKeyIsTerminal(): void
    {
        $container = self::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(
            EntityManagerInterface::class,
        );

        /** @var RuntimeActorContextProvider $actorContextProvider */
        $actorContextProvider = $container->get(
            RuntimeActorContextProvider::class,
        );

        /** @var CheckoutHandlerEntryPoint $entryPoint */
        $entryPoint = $container->get(
            CheckoutHandlerEntryPoint::class,
        );

        $user = new User(
            'checkout-failed-user-'.bin2hex(random_bytes(4)),
        );

        $product = new Product(
            'Checkout Failed Product',
            Money::fromDecimal('100000.00'),
        );

        $product->setStockQuantityForAdjustment(10);

        $entityManager->persist($user);
        $entityManager->persist($product);
        $entityManager->flush();

        self::assertNotNull($user->getId());
        self::assertNotNull($product->getId());

        $actorContextProvider->set(
            new ActorContext(
                userId: $user->getId(),
                sessionId: null,
                requestId: 'checkout-idempotency-failed',
            ),
        );

        $idempotencyKey = 'checkout-failed-'.bin2hex(
                random_bytes(16),
            );

        /*
         * Total = 100000.00.
         * Payment = 200000.00.
         *
         * Domain validation must reject the checkout.
         */
        $input = $this->createInput(
            productId: $product->getId(),
            quantity: 1,
            paymentAmount: '200000.00',
            idempotencyKey: $idempotencyKey,
        );

        try {
            try {
                $entryPoint->handle($input);

                self::fail(
                    'Checkout was expected to fail.',
                );
            } catch (\Throwable) {
                // Expected business/application failure.
            }

            $entityManager->clear();

            $records = $this->findIdempotencyRecords(
                $entityManager,
                $idempotencyKey,
            );

            self::assertCount(
                1,
                $records,
                'Failed checkout must leave an idempotency record.',
            );

            $record = $records[0];

            self::assertSame(
                IdempotencyStatus::FAILED,
                $record->getStatus(),
            );

            self::assertNotNull(
                $record->getResponseStatus(),
            );

            self::assertGreaterThanOrEqual(
                400,
                $record->getResponseStatus(),
            );

            /*
             * The business transaction must not partially commit.
             */
            self::assertSame(
                0,
                $this->countOrders($entityManager),
            );

            self::assertSame(
                0,
                $this->countPayments($entityManager),
            );

            self::assertSame(
                0,
                $this->countSaleMovements($entityManager),
            );

            self::assertSame(
                0,
                $this->countCompletedCheckoutAudits($entityManager),
            );

            $persistedProduct = $entityManager->find(
                Product::class,
                $product->getId(),
            );

            self::assertNotNull($persistedProduct);

            self::assertSame(
                10,
                $persistedProduct->getStockQuantity(),
            );

            /*
             * FAILED is terminal according to DoctrineIdempotencyPort.
             *
             * The same key/request must NOT execute checkout again.
             */
            $entityManager->clear();

            $this->expectException(\DomainException::class);

            $entryPoint->handle($input);
        } finally {
            $actorContextProvider->clear();
        }
    }

    private function createInput(
        int $productId,
        int $quantity,
        string $paymentAmount,
        string $idempotencyKey,
    ): CheckoutInput {
        return new CheckoutInput(
            items: [
                new CheckoutItemInput(
                    productId: $productId,
                    quantity: $quantity,
                ),
            ],
            customerId: null,
            payment: new CheckoutPaymentInput(
                method: PaymentMethod::CASH,
                amount: Money::fromDecimal($paymentAmount),
            ),
            note: 'Idempotency integration test',
            idempotencyKey: $idempotencyKey,
        );
    }

    private function countOrders(
        EntityManagerInterface $entityManager,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(orderEntity.id)')
            ->from(Order::class, 'orderEntity')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPayments(
        EntityManagerInterface $entityManager,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(payment.id)')
            ->from(Payment::class, 'payment')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countSaleMovements(
        EntityManagerInterface $entityManager,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(movement.id)')
            ->from(StockMovement::class, 'movement')
            ->where('movement.type = :type')
            ->setParameter(
                'type',
                StockMovementType::SALE,
            )
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countCompletedCheckoutAudits(
        EntityManagerInterface $entityManager,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(audit.id)')
            ->from(AuditLog::class, 'audit')
            ->where('audit.action = :action')
            ->andWhere('audit.entityType = :entityType')
            ->setParameter(
                'action',
                'ORDER_COMPLETED',
            )
            ->setParameter(
                'entityType',
                'Order',
            )
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<IdempotencyRecord>
     */
    private function findIdempotencyRecords(
        EntityManagerInterface $entityManager,
        string $idempotencyKey,
    ): array {
        /** @var list<IdempotencyRecord> $records */
        $records = $entityManager
            ->getRepository(IdempotencyRecord::class)
            ->findBy([
                'idempotencyKey' => $idempotencyKey,
            ]);

        return $records;
    }

    private function countIdempotencyRecords(
        EntityManagerInterface $entityManager,
        string $idempotencyKey,
    ): int {
        return count(
            $this->findIdempotencyRecords(
                $entityManager,
                $idempotencyKey,
            ),
        );
    }

    private function findSingleOrder(
        EntityManagerInterface $entityManager,
    ): Order {
        $order = $entityManager
            ->getRepository(Order::class)
            ->findOneBy([]);

        if ($order === null) {
            throw new \LogicException(
                'Expected one persisted order.',
            );
        }

        return $order;
    }

    private function countOrdersForUser(
        EntityManagerInterface $entityManager,
        User $user,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(orderEntity.id)')
            ->from(Order::class, 'orderEntity')
            ->where('IDENTITY(orderEntity.user) = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countPaymentsForUser(
        EntityManagerInterface $entityManager,
        User $user,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(payment.id)')
            ->from(Payment::class, 'payment')
            ->where('IDENTITY(payment.user) = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countSaleMovementsForUser(
        EntityManagerInterface $entityManager,
        User $user,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(movement.id)')
            ->from(StockMovement::class, 'movement')
            ->where('movement.type = :type')
            ->andWhere('IDENTITY(movement.user) = :userId')
            ->setParameter(
                'type',
                StockMovementType::SALE,
            )
            ->setParameter(
                'userId',
                $user->getId(),
            )
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countCheckoutAuditsForOrder(
        EntityManagerInterface $entityManager,
        int $orderId,
    ): int {
        return (int) $entityManager
            ->createQueryBuilder()
            ->select('COUNT(audit.id)')
            ->from(AuditLog::class, 'audit')
            ->where('audit.action = :action')
            ->andWhere('audit.entityType = :entityType')
            ->andWhere('audit.entityId = :entityId')
            ->setParameter(
                'action',
                'ORDER_COMPLETED',
            )
            ->setParameter(
                'entityType',
                'Order',
            )
            ->setParameter(
                'entityId',
                (string) $orderId,
            )
            ->getQuery()
            ->getSingleScalarResult();
    }

}
