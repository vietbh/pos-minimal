<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\Enum\UserRole;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        type: 'bigint',
        options: ['unsigned' => true]
    )]
    private ?int $id = null;

    #[ORM\Column(
        length: 180,
        unique: true
    )]
    private string $username;

    /**
     * Nullable intentionally.
     *
     * MVP:
     *   password authentication
     *
     * Future:
     *   Google OAuth / external identity
     */
    #[ORM\Column(
        name: 'password_hash',
        length: 255,
        nullable: true
    )]
    private ?string $passwordHash = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(
        name: 'is_active',
        options: ['default' => true]
    )]
    private bool $isActive = true;

    #[ORM\Column(
        name: 'created_at',
        type: 'datetime_immutable'
    )]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(
        name: 'updated_at',
        type: 'datetime_immutable'
    )]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, UserSession>
     */
    #[ORM\OneToMany(
        targetEntity: UserSession::class,
        mappedBy: 'user'
    )]
    private Collection $sessions;

    public function __construct(
        string $username,
    ) {
        $username = trim($username);

        if ($username === '') {
            throw new \InvalidArgumentException(
                'Username cannot be empty.'
            );
        }

        $this->username = $username;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->sessions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function changeUsername(string $username): void
    {
        $username = trim($username);

        if ($username === '') {
            throw new \InvalidArgumentException(
                'Username cannot be empty.'
            );
        }

        $this->username = $username;
        $this->touch();
    }

    public function getPassword(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): void
    {
        if ($passwordHash === '') {
            throw new \InvalidArgumentException(
                'Password hash cannot be empty.'
            );
        }

        $this->passwordHash = $passwordHash;
        $this->touch();
    }

    public function hasPassword(): bool
    {
        return $this->passwordHash !== null;
    }

    public function eraseCredentials(): void
    {
        // No temporary plaintext credentials are stored on this entity.
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        if (!in_array(UserRole::USER->value, $roles, true)) {
            $roles[] = UserRole::USER->value;
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $normalizedRoles = [];

        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new \InvalidArgumentException(
                    'User roles must be strings.'
                );
            }

            $normalizedRoles[] = $role;
        }

        $this->roles = array_values(
            array_unique($normalizedRoles)
        );

        $this->touch();
    }

    public function hasRole(UserRole $role): bool
    {
        return in_array(
            $role->value,
            $this->getRoles(),
            true
        );
    }

    public function grantRole(UserRole $role): void
    {
        if (!$this->hasRole($role)) {
            $this->roles[] = $role->value;
            $this->roles = array_values(
                array_unique($this->roles)
            );

            $this->touch();
        }
    }

    public function revokeRole(UserRole $role): void
    {
        if ($role === UserRole::USER) {
            throw new \DomainException(
                'ROLE_USER cannot be revoked.'
            );
        }

        $this->roles = array_values(
            array_filter(
                $this->roles,
                static fn (string $existingRole): bool =>
                    $existingRole !== $role->value
            )
        );

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

    /**
     * @return Collection<int, UserSession>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(UserSession $session): void
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
        }

        if ($session->getUser() !== $this) {
            $session->assignUser($this);
        }
    }

    public function removeSession(UserSession $session): void
    {
        $this->sessions->removeElement($session);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
