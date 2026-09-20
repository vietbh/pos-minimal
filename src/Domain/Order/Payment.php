<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Payment\Enum\PaymentMethod;
use App\Domain\Payment\PaymentBankAccount;
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
#[ORM\UniqueConstraint(
    name: 'UNIQ_PAYMENTS_REFERENCE',
    columns: ['reference']
)]
#[ORM\Index(
    name: 'idx_payment_bank_account',
    columns: ['payment_bank_account_id']
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

    #[ORM\ManyToOne(targetEntity: PaymentBankAccount::class)]
    #[ORM\JoinColumn(
        name: 'payment_bank_account_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'RESTRICT',
    )]
    private ?PaymentBankAccount $bankAccount = null;

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
        ?PaymentBankAccount $bankAccount = null,
    ) {
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException(
                'Payment amount must be greater than zero.',
            );
        }

        $this->amount = $amount;
        $this->method = $method;
        $this->user = $user;
        $this->reference = self::normalizeNullableString($reference);
        if ($this->reference === null && $bankAccount !== null) {
            $this->reference = self::generatePaymentReference();
        }
        $this->bankAccount = $bankAccount;
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

    public function getBankAccount(): ?PaymentBankAccount
    {
        return $this->bankAccount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function generatePaymentReference(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bytes = random_bytes(8);
        $suffix = '';

        for ($i = 0, $length = strlen($bytes); $i < $length; ++$i) {
            $suffix .= $alphabet[ord($bytes[$i]) % strlen($alphabet)];
        }

        return 'PAY' . $suffix;
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
