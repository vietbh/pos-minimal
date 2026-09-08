<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Order\Enum\FinancialReversalType;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'order_financial_reversals')]
#[ORM\Index(name: 'idx_order_financial_reversal_type_created', columns: ['type', 'created_at'])]
class OrderFinancialReversal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'RESTRICT')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $user;

    #[ORM\Column(enumType: FinancialReversalType::class, length: 30)]
    private FinancialReversalType $type;

    #[ORM\Column(type: 'money')]
    private Money $amount;

    #[ORM\Column(length: 255)]
    private string $reason;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Order $order,
        User $user,
        FinancialReversalType $type,
        Money $amount,
        string $reason,
    ) {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('Financial reversal amount must be greater than zero.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Financial reversal reason cannot be empty.');
        }

        if (mb_strlen($reason) > 255) {
            throw new \InvalidArgumentException('Financial reversal reason cannot exceed 255 characters.');
        }

        $this->order = $order;
        $this->user = $user;
        $this->type = $type;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrder(): Order { return $this->order; }
    public function getUser(): User { return $this->user; }
    public function getType(): FinancialReversalType { return $this->type; }
    public function getAmount(): Money { return $this->amount; }
    public function getReason(): string { return $this->reason; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
