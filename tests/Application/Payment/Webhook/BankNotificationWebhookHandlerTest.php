<?php

declare(strict_types=1);

namespace App\Tests\Application\Payment\Webhook;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Application\Order\Command\Checkout\ProductLockingInterface;
use App\Application\Order\OrderNumberGeneratorInterface;
use App\Application\Payment\Webhook\BankNotificationReconciliationService;
use App\Application\Payment\Webhook\BankNotificationWebhookHandler;
use App\Domain\Audit\Repository\AuditLogRepositoryInterface;
use App\Domain\Order\Order;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Order\Repository\PaymentRepositoryInterface;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Payment\CheckoutPaymentSession;
use App\Domain\Payment\Enum\CheckoutPaymentSessionStatus;
use App\Domain\Payment\Enum\PaymentReferenceStatus;
use App\Domain\Payment\ExternalPaymentTransaction;
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

final class BankNotificationWebhookHandlerTest extends TestCase
{
    public function testUnknownReferenceIsRejectedWithoutCreatingAnything(): void
    {
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $transactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $references->expects(self::once())->method('findByReferenceForUpdate')->with('5CC393D6E1DF')->willReturn(null);
        $transactions->method('findByProviderTransactionId')->willReturn(null);

        $handler = new BankNotificationWebhookHandler($this->service($references, $transactions));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment reference not found.');
        $handler->handle(['content' => 'Thanh 5CC393D6E1DF Chuyen khoan cho BUIHOANGVIET']);
    }

    public function testStandaloneReferenceIsAccepted(): void
    {
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $transactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $references->expects(self::once())->method('findByReferenceForUpdate')->with('678DCE942ED3')->willReturn(null);
        $transactions->method('findByProviderTransactionId')->willReturn(null);

        $handler = new BankNotificationWebhookHandler($this->service($references, $transactions));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment reference not found.');
        $handler->handle(['content' => '678DCE942ED3']);
    }

    public function testCoThanhReferenceIsAccepted(): void
    {
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $transactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $references->expects(self::once())->method('findByReferenceForUpdate')->with('678DCE942ED3')->willReturn(null);
        $transactions->method('findByProviderTransactionId')->willReturn(null);

        $handler = new BankNotificationWebhookHandler($this->service($references, $transactions));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment reference not found.');
        $handler->handle(['content' => 'CO THANH 678DCE942ED3']);
    }

    public function testValidWebhookCreatesExactlyOneDraftOrderAndPaymentButDoesNotCompleteSale(): void
    {
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $transactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $payments = $this->createMock(PaymentRepositoryInterface::class);
        $orders = $this->createMock(OrderRepositoryInterface::class);
        $sessions = $this->createMock(CheckoutPaymentSessionRepositoryInterface::class);
        $auditLogs = $this->createMock(AuditLogRepositoryInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $locking = $this->createMock(ProductLockingInterface::class);
        $numberGenerator = $this->createMock(OrderNumberGeneratorInterface::class);

        $user = new User('phase227-webhook-'.bin2hex(random_bytes(3)));
        $account = new PaymentBankAccount('970415', 'Test Bank', '123456789', 'TEST USER');
        $product = new Product('Webhook product', Money::fromInt(100000));
        $product->setStockQuantityForAdjustment(10);
        $session = new CheckoutPaymentSession(
            $user, null, $account,
            [['productId' => 1, 'quantity' => 1, 'unitPrice' => '100000.00']],
            Money::fromInt(100000), null, 'webhook-test',
            new \DateTimeImmutable('-5 minutes'),
        );
        $reference = $this->createMock(PaymentReference::class);
        $reference->method('getStatus')->willReturn(PaymentReferenceStatus::PENDING);
        $reference->method('isExpired')->willReturn(false);
        $reference->method('getCheckoutPaymentSession')->willReturn($session);
        $reference->method('getBankAccount')->willReturn($account);
        $reference->method('getAmount')->willReturn(Money::fromInt(100000));
        $reference->method('getReference')->willReturn('5CC393D6E1DF');
        $reference->expects(self::once())->method('attachOrder');
        $reference->expects(self::once())->method('attachPayment');
        $reference->expects(self::once())->method('markMatched');

        $references->expects(self::once())->method('findByReferenceForUpdate')->willReturn($reference);
        $sessions->expects(self::once())->method('findByIdForUpdate')->willReturn($session);
        $transactions->method('findByProviderTransactionId')->willReturn(null);
        $transactions->expects(self::once())->method('save')->with(self::isInstanceOf(ExternalPaymentTransaction::class));
        $payments->expects(self::once())->method('save');
        $orders->expects(self::once())->method('save')->with(self::isInstanceOf(Order::class))->willReturnCallback(function (Order $order): void {
            (new \ReflectionClass($order))->getProperty('id')->setValue($order, 10);
        });
        $locking->expects(self::once())->method('lock')->with(1)->willReturn($product);
        $numberGenerator->expects(self::once())->method('generate')->willReturn(new OrderNumber('ORD-WEBHOOK123456789'));
        $auditLogs->expects(self::once())->method('save');
        $em->expects(self::once())->method('flush');

        $service = new BankNotificationReconciliationService(
            $references, $sessions, $transactions, $payments, $orders,
            $numberGenerator, $locking, $auditLogs, $em, $this->inlineTransactionManager(),
        );
        $result = (new BankNotificationWebhookHandler($service))->handle([
            'provider' => 'SOME_ANDROID_BRIDGE',
            'content' => 'Thanh 5CC393D6E1DF Chuyen khoan cho BUIHOANGVIET',
            'amount' => '100000.00',
            'bankAccountId' => $account->getId(),
        ]);

        self::assertSame('MATCHED', $result['status']);
        self::assertSame('5CC393D6E1DF', $result['reference']);
        self::assertSame(10, $result['orderId']);
        self::assertSame(CheckoutPaymentSessionStatus::PAID, $session->getStatus());
    }

    public function testDuplicateExternalTransactionWithDifferentReferenceIsRejectedAsConflict(): void
    {
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $transactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $account = new PaymentBankAccount('970415', 'Test Bank', '123456789', 'TEST USER');
        $existing = new ExternalPaymentTransaction(
            provider: 'CASSO',
            externalTransactionId: 'TX-1',
            account: $account,
            amount: Money::fromInt(100000),
            description: 'THANH ABC12345',
            occurredAt: new \DateTimeImmutable('2026-09-18T17:43:30+07:00'),
            transactionReference: 'ABC12345',
        );
        $transactions->expects(self::once())->method('findByProviderTransactionId')->with('CASSO', 'TX-1')->willReturn($existing);
        $references->expects(self::never())->method('findByReferenceForUpdate');

        $handler = new BankNotificationWebhookHandler($this->service($references, $transactions));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('conflicts with existing external transaction reference');
        $handler->handle([
            'provider' => 'casso',
            'externalTransactionId' => 'TX-1',
            'content' => 'THANH XYZ98765',
            'amount' => '100000.00',
        ]);
    }

    public function testExternalTransactionIdTakesPrecedenceOverProviderEventId(): void
    {
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $transactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $references->expects(self::once())->method('findByReferenceForUpdate')->with('ABC12345')->willReturn(null);
        $transactions->expects(self::once())->method('findByProviderTransactionId')->with('CASSO', 'TX-REAL')->willReturn(null);

        $handler = new BankNotificationWebhookHandler($this->service($references, $transactions));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment reference not found.');
        $handler->handle([
            'provider' => 'casso',
            'eventId' => 'EVENT-ENVELOPE',
            'externalTransactionId' => 'TX-REAL',
            'content' => 'THANH ABC12345',
        ]);
    }

    public function testMatchedReferenceRemainsValidForDelayedWebhookEvenWhenReferenceWouldNowBeExpired(): void
    {
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $transactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $account = new PaymentBankAccount('970415', 'Test Bank', '123456789', 'TEST USER');
        $payment = $this->createMock(\App\Domain\Order\Payment::class);
        $order = $this->createMock(\App\Domain\Order\Order::class);

        $payment->method('getId')->willReturn(88);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAmount')->willReturn(Money::fromDecimal('100000.00'));
        $payment->method('getBankAccount')->willReturn($account);
        $order->method('getId')->willReturn(77);

        $reference = $this->createMock(PaymentReference::class);
        $reference->method('getStatus')->willReturn(PaymentReferenceStatus::MATCHED);
        $reference->method('getPayment')->willReturn($payment);
        $reference->method('getOrder')->willReturn($order);
        $reference->method('getCheckoutPaymentSession')->willReturn($this->createMock(\App\Domain\Payment\CheckoutPaymentSession::class));
        $reference->method('getBankAccount')->willReturn($account);
        $reference->method('getAmount')->willReturn(Money::fromDecimal('100000.00'));
        $reference->method('getReference')->willReturn('ABC12345');
        $reference->expects(self::never())->method('isExpired');

        $transactions->method('findByProviderTransactionId')->willReturn(null);
        $transactions->expects(self::once())->method('save')->with(self::isInstanceOf(ExternalPaymentTransaction::class));
        $references->expects(self::once())->method('findByReferenceForUpdate')->with('ABC12345')->willReturn($reference);

        $handler = new BankNotificationWebhookHandler($this->service($references, $transactions));
        $result = $handler->handle([
            'provider' => 'VCB',
            'externalTransactionId' => 'TX-DELAYED-1',
            'content' => 'Thanh ABC12345 chuyen khoan',
            'amount' => '100000.00',
            'occurredAt' => '2026-09-18T17:43:30+07:00',
            'bankAccountId' => $account->getId(),
        ]);

        self::assertSame('ENRICHED', $result['status']);
        self::assertSame(77, $result['orderId']);
        self::assertSame(88, $result['paymentId']);
    }

    public function testAlreadyMatchedReferenceIsIdempotent(): void
    {
        $references = $this->createMock(PaymentReferenceRepositoryInterface::class);
        $transactions = $this->createMock(ExternalPaymentTransactionRepositoryInterface::class);
        $transactions->expects(self::once())->method('save')->with(self::isInstanceOf(ExternalPaymentTransaction::class));
        $payment = $this->createMock(\App\Domain\Order\Payment::class);
        $payment->method('getId')->willReturn(8);
        $reference = $this->createMock(PaymentReference::class);
        $reference->method('getStatus')->willReturn(PaymentReferenceStatus::MATCHED);
        $reference->method('getPayment')->willReturn($payment);
        $reference->method('getReference')->willReturn('ABC12345');
        $references->method('findByReferenceForUpdate')->willReturn($reference);
        $transactions->method('findByProviderTransactionId')->willReturn(null);

        $result = (new BankNotificationWebhookHandler($this->service($references, $transactions)))->handle([
            'provider' => 'ANOTHER_PROVIDER', 'content' => 'Thanh ABC12345 transfer received',
        ]);
        self::assertSame('ENRICHED', $result['status']);
        self::assertSame(8, $result['paymentId']);
    }

    private function service(PaymentReferenceRepositoryInterface $references, ExternalPaymentTransactionRepositoryInterface $transactions): BankNotificationReconciliationService
    {
        return new BankNotificationReconciliationService(
            $references,
            $this->createMock(CheckoutPaymentSessionRepositoryInterface::class),
            $transactions,
            $this->createMock(PaymentRepositoryInterface::class),
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(OrderNumberGeneratorInterface::class),
            $this->createMock(ProductLockingInterface::class),
            $this->createMock(AuditLogRepositoryInterface::class),
            $this->createMock(EntityManagerInterface::class),
            $this->inlineTransactionManager(),
        );
    }

    private function inlineTransactionManager(): TransactionManagerInterface
    {
        return new class implements TransactionManagerInterface {
            public function run(callable $operation): mixed
            {
                return $operation(new class implements TransactionContextInterface { public function flush(): void {} });
            }
        };
    }
}
