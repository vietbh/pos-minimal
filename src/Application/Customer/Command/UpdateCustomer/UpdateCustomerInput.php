<?php

declare(strict_types=1);

namespace App\Application\Customer\Command\UpdateCustomer;

final readonly class UpdateCustomerInput
{
    public function __construct(
        public int $customerId,
        public string $name,
        public ?string $phone = null,
        public ?string $note = null,
    ) {
    }
}
