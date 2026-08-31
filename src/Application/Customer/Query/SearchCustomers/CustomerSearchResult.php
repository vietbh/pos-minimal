<?php

declare(strict_types=1);

namespace App\Application\Customer\Query\SearchCustomers;

final readonly class CustomerSearchResult
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $phone,
    ) {
    }
}
