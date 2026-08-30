<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Product\Enum\ImageStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'product_images')]
#[ORM\Index(
    name: 'idx_product_image_product_sort',
    columns: ['product_id', 'sort_order'],
)]
#[ORM\Index(
    name: 'idx_product_image_product_primary',
    columns: ['product_id', 'is_primary'],
)]
#[ORM\Index(
    name: 'idx_product_image_status',
    columns: ['status'],
)]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Product::class,
        inversedBy: 'images',
    )]
    #[ORM\JoinColumn(
        name: 'product_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private Product $product;

    #[ORM\Column(
        name: 'storage_key',
        length: 500,
    )]
    private string $storageKey;

    #[ORM\Column(
        name: 'original_filename',
        length: 255,
        nullable: true,
    )]
    private ?string $originalFilename = null;

    #[ORM\Column(
        name: 'mime_type',
        length: 100,
    )]
    private string $mimeType;

    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private int $size;

    #[ORM\Column(
        type: 'integer',
        options: ['unsigned' => true],
        nullable: true,
    )]
    private ?int $width = null;

    #[ORM\Column(
        type: 'integer',
        options: ['unsigned' => true],
        nullable: true,
    )]
    private ?int $height = null;

    #[ORM\Column(
        enumType: ImageStatus::class,
        length: 30,
    )]
    private ImageStatus $status;

    #[ORM\Column(
        name: 'sort_order',
        type: 'integer',
        options: ['unsigned' => true],
    )]
    private int $sortOrder = 0;

    #[ORM\Column(
        name: 'is_primary',
        options: ['default' => false],
    )]
    private bool $isPrimary = false;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(
        name: 'updated_at',
        type: 'datetime_immutable',
        nullable: true,
    )]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct(
        string $storageKey,
        string $mimeType,
        int $size,
        ?string $originalFilename = null,
        ?int $width = null,
        ?int $height = null,
    ) {
        $storageKey = trim($storageKey);
        $mimeType = trim($mimeType);

        if ($storageKey === '') {
            throw new \InvalidArgumentException(
                'Storage key cannot be empty.',
            );
        }

        if ($mimeType === '') {
            throw new \InvalidArgumentException(
                'MIME type cannot be empty.',
            );
        }

        if ($size <= 0) {
            throw new \InvalidArgumentException(
                'Image size must be greater than zero.',
            );
        }

        if ($width !== null && $width <= 0) {
            throw new \InvalidArgumentException(
                'Image width must be greater than zero.',
            );
        }

        if ($height !== null && $height <= 0) {
            throw new \InvalidArgumentException(
                'Image height must be greater than zero.',
            );
        }

        $this->storageKey = $storageKey;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->originalFilename = self::normalizeFilename(
            $originalFilename,
        );
        $this->width = $width;
        $this->height = $height;
        $this->status = ImageStatus::UPLOADING;
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

    public function assignProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getStatus(): ImageStatus
    {
        return $this->status;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function changeSortOrder(int $sortOrder): void
    {
        if ($sortOrder < 0) {
            throw new \InvalidArgumentException(
                'Image sort order cannot be negative.',
            );
        }

        $this->sortOrder = $sortOrder;
        $this->touch();
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function markAsPrimary(): void
    {
        $this->isPrimary = true;
        $this->touch();
    }

    public function unmarkAsPrimary(): void
    {
        $this->isPrimary = false;
        $this->touch();
    }

    public function markProcessing(): void
    {
        if ($this->status !== ImageStatus::UPLOADING) {
            throw new \DomainException(
                'Only uploading images can start processing.',
            );
        }

        $this->status = ImageStatus::PROCESSING;
        $this->touch();
    }

    public function markReady(): void
    {
        if ($this->status !== ImageStatus::PROCESSING) {
            throw new \DomainException(
                'Only processing images can become ready.',
            );
        }

        $this->status = ImageStatus::READY;
        $this->touch();
    }

    public function markFailed(): void
    {
        if ($this->status !== ImageStatus::PROCESSING) {
            throw new \DomainException(
                'Only processing images can fail.',
            );
        }

        $this->status = ImageStatus::FAILED;
        $this->touch();
    }

    public function retryProcessing(): void
    {
        if ($this->status !== ImageStatus::FAILED) {
            throw new \DomainException(
                'Only failed images can be retried.',
            );
        }

        $this->status = ImageStatus::PROCESSING;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private static function normalizeFilename(
        ?string $filename,
    ): ?string {
        if ($filename === null) {
            return null;
        }

        $filename = trim($filename);

        return $filename === '' ? null : $filename;
    }
}
