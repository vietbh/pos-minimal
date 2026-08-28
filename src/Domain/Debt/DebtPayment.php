<?php

declare(strict_types=1);

namespace App\Domain\Debt;

use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'debt_payments',
    indexes: [
        new ORM\Index(
            name: 'idx_debt_payment_debt_created',
            columns: ['debt_id', 'created_at'],
        ),
        new ORM\Index(
            name: 'idx_debt_payment_user_created',
            columns: ['user_id', 'created_at'],
        ),
    ],
)]
class DebtPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Debt::class,
        inversedBy: 'payments',
    )]
    #[ORM\JoinColumn(
        name: 'debt_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private Debt $debt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(
        name: 'user_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private User $user;

    #[ORM\Column(
        type: 'money',
    )]
    private Money $amount;

    #[ORM\Column(
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Money $amount,
        User $user,
    ) {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException(
                'Debt payment amount must be greater than zero.',
            );
        }

        $this->amount = $amount;
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDebt(): Debt
    {
        return $this->debt;
    }

    public function assignDebt(Debt $debt): void
    {
        if (isset($this->debt) && $this->debt !== $debt) {
            throw new \DomainException(
                'Debt payment cannot be moved to another debt.',
            );
        }

        $this->debt = $debt;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
