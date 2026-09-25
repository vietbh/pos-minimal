<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Customer\Customer;
use App\Domain\Payment\Enum\CheckoutPaymentSessionStatus;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use App\Domain\SalesPoint\SalesPoint;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'checkout_payment_sessions')]
#[ORM\Index(name: 'idx_checkout_session_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_checkout_session_active_key', columns: ['active_key'])]
#[ORM\UniqueConstraint(name: 'UNIQ_CHECKOUT_SESSION_ACTIVE_KEY', columns: ['active_key'])]
class CheckoutPaymentSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Customer $customer;

    #[ORM\ManyToOne(targetEntity: SalesPoint::class)]
    #[ORM\JoinColumn(name: 'sales_point_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?SalesPoint $salesPoint;

    #[ORM\ManyToOne(targetEntity: PaymentBankAccount::class)]
    #[ORM\JoinColumn(name: 'payment_bank_account_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private PaymentBankAccount $bankAccount;

    #[ORM\Column(name: 'cart_snapshot', type: 'json')]
    private array $cartSnapshot;

    #[ORM\Column(type: 'money')]
    private Money $amount;

    #[ORM\Column(name: 'note', type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\Column(name: 'active_key', length: 64, nullable: true)]
    private ?string $activeKey;

    #[ORM\Column(enumType: CheckoutPaymentSessionStatus::class, length: 40)]
    private CheckoutPaymentSessionStatus $status;

    #[ORM\ManyToOne(targetEntity: \App\Domain\Order\Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?\App\Domain\Order\Order $order = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @param list<array{productId:int,quantity:int,unitPrice:string}> $cartSnapshot */
    public function __construct(
        User $user,
        ?Customer $customer,
        PaymentBankAccount $bankAccount,
        array $cartSnapshot,
        Money $amount,
        ?string $note,
        string $activeKey,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $createdAt = null,
        ?SalesPoint $salesPoint = null,
    ) {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('Checkout payment session amount must be greater than zero.');
        }
        if ($cartSnapshot === []) {
            throw new \InvalidArgumentException('Checkout payment session cart cannot be empty.');
        }
        if (trim($activeKey) === '') {
            throw new \InvalidArgumentException('Checkout payment session active key cannot be empty.');
        }
        $createdAt ??= new \DateTimeImmutable();
        if ($expiresAt <= $createdAt) {
            throw new \InvalidArgumentException('Checkout payment session expiration must be in the future.');
        }

        $this->user = $user;
        $this->customer = $customer;
        $this->bankAccount = $bankAccount;
        $this->salesPoint = $salesPoint;
        $this->cartSnapshot = array_values($cartSnapshot);
        $this->amount = $amount;
        $this->note = $note !== null ? trim($note) ?: null : null;
        $this->activeKey = hash('sha256', trim($activeKey));
        $this->status = CheckoutPaymentSessionStatus::WAITING_FOR_BANK_PAYMENT;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->updatedAt = $createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getCustomer(): ?Customer { return $this->customer; }
    public function getBankAccount(): PaymentBankAccount { return $this->bankAccount; }
    public function getSalesPoint(): ?SalesPoint { return $this->salesPoint; }
    /** @return list<array{productId:int,quantity:int,unitPrice:string}> */
    public function getCartSnapshot(): array { return $this->cartSnapshot; }
    public function getAmount(): Money { return $this->amount; }
    public function getNote(): ?string { return $this->note; }
    public function getStatus(): CheckoutPaymentSessionStatus { return $this->status; }
    public function getOrder(): ?\App\Domain\Order\Order { return $this->order; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function isExpired(?\DateTimeImmutable $now = null): bool { return ($now ?? new \DateTimeImmutable()) >= $this->expiresAt; }
    public function markPaid(): void { $this->status = CheckoutPaymentSessionStatus::PAID; $this->activeKey = null; $this->updatedAt = new \DateTimeImmutable(); }
    public function expire(): void { if ($this->status === CheckoutPaymentSessionStatus::WAITING_FOR_BANK_PAYMENT) { $this->status = CheckoutPaymentSessionStatus::EXPIRED; $this->activeKey = null; $this->updatedAt = new \DateTimeImmutable(); } }
    public function cancel(): void { if ($this->status === CheckoutPaymentSessionStatus::WAITING_FOR_BANK_PAYMENT) { $this->status = CheckoutPaymentSessionStatus::CANCELLED; $this->activeKey = null; $this->updatedAt = new \DateTimeImmutable(); } }
    public function attachOrder(\App\Domain\Order\Order $order): void { if ($this->order !== null && $this->order !== $order) throw new \DomainException('Checkout payment session is already linked to an order.'); $this->order = $order; $this->updatedAt = new \DateTimeImmutable(); }
}
