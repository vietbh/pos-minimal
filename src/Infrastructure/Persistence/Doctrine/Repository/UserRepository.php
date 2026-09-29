<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\Enum\UserRole;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
    }

    public function findById(int $id): ?User
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->find($id);
    }

    public function findByIdForUpdate(int $id): ?User
    {
        return $this->entityManager->find(User::class, $id, LockMode::PESSIMISTIC_WRITE);
    }

    public function findRoot(): ?User
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.roles LIKE :rootPattern')
            ->setParameter('rootPattern', '%"' . UserRole::ROOT->value . '"%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUsername(string $username): ?User
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.username = :username')
            ->setParameter('username', $username)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<User> */
    public function findAllOrderedByUsername(): array
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.username', 'ASC')
            ->addOrderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function searchPage(
        ?string $search,
        ?bool $active,
        ?UserRole $role,
        int $page,
        int $limit,
        array $visibleRoles,
    ): array {
        $page = max(1, $page);
        $limit = min(50, max(1, $limit));
        $offset = ($page - 1) * $limit;

        $qb = $this->entityManager
            ->createQueryBuilder()
            ->from(User::class, 'u');

        // ROOT is a protected bootstrap identity and is never returned by the
        // normal user-management listing, even if a caller passes it in.
        $qb->andWhere('u.roles NOT LIKE :rootPattern')
            ->setParameter('rootPattern', '%\"' . UserRole::ROOT->value . '\"%');

        if ($visibleRoles === []) {
            $qb->andWhere('1 = 0');
        } else {
            $rolePredicates = [];
            foreach (array_values($visibleRoles) as $index => $visibleRole) {
                $parameter = 'visibleRole' . $index;
                $rolePredicates[] = 'u.roles LIKE :' . $parameter;
                $qb->setParameter($parameter, '%"' . $visibleRole->value . '"%');
            }
            $qb->andWhere('(' . implode(' OR ', $rolePredicates) . ')');
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('LOWER(u.username) LIKE LOWER(:search)')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        if ($active !== null) {
            $qb->andWhere('u.isActive = :active')
                ->setParameter('active', $active);
        }

        if ($role !== null) {
            $qb->andWhere('u.roles LIKE :rolePattern')
                ->setParameter('rolePattern', '%"' . $role->value . '"%');
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->select('u')
            ->orderBy('u.username', 'ASC')
            ->addOrderBy('u.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => array_values(array_filter($items, static fn (mixed $item): bool => $item instanceof User)),
            'total' => $total,
        ];
    }
}
