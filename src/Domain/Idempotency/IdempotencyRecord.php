<?php

declare(strict_types=1);

namespace App\Domain\Idempotency;

use App\Domain\Idempotency\Enum\IdempotencyStatus;
use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'idempotency_records')]
#[ORM\Index(
    name: 'idx_idempotency_user_created',
    columns: ['user_id', 'created_at'],
)]
#[ORM\Index(
    name: 'idx_idempotency_status_created',
    columns: ['status', 'created_at'],
)]
#[ORM\UniqueConstraint(
    name: 'uq_idempotency_user_key',
    columns: ['user_id', 'idempotency_key'],
)]
class IdempotencyRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(
        name: 'user_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT',
    )]
    private User $user;

    #[ORM\Column(
        name: 'idempotency_key',
        length: 255,
    )]
    private string $idempotencyKey;

    #[ORM\Column(
        length: 100,
    )]
    private string $operation;

    #[ORM\Column(
        enumType: IdempotencyStatus::class,
        length: 30,
    )]
    private IdempotencyStatus $status;

    #[ORM\Column(
        name: 'request_hash',
        length: 64,
        nullable: true,
    )]
    private ?string $requestHash = null;

    #[ORM\Column(
        name: 'response_status',
        type: 'integer',
        nullable: true,
    )]
    private ?int $responseStatus = null;

    #[ORM\Column(
        name: 'response_body',
        type: 'json',
        nullable: true,
    )]
    private ?array $responseBody = null;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(
        name: 'completed_at',
        type: 'datetime_immutable',
        nullable: true,
    )]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(
        User $user,
        string $idempotencyKey,
        string $operation,
        ?string $requestHash = null,
    ) {
        $idempotencyKey = trim($idempotencyKey);
        $operation = trim($operation);

        if ($idempotencyKey === '') {
            throw new \InvalidArgumentException(
                'Idempotency key cannot be empty.',
            );
        }

        if ($operation === '') {
            throw new \InvalidArgumentException(
                'Idempotency operation cannot be empty.',
            );
        }

        if ($requestHash !== null) {
            $requestHash = trim($requestHash);

            if (
                $requestHash !== ''
                && !preg_match('/^[a-f0-9]{64}$/', $requestHash)
            ) {
                throw new \InvalidArgumentException(
                    'Request hash must be a SHA-256 hexadecimal hash.',
                );
            }

            $requestHash = $requestHash === ''
                ? null
                : $requestHash;
        }

        $this->user = $user;
        $this->idempotencyKey = $idempotencyKey;
        $this->operation = $operation;
        $this->requestHash = $requestHash;
        $this->status = IdempotencyStatus::PROCESSING;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getStatus(): IdempotencyStatus
    {
        return $this->status;
    }

    public function getRequestHash(): ?string
    {
        return $this->requestHash;
    }

    public function getResponseStatus(): ?int
    {
        return $this->responseStatus;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function markCompleted(
        int $responseStatus,
        ?array $responseBody = null,
    ): void {
        if ($this->status !== IdempotencyStatus::PROCESSING) {
            throw new \DomainException(
                'Only processing idempotency records can be completed.',
            );
        }

        if ($responseStatus < 100 || $responseStatus > 599) {
            throw new \InvalidArgumentException(
                'Invalid HTTP response status.',
            );
        }

        $this->status = IdempotencyStatus::COMPLETED;
        $this->responseStatus = $responseStatus;
        $this->responseBody = $responseBody;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function markFailed(
        int $responseStatus,
        ?array $responseBody = null,
    ): void {
        if ($this->status !== IdempotencyStatus::PROCESSING) {
            throw new \DomainException(
                'Only processing idempotency records can be failed.',
            );
        }

        if ($responseStatus < 100 || $responseStatus > 599) {
            throw new \InvalidArgumentException(
                'Invalid HTTP response status.',
            );
        }

        $this->status = IdempotencyStatus::FAILED;
        $this->responseStatus = $responseStatus;
        $this->responseBody = $responseBody;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function isProcessing(): bool
    {
        return $this->status === IdempotencyStatus::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === IdempotencyStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === IdempotencyStatus::FAILED;
    }
}
