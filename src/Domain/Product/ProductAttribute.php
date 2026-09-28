<?php

declare(strict_types=1);

namespace App\Domain\Product;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_attributes')]
#[ORM\Index(name: 'idx_product_attribute_product', columns: ['product_id'])]
class ProductAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(name: 'attribute_name', length: 100)]
    private string $name;

    #[ORM\Column(name: 'attribute_value', length: 255)]
    private string $value;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['unsigned' => true])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'is_selectable', type: 'boolean', options: ['default' => true])]
    private bool $selectable = true;

    public function __construct(Product $product, string $name, string $value, int $sortOrder = 0, bool $selectable = true)
    {
        $name = trim($name);
        $value = trim($value);
        if ($name === '') throw new \InvalidArgumentException('Tên thuộc tính không được để trống.');
        if ($value === '') throw new \InvalidArgumentException('Giá trị thuộc tính không được để trống.');
        if (mb_strlen($name) > 100) throw new \InvalidArgumentException('Tên thuộc tính không được vượt quá 100 ký tự.');
        if (mb_strlen($value) > 255) throw new \InvalidArgumentException('Giá trị thuộc tính không được vượt quá 255 ký tự.');
        if ($sortOrder < 0) throw new \InvalidArgumentException('Thứ tự thuộc tính không hợp lệ.');
        $this->product = $product;
        $this->name = $name;
        $this->value = $value;
        $this->sortOrder = $sortOrder;
        $this->selectable = $selectable;
    }

    public function getId(): ?int { return $this->id; }
    public function getProduct(): Product { return $this->product; }
    public function getName(): string { return $this->name; }
    public function getValue(): string { return $this->value; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function isSelectable(): bool { return $this->selectable; }
}
