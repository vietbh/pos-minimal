<?php

declare(strict_types=1);

namespace App\Application\Customer\Command\UpdateCustomer;

use App\Application\Common\Transaction\TransactionContextInterface;
use App\Application\Common\Transaction\TransactionManagerInterface;
use App\Domain\Customer\Repository\CustomerRepositoryInterface;

final readonly class UpdateCustomerHandler
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(UpdateCustomerInput $input): void
    {
        $this->validateInput($input);

        $this->transactionManager->run(
            function (TransactionContextInterface $transaction) use ($input): void {
                $customer = $this->customerRepository->findById(
                    $input->customerId,
                );

                if ($customer === null) {
                    throw new \DomainException(
                        sprintf(
                            'Customer %d was not found.',
                            $input->customerId,
                        ),
                    );
                }

                $phone = $this->normalizePhone($input->phone);

                if (
                    $phone !== null
                    && $this->customerRepository->existsByPhone(
                        $phone,
                        $input->customerId,
                    )
                ) {
                    throw new \DomainException(
                        'A customer with this phone already exists.',
                    );
                }

                $customer->rename($input->name);
                $customer->changePhone($phone);
                $customer->changeNote($input->note);

                $this->customerRepository->save($customer);
                $transaction->flush();
            },
        );
    }

    private function validateInput(UpdateCustomerInput $input): void
    {
        if ($input->customerId <= 0) {
            throw new \InvalidArgumentException(
                'Customer ID must be greater than zero.',
            );
        }

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
