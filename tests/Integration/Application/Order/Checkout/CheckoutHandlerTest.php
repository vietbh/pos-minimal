<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Checkout;

use App\Application\Order\Command\Checkout\CheckoutHandlerEntryPoint;
use App\Application\Order\Command\Checkout\CheckoutInput;
use App\Application\Order\Command\Checkout\CheckoutItemInput;
use App\Application\Order\Command\Checkout\CheckoutPaymentInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Audit\AuditLog;
use App\Domain\Idempotency\Enum\IdempotencyStatus;
use App\Domain\Idempotency\IdempotencyRecord;
use App\Domain\Order\Enum\OrderStatus;
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

final class CheckoutHandlerTest extends IntegrationTestCase
{
    public function testSuccessfulCheckoutPersistsCompleteState(): void
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

        $user = new User(
            'checkout-golden-path-user-'.bin2hex(random_bytes(4)),
        );

        $product = new Product(
            'Golden Path Product',
            Money::fromDecimal('100000.00'),
        );

        $product->setStockQuantityForAdjustment(10);

        $entityManager->persist($user);
        $entityManager->persist($product);
        $entityManager->flush();

        $userId = $user->getId();
        $productId = $product->getId();

        self::assertNotNull($userId);
        self::assertNotNull($productId);

        $actorContextProvider->set(
            new ActorContext(
                userId: $userId,
                sessionId: null,
                requestId: 'checkout-golden-path',
            ),
        );

        $idempotencyKey = 'checkout-golden-path-'
            .bin2hex(random_bytes(16));

        $input = new CheckoutInput(
            items: [
                new CheckoutItemInput(
                    productId: $productId,
                    quantity: 2,
                ),
            ],
            customerId: null,
            payment: new CheckoutPaymentInput(
                method: PaymentMethod::CASH,
                amount: Money::fromDecimal('200000.00'),
            ),
            note: 'Golden path integration test',
            idempotencyKey: $idempotencyKey,
        );

        /** @var CheckoutHandlerEntryPoint $entryPoint */
        $entryPoint = $container->get(
            CheckoutHandlerEntryPoint::class,
        );

        try {
            $result = $entryPoint->handle($input);
        } finally {
            $actorContextProvider->clear();
        }

        /*
         * -------------------------------------------------------------
         * Result
         * -------------------------------------------------------------
         */

        self::assertGreaterThan(0, $result->orderId);
        self::assertNotSame('', $result->orderNumber);

        self::assertSame(
            '200000.00',
            $result->total->toDecimal(),
        );

        self::assertSame(
            '200000.00',
            $result->paidAmount->toDecimal(),
        );

        self::assertSame(
            '0.00',
            $result->debtAmount->toDecimal(),
        );

        self::assertSame(
            OrderStatus::COMPLETED,
            $result->status,
        );

        /*
         * -------------------------------------------------------------
         * Clear Unit Of Work before database verification.
         *
         * This ensures the assertions below hydrate fresh state from
         * the committed test database instead of relying on objects
         * already held by Doctrine's identity map.
         * -------------------------------------------------------------
         */

        $entityManager->clear();

        /** @var Order|null $order */
        $order = $entityManager->find(
            Order::class,
            $result->orderId,
        );

        self::assertNotNull($order);

        /*
         * -------------------------------------------------------------
         * Order
         * -------------------------------------------------------------
         */

        self::assertSame(
            $result->orderNumber,
            $order->getOrderNumber()->value(),
        );

        self::assertSame(
            OrderStatus::COMPLETED,
            $order->getStatus(),
        );

        self::assertSame(
            '200000.00',
            $order->getSubtotal()->toDecimal(),
        );

        self::assertSame(
            '200000.00',
            $order->getTotal()->toDecimal(),
        );

        self::assertSame(
            '200000.00',
            $order->getPaidAmount()->toDecimal(),
        );

        self::assertSame(
            '0.00',
            $order->getDebtAmount()->toDecimal(),
        );

        self::assertCount(
            1,
            $order->getItems(),
        );

        /*
         * -------------------------------------------------------------
         * OrderItem historical snapshot
         * -------------------------------------------------------------
         */

        $item = $order->getItems()->first();

        self::assertNotFalse($item);

        self::assertSame(
            2,
            $item->getQuantity(),
        );

        self::assertSame(
            '100000.00',
            $item->getUnitPrice()->toDecimal(),
        );

        self::assertSame(
            '200000.00',
            $item->getSubtotal()->toDecimal(),
        );

        self::assertSame(
            'Golden Path Product',
            $item->getProductName(),
        );

        /*
         * -------------------------------------------------------------
         * Payment
         * -------------------------------------------------------------
         */

        self::assertCount(
            1,
            $order->getPayments(),
        );

        $payment = $order->getPayments()->first();

        self::assertNotFalse($payment);

        self::assertSame(
            '200000.00',
            $payment->getAmount()->toDecimal(),
        );

        self::assertSame(
            PaymentMethod::CASH,
            $payment->getMethod(),
        );

        self::assertSame(
            $userId,
            $payment->getUser()->getId(),
        );

        /*
         * -------------------------------------------------------------
         * Product / Stock
         * -------------------------------------------------------------
         */

        /** @var Product|null $persistedProduct */
        $persistedProduct = $entityManager->find(
            Product::class,
            $productId,
        );

        self::assertNotNull($persistedProduct);

        self::assertSame(
            8,
            $persistedProduct->getStockQuantity(),
        );

        /*
         * -------------------------------------------------------------
         * StockMovement
         * -------------------------------------------------------------
         */

        $stockMovements = $entityManager
            ->getRepository(StockMovement::class)
            ->findBy(
                [
                    'product' => $productId,
                    'order' => $result->orderId,
                ],
                [
                    'id' => 'ASC',
                ],
            );

        self::assertCount(
            1,
            $stockMovements,
        );

        $stockMovement = $stockMovements[0];

        self::assertSame(
            StockMovementType::SALE,
            $stockMovement->getType(),
        );

        self::assertSame(
            10,
            $stockMovement->getQuantityBefore(),
        );

        self::assertSame(
            -2,
            $stockMovement->getQuantityChange(),
        );

        self::assertSame(
            8,
            $stockMovement->getQuantityAfter(),
        );

        self::assertSame(
            $userId,
            $stockMovement->getUser()->getId(),
        );

        self::assertSame(
            $result->orderId,
            $stockMovement->getOrder()?->getId(),
        );

        self::assertSame(
            'Checkout',
            $stockMovement->getReason(),
        );

        /*
         * -------------------------------------------------------------
         * AuditLog
         * -------------------------------------------------------------
         */

        $auditLogs = $entityManager
            ->getRepository(AuditLog::class)
            ->findBy(
                [
                    'user' => $userId,
                    'entityType' => 'Order',
                    'entityId' => (string) $result->orderId,
                ],
                [
                    'id' => 'ASC',
                ],
            );

        self::assertNotEmpty($auditLogs);

        $checkoutAudit = null;

        foreach ($auditLogs as $auditLog) {
            if ($auditLog->getAction() === 'ORDER_COMPLETED') {
                $checkoutAudit = $auditLog;
                break;
            }
        }

        self::assertNotNull(
            $checkoutAudit,
            'Checkout audit record was not persisted.',
        );

        /*
         * -------------------------------------------------------------
         * IdempotencyRecord
         * -------------------------------------------------------------
         */

        $idempotencyRecords = $entityManager
            ->getRepository(IdempotencyRecord::class)
            ->findBy(
                [
                    'user' => $userId,
                    'idempotencyKey' => $idempotencyKey,
                ],
            );

        self::assertCount(
            1,
            $idempotencyRecords,
        );

        $idempotencyRecord = $idempotencyRecords[0];

        self::assertSame(
            IdempotencyStatus::COMPLETED,
            $idempotencyRecord->getStatus(),
        );

        self::assertSame(
            200,
            $idempotencyRecord->getResponseStatus(),
        );

        $responseBody = $idempotencyRecord->getResponseBody();

        self::assertIsArray($responseBody);

        self::assertSame(
            'ORDER_COMPLETED',
            $checkoutAudit->getAction(),
        );

        self::assertSame(
            $userId,
            $checkoutAudit->getUser()?->getId(),
        );

        self::assertSame(
            'Order',
            $checkoutAudit->getEntityType(),
        );

        self::assertSame(
            (string) $result->orderId,
            $checkoutAudit->getEntityId(),
        );

        self::assertSame(
            $result->orderNumber,
            $checkoutAudit->getNewValues()['orderNumber'],
        );

        self::assertSame(
            OrderStatus::COMPLETED->value,
            $checkoutAudit->getNewValues()['status'],
        );

        self::assertSame(
            '200000.00',
            $checkoutAudit->getNewValues()['total'],
        );

        self::assertSame(
            '200000.00',
            $checkoutAudit->getNewValues()['paidAmount'],
        );

        self::assertSame(
            '0.00',
            $checkoutAudit->getNewValues()['debtAmount'],
        );

        self::assertSame(
            $result->orderId,
            (int) $responseBody['orderId'],
        );

        self::assertSame(
            $result->orderNumber,
            $responseBody['orderNumber'],
        );

        self::assertSame(
            '200000.00',
            $responseBody['total'],
        );

        self::assertSame(
            '200000.00',
            $responseBody['paidAmount'],
        );

        self::assertSame(
            '0.00',
            $responseBody['debtAmount'],
        );

        self::assertSame(
            OrderStatus::COMPLETED->value,
            $responseBody['status'],
        );
    }
}
