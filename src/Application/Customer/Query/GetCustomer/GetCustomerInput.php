<?php

declare(strict_types=1);

namespace App\Application\Customer\Query\GetCustomer;

final readonly class GetCustomerInput
{
    public function __construct(public int $id)
    {
    }
}
