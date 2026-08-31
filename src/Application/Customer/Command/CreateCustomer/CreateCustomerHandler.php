<?php

declare(strict_types=1);

namespace App\Application\Customer\Command\CreateCustomer;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Customer\Customer;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;

final readonly class CreateCustomerHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(CreateCustomerInput $input): int
    {
        $this->validateInput($input);

        return $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($input): int {
                $phone = $this->normalizePhone($input->phone);

                if (
                    $phone !== null
                    && $this->customerRepository->existsByPhone($phone)
                ) {
                    throw new \DomainException(
                        'A customer with this phone already exists.',
                    );
                }

                $customer = new Customer(
                    name: $input->name,
                    phone: $phone,
                    note: $input->note,
                );

                $this->customerRepository->save($customer);
                $transaction->flush();

                $id = $customer->getId();

                if ($id === null) {
                    throw new \LogicException(
                        'Customer ID was not generated after flush.',
                    );
                }

                return $id;
            }
        );
    }

    private function validateInput(CreateCustomerInput $input): void
    {
        if (trim($input->name) === '') {
            throw new \InvalidArgumentException(
                'Customer name cannot be empty.',
            );
        }

        $phone = $this->normalizePhone($input->phone);

        if ($phone !== null && mb_strlen($phone) > 30) {
            throw new \InvalidArgumentException(
                'Customer phone cannot exceed 30 characters.',
            );
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $phone = trim($phone);

        return $phone === '' ? null : $phone;
    }
}
