<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use App\Domain\Order\Order;
use App\Domain\Product\Product;
use App\Domain\Stock\Enum\StockMovementType;
use App\Domain\User\User;
use App\Domain\User\UserSession;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'stock_movements')]
#[ORM\Index(
    name: 'idx_stock_movement_product_created',
    columns: ['product_id', 'created_at'],
)]
#[ORM\Index(
    name: 'idx_stock_movement_order',
    columns: ['order_id'],
)]
#[ORM\Index(
    name: 'idx_stock_movement_user_created',
    columns: ['user_id', 'created_at'],
)]
#[ORM\Index(
    name: 'idx_stock_movement_type_created',
    columns: ['type', 'created_at'],
)]
class StockMovement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(
        name: 'product_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(
        name: 'order_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'RESTRICT',
    )]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(
        name: 'user_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private User $user;

    #[ORM\ManyToOne(targetEntity: UserSession::class)]
    #[ORM\JoinColumn(
        name: 'session_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'RESTRICT',
    )]
    private ?UserSession $session = null;

    #[ORM\Column(
        enumType: StockMovementType::class,
        length: 30,
    )]
    private StockMovementType $type;

    #[ORM\Column(
        name: 'quantity_before',
        type: 'integer',
        options: ['unsigned' => true],
    )]
    private int $quantityBefore;

    #[ORM\Column(
        name: 'quantity_change',
        type: 'integer',
    )]
    private int $quantityChange;

    #[ORM\Column(
        name: 'quantity_after',
        type: 'integer',
        options: ['unsigned' => true],
    )]
    private int $quantityAfter;

    #[ORM\Column(
        type: 'string',
        length: 255,
        nullable: true,
    )]
    private ?string $reason = null;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Product $product,
        StockMovementType $type,
        int $quantityBefore,
        int $quantityChange,
        User $user,
        ?Order $order = null,
        ?UserSession $session = null,
        ?string $reason = null,
    ) {
        if ($quantityBefore < 0) {
            throw new \InvalidArgumentException(
                'Quantity before cannot be negative.',
            );
        }

        if ($quantityChange === 0) {
            throw new \InvalidArgumentException(
                'Quantity change cannot be zero.',
            );
        }

        $quantityAfter = $quantityBefore + $quantityChange;

        if ($quantityAfter < 0) {
            throw new \InvalidArgumentException(
                'Quantity after cannot be negative.',
            );
        }

        $this->product = $product;
        $this->type = $type;
        $this->quantityBefore = $quantityBefore;
        $this->quantityChange = $quantityChange;
        $this->quantityAfter = $quantityAfter;

        $this->user = $user;
        $this->order = $order;
        $this->session = $session;

        $reason = $reason !== null ? trim($reason) : null;
        $this->reason = $reason === '' ? null : $reason;

        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSession(): ?UserSession
    {
        return $this->session;
    }

    public function getType(): StockMovementType
    {
        return $this->type;
    }

    public function getQuantityBefore(): int
    {
        return $this->quantityBefore;
    }

    public function getQuantityChange(): int
    {
        return $this->quantityChange;
    }

    public function getQuantityAfter(): int
    {
        return $this->quantityAfter;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
