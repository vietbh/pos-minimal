<?php

declare(strict_types=1);

namespace App\Tests\Application\Payment;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Order\Command\Checkout\ProductLockingInterface;
use App\Application\Order\Command\CompleteOrder\CompleteOrderService;
use App\Application\Order\OrderNumberGeneratorInterface;
use App\Application\Payment\ManualBankPaymentConfirmationService;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Order\Order;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Order\Repository\PaymentRepositoryInterface;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Payment\CheckoutPaymentSession;
use App\Domain\Payment\PaymentBankAccount;
use App\Domain\Payment\PaymentReference;
use App\Domain\Payment\Repository\CheckoutPaymentSessionRepositoryInterface;
use App\Domain\Payment\Repository\ExternalPaymentTransactionRepositoryInterface;
use App\Domain\Payment\Repository\PaymentReferenceRepositoryInterface;
use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ManualBankPaymentConfirmationServiceTest extends TestCase
{
    public function testManualConfirmationCreatesPaymentCompletesOrderAndLeavesWebhookEnrichmentPending(): void
    {
        $user = new User('manual-confirm-' . bin2hex(random_bytes(3)));
        $account = new PaymentBankAccount('970415', 'Test Bank', '123456789', 'TEST USER');
        $product = new Product('Manual confirmation product', Money::fromDecimal('100000.00'));
        $product->setStockQuantityForAdjustment(10);
        $session = new CheckoutPaymentSession(
            $user,
            null,
            $account,
            [['productId' => 1, 'quantity' => 1, 'unitPrice' => '100000.00']],
            Money::fromDecimal('100000.00'),
            null,
            'manual-confirm-key',
            new \DateTimeImmutable('+5 minutes'),
        );
        $reference = new PaymentReference(
            'ABC12345',
            $session,
            $account,
            Money::fromDecimal('100000.00'),
            new \DateTimeImmutable('+5 minutes'),
        );

        $sessions = $this->createMock(CheckoutPaymentSessionRepositoryInterface::class);
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $payments = $this->createMock(PaymentRepositoryInterface::class);
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $numberGenerator = $this->createMock(OrderNumberGeneratorInterface::class);
        $locking = $this->createMock(ProductLockingInterface::class);
        $audits = $this->createMock(AuditLogRepositoryInterface::class);
        $completion = $this->createMock(CompleteOrderService::class);
        $externalTransactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $externalTransactions->expects(self::once())->method('findLatestByOrderId')->with(77)->willReturn(null);

        $sessions->expects(self::once())->method('findByIdForUpdate')->willReturn($session);
        $references->expects(self::once())->method('findByReferenceForUpdate')->with('ABC12345')->willReturn($reference);
        $numberGenerator->expects(self::once())->method('generate')->willReturn(new OrderNumber('ORD-MANUAL123456789'));
        $locking->expects(self::once())->method('lock')->with(1)->willReturn($product);
        $orders->expects(self::once())->method('save')->with(self::isInstanceOf(Order::class))->willReturnCallback(function (Order $order): void {
            (new \ReflectionClass($order))->getProperty('id')->setValue($order, 77);
        });
        $payments->expects(self::once())->method('save')->with(self::isInstanceOf(\App\Domain\Order\Payment::class))->willReturnCallback(function ($payment): void {
            (new \ReflectionClass($payment))->getProperty('id')->setValue($payment, 88);
        });
        $audits->expects(self::once())->method('save');

        $flushCount = 0;
        $transactionContext = new class($flushCount) implements TransactionContextInterface {
            public function __construct(private int &$flushCount) {}

            public function flush(): void
            {
                ++$this->flushCount;
            }
        };

        $completion->expects(self::once())->method('completePaidOrder')->willReturnCallback(
            function () use (&$flushCount): void {
                self::assertSame(1, $flushCount, 'The new order/payment must be flushed before completion reloads the generated order ID.');
            },
        );

        $service = new ManualBankPaymentConfirmationService(
            $sessions,
            $references,
            $payments,
            $orders,
            $numberGenerator,
            $locking,
            $audits,
            $completion,
            $externalTransactions,
            new class($transactionContext) implements TransactionManagerInterface {
                public function __construct(private TransactionContextInterface $context) {}

                public function run(callable $operation): mixed
                {
                    return $operation($this->context);
                }
            },
        );

        $result = $service->confirmAndComplete(1, 'abc12345', '100000.00', $user, 'req-manual');

        self::assertSame('DRAFT', $result['status'], 'The mocked completion service must not mutate the domain object; the real integration path completes it.');
        self::assertSame(77, $result['orderId']);
        self::assertSame(88, $result['paymentId']);
        self::assertSame('ABC12345', $result['reference']);
    }
}
