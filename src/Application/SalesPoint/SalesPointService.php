<?php

declare(strict_types=1);

namespace App\Application\SalesPoint;

use App\Domain\SalesPoint\Enum\SalesPointType;
use App\Domain\SalesPoint\Repository\SalesPointGroupRepositoryInterface;
use App\Domain\SalesPoint\Repository\SalesPointRepositoryInterface;
use App\Domain\SalesPoint\SalesPoint;
use App\Domain\SalesPoint\SalesPointGroup;
use Doctrine\ORM\EntityManagerInterface;

final readonly class SalesPointService
{
    public function __construct(
        private SalesPointRepositoryInterface $salesPoints,
        private SalesPointGroupRepositoryInterface $groups,
        private EntityManagerInterface $entityManager,
    ) {}

    public function createGroup(string $code, string $name, int $sortOrder = 0): SalesPointGroup
    {
        if ($this->groups->existsByCode($code)) throw new \DomainException('Sales point group code already exists.');
        $group = new SalesPointGroup($code, $name, $sortOrder);
        $this->groups->save($group);
        $this->entityManager->flush();
        return $group;
    }

    public function createPoint(string $code, string $name, SalesPointType $type, ?int $groupId = null): SalesPoint
    {
        if ($this->salesPoints->existsByCode($code)) throw new \DomainException('Sales point code already exists.');
        $group = $groupId !== null ? $this->groups->findById($groupId) : null;
        if ($groupId !== null && $group === null) throw new \DomainException('Sales point group not found.');
        if ($group !== null && !$group->isActive()) throw new \DomainException('Sales point group is inactive.');
        $point = new SalesPoint($code, $name, $type, $group);
        $this->salesPoints->save($point);
        $this->entityManager->flush();
        return $point;
    }

    public function updatePoint(int $id, string $code, string $name, SalesPointType $type, ?int $groupId): SalesPoint
    {
        $point = $this->salesPoints->findById($id);
        if ($point === null) throw new \DomainException('Sales point not found.');
        if ($this->salesPoints->existsByCode($code, $id)) throw new \DomainException('Sales point code already exists.');
        $group = $groupId !== null ? $this->groups->findById($groupId) : null;
        if ($groupId !== null && $group === null) throw new \DomainException('Sales point group not found.');
        if ($group !== null && !$group->isActive()) throw new \DomainException('Sales point group is inactive.');
        $point->changeCode($code);
        $point->rename($name);
        $point->changeType($type);
        $point->moveToGroup($group);
        $this->entityManager->flush();
        return $point;
    }

    public function setPointActive(int $id, bool $active): void
    {
        $point = $this->salesPoints->findById($id);
        if ($point === null) throw new \DomainException('Sales point not found.');
        $active ? $point->activate() : $point->deactivate();
        $this->entityManager->flush();
    }
}
