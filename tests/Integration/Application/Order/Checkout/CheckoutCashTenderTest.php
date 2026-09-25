<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Order\Checkout;

use App\Application\Order\Command\Checkout\CheckoutHandlerEntryPoint;
use App\Application\Order\Command\Checkout\CheckoutInput;
use App\Application\Order\Command\Checkout\CheckoutItemInput;
use App\Application\Order\Command\Checkout\CheckoutPaymentInput;
use App\Application\Order\Command\Checkout\CheckoutResult;
use App\Application\Security\ActorContext;
use App\Application\Security\RuntimeActorContextProvider;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\Order;
use App\Domain\Order\Payment;
use App\Domain\Customer\Customer;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use App\Tests\Integration\IntegrationTestCase;

final class CheckoutCashTenderTest extends IntegrationTestCase
{
    public function testExactCashTenderCompletesWithNoChange(): void
    {
        $user = new User('cash-exact-'.bin2hex(random_bytes(4)));
        $product = new Product('Cash exact product', Money::fromDecimal('135000.00'));
        $product->setStockQuantityForAdjustment(5);

        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $result = $this->checkout(
            $user,
            $product,
            '135000.00',
            '135000.00',
        );

        self::assertSame('135000.00', $result->total->toDecimal());
        self::assertSame('135000.00', $result->paidAmount->toDecimal());
        self::assertSame('135000.00', $result->tenderedAmount->toDecimal());
        self::assertSame('0.00', $result->changeAmount->toDecimal());
        self::assertSame('0.00', $result->debtAmount->toDecimal());
        self::assertSame(OrderStatus::COMPLETED, $result->status);
    }

    public function testZeroCashTenderCompletesAsDebtWhenCustomerIsProvided(): void
    {
        $user = new User('cash-zero-debt-'.bin2hex(random_bytes(4)));
        $customer = new Customer('Cash zero debt customer');
        $product = new Product('Cash zero debt product', Money::fromDecimal('135000.00'));
        $product->setStockQuantityForAdjustment(5);

        $this->entityManager->persist($user);
        $this->entityManager->persist($customer);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $result = $this->checkout(
            $user,
            $product,
            '0.00',
            '0.00',
            $customer,
        );

        self::assertSame('135000.00', $result->total->toDecimal());
        self::assertSame('0.00', $result->paidAmount->toDecimal());
        self::assertSame('0.00', $result->tenderedAmount->toDecimal());
        self::assertSame('135000.00', $result->debtAmount->toDecimal());
        self::assertSame(OrderStatus::COMPLETED, $result->status);
    }

    public function testCashOverpaymentAppliesOnlyOrderTotalAndReturnsChange(): void
    {
        $user = new User('cash-over-'.bin2hex(random_bytes(4)));
        $product = new Product('Cash over product', Money::fromDecimal('135000.00'));
        $product->setStockQuantityForAdjustment(5);

        $this->entityManager->persist($user);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $result = $this->checkout(
            $user,
            $product,
            '135000.00',
            '200000.00',
        );

        self::assertSame('135000.00', $result->total->toDecimal());
        self::assertSame('135000.00', $result->paidAmount->toDecimal());
        self::assertSame('200000.00', $result->tenderedAmount->toDecimal());
        self::assertSame('65000.00', $result->changeAmount->toDecimal());
        self::assertSame('0.00', $result->debtAmount->toDecimal());
        self::assertSame(OrderStatus::COMPLETED, $result->status);

        $this->entityManager->clear();

        $order = $this->entityManager->find(Order::class, $result->orderId);
        self::assertNotNull($order);

        $payments = $order->getPayments();
        self::assertCount(1, $payments);

        /** @var Payment $payment */
        $payment = $payments->first();
        self::assertNotFalse($payment);
        self::assertSame('135000.00', $payment->getAmount()->toDecimal());
        self::assertSame(PaymentMethod::CASH, $payment->getMethod());
    }

    private function checkout(
        User $user,
        Product $product,
        string $paymentAmount,
        string $tenderedAmount,
        ?Customer $customer = null,
    ): CheckoutResult {
        /** @var RuntimeActorContextProvider $actorContextProvider */
        $actorContextProvider = self::getContainer()->get(RuntimeActorContextProvider::class);
        $actorContextProvider->set(new ActorContext(
            userId: $user->getId() ?? throw new \LogicException('User ID missing.'),
            sessionId: null,
            requestId: 'cash-tender-test',
        ));

        try {
            /** @var CheckoutHandlerEntryPoint $entryPoint */
            $entryPoint = self::getContainer()->get(CheckoutHandlerEntryPoint::class);

            return $entryPoint->handle(new CheckoutInput(
                items: [new CheckoutItemInput($product->getId() ?? throw new \LogicException('Product ID missing.'), 1)],
                customerId: $customer?->getId(),
                payment: new CheckoutPaymentInput(
                    method: PaymentMethod::CASH,
                    amount: Money::fromDecimal($paymentAmount),
                    tenderedAmount: Money::fromDecimal($tenderedAmount),
                ),
                note: null,
                idempotencyKey: 'cash-tender-'.bin2hex(random_bytes(12)),
            ));
        } finally {
            $actorContextProvider->clear();
        }
    }
}
