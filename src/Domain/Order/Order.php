<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Customer\Customer;
use App\Domain\Order\Enum\OrderStatus;
use App\Domain\Order\ValueObject\OrderNumber;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'orders',
    indexes: [
        new ORM\Index(
            name: 'idx_order_customer_created',
            columns: ['customer_id', 'created_at'],
        ),
        new ORM\Index(
            name: 'idx_order_user_created',
            columns: ['user_id', 'created_at'],
        ),
        new ORM\Index(
            name: 'idx_order_status_created',
            columns: ['status', 'created_at'],
        ),
        new ORM\Index(
            name: 'idx_order_created',
            columns: ['created_at'],
        ),
    ],
)]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\Column(
        name: 'order_number',
        type: 'order_number',
        length: 50,
        unique: true,
    )]
    private OrderNumber $orderNumber;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(
        name: 'user_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(
        name: 'customer_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'RESTRICT',
    )]
    private ?Customer $customer = null;

    #[ORM\Column(
        enumType: OrderStatus::class,
        length: 30,
    )]
    private OrderStatus $status;

    #[ORM\Column(
        type: 'money',
    )]
    private Money $subtotal;

    #[ORM\Column(
        type: 'money',
    )]
    private Money $total;

    #[ORM\Column(
        type: 'text',
        nullable: true,
    )]
    private ?string $note = null;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(
        name: 'completed_at',
        type: 'datetime_immutable',
        nullable: true,
    )]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(
        name: 'cancelled_at',
        type: 'datetime_immutable',
        nullable: true,
    )]
    private ?\DateTimeImmutable $cancelledAt = null;

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(
        mappedBy: 'order',
        targetEntity: OrderItem::class,
        cascade: ['persist'],
        orphanRemoval: true,
    )]
    #[ORM\OrderBy([
        'id' => 'ASC',
    ])]
    private Collection $items;

    /**
     * @var Collection<int, Payment>
     */
    #[ORM\OneToMany(
        mappedBy: 'order',
        targetEntity: Payment::class,
        cascade: ['persist'],
        orphanRemoval: false,
    )]
    #[ORM\OrderBy([
        'createdAt' => 'ASC',
        'id' => 'ASC',
    ])]
    private Collection $payments;

    public function __construct(
        OrderNumber $orderNumber,
        User $user,
        ?Customer $customer = null,
        ?string $note = null,
    ) {
        $this->orderNumber = $orderNumber;
        $this->user = $user;
        $this->customer = $customer;
        $this->status = OrderStatus::DRAFT;

        $this->subtotal = Money::zero();
        $this->total = Money::zero();

        $this->note = self::normalizeNullableString($note);

        $this->createdAt = new \DateTimeImmutable();

        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): OrderNumber
    {
        return $this->orderNumber;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function changeCustomer(?Customer $customer): void
    {
        if (!$this->isDraft()) {
            throw new \DomainException(
                'Customer can only be changed while order is draft.',
            );
        }

        $this->customer = $customer;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === OrderStatus::DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === OrderStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::CANCELLED;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): void
    {
        if (!$this->isDraft()) {
            throw new \DomainException(
                'Order items can only be changed while order is draft.',
            );
        }

        $item->assignOrder($this);

        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }

        $this->recalculateTotals();
    }

    public function removeItem(OrderItem $item): void
    {
        if (!$this->isDraft()) {
            throw new \DomainException(
                'Order items can only be changed while order is draft.',
            );
        }

        $this->items->removeElement($item);

        $this->recalculateTotals();
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    /**
     * No discount field in MVP.
     *
     * total = subtotal
     */
    public function recalculateTotals(): void
    {
        $subtotal = Money::zero();

        foreach ($this->items as $item) {
            $subtotal = $subtotal->add(
                $item->getSubtotal(),
            );
        }

        $this->subtotal = $subtotal;
        $this->total = $subtotal;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): void
    {
        if (!$this->isDraft()) {
            throw new \DomainException(
                'Payments can only be changed while order is draft.',
            );
        }

        $payment->assignOrder($this);

        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
        }
    }

    public function getPaidAmount(): Money
    {
        $paid = Money::zero();

        foreach ($this->payments as $payment) {
            $paid = $paid->add(
                $payment->getAmount(),
            );
        }

        return $paid;
    }

    public function getDebtAmount(): Money
    {
        $paid = $this->getPaidAmount();

        if ($paid->isGreaterThanOrEqual($this->total)) {
            return Money::zero();
        }

        return $this->total->subtract($paid);
    }

    public function canComplete(): bool
    {
        if (!$this->isDraft()) {
            return false;
        }

        if ($this->items->isEmpty()) {
            return false;
        }

        $this->recalculateTotals();

        return $this->getPaidAmount()->isLessThanOrEqual($this->total);
    }

    public function complete(
        ?\DateTimeImmutable $completedAt = null,
    ): void {
        if (!$this->isDraft()) {
            throw new \DomainException(
                'Only draft orders can be completed.',
            );
        }

        if ($this->items->isEmpty()) {
            throw new \DomainException(
                'An order must contain at least one item.',
            );
        }

        $this->recalculateTotals();

        $paidAmount = $this->getPaidAmount();

        if ($paidAmount->isGreaterThan($this->total)) {
            throw new \DomainException(
                'Payment amount cannot exceed order total.',
            );
        }

        $this->status = OrderStatus::COMPLETED;
        $this->completedAt = $completedAt ?? new \DateTimeImmutable();
    }

    public function cancel(
        ?\DateTimeImmutable $cancelledAt = null,
    ): void {
        if ($this->status !== OrderStatus::COMPLETED) {
            throw new \DomainException(
                'Only completed orders can be cancelled.',
            );
        }

        $this->status = OrderStatus::CANCELLED;
        $this->cancelledAt = $cancelledAt ?? new \DateTimeImmutable();
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function changeNote(?string $note): void
    {
        if (!$this->isDraft()) {
            throw new \DomainException(
                'Order note cannot be changed after completion.',
            );
        }

        $this->note = self::normalizeNullableString($note);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    private static function normalizeNullableString(
        ?string $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
