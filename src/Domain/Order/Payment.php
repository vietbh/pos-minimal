<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Shared\ValueObject\Money;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]

#[ORM\Table(name: 'payments')]
#[ORM\Index(
    name: 'idx_payment_order_created',
    columns: ['order_id', 'created_at'],
)]
#[ORM\Index(
    name: 'idx_payment_user_created',
    columns: ['user_id', 'created_at'],
)]
#[ORM\Index(
    name: 'idx_payment_method_created',
    columns: ['method', 'created_at'],
)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Order::class,
        inversedBy: 'payments',
    )]
    #[ORM\JoinColumn(
        name: 'order_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private Order $order;

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
        enumType: PaymentMethod::class,
        length: 30,
    )]
    private PaymentMethod $method;

    #[ORM\Column(
        length: 255,
        nullable: true,
    )]
    private ?string $reference = null;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Money $amount,
        PaymentMethod $method,
        User $user,
        ?string $reference = null,
    ) {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException(
                'Payment amount must be greater than zero.',
            );
        }

        $this->amount = $amount;
        $this->method = $method;
        $this->user = $user;
        $this->reference = self::normalizeNullableString(
            $reference,
        );
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
    public function assignOrder(Order $order): void
    {
        if (isset($this->order) && $this->order !== $order) {
            throw new \DomainException(
                'Order item cannot be moved to another order.',
            );
        }

        $this->order = $order;
    }
    public function getUser(): User
    {
        return $this->user;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
