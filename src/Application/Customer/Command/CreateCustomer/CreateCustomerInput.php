<?php

declare(strict_types=1);

namespace App\Application\Customer\Command\CreateCustomer;

final readonly class CreateCustomerInput
{
    public function __construct(
        public string $name,
        public ?string $phone = null,
        public ?string $note = null,
    ) {
    }
}
