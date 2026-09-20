<?php

declare(strict_types=1);

namespace App\Application\Customer\Query\GetCustomer;

use App\Application\Customer\Query\CustomerQueryRepositoryInterface;

final readonly class GetCustomerHandler
{
    public function __construct(private CustomerQueryRepositoryInterface $repository)
    {
    }

    public function __invoke(GetCustomerInput $input): ?CustomerDetailResult
    {
        if ($input->id <= 0) {
            throw new \InvalidArgumentException('Customer ID must be greater than zero.');
        }

        return $this->repository->findCustomerDetail($input->id);
    }
}
