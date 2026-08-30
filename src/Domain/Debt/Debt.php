<?php

declare(strict_types=1);

namespace App\Domain\Debt;

use App\Domain\Customer\Customer;
use App\Domain\Debt\Enum\DebtStatus;
use App\Domain\Order\Order;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'debts')]
#[ORM\Index(
    name: 'idx_debt_customer_status',
    columns: ['customer_id', 'status'],
)]
#[ORM\Index(
    name: 'idx_debt_order',
    columns: ['order_id'],
)]
#[ORM\Index(
    name: 'idx_debt_status_created',
    columns: ['status', 'created_at'],
)]
#[ORM\Index(
    name: 'idx_debt_created',
    columns: ['created_at'],
)]
class Debt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(
        name: 'customer_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private Customer $customer;

    #[ORM\OneToOne(
        targetEntity: Order::class,
    )]
    #[ORM\JoinColumn(
        name: 'order_id',
        referencedColumnName: 'id',
        nullable: false,
        unique: true,
        onDelete: 'RESTRICT',
    )]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(
        name: 'created_by',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private User $createdBy;

    #[ORM\Column(
        name: 'original_amount',
        type: 'money',
    )]
    private Money $originalAmount;

    #[ORM\Column(
        enumType: DebtStatus::class,
        length: 30,
    )]
    private DebtStatus $status;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(
        name: 'updated_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, DebtPayment>
     */
    #[ORM\OneToMany(
        mappedBy: 'debt',
        targetEntity: DebtPayment::class,
        cascade: ['persist'],
        orphanRemoval: false,
    )]
    #[ORM\OrderBy([
        'createdAt' => 'ASC',
        'id' => 'ASC',
    ])]
    private Collection $payments;

    public function __construct(
        Customer $customer,
        Order $order,
        User $createdBy,
        Money $originalAmount,
    ) {
        if (!$originalAmount->isPositive()) {
            throw new \InvalidArgumentException(
                'Debt original amount must be greater than zero.',
            );
        }

        $this->customer = $customer;
        $this->order = $order;
        $this->createdBy = $createdBy;
        $this->originalAmount = $originalAmount;
        $this->status = DebtStatus::OPEN;

        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;

        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function getOriginalAmount(): Money
    {
        return $this->originalAmount;
    }

    public function getStatus(): DebtStatus
    {
        return $this->status;
    }

    /**
     * @return Collection<int, DebtPayment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(DebtPayment $payment): void
    {
        if ($payment->getDebt() !== $this) {
            $payment->assignDebt($this);
        }

        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
        }

        $this->recalculateStatus();
    }

    public function getPaidAmount(): Money
    {
        $paid = Money::zero();

        foreach ($this->payments as $payment) {
            $paid = $paid->add($payment->getAmount());
        }

        return $paid;
    }

    public function getRemainingAmount(): Money
    {
        $paid = $this->getPaidAmount();

        if ($paid->isGreaterThanOrEqual($this->originalAmount)) {
            return Money::zero();
        }

        return $this->originalAmount->subtract($paid);
    }

    public function recalculateStatus(): void
    {
        $paid = $this->getPaidAmount();

        if ($paid->isZero()) {
            $this->status = DebtStatus::OPEN;
        } elseif (
            $paid->isGreaterThanOrEqual($this->originalAmount)
        ) {
            $this->status = DebtStatus::PAID;
        } else {
            $this->status = DebtStatus::PARTIALLY_PAID;
        }

        $this->touch();
    }

    public function isOpen(): bool
    {
        return $this->status === DebtStatus::OPEN;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === DebtStatus::PARTIALLY_PAID;
    }

    public function isPaid(): bool
    {
        return $this->status === DebtStatus::PAID;
    }

    public function canReceivePayment(): bool
    {
        return !$this->isPaid();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
