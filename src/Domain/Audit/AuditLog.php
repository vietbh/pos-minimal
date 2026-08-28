<?php

declare(strict_types=1);

namespace App\Domain\Audit;

use App\Domain\User\User;
use App\Domain\User\UserSession;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'audit_logs',
    indexes: [
        new ORM\Index(
            name: 'idx_audit_user_created',
            columns: ['user_id', 'created_at'],
        ),
        new ORM\Index(
            name: 'idx_audit_entity',
            columns: ['entity_type', 'entity_id'],
        ),
        new ORM\Index(
            name: 'idx_audit_action_created',
            columns: ['action', 'created_at'],
        ),
        new ORM\Index(
            name: 'idx_audit_created',
            columns: ['created_at'],
        ),
    ],
)]
class AuditLog
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
        nullable: true,
        onDelete: 'RESTRICT',
    )]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: UserSession::class)]
    #[ORM\JoinColumn(
        name: 'session_id',
        referencedColumnName: 'id',
        nullable: true,
        onDelete: 'RESTRICT',
    )]
    private ?UserSession $session = null;

    #[ORM\Column(
        length: 100,
    )]
    private string $action;

    #[ORM\Column(
        name: 'entity_type',
        length: 100,
        nullable: true,
    )]
    private ?string $entityType = null;

    #[ORM\Column(
        name: 'entity_id',
        length: 100,
        nullable: true,
    )]
    private ?string $entityId = null;

    #[ORM\Column(
        name: 'old_values',
        type: 'json',
        nullable: true,
    )]
    private ?array $oldValues = null;

    #[ORM\Column(
        name: 'new_values',
        type: 'json',
        nullable: true,
    )]
    private ?array $newValues = null;

    #[ORM\Column(
        name: 'ip_address',
        length: 45,
        nullable: true,
    )]
    private ?string $ipAddress = null;

    #[ORM\Column(
        name: 'user_agent',
        length: 500,
        nullable: true,
    )]
    private ?string $userAgent = null;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable',
    )]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $action,
        ?User $user = null,
        ?UserSession $session = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $action = trim($action);

        if ($action === '') {
            throw new \InvalidArgumentException(
                'Audit action cannot be empty.',
            );
        }

        $entityType = self::normalizeNullableString($entityType);
        $entityId = self::normalizeNullableString($entityId);
        $ipAddress = self::normalizeNullableString($ipAddress);
        $userAgent = self::normalizeNullableString($userAgent);

        if ($ipAddress !== null && !filter_var(
                $ipAddress,
                FILTER_VALIDATE_IP,
            )) {
            throw new \InvalidArgumentException(
                'Invalid IP address.',
            );
        }

        $this->action = $action;
        $this->user = $user;
        $this->session = $session;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->oldValues = $oldValues;
        $this->newValues = $newValues;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getSession(): ?UserSession
    {
        return $this->session;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function getOldValues(): ?array
    {
        return $this->oldValues;
    }

    public function getNewValues(): ?array
    {
        return $this->newValues;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
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
