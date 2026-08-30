<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'customers')]
#[ORM\Index(
    name: 'idx_customer_name',
    columns: ['name'],
)]
#[ORM\Index(
    name: 'idx_customer_phone',
    columns: ['phone'],
)]
class Customer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
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

    public function __construct(
        string  $name,
        ?string $phone = null,
        ?string $note = null,
    )
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException(
                'Customer name cannot be empty.',
            );
        }

        $this->name = $name;
        $this->phone = self::normalizePhone($phone);
        $this->note = self::normalizeNote($note);

        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
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
                'Customer name cannot be empty.',
            );
        }

        if ($name === $this->name) {
            return;
        }

        $this->name = $name;
        $this->touch();
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function changePhone(?string $phone): void
    {
        $normalizedPhone = self::normalizePhone($phone);

        if ($normalizedPhone === $this->phone) {
            return;
        }

        $this->phone = $normalizedPhone;
        $this->touch();
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function changeNote(?string $note): void
    {
        $normalizedNote = self::normalizeNote($note);

        if ($normalizedNote === $this->note) {
            return;
        }

        $this->note = $normalizedNote;
        $this->touch();
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

    private static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = trim($phone);

        return $phone === '' ? null : $phone;
    }

    private static function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = trim($note);

        return $note === '' ? null : $note;
    }
}
