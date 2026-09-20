<?php

declare(strict_types=1);

namespace App\Domain\Product;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'product_categories')]
#[ORM\Index(name: 'idx_product_category_active_name', columns: ['is_active', 'name'])]
#[ORM\UniqueConstraint(name: 'UNIQ_PRODUCT_CATEGORY_NAME', columns: ['name'])]
class ProductCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name)
    {
        $this->rename($name, false);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function isActive(): bool { return $this->isActive; }

    public function rename(string $name, bool $touch = true): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Category name cannot be empty.');
        }
        $this->name = $name;
        if ($touch) $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void { $this->isActive = true; $this->updatedAt = new \DateTimeImmutable(); }
    public function deactivate(): void { $this->isActive = false; $this->updatedAt = new \DateTimeImmutable(); }
}
