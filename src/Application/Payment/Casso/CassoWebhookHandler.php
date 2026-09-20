<?php
declare(strict_types=1);
namespace App\Application\Payment\Casso;
use App\Domain\Order\Repository\OrderRepositoryInterface;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Payment\ExternalPaymentTransaction;
use App\Domain\Payment\Repository\ExternalPaymentTransactionRepositoryInterface;
use App\Domain\Payment\Repository\PaymentBankAccountRepositoryInterface;
use App\Domain\Shared\ValueObject\Money;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CassoWebhookHandler
{
    public function __construct(
        private PaymentBankAccountRepositoryInterface $accounts,
        private ExternalPaymentTransactionRepositoryInterface $transactions,
        private OrderRepositoryInterface $orders,
        private EntityManagerInterface $em,
    ) {}

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): string
    {
        $id = $payload['id'] ?? $payload['tid'] ?? null;
        $amount = $payload['amount'] ?? null;
        $description = $payload['description'] ?? '';
        $subAccount = isset($payload['bank_sub_acc_id']) ? (string)$payload['bank_sub_acc_id'] : null;
        if ($id === null || (!is_int($amount) && !is_float($amount) && !is_string($amount))) {
            throw new \InvalidArgumentException('Invalid Casso transaction payload.');
        }

        $id = (string)$id;
        $existing = $this->transactions->findByProviderTransactionId('CASSO', $id);
        if ($existing !== null) return 'DUPLICATE';

        $account = null;
        if ($subAccount !== null && $subAccount !== '') {
            foreach ($this->accounts->findAll() as $candidate) {
                if ($candidate->getCassoSubAccountId() === $subAccount) { $account = $candidate; break; }
            }
        }
        if ($account === null) {
            throw new \DomainException('Casso bank sub-account is not configured.');
        }

        $occurredAt = new \DateTimeImmutable((string)($payload['when'] ?? 'now'));
        $transaction = new ExternalPaymentTransaction(
            provider: 'CASSO',
            externalTransactionId: $id,
            account: $account,
            amount: Money::fromDecimal(is_int($amount) || ctype_digit((string)$amount) ? ((string)$amount . '.00') : (string)$amount),
            description: (string)$description,
            occurredAt: $occurredAt,
            transactionReference: $this->extractOrderNumber((string)$description),
        );

        $orderNumber = $transaction->getTransactionReference();
        if ($orderNumber !== null) {
            $order = $this->orders->findByOrderNumber(new OrderNumber($orderNumber));
            if ($order !== null) {
                if ($order->getTotal()->equals($transaction->getAmount()) && $order->getStatus()->value === 'COMPLETED') {
                    $transaction->matchOrder($order, 'MATCHED');
                } else {
                    $transaction->matchOrder($order, 'CONFLICT');
                }
            }
        }

        try {
            $this->transactions->save($transaction);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return 'DUPLICATE';
        }

        return $transaction->getStatus();
    }

    private function extractOrderNumber(string $description): ?string
    {
        if (preg_match('/\b(ORD-[A-F0-9]{16})\b/i', $description, $m) !== 1) return null;
        return strtoupper($m[1]);
    }
}
