<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Product\ValueObject\Sku;
use App\Domain\Shared\ValueObject\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'products')]
#[ORM\Index(
    name: 'idx_product_name',
    columns: ['name'],
)]
#[ORM\Index(
    name: 'idx_product_active_name',
    columns: ['is_active', 'name'],
)]
#[ORM\Index(
    name: 'idx_product_stock',
    columns: ['stock_quantity'],
)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\Column(
        type: 'sku',
        length: 100,
        unique: true,
        nullable: true,
    )]
    private ?Sku $sku = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(
        name: 'selling_price',
        type: 'money',
    )]
    private Money $sellingPrice;

    #[ORM\Column(
        name: 'cost_price',
        type: 'money',
        nullable: true,
    )]
    private ?Money $costPrice = null;

    #[ORM\Column(
        name: 'stock_quantity',
        type: 'integer',
        options: ['unsigned' => true],
    )]
    private int $stockQuantity = 0;

    #[ORM\Column(
        name: 'low_stock_threshold',
        type: 'integer',
        options: ['unsigned' => true],
    )]
    private int $lowStockThreshold = 0;

    #[ORM\Column(
        name: 'is_active',
        options: ['default' => true],
    )]
    private bool $isActive = true;

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
        name: 'updated_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, ProductImage>
     */
    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductImage::class,
        cascade: ['persist'],
    )]
    #[ORM\OrderBy([
        'sortOrder' => 'ASC',
        'id' => 'ASC',
    ])]
    private Collection $images;

    public function __construct(
        string $name,
        Money $sellingPrice,
        ?Sku $sku = null,
        ?string $unit = null,
        ?Money $costPrice = null,
        int $lowStockThreshold = 0,
        ?string $note = null,
    ) {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException(
                'Product name cannot be empty.',
            );
        }

        if (!$sellingPrice->isPositive() && !$sellingPrice->isZero()) {
            throw new \InvalidArgumentException(
                'Selling price cannot be negative.',
            );
        }

        if ($costPrice !== null
            && !$costPrice->isPositive()
            && !$costPrice->isZero()
        ) {
            throw new \InvalidArgumentException(
                'Cost price cannot be negative.',
            );
        }

        if ($lowStockThreshold < 0) {
            throw new \InvalidArgumentException(
                'Low stock threshold cannot be negative.',
            );
        }

        $this->name = $name;
        $this->sku = $sku;
        $this->unit = self::normalizeNullableString($unit);
        $this->sellingPrice = $sellingPrice;
        $this->costPrice = $costPrice;
        $this->lowStockThreshold = $lowStockThreshold;
        $this->note = self::normalizeNullableString($note);

        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;

        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): ?Sku
    {
        return $this->sku;
    }

    public function changeSku(?Sku $sku): void
    {
        $this->sku = $sku;
        $this->touch();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException(
                'Product name cannot be empty.',
            );
        }

        $this->name = $name;
        $this->touch();
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function changeUnit(?string $unit): void
    {
        $this->unit = self::normalizeNullableString($unit);
        $this->touch();
    }

    public function getSellingPrice(): Money
    {
        return $this->sellingPrice;
    }

    public function changeSellingPrice(Money $sellingPrice): void
    {
        $this->sellingPrice = $sellingPrice;
        $this->touch();
    }

    public function getCostPrice(): ?Money
    {
        return $this->costPrice;
    }

    public function changeCostPrice(?Money $costPrice): void
    {
        $this->costPrice = $costPrice;
        $this->touch();
    }

    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    /**
     * Stock mutation is intentionally not exposed as a generic setter.
     *
     * Actual stock changes must later go through the stock application/domain
     * operation so that StockMovement is created in the same transaction.
     */
    public function increaseStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Stock increase quantity must be greater than zero.',
            );
        }

        $this->stockQuantity += $quantity;
    }

    public function decreaseStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException(
                'Stock decrease quantity must be greater than zero.',
            );
        }

        if ($quantity > $this->stockQuantity) {
            throw new \DomainException(
                'Insufficient stock.',
            );
        }

        $this->stockQuantity -= $quantity;
    }

    /**
     * Used by controlled stock initialization/adjustment workflows.
     *
     * This method must NOT be called directly from a controller.
     * The application/domain service must create the corresponding
     * StockMovement in the same transaction.
     */
    public function setStockQuantityForAdjustment(int $quantity): void
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException(
                'Stock quantity cannot be negative.',
            );
        }

        $this->stockQuantity = $quantity;
    }

    public function getLowStockThreshold(): int
    {
        return $this->lowStockThreshold;
    }

    public function changeLowStockThreshold(int $threshold): void
    {
        if ($threshold < 0) {
            throw new \InvalidArgumentException(
                'Low stock threshold cannot be negative.',
            );
        }

        $this->lowStockThreshold = $threshold;
        $this->touch();
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function activate(): void
    {
        if ($this->isActive) {
            return;
        }

        $this->isActive = true;
        $this->touch();
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            return;
        }

        $this->isActive = false;
        $this->touch();
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function changeNote(?string $note): void
    {
        $this->note = self::normalizeNullableString($note);
        $this->touch();
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): void
    {
        if ($image->getProduct() !== $this) {
            $image->assignProduct($this);
        }

        if (!$this->images->contains($image)) {
            $this->images->add($image);
        }
    }

    public function removeImage(ProductImage $image): void
    {
        $this->images->removeElement($image);
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
