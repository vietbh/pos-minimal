<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Lifecycle;

use App\Application\Order\Command\CancelOrder\CancelOrderHandlerEntryPoint;
use App\Application\Order\Command\CancelOrder\CancelOrderInput;
use App\Application\Order\Command\Checkout\CheckoutHandlerEntryPoint;
use App\Application\Order\Command\Checkout\CheckoutInput;
use App\Application\Order\Command\Checkout\CheckoutItemInput;
use App\Application\Order\Command\Checkout\CheckoutPaymentInput;
use App\Application\Order\Command\RefundOrder\RefundOrderHandlerEntryPoint;
use App\Application\Order\Command\RefundOrder\RefundOrderInput;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Audit\AuditLog;
use App\Domain\Debt\Debt;
use App\Domain\Debt\Enum\DebtStatus;
use App\Domain\Order\Enum\FinancialReversalType;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Order;
use App\Domain\Order\OrderFinancialReversal;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\Stock\StockMovement;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class OrderLifecycleTest extends IntegrationTestCase
{
    public function testCancelRestoresStockReversesDebtAndRecordsHistory(): void
    {
        [$user, $product, $order] = $this->createCompletedOrder('40000.00', 2, true);
        $context = self::getContainer()->get(RuntimeActorContextProvider::class);
        $context->set(new ActorContext($user->getId(), null, 'cancel-test'));

        try {
            $result = self::getContainer()->get(CancelOrderHandlerEntryPoint::class)->handle(
                new CancelOrderInput($order->getId(), 'Customer cancelled', 'cancel-'.bin2hex(random_bytes(8)))
            );
        } finally {
            $context->clear();
        }

        $this->entityManager->clear();
        $persistedOrder = $this->entityManager->find(Order::class, $result->orderId);
        $persistedProduct = $this->entityManager->find(Product::class, $product->getId());
        $debt = $this->entityManager->getRepository(Debt::class)->findOneBy(['order' => $result->orderId]);
        $reversal = $this->entityManager->getRepository(OrderFinancialReversal::class)->findOneBy(['order' => $result->orderId]);
        $movements = $this->entityManager->getRepository(StockMovement::class)->findBy(['order' => $result->orderId], ['id' => 'ASC']);
        $audits = $this->entityManager->getRepository(AuditLog::class)->findBy(['entityType' => 'Order', 'entityId' => (string) $result->orderId], ['id' => 'ASC']);

        self::assertNotNull($persistedOrder);
        self::assertNotNull($persistedProduct);
        self::assertNotNull($debt);
        self::assertNotNull($reversal);
        self::assertSame(OrderStatus::CANCELLED, $persistedOrder->getStatus());
        self::assertSame(10, $persistedProduct->getStockQuantity());
        self::assertSame(DebtStatus::REVERSED, $debt->getStatus());
        self::assertSame('40000.00', $reversal->getAmount()->toDecimal());
        self::assertSame(FinancialReversalType::CANCEL, $reversal->getType());
        self::assertCount(2, $movements);
        self::assertSame(StockMovementType::SALE, $movements[0]->getType());
        self::assertSame(StockMovementType::SALE_REVERSAL, $movements[1]->getType());
        self::assertSame(2, $movements[1]->getQuantityChange());
        self::assertNotEmpty(array_filter($audits, static fn (AuditLog $a): bool => $a->getAction() === 'ORDER_CANCEL'));
    }

    public function testRefundRestoresStockAndCreatesImmutableFinancialReversal(): void
    {
        [$user, $product, $order] = $this->createCompletedOrder('80000.00', 2, false);
        $context = self::getContainer()->get(RuntimeActorContextProvider::class);
        $context->set(new ActorContext($user->getId(), null, 'refund-test'));

        try {
            $result = self::getContainer()->get(RefundOrderHandlerEntryPoint::class)->handle(
                new RefundOrderInput($order->getId(), 'Customer returned goods', 'refund-'.bin2hex(random_bytes(8)))
            );
        } finally {
            $context->clear();
        }

        $this->entityManager->clear();
        $persistedOrder = $this->entityManager->find(Order::class, $result->orderId);
        $persistedProduct = $this->entityManager->find(Product::class, $product->getId());
        $reversal = $this->entityManager->getRepository(OrderFinancialReversal::class)->findOneBy(['order' => $result->orderId]);
        $payments = $this->entityManager->getRepository(\App\Domain\Order\Payment::class)->findBy(['order' => $result->orderId]);

        self::assertNotNull($persistedOrder);
        self::assertNotNull($persistedProduct);
        self::assertNotNull($reversal);
        self::assertSame(OrderStatus::REFUNDED, $persistedOrder->getStatus());
        self::assertSame(10, $persistedProduct->getStockQuantity());
        self::assertSame(FinancialReversalType::REFUND, $reversal->getType());
        self::assertSame('80000.00', $reversal->getAmount()->toDecimal());
        self::assertCount(1, $payments);
        self::assertSame('80000.00', $payments[0]->getAmount()->toDecimal());
    }

    public function testSecondCancelIsRejectedWithoutSecondReversal(): void
    {
        [$user, $product, $order] = $this->createCompletedOrder('40000.00', 1, false);
        $context = self::getContainer()->get(RuntimeActorContextProvider::class);
        $key = 'cancel-once-'.bin2hex(random_bytes(8));
        $context->set(new ActorContext($user->getId(), null, 'cancel-once'));
        try {
            $entry = self::getContainer()->get(CancelOrderHandlerEntryPoint::class);
            $entry->handle(new CancelOrderInput($order->getId(), 'first', $key));
            $this->entityManager->clear();
            self::expectException(\DomainException::class);
            $entry->handle(new CancelOrderInput($order->getId(), 'second', 'cancel-second-'.bin2hex(random_bytes(8))));
        } finally {
            $context->clear();
        }
        $this->entityManager->clear();
        self::assertCount(1, $this->entityManager->getRepository(OrderFinancialReversal::class)->findBy(['order' => $order->getId()]));
        self::assertCount(1, $this->entityManager->getRepository(StockMovement::class)->findBy(['order' => $order->getId(), 'type' => StockMovementType::SALE_REVERSAL]));
    }

    /** @return array{0: User, 1: Product, 2: Order} */
    private function createCompletedOrder(string $paymentAmount, int $quantity, bool $withDebt): array
    {
        $user = new User('lifecycle-'.bin2hex(random_bytes(5)));
        $product = new Product('Lifecycle Product', Money::fromDecimal('40000.00'));
        $product->setStockQuantityForAdjustment(10);
        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $customer = null;
        if ($withDebt) {
            $customer = new \App\Domain\Customer\Customer('Lifecycle Customer');
            $this->entityManager->persist($customer);
        }
        $this->entityManager->flush();

        $context = self::getContainer()->get(RuntimeActorContextProvider::class);
        $context->set(new ActorContext($user->getId(), null, 'checkout-fixture'));
        try {
            $result = self::getContainer()->get(CheckoutHandlerEntryPoint::class)->handle(new CheckoutInput(
                [new CheckoutItemInput($product->getId(), $quantity)],
                $customer?->getId(),
                new CheckoutPaymentInput(PaymentMethod::CASH, Money::fromDecimal($paymentAmount)),
                'fixture',
                'fixture-'.bin2hex(random_bytes(8)),
            ));
        } finally {
            $context->clear();
        }
        $this->entityManager->clear();
        $order = $this->entityManager->find(Order::class, $result->orderId);
        self::assertNotNull($order);
        return [$this->entityManager->find(User::class, $user->getId()), $this->entityManager->find(Product::class, $product->getId()), $order];
    }
}
