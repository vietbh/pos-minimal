<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Order\Order;
use App\Domain\Order\Payment;
use App\Domain\Payment\Enum\PaymentReferenceStatus;
use App\Domain\Shared\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payment_references')]
#[ORM\UniqueConstraint(name: 'UNIQ_PAYMENT_REFERENCES_REFERENCE', columns: ['reference'])]
#[ORM\Index(name: 'idx_payment_reference_order', columns: ['order_id'])]
#[ORM\Index(name: 'idx_payment_reference_session', columns: ['checkout_payment_session_id'])]
#[ORM\Index(name: 'idx_payment_reference_status_expires', columns: ['status', 'expires_at'])]
class PaymentReference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $reference;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: CheckoutPaymentSession::class)]
    #[ORM\JoinColumn(name: 'checkout_payment_session_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private CheckoutPaymentSession $checkoutPaymentSession;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Payment $payment = null;

    #[ORM\ManyToOne(targetEntity: PaymentBankAccount::class)]
    #[ORM\JoinColumn(name: 'payment_bank_account_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private PaymentBankAccount $bankAccount;

    #[ORM\Column(type: 'money')]
    private Money $amount;

    #[ORM\Column(enumType: PaymentReferenceStatus::class, length: 20)]
    private PaymentReferenceStatus $status;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'matched_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $matchedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $reference,
        CheckoutPaymentSession $checkoutPaymentSession,
        PaymentBankAccount $bankAccount,
        Money $amount,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $reference = strtoupper(trim($reference));

        if (preg_match('/^[A-Z0-9]{8,32}$/', $reference) !== 1) {
            throw new \InvalidArgumentException('Payment reference has an invalid format.');
        }

        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('Payment reference amount must be greater than zero.');
        }

        $createdAt ??= new \DateTimeImmutable();

        if ($expiresAt <= $createdAt) {
            throw new \InvalidArgumentException('Payment reference expiration must be in the future.');
        }

        $this->reference = $reference;
        $this->checkoutPaymentSession = $checkoutPaymentSession;
        $this->bankAccount = $bankAccount;
        $this->amount = $amount;
        $this->status = PaymentReferenceStatus::PENDING;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getOrder(): ?Order { return $this->order; }
    public function getCheckoutPaymentSession(): CheckoutPaymentSession { return $this->checkoutPaymentSession; }
    public function attachOrder(Order $order): void
    {
        if ($this->order !== null && $this->order !== $order) {
            throw new \DomainException('Payment reference is already linked to an order.');
        }
        $this->order = $order;
        $this->touch();
    }
    public function getPayment(): ?Payment { return $this->payment; }
    public function getBankAccount(): PaymentBankAccount { return $this->bankAccount; }
    public function getAmount(): Money { return $this->amount; }
    public function getStatus(): PaymentReferenceStatus { return $this->status; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getMatchedAt(): ?\DateTimeImmutable { return $this->matchedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable()) >= $this->expiresAt;
    }

    public function expire(?\DateTimeImmutable $now = null): void
    {
        if ($this->status !== PaymentReferenceStatus::PENDING) {
            return;
        }

        if (!$this->isExpired($now)) {
            throw new \DomainException('Payment reference has not expired yet.');
        }

        $this->status = PaymentReferenceStatus::EXPIRED;
        $this->touch($now);
    }

    public function attachPayment(Payment $payment): void
    {
        if ($this->payment !== null && $this->payment !== $payment) {
            throw new \DomainException('Payment reference is already attached to a payment.');
        }

        if ($this->order === null || $payment->getOrder() !== $this->order) {
            throw new \DomainException('Payment does not belong to the payment reference order.');
        }

        if (!$payment->getAmount()->equals($this->amount)) {
            throw new \DomainException('Payment amount does not match the payment reference amount.');
        }

        if ($payment->getBankAccount() !== $this->bankAccount) {
            throw new \DomainException('Payment bank account does not match the payment reference bank account.');
        }

        $this->payment = $payment;
        $this->touch();
    }

    public function markMatched(?\DateTimeImmutable $matchedAt = null): void
    {
        if ($this->status === PaymentReferenceStatus::MATCHED) {
            return;
        }

        if ($this->status !== PaymentReferenceStatus::PENDING) {
            throw new \DomainException('Only pending payment references can be matched.');
        }

        $matchedAt ??= new \DateTimeImmutable();

        if ($this->isExpired($matchedAt)) {
            $this->expire($matchedAt);
            throw new \DomainException('Payment reference has expired.');
        }

        if ($this->payment === null) {
            throw new \DomainException('Payment must be attached before the reference can be matched.');
        }

        $this->status = PaymentReferenceStatus::MATCHED;
        $this->matchedAt = $matchedAt;
        $this->touch($matchedAt);
    }

    public function cancel(): void
    {
        if ($this->status === PaymentReferenceStatus::MATCHED) {
            throw new \DomainException('Matched payment reference cannot be cancelled.');
        }

        if ($this->status === PaymentReferenceStatus::EXPIRED) {
            return;
        }

        $this->status = PaymentReferenceStatus::CANCELLED;
        $this->touch();
    }

    private function touch(?\DateTimeImmutable $at = null): void
    {
        $this->updatedAt = $at ?? new \DateTimeImmutable();
    }
}
