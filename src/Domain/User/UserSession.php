<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Enum\SessionStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'user_sessions',
    indexes: [
        new ORM\Index(
            name: 'idx_user_session_user_status',
            columns: ['user_id', 'status']
        ),
        new ORM\Index(
            name: 'idx_user_session_status_activity',
            columns: ['status', 'last_activity_at']
        ),
    ],
)]
class UserSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true]
    )]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: User::class,
        inversedBy: 'sessions'
    )]
    #[ORM\JoinColumn(
        name: 'user_id',
        referencedColumnName: 'id',
        nullable: false,
        onDelete: 'RESTRICT'
    )]
    private User $user;

    #[ORM\Column(
        name: 'session_identifier',
        length: 255,
        unique: true
    )]
    private string $sessionIdentifier;

    #[ORM\Column(
        name: 'login_at',
        type: 'datetime_immutable'
    )]
    private \DateTimeImmutable $loginAt;

    #[ORM\Column(
        name: 'last_activity_at',
        type: 'datetime_immutable'
    )]
    private \DateTimeImmutable $lastActivityAt;

    #[ORM\Column(
        name: 'logout_at',
        type: 'datetime_immutable',
        nullable: true
    )]
    private ?\DateTimeImmutable $logoutAt = null;

    #[ORM\Column(
        name: 'revoked_at',
        type: 'datetime_immutable',
        nullable: true
    )]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(
        name: 'ip_address',
        length: 45,
        nullable: true
    )]
    private ?string $ipAddress = null;

    #[ORM\Column(
        name: 'user_agent',
        length: 512,
        nullable: true
    )]
    private ?string $userAgent = null;

    #[ORM\Column(
        length: 100,
        nullable: true
    )]
    private ?string $device = null;

    #[ORM\Column(
        length: 30,
        enumType: SessionStatus::class
    )]
    private SessionStatus $status;

    public function __construct(
        string $sessionIdentifier,
        ?\DateTimeImmutable $loginAt = null,
    ) {
        $sessionIdentifier = trim($sessionIdentifier);

        if ($sessionIdentifier === '') {
            throw new \InvalidArgumentException(
                'Session identifier cannot be empty.'
            );
        }

        $now = $loginAt ?? new \DateTimeImmutable();

        $this->sessionIdentifier = $sessionIdentifier;
        $this->loginAt = $now;
        $this->lastActivityAt = $now;
        $this->status = SessionStatus::ACTIVE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function assignUser(User $user): void
    {
        $this->user = $user;
    }

    public function getSessionIdentifier(): string
    {
        return $this->sessionIdentifier;
    }

    public function getLoginAt(): \DateTimeImmutable
    {
        return $this->loginAt;
    }

    public function getLastActivityAt(): \DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function getLogoutAt(): ?\DateTimeImmutable
    {
        return $this->logoutAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function setDevice(?string $device): void
    {
        $this->device = $device;
    }

    public function getStatus(): SessionStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === SessionStatus::ACTIVE;
    }

    public function recordActivity(
        ?\DateTimeImmutable $at = null
    ): void {
        if (!$this->isActive()) {
            throw new \DomainException(
                'Only active sessions can record activity.'
            );
        }

        $this->lastActivityAt = $at ?? new \DateTimeImmutable();
    }

    public function logout(
        ?\DateTimeImmutable $at = null
    ): void {
        if (!$this->isActive()) {
            return;
        }

        $at ??= new \DateTimeImmutable();

        $this->logoutAt = $at;
        $this->lastActivityAt = $at;
        $this->status = SessionStatus::LOGGED_OUT;
    }

    public function revoke(
        ?\DateTimeImmutable $at = null
    ): void {
        if (!$this->isActive()) {
            return;
        }

        $at ??= new \DateTimeImmutable();

        $this->revokedAt = $at;
        $this->lastActivityAt = $at;
        $this->status = SessionStatus::REVOKED;
    }

    public function expire(
        ?\DateTimeImmutable $at = null
    ): void {
        if (!$this->isActive()) {
            return;
        }

        $at ??= new \DateTimeImmutable();

        $this->lastActivityAt = $at;
        $this->status = SessionStatus::EXPIRED;
    }
}
