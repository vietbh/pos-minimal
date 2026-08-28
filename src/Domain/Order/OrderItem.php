<?php

declare(strict_types=1);

namespace App\Domain\Order;

use App\Domain\Product\Product;
use App\Domain\Shared\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'order_items',
    indexes: [
        new ORM\Index(
            name: 'idx_order_item_order',
            columns: ['order_id'],
        ),
        new ORM\Index(
            name: 'idx_order_item_product',
            columns: ['product_id'],
        ),
    ],
)]
class OrderItem
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
        inversedBy: 'items',
    )]
    #[ORM\JoinColumn(
        name: 'order_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'CASCADE',
    )]
    private Order $order;

    #[ORM\ManyToOne(
        targetEntity: Product::class,
    )]
    #[ORM\JoinColumn(
        name: 'product_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private Product $product;

    /**
     * Historical snapshot.
     */
    #[ORM\Column(
        name: 'product_name',
        length: 255,
    )]
    private string $productName;

    /**
     * Historical snapshot.
     */
    #[ORM\Column(
        length: 100,
        nullable: true,
    )]
    private ?string $sku = null;

    #[ORM\Column(
        name: 'unit_price',
        type: 'money',
    )]
    private Money $unitPrice;

    #[ORM\Column(
        type: 'integer',
        options: ['unsigned' => true],
    )]
    private int $quantity;

    #[ORM\Column(
        type: 'money',
    )]
    private Money $subtotal;

    public function __construct(
        Product $product,
        int $quantity,
        ?Money $unitPrice = null,
    ) {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Order item quantity must be greater than zero.',
            );
        }

        $this->product = $product;
        $this->productName = $product->getName();
        $this->sku = $product->getSku()?->value();

        /*
         * Checkout must normally resolve the authoritative current price
         * from Product and pass it here.
         *
         * The optional argument keeps the entity usable for controlled
         * domain construction.
         */
        $this->unitPrice = $unitPrice ?? $product->getSellingPrice();
        $this->quantity = $quantity;
        $this->subtotal = $this->unitPrice->multiply($quantity);
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

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function getUnitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    public function changeQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Order item quantity must be greater than zero.',
            );
        }

        if (isset($this->order) && !$this->order->isDraft()) {
            throw new \DomainException(
                'Order item quantity cannot be changed after order completion.',
            );
        }

        $this->quantity = $quantity;
        $this->recalculateSubtotal();
    }

    private function recalculateSubtotal(): void
    {
        $this->subtotal = $this->unitPrice->multiply(
            $this->quantity,
        );
    }
}
