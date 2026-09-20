<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Payment;

use App\Domain\Order\Order;
use App\Domain\Order\OrderItem;
use App\Domain\Order\Payment;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Payment\Enum\PaymentReferenceStatus;
use App\Domain\Payment\PaymentBankAccount;
use App\Domain\Payment\CheckoutPaymentSession;
use App\Domain\Payment\PaymentReference;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class PaymentReferenceTest extends TestCase
{
    public function testPendingReferenceExpiresAndCannotBeMatched(): void
    {
        $user = new User('phase227-user-'.bin2hex(random_bytes(3)));
        $order = new Order(new OrderNumber('ORD-ABCDEF1234567890'), $user);
        $account = new PaymentBankAccount('970415', 'Test Bank', '123456789', 'TEST USER');
        $session = new CheckoutPaymentSession($user, null, $account, [['productId' => 1, 'quantity' => 1, 'unitPrice' => '100000.00']], Money::fromInt(100000), null, 'test-active-1', new \DateTimeImmutable('2030-01-01 00:05:00'));

        $reference = new PaymentReference(
            'ABC12345',
            $session,
            $account,
            Money::fromInt(100000),
            new \DateTimeImmutable('2030-01-01 00:00:00'),
        );

        $now = new \DateTimeImmutable('2030-01-01 00:00:01');

        self::assertTrue($reference->isExpired($now));
        $reference->expire($now);
        self::assertSame(PaymentReferenceStatus::EXPIRED, $reference->getStatus());

        $this->expectException(\DomainException::class);
        $reference->markMatched($now);
    }

    public function testPendingReferenceCanBeAttachedToPaymentAndMatchedBeforeExpiration(): void
    {
        $user = new User('phase227-user-'.bin2hex(random_bytes(3)));
        $order = new Order(new OrderNumber('ORD-ABCDEF1234567891'), $user);
        $product = new Product('Test product', Money::fromInt(100000));
        $order->addItem(new OrderItem($product, 1, Money::fromInt(100000)));
        $account = new PaymentBankAccount('970415', 'Test Bank', '123456789', 'TEST USER');

        $payment = new Payment(
            Money::fromInt(100000),
            PaymentMethod::BANK_TRANSFER,
            $user,
            'ABC12346',
            $account,
        );
        $order->addPayment($payment);

        $session = new CheckoutPaymentSession($user, null, $account, [['productId' => 1, 'quantity' => 1, 'unitPrice' => '100000.00']], Money::fromInt(100000), null, 'test-active-2', new \DateTimeImmutable('2030-01-01 00:05:00'));

        $reference = new PaymentReference(
            'ABC12346',
            $session,
            $account,
            Money::fromInt(100000),
            new \DateTimeImmutable('2030-01-01 00:00:00'),
        );

        $reference->attachOrder($order);
        $reference->attachPayment($payment);
        $reference->markMatched(new \DateTimeImmutable('2029-12-31 23:59:59'));

        self::assertSame(PaymentReferenceStatus::MATCHED, $reference->getStatus());
        self::assertSame($payment, $reference->getPayment());
    }

    public function testExpiredReferenceCannotReturnToPending(): void
    {
        $user = new User('phase227-user-'.bin2hex(random_bytes(3)));
        $order = new Order(new OrderNumber('ORD-ABCDEF1234567892'), $user);
        $account = new PaymentBankAccount('970415', 'Test Bank', '123456789', 'TEST USER');

        $session = new CheckoutPaymentSession($user, null, $account, [['productId' => 1, 'quantity' => 1, 'unitPrice' => '100000.00']], Money::fromInt(100000), null, 'test-active-3', new \DateTimeImmutable('2030-01-01 00:05:00'));

        $reference = new PaymentReference(
            'ABC12347',
            $session,
            $account,
            Money::fromInt(100000),
            new \DateTimeImmutable('2030-01-01 00:00:00'),
        );

        $reference->expire(new \DateTimeImmutable('2030-01-01 00:00:01'));

        $this->expectException(\DomainException::class);
        $reference->markMatched(new \DateTimeImmutable('2030-01-01 00:00:02'));
    }
}
