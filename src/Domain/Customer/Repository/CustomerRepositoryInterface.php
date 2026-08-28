<?php

declare(strict_types=1);

namespace App\Domain\Customer\Repository;

use App\Domain\Customer\Customer;

interface CustomerRepositoryInterface
{
    public function save(Customer $customer): void;

    public function findById(int $id): ?Customer;

    public function findByPhone(string $phone): ?Customer;

    public function existsByPhone(
        string $phone,
        ?int $excludeId = null,
    ): bool;
}
